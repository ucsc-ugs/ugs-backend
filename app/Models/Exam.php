<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code_name',
        'description',
        'price',
        'organization_id',
        'registration_deadline',
    ];

    protected $casts = [
        'price' => 'float',
        'registration_deadline' => 'datetime',
        'commission_rate' => 'float',
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

    public function students()
    {
        return $this->hasMany(StudentExam::class);
    }

     public function revenueTransactions(): HasMany
    {
        return $this->hasMany(RevenueTransaction::class);
    }

    public function calculateCommission(float $amount): float
    {
        return round(($amount * $this->commission_rate) / 100, 2);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'student_exams', 'exam_id', 'student_id')
                    ->withPivot('payment_id', 'status', 'attended', 'result')
                    ->withTimestamps();
    }

}
