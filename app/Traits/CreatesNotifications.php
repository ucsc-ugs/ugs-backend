<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Carbon\Carbon;

trait CreatesNotifications
{
    /**
     * Create a notification record in the notifications table.
     *
     * @param string $title
     * @param string $message
     * @param int|null $examId
     * @param int|null $userId
     * @param bool $isForAll
     * @return int|array|null  Returns the created notification ID or record, or null on failure
     */
    public function createNotification($title, $message, $examId = null, $userId = null, $isForAll = false)
    {
        try {
            $now = Carbon::now();
            $data = [
                'title' => $title,
                'message' => $message,
                'exam_id' => $examId,
                'user_id' => $userId,
                'is_for_all' => $isForAll,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $id = DB::table('notifications')->insertGetId($data);
            return $id;
        } catch (Exception $e) {
            Log::error('Failed to create notification: ' . $e->getMessage());
            return null;
        }
    }
}
