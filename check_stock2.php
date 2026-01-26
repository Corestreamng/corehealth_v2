<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Find a patient with a prescription for this product
$pr = App\Models\ProductRequest::where('product_id', 58)->whereIn('status', [1, 2])->first();

if ($pr) {
    echo "ProductRequest ID: " . $pr->id . PHP_EOL;
    echo "Patient ID: " . $pr->patient_id . PHP_EOL;
    echo "Product ID: " . $pr->product_id . PHP_EOL;

    // Get stock from StockBatch
    $globalStock = 0;
    $storeStocks = [];

    $storeStockData = App\Models\StockBatch::where('product_id', $pr->product_id)
        ->where('current_qty', '>', 0)
        ->selectRaw('store_id, SUM(current_qty) as total_qty')
        ->groupBy('store_id')
        ->orderByDesc('total_qty')
        ->get();

    echo "StockBatch query result count: " . $storeStockData->count() . PHP_EOL;

    foreach ($storeStockData as $batch) {
        $store = App\Models\Store::find($batch->store_id);
        $qty = (int) $batch->total_qty;
        $globalStock += $qty;
        $storeStocks[] = [
            'store_id' => $batch->store_id,
            'store_name' => $store ? $store->store_name : 'Unknown Store',
            'quantity' => $qty
        ];
        echo "  Store " . $batch->store_id . ": " . $qty . PHP_EOL;
    }

    echo PHP_EOL . "Global Stock: " . $globalStock . PHP_EOL;
    echo "Store Stocks: " . json_encode($storeStocks) . PHP_EOL;
} else {
    echo "No ProductRequest found for product 58" . PHP_EOL;
}
