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
        'organization_id',
    ];

    // protected $casts = [
    //     'starts_at' => 'datetime',
    //     'ends_at' => 'datetime',
    // ];

    //An exam belongs to an organization
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
