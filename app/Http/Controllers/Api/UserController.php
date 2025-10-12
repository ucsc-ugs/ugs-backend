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

        $exams = $user->exams()->get();

        return response()->json($exams);
    }
}
