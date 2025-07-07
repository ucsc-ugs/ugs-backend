<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ExamController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $exams = Exam::all();

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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'organization_id' => 'required|exists:organizations,id'
        ]);

        $exam = Exam::create($validated);

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
        $exam = Exam::with('organization')->find($id);

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
            'organization_id' => 'sometimes|exists:organizations,id'
        ]);

        $exam->update($validated);

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
