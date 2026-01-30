<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add metadata columns to journal_entry_lines
 *
 * Purpose: Enable granular filtering and drill-down on journal entries
 * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Part 7.1.2
 *
 * Metadata allows queries like:
 * - Revenue by service category
 * - AR by HMO company
 * - All entries for a specific product
 * - Expense by supplier
 */
class AddMetadataToJournalEntryLinesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            // Direct entity references for granular filtering and drill-down
            $table->unsignedBigInteger('product_id')->nullable()->after('sub_account_id');
            $table->unsignedBigInteger('service_id')->nullable()->after('product_id');
            $table->unsignedBigInteger('product_category_id')->nullable()->after('service_id');
            $table->unsignedBigInteger('service_category_id')->nullable()->after('product_category_id');
            $table->unsignedBigInteger('hmo_id')->nullable()->after('service_category_id');
            $table->unsignedBigInteger('supplier_id')->nullable()->after('hmo_id');
            $table->unsignedBigInteger('patient_id')->nullable()->after('supplier_id');
            $table->unsignedBigInteger('department_id')->nullable()->after('patient_id');
            $table->string('category', 50)->nullable()->after('department_id')
                  ->comment('lab, pharmacy, imaging, consultation, procedure, admission, payroll, expense, po_payment, hmo_remittance');

            // Foreign key constraints
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->nullOnDelete();
            $table->foreign('product_category_id')->references('id')->on('product_categories')->nullOnDelete();
            $table->foreign('service_category_id')->references('id')->on('service_categories')->nullOnDelete();
            $table->foreign('hmo_id')->references('id')->on('hmos')->nullOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
            $table->foreign('patient_id')->references('id')->on('patients')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();

            // Indexes for fast queries
            $table->index('product_id', 'jel_product_idx');
            $table->index('service_id', 'jel_service_idx');
            $table->index('product_category_id', 'jel_prod_cat_idx');
            $table->index('service_category_id', 'jel_svc_cat_idx');
            $table->index('hmo_id', 'jel_hmo_idx');
            $table->index('supplier_id', 'jel_supplier_idx');
            $table->index('patient_id', 'jel_patient_idx');
            $table->index('department_id', 'jel_dept_idx');
            $table->index('category', 'jel_category_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('journal_entry_lines', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['product_id']);
            $table->dropForeign(['service_id']);
            $table->dropForeign(['product_category_id']);
            $table->dropForeign(['service_category_id']);
            $table->dropForeign(['hmo_id']);
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['patient_id']);
            $table->dropForeign(['department_id']);

            // Drop indexes
            $table->dropIndex('jel_product_idx');
            $table->dropIndex('jel_service_idx');
            $table->dropIndex('jel_prod_cat_idx');
            $table->dropIndex('jel_svc_cat_idx');
            $table->dropIndex('jel_hmo_idx');
            $table->dropIndex('jel_supplier_idx');
            $table->dropIndex('jel_patient_idx');
            $table->dropIndex('jel_dept_idx');
            $table->dropIndex('jel_category_idx');

            // Drop columns
            $table->dropColumn([
                'product_id',
                'service_id',
                'product_category_id',
                'service_category_id',
                'hmo_id',
                'supplier_id',
                'patient_id',
                'department_id',
                'category',
            ]);
        });
    }
}
