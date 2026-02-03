<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\JournalEntry;

echo "=== VERIFY FIXED ASSET JOURNAL ENTRIES ===\n\n";

// Get the last created asset
$asset = FixedAsset::with(['journalEntry.lines.account', 'category'])
    ->latest()
    ->first();

if (!$asset) {
    echo "No assets found.\n";
    exit;
}

echo "Asset: {$asset->asset_number} - {$asset->name}\n";
echo "Total Cost: ₦" . number_format($asset->total_cost, 2) . "\n";
echo "Journal Entry ID: " . ($asset->journal_entry_id ?? 'NULL') . "\n\n";

if ($asset->journalEntry) {
    echo "✅ ACQUISITION JOURNAL ENTRY FOUND:\n";
    echo "Entry Number: {$asset->journalEntry->entry_number}\n";
    echo "Entry Date: {$asset->journalEntry->entry_date}\n";
    echo "Reference: {$asset->journalEntry->reference_number}\n";
    echo "Status: {$asset->journalEntry->status}\n";
    echo "Description: {$asset->journalEntry->description}\n\n";

    echo "Journal Entry Lines:\n";
    $totalDebit = 0;
    $totalCredit = 0;

    foreach ($asset->journalEntry->lines as $line) {
        $type = $line->debit_amount > 0 ? 'DEBIT ' : 'CREDIT';
        $amount = $line->debit_amount > 0 ? $line->debit_amount : $line->credit_amount;

        echo "  {$type}: {$line->account->code} - {$line->account->name}\n";
        echo "           Amount: ₦" . number_format($amount, 2) . "\n";

        $totalDebit += $line->debit_amount;
        $totalCredit += $line->credit_amount;
    }

    echo "\nTotal Debits:  ₦" . number_format($totalDebit, 2) . "\n";
    echo "Total Credits: ₦" . number_format($totalCredit, 2) . "\n";

    if ($totalDebit == $totalCredit) {
        echo "✅ Journal Entry is BALANCED\n";
    } else {
        echo "❌ Journal Entry is UNBALANCED!\n";
    }

    // Verify correct accounts
    echo "\n=== ACCOUNT VERIFICATION ===\n";
    $debitLine = $asset->journalEntry->lines->where('debit_amount', '>', 0)->first();
    $creditLine = $asset->journalEntry->lines->where('credit_amount', '>', 0)->first();

    if ($debitLine && $debitLine->account_id == $asset->category->asset_account_id) {
        echo "✅ DEBIT to correct Fixed Asset account: {$debitLine->account->code} - {$debitLine->account->name}\n";
    } else {
        echo "❌ DEBIT to WRONG account!\n";
    }

    if ($creditLine) {
        echo "✅ CREDIT to Bank/Cash account: {$creditLine->account->code} - {$creditLine->account->name}\n";
    } else {
        echo "❌ No CREDIT line found!\n";
    }

} else {
    echo "❌ NO ACQUISITION JOURNAL ENTRY CREATED!\n";
    echo "The FixedAssetObserver may not be working.\n";
}

echo "\n=== DONE ===\n";
