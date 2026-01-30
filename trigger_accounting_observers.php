<?php

/**
 * Trigger Accounting Observers for Existing Records
 *
 * This script re-saves existing records to trigger their observers,
 * which will create journal entries for historical transactions.
 *
 * Usage: php trigger_accounting_observers.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=================================================\n";
echo "Triggering Accounting Observers for Existing Data\n";
echo "=================================================\n\n";

// Check if we have an open fiscal period first
$currentPeriod = \App\Models\Accounting\AccountingPeriod::where('status', 'open')
    ->where('start_date', '<=', now())
    ->where('end_date', '>=', now())
    ->first();

if (!$currentPeriod) {
    echo "WARNING: No open accounting period found for today!\n";
    echo "Journal entries will need a valid period to post to.\n\n";
}

// Track statistics
$stats = [
    'payments' => ['processed' => 0, 'success' => 0, 'errors' => 0],
    'expenses' => ['processed' => 0, 'success' => 0, 'errors' => 0],
    'purchase_orders' => ['processed' => 0, 'success' => 0, 'errors' => 0],
    'payroll_batches' => ['processed' => 0, 'success' => 0, 'errors' => 0],
];

// =====================================
// 1. Process Payments
// =====================================
echo "Processing Payments...\n";

if (class_exists(\App\Models\Payment::class)) {
    try {
        $payments = \App\Models\Payment::where('status', 'completed')
            ->whereDoesntHave('journalEntries') // Only those without journal entries
            ->get();

        echo "  Found {$payments->count()} payments without journal entries\n";

        foreach ($payments as $payment) {
            try {
                $stats['payments']['processed']++;

                // Dispatch the created event manually
                event('eloquent.created: ' . get_class($payment), $payment);

                // Or alternatively, touch to trigger updated
                // $payment->touch();

                $stats['payments']['success']++;
                echo ".";
            } catch (\Exception $e) {
                $stats['payments']['errors']++;
                Log::error("Observer trigger failed for Payment #{$payment->id}: " . $e->getMessage());
            }
        }
        echo "\n";
    } catch (\Exception $e) {
        echo "  Payment model not available or error: {$e->getMessage()}\n";
    }
} else {
    echo "  Payment model not found, skipping...\n";
}

// =====================================
// 2. Process Expenses
// =====================================
echo "\nProcessing Expenses...\n";

if (class_exists(\App\Models\Expense::class)) {
    try {
        $expenses = \App\Models\Expense::where('status', 'approved')
            ->whereDoesntHave('journalEntries')
            ->get();

        echo "  Found {$expenses->count()} expenses without journal entries\n";

        foreach ($expenses as $expense) {
            try {
                $stats['expenses']['processed']++;
                event('eloquent.created: ' . get_class($expense), $expense);
                $stats['expenses']['success']++;
                echo ".";
            } catch (\Exception $e) {
                $stats['expenses']['errors']++;
                Log::error("Observer trigger failed for Expense #{$expense->id}: " . $e->getMessage());
            }
        }
        echo "\n";
    } catch (\Exception $e) {
        echo "  Expense model not available or error: {$e->getMessage()}\n";
    }
} else {
    echo "  Expense model not found, skipping...\n";
}

// =====================================
// 3. Process Purchase Orders
// =====================================
echo "\nProcessing Purchase Orders...\n";

if (class_exists(\App\Models\Inventory\PurchaseOrder::class)) {
    try {
        $purchaseOrders = \App\Models\Inventory\PurchaseOrder::where('status', 'received')
            ->whereDoesntHave('journalEntries')
            ->get();

        echo "  Found {$purchaseOrders->count()} purchase orders without journal entries\n";

        foreach ($purchaseOrders as $po) {
            try {
                $stats['purchase_orders']['processed']++;
                event('eloquent.created: ' . get_class($po), $po);
                $stats['purchase_orders']['success']++;
                echo ".";
            } catch (\Exception $e) {
                $stats['purchase_orders']['errors']++;
                Log::error("Observer trigger failed for PO #{$po->id}: " . $e->getMessage());
            }
        }
        echo "\n";
    } catch (\Exception $e) {
        echo "  PurchaseOrder model not available or error: {$e->getMessage()}\n";
    }
} else {
    echo "  PurchaseOrder model not found, skipping...\n";
}

// =====================================
// 4. Process Payroll Batches
// =====================================
echo "\nProcessing Payroll Batches...\n";

if (class_exists(\App\Models\HR\PayrollBatch::class)) {
    try {
        $payrollBatches = \App\Models\HR\PayrollBatch::where('status', 'approved')
            ->whereDoesntHave('journalEntries')
            ->get();

        echo "  Found {$payrollBatches->count()} payroll batches without journal entries\n";

        foreach ($payrollBatches as $batch) {
            try {
                $stats['payroll_batches']['processed']++;
                event('eloquent.created: ' . get_class($batch), $batch);
                $stats['payroll_batches']['success']++;
                echo ".";
            } catch (\Exception $e) {
                $stats['payroll_batches']['errors']++;
                Log::error("Observer trigger failed for PayrollBatch #{$batch->id}: " . $e->getMessage());
            }
        }
        echo "\n";
    } catch (\Exception $e) {
        echo "  PayrollBatch model not available or error: {$e->getMessage()}\n";
    }
} else {
    echo "  PayrollBatch model not found, skipping...\n";
}

// =====================================
// Summary
// =====================================
echo "\n=================================================\n";
echo "SUMMARY\n";
echo "=================================================\n";

foreach ($stats as $type => $data) {
    $label = ucwords(str_replace('_', ' ', $type));
    echo "{$label}:\n";
    echo "  Processed: {$data['processed']}\n";
    echo "  Success:   {$data['success']}\n";
    echo "  Errors:    {$data['errors']}\n";
}

// Check how many journal entries were created
$journalCount = \App\Models\Accounting\JournalEntry::count();
echo "\nTotal Journal Entries in System: {$journalCount}\n";

echo "\nDone!\n";
