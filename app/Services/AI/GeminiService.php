<?php
// app/Services/AI/GeminiService.php
namespace App\Services\AI;

use App\Models\DietPlan;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Jobs\GenerateMealPlanForDay;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;
use App\Services\OptionMappingService;
use Exception;

class GeminiService extends BaseAIService
{
    protected $apiKey;
    protected $apiUrl;
    protected $model;
    protected $rateLimit;
    protected $tokenLimit;

    public function __construct($config = null)
    {
        parent::__construct($config);

        // Set default values if no configuration provided
        $this->apiKey = $config->api_key ?? config('services.gemini.api_key');
        $this->apiUrl = $config->api_url ?? config('services.gemini.api_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->model = $config->model ?? config('services.gemini.model', 'gemini-1.5-pro');

        // Rate Limits (from Gemini Docs)
        $this->rateLimit = ($this->model === 'gemini-1.5-flash') ? 100 : 50;  // RPM (Requests per Minute)
        $this->tokenLimit = ($this->model === 'gemini-1.5-flash') ? 1000000 : 500000; // TPM (Tokens per Minute)
    }

    /**
     * Generate meal plans using Gemini - modified to handle single day case
     */
    public function generateMealPlans(DietPlan $dietPlan, array $responses, array $preferences): bool
    {
        // Check if we're generating for a specific day only
        $specificDay = $preferences['day'] ?? null;
        $specificMealPlanId = $preferences['meal_plan_id'] ?? null;

        if ($specificDay && $specificMealPlanId) {
            // Single day generation case (from job)
            $mealPlan = MealPlan::findOrFail($specificMealPlanId);

            try {
                $this->generateMealsForDay($mealPlan, $dietPlan, $preferences['profile'], $responses, $preferences, $specificDay);
                Log::info("Meal plan generated for {$specificDay}", ['diet_plan_id' => $dietPlan->id]);
                return true;
            } catch (Exception $e) {
                Log::error("Error generating meal plan for {$specificDay}", ['error' => $e->getMessage(), 'diet_plan_id' => $dietPlan->id]);
                $this->createDefaultMeals($mealPlan->id, $dietPlan);
                return false;
            }
        }

        // First, create meal plan for Monday immediately for instant feedback
        $monday = 'monday';
        $mealPlan = MealPlan::create([
            'diet_plan_id' => $dietPlan->id,
            'day_of_week' => $monday,
            'generation_status' => 'in_progress'
        ]);

        try {
            $this->generateMealsForDay($mealPlan, $dietPlan, $preferences['profile'], $responses, $preferences, $monday);
            $mealPlan->generation_status = 'completed';
            $mealPlan->calculateTotals();
            $mealPlan->save();
            Log::info('Monday meal plan generated immediately', ['diet_plan_id' => $dietPlan->id]);
        } catch (Exception $e) {
            Log::error('Error generating Monday meal plan', ['error' => $e->getMessage(), 'diet_plan_id' => $dietPlan->id]);
            $this->createDefaultMeals($mealPlan->id, $dietPlan);
            $mealPlan->generation_status = 'failed';
            $mealPlan->save();
        }

        // Queue the generation of the remaining days with progressive delays
        $remainingDays = ['tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($remainingDays as $index => $day) {
            // Create the meal plan record immediately, we'll populate meals later
            $newMealPlan = MealPlan::create([
                'diet_plan_id' => $dietPlan->id,
                'day_of_week' => $day,
                'generation_status' => 'pending'
            ]);

            // Default meals as placeholders
            $this->createDefaultMeals($newMealPlan->id, $dietPlan);

            // Queue the job with increasing delay for each day
            // This spreads the load and respects API rate limits
            $delay = ($index + 1) * 60; // 1 minute, 2 minutes, etc.

            Queue::later(
                now()->addSeconds($delay),
                new GenerateMealPlanForDay(
                    $newMealPlan->id,
                    $dietPlan->id,
                    $preferences['profile']->id,
                    $responses,
                    $preferences,
                    $day
                )
            );

            Log::info("Queued generation for $day", [
                'diet_plan_id' => $dietPlan->id,
                'meal_plan_id' => $newMealPlan->id,
                'delay' => $delay
            ]);
        }

        return true;
    }
    /**
     * Generate meals for a specific day
     * Can be called directly or from a queued job
     */
    public function generateMealsForDay($mealPlan, $dietPlan, $profile, $responses, $preferences, $day)
    {
        $attempts = 0;
        $maxRetries = 3;
        $backoff = 2;

        while ($attempts < $maxRetries) {
            try {
                $cacheKey = $this->checkRateLimit();

                $prompt = $this->formatMealPrompt($dietPlan, $profile, $preferences, $day);
                Log::info('promts for the gemini' . $prompt);
                $endpoint = rtrim($this->apiUrl, '/') . "/models/{$this->model}:generateContent?key={$this->apiKey}";

                // Make the API call
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->post($endpoint, [
                        'contents' => [['parts' => [['text' => $prompt]]]],
                        'generationConfig' => [
                            'temperature' => 0.7,
                            'maxOutputTokens' => 1500,
                            'topP' => 0.95,
                            'topK' => 40
                        ]
                    ]);

                // Update rate limit counter
                $usage = Cache::get($cacheKey, ['requests' => 0, 'tokens' => 0, 'last_reset' => now()]);
                $usage['requests']++;
                // Estimate token count: 4 chars ~= 1 token for input + roughly estimate output
                $usage['tokens'] += (int) (strlen($prompt) / 4) + 1500;
                Cache::put($cacheKey, $usage, 60);

                if (!$response->successful()) {
                    if ($response->status() == 429) {
                        // Rate limited, calculate cooldown time
                        $cooldown = rand(30, 120); // Random cooldown between 30s and 2m
                        Log::warning("Gemini API Rate Limit. Retrying in {$cooldown} seconds...");
                        sleep($cooldown);
                        continue;
                    }
                    throw new Exception('Gemini API error: ' . $response->body());
                }

                $result = $response->json();
                $content = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

                if (empty($content)) {
                    throw new Exception('Empty response from Gemini');
                }

                // Parse JSON from response
                if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
                    $jsonStr = $matches[1];
                } else {
                    $jsonStr = $content;
                }

                $mealData = json_decode($jsonStr, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error('JSON parsing error', [
                        'error' => json_last_error_msg(),
                        'content' => $content
                    ]);
                    throw new Exception('Failed to parse Gemini response as JSON');
                }

                // Clear existing meals if any (for re-generation)
                if ($mealPlan->meals()->count() > 0) {
                    $mealPlan->meals()->delete();
                }

                // Create meals from parsed data
                foreach ($mealData as $meal) {
                    $this->createMeal($mealPlan->id, $meal);
                }

                // Update meal plan with nutritional totals
                $this->updateMealPlanTotals($mealPlan->id);

                return true;
            } catch (Exception $e) {
                Log::error("Meal generation attempt {$attempts} failed", [
                    'error' => $e->getMessage(),
                    'day' => $day,
                    'diet_plan_id' => $dietPlan->id
                ]);

                $attempts++;

                if ($attempts >= $maxRetries) {
                    // After all retries, ensure we have something to show
                    if ($mealPlan->meals()->count() == 0) {
                        $this->createDefaultMeals($mealPlan->id, $dietPlan);
                    }

                    throw new Exception('Max retries reached for Gemini API: ' . $e->getMessage());
                }

                sleep($backoff); // Exponential backoff
                $backoff *= 2;
            }
        }
    }

