<?php

namespace App\Http\Controllers\Api;

// ... existing use statements ...
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\User;
use App\Models\Student;
use App\Traits\ValidatesNicPassport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class StudentAdminController extends Controller
{
    use ValidatesNicPassport;
    // ... existing index() method ...
    public function index(Request $request)
    {
        $user = $request->user();
        $isSuper = $user->isSuperAdmin();
        $perPage = (int) $request->input('per_page', 10);

        $query = User::whereHas('student')
            ->with(['student', 'organization']);

        if (!$isSuper) {
            $orgAdmin = $user->orgAdmin;
            if (!$orgAdmin) {
                return response()->json(['message' => 'Unauthorized. Organization admin access required.'], 403);
            }

            $orgId = $orgAdmin->organization_id;

            // Only include students who have PAID registrations for exams of this organization
            $query->whereExists(function ($q) use ($orgId) {
                $q->select(DB::raw(1))
                    ->from('student_exams')
                    ->join('exams', 'student_exams.exam_id', '=', 'exams.id')
                    ->join('payments', 'payments.student_exam_id', '=', 'student_exams.id')
                    ->whereColumn('student_exams.student_id', 'users.id')
                    ->where('exams.organization_id', $orgId)
                    ->where('payments.status_code', 2); // PayHere success
            });
        }

        if ($q = $request->input('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'ilike', "%{$q}%")
                    ->orWhere('email', 'ilike', "%{$q}%")
                    ->orWhereHas('student', function ($s) use ($q) {
                        $s->where('passport_nic', 'ilike', "%{$q}%");
                    });
            });
        }

        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Attach latest PAID registration per student (exam name + registered_at)
        $students = $paginated->getCollection();
        if ($students->isNotEmpty()) {
            $ids = $students->pluck('id')->all();
            $orgFilterSql = '';
            if (!$isSuper) {
                $orgId = $user->orgAdmin->organization_id ?? null;
                if ($orgId) {
                    $orgFilterSql = ' AND exams.organization_id = ' . ((int) $orgId);
                }
            }
            $subSql = '(
                SELECT student_exams.student_id,
                       exams.name AS exam_name,
                       student_exams.created_at AS registered_at,
                       ROW_NUMBER() OVER (PARTITION BY student_exams.student_id ORDER BY student_exams.created_at DESC) AS rn
                FROM student_exams
                JOIN payments ON payments.student_exam_id = student_exams.id
                JOIN exams ON student_exams.exam_id = exams.id
                WHERE payments.status_code = 2' . $orgFilterSql . '
            ) t';

            $latestPaid = \Illuminate\Support\Facades\DB::table(\Illuminate\Support\Facades\DB::raw($subSql))
                ->where('rn', 1)
                ->whereIn('student_id', $ids)
                ->get()
                ->keyBy('student_id');

            $students->transform(function ($u) use ($latestPaid) {
                $row = $latestPaid->get($u->id);
                if ($row) {
                    // Dynamically attach for resource
                    $u->setAttribute('last_exam_name', $row->exam_name);
                    $u->setAttribute('last_registered_at', $row->registered_at);
                }
                return $u;
            });

            // Also attach compact list of paid exams (names) and total count per student
            $paidRows = \Illuminate\Support\Facades\DB::table('student_exams')
                ->join('payments', 'payments.student_exam_id', '=', 'student_exams.id')
                ->join('exams', 'student_exams.exam_id', '=', 'exams.id')
                ->when(!$isSuper && !empty($orgFilterSql), function ($q) use ($orgId) {
                    $q->where('exams.organization_id', $orgId);
                })
                ->where('payments.status_code', 2)
                ->whereIn('student_exams.student_id', $ids)
                ->orderBy('student_exams.created_at', 'desc')
                ->get(['student_exams.student_id as sid', 'exams.name as exam_name']);

            $byStudent = [];
            foreach ($paidRows as $r) {
                $sid = $r->sid;
                if (!isset($byStudent[$sid])) { $byStudent[$sid] = []; }
                // prevent duplicates if any
                if (!in_array($r->exam_name, $byStudent[$sid], true)) {
                    $byStudent[$sid][] = $r->exam_name;
                }
            }

            $students->transform(function ($u) use ($byStudent) {
                $list = $byStudent[$u->id] ?? [];
                $u->setAttribute('paid_exam_names', array_slice($list, 0, 3));
                $u->setAttribute('paid_exam_count', count($list));
                return $u;
            });
        }

        return response()->json([
            'message' => 'Students retrieved',
            'data' => StudentResource::collection($paginated),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ]
        ]);
    }

    // ... existing show() method ...
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $isSuper = $user->isSuperAdmin();

    $studentUser = User::with(['student', 'organization'])->findOrFail($id);

        if (!$isSuper) {
            $orgAdmin = $user->orgAdmin;
            if (!$orgAdmin) {
                return response()->json(['message' => 'Unauthorized. Organization admin access required.'], 403);
            }

            $orgId = $orgAdmin->organization_id;

            // Require PAID registration in this organization to view details
            $hasPaidExamInOrg = DB::table('student_exams')
                ->join('exams', 'student_exams.exam_id', '=', 'exams.id')
                ->join('payments', 'payments.student_exam_id', '=', 'student_exams.id')
                ->where('student_exams.student_id', $studentUser->id)
                ->where('exams.organization_id', $orgId)
                ->where('payments.status_code', 2)
                ->exists();

            if (!$hasPaidExamInOrg) {
                return response()->json(['message' => 'Unauthorized. Visible only after exam fee is paid for your organization\'s exams.'], 403);
            }
        }

        // Build exam registrations explicitly to avoid relying on potentially missing pivot columns
        $orgId = !$isSuper ? ($user->orgAdmin->organization_id ?? null) : null;
        // Resolve column names based on actual schema to avoid missing-column errors
        $examNameCol = Schema::hasColumn('exams', 'name')
            ? 'exams.name'
            : (Schema::hasColumn('exams', 'title') ? 'exams.title' : null);
        $examCodeCol = Schema::hasColumn('exams', 'code_name')
            ? 'exams.code_name'
            : (Schema::hasColumn('exams', 'code') ? 'exams.code' : null);
        $startCol = Schema::hasColumn('exams', 'start_date')
            ? 'exams.start_date'
            : (Schema::hasColumn('exams', 'registration_deadline') ? 'exams.registration_deadline' : null);
        $endCol = Schema::hasColumn('exams', 'end_date') ? 'exams.end_date' : null;

        $selects = [
            'exams.id as exam_id',
            $examNameCol ? DB::raw($examNameCol . ' as exam_name') : DB::raw("'Exam' as exam_name"),
            $examCodeCol ? DB::raw($examCodeCol . ' as exam_code') : DB::raw('NULL as exam_code'),
            $startCol ? DB::raw($startCol . ' as start_date') : DB::raw('NULL as start_date'),
            $endCol ? DB::raw($endCol . ' as end_date') : DB::raw('NULL as end_date'),
            'student_exams.created_at as registered_at',
            'student_exams.attended as attended',
            'student_exams.status as reg_status',
        ];

        $orderCol = $startCol ? $startCol : 'student_exams.created_at';

        $examRegs = DB::table('student_exams')
            ->join('exams', 'student_exams.exam_id', '=', 'exams.id')
            ->when(!$isSuper && $orgId, function ($q) use ($orgId) {
                $q->where('exams.organization_id', $orgId);
            })
            ->where('student_exams.student_id', $studentUser->id)
            ->select($selects)
            ->orderBy(DB::raw($orderCol), 'desc')
            ->get()
            ->map(function ($row) {
                $end = $row->end_date ? Carbon::parse($row->end_date) : null;
                $start = $row->start_date ? Carbon::parse($row->start_date) : null;
                // Completion heuristic: attended=true OR end_date/start_date in past OR status indicates completion
                $completed = false;
                if (!is_null($row->attended)) {
                    $completed = (bool)$row->attended;
                } elseif ($end) {
                    $completed = $end->isPast();
                } elseif ($start) {
                    $completed = $start->isPast();
                }
                if (!$completed && !empty($row->reg_status)) {
                    $completed = in_array(strtolower((string)$row->reg_status), ['completed','finished','graded','done','closed']);
                }

                return [
                    'id' => $row->exam_id,
                    'name' => $row->exam_name,
                    'code' => $row->exam_code,
                    'exam_date' => $row->start_date,
                    'end_date' => $row->end_date,
                    'registered_at' => $row->registered_at,
                    'completed' => $completed,
                ];
            })
            ->values();

        $resource = new StudentResource($studentUser);
        $data = array_merge($resource->toArray($request), [
            'exam_registrations' => $examRegs,
        ]);

        return response()->json([
            'message' => 'Student retrieved',
            'data' => $data,
        ]);
    }

    /**
     * Create a new student and user (CORRECTED LOGIC)
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $isSuper = $user->isSuperAdmin();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8'],
            'local' => ['required', 'boolean'],
            'passport_nic' => [
                'required',
                'string',
                'unique:students,passport_nic',
                'max:20',
                function ($attribute, $value, $fail) use ($request) {
                    $isLocal = $request->input('local');
                    if (!static::validateNicOrPassport($value, $isLocal)) {
                        if ($isLocal) {
                            $fail('The NIC number must be a valid Sri Lankan NIC number (format: 9 digits + V/X or 12 digits).');
                        } else {
                            $fail('The passport number must be a valid format (6-15 alphanumeric characters).');
                        }
                    }
                }
            ],
            'organization_id' => ['sometimes', 'integer', 'exists:organizations,id'],
        ]);

        try {
            DB::beginTransaction();

            // Step 1: Prepare User data
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
            ];

            // Step 2: Assign organization_id to the User
            if (!$isSuper) {
                $orgAdmin = $user->orgAdmin;
                if (!$orgAdmin) {
                    return response()->json(['message' => 'Unauthorized. Organization admin access required.'], 403);
                }
                $userData['organization_id'] = $orgAdmin->organization_id;
            } else {
                if (!empty($data['organization_id'])) {
                    $userData['organization_id'] = $data['organization_id'];
                }
            }

            // Step 3: Create the User first
            $newUser = User::create($userData);
            $newUser->assignRole('student');

            // Step 4: Create the Student using the new User's ID
            $student = Student::create([
                'id' => $newUser->id, // Use the User's ID as the Student's primary key
                'local' => $data['local'],
                'passport_nic' => $data['passport_nic']
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Student created',
                'data' => new StudentResource($newUser->load('student')) // Eager load the new student data
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Student creation error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create student', 'errors' => ['general' => $e->getMessage()]], 500);
        }
    }


    // ... existing update() method ...
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $isSuper = $user->isSuperAdmin();

        $target = User::with('student')->findOrFail($id);

        if (!$isSuper) {
            $orgAdmin = $user->orgAdmin;
            if (!$orgAdmin || $target->organization_id !== $orgAdmin->organization_id) {
                return response()->json(['message' => 'Unauthorized. You can only update students from your organization.'], 403);
            }
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $target->id],
            'password' => ['sometimes', 'nullable', 'min:8'],
            'local' => ['sometimes', 'boolean'],
            'passport_nic' => [
                'sometimes',
                'string',
                'unique:students,passport_nic,' . ($target->student->id ?? 'NULL'),
                'max:20',
                function ($attribute, $value, $fail) use ($request, $target) {
                    // Get the local value from request or existing student record
                    $isLocal = $request->input('local', $target->student->local ?? true);
                    if (!static::validateNicOrPassport($value, $isLocal)) {
                        if ($isLocal) {
                            $fail('The NIC number must be a valid Sri Lankan NIC number (format: 9 digits + V/X or 12 digits).');
                        } else {
                            $fail('The passport number must be a valid format (6-15 alphanumeric characters).');
                        }
                    }
                }
            ],
        ]);

        try {
            DB::beginTransaction();

            if (isset($data['name']) || isset($data['email']) || array_key_exists('password', $data)) {
                $update = [];
                if (isset($data['name'])) $update['name'] = $data['name'];
                if (isset($data['email'])) $update['email'] = $data['email'];
                if (isset($data['password']) && $data['password']) $update['password'] = bcrypt($data['password']);
                if (!empty($update)) $target->update($update);
            }

            if ($target->student) {
                $studentUpdate = [];
                if (isset($data['local'])) $studentUpdate['local'] = $data['local'];
                if (isset($data['passport_nic'])) $studentUpdate['passport_nic'] = $data['passport_nic'];
                if (!empty($studentUpdate)) $target->student->update($studentUpdate);
            }

            DB::commit();

            return response()->json(['message' => 'Student updated', 'data' => new StudentResource($target->fresh()->load(['student','organization']))]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Student update error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update student', 'errors' => ['general' => $e->getMessage()]], 500);
        }
    }

    // ... existing destroy() method ...
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $isSuper = $user->isSuperAdmin();

        $target = User::with('student')->findOrFail($id);

        if (!$isSuper) {
            $orgAdmin = $user->orgAdmin;
            if (!$orgAdmin || $target->organization_id !== $orgAdmin->organization_id) {
                return response()->json(['message' => 'Unauthorized. You can only delete students from your organization.'], 403);
            }
        }

        try {
            DB::beginTransaction();

            if ($target->student) {
                $target->student->delete();
            }
            $target->delete();

            DB::commit();

            return response()->json(['message' => 'Student deleted']);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Student deletion error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to delete student', 'errors' => ['general' => $e->getMessage()]], 500);
        }
    }
}

