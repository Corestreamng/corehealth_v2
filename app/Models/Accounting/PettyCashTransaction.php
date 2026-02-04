<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Petty Cash Transaction Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.7
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 1.4
 *
 * Represents individual petty cash disbursements, replenishments,
 * and adjustments. Links to journal_entries for accounting.
 */
class PettyCashTransaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'petty_cash_transactions';

    // Transaction Types
    public const TYPE_DISBURSEMENT = 'disbursement';
    public const TYPE_REPLENISHMENT = 'replenishment';
    public const TYPE_ADJUSTMENT = 'adjustment';

    // Status
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DISBURSED = 'disbursed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_VOIDED = 'voided';

    // Payee Types
    public const PAYEE_STAFF = 'staff';
    public const PAYEE_VENDOR = 'vendor';
    public const PAYEE_OTHER = 'other';

    // Payment Methods (for replenishment)
    public const METHOD_CASH = 'cash';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    protected $fillable = [
        'fund_id',
        'journal_entry_id',
        'transaction_type',
        'transaction_date',
        'voucher_number',
        'description',
        'amount',
        'expense_category',
        'expense_account_id',
        'requested_by',
        'approved_by',
        'approved_at',
        'receipt_number',
        'receipt_attached',
        'receipt_path',
        'payee_name',
        'payee_type',
        // Payment source (for replenishment)
        'payment_method',  // 'cash' or 'bank_transfer'
        'bank_id',         // FK to banks table
        'status',
        'rejection_reason',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'receipt_attached' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Fund this transaction belongs to.
     */
    public function fund()
    {
        return $this->belongsTo(PettyCashFund::class, 'fund_id');
    }

    /**
     * Journal entry for this transaction.
     */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Bank used for replenishment (if payment_method is 'bank_transfer').
     */
    public function bank()
    {
        return $this->belongsTo(\App\Models\Bank::class, 'bank_id');
    }

    /**
     * Expense account for disbursements.
     */
    public function expenseAccount()
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    /**
     * User who requested this transaction.
     */
    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Alias for requester relationship.
     */
    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * User who approved this transaction.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Alias for approver relationship.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ==========================================
    // STATUS CHECKS
    // ==========================================

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isDisbursed(): bool
    {
        return $this->status === self::STATUS_DISBURSED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOIDED;
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canBeDisbursed(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function canBeVoided(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    /**
     * Check if transaction requires approval based on fund settings.
     */
    public function requiresApproval(): bool
    {
        if (!$this->fund) {
            return true;
        }

        if (!$this->fund->requires_approval) {
            return false;
        }

        // Always require approval if above threshold
        if ($this->fund->approval_threshold > 0 && $this->amount >= $this->fund->approval_threshold) {
            return true;
        }

        return $this->fund->requires_approval;
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeDisbursed($query)
    {
        return $query->where('status', self::STATUS_DISBURSED);
    }

    public function scopeDisbursements($query)
    {
        return $query->where('transaction_type', self::TYPE_DISBURSEMENT);
    }

    public function scopeReplenishments($query)
    {
        return $query->where('transaction_type', self::TYPE_REPLENISHMENT);
    }

    public function scopeForFund($query, int $fundId)
    {
        return $query->where('fund_id', $fundId);
    }

    public function scopeForPeriod($query, string $fromDate, string $toDate)
    {
        return $query->whereBetween('transaction_date', [$fromDate, $toDate]);
    }

    public function scopeByRequester($query, int $userId)
    {
        return $query->where('requested_by', $userId);
    }

    public function scopeWithoutJournalEntry($query)
    {
        return $query->whereNull('journal_entry_id');
    }

    // ==========================================
    // HELPERS
    // ==========================================

    /**
     * Get transaction type label.
     */
    public function getTransactionTypeLabelAttribute(): string
    {
        return match ($this->transaction_type) {
            self::TYPE_DISBURSEMENT => 'Disbursement',
            self::TYPE_REPLENISHMENT => 'Replenishment',
            self::TYPE_ADJUSTMENT => 'Adjustment',
            default => ucfirst($this->transaction_type ?? 'Unknown'),
        };
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_DISBURSED => 'Disbursed',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_VOIDED => 'Voided',
            default => ucfirst($this->status ?? 'Unknown'),
        };
    }

    /**
     * Get status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED => 'info',
            self::STATUS_DISBURSED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_VOIDED => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Get effect on fund balance (positive = increase, negative = decrease).
     */
    public function getBalanceEffectAttribute(): float
    {
        return match ($this->transaction_type) {
            self::TYPE_DISBURSEMENT => -$this->amount,
            self::TYPE_REPLENISHMENT => $this->amount,
            self::TYPE_ADJUSTMENT => $this->amount, // Can be positive or negative
            default => 0,
        };
    }

    /**
     * Get payee type label.
     */
    public function getPayeeTypeLabelAttribute(): string
    {
        return match ($this->payee_type) {
            self::PAYEE_STAFF => 'Staff',
            self::PAYEE_VENDOR => 'Vendor',
            self::PAYEE_OTHER => 'Other',
            default => ucfirst($this->payee_type ?? 'Unknown'),
        };
    }
}