    /**
     * Check API Rate Limits and Wait if Needed
     */
    protected function checkRateLimit()
    {
        $cacheKey = "gemini_api_usage";
        $usage = Cache::get($cacheKey, ['requests' => 0, 'tokens' => 0, 'last_reset' => now()]);

        // If minute has passed, reset counters
        if (Carbon::parse($usage['last_reset'])->diffInMinutes(now()) >= 1) {
            $usage = ['requests' => 0, 'tokens' => 0, 'last_reset' => now()];
            Cache::put($cacheKey, $usage, 60);
        }

        // Calculate cooldown if limits exceeded
        if ($usage['requests'] >= $this->rateLimit || $usage['tokens'] >= $this->tokenLimit) {
            $cooldown = 60 - Carbon::parse($usage['last_reset'])->diffInSeconds(now());

            // If cooldown is very short, just wait
            if ($cooldown <= 5) {
                sleep($cooldown);
                return $this->checkRateLimit(); // Recursive call after waiting
            }

            throw new Exception("Rate limit exceeded. Cooling down for {$cooldown} seconds.");
        }

        return $cacheKey;
    }

    /**
     * Update meal plan nutritional totals
     */
    private function updateMealPlanTotals($mealPlanId)
    {
        $mealPlan = MealPlan::findOrFail($mealPlanId);
        $totals = [
            'calories' => 0,
            'protein' => 0,
            'carbs' => 0,
            'fats' => 0
        ];

        $meals = $mealPlan->meals;

        foreach ($meals as $meal) {
            $totals['calories'] += $meal->calories;
            $totals['protein'] += $meal->protein_grams;
            $totals['carbs'] += $meal->carbs_grams;
            $totals['fats'] += $meal->fats_grams;
        }

        // Store totals in meal plan metadata
        $mealPlan->total_calories = $totals['calories'];
        $mealPlan->total_protein = $totals['protein'];
        $mealPlan->total_carbs = $totals['carbs'];
        $mealPlan->total_fats = $totals['fats'];
        $mealPlan->save();
    }

