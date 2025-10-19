<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrgAdmin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class SuperAdminController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function dashboard(Request $request)
    {
        // Check if user is super admin
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized. Super admin access required.'], 403);
        }

        $stats = [
            'total_organizations' => Organization::count(),
            'total_org_admins' => OrgAdmin::count(),
            'total_students' => User::whereHas('student')->count(),
            'recent_organizations' => Organization::latest()->take(5)->get(),
            'recent_org_admins' => OrgAdmin::with(['user', 'organization'])->latest()->take(5)->get(),
        ];

        return response()->json([
            'message' => 'Dashboard data retrieved successfully',
            'data' => $stats
        ]);
    }

    /**
     * Get all organizations
     */
    public function getOrganizations(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized. Super admin access required.'], 403);
        }

        $organizations = Organization::withCount('orgAdmins')->get();
        return response()->json([
            'message' => 'Organizations retrieved successfully',
            'data' => $organizations
        ]);
    }

    /**
     * Create a new organization
     */
    public function createOrganization(Request $request)
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Unauthorized. Super admin access required.'], 403);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:organizations,name'],
            'description' => ['nullable', 'string', 'max:1000']
        ]);

        try {
            $organization = Organization::create($data);

            return response()->json([
                'message' => 'Organization created successfully',
                'data' => $organization
            ], 201);
        } catch (\Exception $e) {
            Log::error('Organization creation error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create organization',
                'errors' => ['general' => 'Something went wrong during organization creation.']
            ], 500);
        }
    }

    /**
     * Update an organization
     */
    public function updateOrganization(Request $request, $id)
    {
        $organization = Organization::findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:organizations,name,' . $id],
            'description' => ['nullable', 'string', 'max:1000']
        ]);

        try {
            $organization->update($data);

            return response()->json([
                'message' => 'Organization updated successfully',
                'data' => $organization
            ]);
        } catch (\Exception $e) {
            Log::error('Organization update error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update organization',
                'errors' => ['general' => 'Something went wrong during organization update.']
            ], 500);
        }
    }

    /**
     * Delete an organization
     */
    public function deleteOrganization($id)
    {
        try {
            $organization = Organization::findOrFail($id);
            $organization->delete();

            return response()->json([
                'message' => 'Organization deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Organization deletion error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete organization',
                'errors' => ['general' => 'Something went wrong during organization deletion.']
            ], 500);
        }
    }

    /**
     * Get all organizational admins
     */
    public function getOrgAdmins()
    {
        $orgAdmins = OrgAdmin::with(['user', 'organization'])->get();
        return response()->json([
            'message' => 'Organizational admins retrieved successfully',
            'data' => $orgAdmins
        ]);
    }

    /**
     * Create a new organizational admin
     */
    public function createOrgAdmin(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8'],
            'organization_id' => ['required', 'exists:organizations,id']
        ]);

        try {
            DB::beginTransaction();

            // Create user
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'user_type' => 'org-admin',
                'organization_id' => $data['organization_id']
            ]);

            // Assign org_admin role
            $user->assignRole('org_admin');

            // Give org admin all their permissions
            $orgAdminPermissions = [
                'organization.view',
                'organization.update',
                'organization.admins.create',
                'organization.admins.view',
                'organization.admins.update',
                'organization.admins.delete',
                'student.create',
                'student.view',
                'student.update',
                'student.delete',
                'student.detail.view',
                'exam.create',
                'exam.view',
                'exam.update',
                'exam.schedule.set',
                'exam.schedule.update',
                'exam.registration.deadline.set',
                'exam.registration.deadline.extend',
                'exam.location.manage',
                'payments.view',
                'payments.create',
                'payments.update',
                'announcement.create',
                'announcement.view',
                'announcement.update',
                'announcement.publish',
            ];
            $user->givePermissionTo($orgAdminPermissions);

            // Create org admin record
            $orgAdmin = OrgAdmin::create([
                'name' => $data['name'],
                'user_id' => $user->id,
                'organization_id' => $data['organization_id']
            ]);

            $orgAdmin->load(['user', 'organization']);

            DB::commit();

            return response()->json([
                'message' => 'Organizational admin created successfully',
                'data' => $orgAdmin
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Org admin creation error: ' . $e->getMessage());

            if (str_contains($e->getMessage(), 'users_email_unique')) {
                return response()->json([
                    'message' => 'Creation failed.',
                    'errors' => ['email' => 'This email address is already registered.']
                ], 422);
            }

            return response()->json([
                'message' => 'Failed to create organizational admin',
                'errors' => ['general' => 'Something went wrong during creation.']
            ], 500);
        }
    }

    /**
     * Update an organizational admin
     */
    public function updateOrgAdmin(Request $request, $id)
    {
        $orgAdmin = OrgAdmin::with('user')->findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,' . $orgAdmin->user->id],
            'organization_id' => ['required', 'exists:organizations,id']
        ]);

        try {
            DB::beginTransaction();

            // Update user
            $orgAdmin->user->update([
                'name' => $data['name'],
                'email' => $data['email']
            ]);

            // Update org admin
            $orgAdmin->update([
                'name' => $data['name'],
                'organization_id' => $data['organization_id']
            ]);

            $orgAdmin->load(['user', 'organization']);

            DB::commit();

            return response()->json([
                'message' => 'Organizational admin updated successfully',
                'data' => $orgAdmin
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Org admin update error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update organizational admin',
                'errors' => ['general' => 'Something went wrong during update.']
            ], 500);
        }
    }

    /**
     * Delete an organizational admin
     */
    public function deleteOrgAdmin($id)
    {
        try {
            DB::beginTransaction();

            $orgAdmin = OrgAdmin::with('user')->findOrFail($id);
            $user = $orgAdmin->user;

            // Delete org admin record
            $orgAdmin->delete();

            // Delete user account
            $user->delete();

            DB::commit();

            return response()->json([
                'message' => 'Organizational admin deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Org admin deletion error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete organizational admin',
                'errors' => ['general' => 'Something went wrong during deletion.']
            ], 500);
        }
    }
}
