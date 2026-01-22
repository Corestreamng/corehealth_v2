<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Inventory Permissions Seeder
 *
 * Creates all necessary permissions for the new inventory management system:
 * - Purchase Orders
 * - Store Requisitions
 * - Store Workbench
 * - Expenses
 *
 * Run with: php artisan db:seed --class=InventoryPermissionsSeeder
 */
class InventoryPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // ===== PURCHASE ORDERS =====
            'purchase-orders.view' => 'View purchase orders',
            'purchase-orders.create' => 'Create purchase orders',
            'purchase-orders.edit' => 'Edit purchase orders',
            'purchase-orders.delete' => 'Delete purchase orders',
            'purchase-orders.submit' => 'Submit purchase orders for approval',
            'purchase-orders.approve' => 'Approve purchase orders',
            'purchase-orders.receive' => 'Receive items from purchase orders',
            'purchase-orders.cancel' => 'Cancel purchase orders',

            // ===== STORE REQUISITIONS =====
            'requisitions.view' => 'View store requisitions',
            'requisitions.create' => 'Create store requisitions',
            'requisitions.approve' => 'Approve store requisitions',
            'requisitions.reject' => 'Reject store requisitions',
            'requisitions.fulfill' => 'Fulfill store requisitions',
            'requisitions.cancel' => 'Cancel store requisitions',

            // ===== STORE WORKBENCH =====
            'store-workbench.view' => 'View store workbench',
            'store-workbench.manage-batches' => 'Manage stock batches',
            'store-workbench.adjust-stock' => 'Adjust stock quantities',
            'store-workbench.write-off' => 'Write off expired/damaged stock',
            'store-workbench.manual-batch' => 'Create manual stock batches',
            'store-workbench.view-reports' => 'View inventory reports',

            // ===== EXPENSES =====
            'expenses.view' => 'View expenses',
            'expenses.create' => 'Create expenses',
            'expenses.edit' => 'Edit expenses',
            'expenses.delete' => 'Delete expenses',
            'expenses.approve' => 'Approve expenses',
            'expenses.reject' => 'Reject expenses',
            'expenses.void' => 'Void expenses',
            'expenses.view-reports' => 'View expense reports',

            // ===== STOCK MANAGEMENT (GENERAL) =====
            'stock.view' => 'View stock levels',
            'stock.transfer' => 'Transfer stock between stores',
            'stock.dispense-with-batch' => 'Dispense items with batch selection',
        ];

        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web']
            );
            $this->command->info("Created permission: {$name}");
        }

        // Assign permissions to roles
        $this->assignPermissionsToRoles();

        $this->command->info('Inventory permissions seeded successfully!');
    }

    /**
     * Assign permissions to existing roles
     */
    private function assignPermissionsToRoles(): void
    {
        // Admin role - gets all permissions
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo(Permission::where('name', 'like', 'purchase-orders.%')->pluck('name'));
            $adminRole->givePermissionTo(Permission::where('name', 'like', 'requisitions.%')->pluck('name'));
            $adminRole->givePermissionTo(Permission::where('name', 'like', 'store-workbench.%')->pluck('name'));
            $adminRole->givePermissionTo(Permission::where('name', 'like', 'expenses.%')->pluck('name'));
            $adminRole->givePermissionTo(Permission::where('name', 'like', 'stock.%')->pluck('name'));
            $this->command->info('Assigned all inventory permissions to admin role');
        }

        // Pharmacist role
        $pharmacistRole = Role::where('name', 'pharmacist')->first();
        if ($pharmacistRole) {
            $pharmacistRole->givePermissionTo([
                'purchase-orders.view',
                'requisitions.view',
                'requisitions.create',
                'store-workbench.view',
                'stock.view',
                'stock.dispense-with-batch',
            ]);
            $this->command->info('Assigned pharmacy permissions to pharmacist role');
        }

        // Store Manager role (if exists)
        $storeManagerRole = Role::where('name', 'store-manager')->first();
        if ($storeManagerRole) {
            $storeManagerRole->givePermissionTo([
                'purchase-orders.view',
                'purchase-orders.create',
                'purchase-orders.edit',
                'purchase-orders.submit',
                'purchase-orders.receive',
                'requisitions.view',
                'requisitions.create',
                'requisitions.approve',
                'requisitions.fulfill',
                'store-workbench.view',
                'store-workbench.manage-batches',
                'store-workbench.adjust-stock',
                'store-workbench.write-off',
                'store-workbench.manual-batch',
                'store-workbench.view-reports',
                'stock.view',
                'stock.transfer',
            ]);
            $this->command->info('Assigned store management permissions to store-manager role');
        }

        // Accountant role (if exists)
        $accountantRole = Role::where('name', 'accountant')->first();
        if ($accountantRole) {
            $accountantRole->givePermissionTo([
                'purchase-orders.view',
                'purchase-orders.approve',
                'expenses.view',
                'expenses.create',
                'expenses.edit',
                'expenses.approve',
                'expenses.view-reports',
                'stock.view',
            ]);
            $this->command->info('Assigned accounting permissions to accountant role');
        }

        // Procurement role (if exists)
        $procurementRole = Role::where('name', 'procurement')->first();
        if ($procurementRole) {
            $procurementRole->givePermissionTo([
                'purchase-orders.view',
                'purchase-orders.create',
                'purchase-orders.edit',
                'purchase-orders.submit',
                'purchase-orders.receive',
                'requisitions.view',
                'store-workbench.view',
                'stock.view',
            ]);
            $this->command->info('Assigned procurement permissions to procurement role');
        }

        // Nurse role
        $nurseRole = Role::where('name', 'nurse')->first();
        if ($nurseRole) {
            $nurseRole->givePermissionTo([
                'requisitions.view',
                'requisitions.create',
                'stock.view',
            ]);
            $this->command->info('Assigned basic inventory permissions to nurse role');
        }
    }
}
