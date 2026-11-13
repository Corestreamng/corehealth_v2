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
        Schema::table('stock_batches', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_batches', 'acquisition_type')) {
                $table->string('acquisition_type', 50)->default('credit')->after('source');
                $table->index('acquisition_type');
            }
            if (!Schema::hasColumn('stock_batches', 'payment_account_code')) {
                $table->string('payment_account_code', 20)->nullable()->after('acquisition_type');
            }
            if (!Schema::hasColumn('stock_batches', 'journal_entry_id')) {
                $table->unsignedBigInteger('journal_entry_id')->nullable()->after('payment_account_code');
                $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('set null');
            }
            if (!Schema::hasColumn('stock_batches', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('is_active');
                $table->index('voided_at', 'idx_stock_batches_voided');
            }
            if (!Schema::hasColumn('stock_batches', 'voided_by')) {
                $table->unsignedBigInteger('voided_by')->nullable()->after('voided_at');
                $table->foreign('voided_by')->references('id')->on('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('stock_batches', 'void_reason')) {
                $table->text('void_reason')->nullable()->after('voided_by');
            }
            if (!Schema::hasColumn('stock_batches', 'void_reversal_je_id')) {
                $table->unsignedBigInteger('void_reversal_je_id')->nullable()->after('void_reason');
                $table->foreign('void_reversal_je_id')->references('id')->on('journal_entries')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_batches', function (Blueprint $table) {
            if (Schema::hasColumn('stock_batches', 'void_reversal_je_id')) {
                $table->dropForeign(['void_reversal_je_id']);
                $table->dropColumn('void_reversal_je_id');
            }
            if (Schema::hasColumn('stock_batches', 'void_reason')) {
                $table->dropColumn('void_reason');
            }
            if (Schema::hasColumn('stock_batches', 'voided_by')) {
                $table->dropForeign(['voided_by']);
                $table->dropColumn('voided_by');
            }
            if (Schema::hasColumn('stock_batches', 'voided_at')) {
                $table->dropIndex('idx_stock_batches_voided');
                $table->dropColumn('voided_at');
            }
            if (Schema::hasColumn('stock_batches', 'journal_entry_id')) {
                $table->dropForeign(['journal_entry_id']);
                $table->dropColumn('journal_entry_id');
            }
            if (Schema::hasColumn('stock_batches', 'payment_account_code')) {
                $table->dropColumn('payment_account_code');
            }
            if (Schema::hasColumn('stock_batches', 'acquisition_type')) {
                $table->dropIndex(['acquisition_type']);
                $table->dropColumn('acquisition_type');
            }
        });
    }
};
