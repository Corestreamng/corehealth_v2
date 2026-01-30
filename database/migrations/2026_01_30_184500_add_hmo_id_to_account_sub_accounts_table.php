<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add hmo_id to account_sub_accounts
 *
 * Adds the missing hmo_id column needed for HMO sub-accounts
 * used by HmoRemittanceObserver and ProductOrServiceRequestObserver.
 *
 * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Part 7.5.1
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('account_sub_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('account_sub_accounts', 'hmo_id')) {
                $table->foreignId('hmo_id')->nullable()->after('patient_id')
                    ->constrained('hmos')->nullOnDelete();
                $table->index('hmo_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_sub_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('account_sub_accounts', 'hmo_id')) {
                $table->dropForeign(['hmo_id']);
                $table->dropIndex(['hmo_id']);
                $table->dropColumn('hmo_id');
            }
        });
    }
};
