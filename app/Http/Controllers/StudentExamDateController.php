<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\StudentExam;
use App\Models\ExamDate;
use App\Models\Exam;

class StudentExamDateController extends Controller
{
    /**
     * Get exam dates for the logged-in student's registered exams
     */
    public function getStudentExamDates(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Get student's registered exams with their selected exam date IDs and assigned locations
            $examDates = DB::table('student_exams')
                ->where('student_exams.student_id', $user->id)
                ->whereIn('student_exams.status', ['pending', 'registered', 'approved'])
                ->whereNotNull('student_exams.selected_exam_date_id')
                ->join('exam_dates', 'student_exams.selected_exam_date_id', '=', 'exam_dates.id')
                ->join('exams', 'exam_dates.exam_id', '=', 'exams.id')
                ->leftJoin('locations', 'student_exams.assigned_location_id', '=', 'locations.id')
                ->select(
                    'exam_dates.id',
                    'exam_dates.exam_id',
                    'exam_dates.date',
                    'exam_dates.status',
                    'exams.name as exam_title',
                    'exams.code_name as exam_code',
                    'locations.location_name as location',
                    'exam_dates.location as fallback_location'
                )
                ->orderBy('exam_dates.date', 'asc')
                ->get();

            // Format the response
            $formattedDates = $examDates->map(function ($examDate) {
                // Use assigned location if available, otherwise use exam_dates location
                $location = $examDate->location ?: ($examDate->fallback_location ?: 'Location not assigned');

                return [
                    'id' => $examDate->id,
                    'exam_id' => $examDate->exam_id,
                    'exam_title' => $examDate->exam_title,
                    'exam_code' => $examDate->exam_code,
                    'date' => $examDate->date,
                    'location' => $location,
                    'status' => $examDate->status,
                ];
            });

            return response()->json($formattedDates);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch exam dates',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
