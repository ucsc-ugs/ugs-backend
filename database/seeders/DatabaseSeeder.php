<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seeding the organization (idempotent)
        $org = Organization::firstOrCreate(
            ['name' => 'University of Colombo School of Computing'],
            [
                'description' => 'UCSC offers 5 Undergraduate degree programmes, 6 Masters degree programmes, 2 Research degree programmes and 1 External degree programme, plus a talented team of staff to help find what is right for you. Whatever your passion, we will put you on the path to success.',
            ]
        );

        // First, seed the roles
        $this->call([
            RoleSeeder::class,
        ]);

        // Skip bulk random users to keep seed idempotent and avoid sequence drift

        // Create test student user (idempotent)
        $testStudent = User::updateOrCreate(
            ['email' => 'student@example.com'],
            [
                'name' => 'test student',
                'password' => Hash::make('password'),
            ]
        );
        if (!$testStudent->hasRole('student')) {
            $testStudent->assignRole('student');
        }
        // Ensure Student profile exists
        Student::firstOrCreate(
            ['id' => $testStudent->id],
            [
                'local' => true,
                'passport_nic' => '123456789V',
            ]
        );

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
        $orgAdminUser = User::updateOrCreate(
            ['email' => 'orgadmin@example.com'],
            [
                'name' => 'Organization Admin',
                'password' => Hash::make('password'),
                'organization_id' => $org->id,
            ]
        );
        if (!$orgAdminUser->hasRole('org_admin')) {
            $orgAdminUser->assignRole('org_admin');
        }
        
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

        // Align PostgreSQL sequences to prevent duplicate key errors when factories ran previously
        try {
            DB::statement("SELECT setval(pg_get_serial_sequence('users','id'), COALESCE((SELECT MAX(id) FROM users), 1))");
            DB::statement("SELECT setval(pg_get_serial_sequence('students','id'), COALESCE((SELECT MAX(id) FROM students), 1))");
            DB::statement("SELECT setval(pg_get_serial_sequence('organizations','id'), COALESCE((SELECT MAX(id) FROM organizations), 1))");
        } catch (\Throwable $e) {
            // Ignore if not PostgreSQL or sequences not applicable
        }
    }
}
