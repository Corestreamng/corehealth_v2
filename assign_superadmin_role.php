<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== ASSIGN SUPERADMIN ROLE ===\n\n";

$user = User::find(1);

if (!$user) {
    echo "User ID 1 not found!\n";
    exit;
}

echo "User: {$user->name} ({$user->email})\n\n";

// Check if SUPERADMIN role exists
$superadminRole = DB::table('roles')->where('name', 'SUPERADMIN')->first();

if (!$superadminRole) {
    echo "❌ SUPERADMIN role not found in database!\n";
    echo "Available roles with 'SUPER' or 'ADMIN':\n";
    $roles = DB::table('roles')->where('name', 'like', '%SUPER%')->orWhere('name', 'like', '%ADMIN%')->get();
    foreach ($roles as $role) {
        echo "  - ID: {$role->id}, Name: {$role->name}\n";
    }
    exit;
}

echo "Found SUPERADMIN role (ID: {$superadminRole->id})\n\n";

// Check if user already has this role
$hasRole = DB::table('model_has_roles')
    ->where('role_id', $superadminRole->id)
    ->where('model_id', $user->id)
    ->where('model_type', 'App\\Models\\User')
    ->exists();

if ($hasRole) {
    echo "✅ User already has SUPERADMIN role\n";
} else {
    echo "Assigning SUPERADMIN role to user...\n";

    DB::table('model_has_roles')->insert([
        'role_id' => $superadminRole->id,
        'model_type' => 'App\\Models\\User',
        'model_id' => $user->id
    ]);

    echo "✅ SUPERADMIN role assigned successfully!\n";
}

// Verify
echo "\nVerifying roles for user...\n";
$userRoles = DB::table('model_has_roles')
    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
    ->where('model_has_roles.model_id', $user->id)
    ->where('model_has_roles.model_type', 'App\\Models\\User')
    ->select('roles.name')
    ->pluck('name')
    ->toArray();

echo "Current roles: " . implode(', ', $userRoles) . "\n";

// Test hasRole method
$user->refresh();
if (method_exists($user, 'hasRole')) {
    echo "\nRole checks after assignment:\n";
    echo "hasRole('SUPERADMIN'): " . ($user->hasRole('SUPERADMIN') ? 'YES ✅' : 'NO ❌') . "\n";
    echo "hasRole('ADMIN'): " . ($user->hasRole('ADMIN') ? 'YES ✅' : 'NO ❌') . "\n";
    echo "hasRole(['SUPERADMIN', 'ADMIN']): " . ($user->hasRole(['SUPERADMIN', 'ADMIN']) ? 'YES ✅' : 'NO ❌') . "\n";
}

echo "\n=== DONE ===\n";
echo "You should now be able to approve budgets!\n";
