<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit Notes Table Migration
 *
 * Reference: Accounting System Plan §3.5 - Credit Note Tables
 *
 * Stores credit notes (refunds) for patient payments.
 * Credit notes go through an approval workflow before being processed.
 *
 * Refund Methods:
 * - cash: Refund given as cash
 * - bank: Refund transferred to patient's bank
 * - account_credit: Refund credited to patient's account balance
 *
 * When processed, a journal entry is created:
 * - DR: Income accounts (reversing the original revenue)
 * - CR: Bank/Cash or Patient Deposits (based on refund method)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('credit_note_number', 20)->unique();  // "CN202601-0001"
            $table->foreignId('patient_id')->constrained('patients');
            $table->foreignId('original_payment_id')->constrained('payments');
            $table->decimal('amount', 15, 2);
            $table->text('reason');
            $table->enum('refund_method', ['cash', 'bank', 'account_credit']);
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();

            $table->enum('status', ['draft', 'pending_approval', 'approved', 'processed', 'void'])->default('draft');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users');
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users');
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('patient_id');
            $table->index('original_payment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
