<?php

namespace App\Models\HR;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * HRMS Implementation Plan - Section 5.2
 * Staff Termination Model with exit process tracking
 */
class StaffTermination extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'termination_number',
        'staff_id',
        'disciplinary_query_id',
        'type',
        'reason_category',
        'reason_details',
        'notice_date',
        'effective_date',
        'last_working_day',
        'exit_interview_conducted',
        'exit_interview_notes',
        'clearance_completed',
        'final_payment_processed',
        'processed_by'
    ];

    protected $casts = [
        'notice_date' => 'date',
        'effective_date' => 'date',
        'last_working_day' => 'date',
        'exit_interview_conducted' => 'boolean',
        'clearance_completed' => 'boolean',
        'final_payment_processed' => 'boolean',
    ];

    const TYPE_VOLUNTARY = 'voluntary';
    const TYPE_INVOLUNTARY = 'involuntary';
    const TYPE_RETIREMENT = 'retirement';
    const TYPE_DEATH = 'death';
    const TYPE_CONTRACT_END = 'contract_end';

    const REASON_RESIGNATION = 'resignation';
    const REASON_MISCONDUCT = 'misconduct';
    const REASON_POOR_PERFORMANCE = 'poor_performance';
    const REASON_REDUNDANCY = 'redundancy';
    const REASON_RETIREMENT = 'retirement';
    const REASON_MEDICAL = 'medical';
    const REASON_DEATH = 'death';
    const REASON_CONTRACT_EXPIRY = 'contract_expiry';
    const REASON_OTHER = 'other';

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->termination_number)) {
                $model->termination_number = self::generateTerminationNumber();
            }
        });

        // When termination is created, update staff employment status
        static::created(function ($model) {
            $status = $model->type === self::TYPE_VOLUNTARY ? 'resigned' : 'terminated';
            $model->staff->update(['employment_status' => $status]);

            // Also deactivate the user account
            if ($model->staff->user) {
                $model->staff->user->update(['status' => 0]);
            }
        });
    }

    /**
     * Generate unique termination number
     */
    public static function generateTerminationNumber(): string
    {
        $prefix = 'TRM';
        $year = date('Y');
        $last = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $last ? (int) substr($last->termination_number, -6) + 1 : 1;
        return $prefix . $year . str_pad($sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Get the staff member
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get the related query
     */
    public function disciplinaryQuery()
    {
        return $this->belongsTo(DisciplinaryQuery::class);
    }

    /**
     * Get who processed the termination
     */
    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get attachments
     */
    public function attachments()
    {
        return $this->morphMany(HrAttachment::class, 'attachable');
    }

    /**
     * Check if clearance is complete
     */
    public function isClearanceComplete(): bool
    {
        return $this->clearance_completed && $this->final_payment_processed;
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            self::TYPE_VOLUNTARY => 'Voluntary',
            self::TYPE_INVOLUNTARY => 'Involuntary',
            self::TYPE_RETIREMENT => 'Retirement',
            self::TYPE_DEATH => 'Death',
            self::TYPE_CONTRACT_END => 'Contract End',
            default => 'Unknown'
        };
    }

    /**
     * Get static types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_VOLUNTARY => 'Voluntary (Resignation)',
            self::TYPE_INVOLUNTARY => 'Involuntary (Termination)',
            self::TYPE_RETIREMENT => 'Retirement',
            self::TYPE_DEATH => 'Death',
            self::TYPE_CONTRACT_END => 'Contract End',
        ];
    }

    /**
     * Get static reason categories
     */
    public static function getReasonCategories(): array
    {
        return [
            self::REASON_RESIGNATION => 'Resignation',
            self::REASON_MISCONDUCT => 'Misconduct',
            self::REASON_POOR_PERFORMANCE => 'Poor Performance',
            self::REASON_REDUNDANCY => 'Redundancy',
            self::REASON_RETIREMENT => 'Retirement',
            self::REASON_MEDICAL => 'Medical Reasons',
            self::REASON_DEATH => 'Death',
            self::REASON_CONTRACT_EXPIRY => 'Contract Expiry',
            self::REASON_OTHER => 'Other',
        ];
    }
}
