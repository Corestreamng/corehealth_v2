<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


use OwenIt\Auditing\Contracts\Auditable;
class MedicationSchedule extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'patient_id',
        'product_or_service_request_id',
        'scheduled_date',
        'scheduled_time',
        'is_repeating',
        'repeat_until',
        'is_discontinued',
        'discontinued_at',
        'discontinue_reason',
        'discontinued_by',
        'is_resumed',
        'resumed_at',
        'resume_reason',
        'resumed_by',
        'created_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'repeat_until' => 'date',
        'discontinued_at' => 'datetime',
        'resumed_at' => 'datetime',
        'is_repeating' => 'boolean',
        'is_discontinued' => 'boolean',
        'is_resumed' => 'boolean',
    ];

    /**
     * Get the patient that owns the schedule.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the product or service request associated with this schedule.
     */
    public function productOrServiceRequest(): BelongsTo
    {
        return $this->belongsTo(ProductOrServiceRequest::class);
    }

    /**
     * Get the user who created this schedule.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who discontinued this schedule.
     */
    public function discontinuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discontinued_by');
    }

    /**
     * Get the user who resumed this schedule.
     */
    public function resumer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resumed_by');
    }

    /**
     * Get all administrations for this schedule.
     */
    public function administrations(): HasMany
    {
        return $this->hasMany(MedicationAdministration::class, 'schedule_id');
    }

    /**
     * Check if the schedule is active on a specific date.
     */
    public function isActiveOn($date)
    {
        // If the schedule is not repeating, it's only active on its scheduled date
        if (!$this->is_repeating) {
            return $this->scheduled_date->format('Y-m-d') === $date->format('Y-m-d');
        }

        // Check if the date is within the range (scheduled_date to repeat_until)
        $isWithinRange = $date->greaterThanOrEqualTo($this->scheduled_date) &&
                         (!$this->repeat_until || $date->lessThanOrEqualTo($this->repeat_until));

        // If discontinued, check if the date is before the discontinuation
        if ($this->is_discontinued && $this->discontinued_at) {
            if ($date->greaterThanOrEqualTo($this->discontinued_at->startOfDay())) {
                // If it's resumed after being discontinued, check the resumed date
                if ($this->is_resumed && $this->resumed_at) {
                    return $isWithinRange && $date->greaterThanOrEqualTo($this->resumed_at->startOfDay());
                }
                return false; // Discontinued and not resumed
            }
        }

        return $isWithinRange;
    }
}
