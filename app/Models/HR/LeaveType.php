<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * HRMS Implementation Plan - Section 5.2
 * Leave Type Model with configurable constraints
 */
class LeaveType extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'code',
        'description',
        'max_days_per_year',
        'max_consecutive_days',
        'max_requests_per_year',
        'min_days_notice',
        'requires_attachment',
        'is_paid',
        'is_active',
        'color',
        'applicable_employment_types'
    ];

    protected $casts = [
        'requires_attachment' => 'boolean',
        'is_paid' => 'boolean',
        'is_active' => 'boolean',
        'applicable_employment_types' => 'array',
    ];

    /**
     * Get leave requests of this type
     */
    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Get leave balances for this type
     */
    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * Scope for active leave types
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if this leave type is applicable for an employment type
     */
    public function isApplicableFor(string $employmentType): bool
    {
        if (empty($this->applicable_employment_types)) {
            return true; // Applicable to all if not specified
        }

        return in_array($employmentType, $this->applicable_employment_types);
    }

    /**
     * Get color for calendar display
     */
    public function getCalendarColorAttribute(): string
    {
        return $this->color ?? '#3498db';
    }
}
