<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;

/**
 * Bed Model
 *
 * Represents a hospital bed within a ward.
 *
 * Bed statuses:
 * - available: Ready for new patient
 * - occupied: Currently has a patient (occupant_id set)
 * - reserved: Reserved for incoming patient
 * - maintenance: Under cleaning/repair
 * - out_of_service: Not available for use
 *
 * @see App\Models\Ward
 * @see App\Models\AdmissionRequest
 */
class Bed extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'ward_id',
        'ward',          // Legacy text field for backward compatibility
        'unit',
        'price',
        'status',
        'bed_status',    // New detailed status enum
        'service_id',
        'occupant_id'
    ];

    /**
     * Bed status constants
     */
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_OCCUPIED = 'occupied';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_OUT_OF_SERVICE = 'out_of_service';

    /**
     * Status options for dropdowns
     */
    public const STATUS_OPTIONS = [
        self::STATUS_AVAILABLE => 'Available',
        self::STATUS_OCCUPIED => 'Occupied',
        self::STATUS_RESERVED => 'Reserved',
        self::STATUS_MAINTENANCE => 'Maintenance',
        self::STATUS_OUT_OF_SERVICE => 'Out of Service',
    ];

    // =====================
    // Relationships
    // =====================

    /**
     * Ward this bed belongs to
     */
    public function wardRelation()
    {
        return $this->belongsTo(Ward::class, 'ward_id');
    }

    public function admissions()
    {
        return $this->hasMany(AdmissionRequest::class, 'bed_id', 'id');
    }

    /**
     * Current active admission for this bed
     */
    public function currentAdmission()
    {
        return $this->hasOne(AdmissionRequest::class, 'bed_id')
            ->where('discharged', 0);
    }

    public function occupant()
    {
        return $this->belongsTo(patient::class, 'occupant_id', 'id');
    }

    public function service()
    {
        return $this->hasOne(service::class, 'id', 'service_id');
    }

    // =====================
    // Scopes
    // =====================

    /**
     * Filter available beds
     */
    public function scopeAvailable($query)
    {
        return $query->whereNull('occupant_id')
            ->where(function ($q) {
                $q->where('bed_status', self::STATUS_AVAILABLE)
                  ->orWhereNull('bed_status');
            })
            ->where('status', 1);
    }

    /**
     * Filter occupied beds
     */
    public function scopeOccupied($query)
    {
        return $query->whereNotNull('occupant_id');
    }

    /**
     * Filter by ward
     */
    public function scopeInWard($query, $wardId)
    {
        return $query->where('ward_id', $wardId);
    }

    /**
     * Filter by ward name (legacy)
     */
    public function scopeInWardNamed($query, string $wardName)
    {
        return $query->where('ward', $wardName);
    }

    // =====================
    // Accessors
    // =====================

    /**
     * Get computed status based on occupant
     */
    public function getComputedStatusAttribute(): string
    {
        if ($this->occupant_id) {
            return self::STATUS_OCCUPIED;
        }

        return $this->bed_status ?? self::STATUS_AVAILABLE;
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_OPTIONS[$this->computed_status] ?? 'Unknown';
    }

    /**
     * Get full location string
     */
    public function getFullLocationAttribute(): string
    {
        $parts = array_filter([
            $this->wardRelation?->name ?? $this->ward,
            $this->unit ? "Unit: {$this->unit}" : null,
            $this->name,
        ]);

        return implode(' - ', $parts);
    }

    // =====================
    // Methods
    // =====================

    /**
     * Assign patient to this bed
     */
    public function assignPatient(int $patientId): void
    {
        $this->update([
            'occupant_id' => $patientId,
            'bed_status' => self::STATUS_OCCUPIED,
        ]);
    }

    /**
     * Release this bed (clear occupant)
     */
    public function release(): void
    {
        $this->update([
            'occupant_id' => null,
            'bed_status' => self::STATUS_AVAILABLE,
        ]);
    }

    /**
     * Set bed to maintenance mode
     */
    public function setMaintenance(): void
    {
        $this->update([
            'bed_status' => self::STATUS_MAINTENANCE,
        ]);
    }

    /**
     * Reserve this bed for a patient
     */
    public function reserve(): void
    {
        $this->update([
            'bed_status' => self::STATUS_RESERVED,
        ]);
    }
}
