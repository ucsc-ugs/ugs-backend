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

    /**
     * Get detailed information about an exam date including location and registrations
     */
    public function details(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        // Check if user has required roles
        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $examDate = ExamDate::with([
            'exam',
            'locations', // Multi-location support
            'studentExams.student',
            'studentExams.assignedLocation'
        ])->findOrFail($id);

        // For org_admin, ensure they can only view exam dates for their organization
        if ($user->hasRole('org_admin')) {
            $user->load('orgAdmin');
            $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

            if (!$organizationId || $examDate->exam->organization_id !== $organizationId) {
                return response()->json([
                    'message' => 'Unauthorized. You can only view exam dates for your organization.'
                ], 403);
            }
        }

        // Prepare location details for multiple locations
        $locationDetails = [];
        $totalCapacity = 0;
        $totalRegistrations = $examDate->studentExams->count();
        
        if ($examDate->locations && $examDate->locations->count() > 0) {
            foreach ($examDate->locations as $location) {
                // Count registrations for this specific location
                $locationRegistrations = $examDate->studentExams
                    ->where('assigned_location_id', $location->id)
                    ->count();
                
                $locationDetails[] = [
                    'id' => $location->id,
                    'location_name' => $location->location_name,
                    'capacity' => $location->capacity,
                    'current_registrations' => $locationRegistrations,
                    'priority' => $location->pivot->priority ?? 0,
                ];
                
                $totalCapacity += $location->capacity;
            }
        }
        
        // Legacy single location fallback
        if (empty($locationDetails) && $examDate->location) {
            $locationDetails[] = [
                'id' => $examDate->location->id,
                'location_name' => $examDate->location->location_name,
                'capacity' => $examDate->location->capacity,
                'current_registrations' => $totalRegistrations,
                'priority' => 1,
            ];
            $totalCapacity = $examDate->location->capacity;
        }

        // Prepare registrations data
        $registrations = $examDate->studentExams->map(function ($studentExam) {
            return [
                'id' => $studentExam->id,
                'student_id' => $studentExam->student_id,
                'exam_id' => $studentExam->exam_id,
                'index_number' => $studentExam->index_number,
                'status' => $studentExam->status,
                'attended' => $studentExam->attended,
                'result' => $studentExam->result,
                'created_at' => $studentExam->created_at,
                'student' => [
                    'id' => $studentExam->student->id,
                    'name' => $studentExam->student->name,
                    'email' => $studentExam->student->email,
                    'phone' => $studentExam->student->phone,
                ],
                'assigned_location' => $studentExam->assignedLocation ? [
                    'id' => $studentExam->assignedLocation->id,
                    'location_name' => $studentExam->assignedLocation->location_name,
                    'capacity' => $studentExam->assignedLocation->capacity,
                ] : null,
            ];
        });

        return response()->json([
            'id' => $examDate->id,
            'exam_id' => $examDate->exam_id,
            'date' => $examDate->date,
            'registration_deadline' => $examDate->registration_deadline,
            'location' => $examDate->location, // legacy location field
            'status' => $examDate->status,
            'exam' => [
                'id' => $examDate->exam->id,
                'name' => $examDate->exam->name,
                'code_name' => $examDate->exam->code_name,
                'price' => $examDate->exam->price,
            ],
            'locations' => $locationDetails,
            'total_capacity' => $totalCapacity,
            'total_registrations' => $totalRegistrations,
            'registrations' => $registrations,
        ]);
    }

    /**
     * Add a new exam date to an existing exam
     */
    public function addDateToExam(Request $request, $examId): JsonResponse
    {
        $user = $request->user();

        // Check if user has required roles
        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        // Find the exam
        $exam = \App\Models\Exam::findOrFail($examId);

        // For org_admin, ensure they can only add dates to their organization's exams
        if ($user->hasRole('org_admin')) {
            $user->load('orgAdmin');
            $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

            if (!$organizationId || $exam->organization_id !== $organizationId) {
                return response()->json([
                    'message' => 'Unauthorized. You can only manage exams for your organization.'
                ], 403);
            }
        }

        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d\TH:i|after:now',
            'location' => 'nullable|string|max:255',
            'location_id' => 'nullable|exists:locations,id',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'exists:locations,id'
        ]);

        // Create the exam date
        $examDate = \App\Models\ExamDate::create([
            'exam_id' => $exam->id,
            'date' => $validated['date'],
            'location' => $validated['location'] ?? null,
            'location_id' => $validated['location_id'] ?? null,
            'status' => 'upcoming'
        ]);

        // Handle multiple locations (new format)
        if (!empty($validated['location_ids']) && is_array($validated['location_ids'])) {
            foreach ($validated['location_ids'] as $index => $locationId) {
                \App\Models\ExamDateLocation::create([
                    'exam_date_id' => $examDate->id,
                    'location_id' => $locationId,
                    'priority' => $index + 1,
                    'current_registrations' => 0
                ]);
            }
        }
        // Handle single location (backward compatibility)
        elseif (!empty($validated['location_id'])) {
            \App\Models\ExamDateLocation::create([
                'exam_date_id' => $examDate->id,
                'location_id' => $validated['location_id'],
                'priority' => 1,
                'current_registrations' => 0
            ]);
        }

        // Load the exam date with relationships
        $examDate->load('locations', 'exam');

        return response()->json([
            'message' => 'Exam date added successfully',
            'data' => $examDate
        ]);
    }

    /**
     * Add multiple exam dates to an existing exam (bulk operation)
     */
    public function addMultipleDatesToExam(Request $request, $examId): JsonResponse
    {
        $user = $request->user();

        // Check if user has required roles
        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        // Find the exam
        $exam = \App\Models\Exam::findOrFail($examId);

        // For org_admin, ensure they can only add dates to their organization's exams
        if ($user->hasRole('org_admin')) {
            $user->load('orgAdmin');
            $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

            if (!$organizationId || $exam->organization_id !== $organizationId) {
                return response()->json([
                    'message' => 'Unauthorized. You can only manage exams for your organization.'
                ], 403);
            }
        }

        $validated = $request->validate([
            'exam_dates' => 'required|array|min:1|max:10',
            'exam_dates.*.date' => 'required|date_format:Y-m-d\TH:i|after:now',
            'exam_dates.*.location' => 'nullable|string|max:255',
            'exam_dates.*.location_id' => 'nullable|exists:locations,id',
            'exam_dates.*.location_ids' => 'nullable|array',
            'exam_dates.*.location_ids.*' => 'exists:locations,id'
        ]);

        $createdExamDates = [];

        foreach ($validated['exam_dates'] as $examDateData) {
            // Create the exam date
            $examDate = \App\Models\ExamDate::create([
                'exam_id' => $exam->id,
                'date' => $examDateData['date'],
                'location' => $examDateData['location'] ?? null,
                'location_id' => $examDateData['location_id'] ?? null,
                'status' => 'upcoming'
            ]);

            // Handle multiple locations (new format)
            if (!empty($examDateData['location_ids']) && is_array($examDateData['location_ids'])) {
                foreach ($examDateData['location_ids'] as $index => $locationId) {
                    \App\Models\ExamDateLocation::create([
                        'exam_date_id' => $examDate->id,
                        'location_id' => $locationId,
                        'priority' => $index + 1,
                        'current_registrations' => 0
                    ]);
                }
            }
            // Handle single location (backward compatibility)
            elseif (!empty($examDateData['location_id'])) {
                \App\Models\ExamDateLocation::create([
                    'exam_date_id' => $examDate->id,
                    'location_id' => $examDateData['location_id'],
                    'priority' => 1,
                    'current_registrations' => 0
                ]);
            }

            $createdExamDates[] = $examDate->load('locations', 'exam');
        }

        return response()->json([
            'message' => count($createdExamDates) . ' exam dates added successfully',
            'data' => $createdExamDates
        ]);
    }

    /**
     * Update exam date details (date, registration_deadline, locations)
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        // Check if user has required roles
        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        try {
            $examDate = ExamDate::with('exam', 'locations')->findOrFail($id);

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

            // Validate request
            $request->validate([
                'date' => 'required|date|after:now',
                'registration_deadline' => 'nullable|date|after:now|before:date',
                'location_ids' => 'required|array|min:1',
                'location_ids.*' => 'integer|exists:locations,id',
            ]);

            // Validate locations belong to the same organization
            $locationIds = $request->location_ids;
            $organizationId = $examDate->exam->organization_id;
            
            $validLocations = \App\Models\Location::whereIn('id', $locationIds)
                ->where('organization_id', $organizationId)
                ->count();

            if ($validLocations !== count($locationIds)) {
                return response()->json([
                    'message' => 'Some selected locations do not belong to your organization or do not exist.'
                ], 422);
            }

            // Update exam date basic details
            $examDate->update([
                'date' => $request->date,
                'registration_deadline' => $request->registration_deadline,
            ]);

            // Update location relationships
            $examDate->locations()->detach(); // Remove existing relationships

            // Add new location relationships with priority
            foreach ($locationIds as $index => $locationId) {
                $examDate->locations()->attach($locationId, [
                    'priority' => $index + 1,
                    'current_registrations' => 0 // Reset registrations for new setup
                ]);
            }

            \Illuminate\Support\Facades\Log::info('Exam date updated successfully', [
                'exam_date_id' => $examDate->id,
                'updated_by' => $user->id,
                'location_ids' => $locationIds
            ]);

            return response()->json([
                'message' => 'Exam date updated successfully',
                'data' => $examDate->fresh(['locations', 'exam'])
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Exam date not found'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to update exam date: ' . $e->getMessage(), [
                'exam_date_id' => $id,
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to update exam date',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate student list CSV for a specific hall
     */
    public function generateHallStudentList(Request $request, $examDateId, $locationId)
    {
        $user = $request->user();

        // Check if user has required roles
        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        try {
            \Illuminate\Support\Facades\Log::info('Generating hall student list', [
                'exam_date_id' => $examDateId,
                'location_id' => $locationId,
                'user_id' => $user->id
            ]);

            $examDate = ExamDate::with([
                'exam.organization',
                'locations',
                'studentExams' => function($query) use ($locationId) {
                    $query->where('assigned_location_id', $locationId)
                          ->with('student')
                          ->orderBy('index_number');
                }
            ])->findOrFail($examDateId);

            \Illuminate\Support\Facades\Log::info('Exam date loaded', [
                'exam_name' => $examDate->exam->name,
                'student_count' => $examDate->studentExams->count()
            ]);

            // For org_admin, ensure they can only access their organization's data
            if ($user->hasRole('org_admin')) {
                $user->load('orgAdmin');
                $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

                if (!$organizationId || $examDate->exam->organization_id !== $organizationId) {
                    return response()->json([
                        'message' => 'Unauthorized. You can only access your organization data.'
                    ], 403);
                }
            }

            // Get the specific location
            $location = $examDate->locations->where('id', $locationId)->first();
            
            if (!$location) {
                return response()->json([
                    'message' => 'Location not found for this exam date.'
                ], 404);
            }

            // Get students assigned to this hall
            $students = $examDate->studentExams;

            \Illuminate\Support\Facades\Log::info('Students found', [
                'student_count' => $students->count(),
                'location_name' => $location->location_name
            ]);

            // Generate CSV content
            $csvData = [];
            
            // Add header row
            $csvData[] = [
                'No.',
                'Index Number',
                'Student Name',
                'Signature'
            ];
            
            // Add student data
            foreach ($students as $index => $studentExam) {
                $csvData[] = [
                    $index + 1,
                    $studentExam->index_number,
                    $studentExam->student->first_name . ' ' . $studentExam->student->last_name,
                    '' // Empty signature column
                ];
            }

            // Convert to CSV string
            $csvContent = '';
            foreach ($csvData as $row) {
                $csvContent .= implode(',', array_map(function($field) {
                    // Escape fields that contain commas, quotes, or newlines
                    if (strpos($field, ',') !== false || strpos($field, '"') !== false || strpos($field, "\n") !== false) {
                        return '"' . str_replace('"', '""', $field) . '"';
                    }
                    return $field;
                }, $row)) . "\n";
            }

            $filename = $location->location_name . '_StudentList_' . date('Y-m-d') . '.csv';

            return response($csvContent)
                ->header('Content-Type', 'text/csv; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Exam date not found'
            ], 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to generate student list: ' . $e->getMessage(), [
                'exam_date_id' => $examDateId,
                'location_id' => $locationId,
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Failed to generate student list',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate HTML for student list that can be printed or converted to PDF
     */
    private function generateStudentListHtml($examDate, $location, $students)
    {
        $examName = $examDate->exam->name;
        $examCode = $examDate->exam->code_name;
        $organizationName = $examDate->exam->organization->name;
        $examDateTime = $examDate->date->format('F j, Y \a\t g:i A');
        $hallName = $location->location_name;
        $totalStudents = $students->count();
        $hallCapacity = $location->capacity;

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Student List - ' . $hallName . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        .header h2 {
            margin: 5px 0;
            font-size: 18px;
            color: #666;
        }
        .exam-info {
            margin-bottom: 20px;
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow: hidden;
        }
        .exam-info-row {
            display: block;
            margin-bottom: 8px;
        }
        .exam-info strong {
            display: inline-block;
            width: 120px;
            font-weight: bold;
        }
        .student-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .student-table th,
        .student-table td {
            border: 1px solid #333;
            padding: 12px 8px;
            text-align: left;
        }
        .student-table th {
            background-color: #f1f1f1;
            font-weight: bold;
            text-align: center;
        }
        .student-table .index-col {
            width: 30%;
            text-align: center;
        }
        .student-table .name-col {
            width: 40%;
        }
        .student-table .signature-col {
            width: 30%;
            height: 40px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        @media print {
            body { margin: 15px; }
            .header { page-break-after: avoid; }
            .student-table { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . htmlspecialchars($examName) . ' (' . htmlspecialchars($examCode) . ')</h1>
        <h2>' . htmlspecialchars($organizationName) . '</h2>
    </div>
    
    <div class="exam-info">
        <div class="exam-info-row">
            <strong>Date & Time:</strong> ' . $examDateTime . '
        </div>
        <div class="exam-info-row">
            <strong>Hall:</strong> ' . htmlspecialchars($hallName) . '
        </div>
        <div class="exam-info-row">
            <strong>Total Students:</strong> ' . $totalStudents . '
        </div>
        <div class="exam-info-row">
            <strong>Hall Capacity:</strong> ' . $hallCapacity . '
        </div>
    </div>

    <table class="student-table">
        <thead>
            <tr>
                <th class="index-col">Index Number</th>
                <th class="name-col">Student Name</th>
                <th class="signature-col">Signature</th>
            </tr>
        </thead>
        <tbody>';

        if ($students->count() > 0) {
            foreach ($students as $studentExam) {
                $html .= '
            <tr>
                <td class="index-col">' . htmlspecialchars($studentExam->index_number) . '</td>
                <td class="name-col">' . htmlspecialchars($studentExam->student->name) . '</td>
                <td class="signature-col"></td>
            </tr>';
            }
        } else {
            $html .= '
            <tr>
                <td colspan="3" style="text-align: center; padding: 20px; color: #666;">
                    No students registered for this hall
                </td>
            </tr>';
        }

        $html .= '
        </tbody>
    </table>

    <div class="footer">
        <p>Generated on ' . now()->format('F j, Y \a\t g:i A') . '</p>
        <p><strong>Instructions:</strong> Students must sign in the signature column upon arrival</p>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Generate filename for the student list
     */
    private function generateFilename($examDate, $location)
    {
        $examCode = $examDate->exam->code_name;
        $date = $examDate->date->format('Y-m-d');
        $hallName = preg_replace('/[^A-Za-z0-9\-_]/', '', $location->location_name);
        
        return $examCode . '_' . $date . '_' . $hallName . '_StudentList';
    }
}
