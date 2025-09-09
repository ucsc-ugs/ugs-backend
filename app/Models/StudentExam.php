<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentExam extends Model
{
    protected $fillable = [
        'index_number',
        'student_id',
        'exam_id',
        'payment_id',
        'status',
        'attended',
        'result'
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
}
