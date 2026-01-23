<?php

namespace App\Models\HR;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * HRMS Implementation Plan - Section 5.2
 * Leave Balance Model for tracking entitlements
 */
class LeaveBalance extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'staff_id',
        'leave_type_id',
        'year',
        'entitled_days',
        'used_days',
        'pending_days',
        'carried_forward'
    ];

    protected $casts = [
        'entitled_days' => 'decimal:1',
        'used_days' => 'decimal:1',
        'pending_days' => 'decimal:1',
        'carried_forward' => 'decimal:1',
    ];

    /**
     * Get the staff member
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get the leave type
     */
    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Get total entitled days (including carried forward)
     */
    public function getTotalEntitledAttribute(): float
    {
        return $this->entitled_days + $this->carried_forward;
    }

    /**
     * Get available balance
     */
    public function getAvailableAttribute(): float
    {
        return $this->total_entitled - $this->used_days - $this->pending_days;
    }

    /**
     * Check if has sufficient balance
     */
    public function hasSufficientBalance(float $days): bool
    {
        return $this->available >= $days;
    }

    /**
     * Scope for current year
     */
    public function scopeCurrentYear($query)
    {
        return $query->where('year', now()->year);
    }

    /**
     * Scope for specific staff
     */
    public function scopeForStaff($query, int $staffId)
    {
        return $query->where('staff_id', $staffId);
    }
}
