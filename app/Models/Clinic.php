<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Clinic extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
    ];

    // ──────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────

    public function doctors()
    {
        return $this->hasMany(Staff::class, 'clinic_id', 'id');
    }

    public function schedules()
    {
        return $this->hasMany(ClinicSchedule::class, 'clinic_id', 'id');
    }

    public function appointments()
    {
        return $this->hasMany(DoctorAppointment::class, 'clinic_id', 'id');
    }

    public function queues()
    {
        return $this->hasMany(DoctorQueue::class, 'clinic_id', 'id');
    }

    // ──────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────

    /**
     * Get today's schedule (if any).
     */
    public function getTodayScheduleAttribute(): ?ClinicSchedule
    {
        return $this->schedules()
            ->where('day_of_week', now()->dayOfWeek)
            ->where('is_active', true)
            ->first();
    }
}
