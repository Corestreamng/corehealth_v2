<?php

namespace App\Models\HR;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class StaffTraining extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'staff_id',
        'type',
        'title',
        'description',
        'institution',
        'start_date',
        'end_date',
        'status',
        'certificate_path',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    const TYPE_ATTENDED = 'attended';
    const TYPE_IDENTIFIED = 'identified';
    const TYPE_CAREER_PLAN = 'career_plan';

    const STATUS_PLANNED = 'planned';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeAttended($query)
    {
        return $query->where('type', self::TYPE_ATTENDED);
    }

    public function scopeIdentified($query)
    {
        return $query->where('type', self::TYPE_IDENTIFIED);
    }

    public function scopeCareerPlan($query)
    {
        return $query->where('type', self::TYPE_CAREER_PLAN);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_PLANNED, self::STATUS_IN_PROGRESS]);
    }

    public function getTypeLabelAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_ATTENDED => 'Attended',
            self::TYPE_IDENTIFIED => 'Identified Need',
            self::TYPE_CAREER_PLAN => 'Career Plan',
            default => ucfirst($this->type),
        };
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PLANNED => 'badge-info',
            self::STATUS_IN_PROGRESS => 'badge-warning',
            self::STATUS_COMPLETED => 'badge-success',
            self::STATUS_CANCELLED => 'badge-secondary',
            default => 'badge-secondary',
        };
    }

    public static function getTypes(): array
    {
        return [
            self::TYPE_ATTENDED => 'Training Attended',
            self::TYPE_IDENTIFIED => 'Identified Training Need',
            self::TYPE_CAREER_PLAN => 'Career Plan',
        ];
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PLANNED => 'Planned',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
    }
}
