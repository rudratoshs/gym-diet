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
            'monthly_price' => $this->monthly_price,
            'quarterly_price' => $this->quarterly_price,
            'annual_price' => $this->annual_price,
            'features' => $this->features,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'features_detail' => $this->when($request->user() && $request->user()->hasRole('admin'), function() {
                return $this->features()->get()->map(function($feature) {
                    return [
                        'id' => $feature->id,
                        'name' => $feature->name,
                        'code' => $feature->code,
                        'type' => $feature->type,
                        'limit' => $feature->pivot->limit,
                        'value' => $feature->pivot->value,
                    ];
                });
            }),
        ];
    }
}