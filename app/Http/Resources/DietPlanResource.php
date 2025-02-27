<?php

// app/Http/Resources/DietPlanResource.php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DietPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'client' => new UserResource($this->whenLoaded('client')),
            'created_by' => $this->created_by,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'title' => $this->title,
            'description' => $this->description,
            'daily_calories' => $this->daily_calories,
            'protein_grams' => $this->protein_grams,
            'carbs_grams' => $this->carbs_grams,
            'fats_grams' => $this->fats_grams,
            'macro_percentages' => $this->macro_percentages,
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'meal_plans' => MealPlanResource::collection($this->whenLoaded('mealPlans')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}