<?php

namespace App\Models;

use App\Models\HR\LeaveRequest;
use App\Models\HR\LeaveBalance;
use App\Models\HR\DisciplinaryQuery;
use App\Models\HR\StaffSuspension;
use App\Models\HR\StaffTermination;
use App\Models\HR\StaffSalaryProfile;
use App\Models\HR\PayrollItem;
use App\Models\HR\HrAttachment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Staff Model - Extended with HRMS relationships
 * HRMS Implementation Plan - Section 5.3
 */
class Staff extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        // Original fields
        'user_id',
        'specialization_id',
        'clinic_id',
        'gender',
        'date_of_birth',
        'home_address',
        'phone_number',
        'consultation_fee',
        'is_unit_head',
        'is_dept_head',
        'status',

        // HR Fields (from migration 2026_01_23_100001)
        'employee_id',
        'date_hired',
        'employment_type',
        'employment_status',
        'job_title',
        'department_id', // Changed from 'department' to foreign key

        // Bank information
        'bank_name',
        'bank_account_number',
        'bank_account_name',

        // Emergency contact
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',

        // Tax & pension
        'tax_id',
        'pension_id',

        // Suspension fields
        'suspended_at',
        'suspended_by',
        'suspension_reason',
        'suspension_end_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_unit_head' => 'boolean',
        'is_dept_head' => 'boolean',
        'date_of_birth' => 'date',
        'date_hired' => 'date',
        'suspended_at' => 'datetime',
        'suspension_end_date' => 'date',
    ];

    // Employment Types
    const EMPLOYMENT_FULL_TIME = 'full_time';
    const EMPLOYMENT_PART_TIME = 'part_time';
    const EMPLOYMENT_CONTRACT = 'contract';
    const EMPLOYMENT_INTERN = 'intern';

    // Employment Statuses
    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_RESIGNED = 'resigned';
    const STATUS_TERMINATED = 'terminated';

    // ====================
    // ORIGINAL RELATIONSHIPS
    // ====================

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function specialization()
    {
        return $this->belongsTo(Specialization::class, 'specialization_id', 'id');
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class, 'clinic_id', 'id');
    }

    /**
     * Get the department this staff belongs to
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    // ====================
    // HR RELATIONSHIPS
    // ====================

    /**
     * Get leave requests for this staff
     */
    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Get leave balances for this staff
     */
    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * Get disciplinary queries for this staff
     */
    public function disciplinaryQueries()
    {
        return $this->hasMany(DisciplinaryQuery::class);
    }

    /**
     * Get suspensions for this staff
     */
    public function suspensions()
    {
        return $this->hasMany(StaffSuspension::class);
    }

    /**
     * Get active suspension
     */
    public function activeSuspension()
    {
        return $this->hasOne(StaffSuspension::class)->active();
    }

    /**
     * Get termination record
     */
    public function termination()
    {
        return $this->hasOne(StaffTermination::class);
    }

    /**
     * Get salary profiles
     */
    public function salaryProfiles()
    {
        return $this->hasMany(StaffSalaryProfile::class);
    }

    /**
     * Get current salary profile
     */
    public function currentSalaryProfile()
    {
        return $this->hasOne(StaffSalaryProfile::class)->current();
    }

    /**
     * Get payroll items
     */
    public function payrollItems()
    {
        return $this->hasMany(PayrollItem::class);
    }

    /**
     * Get HR attachments
     */
    public function hrAttachments()
    {
        return $this->morphMany(HrAttachment::class, 'attachable');
    }

    /**
     * Get who suspended this staff
     */
    public function suspendedBy()
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    // ====================
    // HR HELPER METHODS
    // ====================

    /**
     * Check if staff is suspended
     */
    public function isSuspended(): bool
    {
        return $this->employment_status === self::STATUS_SUSPENDED;
    }

    /**
     * Check if staff is active
     */
    public function isActive(): bool
    {
        return $this->employment_status === self::STATUS_ACTIVE ||
               $this->employment_status === null; // Legacy compatibility
    }

    /**
     * Check if staff can login (not suspended or terminated)
     */
    public function canLogin(): bool
    {
        return in_array($this->employment_status, [self::STATUS_ACTIVE, null]);
    }

    /**
     * Get suspension message for login blocking
     */
    public function getSuspensionMessageAttribute(): ?string
    {
        $activeSuspension = $this->activeSuspension;
        return $activeSuspension?->suspension_message;
    }

    /**
     * Get leave balance for a specific leave type and year
     */
    public function getLeaveBalance(int $leaveTypeId, ?int $year = null): ?LeaveBalance
    {
        $year = $year ?? now()->year;
        return $this->leaveBalances()
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();
    }

    /**
     * Get full name (from user relationship)
     */
    public function getFullNameAttribute(): string
    {
        return $this->user?->name ?? 'Unknown Staff';
    }

    /**
     * Get employment types
     */
    public static function getEmploymentTypes(): array
    {
        return [
            self::EMPLOYMENT_FULL_TIME => 'Full Time',
            self::EMPLOYMENT_PART_TIME => 'Part Time',
            self::EMPLOYMENT_CONTRACT => 'Contract',
            self::EMPLOYMENT_INTERN => 'Intern',
        ];
    }

    /**
     * Get employment statuses
     */
    public static function getEmploymentStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_SUSPENDED => 'Suspended',
            self::STATUS_RESIGNED => 'Resigned',
            self::STATUS_TERMINATED => 'Terminated',
        ];
    }

    /**
     * Scope for active staff
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('employment_status', self::STATUS_ACTIVE)
              ->orWhereNull('employment_status');
        });
    }

    /**
     * Scope for suspended staff
     */
    public function scopeSuspended($query)
    {
        return $query->where('employment_status', self::STATUS_SUSPENDED);
    }

    /**
     * Scope for staff with salary profiles (for payroll)
     */
    public function scopeWithSalaryProfile($query)
    {
        return $query->whereHas('currentSalaryProfile');
    }
}
