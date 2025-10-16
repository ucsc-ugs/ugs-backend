<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'user',
            'role' => $this->getRoleNames()->first(),
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'user_type' => $this->user_type,
            'data' => [
                'name' => $this->name,
                'email' => $this->email,
                'created_at' => $this->created_at,
                'student' => $this->getRoleNames()->first() === 'student' ? [
                    'local' => $this->student?->local,
                    'passport_nic' => $this->student?->passport_nic,
                ] : null,
            ],
            'meta' => [
                'permissions' => $this->getAllPermissions()->pluck('name'),
            ],
        ];
    }
}
