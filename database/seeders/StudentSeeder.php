<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ensure the student role exists
        Role::firstOrCreate(['name' => 'student']);

        // Create or update a test user
        $user = \App\Models\User::updateOrCreate(
            ['email' => 'student@example.com'],
            [
                'name' => 'Amal Perera',
                'password' => bcrypt('password123'),
                'organization_id' => 1,
            ]
        );

        if (! $user->hasRole('student')) {
            $user->assignRole('student');
        }

        // Create a test student record if missing
        \App\Models\Student::firstOrCreate(
            ['id' => $user->id],
            [
                'local' => true,
                'passport_nic' => '991234567V',
            ]
        );

        // Create an international student
        $user2 = \App\Models\User::updateOrCreate(
            ['email' => 'international@example.com'],
            [
                'name' => 'John Smith',
                'password' => bcrypt('password123'),
                'organization_id' => 1,
            ]
        );

        if (! $user2->hasRole('student')) {
            $user2->assignRole('student');
        }

        \App\Models\Student::firstOrCreate(
            ['id' => $user2->id],
            [
                'local' => false,
                'passport_nic' => 'P9876543',
            ]
        );
    }
}
