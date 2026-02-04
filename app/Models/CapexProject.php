<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CapexProject extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'capex_projects';

    protected $fillable = [
        'project_code',
        'reference_number',
        'project_name',
        'title',
        'description',
        'project_type',
        'category',
        'department_id',
        'cost_center_id',
        'vendor_id',
        'fixed_asset_category_id',
        'fiscal_year',
        // Financial
        'estimated_cost',
        'requested_amount',
        'approved_budget',
        'approved_amount',
        'actual_cost',
        'actual_amount',
        'committed_cost',
        'remaining_budget',
        // Timeline
        'proposed_date',
        'approved_date',
        'expected_start_date',
        'expected_completion_date',
        'actual_start_date',
        'actual_completion_date',
        'submitted_at',
        // Approval
        'requested_by',
        'approved_by',
        'justification',
        'rejection_reason',
        // Status
        'status',
        'priority',
        'completion_percentage',
        // ROI
        'expected_benefits',
        'expected_annual_savings',
        'expected_payback_months',
        'expected_roi_percentage',
        'notes',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:2',
        'requested_amount' => 'decimal:2',
        'approved_budget' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'committed_cost' => 'decimal:2',
        'remaining_budget' => 'decimal:2',
        'expected_annual_savings' => 'decimal:2',
        'expected_roi_percentage' => 'decimal:2',
        'completion_percentage' => 'integer',
        'expected_payback_months' => 'integer',
        'fiscal_year' => 'integer',
        'proposed_date' => 'date',
        'approved_date' => 'date',
        'expected_start_date' => 'date',
        'expected_completion_date' => 'date',
        'actual_start_date' => 'date',
        'actual_completion_date' => 'date',
        'submitted_at' => 'datetime',
    ];

    // Relationships
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function costCenter()
    {
        return $this->belongsTo(CostCenter::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Supplier::class, 'vendor_id');
    }

    public function fixedAssetCategory()
    {
        return $this->belongsTo(FixedAssetCategory::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function expenses()
    {
        return $this->hasMany(CapexProjectExpense::class, 'project_id');
    }

    public function items()
    {
        return $this->hasMany(CapexRequestItem::class, 'capex_request_id');
    }

    public function approvalHistory()
    {
        return $this->hasMany(CapexApprovalHistory::class, 'capex_request_id');
    }
}
