<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\FixedAssetDisposal;

try {
    echo "=== Checking Latest JE ===\n";
    $latestJE = JournalEntry::orderBy('id', 'desc')->first();
    echo "Latest JE: " . ($latestJE ? $latestJE->entry_number . " (ID: {$latestJE->id})" : 'none') . "\n";

    echo "\n=== Deleting Incorrect JE #43 ===\n";

    $je = JournalEntry::find(43);
    if ($je) {
        echo "Found JE: {$je->entry_number}\n";
        $je->lines()->delete();
        echo "Deleted lines\n";
        $je->delete();
        echo "Deleted JE\n";
    }

    echo "\n=== Clearing Disposal Link ===\n";
    $disposal = FixedAssetDisposal::find(4);
    $disposal->journal_entry_id = null;
    $disposal->save();
    echo "Disposal #4 JE link cleared\n";

    // Clear any cache
    \Illuminate\Support\Facades\Cache::flush();

    echo "\n=== Testing Entry Number Generation ===\n";
    $testNumber = JournalEntry::generateEntryNumber();
    echo "Next entry number will be: {$testNumber}\n";

    echo "\n=== Recreating JE ===\n";
    $observer = new \App\Observers\Accounting\FixedAssetDisposalObserver();
    $observer->created($disposal);

    $disposal->refresh();
    echo "New JE ID: " . ($disposal->journal_entry_id ?? 'FAILED') . "\n";

    if ($disposal->journal_entry_id) {
        $newJE = JournalEntry::with('lines.account')->find($disposal->journal_entry_id);
        echo "\nNew JE: {$newJE->entry_number}\n";
        echo "Lines: {$newJE->lines->count()}\n\n";

        $totalDR = 0;
        $totalCR = 0;

        foreach ($newJE->lines as $line) {
            $dr = $line->debit > 0 ? 'DR ₦' . number_format($line->debit, 2) : '';
            $cr = $line->credit > 0 ? 'CR ₦' . number_format($line->credit, 2) : '';
            echo "  {$line->account->code} - {$line->account->name}: {$dr}{$cr}\n";
            $totalDR += $line->debit;
            $totalCR += $line->credit;
        }

        echo "\nTotal DR: ₦" . number_format($totalDR, 2) . "\n";
        echo "Total CR: ₦" . number_format($totalCR, 2) . "\n";
        echo "Balanced: " . ($totalDR == $totalCR ? 'YES ✓' : 'NO ✗') . "\n";
    }

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
