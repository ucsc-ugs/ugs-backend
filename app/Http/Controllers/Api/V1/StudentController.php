<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UpdateUserRequest;
use App\Http\Requests\V1\CreateStudentUserRequest;
use App\Http\Resources\V1\StudentResource;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class StudentController extends Controller
{
    /**
     * Create a new student account
     */
    public function studentRegister(CreateStudentUserRequest $request)
    {
        $validated = $request->validated();

        try {
            $student = Student::create([
                'local' => $validated['local'],
                'passport_nic' => $validated['passport_nic'],
            ]);

            // Associate the student with a user
            $student->user()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
            ]);
        } catch (\Exception $e) {
            if (isset($student)) {
                $student->delete();
            }
            return response()->json([
                'message' => 'Failed to register student',
                'error' => $e->getMessage()
            ], 500);
        }

        // Assign the student role to the user
        $student->user->assignRole('student');

        return response()->json([
            'message' => 'Student registered successfully',
            'data' => StudentResource::make($student->user->load('student'))
        ]);
    }

    /**
     * Get the authenticated user's profile.
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProfile()
    {
        $userId = Auth::user()->id;
        $user = User::findOrFail($userId);
        
        return response()->json([
            'message' => 'Profile retrieved successfully',
            'data' => StudentResource::make($user->load('student'))
        ]);
    }

    /**
     * Update the authenticated user's profile.
     * 
     * @param UpdateCandidateUserRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(UpdateUserRequest $request)
    {
        $user = Auth::user();

        $validated = $request->validated();

        // Remove password fields if they exist (these should be handled separately)
        unset($validated['current_password'], $validated['password'], $validated['password_confirmation']);

        $user->update($validated);
        $user->student?->update($request->only(['local', 'passport_nic']));

        return response()->json([
            'message' => 'Profile updated successfully',
            'data' => StudentResource::make($user->fresh()->load('student'))
        ]);
    }

    public function updatePassword(UpdateUserRequest $request)
    {
        $user = Auth::user();
        $validated = $request->validated();

        // Verify current password
        if (!password_verify($validated['current_password'], $user->password_hash)) {
            return response()->json([
                'message' => 'Current password is incorrect'
            ], 400);
        }

        // Update password
        $user->update([
            'password_hash' => bcrypt($validated['password'])
        ]);

        return response()->json([
            'message' => 'Password updated successfully'
        ]);
    }

    /**
     * Profile soft delete should be implemented.
     */
}
