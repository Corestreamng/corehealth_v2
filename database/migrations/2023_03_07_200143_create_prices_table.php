<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePricesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->integer('pr_buy_price')->default(0);
            $table->integer('initial_sale_price')->default(0);
            $table->date('initial_sale_date')->nullable();
            $table->float('current_sale_price')->default(0);
            $table->integer('half_price')->default(0);
            $table->integer('pieces_price')->default(0);
            $table->integer('pieces_max_discount')->default(0);
            $table->date('current_sale_date')->nullable();
            $table->integer('max_discount')->default(0);
            $table->boolean('status')->default(1);
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
        Schema::dropIfExists('prices');
    }
}
