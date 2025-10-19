<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentExam extends Model
{
    protected $fillable = [
        'index_number',
        'student_id',
        'exam_id',
        'selected_exam_date_id',
        'assigned_location_id',
        'payment_id',
        'status',
        'attended',
        'result'
    ];

    public function student()
    {
        return $this->belongsTo(User::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function selectedExamDate()
    {
        return $this->belongsTo(ExamDate::class, 'selected_exam_date_id');
    }

    public function assignedLocation()
    {
        return $this->belongsTo(Location::class, 'assigned_location_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function revenueTransaction()
    {
        return $this->hasOne(RevenueTransaction::class);
    }
}
