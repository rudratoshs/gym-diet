<?php

// app/Policies/DietPlanPolicy.php
namespace App\Policies;

use App\Models\DietPlan;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DietPlanPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_diet_plans');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DietPlan $dietPlan): bool
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
                $userGyms = $user->gyms()->pluck('gyms.id')->toArray();
                $clientGyms = $client->gyms()->pluck('gyms.id')->toArray();

                // Check if there's an overlap in gyms
                return count(array_intersect($userGyms, $clientGyms)) > 0;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_diet_plans');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DietPlan $dietPlan): bool
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
        if ($user->hasRole(['gym_admin', 'dietitian']) && $user->hasPermissionTo('edit_diet_plans')) {
            $client = User::find($dietPlan->client_id);

            if ($client) {
                $userGyms = $user->gyms()->pluck('gyms.id')->toArray();
                $clientGyms = $client->gyms()->pluck('gyms.id')->toArray();

                // Check if there's an overlap in gyms
                return count(array_intersect($userGyms, $clientGyms)) > 0;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DietPlan $dietPlan): bool
    {
        return $this->update($user, $dietPlan);
    }
}