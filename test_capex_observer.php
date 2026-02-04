<?php
/**
 * Test CAPEX Expense Observer
 *
 * Tests that journal entries are created when CAPEX expenses are:
 * 1. Created with approved status
 * 2. Updated from pending to approved status
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CapexProjectExpense;
use Illuminate\Support\Facades\DB;

echo "=================================================\n";
echo "Testing CAPEX Expense Observer\n";
echo "=================================================\n\n";

// Test 1: Update existing expense from pending to approved
echo "Test 1: Update expense #4 from pending to approved\n";

$expense = CapexProjectExpense::find(4);
if ($expense) {
    echo "  Before: status={$expense->status}, journal_entry_id=" . ($expense->journal_entry_id ?? 'NULL') . "\n";

    $expense->status = 'approved';
    $expense->bank_id = 1;
    $expense->save();

    // Refresh from database
    $expense->refresh();
    echo "  After:  status={$expense->status}, journal_entry_id=" . ($expense->journal_entry_id ?? 'NULL') . "\n";

    if ($expense->journal_entry_id) {
        echo "  ✓ SUCCESS: Journal entry #{$expense->journal_entry_id} created!\n";

        // Show journal entry details
        $je = DB::table('journal_entries')->where('id', $expense->journal_entry_id)->first();
        echo "  Entry: {$je->entry_number} - {$je->description}\n";

        // Show lines
        $lines = DB::table('journal_entry_lines as jel')
            ->join('accounts as a', 'jel.account_id', '=', 'a.id')
            ->where('jel.journal_entry_id', $expense->journal_entry_id)
            ->get(['a.code', 'a.name', 'jel.debit', 'jel.credit']);

        foreach ($lines as $line) {
            $type = $line->debit > 0 ? 'DR' : 'CR';
            $amount = $line->debit > 0 ? $line->debit : $line->credit;
            echo "    {$type}: {$line->code} ({$line->name}) = ₦" . number_format($amount, 2) . "\n";
        }
    } else {
        echo "  ✗ FAILED: No journal entry created\n";
    }
} else {
    echo "  Expense #4 not found\n";
}

echo "\n";

// Test 2: Create new expense with approved status
echo "Test 2: Create new expense with approved status\n";

$newExpense = CapexProjectExpense::create([
    'project_id' => 9,
    'expense_date' => now()->toDateString(),
    'amount' => 1500.00,
    'description' => 'Test Observer - New Expense',
    'payment_method' => 'bank_transfer',
    'bank_id' => 1,
    'status' => 'approved',
]);

echo "  Created expense #{$newExpense->id}\n";
echo "  journal_entry_id=" . ($newExpense->journal_entry_id ?? 'NULL') . "\n";

if ($newExpense->journal_entry_id) {
    echo "  ✓ SUCCESS: Journal entry #{$newExpense->journal_entry_id} created on insert!\n";
} else {
    echo "  ✗ FAILED: No journal entry created on insert\n";
}

echo "\n=================================================\n";
echo "Tests Complete\n";
echo "=================================================\n";
