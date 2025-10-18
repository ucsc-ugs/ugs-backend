<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ExamDate;
use Carbon\Carbon;

class UpdateExpiredExamStatuses extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'exams:update-expired-statuses';

    /**
     * The console command description.
     */
    protected $description = 'Automatically update exam date statuses from upcoming to completed when the exam date has passed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for expired exam dates...');

        $today = Carbon::now()->startOfDay();

        // Get upcoming exams that have passed their date
        $expiredExams = ExamDate::where('status', 'upcoming')
            ->where('date', '<', $today)
            ->with('exam')
            ->get();

        if ($expiredExams->isEmpty()) {
            $this->info('No expired exam dates found.');
            return 0;
        }

        $this->info("Found {$expiredExams->count()} expired exam dates. Updating statuses...");

        foreach ($expiredExams as $examDate) {
            $this->line("- {$examDate->exam->name} ({$examDate->exam->code_name}) on {$examDate->date->format('Y-m-d')} -> Completed");
        }

        // Update all expired exams to completed
        $updated = ExamDate::where('status', 'upcoming')
            ->where('date', '<', $today)
            ->update(['status' => 'completed']);

        $this->info("Successfully updated {$updated} exam date(s) to completed status.");

        return 0;
    }
}
