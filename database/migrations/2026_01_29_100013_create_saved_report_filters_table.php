<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved Report Filters Table Migration
 *
 * Reference: Accounting System Plan §3.6 - Saved Report Filters Table
 *
 * Allows users to save frequently-used filter combinations for reports.
 * Filters can be personal or shared with the team.
 * Each report type can have one default filter per user.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('saved_report_filters', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('report_type', [
                'trial_balance',
                'profit_loss',
                'balance_sheet',
                'general_ledger',
                'accounts_payable',
                'accounts_receivable',
                'cash_flow',
                'daily_audit'
            ]);
            $table->json('filters');                            // All filter parameters
            $table->boolean('is_default')->default(false);
            $table->boolean('is_shared')->default(false);       // Visible to all users
            $table->text('description')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index('report_type');
            $table->index('created_by');
            $table->index(['report_type', 'created_by', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_report_filters');
    }
};
