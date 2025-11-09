<?php

use App\Enums\UserType;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$userName = $argv[1] ?? null;

$query = User::query()
    ->select(['id', 'user_name', 'name', 'type', 'balance', 'agent_id'])
    ->orderBy('type')
    ->orderBy('user_name');

if ($userName) {
    $query->where('user_name', $userName);
}

$users = $query->get();

if ($users->isEmpty()) {
    fwrite(STDOUT, $userName
        ? "No user found with user_name '{$userName}'\n"
        : "No users found.\n"
    );

    exit(0);
}

fwrite(STDOUT, str_pad('ID', 6)
    .str_pad('Username', 18)
    .str_pad('Name', 22)
    .str_pad('Type', 16)
    .str_pad('Balance', 20)
    ."Parent\n");
fwrite(STDOUT, str_repeat('-', 90)."\n");

foreach ($users as $user) {
    $type = UserType::tryFrom((int) $user->type)?->name ?? $user->type;
    fwrite(STDOUT, str_pad((string) $user->id, 6)
        .str_pad($user->user_name, 18)
        .str_pad($user->name ?? '-', 22)
        .str_pad($type, 16)
        .str_pad(number_format((int) $user->balance, 2, '.', ','), 20)
        .($user->agent_id ?? '-')
        ."\n");
}

exit(0);

