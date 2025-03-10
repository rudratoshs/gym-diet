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
                            'maxOutputTokens' => 2500,
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
                Log::info('Raw response from Gemini:', ['content' => $content]);

                // Ensure the response is a string and remove Markdown JSON block if present
                $jsonStr = trim($content);
                if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $jsonStr, $matches)) {
                    $jsonStr = trim($matches[1]);
                }

                Log::info('Extracted JSON string:', ['json' => $jsonStr]);

                // Decode JSON and check for errors
                $mealData = json_decode($jsonStr, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::error('JSON parsing error:', [
                        'error' => json_last_error_msg(),
                        'content' => $jsonStr
                    ]);
                    throw new Exception('Failed to parse Gemini response as JSON: ' . json_last_error_msg());
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

        // Current season and weather (estimated based on location and current date)
        $season = $this->estimateCurrentSeason($country);
        $weather = $this->estimateCurrentWeather($country, $city);

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
            $prompt .= "\n- Current season: {$season}";
            $prompt .= "\n- Current weather: {$weather}";
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

        // Determine number of meals and meal types based on goal and meal timing
        list($mealCount, $mealTypes) = $this->determineMealsBasedOnGoal($primaryGoal, $transformedProfile, $assessmentLevel);

        // Complete the prompt with meal details request
        $prompt .= "

Include {$mealCount} meals: {$mealTypes}.

For each meal, provide:
1. meal_type (e.g., breakfast, lunch, dinner, etc.)
2. title (unique and descriptive)
3. description";

        // Adjust level of detail based on assessment type
        if ($assessmentLevel == 'quick') {
            $prompt .= "
4. calories
5. protein_grams
6. carbs_grams
7. fats_grams
8. time_of_day (e.g., 08:00)
9. recipe with ingredients (with EXACT measurements in grams for each ingredient) and simple instructions
10. preparation_time";
        } elseif ($assessmentLevel == 'moderate') {
            $prompt .= "
4. calories
5. protein_grams
6. carbs_grams
7. fats_grams
8. time_of_day (e.g., 08:00)
9. recipe with ingredients (with EXACT measurements in grams for each ingredient) and detailed instructions
10. preparation_time";
        } else { // comprehensive
            $prompt .= "
4. calories
5. protein_grams
6. carbs_grams
7. fats_grams
8. time_of_day (e.g., 08:00)
9. recipe with detailed ingredients (with EXACT measurements in grams) and step-by-step instructions
10. nutritional benefits
11. preparation_time
12. cooking_time";
        }

        // Add goal-specific instructions with enhanced personalization
        $prompt .= $this->getEnhancedGoalSpecificInstructions($primaryGoal, $transformedProfile, $exerciseRoutine ?? 'moderate');

        // Get regional preferences
        $region = trim("$city, $state, $country", ', '); // Combine non-empty values
        $regionStr = $region ? "- Regional cuisine preference: {$region}" : "";

        // Add detailed dietitian best practices
        $prompt .= $this->getDietitianBestPractices($primaryGoal, $season, $weather);

        // Final instructions with enhanced verification and requirements
        $prompt .= "

IMPORTANT REQUIREMENTS:
- Create ORIGINAL recipes that incorporate local cuisine and ingredients" . ($region ? " from $region" : "") . "
- Prioritize locally available and SEASONAL produce appropriate for {$season} in " . ($country ? $country : "your region") . "
- Each main meal MUST include a side salad component or vegetable preparation using regional vegetables
- Include at least one traditional " . ($country ? $country . "n" : "regional") . " protein-rich beverage or smoothie
- Balance traditional ingredients with nutrition science for {$primaryGoal}
- Use a VARIETY of protein sources (include at least 3-4 different sources throughout the day)
- Adapt recipes to the current {$weather} weather

MANDATORY VERIFICATION CHECKLIST:
- The total calories MUST add up to exactly {$dietPlan->daily_calories} calories (±20 calories)
- The total macronutrients MUST match the daily targets: {$dietPlan->protein_grams}g protein, {$dietPlan->carbs_grams}g carbs, {$dietPlan->fats_grams}g fats (±2g)
- All meals strictly respect the dietary restrictions and allergies: {$allergiesStr}
- Each recipe MUST have EXACT measurements in grams for EVERY ingredient
- Each recipe has a unique flavor profile (avoid repetition of taste profiles)
- Include EXACT preparation time estimates for each recipe";

        if ($assessmentLevel != 'quick') {
            $prompt .= " - Recipes are appropriate for the person's cooking capability: " . (isset($cookingCapability) ? $cookingCapability : 'basic') . " - The meal plan aligns with the person's primary goal: {$primaryGoal}";
            if (isset($cuisinePreferences) && $cuisinePreferences != 'no_preference') {
                $prompt .= " - Incorporate the preferred cuisines: {$cuisineStr}";
            }
        }

        $prompt .= "

Format your response as a valid JSON array of meal objects. Each meal should be a complete object with all the requested fields. DO NOT include any explanation, just the JSON array.";

        return $prompt;
    }
    /**
     * Determine number of meals and meal types based on goal and user preferences
     */
    protected function determineMealsBasedOnGoal($primaryGoal, $profile, $assessmentLevel)
    {
        // Default meal structure
        $mealCount = 3;
        $mealTypes = "'breakfast', 'lunch', 'dinner'";

        // Get the meal timing preference if available
        $mealTiming = $profile['meal_timing'] ?? 'traditional';

        // First check meal timing preference (if available in moderate or comprehensive assessment)
        if ($assessmentLevel != 'quick' && isset($profile['meal_timing'])) {
            if ($mealTiming == 'small_frequent' || $mealTiming == 'frequent') {
                $mealCount = 6;
                $mealTypes = "'breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner', 'evening_snack'";
            } elseif ($mealTiming == 'intermittent' || $mealTiming == 'intermittent_fasting') {
                $mealCount = 2;
                $mealTypes = "'lunch', 'dinner'";
            } elseif ($mealTiming == 'omad' || $mealTiming == 'one_meal') {
                $mealCount = 1;
                $mealTypes = "'dinner'";
            }
        }

        // Now adjust based on primary goal to optimize meal structure
        switch ($primaryGoal) {
            case 'muscle_gain':
                // For muscle gain, add pre/post workout meals if not already included
                if ($mealCount <= 3) {
                    $mealCount = 5;
                    $mealTypes = "'breakfast', 'lunch', 'pre_workout_snack', 'post_workout_shake', 'dinner'";
                } elseif ($mealCount > 3) {
                    // For people already doing more frequent meals, ensure pre/post workout are included
                    $mealTypes = str_replace("'afternoon_snack'", "'pre_workout_snack', 'post_workout_shake'", $mealTypes);
                }
                break;

            case 'weight_loss':
                // For weight loss, structure is important but fewer, larger meals can increase satiety
                if ($mealTiming == 'traditional') {
                    $mealCount = 4;
                    $mealTypes = "'breakfast', 'lunch', 'afternoon_snack', 'dinner'";
                }
                break;

            case 'energy':
                // For energy goals, frequent small meals help maintain stable blood sugar
                if ($mealCount < 4) {
                    $mealCount = 5;
                    $mealTypes = "'breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner'";
                }
                break;

            case 'health':
                // For general health, 3 balanced meals with optional snacks
                if ($mealTiming == 'traditional') {
                    $mealCount = 4;
                    $mealTypes = "'breakfast', 'lunch', 'afternoon_snack', 'dinner'";
                }
                break;
        }

        return [$mealCount, $mealTypes];
    }

    /**
     * Enhanced goal-specific instructions based on primary goal
     */
    protected function getEnhancedGoalSpecificInstructions($primaryGoal, $profile, $exerciseRoutine)
    {
        $workout = strpos($exerciseRoutine, 'minimal') !== false ? false : true;
        $instructions = "\n\nBased on the primary goal of '{$primaryGoal}', focus on:";

        switch ($primaryGoal) {
            case 'muscle_gain':
                $instructions .= "
- Higher protein intake across all meals (aim for 30g+ per main meal)
- Strategic nutrient timing (higher carbs before and after workouts)
- Balanced amino acid profile through complementary protein sources
- Include leucine-rich foods in each meal to stimulate muscle protein synthesis
- Sufficient complex carbohydrates for energy and recovery
- Healthy fats to support testosterone and overall hormone production
- Anti-inflammatory ingredients to support recovery and reduce soreness
- Include a slow-digesting protein source before bed for overnight recovery";
                break;

            case 'weight_loss':
                $instructions .= "
- High volume, low calorie density foods for satiety
- Higher protein intake (25-30% of calories) to preserve muscle mass
- Strategic carbohydrate timing (higher earlier in the day)
- Fiber-rich foods to promote fullness and improve gut health
- Include metabolism-supporting spices and ingredients
- Adequate healthy fats to support hormone function
- Hydrating foods with high water content
- Thermogenic ingredients like chili, ginger, and green tea";
                break;

            case 'maintain':
                $instructions .= "
- Balanced macronutrient distribution throughout the day
- Consistent meal timing to support metabolic homeostasis
- Focus on nutrient density rather than caloric manipulation
- Include a variety of whole foods from all food groups
- Adequate protein to maintain muscle mass
- Balanced carbohydrates for energy maintenance
- Healthy fats to support hormonal function
- Micronutrient-rich foods for long-term health maintenance";
                break;

            case 'energy':
                $instructions .= "
- Complex carbohydrates with low glycemic index for sustained energy
- Strategic protein distribution to maintain alertness
- Iron-rich foods to support oxygen transport
- B-vitamin rich ingredients for energy metabolism
- Magnesium-rich foods to support energy production
- Balanced meals to prevent blood sugar fluctuations
- Hydrating ingredients with electrolytes
- Adaptogenic herbs and spices for sustained energy";
                break;

            default: // health or other goals
                $instructions .= "
- Variety of colorful vegetables and fruits
- Anti-inflammatory foods and spices
- Heart-healthy fats and oils
- Balanced macronutrients
- Foods known to support immune function
- Fiber-rich whole grains and legumes
- Antioxidant-rich ingredients
- Probiotic and prebiotic foods for gut health";
                break;
        }

        // Add workout-specific instructions if applicable
        if ($workout && ($primaryGoal == 'muscle_gain' || $primaryGoal == 'weight_loss')) {
            $instructions .= "\n- Pre-workout nutrition: " . ($primaryGoal == 'muscle_gain' ? "Easily digestible carbs and moderate protein 1-2 hours before exercise" : "Light, easily digestible meal with focus on complex carbs 1-2 hours before exercise");
            $instructions .= "\n- Post-workout nutrition: " . ($primaryGoal == 'muscle_gain' ? "Fast-absorbing protein and carbs within 30-45 minutes after exercise" : "Balanced protein and moderate carbs within 45 minutes after exercise");
        }

        return $instructions;
    }

    /**
     * Add dietitian best practices tailored to goals and conditions
     */
    protected function getDietitianBestPractices($primaryGoal, $season, $weather)
    {
        $isWarm = strpos(strtolower($weather), 'warm') !== false || strpos(strtolower($weather), 'hot') !== false;

        $practices = "\n\nDietitian Best Practices to Include:";

        // Common best practices for all goals
        $practices .= "
- Meal timing optimized for the person's daily schedule and primary goal
- Proper hydration reminders throughout the day";

        // Add hydration recommendations based on weather
        if ($isWarm) {
            $practices .= " (minimum 3-4 liters)";
        } else {
            $practices .= " (minimum 2-3 liters)";
        }

        // Common practices continued
        $practices .= "
- Anti-inflammatory spices like turmeric, ginger and cumin
- Proper food pairing for maximum nutrient absorption (like vitamin C with plant iron sources)
- Balance of raw and cooked vegetables for optimal digestion
- Focus on fiber-rich whole foods for gut health (minimum 25g fiber per day)
- Include probiotic foods (like yogurt/curd) for digestive health";

        // Weather-specific practices
        if ($isWarm) {
            $practices .= "
- Cooling foods and spices appropriate for warm weather
- Strategic sodium and electrolyte levels for hydration in warm climate
- Lighter cooking methods to avoid heating the body";
        } else {
            $practices .= "
- Warming spices and foods appropriate for cooler weather
- Cooked foods to support digestion in cooler temperatures
- Hearty meals that provide sustained warmth and energy";
        }

        // Goal-specific practices
        switch ($primaryGoal) {
            case 'muscle_gain':
                $practices .= "
- Strategic protein distribution every 3-4 hours to maximize muscle protein synthesis
- Leucine threshold of minimum 2.5g per meal to trigger anabolism
- Post-workout window nutrition optimization
- Carbohydrate cycling based on workout intensity";
                break;

            case 'weight_loss':
                $practices .= "
- Caloric deficit structured for sustainable fat loss without muscle loss
- Volume eating strategies for satiety with lower calories
- Strategic meal timing to manage hunger hormones
- Protein-forward meals to preserve lean mass during deficit";
                break;

            case 'energy':
                $practices .= "
- Blood sugar management through balanced macronutrients
- Iron status optimization through diet
- B-vitamin rich foods to support energy metabolism
- Small, frequent meals to maintain energy levels";
                break;
        }

        return $practices;
    }

    /**
     * Estimate current season based on location
     */
    protected function estimateCurrentSeason($country)
    {
        $month = date('n'); // Current month (1-12)

        // Default to northern hemisphere seasons
        if ($month >= 3 && $month <= 5) {
            $season = 'Spring';
        } elseif ($month >= 6 && $month <= 8) {
            $season = 'Summer';
        } elseif ($month >= 9 && $month <= 11) {
            $season = 'Fall';
        } else {
            $season = 'Winter';
        }

        // Southern hemisphere countries have opposite seasons
        $southernHemisphereCountries = ['australia', 'new zealand', 'argentina', 'chile', 'south africa', 'brazil', 'uruguay', 'paraguay', 'bolivia'];

        if (in_array(strtolower($country), $southernHemisphereCountries)) {
            switch ($season) {
                case 'Spring':
                    $season = 'Fall';
                    break;
                case 'Summer':
                    $season = 'Winter';
                    break;
                case 'Fall':
                    $season = 'Spring';
                    break;
                case 'Winter':
                    $season = 'Summer';
                    break;
            }
        }

        // India has different seasons
        if (strtolower($country) == 'india') {
            if ($month >= 3 && $month <= 5) {
                $season = 'Summer';
            } elseif ($month >= 6 && $month <= 9) {
                $season = 'Monsoon';
            } elseif ($month >= 10 && $month <= 11) {
                $season = 'Post-Monsoon';
            } else {
                $season = 'Winter';
            }
        }

        return $season;
    }

    /**
     * Estimate current weather based on location and season
     */
    protected function estimateCurrentWeather($country, $city)
    {
        $season = $this->estimateCurrentSeason($country);
        $month = date('n');

        // Default weather patterns based on season
        $weatherMap = [
            'Spring' => 'Mild and variable',
            'Summer' => 'Warm and humid',
            'Fall' => 'Cool and breezy',
            'Winter' => 'Cold and dry',
            'Monsoon' => 'Warm and rainy',
            'Post-Monsoon' => 'Warm and gradually cooling'
        ];

        // Try to provide more specific weather for India
        if (strtolower($country) == 'india') {
            if (in_array(strtolower($city), ['delhi', 'jaipur', 'agra', 'chandigarh', 'lucknow'])) {
                $northIndia = [
                    'Winter' => 'Cold and dry',
                    'Summer' => 'Hot and dry',
                    'Monsoon' => 'Warm and humid',
                    'Post-Monsoon' => 'Pleasant and cooling'
                ];
                return $northIndia[$season] ?? $weatherMap[$season];
            } elseif (in_array(strtolower($city), ['mumbai', 'goa', 'pune', 'ahmedabad', 'indore'])) {
                $westIndia = [
                    'Winter' => 'Mild and dry',
                    'Summer' => 'Hot and dry',
                    'Monsoon' => 'Warm and very rainy',
                    'Post-Monsoon' => 'Warm and gradually cooling'
                ];
                return $westIndia[$season] ?? $weatherMap[$season];
            } elseif (in_array(strtolower($city), ['chennai', 'bangalore', 'hyderabad', 'kochi', 'mysore'])) {
                $southIndia = [
                    'Winter' => 'Pleasant and mild',
                    'Summer' => 'Hot and humid',
                    'Monsoon' => 'Warm and rainy',
                    'Post-Monsoon' => 'Warm and pleasant'
                ];
                return $southIndia[$season] ?? $weatherMap[$season];
            } elseif (in_array(strtolower($city), ['kolkata', 'bhubaneswar', 'patna', 'guwahati', 'siliguri'])) {
                $eastIndia = [
                    'Winter' => 'Cool and dry',
                    'Summer' => 'Hot and humid',
                    'Monsoon' => 'Warm and very rainy',
                    'Post-Monsoon' => 'Warm and humid'
                ];
                return $eastIndia[$season] ?? $weatherMap[$season];
            }
        }

        return $weatherMap[$season] ?? 'Moderate';
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