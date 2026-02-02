<?php

namespace App\Models\Accounting;

use App\Models\Bank;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Bank Reconciliation Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 2
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 2
 *
 * Header record for bank reconciliation tracking statement vs GL balances.
 */
class BankReconciliation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bank_reconciliations';

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_FINALIZED = 'finalized';

    protected $fillable = [
        'bank_id',
        'account_id',
        'fiscal_period_id',
        'reconciliation_number',
        'statement_date',
        'statement_period_from',
        'statement_period_to',
        'statement_opening_balance',
        'statement_closing_balance',
        'gl_opening_balance',
        'gl_closing_balance',
        'outstanding_deposits',
        'outstanding_checks',
        'deposits_in_transit',
        'unrecorded_charges',
        'unrecorded_credits',
        'bank_errors',
        'book_errors',
        'variance',
        'status',
        'adjustment_entry_ids',
        'notes',
        'prepared_by',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'finalized_at',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'statement_period_from' => 'date',
        'statement_period_to' => 'date',
        'statement_opening_balance' => 'decimal:2',
        'statement_closing_balance' => 'decimal:2',
        'gl_opening_balance' => 'decimal:2',
        'gl_closing_balance' => 'decimal:2',
        'outstanding_deposits' => 'decimal:2',
        'outstanding_checks' => 'decimal:2',
        'deposits_in_transit' => 'decimal:2',
        'unrecorded_charges' => 'decimal:2',
        'unrecorded_credits' => 'decimal:2',
        'bank_errors' => 'decimal:2',
        'book_errors' => 'decimal:2',
        'variance' => 'decimal:2',
        'adjustment_entry_ids' => 'array',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function fiscalPeriod()
    {
        return $this->belongsTo(AccountingPeriod::class, 'fiscal_period_id');
    }

    public function items()
    {
        return $this->hasMany(BankReconciliationItem::class, 'reconciliation_id');
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ==========================================
    // STATUS METHODS
    // ==========================================

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isPendingReview(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isFinalized(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }

    public function isReconciled(): bool
    {
        return abs($this->variance) < 0.01;
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS]);
    }

    // ==========================================
    // CALCULATION METHODS
    // ==========================================

    /**
     * Calculate and update variance.
     */
    public function calculateVariance(): void
    {
        // Adjusted bank balance = Statement + Outstanding Deposits - Outstanding Checks
        $adjustedBankBalance = $this->statement_closing_balance
            + $this->outstanding_deposits
            - $this->outstanding_checks
            - $this->bank_errors;

        // Adjusted book balance = GL + Unrecorded Credits - Unrecorded Charges
        $adjustedBookBalance = $this->gl_closing_balance
            + $this->unrecorded_credits
            - $this->unrecorded_charges
            - $this->book_errors;

        $this->variance = round($adjustedBankBalance - $adjustedBookBalance, 2);
    }

    /**
     * Get adjusted bank balance.
     */
    public function getAdjustedBankBalanceAttribute(): float
    {
        return round(
            $this->statement_closing_balance
            + $this->outstanding_deposits
            - $this->outstanding_checks
            - ($this->bank_errors ?? 0),
            2
        );
    }

    /**
     * Get adjusted book balance.
     */
    public function getAdjustedBookBalanceAttribute(): float
    {
        return round(
            $this->gl_closing_balance
            + ($this->unrecorded_credits ?? 0)
            - ($this->unrecorded_charges ?? 0)
            - ($this->book_errors ?? 0),
            2
        );
    }

    // ==========================================
    // ITEM AGGREGATION
    // ==========================================

    /**
     * Get outstanding deposit items.
     */
    public function getOutstandingDeposits()
    {
        return $this->items()
            ->where('source', 'gl')
            ->where('item_type', 'deposit')
            ->where('is_outstanding', true)
            ->get();
    }

    /**
     * Get outstanding check items.
     */
    public function getOutstandingChecks()
    {
        return $this->items()
            ->where('source', 'gl')
            ->where('item_type', 'check')
            ->where('is_outstanding', true)
            ->get();
    }

    /**
     * Get unmatched GL items.
     */
    public function getUnmatchedGlItems()
    {
        return $this->items()
            ->where('source', 'gl')
            ->where('is_matched', false)
            ->get();
    }

    /**
     * Get unmatched statement items.
     */
    public function getUnmatchedStatementItems()
    {
        return $this->items()
            ->where('source', 'statement')
            ->where('is_matched', false)
            ->get();
    }

    // ==========================================
    // NUMBER GENERATION
    // ==========================================

    /**
     * Generate reconciliation number.
     */
    public static function generateNumber(int $bankId): string
    {
        $prefix = 'REC-';
        $year = now()->format('Y');
        $month = now()->format('m');

        $lastRec = self::where('bank_id', $bankId)
            ->where('reconciliation_number', 'like', $prefix . $year . $month . '%')
            ->orderBy('reconciliation_number', 'desc')
            ->first();

        if ($lastRec) {
            $lastNumber = (int) substr($lastRec->reconciliation_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . $year . $month . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeForBank($query, int $bankId)
    {
        return $query->where('bank_id', $bankId);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeInProgress($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS]);
    }

    public function scopeFinalized($query)
    {
        return $query->where('status', self::STATUS_FINALIZED);
    }

    public function scopeWithVariance($query)
    {
        return $query->where('variance', '!=', 0);
    }

    public function scopeForPeriod($query, string $fromDate, string $toDate)
    {
        return $query->whereBetween('statement_date', [$fromDate, $toDate]);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_PENDING_REVIEW => 'Pending Review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_FINALIZED => 'Finalized',
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
            self::STATUS_IN_PROGRESS => 'primary',
            self::STATUS_PENDING_REVIEW => 'warning',
            self::STATUS_APPROVED => 'info',
            self::STATUS_FINALIZED => 'success',
            default => 'secondary',
        };
    }

    /**
     * Get reconciliation summary.
     */
    public function getSummaryAttribute(): string
    {
        return sprintf(
            '%s - %s (%s)',
            $this->bank?->name ?? 'Unknown Bank',
            $this->statement_date?->format('M Y') ?? 'N/A',
            $this->status_label
        );
    }
}
