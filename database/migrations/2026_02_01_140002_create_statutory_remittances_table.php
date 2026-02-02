<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Create statutory_remittances table
 *
 * Reference: Accounting Gap Analysis - Statutory Remittance Module
 *
 * This table tracks payments made to statutory/regulatory bodies for payroll deductions.
 * Examples: PAYE to tax authority, Pension to PFA, NHF to FMBN, etc.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('statutory_remittances', function (Blueprint $table) {
            $table->id();

            // Link to pay head (deduction type)
            $table->foreignId('pay_head_id')->constrained('pay_heads')->onDelete('restrict');

            // Reference & Period
            $table->string('reference_number', 30)->unique();
            $table->date('period_from')->comment('Payroll period start');
            $table->date('period_to')->comment('Payroll period end');
            $table->date('due_date')->nullable()->comment('When remittance is due');
            $table->date('remittance_date')->nullable()->comment('Actual payment date');

            // Amount
            $table->decimal('amount', 15, 2);

            // Payee (statutory body) details
            $table->string('payee_name')->comment('Statutory body name');
            $table->string('payee_account_number')->nullable()->comment('Their bank account');
            $table->string('payee_bank_name')->nullable()->comment('Their bank name');

            // Payment details
            $table->enum('payment_method', ['bank_transfer', 'cheque', 'cash'])->nullable();
            $table->foreignId('bank_id')->nullable()->constrained('banks')->onDelete('set null');
            $table->string('cheque_number', 50)->nullable();
            $table->string('transaction_reference', 100)->nullable();

            // Notes
            $table->text('notes')->nullable();

            // Status
            $table->enum('status', ['draft', 'pending', 'approved', 'paid', 'voided'])->default('draft');

            // Accounting link
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->onDelete('set null');

            // Workflow tracking
            $table->foreignId('prepared_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['pay_head_id', 'period_from', 'period_to']);
            $table->index(['status', 'due_date']);
            $table->index('period_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statutory_remittances');
    }
};
