<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateStudentUserRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Models\User;
use Database\Factories\StudentFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Handle an authentication attempt.
     */
    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            // Load student relationship
            $user->load('student');

            // Create token
            $token = $user->createToken('auth-token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful',
                'token' => $token,
                'data' => StudentResource::make($user),

            ]);
        }

        return response()->json([
            'message' => 'Invalid credentials. Please check your email and password.',
        ], 401);
    }

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
            ]);

            // Load the student relationship
            $user->load('student');

            // Assign the student role to the user
            $user->assignRole('student');

            // Create token
            $token = $user->createToken('auth-token')->plainTextToken;

            DB::commit();
            
            return response()->json([
                'message' => 'Registration successful! Welcome to UGS.',
                'token' => $token,
                'data' => StudentResource::make($user->load('student'))
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

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    public function user(Request $request)
    {
        $user = $request->user();
        $user->load('student');

        return response()->json([
            'user' => $user
        ]);
    }
}
