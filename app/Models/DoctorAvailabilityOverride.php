<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class DoctorAvailabilityOverride extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'staff_id',
        'clinic_id',
        'override_date',
        'start_time',
        'end_time',
        'is_available',
        'reason',
    ];

    protected $casts = [
        'override_date' => 'date',
        'is_available'  => 'boolean',
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

    /**
     * Overrides that block availability.
     */
    public function scopeBlocked($query)
    {
        return $query->where('is_available', false);
    }

    /**
     * Overrides that add extra availability.
     */
    public function scopeExtra($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('override_date', $date);
    }

    public function scopeForDoctor($query, int $staffId)
    {
        return $query->where('staff_id', $staffId);
    }
}
