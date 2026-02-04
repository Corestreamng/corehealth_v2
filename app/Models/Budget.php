<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'budgets';

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
        'year' => 'integer',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function fiscalYear()
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines()
    {
        return $this->hasMany(BudgetLine::class);
    }
}
