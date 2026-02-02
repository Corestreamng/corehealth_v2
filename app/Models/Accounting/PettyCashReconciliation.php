<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Petty Cash Reconciliation Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.7
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 1.4
 *
 * Records periodic physical cash counts and variance tracking.
 */
class PettyCashReconciliation extends Model
{
    use HasFactory;

    protected $table = 'petty_cash_reconciliations';

    // Status constants
    public const STATUS_BALANCED = 'balanced';
    public const STATUS_SHORTAGE = 'shortage';
    public const STATUS_OVERAGE = 'overage';
    public const STATUS_PENDING = 'pending';

    protected $fillable = [
        'fund_id',
        'reconciliation_date',
        'reconciliation_number',
        'expected_balance',
        'actual_cash_count',
        'variance',
        'denomination_breakdown',
        'outstanding_vouchers',
        'outstanding_voucher_ids',
        'status',
        'adjustment_entry_id',
        'notes',
        'reconciled_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reconciliation_date' => 'date',
        'expected_balance' => 'decimal:2',
        'actual_cash_count' => 'decimal:2',
        'variance' => 'decimal:2',
        'outstanding_vouchers' => 'decimal:2',
        'denomination_breakdown' => 'array',
        'outstanding_voucher_ids' => 'array',
        'reviewed_at' => 'datetime',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Fund being reconciled.
     */
    public function fund()
    {
        return $this->belongsTo(PettyCashFund::class, 'fund_id');
    }

    /**
     * Adjustment journal entry if variance exists.
     */
    public function adjustmentEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'adjustment_entry_id');
    }

    /**
     * User who performed reconciliation.
     */
    public function reconciledBy()
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    /**
     * User who reviewed reconciliation.
     */
    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ==========================================
    // STATUS METHODS
    // ==========================================

    public function isBalanced(): bool
    {
        return $this->status === self::STATUS_BALANCED;
    }

    public function hasShortage(): bool
    {
        return $this->status === self::STATUS_SHORTAGE;
    }

    public function hasOverage(): bool
    {
        return $this->status === self::STATUS_OVERAGE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function needsAdjustment(): bool
    {
        return abs($this->variance) > 0.01 && !$this->adjustment_entry_id;
    }

    // ==========================================
    // COMPUTATION HELPERS
    // ==========================================

    /**
     * Calculate and set variance.
     */
    public function calculateVariance(): void
    {
        $this->variance = round($this->expected_balance - $this->actual_cash_count, 2);

        // Determine status based on variance
        if (abs($this->variance) < 0.01) {
            $this->status = self::STATUS_BALANCED;
        } elseif ($this->variance > 0) {
            $this->status = self::STATUS_SHORTAGE; // Expected > Actual = cash missing
        } else {
            $this->status = self::STATUS_OVERAGE; // Expected < Actual = extra cash
        }
    }

    /**
     * Get total from denomination breakdown.
     */
    public function getTotalFromDenominations(): float
    {
        if (!$this->denomination_breakdown) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($this->denomination_breakdown as $denomination => $count) {
            $total += (float)$denomination * (int)$count;
        }

        return $total;
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeForFund($query, int $fundId)
    {
        return $query->where('fund_id', $fundId);
    }

    public function scopeWithVariance($query)
    {
        return $query->where('variance', '!=', 0);
    }

    public function scopeBalanced($query)
    {
        return $query->where('status', self::STATUS_BALANCED);
    }

    public function scopePendingReview($query)
    {
        return $query->whereNull('reviewed_by');
    }

    public function scopeForPeriod($query, string $fromDate, string $toDate)
    {
        return $query->whereBetween('reconciliation_date', [$fromDate, $toDate]);
    }

    // ==========================================
    // NUMBER GENERATION
    // ==========================================

    /**
     * Generate reconciliation number.
     */
    public static function generateNumber(int $fundId): string
    {
        $prefix = 'PCR-';
        $year = now()->format('Y');
        $month = now()->format('m');

        $lastRec = self::where('fund_id', $fundId)
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
    // HELPERS
    // ==========================================

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_BALANCED => 'Balanced',
            self::STATUS_SHORTAGE => 'Shortage',
            self::STATUS_OVERAGE => 'Overage',
            self::STATUS_PENDING => 'Pending Review',
            default => ucfirst($this->status ?? 'Unknown'),
        };
    }

    /**
     * Get status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_BALANCED => 'success',
            self::STATUS_SHORTAGE => 'danger',
            self::STATUS_OVERAGE => 'warning',
            self::STATUS_PENDING => 'info',
            default => 'secondary',
        };
    }

    /**
     * Get variance as absolute value with direction.
     */
    public function getVarianceDisplayAttribute(): string
    {
        $amount = number_format(abs($this->variance), 2);

        if ($this->variance > 0) {
            return "({$amount}) Short";
        } elseif ($this->variance < 0) {
            return "{$amount} Over";
        }

        return '0.00 Balanced';
    }
}
