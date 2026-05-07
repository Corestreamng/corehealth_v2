<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReturnedQtyToPurchaseOrderItemsTable extends Migration
{
    public function up()
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->integer('returned_qty')->default(0)->after('received_qty')
                ->comment('Total quantity returned to supplier across all PO returns');
        });
    }

    public function down()
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('returned_qty');
        });
    }
}
