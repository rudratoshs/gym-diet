<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GymSubscriptionPlanResource extends JsonResource
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
            'gym_id' => $this->gym_id,
            'gym' => $this->when($request->user() && $request->user()->hasRole('admin'), function() {
                return [
                    'id' => $this->gym->id,
                    'name' => $this->gym->name,
                ];
            }),
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'billing_cycle' => $this->billing_cycle,
            'features' => $this->features,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'active_subscriptions_count' => $this->when(
                $request->user() && 
                ($request->user()->hasRole('admin') || $request->user()->belongsToGym($this->gym_id, ['gym_admin', 'trainer', 'dietitian'])), 
                $this->activeSubscriptionsCount()
            ),
        ];
    }
}