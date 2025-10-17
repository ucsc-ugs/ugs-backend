<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ExamDateController extends Controller
{
    /**
     * Update the status of an exam date
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        // Check if user has required roles
        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:upcoming,completed,cancelled'
        ]);

        $examDate = ExamDate::with('exam')->findOrFail($id);

        // For org_admin, ensure they can only update exam dates for their organization
        if ($user->hasRole('org_admin')) {
            $user->load('orgAdmin');
            $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

            if (!$organizationId || $examDate->exam->organization_id !== $organizationId) {
                return response()->json([
                    'message' => 'Unauthorized. You can only manage exam dates for your organization.'
                ], 403);
            }
        }

        $examDate->update($validated);

        return response()->json([
            'message' => 'Exam date status updated successfully',
            'data' => $examDate->fresh()
        ]);
    }

    /**
     * Automatically update exam date statuses based on current date
     */
    public function updateExpiredStatuses(): JsonResponse
    {
        $today = Carbon::now()->startOfDay();

        // Update upcoming exams that have passed their date to completed
        $updated = ExamDate::where('status', 'upcoming')
            ->where('date', '<', $today)
            ->update(['status' => 'completed']);

        return response()->json([
            'message' => 'Exam date statuses updated automatically',
            'updated_count' => $updated
        ]);
    }

    /**
     * Get detailed information about an exam date including location and registrations
     */
    public function details(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        // Check if user has required roles
        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $examDate = ExamDate::with([
            'exam',
            'location',
            'studentExams.student',
            'studentExams.assignedLocation'
        ])->findOrFail($id);

        // For org_admin, ensure they can only view exam dates for their organization
        if ($user->hasRole('org_admin')) {
            $user->load('orgAdmin');
            $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

            if (!$organizationId || $examDate->exam->organization_id !== $organizationId) {
                return response()->json([
                    'message' => 'Unauthorized. You can only view exam dates for your organization.'
                ], 403);
            }
        }

        // Prepare location details
        $locationDetails = null;
        if ($examDate->location) {
            $locationDetails = [
                'id' => $examDate->location->id,
                'location_name' => $examDate->location->location_name,
                'capacity' => $examDate->location->capacity,
                'current_registrations' => $examDate->location->getCurrentRegistrationCount()
            ];
        }

        // Prepare registrations data
        $registrations = $examDate->studentExams->map(function ($studentExam) {
            return [
                'id' => $studentExam->id,
                'student_id' => $studentExam->student_id,
                'exam_id' => $studentExam->exam_id,
                'index_number' => $studentExam->index_number,
                'status' => $studentExam->status,
                'attended' => $studentExam->attended,
                'result' => $studentExam->result,
                'created_at' => $studentExam->created_at,
                'student' => [
                    'id' => $studentExam->student->id,
                    'name' => $studentExam->student->name,
                    'email' => $studentExam->student->email,
                    'phone' => $studentExam->student->phone,
                ],
                'assigned_location' => $studentExam->assignedLocation ? [
                    'id' => $studentExam->assignedLocation->id,
                    'location_name' => $studentExam->assignedLocation->location_name,
                    'capacity' => $studentExam->assignedLocation->capacity,
                ] : null,
            ];
        });

        return response()->json([
            'id' => $examDate->id,
            'exam_id' => $examDate->exam_id,
            'date' => $examDate->date,
            'location' => $examDate->location, // legacy location field
            'status' => $examDate->status,
            'exam' => [
                'id' => $examDate->exam->id,
                'name' => $examDate->exam->name,
                'code_name' => $examDate->exam->code_name,
                'price' => $examDate->exam->price,
            ],
            'location_details' => $locationDetails,
            'registrations' => $registrations,
        ]);
    }
}
