<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    echo "=== Journal Entries Table Columns ===\n\n";

    $columns = Schema::getColumnListing('journal_entries');
    print_r($columns);

    echo "\n\n=== Sample Voided Entry ===\n\n";
    $voidedEntry = DB::table('journal_entries')
        ->where('status', 'voided')
        ->first();

    if ($voidedEntry) {
        print_r($voidedEntry);
    } else {
        echo "No voided entries found.\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
