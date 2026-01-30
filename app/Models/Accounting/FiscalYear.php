<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\User;

/**
 * Fiscal Year Model
 *
 * Reference: Accounting System Plan §4.1 - Eloquent Models
 *
 * Represents a fiscal year containing multiple accounting periods.
 * Status: open → closing → closed
 */
class FiscalYear extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'year_name',
        'start_date',
        'end_date',
        'status',
        'closed_by',
        'closed_at',
        'retained_earnings_entry_id',
    ];

    protected $appends = ['name', 'is_active'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'closed_at' => 'datetime',
    ];

    // Status constants
    const STATUS_OPEN = 'open';
    const STATUS_CLOSING = 'closing';
    const STATUS_CLOSED = 'closed';

    /**
     * Accessor for backward compatibility with code using name.
     */
    public function getNameAttribute(): string
    {
        return $this->year_name ?? '';
    }

    /**
     * Accessor for backward compatibility with code using is_active.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Get the periods for this fiscal year.
     */
    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class)->orderBy('period_number');
    }

    /**
     * Get the user who closed this fiscal year.
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Get the retained earnings journal entry created on year close.
     */
    public function retainedEarningsEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'retained_earnings_entry_id');
    }

    /**
     * Check if this fiscal year can be closed.
     */
    public function canClose(): bool
    {
        // All periods must be closed
        return $this->status === self::STATUS_OPEN
            && $this->periods()->where('status', '!=', AccountingPeriod::STATUS_CLOSED)->count() === 0;
    }

    /**
     * Check if this fiscal year is open.
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Check if this fiscal year is closed.
     */
    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Get the current open fiscal year.
     */
    public static function current(): ?self
    {
        return static::where('status', self::STATUS_OPEN)->first();
    }

    /**
     * Scope to filter by status.
     */
    public function scopeOfStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
