<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Student;
use App\Models\Organization;

class SpecificPaidTestStudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $org = Organization::first();
        if (!$org) {
            $this->command->error('No organization found. Seed organizations first.');
            return;
        }

        // Ensure at least one exam exists for the organization
        $exam = DB::table('exams')->where('organization_id', $org->id)->first();
        if (!$exam) {
            $this->command->error('No exams found for organization. Please seed exams first.');
            return;
        }

        // Create or update a test student user
        $email = 'finance.student@example.com';
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Finance Test Student',
                'password' => Hash::make('password123'),
                'organization_id' => $org->id,
            ]
        );
        if (!$user->hasRole('student')) {
            $user->assignRole('student');
        }

        Student::firstOrCreate(
            ['id' => $user->id],
            [
                'local' => true,
                'passport_nic' => 'FIN123456V',
            ]
        );

        // Create a registration in student_exams
        $indexNumber = 'FIN' . now()->format('YmdHis') . rand(100,999);
        $studentExamId = DB::table('student_exams')->insertGetId([
            'student_id' => $user->id,
            'exam_id' => $exam->id,
            'index_number' => $indexNumber,
            'status' => 'registered',
            'attended' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a PAID payment record linked to the registration
        $amountCol = Schema::hasColumn('payments', 'payhere_amount') ? 'payhere_amount' : (Schema::hasColumn('payments', 'amount') ? 'amount' : null);
        $paymentData = [
            'student_exam_id' => $studentExamId,
            'status_code' => 2,
            'status_message' => 'Payment success (seeded)',
            'order_id' => 'SEED-' . $studentExamId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($amountCol) {
            $paymentData[$amountCol] = $exam->price ?? 0;
        }
        if (Schema::hasColumn('payments', 'currency')) {
            $paymentData['currency'] = 'LKR';
        }
        if (Schema::hasColumn('payments', 'method')) {
            $paymentData['method'] = 'seed';
        }
        DB::table('payments')->insert($paymentData);

        $this->command->info("Created paid test student: {$email} (password: password123), exam_id={$exam->id}");
    }
}
