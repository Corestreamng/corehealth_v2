<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add journal_entry_id to liability_schedules table
 *
 * This allows tracking the initial booking JE when a liability is created.
 * Journal Entry: DEBIT Bank, CREDIT Liability Account
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('liability_schedules', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->after('interest_expense_account_id')
                ->constrained('journal_entries')
                ->nullOnDelete();

            $table->foreignId('bank_account_id')
                ->nullable()
                ->after('journal_entry_id')
                ->constrained('accounts')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('liability_schedules', function (Blueprint $table) {
            $table->dropForeign(['journal_entry_id']);
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn(['journal_entry_id', 'bank_account_id']);
        });
    }
};
