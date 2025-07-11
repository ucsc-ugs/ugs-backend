<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'id',
        'local',
        'passport_nic'
    ];

    // Override the primary key behavior since we're using it as a foreign key
    public $incrementing = false;
    protected $keyType = 'int';

    public function user()
    {
        return $this->belongsTo(User::class, 'id', 'id');
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
