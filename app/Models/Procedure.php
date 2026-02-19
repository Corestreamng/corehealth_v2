<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Procedure Model (Patient Procedure Instance)
 *
 * This model represents actual procedure instances for patients.
 * It extends the existing procedures table with additional tracking fields.
 *
 * For procedure definitions (catalog), see ProcedureDefinition model.
 */
class Procedure extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'procedures';

    protected $fillable = [
        'service_id',
        'procedure_definition_id',
        'requested_by',
        'patient_id',
        'encounter_id',
        'admission_request_id',
        'product_or_service_request_id',
        'requested_on',
        'billed_by',
        'billed_on',
        'pre_notes',
        'pre_notes_by',
        'post_notes',
        'post_notes_by',
        'procedure_status',
        'priority',
        'scheduled_date',
        'scheduled_time',
        'actual_start_time',
        'actual_end_time',
        'operating_room',
        'outcome',
        'outcome_notes',
        'cancellation_reason',
        'refund_amount',
        'cancelled_at',
        'cancelled_by',
        'status',
    ];

    protected $casts = [
        'requested_on' => 'datetime',
        'billed_on' => 'datetime',
        'scheduled_date' => 'date',
        'actual_start_time' => 'datetime',
        'actual_end_time' => 'datetime',
        'cancelled_at' => 'datetime',
        'refund_amount' => 'decimal:2',
        'status' => 'boolean',
    ];

    /**
     * Status constants
     */
    const STATUS_REQUESTED = 'requested';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    const STATUSES = [
        'requested' => 'Requested',
        'scheduled' => 'Scheduled',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    /**
     * Priority constants
     */
    const PRIORITY_ROUTINE = 'routine';
    const PRIORITY_URGENT = 'urgent';
    const PRIORITY_EMERGENCY = 'emergency';

    const PRIORITIES = [
        'routine' => 'Routine',
        'urgent' => 'Urgent',
        'emergency' => 'Emergency',
    ];

    /**
     * Outcome constants
     */
    const OUTCOME_SUCCESSFUL = 'successful';
    const OUTCOME_COMPLICATIONS = 'complications';
    const OUTCOME_ABORTED = 'aborted';
    const OUTCOME_CONVERTED = 'converted';

    const OUTCOMES = [
        'successful' => 'Successful',
        'complications' => 'Complications Occurred',
        'aborted' => 'Aborted',
        'converted' => 'Converted to Different Procedure',
    ];

    /**
     * Get the service.
     */
    public function service()
    {
        return $this->belongsTo(service::class, 'service_id', 'id');
    }

    /**
     * Get the procedure definition (catalog entry).
     */
    public function procedureDefinition()
    {
        return $this->belongsTo(ProcedureDefinition::class, 'procedure_definition_id', 'id');
    }

    /**
     * Get the patient.
     */
    public function patient()
    {
        return $this->belongsTo(patient::class, 'patient_id', 'id');
    }

    /**
     * Get the encounter.
     */
    public function encounter()
    {
        return $this->belongsTo(Encounter::class, 'encounter_id', 'id');
    }

    /**
     * Get the admission request.
     */
    public function admissionRequest()
    {
        return $this->belongsTo(AdmissionRequest::class, 'admission_request_id', 'id');
    }

    /**
     * Get the billing entry.
     */
    public function productOrServiceRequest()
    {
        return $this->belongsTo(ProductOrServiceRequest::class, 'product_or_service_request_id', 'id');
    }

    /**
     * Get the user who requested the procedure.
     */
    public function requestedByUser()
    {
        return $this->belongsTo(User::class, 'requested_by', 'id');
    }

    /**
     * Get the user who billed the procedure.
     */
    public function billedByUser()
    {
        return $this->belongsTo(User::class, 'billed_by', 'id');
    }

    /**
     * Get the user who wrote pre-notes.
     */
    public function preNotesBy()
    {
        return $this->belongsTo(User::class, 'pre_notes_by', 'id');
    }

    /**
     * Get the user who wrote post-notes.
     */
    public function postNotesBy()
    {
        return $this->belongsTo(User::class, 'post_notes_by', 'id');
    }

    /**
     * Get the user who cancelled the procedure.
     */
    public function cancelledByUser()
    {
        return $this->belongsTo(User::class, 'cancelled_by', 'id');
    }

    /**
     * Get the procedure team members.
     */
    public function teamMembers()
    {
        return $this->hasMany(ProcedureTeamMember::class, 'procedure_id', 'id');
    }

    /**
     * Get the procedure notes (WYSIWYG).
     */
    public function notes()
    {
        return $this->hasMany(ProcedureNote::class, 'procedure_id', 'id');
    }

    /**
     * Get the procedure items (labs, imaging, medications).
     */
    public function items()
    {
        return $this->hasMany(ProcedureItem::class, 'procedure_id', 'id');
    }

    /**
     * Scope to get procedures by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('procedure_status', $status);
    }

    /**
     * Scope for requested procedures.
     */
    public function scopeRequested($query)
    {
        return $query->byStatus(self::STATUS_REQUESTED);
    }

    /**
     * Scope for scheduled procedures.
     */
    public function scopeScheduled($query)
    {
        return $query->byStatus(self::STATUS_SCHEDULED);
    }

    /**
     * Scope for in-progress procedures.
     */
    public function scopeInProgress($query)
    {
        return $query->byStatus(self::STATUS_IN_PROGRESS);
    }

    /**
     * Scope for completed procedures.
     */
    public function scopeCompleted($query)
    {
        return $query->byStatus(self::STATUS_COMPLETED);
    }

    /**
     * Scope for cancelled procedures.
     */
    public function scopeCancelled($query)
    {
        return $query->byStatus(self::STATUS_CANCELLED);
    }

    /**
     * Scope for a specific patient.
     */
    public function scopeForPatient($query, $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope for a specific encounter.
     */
    public function scopeForEncounter($query, $encounterId)
    {
        return $query->where('encounter_id', $encounterId);
    }

    /**
     * Get status display label.
     */
    public function getStatusDisplayAttribute()
    {
        return self::STATUSES[$this->procedure_status] ?? ucfirst(str_replace('_', ' ', $this->procedure_status));
    }

    /**
     * Get priority display label.
     */
    public function getPriorityDisplayAttribute()
    {
        return self::PRIORITIES[$this->priority] ?? ucfirst($this->priority);
    }

    /**
     * Get outcome display label.
     */
    public function getOutcomeDisplayAttribute()
    {
        return self::OUTCOMES[$this->outcome] ?? ucfirst(str_replace('_', ' ', $this->outcome));
    }

    /**
     * Check if procedure can be cancelled.
     */
    public function canBeCancelled()
    {
        return in_array($this->procedure_status, [self::STATUS_REQUESTED, self::STATUS_SCHEDULED]);
    }

    /**
     * Check if procedure can be scheduled.
     */
    public function canBeScheduled()
    {
        return $this->procedure_status === self::STATUS_REQUESTED;
    }

    /**
     * Check if procedure can be started.
     */
    public function canBeStarted()
    {
        return $this->procedure_status === self::STATUS_SCHEDULED;
    }

    /**
     * Check if procedure can be completed.
     */
    public function canBeCompleted()
    {
        return $this->procedure_status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Calculate duration in minutes if both start and end times are set.
     */
    public function getDurationMinutesAttribute()
    {
        if ($this->actual_start_time && $this->actual_end_time) {
            return $this->actual_start_time->diffInMinutes($this->actual_end_time);
        }
        return null;
    }

    /**
     * Get the price from the linked service.
     */
    public function getPriceAttribute()
    {
        return $this->service?->price?->price ?? 0;
    }
}
