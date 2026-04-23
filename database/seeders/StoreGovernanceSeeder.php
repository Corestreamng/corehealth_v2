<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Store;
use App\Models\StoreLanePolicy;

/**
 * StoreGovernanceSeeder — seeds the governance layer on top of existing stores.
 *
 * Plan Reference: docs/STORE_GOVERNANCE_AND_CONTEXTUAL_WORKBENCH_PLAN.md
 *   § 4   (Data Model — distribution_role values)
 *   § 5.1 (Default Lane Matrix)
 *   § 12  (Phase A rollout: step 2 "Seed distribution_role for all existing stores")
 *   § Appendix C (Backward compat: existing stores must continue working)
 *
 * SAFE TO RE-RUN — uses updateOrCreate / upsert patterns.
 * Stores whose distribution_role is already set are NOT changed.
 * Stores with no keyword match get 'other' (lane-bypass, Plan §14 NFRs).
 *
 * Step 1 — Map distribution_role onto existing stores by name/type heuristics.
 * Step 2 — Seed the default lane policy matrix (Plan §5.1).
 * Step 3 — Seed the default fallback_behavior context rule (Plan §9.3 Section E).
 */
class StoreGovernanceSeeder extends Seeder
{
    // Keyword → distribution_role mapping (case-insensitive on store_name)
    private const NAME_MAP = [
        'central'       => Store::ROLE_CENTRAL,
        'main store'    => Store::ROLE_CENTRAL,
        'main pharmacy' => Store::ROLE_PHARMACY_HUB,
        'central pharm' => Store::ROLE_PHARMACY_HUB,
        'pharmacy hub'  => Store::ROLE_PHARMACY_HUB,
        'satellite'     => Store::ROLE_PHARMACY_SATELLITE,
        'dispensary'    => Store::ROLE_PHARMACY_SATELLITE,
        'outpatient'    => Store::ROLE_PHARMACY_SATELLITE,
        'theatre'       => Store::ROLE_DEPARTMENT,
        'laboratory'    => Store::ROLE_DEPARTMENT,
        'imaging'       => Store::ROLE_DEPARTMENT,
        'radiology'     => Store::ROLE_DEPARTMENT,
        'ward'          => Store::ROLE_WARD,
        'postnatal'     => Store::ROLE_WARD,
        'maternity'     => Store::ROLE_WARD,
        'icu'           => Store::ROLE_WARD,
        'nicu'          => Store::ROLE_WARD,
        'picu'          => Store::ROLE_WARD,
    ];

    // store_type → distribution_role fallback when name keywords don't match
    private const TYPE_MAP = [
        'warehouse' => Store::ROLE_CENTRAL,
        'pharmacy'  => Store::ROLE_PHARMACY_HUB,
        'theatre'   => Store::ROLE_DEPARTMENT,
        'ward'      => Store::ROLE_WARD,
    ];

    public function run(): void
    {
        $this->seedDistributionRoles();
        $this->seedLanePolicies();
        $this->seedFallbackRule();
    }

    // -------------------------------------------------------------------------
    // Step 1 — Map distribution_role onto existing stores
    // -------------------------------------------------------------------------
    private function seedDistributionRoles(): void
    {
        Store::all()->each(function (Store $store) {
            // Don't overwrite stores already classified
            if ($store->distribution_role && $store->distribution_role !== 'other') {
                return;
            }

            $role = $this->inferRole($store);
            $store->update(['distribution_role' => $role]);

            // For pharmacy stores: set allows_direct_patient_dispense = true
            if (in_array($role, Store::DISPENSE_ROLES)) {
                $store->update(['allows_direct_patient_dispense' => true]);
            }

            // For ward stores: set requires_shift_context = true
            if ($role === Store::ROLE_WARD) {
                $store->update(['requires_shift_context' => true]);
            }
        });
    }

    private function inferRole(Store $store): string
    {
        $name = strtolower($store->store_name ?? '');

        foreach (self::NAME_MAP as $keyword => $role) {
            if (str_contains($name, $keyword)) {
                return $role;
            }
        }

        return self::TYPE_MAP[$store->store_type] ?? Store::ROLE_OTHER;
    }

    // -------------------------------------------------------------------------
    // Step 2 — Seed default lane policy matrix (Plan §5.1)
    // -------------------------------------------------------------------------
    private function seedLanePolicies(): void
    {
        foreach (StoreLanePolicy::defaultMatrix() as $row) {
            StoreLanePolicy::updateOrCreate(
                [
                    'source_role'      => $row[0],
                    'destination_role' => $row[1],
                ],
                [
                    'allowed'                 => $row[2],
                    'requires_approval_level' => $row[3],
                    'notes'                   => $row[4],
                ]
            );
        }
    }

    // -------------------------------------------------------------------------
    // Step 3 — Default fallback behaviour: block when context unresolved (Plan §9.3 E)
    // -------------------------------------------------------------------------
    private function seedFallbackRule(): void
    {
        \App\Models\StoreContextRule::updateOrCreate(
            ['rule_type' => 'fallback_behavior'],
            [
                'fallback_action' => 'block',
                'notes'           => 'Default: block all stock actions when store context cannot be resolved.',
            ]
        );
    }
}
