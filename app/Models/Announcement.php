<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    // ...existing code...
    public function announcementReads()
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    protected $fillable = [
        'title',
        'message',
        'audience',
        'exam_id',
        'expiry_date',
        'publish_date',
        'status',
        'priority',
        'category',
        'tags',
        'is_pinned',
        'created_by',
    ];

    protected $casts = [
        'tags' => 'array',
    ];
}
