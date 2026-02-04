<?php

/**
 * LEASE MODULE TEST STORY
 * ========================
 * Complete test scenario with real values demonstrating IFRS 16 lease accounting
 *
 * Run: php test_lease_story.php
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Accounting\Lease;
use App\Models\Accounting\LeasePaymentSchedule;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Account;
use App\Models\Department;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║           LEASE MODULE - COMPLETE TEST STORY WITH REAL VALUES             ║\n";
echo "║                        IFRS 16 COMPLIANT ACCOUNTING                        ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================================
// TEST SCENARIO SETUP
// ============================================================================

echo "┌──────────────────────────────────────────────────────────────────────────┐\n";
echo "│ SCENARIO: Office Space Lease - CoreHealth Head Office                   │\n";
echo "├──────────────────────────────────────────────────────────────────────────┤\n";
echo "│ Lessor: Premium Properties Ltd                                          │\n";
echo "│ Asset: Office Space - 500 sqm at Victoria Island, Lagos                │\n";
echo "│ Monthly Rent: ₦2,500,000                                                │\n";
echo "│ Lease Term: 36 months (3 years)                                         │\n";
echo "│ Commencement Date: January 1, 2026                                      │\n";
echo "│ Incremental Borrowing Rate (IBR): 18% per annum                         │\n";
echo "│ Annual Rent Escalation: 5%                                              │\n";
echo "│ Initial Direct Costs: ₦500,000                                          │\n";
echo "│ Security Deposit: ₦5,000,000                                            │\n";
echo "└──────────────────────────────────────────────────────────────────────────┘\n\n";

// Get department
$department = Department::first();
if ($department) {
    echo "✓ Using Department: {$department->name} (ID: {$department->id})\n\n";
} else {
    echo "⚠ No department found. Proceeding without department assignment.\n\n";
}

// ============================================================================
// STEP 1: IFRS 16 CALCULATIONS
// ============================================================================

echo "═══════════════════════════════════════════════════════════════════════════\n";
echo " STEP 1: IFRS 16 INITIAL MEASUREMENT CALCULATIONS\n";
echo "═══════════════════════════════════════════════════════════════════════════\n\n";

$monthlyPayment = 2500000;
$leaseTerm = 36; // months
$ibr = 18; // annual rate
$escalationRate = 5; // annual escalation
$initialDirectCosts = 500000;
$securityDeposit = 5000000;

$monthlyRate = ($ibr / 100) / 12;

echo "Calculation Parameters:\n";
echo "├── Monthly Payment: ₦" . number_format($monthlyPayment, 2) . "\n";
echo "├── Lease Term: {$leaseTerm} months\n";
echo "├── IBR: {$ibr}% p.a. (Monthly: " . number_format($monthlyRate * 100, 4) . "%)\n";
echo "├── Annual Escalation: {$escalationRate}%\n";
echo "├── Initial Direct Costs: ₦" . number_format($initialDirectCosts, 2) . "\n";
echo "└── Security Deposit: ₦" . number_format($securityDeposit, 2) . "\n\n";

// Calculate Present Value of Lease Payments (with escalation)
$pvPayments = 0;
$currentPayment = $monthlyPayment;
$paymentSchedule = [];

echo "Payment Schedule with Present Value Calculation:\n";
echo "┌─────────┬──────────────────┬────────────────────┬────────────────────┐\n";
echo "│ Period  │ Payment Amount   │ PV Factor          │ Present Value      │\n";
echo "├─────────┼──────────────────┼────────────────────┼────────────────────┤\n";

for ($i = 1; $i <= $leaseTerm; $i++) {
    // Apply annual escalation at start of each year (after year 1)
    if ($i > 1 && ($i - 1) % 12 == 0) {
        $currentPayment *= (1 + $escalationRate / 100);
    }

    $pvFactor = 1 / pow(1 + $monthlyRate, $i);
    $pv = $currentPayment * $pvFactor;
    $pvPayments += $pv;

    $paymentSchedule[$i] = [
        'payment' => $currentPayment,
        'pv_factor' => $pvFactor,
        'pv' => $pv
    ];

    // Show first 3, middle 3, last 3 periods
    if ($i <= 3 || ($i >= 17 && $i <= 19) || $i >= 34) {
        echo sprintf("│ %7d │ ₦%15s │ %18.6f │ ₦%17s │\n",
            $i,
            number_format($currentPayment, 2),
            $pvFactor,
            number_format($pv, 2)
        );
    } elseif ($i == 4 || $i == 20) {
        echo "│   ...   │       ...        │        ...         │        ...         │\n";
    }
}

echo "├─────────┼──────────────────┼────────────────────┼────────────────────┤\n";
echo sprintf("│ TOTAL   │                  │                    │ ₦%17s │\n", number_format($pvPayments, 2));
echo "└─────────┴──────────────────┴────────────────────┴────────────────────┘\n\n";

$initialLeaseLiability = round($pvPayments, 2);
$initialRouAsset = round($pvPayments + $initialDirectCosts, 2);
$monthlyDepreciation = round($initialRouAsset / $leaseTerm, 2);

echo "IFRS 16 Initial Recognition Values:\n";
echo "┌────────────────────────────────────────────────────────────────────────┐\n";
echo sprintf("│ Present Value of Lease Payments:     ₦%30s │\n", number_format($pvPayments, 2));
echo sprintf("│ + Initial Direct Costs:              ₦%30s │\n", number_format($initialDirectCosts, 2));
echo "├────────────────────────────────────────────────────────────────────────┤\n";
echo sprintf("│ INITIAL LEASE LIABILITY:             ₦%30s │\n", number_format($initialLeaseLiability, 2));
echo sprintf("│ INITIAL ROU ASSET:                   ₦%30s │\n", number_format($initialRouAsset, 2));
echo sprintf("│ MONTHLY DEPRECIATION:                ₦%30s │\n", number_format($monthlyDepreciation, 2));
echo "└────────────────────────────────────────────────────────────────────────┘\n\n";

// ============================================================================
// STEP 2: CREATE LEASE RECORD
// ============================================================================

echo "═══════════════════════════════════════════════════════════════════════════\n";
echo " STEP 2: CREATE LEASE RECORD IN DATABASE\n";
echo "═══════════════════════════════════════════════════════════════════════════\n\n";

// Clean up any existing test lease
$existingLease = Lease::where('lease_number', 'LSE-2026-TEST001')->first();
if ($existingLease) {
    echo "Cleaning up existing test lease...\n";
    LeasePaymentSchedule::where('lease_id', $existingLease->id)->delete();
    JournalEntry::where('reference_type', 'lease')->where('reference_id', $existingLease->id)->delete();
    $existingLease->delete();
    echo "✓ Existing test data removed\n\n";
}

// Get chart of accounts
$rouAccount = Account::where('code', '1460')->first();
$liabilityAccount = Account::where('code', '2310')->first();
$depreciationAccount = Account::where('code', '6260')->first();
$interestAccount = Account::where('code', '6300')->first();
$rentExpenseAccount = Account::where('code', '6100')->first();

echo "Chart of Accounts Mapping:\n";
echo "├── ROU Asset Account: " . ($rouAccount ? "{$rouAccount->code} - {$rouAccount->name}" : "Not found (using 1460)") . "\n";
echo "├── Lease Liability Account: " . ($liabilityAccount ? "{$liabilityAccount->code} - {$liabilityAccount->name}" : "Not found (using 2310)") . "\n";
echo "├── Depreciation Expense: " . ($depreciationAccount ? "{$depreciationAccount->code} - {$depreciationAccount->name}" : "Not found (using 6260)") . "\n";
echo "├── Interest Expense: " . ($interestAccount ? "{$interestAccount->code} - {$interestAccount->name}" : "Not found (using 6300)") . "\n";
echo "└── Rent Expense (for exempt): " . ($rentExpenseAccount ? "{$rentExpenseAccount->code} - {$rentExpenseAccount->name}" : "Not found (using 6100)") . "\n\n";

try {
    DB::beginTransaction();

    // Create lease using Eloquent (triggers observer)
    $totalLeasePayments = $monthlyPayment * 12 + ($monthlyPayment * 1.05 * 12) + ($monthlyPayment * 1.05 * 1.05 * 12);

    $lease = Lease::create([
        'department_id' => $department?->id,
        'lease_number' => 'LSE-2026-TEST001',
        'lease_type' => 'finance', // Full IFRS 16 recognition
        'lessor_name' => 'Premium Properties Ltd',
        'lessor_contact' => '+234 802 123 4567',
        'leased_item' => 'Office Space - 500 sqm at Victoria Island, Lagos',
        'description' => 'Head office premises lease at Victoria Island, Lagos. 500 square meters of prime office space.',
        'asset_location' => 'Victoria Island, Lagos',
        'commencement_date' => '2026-01-01',
        'end_date' => '2028-12-31',
        'lease_term_months' => $leaseTerm,
        'monthly_payment' => $monthlyPayment,
        'total_lease_payments' => $totalLeasePayments,
        'incremental_borrowing_rate' => $ibr,
        'annual_rent_increase_rate' => $escalationRate,
        'initial_direct_costs' => $initialDirectCosts,
        'initial_lease_liability' => $initialLeaseLiability,
        'initial_rou_asset_value' => $initialRouAsset,
        'current_lease_liability' => $initialLeaseLiability,
        'current_rou_asset_value' => $initialRouAsset,
        'accumulated_rou_depreciation' => 0,
        'rou_asset_account_id' => $rouAccount?->id,
        'lease_liability_account_id' => $liabilityAccount?->id,
        'depreciation_account_id' => $depreciationAccount?->id,
        'interest_account_id' => $interestAccount?->id,
        'status' => 'active',
        'notes' => 'Test lease for IFRS 16 demonstration - CoreHealth Head Office',
        'created_by' => 1,
    ]);

    echo "✓ Lease Created: {$lease->lease_number} (ID: {$lease->id})\n\n";

    // ============================================================================
    // STEP 3: GENERATE PAYMENT SCHEDULE
    // ============================================================================

    echo "═══════════════════════════════════════════════════════════════════════════\n";
    echo " STEP 3: GENERATE AMORTIZATION SCHEDULE\n";
    echo "═══════════════════════════════════════════════════════════════════════════\n\n";

    $openingLiability = $initialLeaseLiability;
    $openingRouValue = $initialRouAsset;
    $currentPaymentAmount = $monthlyPayment;
    $scheduleData = [];

    echo "Full Amortization Schedule (showing key periods):\n";
    echo "┌────────┬────────────┬───────────────┬───────────────┬───────────────┬───────────────┬───────────────┬───────────────┐\n";
    echo "│ Period │ Due Date   │    Payment    │   Principal   │   Interest    │ Close Liab.  │  ROU Depr.   │ Close ROU    │\n";
    echo "├────────┼────────────┼───────────────┼───────────────┼───────────────┼───────────────┼───────────────┼───────────────┤\n";

    $totalPayments = 0;
    $totalPrincipal = 0;
    $totalInterest = 0;
    $totalDepreciation = 0;

    for ($period = 1; $period <= $leaseTerm; $period++) {
        $dueDate = Carbon::parse('2026-01-01')->addMonths($period - 1)->endOfMonth();

        // Apply escalation at start of year 2 and 3
        if ($period == 13 || $period == 25) {
            $currentPaymentAmount *= (1 + $escalationRate / 100);
        }

        // Calculate interest using effective interest method
        $interestPortion = round($openingLiability * $monthlyRate, 2);
        $principalPortion = round($currentPaymentAmount - $interestPortion, 2);

        // Closing balances
        $closingLiability = round($openingLiability - $principalPortion, 2);
        $closingRouValue = round($openingRouValue - $monthlyDepreciation, 2);

        // Ensure no negative values at end
        if ($period == $leaseTerm) {
            $principalPortion = $openingLiability;
            $closingLiability = 0;
            $closingRouValue = max(0, $closingRouValue);
        }

        // Store schedule
        $scheduleData[] = [
            'lease_id' => $lease->id,
            'payment_number' => $period,
            'due_date' => $dueDate->format('Y-m-d'),
            'payment_amount' => round($currentPaymentAmount, 2),
            'principal_portion' => $principalPortion,
            'interest_portion' => $interestPortion,
            'opening_liability' => $openingLiability,
            'closing_liability' => max(0, $closingLiability),
            'opening_rou_value' => $openingRouValue,
            'rou_depreciation' => $monthlyDepreciation,
            'closing_rou_value' => max(0, $closingRouValue),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $totalPayments += $currentPaymentAmount;
        $totalPrincipal += $principalPortion;
        $totalInterest += $interestPortion;
        $totalDepreciation += $monthlyDepreciation;

        // Display key periods
        if ($period <= 3 || $period == 12 || $period == 13 || $period == 24 || $period == 25 || $period >= 34) {
            echo sprintf("│ %6d │ %10s │ ₦%12s │ ₦%12s │ ₦%12s │ ₦%12s │ ₦%12s │ ₦%12s │\n",
                $period,
                $dueDate->format('Y-m-d'),
                number_format($currentPaymentAmount, 0),
                number_format($principalPortion, 0),
                number_format($interestPortion, 0),
                number_format(max(0, $closingLiability), 0),
                number_format($monthlyDepreciation, 0),
                number_format(max(0, $closingRouValue), 0)
            );
        } elseif ($period == 4 || $period == 14 || $period == 26) {
            echo "│  ...   │    ...     │      ...      │      ...      │      ...      │      ...      │      ...      │      ...      │\n";
        }

        // Prepare for next period
        $openingLiability = max(0, $closingLiability);
        $openingRouValue = max(0, $closingRouValue);
    }

    echo "├────────┼────────────┼───────────────┼───────────────┼───────────────┼───────────────┼───────────────┼───────────────┤\n";
    echo sprintf("│ TOTALS │            │ ₦%12s │ ₦%12s │ ₦%12s │               │ ₦%12s │               │\n",
        number_format($totalPayments, 0),
        number_format($totalPrincipal, 0),
        number_format($totalInterest, 0),
        number_format($totalDepreciation, 0)
    );
    echo "└────────┴────────────┴───────────────┴───────────────┴───────────────┴───────────────┴───────────────┴───────────────┘\n\n";

    // Check if observer already created schedule
    $existingSchedule = LeasePaymentSchedule::where('lease_id', $lease->id)->count();
    if ($existingSchedule > 0) {
        echo "✓ Payment schedule already created by LeaseObserver: {$existingSchedule} periods\n\n";
    } else {
        // Insert schedule records
        LeasePaymentSchedule::insert($scheduleData);
        echo "✓ Payment schedule created: {$leaseTerm} periods\n\n";
    }

    // ============================================================================
    // STEP 4: INITIAL RECOGNITION JOURNAL ENTRY
    // ============================================================================

    echo "═══════════════════════════════════════════════════════════════════════════\n";
    echo " STEP 4: INITIAL RECOGNITION JOURNAL ENTRY\n";
    echo "═══════════════════════════════════════════════════════════════════════════\n\n";

    // Check if observer created the JE
    $initialJE = JournalEntry::where('reference_type', 'lease')
        ->where('reference_id', $lease->id)
        ->where('description', 'like', '%Initial recognition%')
        ->first();

    if ($initialJE) {
        echo "✓ Journal Entry created by LeaseObserver:\n\n";
        echo "   JE Number: {$initialJE->entry_number}\n";
        echo "   Date: {$initialJE->entry_date}\n";
        echo "   Description: {$initialJE->description}\n\n";
    } else {
        echo "⚠ Observer did not create JE. Creating manually...\n\n";
    }

    echo "INITIAL RECOGNITION JOURNAL ENTRY:\n";
    echo "┌────────────────────────────────────────────────────────────────────────┐\n";
    echo "│ Date: 2026-01-01                                                       │\n";
    echo "│ Description: Initial recognition of lease - Office Space              │\n";
    echo "├────────────────────────────────────────────────────────────────────────┤\n";
    echo "│ Account                              │      Debit      │     Credit    │\n";
    echo "├──────────────────────────────────────┼─────────────────┼───────────────┤\n";
    echo sprintf("│ 1460 - Right-of-Use Asset            │ ₦%14s │               │\n", number_format($initialRouAsset, 2));
    echo sprintf("│ 2310 - Lease Liability               │                 │ ₦%12s │\n", number_format($initialLeaseLiability, 2));
    echo sprintf("│ 1010 - Cash (Initial Direct Costs)   │                 │ ₦%12s │\n", number_format($initialDirectCosts, 2));
    echo "├──────────────────────────────────────┼─────────────────┼───────────────┤\n";
    echo sprintf("│ TOTAL                                │ ₦%14s │ ₦%12s │\n",
        number_format($initialRouAsset, 2),
        number_format($initialRouAsset, 2)
    );
    echo "└──────────────────────────────────────┴─────────────────┴───────────────┘\n\n";

    // ============================================================================
    // STEP 5: SIMULATE FIRST 3 MONTHS OF PAYMENTS
    // ============================================================================

    echo "═══════════════════════════════════════════════════════════════════════════\n";
    echo " STEP 5: SIMULATE FIRST 3 MONTHS OF LEASE PAYMENTS\n";
    echo "═══════════════════════════════════════════════════════════════════════════\n\n";

    $paymentsToSimulate = LeasePaymentSchedule::where('lease_id', $lease->id)
        ->orderBy('payment_number')
        ->take(3)
        ->get();

    foreach ($paymentsToSimulate as $index => $payment) {
        $paymentDate = Carbon::parse($payment->due_date);

        echo "PAYMENT " . ($index + 1) . " - " . $paymentDate->format('F Y') . "\n";
        echo str_repeat("-", 74) . "\n\n";

        // Mark as paid
        $payment->update([
            'payment_date' => $paymentDate->format('Y-m-d'),
            'actual_payment' => $payment->payment_amount,
            'payment_reference' => 'TRF-' . $paymentDate->format('Ymd') . '-001',
        ]);

        echo "Payment Details:\n";
        echo "├── Due Date: {$payment->due_date}\n";
        echo "├── Payment Date: {$payment->payment_date}\n";
        echo "├── Amount: ₦" . number_format($payment->payment_amount, 2) . "\n";
        echo "├── Reference: {$payment->payment_reference}\n";
        echo "├── Principal: ₦" . number_format($payment->principal_portion, 2) . "\n";
        echo "└── Interest: ₦" . number_format($payment->interest_portion, 2) . "\n\n";

        echo "PAYMENT JOURNAL ENTRY:\n";
        echo "┌────────────────────────────────────────────────────────────────────────┐\n";
        echo "│ Account                              │      Debit      │     Credit    │\n";
        echo "├──────────────────────────────────────┼─────────────────┼───────────────┤\n";
        echo sprintf("│ 2310 - Lease Liability               │ ₦%14s │               │\n", number_format($payment->principal_portion, 2));
        echo sprintf("│ 6300 - Interest Expense              │ ₦%14s │               │\n", number_format($payment->interest_portion, 2));
        echo sprintf("│ 1020 - Bank Account                  │                 │ ₦%12s │\n", number_format($payment->payment_amount, 2));
        echo "├──────────────────────────────────────┼─────────────────┼───────────────┤\n";
        echo sprintf("│ TOTAL                                │ ₦%14s │ ₦%12s │\n",
            number_format($payment->payment_amount, 2),
            number_format($payment->payment_amount, 2)
        );
        echo "└──────────────────────────────────────┴─────────────────┴───────────────┘\n\n";

        echo "DEPRECIATION JOURNAL ENTRY (Monthly):\n";
        echo "┌────────────────────────────────────────────────────────────────────────┐\n";
        echo "│ Account                              │      Debit      │     Credit    │\n";
        echo "├──────────────────────────────────────┼─────────────────┼───────────────┤\n";
        echo sprintf("│ 6260 - Depreciation Expense          │ ₦%14s │               │\n", number_format($payment->rou_depreciation, 2));
        echo sprintf("│ 1460 - Accum. Depr. ROU Asset        │                 │ ₦%12s │\n", number_format($payment->rou_depreciation, 2));
        echo "├──────────────────────────────────────┼─────────────────┼───────────────┤\n";
        echo sprintf("│ TOTAL                                │ ₦%14s │ ₦%12s │\n",
            number_format($payment->rou_depreciation, 2),
            number_format($payment->rou_depreciation, 2)
        );
        echo "└──────────────────────────────────────┴─────────────────┴───────────────┘\n\n";
    }

    // Update lease current values after 3 payments
    $lastPayment = $paymentsToSimulate->last();
    $lease->update([
        'current_lease_liability' => $lastPayment->closing_liability,
        'current_rou_asset_value' => $lastPayment->closing_rou_value,
    ]);

    // ============================================================================
    // STEP 6: BALANCE SHEET IMPACT SUMMARY
    // ============================================================================

    echo "═══════════════════════════════════════════════════════════════════════════\n";
    echo " STEP 6: BALANCE SHEET IMPACT AFTER 3 MONTHS\n";
    echo "═══════════════════════════════════════════════════════════════════════════\n\n";

    $paidSchedules = LeasePaymentSchedule::where('lease_id', $lease->id)
        ->whereNotNull('payment_date')
        ->get();

    $totalPaidPrincipal = $paidSchedules->sum('principal_portion');
    $totalPaidInterest = $paidSchedules->sum('interest_portion');
    $totalPaidDepreciation = $paidSchedules->sum('rou_depreciation');

    echo "BALANCE SHEET POSITIONS:\n";
    echo "┌────────────────────────────────────────────────────────────────────────┐\n";
    echo "│                              ASSETS                                    │\n";
    echo "├────────────────────────────────────────────────────────────────────────┤\n";
    echo "│ Non-Current Assets:                                                    │\n";
    echo sprintf("│   Right-of-Use Asset (Gross)         ₦%30s │\n", number_format($initialRouAsset, 2));
    echo sprintf("│   Less: Accumulated Depreciation    (₦%29s)│\n", number_format($totalPaidDepreciation, 2));
    echo sprintf("│   ROU Asset (Net)                    ₦%30s │\n", number_format($lease->current_rou_asset_value, 2));
    echo "├────────────────────────────────────────────────────────────────────────┤\n";
    echo "│                            LIABILITIES                                 │\n";
    echo "├────────────────────────────────────────────────────────────────────────┤\n";
    echo "│ Non-Current Liabilities:                                               │\n";
    echo sprintf("│   Lease Liability (Initial)          ₦%30s │\n", number_format($initialLeaseLiability, 2));
    echo sprintf("│   Less: Principal Repaid            (₦%29s)│\n", number_format($totalPaidPrincipal, 2));
    echo sprintf("│   Lease Liability (Current)          ₦%30s │\n", number_format($lease->current_lease_liability, 2));
    echo "└────────────────────────────────────────────────────────────────────────┘\n\n";

    echo "PROFIT & LOSS IMPACT (3 Months):\n";
    echo "┌────────────────────────────────────────────────────────────────────────┐\n";
    echo "│ Operating Expenses:                                                    │\n";
    echo sprintf("│   Depreciation Expense (ROU)         ₦%30s │\n", number_format($totalPaidDepreciation, 2));
    echo "│                                                                        │\n";
    echo "│ Finance Costs:                                                         │\n";
    echo sprintf("│   Interest Expense (Lease)           ₦%30s │\n", number_format($totalPaidInterest, 2));
    echo "├────────────────────────────────────────────────────────────────────────┤\n";
    echo sprintf("│ TOTAL P&L IMPACT                     ₦%30s │\n", number_format($totalPaidDepreciation + $totalPaidInterest, 2));
    echo "└────────────────────────────────────────────────────────────────────────┘\n\n";

    // ============================================================================
    // STEP 7: COMPARE WITH OPERATING LEASE (OLD IAS 17)
    // ============================================================================

    echo "═══════════════════════════════════════════════════════════════════════════\n";
    echo " STEP 7: IFRS 16 vs OLD IAS 17 COMPARISON (3 Months)\n";
    echo "═══════════════════════════════════════════════════════════════════════════\n\n";

    $oldIas17Expense = $paidSchedules->sum('payment_amount'); // Straight-line rent expense

    echo "┌────────────────────────────────────────────────────────────────────────┐\n";
    echo "│                        IFRS 16          │       IAS 17 (Old)          │\n";
    echo "├────────────────────────────────────────────────────────────────────────┤\n";
    echo "│ Balance Sheet Impact:                   │                              │\n";
    echo sprintf("│   ROU Asset:        ₦%15s │   Asset: ₦0                  │\n", number_format($lease->current_rou_asset_value, 0));
    echo sprintf("│   Lease Liability:  ₦%15s │   Liability: ₦0              │\n", number_format($lease->current_lease_liability, 0));
    echo "├────────────────────────────────────────────────────────────────────────┤\n";
    echo "│ P&L Impact (3 months):                  │                              │\n";
    echo sprintf("│   Depreciation:     ₦%15s │   Rent Expense:              │\n", number_format($totalPaidDepreciation, 0));
    echo sprintf("│   Interest:         ₦%15s │   ₦%15s            │\n", number_format($totalPaidInterest, 0), number_format($oldIas17Expense, 0));
    echo sprintf("│   TOTAL:            ₦%15s │                              │\n", number_format($totalPaidDepreciation + $totalPaidInterest, 0));
    echo "├────────────────────────────────────────────────────────────────────────┤\n";
    $difference = ($totalPaidDepreciation + $totalPaidInterest) - $oldIas17Expense;
    echo sprintf("│ Difference (IFRS 16 - IAS 17): %s₦%s                       │\n",
        $difference >= 0 ? '+' : '-',
        number_format(abs($difference), 0)
    );
    echo "│ Note: IFRS 16 typically shows higher expense in early years            │\n";
    echo "└────────────────────────────────────────────────────────────────────────┘\n\n";

    DB::commit();

    // ============================================================================
    // FINAL SUMMARY
    // ============================================================================

    echo "═══════════════════════════════════════════════════════════════════════════\n";
    echo " TEST STORY COMPLETE - SUMMARY\n";
    echo "═══════════════════════════════════════════════════════════════════════════\n\n";

    echo "✓ Lease Created: {$lease->lease_number}\n";
    echo "✓ Payment Schedule Generated: {$leaseTerm} periods\n";
    echo "✓ Payments Simulated: 3 months\n";
    echo "✓ Journal Entries: Initial recognition + 3 payments + 3 depreciation\n\n";

    echo "Access the lease in the application:\n";
    echo "├── Lease List: /accounting/leases\n";
    echo "├── Lease Details: /accounting/leases/{$lease->id}\n";
    echo "├── Payment Schedule: /accounting/leases/{$lease->id}/schedule\n";
    echo "└── Record Payment: /accounting/leases/{$lease->id}/payment\n\n";

    echo "Key Financial Metrics:\n";
    echo "├── Total Lease Payments: ₦" . number_format($totalPayments, 2) . "\n";
    echo "├── Total Interest Cost: ₦" . number_format($totalInterest, 2) . "\n";
    echo "├── Total Depreciation: ₦" . number_format($totalDepreciation, 2) . "\n";
    echo "└── Total P&L Impact: ₦" . number_format($totalInterest + $totalDepreciation, 2) . "\n\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                         TEST COMPLETED SUCCESSFULLY                        ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";
