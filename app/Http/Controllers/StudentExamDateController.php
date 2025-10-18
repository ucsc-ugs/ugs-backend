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

            // Get student's registered exams (pending or registered status)
            $studentExams = StudentExam::where('student_id', $user->id)
                ->whereIn('status', ['pending', 'registered', 'approved'])
                ->pluck('exam_id');

            if ($studentExams->isEmpty()) {
                return response()->json([]);
            }

            // Get exam dates for those exams with exam details
            $examDates = DB::table('exam_dates')
                ->join('exams', 'exam_dates.exam_id', '=', 'exams.id')
                ->whereIn('exam_dates.exam_id', $studentExams)
                ->select(
                    'exam_dates.id',
                    'exam_dates.exam_id',
                    'exam_dates.date',
                    'exam_dates.location',
                    'exam_dates.status',
                    'exams.name as exam_title',
                    'exams.code_name as exam_code'
                )
                ->orderBy('exam_dates.date', 'asc')
                ->get();

            // Format the response
            $formattedDates = $examDates->map(function ($examDate) {
                return [
                    'id' => $examDate->id,
                    'exam_id' => $examDate->exam_id,
                    'exam_title' => $examDate->exam_title,
                    'exam_code' => $examDate->exam_code,
                    'date' => $examDate->date,
                    'location' => $examDate->location,
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
