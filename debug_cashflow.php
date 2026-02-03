<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Accounting\CashFlowForecast;
use App\Models\Accounting\CashFlowForecastPeriod;

echo "=== Cash Flow Forecast Debug ===\n\n";

// Get the first forecast
$forecast = CashFlowForecast::with(['periods.items'])->first();

if (!$forecast) {
    echo "No forecast found!\n";
    exit;
}

echo "Forecast: {$forecast->forecast_name}\n";
echo "Type: {$forecast->forecast_type}\n";
echo "Period Count: {$forecast->periods->count()}\n\n";

// Check first period (January)
$firstPeriod = $forecast->periods->first();

if ($firstPeriod) {
    echo "=== First Period (January) ===\n";
    echo "Period: {$firstPeriod->period_start_date->format('M d')} - {$firstPeriod->period_end_date->format('M d')}\n";
    echo "Opening Balance: ₦" . number_format($firstPeriod->opening_balance, 2) . "\n";
    echo "Closing Balance: ₦" . number_format($firstPeriod->closing_balance, 2) . "\n";
    echo "Net Cash Flow (stored): ₦" . number_format($firstPeriod->net_cash_flow, 2) . "\n\n";

    // Check DB columns
    echo "=== Raw DB Values ===\n";
    echo "patient_revenue_cash: ₦" . number_format($firstPeriod->patient_revenue_cash, 2) . "\n";
    echo "patient_revenue_hmo: ₦" . number_format($firstPeriod->patient_revenue_hmo, 2) . "\n";
    echo "other_operating_receipts: ₦" . number_format($firstPeriod->other_operating_receipts, 2) . "\n";
    echo "operating_expenses: ₦" . number_format($firstPeriod->operating_expenses, 2) . "\n";
    echo "salary_wages: ₦" . number_format($firstPeriod->salary_wages, 2) . "\n";
    echo "supplier_payments: ₦" . number_format($firstPeriod->supplier_payments, 2) . "\n\n";

    // Check items
    echo "=== Period Items ===\n";
    echo "Item Count: {$firstPeriod->items->count()}\n\n";

    if ($firstPeriod->items->count() > 0) {
        foreach ($firstPeriod->items as $item) {
            echo "- {$item->item_description}\n";
            echo "  Category: {$item->cash_flow_category}\n";
            echo "  Amount: ₦" . number_format($item->forecasted_amount, 2) . "\n\n";
        }
    } else {
        echo "No items found!\n\n";
    }

    // Check accessor calculations
    echo "=== Model Accessor Calculations ===\n";
    echo "Forecasted Inflows (accessor): ₦" . number_format($firstPeriod->forecasted_inflows, 2) . "\n";
    echo "Forecasted Outflows (accessor): ₦" . number_format($firstPeriod->forecasted_outflows, 2) . "\n";

    $calculatedNet = $firstPeriod->forecasted_inflows - $firstPeriod->forecasted_outflows;
    echo "Calculated Net (inflows - outflows): ₦" . number_format($calculatedNet, 2) . "\n\n";

    // Calculate from items
    $itemInflows = $firstPeriod->items->filter(fn($item) => str_contains($item->cash_flow_category, 'inflow'))
        ->sum('forecasted_amount');
    $itemOutflows = $firstPeriod->items->filter(fn($item) => str_contains($item->cash_flow_category, 'outflow'))
        ->sum('forecasted_amount');

    echo "=== Items Calculation ===\n";
    echo "Item Inflows: ₦" . number_format($itemInflows, 2) . "\n";
    echo "Item Outflows: ₦" . number_format($itemOutflows, 2) . "\n";
    echo "Item Net: ₦" . number_format($itemInflows - $itemOutflows, 2) . "\n\n";

    // Check actuals
    if ($firstPeriod->actual_closing_balance !== null) {
        echo "=== Actuals & Variance ===\n";
        echo "Actual Closing Balance: ₦" . number_format($firstPeriod->actual_closing_balance, 2) . "\n";
        echo "Stored Variance: ₦" . number_format($firstPeriod->variance ?? 0, 2) . "\n";

        // Recalculate variance to show the issue
        $forecastedNet = $firstPeriod->forecasted_inflows - $firstPeriod->forecasted_outflows;
        $forecastedClosing = $firstPeriod->opening_balance + $forecastedNet;
        $calculatedVariance = $firstPeriod->actual_closing_balance - $forecastedClosing;

        echo "\n--- Variance Breakdown ---\n";
        echo "Opening Balance (DB): ₦" . number_format($firstPeriod->opening_balance, 2) . "\n";
        echo "Forecasted Net: ₦" . number_format($forecastedNet, 2) . "\n";
        echo "Forecasted Closing: ₦" . number_format($forecastedClosing, 2) . "\n";
        echo "Actual Closing: ₦" . number_format($firstPeriod->actual_closing_balance, 2) . "\n";
        echo "Calculated Variance: ₦" . number_format($calculatedVariance, 2) . "\n";
        echo "Variance Explanation: " . ($firstPeriod->variance_explanation ?? 'N/A') . "\n\n";

        // Check current cash from GL
        echo "--- What display shows ---\n";
        echo "(Display uses getCurrentCashBalance() as opening, not DB opening_balance)\n";
    }
}

echo "=== Issue Diagnosis ===\n";
if ($firstPeriod->items->count() === 0) {
    echo "❌ Problem: Period has no items!\n";
    echo "   The forecast shows net flow from DB but no items to support it.\n";
    echo "   Solution: Edit the period and add inflow/outflow items.\n\n";
}

if ($firstPeriod->net_cash_flow != 0 && $firstPeriod->items->count() === 0) {
    echo "❌ Problem: net_cash_flow is stored in DB but items don't exist!\n";
    echo "   This causes a mismatch between stored value and calculated value.\n";
    echo "   Solution: Either add items or reset net_cash_flow to 0.\n\n";
}

if ($firstPeriod->forecasted_inflows == 0 && $firstPeriod->forecasted_outflows == 0 && $firstPeriod->net_cash_flow != 0) {
    echo "❌ Problem: Accessors return 0 but net_cash_flow has a value!\n";
    echo "   Accessor uses DB columns (patient_revenue_cash, etc.) but they're all 0.\n";
    echo "   net_cash_flow seems to be manually set or from old data.\n\n";
}

echo "\n=== Recommendation ===\n";
echo "The issue is that the period has a stored net_cash_flow value (₦7,200)\n";
echo "but no actual items or populated DB columns to calculate it from.\n";
echo "You need to either:\n";
echo "1. Add forecast items through 'Edit Forecast'\n";
echo "2. Or manually update the period DB columns with expected amounts\n";
