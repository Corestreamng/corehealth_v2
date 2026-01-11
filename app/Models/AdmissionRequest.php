<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;

/**
 * AdmissionRequest Model
 *
 * Represents a patient admission to the hospital.
 *
 * Workflow with checklists:
 * 1. Doctor creates admission request (admission_status: pending_checklist)
 * 2. Nurse completes admission checklist
 * 3. Nurse assigns bed (admission_status: admitted)
 * 4. Doctor requests discharge (admission_status: discharge_requested)
 * 5. Nurse completes discharge checklist
 * 6. Nurse finalizes discharge (admission_status: discharged)
 *
 * @see App\Models\AdmissionChecklist
 * @see App\Models\DischargeChecklist
 * @see App\Models\Bed
 */
class AdmissionRequest extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'service_request_id',
        'billed_by',
        'billed_date',
        'service_id',
        'encounter_id',
        'patient_id',
        'bed_id',
        'bed_assign_date',
        'bed_assigned_by',
        'discharged',
        'discharge_date',
        'discharged_by',
        'doctor_id',
        'note',
        'status',
        'admission_status',     // New workflow status field
        'admission_reason',
        'discharge_reason',
        'discharge_note',
        'followup_instructions',
        'priority'
    ];

    protected $casts = [
        'billed_date' => 'datetime',
        'bed_assign_date' => 'datetime',
        'discharge_date' => 'datetime',
        'discharged' => 'boolean',
    ];

    /**
     * Admission workflow status constants
     */
    public const STATUS_PENDING_CHECKLIST = 'pending_checklist';
    public const STATUS_CHECKLIST_COMPLETE = 'checklist_complete';
    public const STATUS_ADMITTED = 'admitted';
    public const STATUS_DISCHARGE_REQUESTED = 'discharge_requested';
    public const STATUS_DISCHARGE_CHECKLIST = 'discharge_checklist';
    public const STATUS_DISCHARGED = 'discharged';

    /**
     * Priority constants
     */
    public const PRIORITY_ROUTINE = 'routine';
    public const PRIORITY_URGENT = 'urgent';
    public const PRIORITY_EMERGENCY = 'emergency';

    // =====================
    // Relationships
    // =====================

    public function productOrServiceRequest()
    {
        return $this->belongsTo(ProductOrServiceRequest::class, 'service_request_id', 'id');
    }

    public function service()
    {
        return $this->belongsTo(service::class, 'service_id', 'id');
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class, 'encounter_id', 'id');
    }

    public function patient()
    {
        return $this->belongsTo(patient::class, 'patient_id', 'id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id', 'id');
    }

    public function biller()
    {
        return $this->belongsTo(User::class, 'billed_by', 'id');
    }

    public function bed_assigner()
    {
        return $this->belongsTo(User::class, 'bed_assigned_by', 'id');
    }

    public function discharger()
    {
        return $this->belongsTo(User::class, 'discharged_by', 'id');
    }

    public function bed()
    {
        return $this->hasOne(Bed::class, 'id', 'bed_id');
    }

    /**
     * Admission checklist for this admission
     */
    public function admissionChecklist()
    {
        return $this->hasOne(AdmissionChecklist::class, 'admission_request_id');
    }

    /**
     * Discharge checklist for this admission
     */
    public function dischargeChecklist()
    {
        return $this->hasOne(DischargeChecklist::class, 'admission_request_id');
    }

    // =====================
    // Scopes
    // =====================

    /**
     * Filter active admissions (not discharged)
     */
    public function scopeActive($query)
    {
        return $query->where('discharged', 0);
    }

    /**
     * Filter discharged admissions
     */
    public function scopeDischarged($query)
    {
        return $query->where('discharged', 1);
    }

    /**
     * Filter admissions with beds assigned
     */
    public function scopeWithBed($query)
    {
        return $query->whereNotNull('bed_id');
    }

    /**
     * Filter admissions pending bed assignment
     */
    public function scopePendingBed($query)
    {
        return $query->whereNull('bed_id')->where('discharged', 0);
    }

    /**
     * Filter by admission workflow status
     */
    public function scopeWithWorkflowStatus($query, string $status)
    {
        return $query->where('admission_status', $status);
    }

    /**
     * Filter admissions pending discharge (doctor requested)
     */
    public function scopePendingDischarge($query)
    {
        return $query->where('admission_status', self::STATUS_DISCHARGE_REQUESTED)
            ->orWhere('admission_status', self::STATUS_DISCHARGE_CHECKLIST);
    }

    // =====================
    // Accessors
    // =====================

    /**
     * Get days admitted
     */
    public function getDaysAdmittedAttribute(): int
    {
        if (!$this->bed_assign_date) {
            return 0;
        }

        $endDate = $this->discharge_date ?? now();
        return \Carbon\Carbon::parse($this->bed_assign_date)->diffInDays($endDate);
    }

    /**
     * Get human-readable workflow status
     */
    public function getWorkflowStatusLabelAttribute(): string
    {
        return match($this->admission_status) {
            self::STATUS_PENDING_CHECKLIST => 'Pending Admission Checklist',
            self::STATUS_CHECKLIST_COMPLETE => 'Awaiting Bed Assignment',
            self::STATUS_ADMITTED => 'Admitted',
            self::STATUS_DISCHARGE_REQUESTED => 'Discharge Requested',
            self::STATUS_DISCHARGE_CHECKLIST => 'Pending Discharge Checklist',
            self::STATUS_DISCHARGED => 'Discharged',
            default => 'Unknown',
        };
    }

    /**
     * Get priority badge class
     */
    public function getPriorityBadgeClassAttribute(): string
    {
        return match($this->priority) {
            self::PRIORITY_EMERGENCY => 'badge-danger',
            self::PRIORITY_URGENT => 'badge-warning',
            default => 'badge-secondary',
        };
    }

    // =====================
    // Methods
    // =====================

    /**
     * Check if admission checklist is required and complete
     */
    public function isAdmissionChecklistComplete(): bool
    {
        $checklist = $this->admissionChecklist;

        if (!$checklist) {
            return true; // No checklist required
        }

        return in_array($checklist->status, [
            AdmissionChecklist::STATUS_COMPLETED,
            AdmissionChecklist::STATUS_WAIVED,
        ]);
    }

    /**
     * Check if discharge checklist is required and complete
     */
    public function isDischargeChecklistComplete(): bool
    {
        $checklist = $this->dischargeChecklist;

        if (!$checklist) {
            return true; // No checklist required
        }

        return in_array($checklist->status, [
            DischargeChecklist::STATUS_COMPLETED,
            DischargeChecklist::STATUS_WAIVED,
        ]);
    }

    /**
     * Request discharge (called by doctor)
     */
    public function requestDischarge(
        int $doctorId,
        string $reason,
        string $note,
        ?string $followupInstructions = null
    ): void {
        $this->update([
            'admission_status' => self::STATUS_DISCHARGE_REQUESTED,
            'discharge_reason' => $reason,
            'discharge_note' => $note,
            'followup_instructions' => $followupInstructions,
        ]);

        // Create discharge checklist
        DischargeChecklist::createFromTemplate($this->id);
    }

    /**
     * Finalize discharge (called by nurse after checklist complete)
     */
    public function finalizeDischarge(int $nurseId): void
    {
        // Release bed
        if ($this->bed_id) {
            Bed::where('id', $this->bed_id)->update([
                'occupant_id' => null,
                'bed_status' => Bed::STATUS_AVAILABLE,
            ]);
        }

        $this->update([
            'discharged' => true,
            'discharge_date' => now(),
            'discharged_by' => $nurseId,
            'admission_status' => self::STATUS_DISCHARGED,
        ]);
    }
}
