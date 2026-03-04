<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ClinicSchedule extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'clinic_id',
        'day_of_week',
        'open_time',
        'close_time',
        'slot_duration_minutes',
        'max_concurrent_slots',
        'is_active',
    ];

    protected $casts = [
        'day_of_week'          => 'integer',
        'slot_duration_minutes' => 'integer',
        'max_concurrent_slots' => 'integer',
        'is_active'            => 'boolean',
    ];

    /**
     * Day-of-week labels indexed from 0 (Sunday) to 6 (Saturday).
     */
    public const DAY_LABELS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    // ──────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────

    public function clinic()
    {
        return $this->belongsTo(Clinic::class, 'clinic_id', 'id');
    }

    // ──────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForDay($query, int $dayOfWeek)
    {
        return $query->where('day_of_week', $dayOfWeek);
    }

    // ──────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────

    /**
     * Human-readable day name.
     */
    public function getDayNameAttribute(): string
    {
        return self::DAY_LABELS[$this->day_of_week] ?? 'Unknown';
    }
}
