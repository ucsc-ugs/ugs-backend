<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    protected $fillable = [
        'name',
        'description'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function dates()
    {
        return $this->hasMany(ExamDate::class);
    }

    public function studentExams()
    {
        return $this->hasMany(StudentExam::class);
    }
}
