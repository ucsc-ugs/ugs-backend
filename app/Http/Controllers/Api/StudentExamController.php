<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StudentExam;

class StudentExamController extends Controller
{
    public function myExams(Request $request)
    {
        // Get authenticated student ID
        $studentId = $request->user()->id; // make sure this is the correct student ID

        // Fetch exams with payment info
        $studentExams = StudentExam::with(['exam', 'payment'])
            ->where('student_id', $studentId)
            ->get();

        return response()->json($studentExams);
    }
}
