<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create store_context_rules table
 *
 * Plan Reference: docs/STORE_GOVERNANCE_AND_CONTEXTUAL_WORKBENCH_PLAN.md
 *   § 9.3  (Admin Module: Context Resolution Rules)
 *   § 10   (Workbench Context Resolution Logic — resolution chain steps 4 & 5)
 *   § 12 Phase B (StoreContextResolver::resolve() uses this table for role-default fallback)
 *
 * StoreContextResolver resolution chain order (Plan §10):
 *   1. Session explicit override (store-context.change-manual permission)
 *   2. Active NursingShift → stores.ward_id          (no DB lookup needed)
 *   3. User.department_id  → stores.department_id    (no DB lookup needed)
 *   4. User.default_store_id                         (no DB lookup needed)
 *   5. THIS TABLE: user_role → store_id (role-default fallback)
 *
 * Also stores the ward-level "require shift" flag and department→store overrides
 * that the admin configures in the Context Resolution Rules page (Plan §9.3).
 */
class CreateStoreContextRulesTable extends Migration
{
    public function up(): void
    {
        Schema::create('store_context_rules', function (Blueprint $table) {
            $table->id();

            // Rule type distinguishes the three sub-tables shown in Plan §9.3
            // role_default | department_override | fallback_behavior
            $table->string('rule_type', 30);

            // For rule_type = 'role_default': the Spatie role name (e.g. 'pharmacist', 'nurse')
            $table->string('user_role', 60)->nullable();

            // For rule_type = 'department_override': the department (Plan §9.3 Section B)
            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();

            // The store this rule resolves to (Plan §10 step 5)
            $table->unsignedBigInteger('store_id')->nullable();
            $table->foreign('store_id')->references('id')->on('stores')->nullOnDelete();

            // For rule_type = 'fallback_behavior':
            // block | allow_manual | use_role_default
            // Drives the "Fallback Behavior" dropdown in Plan §9.3 Section E
            $table->string('fallback_action', 30)->nullable();

            // Admin notes (shown in config UI)
            $table->string('notes')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_context_rules');
    }
}
