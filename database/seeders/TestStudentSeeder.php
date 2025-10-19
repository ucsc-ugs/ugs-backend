<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestStudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a user for organization 1
        $email = 'teststudent1@example.com';
        $user = User::where('email', $email)->first();
        if (!$user) {
            $user = User::create([
                'name' => 'Test Student 1',
                'email' => $email,
                'password' => bcrypt('password123'),
                'organization_id' => 1,
            ]);
            $user->assignRole('student');
        }

        // Create student record if missing
        $student = Student::find($user->id);
        if (!$student) {
            Student::create([
                'id' => $user->id,
                'local' => true,
                'passport_nic' => 'TS1-0001',
            ]);
        }
    }
}
