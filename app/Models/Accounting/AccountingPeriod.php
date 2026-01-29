<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\User;
use Carbon\Carbon;

/**
 * Accounting Period Model
 *
 * Reference: Accounting System Plan §4.1 - Eloquent Models
 *
 * Represents an accounting period (typically a month) within a fiscal year.
 * All journal entries must belong to a period.
 * Status: open → closing → closed
 */
class AccountingPeriod extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'fiscal_year_id',
        'period_number',
        'period_name',
        'start_date',
        'end_date',
        'status',
        'is_adjustment_period',
        'closed_by',
        'closed_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
        'is_adjustment_period' => 'boolean',
    ];

    // Status constants
    const STATUS_OPEN = 'open';
    const STATUS_CLOSING = 'closing';
    const STATUS_CLOSED = 'closed';

    /**
     * Get the fiscal year this period belongs to.
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * Get the user who closed this period.
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get the journal entries in this period.
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * Check if this period can be closed.
     */
    public function canClose(): bool
    {
        // Must be open
        if ($this->status !== self::STATUS_OPEN) {
            return false;
        }

        // All journal entries must be posted or reversed (no drafts or pending)
        $pendingEntries = $this->journalEntries()
            ->whereIn('status', ['draft', 'pending_approval', 'approved'])
            ->count();

        return $pendingEntries === 0;
    }

    /**
     * Check if this period is open.
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Check if this period is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Check if a date falls within this period.
     */
    public function containsDate(Carbon $date): bool
    {
        return $date->between($this->start_date, $this->end_date);
    }

    /**
     * Get the current open period.
     */
    public static function current(): ?self
    {
        return static::where('status', self::STATUS_OPEN)
            ->whereHas('fiscalYear', function ($q) {
                $q->where('status', FiscalYear::STATUS_OPEN);
            })
            ->first();
    }

    /**
     * Get the period for a specific date.
     */
    public static function forDate(Carbon $date): ?self
    {
        return static::where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->first();
    }

    /**
     * Scope to filter by status.
     */
    public function scopeOfStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get open periods.
     */
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
