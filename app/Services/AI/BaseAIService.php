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
        Log::info('Starting diet plan generation', ['assessment_id' => $session->id]);

        $user = User::findOrFail($session->user_id);
        $responses = $session->responses;

        // Get or create client profile
        $profile = $this->createClientProfileFromResponses($user, $responses);
        Log::info('Client profile created/updated', ['profile_id' => $profile->id]);

        // Generate diet plan
        $dietPlan = $this->createBaseDietPlan($user, $profile, $responses);
        Log::info('Base diet plan created', ['diet_plan_id' => $dietPlan->id]);

        // Generate meal plans using provider-specific implementation
        $this->generateMealPlans($dietPlan, $responses, [
            'profile' => $profile,
            'health_conditions' => $profile->health_conditions ?? ['none'],
            'allergies' => $profile->allergies ?? ['none'],
            'recovery_needs' => $profile->recovery_needs ?? ['none'],
            'meal_preferences' => $profile->meal_preferences ?? ['balanced'],
            'cuisine_preferences' => $profile->cuisine_preferences ?? ['no_preference'],
            'daily_schedule' => $profile->daily_schedule ?? 'standard',
            'cooking_capability' => $profile->cooking_capability ?? 'basic',
            'exercise_routine' => $profile->exercise_routine ?? 'minimal',
            'stress_sleep' => $profile->stress_sleep ?? 'moderate',
        ]);
        Log::info('Meal plans generated', ['diet_plan_id' => $dietPlan->id]);

        return $dietPlan;
    }

    /**
     * Create client profile from assessment responses
     */
    protected function createClientProfileFromResponses(User $user, array $responses)
    {
        $profile = ClientProfile::firstOrNew(['user_id' => $user->id]);

        // Basic Information (Phase 1)
        if (isset($responses['age'])) {
            $profile->age = $responses['age'];
        }

        // Location Information
        if (isset($responses['country'])) {
            $profile->country = $responses['country'];
        }

        if (isset($responses['state'])) {
            $profile->state = $responses['state'];
        }

        if (isset($responses['city'])) {
            $profile->city = $responses['city'];
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

        // Health Assessment (Phase 2)
        if (isset($responses['health_conditions'])) {
            $healthConditions = $this->formatListResponse($responses['health_conditions']);
            $profile->health_conditions = $healthConditions;

            // Add health condition details if available
            if (isset($responses['health_details'])) {
                $profile->health_details = $responses['health_details'];
            }
        }

        // Medications (from Comprehensive Assessment)
        if (isset($responses['medications'])) {
            $profile->medications = $this->formatListResponse($responses['medications']);

            // Add medication details if available
            if (isset($responses['medication_details'])) {
                $profile->medication_details = $responses['medication_details'];
            }
        }

        if (isset($responses['allergies'])) {
            $profile->allergies = $this->formatListResponse($responses['allergies']);
        }

        if (isset($responses['recovery_needs'])) {
            $profile->recovery_needs = $this->formatListResponse($responses['recovery_needs']);

            // Add organ recovery details if available
            if (isset($responses['organ_recovery'])) {
                $profile->organ_recovery_details = $responses['organ_recovery'];
            }
        }

        // Diet Preferences (Phase 3)
        if (isset($responses['diet_type'])) {
            $profile->diet_type = $this->mapDietType($responses['diet_type']);
        }

        if (isset($responses['cuisine_preferences'])) {
            $profile->cuisine_preferences = $this->formatListResponse($responses['cuisine_preferences']);
        }

        if (isset($responses['meal_timing'])) {
            $profile->meal_timing = $this->mapMealTiming($responses['meal_timing']);
        }

        if (isset($responses['food_restrictions'])) {
            $profile->food_restrictions = $this->formatListResponse($responses['food_restrictions']);
        }

        // Meal Variety (from Comprehensive Assessment)
        if (isset($responses['meal_variety'])) {
            $profile->meal_variety = $this->mapMealVariety($responses['meal_variety']);
        }

        // Lifestyle (Phase 4)
        if (isset($responses['daily_schedule'])) {
            $profile->daily_schedule = $this->mapDailySchedule($responses['daily_schedule']);
        }

        if (isset($responses['cooking_capability'])) {
            $profile->cooking_capability = $this->mapCookingCapability($responses['cooking_capability']);
        }

        if (isset($responses['exercise_routine'])) {
            $profile->exercise_routine = $this->mapExerciseRoutine($responses['exercise_routine']);
        }

        if (isset($responses['stress_sleep'])) {
            $profile->stress_sleep = $this->mapStressSleep($responses['stress_sleep']);
        }

        // Goals (Phase 5)
        if (isset($responses['primary_goal'])) {
            $profile->primary_goal = $this->mapPrimaryGoal($responses['primary_goal']);
        }

        if (isset($responses['timeline'])) {
            $profile->goal_timeline = $this->mapTimeline($responses['timeline']);
        }

        // Commitment Level (from Comprehensive Assessment)
        if (isset($responses['commitment_level'])) {
            $profile->commitment_level = $this->mapCommitmentLevel($responses['commitment_level']);
        }

        if (isset($responses['measurement_preference'])) {
            $profile->measurement_preference = $this->mapMeasurementPreference($responses['measurement_preference']);
        }

        // Additional Requests (from Comprehensive Assessment)
        if (isset($responses['additional_requests'])) {
            $profile->additional_requests = $responses['additional_requests'];
        }

        // Plan Customization (Phase 6)
        if (isset($responses['plan_type'])) {
            $profile->plan_type = $responses['plan_type'];
        }

        $profile->save();
        return $profile;
    }

    /**
     * Format list response from string to array
     */
    protected function formatListResponse($response)
    {
        // If already an array, return as is
        if (is_array($response)) {
            return $response;
        }

        // If comma-separated string, split and trim
        if (is_string($response) && str_contains($response, ',')) {
            return array_map('trim', explode(',', $response));
        }

        // If single value, make it an array
        return [$response];
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

        // Customize title based on primary goal
        $goalMap = [
            'weight_loss' => 'Weight Loss',
            'muscle_gain' => 'Muscle Building',
            'energy' => 'Energy Boosting',
            'health' => 'Health Improvement',
            'recovery' => 'Recovery',
            'athletic' => 'Athletic Performance',
            'longevity' => 'Longevity & Prevention'
        ];

        $primaryGoal = $profile->primary_goal ?? 'health';
        $goalTitle = $goalMap[$primaryGoal] ?? 'Personalized';

        // Add diet type to title
        $dietTypeMap = [
            'vegetarian' => 'Vegetarian',
            'vegan' => 'Vegan',
            'pescatarian' => 'Pescatarian',
            'keto' => 'Keto',
            'paleo' => 'Paleo'
        ];

        $dietType = $profile->diet_type ?? '';
        $dietTitle = isset($dietTypeMap[$dietType]) ? $dietTypeMap[$dietType] . ' ' : '';

        $dietPlan->title = $dietTitle . $goalTitle . ' Diet Plan';

        // Create description based on health conditions and goals
        $description = 'Personalized diet plan';

        if (!empty($profile->health_conditions) && $profile->health_conditions[0] !== 'none') {
            $description .= ' designed for ' . $goalMap[$primaryGoal] ?? 'health improvement';

            if (!empty($profile->recovery_needs) && $profile->recovery_needs[0] !== 'none') {
                $recoveryNeeds = is_array($profile->recovery_needs) ? implode(', ', $profile->recovery_needs) : $profile->recovery_needs;
                $description .= ' with focus on ' . $recoveryNeeds;
            }
        } else {
            $description .= ' tailored to your ' . strtolower($goalMap[$primaryGoal] ?? 'health') . ' goals';
        }

        if (!empty($profile->food_restrictions) && $profile->food_restrictions[0] !== 'none') {
            $description .= ', respecting your dietary restrictions';
        }

        $dietPlan->description = $description;
        $dietPlan->status = 'active';
        $dietPlan->start_date = now();

        // Set end date based on timeline
        $timeline = $profile->goal_timeline ?? 'medium';
        switch ($timeline) {
            case 'short':
                $dietPlan->end_date = now()->addWeeks(4);
                break;
            case 'medium':
                $dietPlan->end_date = now()->addMonths(3);
                break;
            case 'long':
                $dietPlan->end_date = now()->addMonths(6);
                break;
            case 'lifestyle':
                $dietPlan->end_date = now()->addYears(1);
                break;
            default:
                $dietPlan->end_date = now()->addMonths(3);
        }

        // Calculate calories and macros
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
        // Same implementation as before
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
                    'ingredients' => [
                        '1/2 cup rolled oats',
                        '1 cup milk',
                        '1 tbsp honey',
                        '1/2 cup mixed fruits'
                    ],
                    'instructions' => [
                        'Cook oats with milk. Top with fruits and honey.'
                    ]
                ]
            ], 
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
     * Calculate BMR using Mifflin-St Jeor Equation
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

    private function calculateMacros($calories, $dietType, $responses)
    {
        $goalType = $responses['primary_goal'] ?? 'weight_loss';

        // Default macro distribution
        $macros = [
            'protein' => 0,
            'carbs' => 0,
            'fats' => 0,
        ];

        // Handle special diet types first
        if ($dietType === 'keto') {
            return [
                'protein' => round(($calories * 0.25) / 4), // 25% protein
                'carbs' => round(($calories * 0.05) / 4),   // 5% carbs
                'fats' => round(($calories * 0.7) / 9),     // 70% fats
            ];
        } elseif ($dietType === 'high_protein') {
            return [
                'protein' => round(($calories * 0.4) / 4),  // 40% protein
                'carbs' => round(($calories * 0.3) / 4),    // 30% carbs
                'fats' => round(($calories * 0.3) / 9),     // 30% fats
            ];
        }

        // Goal-based macros
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
            if (is_string($value) && (strpos(strtolower($value), 'lb') !== false)) {
                $value = (float) preg_replace('/[^0-9.]/', '', $value);
                return round($value * 0.453592); // Convert to kg
            }

            // If it's already in kg
            return (float) preg_replace('/[^0-9.]/', '', $value);
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

    /**
     * Map meal timing from response to database value
     */
    private function mapMealTiming($timing)
    {
        $map = [
            '1' => 'traditional',
            '2' => 'frequent',
            '3' => 'intermittent',
            '4' => 'omad',
            '5' => 'flexible',
            'traditional' => 'traditional',
            'frequent' => 'frequent',
            'intermittent' => 'intermittent',
            'omad' => 'omad',
            'flexible' => 'flexible',
        ];

        return $map[$timing] ?? 'traditional';
    }

    /**
     * Map daily schedule from response to database value
     */
    private function mapDailySchedule($schedule)
    {
        $map = [
            '1' => 'early_riser',
            '2' => 'standard',
            '3' => 'late_riser',
            '4' => 'night_shift',
            '5' => 'irregular',
            'early riser' => 'early_riser',
            'standard' => 'standard',
            'late riser' => 'late_riser',
            'night shift' => 'night_shift',
            'irregular' => 'irregular',
        ];

        return $map[$schedule] ?? 'standard';
    }

    /**
     * Map cooking capability from response to database value
     */
    private function mapCookingCapability($capability)
    {
        $map = [
            '1' => 'full',
            '2' => 'basic',
            '3' => 'minimal',
            '4' => 'prepared',
            '5' => 'help',
            'full' => 'full',
            'basic' => 'basic',
            'minimal' => 'minimal',
            'prepared' => 'prepared',
            'help' => 'help',
        ];

        return $map[$capability] ?? 'basic';
    }

    /**
     * Map exercise routine from response to database value
     */
    private function mapExerciseRoutine($routine)
    {
        $map = [
            '1' => 'strength',
            '2' => 'cardio',
            '3' => 'mixed',
            '4' => 'low_impact',
            '5' => 'sport',
            '6' => 'minimal',
            'strength' => 'strength',
            'cardio' => 'cardio',
            'mixed' => 'mixed',
            'low_impact' => 'low_impact',
            'sport' => 'sport',
            'minimal' => 'minimal',
        ];

        return $map[$routine] ?? 'minimal';
    }

    /**
     * Map stress/sleep from response to database value
     */
    private function mapStressSleep($value)
    {
        $map = [
            '1' => 'low_stress_good_sleep',
            '2' => 'moderate_stress_adequate_sleep',
            '3' => 'high_stress_sufficient_sleep',
            '4' => 'low_stress_poor_sleep',
            '5' => 'high_stress_poor_sleep',
            'low_stress_good_sleep' => 'low_stress_good_sleep',
            'moderate_stress_adequate_sleep' => 'moderate_stress_adequate_sleep',
            'high_stress_sufficient_sleep' => 'high_stress_sufficient_sleep',
            'low_stress_poor_sleep' => 'low_stress_poor_sleep',
            'high_stress_poor_sleep' => 'high_stress_poor_sleep',
        ];

        return $map[$value] ?? 'moderate_stress_adequate_sleep';
    }

    /**
     * Map primary goal from response to database value
     */
    private function mapPrimaryGoal($goal)
    {
        $map = [
            '1' => 'weight_loss',
            '2' => 'muscle_gain',
            '3' => 'energy',
            '4' => 'health',
            '5' => 'recovery',
            '6' => 'athletic',
            '7' => 'longevity',
            'weight_loss' => 'weight_loss',
            'muscle_gain' => 'muscle_gain',
            'energy' => 'energy',
            'health' => 'health',
            'recovery' => 'recovery',
            'athletic' => 'athletic',
            'longevity' => 'longevity',
        ];

        return $map[$goal] ?? 'health';
    }

    /**
     * Map timeline from response to database value
     */
    private function mapTimeline($timeline)
    {
        $map = [
            '1' => 'short',
            '2' => 'medium',
            '3' => 'long',
            '4' => 'lifestyle',
            'short' => 'short',
            'medium' => 'medium',
            'long' => 'long',
            'lifestyle' => 'lifestyle',
        ];

        return $map[$timeline] ?? 'medium';
    }

    /**
     * Map measurement preference from response to database value
     */
    private function mapMeasurementPreference($preference)
    {
        $map = [
            '1' => 'weight',
            '2' => 'measurements',
            '3' => 'energy',
            '4' => 'performance',
            '5' => 'medical',
            '6' => 'combination',
            'weight' => 'weight',
            'measurements' => 'measurements',
            'energy' => 'energy',
            'performance' => 'performance',
            'medical' => 'medical',
            'combination' => 'combination',
        ];

        return $map[$preference] ?? 'combination';
    }

    protected function mapMealVariety($value)
    {
        $map = [
            '1' => 'high_variety',
            '2' => 'moderate_var',
            '3' => 'limited_var',
            '4' => 'repetitive'
        ];

        return $map[$value] ?? 'moderate_var';
    }

    protected function mapCommitmentLevel($value)
    {
        $map = [
            '1' => 'very_committed',
            '2' => 'mostly',
            '3' => 'moderate',
            '4' => 'flexible',
            '5' => 'gradual'
        ];

        return $map[$value] ?? 'moderate';
    }

    /**
     * Test connection to AI service API
     */
    abstract public function testConnection(): bool;
}