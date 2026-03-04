<?php

namespace App\Models;

use App\Enums\QueueStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class DoctorAppointment extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'patient_id',
        'clinic_id',
        'staff_id',
        'booked_by',
        'appointment_date',
        'start_time',
        'end_time',
        'duration_minutes',
        'status',
        'priority',
        'source',
        'appointment_type',
        'reason',
        'notes',
        'cancellation_reason',
        'doctor_queue_id',
        'service_request_id',
        'parent_appointment_id',
        'is_prepaid_followup',
        'referral_id',
        'rescheduled_from_id',
        'reschedule_count',
        'original_staff_id',
        'reassignment_reason',
        'reassigned_at',
        'checked_in_at',
        'cancelled_at',
        'no_show_marked_at',
    ];

    protected $casts = [
        'appointment_date'   => 'date',
        'status'             => 'integer',
        'duration_minutes'   => 'integer',
        'is_prepaid_followup' => 'boolean',
        'reschedule_count'   => 'integer',
        'reassigned_at'      => 'datetime',
        'checked_in_at'      => 'datetime',
        'cancelled_at'       => 'datetime',
        'no_show_marked_at'  => 'datetime',
    ];

    // ──────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'id');
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class, 'clinic_id', 'id');
    }

    public function doctor()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'id');
    }

    public function bookedBy()
    {
        return $this->belongsTo(Staff::class, 'booked_by', 'id');
    }

    public function doctorQueue()
    {
        return $this->belongsTo(DoctorQueue::class, 'doctor_queue_id', 'id');
    }

    public function serviceRequest()
    {
        return $this->belongsTo(ProductOrServiceRequest::class, 'service_request_id', 'id');
    }

    public function parentAppointment()
    {
        return $this->belongsTo(self::class, 'parent_appointment_id', 'id');
    }

    public function followUps()
    {
        return $this->hasMany(self::class, 'parent_appointment_id', 'id');
    }

    public function referral()
    {
        return $this->belongsTo(SpecialistReferral::class, 'referral_id', 'id');
    }

    public function rescheduledFrom()
    {
        return $this->belongsTo(self::class, 'rescheduled_from_id', 'id');
    }

    public function rescheduledTo()
    {
        return $this->hasOne(self::class, 'rescheduled_from_id', 'id');
    }

    public function originalDoctor()
    {
        return $this->belongsTo(Staff::class, 'original_staff_id', 'id');
    }

    // ──────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────

    /**
     * Appointments that are neither completed, cancelled, nor no-show.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', QueueStatus::ACTIVE);
    }

    /**
     * Appointments for a specific date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('appointment_date', $date);
    }

    /**
     * Appointments for a specific clinic.
     */
    public function scopeForClinic($query, int $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }

    /**
     * Appointments for a specific doctor.
     */
    public function scopeForDoctor($query, int $staffId)
    {
        return $query->where('staff_id', $staffId);
    }

    /**
     * Upcoming scheduled appointments (not yet checked in).
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', QueueStatus::SCHEDULED);
    }

    /**
     * Appointments that have been checked in.
     */
    public function scopeCheckedIn($query)
    {
        return $query->whereNotNull('checked_in_at');
    }

    /**
     * Today's appointments.
     */
    public function scopeToday($query)
    {
        return $query->where('appointment_date', now()->toDateString());
    }

    // ──────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────

    /**
     * Human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return QueueStatus::label($this->status);
    }

    /**
     * Bootstrap badge class for the current status.
     */
    public function getStatusBadgeAttribute(): string
    {
        return QueueStatus::badge($this->status);
    }

    /**
     * Whether the appointment is in a terminal state.
     */
    public function getIsTerminalAttribute(): bool
    {
        return QueueStatus::isTerminal($this->status);
    }

    /**
     * Whether this is a follow-up appointment.
     */
    public function getIsFollowUpAttribute(): bool
    {
        return $this->parent_appointment_id !== null;
    }

    /**
     * Whether this was rescheduled from another appointment.
     */
    public function getIsRescheduledAttribute(): bool
    {
        return $this->rescheduled_from_id !== null;
    }

    /**
     * Whether the doctor was reassigned.
     */
    public function getIsReassignedAttribute(): bool
    {
        return $this->original_staff_id !== null;
    }
}
