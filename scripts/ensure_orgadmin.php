<?php
// Script: scripts/ensure_orgadmin.php
// Usage: php scripts/ensure_orgadmin.php
// Ensures the user with email orgadmin@example.com has an OrgAdmin record and organization_id set.

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
// Boot the application
$kernel->bootstrap();

use App\Models\User;
use App\Models\Organization;
use App\Models\OrgAdmin;

$email = 'orgadmin@example.com';

$user = User::where('email', $email)->first();
if (! $user) {
    echo "ERROR: user with email {$email} not found\n";
    exit(2);
}

$org = Organization::first();
if (! $org) {
    echo "ERROR: no Organization records found. Create an organization first (via seeder or manually) and rerun this script.\n";
    exit(3);
}

// Update user's organization_id if missing
if ($user->organization_id !== $org->id) {
    $user->organization_id = $org->id;
    $user->save();
    echo "Updated user {$user->email} organization_id to {$org->id}\n";
} else {
    echo "User already has organization_id {$org->id}\n";
}

$orgAdmin = OrgAdmin::firstOrCreate(
    ['user_id' => $user->id],
    ['organization_id' => $org->id, 'name' => $user->name ?? $user->email]
);

if ($orgAdmin->wasRecentlyCreated) {
    echo "Created OrgAdmin id={$orgAdmin->id} for user_id={$user->id}\n";
} else {
    echo "OrgAdmin already exists id={$orgAdmin->id} for user_id={$user->id}\n";
}

echo "DONE\n";
