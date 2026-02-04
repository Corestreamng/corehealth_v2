<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lease Payment Schedule Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.13
 *
 * Tracks individual lease payments with IFRS 16 interest/principal split.
 * Observer creates journal entries automatically on payment.
 *
 * Journal Entry on payment:
 *   DEBIT:  Lease Liability (principal)      - 2310
 *   DEBIT:  Interest Expense (interest)      - 6300
 *   CREDIT: Bank/Cash Account (total)        - 1020/1010
 */
class LeasePaymentSchedule extends Model
{
    protected $table = 'lease_payment_schedules';

    // Payment Status Constants
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PAID = 'paid';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_OVERDUE = 'overdue';

    protected $fillable = [
        'lease_id',
        'journal_entry_id',
        'payment_number',
        'due_date',
        'payment_date',
        'payment_amount',
        'principal_portion',
        'interest_portion',
        'actual_payment',
        'opening_liability',
        'closing_liability',
        'rou_depreciation',
        'opening_rou_value',
        'closing_rou_value',
        'status',
        'payment_reference',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'payment_date' => 'date',
        'payment_amount' => 'decimal:2',
        'principal_portion' => 'decimal:2',
        'interest_portion' => 'decimal:2',
        'actual_payment' => 'decimal:2',
        'opening_liability' => 'decimal:2',
        'closing_liability' => 'decimal:2',
        'rou_depreciation' => 'decimal:2',
        'opening_rou_value' => 'decimal:2',
        'closing_rou_value' => 'decimal:2',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get all journal entries related to this payment.
     * Uses polymorphic relationship via reference_type and reference_id.
     */
    public function journalEntries()
    {
        return $this->hasMany(JournalEntry::class, 'reference_id')
            ->where('reference_type', 'lease_payment');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_SCHEDULED, self::STATUS_OVERDUE]);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_OVERDUE)
            ->orWhere(function ($q) {
                $q->where('status', self::STATUS_SCHEDULED)
                  ->where('due_date', '<', now());
            });
    }

    public function scopeDueWithin($query, int $days)
    {
        return $query->where('status', self::STATUS_SCHEDULED)
            ->whereBetween('due_date', [now(), now()->addDays($days)]);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OVERDUE ||
               ($this->status === self::STATUS_SCHEDULED && $this->due_date->lt(now()));
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SCHEDULED => 'Scheduled',
            self::STATUS_PAID => 'Paid',
            self::STATUS_PARTIAL => 'Partial',
            self::STATUS_OVERDUE => 'Overdue',
            default => ucfirst($this->status ?? 'Unknown'),
        };
    }

    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_SCHEDULED => 'badge-info',
            self::STATUS_PAID => 'badge-success',
            self::STATUS_PARTIAL => 'badge-warning',
            self::STATUS_OVERDUE => 'badge-danger',
            default => 'badge-secondary',
        };
    }
}
