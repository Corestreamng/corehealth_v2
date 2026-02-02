<?php

namespace App\Models;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Inter-Account Transfer Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.14
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 1.6
 *
 * Tracks transfers between bank accounts with clearance tracking.
 */
class InterAccountTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inter_account_transfers';

    // Transfer Methods
    public const METHOD_INTERNAL = 'internal';
    public const METHOD_WIRE = 'wire';
    public const METHOD_EFT = 'eft';
    public const METHOD_CHEQUE = 'cheque';
    public const METHOD_RTGS = 'rtgs';
    public const METHOD_NEFT = 'neft';

    // Status
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_INITIATED = 'initiated';
    public const STATUS_IN_TRANSIT = 'in_transit';
    public const STATUS_CLEARED = 'cleared';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'transfer_number',
        'from_bank_id',
        'to_bank_id',
        'from_account_id',
        'to_account_id',
        'journal_entry_id',
        'transfer_date',
        'amount',
        'reference',
        'description',
        'transfer_method',
        'is_same_bank',
        'expected_clearance_date',
        'actual_clearance_date',
        'transfer_fee',
        'fee_account_id',
        'status',
        'initiated_by',
        'approved_by',
        'approved_at',
        'initiated_at',
        'cleared_at',
        'failure_reason',
        'cancelled_by',
        'cancelled_at',
        'notes',
    ];

    protected $casts = [
        'transfer_date' => 'date',
        'amount' => 'decimal:2',
        'transfer_fee' => 'decimal:2',
        'is_same_bank' => 'boolean',
        'expected_clearance_date' => 'date',
        'actual_clearance_date' => 'date',
        'approved_at' => 'datetime',
        'initiated_at' => 'datetime',
        'cleared_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Source bank account.
     */
    public function fromBank()
    {
        return $this->belongsTo(Bank::class, 'from_bank_id');
    }

    /**
     * Destination bank account.
     */
    public function toBank()
    {
        return $this->belongsTo(Bank::class, 'to_bank_id');
    }

    /**
     * Source GL account.
     */
    public function fromAccount()
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    /**
     * Destination GL account.
     */
    public function toAccount()
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    /**
     * Fee expense account.
     */
    public function feeAccount()
    {
        return $this->belongsTo(Account::class, 'fee_account_id');
    }

    /**
     * Journal entry for this transfer.
     */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * User who initiated the transfer.
     */
    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /**
     * User who approved the transfer.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * User who cancelled the transfer.
     */
    public function canceller()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // ==========================================
    // STATUS METHODS
    // ==========================================

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isInitiated(): bool
    {
        return $this->status === self::STATUS_INITIATED;
    }

    public function isInTransit(): bool
    {
        return $this->status === self::STATUS_IN_TRANSIT;
    }

    public function isCleared(): bool
    {
        return $this->status === self::STATUS_CLEARED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    public function canBeInitiated(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canBeCleared(): bool
    {
        return in_array($this->status, [self::STATUS_INITIATED, self::STATUS_IN_TRANSIT]);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PENDING_APPROVAL,
            self::STATUS_APPROVED,
        ]);
    }

    // ==========================================
    // TRANSFER NUMBER GENERATION
    // ==========================================

    /**
     * Generate transfer number.
     */
    public static function generateNumber(): string
    {
        $prefix = 'TRF-';
        $year = now()->format('Y');
        $month = now()->format('m');

        $lastTransfer = self::where('transfer_number', 'like', $prefix . $year . $month . '%')
            ->orderBy('transfer_number', 'desc')
            ->first();

        if ($lastTransfer) {
            $lastNumber = (int) substr($lastTransfer->transfer_number, -5);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . $year . $month . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING_APPROVAL);
    }

    public function scopeCleared($query)
    {
        return $query->where('status', self::STATUS_CLEARED);
    }

    public function scopeInTransit($query)
    {
        return $query->whereIn('status', [self::STATUS_INITIATED, self::STATUS_IN_TRANSIT]);
    }

    public function scopeFromBank($query, int $bankId)
    {
        return $query->where('from_bank_id', $bankId);
    }

    public function scopeToBank($query, int $bankId)
    {
        return $query->where('to_bank_id', $bankId);
    }

    public function scopeForPeriod($query, string $fromDate, string $toDate)
    {
        return $query->whereBetween('transfer_date', [$fromDate, $toDate]);
    }

    public function scopeOverdueClearing($query)
    {
        return $query->whereIn('status', [self::STATUS_INITIATED, self::STATUS_IN_TRANSIT])
            ->where('expected_clearance_date', '<', now()->toDateString());
    }

    // ==========================================
    // COMPUTED ATTRIBUTES
    // ==========================================

    /**
     * Get total amount including fees.
     */
    public function getTotalAmountAttribute(): float
    {
        return $this->amount + ($this->transfer_fee ?? 0);
    }

    /**
     * Get days in transit.
     */
    public function getDaysInTransitAttribute(): int
    {
        if (!$this->isInTransit() && !$this->isInitiated()) {
            return 0;
        }

        $startDate = $this->initiated_at ?? $this->transfer_date;
        return now()->diffInDays($startDate);
    }

    /**
     * Check if clearance is overdue.
     */
    public function getIsOverdueAttribute(): bool
    {
        if (!$this->expected_clearance_date) {
            return false;
        }

        if ($this->isCleared() || $this->isCancelled() || $this->isFailed()) {
            return false;
        }

        return $this->expected_clearance_date->isPast();
    }

    // ==========================================
    // HELPERS
    // ==========================================

    /**
     * Get transfer method label.
     */
    public function getTransferMethodLabelAttribute(): string
    {
        return match ($this->transfer_method) {
            self::METHOD_INTERNAL => 'Internal Transfer',
            self::METHOD_WIRE => 'Wire Transfer',
            self::METHOD_EFT => 'Electronic Funds Transfer',
            self::METHOD_CHEQUE => 'Cheque',
            self::METHOD_RTGS => 'RTGS',
            self::METHOD_NEFT => 'NEFT',
            default => ucfirst($this->transfer_method ?? 'Unknown'),
        };
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING_APPROVAL => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_INITIATED => 'Initiated',
            self::STATUS_IN_TRANSIT => 'In Transit',
            self::STATUS_CLEARED => 'Cleared',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($this->status ?? 'Unknown'),
        };
    }

    /**
     * Get status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'secondary',
            self::STATUS_PENDING_APPROVAL => 'warning',
            self::STATUS_APPROVED => 'info',
            self::STATUS_INITIATED => 'primary',
            self::STATUS_IN_TRANSIT => 'primary',
            self::STATUS_CLEARED => 'success',
            self::STATUS_FAILED => 'danger',
            self::STATUS_CANCELLED => 'dark',
            default => 'secondary',
        };
    }

    /**
     * Get transfer summary.
     */
    public function getSummaryAttribute(): string
    {
        return sprintf(
            '%s → %s: %s',
            $this->fromBank?->name ?? 'Unknown',
            $this->toBank?->name ?? 'Unknown',
            number_format($this->amount, 2)
        );
    }

    /**
     * Boot method for model events.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transfer) {
            if (!$transfer->transfer_number) {
                $transfer->transfer_number = self::generateNumber();
            }

            // Auto-set is_same_bank
            if ($transfer->from_bank_id && $transfer->to_bank_id) {
                $fromBank = Bank::find($transfer->from_bank_id);
                $toBank = Bank::find($transfer->to_bank_id);

                if ($fromBank && $toBank) {
                    $transfer->is_same_bank = $fromBank->bank_code === $toBank->bank_code;
                }
            }
        });
    }
}
