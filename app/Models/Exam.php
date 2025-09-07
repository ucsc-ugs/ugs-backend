<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'organization_id',
    ];

    protected $casts = [
        'price' => 'float',
    ];

    //An exam belongs to an organization
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    // An exam can have many exam dates
    public function examDates()
    {
        return $this->hasMany(ExamDate::class);
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
