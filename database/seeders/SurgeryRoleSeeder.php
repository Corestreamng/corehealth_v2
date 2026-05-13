<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class SurgeryRoleSeeder extends Seeder
{
    /**
     * Create the SURGERY role with surgery-specific permissions.
     *
     * @return void
     */
    public function run()
    {
        // Create permissions for Surgery module
        $permissions = [
            'surgery-workbench.access',
            'surgery.consent.record',
            'surgery.orders.manage',
            'surgery.team.manage',
            'surgery.notes',
            'surgery.reports',
            'surgery.attachments',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Reset cached permissions so syncPermissions can find the new ones
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create SURGERY role
        $role = Role::firstOrCreate(['name' => 'SURGERY', 'guard_name' => 'web']);

        // Assign permissions to role
        $role->syncPermissions($permissions);

        $this->command->info('SURGERY role and permissions created successfully!');
    }
}