    /**
     * Format prompt for Gemini
     */
    protected function formatMealPrompt($dietPlan, $profile, $preferences, $day, $assessmentLevel = 'moderate')
    {
        // Log raw inputs for debugging
        Log::info('Raw meal prompt inputs', [
            'diet_plan' => $dietPlan->toArray(),
            'profile_raw' => $profile->getAttributes(),
            'preferences' => $preferences,
            'day' => $day,
            'assessment_level' => $assessmentLevel
        ]);

        // Transform the profile data to replace IDs with labels
        $transformedProfile = OptionMappingService::transformData($profile->getAttributes());

        // Transform preference data if it's different from profile data
        $transformedPreferences = $preferences;
        if (isset($preferences['profile'])) {
            unset($transformedPreferences['profile']); // Remove the profile to avoid duplication
        }
        $transformedPreferences = OptionMappingService::transformData($transformedPreferences);

        // Log transformed data
        Log::info('Transformed data for prompt', [
            'transformed_profile' => $transformedProfile,
            'transformed_preferences' => $transformedPreferences
        ]);

        // Core user information (available at all assessment levels)
        $age = $transformedProfile['age'] ?? 30;
        $gender = $transformedProfile['gender'] ?? 'not_specified';
        $currentWeight = $transformedProfile['current_weight'] ?? 70;
        $height = $transformedProfile['height'] ?? 170;
        $targetWeight = $transformedProfile['target_weight'] ?? $currentWeight;
        $activityLevel = $transformedProfile['activity_level'] ?? 'moderate';

        // Diet type and allergies (available at all assessment levels)
        $dietType = $transformedProfile['diet_type'] ?? 'balanced';

        // Handle allergies (could be array or comma-separated string)
        $allergies = $transformedProfile['allergies'] ?? ['none'];
        $allergiesStr = is_array($allergies) ? implode(', ', $allergies) : $allergies;

        // Goal information (available at all assessment levels)
        $primaryGoal = $transformedProfile['primary_goal'] ?? 'health';

        // Geographic data (important for regional cuisine preferences)
        $country = $transformedProfile['country'] ?? '';
        $state = $transformedProfile['state'] ?? '';
        $city = $transformedProfile['city'] ?? '';

        // Format base prompt with information available at all levels
        $prompt = "Create a detailed meal plan for {$day} for a person with the following characteristics:
- Age: {$age}
- Gender: {$gender}
- Current weight: {$currentWeight} kg
- Target weight: {$targetWeight} kg
- Height: {$height} cm
- Activity level: {$activityLevel}
- Diet type: {$dietType}
- Primary goal: {$primaryGoal}
- Daily calorie target: {$dietPlan->daily_calories} calories
- Protein: {$dietPlan->protein_grams}g
- Carbs: {$dietPlan->carbs_grams}g
- Fats: {$dietPlan->fats_grams}g
- Allergies/Intolerances: {$allergiesStr}";

        // Add location information if available
        if (!empty($country)) {
            $location = trim("$city, $state, $country", ', ');
            $prompt .= "\n- Location: {$location}";
        }

        // Moderate assessment adds more health and preference data
        if ($assessmentLevel == 'moderate' || $assessmentLevel == 'comprehensive') {
            // Health conditions
            $healthConditions = $transformedProfile['health_conditions'] ?? ['none'];
            $healthConditionsStr = is_array($healthConditions) ? implode(', ', $healthConditions) : $healthConditions;

            // Food preferences
            $foodRestrictions = $transformedProfile['food_restrictions'] ?? ['none'];
            $foodRestrictionsStr = is_array($foodRestrictions) ? implode(', ', $foodRestrictions) : $foodRestrictions;

            // Exercise pattern
            $exerciseRoutine = $transformedProfile['exercise_routine'] ?? 'moderate';

            // Timeline
            $timeline = $transformedProfile['goal_timeline'] ?? 'medium-term';

            $prompt .= "
- Health conditions: {$healthConditionsStr}
- Food restrictions: {$foodRestrictionsStr}
- Exercise routine: {$exerciseRoutine}
- Timeline for goals: {$timeline}";
        }

        // Comprehensive assessment adds even more detailed information
        if ($assessmentLevel == 'comprehensive') {
            // Cuisine preferences
            $cuisinePreferences = $transformedProfile['cuisine_preferences'] ?? ['no_preference'];
            $cuisineStr = is_array($cuisinePreferences) ? implode(', ', $cuisinePreferences) : $cuisinePreferences;

            // Meal timing
            $mealTiming = $transformedProfile['meal_timing'] ?? 'traditional';

            // Lifestyle factors
            $dailySchedule = $transformedProfile['daily_schedule'] ?? 'standard';
            $cookingCapability = $transformedProfile['cooking_capability'] ?? 'basic';
            $stressSleep = $transformedProfile['stress_sleep'] ?? 'moderate';

            // Recovery needs if applicable
            $recoveryNeeds = $transformedProfile['recovery_needs'] ?? ['none'];
            $recoveryNeedsStr = is_array($recoveryNeeds) ? implode(', ', $recoveryNeeds) : $recoveryNeeds;

            $prompt .= "
- Cuisine preferences: {$cuisineStr}
- Meal timing preference: {$mealTiming}
- Daily schedule: {$dailySchedule}
- Cooking capability: {$cookingCapability}
- Stress & sleep pattern: {$stressSleep}
- Recovery needs: {$recoveryNeedsStr}";

            // Add any additional detailed requests
            if (isset($transformedProfile['health_details']) && !empty($transformedProfile['health_details'])) {
                $prompt .= "\n- Health details: {$transformedProfile['health_details']}";
            }
        }

        // Determine number of meals based on meal timing preference
        $mealCount = 3; // Default
        $mealTypes = "'breakfast', 'lunch', 'dinner'";

        // Only change meal count if we have timing info (moderate or comprehensive)
        if ($assessmentLevel != 'quick' && isset($transformedProfile['meal_timing'])) {
            $timing = $transformedProfile['meal_timing'];

            if ($timing == 'small_frequent' || $timing == 'frequent') {
                $mealCount = 6;
                $mealTypes = "'breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner', 'evening_snack'";
            } elseif ($timing == 'intermittent' || $timing == 'intermittent_fasting') {
                $mealCount = 2;
                $mealTypes = "'lunch', 'dinner'";
            } elseif ($timing == 'omad' || $timing == 'one_meal') {
                $mealCount = 1;
                $mealTypes = "'dinner'";
            }
        }

        // Complete the prompt with meal details request
        $prompt .= "

Include {$mealCount} meals: {$mealTypes}.

For each meal, provide:
1. meal_type (e.g., breakfast, lunch, dinner, etc.)
2. title
3. description";


        // Adjust level of detail based on assessment type
        if ($assessmentLevel == 'quick') {
            $prompt .= "
4. calories
5. protein_grams
6. carbs_grams
7. fats_grams
8. time_of_day (e.g., 08:00)
9. recipe with basic ingredients list";
        } elseif ($assessmentLevel == 'moderate') {
            $prompt .= "
4. calories
5. protein_grams
6. carbs_grams
7. fats_grams
8. time_of_day (e.g., 08:00)
9. recipe with ingredients and simple instructions";
        } else { // comprehensive
            $prompt .= "
4. calories
5. protein_grams
6. carbs_grams
7. fats_grams
8. time_of_day (e.g., 08:00)
9. recipe with detailed ingredients (with measurements) and step-by-step instructions
10. nutritional benefits
11. prep time and cooking time";
        }

        // Add goal-specific instructions
        $prompt .= $this->getGoalSpecificInstructions($primaryGoal);
        $region = trim("$city, $state, $country", ', '); // Combine non-empty values
        $regionStr = $region ? "- Regional cuisine preference: {$region}" : "";

        // Final instructions
        $prompt .= "

Make sure:
- The total calories for all meals add up to approximately {$dietPlan->daily_calories} calories for the day
- The total macronutrients approximately match the daily targets: {$dietPlan->protein_grams}g protein, {$dietPlan->carbs_grams}g carbs, {$dietPlan->fats_grams}g fats
- All meals respect the dietary restrictions and allergies";

        if ($assessmentLevel != 'quick') {
            $prompt .= "
- Recipes are appropriate for the person's cooking capability
- The meal plan aligns with the person's primary goal: {$primaryGoal}";

            if (isset($cuisinePreferences) && $cuisinePreferences != 'no_preference') {
                $prompt .= "
- Incorporate the preferred cuisines: {$cuisineStr}";
            }
        }

        if ($assessmentLevel == 'comprehensive') {
            $prompt .= "
- Consider the person's stress and sleep patterns when recommending evening meals
- Take into account the person's cooking time availability
- Provide realistic recipes that match the person's commitment level
- Include variety according to the person's preference";

            if ($regionStr) {
                $prompt .= "
- Incorporate regional cuisine preferences where possible";
            }
        }

        $prompt .= "

Format your response as a valid JSON array of meal objects. Each meal should be a complete object with all the requested fields. DO NOT include any explanation, just the JSON array.";

        return $prompt;
    }


