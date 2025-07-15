<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateComplaintRequest;
use App\Http\Requests\UpdateComplaintRequest;
use App\Http\Resources\ComplaintResource;
use App\Models\Complaint;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ComplaintController extends Controller
{
    /**
     * Get all complaints for the authenticated student.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getComplaints()
    {
        $user = Auth::user();
        
        if (!$user->student_id) {
            return response()->json([
                'message' => 'Student profile not found'
            ], 404);
        }

        $complaints = Complaint::where('student_id', $user->student_id)
            ->with(['student.user', 'exam', 'organization'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Complaints retrieved successfully',
            'data' => ComplaintResource::collection($complaints)
        ]);
    }

    /**
     * Create a new complaint for the authenticated student.
     *
     * @param CreateComplaintRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createComplaint(CreateComplaintRequest $request)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Profile not found'
            ], 404);
        }

        $validated = $request->validated();
        
        $complaint = Complaint::create([
            'student_id' => $user->student_id,
            'exam_id' => $validated['exam_id'] ?? null,
            'description' => $validated['description'],
            'organization_id' => $validated['organization_id'] ?? null,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        $complaint->load(['student.user', 'exam', 'organization']);

        return response()->json([
            'message' => 'Complaint created successfully',
            'data' => ComplaintResource::make($complaint)
        ], 201);
    }

    /**
     * Get a specific complaint for the authenticated student.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getComplaint($id)
    {
        $user = Auth::user();
        
        if (!$user->student_id) {
            return response()->json([
                'message' => 'Student profile not found'
            ], 404);
        }

        $complaint = Complaint::where('id', $id)
            ->where('student_id', $user->student_id)
            ->with(['student.user', 'exam', 'organization'])
            ->first();

        if (!$complaint) {
            return response()->json([
                'message' => 'Complaint not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Complaint retrieved successfully',
            'data' => ComplaintResource::make($complaint)
        ]);
    }

    /**
     * Update a complaint for the authenticated student.
     *
     * @param UpdateComplaintRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateComplaint(UpdateComplaintRequest $request, $id)
    {
        $user = Auth::user();
        
        if (!$user->student_id) {
            return response()->json([
                'message' => 'Student profile not found'
            ], 404);
        }

        $complaint = Complaint::where('id', $id)
            ->where('student_id', $user->student_id)
            ->first();

        if (!$complaint) {
            return response()->json([
                'message' => 'Complaint not found'
            ], 404);
        }

        // Students can only update pending complaints
        if ($complaint->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot update complaint that is not pending'
            ], 400);
        }

        $validated = $request->validated();
        
        // Students can only update description, not status
        if (isset($validated['status'])) {
            return response()->json([
                'message' => 'Students cannot update complaint status'
            ], 403);
        }

        $complaint->update(array_merge($validated, [
            'updated_by' => $user->id,
        ]));

        $complaint->load(['student.user', 'exam', 'organization']);

        return response()->json([
            'message' => 'Complaint updated successfully',
            'data' => ComplaintResource::make($complaint)
        ]);
    }

    /**
     * Delete a complaint for the authenticated student.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteComplaint($id)
    {
        $user = Auth::user();
        
        if (!$user->student_id) {
            return response()->json([
                'message' => 'Student profile not found'
            ], 404);
        }

        $complaint = Complaint::where('id', $id)
            ->where('student_id', $user->student_id)
            ->first();

        if (!$complaint) {
            return response()->json([
                'message' => 'Complaint not found'
            ], 404);
        }

        // Students can only delete pending complaints
        if ($complaint->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot delete complaint that is not pending'
            ], 400);
        }

        $complaint->delete();

        return response()->json([
            'message' => 'Complaint deleted successfully'
        ]);
    }
}
