<?php
// app/Services/AIService.php
namespace App\Services;

use App\Models\AssessmentSession;
use App\Models\ClientProfile;
use App\Models\DietPlan;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected $apiKey;
    protected $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->apiUrl = 'https://api.openai.com/v1/chat/completions';
    }

    /**
     * Generate diet plan based on assessment responses
     */
    public function generateDietPlan(AssessmentSession $session)
    {
        $user = User::findOrFail($session->user_id);
        $responses = $session->responses;

        // Get or create client profile
        $profile = $this->createClientProfileFromResponses($user, $responses);

        // Generate diet plan using AI
        $dietPlan = $this->callAIForDietPlan($user, $profile, $responses);

        return $dietPlan;
    }

    /**
     * Create client profile from assessment responses
     */
    private function createClientProfileFromResponses(User $user, array $responses)
    {
        $profile = ClientProfile::firstOrNew(['user_id' => $user->id]);

        // Map assessment responses to profile fields
        if (isset($responses['age'])) {
            $profile->age = $responses['age'];
        }

        if (isset($responses['gender'])) {
            $profile->gender = strtolower($responses['gender']);
        }

        if (isset($responses['height'])) {
            $profile->height = $this->convertToMetric($responses['height'], 'height');
        }

        if (isset($responses['current_weight'])) {
            $profile->current_weight = $this->convertToMetric($responses['current_weight'], 'weight');
        }

        if (isset($responses['target_weight'])) {
            $profile->target_weight = $this->convertToMetric($responses['target_weight'], 'weight');
        }

        if (isset($responses['activity_level'])) {
            $profile->activity_level = $this->mapActivityLevel($responses['activity_level']);
        }

        if (isset($responses['diet_type'])) {
            $profile->diet_type = $this->mapDietType($responses['diet_type']);
        }

        if (isset($responses['health_conditions'])) {
            $profile->health_conditions = $responses['health_conditions'];
        }

        if (isset($responses['allergies'])) {
            $profile->allergies = $responses['allergies'];
        }

        if (isset($responses['recovery_needs'])) {
            $profile->recovery_needs = $responses['recovery_needs'];
        }

        if (isset($responses['meal_preferences'])) {
            $profile->meal_preferences = $responses['meal_preferences'];
        }

        $profile->save();

        return $profile;
    }

    /**
     * Call AI API to generate diet plan
     */
    private function callAIForDietPlan(User $user, ClientProfile $profile, array $responses)
    {
        // Create diet plan record
        $dietPlan = new DietPlan();
        $dietPlan->client_id = $user->id;
        $dietPlan->created_by = $user->id; // AI-generated, so client is creator
        $dietPlan->title = 'AI-Generated Diet Plan';
        $dietPlan->description = 'Personalized diet plan based on your assessment.';
        $dietPlan->status = 'active';
        $dietPlan->start_date = now();
        $dietPlan->end_date = now()->addMonths(3);

        // Calculate calories and macros
        $bmr = $this->calculateBMR($profile);
        $dailyCalories = $this->calculateDailyCalories($bmr, $profile->activity_level);
        $macros = $this->calculateMacros($dailyCalories, $profile->diet_type, $responses);

        $dietPlan->daily_calories = $dailyCalories;
        $dietPlan->protein_grams = $macros['protein'];
        $dietPlan->carbs_grams = $macros['carbs'];
        $dietPlan->fats_grams = $macros['fats'];

        $dietPlan->save();

        // Generate meal plans using AI
        $this->generateMealPlans($dietPlan, $profile, $responses);

        return $dietPlan;
    }

    /**
     * Generate meal plans for the diet plan
     */
    private function generateMealPlans(DietPlan $dietPlan, ClientProfile $profile, array $responses)
    {
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($daysOfWeek as $day) {
            $mealPlan = new MealPlan();
            $mealPlan->diet_plan_id = $dietPlan->id;
            $mealPlan->day_of_week = $day;
            $mealPlan->save();

            $this->generateMeals($mealPlan, $dietPlan, $profile, $responses, $day);
        }
    }

    /**
     * Generate meals for a meal plan
     */
    public function generateMeals(MealPlan $mealPlan, DietPlan $dietPlan, ClientProfile $profile, array $responses, string $day)
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
     * Create default meals for a meal plan
     */
    private function createDefaultMeals($mealPlanId, $dietPlan)
    {
        $defaultMeals = [
            [
                'meal_type' => 'breakfast',
                'title' => 'Oatmeal with Fruits',
                'description' => 'Rolled oats cooked with almond milk, topped with fruits and honey.',
                'calories' => round($dietPlan->daily_calories * 0.25),
                'protein_grams' => round($dietPlan->protein_grams * 0.2),
                'carbs_grams' => round($dietPlan->carbs_grams * 0.3),
                'fats_grams' => round($dietPlan->fats_grams * 0.15),
                'time_of_day' => '08:00',
                'recipes' => [
                    [
                        'name' => 'Oatmeal',
                        'ingredients' => [
                            '1/2 cup rolled oats',
                            '1 cup milk',
                            '1 tbsp honey',
                            '1/2 cup mixed fruits'
                        ],
                        'instructions' => 'Cook oats with milk. Top with fruits and honey.'
                    ]
                ]
            ],
            [
                'meal_type' => 'morning_snack',
                'title' => 'Greek Yogurt with Nuts',
                'description' => 'Greek yogurt with a mix of nuts.',
                'calories' => round($dietPlan->daily_calories * 0.1),
                'protein_grams' => round($dietPlan->protein_grams * 0.15),
                'carbs_grams' => round($dietPlan->carbs_grams * 0.05),
                'fats_grams' => round($dietPlan->fats_grams * 0.15),
                'time_of_day' => '10:30',
                'recipes' => null
            ],
            [
                'meal_type' => 'lunch',
                'title' => 'Grilled Chicken Salad',
                'description' => 'Grilled chicken breast with mixed greens and olive oil dressing.',
                'calories' => round($dietPlan->daily_calories * 0.3),
                'protein_grams' => round($dietPlan->protein_grams * 0.35),
                'carbs_grams' => round($dietPlan->carbs_grams * 0.2),
                'fats_grams' => round($dietPlan->fats_grams * 0.3),
                'time_of_day' => '13:00',
                'recipes' => null
            ],
            [
                'meal_type' => 'afternoon_snack',
                'title' => 'Fruit and Nuts',
                'description' => 'Apple with a handful of mixed nuts.',
                'calories' => round($dietPlan->daily_calories * 0.1),
                'protein_grams' => round($dietPlan->protein_grams * 0.05),
                'carbs_grams' => round($dietPlan->carbs_grams * 0.1),
                'fats_grams' => round($dietPlan->fats_grams * 0.15),
                'time_of_day' => '16:00',
                'recipes' => null
            ],
            [
                'meal_type' => 'dinner',
                'title' => 'Baked Salmon with Vegetables',
                'description' => 'Baked salmon fillet with steamed vegetables.',
                'calories' => round($dietPlan->daily_calories * 0.2),
                'protein_grams' => round($dietPlan->protein_grams * 0.2),
                'carbs_grams' => round($dietPlan->carbs_grams * 0.3),
                'fats_grams' => round($dietPlan->fats_grams * 0.2),
                'time_of_day' => '19:00',
                'recipes' => null
            ],
            [
                'meal_type' => 'evening_snack',
                'title' => 'Protein Shake',
                'description' => 'Protein shake with almond milk.',
                'calories' => round($dietPlan->daily_calories * 0.05),
                'protein_grams' => round($dietPlan->protein_grams * 0.05),
                'carbs_grams' => round($dietPlan->carbs_grams * 0.05),
                'fats_grams' => round($dietPlan->fats_grams * 0.05),
                'time_of_day' => '21:00',
                'recipes' => null
            ]
        ];

        foreach ($defaultMeals as $mealData) {
            $meal = new Meal();
            $meal->meal_plan_id = $mealPlanId;
            $meal->meal_type = $mealData['meal_type'];
            $meal->title = $mealData['title'];
            $meal->description = $mealData['description'];
            $meal->calories = $mealData['calories'];
            $meal->protein_grams = $mealData['protein_grams'];
            $meal->carbs_grams = $mealData['carbs_grams'];
            $meal->fats_grams = $mealData['fats_grams'];
            $meal->time_of_day = $mealData['time_of_day'];
            $meal->recipes = $mealData['recipes'];
            $meal->save();
        }
    }

    /**
     * Calculate Basal Metabolic Rate (BMR)
     */
    private function calculateBMR(ClientProfile $profile)
    {
        if (!$profile->age || !$profile->height || !$profile->current_weight || !$profile->gender) {
            return 1800; // Default value
        }

        // Mifflin-St Jeor Equation
        if ($profile->gender === 'male') {
            return (10 * $profile->current_weight) + (6.25 * $profile->height) - (5 * $profile->age) + 5;
        } else {
            return (10 * $profile->current_weight) + (6.25 * $profile->height) - (5 * $profile->age) - 161;
        }
    }

    /**
     * Calculate daily calorie needs
     */
    private function calculateDailyCalories($bmr, $activityLevel)
    {
        $multipliers = [
            'sedentary' => 1.2,
            'lightly_active' => 1.375,
            'moderately_active' => 1.55,
            'very_active' => 1.725,
            'extremely_active' => 1.9,
        ];

        $multiplier = $multipliers[$activityLevel] ?? 1.2;
        return round($bmr * $multiplier);
    }

    /**
     * Calculate macronutrient breakdown
     */
    private function calculateMacros($calories, $dietType, $responses)
    {
        $goalType = $responses['primary_goal'] ?? 'weight_loss';

        $macros = [
            'protein' => 0,
            'carbs' => 0,
            'fats' => 0,
        ];

        switch ($goalType) {
            case 'weight_loss':
                $macros['protein'] = round(($calories * 0.3) / 4); // 30% protein
                $macros['carbs'] = round(($calories * 0.45) / 4);  // 45% carbs
                $macros['fats'] = round(($calories * 0.25) / 9);   // 25% fats
                break;

            case 'muscle_gain':
                $macros['protein'] = round(($calories * 0.35) / 4); // 35% protein
                $macros['carbs'] = round(($calories * 0.45) / 4);  // 45% carbs
                $macros['fats'] = round(($calories * 0.2) / 9);    // 20% fats
                break;

            case 'maintenance':
                $macros['protein'] = round(($calories * 0.25) / 4); // 25% protein
                $macros['carbs'] = round(($calories * 0.5) / 4);   // 50% carbs
                $macros['fats'] = round(($calories * 0.25) / 9);   // 25% fats
                break;

            default:
                $macros['protein'] = round(($calories * 0.3) / 4); // 30% protein
                $macros['carbs'] = round(($calories * 0.4) / 4);   // 40% carbs
                $macros['fats'] = round(($calories * 0.3) / 9);    // 30% fats
        }

        // Adjust for diet type
        if ($dietType === 'keto') {
            $macros['protein'] = round(($calories * 0.25) / 4); // 25% protein
            $macros['carbs'] = round(($calories * 0.05) / 4);   // 5% carbs
            $macros['fats'] = round(($calories * 0.7) / 9);     // 70% fats
        } elseif ($dietType === 'high_protein') {
            $macros['protein'] = round(($calories * 0.4) / 4);  // 40% protein
            $macros['carbs'] = round(($calories * 0.3) / 4);    // 30% carbs
            $macros['fats'] = round(($calories * 0.3) / 9);     // 30% fats
        }

        return $macros;
    }

    /**
     * Convert height/weight to metric if needed
     */
    private function convertToMetric($value, $type)
    {
        if ($type === 'height') {
            // If it's a string with feet and inches (e.g. "5'11")
            if (is_string($value) && strpos($value, "'") !== false) {
                $parts = explode("'", $value);
                $feet = (int) $parts[0];
                $inches = isset($parts[1]) ? (int) $parts[1] : 0;

                return round(($feet * 30.48) + ($inches * 2.54)); // Convert to cm
            }

            // If it's already in cm
            return (float) $value;
        } elseif ($type === 'weight') {
            // If it has 'lb' or 'lbs' suffix
            if (is_string($value) && (strpos($value, 'lb') !== false)) {
                $value = (float) $value;
                return round($value * 0.453592); // Convert to kg
            }

            // If it's already in kg
            return (float) $value;
        }

        return $value;
    }

    /**
     * Map activity level from response to database value
     */
    private function mapActivityLevel($level)
    {
        $map = [
            '1' => 'sedentary',
            '2' => 'lightly_active',
            '3' => 'moderately_active',
            '4' => 'very_active',
            '5' => 'extremely_active',
            'sedentary' => 'sedentary',
            'lightly active' => 'lightly_active',
            'moderately active' => 'moderately_active',
            'very active' => 'very_active',
            'extremely active' => 'extremely_active',
        ];

        return $map[$level] ?? 'moderately_active';
    }

    /**
     * Map diet type from response to database value
     */
    private function mapDietType($type)
    {
        $map = [
            '1' => 'omnivore',
            '2' => 'vegetarian',
            '3' => 'vegan',
            '4' => 'pescatarian',
            '5' => 'flexitarian',
            '6' => 'keto',
            '7' => 'paleo',
            '8' => 'other',
            'omnivore' => 'omnivore',
            'vegetarian' => 'vegetarian',
            'vegan' => 'vegan',
            'pescatarian' => 'pescatarian',
            'flexitarian' => 'flexitarian',
            'keto' => 'keto',
            'paleo' => 'paleo',
            'other' => 'other',
        ];

        return $map[$type] ?? 'omnivore';
    }
}