<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * CashFlowPattern Model (Recurring Cash Flow Patterns)
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.8
 */
class CashFlowPattern extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'cash_flow_recurring_patterns';

    protected $fillable = [
        'pattern_name',
        'account_id',
        'cash_flow_category',
        'frequency',
        'day_of_period',
        'expected_amount',
        'variance_percentage',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'expected_amount' => 'decimal:2',
        'variance_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Cash flow category constants
    public const CATEGORY_OPERATING_INFLOW = 'operating_inflow';
    public const CATEGORY_OPERATING_OUTFLOW = 'operating_outflow';
    public const CATEGORY_INVESTING_INFLOW = 'investing_inflow';
    public const CATEGORY_INVESTING_OUTFLOW = 'investing_outflow';
    public const CATEGORY_FINANCING_INFLOW = 'financing_inflow';
    public const CATEGORY_FINANCING_OUTFLOW = 'financing_outflow';

    // Frequency constants
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_BI_WEEKLY = 'bi_weekly';
    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_QUARTERLY = 'quarterly';
    public const FREQUENCY_ANNUALLY = 'annually';

    /**
     * Get the account for this pattern.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Scope for active patterns.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
