<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;

class BhagyaGCCTSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates a paid GCCT exam registration for Bhagya student
     */
    public function run(): void
    {
        // 1. Get Bhagya's user account
        $user = User::where('email', 'bhagya@gmail.com')->first();
        
        if (!$user) {
            $this->command->error('❌ Bhagya user not found. Please run StudentsSeeder first.');
            return;
        }

        // 2. Find GCCT exam (General Computing Competency Test)
        $exam = DB::table('exams')
            ->where('code_name', 'GCCT')
            ->where('organization_id', 1) // UCSC organization
            ->first();

        if (!$exam) {
            $this->command->error('❌ GCCT exam not found. Please run ExamsSeeder first.');
            return;
        }

        // 3. Check if Bhagya is already registered for GCCT
        $existingRegistration = DB::table('student_exams')
            ->where('student_id', $user->id)
            ->where('exam_id', $exam->id)
            ->exists();

        if ($existingRegistration) {
            $this->command->warn('⚠️  Bhagya is already registered for GCCT exam.');
            return;
        }

        // 4. Get an exam date for GCCT (prefer future date)
        $examDate = DB::table('exam_dates')
            ->where('exam_id', $exam->id)
            ->where('date', '>=', now())
            ->first();

        if (!$examDate) {
            // If no future dates, get any exam date
            $examDate = DB::table('exam_dates')
                ->where('exam_id', $exam->id)
                ->first();
        }

        // 5. Ensure exam has a registration deadline
        if (!$exam->registration_deadline) {
            DB::table('exams')
                ->where('id', $exam->id)
                ->update([
                    'registration_deadline' => Carbon::now()->addDays(30),
                    'updated_at' => now(),
                ]);
        }

        // 6. Generate unique index number for GCCT
        // Format: GCC + YY + MM + sequence (e.g., GCC25100012)
        $year = date('y');
        $month = date('m');
        $count = DB::table('student_exams')
            ->join('exams', 'student_exams.exam_id', '=', 'exams.id')
            ->where('exams.code_name', 'GCCT')
            ->count() + 1;
        $sequence = str_pad($count, 3, '0', STR_PAD_LEFT);
        $indexNumber = 'GCC' . $year . $month . $sequence;

        // 7. Register Bhagya for GCCT exam
        $studentExamId = DB::table('student_exams')->insertGetId([
            'index_number' => $indexNumber,
            'student_id' => $user->id,
            'exam_id' => $exam->id,
            'status' => 'registered',
            'attended' => false,
            'selected_exam_date_id' => $examDate->id ?? null,
            'date' => $examDate ? $examDate->date : Carbon::now()->addDays(45)->format('Y-m-d'),
            'created_at' => Carbon::now()->subDays(5), // Registered 5 days ago
            'updated_at' => Carbon::now()->subDays(5),
        ]);

        // 8. Create Payment record (status_code 2 = completed/paid)
        $paymentAmount = $exam->price ?? 4500.00; // GCCT default price
        
        DB::table('payments')->insert([
            'student_exam_id' => $studentExamId,
            'payment_id' => 'GCCT_PAY_' . time() . rand(1000, 9999),
            'payhere_amount' => $paymentAmount,
            'payhere_currency' => 'LKR',
            'status_code' => 2, // 2 = Successfully completed (PayHere success)
            'status_message' => 'Successfully completed',
            'method' => 'MASTER', // MasterCard payment
            'md5sig' => md5('gcct_payment_' . $studentExamId),
            'created_at' => Carbon::now()->subDays(5), // Payment made 5 days ago
            'updated_at' => Carbon::now()->subDays(5),
        ]);

        // Display success information
        $this->command->info('');
        $this->command->info('✅ GCCT Exam Registration Added Successfully!');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('📚 Exam Details:');
        $this->command->info('   Exam Name: ' . $exam->name);
        $this->command->info('   Exam Code: ' . $exam->code_name);
        $this->command->info('   Organization: UCSC (ID: 1)');
        $this->command->info('');
        $this->command->info('👤 Student Details:');
        $this->command->info('   Name: Bhagya Semage');
        $this->command->info('   Email: bhagya@gmail.com');
        $this->command->info('   Index Number: ' . $indexNumber);
        $this->command->info('');
        $this->command->info('💰 Payment Details:');
        $this->command->info('   Payment Status: ✓ PAID (status_code: 2)');
        $this->command->info('   Amount: LKR ' . number_format($paymentAmount, 2));
        $this->command->info('   Payment Method: MasterCard');
        $this->command->info('   Payment Date: ' . Carbon::now()->subDays(5)->format('Y-m-d H:i:s'));
        $this->command->info('');
        $this->command->info('📅 Registration Details:');
        $this->command->info('   Registration Date: ' . Carbon::now()->subDays(5)->format('Y-m-d'));
        $this->command->info('   Exam Date: ' . ($examDate ? Carbon::parse($examDate->date)->format('Y-m-d H:i') : 'TBD'));
        $this->command->info('   Status: Registered & Paid');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('');
        $this->command->info('✓ Bhagya now has a PAID GCCT exam registration!');
        $this->command->info('✓ View in Finance Dashboard for revenue tracking');
        $this->command->info('✓ View in Student Management to see student details');
        $this->command->info('');
    }
}
