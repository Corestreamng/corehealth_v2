<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class MaternityRoleSeeder extends Seeder
{
    /**
     * Create the MATERNITY role with maternity-specific permissions.
     *
     * @return void
     */
    public function run()
    {
        // Create permissions for Maternity module
        $permissions = [
            'maternity-workbench.access',
            'maternity.enroll',
            'maternity.anc-visit.create',
            'maternity.delivery.record',
            'maternity.baby.register',
            'maternity.postnatal.create',
            'maternity.clinical-orders',
            'maternity.reports',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Reset cached permissions so syncPermissions can find the new ones
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create MATERNITY role
        $role = Role::firstOrCreate(['name' => 'MATERNITY', 'guard_name' => 'web']);

        // Assign permissions to role
        $role->syncPermissions($permissions);

        $this->command->info('MATERNITY role and permissions created successfully!');
    }
}
