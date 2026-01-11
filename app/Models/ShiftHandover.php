<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Carbon\Carbon;

/**
 * ShiftHandover Model
 * 
 * Represents a handover document created at end of shift.
 * Must be acknowledged by incoming nurse before starting their shift.
 */
class ShiftHandover extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'shift_id',
        'created_by',
        'received_by',
        'ward_id',
        'shift_type',
        'shift_started_at',
        'shift_ended_at',
        'summary',
        'critical_notes',
        'concluding_notes',
        'pending_tasks',
        'patient_highlights',
        'action_summary',
        'audit_details',
        'acknowledged_at',
        'acknowledged_by',
        'acknowledgment_notes',
    ];

    protected $casts = [
        'shift_started_at' => 'datetime',
        'shift_ended_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'pending_tasks' => 'array',
        'patient_highlights' => 'array',
        'action_summary' => 'array',
        'audit_details' => 'array',
    ];

    // ========================================
    // Relationships
    // ========================================

    public function shift()
    {
        return $this->belongsTo(NursingShift::class, 'shift_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function acknowledgedByUser()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    // ========================================
    // Scopes
    // ========================================

    public function scopeUnacknowledged($query)
    {
        return $query->whereNull('acknowledged_at');
    }

    public function scopeAcknowledged($query)
    {
        return $query->whereNotNull('acknowledged_at');
    }

    public function scopeForWard($query, $wardId)
    {
        if ($wardId) {
            return $query->where('ward_id', $wardId);
        }
        return $query;
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('shift_ended_at', '>=', now()->subHours($hours));
    }

    public function scopeWithCriticalNotes($query)
    {
        return $query->whereNotNull('critical_notes')->where('critical_notes', '!=', '');
    }

    // ========================================
    // Accessors
    // ========================================

    public function getIsAcknowledgedAttribute(): bool
    {
        return $this->acknowledged_at !== null;
    }

    public function getShiftDurationAttribute(): ?string
    {
        if (!$this->shift_started_at || !$this->shift_ended_at) {
            return null;
        }
        return $this->shift_started_at->diffForHumans($this->shift_ended_at, true);
    }

    public function getShiftTypeLabelAttribute(): string
    {
        return NursingShift::SHIFT_TYPES[$this->shift_type]['label'] ?? ucfirst($this->shift_type);
    }

    public function getHasCriticalNotesAttribute(): bool
    {
        return !empty($this->critical_notes);
    }

    public function getTotalActionsAttribute(): int
    {
        if (!$this->action_summary) return 0;
        return $this->action_summary['total'] ?? 0;
    }

    public function getStatusBadgeAttribute(): string
    {
        if ($this->is_acknowledged) {
            return '<span class="badge badge-success"><i class="fa fa-check"></i> Acknowledged</span>';
        }
        return '<span class="badge badge-warning"><i class="fa fa-clock"></i> Pending</span>';
    }

    public function getShiftTypeBadgeAttribute(): string
    {
        $colors = [
            'morning' => 'warning',
            'afternoon' => 'info',
            'night' => 'dark',
        ];
        $icons = [
            'morning' => 'mdi-weather-sunny',
            'afternoon' => 'mdi-weather-partly-cloudy',
            'night' => 'mdi-weather-night',
        ];
        $color = $colors[$this->shift_type] ?? 'secondary';
        $icon = $icons[$this->shift_type] ?? 'mdi-clock';
        return '<span class="badge badge-' . $color . '"><i class="mdi ' . $icon . '"></i> ' . $this->shift_type_label . '</span>';
    }

    // ========================================
    // Methods
    // ========================================

    /**
     * Acknowledge the handover
     */
    public function acknowledge(int $userId, ?string $notes = null): self
    {
        $this->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $userId,
            'acknowledgment_notes' => $notes,
        ]);

        return $this;
    }

    /**
     * Get handovers needing acknowledgment for start of shift
     */
    public static function getRecentForAcknowledgment(?int $wardId = null, int $hours = 24): \Illuminate\Database\Eloquent\Collection
    {
        return static::with(['creator', 'ward', 'shift'])
            ->recent($hours)
            ->forWard($wardId)
            ->orderBy('shift_ended_at', 'desc')
            ->get();
    }

    /**
     * Get unacknowledged handovers with critical notes
     */
    public static function getCriticalUnacknowledged(?int $wardId = null): \Illuminate\Database\Eloquent\Collection
    {
        return static::with(['creator', 'ward'])
            ->unacknowledged()
            ->withCriticalNotes()
            ->forWard($wardId)
            ->orderBy('shift_ended_at', 'desc')
            ->get();
    }

    /**
     * Format action summary for display
     * Handles both old format (simple counts) and new audit-based format
     */
    public function formatActionSummary(): array
    {
        $actionSummary = $this->action_summary;
        if (!$actionSummary || !is_array($actionSummary)) return [];

        $formatted = [];

        // Check if it's the new audit-based format (has 'label' key in items)
        $firstItem = !empty($actionSummary) ? array_values($actionSummary)[0] : null;
        $isNewFormat = is_array($firstItem) && isset($firstItem['label']);

        if ($isNewFormat) {
            // New audit-based format
            foreach ($actionSummary as $type => $data) {
                if (($data['count'] ?? 0) > 0) {
                    $formatted[] = [
                        'icon' => $data['icon'] ?? 'mdi-file',
                        'label' => $data['label'] ?? class_basename($type),
                        'color' => $data['color'] ?? 'secondary',
                        'count' => $data['count'],
                        'events' => $data['events'] ?? [],
                        'patients' => $data['patients'] ?? [],
                    ];
                }
            }
        } else {
            // Old simple format for backward compatibility
            $labels = [
                'vitals' => ['icon' => 'mdi-heart-pulse', 'label' => 'Vitals Recorded', 'color' => 'danger'],
                'medications' => ['icon' => 'mdi-pill', 'label' => 'Medications Given', 'color' => 'warning'],
                'injections' => ['icon' => 'mdi-needle', 'label' => 'Injections', 'color' => 'info'],
                'immunizations' => ['icon' => 'mdi-shield-check', 'label' => 'Immunizations', 'color' => 'success'],
                'notes' => ['icon' => 'mdi-note-text', 'label' => 'Nursing Notes', 'color' => 'primary'],
                'bills' => ['icon' => 'mdi-receipt', 'label' => 'Bills Created', 'color' => 'secondary'],
            ];

            foreach ($actionSummary as $key => $count) {
                if ($key === 'total' || $key === 'patients_seen' || $count == 0) continue;
                if (isset($labels[$key])) {
                    $formatted[] = array_merge($labels[$key], ['count' => $count]);
                }
            }
        }

        return $formatted;
    }

    /**
     * Get patient highlights formatted for display
     */
    public function getPatientHighlightsFormatted(): array
    {
        if (!$this->patient_highlights) return [];

        $formatted = [];
        foreach ($this->patient_highlights as $patient) {
            // Group activities by type for summary display
            $activityGroups = [];
            foreach ($patient['activities'] ?? [] as $activity) {
                $type = $activity['type'] ?? 'Other';
                if (!isset($activityGroups[$type])) {
                    $activityGroups[$type] = [
                        'label' => $type,
                        'icon' => $activity['icon'] ?? 'mdi-file',
                        'color' => $activity['color'] ?? 'secondary',
                        'count' => 0,
                        'events' => [],
                    ];
                }
                $activityGroups[$type]['count']++;
                $event = ucfirst($activity['event'] ?? 'updated');
                if (!in_array($event, $activityGroups[$type]['events'])) {
                    $activityGroups[$type]['events'][] = $event;
                }
            }

            $formatted[] = [
                'patient_id' => $patient['patient_id'] ?? null,
                'patient_name' => $patient['patient_name'] ?? 'Unknown Patient',
                'patient_no' => $patient['patient_no'] ?? null,
                'total_events' => $patient['total_events'] ?? count($patient['activities'] ?? []),
                'activities' => array_values($activityGroups),
            ];
        }

        return $formatted;
    }
}
