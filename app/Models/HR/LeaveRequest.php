<?php

namespace App\Models\HR;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * HRMS Implementation Plan - Section 5.2
 * Leave Request Model with Two-Level Approval Workflow:
 * 1. First Level: Unit Head (same department) OR Dept Head (same user category)
 * 2. Second Level: HR Manager (only after first level approved)
 */
class LeaveRequest extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'request_number',
        'staff_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'total_days',
        'is_half_day',
        'reason',
        'handover_notes',
        'contact_during_leave',
        'relief_staff_id',
        'status',
        // First level approval
        'supervisor_approved_by',
        'supervisor_approved_at',
        'supervisor_comments',
        // Second level approval (HR)
        'hr_approved_by',
        'hr_approved_at',
        'hr_comments',
        // Legacy/combined
        'reviewed_by',
        'reviewed_at',
        'review_comments'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_half_day' => 'boolean',
        'supervisor_approved_at' => 'datetime',
        'hr_approved_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    // Status Constants
    const STATUS_PENDING = 'pending';                       // Awaiting first-level approval
    const STATUS_SUPERVISOR_APPROVED = 'supervisor_approved'; // First level approved, awaiting HR
    const STATUS_APPROVED = 'approved';                     // HR approved (final)
    const STATUS_REJECTED = 'rejected';                     // Rejected at any stage
    const STATUS_CANCELLED = 'cancelled';                   // Cancelled by staff
    const STATUS_RECALLED = 'recalled';                     // Recalled after approval

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->request_number)) {
                $model->request_number = self::generateRequestNumber();
            }
        });
    }

    /**
     * Generate unique request number
     */
    public static function generateRequestNumber(): string
    {
        $prefix = 'LR';
        $year = date('Y');
        $lastRequest = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastRequest ? (int) substr($lastRequest->request_number, -6) + 1 : 1;
        return $prefix . $year . str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }

    // ==================== RELATIONSHIPS ====================

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
     * Get the relief staff
     */
    public function reliefStaff()
    {
        return $this->belongsTo(Staff::class, 'relief_staff_id');
    }

    /**
     * Get the supervisor who approved (first level)
     */
    public function supervisorApprovedBy()
    {
        return $this->belongsTo(User::class, 'supervisor_approved_by');
    }

    /**
     * Get the HR manager who approved (second level)
     */
    public function hrApprovedBy()
    {
        return $this->belongsTo(User::class, 'hr_approved_by');
    }

    /**
     * Get the final reviewer (legacy)
     */
    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get attachments
     */
    public function attachments()
    {
        return $this->morphMany(HrAttachment::class, 'attachable');
    }

    // ==================== SCOPES ====================

    /**
     * Scope for pending requests (awaiting first-level approval)
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for supervisor approved requests (awaiting HR approval)
     */
    public function scopeSupervisorApproved($query)
    {
        return $query->where('status', self::STATUS_SUPERVISOR_APPROVED);
    }

    /**
     * Scope for fully approved requests
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for requests pending HR action (supervisor_approved)
     */
    public function scopePendingHrApproval($query)
    {
        return $query->where('status', self::STATUS_SUPERVISOR_APPROVED);
    }

    /**
     * Scope for requests the user can approve as supervisor
     * (user is unit head/dept head for the staff member)
     * Includes own leave requests if user has supervisor rights
     */
    public function scopeCanApproveAsSupervisor($query, User $user)
    {
        $staffProfile = $user->staff_profile;

        if (!$staffProfile) {
            return $query->whereRaw('1 = 0'); // Return empty if no staff profile
        }

        return $query->where('status', self::STATUS_PENDING)
            ->whereHas('staff', function ($q) use ($user, $staffProfile) {
                $q->where(function ($sub) use ($user, $staffProfile) {
                    // Unit Head: same department (department_id)
                    if ($staffProfile->is_unit_head && $staffProfile->department_id) {
                        $sub->orWhere('department_id', $staffProfile->department_id);
                    }
                    // Dept Head: same user category (is_admin) - broader authority across category
                    if ($staffProfile->is_dept_head) {
                        $sub->orWhereHas('user', function ($userQ) use ($user) {
                            $userQ->where('is_admin', $user->is_admin);
                        });
                    }
                });
                // Note: Removed exclusion of own leave requests
                // Supervisors can now approve first stage of their own leave if they have the rights
            });
    }

    /**
     * Scope for ALL requests under supervisor's jurisdiction (any status)
     * Used to show requests that went through the supervisor's desk
     */
    public function scopeUnderSupervisorJurisdiction($query, User $user)
    {
        $staffProfile = $user->staff_profile;

        if (!$staffProfile) {
            return $query->whereRaw('1 = 0'); // Return empty if no staff profile
        }

        return $query->whereHas('staff', function ($q) use ($user, $staffProfile) {
            $q->where(function ($sub) use ($user, $staffProfile) {
                // Unit Head: same department (department_id)
                if ($staffProfile->is_unit_head && $staffProfile->department_id) {
                    $sub->orWhere('department_id', $staffProfile->department_id);
                }
                // Dept Head: same user category (is_admin) - broader authority across category
                if ($staffProfile->is_dept_head) {
                    $sub->orWhereHas('user', function ($userQ) use ($user) {
                        $userQ->where('is_admin', $user->is_admin);
                    });
                }
            });
        });
    }

    // ==================== STATUS CHECKS ====================

    /**
     * Check if the request is pending (awaiting first-level)
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if supervisor has approved (awaiting HR)
     */
    public function isSupervisorApproved(): bool
    {
        return $this->status === self::STATUS_SUPERVISOR_APPROVED;
    }

    /**
     * Check if the request is fully approved
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if rejected
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if the user can approve this request as supervisor
     * Allows supervisors to approve first stage of their own leave if they have the rights
     */
    public function canBeApprovedBySupervisor(User $user): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $staffProfile = $user->staff_profile;
        if (!$staffProfile) {
            return false;
        }

        // Note: Removed check that prevented approving own request
        // Supervisors can now approve first stage of their own leave

        $applicantStaff = $this->staff;

        // Unit Head: same department (department_id)
        if ($staffProfile->is_unit_head &&
            $staffProfile->department_id &&
            $staffProfile->department_id === $applicantStaff->department_id) {
            return true;
        }

        // Dept Head: same user category (is_admin) - broader authority across category
        if ($staffProfile->is_dept_head &&
            $user->is_admin === $applicantStaff->user->is_admin) {
            return true;
        }

        return false;
    }

    /**
     * Check if the user can approve this request as HR
     */
    public function canBeApprovedByHr(User $user): bool
    {
        // Must be supervisor_approved status
        if (!$this->isSupervisorApproved()) {
            return false;
        }

        // User must have HR Manager role or leave-request.hr-approve permission
        return $user->hasAnyRole(['SUPERADMIN', 'ADMIN', 'HR MANAGER']) ||
               $user->can('leave-request.hr-approve');
    }

    // ==================== ATTRIBUTES ====================

    /**
     * Get status badge class
     */
    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_SUPERVISOR_APPROVED => 'info',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_CANCELLED => 'secondary',
            self::STATUS_RECALLED => 'dark',
            default => 'secondary'
        };
    }

    /**
     * Get human-readable status label
     */
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending Supervisor',
            self::STATUS_SUPERVISOR_APPROVED => 'Pending HR Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_RECALLED => 'Recalled',
            default => ucfirst($this->status)
        };
    }
}
