<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\AccountingPeriod;
use App\Services\Accounting\ReportService;

echo "=== GENERAL LEDGER DEBUG ===\n\n";

// 1. Check if we have accounts
$accountCount = Account::count();
echo "1. Total Accounts: $accountCount\n";

$activeAccounts = Account::where('is_active', true)->count();
echo "   Active Accounts: $activeAccounts\n\n";

// 2. Check if we have journal entries
$journalCount = JournalEntry::count();
echo "2. Total Journal Entries: $journalCount\n";

$postedJournals = JournalEntry::where('status', JournalEntry::STATUS_POSTED)->count();
echo "   Posted Journal Entries: $postedJournals\n\n";

// 3. Check journal entry lines
$lineCount = JournalEntryLine::count();
echo "3. Total Journal Entry Lines: $lineCount\n\n";

// 4. Get sample accounts with activity
echo "4. Accounts with Journal Lines:\n";
$accountsWithLines = Account::whereHas('journalLines')->withCount('journalLines')->get();
foreach ($accountsWithLines->take(10) as $account) {
    echo "   - [{$account->code}] {$account->name}: {$account->journal_lines_count} lines\n";
}
echo "\n";

// 5. Get date range of journal entries
echo "5. Journal Entry Date Range:\n";
$firstEntry = JournalEntry::where('status', JournalEntry::STATUS_POSTED)->orderBy('entry_date', 'asc')->first();
$lastEntry = JournalEntry::where('status', JournalEntry::STATUS_POSTED)->orderBy('entry_date', 'desc')->first();
if ($firstEntry && $lastEntry) {
    echo "   First Posted Entry: {$firstEntry->entry_date->format('Y-m-d')} ({$firstEntry->entry_number})\n";
    echo "   Last Posted Entry: {$lastEntry->entry_date->format('Y-m-d')} ({$lastEntry->entry_number})\n";
} else {
    echo "   No posted entries found!\n";
}
echo "\n";

// 6. Test the ReportService query for a specific account
if ($accountsWithLines->count() > 0) {
    $testAccount = $accountsWithLines->first();
    echo "6. Testing ReportService for account [{$testAccount->code}] {$testAccount->name}:\n";

    $fromDate = now()->startOfMonth()->format('Y-m-d');
    $toDate = now()->format('Y-m-d');
    echo "   Date Range: $fromDate to $toDate\n";

    // Check lines directly
    $directLines = JournalEntryLine::where('account_id', $testAccount->id)
        ->whereHas('journalEntry', function ($q) use ($fromDate, $toDate) {
            $q->where('status', JournalEntry::STATUS_POSTED)
                ->whereBetween('entry_date', [$fromDate, $toDate]);
        })
        ->count();
    echo "   Direct query lines (this month): $directLines\n";

    // Try with a wider date range
    $fromDateWide = '2020-01-01';
    $toDateWide = now()->format('Y-m-d');
    $wideDateLines = JournalEntryLine::where('account_id', $testAccount->id)
        ->whereHas('journalEntry', function ($q) use ($fromDateWide, $toDateWide) {
            $q->where('status', JournalEntry::STATUS_POSTED)
                ->whereBetween('entry_date', [$fromDateWide, $toDateWide]);
        })
        ->count();
    echo "   Wide date range lines (2020-now): $wideDateLines\n";

    // Check without date filter
    $allLines = JournalEntryLine::where('account_id', $testAccount->id)
        ->whereHas('journalEntry', function ($q) {
            $q->where('status', JournalEntry::STATUS_POSTED);
        })
        ->count();
    echo "   All posted lines (no date filter): $allLines\n";

    // Check all lines regardless of status
    $allLinesAnyStatus = JournalEntryLine::where('account_id', $testAccount->id)->count();
    echo "   All lines (any status): $allLinesAnyStatus\n";

    // Check journal entry statuses
    echo "\n7. Journal Entry Status Distribution:\n";
    $statuses = JournalEntry::select('status', \DB::raw('count(*) as count'))
        ->groupBy('status')
        ->get();
    foreach ($statuses as $s) {
        echo "   - {$s->status}: {$s->count}\n";
    }

    // Now test the actual service
    echo "\n8. Testing ReportService->generateGeneralLedger():\n";
    try {
        $reportService = app(ReportService::class);
        $report = $reportService->generateGeneralLedger($testAccount->id, $fromDateWide, $toDateWide);

        echo "   Opening Balance: " . number_format($report['opening_balance'], 2) . "\n";
        echo "   Total Debit: " . number_format($report['total_debit'], 2) . "\n";
        echo "   Total Credit: " . number_format($report['total_credit'], 2) . "\n";
        echo "   Closing Balance: " . number_format($report['closing_balance'], 2) . "\n";
        echo "   Transaction Count: " . count($report['transactions']) . "\n";

        if (count($report['transactions']) > 0) {
            echo "\n   Sample Transactions:\n";
            foreach (array_slice($report['transactions'], 0, 3) as $t) {
                echo "   - {$t['date']} | {$t['entry_number']} | Dr: {$t['debit']} | Cr: {$t['credit']}\n";
            }
        }
    } catch (\Exception $e) {
        echo "   ERROR: " . $e->getMessage() . "\n";
    }
}

// 9. Check if the controller default dates might be the issue
echo "\n9. Default Date Range in Controller:\n";
$defaultStart = now()->startOfMonth()->format('Y-m-d');
$defaultEnd = now()->format('Y-m-d');
echo "   Start: $defaultStart\n";
echo "   End: $defaultEnd\n";

// Count entries in this range
$entriesInRange = JournalEntry::where('status', JournalEntry::STATUS_POSTED)
    ->whereBetween('entry_date', [$defaultStart, $defaultEnd])
    ->count();
echo "   Posted entries in this range: $entriesInRange\n";

echo "\n=== DEBUG COMPLETE ===\n";
