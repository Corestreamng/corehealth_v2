<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create Patient Deposits Tables
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.9
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 3.2
 *
 * Two tables:
 * 1. patient_deposits - Deposit receipts (advance payments)
 * 2. patient_deposit_applications - How deposits are applied to bills
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Patient Deposits (Advance Payments)
        Schema::create('patient_deposits', function (Blueprint $table) {
            $table->id();

            // Patient and context
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->unsignedBigInteger('admission_id')->nullable();
            $table->foreignId('encounter_id')->nullable()
                ->constrained('encounters')->nullOnDelete();

            // Deposit details
            $table->string('deposit_number', 50)->unique();
            $table->date('deposit_date');
            $table->decimal('amount', 15, 2);
            $table->decimal('utilized_amount', 15, 2)->default(0);
            $table->decimal('refunded_amount', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->virtualAs('amount - utilized_amount - refunded_amount');

            // JE Link (CRITICAL)
            $table->foreignId('journal_entry_id')->nullable()
                ->constrained('journal_entries')->nullOnDelete();

            // Deposit type
            $table->enum('deposit_type', [
                'admission',      // Pre-admission deposit
                'procedure',      // Procedure deposit
                'surgery',        // Surgery deposit
                'investigation',  // Investigation deposit
                'general',        // General advance
                'other'
            ])->default('general');

            // Payment details
            $table->enum('payment_method', ['cash', 'pos', 'transfer', 'cheque'])
                ->default('cash');
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->string('payment_reference', 100)->nullable();

            // Receipt details
            $table->string('receipt_number', 50)->nullable();
            $table->foreignId('received_by')->constrained('users')->cascadeOnDelete();

            // Status
            $table->enum('status', [
                'active',         // Has balance
                'fully_applied',  // All used for bills
                'refunded',       // Money returned
                'expired',        // Forfeited after period
                'cancelled'
            ])->default('active');

            // Refund tracking
            $table->foreignId('refund_journal_entry_id')->nullable()
                ->constrained('journal_entries')->nullOnDelete();
            $table->text('refund_reason')->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['patient_id', 'status']);
            $table->index(['admission_id', 'status']);
            $table->index(['deposit_date', 'status']);
        });

        // 2. Patient Deposit Applications
        Schema::create('patient_deposit_applications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('deposit_id')
                ->constrained('patient_deposits')->cascadeOnDelete();

            // What was paid
            $table->foreignId('payment_id')->nullable()
                ->constrained('payments')->nullOnDelete();
            $table->foreignId('bill_id')->nullable()
                ->constrained('product_or_service_requests')->nullOnDelete();

            // Application details
            $table->string('application_number', 50)->unique();
            $table->date('application_date');
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();

            // JE Link
            $table->foreignId('journal_entry_id')->nullable()
                ->constrained('journal_entries')->nullOnDelete();

            // User who applied
            $table->foreignId('applied_by')->constrained('users')->cascadeOnDelete();

            $table->timestamps();

            $table->index(['deposit_id', 'application_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_deposit_applications');
        Schema::dropIfExists('patient_deposits');
    }
};
