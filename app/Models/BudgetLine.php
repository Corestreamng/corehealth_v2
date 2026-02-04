<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BudgetLine extends Model
{
    use HasFactory;

    protected $table = 'budget_lines';

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
        'period_number' => 'integer',
        'is_locked' => 'boolean',
    ];

    // Relationships
    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
