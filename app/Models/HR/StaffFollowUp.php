<?php

namespace App\Models\HR;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class StaffFollowUp extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'staff_id',
        'subject',
        'details',
        'due_date',
        'status',
        'priority',
        'created_by',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'resolved_at' => 'datetime',
    ];

    const STATUS_OPEN = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';

    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolvedByUser()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function resolve(int $userId): void
    {
        $this->update([
            'status' => self::STATUS_RESOLVED,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->status !== self::STATUS_RESOLVED;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN => 'badge-warning',
            self::STATUS_IN_PROGRESS => 'badge-info',
            self::STATUS_RESOLVED => 'badge-success',
            default => 'badge-secondary',
        };
    }

    public function getPriorityBadgeAttribute(): string
    {
        return match ($this->priority) {
            self::PRIORITY_HIGH => 'badge-danger',
            self::PRIORITY_MEDIUM => 'badge-warning',
            self::PRIORITY_LOW => 'badge-info',
            default => 'badge-secondary',
        };
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
    }

    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->where('status', '!=', self::STATUS_RESOLVED);
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_OPEN => 'Open',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_RESOLVED => 'Resolved',
        ];
    }

    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_LOW => 'Low',
            self::PRIORITY_MEDIUM => 'Medium',
            self::PRIORITY_HIGH => 'High',
        ];
    }
}
