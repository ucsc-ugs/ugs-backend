<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $super_admin_role = Role::create(['name' => 'super_admin']);
        $org_admin_role = Role::create(['name' => 'org_admin']);
        $student_role = Role::create(['name' => 'student']);

        // may be we might need examiner roles as well

        // Need to assign permissions to these roles
        // $super_admin_role->givePermissionTo(['permission1', 'permission2']);
    }
}
