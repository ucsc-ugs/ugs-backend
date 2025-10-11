<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Student;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seeding the organization
        Organization::factory()->create([
            'name' => 'University of Colombo School of Computing',
            'description' => 'UCSC offers 5 Undergraduate degree programmes, 6 Masters degree programmes, 2 Research degree programmes and 1 External degree programme, plus a talented team of staff to help find what is right for you. Whatever your passion, we will put you on the path to success.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // First, seed the roles
        $this->call([
            RoleSeeder::class,
        ]);

        // Create regular users and assign student role
        $regularUsers = User::factory(10)->create();
        foreach ($regularUsers as $user) {
            $user->assignRole('student');
        }

        // Create test student user
        $testStudent = User::factory()->create([
            'name' => 'test student',
            'email' => 'student@example.com',
            'student_id' => Student::factory()->create([
                'local' => true,
                'passport_nic' => '123456789V', // Example NIC number
            ])->id
        ]);
        $testStudent->assignRole('student');

        // Create admin user
        $adminUser = User::factory()->create([
            'name' => 'Super Admin User',
            'email' => 'admin@example.com',
            'student_id' => null,
            'user_type' => 'super-admin'
        ]);
        $adminUser->assignRole('super_admin');

        // Create org admin user
        $orgAdminUser = User::factory()->create([
            'name' => 'Organization Admin',
            'email' => 'orgadmin@example.com',
            'student_id' => null,
            'user_type' => 'org-admin',
            'organization_id' => Organization::where('name', 'University of Colombo School of Computing')->first()->id
        ]);
        $orgAdminUser->assignRole('org_admin');

        $this->call([
            OrgExamSeeder::class,
        ]);
    }
}
