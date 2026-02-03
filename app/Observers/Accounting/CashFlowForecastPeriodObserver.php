<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\CashFlowForecastPeriod;
use App\Models\Accounting\CashFlowForecastItem;
use App\Models\Accounting\CashFlowPattern;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Cash Flow Forecast Period Observer
 *
 * Automatically applies matching recurring patterns when forecast periods are created.
 * This ensures consistent cash flow projections based on established patterns like:
 * - Monthly payroll
 * - Weekly sales collections
 * - Quarterly loan payments
 * - Annual insurance premiums
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.8
 */
class CashFlowForecastPeriodObserver
{
    /**
     * Handle the CashFlowForecastPeriod "created" event.
     *
     * When a new forecast period is created, this observer:
     * 1. Fetches all active recurring patterns
     * 2. Determines which patterns apply to this period based on frequency
     * 3. Creates forecast items for matching patterns
     * 4. Recalculates period totals
     */
    public function created(CashFlowForecastPeriod $period): void
    {
        $this->applyPatternsToNewPeriod($period);
    }

    /**
     * Apply active patterns to a newly created period
     */
    protected function applyPatternsToNewPeriod(CashFlowForecastPeriod $period): void
    {
        try {
            // Get all active patterns
            $patterns = CashFlowPattern::where('is_active', true)->get();

            if ($patterns->isEmpty()) {
                return;
            }

            // Load the forecast to get forecast_type
            $forecast = $period->forecast;
            if (!$forecast) {
                Log::warning('CashFlowForecastPeriodObserver: Could not load forecast for period', [
                    'period_id' => $period->id
                ]);
                return;
            }

            $itemsCreated = 0;

            foreach ($patterns as $pattern) {
                // Check if pattern applies to this period
                if (!$this->patternAppliesToPeriod($pattern, $period, $forecast->forecast_type)) {
                    continue;
                }

                // Create forecast item from pattern
                CashFlowForecastItem::create([
                    'forecast_period_id' => $period->id,
                    'item_description' => $pattern->pattern_name,
                    'cash_flow_category' => $pattern->cash_flow_category,
                    'forecasted_amount' => $this->calculatePatternAmount($pattern, $period, $forecast->forecast_type),
                    'source_type' => CashFlowForecastItem::SOURCE_PATTERN,
                    'source_reference' => 'pattern:' . $pattern->id,
                ]);

                $itemsCreated++;
            }

            if ($itemsCreated > 0) {
                // Recalculate period totals
                $this->recalculatePeriodTotals($period);

                Log::info('CashFlowForecastPeriodObserver: Applied patterns to new period', [
                    'period_id' => $period->id,
                    'forecast_id' => $forecast->id,
                    'items_created' => $itemsCreated
                ]);
            }

        } catch (\Exception $e) {
            Log::error('CashFlowForecastPeriodObserver: Failed to apply patterns', [
                'period_id' => $period->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Determine if a pattern should apply to a specific period
     *
     * Matching logic:
     * - Weekly patterns → weekly forecasts (every period)
     * - Bi-weekly patterns → weekly forecasts (every other period)
     * - Monthly patterns → monthly forecasts, or first week of month in weekly forecasts
     * - Quarterly patterns → quarterly forecasts, or first month of quarter in monthly
     * - Annual patterns → annual forecasts, or January in other frequencies
     */
    protected function patternAppliesToPeriod(CashFlowPattern $pattern, CashFlowForecastPeriod $period, string $forecastType): bool
    {
        $periodStart = Carbon::parse($period->period_start_date);

        switch ($pattern->frequency) {
            case CashFlowPattern::FREQUENCY_WEEKLY:
                // Weekly patterns apply to all weekly periods
                return $forecastType === 'weekly';

            case CashFlowPattern::FREQUENCY_BI_WEEKLY:
                // Bi-weekly applies every other week (odd period numbers)
                return $forecastType === 'weekly' && ($period->period_number % 2 === 1);

            case CashFlowPattern::FREQUENCY_MONTHLY:
                if ($forecastType === 'monthly') {
                    return true;
                }
                // For weekly forecasts, apply to first week of month or specific day
                if ($forecastType === 'weekly') {
                    $dayOfPeriod = $pattern->day_of_period ?? 1;
                    // Check if this period contains the day_of_period
                    $periodEnd = Carbon::parse($period->period_end_date);
                    return $periodStart->day <= $dayOfPeriod && $periodEnd->day >= $dayOfPeriod;
                }
                return false;

            case CashFlowPattern::FREQUENCY_QUARTERLY:
                if ($forecastType === 'quarterly') {
                    return true;
                }
                // For monthly, apply to first month of quarter (Jan, Apr, Jul, Oct)
                if ($forecastType === 'monthly') {
                    return in_array($periodStart->month, [1, 4, 7, 10]);
                }
                // For weekly, apply to first week of quarter
                if ($forecastType === 'weekly') {
                    return in_array($periodStart->month, [1, 4, 7, 10]) && $periodStart->day <= 7;
                }
                return false;

            case CashFlowPattern::FREQUENCY_ANNUALLY:
                if ($forecastType === 'annual') {
                    return true;
                }
                // For other frequencies, apply in January or on specific day
                $dayOfPeriod = $pattern->day_of_period ?? 1;
                if ($forecastType === 'monthly') {
                    return $periodStart->month === 1;
                }
                if ($forecastType === 'weekly') {
                    return $periodStart->month === 1 && $periodStart->day <= 7;
                }
                return false;

            default:
                return false;
        }
    }

    /**
     * Calculate the amount for a pattern based on period duration
     *
     * Adjusts pattern amount if the forecast period doesn't match pattern frequency.
     * For example, a monthly ₦100,000 rent becomes ~₦23,100 per week.
     */
    protected function calculatePatternAmount(CashFlowPattern $pattern, CashFlowForecastPeriod $period, string $forecastType): float
    {
        $baseAmount = (float) $pattern->expected_amount;

        // If frequencies match directly, use base amount
        $frequencyToType = [
            'weekly' => 'weekly',
            'bi_weekly' => 'weekly', // Still weekly forecast, different occurrence
            'monthly' => 'monthly',
            'quarterly' => 'quarterly',
            'annually' => 'annual',
        ];

        if (isset($frequencyToType[$pattern->frequency]) && $frequencyToType[$pattern->frequency] === $forecastType) {
            return $baseAmount;
        }

        // Convert based on frequency mismatch
        // Monthly pattern → Weekly forecast: divide by ~4.33
        if ($pattern->frequency === 'monthly' && $forecastType === 'weekly') {
            return round($baseAmount / 4.33, 2);
        }

        // Weekly pattern → Monthly forecast: multiply by ~4.33
        if ($pattern->frequency === 'weekly' && $forecastType === 'monthly') {
            return round($baseAmount * 4.33, 2);
        }

        // Quarterly pattern → Monthly forecast: divide by 3
        if ($pattern->frequency === 'quarterly' && $forecastType === 'monthly') {
            return round($baseAmount / 3, 2);
        }

        // Quarterly pattern → Weekly forecast: divide by 13
        if ($pattern->frequency === 'quarterly' && $forecastType === 'weekly') {
            return round($baseAmount / 13, 2);
        }

        // Annual pattern → Monthly forecast: divide by 12
        if ($pattern->frequency === 'annually' && $forecastType === 'monthly') {
            return round($baseAmount / 12, 2);
        }

        // Annual pattern → Weekly forecast: divide by 52
        if ($pattern->frequency === 'annually' && $forecastType === 'weekly') {
            return round($baseAmount / 52, 2);
        }

        return $baseAmount;
    }

    /**
     * Recalculate period totals after adding items
     */
    protected function recalculatePeriodTotals(CashFlowForecastPeriod $period): void
    {
        $period->refresh();

        $inflowTotal = $period->items
            ->filter(fn($item) => str_contains($item->cash_flow_category, 'inflow'))
            ->sum('forecasted_amount');

        $outflowTotal = $period->items
            ->filter(fn($item) => str_contains($item->cash_flow_category, 'outflow'))
            ->sum('forecasted_amount');

        $netCashFlow = $inflowTotal - $outflowTotal;

        $period->update([
            'net_cash_flow' => $netCashFlow,
            'closing_balance' => ($period->opening_balance ?? 0) + $netCashFlow
        ]);
    }
}
