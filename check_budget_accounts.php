<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Accounting\Budget;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Support\Facades\DB;

echo "=== BUDGET vs ACTUAL ACCOUNTS COMPARISON ===\n\n";

$budget = Budget::with(['items.account'])->find(1);

if (!$budget) {
    echo "Budget not found!\n";
    exit;
}

echo "Budget: {$budget->budget_name}\n";
echo "Year: {$budget->year}\n\n";

echo "1. ACCOUNTS IN BUDGET:\n";
echo str_repeat("-", 80) . "\n";
$budgetAccountIds = $budget->items->pluck('account_id')->toArray();
foreach ($budget->items as $item) {
    echo "ID: {$item->account_id} - {$item->account->code} - {$item->account->name}\n";
    echo "  Budgeted: ₦" . number_format($item->budgeted_amount, 2) . "\n";
}

echo "\n2. ACCOUNTS WITH ACTUAL SPENDING (2026):\n";
echo str_repeat("-", 80) . "\n";
$actualSpending = JournalEntryLine::select('account_id', DB::raw('SUM(debit) as total_debit'))
    ->whereHas('journalEntry', function($q) use ($budget) {
        $q->where('status', 'posted')
          ->whereYear('entry_date', $budget->year);
    })
    ->where('debit', '>', 0)
    ->groupBy('account_id')
    ->with('account')
    ->get();

$totalActual = 0;
$matchingAccounts = [];
$nonMatchingAccounts = [];

foreach ($actualSpending as $spending) {
    $totalActual += $spending->total_debit;
    $inBudget = in_array($spending->account_id, $budgetAccountIds) ? '✅ IN BUDGET' : '❌ NOT IN BUDGET';

    echo "ID: {$spending->account_id} - {$spending->account->code} - {$spending->account->name}\n";
    echo "  Actual: ₦" . number_format($spending->total_debit, 2) . " {$inBudget}\n";

    if (in_array($spending->account_id, $budgetAccountIds)) {
        $matchingAccounts[] = $spending;
    } else {
        $nonMatchingAccounts[] = $spending;
    }
}

echo "\n3. SUMMARY:\n";
echo str_repeat("=", 80) . "\n";
echo "Total Budget Accounts: " . count($budgetAccountIds) . "\n";
echo "Total Accounts with Spending: " . $actualSpending->count() . "\n";
echo "Matching Accounts: " . count($matchingAccounts) . "\n";
echo "Non-Matching Accounts: " . count($nonMatchingAccounts) . "\n\n";

echo "Total Actual Spending (ALL accounts): ₦" . number_format($totalActual, 2) . "\n";
$matchingTotal = collect($matchingAccounts)->sum('total_debit');
echo "Total Spending (BUDGET accounts only): ₦" . number_format($matchingTotal, 2) . "\n";
$nonMatchingTotal = collect($nonMatchingAccounts)->sum('total_debit');
echo "Total Spending (NON-BUDGET accounts): ₦" . number_format($nonMatchingTotal, 2) . "\n\n";

if (count($nonMatchingAccounts) > 0) {
    echo "⚠️ ISSUE FOUND:\n";
    echo "The budget is tracking specific accounts, but actual spending occurred\n";
    echo "in different accounts that are NOT part of this budget.\n\n";
    echo "NON-BUDGET ACCOUNTS WITH SPENDING:\n";
    foreach ($nonMatchingAccounts as $spending) {
        echo "  - {$spending->account->code} - {$spending->account->name}: ₦" . number_format($spending->total_debit, 2) . "\n";
    }
    echo "\nSOLUTION: Add these accounts to your budget or create separate budgets for them.\n";
}

echo "\n=== END ===\n";
