<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        $orgAdminRole = Role::firstOrCreate(['name' => 'org_admin']);
        $studentRole = Role::firstOrCreate(['name' => 'student']);

        // Create permissions
        $permissions = [
            # Organizations management permissions (CRUD + admins)
            'organization.create',
            'organization.view',
            'organization.update',
            'organization.delete',
            'organization.admins.create',
            'organization.admins.view',
            'organization.admins.update',
            'organization.admins.delete',

            # Student management permissions (CRUD + details & results)
            'student.create',
            'student.view',
            'student.update',
            'student.delete',
            'student.detail.view',
            'student.results.publish',
            'student.attendance.view',

            # Exam management permissions (CRUD + deadlines, schedule, announcements)
            'exam.create',
            'exam.view',
            'exam.update',
            'exam.delete',
            'exam.registration.deadline.set',
            'exam.registration.deadline.extend',
            'exam.schedule.set',
            'exam.schedule.update',
            'exam.announcement.publish',
            'exam.notification.send',
            'exam.change.notify',

            # Finance management permissions (CRUD + payments)
            'finance.create',
            'finance.view',
            'finance.update',
            'finance.delete',
            'payments.create',
            'payments.view',
            'payments.update',
            'payments.delete',
            'payments.refund',

            # Announcements and notifications permissions
            'announcement.create',
            'announcement.view',
            'announcement.update',
            'announcement.delete',
            'announcement.publish',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to roles
        // Super admin gets everything
        $superAdminRole->givePermissionTo($permissions);

        // Org admin: allow viewing and managing students, exams and payments within their org
        $orgAdminRole->givePermissionTo([
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

            'payments.view',
            'payments.create',
            'payments.update',

            'announcement.create',
            'announcement.view',
            'announcement.update',
            'announcement.publish',
        ]);

        // Student role: limited view-only permissions
        $studentRole->givePermissionTo([
            'exam.view',
            'student.detail.view',
            'announcement.view',
            'payments.view',
        ]);

        // Create default super admin if none exists
        $superAdmin = User::role('super_admin')->first();
        if (!$superAdmin) {
            $superAdmin = User::create([
                'name' => 'Super Admin',
                'email' => 'admin@ugs.com',
                'password' => Hash::make('admin123'),
            ]);

            $superAdmin->assignRole('super_admin');

            $this->command->info('Default super admin created: admin@ugs.com / admin123');
        }
    }
}
