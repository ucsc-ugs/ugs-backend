<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\OrgAdmin;

class FixOrgAdminUserTypes extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'users:fix-org-admin-types';

    /**
     * The console command description.
     */
    protected $description = 'Fix user_type for existing organizational admins';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Finding organizational admins with incorrect user_type...');

        // Find all users who have the org_admin role but wrong user_type
        $orgAdminUsers = User::whereHas('roles', function ($query) {
            $query->where('name', 'org_admin');
        })
            ->where(function ($query) {
                $query->where('user_type', '!=', 'org-admin')
                    ->orWhereNull('user_type');
            })
            ->get();

        if ($orgAdminUsers->isEmpty()) {
            $this->info('No organizational admins found with incorrect user_type.');
            return 0;
        }

        $this->info("Found {$orgAdminUsers->count()} organizational admin(s) with incorrect user_type:");

        foreach ($orgAdminUsers as $user) {
            $currentType = $user->user_type ?: 'NULL';
            $this->line("- User ID {$user->id}: {$user->name} ({$user->email}) - Current type: {$currentType}");
        }

        if ($this->confirm('Do you want to fix the user_type for these users?')) {
            foreach ($orgAdminUsers as $user) {
                $oldType = $user->user_type ?: 'NULL';
                $user->update(['user_type' => 'org-admin']);
                $this->line("✓ Fixed User ID {$user->id}: {$user->name} - Changed from '{$oldType}' to 'org-admin'");
            }

            $this->info("Successfully updated {$orgAdminUsers->count()} user(s).");
        } else {
            $this->info('Operation cancelled.');
        }

        return 0;
    }
}
