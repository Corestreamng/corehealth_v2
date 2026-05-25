<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class ComplementaryRequisitionsPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Ensure the requisitions.view and requisitions.create permissions exist
        $permissions = [
            'requisitions.view',
            'requisitions.create',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web']
            );
            $this->command->info("Verified permission exists: {$permissionName}");
        }

        // 2. Identify all roles that are defined/checked in the Admin Sidebar
        $sidebarRoles = [
            'ACCOUNTS',
            'ADMIN',
            'BILLER',
            'DOCTOR',
            'HMO Executive',
            'HR MANAGER',
            'LAB SCIENTIST',
            'MATERNITY',
            'MORGUE',
            'NURSE',
            'PHARMACIST',
            'RADIOLOGIST',
            'RECEPTIONIST',
            'STORE',
            'super-admin',
            'SUPERADMIN',
            'SURGERY',
        ];

        // 3. Grant the permissions to each of these sidebar roles
        foreach ($sidebarRoles as $roleName) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web']
            );

            // Give permissions if they are not already assigned
            foreach ($permissions as $permissionName) {
                if (!$role->hasPermissionTo($permissionName)) {
                    $role->givePermissionTo($permissionName);
                }
            }

            $this->command->info("Successfully granted requisitions permissions to role: {$roleName}");
        }

        // Reset cached roles and permissions once more to commit changes
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Complementary Requisitions Permissions Seeder completed successfully!');
    }
}
