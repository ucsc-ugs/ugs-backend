<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'message',
        'audience',
        'exam_id',
        'department_id',
        'year_level',
        'expiry_date',
        'publish_date',
        'status',
        'priority',
        'category',
        'tags',
        'is_pinned',
        'notifications_enabled',
        'email_notifications_enabled',
        'sms_notifications_enabled',
        'push_notifications_enabled',
        'created_by',
    ];

    protected $casts = [
        'tags' => 'array',
    ];
}
