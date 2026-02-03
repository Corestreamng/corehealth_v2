<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\FixedAssetDepreciation;

echo "Checking Depreciation Amounts for Asset #24\n";
echo str_repeat("=", 70) . "\n\n";

$asset = FixedAsset::find(24);
if (!$asset) {
    echo "Asset #24 not found\n";
    exit;
}

echo "Asset: {$asset->name}\n";
echo "Asset Number: {$asset->asset_number}\n";
echo "Accumulated Depreciation: ₦" . number_format($asset->accumulated_depreciation, 2) . "\n";
echo "Book Value: ₦" . number_format($asset->book_value, 2) . "\n\n";

echo "Depreciation Records:\n";
echo str_repeat("-", 70) . "\n";

$depreciations = FixedAssetDepreciation::where('fixed_asset_id', 24)
    ->orderBy('depreciation_date', 'desc')
    ->get();

foreach ($depreciations as $dep) {
    echo "ID: {$dep->id}\n";
    echo "Date: {$dep->depreciation_date->format('M d, Y')}\n";
    echo "Amount: ₦" . number_format($dep->amount, 2) . "\n";
    echo "Book Value After: ₦" . number_format($dep->book_value_after, 2) . "\n";
    echo "JE ID: " . ($dep->journal_entry_id ?? 'NULL') . "\n";
    echo str_repeat("-", 70) . "\n";
}

echo "\nTotal records: " . $depreciations->count() . "\n";
