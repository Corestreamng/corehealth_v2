<?php

namespace App\Models\Accounting;

use App\Models\User;
use App\Models\patient;
use App\Models\Bank;
use App\Models\Admission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Patient Deposit Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.9
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 3.2
 *
 * Tracks patient advance payments/deposits.
 */
class PatientDeposit extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'patient_deposits';

    // Deposit Types
    public const TYPE_ADMISSION = 'admission';
    public const TYPE_PROCEDURE = 'procedure';
    public const TYPE_SURGERY = 'surgery';
    public const TYPE_INVESTIGATION = 'investigation';
    public const TYPE_GENERAL = 'general';
    public const TYPE_OTHER = 'other';

    // Payment Methods
    public const METHOD_CASH = 'cash';
    public const METHOD_POS = 'pos';
    public const METHOD_TRANSFER = 'transfer';
    public const METHOD_CHEQUE = 'cheque';

    // Status
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FULLY_APPLIED = 'fully_applied';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'patient_id',
        'admission_id',
        'encounter_id',
        'deposit_number',
        'deposit_date',
        'amount',
        'utilized_amount',
        'refunded_amount',
        'journal_entry_id',
        'source_payment_id', // Links to Payment record when created via BillingWorkbench
        'deposit_type',
        'payment_method',
        'bank_id',
        'payment_reference',
        'receipt_number',
        'received_by',
        'status',
        'refund_journal_entry_id',
        'refund_reason',
        'refunded_by',
        'refunded_at',
        'notes',
    ];

    protected $casts = [
        'deposit_date' => 'date',
        'amount' => 'decimal:2',
        'utilized_amount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
        'refunded_at' => 'datetime',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function patient()
    {
        return $this->belongsTo(patient::class);
    }

    public function admission()
    {
        return $this->belongsTo(\App\Models\AdmissionRequest::class, 'admission_id');
    }

    public function encounter()
    {
        return $this->belongsTo(\App\Models\Encounter::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function refundJournalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'refund_journal_entry_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function refunder()
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    /**
     * Source payment record (when created via BillingWorkbench legacy flow).
     */
    public function sourcePayment()
    {
        return $this->belongsTo(\App\Models\Payment::class, 'source_payment_id');
    }

    public function applications()
    {
        return $this->hasMany(PatientDepositApplication::class, 'deposit_id');
    }

    // ==========================================
    // BALANCE CALCULATIONS
    // ==========================================

    /**
     * Get available balance.
     */
    public function getBalanceAttribute(): float
    {
        return round($this->amount - $this->utilized_amount - $this->refunded_amount, 2);
    }

    /**
     * Check if deposit has available balance.
     */
    public function hasBalance(): bool
    {
        return $this->balance > 0;
    }

    /**
     * Check if amount can be applied.
     */
    public function canApply(float $amount): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->balance >= $amount;
    }

    /**
     * Apply amount from deposit.
     */
    public function applyAmount(float $amount): void
    {
        if (!$this->canApply($amount)) {
            throw new \InvalidArgumentException('Insufficient deposit balance');
        }

        $this->utilized_amount += $amount;

        if ($this->balance <= 0.01) {
            $this->status = self::STATUS_FULLY_APPLIED;
        }

        $this->save();
    }

    /**
     * Refund remaining balance.
     */
    public function refund(float $amount, string $reason, int $refundedBy): void
    {
        if ($amount > $this->balance) {
            throw new \InvalidArgumentException('Refund amount exceeds balance');
        }

        $this->refunded_amount += $amount;
        $this->refund_reason = $reason;
        $this->refunded_by = $refundedBy;
        $this->refunded_at = now();

        if ($this->balance <= 0.01) {
            $this->status = self::STATUS_REFUNDED;
        }

        $this->save();
    }

    // ==========================================
    // STATUS CHECKS
    // ==========================================

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isFullyApplied(): bool
    {
        return $this->status === self::STATUS_FULLY_APPLIED;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    public function canBeRefunded(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->balance > 0;
    }

    // ==========================================
    // NUMBER GENERATION
    // ==========================================

    /**
     * Generate deposit number.
     */
    public static function generateNumber(): string
    {
        $prefix = 'DEP-';
        $year = now()->format('Y');
        $month = now()->format('m');

        $lastDeposit = self::where('deposit_number', 'like', $prefix . $year . $month . '%')
            ->orderBy('deposit_number', 'desc')
            ->first();

        if ($lastDeposit) {
            $lastNumber = (int) substr($lastDeposit->deposit_number, -5);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . $year . $month . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeWithBalance($query)
    {
        return $query->whereRaw('(amount - utilized_amount - refunded_amount) > 0');
    }

    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeForAdmission($query, int $admissionId)
    {
        return $query->where('admission_id', $admissionId);
    }

    public function scopeForPeriod($query, string $fromDate, string $toDate)
    {
        return $query->whereBetween('deposit_date', [$fromDate, $toDate]);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    /**
     * Get deposit type label.
     */
    public function getDepositTypeLabelAttribute(): string
    {
        return match ($this->deposit_type) {
            self::TYPE_ADMISSION => 'Admission Deposit',
            self::TYPE_PROCEDURE => 'Procedure Deposit',
            self::TYPE_SURGERY => 'Surgery Deposit',
            self::TYPE_INVESTIGATION => 'Investigation Deposit',
            self::TYPE_GENERAL => 'General Advance',
            self::TYPE_OTHER => 'Other',
            default => ucfirst($this->deposit_type ?? 'Unknown'),
        };
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_FULLY_APPLIED => 'Fully Applied',
            self::STATUS_REFUNDED => 'Refunded',
            self::STATUS_EXPIRED => 'Expired',
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
            self::STATUS_ACTIVE => 'success',
            self::STATUS_FULLY_APPLIED => 'info',
            self::STATUS_REFUNDED => 'warning',
            self::STATUS_EXPIRED => 'secondary',
            self::STATUS_CANCELLED => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get utilization percentage.
     */
    public function getUtilizationPercentageAttribute(): float
    {
        if ($this->amount <= 0) {
            return 0;
        }

        return round(($this->utilized_amount / $this->amount) * 100, 2);
    }
}
