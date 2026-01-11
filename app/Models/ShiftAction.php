<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ShiftAction Model
 * 
 * Logs individual nursing actions during a shift.
 * Provides detailed activity tracking for handover generation.
 */
class ShiftAction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'shift_id',
        'user_id',
        'action_type',
        'action_subtype',
        'description',
        'details',
        'patient_id',
        'patient_name',
        'auditable_type',
        'auditable_id',
        'metadata',
        'is_critical',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_critical' => 'boolean',
        'created_at' => 'datetime',
    ];

    // ========================================
    // Constants
    // ========================================

    public const ACTION_TYPES = [
        'vitals' => ['icon' => 'mdi-heart-pulse', 'label' => 'Vital Signs', 'color' => 'danger'],
        'medication' => ['icon' => 'mdi-pill', 'label' => 'Medication', 'color' => 'warning'],
        'injection' => ['icon' => 'mdi-needle', 'label' => 'Injection', 'color' => 'info'],
        'immunization' => ['icon' => 'mdi-shield-check', 'label' => 'Immunization', 'color' => 'success'],
        'note' => ['icon' => 'mdi-note-text', 'label' => 'Nursing Note', 'color' => 'primary'],
        'bill' => ['icon' => 'mdi-receipt', 'label' => 'Billing', 'color' => 'secondary'],
        'admission' => ['icon' => 'mdi-account-plus', 'label' => 'Admission', 'color' => 'success'],
        'discharge' => ['icon' => 'mdi-account-minus', 'label' => 'Discharge', 'color' => 'info'],
        'bed_assignment' => ['icon' => 'mdi-bed', 'label' => 'Bed Assignment', 'color' => 'primary'],
        'checklist' => ['icon' => 'mdi-format-list-checks', 'label' => 'Checklist', 'color' => 'info'],
        'other' => ['icon' => 'mdi-dots-horizontal', 'label' => 'Other', 'color' => 'secondary'],
    ];

    // ========================================
    // Relationships
    // ========================================

    public function shift()
    {
        return $this->belongsTo(NursingShift::class, 'shift_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the auditable model
     */
    public function auditable()
    {
        return $this->morphTo();
    }

    // ========================================
    // Scopes
    // ========================================

    public function scopeForShift($query, $shiftId)
    {
        return $query->where('shift_id', $shiftId);
    }

    public function scopeCritical($query)
    {
        return $query->where('is_critical', true);
    }

    public function scopeOfType($query, $type)
    {
        return $query->where('action_type', $type);
    }

    public function scopeForPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    // ========================================
    // Accessors
    // ========================================

    public function getActionIconAttribute(): string
    {
        return self::ACTION_TYPES[$this->action_type]['icon'] ?? 'mdi-dots-horizontal';
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTION_TYPES[$this->action_type]['label'] ?? ucfirst($this->action_type);
    }

    public function getActionColorAttribute(): string
    {
        return self::ACTION_TYPES[$this->action_type]['color'] ?? 'secondary';
    }

    public function getTypeBadgeAttribute(): string
    {
        return '<span class="badge badge-' . $this->action_color . '"><i class="mdi ' . $this->action_icon . '"></i> ' . $this->action_label . '</span>';
    }

    // ========================================
    // Methods
    // ========================================

    /**
     * Log an action to a shift
     */
    public static function logAction(int $shiftId, array $data): self
    {
        $action = static::create([
            'shift_id' => $shiftId,
            'user_id' => $data['user_id'] ?? auth()->id(),
            'action_type' => $data['action_type'],
            'action_subtype' => $data['action_subtype'] ?? null,
            'description' => $data['description'],
            'details' => $data['details'] ?? null,
            'patient_id' => $data['patient_id'] ?? null,
            'patient_name' => $data['patient_name'] ?? null,
            'auditable_type' => $data['auditable_type'] ?? null,
            'auditable_id' => $data['auditable_id'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'is_critical' => $data['is_critical'] ?? false,
            'created_at' => now(),
        ]);

        // Update shift counters
        $shift = NursingShift::find($shiftId);
        if ($shift) {
            $counterMap = [
                'vitals' => 'vitals',
                'medication' => 'medications',
                'injection' => 'injections',
                'immunization' => 'immunizations',
                'note' => 'notes',
                'bill' => 'bills',
            ];
            
            if (isset($counterMap[$data['action_type']])) {
                $shift->incrementAction($counterMap[$data['action_type']]);
            }
        }

        return $action;
    }

    /**
     * Get actions grouped by type for a shift
     */
    public static function getGroupedForShift(int $shiftId): array
    {
        $actions = static::forShift($shiftId)
            ->orderBy('created_at', 'desc')
            ->get();

        $grouped = [];
        foreach (self::ACTION_TYPES as $type => $config) {
            $typeActions = $actions->where('action_type', $type);
            if ($typeActions->count() > 0) {
                $grouped[$type] = [
                    'config' => $config,
                    'count' => $typeActions->count(),
                    'actions' => $typeActions->values(),
                ];
            }
        }

        return $grouped;
    }
}
