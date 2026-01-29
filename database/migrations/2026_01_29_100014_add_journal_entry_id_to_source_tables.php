<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Journal Entry ID to Source Tables Migration
 *
 * Reference: Accounting System Plan §3.7 - Modifications to Existing Tables
 *
 * Adds journal_entry_id FK to source transaction tables for bidirectional
 * traceability between financial transactions and their journal entries.
 *
 * This creates the reverse link: while journal entries know their source
 * via reference_type/reference_id, source records now know their journal entry.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add to payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->after('hmo_id')
                ->constrained('journal_entries')
                ->nullOnDelete();
        });

        // Add to purchase_orders table
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->after('approved_at')
                ->constrained('journal_entries')
                ->nullOnDelete();
        });

        // Add to purchase_order_payments table
        Schema::table('purchase_order_payments', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->after('expense_id')
                ->constrained('journal_entries')
                ->nullOnDelete();
        });

        // Add to payroll_batches table
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->after('expense_id')
                ->constrained('journal_entries')
                ->nullOnDelete();
        });

        // Add to expenses table
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('journal_entry_id')
                ->nullable()
                ->after('bank_id')
                ->constrained('journal_entries')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['journal_entry_id']);
            $table->dropColumn('journal_entry_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['journal_entry_id']);
            $table->dropColumn('journal_entry_id');
        });

        Schema::table('purchase_order_payments', function (Blueprint $table) {
            $table->dropForeign(['journal_entry_id']);
            $table->dropColumn('journal_entry_id');
        });

        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->dropForeign(['journal_entry_id']);
            $table->dropColumn('journal_entry_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['journal_entry_id']);
            $table->dropColumn('journal_entry_id');
        });
    }
};
