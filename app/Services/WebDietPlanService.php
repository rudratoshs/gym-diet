<?php

namespace App\Services;

use App\Models\AssessmentSession;
use App\Models\DietPlan;
use App\Models\MealPlan;
use App\Models\Meal;
use App\Models\User;
use App\Services\AIServiceFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class WebDietPlanService
{
    /**
     * Generate a diet plan from assessment data
     *
     * @param AssessmentSession $session
     * @return DietPlan|null
     */
    public function generatePlanFromAssessment(AssessmentSession $session)
    {
        try {
            $user = User::findOrFail($session->user_id);
            $userGym = $user->gyms()->first();

            if (!$userGym) {
                Log::error('User not associated with any gym', ['user_id' => $user->id]);
                return null;
            }

            // Create AI service using factory (reusing your existing code)
            $aiService = AIServiceFactory::create($userGym);

            if (!$aiService) {
                Log::error('Failed to create AI service', ['user_id' => $user->id]);
                return null;
            }

            // Generate diet plan using AI service
            // This will use your existing logic for interacting with Gemini
            $dietPlan = $aiService->generateDietPlan($session);

            return $dietPlan;
        } catch (\Exception $e) {
            Log::error('Error generating diet plan', [
                'error' => $e->getMessage(),
                'user_id' => $session->user_id
            ]);

            return null;
        }
    }

    /**
     * Get diet plan details including meals
     *
     * @param DietPlan $dietPlan
     * @return array
     */
    public function getDietPlanDetails(DietPlan $dietPlan)
    {
        return [
            'id' => $dietPlan->id,
            'title' => $dietPlan->title,
            'description' => $dietPlan->description,
            'daily_calories' => $dietPlan->daily_calories,
            'protein_grams' => $dietPlan->protein_grams,
            'carbs_grams' => $dietPlan->carbs_grams,
            'fats_grams' => $dietPlan->fats_grams,
            'protein_percentage' => $this->calculateMacroPercentage($dietPlan, 'protein'),
            'carbs_percentage' => $this->calculateMacroPercentage($dietPlan, 'carbs'),
            'fats_percentage' => $this->calculateMacroPercentage($dietPlan, 'fats'),
            'start_date' => $dietPlan->start_date,
            'end_date' => $dietPlan->end_date,
            'meal_plans' => $dietPlan->mealPlans->map(function ($mealPlan) {
                return [
                    'day' => $mealPlan->day_of_week,
                    'meals' => $mealPlan->meals->map(function ($meal) {
                        return [
                            'meal_type' => $meal->meal_type,
                            'title' => $meal->title,
                            'description' => $meal->description,
                            'calories' => $meal->calories,
                            'protein_grams' => $meal->protein_grams,
                            'carbs_grams' => $meal->carbs_grams,
                            'fats_grams' => $meal->fats_grams,
                            'time_of_day' => $meal->time_of_day,
                            'recipes' => json_decode($meal->recipes, true)
                        ];
                    })
                ];
            })
        ];
    }

    /**
     * Calculate macro percentage for display
     *
     * @param DietPlan $dietPlan
     * @param string $macro
     * @return int
     */
    private function calculateMacroPercentage(DietPlan $dietPlan, string $macro)
    {
        $caloriesFromMacro = 0;

        if ($macro === 'protein') {
            $caloriesFromMacro = $dietPlan->protein_grams * 4;
        } elseif ($macro === 'carbs') {
            $caloriesFromMacro = $dietPlan->carbs_grams * 4;
        } elseif ($macro === 'fats') {
            $caloriesFromMacro = $dietPlan->fats_grams * 9;
        }

        if ($dietPlan->daily_calories > 0) {
            return round(($caloriesFromMacro / $dietPlan->daily_calories) * 100);
        }

        return 0;
    }
}