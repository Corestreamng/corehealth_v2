<?php

require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Staff;
use App\Models\HR\PayrollItem;
use App\Models\HR\PayrollBatch;

$staff = Staff::where('user_id', 1)->first();

if (!$staff) {
    echo "No staff found with user_id 1\n";
    exit;
}

echo "Staff ID: {$staff->id}\n";
echo "Staff Name: {$staff->full_name}\n";
echo "Employee ID: {$staff->employee_id}\n";
echo "\n";

echo "=== All Payroll Items for this Staff ===\n";
$items = PayrollItem::where('staff_id', $staff->id)
    ->with('payrollBatch:id,name,pay_period_start,pay_period_end,status')
    ->orderBy('id', 'desc')
    ->get();

echo "Total items found: " . $items->count() . "\n\n";

foreach ($items as $item) {
    $batch = $item->payrollBatch;
    $periodStart = $batch->pay_period_start->format('Y-m-d');
    $periodEnd = $batch->pay_period_end->format('Y-m-d');
    $monthYear = $batch->pay_period_start->format('F Y');

    echo "Item ID: {$item->id}\n";
    echo "  Batch: {$batch->name} (ID: {$batch->id})\n";
    echo "  Period: {$periodStart} to {$periodEnd} ({$monthYear})\n";
    echo "  Status: {$batch->status}\n";
    echo "  Net Salary: ₦" . number_format($item->net_salary, 2) . "\n";
    echo "\n";
}

echo "\n=== Payroll Items by Month ===\n";
$byMonth = $items->groupBy(function($item) {
    return $item->payrollBatch->pay_period_start->format('Y-m');
});

foreach ($byMonth as $month => $monthItems) {
    echo "{$month}: " . $monthItems->count() . " payslip(s)\n";
    foreach ($monthItems as $item) {
        echo "  - Batch: {$item->payrollBatch->name} | Status: {$item->payrollBatch->status}\n";
    }
}

echo "\n=== ESS Query Check (what the ESS page sees) ===\n";
// This mimics what the ESS controller likely does
$essItems = PayrollItem::where('staff_id', $staff->id)
    ->whereHas('payrollBatch', function($q) {
        $q->whereIn('status', [PayrollBatch::STATUS_APPROVED, PayrollBatch::STATUS_PAID]);
    })
    ->with('payrollBatch')
    ->orderByDesc('id')
    ->get();

echo "ESS visible items: " . $essItems->count() . "\n";
foreach ($essItems as $item) {
    $batch = $item->payrollBatch;
    echo "  - {$batch->pay_period_start->format('F Y')} | Status: {$batch->status}\n";
}
