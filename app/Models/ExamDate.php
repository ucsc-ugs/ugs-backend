<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamDate extends Model
{
    protected $fillable = [
        'exam_id',
        'date',
        'location',
        'status'
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}
