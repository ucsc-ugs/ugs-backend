<?php

namespace App\Http\Controllers\Api;

// ... existing use statements ...
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\User;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentAdminController extends Controller
{
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

    // ... existing show() method ...
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $isSuper = $user->isSuperAdmin();

    $studentUser = User::with(['student', 'organization'])->findOrFail($id);

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
            'passport_nic' => ['required', 'string', 'unique:students,passport_nic'],
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

