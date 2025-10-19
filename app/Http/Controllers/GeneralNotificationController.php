<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\StudentExam;

class GeneralNotificationController extends Controller
{
    /**
     * Get general notifications for the logged-in user
     * Returns:
     * - General notifications (is_for_all = true)
     * - User-specific notifications (user_id matches logged-in user)
     * - Exam-specific notifications (exam_id matches user's registered exams)
     */
    public function index(Request $request)
    {
        try {
            // Get authenticated user
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $userId = $user->id;
            Log::info('Fetching general notifications for user:', ['user_id' => $userId]);

            // Use user's ID directly as student_id for checking registered exams
            $studentId = $userId;

            // Get all exam_ids the student is registered for
            $studentExamIds = StudentExam::where('student_id', $studentId)
                ->pluck('exam_id')
                ->toArray();

            Log::info('Student exam IDs:', [
                'student_id' => $studentId,
                'exam_ids' => $studentExamIds
            ]);

            // Fetch notifications from the notifications table
            $query = DB::table('notifications')
                ->where(function ($query) use ($userId, $studentExamIds) {
                    // General notifications for all users
                    $query->where('is_for_all', true)
                        // OR user-specific notifications
                        ->orWhere('user_id', $userId);

                    // OR exam-specific notifications (only if user is registered for those exams)
                    if (!empty($studentExamIds)) {
                        $query->orWhere(function ($subQuery) use ($studentExamIds) {
                            $subQuery->whereIn('exam_id', $studentExamIds)
                                ->whereNotNull('exam_id')
                                ->where('is_for_all', false)
                                ->whereNull('user_id');
                        });
                    }
                })
                ->orderBy('created_at', 'desc');

            $notifications = $query->get();

            Log::info('General notifications found:', ['count' => $notifications->count()]);

            // Get exam details for exam-specific notifications
            $examIds = $notifications->pluck('exam_id')->filter()->unique()->toArray();
            $examDetails = [];
            if (!empty($examIds)) {
                $examDetails = \App\Models\Exam::whereIn('id', $examIds)
                    ->get()
                    ->keyBy('id')
                    ->toArray();
            }

            // Get read status for this user
            $notificationIds = $notifications->pluck('id')->toArray();
            $readNotificationIds = [];
            if (!empty($notificationIds)) {
                $readNotificationIds = DB::table('notification_reads')
                    ->where('user_id', $userId)
                    ->whereIn('notification_id', $notificationIds)
                    ->pluck('notification_id')
                    ->toArray();
            }

            // Format response
            $result = $notifications->map(function ($notification) use ($examDetails, $readNotificationIds) {
                $data = [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'exam_id' => $notification->exam_id,
                    'user_id' => $notification->user_id,
                    'is_for_all' => $notification->is_for_all,
                    'created_at' => $notification->created_at,
                    'is_read' => in_array($notification->id, $readNotificationIds),
                ];

                // Add exam details if exam-specific
                if ($notification->exam_id && isset($examDetails[$notification->exam_id])) {
                    $data['exam_title'] = $examDetails[$notification->exam_id]['name'];
                    $data['exam_code'] = $examDetails[$notification->exam_id]['code_name'];
                }

                return $data;
            });

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('General notification fetch error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'error' => 'Failed to fetch general notifications',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark a single notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $userId = $user->id;

            // Check if notification exists
            $notification = DB::table('notifications')->where('id', $id)->first();
            if (!$notification) {
                return response()->json(['error' => 'Notification not found'], 404);
            }

            // Check if already marked as read
            $exists = DB::table('notification_reads')
                ->where('user_id', $userId)
                ->where('notification_id', $id)
                ->exists();

            if (!$exists) {
                DB::table('notification_reads')->insert([
                    'user_id' => $userId,
                    'notification_id' => $id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Log::info('Notification marked as read:', ['user_id' => $userId, 'notification_id' => $id]);

            return response()->json(['message' => 'Notification marked as read'], 200);
        } catch (\Exception $e) {
            Log::error('Mark as read error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to mark notification as read',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all notifications as read for the logged-in user
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $userId = $user->id;
            $studentId = $userId;

            // Get all exam_ids the student is registered for
            $studentExamIds = StudentExam::where('student_id', $studentId)
                ->pluck('exam_id')
                ->toArray();

            // Get all notification IDs for this user
            $notificationIds = DB::table('notifications')
                ->where(function ($query) use ($userId, $studentExamIds) {
                    $query->where('is_for_all', true)
                        ->orWhere('user_id', $userId);

                    if (!empty($studentExamIds)) {
                        $query->orWhere(function ($subQuery) use ($studentExamIds) {
                            $subQuery->whereIn('exam_id', $studentExamIds)
                                ->whereNotNull('exam_id')
                                ->where('is_for_all', false)
                                ->whereNull('user_id');
                        });
                    }
                })
                ->pluck('id')
                ->toArray();

            if (empty($notificationIds)) {
                return response()->json(['message' => 'No notifications to mark as read'], 200);
            }

            // Get already read notification IDs
            $alreadyReadIds = DB::table('notification_reads')
                ->where('user_id', $userId)
                ->whereIn('notification_id', $notificationIds)
                ->pluck('notification_id')
                ->toArray();

            // Filter out already read notifications
            $unreadIds = array_diff($notificationIds, $alreadyReadIds);

            if (empty($unreadIds)) {
                return response()->json(['message' => 'All notifications already marked as read'], 200);
            }

            // Insert read records for unread notifications
            $insertData = [];
            foreach ($unreadIds as $notificationId) {
                $insertData[] = [
                    'user_id' => $userId,
                    'notification_id' => $notificationId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('notification_reads')->insert($insertData);

            Log::info('All notifications marked as read:', [
                'user_id' => $userId,
                'count' => count($unreadIds)
            ]);

            return response()->json([
                'message' => 'All notifications marked as read',
                'count' => count($unreadIds)
            ], 200);
        } catch (\Exception $e) {
            Log::error('Mark all as read error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to mark all notifications as read',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
