<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * CashFlowForecast Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.8
 */
class CashFlowForecast extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'fiscal_year_id',
        'forecast_name',
        'forecast_type',
        'start_date',
        'end_date',
        'scenario',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    // Forecast type constants
    public const TYPE_WEEKLY = 'weekly';
    public const TYPE_MONTHLY = 'monthly';
    public const TYPE_QUARTERLY = 'quarterly';
    public const TYPE_ANNUAL = 'annual';

    // Scenario constants
    public const SCENARIO_BASE = 'base';
    public const SCENARIO_OPTIMISTIC = 'optimistic';
    public const SCENARIO_PESSIMISTIC = 'pessimistic';

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    /**
     * Get the fiscal year for this forecast.
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * Get the user who created this forecast.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Get the user who approved this forecast.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    /**
     * Get the periods for this forecast.
     */
    public function periods(): HasMany
    {
        return $this->hasMany(CashFlowForecastPeriod::class, 'forecast_id');
    }

    /**
     * Scope for active forecasts.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for draft forecasts.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }
}
