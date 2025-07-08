<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UpdateUserRequest;
use App\Http\Requests\V1\CreateStudentUserRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class StudentController extends Controller
{
    /**
     * Create a new student account
     */
    public function studentRegister(CreateStudentUserRequest $request)
    {
        $validated = $request->validated();

        try {
            $student = Student::create([
                'local' => $validated['local'],
                'passport_nic' => $validated['passport_nic'],
            ]);

            // Associate the student with a user
            $student->user()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
            ]);
        } catch (\Exception $e) {
            if (isset($student)) {
                $student->delete();
            }
            return response()->json([
                'message' => 'Failed to register student',
                'error' => $e->getMessage()
            ], 500);
        }

        return response()->json([
            'message' => 'Student registered successfully',
            'data' => UserResource::make($student->user->load('student'))
        ]);
    }
}
