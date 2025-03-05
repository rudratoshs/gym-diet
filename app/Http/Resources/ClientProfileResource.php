<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,

            // Basic information
            'age' => $this->age,
            'gender' => $this->gender,
            'height' => $this->height,
            'current_weight' => $this->current_weight,
            'target_weight' => $this->target_weight,

            // Location information
            'country' => $this->country,
            'state' => $this->state,
            'city' => $this->city,

            // Activity and diet
            'activity_level' => $this->activity_level,
            'diet_type' => $this->diet_type,

            // Health information
            'health_conditions' => $this->health_conditions,
            'health_details' => $this->health_details,
            'allergies' => $this->allergies,
            'recovery_needs' => $this->recovery_needs,
            'organ_recovery_details' => $this->organ_recovery_details,
            'medications' => $this->medications,
            'medication_details' => $this->medication_details,

            // Food preferences
            'cuisine_preferences' => $this->cuisine_preferences,
            'meal_timing' => $this->meal_timing,
            'food_restrictions' => $this->food_restrictions,
            'meal_preferences' => $this->meal_preferences,
            'meal_variety' => $this->meal_variety,

            // Lifestyle factors
            'daily_schedule' => $this->daily_schedule,
            'cooking_capability' => $this->cooking_capability,
            'exercise_routine' => $this->exercise_routine,
            'stress_sleep' => $this->stress_sleep,

            // Goals and plans
            'primary_goal' => $this->primary_goal,
            'goal_timeline' => $this->goal_timeline,
            'commitment_level' => $this->commitment_level,
            'additional_requests' => $this->additional_requests,
            'measurement_preference' => $this->measurement_preference,
            'plan_type' => $this->plan_type,

            // Computed attributes
            'bmi' => $this->bmi,
            'bmr' => $this->bmr,
            'daily_calories' => $this->daily_calories,
            'weight_goal_type' => $this->weight_goal_type,
            'weight_difference' => $this->weight_difference,

            // Display attributes
            'activity_level_display' => $this->activity_level_display,
            'diet_type_display' => $this->diet_type_display,
            'meal_timing_display' => $this->meal_timing_display,
            'stress_sleep_display' => $this->stress_sleep_display,
            'primary_goal_display' => $this->primary_goal_display,
            'plan_type_display' => $this->plan_type_display,

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}