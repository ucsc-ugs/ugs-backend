<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
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
            'role' => 'student',
            'id' => $this->id,
            'data' => [
                'name' => $this->name,
                'email' => $this->email,
                'created_at' => $this->created_at,
                'local' => $this->student?->local,
                'passport_nic' => $this->student?->passport_nic,
            ],
            'meta' => [
                'permissions' => $this->getAllPermissions()->pluck('name'),
            ],
        ];
    }
}
