<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientSubscriptionResource extends JsonResource
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
            'user_id' => $this->user_id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ],
            'gym_id' => $this->gym_id,
            'gym' => [
                'id' => $this->gym->id,
                'name' => $this->gym->name,
            ],
            'gym_subscription_plan_id' => $this->gym_subscription_plan_id,
            'plan' => new GymSubscriptionPlanResource($this->plan),
            'status' => $this->status,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'auto_renew' => $this->auto_renew,
            'payment_status' => $this->payment_status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'is_active' => $this->isActive(),
            'days_remaining' => $this->end_date ? now()->diffInDays($this->end_date, false) : null,
        ];
    }
}