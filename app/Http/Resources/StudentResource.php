<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class StudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        $user = $this->resource;

        $exams = [];
        if ($user->relationLoaded('exams')) {
            $exams = $user->exams->pluck('name')->toArray();
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'organization' => optional($user->organization)->name,
            'email' => $user->email,
            'contact' => $user->phone_number ?? null,
            'registration' => $user->student->index_number ?? null,
            'nic' => $user->student->passport_nic ?? null,
            'exams_count' => $user->exams->count() ?? 0,
            'exams_list' => array_slice($exams, 0, 5),
            'payment_status' => $this->resolvePaymentStatus($user),
            'status' => $user->active ?? 'active',
            'registered_at' => $user->created_at ? $user->created_at->toDateString() : null,
        ];
    }

    protected function resolvePaymentStatus($user)
    {
        // Basic heuristic: check pivot payment_id on exams
        if ($user->relationLoaded('exams')) {
            if ($user->exams->filter(fn($e) => optional($e->pivot)->payment_id)->count() > 0) {
                return 'paid';
            }
        }

        return 'unpaid';
    }
}
