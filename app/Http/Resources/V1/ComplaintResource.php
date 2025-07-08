<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'complaint',
            'id' => $this->id,
            'data' => [
                'description' => $this->description,
                'status' => $this->status,
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
                'student' => [
                    'id' => $this->student?->id,
                    'name' => $this->student?->user?->name,
                    'email' => $this->student?->user?->email,
                ],
                'exam' => $this->when($this->exam, [
                    'id' => $this->exam?->id,
                    'name' => $this->exam?->name,
                ]),
                'organization' => $this->when($this->organization, [
                    'id' => $this->organization?->id,
                    'name' => $this->organization?->name,
                ]),
                'audit' => [
                    'created_by' => $this->created_by,
                    'updated_by' => $this->updated_by,
                    'resolved_by' => $this->resolved_by,
                    'rejected_by' => $this->rejected_by,
                ],
            ],
        ];
    }
}
