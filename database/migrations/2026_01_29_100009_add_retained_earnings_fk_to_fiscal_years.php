<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add FK from fiscal_years to journal_entries for retained earnings entry
 *
 * Reference: Accounting System Plan §3.1 - Fiscal Period Tables
 *
 * This migration adds the foreign key that couldn't be added in the initial
 * fiscal_years migration because journal_entries didn't exist yet.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->foreign('retained_earnings_entry_id')
                ->references('id')
                ->on('journal_entries')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->dropForeign(['retained_earnings_entry_id']);
        });
    }
};
