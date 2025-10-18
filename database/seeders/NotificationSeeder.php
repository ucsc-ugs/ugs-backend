<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();
        DB::table('notifications')->insert([
            [
                'title' => 'System Maintenance',
                'message' => 'System will be under maintenance on Sunday.',
                'exam_id' => null,
                'user_id' => null,
                'is_for_all' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Exam Results Published',
                'message' => 'Results for Mathematics Final Exam are now available.',
                'exam_id' => 1,
                'user_id' => null,
                'is_for_all' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Payment Successful',
                'message' => 'Your payment has been processed successfully.',
                'exam_id' => null,
                'user_id' => 2,
                'is_for_all' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'New Feature Released',
                'message' => 'We have launched a new dashboard feature.',
                'exam_id' => null,
                'user_id' => null,
                'is_for_all' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'title' => 'Physics Exam Results',
                'message' => 'Physics exam results are now available.',
                'exam_id' => 3,
                'user_id' => null,
                'is_for_all' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
