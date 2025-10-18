<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\OrgAdmin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class OrgAdminController extends Controller
{
    /**
     * Get admins belonging to the authenticated org admin's organization
     */
    public function getOrgAdmins(Request $request)
    {
        $user = $request->user();

        // Check permission
        if (!$user->can('organization.admins.view')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view organization admins.'], 403);
        }

        // Check if user is an org admin
        $orgAdmin = $user->hasRole('org_admin');
        if (!$orgAdmin) {
            return response()->json(['message' => 'Unauthorized. Organization admin access required.'], 403);
        }

        // Get all admins from the same organization, excluding the current admin
        $admins = OrgAdmin::with(['user.permissions', 'user.roles', 'organization'])
            ->where('organization_id', $user->organization_id)
            ->where('user_id', '!=', $user->id) // Exclude self by user_id
            ->orderByDesc('created_at')
            ->get();

        // Extract users from OrgAdmin and use UserResource
        $adminUsers = $admins->map(function ($orgAdmin) {
            return $orgAdmin->user;
        });

        return response()->json([
            'message' => 'Organization admins retrieved successfully',
            'data' => UserResource::collection($adminUsers)
        ]);
    }

    /**
     * Create a new admin for the same organization
     */
    public function createOrgAdmin(Request $request)
    {
        $user = $request->user();

        // Check permission
        if (!$user->can('organization.admins.create')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to create organization admins.'], 403);
        }

        // Check if user is an org admin
        // $orgAdmin = $user->orgAdmin;
        $orgAdmin = $user->hasRole('org_admin');
        if (!$orgAdmin) {
            return response()->json(['message' => 'Unauthorized. Organization admin access required.'], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'distinct', 'exists:permissions,name']
        ]);

        try {
            DB::beginTransaction();

            // Create user
            $newUser = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'user_type' => 'org-admin',
                'organization_id' => $user->organization_id
            ]);

            // Assign org_admin role
            $newUser->assignRole('org_admin');
            foreach ($data['permissions'] as $permission) {
                $newUser->givePermissionTo($permission);
            }

            // Create org admin record for the same organization
            $newOrgAdmin = OrgAdmin::create([
                'name' => $data['name'],
                'user_id' => $newUser->id,
                'organization_id' => $user->organization_id // Same organization as the creator
            ]);

            // Load relationships for the response
            $newUser->load(['permissions', 'roles', 'organization']);

            DB::commit();

            return response()->json([
                'message' => 'Admin created successfully',
                'data' => UserResource::make($newUser)
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
                'message' => 'Failed to create admin',
                'errors' => ['general' => 'Something went wrong during creation.']
            ], 500);
        }
    }

    /**
     * Update an admin from the same organization
     */
    public function updateOrgAdmin(Request $request, $id)
    {
        $user = $request->user();

        // Check permission
        if (!$user->can('organization.admins.update')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to update organization admins.'], 403);
        }

        // Check if user is an org admin
        $orgAdmin = $user->hasRole('org_admin');
        if (!$orgAdmin) {
            return response()->json(['message' => 'Unauthorized. Organization admin access required.'], 403);
        }

        // Find the admin to update and verify it belongs to the same organization
        $targetAdmin = User::with(['permissions', 'roles', 'organization'])->findOrFail($id);

        if ($targetAdmin->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'Unauthorized. You can only manage admins from your organization.'], 403);
        }

        // Prevent admin from updating themselves
        if ($targetAdmin->id === $user->id) {
            return response()->json(['message' => 'You cannot update your own account through this endpoint.'], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($targetAdmin->id)],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', 'exists:permissions,name'],
        ]);

        try {
            DB::beginTransaction();

            // Update user
            $targetAdmin->update([
                'name' => $data['name'],
                'email' => $data['email']
            ]);

            // Update org admin record if exists
            $targetAdmin->orgAdmin?->update([
                'name' => $data['name']
            ]);

            // Update permissions if provided
            if (isset($data['permissions'])) {
                // Sync permissions (this will remove old ones and add new ones)
                $targetAdmin->syncPermissions($data['permissions']);
            }

            $targetAdmin->load(['permissions', 'roles', 'organization']);

            DB::commit();

            return response()->json([
                'message' => 'Admin updated successfully',
                'data' => UserResource::make($targetAdmin)
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Org admin update error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update admin',
                'errors' => ['general' => 'Something went wrong during update.']
            ], 500);
        }
    }

    /**
     * Delete an admin from the same organization
     */
    public function deleteOrgAdmin(Request $request, $id)
    {
        $user = $request->user();

        // Check permission
        if (!$user->can('organization.admins.delete')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to delete organization admins.'], 403);
        }

        // Check if user is an org admin
        $orgAdmin = $user->hasRole('org_admin');
        if (!$orgAdmin) {
            return response()->json(['message' => 'Unauthorized. Organization admin access required.'], 403);
        }

        // Find the admin to delete and verify it belongs to the same organization
        $targetAdmin = User::findOrFail($id);

        if ($targetAdmin->organization_id !== $user->organization_id) {
            return response()->json(['message' => 'Unauthorized. You can only manage admins from your organization.'], 403);
        }

        // Prevent admin from deleting themselves
        if ($targetAdmin->id === $user->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 403);
        }

        try {
            DB::beginTransaction();

            // Delete org admin record if exists
            $targetAdmin->orgAdmin?->delete();

            // Delete user account
            $targetAdmin->delete();

            DB::commit();

            return response()->json([
                'message' => 'Admin deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Org admin deletion error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to delete admin',
                'errors' => ['general' => 'Something went wrong during deletion.']
            ], 500);
        }
    }

    /**
     * Get the authenticated org admin's organization details
     */
    public function getMyOrganization(Request $request)
    {
        $user = $request->user();

        // Check permission
        if (!$user->can('organization.view')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to view organization details.'], 403);
        }

        // Check if user is an org admin
        $orgAdmin = $user->orgAdmin;
        if (!$orgAdmin) {
            return response()->json(['message' => 'Unauthorized. Organization admin access required.'], 403);
        }

        $organization = $orgAdmin->organization;

        return response()->json([
            'message' => 'Organization details retrieved successfully',
            'data' => $organization
        ]);
    }

    /**
     * Update the authenticated org admin's organization details
     */
    public function updateMyOrganization(Request $request)
    {
        $user = $request->user();

        // Check permission
        if (!$user->can('organization.update')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to update organization details.'], 403);
        }

        // Check if user is an org admin
        $orgAdmin = $user->orgAdmin;
        if (!$orgAdmin) {
            return response()->json(['message' => 'Unauthorized. Organization admin access required.'], 403);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'contact_email' => ['sometimes', 'email'],
            'phone_number' => ['sometimes', 'string', 'max:20'],
            'address' => ['sometimes', 'string', 'max:500'],
            'website' => ['sometimes', 'url', 'max:255']
        ]);

        try {
            $organization = $orgAdmin->organization;
            $organization->update($data);

            return response()->json([
                'message' => 'Organization updated successfully',
                'data' => $organization
            ]);
        } catch (\Exception $e) {
            Log::error('Organization update error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to update organization',
                'errors' => ['general' => 'Something went wrong during update.']
            ], 500);
        }
    }

    /**
     * Upload logo for the authenticated org admin's organization
     */
    public function uploadOrganizationLogo(Request $request)
    {
        $user = $request->user();

        // Check permission
        if (!$user->can('organization.update')) {
            return response()->json(['message' => 'Unauthorized. You do not have permission to update organization details.'], 403);
        }

        // Check if user is an org admin
        $orgAdmin = $user->orgAdmin;
        if (!$orgAdmin) {
            return response()->json(['message' => 'Unauthorized. Organization admin access required.'], 403);
        }

        $request->validate([
            'logo' => ['required', 'image', 'max:5120'] // 5MB max
        ]);

        try {
            $organization = $orgAdmin->organization;

            // Delete old logo if exists
            if ($organization->logo) {
                $oldLogoPath = storage_path('app/public' . $organization->logo);
                if (file_exists($oldLogoPath)) {
                    unlink($oldLogoPath);
                }
            }

            // Store new logo
            $logoPath = $request->file('logo')->store('organization-logos', 'public');
            $organization->update(['logo' => '/' . $logoPath]);

            return response()->json([
                'message' => 'Logo uploaded successfully',
                'data' => [
                    'logo_url' => '/storage/' . $logoPath,
                    'organization' => $organization
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Logo upload error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to upload logo',
                'errors' => ['logo' => 'Something went wrong during upload.']
            ], 500);
        }
    }
}
