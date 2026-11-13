<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_batch_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_batch_transactions', 'is_voided')) {
                $table->boolean('is_voided')->default(false)->after('balance_after');
                $table->index('is_voided', 'idx_sbt_is_voided');
            }
            if (!Schema::hasColumn('stock_batch_transactions', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('is_voided');
                $table->index('voided_at', 'idx_sbt_voided_at');
            }
            if (!Schema::hasColumn('stock_batch_transactions', 'voided_by')) {
                $table->unsignedBigInteger('voided_by')->nullable()->after('voided_at');
                $table->foreign('voided_by')->references('id')->on('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('stock_batch_transactions', 'void_reason')) {
                $table->text('void_reason')->nullable()->after('voided_by');
            }
            if (!Schema::hasColumn('stock_batch_transactions', 'journal_entry_id')) {
                $table->unsignedBigInteger('journal_entry_id')->nullable()->after('void_reason');
                $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            }
            if (!Schema::hasColumn('stock_batch_transactions', 'void_reversal_je_id')) {
                $table->unsignedBigInteger('void_reversal_je_id')->nullable()->after('journal_entry_id');
                $table->foreign('void_reversal_je_id')->references('id')->on('journal_entries')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_batch_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('stock_batch_transactions', 'void_reversal_je_id')) {
                $table->dropForeign(['void_reversal_je_id']);
                $table->dropColumn('void_reversal_je_id');
            }
            if (Schema::hasColumn('stock_batch_transactions', 'journal_entry_id')) {
                $table->dropForeign(['journal_entry_id']);
                $table->dropColumn('journal_entry_id');
            }
            if (Schema::hasColumn('stock_batch_transactions', 'void_reason')) {
                $table->dropColumn('void_reason');
            }
            if (Schema::hasColumn('stock_batch_transactions', 'voided_by')) {
                $table->dropForeign(['voided_by']);
                $table->dropColumn('voided_by');
            }
            if (Schema::hasColumn('stock_batch_transactions', 'voided_at')) {
                $table->dropIndex('idx_sbt_voided_at');
                $table->dropColumn('voided_at');
            }
            if (Schema::hasColumn('stock_batch_transactions', 'is_voided')) {
                $table->dropIndex('idx_sbt_is_voided');
                $table->dropColumn('is_voided');
            }
        });
    }
};
