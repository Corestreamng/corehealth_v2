<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CAPEX Projects, Budget, and KPI Tables Migration
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Sections 6.10, 9, 6.15
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phases 8-10
 *
 * Creates tables for:
 * - Capital Expenditure Projects
 * - Budget Management
 * - Financial KPI Dashboard
 */
return new class extends Migration
{
    public function up()
    {
        // ==========================================
        // CAPEX PROJECTS
        // ==========================================

        Schema::create('capex_projects', function (Blueprint $table) {
            $table->id();
            $table->string('project_code')->unique();
            $table->string('project_name');
            $table->text('description')->nullable();
            $table->enum('project_type', [
                'equipment',
                'building',
                'renovation',
                'technology',
                'vehicle',
                'furniture',
                'other',
            ]);
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->foreignId('fixed_asset_category_id')->nullable()->constrained('fixed_asset_categories');

            // Financial
            $table->decimal('estimated_cost', 15, 2);
            $table->decimal('approved_budget', 15, 2)->nullable();
            $table->decimal('actual_cost', 15, 2)->default(0);
            $table->decimal('committed_cost', 15, 2)->default(0);  // POs issued
            $table->decimal('remaining_budget', 15, 2)->default(0);

            // Timeline
            $table->date('proposed_date');
            $table->date('approved_date')->nullable();
            $table->date('expected_start_date')->nullable();
            $table->date('expected_completion_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_completion_date')->nullable();

            // Approval
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->text('justification')->nullable();
            $table->text('rejection_reason')->nullable();

            // Status
            $table->enum('status', [
                'draft',
                'pending_approval',
                'approved',
                'in_progress',
                'completed',
                'cancelled',
                'on_hold',
            ])->default('draft');
            $table->integer('completion_percentage')->default(0);

            // ROI
            $table->text('expected_benefits')->nullable();
            $table->decimal('expected_annual_savings', 15, 2)->nullable();
            $table->integer('expected_payback_months')->nullable();
            $table->decimal('expected_roi_percentage', 8, 2)->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'status']);
            $table->index(['project_type', 'status']);
        });

        // CAPEX Project Expenses
        Schema::create('capex_project_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('capex_projects')->onDelete('cascade');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders');
            $table->foreignId('expense_id')->nullable()->constrained('expenses');
            $table->date('expense_date');
            $table->string('description');
            $table->string('vendor')->nullable();
            $table->string('invoice_number')->nullable();
            $table->decimal('amount', 15, 2);
            $table->enum('status', ['pending', 'approved', 'paid', 'rejected'])->default('pending');
            $table->timestamps();

            $table->index(['project_id', 'expense_date']);
        });

        // ==========================================
        // BUDGET MANAGEMENT
        // ==========================================

        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('budget_name');
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years');
            $table->integer('year');
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers');
            $table->enum('budget_type', [
                'operating',    // OpEx
                'capital',      // CapEx
                'revenue',      // Revenue projections
            ])->default('operating');
            $table->decimal('total_budgeted', 15, 2)->default(0);
            $table->decimal('total_actual', 15, 2)->default(0);
            $table->decimal('total_variance', 15, 2)->default(0);
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'locked'])->default('draft');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['year', 'department_id', 'budget_type']);
        });

        // Budget Line Items
        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->onDelete('cascade');
            $table->foreignId('account_id')->constrained('accounts');
            $table->enum('period_type', ['annual', 'monthly', 'quarterly'])->default('monthly');
            $table->integer('period_number')->nullable();  // 1-12 for monthly, 1-4 for quarterly

            // Amounts
            $table->decimal('budgeted_amount', 15, 2)->default(0);
            $table->decimal('actual_amount', 15, 2)->default(0);
            $table->decimal('variance', 15, 2)->default(0);
            $table->decimal('variance_percentage', 8, 2)->default(0);

            // Forecasting
            $table->decimal('forecast_amount', 15, 2)->nullable();
            $table->decimal('prior_year_actual', 15, 2)->nullable();
            $table->text('assumptions')->nullable();

            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->unique(['budget_id', 'account_id', 'period_type', 'period_number'], 'budget_line_unique');
            $table->index(['budget_id', 'account_id']);
        });

        // Budget Revisions
        Schema::create('budget_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets');
            $table->foreignId('budget_line_id')->constrained('budget_lines');
            $table->integer('revision_number');
            $table->decimal('previous_amount', 15, 2);
            $table->decimal('new_amount', 15, 2);
            $table->decimal('change_amount', 15, 2);
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();

            $table->index(['budget_id', 'status']);
        });

        // ==========================================
        // KPI DASHBOARD
        // ==========================================

        Schema::create('financial_kpis', function (Blueprint $table) {
            $table->id();
            $table->string('kpi_code', 30)->unique();
            $table->string('kpi_name');
            $table->string('category');  // liquidity, profitability, efficiency, solvency
            $table->text('description')->nullable();
            $table->text('calculation_formula');  // JSON formula definition
            $table->string('unit', 20);  // percentage, ratio, currency, days
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'quarterly', 'annually'])->default('monthly');

            // Thresholds
            $table->decimal('target_value', 15, 4)->nullable();
            $table->decimal('warning_threshold_low', 15, 4)->nullable();
            $table->decimal('warning_threshold_high', 15, 4)->nullable();
            $table->decimal('critical_threshold_low', 15, 4)->nullable();
            $table->decimal('critical_threshold_high', 15, 4)->nullable();

            // Display
            $table->integer('display_order')->default(0);
            $table->boolean('show_on_dashboard')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('chart_type')->nullable();  // line, bar, gauge

            $table->timestamps();

            $table->index(['category', 'is_active']);
        });

        // KPI Values (historical)
        Schema::create('financial_kpi_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_id')->constrained('financial_kpis')->onDelete('cascade');
            $table->date('calculation_date');
            $table->integer('year');
            $table->integer('month')->nullable();
            $table->integer('week')->nullable();
            $table->decimal('value', 15, 4);
            $table->decimal('previous_value', 15, 4)->nullable();
            $table->decimal('change_amount', 15, 4)->nullable();
            $table->decimal('change_percentage', 8, 2)->nullable();
            $table->enum('status', ['normal', 'warning', 'critical'])->default('normal');
            $table->json('calculation_details')->nullable();  // Component values
            $table->timestamps();

            $table->unique(['kpi_id', 'year', 'month', 'week'], 'kpi_value_unique');
            $table->index('calculation_date');
        });

        // KPI Alerts
        Schema::create('financial_kpi_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kpi_id')->constrained('financial_kpis');
            $table->foreignId('kpi_value_id')->constrained('financial_kpi_values');
            $table->enum('alert_type', ['warning', 'critical']);
            $table->enum('direction', ['above', 'below']);
            $table->decimal('threshold_value', 15, 4);
            $table->decimal('actual_value', 15, 4);
            $table->text('message');
            $table->boolean('is_acknowledged')->default(false);
            $table->foreignId('acknowledged_by')->nullable()->constrained('users');
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('acknowledgement_notes')->nullable();
            $table->timestamps();

            $table->index(['kpi_id', 'is_acknowledged']);
            $table->index('created_at');
        });

        // Dashboard Configurations
        Schema::create('dashboard_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('dashboard_type');  // financial, operations, executive
            $table->json('widget_layout');  // Widget positions and sizes
            $table->json('kpi_selection')->nullable();  // Selected KPIs
            $table->json('preferences')->nullable();  // Theme, refresh rate, etc.
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'dashboard_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('dashboard_configs');
        Schema::dropIfExists('financial_kpi_alerts');
        Schema::dropIfExists('financial_kpi_values');
        Schema::dropIfExists('financial_kpis');
        Schema::dropIfExists('budget_revisions');
        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('capex_project_expenses');
        Schema::dropIfExists('capex_projects');
    }
};
