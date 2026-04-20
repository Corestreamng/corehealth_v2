<?php

namespace App\Models;

use App\Models\HR\Cadre;
use App\Models\HR\DisciplinaryQuery;
use App\Models\HR\GradeLevel;
use App\Models\HR\HrAttachment;
use App\Models\HR\LeaveBalance;
use App\Models\HR\LeaveRequest;
use App\Models\HR\PayrollItem;
use App\Models\HR\StaffFollowUp;
use App\Models\HR\StaffMedicalExam;
use App\Models\HR\StaffNextOfKin;
use App\Models\HR\StaffPromotion;
use App\Models\HR\StaffQualification;
use App\Models\HR\StaffSalaryProfile;
use App\Models\HR\StaffSuspension;
use App\Models\HR\StaffTermination;
use App\Models\HR\StaffTraining;
use App\Models\HR\Unit;
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
        'can_see_clinic_queues',
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
        'department_id',

        // Organizational structure (from migration 2026_04_19)
        'unit_id',
        'cadre_id',
        'grade_level_id',
        'entry_grade_level_id',

        // Professional licensing
        'license_number',
        'license_expiry_date',
        'national_id_number',

        // Job location & responsibility
        'job_location',
        'responsibility',

        // Personal details
        'marital_status',
        'number_of_children',
        'permanent_home_address',
        'other_talents',

        // Confirmation tracking
        'date_confirmed',
        'confirmation_due_date',

        // Retirement & exit planning
        'retirement_date',
        'max_service_date',

        // Promotion tracking (denormalized)
        'last_promotion_date',
        'next_promotion_due_date',

        // Medical exam tracking (denormalized)
        'last_medical_exam_date',
        'next_medical_exam_due',

        // Salary increment tracking
        'salary_increment_date',

        // Bank information
        'bank_name',
        'bank_account_number',
        'bank_account_name',

        // Emergency contact (legacy - replaced by staff_next_of_kin)
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',

        // Tax & pension
        'tax_id',
        'pension_id',

        // HR notes
        'hr_notes',

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
        'is_unit_head'            => 'boolean',
        'is_dept_head'            => 'boolean',
        'date_of_birth'           => 'date',
        'date_hired'              => 'date',
        'date_confirmed'          => 'date',
        'confirmation_due_date'   => 'date',
        'license_expiry_date'     => 'date',
        'retirement_date'         => 'date',
        'max_service_date'        => 'date',
        'last_promotion_date'     => 'date',
        'next_promotion_due_date' => 'date',
        'last_medical_exam_date'  => 'date',
        'next_medical_exam_due'   => 'date',
        'salary_increment_date'   => 'date',
        'suspended_at'            => 'datetime',
        'suspension_end_date'     => 'date',
        'can_see_clinic_queues'   => 'array',
        'number_of_children'      => 'integer',
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

    // Marital Statuses
    const MARITAL_SINGLE = 'single';
    const MARITAL_MARRIED = 'married';
    const MARITAL_DIVORCED = 'divorced';
    const MARITAL_WIDOWED = 'widowed';
    const MARITAL_SEPARATED = 'separated';

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
     * All clinic IDs this doctor can monitor (primary + can_see_clinic_queues).
     * Usage: $doctor->all_clinic_ids  => [1, 3, 7]
     */
    public function getAllClinicIdsAttribute(): array
    {
        $ids = array_merge(
            $this->clinic_id ? [$this->clinic_id] : [],
            $this->can_see_clinic_queues ?? []
        );
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Get the department this staff belongs to
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    // ====================
    // APPOINTMENT / AVAILABILITY RELATIONSHIPS
    // ====================

    /**
     * Get weekly availability slots for this staff
     */
    public function availabilities()
    {
        return $this->hasMany(DoctorAvailability::class, 'staff_id', 'id');
    }

    /**
     * Get availability overrides (holidays, extra slots)
     */
    public function availabilityOverrides()
    {
        return $this->hasMany(DoctorAvailabilityOverride::class, 'staff_id', 'id');
    }

    /**
     * Get appointments assigned to this staff
     */
    public function appointments()
    {
        return $this->hasMany(DoctorAppointment::class, 'staff_id', 'id');
    }

    /**
     * Get referrals made by this staff
     */
    public function referralsMade()
    {
        return $this->hasMany(SpecialistReferral::class, 'referring_doctor_id', 'id');
    }

    /**
     * Get referrals targeting this staff
     */
    public function referralsReceived()
    {
        return $this->hasMany(SpecialistReferral::class, 'target_doctor_id', 'id');
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
    // ENHANCED HR RELATIONSHIPS
    // ====================

    /**
     * Get the unit this staff belongs to
     */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the cadre for this staff
     */
    public function cadre()
    {
        return $this->belongsTo(Cadre::class);
    }

    /**
     * Get the current grade level
     */
    public function gradeLevel()
    {
        return $this->belongsTo(GradeLevel::class);
    }

    /**
     * Get the entry (initial) grade level
     */
    public function entryGradeLevel()
    {
        return $this->belongsTo(GradeLevel::class, 'entry_grade_level_id');
    }

    /**
     * Get all qualifications
     */
    public function qualifications()
    {
        return $this->hasMany(StaffQualification::class);
    }

    /**
     * Get the entry qualification
     */
    public function entryQualification()
    {
        return $this->hasOne(StaffQualification::class)->where('type', 'entry')->latest();
    }

    /**
     * Get additional qualifications
     */
    public function additionalQualifications()
    {
        return $this->hasMany(StaffQualification::class)->where('type', 'additional');
    }

    /**
     * Get promotion history
     */
    public function promotions()
    {
        return $this->hasMany(StaffPromotion::class)->orderByDesc('promotion_date');
    }

    /**
     * Get the latest promotion
     */
    public function latestPromotion()
    {
        return $this->hasOne(StaffPromotion::class)->latestOfMany('promotion_date');
    }

    /**
     * Get training records
     */
    public function trainings()
    {
        return $this->hasMany(StaffTraining::class);
    }

    /**
     * Get medical exams
     */
    public function medicalExams()
    {
        return $this->hasMany(StaffMedicalExam::class)->orderByDesc('exam_date');
    }

    /**
     * Get the latest medical exam
     */
    public function latestMedicalExam()
    {
        return $this->hasOne(StaffMedicalExam::class)->latestOfMany('exam_date');
    }

    /**
     * Get next of kin records
     */
    public function nextOfKin()
    {
        return $this->hasMany(StaffNextOfKin::class);
    }

    /**
     * Get primary next of kin
     */
    public function primaryNextOfKin()
    {
        return $this->hasOne(StaffNextOfKin::class)->where('is_primary', true);
    }

    /**
     * Get follow-ups for this staff
     */
    public function followUps()
    {
        return $this->hasMany(StaffFollowUp::class);
    }

    /**
     * Get open follow-ups
     */
    public function openFollowUps()
    {
        return $this->hasMany(StaffFollowUp::class)->open();
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

    // ====================
    // COMPUTED ATTRIBUTES
    // ====================

    /**
     * Years of service since date_hired
     */
    public function getYearsOfServiceAttribute(): ?float
    {
        if (!$this->date_hired) return null;
        return round($this->date_hired->diffInYears(now()), 1);
    }

    /**
     * Age from date of birth
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->date_of_birth) return null;
        return $this->date_of_birth->age;
    }

    /**
     * Expected exit date based on retirement age from grade level
     */
    public function getExpectedExitByAgeAttribute(): ?\Carbon\Carbon
    {
        if (!$this->date_of_birth) return null;
        $retirementAge = $this->gradeLevel?->retirement_age ?? 60;
        return $this->date_of_birth->copy()->addYears($retirementAge);
    }

    /**
     * Expected exit date based on max years of service from grade level
     */
    public function getExpectedExitByServiceAttribute(): ?\Carbon\Carbon
    {
        if (!$this->date_hired) return null;
        $maxYears = $this->gradeLevel?->max_years_of_service ?? 35;
        return $this->date_hired->copy()->addYears($maxYears);
    }

    /**
     * Gross annual salary from current salary profile
     */
    public function getGrossAnnualSalaryAttribute(): ?float
    {
        $profile = $this->currentSalaryProfile;
        if (!$profile) return null;
        return (float) $profile->gross_salary * 12;
    }

    /**
     * Check if promotion is overdue
     */
    public function getIsPromotionDueAttribute(): bool
    {
        return $this->next_promotion_due_date && $this->next_promotion_due_date->isPast();
    }

    /**
     * Check if confirmation is overdue
     */
    public function getIsConfirmationDueAttribute(): bool
    {
        return $this->confirmation_due_date
            && $this->confirmation_due_date->isPast()
            && !$this->date_confirmed;
    }

    /**
     * Check if license is expiring within 90 days
     */
    public function getIsLicenseExpiringAttribute(): bool
    {
        return $this->license_expiry_date
            && $this->license_expiry_date->isBetween(now(), now()->addDays(90));
    }

    /**
     * Check if license has expired
     */
    public function getIsLicenseExpiredAttribute(): bool
    {
        return $this->license_expiry_date && $this->license_expiry_date->isPast();
    }

    /**
     * Check if medical exam is due
     */
    public function getIsMedicalExamDueAttribute(): bool
    {
        return $this->next_medical_exam_due && $this->next_medical_exam_due->isPast();
    }

    /**
     * Compute retirement date from DOB + grade level retirement age
     */
    public function computeRetirementDate(): ?\Carbon\Carbon
    {
        if (!$this->date_of_birth) return null;
        $retirementAge = $this->gradeLevel?->retirement_age ?? 60;
        return $this->date_of_birth->copy()->addYears($retirementAge);
    }

    /**
     * Compute max service date from hire date + grade level max years
     */
    public function computeMaxServiceDate(): ?\Carbon\Carbon
    {
        if (!$this->date_hired) return null;
        $maxYears = $this->gradeLevel?->max_years_of_service ?? 35;
        return $this->date_hired->copy()->addYears($maxYears);
    }

    /**
     * Recalculate and save exit dates based on current grade level
     */
    public function recalculateExitDates(): void
    {
        $this->retirement_date = $this->computeRetirementDate();
        $this->max_service_date = $this->computeMaxServiceDate();
        $this->saveQuietly();
    }

    /**
     * Get marital statuses for forms
     */
    public static function getMaritalStatuses(): array
    {
        return [
            self::MARITAL_SINGLE => 'Single',
            self::MARITAL_MARRIED => 'Married',
            self::MARITAL_DIVORCED => 'Divorced',
            self::MARITAL_WIDOWED => 'Widowed',
            self::MARITAL_SEPARATED => 'Separated',
        ];
    }
}
