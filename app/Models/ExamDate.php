<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ExamDate extends Model
{
    protected $fillable = [
        'exam_id',
        'date',
        'location',
        'location_id',
        'status'
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get all locations assigned to this exam date
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'exam_date_locations')
            ->withPivot('priority', 'current_registrations')
            ->withTimestamps()
            ->orderBy('priority');
    }

    /**
     * Get the exam date location pivot records
     */
    public function examDateLocations(): HasMany
    {
        return $this->hasMany(ExamDateLocation::class)->orderBy('priority');
    }

    /**
     * Get the next available hall for student assignment
     */
    public function getNextAvailableHall(): ?ExamDateLocation
    {
        return $this->examDateLocations()
            ->with('location')
            ->get()
            ->first(function ($examDateLocation) {
                return $examDateLocation->hasAvailableCapacity();
            });
    }

    /**
     * Get total capacity across all halls
     */
    public function getTotalCapacity(): int
    {
        return $this->locations()->sum('capacity');
    }

    /**
     * Get total current registrations across all halls
     */
    public function getTotalRegistrations(): int
    {
        return $this->examDateLocations()->sum('current_registrations');
    }

    /**
     * Check if there's any available capacity across all halls
     */
    public function hasAvailableCapacity(): bool
    {
        return $this->getTotalRegistrations() < $this->getTotalCapacity();
    }
}
