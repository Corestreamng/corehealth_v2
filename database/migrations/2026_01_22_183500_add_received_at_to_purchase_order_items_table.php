<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add received_at to Purchase Order Items
 *
 * Purpose: Track when each line item was received (first or last reception)
 */
class AddReceivedAtToPurchaseOrderItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('received_at');
        });
    }
}
