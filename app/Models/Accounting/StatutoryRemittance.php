<?php

namespace App\Models\Accounting;

use App\Models\Bank;
use App\Models\HR\PayHead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Statutory Remittance Model
 *
 * Reference: Accounting Gap Analysis - Statutory Remittance Module
 *
 * Tracks payments made to statutory/regulatory bodies for payroll deductions:
 * - PAYE (Pay As You Earn) → Tax Authority
 * - Pension Contribution → PFA (Pension Fund Administrator)
 * - NHF (National Housing Fund) → FMBN
 * - NSITF (Employees' Compensation) → NSITF
 * - ITF (Industrial Training Fund) → ITF
 * - Staff Loans → Various
 *
 * JOURNAL ENTRY (on payment):
 * DEBIT:  Liability Account (2xxx) from PayHead - clearing the liability
 * CREDIT: Bank/Cash Account - the payment
 */
class StatutoryRemittance extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $table = 'statutory_remittances';

    // Status constants
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PAID = 'paid';
    public const STATUS_VOIDED = 'voided';

    // Payment method constants
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_CHEQUE = 'cheque';
    public const METHOD_CASH = 'cash';

    protected $fillable = [
        'pay_head_id',          // FK to pay_heads - the deduction being remitted
        'reference_number',     // Internal reference
        'period_from',          // Period start (e.g., payroll month)
        'period_to',            // Period end
        'due_date',             // When remittance is due
        'remittance_date',      // Actual remittance date
        'amount',               // Total amount to remit
        'payee_name',           // Statutory body name (e.g., FIRS, PFA name)
        'payee_account_number', // Their bank account (for reference)
        'payee_bank_name',      // Their bank name
        'payment_method',       // bank_transfer, cheque, cash
        'bank_id',              // Our bank account used for payment
        'cheque_number',        // If payment by cheque
        'transaction_reference',// Bank transaction reference
        'notes',                // Additional notes
        'status',
        'journal_entry_id',     // FK to journal_entries (created on payment)
        'prepared_by',          // User who prepared
        'approved_by',          // User who approved
        'approved_at',
        'paid_by',              // User who marked as paid
        'paid_at',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'due_date' => 'date',
        'remittance_date' => 'date',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * Pay head (deduction type) being remitted.
     */
    public function payHead()
    {
        return $this->belongsTo(PayHead::class);
    }

    /**
     * Bank account used for payment.
     */
    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * Journal entry created when payment is made.
     */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * User who prepared the remittance.
     */
    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /**
     * User who approved the remittance.
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * User who marked as paid.
     */
    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * User who voided the remittance.
     */
    public function voidedBy()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    // ==========================================
    // ACCESSORS
    // ==========================================

    /**
     * Get formatted period string.
     */
    public function getPeriodStringAttribute(): string
    {
        if ($this->period_from && $this->period_to) {
            return $this->period_from->format('M Y') . ' - ' . $this->period_to->format('M Y');
        }
        return $this->period_from?->format('M Y') ?? 'N/A';
    }

    /**
     * Get status badge class.
     */
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'secondary',
            self::STATUS_PENDING => 'warning',
            self::STATUS_APPROVED => 'info',
            self::STATUS_PAID => 'success',
            self::STATUS_VOIDED => 'danger',
            default => 'secondary'
        };
    }

    /**
     * Get liability account from the pay head.
     */
    public function getLiabilityAccountAttribute()
    {
        return $this->payHead?->liabilityAccount;
    }

    // ==========================================
    // QUERY SCOPES
    // ==========================================

    /**
     * Filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Filter by pay head.
     */
    public function scopeForPayHead($query, int $payHeadId)
    {
        return $query->where('pay_head_id', $payHeadId);
    }

    /**
     * Filter by period.
     */
    public function scopeForPeriod($query, $from, $to)
    {
        return $query->where('period_from', '>=', $from)
            ->where('period_to', '<=', $to);
    }

    /**
     * Get pending remittances (draft or pending approval).
     */
    public function scopePendingRemittances($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_PENDING]);
    }

    /**
     * Get overdue remittances.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->whereNotIn('status', [self::STATUS_PAID, self::STATUS_VOIDED]);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Check if remittance can be edited.
     */
    public function canEdit(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING]);
    }

    /**
     * Check if remittance can be approved.
     */
    public function canApprove(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if remittance can be marked as paid.
     */
    public function canPay(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if remittance can be voided.
     */
    public function canVoid(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING, self::STATUS_APPROVED]);
    }

    /**
     * Generate next reference number.
     */
    public static function generateReferenceNumber(): string
    {
        $prefix = 'SR';
        $year = date('Y');
        $month = date('m');

        $lastRemittance = self::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        $nextNumber = $lastRemittance ? ((int) substr($lastRemittance->reference_number, -4)) + 1 : 1;

        return sprintf('%s%s%s%04d', $prefix, $year, $month, $nextNumber);
    }

    /**
     * Get statutory body types (for dropdown).
     */
    public static function getStatutoryTypes(): array
    {
        return [
            'paye' => 'PAYE (Tax)',
            'pension' => 'Pension Contribution',
            'nhf' => 'National Housing Fund (NHF)',
            'nsitf' => 'NSITF (Employee Compensation)',
            'itf' => 'Industrial Training Fund (ITF)',
            'union' => 'Union Dues',
            'cooperative' => 'Cooperative Deduction',
            'loan' => 'Staff Loan Remittance',
            'other' => 'Other Statutory',
        ];
    }

    /**
     * Get payment methods.
     */
    public static function getPaymentMethods(): array
    {
        return [
            self::METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::METHOD_CHEQUE => 'Cheque',
            self::METHOD_CASH => 'Cash',
        ];
    }
}
