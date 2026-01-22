<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Store Requisition Items Table
 *
 * Plan Reference: Phase 1 - Database Schema Changes
 * Purpose: Line items for store requisitions
 *
 * Key Features:
 * - Tracks requested, approved, and fulfilled quantities
 * - Links to source batch (where items come from)
 * - Links to destination batch (created when items are received)
 *
 * Related Models: StoreRequisitionItem, StoreRequisition, Product, StockBatch
 * Related Files:
 * - app/Models/StoreRequisitionItem.php
 * - app/Services/RequisitionService.php
 */
class CreateStoreRequisitionItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('store_requisition_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_requisition_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('requested_qty')->default(0);
            $table->integer('approved_qty')->nullable(); // Quantity approved by admin
            $table->integer('fulfilled_qty')->nullable(); // Actual quantity transferred
            $table->unsignedBigInteger('source_batch_id')->nullable(); // Batch from which items are taken
            $table->unsignedBigInteger('destination_batch_id')->nullable(); // New batch created in destination store
            $table->enum('status', ['pending', 'approved', 'rejected', 'partial', 'fulfilled', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('store_requisition_id')->references('id')->on('store_requisitions')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('source_batch_id')->references('id')->on('stock_batches')->onDelete('set null');
            $table->foreign('destination_batch_id')->references('id')->on('stock_batches')->onDelete('set null');

            // Indexes
            $table->index(['store_requisition_id', 'status']);
            $table->index(['product_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('store_requisition_items');
    }
}
