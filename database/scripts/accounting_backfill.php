<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Payment;
use App\Models\ProductOrServiceRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\App;

echo "Starting Accounting Backfill Script for _corehealth_db_v2_hopehill...\n";

// 1. Switch Database Connection
DB::purge('mysql');
Config::set('database.connections.mysql.database', '_corehealth_db_v2_hopehill');
DB::reconnect('mysql');

$dbName = DB::connection('mysql')->getDatabaseName();
if ($dbName !== '_corehealth_db_v2_hopehill') {
    die("Failed to switch database connection. Current DB: {$dbName}\n");
}

echo "Successfully connected to: {$dbName}\n";

// 2. Determine Earliest Transaction Date
$earliestPayment = Payment::min('created_at');
$earliestPosr = ProductOrServiceRequest::min('created_at');

$earliestDateStr = $earliestPayment < $earliestPosr ? $earliestPayment : $earliestPosr;
$earliestDate = $earliestDateStr ? Carbon::parse($earliestDateStr) : Carbon::now();

echo "Earliest transaction date found: {$earliestDate->toDateString()}\n";

$startYear = $earliestDate->year;
$currentYear = Carbon::now()->year;

// 3. Create Fiscal Years and Periods
echo "Creating Fiscal Years and Periods from {$startYear} to {$currentYear}...\n";

// Get retained earnings account for Fiscal Year creation
$retainedEarningsCode = '3000';
$retainedEarningsAcc = \App\Models\Accounting\Account::where('code', $retainedEarningsCode)->first();
$retainedEarningsAccId = $retainedEarningsAcc ? $retainedEarningsAcc->id : null;

for ($year = $startYear; $year <= $currentYear; $year++) {
    $startDate = Carbon::create($year, 1, 1)->startOfDay();
    $endDate = Carbon::create($year, 12, 31)->endOfDay();
    
    $fiscalYear = FiscalYear::firstOrCreate(
        ['year_name' => "FY {$year}"],
        [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'open', // Leave open to backfill
            'retained_earnings_account_id' => $retainedEarningsAccId
        ]
    );
    
    echo "Ensured Fiscal Year: FY {$year}\n";
    
    for ($month = 1; $month <= 12; $month++) {
        $periodStart = Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth()->endOfDay();
        
        AccountingPeriod::firstOrCreate(
            [
                'fiscal_year_id' => $fiscalYear->id,
                'period_number' => $month
            ],
            [
                'period_name' => $periodStart->format('F Y'),
                'start_date' => $periodStart,
                'end_date' => $periodEnd,
                'status' => 'open'
            ]
        );
    }
}

// 4. Backfill Payments (Cash/Bank Receipts)
echo "Backfilling Payments...\n";
$payments = Payment::whereNull('journal_entry_id')->get();
$paymentObserver = App::make(\App\Observers\Accounting\PaymentObserver::class);
$paymentCount = 0;

foreach ($payments as $payment) {
    try {
        // Only process if it has total > 0 (skip auto-settled ones if they exist)
        if ($payment->total > 0) {
            $paymentObserver->created($payment);
            $paymentCount++;
            if ($paymentCount % 100 == 0) {
                echo "Processed {$paymentCount} payments...\n";
            }
        }
    } catch (\Exception $e) {
        echo "Error backfilling payment {$payment->id}: " . $e->getMessage() . "\n";
    }
}
echo "Completed processing {$paymentCount} payments.\n";

// 5. Backfill HMO Accruals (ProductOrServiceRequests)
echo "Backfilling HMO Accruals for POSR...\n";
$posrObserver = App::make(\App\Observers\Accounting\ProductOrServiceRequestObserver::class);

// Use reflection to call protected createHmoRevenueEntry method
$reflectionClass = new \ReflectionClass($posrObserver);
$createHmoMethod = $reflectionClass->getMethod('createHmoRevenueEntry');
$createHmoMethod->setAccessible(true);

// Pre-process HMO data from patients and payments for this specific client
echo "Pre-processing HMO data for claims...\n";
DB::statement("
    UPDATE payments pay
    JOIN patients p ON pay.patient_id = p.id
    SET pay.hmo_id = p.hmo_id
    WHERE pay.payment_type = 'CLAIMS' AND p.hmo_id IS NOT NULL
");

DB::statement("
    UPDATE product_or_service_requests posr
    JOIN payments pay ON posr.payment_id = pay.id
    JOIN patients p ON pay.patient_id = p.id
    SET posr.hmo_id = p.hmo_id,
        posr.coverage_mode = 'hmo',
        posr.claims_amount = posr.amount - posr.discount
    WHERE pay.payment_type = 'CLAIMS' AND p.hmo_id IS NOT NULL
");

$posrs = ProductOrServiceRequest::whereNotNull('hmo_id')->get();

$posrCount = 0;
foreach ($posrs as $posr) {
    try {
        // Check if JE already exists
        $exists = JournalEntry::where('reference_type', ProductOrServiceRequest::class)
            ->where('reference_id', $posr->id)
            ->exists();
            
        if (!$exists) {
            $createHmoMethod->invoke($posrObserver, $posr);
            $posrCount++;
            if ($posrCount % 100 == 0) {
                echo "Processed {$posrCount} HMO POSR accruals...\n";
            }
        }
    } catch (\Exception $e) {
        // Skip silently, might just be missing accounts or auto-settled
        echo "Error backfilling POSR {$posr->id}: " . $e->getMessage() . "\n";
    }
}
echo "Completed processing {$posrCount} HMO POSR accruals.\n";

echo "Accounting Backfill Script completed successfully!\n";
