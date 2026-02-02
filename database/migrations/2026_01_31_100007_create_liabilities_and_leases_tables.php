<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liabilities & Lease Management Tables Migration
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Sections 4.1A, 6.13
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 6
 *
 * Creates tables for:
 * - Liability schedules (loans, mortgages)
 * - Lease management (IFRS 16 compliance)
 * - Lease payment schedules
 */
return new class extends Migration
{
    public function up()
    {
        // Liability Schedules (Loans, Mortgages, etc.)
        Schema::create('liability_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('liability_number')->unique();
            $table->foreignId('account_id')->constrained('accounts');  // Liability account (2xxx)
            $table->foreignId('interest_expense_account_id')->nullable()->constrained('accounts');
            $table->string('liability_type');  // loan, mortgage, overdraft, credit_line
            $table->string('creditor_name');
            $table->string('creditor_contact')->nullable();
            $table->string('reference_number')->nullable();  // Bank reference/loan ID

            // Amounts
            $table->decimal('principal_amount', 15, 2);
            $table->decimal('current_balance', 15, 2);
            $table->decimal('interest_rate', 8, 4);  // Annual rate
            $table->enum('interest_type', ['simple', 'compound', 'flat'])->default('compound');

            // Terms
            $table->date('start_date');
            $table->date('maturity_date');
            $table->integer('term_months');
            $table->enum('payment_frequency', [
                'weekly',
                'bi_weekly',
                'monthly',
                'quarterly',
                'semi_annually',
                'annually',
                'at_maturity',
            ])->default('monthly');
            $table->date('next_payment_date');
            $table->decimal('regular_payment_amount', 15, 2);  // EMI/regular payment

            // Security/Collateral
            $table->text('collateral_description')->nullable();
            $table->decimal('collateral_value', 15, 2)->nullable();

            // Classification
            $table->decimal('current_portion', 15, 2)->default(0);      // Due within 1 year
            $table->decimal('non_current_portion', 15, 2)->default(0);  // Due after 1 year

            $table->enum('status', [
                'active',
                'paid_off',
                'defaulted',
                'restructured',
                'written_off',
            ])->default('active');

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_id', 'status']);
            $table->index('next_payment_date');
            $table->index('maturity_date');
        });

        // Liability Payment Schedule (Amortization)
        Schema::create('liability_payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liability_id')->constrained('liability_schedules')->onDelete('cascade');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->integer('payment_number');
            $table->date('due_date');
            $table->date('payment_date')->nullable();

            // Payment breakdown
            $table->decimal('scheduled_payment', 15, 2);
            $table->decimal('principal_portion', 15, 2);
            $table->decimal('interest_portion', 15, 2);
            $table->decimal('actual_payment', 15, 2)->nullable();
            $table->decimal('late_fee', 15, 2)->default(0);

            // Balances
            $table->decimal('opening_balance', 15, 2);
            $table->decimal('closing_balance', 15, 2);

            $table->enum('status', [
                'scheduled',
                'paid',
                'partial',
                'overdue',
                'waived',
            ])->default('scheduled');

            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['liability_id', 'payment_number']);
            $table->index(['due_date', 'status']);
        });

        // Lease Management (IFRS 16)
        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->string('lease_number')->unique();
            $table->enum('lease_type', [
                'operating',     // Old classification (pre-IFRS 16)
                'finance',       // Finance/capital lease
                'short_term',    // <= 12 months, exempt from IFRS 16
                'low_value',     // Low value assets, exempt from IFRS 16
            ]);
            $table->string('leased_item');
            $table->text('description')->nullable();

            // Parties
            $table->foreignId('lessor_id')->nullable()->constrained('suppliers');
            $table->string('lessor_name')->nullable();
            $table->string('lessor_contact')->nullable();

            // Accounts for IFRS 16
            $table->foreignId('rou_asset_account_id')->nullable()->constrained('accounts');       // Right-of-Use Asset
            $table->foreignId('lease_liability_account_id')->nullable()->constrained('accounts'); // Lease Liability
            $table->foreignId('depreciation_account_id')->nullable()->constrained('accounts');    // ROU Depreciation
            $table->foreignId('interest_account_id')->nullable()->constrained('accounts');        // Interest Expense

            // Lease terms
            $table->date('commencement_date');
            $table->date('end_date');
            $table->integer('lease_term_months');
            $table->decimal('monthly_payment', 15, 2);
            $table->decimal('annual_rent_increase_rate', 5, 2)->default(0);  // Annual escalation %

            // IFRS 16 Values
            $table->decimal('incremental_borrowing_rate', 8, 4)->default(10);  // IBR %
            $table->decimal('total_lease_payments', 15, 2);
            $table->decimal('initial_rou_asset_value', 15, 2)->default(0);     // PV of payments
            $table->decimal('initial_lease_liability', 15, 2)->default(0);
            $table->decimal('current_rou_asset_value', 15, 2)->default(0);
            $table->decimal('accumulated_rou_depreciation', 15, 2)->default(0);
            $table->decimal('current_lease_liability', 15, 2)->default(0);

            // Initial direct costs and incentives
            $table->decimal('initial_direct_costs', 15, 2)->default(0);
            $table->decimal('lease_incentives_received', 15, 2)->default(0);

            // Purchase option
            $table->boolean('has_purchase_option')->default(false);
            $table->decimal('purchase_option_amount', 15, 2)->nullable();
            $table->boolean('purchase_option_reasonably_certain')->default(false);

            // Termination option
            $table->boolean('has_termination_option')->default(false);
            $table->date('earliest_termination_date')->nullable();
            $table->decimal('termination_penalty', 15, 2)->nullable();

            // Residual value guarantee
            $table->decimal('residual_value_guarantee', 15, 2)->default(0);

            // Location
            $table->string('asset_location')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments');

            $table->enum('status', [
                'draft',
                'active',
                'expired',
                'terminated',
                'purchased',
            ])->default('draft');

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['lease_type', 'status']);
            $table->index('commencement_date');
            $table->index('end_date');
        });

        // Lease Payment Schedule
        Schema::create('lease_payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases')->onDelete('cascade');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->integer('payment_number');
            $table->date('due_date');
            $table->date('payment_date')->nullable();

            // Payment breakdown
            $table->decimal('payment_amount', 15, 2);
            $table->decimal('principal_portion', 15, 2);       // Reduces lease liability
            $table->decimal('interest_portion', 15, 2);        // Interest expense
            $table->decimal('actual_payment', 15, 2)->nullable();

            // Balances
            $table->decimal('opening_liability', 15, 2);
            $table->decimal('closing_liability', 15, 2);

            // ROU Depreciation (for this period)
            $table->decimal('rou_depreciation', 15, 2)->default(0);
            $table->decimal('opening_rou_value', 15, 2)->nullable();
            $table->decimal('closing_rou_value', 15, 2)->nullable();

            $table->enum('status', [
                'scheduled',
                'paid',
                'partial',
                'overdue',
            ])->default('scheduled');

            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['lease_id', 'payment_number']);
            $table->index(['due_date', 'status']);
        });

        // Lease Modifications (IFRS 16 remeasurement events)
        Schema::create('lease_modifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained('leases');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->date('modification_date');
            $table->enum('modification_type', [
                'term_extension',
                'term_reduction',
                'payment_change',
                'scope_change',
                'rate_change',
            ]);
            $table->text('description');

            // Before modification
            $table->decimal('old_lease_liability', 15, 2);
            $table->decimal('old_rou_asset', 15, 2);
            $table->integer('old_remaining_term_months');
            $table->decimal('old_monthly_payment', 15, 2);

            // After modification
            $table->decimal('new_lease_liability', 15, 2);
            $table->decimal('new_rou_asset', 15, 2);
            $table->integer('new_remaining_term_months');
            $table->decimal('new_monthly_payment', 15, 2);
            $table->decimal('adjustment_amount', 15, 2);

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('modification_date');
        });
    }

    public function down()
    {
        Schema::dropIfExists('lease_modifications');
        Schema::dropIfExists('lease_payment_schedules');
        Schema::dropIfExists('leases');
        Schema::dropIfExists('liability_payment_schedules');
        Schema::dropIfExists('liability_schedules');
    }
};
