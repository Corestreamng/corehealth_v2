<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add bank_id and payment_method to fixed_asset_disposals table.
 *
 * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md
 *
 * This enables tracking whether disposal proceeds were received
 * via cash or through a specific bank account, ensuring correct
 * GL account selection (Cash in Hand 1010 vs specific bank account).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_asset_disposals', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->after('status')
                  ->comment('cash or bank_transfer');
            $table->unsignedBigInteger('bank_id')->nullable()->after('payment_method');

            $table->foreign('bank_id')
                  ->references('id')
                  ->on('banks')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fixed_asset_disposals', function (Blueprint $table) {
            $table->dropForeign(['bank_id']);
            $table->dropColumn(['payment_method', 'bank_id']);
        });
    }
};
