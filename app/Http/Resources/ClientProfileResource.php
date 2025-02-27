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
            'age' => $this->age,
            'gender' => $this->gender,
            'height' => $this->height,
            'current_weight' => $this->current_weight,
            'target_weight' => $this->target_weight,
            'activity_level' => $this->activity_level,
            'diet_type' => $this->diet_type,
            'health_conditions' => $this->health_conditions,
            'allergies' => $this->allergies,
            'recovery_needs' => $this->recovery_needs,
            'meal_preferences' => $this->meal_preferences,
            // Computed attributes
            'bmi' => $this->bmi,
            'bmr' => $this->bmr,
            'daily_calories' => $this->daily_calories,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}