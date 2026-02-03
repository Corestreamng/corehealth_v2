<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\FixedAsset;

// Check latest fixed asset
$asset = FixedAsset::latest()->first();
if ($asset) {
    echo "Latest Asset: {$asset->name} (ID: {$asset->id})\n";
    echo "Asset Number: {$asset->asset_number}\n\n";
}

// Check for journal entries
$je = JournalEntry::where('reference_type', FixedAsset::class)
    ->latest()
    ->first();

if ($je) {
    echo "Latest Fixed Asset JE:\n";
    echo "  Entry Number: {$je->entry_number}\n";
    echo "  Description: {$je->description}\n";
    echo "  Reference ID: {$je->reference_id}\n";
    echo "  Status: {$je->status}\n";
    echo "  Lines:\n";
    foreach ($je->lines as $line) {
        echo "    - {$line->account->code}: DR {$line->debit} / CR {$line->credit}\n";
    }
} else {
    echo "No Fixed Asset JE found\n";
}

// Check if observer is registered
echo "\nChecking observers...\n";
$dispatcher = app('events');
$listeners = $dispatcher->getListeners('eloquent.created: ' . FixedAsset::class);
echo "Listeners for FixedAsset created event: " . count($listeners) . "\n";
foreach ($listeners as $listener) {
    echo "  - " . get_class($listener[0]) . "\n";
}
