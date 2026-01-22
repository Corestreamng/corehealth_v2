<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Add Fields to Store Stocks Table
 *
 * Plan Reference: Phase 1 - Database Schema Changes
 * Purpose: Enhance store_stocks with reservation tracking and reorder management
 *
 * Note: store_stocks will still be used for quick lookups of aggregate quantities,
 * but actual stock movements are tracked via stock_batches
 *
 * Key Features:
 * - reserved_qty: Items reserved for pending orders but not yet dispensed
 * - reorder_level: Threshold for low stock alerts
 * - is_active: Whether this store carries this product
 *
 * Related Models: StoreStock, Store, Product
 * Related Files:
 * - app/Models/StoreStock.php
 */
class AddFieldsToStoreStocksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('store_stocks', function (Blueprint $table) {
            // Check if columns exist before adding
            if (!Schema::hasColumn('store_stocks', 'reserved_qty')) {
                $table->integer('reserved_qty')->default(0);
            }
            if (!Schema::hasColumn('store_stocks', 'reorder_level')) {
                $table->integer('reorder_level')->default(10);
            }
            if (!Schema::hasColumn('store_stocks', 'max_stock_level')) {
                $table->integer('max_stock_level')->nullable();
            }
            if (!Schema::hasColumn('store_stocks', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn('store_stocks', 'last_restocked_at')) {
                $table->timestamp('last_restocked_at')->nullable();
            }
            if (!Schema::hasColumn('store_stocks', 'last_sold_at')) {
                $table->timestamp('last_sold_at')->nullable();
            }
        });

        // Add index for low stock queries
        Schema::table('store_stocks', function (Blueprint $table) {
            // Index for low stock alert queries (using current_quantity instead of qty)
            $table->index(['is_active', 'current_quantity'], 'store_stocks_low_stock_idx');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('store_stocks', function (Blueprint $table) {
            // Drop index
            $table->dropIndex('store_stocks_low_stock_idx');

            // Drop columns
            $table->dropColumn([
                'reserved_qty',
                'reorder_level',
                'max_stock_level',
                'is_active',
                'last_restocked_at',
                'last_sold_at'
            ]);
        });
    }
}
