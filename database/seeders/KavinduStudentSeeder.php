<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;

class KavinduStudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create Student User
        $user = User::updateOrCreate(
            ['email' => 'kavindu@example.com'],
            [
                'name' => 'Kavindu',
                'password' => Hash::make('kp123'),
                'organization_id' => 1,
                'email_verified_at' => now(),
            ]
        );

        // 2. Assign student role
        if (!$user->hasRole('student')) {
            $user->assignRole('student');
        }

        // 3. Create Student profile
        DB::table('students')->updateOrInsert(
            ['id' => $user->id],
            [
                'local' => true,
                'passport_nic' => '200112345678V',
            ]
        );

        // 4. Get an exam from organization 1
        $exam = DB::table('exams')
            ->where('organization_id', 1)
            ->first();

        if (!$exam) {
            $this->command->error('No exam found for organization 1. Please run OrgExamSeeder first.');
            return;
        }

        // 5. Register student for exam
        $studentExam = DB::table('student_exams')->updateOrInsert(
            [
                'student_id' => $user->id,
                'exam_id' => $exam->id,
            ],
            [
                'index_number' => 'IDX' . time() . rand(100, 999),
                'status' => 'registered',
                'date' => Carbon::now()->addDays(30)->format('Y-m-d'),
                'created_at' => Carbon::now()->subDays(5),
                'updated_at' => Carbon::now()->subDays(5),
            ]
        );

        $studentExamId = DB::table('student_exams')
            ->where('student_id', $user->id)
            ->where('exam_id', $exam->id)
            ->value('id');

        // 6. Create Payment record (status_code 2 = completed/paid)
        DB::table('payments')->updateOrInsert(
            ['student_exam_id' => $studentExamId],
            [
                'payment_id' => 'PAY' . time() . rand(1000, 9999),
                'payhere_amount' => $exam->price ?? 2000.00,
                'payhere_currency' => 'LKR',
                'status_code' => 2, // 2 = Successfully completed
                'status_message' => 'Successfully completed',
                'method' => 'VISA',
                'created_at' => Carbon::now()->subDays(5),
                'updated_at' => Carbon::now()->subDays(5),
            ]
        );

        $this->command->info('✅ Test Student Created Successfully!');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('Name: Kavindu');
        $this->command->info('Email: kavindu@example.com');
        $this->command->info('Password: kp123');
        $this->command->info('Organization ID: 1');
        $this->command->info('Role: student');
        $this->command->info('Exam Registered: ' . $exam->name);
        $this->command->info('Payment Status: PAID (status_code: 2)');
        $this->command->info('Registration Date: 5 days ago');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}
