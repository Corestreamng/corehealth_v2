<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Update capex_expenses view to include all fields needed by controller
 */
class UpdateCapexExpensesView extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Drop and recreate the view with all necessary fields
        DB::statement("DROP VIEW IF EXISTS capex_expenses");

        DB::statement("
            CREATE OR REPLACE VIEW capex_expenses AS
            SELECT
                id,
                project_id as capex_request_id,
                journal_entry_id,
                purchase_order_id,
                expense_id,
                expense_date,
                description,
                vendor,
                vendor as vendor_id,
                invoice_number as payment_reference,
                amount,
                status,
                created_at,
                updated_at
            FROM capex_project_expenses
        ");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Recreate old view
        DB::statement("DROP VIEW IF EXISTS capex_expenses");

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
    }
}
