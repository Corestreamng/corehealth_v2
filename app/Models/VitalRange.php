<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VitalRange extends Model
{
    use HasFactory;

    protected $fillable = [
        'vital_key',
        'age_min_days',
        'age_max_days',
        'gender',
        'normal_min',
        'normal_max',
        'warning_min',
        'warning_max',
        'critical_min',
        'critical_max',
        'notes',
    ];

    /**
     * Resolve the best matching range for a patient and vital sign.
     * 
     * @param string $vitalKey e.g. 'temp', 'heart_rate', 'resp_rate', 'spo2', 'bp_sys', 'bp_dia'
     * @param int $ageDays
     * @param string|null $gender
     * @return self|null
     */
    public static function resolve($vitalKey, $ageDays, $gender = null)
    {
        return self::where('vital_key', $vitalKey)
            ->where('age_min_days', '<=', $ageDays)
            ->where('age_max_days', '>=', $ageDays)
            ->where(function($q) use ($gender) {
                $q->whereNull('gender')
                  ->orWhere('gender', $gender);
            })
            ->orderByRaw('gender IS NOT NULL DESC') // Prioritize gender-specific ranges
            ->orderBy('age_max_days', 'asc') // Prioritize narrower age ranges
            ->first();
    }
}
