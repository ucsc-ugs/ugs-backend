<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OrganizationAdminsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // All the permissions of an organization admin
        $orgAdminPermissions = [
            # Organizations management permissions (CRUD + admins)
            'organization.update',
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
            'exam.location.manage',

            # Finance and Payments management permissions (CRUD + payments)
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

        // Create an organization admin for UCSC
        $ucscOrganization = Organization::where('name', 'University of Colombo School of Computing')->first();
        
        $ucscAdminUser = User::create([
            'name' => 'UCSC Organization Admin',
            'email' => 'orgadmin@ucsc.lk',
            'password' => Hash::make('password'),
            'user_type' => 'org-admin',
            'organization_id' => $ucscOrganization->id,
            'email_verified_at' => now(),
        ]);

        $ucscAdminUser->assignRole('org_admin');
        $ucscAdminUser->givePermissionTo($orgAdminPermissions);

        // Add to org_admins table
        \App\Models\OrgAdmin::create([
            'organization_id' => $ucscOrganization->id,
            'user_id' => $ucscAdminUser->id,
            'name' => $ucscAdminUser->name,
        ]);
    }
}
