<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class MaternityEnrollment extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'patient_id', 'enrolled_by', 'entry_point', 'status',
        'enrollment_date', 'booking_date', 'lmp', 'edd', 'gestational_age_at_booking',
        'gravida', 'parity', 'alive', 'abortion_miscarriage',
        'blood_group', 'genotype', 'height_cm', 'booking_weight_kg',
        'booking_bmi', 'booking_bp',
        'risk_level', 'risk_factors',
        'birth_plan_notes', 'preferred_delivery_place',
        'completed_at', 'outcome_summary',
    ];

    protected $casts = [
        'enrollment_date' => 'date',
        'booking_date' => 'date',
        'lmp' => 'date',
        'edd' => 'date',
        'completed_at' => 'datetime',
        'risk_factors' => 'array',
        'height_cm' => 'decimal:1',
        'booking_weight_kg' => 'decimal:2',
        'booking_bmi' => 'decimal:1',
    ];

    /* ── Relationships ─────────────────────── */

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function enrolledBy()
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    public function medicalHistory()
    {
        return $this->hasMany(MaternityMedicalHistory::class, 'enrollment_id');
    }

    public function previousPregnancies()
    {
        return $this->hasMany(MaternityPreviousPregnancy::class, 'enrollment_id');
    }

    public function ancVisits()
    {
        return $this->hasMany(AncVisit::class, 'enrollment_id');
    }

    public function ancInvestigations()
    {
        return $this->hasMany(AncInvestigation::class, 'enrollment_id');
    }

    public function deliveryRecord()
    {
        return $this->hasOne(DeliveryRecord::class, 'enrollment_id');
    }

    public function babies()
    {
        return $this->hasMany(MaternityBaby::class, 'enrollment_id');
    }

    public function postnatalVisits()
    {
        return $this->hasMany(PostnatalVisit::class, 'enrollment_id');
    }

    public function encounterLinks()
    {
        return $this->hasMany(MaternityEncounterLink::class, 'enrollment_id');
    }

    /* ── Scopes ─────────────────────────────── */

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /* ── Helpers ─────────────────────────────── */

    public function getCurrentGestationalAge()
    {
        if (!$this->lmp) return null;
        $weeks = $this->lmp->diffInWeeks(now());
        $days  = $this->lmp->diffInDays(now()) % 7;
        return "{$weeks}w {$days}d";
    }

    public function isHighRisk()
    {
        return $this->risk_level === 'high';
    }

    public function getRemainingDays()
    {
        if (!$this->edd) return null;
        return max(0, now()->diffInDays($this->edd, false));
    }
}
