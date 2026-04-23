<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\StoreLanePolicy;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * StoreGovernancePolicy — all Gate checks for the store governance system.
 *
 * Plan Reference: docs/STORE_GOVERNANCE_AND_CONTEXTUAL_WORKBENCH_PLAN.md
 *   § 5.2 (Policy enforcement points in StoreRequisitionController)
 *   § 7.5 (Clinical dispense / administer / consumable Gates)
 *   § 11  (Permissions model — Gate name → permission slug mapping)
 *   § 12  (Phase A10, B6, B7 — where each Gate is injected)
 *
 * Registration: app/Providers/AuthServiceProvider.php (see registration block below)
 * All methods use Spatie hasPermissionTo() so the permission matrix from
 * StoreGovernancePermissionsSeeder is the single source of truth.
 *
 * Naming note: Gate names that are scoped to a specific store are called with
 * two arguments: (User $user, Store $store). The controller resolves $store first.
 *
 * == How to register in AuthServiceProvider ==
 *
 *   use App\Models\Store;
 *   use App\Policies\StoreGovernancePolicy;
 *
 *   Gate::define('requisition-lane-allowed', [StoreGovernancePolicy::class, 'requisitionLaneAllowed']);
 *   Gate::define('can-approve-requisition-for-store', [StoreGovernancePolicy::class, 'canApproveRequisitionForStore']);
 *   Gate::define('dispense-from-store', [StoreGovernancePolicy::class, 'dispenseFromStore']);
 *   Gate::define('administer-from-store', [StoreGovernancePolicy::class, 'administerFromStore']);
 *   Gate::define('bill-consumable-from-store', [StoreGovernancePolicy::class, 'billConsumableFromStore']);
 *   Gate::define('store-governance.view', [StoreGovernancePolicy::class, 'viewGovernance']);
 *   Gate::define('store-governance.manage', [StoreGovernancePolicy::class, 'manageGovernance']);
 */
class StoreGovernancePolicy
{
    /**
     * Gate: 'requisition-lane-allowed'
     *
     * Called before RequisitionService::create() in StoreRequisitionController::store() L180
     * Plan §5.2 Step 1, §7.1
     *
     * @param  User   $user
     * @param  string $sourceRole       $sourceStore->distribution_role
     * @param  string $destinationRole  $destinationStore->distribution_role
     * @return Response
     */
    public function requisitionLaneAllowed(User $user, string $sourceRole, string $destinationRole): Response
    {
        // Admin/superadmin bypass lane restrictions
        if ($user->hasRole(['ADMIN', 'SUPERADMIN', 'super-admin'])) {
            return Response::allow();
        }

        $policy = StoreLanePolicy::check($sourceRole, $destinationRole);

        if ($policy->allowed) {
            return Response::allow();
        }

        // Allow if the user has the explicit lane-override permission (Plan §11)
        if ($user->hasPermissionTo('store-policy.override-lane')) {
            return Response::allow();
        }

        return Response::deny($policy->denyReason());
    }

    /**
     * Gate: 'can-approve-requisition-for-store'
     *
     * Called before RequisitionService::approve() in StoreRequisitionController::approve() L242
     * Plan §5.2 Step 2, §7.2
     *
     * The user must be the manager of the SOURCE store (the store being drawn from).
     *
     * @param  User  $user
     * @param  Store $sourceStore  the store that will fulfill this requisition
     * @return Response
     */
    public function canApproveRequisitionForStore(User $user, Store $sourceStore): Response
    {
        if ($user->hasRole(['ADMIN', 'SUPERADMIN', 'super-admin'])) {
            return Response::allow();
        }

        if (! $user->hasPermissionTo('can-approve-requisition-for-store')) {
            return Response::deny('You do not have permission to approve requisitions.');
        }

        // Path A — static store manager assignment
        if ($sourceStore->manager_id === $user->id) {
            return Response::allow();
        }

        // Path B — governance context: the source store is within the user's
        // candidateStores() set (e.g. the ward they're on shift for, or their dept).
        // This allows a charge nurse covering a ward to approve requisitions
        // drawn from that ward's store without being the static manager.
        $candidateIds = app(\App\Services\StoreContextResolver::class)
            ->candidateStores($user)
            ->pluck('id');

        if ($candidateIds->contains($sourceStore->id)) {
            return Response::allow();
        }

        return Response::deny(
            "Approval blocked: only the manager of {$sourceStore->store_name} (or a user whose "
            . "governance context includes that store) can approve this requisition."
        );
    }

