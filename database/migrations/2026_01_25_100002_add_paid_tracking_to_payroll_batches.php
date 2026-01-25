<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add payment tracking columns to payroll_batches
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->unsignedBigInteger('paid_by')->nullable()->after('rejection_reason');
            $table->timestamp('paid_at')->nullable()->after('paid_by');
            $table->text('payment_comments')->nullable()->after('paid_at');

            $table->foreign('paid_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->dropForeign(['paid_by']);
            $table->dropColumn(['paid_by', 'paid_at', 'payment_comments']);
        });
    }
};
