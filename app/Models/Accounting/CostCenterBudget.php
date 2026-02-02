<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Cost Center Budget Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.11
 */
class CostCenterBudget extends Model
{
    use HasFactory;

    protected $fillable = [
        'cost_center_id',
        'account_id',
        'fiscal_year_id',
        'year',
        'month',
        'budgeted_amount',
        'actual_amount',
        'variance',
        'variance_percentage',
        'is_locked',
        'notes',
    ];

    protected $casts = [
        'budgeted_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'variance' => 'decimal:2',
        'variance_percentage' => 'decimal:2',
        'is_locked' => 'boolean',
    ];

    /**
     * Get the cost center this budget belongs to.
     */
    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * Get the account this budget is for.
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the fiscal year this budget belongs to.
     */
    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
    }
}
