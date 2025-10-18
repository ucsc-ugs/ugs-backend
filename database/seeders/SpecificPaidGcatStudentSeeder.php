<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Student;

class SpecificPaidGcatStudentSeeder extends Seeder
{
    public function run(): void
    {
        // Find GCAT exam by name (as seeded by OrgExamSeeder)
        $exam = DB::table('exams')->where('name', 'General Computing Aptitude Test')->first();
        if (!$exam) {
            $this->command->error('GCAT exam not found. Please run OrgExamSeeder first.');
            return;
        }

        $email = 'gcat.student@example.com';
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'GCAT Paid Student',
                'password' => Hash::make('password123'),
                'organization_id' => $exam->organization_id,
            ]
        );
        if (!$user->hasRole('student')) {
            $user->assignRole('student');
        }

        Student::firstOrCreate(
            ['id' => $user->id],
            [
                'local' => true,
                'passport_nic' => 'GCAT123456V',
            ]
        );

        // Create registration with unique index_number
        $indexNumber = 'GCAT' . now()->format('YmdHis') . rand(100,999);
        $studentExamId = DB::table('student_exams')->insertGetId([
            'student_id' => $user->id,
            'exam_id' => $exam->id,
            'index_number' => $indexNumber,
            'status' => 'registered',
            'attended' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert paid payment, schema-aware
        $amountCol = Schema::hasColumn('payments', 'payhere_amount') ? 'payhere_amount' : (Schema::hasColumn('payments', 'amount') ? 'amount' : null);
        $payment = [
            'student_exam_id' => $studentExamId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (Schema::hasColumn('payments', 'status_code')) {
            $payment['status_code'] = 2;
        } elseif (Schema::hasColumn('payments', 'status')) {
            $payment['status'] = 'success';
        }
        if (Schema::hasColumn('payments', 'status_message')) {
            $payment['status_message'] = 'Payment success (seeded)';
        }
        if ($amountCol) {
            $payment[$amountCol] = $exam->price ?? 0;
        }
        if (Schema::hasColumn('payments', 'currency')) {
            $payment['currency'] = 'LKR';
        }
        if (Schema::hasColumn('payments', 'method')) {
            $payment['method'] = 'seed';
        }
        if (Schema::hasColumn('payments', 'order_id')) {
            $payment['order_id'] = 'SEED-GCAT-' . $studentExamId;
        }
        DB::table('payments')->insert($payment);

        $this->command->info("GCAT paid student created: {$email} / password123, exam_id={$exam->id}");
    }
}
