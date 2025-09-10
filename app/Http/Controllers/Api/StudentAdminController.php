<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\User;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentAdminController extends Controller
{
    /**
     * List students (paginated). Org admins see students in their org; super_admin sees all.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $isSuper = $user->isSuperAdmin();
        $perPage = (int) $request->input('per_page', 10);

        $query = User::whereHas('student')
            ->with(['student', 'organization', 'exams']);

        if (!$isSuper) {
            $orgAdmin = $user->orgAdmin;
            if (!$orgAdmin) {
                return response()->json(['message' => 'Unauthorized. Organization admin access required.'], 403);
            }
            $query->where('organization_id', $orgAdmin->organization_id);
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

    /**
     * Show a single student
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $isSuper = $user->isSuperAdmin();

        $studentUser = User::with(['student', 'organization', 'exams'])->findOrFail($id);

        if (!$isSuper) {
            $orgAdmin = $user->orgAdmin;
            if (!$orgAdmin || $studentUser->organization_id !== $orgAdmin->organization_id) {
                return response()->json(['message' => 'Unauthorized. You can only view students from your organization.'], 403);
            }
        }

        return response()->json([
            'message' => 'Student retrieved',
            'data' => new StudentResource($studentUser)
        ]);
    }

    /**
     * Create a new student and user
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
            'passport_nic' => ['required', 'string', 'unique:students,passport_nic'],
            'organization_id' => ['sometimes', 'integer', 'exists:organizations,id'],
        ]);

        try {
            DB::beginTransaction();

            $student = Student::create([
                'local' => $data['local'],
                'passport_nic' => $data['passport_nic']
            ]);

            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
                'student_id' => $student->id,
            ];

            // if org admin creating, attach to their org
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

            $newUser = User::create($userData);
            $newUser->assignRole('student');

            DB::commit();

            return response()->json([
                'message' => 'Student created',
                'data' => new StudentResource($newUser)
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Student creation error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create student', 'errors' => ['general' => $e->getMessage()]], 500);
        }
    }

    /**
     * Update student and user
     */
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
            'passport_nic' => ['sometimes', 'string', 'unique:students,passport_nic,' . ($target->student->id ?? 'NULL')],
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

            return response()->json(['message' => 'Student updated', 'data' => new StudentResource($target->fresh()->load(['student','organization','exams']))]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Student update error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update student', 'errors' => ['general' => $e->getMessage()]], 500);
        }
    }

    /**
     * Delete student and associated user
     */
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
