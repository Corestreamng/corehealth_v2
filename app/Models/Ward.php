<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Ward Model
 *
 * Represents hospital wards/units for bed management.
 *
 * Ward types:
 * - general: General medical/surgical ward
 * - icu: Intensive Care Unit
 * - pediatric: Children's ward
 * - maternity: Obstetrics/Gynecology ward
 * - emergency: Emergency/Accident ward
 * - psychiatric: Mental health ward
 * - isolation: Infectious disease isolation
 * - recovery: Post-operative recovery
 * - private: Private/VIP rooms
 *
 * @see App\Models\Bed
 * @see NursingWorkbenchController::wardDashboard()
 */
class Ward extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'code',
        'type',
        'capacity',
        'floor',
        'building',
        'nurse_station',
        'contact_extension',
        'nurse_patient_ratio',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'nurse_patient_ratio' => 'decimal:1',
        'is_active' => 'boolean',
    ];

    /**
     * Ward type options for dropdowns
     */
    public const TYPES = [
        'general' => 'General Ward',
        'icu' => 'Intensive Care Unit',
        'pediatric' => 'Pediatric Ward',
        'maternity' => 'Maternity Ward',
        'emergency' => 'Emergency Ward',
        'psychiatric' => 'Psychiatric Ward',
        'isolation' => 'Isolation Ward',
        'recovery' => 'Recovery Ward',
        'private' => 'Private/VIP',
        'other' => 'Other',
    ];

    // =====================
    // Relationships
    // =====================

    /**
     * Beds belonging to this ward
     */
    public function beds()
    {
        return $this->hasMany(Bed::class, 'ward_id');
    }

    /**
     * User who created this ward
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // =====================
    // Scopes
    // =====================

    /**
     * Filter active wards only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Filter by ward type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // =====================
    // Accessors
    // =====================

    /**
     * Get human-readable type name
     */
    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    /**
     * Get full location string
     */
    public function getLocationAttribute(): string
    {
        $parts = array_filter([
            $this->building,
            $this->floor ? "Floor {$this->floor}" : null,
        ]);

        return implode(', ', $parts) ?: 'N/A';
    }

    // =====================
    // Statistics Methods
    // =====================

    /**
     * Get total bed count for this ward
     */
    public function getTotalBedsAttribute(): int
    {
        return $this->beds()->count();
    }

    /**
     * Get occupied bed count
     */
    public function getOccupiedBedsAttribute(): int
    {
        return $this->beds()->whereNotNull('occupant_id')->count();
    }

    /**
     * Get available bed count
     */
    public function getAvailableBedsAttribute(): int
    {
        return $this->beds()
            ->whereNull('occupant_id')
            ->where(function ($q) {
                $q->where('bed_status', 'available')
                  ->orWhereNull('bed_status');
            })
            ->count();
    }

    /**
     * Get occupancy percentage
     */
    public function getOccupancyRateAttribute(): float
    {
        $total = $this->total_beds;
        if ($total === 0) {
            return 0;
        }

        return round(($this->occupied_beds / $total) * 100, 1);
    }

    /**
     * Get ward statistics summary
     */
    public function getStatistics(): array
    {
        $totalBeds = $this->beds()->count();
        $occupiedBeds = $this->beds()->whereNotNull('occupant_id')->count();
        $availableBeds = $this->beds()
            ->whereNull('occupant_id')
            ->where(function ($q) {
                $q->where('bed_status', 'available')
                  ->orWhereNull('bed_status');
            })
            ->count();
        $maintenanceBeds = $this->beds()
            ->where('bed_status', 'maintenance')
            ->count();
        $reservedBeds = $this->beds()
            ->where('bed_status', 'reserved')
            ->count();

        // Pending admissions for this ward
        $pendingAdmissions = AdmissionRequest::whereNull('bed_id')
            ->where('discharged', 0)
            ->count();

        return [
            'total_beds' => $totalBeds,
            'occupied_beds' => $occupiedBeds,
            'available_beds' => $availableBeds,
            'maintenance_beds' => $maintenanceBeds,
            'reserved_beds' => $reservedBeds,
            'occupancy_rate' => $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0,
            'pending_admissions' => $pendingAdmissions,
        ];
    }

    /**
     * Get all beds with their current status and occupant info
     */
    public function getBedsWithStatus()
    {
        return $this->beds()
            ->with(['occupant.user', 'admissions' => function ($q) {
                $q->where('discharged', 0)
                  ->whereNotNull('bed_id')
                  ->with('doctor');
            }])
            ->orderBy('name')
            ->get()
            ->map(function ($bed) {
                $admission = $bed->admissions->first();

                return [
                    'id' => $bed->id,
                    'name' => $bed->name,
                    'unit' => $bed->unit,
                    'price' => $bed->price,
                    'status' => $bed->bed_status ?? ($bed->occupant_id ? 'occupied' : 'available'),
                    'occupant' => $bed->occupant ? [
                        'id' => $bed->occupant->id,
                        'name' => userfullname($bed->occupant->user_id),
                        'file_no' => $bed->occupant->file_no,
                    ] : null,
                    'admission' => $admission ? [
                        'id' => $admission->id,
                        'admitted_date' => $admission->bed_assign_date,
                        'days' => $admission->bed_assign_date
                            ? \Carbon\Carbon::parse($admission->bed_assign_date)->diffInDays(now())
                            : 0,
                        'priority' => $admission->priority,
                        'doctor' => $admission->doctor ? userfullname($admission->doctor->id) : null,
                    ] : null,
                ];
            });
    }
}
