<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Carbon\Carbon;

class RanugaSecondExamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Get Ranuga's user account
        $user = User::where('email', 'ranuga@gmail.com')->first();
        
        if (!$user) {
            $this->command->error('Ranuga user not found.');
            return;
        }

        // 2. Get exams from organization 1
        $registeredExamIds = DB::table('student_exams')
            ->where('student_id', $user->id)
            ->pluck('exam_id')
            ->toArray();

        $this->command->info('Ranuga is currently registered for exam IDs: ' . implode(', ', $registeredExamIds));

        $exam = DB::table('exams')
            ->where('organization_id', 1)
            ->whereNotIn('id', $registeredExamIds)
            ->first();

        if (!$exam) {
            // Get any exam from organization 1
            $exam = DB::table('exams')
                ->where('organization_id', 1)
                ->first();
                
            if (!$exam) {
                $this->command->error('No exams found in organization 1.');
                return;
            }
        }

        // 3. Ensure exam has a registration deadline
        DB::table('exams')
            ->where('id', $exam->id)
            ->update([
                'registration_deadline' => Carbon::now()->addDays(25),
                'updated_at' => now(),
            ]);

        // 4. Create student record if doesn't exist
        $studentExists = DB::table('students')->where('id', $user->id)->exists();
        if (!$studentExists) {
            DB::table('students')->insert([
                'id' => $user->id,
                'local' => true,
                'passport_nic' => '199712345678V',
            ]);
        }

        // 5. Register student for the exam
        $studentExamId = DB::table('student_exams')->insertGetId([
            'index_number' => 'IDX' . time() . rand(100, 999),
            'student_id' => $user->id,
            'exam_id' => $exam->id,
            'status' => 'registered',
            'date' => Carbon::now()->addDays(40)->format('Y-m-d'),
            'created_at' => Carbon::now()->subDays(7),
            'updated_at' => Carbon::now()->subDays(7),
        ]);

        // 6. Create Payment record (status_code 2 = completed/paid)
        DB::table('payments')->insert([
            'student_exam_id' => $studentExamId,
            'payment_id' => 'PAY' . time() . rand(1000, 9999),
            'payhere_amount' => $exam->price ?? 2000.00,
            'payhere_currency' => 'LKR',
            'status_code' => 2, // 2 = Successfully completed
            'status_message' => 'Successfully completed',
            'method' => 'VISA',
            'created_at' => Carbon::now()->subDays(7),
            'updated_at' => Carbon::now()->subDays(7),
        ]);

        $this->command->info('✅ Exam Registration Added Successfully!');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('Student: Ranuga Geenal (ranuga@gmail.com)');
        $this->command->info('Exam: ' . $exam->name);
        $this->command->info('Exam Code: ' . ($exam->code_name ?? 'N/A'));
        $this->command->info('Payment Status: PAID (status_code: 2)');
        $this->command->info('Registration Date: 7 days ago');
        $this->command->info('Exam Date: ' . Carbon::now()->addDays(40)->format('Y-m-d'));
        $this->command->info('Amount Paid: LKR ' . number_format($exam->price ?? 2000, 2));
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('');
        $this->command->info('✓ Ranuga now has a paid exam registration!');
        $this->command->info('✓ You can view this in the Student Management dashboard.');
    }
}
