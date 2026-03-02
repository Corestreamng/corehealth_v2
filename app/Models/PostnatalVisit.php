<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class PostnatalVisit extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'enrollment_id', 'patient_id', 'encounter_id',
        'visit_type', 'visit_date', 'days_postpartum',
        // Mother
        'general_condition', 'blood_pressure', 'temperature_c',
        'uterus_assessment', 'lochia', 'wound_assessment',
        'breast_assessment', 'breastfeeding_support',
        'emotional_wellbeing', 'emotional_notes',
        // Baby
        'baby_weight_kg', 'baby_feeding', 'cord_status',
        'jaundice', 'baby_general_condition', 'baby_notes',
        // Family planning
        'family_planning_counselled', 'family_planning_method',
        'clinical_notes', 'next_appointment', 'seen_by',
    ];

    protected $casts = [
        'visit_date' => 'date',
        'next_appointment' => 'date',
        'temperature_c' => 'decimal:1',
        'baby_weight_kg' => 'decimal:3',
        'jaundice' => 'boolean',
        'family_planning_counselled' => 'boolean',
    ];

    /* ── Relationships ─────────────────────── */

    public function enrollment()
    {
        return $this->belongsTo(MaternityEnrollment::class, 'enrollment_id');
    }

    public function patient()
    {
        return $this->belongsTo(patient::class, 'patient_id');
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class, 'encounter_id');
    }

    public function seenBy()
    {
        return $this->belongsTo(User::class, 'seen_by');
    }
}
