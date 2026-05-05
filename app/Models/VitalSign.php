<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;

/**
 * VitalSign Model
 *
 * Stores patient vital signs measurements.
 *
 * Extended fields added for comprehensive vital monitoring:
 * - weight: Body weight in kg
 * - height: Height in cm (for BMI)
 * - spo2: Oxygen saturation %
 * - blood_sugar: Blood glucose mg/dL
 * - bmi: Body Mass Index (calculated)
 * - pain_score: 0-10 pain scale
 *
 * @see NursingWorkbenchController for store/update methods
 * @see unified_vitals.blade.php for form
 */
class VitalSign extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'requested_by',
        'taken_by',
        'patient_id',
        'blood_pressure',
        'temp',
        'heart_rate',
        'resp_rate',
        'weight',
        'height',
        'spo2',
        'blood_sugar',
        'bmi',
        'pain_score',
        'other_notes',
        'form_data',
        'time_taken',
        'status',
        'source'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'time_taken' => 'datetime',
        'temp' => 'decimal:1',
        'weight' => 'decimal:1',
        'height' => 'decimal:1',
        'spo2' => 'decimal:1',
        'blood_sugar' => 'decimal:1',
        'bmi' => 'decimal:1',
        'pain_score' => 'integer',
        'form_data' => 'array',
    ];

    /**
     * Calculate BMI from weight and height
     * BMI = weight(kg) / height(m)²
     *
     * @return float|null
     */
    public function calculateBmi(): ?float
    {
        if (!$this->weight || !$this->height || $this->height == 0) {
            return null;
        }

        $heightInMeters = $this->height / 100;
        return round($this->weight / ($heightInMeters * $heightInMeters), 1);
    }

    /**
     * Get BMI classification
     *
     * @return string|null
     */
    public function getBmiClassificationAttribute(): ?string
    {
        $bmi = $this->bmi ?? $this->calculateBmi();

        if (!$bmi) {
            return null;
        }

        if ($bmi < 18.5) return 'Underweight';
        if ($bmi < 25) return 'Normal';
        if ($bmi < 30) return 'Overweight';
        return 'Obese';
    }

    /**
     * Get vital status color based on value ranges
     *
     * @param string $vital
     * @return string CSS class (vital-normal, vital-warning, vital-critical)
     */
    /**
     * Get vital status color based on dynamic age-based ranges
     *
     * @param string $vital
     * @return string CSS class (vital-normal, vital-warning, vital-critical)
     */
    public function getVitalStatus(string $vital): string
    {
        if (!$this->patient || !$this->patient->dob) {
            return $this->getLegacyVitalStatus($vital);
        }

        $ageDays = $this->time_taken->diffInDays($this->patient->dob);
        $gender = $this->patient->gender;

        $val = null;
        $keys = [];

        switch ($vital) {
            case 'temp':
                $val = floatval($this->temp);
                $keys = ['temp'];
                break;
            case 'heart_rate':
                $val = intval($this->heart_rate);
                $keys = ['heart_rate'];
                break;
            case 'resp_rate':
                $val = intval($this->resp_rate);
                $keys = ['resp_rate'];
                break;
            case 'spo2':
                $val = floatval($this->spo2);
                $keys = ['spo2'];
                break;
            case 'blood_pressure':
                if (!$this->blood_pressure || !str_contains($this->blood_pressure, '/')) return '';
                [$systolic, $diastolic] = array_map('intval', explode('/', $this->blood_pressure));
                
                $sysRange = VitalRange::resolve('bp_sys', $ageDays, $gender);
                $diaRange = VitalRange::resolve('bp_dia', $ageDays, $gender);
                
                $sysStatus = $sysRange ? $this->evaluateValue($systolic, $sysRange) : null;
                $diaStatus = $diaRange ? $this->evaluateValue($diastolic, $diaRange) : null;

                if ($sysStatus === 'vital-critical' || $diaStatus === 'vital-critical') return 'vital-critical';
                if ($sysStatus === 'vital-warning' || $diaStatus === 'vital-warning') return 'vital-warning';
                return $sysStatus ?? $diaStatus ?? 'vital-normal';

            case 'pain_score':
                $val = intval($this->pain_score);
                if ($val >= 7) return 'vital-critical';
                if ($val >= 4) return 'vital-warning';
                return 'vital-normal';
            default:
                return '';
        }

        if ($val !== null && !empty($keys)) {
            $range = VitalRange::resolve($keys[0], $ageDays, $gender);
            if ($range) {
                return $this->evaluateValue($val, $range);
            }
        }

        return $this->getLegacyVitalStatus($vital);
    }

    private function evaluateValue($val, $range): string
    {
        if ($range->critical_min !== null && $val < $range->critical_min) return 'vital-critical';
        if ($range->critical_max !== null && $val > $range->critical_max) return 'vital-critical';
        
        if ($range->warning_min !== null && $val < $range->warning_min) return 'vital-warning';
        if ($range->warning_max !== null && $val > $range->warning_max) return 'vital-warning';
        
        return 'vital-normal';
    }

    /**
     * Fallback to hardcoded adult ranges if no dynamic range is found
     */
    private function getLegacyVitalStatus(string $vital): string
    {
        switch ($vital) {
            case 'temp':
                $val = floatval($this->temp);
                if ($val < 34 || $val > 39) return 'vital-critical';
                if ($val < 36.1 || $val > 38) return 'vital-warning';
                return 'vital-normal';

            case 'heart_rate':
                $val = intval($this->heart_rate);
                if ($val < 50 || $val > 150) return 'vital-critical';
                if ($val < 60 || $val > 100) return 'vital-warning';
                return 'vital-normal';

            case 'resp_rate':
                $val = intval($this->resp_rate);
                if ($val < 8 || $val > 30) return 'vital-critical';
                if ($val < 12 || $val > 20) return 'vital-warning';
                return 'vital-normal';

            case 'spo2':
                $val = floatval($this->spo2);
                if ($val < 90) return 'vital-critical';
                if ($val < 95) return 'vital-warning';
                return 'vital-normal';

            case 'blood_pressure':
                if (!$this->blood_pressure || !str_contains($this->blood_pressure, '/')) return '';
                [$systolic, $diastolic] = array_map('intval', explode('/', $this->blood_pressure));
                if ($systolic > 180 || $systolic < 80 || $diastolic > 110 || $diastolic < 50) return 'vital-critical';
                if ($systolic > 140 || $systolic < 90 || $diastolic > 90 || $diastolic < 60) return 'vital-warning';
                return 'vital-normal';

            default:
                return '';
        }
    }

    public function takenBy(){
        return $this->belongsTo(User::class, 'taken_by', 'id');
    }

    public function requstedBy(){
        return $this->belongsTo(User::class, 'requested_by', 'id');
    }

    public function patient(){
        return $this->belongsTo(Patient::class, 'patient_id', 'id');
    }
}
