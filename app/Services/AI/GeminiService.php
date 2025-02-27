<?php
// app/Services/AI/GeminiService.php
namespace App\Services\AI;

use App\Models\DietPlan;
use App\Models\Meal;
use App\Models\MealPlan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
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
            $cooldown = Carbon::parse($usage['last_reset'])->addMinutes(1)->diffInSeconds(now());
            throw new Exception("Rate limit exceeded. Cooling down for {$cooldown} seconds.");
        }

        return $cacheKey;
    }

    /**
     * Generate meal plans using Gemini
     */
    public function generateMealPlans(DietPlan $dietPlan, array $responses, array $preferences): bool
    {
        $daysOfWeek = ['monday'];
        $profile = $preferences['profile'] ?? null;
        $success = true;

        foreach ($daysOfWeek as $day) {
            $mealPlan = MealPlan::create(['diet_plan_id' => $dietPlan->id, 'day_of_week' => $day]);

            try {
                $this->generateMealsForDay($mealPlan, $dietPlan, $profile, $responses, $preferences, $day);
            } catch (Exception $e) {
                Log::error('Error generating meals with Gemini', ['error' => $e->getMessage(), 'day' => $day, 'diet_plan_id' => $dietPlan->id]);
                $this->createDefaultMeals($mealPlan->id, $dietPlan);
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Generate meals for a specific day with retry mechanism
     */
    protected function generateMealsForDay($mealPlan, $dietPlan, $profile, $responses, $preferences, $day)
    {
        $attempts = 0;
        $maxRetries = 3;
        $backoff = 2;

        while ($attempts < $maxRetries) {
            try {
                $this->checkRateLimit(); // Ensure we're within limits
                $prompt = $this->formatMealPrompt($dietPlan, $profile, $preferences, $day);
                $endpoint = rtrim($this->apiUrl, '/') . "/models/{$this->model}:generateContent?key={$this->apiKey}";

                Log::info('Gemini API Request', ['url' => $endpoint, 'payload' => json_encode($prompt, JSON_PRETTY_PRINT)]);

                $response = Http::withHeaders(['Content-Type' => 'application/json'])->post($endpoint, [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.7, 'maxOutputTokens' => 1500]
                ]);

                Log::info('Gemini API Response', ['status' => $response->status(), 'body' => $response->json()]);

                if (!$response->successful()) {
                    if ($response->status() == 429) {
                        $cooldown = rand(5, 15);
                        Log::warning("Gemini API Rate Limit. Retrying in {$cooldown} seconds...");
                        sleep($cooldown);
                        continue;
                    }
                    throw new Exception('Gemini API error: ' . $response->body());
                }

                $result = $response->json();
                $content = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

                if (empty($content))
                    throw new Exception('Empty response from Gemini');

                if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
                    $jsonStr = $matches[1];
                } else {
                    $jsonStr = $content;
                }

                $mealData = json_decode($jsonStr, true);
                if (json_last_error() !== JSON_ERROR_NONE)
                    throw new Exception('JSON Parsing Error');

                foreach ($mealData as $meal) {
                    $this->createMeal($mealPlan->id, $meal);
                }

                return true;
            } catch (Exception $e) {
                Log::error("Meal generation attempt {$attempts} failed", ['error' => $e->getMessage()]);
                $attempts++;
                sleep($backoff); // Exponential backoff
                $backoff *= 2;
            }
        }

        throw new Exception('Max retries reached for Gemini API');
    }

    /**
     * Format prompt for Gemini
     */
    protected function formatMealPrompt($dietPlan, $profile, $preferences, $day)
    {
        $dietType = $profile->diet_type ?? 'balanced';
        $healthConditions = $preferences['health_conditions'] ?? ['none'];
        $allergies = $preferences['allergies'] ?? ['none'];

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
- Health conditions: " . implode(', ', $healthConditions) . "
- Allergies: " . implode(', ', $allergies) . "

Include 6 meals: breakfast, morning snack, lunch, afternoon snack, dinner, and evening snack.

For each meal, provide:
1. meal_type (e.g., breakfast, morning_snack, lunch, etc.)
2. title
3. description
4. calories
5. protein_grams
6. carbs_grams
7. fats_grams
8. time_of_day (e.g., 08:00)
9. recipe with ingredients and instructions

Format your response as a valid JSON array of meal objects. DO NOT include any explanation, just the JSON array.";
    }
    /**
     * Create or update meal from data
     */
    protected function createMeal($mealPlanId, $mealData)
    {
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
                'recipes' => json_encode($mealData['recipe']), // Ensure recipe is stored as JSON
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
                'recipes' => json_encode($mealData['recipe']), // Ensure recipe is stored as JSON
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
            $this->checkRateLimit();
            $endpoint = "{$this->apiUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";
            Log::info('Testing Gemini connection', ['url' => $endpoint]);

            $response = Http::withHeaders(['Content-Type' => 'application/json'])->post($endpoint, [
                'contents' => [['parts' => [['text' => 'Hello, respond with "Connection successful".']]]],
                'generationConfig' => ['maxOutputTokens' => 50],
            ]);

            return $response->successful();
        } catch (Exception $e) {
            Log::error('Gemini connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}