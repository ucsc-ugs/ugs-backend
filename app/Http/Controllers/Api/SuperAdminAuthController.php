<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class SuperAdminAuthController extends Controller
{
    /**
     * Super Admin Login
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required']
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            
            // Check if user is a super admin
            if (!$user->isSuperAdmin()) {
                Auth::logout();
                return response()->json([
                    'message' => 'Unauthorized. Super admin access required.',
                ], 403);
            }

            // Create token for super admin
            $token = $user->createToken('super-admin-token')->plainTextToken;
            
            $user->load(['roles']);

            return response()->json([
                'message' => 'Super admin login successful',
                'user' => $user,
                'token' => $token,
                'user_type' => 'super_admin'
            ]);
        }

        return response()->json([
            'message' => 'Invalid credentials. Please check your email and password.',
        ], 401);
    }

    /**
     * Super Admin Logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Super admin logged out successfully'
        ]);
    }

    /**
     * Get current super admin user
     */
    public function user(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        
        $user->load(['roles']);

        return response()->json([
            'user' => $user
        ]);
    }

    /**
     * Create the first super admin (setup)
     */
    public function createSuperAdmin(Request $request)
    {
        // Check if any super admin already exists
        $existingSuperAdmin = User::role('super_admin')->first();
        if ($existingSuperAdmin) {
            return response()->json([
                'message' => 'Super admin already exists. Cannot create another one.',
            ], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8']
        ]);

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password'])
            ]);

            // Assign super admin role
            $user->assignRole('super_admin');

            $token = $user->createToken('super-admin-token')->plainTextToken;

            DB::commit();

            return response()->json([
                'message' => 'Super admin created successfully',
                'user' => $user,
                'token' => $token
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Super admin creation error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to create super admin',
                'errors' => ['general' => 'Something went wrong during creation.']
            ], 500);
        }
    }
}
