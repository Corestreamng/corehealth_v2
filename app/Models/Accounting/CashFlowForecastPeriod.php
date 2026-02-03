<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * CashFlowForecastPeriod Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.8
 */
class CashFlowForecastPeriod extends Model implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'forecast_id',
        'period_number',
        'period_start_date',
        'period_end_date',
        'opening_balance',
        'closing_balance',
        'patient_revenue_cash',
        'patient_revenue_hmo',
        'other_operating_receipts',
        'operating_expenses',
        'salary_wages',
        'supplier_payments',
        'net_operating_cash_flow',
        'capex_payments',
        'asset_disposals',
        'net_investing_cash_flow',
        'loan_receipts',
        'loan_repayments',
        'capital_contributions',
        'dividends_drawings',
        'net_financing_cash_flow',
        'net_cash_flow',
        'actual_closing_balance',
        'variance',
        'variance_explanation',
        'is_locked',
    ];

    protected $casts = [
        'period_start_date' => 'date',
        'period_end_date' => 'date',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'patient_revenue_cash' => 'decimal:2',
        'patient_revenue_hmo' => 'decimal:2',
        'other_operating_receipts' => 'decimal:2',
        'operating_expenses' => 'decimal:2',
        'salary_wages' => 'decimal:2',
        'supplier_payments' => 'decimal:2',
        'net_operating_cash_flow' => 'decimal:2',
        'capex_payments' => 'decimal:2',
        'asset_disposals' => 'decimal:2',
        'net_investing_cash_flow' => 'decimal:2',
        'loan_receipts' => 'decimal:2',
        'loan_repayments' => 'decimal:2',
        'capital_contributions' => 'decimal:2',
        'dividends_drawings' => 'decimal:2',
        'net_financing_cash_flow' => 'decimal:2',
        'net_cash_flow' => 'decimal:2',
        'actual_closing_balance' => 'decimal:2',
        'variance' => 'decimal:2',
        'is_locked' => 'boolean',
    ];

    /**
     * Get the forecast this period belongs to.
     */
    public function forecast(): BelongsTo
    {
        return $this->belongsTo(CashFlowForecast::class, 'forecast_id');
    }

    /**
     * Get the line items for this period.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CashFlowForecastItem::class, 'forecast_period_id');
    }

    /**
     * Get forecasted inflows from items.
     */
    public function getForecastedInflowsAttribute(): float
    {
        return (float) $this->items()
            ->whereIn('cash_flow_category', [
                'operating_inflow',
                'investing_inflow',
                'financing_inflow'
            ])
            ->sum('forecasted_amount');
    }

    /**
     * Get forecasted outflows from items.
     */
    public function getForecastedOutflowsAttribute(): float
    {
        return (float) $this->items()
            ->whereIn('cash_flow_category', [
                'operating_outflow',
                'investing_outflow',
                'financing_outflow'
            ])
            ->sum('forecasted_amount');
    }

    /**
     * Check if this period is the current period.
     */
    public function isCurrent(): bool
    {
        $now = now();
        return $this->period_start_date && $this->period_end_date &&
               $this->period_start_date->lte($now) &&
               $this->period_end_date->gte($now);
    }
}
