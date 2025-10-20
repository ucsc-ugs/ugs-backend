<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

            // Create token
            $token = $user->createToken('auth-token')->plainTextToken;

            // Check if user is super admin (accessed via admin endpoint)
            if ($request->is('api/admin/*')) {
                // Verify user has super admin role
                if (!($user->hasRole('super_admin') || $user->hasRole('org_admin'))) {
                    Auth::logout();
                    return response()->json([
                        'message' => 'Access denied. Super admin privileges required.',
                    ], 403);
                }

                $userResource = UserResource::make($user)->toArray($request) ;
                return response()->json([
                    'message' => 'Admin login successful',
                    'token' => $token,
                ] + $userResource);
            }

            // Regular student login
            $user->load('student');

            $userResource = UserResource::make($user)->toArray($request);
            return response()->json([
                'message' => 'Login successful',
                'token' => $token,
            ] + $userResource);
        }

        return response()->json([
            'message' => 'Invalid credentials. Please check your email and password.',
        ], 401);
    }

    /**
     * Logout the authenticated user.
     * Deletes the user's tokens.
     */
    public function logout(Request $request)
    {
        $request->user()->token()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Check if the authenticated user's email is verified.
     */
    public function checkEmailVerified(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'verified' => !is_null($user->email_verified_at)
        ]);
    }
}
