<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * CashFlowForecastItem Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.8
 */
class CashFlowForecastItem extends Model implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'forecast_period_id',
        'account_id',
        'cash_flow_category',
        'item_description',
        'forecasted_amount',
        'actual_amount',
        'source_type',
        'source_reference',
        'notes',
    ];

    protected $casts = [
        'forecasted_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
    ];

    // Cash flow category constants
    const CATEGORY_OPERATING_INFLOW = 'operating_inflow';
    const CATEGORY_OPERATING_OUTFLOW = 'operating_outflow';
    const CATEGORY_INVESTING_INFLOW = 'investing_inflow';
    const CATEGORY_INVESTING_OUTFLOW = 'investing_outflow';
    const CATEGORY_FINANCING_INFLOW = 'financing_inflow';
    const CATEGORY_FINANCING_OUTFLOW = 'financing_outflow';

    // Source type constants
    const SOURCE_MANUAL = 'manual';
    const SOURCE_RECURRING = 'recurring';
    const SOURCE_PATTERN = 'pattern';       // Auto-applied from recurring patterns
    const SOURCE_SCHEDULED = 'scheduled';
    const SOURCE_HISTORICAL = 'historical';
    const SOURCE_COMMITMENT = 'commitment';

    /**
     * Get the period this item belongs to.
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(CashFlowForecastPeriod::class, 'forecast_period_id');
    }

    /**
     * Get the account for this item.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Check if this is an inflow item.
     */
    public function isInflow(): bool
    {
        return in_array($this->cash_flow_category, [
            self::CATEGORY_OPERATING_INFLOW,
            self::CATEGORY_INVESTING_INFLOW,
            self::CATEGORY_FINANCING_INFLOW,
        ]);
    }

    /**
     * Check if this is an outflow item.
     */
    public function isOutflow(): bool
    {
        return in_array($this->cash_flow_category, [
            self::CATEGORY_OPERATING_OUTFLOW,
            self::CATEGORY_INVESTING_OUTFLOW,
            self::CATEGORY_FINANCING_OUTFLOW,
        ]);
    }
}
