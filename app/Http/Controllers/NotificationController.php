<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Notification;
use App\Models\Announcement;
use App\Models\StudentExam;

class NotificationController extends Controller
{
    // Get notifications for the logged-in student
    public function index(Request $request)
    {
        try {
            $studentId = $request->query('student_id');
            $examIds = [];
            if ($studentId) {
                $examIds = StudentExam::where('student_id', $studentId)
                    ->pluck('exam_id')
                    ->toArray();
            }

            $now = now();

            // Get published announcements for all students that haven't expired
            $allAnnouncements = Announcement::where('status', 'published')
                ->where('audience', 'all')
                ->where('expiry_date', '>=', $now)
                ->get();

            // Get published exam-specific announcements for exams the student is registered for
            $examAnnouncements = [];
            if (!empty($examIds)) {
                $examAnnouncements = Announcement::where('status', 'published')
                    ->where('audience', 'exam-specific')
                    ->whereIn('exam_id', $examIds)
                    ->where('expiry_date', '>=', $now)
                    ->get();
            }

            // Merge and sort by created_at DESC (most recent first)
            $merged = collect($allAnnouncements)->merge($examAnnouncements)
                ->sortByDesc('created_at')
                ->values();

            // Get exam details for exam-specific announcements
            $examDetails = [];
            if (!empty($examIds)) {
                $examDetails = \App\Models\Exam::whereIn('id', $examIds)
                    ->get()
                    ->keyBy('id')
                    ->toArray();
            }

            // Format response with essential details
            $result = $merged->map(function ($announcement) use ($examDetails) {
                $data = [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'message' => $announcement->message,
                    'audience' => $announcement->audience,
                    'exam_id' => $announcement->exam_id,
                    'publish_date' => $announcement->publish_date
                        ? (is_object($announcement->publish_date) ? $announcement->publish_date->format('Y-m-d H:i:s') : (string)$announcement->publish_date)
                        : (is_object($announcement->created_at) ? $announcement->created_at->format('Y-m-d H:i:s') : (string)$announcement->created_at),
                    'expiry_date' => $announcement->expiry_date
                        ? (is_object($announcement->expiry_date) ? $announcement->expiry_date->format('Y-m-d H:i:s') : (string)$announcement->expiry_date)
                        : null,
                    'status' => $announcement->status,
                    'priority' => $announcement->priority ?? 'medium',
                    'category' => $announcement->category ?? 'general',
                    'is_pinned' => $announcement->is_pinned ?? false,
                    'created_at' => is_object($announcement->created_at) ? $announcement->created_at->format('Y-m-d H:i:s') : (string)$announcement->created_at,
                    'tags' => $announcement->tags ?? [],
                ];

                // Add exam title if exam-specific
                if ($announcement->exam_id && isset($examDetails[$announcement->exam_id])) {
                    $data['exam_title'] = $examDetails[$announcement->exam_id]['name'];
                    $data['exam_code'] = $examDetails[$announcement->exam_id]['code_name'];
                }

                return $data;
            });

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Notification fetch error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'error' => 'Failed to fetch notifications',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
