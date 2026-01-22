<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add Batch Fields to Product Requests Table
 *
 * Plan Reference: Phase 1 - Database Schema Changes
 * Purpose: Track which batch items were dispensed from and support product adaptation
 *
 * Key Features:
 * - dispensed_from_batch_id: Links to the batch that was used for dispensing
 * - Product adaptation fields: Allow changing one product to another during billing
 *
 * Related Models: ProductRequest, StockBatch, Product
 * Related Files:
 * - app/Models/ProductRequest.php
 * - app/Http/Controllers/PharmacyWorkbenchController.php
 */
class AddBatchFieldsToProductRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('product_requests', function (Blueprint $table) {
            // Batch tracking for dispensing
            $table->unsignedBigInteger('dispensed_from_batch_id')->nullable();

            // Product adaptation fields (when billing changes the product)
            $table->unsignedBigInteger('original_product_id')->nullable();
            $table->unsignedBigInteger('adapted_from_product_id')->nullable();
            $table->integer('original_qty')->nullable();
            $table->text('adaptation_note')->nullable();
            $table->boolean('is_adapted')->default(false);
            $table->unsignedBigInteger('adapted_by')->nullable();
            $table->timestamp('adapted_at')->nullable();

            // Foreign keys
            $table->foreign('dispensed_from_batch_id')->references('id')->on('stock_batches')->onDelete('set null');
            $table->foreign('original_product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('adapted_from_product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('adapted_by')->references('id')->on('users')->onDelete('set null');

            // Index for batch tracking
            $table->index('dispensed_from_batch_id');
            $table->index('is_adapted');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('product_requests', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['dispensed_from_batch_id']);
            $table->dropForeign(['original_product_id']);
            $table->dropForeign(['adapted_from_product_id']);
            $table->dropForeign(['adapted_by']);

            // Drop indexes
            $table->dropIndex(['dispensed_from_batch_id']);
            $table->dropIndex(['is_adapted']);

            // Drop columns
            $table->dropColumn([
                'dispensed_from_batch_id',
                'original_product_id',
                'adapted_from_product_id',
                'original_qty',
                'adaptation_note',
                'is_adapted',
                'adapted_by',
                'adapted_at'
            ]);
        });
    }
}
