<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\User;
use App\Models\Department;
use App\Models\Accounting\CostCenter;

class Budget extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'budget_name',
        'fiscal_year_id',
        'year',
        'department_id',
        'cost_center_id',
        'budget_type',
        'total_budgeted',
        'total_actual',
        'total_variance',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'total_budgeted' => 'decimal:2',
        'total_actual' => 'decimal:2',
        'total_variance' => 'decimal:2',
        'approved_at' => 'datetime',
        'year' => 'integer',
    ];

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_APPROVED = 'approved';
    const STATUS_LOCKED = 'locked';

    // Type constants
    const TYPE_OPERATING = 'operating';
    const TYPE_CAPITAL = 'capital';
    const TYPE_REVENUE = 'revenue';

    /**
     * Get the fiscal year for this budget.
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * Get the department for this budget.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the cost center for this budget.
     */
    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(CostCenter::class);
    }

    /**
     * Get the user who created this budget.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this budget.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the budget lines for this budget.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class);
    }

    /**
     * Alias for lines() - backward compatibility.
     */
    public function items(): HasMany
    {
        return $this->lines();
    }

    /**
     * Check if budget is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if budget is locked.
     */
    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    /**
     * Calculate variance percentage.
     */
    public function getVariancePercentageAttribute(): float
    {
        if ($this->total_budgeted == 0) {
            return 0;
        }
        return ($this->total_variance / $this->total_budgeted) * 100;
    }
}
