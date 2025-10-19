<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamDate;
use App\Models\ExamDateLocation;
use App\Models\Location;
use App\Models\StudentExam;
use App\Traits\CreatesNotifications;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Pail\ValueObjects\Origin\Console;
use Illuminate\Validation\Rule;

class ExamController extends Controller
{
    use CreatesNotifications;

    /**
     * Display a public listing of all exams for students
     */
    public function publicIndex(): JsonResponse
    {
        $exams = Exam::with(['organization', 'examDates.locations'])
            ->where('created_at', '<=', now()) // Only show created exams
            ->get();

        return response()->json([
            'message' => 'Exams retrieved successfully',
            'data' => $exams
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        // Check if user has required roles
        $user = $request->user();
        $user->load('orgAdmin'); // Load the orgAdmin relationship

        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        // Super admin can see all exams
        if ($user->hasRole('super_admin')) {
            $exams = Exam::with(['organization:id,name', 'examDates' => function ($query) {
                $query->withCount([
                    'studentExams as current_registrations' => function ($q) {
                        $q->whereHas('payment', function ($payment) {
                            $payment->where('status_code', 2);
                        });
                    }
                ])->with(['locations:id,location_name,capacity']);
            }])
            ->withCount([
                'studentExams as total_students_enrolled' => function ($q) {
                    $q->whereHas('payment', function ($payment) {
                        $payment->where('status_code', 2);
                    });
                }
            ])
            ->get();
        }
        // Org admin can only see exams related to their organization
        elseif ($user->hasRole('org_admin')) {
            // Get organization_id from user's organization_id or orgAdmin relationship
            $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

            if (!$organizationId) {
                return response()->json([
                    'message' => 'No organization found for this user'
                ], 404);
            }

            $exams = Exam::with(['organization:id,name', 'examDates' => function ($query) {
                $query->withCount([
                    'studentExams as current_registrations' => function ($q) {
                        $q->whereHas('payment', function ($payment) {
                            $payment->where('status_code', 2);
                        });
                    }
                ])->with(['locations:id,location_name,capacity']);
            }])
            ->withCount([
                'studentExams as total_students_enrolled' => function ($q) {
                    $q->whereHas('payment', function ($payment) {
                        $payment->where('status_code', 2);
                    });
                }
            ])
            ->where('organization_id', $organizationId)->get();
        }

        // Transform the data to include max participants (calculated from locations)
        $transformedExams = $exams->map(function ($exam) {
            $exam->examDates = $exam->examDates->map(function ($examDate) {
                // Calculate total capacity from all locations
                $maxParticipants = $examDate->locations->sum('capacity');
                $examDate->max_participants = $maxParticipants;

                return $examDate;
            });

            // Add total students count at exam level for easier frontend access
            $exam->students_count = $exam->total_students_enrolled;

            return $exam;
        });

        return response()->json([
            'message' => 'Exams retrieved successfully',
            'data' => $transformedExams
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
            'code_name' => 'required|string|max:255|unique:exams,code_name',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'organization_id' => 'required|exists:organizations,id',
            'registration_deadline' => 'nullable|date',
            'exam_dates' => 'nullable|array',
            'exam_dates.*.date' => 'required|date_format:Y-m-d\TH:i',
            'exam_dates.*.location' => 'nullable|string|max:255',
            'exam_dates.*.location_id' => 'nullable|exists:locations,id',
            'exam_dates.*.location_ids' => 'nullable|array',
            'exam_dates.*.location_ids.*' => 'exists:locations,id'
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
            'code_name' => $validated['code_name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'organization_id' => $validated['organization_id'],
            'registration_deadline' => $validated['registration_deadline'] ?? null
        ]);

        // Create exam dates if provided
        if (!empty($validated['exam_dates'])) {
            foreach ($validated['exam_dates'] as $examDateData) {
                $examDate = ExamDate::create([
                    'exam_id' => $exam->id,
                    'date' => $examDateData['date'],
                    'location' => $examDateData['location'] ?? null,
                    'location_id' => $examDateData['location_id'] ?? null
                ]);

                // Handle multiple locations
                if (!empty($examDateData['location_ids'])) {
                    foreach ($examDateData['location_ids'] as $index => $locationId) {
                        ExamDateLocation::create([
                            'exam_date_id' => $examDate->id,
                            'location_id' => $locationId,
                            'priority' => $index + 1, // 1-based priority
                            'current_registrations' => 0
                        ]);
                    }
                } elseif (!empty($examDateData['location_id'])) {
                    // Backward compatibility - single location
                    ExamDateLocation::create([
                        'exam_date_id' => $examDate->id,
                        'location_id' => $examDateData['location_id'],
                        'priority' => 1,
                        'current_registrations' => 0
                    ]);
                }
            }
        }

        // Load the exam with its dates and locations for response
        $exam->load(['examDates.locations', 'examDates.examDateLocations.location']);

        return response()->json([
            'message' => 'Exam created successfully',
            'data' => $exam
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $code_name): JsonResponse
    {
        $code_name = strtoupper($code_name);
        $exam = Exam::with(['organization', 'examDates'])->where('code_name', $code_name)->first();

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
            'code_name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('exams', 'code_name')->ignore($id)
            ],
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'organization_id' => 'sometimes|exists:organizations,id',
            'registration_deadline' => 'nullable|date',
            'exam_dates' => 'nullable|array',
            'exam_dates.*.date' => 'required|date_format:Y-m-d\TH:i',
            'exam_dates.*.location' => 'nullable|string|max:255',
            'exam_dates.*.location_id' => 'nullable|exists:locations,id',
            'exam_dates.*.location_ids' => 'nullable|array',
            'exam_dates.*.location_ids.*' => 'exists:locations,id'
        ]);

        // Update exam basic info
        $exam->update([
            'name' => $validated['name'] ?? $exam->name,
            'code_name' => $validated['code_name'] ?? $exam->code_name,
            'description' => $validated['description'] ?? $exam->description,
            'price' => $validated['price'] ?? $exam->price,
            'organization_id' => $validated['organization_id'] ?? $exam->organization_id,
            'registration_deadline' => $validated['registration_deadline'] ?? $exam->registration_deadline
        ]);

        // Update exam dates if provided
        if (array_key_exists('exam_dates', $validated)) {
            // Delete existing exam dates (this will cascade delete exam_date_locations)
            $exam->examDates()->delete();

            // Create new exam dates if provided
            if (!empty($validated['exam_dates'])) {
                foreach ($validated['exam_dates'] as $examDateData) {
                    $examDate = ExamDate::create([
                        'exam_id' => $exam->id,
                        'date' => $examDateData['date'],
                        'location' => $examDateData['location'] ?? null,
                        'location_id' => $examDateData['location_id'] ?? null
                    ]);

                    // Handle multiple locations (new format)
                    if (!empty($examDateData['location_ids']) && is_array($examDateData['location_ids'])) {
                        foreach ($examDateData['location_ids'] as $index => $locationId) {
                            ExamDateLocation::create([
                                'exam_date_id' => $examDate->id,
                                'location_id' => $locationId,
                                'priority' => $index + 1, // Priority based on array order
                                'current_registrations' => 0
                            ]);
                        }
                    }
                    // Handle single location (backward compatibility)
                    elseif (!empty($examDateData['location_id'])) {
                        ExamDateLocation::create([
                            'exam_date_id' => $examDate->id,
                            'location_id' => $examDateData['location_id'],
                            'priority' => 1,
                            'current_registrations' => 0
                        ]);
                    }
                }
            }
        }

        // Load the exam with its dates and locations for response
        $exam->load('examDates.locations');

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
        $user = request()->user();

        // Check if user has required roles
        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $exam = Exam::find($id);

        if (!$exam) {
            return response()->json([
                'message' => 'Exam not found'
            ], 404);
        }

        // For org_admin, ensure they can only delete exams for their organization
        if ($user->hasRole('org_admin')) {
            $user->load('orgAdmin');
            $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

            if (!$organizationId || $exam->organization_id !== $organizationId) {
                return response()->json([
                    'message' => 'Unauthorized. You can only delete exams for your organization.'
                ], 403);
            }
        }

        try {
            $exam->delete();

            return response()->json([
                'message' => 'Exam deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete exam: ' . $e->getMessage()
            ], 500);
        }
    }

    public function regForExam()
    {
        $exam_id = request()->examId;
        $selected_exam_date_id = request()->selectedExamDateId;

        if (!$exam_id) {
            return response()->json([
                'message' => 'Exam ID is required'
            ], 400);
        }

        if (!$selected_exam_date_id) {
            return response()->json([
                'message' => 'Selected exam date is required'
            ], 400);
        }

        $exam = Exam::find($exam_id);
        if (!$exam) {
            return response()->json([
                'message' => 'Exam not found'
            ], 404);
        }

        // Verify that the selected exam date belongs to this exam
        $examDate = ExamDate::where('id', $selected_exam_date_id)
            ->where('exam_id', $exam_id)
            ->first();

        if (!$examDate) {
            return response()->json([
                'message' => 'Invalid exam date selected'
            ], 400);
        }

        // Check if student is already registered for this exam
        $existingRegistration = StudentExam::where('student_id', Auth::id())
            ->where('exam_id', $exam_id)
            ->first();

        if ($existingRegistration) {
            return response()->json([
                'message' => 'You are already registered for this exam'
            ], 400);
        }

        // Find the first available location for this exam's organization
        $assigned_location_id = null;
        $exam_organization_id = $exam->organization_id;

        if ($exam_organization_id) {
            $availableLocation = Location::where('organization_id', $exam_organization_id)
                ->whereHas('examDates', function ($query) use ($selected_exam_date_id) {
                    $query->where('exam_dates.id', $selected_exam_date_id);
                })
                ->get()
                ->first(function ($location) {
                    return $location->hasAvailableCapacity();
                });

            // If no location found directly linked to exam date, try any location in the organization
            if (!$availableLocation) {
                $availableLocation = Location::where('organization_id', $exam_organization_id)
                    ->get()
                    ->first(function ($location) {
                        return $location->hasAvailableCapacity();
                    });
            }

            if ($availableLocation) {
                $assigned_location_id = $availableLocation->id;
            }
        }

        $exam_name = $exam->code_name;

        // Proceed with registration logic
        $registration = StudentExam::create([
            'index_number' => $this->genIndexNumber($exam_name),
            'student_id' => Auth::id(),
            'exam_id' => $exam_id,
            'selected_exam_date_id' => $selected_exam_date_id,
            'assigned_location_id' => $assigned_location_id,
            'payment_id' => null,
        ]);

        $registration->load('exam');

        $paymentController = new PaymentController();
        $payhere_form_data = $paymentController->initiatePayment($registration->id, $registration->exam->price, [
            'first_name' => explode(' ', trim(Auth::user()->name))[0],
            'last_name' => explode(' ', trim(Auth::user()->name))[1] ?? '',
            'email' => Auth::user()->email,
            'phone' => Auth::user()->phone,
        ]);

        return response()->json($payhere_form_data);
    }

    /**
     * Generate a unique index number for the exam registration.
     */
    private function genIndexNumber(string $exam_name): string
    {
        if ($exam_name === 'GCCT') {
            $prefix = 'GCC';
            $year = date('y'); // last two digits of year
            $month = date('m'); // two digit month
            $count = StudentExam::whereHas('exam', function ($query) use ($exam_name) {
                $query->where('code_name', $exam_name);
            })->count() + 1;
            $sequence = str_pad($count, 3, '0', STR_PAD_LEFT);
            $suffix = $year . $month . $sequence;
            return $prefix . $suffix;
        } else if ($exam_name === 'GCAT') {
            $prefix = 'GCT';
            $year = date('y'); // last two digits of year
            $month = date('m'); // two digit month
            $count = StudentExam::whereHas('exam', function ($query) use ($exam_name) {
                $query->where('code_name', $exam_name);
            })->count() + 1;
            $sequence = str_pad($count, 3, '0', STR_PAD_LEFT);
            $suffix = $year . $month . $sequence;
            return $prefix . $suffix;
        } else {
            $prefix = 'ET(' . $exam_name . ')';
            $count = StudentExam::whereHas('exam', function ($query) use ($exam_name) {
                $query->where('code_name', $exam_name);
            })->count() + 1;
            $sequence = str_pad($count, 3, '0', STR_PAD_LEFT);
            $suffix = $sequence;
            return $prefix . $suffix;
        }
    }

    /**
     * Update exam type details only (name, code_name, description, price)
     */
    public function updateType(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        // Check if user has required roles
        if (!$user->hasAnyRole(['super_admin', 'org_admin'])) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        try {
            $exam = Exam::findOrFail($id);

            // For org_admin, ensure the exam belongs to their organization
            if ($user->hasRole('org_admin') && !$user->hasRole('super_admin')) {
                $user->load('orgAdmin');
                if (!$user->orgAdmin || $exam->organization_id !== $user->orgAdmin->organization_id) {
                    return response()->json([
                        'message' => 'Unauthorized. You can only update exams from your organization.'
                    ], 403);
                }
            }

            // Validate request
            $request->validate([
                'name' => 'required|string|max:255',
                'code_name' => 'required|string|max:50',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
            ]);

            // Update only exam type fields
            $exam->update([
                'name' => $request->name,
                'code_name' => $request->code_name,
                'description' => $request->description,
                'price' => $request->price,
            ]);

            Log::info('Exam type updated successfully', [
                'exam_id' => $exam->id,
                'updated_by' => $user->id,
                'fields' => ['name', 'code_name', 'description', 'price']
            ]);

            return response()->json([
                'message' => 'Exam type updated successfully',
                'data' => $exam->fresh()
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Exam not found'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update exam type: ' . $e->getMessage(), [
                'exam_id' => $id,
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to update exam type',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //org admin getting exams of his organization with past exam dates
    public function getExamsWithPastDates(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('orgAdmin');

        if (!$user->hasRole('org_admin')) {
            return response()->json([
                'message' => 'Unauthorized. Org admin access required.'
            ], 403);
        }

        $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'message' => 'No organization found for this user'
            ], 404);
        }

        $exams = Exam::with(['examDates' => function ($query) {
            $query->where('date', '<', now())
                ->withCount('studentExams'); // count registered students
        }])
            ->where('organization_id', $organizationId)
            ->get()
            ->filter(fn($exam) => $exam->examDates->isNotEmpty());

        return response()->json([
            'message' => 'Exams with past dates retrieved successfully',
            'data' => $exams
        ]);
    }

    /**
     * Get students registered for a specific exam date
     */
    public function getExamDateStudents(Request $request, $examDateId): JsonResponse
    {
        $user = $request->user();
        $user->load('orgAdmin');

        if (!$user->hasRole('org_admin')) {
            return response()->json([
                'message' => 'Unauthorized. Org admin access required.'
            ], 403);
        }

        $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'message' => 'No organization found for this user'
            ], 404);
        }

        // Verify the exam date belongs to the org admin's organization
        $examDate = ExamDate::with(['exam', 'studentExams.student'])
            ->whereHas('exam', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->find($examDateId);

        if (!$examDate) {
            return response()->json([
                'message' => 'Exam date not found or access denied'
            ], 404);
        }

        $students = $examDate->studentExams->map(function ($studentExam) {
            return [
                'id' => $studentExam->id,
                'index_number' => $studentExam->index_number,
                'student_id' => $studentExam->student_id,
                'student_name' => $studentExam->student->name ?? 'Unknown',
                'status' => $studentExam->status,
                'attended' => $studentExam->attended,
                'result' => $studentExam->result,
                'selected_exam_date_id' => $studentExam->selected_exam_date_id,
                'assigned_location_id' => $studentExam->assigned_location_id
            ];
        });

        return response()->json([
            'message' => 'Students retrieved successfully',
            'data' => [
                'exam_date' => $examDate,
                'students' => $students
            ]
        ]);
    }

    /**
     * Publish exam results for a specific exam date
     */
    public function publishExamResults(Request $request, $examDateId): JsonResponse
    {
        $user = $request->user();
        $user->load('orgAdmin');

        if (!$user->hasRole('org_admin')) {
            return response()->json([
                'message' => 'Unauthorized. Org admin access required.'
            ], 403);
        }

        $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'message' => 'No organization found for this user'
            ], 404);
        }

