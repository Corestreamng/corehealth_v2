<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class PatientImmunizationSchedule extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'patient_id',
        'schedule_item_id',
        'due_date',
        'administered_date',
        'status',
        'immunization_record_id',
        'skip_reason',
        'notes',
        'updated_by',
    ];

    protected $casts = [
        'due_date' => 'date',
        'administered_date' => 'date',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_DUE = 'due';
    const STATUS_OVERDUE = 'overdue';
    const STATUS_ADMINISTERED = 'administered';
    const STATUS_SKIPPED = 'skipped';
    const STATUS_CONTRAINDICATED = 'contraindicated';

    /**
     * Get the patient for this schedule entry.
     */
    public function patient()
    {
        return $this->belongsTo(patient::class, 'patient_id');
    }

    /**
     * Get the schedule item.
     */
    public function scheduleItem()
    {
        return $this->belongsTo(VaccineScheduleItem::class, 'schedule_item_id');
    }

    /**
     * Get the immunization record if administered.
     */
    public function immunizationRecord()
    {
        return $this->belongsTo(ImmunizationRecord::class, 'immunization_record_id');
    }

    /**
     * Get the user who last updated this record.
     */
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Generate schedule for a patient from a template.
     */
    public static function generateForPatient($patientId, $templateId = null)
    {
        $patient = patient::findOrFail($patientId);

        // Patient model uses 'dob' not 'date_of_birth'
        if (!$patient->dob) {
            throw new \Exception('Patient date of birth is required to generate immunization schedule.');
        }

        $template = $templateId
            ? VaccineScheduleTemplate::findOrFail($templateId)
            : VaccineScheduleTemplate::getDefault();

        if (!$template) {
            throw new \Exception('No vaccine schedule template found.');
        }

        $schedules = [];
        $today = Carbon::today();

        foreach ($template->items as $item) {
            // Check if schedule already exists for this patient and item
            $existing = self::where('patient_id', $patientId)
                ->where('schedule_item_id', $item->id)
                ->first();

            if ($existing) {
                continue; // Skip if already exists
            }

            $dueDate = $item->calculateDueDate($patient->dob);

            // Determine initial status
            $status = self::STATUS_PENDING;
            if ($dueDate->isPast()) {
                $status = self::STATUS_OVERDUE;
            } elseif ($dueDate->isSameDay($today) || $dueDate->diffInDays($today) <= 7) {
                $status = self::STATUS_DUE;
            }

            $schedules[] = self::create([
                'patient_id' => $patientId,
                'schedule_item_id' => $item->id,
                'due_date' => $dueDate,
                'status' => $status,
            ]);
        }

        return $schedules;
    }

    /**
     * Update statuses for a patient's schedule based on current date.
     */
    public static function updateStatusesForPatient($patientId)
    {
        $today = Carbon::today();

        // Update overdue
        self::where('patient_id', $patientId)
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_DUE])
            ->where('due_date', '<', $today)
            ->update(['status' => self::STATUS_OVERDUE]);

        // Update due
        self::where('patient_id', $patientId)
            ->where('status', self::STATUS_PENDING)
            ->where('due_date', '>=', $today)
            ->where('due_date', '<=', $today->copy()->addDays(7))
            ->update(['status' => self::STATUS_DUE]);
    }

    /**
     * Mark this schedule entry as administered.
     */
    public function markAsAdministered($immunizationRecordId, $userId = null)
    {
        $this->update([
            'status' => self::STATUS_ADMINISTERED,
            'administered_date' => Carbon::today(),
            'immunization_record_id' => $immunizationRecordId,
            'updated_by' => $userId ?? auth()->id(),
        ]);
    }

    /**
     * Mark this schedule entry as skipped.
     */
    public function markAsSkipped($reason, $userId = null)
    {
        $this->update([
            'status' => self::STATUS_SKIPPED,
            'skip_reason' => $reason,
            'updated_by' => $userId ?? auth()->id(),
        ]);
    }

    /**
     * Mark this schedule entry as contraindicated.
     */
    public function markAsContraindicated($reason, $userId = null)
    {
        $this->update([
            'status' => self::STATUS_CONTRAINDICATED,
            'skip_reason' => $reason,
            'updated_by' => $userId ?? auth()->id(),
        ]);
    }

    /**
     * Scope to get pending/due/overdue schedules.
     */
    public function scopeActionable($query)
    {
        return $query->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_DUE,
            self::STATUS_OVERDUE,
        ]);
    }

    /**
     * Get status badge class for UI.
     */
    public function getStatusBadgeClassAttribute()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'badge-secondary',
            self::STATUS_DUE => 'badge-warning',
            self::STATUS_OVERDUE => 'badge-danger',
            self::STATUS_ADMINISTERED => 'badge-success',
            self::STATUS_SKIPPED => 'badge-info',
            self::STATUS_CONTRAINDICATED => 'badge-dark',
            default => 'badge-secondary',
        };
    }

    /**
     * Get human-readable status label.
     */
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_DUE => 'Due',
            self::STATUS_OVERDUE => 'Overdue',
            self::STATUS_ADMINISTERED => 'Administered',
            self::STATUS_SKIPPED => 'Skipped',
            self::STATUS_CONTRAINDICATED => 'Contraindicated',
            default => ucfirst($this->status),
        };
    }
}
