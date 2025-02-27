<?php
namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_users');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermissionTo('view_users');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create_users');
    }

    public function update(User $user, User $model): bool
    {
        // Prevent editing admins if not an admin
        if ($model->hasRole('admin') && !$user->hasRole('admin')) {
            return false;
        }

        return $user->hasPermissionTo('edit_users');
    }

    public function delete(User $user, User $model): bool
    {
        // Prevent self-deletion
        if ($user->id === $model->id) {
            return false;
        }

        // Prevent deleting admins if not an admin
        if ($model->hasRole('admin') && !$user->hasRole('admin')) {
            return false;
        }

        return $user->hasPermissionTo('delete_users');
    }
}