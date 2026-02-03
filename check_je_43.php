<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\FixedAsset;

try {
    echo "=== Checking JE #43 Lines ===\n\n";

    $je = JournalEntry::with('lines.account')->find(43);

    if (!$je) {
        echo "JE #43 not found!\n";
        exit;
    }

    echo "Entry: {$je->entry_number}\n";
    echo "Description: {$je->description}\n";
    echo "Lines: {$je->lines->count()}\n\n";

    $totalDR = 0;
    $totalCR = 0;

    foreach ($je->lines as $line) {
        echo "Line {$line->line_number}:\n";
        echo "  Account: {$line->account->account_name} ({$line->account->code})\n";
        echo "  Debit: " . ($line->debit ?: '0.00') . "\n";
        echo "  Credit: " . ($line->credit ?: '0.00') . "\n";
        echo "  Narration: {$line->narration}\n\n";

        $totalDR += $line->debit;
        $totalCR += $line->credit;
    }

    echo "Total DR: ₦" . number_format($totalDR, 2) . "\n";
    echo "Total CR: ₦" . number_format($totalCR, 2) . "\n";
    echo "Balanced: " . ($totalDR == $totalCR ? 'YES' : 'NO') . "\n\n";

    echo "=== Asset #3 Details ===\n";
    $asset = FixedAsset::find(3);
    echo "Accumulated Depreciation: ₦" . number_format($asset->accumulated_depreciation, 2) . "\n";
    echo "Total Cost: ₦" . number_format($asset->total_cost, 2) . "\n\n";

    echo "=== Checking Gain/Loss Accounts ===\n";
    $gainAcc = \App\Models\Accounting\Account::where('code', '4200')->first();
    $lossAcc = \App\Models\Accounting\Account::where('code', '6900')->first();
    echo "Gain Account (4200): " . ($gainAcc ? $gainAcc->account_name : 'NOT FOUND') . "\n";
    echo "Loss Account (6900): " . ($lossAcc ? $lossAcc->account_name : 'NOT FOUND') . "\n";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
