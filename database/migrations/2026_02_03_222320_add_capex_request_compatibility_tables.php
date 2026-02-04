<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add compatibility tables and columns for CAPEX controller
 *
 * The controller was written expecting certain column names and tables
 * that don't exist in the base capex_projects migration.
 * This migration adds those missing pieces while maintaining compatibility.
 */
class AddCapexRequestCompatibilityTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add missing columns to capex_projects to match controller expectations
        Schema::table('capex_projects', function (Blueprint $table) {
            // Add columns expected by controller/views
            $table->string('reference_number')->unique()->after('id')->nullable();
            $table->string('title')->after('project_name')->nullable();
            $table->string('category')->after('project_type')->nullable();
            $table->decimal('requested_amount', 15, 2)->after('estimated_cost')->nullable();
            $table->decimal('approved_amount', 15, 2)->after('approved_budget')->nullable();
            $table->decimal('actual_amount', 15, 2)->after('actual_cost')->nullable();
            $table->foreignId('cost_center_id')->nullable()->after('department_id')->constrained('cost_centers');
            $table->foreignId('vendor_id')->nullable()->after('cost_center_id')->constrained('suppliers');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium')->after('status');
            $table->timestamp('submitted_at')->nullable()->after('approved_date');
        });

        // Update the view to include new columns
        DB::statement('CREATE OR REPLACE VIEW capex_requests AS SELECT * FROM capex_projects');

        // Create capex_request_items table (line items for requests)
        Schema::create('capex_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capex_request_id')->constrained('capex_projects')->onDelete('cascade');
            $table->string('description');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('capex_request_id');
        });

        // Create capex_approval_history table
        Schema::create('capex_approval_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capex_request_id')->constrained('capex_projects')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('action', ['submitted', 'approved', 'rejected', 'revision_requested', 'started', 'completed', 'cancelled']);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['capex_request_id', 'created_at']);
        });

        // Create capex_expenses table (alias/view to capex_project_expenses with column mapping)
        DB::statement("
            CREATE OR REPLACE VIEW capex_expenses AS
            SELECT
                id,
                project_id as capex_request_id,
                expense_date,
                description,
                vendor,
                invoice_number as payment_reference,
                amount,
                status,
                created_at,
                updated_at,
                NULL as created_by
            FROM capex_project_expenses
        ");

        // Add status values that controller expects
        DB::statement("
            ALTER TABLE capex_projects
            MODIFY COLUMN status ENUM(
                'draft',
                'pending',
                'pending_approval',
                'approved',
                'in_progress',
                'completed',
                'cancelled',
                'rejected',
                'on_hold',
                'revision'
            ) DEFAULT 'draft'
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop views first
        DB::statement('DROP VIEW IF EXISTS capex_expenses');

        // Drop tables
        Schema::dropIfExists('capex_approval_history');
        Schema::dropIfExists('capex_request_items');

        // Remove added columns
        Schema::table('capex_projects', function (Blueprint $table) {
            $table->dropForeign(['cost_center_id']);
            $table->dropForeign(['vendor_id']);
            $table->dropColumn([
                'reference_number',
                'title',
                'category',
                'requested_amount',
                'approved_amount',
                'actual_amount',
                'cost_center_id',
                'vendor_id',
                'priority',
                'submitted_at',
            ]);
        });

        // Recreate original view
        DB::statement('CREATE OR REPLACE VIEW capex_requests AS SELECT * FROM capex_projects');

        // Revert status enum
        DB::statement("
            ALTER TABLE capex_projects
            MODIFY COLUMN status ENUM(
                'draft',
                'pending_approval',
                'approved',
                'in_progress',
                'completed',
                'cancelled',
                'on_hold'
            ) DEFAULT 'draft'
        ");
    }
}
