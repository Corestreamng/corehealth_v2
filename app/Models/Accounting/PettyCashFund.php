<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Petty Cash Fund Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.7
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 1.4
 *
 * Represents a petty cash fund with assigned custodian,
 * limits, and link to GL account.
 */
class PettyCashFund extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'petty_cash_funds';

    // Status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'fund_name',
        'fund_code',
        'account_id',
        'custodian_user_id',
        'department_id',
        'fund_limit',
        'transaction_limit',
        'current_balance',
        'requires_approval',
        'approval_threshold',
        'status',
        'notes',
    ];

    protected $casts = [
        'fund_limit' => 'decimal:2',
        'transaction_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'approval_threshold' => 'decimal:2',
        'requires_approval' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * GL Account for this fund.
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Custodian responsible for this fund.
     */
    public function custodian()
    {
        return $this->belongsTo(User::class, 'custodian_user_id');
    }

    /**
     * Department this fund belongs to.
     */
    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class);
    }

    /**
     * All transactions for this fund.
     */
    public function transactions()
    {
        return $this->hasMany(PettyCashTransaction::class, 'fund_id');
    }

    /**
     * Reconciliations for this fund.
     */
    public function reconciliations()
    {
        return $this->hasMany(PettyCashReconciliation::class, 'fund_id');
    }

    // ==========================================
    // BALANCE CALCULATIONS (JE CENTRIC)
    // ==========================================

    /**
     * Get balance from journal entries.
     *
     * CRITICAL: This is the source of truth.
     */
    public function getBalanceFromJournalEntries(?string $asOfDate = null): float
    {
        if (!$this->account_id) {
            return 0.0;
        }

        return $this->account->getBalance(null, $asOfDate);
    }

    /**
     * Get balance from pending transactions (not yet in JE).
     */
    public function getPendingDisbursements(): float
    {
        return (float) $this->transactions()
            ->where('transaction_type', PettyCashTransaction::TYPE_DISBURSEMENT)
            ->where('status', PettyCashTransaction::STATUS_APPROVED)
            ->sum('amount');
    }

    /**
     * Get effective balance (JE balance - pending disbursements).
     */
    public function getEffectiveBalance(): float
    {
        return $this->getBalanceFromJournalEntries() - $this->getPendingDisbursements();
    }

    // ==========================================
    // VALIDATION HELPERS
    // ==========================================

    /**
     * Check if amount can be disbursed.
     */
    public function canDisburse(float $amount): bool
    {
        if ($amount > $this->transaction_limit) {
            return false;
        }

        return $this->getEffectiveBalance() >= $amount;
    }

    /**
     * Check if fund needs replenishment.
     */
    public function needsReplenishment(): bool
    {
        $threshold = $this->fund_limit * 0.25; // 25% of limit
        return $this->getBalanceFromJournalEntries() < $threshold;
    }

    /**
     * Get replenishment amount to restore to fund limit.
     */
    public function getReplenishmentAmount(): float
    {
        return max(0, $this->fund_limit - $this->getBalanceFromJournalEntries());
    }

    // ==========================================
    // VOUCHER NUMBER GENERATION
    // ==========================================

    /**
     * Generate next voucher number for this fund.
     */
    public function generateVoucherNumber(): string
    {
        $prefix = 'PCV-' . $this->fund_code . '-';
        $year = now()->format('Y');

        $lastVoucher = $this->transactions()
            ->where('voucher_number', 'like', $prefix . $year . '%')
            ->orderBy('voucher_number', 'desc')
            ->first();

        if ($lastVoucher) {
            $lastNumber = (int) substr($lastVoucher->voucher_number, -5);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . $year . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForCustodian($query, int $userId)
    {
        return $query->where('custodian_user_id', $userId);
    }

    public function scopeForDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeNeedingReplenishment($query)
    {
        // This would need a subquery for JE-based balance
        // Simplified: flag funds where current_balance < 25% of limit
        return $query->whereColumn('current_balance', '<', \DB::raw('fund_limit * 0.25'));
    }

    // ==========================================
    // HELPERS
    // ==========================================

    /**
     * Get status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_SUSPENDED => 'warning',
            self::STATUS_CLOSED => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get utilization percentage.
     */
    public function getUtilizationPercentageAttribute(): float
    {
        if ($this->fund_limit <= 0) {
            return 0;
        }

        $disbursed = $this->fund_limit - $this->getBalanceFromJournalEntries();
        return round(($disbursed / $this->fund_limit) * 100, 2);
    }
}
