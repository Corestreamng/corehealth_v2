<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create store_lane_policies table
 *
 * Plan Reference: docs/STORE_GOVERNANCE_AND_CONTEXTUAL_WORKBENCH_PLAN.md
 *   § 4  (Data Model — store_lane_policies table)
 *   § 5  (Policy Lanes — default matrix)
 *   § 5.2 (Enforcement in StoreRequisitionController::store() L180 + ::approve() L242)
 *   § 9.1 (Admin UI — Lane Policy Matrix, Section B)
 *
 * This table replaces hard-coded lane defaults so the admin can manage them at runtime.
 * Rows in this table are read by the 'requisition-lane-allowed' Gate check that is
 * inserted before RequisitionService::create() (no change to the service itself).
 *
 * Default matrix seeded by StoreGovernanceSeeder:
 *   central  → pharmacy_hub         allowed, no approval
 *   central  → pharmacy_satellite   allowed, no approval
 *   central  → department           allowed, no approval
 *   central  → ward                 allowed, manager approval
 *   pharmacy_hub → pharmacy_satellite allowed, no approval
 *   pharmacy_hub → ward             allowed, manager approval (toggle off by default)
 *   (everything else)               denied
 */
class CreateStoreLanePoliciesTable extends Migration
{
    public function up(): void
    {
        Schema::create('store_lane_policies', function (Blueprint $table) {
            $table->id();

            // Source and destination distribution roles (Plan §3.1 store types)
            $table->string('source_role', 30);
            $table->string('destination_role', 30);

            // Whether this lane is open — false means Gate returns 403
            $table->boolean('allowed')->default(false);

            // Approval level required when allowed = true
            // none | manager | admin
            // 'manager' → checked via Gate 'can-approve-requisition-for-store' (Plan §11)
            $table->string('requires_approval_level', 20)->default('none');

            // Notes visible in admin matrix (Plan §9.1 Section B)
            $table->string('notes')->nullable();

            // Audit trail — changes here affect active requisition lanes (Plan §9.4 save guards)
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();

            // Unique per source→destination pair so upserts are clean
            $table->unique(['source_role', 'destination_role'], 'lane_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_lane_policies');
    }
}
