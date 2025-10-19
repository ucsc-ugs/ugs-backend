<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueTransaction extends Model
{
    protected $fillable = [
        'student_exam_id',
        'organization_id',
        'exam_id',
        'revenue',
        'commission',
        'net_revenue',
        'transaction_reference',
        'status',
        'transaction_date',
    ];

    protected $casts = [
        'revenue' => 'decimal:2',
        'commission' => 'decimal:2',
        'net_revenue' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    public function studentExam(): BelongsTo
    {
        return $this->belongsTo(StudentExam::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}