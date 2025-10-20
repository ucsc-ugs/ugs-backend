<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SuperAdminsSeeder::class,
            OrganizationsSeeder::class,
            OrganizationAdminsSeeder::class,
            ExamsSeeder::class,
            StudentsSeeder::class,
        ]);
    }
}
