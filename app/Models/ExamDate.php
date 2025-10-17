<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamDate extends Model
{
    protected $fillable = [
        'exam_id',
        'date',
        'location',
        'location_id',
        'status'
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
