<?php
// app/Services/AI/OpenAIService.php
namespace App\Services\AI;

use App\Models\DietPlan;
use App\Models\Meal;
use App\Models\MealPlan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ClientProfile;

class OpenAIService extends BaseAIService
{
    protected $apiKey;
    protected $apiUrl;
    protected $model;

    public function __construct($config = null)
    {
        parent::__construct($config);

        $this->apiKey = $config->api_key ?? config('services.openai.api_key');
        $this->apiUrl = $config->api_url ?? config('services.openai.api_url', 'https://api.openai.com/v1/chat/completions');
        $this->model = $config->model ?? config('services.openai.model', 'gpt-4o');
    }

    /**
     * Generate meal plans using OpenAI
     */
    public function generateMealPlans(DietPlan $dietPlan, array $responses, array $preferences): bool
    {
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $profile = $preferences['profile'] ?? null;

        $success = true;

        foreach ($daysOfWeek as $day) {
            $mealPlan = new MealPlan();
            $mealPlan->diet_plan_id = $dietPlan->id;
            $mealPlan->day_of_week = $day;
            $mealPlan->save();

            try {
                $this->generateMealsForDay($mealPlan, $dietPlan, $profile, $responses, $preferences, $day);
            } catch (\Exception $e) {
                Log::error('Error generating meals with Gemini', [
                    'error' => $e->getMessage(),
                    'day' => $day,
                    'diet_plan_id' => $dietPlan->id
                ]);

                // Create default meals as fallback
                $this->createDefaultMeals($mealPlan->id, $dietPlan);
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Test API connection
     */
    public function testConnection(): bool
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                        'model' => $this->model,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'You are a helpful assistant.'
                            ],
                            [
                                'role' => 'user',
                                'content' => 'Hello, please respond with "Connection successful" if you can read this.'
                            ]
                        ],
                        'max_tokens' => 50
                    ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('OpenAI connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
    /**
     * Generate meals for a meal plan
     */
    private function generateMeals(MealPlan $mealPlan, DietPlan $dietPlan, ClientProfile $profile, array $responses, string $day)
    {
        // Format the prompt for OpenAI
        $prompt = $this->formatMealPrompt($dietPlan, $profile, $responses, $day);

        try {
            // Call OpenAI API
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                        'model' => 'gpt-4o',
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'You are a professional dietitian creating personalized meal plans. Respond with a detailed meal plan including exactly 6 meals with recipes, calories, and macros in JSON format.'
                            ],
                            [
                                'role' => 'user',
                                'content' => $prompt
                            ]
                        ],
                        'temperature' => 0.7,
                        'max_tokens' => 1500
                    ]);

            if ($response->successful()) {
                $result = $response->json();
                $mealData = $this->parseMealPlanResponse($result);

                if (!empty($mealData)) {
                    foreach ($mealData as $meal) {
                        $this->createMeal($mealPlan->id, $meal);
                    }
                } else {
                    // Fallback to predefined meals if parsing fails
                    $this->createDefaultMeals($mealPlan->id, $dietPlan);
                }
            } else {
                Log::error('OpenAI API error', ['response' => $response->body()]);
                // Fallback to predefined meals
                $this->createDefaultMeals($mealPlan->id, $dietPlan);
            }
        } catch (\Exception $e) {
            Log::error('Exception in AI meal generation', ['error' => $e->getMessage()]);
            // Fallback to predefined meals
            $this->createDefaultMeals($mealPlan->id, $dietPlan);
        }
    }

    /**
     * Format prompt for meal plan generation
     */
    private function formatMealPrompt(DietPlan $dietPlan, ClientProfile $profile, array $responses, string $day)
    {
        $dietType = $profile->diet_type ?? 'balanced';
        $cuisinePreferences = $responses['regional_preferences'] ?? ['general'];
        $allergies = $profile->allergies ?? ['none'];
        $healthConditions = $profile->health_conditions ?? ['none'];

        $prompt = "Create a detailed meal plan for {$day} for a person with the following characteristics:
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
- Cuisine preferences: " . implode(', ', $cuisinePreferences) . "
- Allergies: " . implode(', ', $allergies) . "
- Health conditions: " . implode(', ', $healthConditions) . "

Include 6 meals: breakfast, morning snack, lunch, afternoon snack, dinner, and evening snack.

For each meal, provide:
1. Title
2. Description
3. Calories
4. Protein (g)
5. Carbs (g)
6. Fats (g)
7. Time of day (e.g., 08:00)
8. Recipe with ingredients and instructions

Format your response as a valid JSON array of meal objects.";

        return $prompt;
    }

    /**
     * Parse the AI response for meal plan
     */
    private function parseMealPlanResponse($result)
    {
        try {
            $content = $result['choices'][0]['message']['content'] ?? '';

            // Extract JSON from response (it might be wrapped in markdown code blocks)
            if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
                $jsonStr = $matches[1];
            } else {
                $jsonStr = $content;
            }

            $mealData = json_decode($jsonStr, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('JSON parsing error', ['error' => json_last_error_msg(), 'content' => $content]);
                return [];
            }

            return $mealData;
        } catch (\Exception $e) {
            Log::error('Exception in parsing meal response', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Create a meal from parsed data
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
     * Generate meals for a specific day
     */
    public function generateMealsForDay($mealPlan, $dietPlan, $profile, $responses, $preferences, $day)
    {
        // Format the prompt
        $prompt = $this->formatMealPrompt($dietPlan, $profile, $preferences, $day);

        // Call Gemini API
        $endpoint = "{$this->apiUrl}/models/{$this->model}:generateContent";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post("{$endpoint}?key={$this->apiKey}", [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 1500,
                    ]
                ]);

        if (!$response->successful()) {
            Log::error('Open API error', ['response' => $response->body()]);
            throw new \Exception('Open API error: ' . $response->body());
        }

        // Parse the result
        $result = $response->json();
        $content = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

        if (empty($content)) {
            throw new \Exception('Empty response from Open');
        }

        // Extract JSON from response (it might be wrapped in markdown code blocks)
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $content, $matches)) {
            $jsonStr = $matches[1];
        } else {
            $jsonStr = $content;
        }

        $mealData = json_decode($jsonStr, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('JSON parsing error', ['error' => json_last_error_msg(), 'content' => $content]);
            throw new \Exception('Failed to parse Gemini response as JSON');
        }

        // Create meals from parsed data
        foreach ($mealData as $meal) {
            $this->createMeal($mealPlan->id, $meal);
        }

        return true;
    }
}