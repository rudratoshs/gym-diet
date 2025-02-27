<?php

// app/Http/Controllers/Api/MealPlanController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MealPlanResource;
use App\Models\DietPlan;
use App\Models\MealPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MealPlanController extends Controller
{
    /**
     * Store a newly created meal plan in storage.
     */
    public function store(Request $request, DietPlan $dietPlan)
    {
        // Check if user can update this diet plan
        if (!$this->canUpdateDietPlan($request->user(), $dietPlan)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'day_of_week' => [
                'required',
                Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
                Rule::unique('meal_plans')->where(function ($query) use ($dietPlan) {
                    return $query->where('diet_plan_id', $dietPlan->id);
                }),
            ],
        ]);

        $mealPlan = new MealPlan($validated);
        $mealPlan->diet_plan_id = $dietPlan->id;
        $mealPlan->save();

        return new MealPlanResource($mealPlan);
    }

    /**
     * Display the specified meal plan.
     */
    public function show(Request $request, DietPlan $dietPlan, MealPlan $mealPlan)
    {
        // Check if meal plan belongs to diet plan
        if ($mealPlan->diet_plan_id !== $dietPlan->id) {
            return response()->json(['message' => 'Meal plan not found for this diet plan'], 404);
        }

        // Check if user can view this diet plan
        if (!$this->canViewDietPlan($request->user(), $dietPlan)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mealPlan->load('meals');

        return new MealPlanResource($mealPlan);
    }

    /**
     * Remove the specified meal plan from storage.
     */
    public function destroy(Request $request, DietPlan $dietPlan, MealPlan $mealPlan)
    {
        // Check if meal plan belongs to diet plan
        if ($mealPlan->diet_plan_id !== $dietPlan->id) {
            return response()->json(['message' => 'Meal plan not found for this diet plan'], 404);
        }

        // Check if user can update this diet plan
        if (!$this->canUpdateDietPlan($request->user(), $dietPlan)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mealPlan->delete();

        return response()->json(['message' => 'Meal plan deleted successfully']);
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