    /**
     * Get goal-specific instructions for the meal plan
     */
    private function getGoalSpecificInstructions($primaryGoal)
    {
        $instructions = "\n\nBased on the primary goal of '{$primaryGoal}', focus on:";

        switch (strtolower($primaryGoal)) {
            case 'weight_loss':
            case 'weight loss':
                $instructions .= "
- Foods with high satiety but lower calorie density
- Higher protein content to preserve muscle mass
- Complex carbohydrates over simple sugars
- Balanced meals that prevent hunger and cravings
- Adequate fiber to promote fullness";
                break;

            case 'muscle_gain':
            case 'muscle gain':
                $instructions .= "
- Higher protein intake across all meals
- Nutrient timing (especially around workouts)
- Sufficient complex carbohydrates for energy and recovery
- Healthy fats to support hormone production
- Nutrient-dense foods for overall recovery";
                break;

            case 'maintain':
            case 'maintain weight':
                $instructions .= "
- Balanced macronutrient distribution
- Focus on whole, unprocessed foods
- Stable meal timing and portion sizes
- Nutrient density for overall health
- Sustainable and enjoyable food choices";
                break;

            case 'energy':
            case 'better energy':
            case 'energy improvement':
                $instructions .= "
- Complex carbohydrates for sustained energy
- Regular protein intake throughout the day
- Anti-inflammatory foods
- Foods rich in B vitamins, iron, and magnesium
- Proper hydration recommendations with each meal";
                break;

            case 'health':
            case 'improved health':
            case 'overall health':
                $instructions .= "
- Variety of colorful vegetables and fruits
- Anti-inflammatory foods
- Heart-healthy fats and oils
- Balanced macronutrients
- Foods known to support immune function";
                break;

            case 'athletic performance':
            case 'performance':
                $instructions .= "
- Properly timed pre-workout nutrition
- Post-workout recovery meals
- Adequate carbohydrates for performance
- Protein distribution throughout the day
- Foods that support endurance and strength";
                break;

            case 'recovery':
            case 'recovery from condition':
                $instructions .= "
- Anti-inflammatory foods
- Nutrient-dense healing foods
- Easy-to-digest options when appropriate
- Foods that support the specific recovery needs mentioned
- Adequate protein for tissue repair";
                break;

            default:
                $instructions .= "
- Balance of macronutrients
- Whole food sources
- Nutrient density
- Dietary adherence through enjoyable meals
- Overall health promotion";
        }

        return $instructions;
    }


