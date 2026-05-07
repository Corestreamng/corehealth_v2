<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStoreRequisitionReturnsTable extends Migration
{
    public function up()
    {
        Schema::create('store_requisition_returns', function (Blueprint $table) {
            $table->id();

            // Requisition reference
            $table->unsignedBigInteger('store_requisition_id');
            $table->unsignedBigInteger('store_requisition_item_id');

            // Product & batch
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('source_store_id');  // Store returning items (originally received at)
            $table->unsignedBigInteger('destination_store_id'); // Store receiving returns (originally fulfilled from)
            $table->unsignedBigInteger('batch_id')->nullable(); // Batch being returned

            // Return details
            $table->integer('qty_returned');
            $table->string('return_condition', 50)->default('good')->comment('good, damaged, partial');
            $table->text('return_reason');

            // Whether stock is restocked at the source (origin) store
            $table->boolean('restock')->default(true)->comment('Re-add to origin store stock on approval');

            // Workflow
            $table->string('status', 50)->default('pending')->comment('pending, approved, rejected');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            // Stock tracking (no JE since inter-store movement)
            $table->boolean('stock_adjusted')->default(false);
            $table->timestamp('stock_adjusted_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('store_requisition_id')->references('id')->on('store_requisitions')->onDelete('cascade');
            $table->foreign('store_requisition_item_id')->references('id')->on('store_requisition_items')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('source_store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('destination_store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('batch_id')->references('id')->on('stock_batches')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index('status');
            $table->index('store_requisition_id');
            $table->index(['source_store_id', 'created_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('store_requisition_returns');
    }
}
