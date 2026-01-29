<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounting Periods Table Migration
 *
 * Reference: Accounting System Plan §3.1 - Fiscal Period Tables
 *
 * Stores accounting periods (typically months) within fiscal years.
 * Each period can be opened, closed, or in closing state.
 * Only one period should be open at a time for normal operations.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->tinyInteger('period_number');               // 1-12 for monthly, 13 for adjustment period
            $table->string('period_name', 50);                  // "January 2026"
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['open', 'closing', 'closed'])->default('open');
            $table->boolean('is_adjustment_period')->default(false);  // For year-end adjustments
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['fiscal_year_id', 'period_number']);
            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};
