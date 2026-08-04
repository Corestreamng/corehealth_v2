<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds audit tracking columns to service request tables,
     * shift_id to payments, context to nursing_shifts,
     * and billing preference + role to patients.
     */
    public function up(): void
    {
        // 1. Audit tracking on product_or_service_requests
        Schema::table('product_or_service_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('product_or_service_requests', 'audited_at')) {
                $table->timestamp('audited_at')->nullable()->after('is_bundle_item');
            }
            if (!Schema::hasColumn('product_or_service_requests', 'audited_by')) {
                $table->foreignId('audited_by')->nullable()->after('audited_at')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('product_or_service_requests', 'audit_notes')) {
                $table->text('audit_notes')->nullable()->after('audited_by');
            }
        });

        // 2. Audit tracking on lab_service_requests
        Schema::table('lab_service_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('lab_service_requests', 'audited_at')) {
                $table->timestamp('audited_at')->nullable();
            }
            if (!Schema::hasColumn('lab_service_requests', 'audited_by')) {
                $table->foreignId('audited_by')->nullable()
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('lab_service_requests', 'audit_notes')) {
                $table->text('audit_notes')->nullable();
            }
        });

        // 3. Audit tracking on imaging_service_requests
        Schema::table('imaging_service_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('imaging_service_requests', 'audited_at')) {
                $table->timestamp('audited_at')->nullable();
            }
            if (!Schema::hasColumn('imaging_service_requests', 'audited_by')) {
                $table->foreignId('audited_by')->nullable()
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('imaging_service_requests', 'audit_notes')) {
                $table->text('audit_notes')->nullable();
            }
        });

        // 4. Shift tracking on payments
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'shift_id')) {
                $table->foreignId('shift_id')->nullable()
                    ->constrained('nursing_shifts')->nullOnDelete();
            }
        });

        // 5. Context on nursing_shifts (reuse for billing/lab/general)
        Schema::table('nursing_shifts', function (Blueprint $table) {
            if (!Schema::hasColumn('nursing_shifts', 'context')) {
                $table->string('context', 20)->default('nursing')->after('shift_type')
                    ->comment('nursing, billing, lab, general');
            }
            // Add a denormalized payments_count for billing shift tracking
            if (!Schema::hasColumn('nursing_shifts', 'payments_count')) {
                $table->unsignedInteger('payments_count')->default(0)->after('bills_count');
            }
            // Add a denormalized total_collected for billing shift quick display
            if (!Schema::hasColumn('nursing_shifts', 'total_collected')) {
                $table->decimal('total_collected', 14, 2)->default(0)->after('payments_count');
            }
        });

        // 6. Patient billing preferences + dependent role
        Schema::table('patients', function (Blueprint $table) {
            if (!Schema::hasColumn('patients', 'default_billing_mode')) {
                $table->string('default_billing_mode', 30)->nullable()->after('principal_id')
                    ->comment('CASH, POS, TRANSFER, MOBILE, BILL_TO_STAFF, BILL_TO_ORGANIZATION, ACCOUNT');
            }
            if (!Schema::hasColumn('patients', 'default_billing_id')) {
                $table->unsignedBigInteger('default_billing_id')->nullable()->after('default_billing_mode')
                    ->comment('staff_user_id or organization_id depending on default_billing_mode');
            }
            if (!Schema::hasColumn('patients', 'role')) {
                $table->string('role', 20)->nullable()->after('default_billing_id')
                    ->comment('Principal, Spouse, Child, Other — for HMO/insurance dependents');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_or_service_requests', function (Blueprint $table) {
            $table->dropForeign(['audited_by']);
            $table->dropColumn(['audited_at', 'audited_by', 'audit_notes']);
        });

        Schema::table('lab_service_requests', function (Blueprint $table) {
            $table->dropForeign(['audited_by']);
            $table->dropColumn(['audited_at', 'audited_by', 'audit_notes']);
        });

        Schema::table('imaging_service_requests', function (Blueprint $table) {
            $table->dropForeign(['audited_by']);
            $table->dropColumn(['audited_at', 'audited_by', 'audit_notes']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropColumn('shift_id');
        });

        Schema::table('nursing_shifts', function (Blueprint $table) {
            $table->dropColumn(['context', 'payments_count', 'total_collected']);
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['default_billing_mode', 'default_billing_id', 'role']);
        });
    }
};
