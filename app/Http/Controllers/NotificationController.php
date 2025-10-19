<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Announcement;
use App\Models\StudentExam;

class NotificationController extends Controller
{
    // Get notifications for the logged-in student
    public function index(Request $request)
    {
        try {
            // Try to get student_id from query parameter (for public access) or from authenticated user
            $studentId = $request->query('student_id');

            // If no student_id in query and user is authenticated, use authenticated user's ID
            if (!$studentId && Auth::check()) {
                $user = Auth::user();
                // Assuming the user model has a student_id or id field
                $studentId = $user->student_id ?? $user->id;
                Log::info('Using authenticated user ID:', ['student_id' => $studentId]);
            }

            // If still no student_id, return all general announcements only
            if (!$studentId) {
                Log::info('No student ID provided, returning general announcements only');
                $now = now();
                $allAnnouncements = Announcement::where('status', 'published')
                    ->where('audience', 'all')
                    ->where('expiry_date', '>=', $now)
                    ->get();

                $result = $allAnnouncements->map(function ($announcement) {
                    return [
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
                });

                return response()->json($result, 200);
            }

            $now = now();

            // General announcements (audience=all, published, not expired)
            // Limit to most recent 50 announcements to improve performance
            $allAnnouncements = Announcement::where('status', 'published')
                ->where('audience', 'all')
                ->where('expiry_date', '>=', $now)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            Log::info('General announcements found:', ['count' => $allAnnouncements->count()]);

            // Get all exam_ids the student is registered for
            $studentExamIds = StudentExam::where('student_id', $studentId)
                ->pluck('exam_id')
                ->toArray();

            Log::info('Student exam IDs:', ['student_id' => $studentId, 'exam_ids' => $studentExamIds]);

            // Exam-specific announcements (audience=exam-specific, published, not expired, exam_id in student's registered exams)
            $examAnnouncements = collect([]);
            if (!empty($studentExamIds)) {
                $examAnnouncements = Announcement::where('status', 'published')
                    ->where('audience', 'exam-specific')
                    ->whereIn('exam_id', $studentExamIds)
                    ->where('expiry_date', '>=', $now)
                    ->orderBy('created_at', 'desc')
                    ->limit(50)
                    ->get();

                Log::info('Exam-specific announcements found:', ['count' => $examAnnouncements->count()]);
            } else {
                Log::info('Student has no registered exams');
            }

            // Get exam details for all relevant exam_ids
            $allExamIds = $examAnnouncements->pluck('exam_id')->unique()->toArray();
            $examDetails = [];
            if (!empty($allExamIds)) {
                $examDetails = \App\Models\Exam::whereIn('id', $allExamIds)
                    ->get()
                    ->keyBy('id')
                    ->toArray();
            }


            // Get all announcement IDs
            $allAnnouncementIds = collect($allAnnouncements)->pluck('id')->merge($examAnnouncements->pluck('id'))->unique()->toArray();
            // Get read announcements for this student
            $readIds = [];
            if ($studentId) {
                $readIds = \App\Models\AnnouncementRead::where('student_id', $studentId)
                    ->whereIn('announcement_id', $allAnnouncementIds)
                    ->pluck('announcement_id')
                    ->toArray();
            }

            // Merge and sort by created_at DESC (most recent first)
            $merged = collect($allAnnouncements)->merge($examAnnouncements)
                ->sortByDesc('created_at')
                ->values();

            // Format response with essential details and read status
            $result = $merged->map(function ($announcement) use ($examDetails, $readIds) {
                // Truncate message to 200 characters for list view (full message available on detail view)
                $message = $announcement->message;
                $truncatedMessage = strlen($message) > 200
                    ? substr($message, 0, 200) . '...'
                    : $message;

                $data = [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'message' => $truncatedMessage,
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
                    'is_read' => in_array($announcement->id, $readIds),
                ];

                // Add exam title if exam-specific
                if ($announcement->exam_id && isset($examDetails[$announcement->exam_id])) {
                    $data['exam_title'] = $examDetails[$announcement->exam_id]['name'];
                    $data['exam_code'] = $examDetails[$announcement->exam_id]['code_name'];
                }

                return $data;
            });

            Log::info('Returning notifications:', [
                'total_count' => $result->count(),
                'general_count' => $allAnnouncements->count(),
                'exam_specific_count' => $examAnnouncements->count()
            ]);

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

    /**
     * Get exam details by exam ID (for notification modal)
     */
    public function examDetails($id)
    {
        $exam = \App\Models\Exam::find($id);
        if (!$exam) {
            return response()->json(['error' => 'Exam not found'], 404);
        }
        return response()->json($exam);
    }

    /**
     * Get full announcement details by ID
     */
    public function show($id)
    {
        try {
            $announcement = Announcement::with('exam')->find($id);

            if (!$announcement) {
                return response()->json(['error' => 'Announcement not found'], 404);
            }

            $data = [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'message' => $announcement->message, // Full message
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

            // Add exam details if available
            if ($announcement->exam) {
                $data['exam_title'] = $announcement->exam->name;
                $data['exam_code'] = $announcement->exam->code_name;
            }

            return response()->json($data, 200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch announcement: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch announcement'], 500);
        }
    }
}
