<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * StoreGovernancePermissionsSeeder — registers all Gate permission slugs for store governance.
 *
 * Plan Reference: docs/STORE_GOVERNANCE_AND_CONTEXTUAL_WORKBENCH_PLAN.md
 *   § 11  (Permissions Model — complete table of all 13 new permission slugs)
 *   § 12  (Phase A step 4: assign to roles per §11 matrix)
 *
 * These permissions are checked via:
 *   - Spatie hasPermissionTo() middleware on admin config routes
 *   - Gate::authorize() injected into controllers at Phase A10 and B6/B7 steps
 *
 * SAFE TO RE-RUN — uses firstOrCreate.
 *
 * Run with: php artisan db:seed --class=StoreGovernancePermissionsSeeder
 */
class StoreGovernancePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // ===== GOVERNANCE CONFIG (Plan §11) =====
            // Checked on admin Store Governance config routes (Plan §9.1)
            'store-governance.view'   => 'View store governance config (roles, lane matrix, ownership)',
            'store-governance.manage' => 'Edit store roles, lane matrix, ownership and manager assignments',

            // ===== POLICY OVERRIDES (Plan §11) =====
            // Checked before StoreRequisitionController::store() L180 (Plan §5.2, §A10)
            'store-policy.override-lane'    => 'Create a requisition on a denied lane (with mandatory reason)',
            // Checked before fulfill/dispense batch selection (Plan §7.3, §7.5.1)
            'store-policy.override-fifo'    => 'Select a non-FIFO batch; deviation is logged as ShiftAction',
            // Checked before PurchaseOrderController::receive() L336 (Plan §7.4)
            'store-policy.over-receive'     => 'Receive more than the ordered quantity (within tolerance%)',

            // ===== CONTEXT MANAGEMENT (Plan §11) =====
            // Allows manual store override in StoreContextResolver (Plan §10 step 1)
            'store-context.change-manual'         => 'Override auto-resolved store with a manual selection',
            // Allows resolving stores from a different department (Plan §10 step 3)
            'store-context.use-cross-department'  => 'Resolve stores from a department other than own',

            // ===== CLINICAL STOCK ACTIONS (Plan §11, §7.5) =====
            // Scoped to store ID — checked before dispenseMedication() L933 (Plan §7.5.1)
            'dispense-from-store'       => 'Dispense medication to patients from a specific pharmacy store',
            // Scoped to store ID — checked before administerInjection() L775,
            // administerImmunization() L1185, administerFromScheduleNew() L3134 (Plan §7.5.2, §7.5.3)
            'administer-from-store'     => 'Administer ward-stock drugs/vaccines from a specific store',
            // Scoped to store ID — checked before addConsumableBill() L1745 (Plan §7.5.4)
            'bill-consumable-from-store' => 'Bill consumables from a specific store',

            // ===== REQUISITION GATES (Plan §11, §5.2) =====
            // Auto-approved when lane policy allows; injected at StoreRequisitionController::store() L180
            'requisition-lane-allowed'             => 'Create a requisition on an approved lane',
            // Scoped to store ID — injected at StoreRequisitionController::approve() L242
            'can-approve-requisition-for-store'    => 'Approve requisitions where user manages the source store',

            // ===== CANDIDATE STORE ACCESS (Option A — all-stores bypass) =====
            // Bypasses rule-driven candidateStores() and returns every active store.
            // Respects $typeFilter so workbench UIs still scope to their type.
            'stores.candidate-all' => 'See all active stores in any store-picker (bypasses governance rules)',
        ];

        foreach (array_keys($permissions) as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // ===== ASSIGN TO ROLES (Plan §11 Permission Assignment Matrix) =====
        // NOTE: Role names are the canonical DB values (UPPERCASE) discovered from
        //       the Spatie permissions cache. There is no separate 'pharmacy manager'
        //       or 'head nurse' role — those concerns are covered by PHARMACIST and NURSE.
        //       'store keeper' sidebar role is actually named STORE in the DB.
        //
        //       Using where()->first() instead of firstOrCreate to NEVER create phantom roles.
        //       Missing roles are silently skipped (admin can create them if needed).

        // Admin tiers — all permissions
        foreach (['ADMIN', 'SUPERADMIN', 'super-admin'] as $adminRole) {
            $this->assignToRole($adminRole, array_keys($permissions));
        }

        // PHARMACIST — covers both pharmacist and pharmacy-manager duties
        // (no separate 'pharmacy manager' role in the system)
        $this->assignToRole('PHARMACIST', [
            'store-governance.view',
            'dispense-from-store',
            'store-policy.override-fifo',
            'store-context.change-manual',
            'can-approve-requisition-for-store',
            'requisition-lane-allowed',
        ]);

        // NURSE — covers both regular nurse AND head nurse duties
        // (no separate 'head nurse' role — use the broader permission set)
        $this->assignToRole('NURSE', [
            'store-governance.view',
            'administer-from-store',
            'bill-consumable-from-store',
            'store-policy.override-fifo',
            'can-approve-requisition-for-store',
            'requisition-lane-allowed',
        ]);

        // MATERNITY — covers maternity nurse duties (was 'maternity nurse' in old seeder)
        $this->assignToRole('MATERNITY', [
            'administer-from-store',
            'bill-consumable-from-store',
            'requisition-lane-allowed',
        ]);

        // STORE — the sidebar 'Store / Inventory' role (was called 'store keeper' in old seeder)
        $this->assignToRole('STORE', [
            'store-governance.view',
            'can-approve-requisition-for-store',
            'store-policy.over-receive',
            'requisition-lane-allowed',
            'stores.candidate-all',
        ]);
    }

    /**
     * Assign a list of permissions to a role (by exact DB name).
     * NEVER creates the role — silently skips if the role doesn't exist.
     * This prevents creating phantom lowercase roles that shadow real UPPERCASE ones.
     */
    private function assignToRole(string $roleName, array $permissionNames): void
    {
        $role = Role::where('name', $roleName)->first();

        if (! $role) {
            $this->command->warn("Role '{$roleName}' not found in DB — skipping governance permission assignment.");
            return;
        }

        $perms = Permission::whereIn('name', $permissionNames)->get();
        $role->givePermissionTo($perms);
        $this->command->info("Assigned " . $perms->count() . " governance permissions to role '{$roleName}'.");
    }
}
