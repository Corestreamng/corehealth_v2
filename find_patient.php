<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Find Martha patient
$patient = App\Models\Patient::where('firstname', 'like', '%Martha%')
    ->orWhere('lastname', 'like', '%Uwalaka%')
    ->first();

if ($patient) {
    echo "Found Patient ID: " . $patient->id . PHP_EOL;
    echo "Name: " . $patient->firstname . " " . $patient->lastname . PHP_EOL;

    // Get prescriptions
    $prescriptions = App\Models\ProductRequest::with('product')
        ->where('patient_id', $patient->id)
        ->whereIn('status', [1, 2])
        ->get();

    echo PHP_EOL . "Prescriptions (" . $prescriptions->count() . "):" . PHP_EOL;

    foreach ($prescriptions as $pr) {
        echo "---" . PHP_EOL;
        echo "PR ID: " . $pr->id . PHP_EOL;
        echo "Product ID: " . $pr->product_id . PHP_EOL;
        echo "Product Name: " . optional($pr->product)->product_name . PHP_EOL;

        // Check if adapted
        if (isset($pr->adapted_from_product_id) && $pr->adapted_from_product_id) {
            echo "*** ADAPTED FROM PRODUCT ID: " . $pr->adapted_from_product_id . " ***" . PHP_EOL;
        }

        echo "Status: " . $pr->status . PHP_EOL;

        // Check stock using StockBatch
        if ($pr->product_id) {
            $storeStockData = App\Models\StockBatch::where('product_id', $pr->product_id)
                ->where('current_qty', '>', 0)
                ->selectRaw('store_id, SUM(current_qty) as total_qty')
                ->groupBy('store_id')
                ->get();

            $globalStock = (int) $storeStockData->sum('total_qty');
            echo "Global Stock (StockBatch): " . $globalStock . PHP_EOL;
        } else {
            echo "NO PRODUCT_ID!" . PHP_EOL;
        }
    }
} else {
    echo "Patient not found" . PHP_EOL;

    // List some patients
    echo PHP_EOL . "Recent patients:" . PHP_EOL;
    $patients = App\Models\Patient::orderByDesc('id')->limit(5)->get(['id', 'firstname', 'lastname']);
    foreach ($patients as $p) {
        echo "  ID: " . $p->id . " - " . $p->firstname . " " . $p->lastname . PHP_EOL;
    }
}
