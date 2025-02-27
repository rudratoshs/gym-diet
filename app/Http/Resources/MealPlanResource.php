<?php

// app/Http/Resources/MealPlanResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'diet_plan_id' => $this->diet_plan_id,
            'day_of_week' => $this->day_of_week,
            'total_calories' => $this->total_calories,
            'total_protein' => $this->total_protein,
            'total_carbs' => $this->total_carbs,
            'total_fats' => $this->total_fats,
            'meals' => MealResource::collection($this->whenLoaded('meals')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
