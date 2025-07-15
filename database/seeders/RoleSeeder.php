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
            'manage-organizations',
            'manage-org-admins',
            'manage-students',
            'view-dashboard',
            'manage-exams',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to roles
        $superAdminRole->givePermissionTo($permissions);
        $orgAdminRole->givePermissionTo(['manage-students', 'view-dashboard', 'manage-exams']);
        $studentRole->givePermissionTo(['view-dashboard']);

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
