<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('category_id');
            $table->string('product_name');
            $table->string('product_code')->nullable();
            $table->string('reorder_alert')->nullable();
            $table->string('has_have')->nullable();
            $table->string('has_piece')->nullable();
            $table->string('howmany_to')->nullable();
            $table->string('current_quantity')->nullable();
            $table->boolean('status')->default(1);
            $table->boolean('stock_assign')->default(0);
            $table->boolean('price_assign')->default(0);
            $table->integer('promotion')->default(0);
            $table->integer('1')->default(0);//non functional imlemented for backward comatibility
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
        Schema::dropIfExists('products');
    }
}