    /**
     * Create or update meal from data
     */
    protected function createMeal($mealPlanId, $mealData)
    {
        // Validate required fields
        $requiredFields = ['meal_type', 'title', 'description', 'calories', 'protein_grams', 'carbs_grams', 'fats_grams', 'time_of_day'];
        foreach ($requiredFields as $field) {
            if (!isset($mealData[$field])) {
                Log::warning("Missing required field '$field' in meal data", ['meal_plan_id' => $mealPlanId]);
                $mealData[$field] = $field === 'meal_type' ? 'other' : ($field === 'time_of_day' ? '12:00' : 0);
            }
        }

        // Check if the meal already exists
        $existingMeal = Meal::where('meal_plan_id', $mealPlanId)
            ->where('meal_type', $mealData['meal_type'])
            ->first();

        if ($existingMeal) {
            // Log that we're updating the meal instead of skipping
            Log::info('Updating existing meal', ['meal_plan_id' => $mealPlanId, 'meal_type' => $mealData['meal_type']]);

            // Update existing meal with new details
            $existingMeal->update([
                'title' => $mealData['title'],
                'description' => $mealData['description'],
                'calories' => $mealData['calories'],
                'protein_grams' => $mealData['protein_grams'],
                'carbs_grams' => $mealData['carbs_grams'],
                'fats_grams' => $mealData['fats_grams'],
                'time_of_day' => $mealData['time_of_day'],
                'recipes' => isset($mealData['recipe']) ? json_encode($mealData['recipe']) : null
            ]);

            Log::info('Meal successfully updated', ['meal_plan_id' => $mealPlanId, 'meal_type' => $mealData['meal_type']]);
        } else {
            // Insert new meal if it does not exist
            Meal::create([
                'meal_plan_id' => $mealPlanId,
                'meal_type' => $mealData['meal_type'],
                'title' => $mealData['title'],
                'description' => $mealData['description'],
                'calories' => $mealData['calories'],
                'protein_grams' => $mealData['protein_grams'],
                'carbs_grams' => $mealData['carbs_grams'],
                'fats_grams' => $mealData['fats_grams'],
                'time_of_day' => $mealData['time_of_day'],
                'recipes' => isset($mealData['recipe']) ? json_encode($mealData['recipe']) : null
            ]);

            Log::info('Meal successfully created', ['meal_plan_id' => $mealPlanId, 'meal_type' => $mealData['meal_type']]);
        }
    }

    /**
     * Test API connection
     */
    public function testConnection(): bool
    {
        try {
            $cacheKey = $this->checkRateLimit();
            $endpoint = "{$this->apiUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";
            Log::info('Testing Gemini connection', ['url' => $endpoint]);

            $response = Http::withHeaders(['Content-Type' => 'application/json'])->post($endpoint, [
                'contents' => [['parts' => [['text' => 'Hello, respond with "Connection successful".']]]],
                'generationConfig' => ['maxOutputTokens' => 50],
            ]);

            // Update usage even for test
            $usage = Cache::get($cacheKey, ['requests' => 0, 'tokens' => 0, 'last_reset' => now()]);
            $usage['requests']++;
            $usage['tokens'] += 50; // Minimal token usage for test
            Cache::put($cacheKey, $usage, 60);

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Gemini connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}