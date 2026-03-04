<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class SpecialistReferral extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'patient_id',
        'encounter_id',
        'referring_doctor_id',
        'referring_clinic_id',
        'referral_type',
        'target_clinic_id',
        'target_doctor_id',
        'external_facility_name',
        'external_doctor_name',
        'external_facility_address',
        'external_facility_phone',
        'target_specialization_id',
        'reason',
        'clinical_summary',
        'provisional_diagnosis',
        'urgency',
        'status',
        'actioned_by',
        'actioned_at',
        'action_notes',
        'appointment_id',
        'referral_letter_attachment_id',
    ];

    protected $casts = [
        'actioned_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_PENDING      = 'pending';
    public const STATUS_BOOKED       = 'booked';
    public const STATUS_REFERRED_OUT = 'referred_out';
    public const STATUS_COMPLETED    = 'completed';
    public const STATUS_DECLINED     = 'declined';
    public const STATUS_CANCELLED    = 'cancelled';

    // Urgency constants
    public const URGENCY_ROUTINE   = 'routine';
    public const URGENCY_URGENT    = 'urgent';
    public const URGENCY_EMERGENCY = 'emergency';

    // ──────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'id');
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class, 'encounter_id', 'id');
    }

    public function referringDoctor()
    {
        return $this->belongsTo(Staff::class, 'referring_doctor_id', 'id');
    }

    public function referringClinic()
    {
        return $this->belongsTo(Clinic::class, 'referring_clinic_id', 'id');
    }

    public function targetClinic()
    {
        return $this->belongsTo(Clinic::class, 'target_clinic_id', 'id');
    }

    public function targetDoctor()
    {
        return $this->belongsTo(Staff::class, 'target_doctor_id', 'id');
    }

    public function targetSpecialization()
    {
        return $this->belongsTo(Specialization::class, 'target_specialization_id', 'id');
    }

    public function actionedBy()
    {
        return $this->belongsTo(Staff::class, 'actioned_by', 'id');
    }

    public function appointment()
    {
        return $this->belongsTo(DoctorAppointment::class, 'appointment_id', 'id');
    }

    // ──────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeInternal($query)
    {
        return $query->where('referral_type', 'internal');
    }

    public function scopeExternal($query)
    {
        return $query->where('referral_type', 'external');
    }

    public function scopeActionable($query)
    {
        return $query->whereIn('status', [self::STATUS_PENDING]);
    }

    // ──────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────

    public function getIsInternalAttribute(): bool
    {
        return $this->referral_type === 'internal';
    }

    public function getIsExternalAttribute(): bool
    {
        return $this->referral_type === 'external';
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
