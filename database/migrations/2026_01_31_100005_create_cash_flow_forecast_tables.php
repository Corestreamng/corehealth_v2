<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash Flow Forecast Tables Migration
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.8
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 3.1
 *
 * Creates tables for cash flow forecasting and 13-week projections.
 */
return new class extends Migration
{
    public function up()
    {
        // Main cash flow forecasts table
        Schema::create('cash_flow_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years');
            $table->string('forecast_name');
            $table->enum('forecast_type', [
                'weekly',       // 13-week rolling
                'monthly',      // Monthly projection
                'quarterly',    // Quarterly projection
                'annual',       // Annual budget
            ]);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('scenario', [
                'base',         // Base case
                'optimistic',   // Best case
                'pessimistic',  // Worst case
            ])->default('base');
            $table->enum('status', [
                'draft',
                'active',
                'archived',
            ])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fiscal_year_id', 'status']);
            $table->index(['forecast_type', 'status']);
        });

        // Cash flow forecast periods (13-week breakdown)
        Schema::create('cash_flow_forecast_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_id')->constrained('cash_flow_forecasts')->onDelete('cascade');
            $table->integer('period_number');           // Week 1, 2, 3... or Month 1, 2, 3...
            $table->date('period_start_date');
            $table->date('period_end_date');

            // Opening and closing balances
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('closing_balance', 15, 2)->default(0);

            // Operating activities
            $table->decimal('patient_revenue_cash', 15, 2)->default(0);
            $table->decimal('patient_revenue_hmo', 15, 2)->default(0);
            $table->decimal('other_operating_receipts', 15, 2)->default(0);
            $table->decimal('operating_expenses', 15, 2)->default(0);
            $table->decimal('salary_wages', 15, 2)->default(0);
            $table->decimal('supplier_payments', 15, 2)->default(0);
            $table->decimal('net_operating_cash_flow', 15, 2)->default(0);

            // Investing activities
            $table->decimal('capex_payments', 15, 2)->default(0);
            $table->decimal('asset_disposals', 15, 2)->default(0);
            $table->decimal('net_investing_cash_flow', 15, 2)->default(0);

            // Financing activities
            $table->decimal('loan_receipts', 15, 2)->default(0);
            $table->decimal('loan_repayments', 15, 2)->default(0);
            $table->decimal('capital_contributions', 15, 2)->default(0);
            $table->decimal('dividends_drawings', 15, 2)->default(0);
            $table->decimal('net_financing_cash_flow', 15, 2)->default(0);

            // Net change and actual vs forecast
            $table->decimal('net_cash_flow', 15, 2)->default(0);
            $table->decimal('actual_closing_balance', 15, 2)->nullable();
            $table->decimal('variance', 15, 2)->nullable();
            $table->text('variance_explanation')->nullable();

            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->unique(['forecast_id', 'period_number']);
            $table->index('period_start_date');
        });

        // Cash flow forecast line items (detailed breakdown)
        Schema::create('cash_flow_forecast_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_period_id')->constrained('cash_flow_forecast_periods')->onDelete('cascade');
            $table->foreignId('account_id')->nullable()->constrained('accounts');
            $table->enum('cash_flow_category', [
                'operating_inflow',
                'operating_outflow',
                'investing_inflow',
                'investing_outflow',
                'financing_inflow',
                'financing_outflow',
            ]);
            $table->string('item_description');
            $table->decimal('forecasted_amount', 15, 2)->default(0);
            $table->decimal('actual_amount', 15, 2)->nullable();
            $table->enum('source_type', [
                'manual',           // Manually entered
                'recurring',        // Based on recurring pattern
                'scheduled',        // Based on scheduled payments
                'historical',       // Based on historical average
                'commitment',       // Based on PO/contracts
            ])->default('manual');
            $table->string('source_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['forecast_period_id', 'cash_flow_category'], 'cf_items_period_cat_idx');
        });

        // Recurring cash flow patterns (for auto-forecasting)
        Schema::create('cash_flow_recurring_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('pattern_name');
            $table->foreignId('account_id')->nullable()->constrained('accounts');
            $table->enum('cash_flow_category', [
                'operating_inflow',
                'operating_outflow',
                'investing_inflow',
                'investing_outflow',
                'financing_inflow',
                'financing_outflow',
            ]);
            $table->enum('frequency', [
                'weekly',
                'bi_weekly',
                'monthly',
                'quarterly',
                'annually',
            ]);
            $table->integer('day_of_period')->nullable();  // Day of week/month
            $table->decimal('expected_amount', 15, 2);
            $table->decimal('variance_percentage', 5, 2)->default(10);  // Expected variance
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'frequency']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('cash_flow_recurring_patterns');
        Schema::dropIfExists('cash_flow_forecast_items');
        Schema::dropIfExists('cash_flow_forecast_periods');
        Schema::dropIfExists('cash_flow_forecasts');
    }
};
