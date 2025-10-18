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

        // Give all permissions to super admin
        $allPermissions = \Spatie\Permission\Models\Permission::all();
        $adminUser->givePermissionTo($allPermissions);

        // Create org admin user
        $orgAdminUser = User::factory()->create([
            'name' => 'Organization Admin',
            'email' => 'orgadmin@example.com',
            'student_id' => null,
            'user_type' => 'org-admin',
            'organization_id' => Organization::where('name', 'University of Colombo School of Computing')->first()->id
        ]);
        $orgAdminUser->assignRole('org_admin');

        // Give org admin specific permissions
        $orgAdminPermissions = [
            'organization.view',
            'organization.update',
            'organization.admins.create',
            'organization.admins.view',
            'organization.admins.update',
            'organization.admins.delete',
            'student.create',
            'student.view',
            'student.update',
            'student.delete',
            'student.detail.view',
            'exam.create',
            'exam.view',
            'exam.update',
            'exam.schedule.set',
            'exam.schedule.update',
            'exam.registration.deadline.set',
            'exam.registration.deadline.extend',
            'exam.location.manage',
            'payments.view',
            'payments.create',
            'payments.update',
            'announcement.create',
            'announcement.view',
            'announcement.update',
            'announcement.publish',
        ];
        $orgAdminUser->givePermissionTo($orgAdminPermissions);

        $this->call([
            OrgExamSeeder::class,
        ]);
    }
}
