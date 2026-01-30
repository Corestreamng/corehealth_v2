<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add account_id to transaction tables
 *
 * Purpose: Allow users to select specific GL accounts for transactions
 * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Part 7.1.3
 *
 * This allows:
 * - Banks to be linked to their GL account (e.g., Bank A → 1020)
 * - Payments to specify which bank/cash account to use
 * - Expenses to specify payment account
 * - Payroll batches to specify payment account
 * - HMO remittances to specify receipt account
 * - PO payments to specify payment account
 */
class AddAccountIdToTransactionTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add GL account_id to banks table (for auto-linking)
        if (!Schema::hasColumn('banks', 'account_id')) {
            Schema::table('banks', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable()->after('is_active');
                $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
            });
        }

        // Add bank_id and account_id to hmo_remittances
        if (!Schema::hasColumn('hmo_remittances', 'bank_id')) {
            Schema::table('hmo_remittances', function (Blueprint $table) {
                $table->unsignedBigInteger('bank_id')->nullable()->after('payment_method');
                $table->unsignedBigInteger('account_id')->nullable()->after('bank_id');
                $table->unsignedBigInteger('journal_entry_id')->nullable()->after('account_id');

                $table->foreign('bank_id')->references('id')->on('banks')->nullOnDelete();
                $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
                $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            });
        }

        // Add payment_method, bank_id, account_id to payroll_batches (journal_entry_id may already exist)
        Schema::table('payroll_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_batches', 'payment_method')) {
                $table->string('payment_method')->default('bank_transfer')->after('total_net');
            }
        });
        Schema::table('payroll_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_batches', 'bank_id')) {
                $table->unsignedBigInteger('bank_id')->nullable()->after('payment_method');
                $table->foreign('bank_id')->references('id')->on('banks')->nullOnDelete();
            }
        });
        Schema::table('payroll_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('payroll_batches', 'account_id')) {
                $table->unsignedBigInteger('account_id')->nullable()->after('bank_id');
                $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
            }
        });
        // journal_entry_id may already exist from a previous migration

        // Add account_id to payments table (bank_id already exists)
        if (!Schema::hasColumn('payments', 'account_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable()->after('bank_id');
                $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
            });
        }

        // Add bank_id and account_id to expenses table
        if (!Schema::hasColumn('expenses', 'bank_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->unsignedBigInteger('bank_id')->nullable()->after('payment_method');
                $table->unsignedBigInteger('account_id')->nullable()->after('bank_id');

                $table->foreign('bank_id')->references('id')->on('banks')->nullOnDelete();
                $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
            });
        }

        // Add account_id to purchase_order_payments table
        if (!Schema::hasColumn('purchase_order_payments', 'account_id')) {
            Schema::table('purchase_order_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable()->after('bank_id');
                $table->foreign('account_id')->references('id')->on('accounts')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Remove from purchase_order_payments
        if (Schema::hasColumn('purchase_order_payments', 'account_id')) {
            Schema::table('purchase_order_payments', function (Blueprint $table) {
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            });
        }

        // Remove from expenses
        if (Schema::hasColumn('expenses', 'bank_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropForeign(['bank_id']);
                $table->dropForeign(['account_id']);
                $table->dropColumn(['bank_id', 'account_id']);
            });
        }

        // Remove from payments
        if (Schema::hasColumn('payments', 'account_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            });
        }

        // Remove from payroll_batches
        if (Schema::hasColumn('payroll_batches', 'bank_id')) {
            Schema::table('payroll_batches', function (Blueprint $table) {
                $table->dropForeign(['bank_id']);
                $table->dropForeign(['account_id']);
                $table->dropForeign(['journal_entry_id']);
                $table->dropColumn(['payment_method', 'bank_id', 'account_id', 'journal_entry_id']);
            });
        }

        // Remove from hmo_remittances
        if (Schema::hasColumn('hmo_remittances', 'bank_id')) {
            Schema::table('hmo_remittances', function (Blueprint $table) {
                $table->dropForeign(['bank_id']);
                $table->dropForeign(['account_id']);
                $table->dropForeign(['journal_entry_id']);
                $table->dropColumn(['bank_id', 'account_id', 'journal_entry_id']);
            });
        }

        // Remove from banks
        if (Schema::hasColumn('banks', 'account_id')) {
            Schema::table('banks', function (Blueprint $table) {
                $table->dropForeign(['account_id']);
                $table->dropColumn('account_id');
            });
        }
    }
}
