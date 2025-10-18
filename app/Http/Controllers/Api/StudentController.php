<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateStudentUserRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentController extends Controller
{
    /**
     * Create a new student account
     */
    public function register(CreateStudentUserRequest $request)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            // Create the student record with the same ID
            $student = \App\Models\Student::create([
                'local' => $validated['local'],
                'passport_nic' => $validated['passport_nic'],
            ]);

            // Create the user
            $user = \App\Models\User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'student_id' => $student->id,
                'user_type' => 'student',
            ]);

            // Load the student relationship
            $user->load('student');

            // Assign the student role to the user
            $user->assignRole('student');

            // Send email verification notification
            $user->sendEmailVerificationNotification();

            // Create token
            $token = $user->createToken('auth-token')->plainTextToken;

            DB::commit();
            
            return response()->json([
                'message' => 'Registration successful! Please check your email to verify your account.',
                'token' => $token,
                'data' => UserResource::make($user->load('student'))
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();

            if (isset($student)) {
                $student->delete();
            }

            // Log the actual error for debugging
            Log::error('Registration error: ' . $e->getMessage());
            Log::error('Registration error trace: ' . $e->getTraceAsString());

            // Check for specific database constraint violations
            if (str_contains($e->getMessage(), 'users_email_unique')) {
                return response()->json([
                    'message' => 'Registration failed.',
                    'errors' => ['email' => 'This email address is already registered.']
                ], 422);
            }

            if (str_contains($e->getMessage(), 'students_passport_nic_unique')) {
                return response()->json([
                    'message' => 'Registration failed.',
                    'errors' => ['passport_nic' => 'This NIC/Passport number is already registered.']
                ], 422);
            }

            return response()->json([
                'message' => 'Registration failed. Please try again.',
                'errors' => [
                    'general' => 'Something went wrong during registration.',
                    'error' => $e->getMessage() // Include the error message for debugging
                ]
            ], 500);
        }
    }

    
}
