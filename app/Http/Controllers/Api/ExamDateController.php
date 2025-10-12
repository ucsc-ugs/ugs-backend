<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ExamDateController extends Controller
{
    /**
     * Update the status of an exam date
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        // Check if user has required roles
        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:upcoming,completed,cancelled'
        ]);

        $examDate = ExamDate::with('exam')->findOrFail($id);

        // For org_admin, ensure they can only update exam dates for their organization
        if ($user->hasRole('org_admin')) {
            $user->load('orgAdmin');
            $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

            if (!$organizationId || $examDate->exam->organization_id !== $organizationId) {
                return response()->json([
                    'message' => 'Unauthorized. You can only manage exam dates for your organization.'
                ], 403);
            }
        }

        $examDate->update($validated);

        return response()->json([
            'message' => 'Exam date status updated successfully',
            'data' => $examDate->fresh()
        ]);
    }

    /**
     * Automatically update exam date statuses based on current date
     */
    public function updateExpiredStatuses(): JsonResponse
    {
        $today = Carbon::now()->startOfDay();

        // Update upcoming exams that have passed their date to completed
        $updated = ExamDate::where('status', 'upcoming')
            ->where('date', '<', $today)
            ->update(['status' => 'completed']);

        return response()->json([
            'message' => 'Exam date statuses updated automatically',
            'updated_count' => $updated
        ]);
    }
}
