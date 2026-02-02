<?php
/**
 * Debug script to find GL entries for Zenith Bank in January 2025
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Bank;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\BankReconciliation;
use Illuminate\Support\Facades\DB;

echo "========================================\n";
echo "GL ENTRIES DEBUG FOR ZENITH BANK - JAN 2025\n";
echo "========================================\n\n";

// 1. Find Zenith Bank
echo "1. FINDING ZENITH BANK\n";
echo "----------------------\n";
$bank = Bank::where('name', 'like', '%zenith%')->first();

if (!$bank) {
    echo "ERROR: Zenith bank not found!\n";
    echo "\nAll banks in system:\n";
    Bank::all()->each(function($b) {
        echo "  - ID: {$b->id}, Name: {$b->name}, Account ID: {$b->account_id}\n";
    });
    exit;
}

echo "Found: ID={$bank->id}, Name={$bank->name}, Account ID={$bank->account_id}\n\n";

// 2. Get the linked GL account
echo "2. LINKED GL ACCOUNT\n";
echo "--------------------\n";
if (!$bank->account_id) {
    echo "ERROR: Bank has no linked GL account!\n";
    exit;
}

$account = Account::find($bank->account_id);
if (!$account) {
    echo "ERROR: GL Account ID {$bank->account_id} not found in accounts table!\n";
    exit;
}

echo "Account: ID={$account->id}, Code={$account->account_code}, Name={$account->account_name}\n";
echo "Type: {$account->account_type}, Sub-type: " . ($account->sub_type ?? 'N/A') . "\n\n";

// 3. Check Journal Entry Lines directly
echo "3. JOURNAL ENTRY LINES (Jan 2025)\n";
echo "---------------------------------\n";
$journalLines = JournalEntryLine::where('account_id', $account->id)
    ->whereHas('journalEntry', function($q) {
        $q->whereYear('entry_date', 2025)
          ->whereMonth('entry_date', 1);
    })
    ->with('journalEntry')
    ->orderBy('id')
    ->get();

echo "Found {$journalLines->count()} journal entry lines for Jan 2025\n";
if ($journalLines->count() > 0) {
    echo "\nFirst 15 lines:\n";
    foreach ($journalLines->take(15) as $line) {
        $je = $line->journalEntry;
        echo "  [{$je->entry_date}] JE#{$je->entry_number}, Debit: " . number_format($line->debit, 2) . ", Credit: " . number_format($line->credit, 2) . "\n";
        echo "    Desc: " . substr($line->narration ?? $je->description, 0, 60) . "\n";
    }
    echo "\nTotals for Jan 2025:\n";
    echo "  Total Debits:  " . number_format($journalLines->sum('debit'), 2) . "\n";
    echo "  Total Credits: " . number_format($journalLines->sum('credit'), 2) . "\n";
}

// 4. Check what date range has data for this account
echo "\n4. DATE RANGE WITH DATA FOR THIS ACCOUNT\n";
echo "-----------------------------------------\n";
$dateRange = DB::table('journal_entry_lines as jel')
    ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
    ->where('jel.account_id', $account->id)
    ->selectRaw('MIN(je.entry_date) as min_date, MAX(je.entry_date) as max_date, COUNT(*) as total')
    ->first();

echo "Min Date: {$dateRange->min_date}\n";
echo "Max Date: {$dateRange->max_date}\n";
echo "Total Entries: {$dateRange->total}\n";

// 5. Monthly breakdown
echo "\n5. MONTHLY BREAKDOWN (Last 12 months of data)\n";
echo "----------------------------------------------\n";
$monthlyData = DB::table('journal_entry_lines as jel')
    ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
    ->where('jel.account_id', $account->id)
    ->selectRaw("DATE_FORMAT(je.entry_date, '%Y-%m') as month, COUNT(*) as count, SUM(jel.debit) as debits, SUM(jel.credit) as credits")
    ->groupByRaw("DATE_FORMAT(je.entry_date, '%Y-%m')")
    ->orderByDesc('month')
    ->limit(12)
    ->get();

foreach ($monthlyData as $row) {
    echo "  {$row->month}: {$row->count} entries, Debits: " . number_format($row->debits, 2) . ", Credits: " . number_format($row->credits, 2) . "\n";
}

// 6. Check existing reconciliations
echo "\n6. EXISTING RECONCILIATIONS FOR ZENITH BANK\n";
echo "--------------------------------------------\n";
$reconciliations = BankReconciliation::where('bank_id', $bank->id)
    ->orderByDesc('statement_date')
    ->get();

echo "Found {$reconciliations->count()} reconciliations\n";
foreach ($reconciliations as $recon) {
    echo "  [{$recon->statement_date}] #{$recon->reconciliation_number}, Status: {$recon->status}\n";
    echo "    Period: {$recon->statement_period_from} to {$recon->statement_period_to}\n";
    echo "    Statement Bal: " . number_format($recon->statement_closing_balance, 2) . ", GL Bal: " . number_format($recon->gl_closing_balance, 2) . "\n";
}

// 7. Check Journal Entries for Jan 2025 (all)
echo "\n7. ALL JOURNAL ENTRIES FOR JANUARY 2025\n";
echo "----------------------------------------\n";
$allJanEntries = JournalEntry::whereYear('entry_date', 2025)
    ->whereMonth('entry_date', 1)
    ->count();

echo "Total Journal Entries for Jan 2025: {$allJanEntries}\n";

// 8. Check bank-related accounts
echo "\n8. ACCOUNTS WITH JOURNAL ENTRIES\n";
echo "--------------------------------\n";
$accountIds = DB::table('journal_entry_lines')
    ->distinct()
    ->pluck('account_id')
    ->toArray();

echo "Accounts with entries: " . implode(', ', $accountIds) . "\n\n";

$bankAccounts = Account::whereIn('id', $accountIds)->get();
foreach ($bankAccounts as $acc) {
    $entryCount = DB::table('journal_entry_lines')
        ->where('account_id', $acc->id)
        ->count();
    echo "  [{$acc->code}] {$acc->name}: {$entryCount} entries\n";
}

// Check if account 79 should have a different ID
echo "\n9. ALL BANK ACCOUNTS IN SYSTEM\n";
echo "------------------------------\n";
$allBankAccts = Account::where('is_bank_account', true)->get();
foreach ($allBankAccts as $acc) {
    $cnt = DB::table('journal_entry_lines')->where('account_id', $acc->id)->count();
    echo "  [{$acc->id}] {$acc->code} - {$acc->name}: {$cnt} JE lines\n";
}

// 9. Check the bank reconciliation edit view query
echo "\n9. SIMULATING RECONCILIATION EDIT VIEW QUERY\n";
echo "---------------------------------------------\n";
$latestRecon = BankReconciliation::where('bank_id', $bank->id)->first();
if ($latestRecon) {
    echo "Using reconciliation: {$latestRecon->reconciliation_number}\n";
    echo "Period: {$latestRecon->statement_period_from} to {$latestRecon->statement_period_to}\n";

    // This is the query used in the edit view to get GL items
    $glItems = DB::table('journal_entry_lines as jel')
        ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
        ->where('jel.account_id', $latestRecon->account_id)
        ->whereBetween('je.entry_date', [$latestRecon->statement_period_from, $latestRecon->statement_period_to])
        ->select('je.entry_date', 'je.entry_number', 'jel.debit', 'jel.credit', 'jel.narration')
        ->orderBy('je.entry_date')
        ->get();

    echo "GL Items found for period: {$glItems->count()}\n";
    if ($glItems->count() > 0) {
        echo "\nFirst 10:\n";
        foreach ($glItems->take(10) as $item) {
            echo "  [{$item->entry_date}] #{$item->entry_number}, Dr: " . number_format($item->debit, 2) . ", Cr: " . number_format($item->credit, 2) . "\n";
        }
    }
} else {
    echo "No reconciliation found for Zenith bank\n";
}

// 10. Raw SQL check of what's in bank_reconciliation_items
echo "\n10. BANK_RECONCILIATION_ITEMS TABLE CHECK\n";
echo "------------------------------------------\n";
if ($latestRecon) {
    $items = DB::table('bank_reconciliation_items')
        ->where('reconciliation_id', $latestRecon->id)
        ->get();

    echo "Items in bank_reconciliation_items for this recon: {$items->count()}\n";

    $glItems = $items->where('source', 'gl');
    $stmtItems = $items->where('source', 'statement');

    echo "  - GL source items: " . $glItems->count() . "\n";
    echo "  - Statement source items: " . $stmtItems->count() . "\n";
}

echo "\n========================================\n";
echo "DEBUG COMPLETE\n";
echo "========================================\n";
