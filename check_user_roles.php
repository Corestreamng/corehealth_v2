<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "=== USER ROLES CHECK ===\n\n";

$user = User::find(1);

if (!$user) {
    echo "User ID 1 not found!\n";
    exit;
}

echo "User Details:\n";
echo "ID: {$user->id}\n";
echo "Name: {$user->name}\n";
echo "Email: {$user->email}\n\n";

echo "Roles:\n";
if (method_exists($user, 'getRoleNames')) {
    $roles = $user->getRoleNames();
    if ($roles->count() > 0) {
        foreach ($roles as $role) {
            echo "  - {$role}\n";
        }
    } else {
        echo "  No roles assigned!\n";
    }
} else {
    echo "  getRoleNames() method not available\n";
}

echo "\nRole Checks:\n";
if (method_exists($user, 'hasRole')) {
    echo "hasRole('SUPERADMIN'): " . ($user->hasRole('SUPERADMIN') ? 'YES ✅' : 'NO ❌') . "\n";
    echo "hasRole('ADMIN'): " . ($user->hasRole('ADMIN') ? 'YES ✅' : 'NO ❌') . "\n";
    echo "hasRole('ACCOUNTS'): " . ($user->hasRole('ACCOUNTS') ? 'YES ✅' : 'NO ❌') . "\n";
    echo "hasRole(['SUPERADMIN', 'ADMIN']): " . ($user->hasRole(['SUPERADMIN', 'ADMIN']) ? 'YES ✅' : 'NO ❌') . "\n";
} else {
    echo "hasRole() method not available\n";
}

echo "\nDirect roles table query:\n";
$rolesQuery = \DB::table('model_has_roles')
    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
    ->where('model_has_roles.model_id', 1)
    ->where('model_has_roles.model_type', 'App\\Models\\User')
    ->select('roles.id', 'roles.name')
    ->get();

if ($rolesQuery->count() > 0) {
    foreach ($rolesQuery as $role) {
        echo "  ID: {$role->id}, Name: {$role->name}\n";
    }
} else {
    echo "  No roles in model_has_roles table\n";
}

echo "\nAll available roles in system:\n";
$allRoles = \DB::table('roles')->get();
foreach ($allRoles as $role) {
    echo "  ID: {$role->id}, Name: {$role->name}\n";
}

echo "\n=== END ===\n";
