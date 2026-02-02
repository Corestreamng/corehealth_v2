<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fixed Assets Tables Migration
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Sections 4.1B, 6.6
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 5
 *
 * Creates tables for fixed asset management (IAS 16 compliance):
 * - fixed_asset_categories
 * - fixed_assets
 * - fixed_asset_depreciations (monthly depreciation log)
 * - fixed_asset_disposals
 * - fixed_asset_transfers
 * - equipment_maintenance_schedules
 *
 * Also extends purchase_order_items to support fixed asset acquisition.
 */
return new class extends Migration
{
    public function up()
    {
        // Fixed Asset Categories
        Schema::create('fixed_asset_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->foreignId('asset_account_id')->constrained('accounts');       // e.g., 1400 Fixed Assets
            $table->foreignId('depreciation_account_id')->constrained('accounts'); // e.g., 1410 Accumulated Depreciation
            $table->foreignId('expense_account_id')->constrained('accounts');      // e.g., 6200 Depreciation Expense
            $table->integer('default_useful_life_years');
            $table->enum('default_depreciation_method', [
                'straight_line',
                'declining_balance',
                'double_declining',
                'sum_of_years',
                'units_of_production',
            ])->default('straight_line');
            $table->decimal('default_salvage_percentage', 5, 2)->default(10.00);
            $table->boolean('is_depreciable')->default(true);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });

        // Main Fixed Assets Register
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('asset_number')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('category_id')->constrained('fixed_asset_categories');
            $table->foreignId('account_id')->constrained('accounts');             // Asset account
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries'); // Acquisition JE

            // Acquisition source polymorphic
            $table->string('source_type')->nullable();    // App\Models\PurchaseOrder, manual, etc.
            $table->unsignedBigInteger('source_id')->nullable();

            // Cost and valuation
            $table->decimal('acquisition_cost', 15, 2);
            $table->decimal('additional_costs', 15, 2)->default(0);   // Installation, transport
            $table->decimal('total_cost', 15, 2);                      // acquisition + additional
            $table->decimal('salvage_value', 15, 2)->default(0);
            $table->decimal('depreciable_amount', 15, 2);              // total_cost - salvage
            $table->decimal('accumulated_depreciation', 15, 2)->default(0);
            $table->decimal('book_value', 15, 2);                      // total_cost - accumulated_depreciation

            // Depreciation settings
            $table->enum('depreciation_method', [
                'straight_line',
                'declining_balance',
                'double_declining',
                'sum_of_years',
                'units_of_production',
            ])->default('straight_line');
            $table->integer('useful_life_years');
            $table->integer('useful_life_months')->nullable();         // For precise calculation
            $table->decimal('monthly_depreciation', 15, 2)->default(0);

            // Dates
            $table->date('acquisition_date');
            $table->date('in_service_date');                           // When depreciation starts
            $table->date('last_depreciation_date')->nullable();
            $table->date('disposal_date')->nullable();

            // Physical details
            $table->string('serial_number')->nullable();
            $table->string('model_number')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('location')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->foreignId('custodian_user_id')->nullable()->constrained('users');

            // Warranty and insurance
            $table->date('warranty_expiry_date')->nullable();
            $table->string('warranty_provider')->nullable();
            $table->string('insurance_policy_number')->nullable();
            $table->date('insurance_expiry_date')->nullable();

            // Supplier info
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers');
            $table->string('invoice_number')->nullable();

            // Status
            $table->enum('status', [
                'active',
                'fully_depreciated',
                'disposed',
                'impaired',
                'under_maintenance',
                'idle',
            ])->default('active');

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'status']);
            $table->index(['department_id', 'status']);
            $table->index('acquisition_date');
            $table->index('in_service_date');
        });

        // Monthly Depreciation Log
        Schema::create('fixed_asset_depreciations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->onDelete('cascade');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years');
            $table->date('depreciation_date');
            $table->integer('year_number');                // Year 1, 2, 3...
            $table->integer('month_number');               // Month 1-12
            $table->decimal('opening_book_value', 15, 2);
            $table->decimal('depreciation_amount', 15, 2);
            $table->decimal('closing_book_value', 15, 2);
            $table->decimal('accumulated_depreciation_to_date', 15, 2);
            $table->enum('calculation_method', [
                'scheduled',       // Regular monthly depreciation
                'catch_up',        // Catch-up depreciation
                'adjustment',      // Manual adjustment
                'impairment',      // Impairment loss
            ])->default('scheduled');
            $table->text('notes')->nullable();
            $table->foreignId('processed_by')->constrained('users');
            $table->timestamps();

            $table->unique(['fixed_asset_id', 'year_number', 'month_number'], 'fa_depr_asset_period_unique');
            $table->index('depreciation_date');
        });

        // Asset Disposals
        Schema::create('fixed_asset_disposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->date('disposal_date');
            $table->enum('disposal_type', [
                'sale',
                'scrapped',
                'donated',
                'trade_in',
                'theft_loss',
                'insurance_claim',
            ]);
            $table->decimal('disposal_proceeds', 15, 2)->default(0);
            $table->decimal('book_value_at_disposal', 15, 2);
            $table->decimal('gain_loss_on_disposal', 15, 2);      // proceeds - book_value
            $table->decimal('disposal_costs', 15, 2)->default(0);
            $table->string('buyer_name')->nullable();
            $table->string('invoice_number')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', [
                'pending',
                'approved',
                'completed',
                'cancelled',
            ])->default('pending');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['disposal_date', 'status']);
        });

        // Asset Transfers (between departments/locations)
        Schema::create('fixed_asset_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets');
            $table->string('transfer_number')->unique();
            $table->date('transfer_date');

            // From
            $table->foreignId('from_department_id')->nullable()->constrained('departments');
            $table->foreignId('from_custodian_id')->nullable()->constrained('users');
            $table->string('from_location')->nullable();

            // To
            $table->foreignId('to_department_id')->nullable()->constrained('departments');
            $table->foreignId('to_custodian_id')->nullable()->constrained('users');
            $table->string('to_location')->nullable();

            $table->text('reason')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->enum('status', [
                'pending',
                'approved',
                'completed',
                'rejected',
            ])->default('pending');
            $table->timestamps();

            $table->index(['transfer_date', 'status']);
        });

        // Equipment Maintenance Schedules
        Schema::create('equipment_maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets');
            $table->string('schedule_number')->unique();
            $table->enum('maintenance_type', [
                'preventive',
                'corrective',
                'calibration',
                'inspection',
                'certification',
            ]);
            $table->string('description');
            $table->enum('frequency', [
                'daily',
                'weekly',
                'monthly',
                'quarterly',
                'semi_annually',
                'annually',
                'as_needed',
            ])->nullable();
            $table->date('scheduled_date');
            $table->date('actual_date')->nullable();
            $table->foreignId('service_provider_id')->nullable()->constrained('suppliers');
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->decimal('actual_cost', 15, 2)->nullable();
            $table->foreignId('expense_id')->nullable()->constrained('expenses');
            $table->enum('status', [
                'scheduled',
                'in_progress',
                'completed',
                'overdue',
                'cancelled',
            ])->default('scheduled');
            $table->date('next_scheduled_date')->nullable();
            $table->text('findings')->nullable();
            $table->text('actions_taken')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fixed_asset_id', 'status']);
            $table->index(['scheduled_date', 'status']);
        });

        // Extend purchase_order_items to support fixed asset acquisition
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->enum('item_type', ['inventory', 'fixed_asset', 'expense'])->default('inventory')->after('product_id');
            $table->foreignId('fixed_asset_category_id')->nullable()->after('item_type')->constrained('fixed_asset_categories');
            $table->string('asset_name')->nullable()->after('fixed_asset_category_id');
            $table->string('asset_serial_number')->nullable()->after('asset_name');
        });
    }

    public function down()
    {
        // Remove columns from purchase_order_items
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['fixed_asset_category_id']);
            $table->dropColumn(['item_type', 'fixed_asset_category_id', 'asset_name', 'asset_serial_number']);
        });

        Schema::dropIfExists('equipment_maintenance_schedules');
        Schema::dropIfExists('fixed_asset_transfers');
        Schema::dropIfExists('fixed_asset_disposals');
        Schema::dropIfExists('fixed_asset_depreciations');
        Schema::dropIfExists('fixed_assets');
        Schema::dropIfExists('fixed_asset_categories');
    }
};
