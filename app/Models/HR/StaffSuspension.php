<?php

namespace App\Models\HR;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * HRMS Implementation Plan - Section 5.2
 * Staff Suspension Model with login blocking support
 */
class StaffSuspension extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'suspension_number',
        'staff_id',
        'disciplinary_query_id',
        'type',
        'start_date',
        'end_date',
        'reason',
        'suspension_message',
        'status',
        'lifted_by',
        'lifted_at',
        'lift_reason',
        'issued_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'lifted_at' => 'datetime',
    ];

    public const TYPE_PAID = 'paid';
    public const TYPE_UNPAID = 'unpaid';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_LIFTED = 'lifted';
    public const STATUS_EXPIRED = 'expired';

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->suspension_number)) {
                $model->suspension_number = self::generateSuspensionNumber();
            }
        });

        // When suspension is created, update staff employment status, deactivate user, and revoke locum passes
        static::created(function ($model) {
            $staff = $model->staff;
            if ($staff) {
                $staff->update([
                    'employment_status' => Staff::STATUS_SUSPENDED,
                    'status' => 0,
                    'suspended_at' => now(),
                    'suspended_by' => $model->issued_by,
                    'suspension_reason' => $model->reason,
                    'suspension_end_date' => $model->end_date,
                ]);

                if ($staff->user) {
                    $staff->user->update(['status' => 0]);
                    if ($staff->user->isLocum()) {
                        \App\Models\LocumPass::where('user_id', $staff->user->id)
                            ->whereNull('revoked_at')
                            ->update([
                                'revoked_at' => now(),
                                'revoked_by' => $model->issued_by ?: auth()->id() ?: 1,
                                'revocation_reason' => 'Staff suspended by HR disciplinary action',
                            ]);
                    }
                }
            }
        });

        // When suspension is lifted, automatically restore staff and user status to active
        static::updated(function ($model) {
            if ($model->wasChanged('status') && $model->status === self::STATUS_LIFTED) {
                $staff = $model->staff;
                if ($staff) {
                    $staff->update([
                        'employment_status' => Staff::STATUS_ACTIVE,
                        'status' => 1,
                        'suspended_at' => null,
                        'suspended_by' => null,
                        'suspension_reason' => null,
                        'suspension_end_date' => null,
                    ]);

                    if ($staff->user) {
                        $staff->user->update(['status' => 1]);
                    }
                }
            }
        });
    }

    /**
     * Generate unique suspension number
     */
    public static function generateSuspensionNumber(): string
    {
        $prefix = 'SUS';
        $year = date('Y');
        $last = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $last ? (int) substr($last->suspension_number, -6) + 1 : 1;

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
     * Get the issuer
     */
    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Get who lifted the suspension
     */
    public function liftedBy()
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    /**
     * Get attachments
     */
    public function attachments()
    {
        return $this->morphMany(HrAttachment::class, 'attachable');
    }

    /**
     * Check if suspension is active
     */
    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        // Check if end date has passed
        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Lift the suspension
     */
    public function lift(int $liftedBy, string $reason): void
    {
        $this->update([
            'status' => self::STATUS_LIFTED,
            'lifted_by' => $liftedBy,
            'lifted_at' => now(),
            'lift_reason' => $reason,
        ]);

        // Update staff status
        $this->staff->update([
            'employment_status' => Staff::STATUS_ACTIVE,
            'status' => 1,
            'suspended_at' => null,
            'suspended_by' => null,
            'suspension_reason' => null,
            'suspension_end_date' => null,
        ]);

        if ($this->staff->user) {
            $this->staff->user->update(['status' => 1]);
        }
    }

    /**
     * Get status badge
     */
    public function getStatusBadgeAttribute(): string
    {
        return match($this->status) {
            self::STATUS_ACTIVE => 'danger',
            self::STATUS_LIFTED => 'success',
            self::STATUS_EXPIRED => 'secondary',
            default => 'secondary'
        };
    }

    /**
     * Get type badge
     */
    public function getTypeBadgeAttribute(): string
    {
        return match($this->type) {
            self::TYPE_PAID => 'info',
            self::TYPE_UNPAID => 'warning',
            default => 'secondary'
        };
    }

    /**
     * Scope for active suspensions
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('end_date')
                  ->orWhere('end_date', '>=', now());
            });
    }
}
