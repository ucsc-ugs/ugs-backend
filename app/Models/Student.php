<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'local',
        'passport_nic'
    ];

    public function user()
    {
        return $this->hasOne(User::class, 'student_id');
    }

    // If you want to maintain a primary user relationship
    public function primaryUser()
    {
        return $this->hasOne(User::class, 'student_id');
    }

    public function exams()
    {
        return $this->hasMany(StudentExam::class);
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }
}
