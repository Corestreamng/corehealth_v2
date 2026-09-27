<?php

namespace App\Models;

use App\Traits\IsAuditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * NursingShift Model
 *
 * Represents a nurse's work shift with activity tracking.
 * Shifts auto-end after 12 hours if not manually ended.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $ward_id
 * @property string $shift_type
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property Carbon $scheduled_end_at
 * @property string $status
 * @property bool $handover_created
 * @property string|null $concluding_notes
 * @property string|null $critical_notes
 * @property int|null $incoming_nurse_id
 */

class NursingShift extends Model implements Auditable
{
    use IsAuditable;

    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'user_id',
        'ward_id',
        'shift_type',
        'context', // nursing, billing, lab, general
        'started_at',
        'ended_at',
        'scheduled_end_at',
        'status',
        'handover_created',
        'concluding_notes',
        'critical_notes',
        'incoming_nurse_id',
        'vitals_count',
        'medications_count',
        'notes_count',
        'injections_count',
        'immunizations_count',
        'bills_count',
        'payments_count',
        'total_collected',
        'patients_seen',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'scheduled_end_at' => 'datetime',
        'handover_created' => 'boolean',
        'vitals_count' => 'integer',
        'medications_count' => 'integer',
        'notes_count' => 'integer',
        'injections_count' => 'integer',
        'immunizations_count' => 'integer',
        'bills_count' => 'integer',
        'payments_count' => 'integer',
        'total_collected' => 'decimal:2',
        'patients_seen' => 'integer',
    ];

    // ========================================
    // Constants
    // ========================================

    public const SHIFT_TYPES = [
        'morning' => ['label' => 'Morning Shift', 'start' => '06:00', 'end' => '14:00'],
        'afternoon' => ['label' => 'Afternoon Shift', 'start' => '14:00', 'end' => '22:00'],
        'night' => ['label' => 'Night Shift', 'start' => '22:00', 'end' => '06:00'],
    ];

    public const STATUSES = [
        'active' => 'Active',
        'completed' => 'Completed',
        'auto_ended' => 'Auto-Ended',
        'cancelled' => 'Cancelled',
    ];

    public const MAX_SHIFT_HOURS = 12;

    // ========================================
    // Relationships
    // ========================================

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    public function incomingNurse()
    {
        return $this->belongsTo(User::class, 'incoming_nurse_id');
    }

    public function actions()
    {
        return $this->hasMany(ShiftAction::class, 'shift_id');
    }

    public function handover()
    {
        return $this->hasOne(ShiftHandover::class, 'shift_id');
    }

    /**
     * Get all payments collected during this shift (for billing context).
     */
    public function shiftPayments()
    {
        return $this->hasMany(Payment::class, 'shift_id');
    }

    // ========================================
    // Scopes
    // ========================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'active')
            ->where('scheduled_end_at', '<', now());
    }

    public function scopeRecent($query, $hours = 24)
    {
        return $query->where('started_at', '>=', now()->subHours($hours));
    }

    public function scopeBilling($query)
    {
        return $query->where('context', 'billing');
    }

    public function scopeForContext($query, $context)
    {
        return $query->where('context', $context);
    }

    // ========================================
    // Accessors
    // ========================================

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->is_active && $this->scheduled_end_at->isPast();
    }

    public function getDurationAttribute(): string
    {
        if (!$this->started_at) {
            return '0m';
        }

        $end = $this->ended_at ?? now();

        return $this->started_at->diffForHumans($end, true);
    }

    public function getDurationMinutesAttribute(): int
    {
        if (!$this->started_at) {
            return 0;
        }

        $end = $this->ended_at ?? now();

        return $this->started_at->diffInMinutes($end);
    }

    public function getElapsedSecondsAttribute(): int
    {
        if (!$this->started_at) {
            return 0;
        }

        $end = $this->ended_at ?? now();

        return (int) $this->started_at->diffInSeconds($end);
    }

    public function getRemainingSecondsAttribute(): int
    {
        if (!$this->is_active) {
            return 0;
        }

        return max(0, now()->diffInSeconds($this->scheduled_end_at, false));
    }

    public function getTotalActionsAttribute(): int
    {
        return $this->vitals_count + $this->medications_count + $this->notes_count +
               $this->injections_count + $this->immunizations_count + $this->bills_count;
    }

    public function getShiftTypeLabelAttribute(): string
    {
        return self::SHIFT_TYPES[$this->shift_type]['label'] ?? ucfirst($this->shift_type);
    }

    public function getStatusBadgeAttribute(): string
    {
        $colors = [
            'active' => 'success',
            'completed' => 'primary',
            'auto_ended' => 'warning',
            'cancelled' => 'danger',
        ];
        $color = $colors[$this->status] ?? 'secondary';

        return '<span class="badge badge-' . $color . '">' . self::STATUSES[$this->status] . '</span>';
    }

    // ========================================
    // Methods
    // ========================================

    /**
     * Get the active shift for a user
     */
    public static function getActiveForUser($userId): ?self
    {
        return static::forUser($userId)->active()->first();
    }

    /**
     * Start a new shift for a user
     */
    public static function startShift($userId, array $data = []): self
    {
        // End any existing active shift first
        $existingShift = static::getActiveForUser($userId);
        if ($existingShift) {
            $existingShift->endShift(true); // Auto-end
        }

        $now = now();
        $shiftType = $data['shift_type'] ?? static::determineShiftType($now);

        return static::create([
            'user_id' => $userId,
            'ward_id' => $data['ward_id'] ?? null,
            'shift_type' => $shiftType,
            'started_at' => $now,
            'scheduled_end_at' => $now->copy()->addHours(self::MAX_SHIFT_HOURS),
            'status' => 'active',
        ]);
    }

    /**
     * Determine shift type based on current time
     */
    public static function determineShiftType(Carbon $time = null): string
    {
        $time = $time ?? now();
        $hour = $time->hour;

        if ($hour >= 6 && $hour < 14) {
            return 'morning';
        } elseif ($hour >= 14 && $hour < 22) {
            return 'afternoon';
        } else {
            return 'night';
        }
    }

    /**
     * End the shift
     */
    public function endShift(bool $autoEnded = false): self
    {
        $this->update([
            'ended_at' => now(),
            'status' => $autoEnded ? 'auto_ended' : 'completed',
        ]);

        return $this;
    }

    /**
     * Increment action counter
     */
    public function incrementAction(string $type, int $count = 1): void
    {
        $column = $type . '_count';
        if (in_array($column, ['vitals_count', 'medications_count', 'notes_count',
                               'injections_count', 'immunizations_count', 'bills_count'])) {
            $this->increment($column, $count);
        }
    }

    /**
     * Add a patient to seen list
     */
    public function recordPatientSeen(int $patientId): void
    {
        // This could be more sophisticated with a pivot table
        // For now, just increment the counter
        $this->increment('patients_seen');
    }

    /**
     * Nursing-related auditable model types
     */
    public const NURSING_AUDITABLE_TYPES = [
        // Core Nursing Activities
        'App\\Models\\VitalSign' => ['label' => 'Vital Signs', 'icon' => 'mdi-heart-pulse', 'color' => 'danger'],
        'App\\Models\\NursingNote' => ['label' => 'Nursing Notes', 'icon' => 'mdi-note-text', 'color' => 'primary'],

        // Injection & Immunization
        'App\\Models\\InjectionAdministration' => ['label' => 'Injections', 'icon' => 'mdi-needle', 'color' => 'info'],
        'App\\Models\\ImmunizationRecord' => ['label' => 'Immunizations', 'icon' => 'mdi-shield-check', 'color' => 'success'],
        'App\\Models\\PatientImmunizationSchedule' => ['label' => 'Immunization Schedule', 'icon' => 'mdi-calendar-check', 'color' => 'success'],

        // Medication Management
        'App\\Models\\MedicationAdministration' => ['label' => 'Medication Administration', 'icon' => 'mdi-pill', 'color' => 'warning'],
        'App\\Models\\MedicationSchedule' => ['label' => 'Medication Schedule', 'icon' => 'mdi-clock-outline', 'color' => 'warning'],

        // I/O Charting
        'App\\Models\\IntakeOutputPeriod' => ['label' => 'I/O Period', 'icon' => 'mdi-chart-line', 'color' => 'info'],
        'App\\Models\\IntakeOutputRecord' => ['label' => 'I/O Record', 'icon' => 'mdi-water', 'color' => 'info'],

        // Billing
        'App\\Models\\ProductOrServiceRequest' => ['label' => 'Billing', 'icon' => 'mdi-receipt', 'color' => 'secondary'],

        // Admissions & Bed Management
        'App\\Models\\AdmissionRequest' => ['label' => 'Admissions', 'icon' => 'mdi-bed', 'color' => 'primary'],
        'App\\Models\\Bed' => ['label' => 'Bed Management', 'icon' => 'mdi-bed-outline', 'color' => 'info'],

        // Checklists
        'App\\Models\\AdmissionChecklist' => ['label' => 'Admission Checklists', 'icon' => 'mdi-clipboard-check', 'color' => 'primary'],
        'App\\Models\\AdmissionChecklistItem' => ['label' => 'Admission Checklist Items', 'icon' => 'mdi-checkbox-marked', 'color' => 'primary'],
        'App\\Models\\DischargeChecklist' => ['label' => 'Discharge Checklists', 'icon' => 'mdi-clipboard-check-outline', 'color' => 'warning'],
        'App\\Models\\DischargeChecklistItem' => ['label' => 'Discharge Checklist Items', 'icon' => 'mdi-checkbox-marked-outline', 'color' => 'warning'],
    ];

    /**
     * Get audit logs for this shift period
     */
    public function getShiftAuditLogs()
    {
        $auditableTypes = array_keys(self::NURSING_AUDITABLE_TYPES);

        return \OwenIt\Auditing\Models\Audit::where('user_id', $this->user_id)
            ->whereIn('auditable_type', $auditableTypes)
            ->where('created_at', '>=', $this->started_at)
            ->where('created_at', '<=', $this->ended_at ?? now())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get audit logs grouped by model type
     */
    public function getGroupedAuditLogs(): array
    {
        $audits = $this->getShiftAuditLogs();
        $grouped = [];

        foreach ($audits as $audit) {
            $type = $audit->auditable_type;
            $config = self::NURSING_AUDITABLE_TYPES[$type] ?? ['label' => class_basename($type), 'icon' => 'mdi-file', 'color' => 'secondary'];

            if (!isset($grouped[$type])) {
                $grouped[$type] = [
                    'label' => $config['label'],
                    'icon' => $config['icon'],
                    'color' => $config['color'],
                    'count' => 0,
                    'events' => ['created' => 0, 'updated' => 0, 'deleted' => 0],
                    'items' => [],
                    'patients' => [],
                ];
            }

            $grouped[$type]['count']++;
            $grouped[$type]['events'][$audit->event] = ($grouped[$type]['events'][$audit->event] ?? 0) + 1;

            // Try to get patient info from the audit
            $patientId = null;
            $patientName = null;

            // Check new_values first, then old_values for patient_id
            $values = $audit->new_values ?? $audit->old_values ?? [];
            if (isset($values['patient_id'])) {
                $patientId = $values['patient_id'];
            }

            // Get patient info if we have an ID
            if ($patientId && !isset($grouped[$type]['patients'][$patientId])) {
                $patient = Patient::with('user')->find($patientId);
                if ($patient) {
                    // Patient name comes from user relationship
                    $patientName = $patient->user?->name ?? $patient->user?->first_name ?? "Patient #{$patientId}";
                    $grouped[$type]['patients'][$patientId] = [
                        'name' => $patientName,
                        'patient_no' => $patient->file_no ?? null,
                    ];
                } else {
                    $grouped[$type]['patients'][$patientId] = [
                        'name' => "Patient #{$patientId}",
                        'patient_no' => null,
                    ];
                }
            }

            $patientInfo = $grouped[$type]['patients'][$patientId] ?? null;

            // Parse changes between old and new values
            $changes = $this->parseAuditChanges($audit->event, $audit->old_values ?? [], $audit->new_values ?? [], $type);

            $grouped[$type]['items'][] = [
                'id' => $audit->id,
                'event' => $audit->event,
                'auditable_id' => $audit->auditable_id,
                'patient_id' => $patientId,
                'patient_name' => $patientInfo['name'] ?? null,
                'patient_no' => $patientInfo['patient_no'] ?? null,
                'old_values' => $audit->old_values,
                'new_values' => $audit->new_values,
                'changes' => $changes,
                'created_at' => $audit->created_at,
                'time_ago' => $audit->created_at->diffForHumans(),
            ];
        }

        return $grouped;
    }

    /**
     * Parse audit changes into human-readable format
     */
    protected function parseAuditChanges(string $event, array $oldValues, array $newValues, string $modelType): array
    {
        $changes = [];

        // Fields to ignore (internal/technical fields)
        $ignoreFields = ['id', 'created_at', 'updated_at', 'deleted_at', 'user_id', 'staff_user_id'];

        // Human-readable field labels
        $fieldLabels = [
            'blood_pressure' => 'Blood Pressure',
            'temp' => 'Temperature',
            'heart_rate' => 'Heart Rate',
            'resp_rate' => 'Respiratory Rate',
            'weight' => 'Weight',
            'height' => 'Height',
            'spo2' => 'SpO2',
            'blood_sugar' => 'Blood Sugar',
            'bmi' => 'BMI',
            'pain_score' => 'Pain Score',
            'dose' => 'Dose',
            'route' => 'Route',
            'site' => 'Site',
            'administered_at' => 'Administered At',
            'batch_number' => 'Batch Number',
            'vaccine_name' => 'Vaccine Name',
            'dose_number' => 'Dose Number',
            'next_due_date' => 'Next Due Date',
            'adverse_reaction' => 'Adverse Reaction',
            'note' => 'Note',
            'notes' => 'Notes',
            'other_notes' => 'Other Notes',
            'status' => 'Status',
            'is_completed' => 'Completed',
            'comment' => 'Comment',
            'qty' => 'Quantity',
            'payable_amount' => 'Amount',
            'claims_amount' => 'Claims Amount',
            'admission_status' => 'Admission Status',
            'bed_status' => 'Bed Status',
            'due_date' => 'Due Date',
            'administered_date' => 'Administered Date',
            'skip_reason' => 'Skip Reason',
            'type' => 'Type',
            'amount' => 'Amount',
            'description' => 'Description',
            'recorded_at' => 'Recorded At',
            'scheduled_date' => 'Scheduled Date',
            'scheduled_time' => 'Scheduled Time',
            'is_discontinued' => 'Discontinued',
            'discontinue_reason' => 'Discontinue Reason',
        ];

        if ($event === 'created') {
            // For created events, show key new values
            foreach ($newValues as $field => $value) {
                if (in_array($field, $ignoreFields) || $value === null || $value === '') {
                    continue;
                }
                $label = $fieldLabels[$field] ?? ucwords(str_replace('_', ' ', $field));
                $changes[] = [
                    'field' => $field,
                    'label' => $label,
                    'type' => 'created',
                    'value' => $this->formatFieldValue($field, $value),
                ];
            }
        } elseif ($event === 'updated') {
            // For updated events, show what changed
            foreach ($newValues as $field => $newValue) {
                if (in_array($field, $ignoreFields)) {
                    continue;
                }
                $oldValue = $oldValues[$field] ?? null;
                if ($oldValue !== $newValue) {
                    $label = $fieldLabels[$field] ?? ucwords(str_replace('_', ' ', $field));
                    $changes[] = [
                        'field' => $field,
                        'label' => $label,
                        'type' => 'changed',
                        'old' => $this->formatFieldValue($field, $oldValue),
                        'new' => $this->formatFieldValue($field, $newValue),
                    ];
                }
            }
        } elseif ($event === 'deleted') {
            // For deleted events, show what was removed
            foreach ($oldValues as $field => $value) {
                if (in_array($field, $ignoreFields) || $value === null || $value === '') {
                    continue;
                }
                $label = $fieldLabels[$field] ?? ucwords(str_replace('_', ' ', $field));
                $changes[] = [
                    'field' => $field,
                    'label' => $label,
                    'type' => 'deleted',
                    'value' => $this->formatFieldValue($field, $value),
                ];
            }
        }

        return $changes;
    }

    /**
     * Format field value for display
     */
    protected function formatFieldValue(string $field, $value): string
    {
        if ($value === null) {
            return 'N/A';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            return json_encode($value);
        }

        // Date/time fields
        if (in_array($field, ['administered_at', 'recorded_at', 'scheduled_date', 'due_date', 'next_due_date', 'administered_date'])) {
            try {
                return \Carbon\Carbon::parse($value)->format('M j, Y g:i A');
            } catch (\Exception $e) {
                return (string) $value;
            }
        }

        // Boolean-like fields
        if (in_array($field, ['is_completed', 'is_discontinued', 'is_repeating'])) {
            return $value ? 'Yes' : 'No';
        }

        return (string) $value;
    }

    /**
     * Generate detailed audit log entries for handover
     */
    public function generateAuditDetails(): array
    {
        return app(\App\Services\ClinicalHandoverFormatter::class)
            ->formatShiftHandover($this)['activity_timeline'];
    }

    /**
     * Generate detailed summary from audit logs
     */
    public function generateDetailedSummary(): string
    {
        return app(\App\Services\ClinicalHandoverFormatter::class)
            ->formatShiftHandover($this)['executive_summary'];
    }

    /**
     * Get patient highlights from audit logs
     */
    public function getPatientHighlights(): array
    {
        return app(\App\Services\ClinicalHandoverFormatter::class)
            ->formatShiftHandover($this)['patient_summaries'];
    }

    /**
     * Create handover document from this shift
     */
    public function createHandover(array $data = []): ShiftHandover
    {
        $formatter = app(\App\Services\ClinicalHandoverFormatter::class);
        $formatted = $formatter->formatShiftHandover($this);

        $handover = ShiftHandover::create([
            'shift_id' => $this->id,
            'created_by' => $this->user_id,
            'received_by' => $data['received_by'] ?? null,
            'ward_id' => $this->ward_id,
            'shift_type' => $this->shift_type,
            'shift_started_at' => $this->started_at,
            'shift_ended_at' => $this->ended_at ?? now(),
            'summary' => $data['summary'] ?? $formatted['executive_summary'],
            'critical_notes' => $data['critical_notes'] ?? $this->critical_notes,
            'concluding_notes' => $data['concluding_notes'] ?? $this->concluding_notes,
            'pending_tasks' => $data['pending_tasks'] ?? null,
            'patient_highlights' => $data['patient_highlights'] ?? $formatted['patient_summaries'],
            'action_summary' => $data['action_summary'] ?? $formatted['category_counts'],
            'audit_details' => $data['audit_details'] ?? $formatted['activity_timeline'],
        ]);

        $this->update(['handover_created' => true]);

        return $handover;
    }

    /**
     * Generate auto-summary from actions
     */
    public function generateSummary(): string
    {
        $parts = [];

        if ($this->vitals_count > 0) {
            $parts[] = "{$this->vitals_count} vital sign(s) recorded";
        }
        if ($this->medications_count > 0) {
            $parts[] = "{$this->medications_count} medication(s) administered";
        }
        if ($this->injections_count > 0) {
            $parts[] = "{$this->injections_count} injection(s) given";
        }
        if ($this->immunizations_count > 0) {
            $parts[] = "{$this->immunizations_count} immunization(s) administered";
        }
        if ($this->notes_count > 0) {
            $parts[] = "{$this->notes_count} nursing note(s) added";
        }
        if ($this->bills_count > 0) {
            $parts[] = "{$this->bills_count} bill item(s) created";
        }

        if (empty($parts)) {
            return "No significant actions recorded during this shift.";
        }

        return "During this shift: " . implode(', ', $parts) . ".";
    }

    /**
     * Get action summary as array
     */
    public function getActionSummary(): array
    {
        return [
            'vitals' => $this->vitals_count,
            'medications' => $this->medications_count,
            'injections' => $this->injections_count,
            'immunizations' => $this->immunizations_count,
            'notes' => $this->notes_count,
            'bills' => $this->bills_count,
            'patients_seen' => $this->patients_seen,
            'total' => $this->total_actions,
        ];
    }
}
