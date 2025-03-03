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
                Log::info('promts for the gemini'.$prompt);
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
    protected function formatMealPrompt($dietPlan, $profile, $preferences, $day)
    {
        // Get diet type and health conditions from profile or preferences
        $dietType = $profile->diet_type ?? 'balanced';
        $healthConditions = $preferences['health_conditions'] ?? ['none'];
        $allergies = $preferences['allergies'] ?? ['none'];
        $cuisinePreferences = $preferences['cuisine_preferences'] ?? ['no_preference'];

        // Additional preferences
        $mealTiming = $profile->meal_timing ?? 'traditional';
        $cookingCapability = $preferences['cooking_capability'] ?? 'basic';

        // Format health conditions and allergies for prompt
        $healthConditionsStr = is_array($healthConditions) ? implode(', ', $healthConditions) : $healthConditions;
        $allergiesStr = is_array($allergies) ? implode(', ', $allergies) : $allergies;
        $cuisineStr = is_array($cuisinePreferences) ? implode(', ', $cuisinePreferences) : $cuisinePreferences;

        // Determine number of meals based on meal timing preference
        $mealCount = 3; // Default
        $mealTypes = "'breakfast', 'lunch', 'dinner'";

        if ($mealTiming == 'frequent') {
            $mealCount = 6;
            $mealTypes = "'breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner', 'evening_snack'";
        } elseif ($mealTiming == 'intermittent') {
            $mealCount = 2;
            $mealTypes = "'lunch', 'dinner'";
        } elseif ($mealTiming == 'omad') {
            $mealCount = 1;
            $mealTypes = "'dinner'";
        }

        // Build the prompt
        return "Create a detailed meal plan for {$day} for a person with the following characteristics:
- Age: {$profile->age}
- Gender: {$profile->gender}
- Current weight: {$profile->current_weight} kg
- Height: {$profile->height} cm
- Activity level: {$profile->activity_level}
- Diet type: {$dietType}
- Daily calorie target: {$dietPlan->daily_calories} calories
- Protein: {$dietPlan->protein_grams}g
- Carbs: {$dietPlan->carbs_grams}g
- Fats: {$dietPlan->fats_grams}g
- Health conditions: {$healthConditionsStr}
- Allergies: {$allergiesStr}
- Cuisine preferences: {$cuisineStr}
- Cooking capability: {$cookingCapability}

Include {$mealCount} meals: {$mealTypes}.

For each meal, provide:
1. meal_type (e.g., breakfast, lunch, dinner, etc.)
2. title
3. description
4. calories
5. protein_grams
6. carbs_grams
7. fats_grams
8. time_of_day (e.g., 08:00)
9. recipe with ingredients and instructions

Make sure:
- The total calories are approximately {$dietPlan->daily_calories} calories for the day
- The total macronutrients approximately match the daily targets
- All meals respect the dietary restrictions and allergies
- Recipes are appropriate for the person's cooking capability
- Preferred cuisines are incorporated

Format your response as a valid JSON array of meal objects. DO NOT include any explanation, just the JSON array.";
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