<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Migration: Create Initial Stock Batches from Existing Store Stock
 *
 * Plan Reference: Data Migration Strategy
 * Purpose: Convert existing store_stocks records into stock_batches for batch tracking
 *
 * This migration creates "Legacy" batches from existing StoreStock records
 * to ensure backward compatibility with the new batch-based inventory system.
 *
 * Features:
 * - Creates a batch for each product/store combination with stock
 * - Uses "LEGACY-{store_code}-{product_id}" as batch number
 * - Sets initial_qty and current_qty from store_stocks.current_quantity
 * - Records an initial "receive" transaction
 *
 * Related Models: StockBatch, StoreStock, StockBatchTransaction
 */
class CreateInitialBatchesFromStoreStock extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Get all store stocks with quantity > 0
        $storeStocks = DB::table('store_stocks')
            ->join('stores', 'store_stocks.store_id', '=', 'stores.id')
            ->where('store_stocks.current_quantity', '>', 0)
            ->select(
                'store_stocks.id as store_stock_id',
                'store_stocks.store_id',
                'store_stocks.product_id',
                'store_stocks.current_quantity',
                'stores.code as store_code',
                'stores.store_name'
            )
            ->get();

        foreach ($storeStocks as $storeStock) {
            // Check if a legacy batch already exists
            $existingBatch = DB::table('stock_batches')
                ->where('product_id', $storeStock->product_id)
                ->where('store_id', $storeStock->store_id)
                ->where('batch_number', 'LIKE', 'LEGACY-%')
                ->first();

            if ($existingBatch) {
                continue; // Skip if legacy batch already exists
            }

            $storeCode = $storeStock->store_code ?? 'STR';
            $batchNumber = "LEGACY-{$storeCode}-{$storeStock->product_id}";
            $batchName = "Legacy Stock - {$storeStock->store_name}";
            $now = Carbon::now();

            // Create the batch
            $batchId = DB::table('stock_batches')->insertGetId([
                'batch_name' => $batchName,
                'batch_number' => $batchNumber,
                'product_id' => $storeStock->product_id,
                'store_id' => $storeStock->store_id,
                'purchase_order_item_id' => null, // Legacy batch, no PO
                'initial_qty' => $storeStock->current_quantity,
                'current_qty' => $storeStock->current_quantity,
                'sold_qty' => 0,
                'cost_price' => 0.00, // Unknown for legacy stock
                'expiry_date' => null, // Unknown for legacy stock
                'received_date' => $now->toDateString(),
                'source' => 'manual',
                'source_requisition_id' => null,
                'created_by' => 1, // System user
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Create initial receive transaction
            DB::table('stock_batch_transactions')->insert([
                'stock_batch_id' => $batchId,
                'type' => 'in',
                'qty' => $storeStock->current_quantity,
                'balance_after' => $storeStock->current_quantity,
                'reference_type' => 'Migration',
                'reference_id' => null,
                'performed_by' => 1, // System user
                'notes' => 'Initial batch creation from legacy store stock',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Delete all legacy batches and their transactions
        $legacyBatchIds = DB::table('stock_batches')
            ->where('batch_number', 'LIKE', 'LEGACY-%')
            ->pluck('id');

        DB::table('stock_batch_transactions')
            ->whereIn('stock_batch_id', $legacyBatchIds)
            ->delete();

        DB::table('stock_batches')
            ->whereIn('id', $legacyBatchIds)
            ->delete();
    }
}
