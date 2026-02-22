<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tables = [
    'product_requests',
    'product_or_service_requests',
    'medication_schedules',
    'medication_administrations',
    'injection_administrations',
    'stores',
    'stock_batches',
    'products',
];

foreach ($tables as $t) {
    echo "\n=== $t ===\n";
    if (!Illuminate\Support\Facades\Schema::hasTable($t)) {
        echo "TABLE DOES NOT EXIST\n";
        continue;
    }
    $cols = Illuminate\Support\Facades\Schema::getColumnListing($t);
    echo implode(', ', $cols) . "\n";
}
