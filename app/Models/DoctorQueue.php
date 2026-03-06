<?php

namespace App\Models;

use App\Enums\QueueStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class DoctorQueue extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'patient_id',
        'clinic_id',
        'staff_id',
        'receptionist_id',
        'request_entry_id',
        'appointment_id',
        'status',
        'vitals_taken',
        'priority',
        'source',
        'triage_note',
        'consultation_started_at',
        'consultation_ended_at',
        'consultation_paused_seconds',
        'last_paused_at',
        'last_resumed_at',
        'is_paused',
    ];

    protected $casts = [
        'status'                      => 'integer',
        'vitals_taken'                => 'boolean',
        'is_paused'                   => 'boolean',
        'consultation_paused_seconds' => 'integer',
        'consultation_started_at'     => 'datetime',
        'consultation_ended_at'       => 'datetime',
        'last_paused_at'              => 'datetime',
        'last_resumed_at'             => 'datetime',
    ];

    // ──────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'id');
    }

    public function clinic()
    {
        return $this->belongsTo(Clinic::class, 'clinic_id', 'id');
    }

    public function doctor()
    {
        return $this->belongsTo(Staff::class, 'staff_id', 'id');
    }

    public function receptionist()
    {
        return $this->belongsTo(Staff::class, 'receptionist_id', 'id');
    }

    public function request_entry()
    {
        return $this->belongsTo(ProductOrServiceRequest::class, 'request_entry_id', 'id');
    }

    public function appointment()
    {
        return $this->belongsTo(DoctorAppointment::class, 'appointment_id', 'id');
    }

    public function encounter()
    {
        return $this->hasOne(Encounter::class, 'queue_id', 'id');
    }

    // ──────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────

    /**
     * Only active (non-terminal) queue entries.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', QueueStatus::ACTIVE);
    }

    /**
     * Queue entries for a specific clinic.
     */
    public function scopeForClinic($query, int $clinicId)
    {
        return $query->where('clinic_id', $clinicId);
    }

    /**
     * Queue entries assigned to a specific doctor.
     */
    public function scopeForDoctor($query, int $staffId)
    {
        return $query->where('staff_id', $staffId);
    }

    /**
     * Today's queue entries.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', now()->toDateString());
    }

    // ──────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────

    /**
     * Human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return QueueStatus::label($this->status);
    }

    /**
     * Bootstrap badge class for the current status.
     */
    public function getStatusBadgeAttribute(): string
    {
        return QueueStatus::badge($this->status);
    }

    /**
     * Net consultation duration in seconds (excluding pauses).
     */
    public function getConsultationDurationSecondsAttribute(): int
    {
        if (!$this->consultation_started_at) {
            return 0;
        }

        $end = $this->consultation_ended_at ?? now();
        $totalSeconds = $this->consultation_started_at->diffInSeconds($end);

        return max(0, $totalSeconds - ($this->consultation_paused_seconds ?? 0));
    }

    /**
     * Formatted consultation duration (HH:MM:SS).
     */
    public function getConsultationDurationFormattedAttribute(): string
    {
        $seconds = $this->consultation_duration_seconds;
        $hours   = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs    = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }
}
