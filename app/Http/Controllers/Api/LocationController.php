<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class LocationController extends Controller
{
    /**
     * Display a listing of locations
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('orgAdmin');

        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        // Super admin can see all locations
        if ($user->hasRole('super_admin')) {
            $locations = Location::with(['organization'])->get();
        }
        // Org admin can only see locations for their organization
        elseif ($user->hasRole('org_admin')) {
            $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

            if (!$organizationId) {
                return response()->json([
                    'message' => 'No organization found for this user'
                ], 404);
            }

            $locations = Location::with(['organization'])
                ->where('organization_id', $organizationId)
                ->get();
        }

        // Add current registration count to each location
        $locations = $locations->map(function ($location) {
            $location->current_registrations = $location->getCurrentRegistrationCount();
            $location->available_capacity = $location->capacity - $location->current_registrations;
            return $location;
        });

        return response()->json([
            'message' => 'Locations retrieved successfully',
            'data' => $locations
        ]);
    }

    /**
     * Store a newly created location
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        // Different validation rules based on user role
        if ($user->hasRole('super_admin')) {
            $validated = $request->validate([
                'location_name' => 'required|string|max:255',
                'capacity' => 'required|integer|min:1',
                'organization_id' => 'required|exists:organizations,id'
            ]);
        } else {
            // For org_admin, only validate location_name and capacity
            $validated = $request->validate([
                'location_name' => 'required|string|max:255',
                'capacity' => 'required|integer|min:1'
            ]);

            // Automatically set organization_id for org_admin
            $user->load('orgAdmin');
            $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

            if (!$organizationId) {
                return response()->json([
                    'message' => 'No organization found for this user'
                ], 404);
            }

            $validated['organization_id'] = $organizationId;
        }

        $location = Location::create($validated);
        $location->load('organization');

        return response()->json([
            'message' => 'Location created successfully',
            'data' => $location
        ], 201);
    }

    /**
     * Display the specified location
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $location = Location::with(['organization'])->find($id);

        if (!$location) {
            return response()->json([
                'message' => 'Location not found'
            ], 404);
        }

        // Org admin can only view locations for their organization
        if ($user->hasRole('org_admin')) {
            $user->load('orgAdmin');
            $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

            if ($location->organization_id != $organizationId) {
                return response()->json([
                    'message' => 'Unauthorized to view this location'
                ], 403);
            }
        }

        // Add registration details
        $location->current_registrations = $location->getCurrentRegistrationCount();
        $location->available_capacity = $location->capacity - $location->current_registrations;
        $location->assigned_students = $location->assignedStudents()->with(['student.user', 'exam'])->get();

        return response()->json([
            'message' => 'Location retrieved successfully',
            'data' => $location
        ]);
    }

    /**
     * Update the specified location
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $location = Location::find($id);

        if (!$location) {
            return response()->json([
                'message' => 'Location not found'
            ], 404);
        }

        // Org admin can only update locations for their organization
        if ($user->hasRole('org_admin')) {
            $user->load('orgAdmin');
            $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

            if ($location->organization_id != $organizationId) {
                return response()->json([
                    'message' => 'Unauthorized to update this location'
                ], 403);
            }
        }

        $validated = $request->validate([
            'location_name' => 'sometimes|string|max:255',
            'capacity' => 'sometimes|integer|min:1',
        ]);

        // Ensure capacity is not reduced below current registrations
        if (isset($validated['capacity'])) {
            $currentRegistrations = $location->getCurrentRegistrationCount();
            if ($validated['capacity'] < $currentRegistrations) {
                return response()->json([
                    'message' => "Cannot reduce capacity below current registrations ({$currentRegistrations})"
                ], 400);
            }
        }

        $location->update($validated);
        $location->load('organization');

        return response()->json([
            'message' => 'Location updated successfully',
            'data' => $location
        ]);
    }

    /**
     * Remove the specified location
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $location = Location::find($id);

        if (!$location) {
            return response()->json([
                'message' => 'Location not found'
            ], 404);
        }

        // Org admin can only delete locations for their organization
        if ($user->hasRole('org_admin')) {
            $user->load('orgAdmin');
            $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

            if ($location->organization_id != $organizationId) {
                return response()->json([
                    'message' => 'Unauthorized to delete this location'
                ], 403);
            }
        }

        // Check if location has assigned students
        $registrationCount = $location->getCurrentRegistrationCount();
        if ($registrationCount > 0) {
            return response()->json([
                'message' => "Cannot delete location with {$registrationCount} registered student(s)"
            ], 400);
        }

        $location->delete();

        return response()->json([
            'message' => 'Location deleted successfully'
        ]);
    }

    /**
     * Get locations for a specific organization
     */
    public function getByOrganization(Request $request, $organizationId): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        // Org admin can only view locations for their organization
        if ($user->hasRole('org_admin')) {
            $user->load('orgAdmin');
            $userOrgId = $user->organization_id ?? $user->orgAdmin?->organization_id;

            if ($organizationId != $userOrgId) {
                return response()->json([
                    'message' => 'Unauthorized to view these locations'
                ], 403);
            }
        }

        $locations = Location::where('organization_id', $organizationId)
            ->with(['organization'])
            ->get();

        // Add current registration count to each location
        $locations = $locations->map(function ($location) {
            $location->current_registrations = $location->getCurrentRegistrationCount();
            $location->available_capacity = $location->capacity - $location->current_registrations;
            return $location;
        });

        return response()->json([
            'message' => 'Locations retrieved successfully',
            'data' => $locations
        ]);
    }
}
