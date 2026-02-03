<?php
/**
 * Debug script to check budget stats calculations
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Accounting\Budget;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Support\Facades\DB;

echo "=== BUDGET STATS VERIFICATION ===\n\n";

// 1. Check budgets in database
echo "1. BUDGETS IN DATABASE:\n";
echo str_repeat("-", 80) . "\n";
$budgets = Budget::with(['fiscalYear', 'department'])->get();
foreach ($budgets as $budget) {
    echo "ID: {$budget->id}\n";
    echo "  Name: {$budget->budget_name}\n";
    echo "  Fiscal Year: " . ($budget->fiscalYear->year_name ?? 'N/A') . " (ID: {$budget->fiscal_year_id})\n";
    echo "  Year: {$budget->year}\n";
    echo "  Department: " . ($budget->department->name ?? 'Organization-wide') . "\n";
    echo "  Status: {$budget->status}\n";
    echo "  Total Budgeted: ₦" . number_format($budget->total_budgeted, 2) . "\n";
    echo "  Total Actual: ₦" . number_format($budget->total_actual, 2) . "\n";
    echo "  Total Variance: ₦" . number_format($budget->total_variance, 2) . "\n";
    echo "  Created: {$budget->created_at}\n\n";
}

// 2. Get current fiscal year
echo "\n2. CURRENT FISCAL YEAR:\n";
echo str_repeat("-", 80) . "\n";
$currentYear = date('Y');
$currentMonth = date('m');
echo "Current Year: {$currentYear}\n";
echo "Current Month: {$currentMonth}\n\n";

$fiscalYear = FiscalYear::where('status', 'open')
    ->whereYear('start_date', '<=', $currentYear)
    ->whereYear('end_date', '>=', $currentYear)
    ->first();

if ($fiscalYear) {
    echo "Active Fiscal Year Found:\n";
    echo "  ID: {$fiscalYear->id}\n";
    echo "  Name: {$fiscalYear->year_name}\n";
    echo "  Start: {$fiscalYear->start_date}\n";
    echo "  End: {$fiscalYear->end_date}\n";
    echo "  Status: {$fiscalYear->status}\n\n";
} else {
    echo "⚠️ No active fiscal year found!\n\n";
}

// 3. Calculate Total Budget (APPROVED only)
echo "\n3. TOTAL BUDGET CALCULATION:\n";
echo str_repeat("-", 80) . "\n";

$totalBudgetQuery = Budget::where('status', 'approved');
if ($fiscalYear) {
    $totalBudgetQuery->where('fiscal_year_id', $fiscalYear->id);
}
$totalBudget = $totalBudgetQuery->sum('total_budgeted');

echo "Total APPROVED Budgets: ₦" . number_format($totalBudget, 2) . "\n";
echo "Count: " . $totalBudgetQuery->count() . "\n\n";

// Show breakdown by status
echo "Budget Breakdown by Status:\n";
$statusBreakdown = Budget::select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_budgeted) as total'))
    ->groupBy('status')
    ->get();

foreach ($statusBreakdown as $status) {
    echo "  {$status->status}: {$status->count} budgets, ₦" . number_format($status->total, 2) . "\n";
}

// 4. Check YTD Actual Expenses
echo "\n\n4. YTD ACTUAL EXPENSES:\n";
echo str_repeat("-", 80) . "\n";

try {
    $ytdActual = JournalEntryLine::whereHas('journalEntry', function($q) use ($currentYear) {
        $q->where('status', 'posted')
          ->whereYear('entry_date', $currentYear);
    })
    ->whereHas('account.accountGroup.accountClass', function($q) {
        $q->where('name', 'Expenses');
    })
    ->sum('debit');

    echo "YTD Actual (from Journal Entries): ₦" . number_format($ytdActual, 2) . "\n";
    echo "Query Status: ✅ Success\n\n";
} catch (\Exception $e) {
    echo "❌ Error calculating YTD Actual: " . $e->getMessage() . "\n\n";
    $ytdActual = 0;
}

// 5. Check MTD Actual
echo "\n5. MTD ACTUAL EXPENSES:\n";
echo str_repeat("-", 80) . "\n";

try {
    $mtdActual = JournalEntryLine::whereHas('journalEntry', function($q) use ($currentYear, $currentMonth) {
        $q->where('status', 'posted')
          ->whereYear('entry_date', $currentYear)
          ->whereMonth('entry_date', $currentMonth);
    })
    ->whereHas('account.accountGroup.accountClass', function($q) {
        $q->where('name', 'Expenses');
    })
    ->sum('debit');

    echo "MTD Actual (from Journal Entries): ₦" . number_format($mtdActual, 2) . "\n";
    echo "Query Status: ✅ Success\n\n";
} catch (\Exception $e) {
    echo "❌ Error calculating MTD Actual: " . $e->getMessage() . "\n\n";
    $mtdActual = 0;
}

// 6. Calculate Variance and Utilization
echo "\n6. VARIANCE & UTILIZATION:\n";
echo str_repeat("-", 80) . "\n";

$monthlyBudget = $totalBudget > 0 ? $totalBudget / 12 : 0;
$expectedYtd = $monthlyBudget * $currentMonth;
$variance = $expectedYtd - $ytdActual;
$utilization = $totalBudget > 0 ? ($ytdActual / $totalBudget) * 100 : 0;

echo "Monthly Budget (Total/12): ₦" . number_format($monthlyBudget, 2) . "\n";
echo "Expected YTD (Month {$currentMonth}): ₦" . number_format($expectedYtd, 2) . "\n";
echo "Actual YTD: ₦" . number_format($ytdActual, 2) . "\n";
echo "Variance: ₦" . number_format($variance, 2) . "\n";
echo "Utilization: " . number_format($utilization, 2) . "%\n\n";

// 7. Budget Counts
echo "\n7. BUDGET COUNTS:\n";
echo str_repeat("-", 80) . "\n";

$budgetCount = Budget::count();
$approvedCount = Budget::where('status', 'approved')->count();
$pendingCount = Budget::where('status', 'pending_approval')->count();
$draftCount = Budget::where('status', 'draft')->count();

echo "Total Budgets: {$budgetCount}\n";
echo "Approved: {$approvedCount}\n";
echo "Pending Approval: {$pendingCount}\n";
echo "Draft: {$draftCount}\n\n";

// 8. Summary
echo "\n8. SUMMARY - WHAT SHOULD BE DISPLAYED:\n";
echo str_repeat("=", 80) . "\n";
echo "Total Budget (2026): ₦" . number_format($totalBudget, 2) . " (APPROVED budgets only)\n";
echo "YTD Actual Spending: ₦" . number_format($ytdActual, 2) . "\n";
echo "YTD Variance: ₦" . number_format($variance, 2) . "\n";
echo "Budget Utilization: " . number_format($utilization, 2) . "%\n";
echo "Total Budgets: {$budgetCount}\n";
echo "Approved: {$approvedCount}\n";
echo "Pending Approval: {$pendingCount}\n";
echo "Monthly Budget: ₦" . number_format($monthlyBudget, 2) . "\n";
echo "MTD Actual: ₦" . number_format($mtdActual, 2) . "\n\n";

echo "\n⚠️ NOTE: Stats only count APPROVED budgets!\n";
echo "   The '2026 Misc budget' is in DRAFT status, so it's not included in totals.\n";
echo "   To see it reflected in stats, change status to 'approved'.\n\n";

echo "=== END ===\n";
