<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\FixedAssetDisposal;
use App\Models\Accounting\JournalEntry;
use Illuminate\Support\Facades\DB;

try {
    echo "=== Investigating Fixed Asset #3 ===\n\n";

    $asset = FixedAsset::find(3);

    if (!$asset) {
        echo "Asset #3 not found!\n";
        exit;
    }

    echo "Asset Details:\n";
    echo "  ID: {$asset->id}\n";
    echo "  Number: {$asset->asset_number}\n";
    echo "  Name: {$asset->name}\n";
    echo "  Status: {$asset->status}\n";
    echo "  Journal Entry ID: " . ($asset->journal_entry_id ?? 'NULL') . "\n";
    echo "  Disposal Date: " . ($asset->disposal_date ?? 'NULL') . "\n";

    echo "\n--- Acquisition Journal Entry ---\n";
    if ($asset->journal_entry_id) {
        $je = JournalEntry::find($asset->journal_entry_id);
        if ($je) {
            echo "  Entry #: {$je->entry_number}\n";
            echo "  Date: {$je->entry_date}\n";
            echo "  Status: {$je->status}\n";
            echo "  Description: {$je->description}\n";
            echo "  Lines: {$je->lines()->count()}\n";
        } else {
            echo "  Journal Entry #{$asset->journal_entry_id} NOT FOUND!\n";
        }
    } else {
        echo "  No acquisition journal entry linked!\n";
    }

    echo "\n--- Disposal Records ---\n";
    $disposals = FixedAssetDisposal::where('fixed_asset_id', $asset->id)->get();
    echo "  Found {$disposals->count()} disposal(s)\n\n";

    foreach ($disposals as $disposal) {
        echo "  Disposal ID: {$disposal->id}\n";
        echo "    Date: {$disposal->disposal_date}\n";
        echo "    Type: {$disposal->disposal_type}\n";
        echo "    Status: {$disposal->disposal_status}\n";
        echo "    Proceeds: ₦" . number_format($disposal->disposal_proceeds, 2) . "\n";
        echo "    Book Value: ₦" . number_format($disposal->book_value_at_disposal, 2) . "\n";
        echo "    Gain/Loss: ₦" . number_format($disposal->gain_loss_on_disposal, 2) . "\n";
        echo "    Journal Entry ID: " . ($disposal->journal_entry_id ?? 'NULL') . "\n";

        if ($disposal->journal_entry_id) {
            $je = JournalEntry::find($disposal->journal_entry_id);
            if ($je) {
                echo "    JE Entry #: {$je->entry_number}\n";
                echo "    JE Status: {$je->status}\n";
                echo "    JE Lines: {$je->lines()->count()}\n";

                echo "    JE Details:\n";
                foreach ($je->lines as $line) {
                    $debit = $line->debit_amount > 0 ? number_format($line->debit_amount, 2) : '-';
                    $credit = $line->credit_amount > 0 ? number_format($line->credit_amount, 2) : '-';
                    echo "      DR: ₦{$debit} | CR: ₦{$credit} | {$line->account->account_name}\n";
                }
            } else {
                echo "    Journal Entry #{$disposal->journal_entry_id} NOT FOUND!\n";
            }
        } else {
            echo "    No disposal journal entry created yet!\n";
        }
        echo "\n";
    }

    echo "\n--- Related Journal Entries (by reference) ---\n";
    $relatedJEs = JournalEntry::where('reference_type', 'App\\Models\\Accounting\\FixedAsset')
        ->where('reference_id', $asset->id)
        ->orWhere('reference_type', 'fixed_asset_disposal')
        ->whereIn('reference_id', $disposals->pluck('id'))
        ->get();

    echo "  Found {$relatedJEs->count()} related journal entries\n\n";

    foreach ($relatedJEs as $je) {
        echo "  JE #{$je->id} - {$je->entry_number}\n";
        echo "    Type: {$je->reference_type}\n";
        echo "    Reference ID: {$je->reference_id}\n";
        echo "    Status: {$je->status}\n";
        echo "    Date: {$je->entry_date}\n";
        echo "    Description: {$je->description}\n";
        echo "\n";
    }

    echo "\n=== Database Raw Check ===\n";
    $rawDisposals = DB::table('fixed_asset_disposals')
        ->where('fixed_asset_id', 3)
        ->get();

    echo "Raw disposal records: {$rawDisposals->count()}\n";
    foreach ($rawDisposals as $d) {
        echo "  ID: {$d->id}, Status: {$d->status}, JE ID: " . ($d->journal_entry_id ?? 'NULL') . "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
