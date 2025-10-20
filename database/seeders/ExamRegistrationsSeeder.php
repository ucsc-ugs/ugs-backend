<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamDate;
use App\Models\ExamDateLocation;
use App\Models\Payment;
use App\Models\RevenueTransaction;
use App\Models\StudentExam;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExamRegistrationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all students
        $students = User::where('user_type', 'student')->whereNotNull('student_id')->get();
        
        if ($students->isEmpty()) {
            $this->command->warn('No students found. Please run StudentsSeeder first.');
            return;
        }

        // Get all exams with their dates
        $exams = Exam::with(['examDates.examDateLocations.location'])->get();
        
        if ($exams->isEmpty()) {
            $this->command->warn('No exams found. Please run ExamsSeeder first.');
            return;
        }

        $this->command->info('Creating exam registrations...');

        $indexCounter = 1000; // Starting index number
        $paymentStatuses = ['completed', 'pending', 'failed'];
        $registrationStatuses = ['registered', 'pending', 'rejected'];

        foreach ($students as $student) {
            // Randomly decide how many exams this student will register for (0-2)
            $examCount = rand(0, 2);
            
            if ($examCount === 0) {
                $this->command->info("Student '{$student->name}' - No registrations");
                continue;
            }

            // Randomly select exams for this student
            $selectedExams = $exams->random(min($examCount, $exams->count()));

            foreach ($selectedExams as $exam) {
                // Skip if exam has no dates
                if ($exam->examDates->isEmpty()) {
                    continue;
                }

                // Randomly select an exam date
                $examDate = $exam->examDates->random();

                // Get available location with capacity
                $availableLocation = $examDate->getNextAvailableHall();
                
                if (!$availableLocation) {
                    $this->command->warn("  - No available locations for exam '{$exam->name}' on {$examDate->date->format('Y-m-d')}");
                    continue;
                }

                // Generate unique index number
                $indexNumber = 'IDX' . str_pad($indexCounter++, 6, '0', STR_PAD_LEFT);

                // Randomly determine payment and registration status
                $paymentStatus = $paymentStatuses[array_rand($paymentStatuses)];
                $registrationStatus = $paymentStatus === 'completed' 
                    ? 'registered' 
                    : ($paymentStatus === 'pending' ? 'pending' : 'rejected');

                // Create student exam registration
                $studentExam = StudentExam::create([
                    'index_number' => $indexNumber,
                    'student_id' => $student->id,
                    'exam_id' => $exam->id,
                    'selected_exam_date_id' => $examDate->id,
                    'assigned_location_id' => $availableLocation->location_id,
                    'status' => $registrationStatus,
                    'attended' => false,
                    'result' => null,
                ]);

                // Update current registrations count for the location
                $availableLocation->increment('current_registrations');

                // Create payment record
                $amount = $exam->price;
                $commissionRate = $exam->commission_rate ?? 5.0;
                $commissionAmount = round(($amount * $commissionRate) / 100, 2);
                $netAmount = $amount - $commissionAmount;

                $payment = Payment::create([
                    'student_exam_id' => $studentExam->id,
                    'payment_id' => 'PAY' . Str::upper(Str::random(10)),
                    'payhere_amount' => $amount,
                    'payhere_currency' => 'LKR',
                    'status_code' => $paymentStatus === 'completed' ? 2 : ($paymentStatus === 'pending' ? 0 : -1),
                    'md5sig' => md5($studentExam->id . $amount . time()),
                    'method' => rand(0, 1) ? 'VISA' : 'MASTER',
                    'status_message' => $paymentStatus === 'completed' ? 'Success' : ($paymentStatus === 'pending' ? 'Pending' : 'Failed'),
                    'commission_amount' => $commissionAmount,
                    'net_amount' => $netAmount,
                ]);

                // Update student exam with payment_id
                $studentExam->update(['payment_id' => $payment->id]);

                // Create revenue transaction if payment is completed
                if ($paymentStatus === 'completed') {
                    RevenueTransaction::create([
                        'student_exam_id' => $studentExam->id,
                        'organization_id' => $exam->organization_id,
                        'exam_id' => $exam->id,
                        'revenue' => $amount,
                        'commission' => $commissionAmount,
                        'net_revenue' => $netAmount,
                        'transaction_reference' => $payment->payment_id,
                        'status' => 'completed',
                        'transaction_date' => now(),
                    ]);
                }

                $statusIcon = $paymentStatus === 'completed' ? '✓' : ($paymentStatus === 'pending' ? '⏳' : '✗');
                $this->command->info("  {$statusIcon} Student '{$student->name}' registered for '{$exam->name}' - {$indexNumber} - Payment: {$paymentStatus}");
            }
        }

        $this->command->info('Exam registrations seeder completed!');
        $this->command->info('Total registrations created: ' . StudentExam::count());
        $this->command->info('Total payments created: ' . Payment::count());
        $this->command->info('Total revenue transactions created: ' . RevenueTransaction::count());
    }
}
