<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class DoctorAvailability extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'staff_id',
        'clinic_id',
        'day_of_week',
        'start_time',
        'end_time',
        'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_active'   => 'boolean',
    ];

    // ──────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'id');
    }

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

    public function scopeForDoctor($query, int $staffId)
    {
        return $query->where('staff_id', $staffId);
    }

    // ──────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────

    public function getDayNameAttribute(): string
    {
        return ClinicSchedule::DAY_LABELS[$this->day_of_week] ?? 'Unknown';
    }
}
