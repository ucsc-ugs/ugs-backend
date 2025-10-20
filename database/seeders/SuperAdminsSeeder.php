<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create super admin user
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@ugs.lk',
            'password' => Hash::make('password'),
            'user_type' => 'super-admin',
            'email_verified_at' => now(),
        ]);

        $superAdmin->assignRole('super_admin');
    }
}
