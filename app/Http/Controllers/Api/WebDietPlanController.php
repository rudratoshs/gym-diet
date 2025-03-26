<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DietPlan;
use App\Models\MealPlan;
use App\Models\Meal;
use App\Services\WebDietPlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WebDietPlanController extends Controller
{
    protected $dietPlanService;

    public function __construct(WebDietPlanService $dietPlanService)
    {
        $this->dietPlanService = $dietPlanService;
    }

    /**
     * Get active diet plan for user
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActivePlan()
    {
        $user = Auth::user();

        $activePlan = DietPlan::where('client_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$activePlan) {
            return response()->json([
                'message' => 'No active diet plan found'
            ], 404);
        }

        $planDetails = $this->dietPlanService->getDietPlanDetails($activePlan);

        return response()->json([
            'diet_plan' => $planDetails
        ]);
    }

    /**
     * Get day-specific meal plan
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDayPlan(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:diet_plans,id',
            'day' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'
        ]);

        $planId = $request->input('plan_id');
        $day = $request->input('day');

        // Check ownership
        $dietPlan = DietPlan::findOrFail($planId);
        if ($dietPlan->client_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized access to diet plan'
            ], 403);
        }

        // Get day plan
        $mealPlan = MealPlan::where('diet_plan_id', $planId)
            ->where('day_of_week', $day)
            ->first();

        if (!$mealPlan) {
            return response()->json([
                'message' => 'No meal plan found for this day'
            ], 404);
        }

        // Format day plan
        $dayPlan = [
            'day' => $mealPlan->day_of_week,
            'total_calories' => $mealPlan->total_calories,
            'total_protein' => $mealPlan->total_protein,
            'total_carbs' => $mealPlan->total_carbs,
            'total_fats' => $mealPlan->total_fats,
            'meals' => $mealPlan->meals->map(function ($meal) {
                return [
                    'id' => $meal->id,
                    'meal_type' => $meal->meal_type,
                    'title' => $meal->title,
                    'description' => $meal->description,
                    'calories' => $meal->calories,
                    'protein_grams' => $meal->protein_grams,
                    'carbs_grams' => $meal->carbs_grams,
                    'fats_grams' => $meal->fats_grams,
                    'time_of_day' => $meal->time_of_day,
                    'has_recipe' => !empty($meal->recipes)
                ];
            })
        ];

        return response()->json([
            'meal_plan' => $dayPlan
        ]);
    }

    /**
     * Get meal details with recipe
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMealDetails(Request $request)
    {
        $request->validate([
            'meal_id' => 'required|exists:meals,id',
        ]);

        $mealId = $request->input('meal_id');

        // Get meal
        $meal = Meal::findOrFail($mealId);

        // Check ownership through diet plan
        $mealPlan = MealPlan::findOrFail($meal->meal_plan_id);
        $dietPlan = DietPlan::findOrFail($mealPlan->diet_plan_id);

        if ($dietPlan->client_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized access to meal'
            ], 403);
        }

        // Format meal details
        $mealDetails = [
            'id' => $meal->id,
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

        return response()->json([
            'meal' => $mealDetails
        ]);
    }

    /**
     * List all diet plans for user
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function listPlans()
    {
        $user = Auth::user();

        $plans = DietPlan::where('client_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'title' => $plan->title,
                    'status' => $plan->status,
                    'daily_calories' => $plan->daily_calories,
                    'start_date' => $plan->start_date,
                    'end_date' => $plan->end_date,
                    'created_at' => $plan->created_at
                ];
            });

        return response()->json([
            'plans' => $plans
        ]);
    }

    /**
     * Archive a diet plan
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function archivePlan(Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:diet_plans,id',
        ]);

        $planId = $request->input('plan_id');

        // Check ownership
        $dietPlan = DietPlan::findOrFail($planId);
        if ($dietPlan->client_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized access to diet plan'
            ], 403);
        }

        // Archive the plan
        $dietPlan->status = 'archived';
        $dietPlan->save();

        return response()->json([
            'message' => 'Diet plan archived successfully'
        ]);
    }
}