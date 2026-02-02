<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cost Center Accounting Tables Migration
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.11
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 7
 *
 * Creates tables for cost center tracking and allocation.
 */
return new class extends Migration
{
    public function up()
    {
        // Cost Centers
        Schema::create('cost_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->foreignId('department_id')->nullable()->constrained('departments');
            $table->foreignId('manager_user_id')->nullable()->constrained('users');
            $table->foreignId('parent_cost_center_id')->nullable()->constrained('cost_centers');
            $table->enum('center_type', [
                'revenue',      // Generates revenue (e.g., departments)
                'cost',         // Only incurs costs (e.g., admin)
                'service',      // Internal service center
                'project',      // Project-based
            ])->default('cost');
            $table->integer('hierarchy_level')->default(1);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'is_active']);
            $table->index('center_type');
        });

        // Cost Center Budgets
        Schema::create('cost_center_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cost_center_id')->constrained('cost_centers')->onDelete('cascade');
            $table->foreignId('account_id')->constrained('accounts');
            $table->foreignId('fiscal_year_id')->nullable()->constrained('fiscal_years');
            $table->integer('year');
            $table->integer('month')->nullable();  // NULL for annual budget
            $table->decimal('budgeted_amount', 15, 2);
            $table->decimal('actual_amount', 15, 2)->default(0);
            $table->decimal('variance', 15, 2)->default(0);
            $table->decimal('variance_percentage', 8, 2)->default(0);
            $table->boolean('is_locked')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['cost_center_id', 'account_id', 'year', 'month']);
            $table->index(['year', 'month']);
        });

        // Cost Allocations (for overhead distribution)
        Schema::create('cost_center_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('allocation_name');
            $table->foreignId('source_cost_center_id')->constrained('cost_centers');
            $table->foreignId('target_cost_center_id')->constrained('cost_centers');
            $table->foreignId('account_id')->nullable()->constrained('accounts');  // NULL = all accounts
            $table->enum('allocation_method', [
                'percentage',        // Fixed percentage
                'headcount',         // Based on employee count
                'square_footage',    // Based on area
                'revenue',           // Based on revenue proportion
                'direct_hours',      // Based on labor hours
                'custom',            // Custom calculation
            ])->default('percentage');
            $table->decimal('allocation_percentage', 8, 4)->nullable();
            $table->string('allocation_driver')->nullable();  // Column name for driver
            $table->enum('frequency', ['monthly', 'quarterly', 'annually'])->default('monthly');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['source_cost_center_id', 'is_active']);
        });

        // Cost Allocation Runs (history)
        Schema::create('cost_allocation_runs', function (Blueprint $table) {
            $table->id();
            $table->date('allocation_date');
            $table->integer('year');
            $table->integer('month');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
            $table->decimal('total_allocated', 15, 2);
            $table->integer('allocations_count');
            $table->enum('status', ['pending', 'completed', 'reversed'])->default('pending');
            $table->foreignId('processed_by')->constrained('users');
            $table->timestamp('processed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month']);
        });

        // Cost Allocation Details
        Schema::create('cost_allocation_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('allocation_run_id')->constrained('cost_allocation_runs')->onDelete('cascade');
            $table->foreignId('allocation_id')->constrained('cost_center_allocations');
            $table->foreignId('source_cost_center_id')->constrained('cost_centers');
            $table->foreignId('target_cost_center_id')->constrained('cost_centers');
            $table->foreignId('account_id')->constrained('accounts');
            $table->decimal('source_amount', 15, 2);
            $table->decimal('allocation_percentage', 8, 4);
            $table->decimal('allocated_amount', 15, 2);
            $table->timestamps();

            $table->index('allocation_run_id');
        });

        // Add cost_center_id to journal_entry_lines for tracking
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->foreignId('cost_center_id')->nullable()->after('account_id')
                ->constrained('cost_centers');
        });
    }

    public function down()
    {
        // Remove cost_center_id from journal_entry_lines
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->dropForeign(['cost_center_id']);
            $table->dropColumn('cost_center_id');
        });

        Schema::dropIfExists('cost_allocation_details');
        Schema::dropIfExists('cost_allocation_runs');
        Schema::dropIfExists('cost_center_allocations');
        Schema::dropIfExists('cost_center_budgets');
        Schema::dropIfExists('cost_centers');
    }
};
