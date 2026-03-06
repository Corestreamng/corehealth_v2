<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class AncVisit extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'enrollment_id', 'patient_id', 'encounter_id',
        'visit_number', 'visit_type', 'visit_date',
        'gestational_age_weeks', 'gestational_age_days',
        'weight_kg', 'blood_pressure_systolic', 'blood_pressure_diastolic',
        'fundal_height_cm', 'presentation', 'fetal_heart_rate',
        'foetal_movement', 'oedema', 'urine_protein', 'urine_glucose',
        'haemoglobin', 'clinical_notes', 'next_appointment', 'seen_by',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'next_appointment' => 'date',
        'weight_kg' => 'decimal:2',
        'fundal_height_cm' => 'decimal:1',
        'haemoglobin' => 'decimal:1',
    ];

    /* ── Relationships ─────────────────────── */

    public function enrollment()
    {
        return $this->belongsTo(MaternityEnrollment::class, 'enrollment_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class, 'encounter_id');
    }

    public function seenBy()
    {
        return $this->belongsTo(User::class, 'seen_by');
    }

    public function investigations()
    {
        return $this->hasMany(AncInvestigation::class, 'anc_visit_id');
    }

    /* ── Helpers ─────────────────────────────── */

    public function getGestationalAge()
    {
        return "{$this->gestational_age_weeks}w {$this->gestational_age_days}d";
    }

    public function getBloodPressure()
    {
        return "{$this->blood_pressure_systolic}/{$this->blood_pressure_diastolic}";
    }
}
