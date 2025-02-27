<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientProfileResource;
use App\Models\ClientProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ClientProfileController extends Controller
{
    /**
     * Get client profile for a user.
     */
    public function show(User $user)
    {
        Gate::authorize('view', $user);

        $profile = $user->clientProfile;

        if (!$profile) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        return new ClientProfileResource($profile);
    }

    /**
     * Create or update client profile.
     */
    public function store(Request $request, User $user)
    {
        Gate::authorize('update', $user);

        $validated = $request->validate([
            'age' => 'nullable|integer|min:1|max:120',
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'height' => 'nullable|numeric|min:50|max:300',
            'current_weight' => 'nullable|numeric|min:20|max:300',
            'target_weight' => 'nullable|numeric|min:20|max:300',
            'activity_level' => [
                'nullable',
                Rule::in([
                    'sedentary',
                    'lightly_active',
                    'moderately_active',
                    'very_active',
                    'extremely_active'
                ])
            ],
            'diet_type' => [
                'nullable',
                Rule::in([
                    'omnivore',
                    'vegetarian',
                    'vegan',
                    'pescatarian',
                    'flexitarian',
                    'keto',
                    'paleo',
                    'other'
                ])
            ],
            'health_conditions' => 'nullable|array',
            'allergies' => 'nullable|array',
            'recovery_needs' => 'nullable|array',
            'meal_preferences' => 'nullable|array',
        ]);

        $profile = $user->clientProfile;

        if ($profile) {
            $profile->update($validated);
        } else {
            $profile = new ClientProfile($validated);
            $profile->user_id = $user->id;
            $profile->save();
        }

        return new ClientProfileResource($profile);
    }

    /**
     * Update client profile.
     */
    public function update(Request $request, User $user)
    {
        // Same as store but for PUT requests
        return $this->store($request, $user);
    }
}