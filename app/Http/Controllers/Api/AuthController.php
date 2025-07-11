<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
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
                'user' => $user,
                'token' => $token,
            ]);
        }

        return response()->json([
            'message' => 'Invalid credentials. Please check your email and password.',
        ], 401);
    }

    public function register(Request $request)
    {
        // Validate input
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'local' => ['required', 'boolean'],
            'passport_nic' => ['required', 'string', 'max:255'],
        ]);

        try {
            DB::beginTransaction();

            // Create the user
            $user = \App\Models\User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
            ]);

            // Create the student record with the same ID
            $student = \App\Models\Student::create([
                'id' => $user->id,
                'local' => $data['local'],
                'passport_nic' => $data['passport_nic'],
            ]);

            // Load the student relationship
            $user->load('student');

            // Create token
            $token = $user->createToken('auth-token')->plainTextToken;

            DB::commit();

            return response()->json([
                'message' => 'Registration successful! Welcome to UGS.',
                'user' => $user,
                'token' => $token,
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();

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
                'errors' => ['general' => 'Something went wrong during registration.']
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
