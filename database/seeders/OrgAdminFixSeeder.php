<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\OrgAdmin;
use Illuminate\Support\Facades\Log;

class OrgAdminFixSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('OrgAdminFixSeeder: Scanning for users with org_admin role...');

        $users = User::role('org_admin')->get();
        $created = 0;
        $skipped = 0;

        foreach ($users as $user) {
            // If an OrgAdmin record already exists, skip
            if ($user->orgAdmin) {
                $skipped++;
                continue;
            }

            // Ensure the user has an organization_id to attach
            if (empty($user->organization_id)) {
                $this->command->warn("Skipping user {$user->id} ({$user->email}) - no organization_id set.");
                $skipped++;
                continue;
            }

            OrgAdmin::create([
                'name' => $user->name,
                'user_id' => $user->id,
                'organization_id' => $user->organization_id,
            ]);

            $created++;
        }

        $this->command->info("OrgAdminFixSeeder: Created {$created} org_admin records, skipped {$skipped} entries.");
    }
}
