<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\OrgAdmin;

require __DIR__ . '/..\\vendor\\autoload.php';

$app = require_once __DIR__ . '/..\\bootstrap\\app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$org = Organization::first();
if (!$org) {
    echo "No organization found\n";
    exit(1);
}

$user = User::where('email', 'orgadmin@example.com')->first();
if (!$user) {
    echo "User orgadmin@example.com not found\n";
    exit(1);
}

if (empty($user->organization_id)) {
    $user->organization_id = $org->id;
    $user->save();
    echo "Updated user organization_id to {$org->id}\n";
} else {
    echo "User already has organization_id {$user->organization_id}\n";
}

if (!OrgAdmin::where('user_id', $user->id)->exists()) {
    OrgAdmin::create([
        'name' => $user->name,
        'user_id' => $user->id,
        'organization_id' => $org->id,
    ]);
    echo "Created OrgAdmin record for user {$user->email}\n";
} else {
    echo "OrgAdmin record already exists for user {$user->email}\n";
}

echo "Done.\n";
