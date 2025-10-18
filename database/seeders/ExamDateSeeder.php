<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExamDateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $examDates = [
            [
                'exam_id' => 1,
                'date' => Carbon::now()->addDays(5)->setTime(9, 0, 0),
                'location' => 'Main Examination Hall A',
                'location_id' => null,
                'status' => 'upcoming',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'exam_id' => 1,
                'date' => Carbon::now()->addDays(6)->setTime(14, 0, 0),
                'location' => 'Main Examination Hall B',
                'location_id' => null,
                'status' => 'upcoming',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'exam_id' => 2,
                'date' => Carbon::now()->addDays(10)->setTime(9, 0, 0),
                'location' => 'Computer Lab 1',
                'location_id' => null,
                'status' => 'upcoming',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'exam_id' => 3,
                'date' => Carbon::now()->addDays(15)->setTime(10, 0, 0),
                'location' => 'Main Examination Hall C',
                'location_id' => null,
                'status' => 'upcoming',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'exam_id' => 2,
                'date' => Carbon::now()->addDays(20)->setTime(13, 0, 0),
                'location' => 'Science Building Room 301',
                'location_id' => null,
                'status' => 'upcoming',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'exam_id' => 3,
                'date' => Carbon::now()->subDays(5)->setTime(9, 0, 0),
                'location' => 'Main Examination Hall A',
                'location_id' => null,
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('exam_dates')->insert($examDates);
    }
}
