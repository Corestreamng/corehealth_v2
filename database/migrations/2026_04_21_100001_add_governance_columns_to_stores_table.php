<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add governance columns to stores table
 *
 * Plan Reference: docs/STORE_GOVERNANCE_AND_CONTEXTUAL_WORKBENCH_PLAN.md § 4 (Minimal Data Model Extension)
 *
 * Adds the distribution_role enum and ownership linkage columns that drive:
 *  - Lane policy enforcement (§ 5)  → StoreRequisitionController::store() L180
 *  - Context resolution (§ 10)      → StoreContextResolver service
 *  - Workbench role-aware rendering (§ 6)
 *  - Gate checks on clinical actions (§ 7.5)
 *
 * BACKWARD COMPATIBILITY:
 *  - All new columns are nullable or have safe defaults.
 *  - Existing store_type enum is preserved untouched (§ Appendix C).
 *  - Stores with distribution_role = 'other' (null → seeded as 'other') bypass lane checks.
 */
class AddGovernanceColumnsToStoresTable extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Core governance discriminator — drives lane policy, workbench tabs, Gate checks.
            // Plan §4: central | pharmacy_hub | pharmacy_satellite | department | ward | other
            if (! Schema::hasColumn('stores', 'distribution_role')) {
                $table->string('distribution_role', 30)->default('other')->after('store_type');
            }

            // Ownership linkages — used by StoreContextResolver (Plan §10) to map:
            //   ward_id       → NursingShift.ward_id  → resolveFromShift()
            //   department_id → User.department_id     → resolveFromUser()
            //   parent_store_id → satellite's hub      → replenishment lane default
            if (! Schema::hasColumn('stores', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('distribution_role');
            }
            if (! Schema::hasColumn('stores', 'ward_id')) {
                $table->unsignedBigInteger('ward_id')->nullable()->after('department_id');
            }
            if (! Schema::hasColumn('stores', 'parent_store_id')) {
                $table->unsignedBigInteger('parent_store_id')->nullable()->after('ward_id');
            }

            // Behaviour flags — checked before clinical stock actions (Plan §7.5)
            // allows_direct_patient_dispense: Gate check in dispenseMedication() L933
            // requires_shift_context:         Gate check in administerInjection() L775,
            //                                 administerImmunization() L1185, addConsumableBill() L1745
            if (! Schema::hasColumn('stores', 'allows_direct_patient_dispense')) {
                $table->boolean('allows_direct_patient_dispense')->default(false)->after('parent_store_id');
            }
            if (! Schema::hasColumn('stores', 'requires_shift_context')) {
                $table->boolean('requires_shift_context')->default(false)->after('allows_direct_patient_dispense');
            }
        });

        // Foreign keys in a separate call to avoid SQLite edge-cases in tests
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'department_id')) {
                try {
                    $table->foreign('department_id')
                          ->references('id')->on('departments')
                          ->nullOnDelete();
                } catch (\Exception $e) {
                    // departments table may not exist in all environments — silently skip
                }
            }
            if (Schema::hasColumn('stores', 'ward_id')) {
                try {
                    $table->foreign('ward_id')
                          ->references('id')->on('wards')
                          ->nullOnDelete();
                } catch (\Exception $e) {
                    // wards table may not exist in all environments — silently skip
                }
            }
            if (Schema::hasColumn('stores', 'parent_store_id')) {
                $table->foreign('parent_store_id')
                      ->references('id')->on('stores')
                      ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // Drop foreign keys before columns
            try { $table->dropForeign(['department_id']); } catch (\Exception $e) {}
            try { $table->dropForeign(['ward_id']); } catch (\Exception $e) {}
            try { $table->dropForeign(['parent_store_id']); } catch (\Exception $e) {}

            $columns = [
                'distribution_role',
                'department_id',
                'ward_id',
                'parent_store_id',
                'allows_direct_patient_dispense',
                'requires_shift_context',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('stores', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
