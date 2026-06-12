<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStockUtilizationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stock_utilizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('store_id');
            $table->integer('qty');
            $table->string('unit')->nullable();
            $table->string('reason');
            $table->enum('utilization_type', ['internal', 'patient'])->default('internal');
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->boolean('is_billed')->default(false);
            $table->unsignedBigInteger('product_or_service_request_id')->nullable();
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('restrict');
            $table->foreign('patient_id')->references('id')->on('patients')->onDelete('restrict');
            $table->foreign('product_or_service_request_id', 'stock_util_posr_foreign')->references('id')->on('product_or_service_requests')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            // Indexes
            $table->index(['store_id', 'product_id']);
            $table->index(['patient_id']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stock_utilizations');
    }
}
