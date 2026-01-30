<?php

/**
 * Re-trigger Accounting Observers for Existing Records
 *
 * This script manually calls the observer methods for existing records
 * that should have journal entries but don't yet (because observers were added later).
 *
 * Observers Covered:
 * - PaymentObserver (created)
 * - ExpenseObserver (updated - when approved)
 * - PayrollBatchObserver (updated - when approved or paid)
 * - PurchaseOrderObserver (updated - when received)
 * - PurchaseOrderPaymentObserver (created)
 * - HmoRemittanceObserver (created)
 * - ProductOrServiceRequestObserver (updated - when HMO validation approved)
 *
 * Usage: php trigger_observers_v2.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Payment;
use App\Models\Expense;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderPayment;
use App\Models\HmoRemittance;
use App\Models\ProductOrServiceRequest;
use App\Models\HR\PayrollBatch;
use App\Models\Accounting\JournalEntry;
use App\Observers\Accounting\PaymentObserver;
use App\Observers\Accounting\ExpenseObserver;
use App\Observers\Accounting\PayrollBatchObserver;
use App\Observers\Accounting\PurchaseOrderObserver;
use App\Observers\Accounting\PurchaseOrderPaymentObserver;
use App\Observers\Accounting\HmoRemittanceObserver;
use App\Observers\Accounting\ProductOrServiceRequestObserver;
use App\Services\Accounting\SubAccountService;
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
    'payroll_batches' => ['found' => 0, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'errors' => 0],
    'purchase_orders' => ['found' => 0, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'errors' => 0],
    'po_payments' => ['found' => 0, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'errors' => 0],
    'hmo_remittances' => ['found' => 0, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'errors' => 0],
    'hmo_claims' => ['found' => 0, 'processed' => 0, 'success' => 0, 'skipped' => 0, 'errors' => 0],
];

// Get SubAccountService for observers that need it
$subAccountService = app(SubAccountService::class);

// =====================================
// 1. Process Payments
// =====================================
echo "1. Processing Payments...\n";

try {
    $payments = Payment::all();
    $stats['payments']['found'] = $payments->count();
    echo "   Found {$payments->count()} payments\n";

    $observer = new PaymentObserver();

    foreach ($payments as $payment) {
        $stats['payments']['processed']++;

        $existingEntry = JournalEntry::where('reference_type', Payment::class)
            ->where('reference_id', $payment->id)
            ->exists();

        if ($existingEntry) {
            $stats['payments']['skipped']++;
            echo "s";
            continue;
        }

        try {
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
    echo "   Error processing payments: {$e->getMessage()}\n";
}

// =====================================
// 2. Process Expenses (Approved)
// =====================================
echo "\n2. Processing Expenses...\n";

try {
    $expenses = Expense::where('status', 'approved')->get();
    $stats['expenses']['found'] = $expenses->count();
    echo "   Found {$expenses->count()} approved expenses\n";

    $observer = new ExpenseObserver($subAccountService);

    foreach ($expenses as $expense) {
        $stats['expenses']['processed']++;

        $existingEntry = JournalEntry::where('reference_type', Expense::class)
            ->where('reference_id', $expense->id)
            ->exists();

        if ($existingEntry) {
            $stats['expenses']['skipped']++;
            echo "s";
            continue;
        }

        try {
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
    echo "   Error processing expenses: {$e->getMessage()}\n";
}

// =====================================
// 3. Process Payroll Batches (Approved)
// =====================================
echo "\n3. Processing Payroll Batches (Approved)...\n";

try {
    $batches = PayrollBatch::where('status', PayrollBatch::STATUS_APPROVED)->get();
    $stats['payroll_batches']['found'] = $batches->count();
    echo "   Found {$batches->count()} approved payroll batches\n";

    $observer = new PayrollBatchObserver();

    foreach ($batches as $batch) {
        $stats['payroll_batches']['processed']++;

        // Check for expense recognition entry
        $existingEntry = JournalEntry::where('reference_type', PayrollBatch::class)
            ->where('reference_id', $batch->id)
            ->exists();

        if ($existingEntry) {
            $stats['payroll_batches']['skipped']++;
            echo "s";
            continue;
        }

        try {
            $reflectionMethod = new \ReflectionMethod($observer, 'createExpenseRecognitionEntry');
            $reflectionMethod->setAccessible(true);
            $reflectionMethod->invoke($observer, $batch);
            $stats['payroll_batches']['success']++;
            echo ".";
        } catch (\Exception $e) {
            $stats['payroll_batches']['errors']++;
            echo "E";
            Log::error("Observer trigger failed for PayrollBatch #{$batch->id} (approved): " . $e->getMessage());
        }
    }
    echo "\n";
} catch (\Exception $e) {
    echo "   Error processing payroll batches: {$e->getMessage()}\n";
}

// =====================================
// 4. Process Payroll Batches (Paid)
// =====================================
echo "\n4. Processing Payroll Batches (Paid)...\n";

try {
    $paidBatches = PayrollBatch::where('status', PayrollBatch::STATUS_PAID)->get();
    echo "   Found {$paidBatches->count()} paid payroll batches\n";

    $observer = new PayrollBatchObserver();

    foreach ($paidBatches as $batch) {
        // Check for payment entry (uses different reference_type suffix)
        $existingEntry = JournalEntry::where('reference_type', PayrollBatch::class . ':payment')
            ->where('reference_id', $batch->id)
            ->exists();

        if ($existingEntry) {
            echo "s";
            continue;
        }

        try {
            $reflectionMethod = new \ReflectionMethod($observer, 'createPaymentEntry');
            $reflectionMethod->setAccessible(true);
            $reflectionMethod->invoke($observer, $batch);
            echo ".";
        } catch (\Exception $e) {
            echo "E";
            Log::error("Observer trigger failed for PayrollBatch #{$batch->id} (paid): " . $e->getMessage());
        }
    }
    echo "\n";
} catch (\Exception $e) {
    echo "   Error processing paid payroll batches: {$e->getMessage()}\n";
}

// =====================================
// 5. Process Purchase Orders (Received)
// =====================================
echo "\n5. Processing Purchase Orders (Received)...\n";

try {
    $purchaseOrders = PurchaseOrder::where('status', PurchaseOrder::STATUS_RECEIVED)->get();
    $stats['purchase_orders']['found'] = $purchaseOrders->count();
    echo "   Found {$purchaseOrders->count()} received purchase orders\n";

    $observer = new PurchaseOrderObserver($subAccountService);

    foreach ($purchaseOrders as $po) {
        $stats['purchase_orders']['processed']++;

        $existingEntry = JournalEntry::where('reference_type', PurchaseOrder::class)
            ->where('reference_id', $po->id)
            ->exists();

        if ($existingEntry) {
            $stats['purchase_orders']['skipped']++;
            echo "s";
            continue;
        }

        try {
            $reflectionMethod = new \ReflectionMethod($observer, 'createPOReceivedJournalEntry');
            $reflectionMethod->setAccessible(true);
            $reflectionMethod->invoke($observer, $po);
            $stats['purchase_orders']['success']++;
            echo ".";
        } catch (\Exception $e) {
            $stats['purchase_orders']['errors']++;
            echo "E";
            Log::error("Observer trigger failed for PurchaseOrder #{$po->id}: " . $e->getMessage());
        }
    }
    echo "\n";
} catch (\Exception $e) {
    echo "   Error processing purchase orders: {$e->getMessage()}\n";
}

// =====================================
// 6. Process Purchase Order Payments
// =====================================
echo "\n6. Processing Purchase Order Payments...\n";

try {
    $poPayments = PurchaseOrderPayment::all();
    $stats['po_payments']['found'] = $poPayments->count();
    echo "   Found {$poPayments->count()} PO payments\n";

    $observer = new PurchaseOrderPaymentObserver($subAccountService);

    foreach ($poPayments as $payment) {
        $stats['po_payments']['processed']++;

        $existingEntry = JournalEntry::where('reference_type', PurchaseOrderPayment::class)
            ->where('reference_id', $payment->id)
            ->exists();

        if ($existingEntry) {
            $stats['po_payments']['skipped']++;
            echo "s";
            continue;
        }

        try {
            $observer->created($payment);
            $stats['po_payments']['success']++;
            echo ".";
        } catch (\Exception $e) {
            $stats['po_payments']['errors']++;
            echo "E";
            Log::error("Observer trigger failed for PurchaseOrderPayment #{$payment->id}: " . $e->getMessage());
        }
    }
    echo "\n";
} catch (\Exception $e) {
    echo "   Error processing PO payments: {$e->getMessage()}\n";
}

// =====================================
// 7. Process HMO Remittances
// =====================================
echo "\n7. Processing HMO Remittances...\n";

try {
    $remittances = HmoRemittance::all();
    $stats['hmo_remittances']['found'] = $remittances->count();
    echo "   Found {$remittances->count()} HMO remittances\n";

    $observer = new HmoRemittanceObserver($subAccountService);

    foreach ($remittances as $remittance) {
        $stats['hmo_remittances']['processed']++;

        $existingEntry = JournalEntry::where('reference_type', HmoRemittance::class)
            ->where('reference_id', $remittance->id)
            ->exists();

        if ($existingEntry) {
            $stats['hmo_remittances']['skipped']++;
            echo "s";
            continue;
        }

        try {
            $observer->created($remittance);
            $stats['hmo_remittances']['success']++;
            echo ".";
        } catch (\Exception $e) {
            $stats['hmo_remittances']['errors']++;
            echo "E";
            Log::error("Observer trigger failed for HmoRemittance #{$remittance->id}: " . $e->getMessage());
        }
    }
    echo "\n";
} catch (\Exception $e) {
    echo "   Error processing HMO remittances: {$e->getMessage()}\n";
}

// =====================================
// 8. Process HMO Claims (Validated)
// =====================================
echo "\n8. Processing HMO Claims (Validated)...\n";

try {
    // Only HMO patient requests with approved validation
    $hmoRequests = ProductOrServiceRequest::whereNotNull('hmo_id')
        ->where('validation_status', 'approved')
        ->where('claims_amount', '>', 0)
        ->get();
    $stats['hmo_claims']['found'] = $hmoRequests->count();
    echo "   Found {$hmoRequests->count()} validated HMO claims\n";

    $observer = new ProductOrServiceRequestObserver($subAccountService);

    foreach ($hmoRequests as $request) {
        $stats['hmo_claims']['processed']++;

        $existingEntry = JournalEntry::where('reference_type', ProductOrServiceRequest::class)
            ->where('reference_id', $request->id)
            ->exists();

        if ($existingEntry) {
            $stats['hmo_claims']['skipped']++;
            echo "s";
            continue;
        }

        try {
            $reflectionMethod = new \ReflectionMethod($observer, 'createHmoRevenueEntry');
            $reflectionMethod->setAccessible(true);
            $reflectionMethod->invoke($observer, $request);
            $stats['hmo_claims']['success']++;
            echo ".";
        } catch (\Exception $e) {
            $stats['hmo_claims']['errors']++;
            echo "E";
            Log::error("Observer trigger failed for ProductOrServiceRequest #{$request->id}: " . $e->getMessage());
        }
    }
    echo "\n";
} catch (\Exception $e) {
    echo "   Error processing HMO claims: {$e->getMessage()}\n";
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
$postedCount = JournalEntry::where('status', 'posted')->count();
echo "\n=================================================\n";
echo "Total Journal Entries: {$journalCount}\n";
echo "Posted Entries:        {$postedCount}\n";
echo "=================================================\n";

echo "\nDone!\n";

// Show any logged errors
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    if (preg_match_all('/Observer trigger failed.*$/m', $logContent, $matches)) {
        $recentErrors = array_slice($matches[0], -10);
        if (!empty($recentErrors)) {
            echo "\nRecent errors logged:\n";
            foreach ($recentErrors as $error) {
                echo "  - {$error}\n";
            }
        }
    }
}
