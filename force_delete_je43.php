<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Accounting\JournalEntry;
use Illuminate\Support\Facades\DB;

echo "Checking for JE #43...\n";

$je = JournalEntry::withTrashed()->find(43);

if ($je) {
    echo "Found JE #43:\n";
    echo "  Entry Number: {$je->entry_number}\n";
    echo "  Deleted At: " . ($je->deleted_at ?? 'NULL') . "\n";

    echo "\nForce deleting...\n";
    $je->forceDelete();
    echo "✓ Force deleted JE #43\n";

    // Verify
    $check = JournalEntry::withTrashed()->find(43);
    if (!$check) {
        echo "✓ Verified: JE #43 is permanently deleted\n";
    } else {
        echo "⚠ Warning: JE #43 still exists\n";
    }
} else {
    echo "JE #43 not found\n";
}

echo "\nNow try recreating disposal JE...\n";
