<?php

// app/Http/Controllers/Api/MealController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MealResource;
use App\Models\DietPlan;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MealController extends Controller
{
    /**
     * Store a newly created meal in storage.
     */
    public function store(Request $request, DietPlan $dietPlan, MealPlan $mealPlan)
    {
        // Check if meal plan belongs to diet plan
        if ($mealPlan->diet_plan_id !== $dietPlan->id) {
            return response()->json(['message' => 'Meal plan not found for this diet plan'], 404);
        }

        // Check if user can update this diet plan
        if (!$this->canUpdateDietPlan($request->user(), $dietPlan)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'meal_type' => [
                'required',
                Rule::in(['breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner', 'evening_snack', 'pre_workout', 'post_workout']),
                Rule::unique('meals')->where(function ($query) use ($mealPlan) {
                    return $query->where('meal_plan_id', $mealPlan->id);
                }),
            ],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'calories' => 'nullable|integer|min:0|max:3000',
            'protein_grams' => 'nullable|integer|min:0|max:200',
            'carbs_grams' => 'nullable|integer|min:0|max:500',
            'fats_grams' => 'nullable|integer|min:0|max:200',
            'time_of_day' => 'nullable|date_format:H:i',
            'recipes' => 'nullable|array',
        ]);

        $meal = new Meal($validated);
        $meal->meal_plan_id = $mealPlan->id;
        $meal->save();

        return new MealResource($meal);
    }

    /**
     * Display the specified meal.
     */
    public function show(Request $request, DietPlan $dietPlan, MealPlan $mealPlan, Meal $meal)
    {
        // Check if meal belongs to meal plan
        if ($meal->meal_plan_id !== $mealPlan->id) {
            return response()->json(['message' => 'Meal not found for this meal plan'], 404);
        }

        // Check if meal plan belongs to diet plan
        if ($mealPlan->diet_plan_id !== $dietPlan->id) {
            return response()->json(['message' => 'Meal plan not found for this diet plan'], 404);
        }

        // Check if user can view this diet plan
        if (!$this->canViewDietPlan($request->user(), $dietPlan)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return new MealResource($meal);
    }

    /**
     * Update the specified meal in storage.
     */
    public function update(Request $request, DietPlan $dietPlan, MealPlan $mealPlan, Meal $meal)
    {
        // Check if meal belongs to meal plan
        if ($meal->meal_plan_id !== $mealPlan->id) {
            return response()->json(['message' => 'Meal not found for this meal plan'], 404);
        }

        // Check if meal plan belongs to diet plan
        if ($mealPlan->diet_plan_id !== $dietPlan->id) {
            return response()->json(['message' => 'Meal plan not found for this diet plan'], 404);
        }

        // Check if user can update this diet plan
        if (!$this->canUpdateDietPlan($request->user(), $dietPlan)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'meal_type' => [
                'sometimes',
                Rule::in(['breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner', 'evening_snack', 'pre_workout', 'post_workout']),
                Rule::unique('meals')->where(function ($query) use ($mealPlan, $meal) {
                    return $query->where('meal_plan_id', $mealPlan->id)
                        ->where('id', '!=', $meal->id);
                }),
            ],
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'calories' => 'nullable|integer|min:0|max:3000',
            'protein_grams' => 'nullable|integer|min:0|max:200',
            'carbs_grams' => 'nullable|integer|min:0|max:500',
            'fats_grams' => 'nullable|integer|min:0|max:200',
            'time_of_day' => 'nullable|date_format:H:i',
            'recipes' => 'nullable|array',
        ]);

        $meal->update($validated);

        return new MealResource($meal);
    }

    /**
     * Remove the specified meal from storage.
     */
    public function destroy(Request $request, DietPlan $dietPlan, MealPlan $mealPlan, Meal $meal)
    {
        // Check if meal belongs to meal plan
        if ($meal->meal_plan_id !== $mealPlan->id) {
            return response()->json(['message' => 'Meal not found for this meal plan'], 404);
        }

        // Check if meal plan belongs to diet plan
        if ($mealPlan->diet_plan_id !== $dietPlan->id) {
            return response()->json(['message' => 'Meal plan not found for this diet plan'], 404);
        }

        // Check if user can update this diet plan
        if (!$this->canUpdateDietPlan($request->user(), $dietPlan)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $meal->delete();

        return response()->json(['message' => 'Meal deleted successfully']);
    }

    /**
     * Check if user can view a diet plan.
     */
    private function canViewDietPlan(User $user, DietPlan $dietPlan)
    {
        // Admin can view any diet plan
        if ($user->hasRole('admin')) {
            return true;
        }

        // Users can view their own diet plans
        if ($dietPlan->client_id === $user->id) {
            return true;
        }

        // Creator can view the diet plan
        if ($dietPlan->created_by === $user->id) {
            return true;
        }

        // Gym staff can view diet plans for clients in their gym
        if ($user->hasRole(['gym_admin', 'trainer', 'dietitian'])) {
            $client = User::find($dietPlan->client_id);

            if ($client) {
                return $this->canManageClient($user, $client);
            }
        }

        return false;
    }

    /**
     * Check if user can update a diet plan.
     */
    private function canUpdateDietPlan(User $user, DietPlan $dietPlan)
    {
        // Admin can update any diet plan
        if ($user->hasRole('admin')) {
            return true;
        }

        // Creator can update the diet plan
        if ($dietPlan->created_by === $user->id) {
            return true;
        }

        // Gym staff can update diet plans for clients in their gym if they have permission
        if ($user->hasRole(['gym_admin', 'dietitian']) && $user->can('edit_diet_plans')) {
            $client = User::find($dietPlan->client_id);

            if ($client) {
                return $this->canManageClient($user, $client);
            }
        }

        return false;
    }

    /**
     * Check if user can manage (create/edit diet plans for) a client.
     */
    private function canManageClient(User $user, User $client)
    {
        // Admin can manage any client
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check if client has role 'client'
        if (!$client->hasRole('client')) {
            return false;
        }

        // Gym admins, trainers, and dietitians can manage clients in their gym
        if ($user->hasRole(['gym_admin', 'trainer', 'dietitian'])) {
            $userGyms = $user->gyms()->pluck('gyms.id')->toArray();
            $clientGyms = $client->gyms()->pluck('gyms.id')->toArray();

            // Check if there's an overlap in gyms
            return count(array_intersect($userGyms, $clientGyms)) > 0;
        }

        return false;
    }
}