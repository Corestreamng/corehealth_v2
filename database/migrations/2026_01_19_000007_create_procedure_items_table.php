<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProcedureItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('procedure_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('procedure_id');

            // Item type references (only one will be set per row)
            $table->unsignedBigInteger('lab_service_request_id')->nullable();
            $table->unsignedBigInteger('imaging_service_request_id')->nullable();
            $table->unsignedBigInteger('product_request_id')->nullable();
            $table->unsignedBigInteger('misc_bill_id')->nullable();

            // Billing reference (only set if NOT bundled)
            $table->unsignedBigInteger('product_or_service_request_id')->nullable();

            // If true, this item is included in the procedure's price (no separate billing)
            $table->boolean('is_bundled')->default(false);

            $table->timestamps();

            // Foreign keys
            $table->foreign('procedure_id')
                  ->references('id')
                  ->on('procedures')
                  ->onDelete('cascade');

            $table->foreign('lab_service_request_id')
                  ->references('id')
                  ->on('lab_service_requests')
                  ->onDelete('cascade');

            $table->foreign('imaging_service_request_id')
                  ->references('id')
                  ->on('imaging_service_requests')
                  ->onDelete('cascade');

            $table->foreign('product_request_id')
                  ->references('id')
                  ->on('product_requests')
                  ->onDelete('cascade');

            // misc_bill_id FK - assuming misc_bills table exists
            // If not, this can be adjusted later
            // $table->foreign('misc_bill_id')
            //       ->references('id')
            //       ->on('misc_bills')
            //       ->onDelete('cascade');

            $table->foreign('product_or_service_request_id')
                  ->references('id')
                  ->on('product_or_service_requests')
                  ->onDelete('set null');

            // Index for finding items by procedure
            $table->index('procedure_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('procedure_items');
    }
}
