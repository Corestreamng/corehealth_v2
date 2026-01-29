<?php

/**
 * Re-trigger Accounting Observers for Existing Records
 *
 * This script manually calls the observer created() method for existing records
 * that should have journal entries but don't yet (because observers were added later).
 *
 * Usage: php trigger_observers_v2.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Payment;
use App\Models\Expense;
use App\Models\Accounting\JournalEntry;
use App\Observers\Accounting\PaymentObserver;
use App\Observers\Accounting\ExpenseObserver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=================================================\n";
echo "Re-triggering Accounting Observers\n";
echo "=================================================\n\n";

// Check if we have an open fiscal period
$currentPeriod = \App\Models\Accounting\AccountingPeriod::where('status', 'open')
    ->where('start_date', '<=', now())
    ->where('end_date', '>=', now())
    ->first();

if (!$currentPeriod) {
    echo "WARNING: No open accounting period found for today!\n";
    echo "Journal entries need a valid period. Opening January 2026 period...\n\n";

    // Try to open January 2026 period if it exists
    $janPeriod = \App\Models\Accounting\AccountingPeriod::where('period_number', 1)
        ->whereYear('start_date', 2026)
        ->first();

    if ($janPeriod) {
        $janPeriod->update(['status' => 'open']);
        echo "Opened period: {$janPeriod->period_name}\n\n";
    }
}

$stats = [
    'payments' => ['found' => 0, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'errors' => 0],
    'expenses' => ['found' => 0, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'errors' => 0],
];

// =====================================
// 1. Process Payments
// =====================================
echo "Processing Payments...\n";

try {
    // Get all payments (payments don't have a status column - they're complete when they exist)
    $payments = Payment::all();
    $stats['payments']['found'] = $payments->count();
    echo "  Found {$payments->count()} payments\n";

    $observer = new PaymentObserver();

    foreach ($payments as $payment) {
        $stats['payments']['processed']++;

        // Check if journal entry already exists for this payment
        $existingEntry = JournalEntry::where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->exists();

        if ($existingEntry) {
            $stats['payments']['skipped']++;
            echo "s";
            continue;
        }

        try {
            // Call the observer's created method directly
            $observer->created($payment);
            $stats['payments']['success']++;
            echo ".";
        } catch (\Exception $e) {
            $stats['payments']['errors']++;
            echo "E";
            Log::error("Observer trigger failed for Payment #{$payment->id}: " . $e->getMessage());
        }
    }
    echo "\n";
} catch (\Exception $e) {
    echo "  Error processing payments: {$e->getMessage()}\n";
}

// =====================================
// 2. Process Expenses
// =====================================
echo "\nProcessing Expenses...\n";

try {
    // Get all approved expenses
    $expenses = Expense::where('status', 'approved')->get();
    $stats['expenses']['found'] = $expenses->count();
    echo "  Found {$expenses->count()} approved expenses\n";

    $observer = new ExpenseObserver();

    foreach ($expenses as $expense) {
        $stats['expenses']['processed']++;

        // Check if journal entry already exists for this expense
        $existingEntry = JournalEntry::where('reference_type', Expense::class)
            ->where('reference_id', $expense->id)
            ->exists();

        if ($existingEntry) {
            $stats['expenses']['skipped']++;
            echo "s";
            continue;
        }

        try {
            // Expense observer uses 'updated' - we need to call it differently
            // Let's directly call the protected method using reflection
            $reflectionMethod = new \ReflectionMethod($observer, 'createExpenseJournalEntry');
            $reflectionMethod->setAccessible(true);
            $reflectionMethod->invoke($observer, $expense);
            $stats['expenses']['success']++;
            echo ".";
        } catch (\Exception $e) {
            $stats['expenses']['errors']++;
            echo "E";
            Log::error("Observer trigger failed for Expense #{$expense->id}: " . $e->getMessage());
        }
    }
    echo "\n";
} catch (\Exception $e) {
    echo "  Error processing expenses: {$e->getMessage()}\n";
}

// =====================================
// Summary
// =====================================
echo "\n=================================================\n";
echo "SUMMARY\n";
echo "=================================================\n";

foreach ($stats as $type => $data) {
    $label = ucwords(str_replace('_', ' ', $type));
    echo "\n{$label}:\n";
    echo "  Found:     {$data['found']}\n";
    echo "  Processed: {$data['processed']}\n";
    echo "  Success:   {$data['success']}\n";
    echo "  Skipped:   {$data['skipped']} (already have journal entries)\n";
    echo "  Errors:    {$data['errors']}\n";
}

// Check how many journal entries were created
$journalCount = JournalEntry::count();
echo "\n=================================================\n";
echo "Total Journal Entries in System: {$journalCount}\n";
echo "=================================================\n";

echo "\nDone!\n";

// Show any logged errors
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    if (preg_match_all('/Observer trigger failed.*$/m', $logContent, $matches)) {
        echo "\nRecent errors logged:\n";
        foreach (array_slice($matches[0], -5) as $error) {
            echo "  - {$error}\n";
        }
    }
}
