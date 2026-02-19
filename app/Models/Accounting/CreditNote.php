<?php

namespace App\Models\Accounting;

use App\Models\Encounter;
use App\Models\patient;
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
        'original_payment_id',
        'amount',
        'reason',
        'refund_method',
        'bank_id',
        'status',
        'journal_entry_id',
        'created_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'processed_by',
        'processed_at',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    // Status constants (matching database enum)
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING_APPROVAL = 'pending_approval';
    const STATUS_PENDING = 'pending_approval'; // Alias for compatibility
    const STATUS_APPROVED = 'approved';
    const STATUS_PROCESSED = 'processed';
    const STATUS_APPLIED = 'processed'; // Alias for compatibility
    const STATUS_VOID = 'void';
    const STATUS_VOIDED = 'void'; // Alias for compatibility

    // Refund methods (matching database enum)
    const REFUND_CASH = 'cash';
    const REFUND_BANK = 'bank';
    const REFUND_ACCOUNT_CREDIT = 'account_credit';

    /**
     * Get the patient.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(patient::class);
    }

    /**
     * Get the original payment being refunded.
     */
    public function originalPayment(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Payment::class, 'original_payment_id');
    }

    /**
     * Get the bank for bank refunds.
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Bank::class);
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Alias for createdBy.
     */
    public function creator(): BelongsTo
    {
        return $this->createdBy();
    }

    /**
     * Get the user who submitted this credit note.
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the user who approved this credit note.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Alias for approvedBy.
     */
    public function approver(): BelongsTo
    {
        return $this->approvedBy();
    }

    /**
     * Get the user who processed the refund.
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Alias for processedBy (backward compatibility).
     */
    public function appliedBy(): BelongsTo
    {
        return $this->processedBy();
    }

    /**
     * Get the user who voided this credit note.
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
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
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    /**
     * Check if credit note is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if credit note has been processed (refunded).
     */
    public function isProcessed(): bool
    {
        return $this->status === self::STATUS_PROCESSED;
    }

    /**
     * Alias for isProcessed (backward compatibility).
     */
    public function isApplied(): bool
    {
        return $this->isProcessed();
    }

    /**
     * Alias for isProcessed (backward compatibility).
     */
    public function isRefunded(): bool
    {
        return $this->isProcessed();
    }

    /**
     * Check if credit note is voided.
     */
    public function isVoided(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    /**
     * Alias for isVoided (backward compatibility).
     */
    public function isCancelled(): bool
    {
        return $this->isVoided();
    }

    // =========================================
    // WORKFLOW PERMISSION CHECKS
    // =========================================

    /**
     * Check if credit note can be edited.
     */
    public function canEdit(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if credit note can be submitted for approval.
     */
    public function canSubmit(): bool
    {
        if ($this->status !== self::STATUS_DRAFT) {
            return false;
        }

        if ($this->amount <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Check if credit note can be approved.
     */
    public function canApprove(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    /**
     * Check if refund can be processed.
     */
    public function canProcess(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Alias for canProcess (backward compatibility).
     */
    public function canProcessRefund(): bool
    {
        return $this->canProcess();
    }

    /**
     * Check if credit note can be voided.
     */
    public function canVoid(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING_APPROVAL, self::STATUS_APPROVED]);
    }

    /**
     * Alias for canVoid (backward compatibility).
     */
    public function canCancel(): bool
    {
        return $this->canVoid();
    }

    // =========================================
    // WORKFLOW ACTIONS
    // =========================================

    /**
     * Submit credit note for approval.
     */
    public function submit(int $userId): bool
    {
        if (!$this->canSubmit()) {
            return false;
        }

        $this->status = self::STATUS_PENDING_APPROVAL;
        $this->submitted_by = $userId;
        $this->submitted_at = now();

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
     * Process the credit note (mark as refunded).
     */
    public function process(int $userId): bool
    {
        if (!$this->canProcess()) {
            return false;
        }

        $this->status = self::STATUS_PROCESSED;
        $this->processed_by = $userId;
        $this->processed_at = now();

        return $this->save();
    }

    /**
     * Alias for process (backward compatibility).
     */
    public function markAsRefunded(int $userId, ?string $method = null, ?string $reference = null): bool
    {
        if ($method) {
            $this->refund_method = $method;
        }
        return $this->process($userId);
    }

    /**
     * Void the credit note.
     */
    public function void(int $userId, string $reason): bool
    {
        if (!$this->canVoid()) {
            return false;
        }

        $this->status = self::STATUS_VOID;
        $this->voided_by = $userId;
        $this->voided_at = now();
        $this->void_reason = $reason;

        return $this->save();
    }

    /**
     * Alias for void (backward compatibility).
     */
    public function cancel(string $reason = 'Cancelled'): bool
    {
        return $this->void(auth()->id() ?? 1, $reason);
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
        return $query->where('status', self::STATUS_PENDING_APPROVAL);
    }

    /**
     * Scope to get processed credit notes.
     */
    public function scopeProcessed($query)
    {
        return $query->where('status', self::STATUS_PROCESSED);
    }

    /**
     * Scope to filter by patient.
     */
    public function scopeForPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, ?string $fromDate, ?string $toDate)
    {
        if ($fromDate) {
            $query->where('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('created_at', '<=', $toDate);
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
            self::STATUS_PENDING_APPROVAL, 'pending_approval' => 'bg-warning text-dark',
            self::STATUS_APPROVED => 'bg-info',
            self::STATUS_PROCESSED, 'processed' => 'bg-success',
            self::STATUS_VOID, 'void' => 'bg-dark',
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
            self::STATUS_PENDING_APPROVAL, 'pending_approval' => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_PROCESSED, 'processed' => 'Processed',
            self::STATUS_VOID, 'void' => 'Voided',
            default => ucfirst(str_replace('_', ' ', $this->status)),
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
            self::REFUND_BANK, 'bank' => 'Bank Transfer',
            self::REFUND_ACCOUNT_CREDIT, 'account_credit' => 'Account Credit',
            default => ucfirst(str_replace('_', ' ', $this->refund_method)),
        };
    }

    /**
     * Get total amount attribute (alias for amount for backward compatibility).
     */
    public function getTotalAmountAttribute(): float
    {
        return (float) $this->amount;
    }

    /**
     * Get processed_at attribute (alias for processed_at for views expecting applied_at).
     */
    public function getAppliedAtAttribute(): ?\Carbon\Carbon
    {
        return $this->processed_at;
    }
}
