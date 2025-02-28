<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
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
                    'owner_id' => $this->gym->owner_id,
                ];
            }),
            'subscription_plan_id' => $this->subscription_plan_id,
            'plan' => new SubscriptionPlanResource($this->plan),
            'status' => $this->status,
            'current_period_start' => $this->current_period_start,
            'current_period_end' => $this->current_period_end,
            'canceled_at' => $this->canceled_at,
            'payment_provider' => $this->payment_provider,
            'payment_provider_id' => $this->when($request->user() && ($request->user()->hasRole('admin') || $request->user()->id === $this->gym->owner_id), $this->payment_provider_id),
            'billing_cycle' => $this->billing_cycle,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'is_active' => $this->isActive(),
            'is_canceled' => $this->isCanceled(),
            'days_remaining' => $this->current_period_end ? now()->diffInDays($this->current_period_end, false) : null,
        ];
    }
}