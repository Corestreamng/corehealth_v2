<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class MaternityBaby extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'enrollment_id', 'patient_id', 'birth_order', 'sex',
        'birth_weight_kg', 'length_cm',
        'head_circumference_cm', 'chest_circumference_cm',
        'apgar_1_min', 'apgar_5_min', 'apgar_10_min',
        'resuscitation', 'resuscitation_details', 'birth_defects',
        'feeding_method',
        'bcg_given', 'opv0_given', 'hbv0_given',
        'vitamin_k_given', 'eye_prophylaxis',
        'date_first_seen', 'reasons_for_special_care',
        'status', 'notes',
    ];

    protected $casts = [
        'birth_weight_kg' => 'decimal:3',
        'length_cm' => 'decimal:1',
        'head_circumference_cm' => 'decimal:1',
        'chest_circumference_cm' => 'decimal:1',
        'resuscitation' => 'boolean',
        'bcg_given' => 'boolean',
        'opv0_given' => 'boolean',
        'hbv0_given' => 'boolean',
        'vitamin_k_given' => 'boolean',
        'eye_prophylaxis' => 'boolean',
        'date_first_seen' => 'date',
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

    public function growthRecords()
    {
        return $this->hasMany(ChildGrowthRecord::class, 'baby_id');
    }

    /* ── Helpers ─────────────────────────────── */

    public function getApgarSummary()
    {
        return "{$this->apgar_1_min}/{$this->apgar_5_min}/{$this->apgar_10_min}";
    }

    public function getAgeInMonths()
    {
        if (!$this->patient || !$this->patient->dob) return null;
        return $this->patient->dob->diffInMonths(now());
    }
}
