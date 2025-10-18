<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organization_id',
        'location_name',
        'capacity'
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];

    // Location belongs to an organization
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    // Location can have many exam dates
    public function examDates()
    {
        return $this->hasMany(ExamDate::class);
    }

    // Get assigned students for this location
    public function assignedStudents()
    {
        return $this->hasMany(StudentExam::class, 'assigned_location_id');
    }

    // Get current registration count for this location
    public function getCurrentRegistrationCount()
    {
        return $this->assignedStudents()->count();
    }

    // Check if location has available capacity
    public function hasAvailableCapacity()
    {
        return $this->getCurrentRegistrationCount() < $this->capacity;
    }
}
