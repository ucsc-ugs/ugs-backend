<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use App\Models\Organization;
use App\Models\User;
use App\Models\Student;
use App\Models\Exam;
use App\Models\StudentExam;
use App\Models\Payment;

class StudentExamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Align sequence for Postgres if out of sync
        try {
            $seq = DB::select("SELECT pg_get_serial_sequence('student_exams','id') as seq");
            if (!empty($seq) && !empty($seq[0]->seq)) {
                $sequenceName = $seq[0]->seq;
                DB::statement("SELECT setval('{$sequenceName}', COALESCE((SELECT MAX(id) FROM student_exams), 0))");
            }
            $seq2 = DB::select("SELECT pg_get_serial_sequence('payments','id') as seq");
            if (!empty($seq2) && !empty($seq2[0]->seq)) {
                $sequenceName2 = $seq2[0]->seq;
                DB::statement("SELECT setval('{$sequenceName2}', COALESCE((SELECT MAX(id) FROM payments), 0))");
            }
        } catch (\Throwable $e) {
            // ignore if not Postgres or sequence missing
        }
        // Ensure roles exist
        Role::firstOrCreate(['name' => 'student']);

        // Ensure base organization exists
        $org = Organization::firstOrCreate(
            ['name' => 'University of Colombo School of Computing'],
            ['description' => 'UCSC - test organization']
        );

        // Ensure exams exist (reuse codes from OrgExamSeeder)
        // Build attributes conditionally to avoid inserting columns that may not exist locally
        $exam1Attrs = [
            'name' => 'Sample Exam 1',
            'description' => 'This is a sample exam for testing purposes.',
            'organization_id' => $org->id,
        ];
        if (Schema::hasColumn('exams', 'code_name')) { $exam1Attrs['code_name'] = 'ET01'; }
        if (Schema::hasColumn('exams', 'price')) { $exam1Attrs['price'] = 1000; }
        $exam1 = Exam::firstOrCreate(['name' => 'Sample Exam 1'], $exam1Attrs);

        $exam2Attrs = [
            'name' => 'General Computing Aptitude Test',
            'description' => 'GCAT test exam',
            'organization_id' => $org->id,
        ];
        if (Schema::hasColumn('exams', 'code_name')) { $exam2Attrs['code_name'] = 'GCAT'; }
        if (Schema::hasColumn('exams', 'price')) { $exam2Attrs['price'] = 2000; }
        $exam2 = Exam::firstOrCreate(['name' => 'General Computing Aptitude Test'], $exam2Attrs);

        // Create or update students
        $amal = User::updateOrCreate(
            ['email' => 'student@example.com'],
            [
                'name' => 'Amal Perera',
                'password' => Hash::make('password123'),
                'organization_id' => $org->id,
            ]
        );
        if (! $amal->hasRole('student')) $amal->assignRole('student');
        Student::firstOrCreate(
            ['id' => $amal->id],
            ['local' => true, 'passport_nic' => '991234567V']
        );

        $john = User::updateOrCreate(
            ['email' => 'international@example.com'],
            [
                'name' => 'John Smith',
                'password' => Hash::make('password123'),
                'organization_id' => $org->id,
            ]
        );
        if (! $john->hasRole('student')) $john->assignRole('student');
        Student::firstOrCreate(
            ['id' => $john->id],
            ['local' => false, 'passport_nic' => 'P9876543']
        );

        // Register the students for exams of this organization (idempotent)
        $this->registerAndPayIfMissing($amal->id, $exam1->id, 1000);
        $this->registerAndPayIfMissing($john->id, $exam2->id, 2000, ['attended' => true, 'status' => 'completed']);

        // Create a student from another organization who registers for this org's exam (to test cross-org visibility)
        $otherOrg = Organization::firstOrCreate(
            ['name' => 'Other University'],
            ['description' => 'Another test organization']
        );
        $eve = User::updateOrCreate(
            ['email' => 'eve.foreign@example.com'],
            [
                'name' => 'Eve Foreign',
                'password' => Hash::make('password123'),
                'organization_id' => $otherOrg->id,
            ]
        );
        if (! $eve->hasRole('student')) $eve->assignRole('student');
        $eveNic = 'P' . str_pad((string)$eve->id, 7, '0', STR_PAD_LEFT) . 'X';
        Student::firstOrCreate(
            ['id' => $eve->id],
            ['local' => false, 'passport_nic' => $eveNic]
        );
        $this->registerAndPayIfMissing($eve->id, $exam1->id, (float)($exam1Attrs['price'] ?? 1000), [
            'status' => 'registered',
            'attended' => false,
        ]);

        $this->command?->info('StudentExamSeeder completed: students and registrations ensured.');
    }

    private function registerAndPayIfMissing(int $studentId, int $examId, float $amount, array $extra = []): void
    {
        $studentExam = StudentExam::where('student_id', $studentId)->where('exam_id', $examId)->first();
        if (!$studentExam) {
            $base = [
                'student_id' => $studentId,
                'exam_id' => $examId,
            ];
            // index_number may be unique; generate if column exists
            $index = 'IDX-' . $studentId . '-' . $examId;
            if (Schema::hasColumn('student_exams', 'index_number')) {
                $base['index_number'] = $index;
            }
            $studentExam = StudentExam::create(array_merge($base, [
                'status' => $extra['status'] ?? 'registered',
                'attended' => $extra['attended'] ?? false,
            ]));
        }

        // Ensure a payment with status_code=2 exists
        $payment = Payment::where('student_exam_id', $studentExam->id)->where('status_code', 2)->first();
        if (!$payment) {
            Payment::create([
                'student_exam_id' => $studentExam->id,
                'payment_id' => 'TEST-' . $studentExam->id,
                'payhere_amount' => $amount,
                'payhere_currency' => 'LKR',
                'status_code' => 2,
                'method' => 'VISA',
                'status_message' => 'Paid (seeded)',
            ]);
        }
    }
}
