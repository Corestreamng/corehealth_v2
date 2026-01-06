<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class HmoExecutiveRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create permissions for HMO Executive
        $permissions = [
            'validate-hmo-requests',
            'view-hmo-workbench',
            'view-claims-reports',
            'approve-hmo-claims',
            'reject-hmo-claims',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create HMO Executive role
        $role = Role::firstOrCreate(['name' => 'HMO Executive']);

        // Assign permissions to role
        $role->syncPermissions($permissions);

        $this->command->info('HMO Executive role and permissions created successfully!');
    }
}
