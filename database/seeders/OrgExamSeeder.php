<?php

namespace Database\Seeders;

use App\Models\Complaint;
use App\Models\Exam;
use App\Models\Organization;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrgExamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Organization::factory()->create([
            'name' => 'University of Colombo School of Computing',
            'description' => 'UCSC offers 5 Undergraduate degree programmes, 6 Masters degree programmes, 2 Research degree programmes and 1 External degree programme, plus a talented team of staff to help find what is right for you. Whatever your passion, we will put you on the path to success.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Exam::factory()->create([
            'name' => 'Sample Exam 1',
            'description' => 'This is a sample exam for testing purposes.',
            'organization_id' => Organization::where('name', 'University of Colombo School of Computing')->first()->id,
            'price' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Exam::factory()->create([
            'name' => 'Sample Exam 2',
            'description' => 'This is a sample exam for testing purposes.',
            'organization_id' => Organization::where('name', 'University of Colombo School of Computing')->first()->id,
            'price' => 1500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
