<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts Table Migration
 *
 * Reference: Accounting System Plan §3.2 - Chart of Accounts Tables
 *
 * Stores individual GL accounts within groups, e.g.:
 * - Cash on Hand, Petty Cash, Zenith Bank (under Current Assets)
 * - Accounts Receivable, Inventory (under Current Assets)
 * - Accounts Payable, Patient Deposits (under Current Liabilities)
 * - Salary Expense, Utilities Expense (under Operating Expenses)
 *
 * System accounts are protected from deletion and represent core accounts.
 * Bank accounts link to the existing banks table for reconciliation.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_group_id')->constrained('account_groups')->cascadeOnDelete();
            $table->string('code', 20)->unique();               // "1000", "1001", "1100"
            $table->string('name', 100);                        // "Cash on Hand", "Zenith Bank"
            $table->text('description')->nullable();
            $table->foreignId('bank_id')->nullable()->constrained('banks')->nullOnDelete();
            $table->boolean('is_system')->default(false);       // Protected from deletion
            $table->boolean('is_active')->default(true);
            $table->boolean('is_bank_account')->default(false);
            $table->enum('cash_flow_category_override', ['operating', 'investing', 'financing'])->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
            $table->index('is_system');
            $table->index('bank_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
