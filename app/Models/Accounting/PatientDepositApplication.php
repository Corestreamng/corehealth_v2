<?php

namespace App\Models\Accounting;

use App\Models\User;
use App\Models\Billing\Bill;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Patient Deposit Application Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.9
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 3.2
 *
 * Tracks how patient deposits are applied to bills/payments.
 */
class PatientDepositApplication extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'patient_deposit_applications';

    // Application Types
    public const TYPE_BILL_PAYMENT = 'bill_payment';
    public const TYPE_REFUND = 'refund';

    // Status
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'deposit_id',
        'payment_id',
        'bill_id',
        'journal_entry_id',
        'application_number',
        'application_type',
        'amount',
        'application_date',
        'applied_by',
        'status',
        'reversal_reason',
        'reversed_by',
        'reversed_at',
        'notes',
    ];

    protected $casts = [
        'application_date' => 'date',
        'amount' => 'decimal:2',
        'reversed_at' => 'datetime',
    ];

    // ==========================================
    // NUMBER GENERATION
    // ==========================================

    /**
     * Generate unique application number.
     */
    public static function generateNumber(): string
    {
        $prefix = 'DPA';
        $date = now()->format('ymd');
        $lastApplication = static::whereDate('created_at', today())
            ->orderByDesc('id')
            ->first();

        if ($lastApplication && preg_match('/(\d{4})$/', $lastApplication->application_number, $matches)) {
            $sequence = (int) $matches[1] + 1;
        } else {
            $sequence = 1;
        }

        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function deposit()
    {
        return $this->belongsTo(PatientDeposit::class, 'deposit_id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function bill()
    {
        return $this->belongsTo(Bill::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function appliedByUser()
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function reversedByUser()
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    // ==========================================
    // STATUS METHODS
    // ==========================================

    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    public function canBeReversed(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    /**
     * Reverse this application.
     */
    public function reverse(string $reason, int $reversedBy): void
    {
        if (!$this->canBeReversed()) {
            throw new \InvalidArgumentException('Application cannot be reversed');
        }

        $this->status = self::STATUS_REVERSED;
        $this->reversal_reason = $reason;
        $this->reversed_by = $reversedBy;
        $this->reversed_at = now();
        $this->save();

        // Restore deposit balance
        $deposit = $this->deposit;
        $deposit->utilized_amount -= $this->amount;
        if ($deposit->status === PatientDeposit::STATUS_FULLY_APPLIED) {
            $deposit->status = PatientDeposit::STATUS_ACTIVE;
        }
        $deposit->save();
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_APPLIED);
    }

    public function scopeForDeposit($query, int $depositId)
    {
        return $query->where('deposit_id', $depositId);
    }

    public function scopeForBill($query, int $billId)
    {
        return $query->where('bill_id', $billId);
    }

    public function scopeForPeriod($query, string $fromDate, string $toDate)
    {
        return $query->whereBetween('application_date', [$fromDate, $toDate]);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    /**
     * Get application type label.
     */
    public function getApplicationTypeLabelAttribute(): string
    {
        return match ($this->application_type) {
            self::TYPE_BILL_PAYMENT => 'Bill Payment',
            self::TYPE_REFUND => 'Refund',
            default => ucfirst($this->application_type ?? 'Unknown'),
        };
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_APPLIED => 'Applied',
            self::STATUS_REVERSED => 'Reversed',
            default => ucfirst($this->status ?? 'Unknown'),
        };
    }

    /**
     * Get status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_APPLIED => 'success',
            self::STATUS_REVERSED => 'warning',
            default => 'secondary',
        };
    }
}
