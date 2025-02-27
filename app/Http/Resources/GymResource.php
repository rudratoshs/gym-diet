<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GymResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'subscription_status' => $this->subscription_status,
            'subscription_expires_at' => $this->subscription_expires_at,
            'max_clients' => $this->max_clients,
            'users_count' => $this->whenCounted('users'),
            'clients_count' => $this->whenCounted('clients'),
            'trainers_count' => $this->whenCounted('trainers'),
            'dietitians_count' => $this->whenCounted('dietitians'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

