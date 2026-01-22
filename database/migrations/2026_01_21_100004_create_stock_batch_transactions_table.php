<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Stock Batch Transactions Table
 *
 * Plan Reference: Phase 1 - Database Schema Changes
 * Purpose: Audit log for all stock batch movements
 *
 * Key Features:
 * - Records every stock in/out transaction
 * - Tracks balance after each transaction
 * - Links to source records (ProductRequest, Requisition, etc.)
 *
 * Related Models: StockBatchTransaction, StockBatch
 * Related Files:
 * - app/Models/StockBatchTransaction.php
 * - app/Services/StockService.php
 */
class CreateStockBatchTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stock_batch_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stock_batch_id');
            $table->enum('type', ['in', 'out', 'adjustment', 'transfer_out', 'transfer_in', 'return', 'expired', 'damaged']);
            $table->integer('qty'); // Positive for in, negative for out
            $table->integer('balance_after');
            $table->string('reference_type')->nullable(); // Model class name (ProductRequest, StoreRequisition, etc.)
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('performed_by');
            $table->timestamps();

            // Foreign keys
            $table->foreign('stock_batch_id')->references('id')->on('stock_batches')->onDelete('cascade');
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('restrict');

            // Indexes
            $table->index(['stock_batch_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['type', 'created_at']);
            $table->index(['performed_by', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stock_batch_transactions');
    }
}
