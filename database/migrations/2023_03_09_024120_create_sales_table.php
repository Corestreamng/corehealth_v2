<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSalesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_or_service_requests_id'); //transaction_id on old db
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedBigInteger('budget_year_id')->nullable(); //for back compatibility
            $table->string('serial_no')->nullable();
            $table->integer('quantity_buy');
            $table->float('sale_price');
            $table->integer('pieces_quantity')->nullable();
            $table->integer('pieces_sales_price')->nullable();
            $table->integer('total_amount');
            $table->unsignedBigInteger('store_id');
            $table->integer('promo_qt')->nullable();
            $table->float('gain');
            $table->float('loss'); //spelt 'lost' in old db
            $table->timestamp('sale_date')->useCurrent();
            $table->unsignedBigInteger('user_id');
            $table->integer('supply');
            $table->timestamp('supply_date')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sales');
    }
}
