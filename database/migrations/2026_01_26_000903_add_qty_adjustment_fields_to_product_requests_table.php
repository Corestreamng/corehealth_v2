<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddQtyAdjustmentFieldsToProductRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_requests', function (Blueprint $table) {
            // Quantity adjustment tracking (adapted_at and adapted_by already exist)
            if (!Schema::hasColumn('product_requests', 'qty_adjusted_from')) {
                $table->integer('qty_adjusted_from')->nullable()->after('adapted_at');
            }
            if (!Schema::hasColumn('product_requests', 'qty_adjustment_reason')) {
                $table->text('qty_adjustment_reason')->nullable()->after('qty_adjusted_from');
            }
            if (!Schema::hasColumn('product_requests', 'qty_adjusted_at')) {
                $table->timestamp('qty_adjusted_at')->nullable()->after('qty_adjustment_reason');
            }
            if (!Schema::hasColumn('product_requests', 'qty_adjusted_by')) {
                $table->unsignedBigInteger('qty_adjusted_by')->nullable()->after('qty_adjusted_at');
            }
        });

        // Add foreign key separately to avoid issues
        Schema::table('product_requests', function (Blueprint $table) {
            if (Schema::hasColumn('product_requests', 'qty_adjusted_by')) {
                $table->foreign('qty_adjusted_by')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_requests', function (Blueprint $table) {
            // Drop foreign key first
            if (Schema::hasColumn('product_requests', 'qty_adjusted_by')) {
                $table->dropForeign(['qty_adjusted_by']);
            }

            // Drop columns that this migration added
            $columnsToDrop = [];
            foreach (['qty_adjusted_from', 'qty_adjustment_reason', 'qty_adjusted_at', 'qty_adjusted_by'] as $col) {
                if (Schema::hasColumn('product_requests', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
}
