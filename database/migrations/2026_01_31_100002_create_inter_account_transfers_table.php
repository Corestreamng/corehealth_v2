<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Inter-Account Transfers Table
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.14
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 1.6
 *
 * Tracks transfers between bank accounts with:
 * - Clearance tracking (for inter-bank transfers)
 * - Approval workflow
 * - JE linkage for accounting
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inter_account_transfers', function (Blueprint $table) {
            $table->id();

            $table->string('transfer_number', 50)->unique();

            // Source and destination
            $table->foreignId('from_bank_id')->constrained('banks')->cascadeOnDelete();
            $table->foreignId('to_bank_id')->constrained('banks')->cascadeOnDelete();

            // GL Account links (for JE centricity validation)
            $table->foreignId('from_account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignId('to_account_id')->constrained('accounts')->cascadeOnDelete();

            // JE Link (CRITICAL)
            $table->foreignId('journal_entry_id')->nullable()
                ->constrained('journal_entries')->nullOnDelete();

            // Transfer details
            $table->date('transfer_date');
            $table->decimal('amount', 15, 2);
            $table->string('reference', 100)->nullable()->comment('Bank reference number');
            $table->text('description');

            // Transfer method
            $table->enum('transfer_method', [
                'internal',      // Same bank different accounts
                'wire',          // Wire transfer
                'eft',           // Electronic funds transfer
                'cheque',        // By cheque
                'rtgs',          // Real-time gross settlement
                'neft'           // National electronic funds transfer
            ])->default('internal');

            // Clearance tracking (for inter-bank)
            $table->boolean('is_same_bank')->default(false);
            $table->date('expected_clearance_date')->nullable();
            $table->date('actual_clearance_date')->nullable();
            $table->decimal('transfer_fee', 15, 2)->default(0);
            $table->foreignId('fee_account_id')->nullable()
                ->constrained('accounts')->nullOnDelete();

            // Approval workflow
            $table->enum('status', [
                'draft',
                'pending_approval',
                'approved',
                'initiated',     // Sent to bank
                'in_transit',    // For inter-bank
                'cleared',       // Funds arrived
                'failed',
                'cancelled'
            ])->default('draft');

            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('cleared_at')->nullable();

            // Failure/cancellation tracking
            $table->text('failure_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['from_bank_id', 'transfer_date']);
            $table->index(['to_bank_id', 'transfer_date']);
            $table->index(['status', 'transfer_date']);
            $table->index(['status', 'expected_clearance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inter_account_transfers');
    }
};
