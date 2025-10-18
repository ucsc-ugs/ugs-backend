<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\StudentExam;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;

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

    /**
     * Register a student for an exam date with automatic hall assignment
     */
    public function registerForExamDate(Request $request)
    {
        $request->validate([
            'exam_date_id' => 'required|exists:exam_dates,id',
            'student_id' => 'required|exists:students,id'
        ]);

        try {
            DB::beginTransaction();

            $examDate = \App\Models\ExamDate::with('locations')->findOrFail($request->exam_date_id);

            // Check if student is already registered for this exam date
            $existingRegistration = StudentExam::where('student_id', $request->student_id)
                ->where('selected_exam_date_id', $request->exam_date_id)
                ->first();

            if ($existingRegistration) {
                return response()->json([
                    'message' => 'Student is already registered for this exam date'
                ], 422);
            }

            // Auto-assign hall based on priority and capacity
            $assignedLocationId = null;

            if ($examDate->locations && $examDate->locations->count() > 0) {
                foreach ($examDate->locations->sortBy('pivot.priority') as $location) {
                    // Count current registrations for this location
                    $currentRegistrations = StudentExam::where('selected_exam_date_id', $request->exam_date_id)
                        ->where('assigned_location_id', $location->id)
                        ->count();

                    // If this location has capacity, assign student here
                    if ($currentRegistrations < $location->capacity) {
                        $assignedLocationId = $location->id;
                        break;
                    }
                }
            }

            if (!$assignedLocationId) {
                return response()->json([
                    'message' => 'No available seats in any hall for this exam date'
                ], 422);
            }

            // Generate unique index number
            $indexNumber = 'STU' . str_pad($request->student_id, 4, '0', STR_PAD_LEFT) . date('Ymd');

            // Create the registration
            $studentExam = StudentExam::create([
                'student_id' => $request->student_id,
                'exam_id' => $examDate->exam_id,
                'selected_exam_date_id' => $request->exam_date_id,
                'assigned_location_id' => $assignedLocationId,
                'index_number' => $indexNumber,
                'status' => 'pending',
                'attended' => false
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Student registered successfully',
                'data' => $studentExam->load(['selectedExamDate', 'assignedLocation', 'student'])
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create sample students for testing (admin only)
     */
    public function createSampleStudents(Request $request)
    {
        try {
            $studentsCreated = 0;

            // Create 10 sample students
            for ($i = 1; $i <= 10; $i++) {
                // Check if student already exists
                $existingStudent = \App\Models\Student::find($i);

                if (!$existingStudent) {
                    // Create user first
                    $user = \App\Models\User::create([
                        'name' => "Test Student $i",
                        'email' => "student{$i}@test.com",
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                    ]);

                    // Create student record
                    \App\Models\Student::create([
                        'id' => $user->id,
                        'local' => 'local',
                        'passport_nic' => "NIC{$i}234567890"
                    ]);

                    $studentsCreated++;
                }
            }

            return response()->json([
                'message' => "$studentsCreated sample students created successfully",
                'total_students' => \App\Models\Student::count()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create sample students: ' . $e->getMessage()
            ], 500);
        }
    }
}
