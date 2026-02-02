<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Bank Reconciliation Item Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 2
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 2
 *
 * Individual transactions for matching during reconciliation.
 */
class BankReconciliationItem extends Model
{
    use HasFactory;

    protected $table = 'bank_reconciliation_items';

    // Source constants
    public const SOURCE_GL = 'gl';
    public const SOURCE_STATEMENT = 'statement';
    public const SOURCE_ADJUSTMENT = 'adjustment';

    // Item type constants
    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_CHECK = 'check';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_BANK_CHARGE = 'bank_charge';
    public const TYPE_INTEREST = 'interest';
    public const TYPE_OTHER_CREDIT = 'other_credit';
    public const TYPE_OTHER_DEBIT = 'other_debit';
    public const TYPE_ADJUSTMENT = 'adjustment';

    // Amount type constants
    public const AMOUNT_DEBIT = 'debit';
    public const AMOUNT_CREDIT = 'credit';

    protected $fillable = [
        'reconciliation_id',
        'journal_entry_line_id',
        'source',
        'item_type',
        'transaction_date',
        'reference',
        'description',
        'amount',
        'amount_type',
        'is_matched',
        'matched_with_id',
        'matched_date',
        'is_reconciled',
        'cleared_date',
        'is_outstanding',
        'expected_clear_date',
        'adjustment_entry_id',
        'adjustment_reason',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'is_matched' => 'boolean',
        'matched_date' => 'date',
        'is_reconciled' => 'boolean',
        'cleared_date' => 'date',
        'is_outstanding' => 'boolean',
        'expected_clear_date' => 'date',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function reconciliation()
    {
        return $this->belongsTo(BankReconciliation::class, 'reconciliation_id');
    }

    public function journalEntryLine()
    {
        return $this->belongsTo(JournalEntryLine::class);
    }

    public function matchedWith()
    {
        return $this->belongsTo(self::class, 'matched_with_id');
    }

    public function adjustmentEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'adjustment_entry_id');
    }

    // ==========================================
    // STATUS METHODS
    // ==========================================

    public function isFromGL(): bool
    {
        return $this->source === self::SOURCE_GL;
    }

    public function isFromStatement(): bool
    {
        return $this->source === self::SOURCE_STATEMENT;
    }

    public function isAdjustment(): bool
    {
        return $this->source === self::SOURCE_ADJUSTMENT;
    }

    public function isDeposit(): bool
    {
        return in_array($this->item_type, [
            self::TYPE_DEPOSIT,
            self::TYPE_INTEREST,
            self::TYPE_OTHER_CREDIT,
        ]);
    }

    public function isWithdrawal(): bool
    {
        return in_array($this->item_type, [
            self::TYPE_CHECK,
            self::TYPE_TRANSFER,
            self::TYPE_BANK_CHARGE,
            self::TYPE_OTHER_DEBIT,
        ]);
    }

    public function canBeMatched(): bool
    {
        return !$this->is_matched && !$this->is_reconciled;
    }

    // ==========================================
    // MATCHING METHODS
    // ==========================================

    /**
     * Match this item with another.
     */
    public function matchWith(self $other): bool
    {
        if (!$this->canBeMatched() || !$other->canBeMatched()) {
            return false;
        }

        // Validate matching criteria
        if ($this->source === $other->source) {
            return false; // Can't match items from same source
        }

        if (abs($this->amount - $other->amount) > 0.01) {
            return false; // Amounts must match
        }

        // Perform the match
        $matchDate = now()->toDateString();

        $this->update([
            'is_matched' => true,
            'matched_with_id' => $other->id,
            'matched_date' => $matchDate,
        ]);

        $other->update([
            'is_matched' => true,
            'matched_with_id' => $this->id,
            'matched_date' => $matchDate,
        ]);

        return true;
    }

    /**
     * Unmatch this item.
     */
    public function unmatch(): bool
    {
        if (!$this->is_matched) {
            return false;
        }

        $matchedItem = $this->matchedWith;

        $this->update([
            'is_matched' => false,
            'matched_with_id' => null,
            'matched_date' => null,
        ]);

        if ($matchedItem) {
            $matchedItem->update([
                'is_matched' => false,
                'matched_with_id' => null,
                'matched_date' => null,
            ]);
        }

        return true;
    }

    /**
     * Mark as reconciled.
     */
    public function markReconciled(?string $clearedDate = null): void
    {
        $this->update([
            'is_reconciled' => true,
            'is_outstanding' => false,
            'cleared_date' => $clearedDate ?? now()->toDateString(),
        ]);
    }

    /**
     * Mark as outstanding.
     */
    public function markOutstanding(?string $expectedClearDate = null): void
    {
        $this->update([
            'is_outstanding' => true,
            'is_reconciled' => false,
            'expected_clear_date' => $expectedClearDate,
        ]);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeFromGL($query)
    {
        return $query->where('source', self::SOURCE_GL);
    }

    public function scopeFromStatement($query)
    {
        return $query->where('source', self::SOURCE_STATEMENT);
    }

    public function scopeMatched($query)
    {
        return $query->where('is_matched', true);
    }

    public function scopeUnmatched($query)
    {
        return $query->where('is_matched', false);
    }

    public function scopeReconciled($query)
    {
        return $query->where('is_reconciled', true);
    }

    public function scopeOutstanding($query)
    {
        return $query->where('is_outstanding', true);
    }

    public function scopeDeposits($query)
    {
        return $query->whereIn('item_type', [
            self::TYPE_DEPOSIT,
            self::TYPE_INTEREST,
            self::TYPE_OTHER_CREDIT,
        ]);
    }

    public function scopeWithdrawals($query)
    {
        return $query->whereIn('item_type', [
            self::TYPE_CHECK,
            self::TYPE_TRANSFER,
            self::TYPE_BANK_CHARGE,
            self::TYPE_OTHER_DEBIT,
        ]);
    }

    public function scopeForDate($query, string $date)
    {
        return $query->where('transaction_date', $date);
    }

    public function scopeForPeriod($query, string $fromDate, string $toDate)
    {
        return $query->whereBetween('transaction_date', [$fromDate, $toDate]);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    /**
     * Get item type label.
     */
    public function getItemTypeLabelAttribute(): string
    {
        return match ($this->item_type) {
            self::TYPE_DEPOSIT => 'Deposit',
            self::TYPE_CHECK => 'Check/Withdrawal',
            self::TYPE_TRANSFER => 'Transfer',
            self::TYPE_BANK_CHARGE => 'Bank Charge',
            self::TYPE_INTEREST => 'Interest',
            self::TYPE_OTHER_CREDIT => 'Other Credit',
            self::TYPE_OTHER_DEBIT => 'Other Debit',
            self::TYPE_ADJUSTMENT => 'Adjustment',
            default => ucfirst($this->item_type ?? 'Unknown'),
        };
    }

    /**
     * Get source label.
     */
    public function getSourceLabelAttribute(): string
    {
        return match ($this->source) {
            self::SOURCE_GL => 'General Ledger',
            self::SOURCE_STATEMENT => 'Bank Statement',
            self::SOURCE_ADJUSTMENT => 'Adjustment',
            default => ucfirst($this->source ?? 'Unknown'),
        };
    }

    /**
     * Get signed amount (positive for debits, negative for credits to bank).
     */
    public function getSignedAmountAttribute(): float
    {
        // For bank reconciliation:
        // Deposits (debits to bank account) are positive
        // Withdrawals (credits to bank account) are negative
        return $this->amount_type === self::AMOUNT_DEBIT
            ? $this->amount
            : -$this->amount;
    }

    /**
     * Get display amount with sign.
     */
    public function getDisplayAmountAttribute(): string
    {
        $sign = $this->amount_type === self::AMOUNT_CREDIT ? '-' : '+';
        return $sign . number_format($this->amount, 2);
    }

    /**
     * Get status badge.
     */
    public function getStatusAttribute(): string
    {
        if ($this->is_reconciled) {
            return 'reconciled';
        }
        if ($this->is_matched) {
            return 'matched';
        }
        if ($this->is_outstanding) {
            return 'outstanding';
        }
        return 'unmatched';
    }

    /**
     * Get status color.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'reconciled' => 'success',
            'matched' => 'info',
            'outstanding' => 'warning',
            'unmatched' => 'secondary',
            default => 'secondary',
        };
    }
}
