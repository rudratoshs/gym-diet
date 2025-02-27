<?php

// app/Http/Resources/MealResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'meal_plan_id' => $this->meal_plan_id,
            'meal_type' => $this->meal_type,
            'meal_type_display' => $this->meal_type_display,
            'title' => $this->title,
            'description' => $this->description,
            'calories' => $this->calories,
            'protein_grams' => $this->protein_grams,
            'carbs_grams' => $this->carbs_grams,
            'fats_grams' => $this->fats_grams,
            'time_of_day' => $this->time_of_day,
            'recipes' => $this->recipes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}