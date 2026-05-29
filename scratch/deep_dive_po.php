<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

$poModels = [
    'PurchaseOrderItem' => \App\Models\PurchaseOrderItem::class,
    'PurchaseOrderPayment' => \App\Models\PurchaseOrderPayment::class,
    'PurchaseOrderReturn' => \App\Models\PurchaseOrderReturn::class,
];

foreach ($poModels as $name => $class) {
    echo "=== {$name} ===\n";
    $instance = new $class;
    $table = $instance->getTable();
    echo "Table: {$table}\n";
    echo "Row Count: " . DB::table($table)->count() . "\n";
    echo "Columns: " . implode(', ', Schema::getColumnListing($table)) . "\n";
    
    $sample = DB::table($table)->first();
    if ($sample) {
        echo "Sample:\n" . json_encode($sample, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "Sample: [No records]\n";
    }
    echo "\n";
}
