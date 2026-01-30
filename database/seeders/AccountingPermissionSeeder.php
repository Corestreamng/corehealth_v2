<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Accounting Permission Seeder
 *
 * Reference: Accounting System Plan §11.2 - Permissions
 *
 * Creates all permissions needed for the accounting module
 * and assigns them to appropriate roles.
 */
class AccountingPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Journal Entries
            'accounting.journal.view' => 'View journal entries',
            'accounting.journal.create' => 'Create journal entries',
            'accounting.journal.edit' => 'Edit draft journal entries',
            'accounting.journal.delete' => 'Delete draft journal entries',
            'accounting.journal.submit' => 'Submit journal entries for approval',
            'accounting.journal.approve' => 'Approve pending journal entries',
            'accounting.journal.reject' => 'Reject pending journal entries',
            'accounting.journal.post' => 'Post approved entries to ledger',
            'accounting.journal.reverse' => 'Reverse posted entries',
            'accounting.journal.request-edit' => 'Request edit for posted entries',

            // Chart of Accounts
            'accounting.accounts.view' => 'View chart of accounts',
            'accounting.accounts.create' => 'Create accounts and groups',
            'accounting.accounts.edit' => 'Edit accounts and groups',
            'accounting.accounts.deactivate' => 'Deactivate/activate accounts',

            // Reports
            'accounting.reports.view' => 'View accounting reports',
            'accounting.reports.export' => 'Export reports to PDF/Excel',
            'accounting.reports.drill-down' => 'Access report drill-down details',

            // Credit Notes
            'accounting.credit-notes.view' => 'View credit notes',
            'accounting.credit-notes.create' => 'Create credit notes',
            'accounting.credit-notes.approve' => 'Approve credit notes',
            'accounting.credit-notes.reject' => 'Reject credit notes',
            'accounting.credit-notes.apply' => 'Apply credit notes to invoices',

            // Fiscal Periods
            'accounting.periods.view' => 'View fiscal periods and years',
            'accounting.periods.manage' => 'Create fiscal years and periods',
            'accounting.periods.close' => 'Close fiscal periods and years',
            'accounting.periods.reopen' => 'Reopen closed periods (admin only)',

            // Opening Balances
            'accounting.opening-balances.view' => 'View opening balances',
            'accounting.opening-balances.create' => 'Create/edit opening balances',

            // Settings & Configuration
            'accounting.settings.view' => 'View accounting settings',
            'accounting.settings.manage' => 'Manage accounting settings',
        ];

        // Create all permissions
        foreach ($permissions as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web']
            );
            $this->command->info("Created permission: {$name}");
        }

        // Define role permissions
        $rolePermissions = [
            // Accountant - Full access to most features
            'accountant' => [
                'accounting.journal.view',
                'accounting.journal.create',
                'accounting.journal.edit',
                'accounting.journal.delete',
                'accounting.journal.submit',
                'accounting.journal.approve',
                'accounting.journal.reject',
                'accounting.journal.post',
                'accounting.journal.reverse',
                'accounting.journal.request-edit',
                'accounting.accounts.view',
                'accounting.accounts.create',
                'accounting.accounts.edit',
                'accounting.accounts.deactivate',
                'accounting.reports.view',
                'accounting.reports.export',
                'accounting.reports.drill-down',
                'accounting.credit-notes.view',
                'accounting.credit-notes.create',
                'accounting.credit-notes.approve',
                'accounting.credit-notes.reject',
                'accounting.credit-notes.apply',
                'accounting.periods.view',
                'accounting.periods.manage',
                'accounting.periods.close',
                'accounting.opening-balances.view',
                'accounting.opening-balances.create',
                'accounting.settings.view',
            ],

            // Accounts Clerk - Data entry, limited approval
            'accounts_clerk' => [
                'accounting.journal.view',
                'accounting.journal.create',
                'accounting.journal.edit',
                'accounting.journal.delete',
                'accounting.journal.submit',
                'accounting.accounts.view',
                'accounting.reports.view',
                'accounting.credit-notes.view',
                'accounting.credit-notes.create',
                'accounting.periods.view',
                'accounting.opening-balances.view',
            ],

            // Finance Manager - Full access including settings
            'finance_manager' => [
                'accounting.journal.view',
                'accounting.journal.create',
                'accounting.journal.edit',
                'accounting.journal.delete',
                'accounting.journal.submit',
                'accounting.journal.approve',
                'accounting.journal.reject',
                'accounting.journal.post',
                'accounting.journal.reverse',
                'accounting.journal.request-edit',
                'accounting.accounts.view',
                'accounting.accounts.create',
                'accounting.accounts.edit',
                'accounting.accounts.deactivate',
                'accounting.reports.view',
                'accounting.reports.export',
                'accounting.reports.drill-down',
                'accounting.credit-notes.view',
                'accounting.credit-notes.create',
                'accounting.credit-notes.approve',
                'accounting.credit-notes.reject',
                'accounting.credit-notes.apply',
                'accounting.periods.view',
                'accounting.periods.manage',
                'accounting.periods.close',
                'accounting.periods.reopen',
                'accounting.opening-balances.view',
                'accounting.opening-balances.create',
                'accounting.settings.view',
                'accounting.settings.manage',
            ],

            // Admin - Everything
            'admin' => array_keys($permissions),
            'super-admin' => array_keys($permissions),

            // Billing Staff - View only for reports
            'billing' => [
                'accounting.journal.view',
                'accounting.accounts.view',
                'accounting.reports.view',
                'accounting.credit-notes.view',
                'accounting.periods.view',
            ],

            // Cashier - Limited view
            'cashier' => [
                'accounting.journal.view',
                'accounting.accounts.view',
                'accounting.reports.view',
            ],
        ];

        // Assign permissions to roles
        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)->first();

            if ($role) {
                foreach ($permissionNames as $permissionName) {
                    $permission = Permission::where('name', $permissionName)->first();
                    if ($permission && !$role->hasPermissionTo($permissionName)) {
                        $role->givePermissionTo($permission);
                    }
                }
                $this->command->info("Assigned accounting permissions to {$roleName} role");
            } else {
                $this->command->warn("Role '{$roleName}' not found, skipping...");
            }
        }

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Accounting permissions seeded successfully!');
    }
}
