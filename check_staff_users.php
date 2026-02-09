<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Staff;
use App\Models\User;

echo "=== Staff ID 12 Details ===\n";
$staff = Staff::find(12);
if ($staff) {
    print_r($staff->toArray());
} else {
    echo "Staff ID 12 not found\n";
}

echo "\n=== Users table (first 20) ===\n";
$users = User::take(20)->get(['id', 'surname', 'firstname']);
foreach ($users as $u) {
    echo "  User {$u->id}: {$u->surname} {$u->firstname}\n";
}

echo "\n=== Staff table (first 20) with user lookups ===\n";
$staffList = Staff::take(20)->get(['id', 'user_id']);
foreach ($staffList as $s) {
    $user = User::find($s->user_id);
    $userName = $user ? "{$user->surname} {$user->firstname}" : "USER NOT FOUND";
    echo "  Staff {$s->id} -> user_id {$s->user_id} -> {$userName}\n";
}
