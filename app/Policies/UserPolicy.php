<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_users');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        // Admin can view any user
        if ($user->hasRole('admin')) {
            return true;
        }

        // Gym admins can view users in their gym
        if ($user->hasRole('gym_admin')) {
            $userGyms = $user->gyms()->pluck('gyms.id')->toArray();
            $modelGyms = $model->gyms()->pluck('gyms.id')->toArray();

            return count(array_intersect($userGyms, $modelGyms)) > 0;
        }

        // Trainers and dietitians can view their clients
        if ($user->hasRole(['trainer', 'dietitian'])) {
            $userGyms = $user->gyms()->pluck('gyms.id')->toArray();
            $modelGyms = $model->gyms()->pluck('gyms.id')->toArray();

            return count(array_intersect($userGyms, $modelGyms)) > 0 && $model->hasRole('client');
        }

        // Users can view themselves
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_users');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        // Admin can update any user
        if ($user->hasRole('admin')) {
            return true;
        }

        // Gym admins can update users in their gym
        if ($user->hasRole('gym_admin')) {
            $userGyms = $user->gyms()->pluck('gyms.id')->toArray();
            $modelGyms = $model->gyms()->pluck('gyms.id')->toArray();

            // But cannot update admins
            if ($model->hasRole('admin')) {
                return false;
            }

            return count(array_intersect($userGyms, $modelGyms)) > 0;
        }

        // Trainers and dietitians can update their clients' profiles
        if ($user->hasRole(['trainer', 'dietitian'])) {
            $userGyms = $user->gyms()->pluck('gyms.id')->toArray();
            $modelGyms = $model->gyms()->pluck('gyms.id')->toArray();

            return count(array_intersect($userGyms, $modelGyms)) > 0 && $model->hasRole('client');
        }

        // Users can update themselves
        return $user->id === $model->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        // Prevent self-deletion
        if ($user->id === $model->id) {
            return false;
        }

        // Admin can delete any user except other admins
        if ($user->hasRole('admin')) {
            return !$model->hasRole('admin') || $user->id === 1; // Super admin can delete other admins
        }

        // Gym admins can delete users in their gym
        if ($user->hasRole('gym_admin')) {
            $userGyms = $user->gyms()->pluck('gyms.id')->toArray();
            $modelGyms = $model->gyms()->pluck('gyms.id')->toArray();

            // But cannot delete admins or other gym admins
            if ($model->hasRole(['admin', 'gym_admin'])) {
                return false;
            }

            return count(array_intersect($userGyms, $modelGyms)) > 0;
        }

        return false;
    }
}