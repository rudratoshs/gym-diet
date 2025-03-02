<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'plan_type' => $this->plan_type,
            'is_active' => $this->is_active,
            'payment_provider' => $this->payment_provider,
            'payment_plans' => $this->when($request->user() && $request->user()->hasRole('admin'), 
                $this->payment_provider_plans
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'features' => $this->when($this->relationLoaded('features'), function() {
                return $this->features->map(function($feature) {
                    return [
                        'id' => $feature->id,
                        'name' => $feature->name,
                        'code' => $feature->code,
                        'description' => $feature->description,
                        'type' => $feature->type,
                        'limit' => $feature->pivot->limit,
                        'value' => $feature->pivot->value,
                    ];
                });
            }),
        ];
    }
}