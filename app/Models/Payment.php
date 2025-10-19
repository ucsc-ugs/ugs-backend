<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'student_exam_id',
        'payment_id',
        'payhere_amount',
        'payhere_currency',
        'status_code',
        'md5sig',
        'method',
        'status_message',
        'commission_amount',
        'net_amount',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payhere_amount' => 'decimal:2',
        'status_code' => 'integer',
        'commission_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    public function studentExam()
    {
        return $this->belongsTo(StudentExam::class, 'student_exam_id');
    }
}
