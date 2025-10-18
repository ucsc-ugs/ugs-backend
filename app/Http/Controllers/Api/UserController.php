<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Get the authenticated user.
     */
    public function user(Request $request)
    {
        $user = $request->user();
        $user->load(['student', 'orgAdmin']);

        return UserResource::make($user);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(UpdateUserRequest $request)
    {
        $user = Auth::user();

        $validated = $request->validated();

        // Remove password fields if they exist (these should be handled separately)
        unset($validated['current_password'], $validated['password'], $validated['password_confirmation']);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => UserResource::make($user->fresh()->load('student'))
        ]);
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Verify current password
        if (!password_verify($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 400);
        }

        // Update password
        $user->update([
            'password' => bcrypt($validated['password'])
        ]);

        return response()->json([
            'message' => 'Password updated successfully'
        ]);
    }

    /**
     * Profile soft delete should be implemented.
     */

    public function myExams()
    {
        $user = Auth::user();

        // Get distinct exams with their registration details
        $studentExams = \App\Models\StudentExam::with(['exam.organization', 'exam.examDates', 'selectedExamDate', 'assignedLocation'])
            ->where('student_id', $user->id)
            ->get();

        // Transform the data to include exam details with pivot data
        $exams = $studentExams->map(function ($studentExam) {
            $exam = $studentExam->exam;

            // Get all available exam dates for this exam (for rescheduling)
            $availableDates = $exam->examDates->map(function ($examDate) {
                return [
                    'id' => $examDate->id,
                    'date' => $examDate->date,
                    'location' => $examDate->location,
                    'status' => $examDate->status,
                ];
            });

            $exam->pivot = [
                'student_id' => $studentExam->student_id,
                'exam_id' => $studentExam->exam_id,
                'payment_id' => $studentExam->payment_id,
                'status' => $studentExam->status,
                'attended' => $studentExam->attended,
                'result' => $studentExam->result,
                'index_number' => $studentExam->index_number,
                'created_at' => $studentExam->created_at,
                'updated_at' => $studentExam->updated_at,
                'selected_exam_date' => $studentExam->selectedExamDate,
                'selected_exam_date_id' => $studentExam->selected_exam_date_id,
                'assigned_location' => $studentExam->assignedLocation,
                'assigned_location_id' => $studentExam->assigned_location_id,
            ];

            // Add available dates for rescheduling
            $exam->available_exam_dates = $availableDates;

            return $exam;
        });

        return response()->json($exams);
    }

    /**
     * Reschedule an exam to a different date
     */
    public function rescheduleExam(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'exam_id' => 'required|exists:exams,id',
            'new_exam_date_id' => 'required|exists:exam_dates,id'
        ]);

        // Find the student's exam registration
        $studentExam = \App\Models\StudentExam::where('student_id', $user->id)
            ->where('exam_id', $validated['exam_id'])
            ->first();

        if (!$studentExam) {
            return response()->json([
                'message' => 'Exam registration not found'
            ], 404);
        }

        // Check if the student can reschedule (e.g., only pending exams can be rescheduled)
        if ($studentExam->status !== 'pending' && $studentExam->status !== 'registered') {
            return response()->json([
                'message' => 'Cannot reschedule this exam. Only pending registrations can be rescheduled.'
            ], 400);
        }

        // Verify that the new exam date belongs to the same exam
        $newExamDate = \App\Models\ExamDate::where('id', $validated['new_exam_date_id'])
            ->where('exam_id', $validated['exam_id'])
            ->first();

        if (!$newExamDate) {
            return response()->json([
                'message' => 'Invalid exam date selected'
            ], 400);
        }

        // Update the selected exam date
        $studentExam->update([
            'selected_exam_date_id' => $validated['new_exam_date_id']
        ]);

        // Load the updated data
        $studentExam->load(['exam', 'selectedExamDate']);

        return response()->json([
            'message' => 'Exam rescheduled successfully',
            'data' => $studentExam
        ]);
    }
}
