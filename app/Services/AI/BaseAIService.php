<?php
// app/Services/AI/BaseAIService.php
namespace App\Services\AI;

use App\AIServiceInterface;
use App\Models\AIConfiguration;
use App\Models\AssessmentSession;
use App\Models\ClientProfile;
use App\Models\DietPlan;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\User;
use Illuminate\Support\Facades\Log;

abstract class BaseAIService implements AIServiceInterface
{
    protected $config;

    public function __construct(?AIConfiguration $config = null)
    {
        $this->config = $config;
    }

    /**
     * Generate diet plan based on assessment responses
     */
    public function generateDietPlan(AssessmentSession $session): DietPlan
    {
        $user = User::findOrFail($session->user_id);
        $responses = $session->responses;

        // Get or create client profile
        $profile = $this->createClientProfileFromResponses($user, $responses);

        // Generate diet plan
        $dietPlan = $this->createBaseDietPlan($user, $profile, $responses);

        // Generate meal plans using provider-specific implementation
        $this->generateMealPlans($dietPlan, $responses, [
            'profile' => $profile,
            'health_conditions' => $profile->health_conditions ?? ['none'],
            'allergies' => $profile->allergies ?? ['none'],
            'recovery_needs' => $profile->recovery_needs ?? ['none'],
            'meal_preferences' => $profile->meal_preferences ?? ['balanced'],
        ]);

        return $dietPlan;
    }

    /**
     * Create client profile from assessment responses
     */
    protected function createClientProfileFromResponses(User $user, array $responses)
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
     * Create base diet plan
     */
    protected function createBaseDietPlan(User $user, ClientProfile $profile, array $responses)
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

        // Calculate calories and macros (same implementation as before)
        $bmr = $this->calculateBMR($profile);
        $dailyCalories = $this->calculateDailyCalories($bmr, $profile->activity_level);
        $macros = $this->calculateMacros($dailyCalories, $profile->diet_type, $responses);

        $dietPlan->daily_calories = $dailyCalories;
        $dietPlan->protein_grams = $macros['protein'];
        $dietPlan->carbs_grams = $macros['carbs'];
        $dietPlan->fats_grams = $macros['fats'];

        $dietPlan->save();

        return $dietPlan;
    }

    /**
     * Generate meal plans for the diet plan
     * (abstract, implemented by specific providers)
     */
    abstract public function generateMealPlans(DietPlan $dietPlan, array $responses, array $preferences): bool;

    /**
     * Create default meals for a meal plan
     */
    protected function createDefaultMeals($mealPlanId, $dietPlan)
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
     * Calculate BMR, daily calories and macros
     * (same implementations as before)
     */
    protected function calculateBMR(ClientProfile $profile)
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

    protected function calculateDailyCalories($bmr, $activityLevel)
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

    protected function calculateMacros($calories, $dietType, $responses)
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