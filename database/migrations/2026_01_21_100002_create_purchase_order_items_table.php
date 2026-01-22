<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Purchase Order Items Table
 *
 * Plan Reference: Phase 1 - Database Schema Changes
 * Purpose: Store individual line items for purchase orders
 *
 * Related Models: PurchaseOrderItem, PurchaseOrder, Product
 * Related Files:
 * - app/Models/PurchaseOrderItem.php
 * - app/Models/PurchaseOrder.php
 * - app/Models/Product.php (existing)
 */
class CreatePurchaseOrderItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id');
            $table->unsignedBigInteger('product_id');
            $table->integer('ordered_qty');
            $table->integer('received_qty')->default(0);
            $table->decimal('unit_cost', 12, 2)->nullable(); // Estimated cost at order time
            $table->decimal('actual_unit_cost', 12, 2)->nullable(); // Actual cost when received
            $table->enum('status', ['pending', 'partial', 'received', 'cancelled'])->default('pending');
            $table->timestamps();

            // Foreign keys
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');

            // Indexes
            $table->index(['purchase_order_id', 'status']);
            $table->index(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('purchase_order_items');
    }
}
