<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Organization;
use App\Models\OrgAdmin;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class SpecificOrgAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $org = Organization::first();
        if (!$org) {
            $this->command->error('No organization found. Please seed an organization first.');
            return;
        }

        $email = 'sula2001karunarathne@gmail.com';
        $name = 'sulakshana';
        $password = 'sula2001';

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'organization_id' => $org->id,
            ]
        );

        // Ensure role exists and assign
        Role::firstOrCreate(['name' => 'org_admin']);
        if (!$user->hasRole('org_admin')) {
            $user->assignRole('org_admin');
        }

        // Ensure OrgAdmin record exists
        OrgAdmin::firstOrCreate([
            'user_id' => $user->id,
        ], [
            'name' => $user->name,
            'organization_id' => $org->id,
        ]);

        $this->command->info("Org admin created/updated: {$email} (password: {$password})");
    }
}
