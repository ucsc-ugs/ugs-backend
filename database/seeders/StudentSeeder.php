<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test user
        $user = \App\Models\User::create([
            'name' => 'Test Student',
            'email' => 'student@example.com',
            'password' => bcrypt('password123'),
        ]);

        // Create a test student record
        \App\Models\Student::create([
            'id' => $user->id,
            'local' => true,
            'passport_nic' => '199012345678',
        ]);

        // Create an international student
        $user2 = \App\Models\User::create([
            'name' => 'International Student',
            'email' => 'international@example.com',
            'password' => bcrypt('password123'),
        ]);

        \App\Models\Student::create([
            'id' => $user2->id,
            'local' => false,
            'passport_nic' => 'P1234567',
        ]);
    }
}
