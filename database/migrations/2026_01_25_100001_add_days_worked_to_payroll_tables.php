<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add days worked columns for pro-rata salary calculation
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add to payroll_batches - the working period selected
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->date('work_period_start')->nullable()->after('pay_period_end');
            $table->date('work_period_end')->nullable()->after('work_period_start');
            $table->integer('days_in_month')->nullable()->after('work_period_end');
            $table->integer('days_worked')->nullable()->after('days_in_month');
        });

        // Add to payroll_items - individual pro-rata info
        Schema::table('payroll_items', function (Blueprint $table) {
            $table->integer('days_in_month')->nullable()->after('salary_profile_id');
            $table->integer('days_worked')->nullable()->after('days_in_month');
            $table->decimal('full_gross_salary', 15, 2)->nullable()->after('basic_salary');
            $table->decimal('full_net_salary', 15, 2)->nullable()->after('net_salary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_batches', function (Blueprint $table) {
            $table->dropColumn(['work_period_start', 'work_period_end', 'days_in_month', 'days_worked']);
        });

        Schema::table('payroll_items', function (Blueprint $table) {
            $table->dropColumn(['days_in_month', 'days_worked', 'full_gross_salary', 'full_net_salary']);
        });
    }
};
