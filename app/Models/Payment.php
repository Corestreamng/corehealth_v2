<?php

namespace App\Models;

use App\Traits\IsAuditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Payment extends Model implements Auditable
{
    use IsAuditable;

    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'reference_no', 'total', 'payment_type', 'payment_method', 'bank_id',
        'invoice_id', 'patient_id', 'user_id', 'hmo_id', 'total_discount',
        'journal_entry_id', // For linking to accounting journal entries
        'shift_id', // For billing shift tracking
        'is_split_payment', // Whether payment was split across multiple methods
        'voided_at', 'voided_by', 'void_reason', 'void_reversal_je_id',
    ];

    protected $casts = [
        'is_split_payment' => 'boolean',
        'voided_at' => 'datetime',
    ];

    /**
     * Transient splits collection passed before saving to assist observers.
     */
    public $pending_splits = null;

    // ──── Scopes ──────────────────────────────────────────────

    /**
     * Scope to exclude voided payments (default for most queries).
     */
    public function scopeNotVoided($query)
    {
        return $query->whereNull('voided_at');
    }

    /**
     * Scope to include only voided payments.
     */
    public function scopeOnlyVoided($query)
    {
        return $query->whereNotNull('voided_at');
    }

    /**
     * Scope that includes all payments (voided + active). Use explicitly.
     */
    public function scopeIncludingVoided($query)
    {
        return $query;
    }

    // ──── Accessors ───────────────────────────────────────────

    /**
     * Check if this payment has been voided.
     */
    public function getIsVoidedAttribute(): bool
    {
        return $this->voided_at !== null;
    }

    // ──── Relationships ───────────────────────────────────────

    /**
     * Get the invoice associated with the payment
     */
    public function invoice()
    {
        return $this->hasOne(invoice::class);
    }

    /**
     * Get the split payment legs (for split payments).
     */
    public function splits()
    {
        return $this->hasMany(PaymentSplit::class, 'payment_id');
    }

    /**
     * Get the adjustment history for this payment.
     */
    public function adjustments()
    {
        return $this->hasMany(PaymentAdjustment::class, 'payment_id');
    }

    /**
     * Get the user who voided this payment.
     */
    public function voidedByUser()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * Get the reversal journal entry for a voided payment.
     */
    public function voidReversalJournalEntry()
    {
        return $this->belongsTo(Accounting\JournalEntry::class, 'void_reversal_je_id');
    }

    public function organizationBill()
    {
        return $this->hasOne(OrganizationBill::class, 'payment_id', 'id');
    }

    public function staffBill()
    {
        return $this->hasOne(StaffBill::class, 'payment_id', 'id');
    }

    public function patientAccount()
    {
        return $this->belongsTo(PatientAccount::class, 'account_id', 'id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'id');
    }

    public function staff_user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class, 'bank_id', 'id');
    }

    public function product_or_service_request()
    {
        return $this->hasMany(ProductOrServiceRequest::class, 'payment_id', 'id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(Accounting\JournalEntry::class, 'journal_entry_id');
    }

    /**
     * Get the billing shift this payment was made during.
     */
    public function shift()
    {
        return $this->belongsTo(NursingShift::class, 'shift_id');
    }

    /**
     * Get staff bills created from this payment (checkout side).
     */
    public function staffBills()
    {
        return $this->hasMany(StaffBill::class, 'payment_id');
    }

    /**
     * Get organization bills created from this payment (checkout side).
     */
    public function organizationBills()
    {
        return $this->hasMany(OrganizationBill::class, 'payment_id');
    }
}
