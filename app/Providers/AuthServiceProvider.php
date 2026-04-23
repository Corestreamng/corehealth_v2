<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Policies\StoreGovernancePolicy;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // =====================================================================
        // Store Governance Gates
        //
        // Plan Reference: docs/STORE_GOVERNANCE_AND_CONTEXTUAL_WORKBENCH_PLAN.md
        //   § 5.2  (Requisition lane + approval Gates)
        //   § 7.5  (Clinical dispense / administer / consumable Gates)
        //   § 9.1  (Admin config Gates)
        //   § 11   (Permissions model — full Gate ↔ permission slug table)
        //
        // Phase A10 (this file) — registers definitions; controller hooks added in Phase B6/B7.
        // =====================================================================

        // Requisition lane policy — before RequisitionService::create() (Plan §5.2, §7.1)
        Gate::define('requisition-lane-allowed', [StoreGovernancePolicy::class, 'requisitionLaneAllowed']);

        // Requisition approval — before RequisitionService::approve() (Plan §5.2, §7.2)
        Gate::define('can-approve-requisition-for-store', [StoreGovernancePolicy::class, 'canApproveRequisitionForStore']);

        // Pharmacy dispense — before dispenseMedication() L933 (Plan §7.5.1)
        Gate::define('dispense-from-store', [StoreGovernancePolicy::class, 'dispenseFromStore']);

        // Ward injection / immunization — before administerInjection() L775,
        // administerImmunization() L1185, administerFromScheduleNew() L3134 (Plan §7.5.2, §7.5.3)
        Gate::define('administer-from-store', [StoreGovernancePolicy::class, 'administerFromStore']);

        // Consumable billing — before addConsumableBill() L1745 (Plan §7.5.4)
        Gate::define('bill-consumable-from-store', [StoreGovernancePolicy::class, 'billConsumableFromStore']);

        // Admin config page access (Plan §9.1)
        Gate::define('store-governance.view', [StoreGovernancePolicy::class, 'viewGovernance']);
        Gate::define('store-governance.manage', [StoreGovernancePolicy::class, 'manageGovernance']);
    }
}

