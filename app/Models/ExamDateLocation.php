<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamDateLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_date_id',
        'location_id',
        'priority',
        'current_registrations'
    ];

    protected $casts = [
        'priority' => 'integer',
        'current_registrations' => 'integer'
    ];

    /**
     * Get the exam date that owns this location assignment
     */
    public function examDate(): BelongsTo
    {
        return $this->belongsTo(ExamDate::class);
    }

    /**
     * Get the location for this assignment
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Check if this hall has available capacity
     */
    public function hasAvailableCapacity(): bool
    {
        return $this->current_registrations < $this->location->capacity;
    }

    /**
     * Get the available capacity for this hall
     */
    public function getAvailableCapacity(): int
    {
        return max(0, $this->location->capacity - $this->current_registrations);
    }

    /**
     * Increment the registration count
     */
    public function incrementRegistrations(int $count = 1): void
    {
        $this->increment('current_registrations', $count);
    }

    /**
     * Decrement the registration count
     */
    public function decrementRegistrations(int $count = 1): void
    {
        $this->decrement('current_registrations', $count);
    }
}
