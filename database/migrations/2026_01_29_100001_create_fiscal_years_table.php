<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiscal Years Table Migration
 *
 * Reference: Accounting System Plan §3.1 - Fiscal Period Tables
 *
 * Stores fiscal year definitions for the accounting system.
 * Each fiscal year contains multiple accounting periods (typically 12 months).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->id();
            $table->string('year_name', 50);                    // "FY 2026"
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['open', 'closing', 'closed'])->default('open');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('retained_earnings_entry_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });

        // Add foreign key for retained_earnings_entry_id after journal_entries table exists
        // This will be added in a later migration
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fiscal_years');
    }
};
