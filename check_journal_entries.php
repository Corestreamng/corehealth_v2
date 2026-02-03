<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\JournalEntry;

echo "=== CHECK JOURNAL ENTRIES ===\n\n";

// Get latest asset
$asset = FixedAsset::latest()->first();
echo "Latest Asset: {$asset->asset_number} - ID: {$asset->id}\n";
echo "Journal Entry ID on asset: " . ($asset->journal_entry_id ?? 'NULL') . "\n\n";

// Look for JEs related to this asset
$jes = JournalEntry::where('reference_type', 'fixed_asset_acquisition')
    ->where('reference_id', $asset->id)
    ->get();

echo "Journal Entries found for this asset: {$jes->count()}\n\n";

if ($jes->count() > 0) {
    foreach ($jes as $je) {
        echo "JE #{$je->id} - {$je->entry_number}\n";
        echo "Reference: {$je->reference_number}\n";
        echo "Status: {$je->status}\n\n";
    }
} else {
    echo "❌ No journal entries found!\n";
}

// Check latest journal entries
echo "\nLatest 5 Journal Entries (all types):\n";
$latest = JournalEntry::latest()->take(5)->get();
foreach ($latest as $je) {
    echo "  JE #{$je->id} - {$je->entry_number} - {$je->reference_type} - {$je->description}\n";
}
