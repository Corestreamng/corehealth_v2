<?php

namespace App\Providers;

use App\Policies\StoreGovernancePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

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

        // Patient registration / create gate (Reception, Biller, HMO, Nurse, Maternity, Lab, Imaging, Pharmacy, Admin, Staff)
        Gate::define('patient-create', function ($user) {
            return $user->hasAnyRole([
                'SUPERADMIN', 'ADMIN', 'super-admin',
                'RECEPTIONIST', 'Receptionist',
                'BILLER', 'Biller',
                'HMO Executive', 'HMO',
                'NURSE', 'Nurse',
                'MATERNITY', 'Maternity',
                'LAB SCIENTIST', 'Lab Scientist',
                'RADIOLOGIST', 'Radiologist', 'Scanning Room',
                'PHARMACIST', 'Pharmacist',
                'DOCTOR', 'Doctor',
                'SURGEON', 'Surgeon',
            ]) || ($user->is_admin && $user->is_admin != 19)
               || ($user->staff_profile()->exists());
        });

        // Patient demographics edit gate:
        // Reception, SuperAdmin, Admin can edit ALL patients.
        // Departmental workbenches can edit patients registered by their department or by themselves.
        Gate::define('patient-edit', function ($user, \App\Models\Patient $patient) {
            return $patient->canBeEditedBy($user);
        });
    }
}