        // Validate request
        $request->validate([
            'results' => 'required|array',
            'results.*.index_number' => 'required|string',
            'results.*.result' => 'required|string',
            'results.*.attended' => 'required|boolean'
        ]);

        // Verify the exam date belongs to the org admin's organization
        $examDate = ExamDate::with(['exam'])
            ->whereHas('exam', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->find($examDateId);

        if (!$examDate) {
            return response()->json([
                'message' => 'Exam date not found or access denied'
            ], 404);
        }

        try {
            $updatedCount = 0;
            $errors = [];

            foreach ($request->results as $resultData) {
                $studentExam = StudentExam::where('exam_id', $examDate->exam_id)
                    ->where('index_number', $resultData['index_number'])
                    ->where('selected_exam_date_id', $examDateId)
                    ->first();

                if ($studentExam) {
                    $studentExam->update([
                        'result' => $resultData['result'],
                        'attended' => $resultData['attended']
                    ]);
                    $updatedCount++;
                } else {
                    $errors[] = "Student with index number {$resultData['index_number']} not found for this exam date";
                }
            }

            // Send notifications to all students who registered for this exam date
            if ($updatedCount > 0) {
                $students = StudentExam::where('exam_id', $examDate->exam_id)
                    ->where('selected_exam_date_id', $examDateId)
                    ->get();

                foreach ($students as $student) {
                    $this->createNotification(
                        'Exam Results Published',
                        "Results for {$examDate->exam->name} on " . \Carbon\Carbon::parse($examDate->date)->format('F j, Y') . " have been published. Check your dashboard to view your results.",
                        null,
                        $student->student_id,
                        false
                    );
                }

                Log::info('Result publication notifications sent', [
                    'exam_date_id' => $examDateId,
                    'exam_id' => $examDate->exam_id,
                    'notification_count' => $students->count(),
                    'updated_by' => $user->id
                ]);
            }

            return response()->json([
                'message' => 'Results published successfully',
                'data' => [
                    'updated_count' => $updatedCount,
                    'total_results' => count($request->results),
                    'errors' => $errors
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to publish exam results: ' . $e->getMessage(), [
                'exam_date_id' => $examDateId,
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to publish results',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get published results for a specific exam date (for editing)
     */
    public function getPublishedResults(Request $request, $examDateId): JsonResponse
    {
        $user = $request->user();
        $user->load('orgAdmin');

        if (!$user->hasRole('org_admin')) {
            return response()->json([
                'message' => 'Unauthorized. Org admin access required.'
            ], 403);
        }

        $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'message' => 'No organization found for this user'
            ], 404);
        }

        // Verify the exam date belongs to the org admin's organization
        $examDate = ExamDate::with(['exam'])
            ->whereHas('exam', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->find($examDateId);

        if (!$examDate) {
            return response()->json([
                'message' => 'Exam date not found or access denied'
            ], 404);
        }

        // Get all student exams with published results for this exam date
        $studentExams = StudentExam::with(['student'])
            ->where('exam_id', $examDate->exam_id)
            ->where('selected_exam_date_id', $examDateId)
            ->whereNotNull('result')
            ->get();

        $results = $studentExams->map(function ($studentExam) {
            return [
                'id' => $studentExam->id,
                'index_number' => $studentExam->index_number,
                'student_id' => $studentExam->student_id,
                'student_name' => $studentExam->student->name ?? 'Unknown',
                'result' => $studentExam->result,
                'attended' => $studentExam->attended,
                'status' => $studentExam->status,
                'updated_at' => $studentExam->updated_at
            ];
        });

        return response()->json([
            'message' => 'Published results retrieved successfully',
            'data' => [
                'exam_date' => $examDate,
                'results' => $results
            ]
        ]);
    }

    /**
     * Update a single published result
     */
    public function updatePublishedResult(Request $request, $examDateId, $resultId): JsonResponse
    {
        $user = $request->user();
        $user->load('orgAdmin');

        if (!$user->hasRole('org_admin')) {
            return response()->json([
                'message' => 'Unauthorized. Org admin access required.'
            ], 403);
        }

        $organizationId = $user->organization_id ?? $user->orgAdmin?->organization_id;

        if (!$organizationId) {
            return response()->json([
                'message' => 'No organization found for this user'
            ], 404);
        }

        // Validate request
        $request->validate([
            'result' => 'required|string',
            'attended' => 'required|boolean'
        ]);

        // Verify the exam date belongs to the org admin's organization
        $examDate = ExamDate::with(['exam'])
            ->whereHas('exam', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->find($examDateId);

        if (!$examDate) {
            return response()->json([
                'message' => 'Exam date not found or access denied'
            ], 404);
        }

        // Find the specific student exam result
        $studentExam = StudentExam::where('exam_id', $examDate->exam_id)
            ->where('selected_exam_date_id', $examDateId)
            ->where('id', $resultId)
            ->first();

        if (!$studentExam) {
            return response()->json([
                'message' => 'Result not found or access denied'
            ], 404);
        }

        try {
            $studentExam->update([
                'result' => $request->result,
                'attended' => $request->attended
            ]);

            return response()->json([
                'message' => 'Result updated successfully',
                'data' => [
                    'id' => $studentExam->id,
                    'index_number' => $studentExam->index_number,
                    'result' => $studentExam->result,
                    'attended' => $studentExam->attended,
                    'updated_at' => $studentExam->updated_at
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update published result: ' . $e->getMessage(), [
                'exam_date_id' => $examDateId,
                'result_id' => $resultId,
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to update result',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
