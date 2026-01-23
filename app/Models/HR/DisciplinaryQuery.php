<?php

namespace App\Models\HR;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * HRMS Implementation Plan - Section 5.2
 * Disciplinary Query Model with response tracking
 */
class DisciplinaryQuery extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'query_number',
        'staff_id',
        'subject',
        'description',
        'severity',
        'incident_date',
        'expected_response',
        'response_deadline',
        'status',
        'staff_response',
        'response_received_at',
        'hr_decision',
        'outcome',
        'decided_by',
        'decided_at',
        'issued_by'
    ];

    protected $casts = [
        'incident_date' => 'date',
        'response_deadline' => 'date',
        'response_received_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    const STATUS_ISSUED = 'issued';
    const STATUS_RESPONSE_RECEIVED = 'response_received';
    const STATUS_UNDER_REVIEW = 'under_review';
    const STATUS_CLOSED = 'closed';

    const SEVERITY_MINOR = 'minor';
    const SEVERITY_MODERATE = 'moderate';
    const SEVERITY_MAJOR = 'major';
    const SEVERITY_GROSS_MISCONDUCT = 'gross_misconduct';

    const OUTCOME_WARNING = 'warning';
    const OUTCOME_FINAL_WARNING = 'final_warning';
    const OUTCOME_SUSPENSION = 'suspension';
    const OUTCOME_TERMINATION = 'termination';
    const OUTCOME_DISMISSED = 'dismissed';
    const OUTCOME_NO_ACTION = 'no_action';

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->query_number)) {
                $model->query_number = self::generateQueryNumber();
            }
        });
    }

    /**
     * Generate unique query number
     */
    public static function generateQueryNumber(): string
    {
        $prefix = 'DQ';
        $year = date('Y');
        $lastQuery = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastQuery ? (int) substr($lastQuery->query_number, -6) + 1 : 1;
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
     * Get the issuer
     */
    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Get the decision maker
     */
    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * Get related suspension
     */
    public function suspension()
    {
        return $this->hasOne(StaffSuspension::class);
    }

    /**
     * Get related termination
     */
    public function termination()
    {
        return $this->hasOne(StaffTermination::class);
    }

    /**
     * Get attachments
     */
    public function attachments()
    {
        return $this->morphMany(HrAttachment::class, 'attachable');
    }

    /**
     * Check if response is overdue
     */
    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_ISSUED &&
               $this->response_deadline->isPast();
    }

    /**
     * Get severity badge
     */
    public function getSeverityBadgeAttribute(): string
    {
        return match($this->severity) {
            self::SEVERITY_MINOR => 'info',
            self::SEVERITY_MODERATE => 'warning',
            self::SEVERITY_MAJOR => 'danger',
            self::SEVERITY_GROSS_MISCONDUCT => 'dark',
            default => 'secondary'
        };
    }

    /**
     * Get status badge
     */
    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            self::STATUS_ISSUED => 'warning',
            self::STATUS_RESPONSE_RECEIVED => 'info',
            self::STATUS_UNDER_REVIEW => 'primary',
            self::STATUS_CLOSED => 'secondary',
            default => 'secondary'
        };
    }

    /**
     * Get static severities
     */
    public static function getSeverities(): array
    {
        return [
            self::SEVERITY_MINOR => 'Minor',
            self::SEVERITY_MODERATE => 'Moderate',
            self::SEVERITY_MAJOR => 'Major',
            self::SEVERITY_GROSS_MISCONDUCT => 'Gross Misconduct',
        ];
    }

    /**
     * Get static outcomes
     */
    public static function getOutcomes(): array
    {
        return [
            self::OUTCOME_WARNING => 'Verbal/Written Warning',
            self::OUTCOME_FINAL_WARNING => 'Final Warning',
            self::OUTCOME_SUSPENSION => 'Suspension',
            self::OUTCOME_TERMINATION => 'Termination',
            self::OUTCOME_DISMISSED => 'Dismissed (No Case)',
            self::OUTCOME_NO_ACTION => 'No Further Action',
        ];
    }
}
