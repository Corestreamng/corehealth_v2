<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountClass;
use App\Services\Accounting\ReportService;
use Illuminate\Support\Facades\DB;

echo "=== Profit & Loss Test Script ===\n\n";

// Check total entries
$totalEntries = JournalEntry::count();
echo "Total Journal Entries: {$totalEntries}\n";

// Check entry statuses
echo "\nEntry Status Breakdown:\n";
$statuses = DB::table('journal_entries')
    ->select('status', DB::raw('count(*) as count'))
    ->groupBy('status')
    ->get();

foreach ($statuses as $status) {
    echo "  {$status->status}: {$status->count}\n";
}

// Check date range of entries
$dateRange = DB::table('journal_entries')
    ->selectRaw('MIN(entry_date) as min_date, MAX(entry_date) as max_date')
    ->first();

echo "\nDate Range of Entries:\n";
echo "  Earliest: {$dateRange->min_date}\n";
echo "  Latest: {$dateRange->max_date}\n";

// Check income and expense accounts
$incomeClass = AccountClass::where('code', AccountClass::CODE_INCOME)->first();
$expenseClass = AccountClass::where('code', AccountClass::CODE_EXPENSE)->first();

if ($incomeClass) {
    $incomeAccounts = Account::whereHas('accountGroup', function($q) use ($incomeClass) {
        $q->where('account_class_id', $incomeClass->id);
    })->count();
    echo "\nIncome Accounts: {$incomeAccounts}\n";
}

if ($expenseClass) {
    $expenseAccounts = Account::whereHas('accountGroup', function($q) use ($expenseClass) {
        $q->where('account_class_id', $expenseClass->id);
    })->count();
    echo "Expense Accounts: {$expenseAccounts}\n";
}

// Test date range: 2025-01-01 to 2026-12-31
echo "\n=== Testing Profit & Loss for 2025-01-01 to 2026-12-31 ===\n";

$reportService = app(ReportService::class);
$report = $reportService->generateProfitAndLoss('2025-01-01', '2026-12-31');

echo "\nTotal Income: " . number_format($report['total_income'], 2) . "\n";
echo "Total Expenses: " . number_format($report['total_expenses'], 2) . "\n";
echo "Net Income: " . number_format($report['net_income'], 2) . "\n";

echo "\nIncome Details:\n";
if (!empty($report['income']['groups'])) {
    foreach ($report['income']['groups'] as $group) {
        echo "  {$group['name']}: " . number_format($group['total'], 2) . "\n";
        foreach ($group['accounts'] as $account) {
            echo "    {$account['code']} - {$account['name']}: " . number_format($account['balance'], 2) . "\n";
        }
    }
} else {
    echo "  No income data found\n";
}

echo "\nExpense Details:\n";
if (!empty($report['expenses']['groups'])) {
    foreach ($report['expenses']['groups'] as $group) {
        echo "  {$group['name']}: " . number_format($group['total'], 2) . "\n";
        foreach ($group['accounts'] as $account) {
            echo "    {$account['code']} - {$account['name']}: " . number_format($account['balance'], 2) . "\n";
        }
    }
} else {
    echo "  No expense data found\n";
}

// Check if there are any posted entries with income/expense accounts
echo "\n=== Checking Posted Entries ===\n";

$postedEntries = JournalEntry::where('status', JournalEntry::STATUS_POSTED)
    ->whereBetween('entry_date', ['2025-01-01', '2026-12-31'])
    ->count();

echo "Posted entries in date range: {$postedEntries}\n";

if ($postedEntries > 0) {
    // Check lines
    $incomeLines = DB::table('journal_entry_lines')
        ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
        ->join('accounts', 'journal_entry_lines.account_id', '=', 'accounts.id')
        ->join('account_groups', 'accounts.account_group_id', '=', 'account_groups.id')
        ->where('journal_entries.status', JournalEntry::STATUS_POSTED)
        ->where('account_groups.account_class_id', $incomeClass->id ?? 0)
        ->whereBetween('journal_entries.entry_date', ['2025-01-01', '2026-12-31'])
        ->count();

    echo "Income account lines: {$incomeLines}\n";

    $expenseLines = DB::table('journal_entry_lines')
        ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
        ->join('accounts', 'journal_entry_lines.account_id', '=', 'accounts.id')
        ->join('account_groups', 'accounts.account_group_id', '=', 'account_groups.id')
        ->where('journal_entries.status', JournalEntry::STATUS_POSTED)
        ->where('account_groups.account_class_id', $expenseClass->id ?? 0)
        ->whereBetween('journal_entries.entry_date', ['2025-01-01', '2026-12-31'])
        ->count();

    echo "Expense account lines: {$expenseLines}\n";
}

echo "\n=== Test Complete ===\n";
