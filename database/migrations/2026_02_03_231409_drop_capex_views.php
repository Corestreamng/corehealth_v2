<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Drop CAPEX views - now using tables directly
 *
 * The controller has been updated to use actual tables instead of views:
 * - capex_requests view -> capex_projects table
 * - capex_expenses view -> capex_project_expenses table
 */
class DropCapexViews extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Drop the views that are no longer needed
        DB::statement('DROP VIEW IF EXISTS capex_requests');
        DB::statement('DROP VIEW IF EXISTS capex_expenses');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Recreate views if needed for rollback
        DB::statement('CREATE OR REPLACE VIEW capex_requests AS SELECT * FROM capex_projects');

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
                updated_at
            FROM capex_project_expenses
        ");
    }
}
