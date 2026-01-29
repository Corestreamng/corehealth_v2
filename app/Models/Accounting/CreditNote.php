<?php

namespace App\Models\Accounting;

use App\Models\Encounter;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Credit Note Model
 *
 * Reference: Accounting System Plan §4.1 - Eloquent Models
 *
 * Represents a refund/credit note issued to a patient.
 * When approved, generates a journal entry to record the accounting impact.
 */
class CreditNote extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'credit_note_number',
        'patient_id',
        'encounter_id',
        'credit_note_date',
        'reason',
        'notes',
        'total_amount',
        'status',
        'journal_entry_id',
        'created_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'refunded_by',
        'refunded_at',
        'refund_method',
        'refund_reference',
    ];

    protected $casts = [
        'credit_note_date' => 'date',
        'total_amount' => 'decimal:4',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    // Status constants
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CANCELLED = 'cancelled';

    // Refund methods
    const REFUND_CASH = 'cash';
    const REFUND_BANK_TRANSFER = 'bank_transfer';
    const REFUND_CARD = 'card';
    const REFUND_WALLET = 'wallet';
    const REFUND_CREDIT = 'credit'; // Patient credit/balance

    /**
     * Get the patient.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the encounter.
     */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    /**
     * Get the credit note items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    /**
     * Get the associated journal entry.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get the user who created this credit note.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this credit note.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who rejected this credit note.
     */
    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get the user who processed the refund.
     */
    public function refunder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    // =========================================
    // STATUS CHECKS
    // =========================================

    /**
     * Check if credit note is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if credit note is pending approval.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if credit note is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if credit note is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if credit note has been refunded.
     */
    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    /**
     * Check if credit note is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    // =========================================
    // WORKFLOW PERMISSION CHECKS
    // =========================================

    /**
     * Check if credit note can be edited.
     */
    public function canEdit(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED]);
    }

    /**
     * Check if credit note can be submitted for approval.
     */
    public function canSubmit(): bool
    {
        if (!in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED])) {
            return false;
        }

        if ($this->items()->count() === 0) {
            return false;
        }

        if ($this->total_amount <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Check if credit note can be approved.
     */
    public function canApprove(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if credit note can be rejected.
     */
    public function canReject(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if refund can be processed.
     */
    public function canProcessRefund(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if credit note can be cancelled.
     */
    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING]);
    }

    // =========================================
    // WORKFLOW ACTIONS
    // =========================================

    /**
     * Submit credit note for approval.
     */
    public function submit(): bool
    {
        if (!$this->canSubmit()) {
            return false;
        }

        $this->status = self::STATUS_PENDING;
        $this->rejected_by = null;
        $this->rejected_at = null;
        $this->rejection_reason = null;

        return $this->save();
    }

    /**
     * Approve credit note.
     */
    public function approve(int $userId): bool
    {
        if (!$this->canApprove()) {
            return false;
        }

        $this->status = self::STATUS_APPROVED;
        $this->approved_by = $userId;
        $this->approved_at = now();

        return $this->save();
    }

    /**
     * Reject credit note.
     */
    public function reject(int $userId, string $reason): bool
    {
        if (!$this->canReject()) {
            return false;
        }

        $this->status = self::STATUS_REJECTED;
        $this->rejected_by = $userId;
        $this->rejected_at = now();
        $this->rejection_reason = $reason;

        return $this->save();
    }

    /**
     * Mark as refunded.
     */
    public function markAsRefunded(int $userId, string $method, ?string $reference = null): bool
    {
        if (!$this->canProcessRefund()) {
            return false;
        }

        $this->status = self::STATUS_REFUNDED;
        $this->refunded_by = $userId;
        $this->refunded_at = now();
        $this->refund_method = $method;
        $this->refund_reference = $reference;

        return $this->save();
    }

    /**
     * Cancel credit note.
     */
    public function cancel(): bool
    {
        if (!$this->canCancel()) {
            return false;
        }

        $this->status = self::STATUS_CANCELLED;
        return $this->save();
    }

    // =========================================
    // CALCULATIONS
    // =========================================

    /**
     * Recalculate total from items.
     */
    public function recalculateTotal(): void
    {
        $this->total_amount = $this->items->sum('amount');
        $this->save();
    }

    // =========================================
    // SCOPES
    // =========================================

    /**
     * Scope to filter by status.
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get pending credit notes.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to filter by patient.
     */
    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope to filter by encounter.
     */
    public function scopeForEncounter($query, int $encounterId)
    {
        return $query->where('encounter_id', $encounterId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, ?string $fromDate, ?string $toDate)
    {
        if ($fromDate) {
            $query->where('credit_note_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('credit_note_date', '<=', $toDate);
        }
        return $query;
    }

    // =========================================
    // HELPERS
    // =========================================

    /**
     * Generate next credit note number.
     */
    public static function generateCreditNoteNumber(): string
    {
        $prefix = 'CN';
        $year = date('Y');
        $month = date('m');

        $lastNote = self::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastNote && preg_match('/CN-\d{6}-(\d+)/', $lastNote->credit_note_number, $matches)) {
            $sequence = intval($matches[1]) + 1;
        } else {
            $sequence = 1;
        }

        return sprintf('%s-%s%s-%04d', $prefix, $year, $month, $sequence);
    }

    /**
     * Get status badge class for UI.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'bg-secondary',
            self::STATUS_PENDING => 'bg-warning text-dark',
            self::STATUS_APPROVED => 'bg-info',
            self::STATUS_REJECTED => 'bg-danger',
            self::STATUS_REFUNDED => 'bg-success',
            self::STATUS_CANCELLED => 'bg-dark',
            default => 'bg-secondary',
        };
    }

    /**
     * Get status display label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_REFUNDED => 'Refunded',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get refund method display label.
     */
    public function getRefundMethodLabelAttribute(): ?string
    {
        if (!$this->refund_method) {
            return null;
        }

        return match ($this->refund_method) {
            self::REFUND_CASH => 'Cash',
            self::REFUND_BANK_TRANSFER => 'Bank Transfer',
            self::REFUND_CARD => 'Card Refund',
            self::REFUND_WALLET => 'Digital Wallet',
            self::REFUND_CREDIT => 'Patient Credit',
            default => ucfirst(str_replace('_', ' ', $this->refund_method)),
        };
    }
}
