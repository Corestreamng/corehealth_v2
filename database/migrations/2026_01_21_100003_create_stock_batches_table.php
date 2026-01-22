<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Stock Batches Table
 *
 * Plan Reference: Phase 1 - Database Schema Changes
 * Purpose: Track inventory batches per store with FIFO support
 *
 * Key Features:
 * - Each batch is tied to a specific store (no global stock)
 * - Supports batch tracking from PO, manual entry, or transfers
 * - Enables FIFO stock deduction
 * - Tracks cost price for accounting
 *
 * Related Models: StockBatch, Product, Store, PurchaseOrderItem
 * Related Files:
 * - app/Models/StockBatch.php
 * - app/Services/StockService.php
 */
class CreateStockBatchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('store_id');
            $table->string('batch_name'); // Custom name + datetime (e.g., "PO-2026-001 - Jan 21, 2026 10:30 AM")
            $table->string('batch_number')->nullable(); // Manufacturer batch number
            $table->integer('initial_qty');
            $table->integer('current_qty');
            $table->integer('sold_qty')->default(0);
            $table->decimal('cost_price', 12, 2); // Purchase price per unit
            $table->date('expiry_date')->nullable();
            $table->date('received_date');
            $table->enum('source', ['purchase_order', 'manual', 'transfer_in'])->default('manual');
            $table->unsignedBigInteger('purchase_order_item_id')->nullable();
            $table->unsignedBigInteger('source_requisition_id')->nullable(); // For transfer_in
            $table->unsignedBigInteger('created_by');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('restrict');
            $table->foreign('purchase_order_item_id')->references('id')->on('purchase_order_items')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');

            // Indexes for FIFO queries (oldest batch with stock first)
            $table->index(['product_id', 'store_id', 'is_active', 'current_qty'], 'stock_batches_fifo_idx');
            $table->index(['store_id', 'current_qty']);
            $table->index(['expiry_date']);
            $table->index(['source', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stock_batches');
    }
}
