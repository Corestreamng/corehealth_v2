<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Bank Reconciliation Tables
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 2
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 2.1
 *
 * Two tables:
 * 1. bank_reconciliations - Header with statement and GL balances
 * 2. bank_reconciliation_items - Individual transactions to match
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Bank Reconciliations (Header)
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();

            // Bank and Account
            $table->foreignId('bank_id')->constrained('banks')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('fiscal_period_id')->nullable()
                ->constrained('accounting_periods')->nullOnDelete();

            // Reference
            $table->string('reconciliation_number', 50)->unique();

            // Statement details
            $table->date('statement_date');
            $table->date('statement_period_from');
            $table->date('statement_period_to');
            $table->decimal('statement_opening_balance', 15, 2);
            $table->decimal('statement_closing_balance', 15, 2);

            // GL (Book) balances - calculated from JE
            $table->decimal('gl_opening_balance', 15, 2);
            $table->decimal('gl_closing_balance', 15, 2);

            // Reconciliation items
            $table->decimal('outstanding_deposits', 15, 2)->default(0)
                ->comment('Deposits in GL not yet on statement');
            $table->decimal('outstanding_checks', 15, 2)->default(0)
                ->comment('Checks/withdrawals in GL not yet on statement');
            $table->decimal('deposits_in_transit', 15, 2)->default(0)
                ->comment('Deposits on statement not yet in GL');
            $table->decimal('unrecorded_charges', 15, 2)->default(0)
                ->comment('Bank charges not yet recorded in GL');
            $table->decimal('unrecorded_credits', 15, 2)->default(0)
                ->comment('Interest/credits not yet recorded in GL');
            $table->decimal('bank_errors', 15, 2)->default(0);
            $table->decimal('book_errors', 15, 2)->default(0);

            // Final variance (should be 0 when reconciled)
            $table->decimal('variance', 15, 2)->default(0);

            // Status and workflow
            $table->enum('status', [
                'draft',
                'in_progress',
                'pending_review',
                'approved',
                'finalized'
            ])->default('draft');

            // Adjusting entries created
            $table->json('adjustment_entry_ids')->nullable();

            $table->text('notes')->nullable();

            // Audit trail
            $table->foreignId('prepared_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('finalized_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['bank_id', 'statement_date']);
            $table->index(['status', 'statement_date']);
        });

        // 2. Bank Reconciliation Items
        Schema::create('bank_reconciliation_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reconciliation_id')
                ->constrained('bank_reconciliations')->cascadeOnDelete();

            // Link to JE line (if from GL)
            $table->foreignId('journal_entry_line_id')->nullable()
                ->constrained('journal_entry_lines')->nullOnDelete();

            // Item source
            $table->enum('source', ['gl', 'statement', 'adjustment'])
                ->comment('GL = from journal entries, Statement = from bank statement');

            // Item type
            $table->enum('item_type', [
                'deposit',
                'check',
                'transfer',
                'bank_charge',
                'interest',
                'other_credit',
                'other_debit',
                'adjustment'
            ]);

            // Transaction details
            $table->date('transaction_date');
            $table->string('reference', 100)->nullable()
                ->comment('Check number, transfer ref, etc.');
            $table->text('description');
            $table->decimal('amount', 15, 2);
            $table->enum('amount_type', ['debit', 'credit']);

            // Matching status
            $table->boolean('is_matched')->default(false);
            $table->foreignId('matched_with_id')->nullable()
                ->constrained('bank_reconciliation_items')->nullOnDelete();
            $table->date('matched_date')->nullable();

            // Reconciliation status
            $table->boolean('is_reconciled')->default(false);
            $table->date('cleared_date')->nullable()
                ->comment('Date item cleared the bank');

            // Outstanding item tracking
            $table->boolean('is_outstanding')->default(false)
                ->comment('True if item is not yet cleared');
            $table->date('expected_clear_date')->nullable();

            // For adjustments
            $table->foreignId('adjustment_entry_id')->nullable()
                ->constrained('journal_entries')->nullOnDelete();
            $table->text('adjustment_reason')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['reconciliation_id', 'source']);
            $table->index(['reconciliation_id', 'is_matched']);
            $table->index(['reconciliation_id', 'is_outstanding']);
            $table->index(['transaction_date', 'reference']);
        });

        // 3. Bank Statement Imports (optional - for statement parsing)
        Schema::create('bank_statement_imports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bank_id')->constrained('banks')->cascadeOnDelete();
            $table->foreignId('reconciliation_id')->nullable()
                ->constrained('bank_reconciliations')->nullOnDelete();

            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_format', 20)->comment('csv, ofx, pdf, etc.');

            $table->date('statement_date');
            $table->date('period_from');
            $table->date('period_to');
            $table->decimal('opening_balance', 15, 2);
            $table->decimal('closing_balance', 15, 2);

            $table->integer('total_transactions')->default(0);
            $table->integer('imported_transactions')->default(0);
            $table->integer('failed_transactions')->default(0);

            $table->enum('status', ['uploaded', 'parsing', 'parsed', 'imported', 'failed'])
                ->default('uploaded');
            $table->text('error_log')->nullable();

            $table->foreignId('imported_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('parsed_at')->nullable();
            $table->timestamp('imported_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_imports');
        Schema::dropIfExists('bank_reconciliation_items');
        Schema::dropIfExists('bank_reconciliations');
    }
};
