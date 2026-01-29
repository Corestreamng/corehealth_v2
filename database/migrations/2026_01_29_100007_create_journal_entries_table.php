<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal Entries Table Migration
 *
 * Reference: Accounting System Plan §3.3 - Journal Entry Tables
 *
 * The journal entry is the foundation of the entire accounting system.
 * All financial reports are derived from journal entries.
 *
 * Entry Types:
 * - auto: Generated automatically by observers (payments, POs, payroll)
 * - manual: Created manually by accounting staff
 * - opening: Opening balance entries
 * - closing: Period/year-end closing entries
 * - reversal: Entries that reverse other entries
 *
 * Status Flow:
 * - draft: Initial state for manual entries
 * - pending_approval: Submitted for approval
 * - approved: Approved, ready to post
 * - posted: Posted to ledger (affects account balances)
 * - reversed: Has been reversed by another entry
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number', 20)->unique();       // "JE202601-0001"
            $table->foreignId('accounting_period_id')->constrained('accounting_periods');
            $table->date('entry_date');
            $table->text('description');

            // Polymorphic reference to source transaction
            $table->string('reference_type', 100)->nullable();  // "App\Models\Payment"
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->enum('entry_type', ['auto', 'manual', 'opening', 'closing', 'reversal']);
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'posted', 'reversed'])->default('draft');

            // Reversal tracking
            $table->unsignedBigInteger('reversal_of_id')->nullable();  // If this is a reversal, points to original
            $table->unsignedBigInteger('reversed_by_id')->nullable();  // If reversed, points to reversal entry

            // Workflow tracking
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestamp('posted_at')->nullable();

            $table->boolean('edit_requires_approval')->default(true);

            $table->timestamps();
            $table->softDeletes();

            // Self-referential foreign keys for reversal tracking
            $table->foreign('reversal_of_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('reversed_by_id')->references('id')->on('journal_entries')->nullOnDelete();

            $table->index('entry_date');
            $table->index('status');
            $table->index('entry_type');
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
