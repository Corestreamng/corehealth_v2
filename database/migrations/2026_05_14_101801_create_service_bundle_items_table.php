<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServiceBundleItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('service_bundle_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_service_id');
            $table->string('item_type'); // 'product' or 'service'
            $table->unsignedBigInteger('item_id');
            $table->decimal('qty', 10, 2)->default(1);
            $table->string('dose')->nullable();
            $table->string('note')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('parent_service_id')->references('id')->on('services')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('service_bundle_items');
    }
}
