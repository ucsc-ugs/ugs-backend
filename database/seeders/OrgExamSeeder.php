<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class OrgExamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orgId = Organization::where('name', 'University of Colombo School of Computing')->first()->id;
        $codeKey = Schema::hasColumn('exams', 'code_name') ? 'code_name' : (Schema::hasColumn('exams', 'code') ? 'code' : null);

        $base1 = [
            'name' => 'Sample Exam 1',
            'description' => 'This is a sample exam for testing purposes.',
            'organization_id' => $orgId,
            'price' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    if ($codeKey) { $base1[$codeKey] = 'ET01'; }
    Exam::firstOrCreate(['name' => $base1['name']], $base1);

        $base2 = [
            'name' => 'Sample Exam 2',
            'description' => 'This is a sample exam for testing purposes.',
            'organization_id' => $orgId,
            'price' => 1500,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    if ($codeKey) { $base2[$codeKey] = 'ET02'; }
    Exam::firstOrCreate(['name' => $base2['name']], $base2);

        $base3 = [
            'name' => 'General Computing Aptitude Test',
            'description' => 'The General Computing Aptitude Test (GCAT) is a prerequisite to apply for Master of Information Technology (MIT), Master of Information Security (MIS), Master of Business Analytics (MB.Analytics), and Master of Cybersecurity (MC) degree programs at University of Colombo School of Computing. Once you sit for a particular test, the validity period of the test results is for 2 years. However, depending on the competitiveness of the test score, you will be notified if you are selected to the applied masters’ degree program or not.',
            'organization_id' => $orgId,
            'price' => 2000,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    if ($codeKey) { $base3[$codeKey] = 'GCAT'; }
    Exam::firstOrCreate(['name' => $base3['name']], $base3);
    }
}
