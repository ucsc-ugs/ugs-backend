<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrganizationController extends Controller
{
    /**
     * Display a listing of organizations.
     */
    public function index(): JsonResponse
    {
        $organizations = Organization::all();

        return response()->json([
            'message' => 'Organizations retrieved successfully',
            'data' => $organizations
        ]);
    }

    /**
     * Create a new organization.
     */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        // Set default status if not provided
        $validated['status'] = $validated['status'] ?? 'active';

        $organization = Organization::create($validated);

        return response()->json([
            'message' => 'Organization created successfully',
            'data' => $organization
        ], 201);
    }

    /**
     * Display the specified organization.
     */
    public function show(string $id): JsonResponse
    {
        $organization = Organization::find($id);

        if (!$organization) {
            return response()->json([
                'message' => 'Organization not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Organization retrieved successfully',
            'data' => $organization
        ]);
    }

    /**
     * Update the specified organization.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $organization = Organization::find($id);

        if (!$organization) {
            return response()->json([
                'message' => 'Organization not found'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        $organization->update($validated);
        $organization->refresh(); // Refresh to get updated data

        return response()->json([
            'message' => 'Organization updated successfully',
            'data' => $organization
        ]);
    }

    /**
     * Remove the specified organization.
     */
    public function delete(string $id): JsonResponse
    {
        $organization = Organization::find($id);

        if (!$organization) {
            return response()->json([
                'message' => 'Organization not found'
            ], 404);
        }

        $organization->delete();

        return response()->json([
            'message' => 'Organization deleted successfully'
        ]);
    }
}
