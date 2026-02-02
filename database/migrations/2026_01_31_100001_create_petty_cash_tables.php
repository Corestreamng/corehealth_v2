<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Petty Cash Tables
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.7
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 1.4
 *
 * Three tables:
 * 1. petty_cash_funds - Fund definitions with limits and custodians
 * 2. petty_cash_transactions - Individual disbursements and replenishments
 * 3. petty_cash_reconciliations - Periodic cash counts and variance tracking
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Petty Cash Funds
        Schema::create('petty_cash_funds', function (Blueprint $table) {
            $table->id();

            $table->string('fund_name');
            $table->string('fund_code', 20)->unique();

            // Link to GL Account (for JE centricity)
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();

            // Custodian responsibility
            $table->foreignId('custodian_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();

            // Limits
            $table->decimal('fund_limit', 15, 2)->comment('Maximum fund balance');
            $table->decimal('transaction_limit', 15, 2)->comment('Max per transaction');
            $table->decimal('current_balance', 15, 2)->default(0)->comment('Cached balance, computed from JE');

            // Approval settings
            $table->boolean('requires_approval')->default(true);
            $table->decimal('approval_threshold', 15, 2)->default(0)->comment('Amount above which needs approval');

            // Status
            $table->enum('status', ['active', 'suspended', 'closed'])->default('active');

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'department_id']);
        });

        // 2. Petty Cash Transactions
        Schema::create('petty_cash_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fund_id')->constrained('petty_cash_funds')->cascadeOnDelete();

            // JE Link (CRITICAL - JE centricity)
            $table->foreignId('journal_entry_id')->nullable()
                ->constrained('journal_entries')->nullOnDelete();

            // Transaction details
            $table->enum('transaction_type', ['disbursement', 'replenishment', 'adjustment'])
                ->default('disbursement');
            $table->date('transaction_date');
            $table->string('voucher_number', 50)->unique();

            $table->text('description');
            $table->decimal('amount', 15, 2);

            // Expense category (for analysis)
            $table->string('expense_category', 50)->nullable();
            $table->foreignId('expense_account_id')->nullable()
                ->constrained('accounts')->nullOnDelete();

            // Request/Approval workflow
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Receipt tracking
            $table->string('receipt_number', 50)->nullable();
            $table->boolean('receipt_attached')->default(false);
            $table->string('receipt_path')->nullable();

            // Payee information
            $table->string('payee_name')->nullable();
            $table->string('payee_type', 20)->nullable()->comment('staff, vendor, other');

            // Status workflow
            $table->enum('status', ['pending', 'approved', 'disbursed', 'rejected', 'voided'])
                ->default('pending');
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['fund_id', 'transaction_date']);
            $table->index(['status', 'transaction_type']);
            $table->index(['requested_by', 'status']);
        });

        // 3. Petty Cash Reconciliations
        Schema::create('petty_cash_reconciliations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fund_id')->constrained('petty_cash_funds')->cascadeOnDelete();

            $table->date('reconciliation_date');
            $table->string('reconciliation_number', 50)->unique();

            // Balance comparison
            $table->decimal('expected_balance', 15, 2)->comment('Balance per JE/system');
            $table->decimal('actual_cash_count', 15, 2)->comment('Physical count');
            $table->decimal('variance', 15, 2)->comment('expected - actual');

            // Breakdown of physical count (optional detail)
            $table->json('denomination_breakdown')->nullable()->comment('Count by denomination');

            // Outstanding vouchers (receipts not yet replenished)
            $table->decimal('outstanding_vouchers', 15, 2)->default(0);
            $table->json('outstanding_voucher_ids')->nullable();

            // Reconciliation result
            $table->enum('status', ['balanced', 'shortage', 'overage', 'pending'])
                ->default('pending');

            // Adjustment JE if variance exists
            $table->foreignId('adjustment_entry_id')->nullable()
                ->constrained('journal_entries')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->foreignId('reconciled_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['fund_id', 'reconciliation_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_reconciliations');
        Schema::dropIfExists('petty_cash_transactions');
        Schema::dropIfExists('petty_cash_funds');
    }
};
