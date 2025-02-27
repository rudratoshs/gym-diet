<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'whatsapp_phone' => $this->whatsapp_phone,
            'status' => $this->status,
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('name');
            }),
            'permissions' => $this->whenLoaded('permissions', function () {
                return $this->getAllPermissions()->pluck('name');
            }),
            'gym_role' => $this->whenPivotLoaded('gym_user', function () {
                return $this->pivot->role;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'client_profile' => $this->whenLoaded('clientProfile', function () {
                return new ClientProfileResource($this->clientProfile);
            }),

        ];
    }
}
