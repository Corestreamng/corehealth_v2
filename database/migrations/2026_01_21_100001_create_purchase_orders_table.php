<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Purchase Orders Table
 *
 * Plan Reference: Phase 1 - Database Schema Changes
 * Purpose: Store purchase orders for inventory procurement
 *
 * Related Models: PurchaseOrder, Supplier, Store, User
 * Related Files:
 * - app/Models/PurchaseOrder.php
 * - app/Models/Supplier.php (existing)
 * - app/Models/Store.php (existing)
 */
class CreatePurchaseOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('target_store_id');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'partial', 'received', 'cancelled'])->default('draft');
            $table->date('expected_date')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('restrict');
            $table->foreign('target_store_id')->references('id')->on('stores')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for common queries
            $table->index(['status', 'created_at']);
            $table->index(['supplier_id', 'status']);
            $table->index(['target_store_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('purchase_orders');
    }
}
