<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class StudentExamResource extends JsonResource
{
    
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'index_number' => $this->index_number,
            'student_id' => $this->student_id,
            'exam_id' => $this->exam_id,
            'payment_id' => $this->payment_id,
            'status' => $this->status,
            'attended' => (bool) $this->attended,
            'result' => $this->result,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            //'exam' => new ExamResource($this->whenLoaded('exam')),
            //'payment' => new PaymentResource($this->whenLoaded('payment')),
        ];
    }
}