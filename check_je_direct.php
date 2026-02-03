<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\FixedAsset;

echo "=== Checking Journal Entries ===\n\n";

// Check asset ID 12
$asset = FixedAsset::find(12);
if ($asset) {
    echo "Asset 12: {$asset->name}\n";
    echo "  journal_entry_id: " . ($asset->journal_entry_id ?? 'NULL') . "\n\n";
}

// Check JE ID 15
$je = JournalEntry::find(15);
if ($je) {
    echo "JE 15:\n";
    echo "  Entry Number: {$je->entry_number}\n";
    echo "  Description: {$je->description}\n";
    echo "  Reference Type: {$je->reference_type}\n";
    echo "  Reference ID: {$je->reference_id}\n";
    echo "  Status: {$je->status}\n";
    echo "  Lines: " . $je->lines->count() . "\n";
} else {
    echo "JE 15 not found\n";
}

echo "\n";

// Query by reference
$je2 = JournalEntry::where('reference_type', FixedAsset::class)
    ->where('reference_id', 12)
    ->first();

if ($je2) {
    echo "Found JE by reference (asset 12):\n";
    echo "  ID: {$je2->id}\n";
    echo "  Entry Number: {$je2->entry_number}\n";
} else {
    echo "No JE found by reference\n";
}
