<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class ChildGrowthRecord extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'baby_id', 'patient_id', 'record_date', 'age_months',
        'weight_kg', 'length_height_cm',
        'head_circumference_cm', 'muac_cm',
        'weight_for_age_z', 'length_for_age_z',
        'weight_for_length_z', 'bmi_for_age_z',
        'nutritional_status', 'milestones',
        'feeding_method', 'dietary_notes', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'record_date' => 'date',
        'age_months' => 'decimal:1',
        'weight_kg' => 'decimal:2',
        'length_height_cm' => 'decimal:1',
        'head_circumference_cm' => 'decimal:1',
        'muac_cm' => 'decimal:1',
        'weight_for_age_z' => 'decimal:2',
        'length_for_age_z' => 'decimal:2',
        'weight_for_length_z' => 'decimal:2',
        'bmi_for_age_z' => 'decimal:2',
        'milestones' => 'array',
    ];

    /* ── Relationships ─────────────────────── */

    public function baby()
    {
        return $this->belongsTo(MaternityBaby::class, 'baby_id');
    }

    public function patient()
    {
        return $this->belongsTo(patient::class, 'patient_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /* ── Helpers ─────────────────────────────── */

    public function isUnderweight()
    {
        return in_array($this->nutritional_status, [
            'mild_underweight', 'moderate_underweight', 'severe_underweight'
        ]);
    }

    public function getZScoreSummary()
    {
        return [
            'WAZ' => $this->weight_for_age_z,
            'LAZ' => $this->length_for_age_z,
            'WLZ' => $this->weight_for_length_z,
            'BAZ' => $this->bmi_for_age_z,
        ];
    }
}
