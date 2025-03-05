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
            // Basic information
            'age' => 'nullable|integer|min:1|max:120',
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'height' => 'nullable|numeric|min:50|max:300',
            'current_weight' => 'nullable|numeric|min:20|max:300',
            'target_weight' => 'nullable|numeric|min:20|max:300',

            // Location information
            'country' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',

            // Activity and diet
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

            // Health information
            'health_conditions' => 'nullable|array',
            'health_details' => 'nullable|string|max:500',
            'allergies' => 'nullable|array',
            'recovery_needs' => 'nullable|array',
            'organ_recovery_details' => 'nullable|string|max:500',
            'medications' => 'nullable|array',
            'medication_details' => 'nullable|string|max:500',

            // Food preferences
            'cuisine_preferences' => 'nullable|array',
            'meal_timing' => [
                'nullable',
                Rule::in([
                    'traditional',
                    'small_frequent',
                    'intermittent',
                    'omad',
                    'flexible'
                ])
            ],
            'food_restrictions' => 'nullable|array',
            'meal_preferences' => 'nullable|array',
            'meal_variety' => [
                'nullable',
                Rule::in([
                    'high_variety',
                    'moderate_var',
                    'limited_var',
                    'repetitive'
                ])
            ],

            // Lifestyle factors
            'daily_schedule' => [
                'nullable',
                Rule::in([
                    'early_riser',
                    'standard',
                    'late_riser',
                    'night_shift',
                    'irregular'
                ])
            ],
            'cooking_capability' => [
                'nullable',
                Rule::in([
                    'full',
                    'basic',
                    'minimal',
                    'prepared_food',
                    'cooking_help'
                ])
            ],
            'exercise_routine' => [
                'nullable',
                Rule::in([
                    'strength',
                    'cardio',
                    'mix_exercise',
                    'yoga',
                    'sport',
                    'minimal'
                ])
            ],
            'stress_sleep' => [
                'nullable',
                Rule::in([
                    'low_good',
                    'moderate_ok',
                    'high_enough',
                    'low_poor',
                    'high_poor'
                ])
            ],

            // Goals and plans
            'primary_goal' => [
                'nullable',
                Rule::in([
                    'weight_loss',
                    'muscle_gain',
                    'maintain',
                    'energy',
                    'health',
                    'other'
                ])
            ],
            'goal_timeline' => [
                'nullable',
                Rule::in([
                    'short',
                    'medium',
                    'long',
                    'lifestyle'
                ])
            ],
            'commitment_level' => [
                'nullable',
                Rule::in([
                    'very_committed',
                    'mostly',
                    'moderate',
                    'flexible',
                    'gradual'
                ])
            ],
            'additional_requests' => 'nullable|string|max:1000',
            'measurement_preference' => [
                'nullable',
                Rule::in([
                    'metric',
                    'imperial',
                    'measurements',
                    'progress_photos'
                ])
            ],
            'plan_type' => [
                'nullable',
                Rule::in([
                    'complete',
                    'basic',
                    'focus'
                ])
            ],
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