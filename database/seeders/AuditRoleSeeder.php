<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AuditRoleSeeder extends Seeder
{
    /**
     * Create the AUDITOR role with audit-specific permissions.
     *
     * @return void
     */
    public function run()
    {
        // Create permissions for Audit module
        $permissions = [
            'audit-workbench.access',
            'audit.stamps.approve',
            'audit.stamps.view',
            'audit.reconciliation',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Reset cached permissions so syncPermissions can find the new ones
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create AUDITOR role
        $role = Role::firstOrCreate(['name' => 'AUDITOR', 'guard_name' => 'web']);

        // Assign permissions to role
        $role->syncPermissions($permissions);

        $this->command->info('AUDITOR role and permissions created successfully!');
    }
}
