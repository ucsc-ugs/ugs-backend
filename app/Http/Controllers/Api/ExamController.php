<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamDate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        // Check if user has required roles
        $user = $request->user();

        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        // Super admin can see all exams
        if ($user->hasRole('super_admin')) {
            $exams = Exam::with(['organization', 'examDates'])->get();
        }
        // Org admin can only see exams related to their organization
        elseif ($user->hasRole('org_admin')) {
            // Get organization through org_admins relationship
            $orgAdmin = $user->orgAdmin; // Assuming User has orgAdmin relationship
            if (!$orgAdmin) {
                return response()->json([
                    'message' => 'No organization found for this admin'
                ], 404);
            }
            $exams = Exam::with(['organization', 'examDates'])
                ->where('organization_id', $orgAdmin->organization_id)
                ->get();
        }

        return response()->json([
            'message' => 'Exams retrieved successfully',
            'data' => $exams
        ]);
    }

    /**
     * Create a new exam (for custom v1 route)
     */
    public function create(Request $request)
    {
        $user = $request->user();

        // Check if user has required roles
        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'organization_id' => 'required|exists:organizations,id',
            'exam_dates' => 'nullable|array',
            'exam_dates.*.date' => 'required|date_format:Y-m-d\TH:i',
            'exam_dates.*.location' => 'nullable|string|max:255'
        ]);

        // For org_admin, ensure they can only create exams for their organization
        if ($user->hasRole('org_admin')) {
            $orgAdmin = $user->orgAdmin;
            if (!$orgAdmin) {
                return response()->json([
                    'message' => 'No organization found for this admin'
                ], 404);
            }

            if ($validated['organization_id'] !== $orgAdmin->organization_id) {
                return response()->json([
                    'message' => 'Unauthorized. You can only create exams for your organization.'
                ], 403);
            }
        }

        // Create the exam
        $exam = Exam::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'organization_id' => $validated['organization_id']
        ]);

        // Create exam dates if provided
        if (!empty($validated['exam_dates'])) {
            foreach ($validated['exam_dates'] as $examDate) {
                ExamDate::create([
                    'exam_id' => $exam->id,
                    'date' => $examDate['date'],
                    'location' => $examDate['location'] ?? null
                ]);
            }
        }

        // Load the exam with its dates for response
        $exam->load('examDates');

        return response()->json([
            'message' => 'Exam created successfully',
            'data' => $exam
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $exam = Exam::with(['organization', 'examDates'])->find($id);

        if (!$exam) {
            return response()->json([
                'message' => 'Exam not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Exam retrieved successfully',
            'data' => $exam
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $exam = Exam::find($id);

        if (!$exam) {
            return response()->json([
                'message' => 'Exam not found'
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'organization_id' => 'sometimes|exists:organizations,id',
            'exam_dates' => 'nullable|array',
            'exam_dates.*.date' => 'required|date_format:Y-m-d\TH:i',
            'exam_dates.*.location' => 'nullable|string|max:255'
        ]);

        // Update exam basic info
        $exam->update([
            'name' => $validated['name'] ?? $exam->name,
            'description' => $validated['description'] ?? $exam->description,
            'organization_id' => $validated['organization_id'] ?? $exam->organization_id
        ]);

        // Update exam dates if provided
        if (array_key_exists('exam_dates', $validated)) {
            // Delete existing exam dates
            $exam->examDates()->delete();

            // Create new exam dates if provided
            if (!empty($validated['exam_dates'])) {
                foreach ($validated['exam_dates'] as $examDate) {
                    ExamDate::create([
                        'exam_id' => $exam->id,
                        'date' => $examDate['date'],
                        'location' => $examDate['location'] ?? null
                    ]);
                }
            }
        }

        // Load the exam with its dates for response
        $exam->load('examDates');

        return response()->json([
            'message' => 'Exam updated successfully',
            'data' => $exam
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function delete(string $id): JsonResponse
    {
        $exam = Exam::find($id);

        if (!$exam) {
            return response()->json([
                'message' => 'Exam not found'
            ], 404);
        }

        $exam->delete();

        return response()->json([
            'message' => 'Exam deleted successfully'
        ]);
    }
}
