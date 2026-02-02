<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add bank_id to petty_cash_transactions for replenishment tracking.
 *
 * Per IAS 7 (Cash Flow Statement) & internal controls:
 * - Replenishment draws from a specific bank account
 * - Audit trail of which bank account was debited
 * - Support for multi-bank organizations
 *
 * Also adds payment_method for flexibility (cash/bank).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            // Payment method: 'cash' or 'bank_transfer' (for replenishment source)
            if (!Schema::hasColumn('petty_cash_transactions', 'payment_method')) {
                $table->string('payment_method', 20)->nullable()->after('payee_type')
                    ->comment('Source: cash or bank_transfer (for replenishment)');
            }

            // Bank FK (for bank replenishment)
            if (!Schema::hasColumn('petty_cash_transactions', 'bank_id')) {
                $table->foreignId('bank_id')->nullable()->after('payment_method')
                    ->constrained('banks')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('petty_cash_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('petty_cash_transactions', 'bank_id')) {
                $table->dropForeign(['bank_id']);
                $table->dropColumn('bank_id');
            }
            if (Schema::hasColumn('petty_cash_transactions', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
        });
    }
};
