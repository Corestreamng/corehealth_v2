<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal Entry Edits Table Migration
 *
 * Reference: Accounting System Plan §3.4 - Edit Request Table
 *
 * Stores edit requests for posted journal entries.
 * When a user wants to edit a posted entry, an edit request is created
 * that must be approved before the changes are applied.
 *
 * This maintains the audit trail and ensures proper oversight of changes
 * to financial records.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('journal_entry_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->json('original_data');                      // Snapshot before edit
            $table->json('edited_data');                        // Proposed changes
            $table->text('edit_reason');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->foreignId('requested_by')->constrained('users');
            $table->timestamp('requested_at');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users');
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index('status');
            $table->index('journal_entry_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entry_edits');
    }
};
