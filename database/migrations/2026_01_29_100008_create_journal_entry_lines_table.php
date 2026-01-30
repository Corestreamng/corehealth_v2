<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal Entry Lines Table Migration
 *
 * Reference: Accounting System Plan §3.3 - Journal Entry Tables
 *
 * Stores individual debit/credit lines for each journal entry.
 * Each line affects one account (and optionally one sub-account).
 *
 * Key Rules:
 * - Either debit OR credit should have a value, not both
 * - Sum of debits must equal sum of credits for a balanced entry
 * - All account balances are calculated from posted journal entry lines
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->smallInteger('line_number');
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('sub_account_id')->nullable()->constrained('account_sub_accounts')->nullOnDelete();
            $table->decimal('debit', 15, 2)->default(0.00);
            $table->decimal('credit', 15, 2)->default(0.00);
            $table->string('narration', 255)->nullable();
            $table->enum('cash_flow_category', ['operating', 'investing', 'financing'])->nullable();
            $table->timestamps();

            $table->index('account_id');
            $table->index('sub_account_id');
            $table->index(['journal_entry_id', 'line_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};
