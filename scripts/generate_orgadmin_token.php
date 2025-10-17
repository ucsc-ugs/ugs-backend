<?php

require __DIR__ . '/..\\vendor\\autoload.php';

$app = require_once __DIR__ . '/..\\bootstrap\\app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('email','orgadmin@example.com')->first();
if (!$user) {
    echo "User not found\n";
    exit(1);
}

$token = $user->createToken('cli-token')->plainTextToken;
echo $token . "\n";
