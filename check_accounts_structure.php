<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

try {
    echo "=== Accounts Table Columns ===\n";
    $columns = Schema::getColumnListing('accounts');
    print_r($columns);

    echo "\n=== Sample Account ===\n";
    $sample = DB::table('accounts')->first();
    print_r($sample);

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
