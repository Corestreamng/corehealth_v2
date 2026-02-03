<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "Checking fixed_asset_depreciations table structure\n";
echo str_repeat("=", 70) . "\n\n";

$columns = DB::select("SHOW COLUMNS FROM fixed_asset_depreciations");

foreach ($columns as $column) {
    echo "Column: {$column->Field}\n";
    echo "Type: {$column->Type}\n";
    echo "Null: {$column->Null}\n";
    echo "Default: " . ($column->Default ?? 'NULL') . "\n";
    echo str_repeat("-", 70) . "\n";
}
