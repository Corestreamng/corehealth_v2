<?php

/**
 * Script: Seed Initial Stock
 * Creates a "Initial Stock" batch for every product in each store whose name contains 'pharm'.
 * Sets the stock of each product to 100 units.
 *
 * Usage:  php seed_initial_stock.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Store;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StoreStock;
use Illuminate\Support\Facades\DB;

$minQty = 70;
$targetQty = 100;
$batchName = 'Initial Stock';
$now = now();

// Get all active stores with names like 'pharm'
$stores = Store::where('store_name', 'like', '%pharm%')->get();

if ($stores->isEmpty()) {
    echo "No stores found with name like 'pharm'.\n";
    exit(1);
}

echo "Found " . $stores->count() . " store(s):\n";
foreach ($stores as $store) {
    echo "  - [{$store->id}] {$store->store_name}\n";
}

// Get all active products
$products = Product::where('status', 1)->get();

if ($products->isEmpty()) {
    echo "No active products found.\n";
    exit(1);
}

echo "Found " . $products->count() . " active product(s).\n";
echo "Topping up products below {$minQty} units to {$targetQty} ...\n\n";

// Use the first user (admin) as created_by fallback
$adminId = DB::table('users')->orderBy('id')->value('id') ?? 1;

$createdBatches = 0;
$updatedStoreStocks = 0;

DB::beginTransaction();

try {
    foreach ($stores as $store) {
        echo "Processing store: {$store->store_name} (ID: {$store->id})\n";

        foreach ($products as $product) {
            // Check current stock level
            $storeStock = StoreStock::firstOrNew([
                'store_id'   => $store->id,
                'product_id' => $product->id,
            ]);

            $currentQty = (int) ($storeStock->current_quantity ?? 0);

            if ($currentQty >= $minQty) {
                continue; // Stock is sufficient
            }

            $topUpQty = $targetQty - $currentQty;

            // Create a top-up stock batch
            $batch = StockBatch::create([
                'product_id'    => $product->id,
                'store_id'      => $store->id,
                'batch_name'    => $batchName,
                'batch_number'  => 'INIT-' . $store->id . '-' . $product->id . '-' . time(),
                'initial_qty'   => $topUpQty,
                'current_qty'   => $topUpQty,
                'sold_qty'      => 0,
                'cost_price'    => 0,
                'received_date' => $now->toDateString(),
                'source'        => 'manual',
                'created_by'    => $adminId,
                'is_active'     => true,
            ]);

            $createdBatches++;

            // Update the store_stocks aggregate record
            $storeStock->initial_quantity = ($storeStock->initial_quantity ?? 0) + $topUpQty;
            $storeStock->current_quantity = $currentQty + $topUpQty;
            $storeStock->save();

            $updatedStoreStocks++;

            echo "  [TOP-UP] {$product->product_name}: {$currentQty} → {$targetQty} (+{$topUpQty})\n";
        }

        echo "  Done.\n";
    }

    DB::commit();

    echo "\n=== Summary ===\n";
    echo "Stock batches created : {$createdBatches}\n";
    echo "Store stocks updated  : {$updatedStoreStocks}\n";
    echo "Done!\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
