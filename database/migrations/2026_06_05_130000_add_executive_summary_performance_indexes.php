<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_or_service_requests', function (Blueprint $table) {
            $table->index(['dispensed_from_store_id', 'order_date'], 'posr_dispensed_order_date_idx');
        });

        Schema::table('store_requisitions', function (Blueprint $table) {
            $table->index('fulfilled_at', 'store_requisitions_fulfilled_at_index');
        });

        Schema::table('store_requisition_items', function (Blueprint $table) {
            $table->index('status', 'store_req_items_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_or_service_requests', function (Blueprint $table) {
            $table->dropIndex('posr_dispensed_order_date_idx');
        });

        Schema::table('store_requisitions', function (Blueprint $table) {
            $table->dropIndex('store_requisitions_fulfilled_at_index');
        });

        Schema::table('store_requisition_items', function (Blueprint $table) {
            $table->dropIndex('store_req_items_status_idx');
        });
    }
};
