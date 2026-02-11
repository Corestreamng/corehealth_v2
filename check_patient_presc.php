<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check a specific patient's prescriptions - patient 9426 from the screenshot
$patient = App\Models\patient::where('id', 9426)->first();

if ($patient) {
    echo "Patient ID: " . $patient->id . PHP_EOL;
    echo "Patient Name: " . $patient->firstname . " " . $patient->lastname . PHP_EOL;

    // Get all prescriptions for this patient
    $prescriptions = App\Models\ProductRequest::with('product')
        ->where('patient_id', $patient->id)
        ->whereIn('status', [1, 2])
        ->get();

    echo PHP_EOL . "Prescriptions:" . PHP_EOL;

    foreach ($prescriptions as $pr) {
        echo "---" . PHP_EOL;
        echo "PR ID: " . $pr->id . PHP_EOL;
        echo "Product ID: " . $pr->product_id . PHP_EOL;
        echo "Product Name: " . optional($pr->product)->product_name . PHP_EOL;
        echo "Adapted From: " . ($pr->adapted_from_product_id ?? 'N/A') . PHP_EOL;
        echo "Status: " . $pr->status . PHP_EOL;

        // Check stock
        if ($pr->product_id) {
            $storeStockData = App\Models\StockBatch::where('product_id', $pr->product_id)
                ->where('current_qty', '>', 0)
                ->selectRaw('store_id, SUM(current_qty) as total_qty')
                ->groupBy('store_id')
                ->get();

            $globalStock = $storeStockData->sum('total_qty');
            echo "Global Stock (from StockBatch): " . $globalStock . PHP_EOL;

            foreach ($storeStockData as $ss) {
                $store = App\Models\Store::find($ss->store_id);
                echo "  Store " . ($store ? $store->store_name : $ss->store_id) . ": " . $ss->total_qty . PHP_EOL;
            }
        }
    }
} else {
    echo "Patient with file number 9426 not found" . PHP_EOL;
}
