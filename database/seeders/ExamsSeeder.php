<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamDate;
use App\Models\ExamDateLocation;
use App\Models\Location;
use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ExamsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get UCSC organization
        $org = Organization::where('name', 'University of Colombo School of Computing')->first();
        
        if (!$org) {
            $this->command->warn('Organization not found. Please run OrganizationsSeeder first.');
            return;
        }

        $orgId = $org->id;

        // Check if code_name or code column exists
        $codeKey = Schema::hasColumn('exams', 'code_name') ? 'code_name' : (Schema::hasColumn('exams', 'code') ? 'code' : null);

        // Create Locations for UCSC
        $locations = [
            [
                'organization_id' => $orgId,
                'location_name' => 'Main Auditorium',
                'capacity' => 200,
            ],
            [
                'organization_id' => $orgId,
                'location_name' => 'Computer Lab A',
                'capacity' => 50,
            ],
            [
                'organization_id' => $orgId,
                'location_name' => 'Computer Lab B',
                'capacity' => 50,
            ],
            [
                'organization_id' => $orgId,
                'location_name' => 'Lecture Hall 1',
                'capacity' => 150,
            ],
            [
                'organization_id' => $orgId,
                'location_name' => 'Lecture Hall 2',
                'capacity' => 100,
            ],
        ];

        $createdLocations = [];
        foreach ($locations as $locationData) {
            $location = Location::firstOrCreate(
                [
                    'organization_id' => $locationData['organization_id'],
                    'location_name' => $locationData['location_name']
                ],
                ['capacity' => $locationData['capacity']]
            );
            $createdLocations[$locationData['location_name']] = $location;
        }

        $this->command->info('Locations created successfully.');

        // Create Exams
        $examsData = [
            [
                'name' => 'General Computing Competence Test',
                'description' => "The General Computing Competence Test (GCCT) is a prerequisite to apply for Master of Computer Science(MCS)*, Master of Science in Computer Science (MSc in CS)* postgraduate programs at University of Colombo School of Computing. Once you sit for a particular test, the validity period of the test results is for 2 years. However, depending on the competitiveness of the test score, you will be notified if you are selected to the applied masters degree program or not.\n\nGCCT is an assessment tool designed to evaluate the general computing skills of candidates and is offered by the University of Colombo School of Computing. By testing the knowledge, skills, and abilities of candidates in the computing field, the GCCT aims to standardize the assessment process and provide a reliable indicator of general competence. The test is scored on a ten-band scale, which provides a clear indication of the candidate's level of expertise in the computing domain. As such, the GCCT is an invaluable tool for anyone seeking to demonstrate their general competence in computing, whether for academic, professional, or personal reasons.",
                'price' => 5000,
                'commission_rate' => 5.0,
                'registration_deadline' => now()->addDays(30),
                'dates' => [
                    [
                        'date' => now()->addDays(45)->setTime(9, 0, 0),
                        'status' => 'active',
                        'locations' => [
                            ['name' => 'Computer Lab A', 'priority' => 1],
                            ['name' => 'Computer Lab B', 'priority' => 2],
                        ],
                    ],
                    [
                        'date' => now()->addDays(60)->setTime(14, 0, 0),
                        'status' => 'active',
                        'locations' => [
                            ['name' => 'Lecture Hall 1', 'priority' => 1],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'General Computing Aptitude Test',
                'description' => 'The General Computing Aptitude Test (GCAT) is a prerequisite to apply for Master of Information Technology (MIT), Master of Information Security (MIS), Master of Business Analytics (MB.Analytics), and Master of Cybersecurity (MC) degree programs at University of Colombo School of Computing. Once you sit for a particular test, the validity period of the test results is for 2 years. However, depending on the competitiveness of the test score, you will be notified if you are selected to the applied masters\' degree program or not.',
                'price' => 5500,
                'commission_rate' => 5.0,
                'registration_deadline' => now()->addDays(40),
                'dates' => [
                    [
                        'date' => now()->addDays(55)->setTime(9, 0, 0),
                        'status' => 'active',
                        'locations' => [
                            ['name' => 'Main Auditorium', 'priority' => 1],
                            ['name' => 'Lecture Hall 1', 'priority' => 2],
                        ],
                    ],
                    [
                        'date' => now()->addDays(70)->setTime(9, 0, 0),
                        'status' => 'active',
                        'locations' => [
                            ['name' => 'Main Auditorium', 'priority' => 1],
                            ['name' => 'Lecture Hall 1', 'priority' => 2],
                            ['name' => 'Lecture Hall 2', 'priority' => 3],
                        ],
                    ],
                    [
                        'date' => now()->addDays(85)->setTime(14, 0, 0),
                        'status' => 'active',
                        'locations' => [
                            ['name' => 'Main Auditorium', 'priority' => 1],
                        ],
                    ],
                ],
            ],
        ];

        $examCodes = ['GCCT', 'GCAT'];

        foreach ($examsData as $index => $examData) {
            // Prepare exam base data
            $examBase = [
                'name' => $examData['name'],
                'description' => $examData['description'],
                'organization_id' => $orgId,
                'price' => $examData['price'],
            ];

            // Add code if column exists
            if ($codeKey) {
                $examBase[$codeKey] = $examCodes[$index];
            }

            // Add commission_rate if column exists
            if (Schema::hasColumn('exams', 'commission_rate')) {
                $examBase['commission_rate'] = $examData['commission_rate'];
            }

            // Add registration_deadline if column exists
            if (Schema::hasColumn('exams', 'registration_deadline')) {
                $examBase['registration_deadline'] = $examData['registration_deadline'];
            }

            // Create or update exam
            $exam = Exam::updateOrCreate(
                ['name' => $examData['name']],
                $examBase
            );

            $this->command->info("Exam '{$exam->name}' created/updated.");

            // Create exam dates with locations
            foreach ($examData['dates'] as $dateData) {
                $examDateBase = [
                    'exam_id' => $exam->id,
                    'date' => $dateData['date'],
                ];

                // Add status if column exists
                if (Schema::hasColumn('exam_dates', 'status')) {
                    $examDateBase['status'] = $dateData['status'];
                }

                $examDate = ExamDate::create($examDateBase);

                $this->command->info("  - Exam date created for {$dateData['date']->format('Y-m-d H:i')}");

                // Attach locations with priorities
                foreach ($dateData['locations'] as $locationData) {
                    $location = $createdLocations[$locationData['name']];
                    
                    ExamDateLocation::create([
                        'exam_date_id' => $examDate->id,
                        'location_id' => $location->id,
                        'priority' => $locationData['priority'],
                        'current_registrations' => 0,
                    ]);

                    $this->command->info("    * Location '{$locationData['name']}' assigned with priority {$locationData['priority']}");
                }
            }
        }

        $this->command->info('Exams seeder completed!');
    }
}
