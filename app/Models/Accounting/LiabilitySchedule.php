<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * Liability Schedule Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 4.1A
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 6.1
 *
 * Manages loans, mortgages, and other long-term liabilities.
 */
class LiabilitySchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'liability_schedules';

    // Status
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAID_OFF = 'paid_off';
    public const STATUS_DEFAULTED = 'defaulted';
    public const STATUS_RESTRUCTURED = 'restructured';
    public const STATUS_WRITTEN_OFF = 'written_off';

    // Payment Frequencies
    public const FREQ_WEEKLY = 'weekly';
    public const FREQ_BI_WEEKLY = 'bi_weekly';
    public const FREQ_MONTHLY = 'monthly';
    public const FREQ_QUARTERLY = 'quarterly';
    public const FREQ_SEMI_ANNUALLY = 'semi_annually';
    public const FREQ_ANNUALLY = 'annually';
    public const FREQ_AT_MATURITY = 'at_maturity';

    protected $fillable = [
        'liability_number',
        'account_id',
        'interest_expense_account_id',
        'journal_entry_id',
        'bank_account_id',
        'liability_type',
        'creditor_name',
        'creditor_contact',
        'reference_number',
        'principal_amount',
        'current_balance',
        'interest_rate',
        'interest_type',
        'start_date',
        'maturity_date',
        'term_months',
        'payment_frequency',
        'next_payment_date',
        'regular_payment_amount',
        'collateral_description',
        'collateral_value',
        'current_portion',
        'non_current_portion',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'regular_payment_amount' => 'decimal:2',
        'collateral_value' => 'decimal:2',
        'current_portion' => 'decimal:2',
        'non_current_portion' => 'decimal:2',
        'start_date' => 'date',
        'maturity_date' => 'date',
        'next_payment_date' => 'date',
        'term_months' => 'integer',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function interestExpenseAccount()
    {
        return $this->belongsTo(Account::class, 'interest_expense_account_id');
    }

    /**
     * Journal entry for initial liability booking
     */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * Bank account that received the loan proceeds
     */
    public function bankAccount()
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentSchedules()
    {
        return $this->hasMany(LiabilityPaymentSchedule::class, 'liability_id');
    }

    // ==========================================
    // CALCULATIONS
    // ==========================================

    /**
     * Calculate EMI (Equated Monthly Installment).
     */
    public function calculateEMI(): float
    {
        $principal = $this->principal_amount;
        $monthlyRate = ($this->interest_rate / 100) / 12;
        $months = $this->term_months;

        if ($monthlyRate == 0) {
            return round($principal / $months, 2);
        }

        $emi = $principal * $monthlyRate * pow(1 + $monthlyRate, $months) /
               (pow(1 + $monthlyRate, $months) - 1);

        return round($emi, 2);
    }

    /**
     * Calculate interest for current period.
     */
    public function calculateCurrentInterest(): float
    {
        $monthlyRate = ($this->interest_rate / 100) / 12;
        return round($this->current_balance * $monthlyRate, 2);
    }

    /**
     * Update current/non-current portions.
     */
    public function updatePortions(): void
    {
        $today = now();
        $oneYearFromNow = $today->copy()->addYear();

        $currentPortion = $this->paymentSchedules()
            ->where('status', 'scheduled')
            ->where('due_date', '<=', $oneYearFromNow)
            ->sum('principal_portion');

        $this->current_portion = $currentPortion;
        $this->non_current_portion = $this->current_balance - $currentPortion;
        $this->save();
    }

    /**
     * Get remaining months.
     */
    public function getRemainingMonthsAttribute(): int
    {
        return max(0, Carbon::parse($this->maturity_date)->diffInMonths(now()));
    }

    /**
     * Get total interest paid.
     */
    public function getTotalInterestPaidAttribute(): float
    {
        return $this->paymentSchedules()
            ->where('status', 'paid')
            ->sum('interest_portion');
    }

    // ==========================================
    // NUMBER GENERATION
    // ==========================================

    public static function generateNumber(): string
    {
        $prefix = 'LIA-';
        $year = now()->format('Y');

        $last = self::where('liability_number', 'like', $prefix . $year . '%')
            ->orderBy('liability_number', 'desc')
            ->first();

        $nextNumber = $last ? ((int) substr($last->liability_number, -5)) + 1 : 1;

        return $prefix . $year . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('next_payment_date', '<', now());
    }

    public function scopeMaturingWithin($query, int $days)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereBetween('maturity_date', [now(), now()->addDays($days)]);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_ACTIVE &&
               $this->next_payment_date < now();
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_PAID_OFF => 'Paid Off',
            self::STATUS_DEFAULTED => 'Defaulted',
            self::STATUS_RESTRUCTURED => 'Restructured',
            self::STATUS_WRITTEN_OFF => 'Written Off',
            default => ucfirst($this->status ?? 'Unknown'),
        };
    }
}
