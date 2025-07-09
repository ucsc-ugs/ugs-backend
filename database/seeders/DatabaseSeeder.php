<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory(10)->create();

        User::factory()->create([
            'name' => 'test student',
            'email' => 'student@example.com',
            'student_id' => Student::factory()->create([
                'local' => true,
                'passport_nic' => '123456789V', // Example NIC number
            ])->id
        ]);

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'student_id' => null
        ]); 

        $this->call([
            RoleSeeder::class,
            OrgExamSeeder::class,
        ]);
    }
}
