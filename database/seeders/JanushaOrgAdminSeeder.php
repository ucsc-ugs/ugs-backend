<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class JanushaOrgAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create User
        $user = User::updateOrCreate(
            ['email' => 'janusha.jayaweera@example.com'],
            [
                'name' => 'Janusha Jayaweera',
                'password' => Hash::make('password123'),
                'organization_id' => 1,
                'email_verified_at' => now(),
            ]
        );

        // 2. Assign org_admin role
        if (!$user->hasRole('org_admin')) {
            $user->assignRole('org_admin');
        }

        // 3. Create OrgAdmin record
        DB::table('org_admins')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'name' => 'Janusha Jayaweera',
                'organization_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 4. Give all org admin permissions
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
        
        $user->givePermissionTo($orgAdminPermissions);

        $this->command->info('✅ Organization Admin Created Successfully!');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('Name: Janusha Jayaweera');
        $this->command->info('Email: janusha.jayaweera@example.com');
        $this->command->info('Password: password123');
        $this->command->info('Organization ID: 1');
        $this->command->info('Role: org_admin');
        $this->command->info('Permissions: All org admin privileges granted');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }
}
