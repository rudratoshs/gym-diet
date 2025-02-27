<?php

namespace App\Policies;

use App\Models\Gym;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class GymPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_gyms');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Gym $gym): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        // User can view if they're the owner or a member of the gym
        return $user->id === $gym->owner_id || $gym->users()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_gyms');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Gym $gym): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        // Only the owner or gym_admin can update
        return $user->id === $gym->owner_id || 
               $gym->users()
                   ->where('user_id', $user->id)
                   ->wherePivot('role', 'gym_admin')
                   ->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Gym $gym): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }
        
        // Only the owner can delete
        return $user->id === $gym->owner_id;
    }
}