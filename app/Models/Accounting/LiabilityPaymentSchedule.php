<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Liability Payment Schedule Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 4.1A
 * Reference: migration 2026_01_31_100007_create_liabilities_and_leases_tables.php
 *
 * Tracks individual payment schedules for liabilities (amortization).
 * Observer creates JE when payment is recorded.
 */
class LiabilityPaymentSchedule extends Model
{
    use HasFactory;

    protected $table = 'liability_payment_schedules';

    // Payment Status Constants
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_PAID = 'paid';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_WAIVED = 'waived';

    protected $fillable = [
        'liability_id',
        'journal_entry_id',
        'payment_number',
        'due_date',
        'payment_date',
        'scheduled_payment',
        'principal_portion',
        'interest_portion',
        'actual_payment',
        'late_fee',
        'opening_balance',
        'closing_balance',
        'status',
        'payment_reference',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'payment_date' => 'date',
        'scheduled_payment' => 'decimal:2',
        'principal_portion' => 'decimal:2',
        'interest_portion' => 'decimal:2',
        'actual_payment' => 'decimal:2',
        'late_fee' => 'decimal:2',
        'opening_balance' => 'decimal:2',
        'closing_balance' => 'decimal:2',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Parent liability schedule
     */
    public function liability()
    {
        return $this->belongsTo(LiabilitySchedule::class, 'liability_id');
    }

    /**
     * Journal entry for this payment
     */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_OVERDUE)
            ->orWhere(function ($q) {
                $q->where('status', self::STATUS_SCHEDULED)
                    ->where('due_date', '<', now()->toDateString());
            });
    }

    public function scopeDueThisMonth($query)
    {
        return $query->whereBetween('due_date', [
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString()
        ])->where('status', self::STATUS_SCHEDULED);
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === self::STATUS_SCHEDULED
            && $this->due_date < now()->toDateString();
    }

    public function getIsPaidAttribute(): bool
    {
        return $this->status === self::STATUS_PAID && $this->payment_date !== null;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => '<span class="badge badge-success"><i class="mdi mdi-check"></i> Paid</span>',
            self::STATUS_PARTIAL => '<span class="badge badge-warning"><i class="mdi mdi-alert"></i> Partial</span>',
            self::STATUS_OVERDUE => '<span class="badge badge-danger"><i class="mdi mdi-alert-circle"></i> Overdue</span>',
            self::STATUS_WAIVED => '<span class="badge badge-secondary"><i class="mdi mdi-close"></i> Waived</span>',
            default => $this->is_overdue
                ? '<span class="badge badge-danger"><i class="mdi mdi-alert-circle"></i> Overdue</span>'
                : '<span class="badge badge-info"><i class="mdi mdi-clock"></i> Scheduled</span>',
        };
    }
}
