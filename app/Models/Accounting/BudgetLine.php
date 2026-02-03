<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class BudgetLine extends Model implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'budget_id',
        'account_id',
        'period_type',
        'period_number',
        'budgeted_amount',
        'actual_amount',
        'variance',
        'variance_percentage',
        'forecast_amount',
        'prior_year_actual',
        'assumptions',
        'is_locked',
    ];

    protected $casts = [
        'budgeted_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'variance' => 'decimal:2',
        'variance_percentage' => 'decimal:2',
        'forecast_amount' => 'decimal:2',
        'prior_year_actual' => 'decimal:2',
        'is_locked' => 'boolean',
        'period_number' => 'integer',
    ];

    /**
     * Get the budget for this line.
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * Get the account for this line.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Calculate variance.
     */
    public function calculateVariance(): void
    {
        $this->variance = $this->actual_amount - $this->budgeted_amount;

        if ($this->budgeted_amount != 0) {
            $this->variance_percentage = ($this->variance / $this->budgeted_amount) * 100;
        } else {
            $this->variance_percentage = 0;
        }
    }
}
