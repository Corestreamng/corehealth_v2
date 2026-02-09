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
 * Billing Architecture:
 * - Each bed MUST have a linked service (service_id) in the bed service category
 * - The service is auto-created when bed is created (via BedController or BedObserver)
 * - Billing uses bed->price field directly, synced to service->price->sale_price
 * - See: BedObserver for auto-service creation
 *
 * @see App\Models\Ward
 * @see App\Models\AdmissionRequest
 * @see App\Observers\BedObserver
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
        return $this->belongsTo(service::class, 'service_id', 'id');
    }

    /**
     * Get the service with pricing eager loaded.
     * Use this for billing operations.
     */
    public function serviceWithPrice()
    {
        return $this->belongsTo(service::class, 'service_id', 'id')->with('price');
    }

    // =====================
    // Billing Helpers
    // =====================

    /**
     * Get the daily billing price for this bed.
     * Priority: bed->price > service->price->sale_price
     *
     * @return float
     */
    public function getBillingPrice(): float
    {
        // Primary source: bed price field
        if ($this->price && $this->price > 0) {
            return (float) $this->price;
        }

        // Fallback: service price
        $this->loadMissing('service.price');
        if ($this->service && $this->service->price) {
            return (float) ($this->service->price->sale_price ?? 0);
        }

        return 0.0;
    }

    /**
     * Check if bed has valid service configuration for billing.
     *
     * @return bool
     */
    public function hasValidBillingService(): bool
    {
        if (!$this->service_id) {
            return false;
        }

        $bedServiceCategoryId = appsettings('bed_service_category_id');
        if (!$bedServiceCategoryId) {
            return true; // No category configured, accept any service
        }

        $this->loadMissing('service');
        return $this->service && $this->service->category_id == $bedServiceCategoryId;
    }

    /**
     * Get the service name for display in billing.
     *
     * @return string
     */
    public function getBillingServiceName(): string
    {
        $this->loadMissing('service');
        if ($this->service) {
            return $this->service->service_name;
        }
        return "Bed {$this->name} - {$this->ward}";
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
