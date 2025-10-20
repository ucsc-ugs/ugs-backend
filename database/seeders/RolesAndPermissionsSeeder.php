<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
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
        $allPermissions = [
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
            'exam.location.manage',

            # Finance and Payments management permissions (CRUD + payments)
            'finance.create',
            'finance.view',
            'finance.update',
            'finance.delete',
            'payments.create',
            'payments.view',
            'payments.update',
            'payments.delete',

            # Announcements and notifications permissions
            'announcement.create',
            'announcement.view',
            'announcement.update',
            'announcement.delete',
            'announcement.publish',
        ];

        $studentPermissions = [
            'organization.view',
            'exam.view',
            'student.detail.view',
            'announcement.view',
            'payments.view',
        ];

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to roles
        $superAdminRole->givePermissionTo($allPermissions);
        $studentRole->givePermissionTo($studentPermissions);
    }
}
