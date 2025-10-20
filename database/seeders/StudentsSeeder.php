<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // NIC or Passport numbers create
        $ranuga = Student::create([
            'local' => true,
            'passport_nic' => '200200090009',
        ]);
        $mandinu = Student::create([
            'local' => false,
            'passport_nic' => 'N356200090009',
        ]);
        $bhagya = Student::create([
            'local' => true,
            'passport_nic' => '200200090010',
        ]);

        // Create new student accounts
        $students = [
            [
                'name' => 'Ranuga Geenal',
                'email' => 'ranuga@gmail.com',
                'student_id' => $ranuga->id
            ],
            [
                'name' => 'Mandinu Maneth',
                'email' => 'mandinu@gmail.com',
                'student_id' => $mandinu->id
            ],
            [
                'name' => 'Bhagya Semage',
                'email' => 'bhagya@gmail.com',
                'student_id' => $bhagya->id
            ],
        ];

        foreach ($students as $studentData) {
            User::firstOrCreate(
                ['email' => $studentData['email']],
                [
                    'name' => $studentData['name'],
                    'password' => Hash::make('password'),
                    'user_type' => 'student',
                    'student_id' => $studentData['student_id'],
                ]
            );
        } 
    }
}