    /**
     * Gate: 'dispense-from-store'
     *
     * Injected before StockService::dispenseStock() in PharmacyWorkbenchController::dispenseMedication() L933
     * Plan §6.3 Tab 1, §7.5.1, §B6
     *
     * The store must allow direct patient dispense AND be a pharmacy-class store.
     *
     * @param  User  $user
     * @param  Store $store  the pharmacy store being dispensed from
     * @return Response
     */
    public function dispenseFromStore(User $user, Store $store): Response
    {
        if ($user->hasRole(['ADMIN', 'SUPERADMIN', 'super-admin'])) {
            return Response::allow();
        }

        if (! $user->hasPermissionTo('dispense-from-store')) {
            return Response::deny('You do not have permission to dispense from any store.');
        }

        if (! $store->canDispenseToPatient()) {
            return Response::deny(
                "Store \"{$store->store_name}\" is not configured for direct patient dispense. "
                . 'Contact your administrator to enable this store for dispensing.'
            );
        }

        return Response::allow();
    }

    /**
     * Gate: 'administer-from-store'
     *
     * Injected before ward-stock paths in:
     *   NursingWorkbenchController::administerInjection() L775       (Plan §7.5.2)
     *   NursingWorkbenchController::administerImmunization() L1185   (Plan §7.5.3)
     *   NursingWorkbenchController::administerFromScheduleNew() L3134 (Plan §6.6 Tab 2)
     *
     * Blocks if the store requires an active shift and the user has none.
     *
     * @param  User  $user
     * @param  Store $store  the ward/department store
     * @return Response
     */
    public function administerFromStore(User $user, Store $store): Response
    {
        if ($user->hasRole(['ADMIN', 'SUPERADMIN', 'super-admin'])) {
            return Response::allow();
        }

        if (! $user->hasPermissionTo('administer-from-store')) {
            return Response::deny('You do not have permission to administer drugs from ward stock.');
        }

        // Plan §7.5.2: if requires_shift_context, active shift must exist
        if ($store->requires_shift_context) {
            $activeShift = \App\Models\NursingShift::where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if (! $activeShift) {
                return Response::deny(
                    "Ward stock actions require an active shift for \"{$store->store_name}\". "
                    . 'Please start your shift before administering from ward stock.'
                );
            }
        }

        return Response::allow();
    }

    /**
     * Gate: 'bill-consumable-from-store'
     *
     * Injected before NursingWorkbenchController::addConsumableBill() L1745
     * Plan §6.5 Tab 1, §7.5.4, §B7
     *
     * @param  User  $user
     * @param  Store $store
     * @return Response
     */
    public function billConsumableFromStore(User $user, Store $store): Response
    {
        if ($user->hasRole(['ADMIN', 'SUPERADMIN', 'super-admin'])) {
            return Response::allow();
        }

        if (! $user->hasPermissionTo('bill-consumable-from-store')) {
            return Response::deny('You do not have permission to bill consumables from store stock.');
        }

        if ($store->requires_shift_context) {
            $activeShift = \App\Models\NursingShift::where('user_id', $user->id)
                ->where('status', 'active')
                ->exists();

            if (! $activeShift) {
                return Response::deny(
                    "Consumable billing from \"{$store->store_name}\" requires an active shift."
                );
            }
        }

        return Response::allow();
    }

    /**
     * Gate: 'store-governance.view'
     *
     * Plan §9.1 — admin config page access
     *
     * @param  User $user
     * @return Response
     */
    public function viewGovernance(User $user): Response
    {
        return $user->hasPermissionTo('store-governance.view')
            ? Response::allow()
            : Response::deny('You do not have permission to view store governance settings.');
    }

    /**
     * Gate: 'store-governance.manage'
     *
     * Plan §9.1 — admin config page edit access; also used for requisition approval role check
     *
     * @param  User $user
     * @return Response
     */
    public function manageGovernance(User $user): Response
    {
        return $user->hasPermissionTo('store-governance.manage')
            ? Response::allow()
            : Response::deny('You do not have permission to manage store governance settings.');
    }
}
