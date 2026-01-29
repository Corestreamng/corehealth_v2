<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Journal Entry Model
 *
 * Reference: Accounting System Plan §4.1 - Eloquent Models
 *
 * THE SINGLE SOURCE OF TRUTH for all financial data.
 * All financial transactions flow through journal entries.
 * All reports derive their data from posted journal entries.
 */
class JournalEntry extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'entry_number',
        'entry_date',
        'accounting_period_id',
        'description',
        'reference_type',
        'reference_id',
        'entry_type',
        'status',
        'reversal_of_id',
        'reversed_by_id',
        'created_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'posted_by',
        'posted_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'edit_requires_approval',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'is_manual' => 'boolean',
        'is_reversing' => 'boolean',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    // Status constants (must match database enum values)
    const STATUS_DRAFT = 'draft';
    const STATUS_PENDING = 'pending_approval';
    const STATUS_PENDING_APPROVAL = 'pending_approval';  // Alias
    const STATUS_APPROVED = 'approved';
    const STATUS_POSTED = 'posted';
    const STATUS_REJECTED = 'rejected';  // Note: May need migration if not in DB
    const STATUS_REVERSED = 'reversed';

    // Entry type constants
    const TYPE_AUTO = 'auto';
    const TYPE_AUTOMATED = 'auto';  // Alias
    const TYPE_MANUAL = 'manual';
    const TYPE_OPENING = 'opening';
    const TYPE_CLOSING = 'closing';
    const TYPE_REVERSAL = 'reversal';
    const TYPE_ADJUSTMENT = 'adjustment';  // Note: May need migration if not in DB

    // Source types for automated entries
    const SOURCE_PAYMENT = 'App\\Models\\Payment';
    const SOURCE_PURCHASE_ORDER = 'App\\Models\\PurchaseOrder';
    const SOURCE_EXPENSE = 'App\\Models\\Expense';
    const SOURCE_PAYROLL = 'App\\Models\\Payroll';
    const SOURCE_CREDIT_NOTE = 'App\\Models\\Accounting\\CreditNote';
    const SOURCE_MANUAL = null;

    /**
     * Get the accounting period.
     */
    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    /**
     * Get the journal entry lines.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * Get the source document (polymorphic).
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the entry this reverses (if this is a reversing entry).
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_of_id');
    }

    /**
     * Alias for reversalOf (backward compatibility).
     */
    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_of_id');
    }

    /**
     * Alias for reversalOf (alternate naming).
     */
    public function originalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_of_id');
    }

    /**
     * Get the entry that reversed this one.
     */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_by_id');
    }

    /**
     * Alias for reversedBy (alternate naming).
     */
    public function reversalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_by_id');
    }

    /**
     * Get reversing entries for this entry.
     */
    public function reversingEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'reversal_of_id');
    }

    /**
     * Get edit requests for this entry.
     */
    public function edits(): HasMany
    {
        return $this->hasMany(JournalEntryEdit::class);
    }

    /**
     * Get the user who created this entry.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Alias for creator relationship (for compatibility).
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who submitted this entry.
     */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the user who approved this entry.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Alias for approver relationship (for compatibility).
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who posted this entry.
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * Alias for poster relationship (for compatibility).
     */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * Get the user who rejected this entry.
     */
    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get edit requests for this entry.
     */
    public function editRequests(): HasMany
    {
        return $this->hasMany(JournalEntryEdit::class);
    }

    // =========================================
    // BALANCE VALIDATION
    // =========================================

    /**
     * Calculate total debits.
     */
    public function getTotalDebitAttribute(): float
    {
        return (float) $this->lines->sum('debit');
    }

    /**
     * Calculate total credits.
     */
    public function getTotalCreditAttribute(): float
    {
        return (float) $this->lines->sum('credit');
    }

    /**
     * Check if entry is balanced (debits = credits).
     */
    public function isBalanced(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.0001;
    }

    /**
     * Get the out-of-balance amount.
     */
    public function getOutOfBalanceAmount(): float
    {
        return round($this->total_debit - $this->total_credit, 4);
    }

    // =========================================
    // STATUS CHECKS
    // =========================================

    /**
     * Check if entry is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if entry is pending approval.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if entry is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if entry is posted.
     */
    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /**
     * Check if entry is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if entry has been reversed.
     */
    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    // =========================================
    // WORKFLOW PERMISSION CHECKS
    // =========================================

    /**
     * Check if entry can be edited.
     * Only draft and rejected entries can be edited.
     */
    public function canEdit(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED]);
    }

    /**
     * Check if entry can be submitted for approval.
     * Must be draft/rejected, balanced, have lines, and period must be open.
     */
    public function canSubmit(): bool
    {
        if (!in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REJECTED])) {
            return false;
        }

        if (!$this->isBalanced()) {
            return false;
        }

        if ($this->lines()->count() < 2) {
            return false;
        }

        if (!$this->accountingPeriod || !$this->accountingPeriod->isOpen()) {
            return false;
        }

        return true;
    }

    /**
     * Check if entry can be approved.
     * Must be pending and period must be open.
     */
    public function canApprove(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        if (!$this->accountingPeriod || !$this->accountingPeriod->isOpen()) {
            return false;
        }

        return true;
    }

    /**
     * Check if entry can be posted.
     * Approved manual entries or auto-generated entries can be posted.
     */
    public function canPost(): bool
    {
        // Auto-generated entries can be posted directly
        if (!$this->is_manual && $this->status === self::STATUS_DRAFT) {
            if ($this->isBalanced() && $this->lines()->count() >= 2) {
                return $this->accountingPeriod && $this->accountingPeriod->isOpen();
            }
        }

        // Manual entries must be approved first
        if ($this->status !== self::STATUS_APPROVED) {
            return false;
        }

        if (!$this->accountingPeriod || !$this->accountingPeriod->isOpen()) {
            return false;
        }

        return true;
    }

    /**
     * Check if entry can be rejected.
     */
    public function canReject(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if entry can be reversed.
     * Only posted entries in open periods can be reversed.
     */
    public function canReverse(): bool
    {
        if ($this->status !== self::STATUS_POSTED) {
            return false;
        }

        if ($this->is_reversing) {
            return false; // Can't reverse a reversing entry
        }

        // Check if already reversed
        if ($this->reversingEntries()->where('status', self::STATUS_POSTED)->exists()) {
            return false;
        }

        // Period must be open for reversal
        if (!$this->accountingPeriod || !$this->accountingPeriod->isOpen()) {
            return false;
        }

        return true;
    }

    /**
     * Check if entry can be deleted.
     * Only draft entries can be deleted.
     */
    public function canDelete(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    // =========================================
    // WORKFLOW ACTIONS
    // =========================================

    /**
     * Submit entry for approval.
     */
    public function submit(int $userId): bool
    {
        if (!$this->canSubmit()) {
            return false;
        }

        $this->status = self::STATUS_PENDING;
        $this->submitted_by = $userId;
        $this->submitted_at = now();
        $this->rejected_by = null;
        $this->rejected_at = null;
        $this->rejection_reason = null;

        return $this->save();
    }

    /**
     * Approve entry.
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
     * Post entry (finalize).
     */
    public function post(int $userId): bool
    {
        if (!$this->canPost()) {
            return false;
        }

        $this->status = self::STATUS_POSTED;
        $this->posted_by = $userId;
        $this->posted_at = now();

        return $this->save();
    }

    /**
     * Reject entry with reason.
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
     * Scope to get posted entries only.
     */
    public function scopePosted($query)
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    /**
     * Scope to get pending entries (awaiting approval).
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get manual entries only.
     */
    public function scopeManual($query)
    {
        return $query->where('is_manual', true);
    }

    /**
     * Scope to get automated entries only.
     */
    public function scopeAutomated($query)
    {
        return $query->where('is_manual', false);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, ?string $fromDate, ?string $toDate)
    {
        if ($fromDate) {
            $query->where('entry_date', '>=', $fromDate);
        }
        if ($toDate) {
            $query->where('entry_date', '<=', $toDate);
        }
        return $query;
    }

    /**
     * Scope to filter by accounting period.
     */
    public function scopeForPeriod($query, int $periodId)
    {
        return $query->where('accounting_period_id', $periodId);
    }

    /**
     * Scope to filter by source type.
     */
    public function scopeForSourceType($query, string $sourceType)
    {
        return $query->where('source_type', $sourceType);
    }

    // =========================================
    // HELPERS
    // =========================================

    /**
     * Generate next entry number.
     */
    public static function generateEntryNumber(): string
    {
        $prefix = 'JE';
        $year = date('Y');
        $month = date('m');

        $lastEntry = self::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastEntry && preg_match('/JE-\d{6}-(\d+)/', $lastEntry->entry_number, $matches)) {
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
            self::STATUS_POSTED => 'bg-success',
            self::STATUS_REJECTED => 'bg-danger',
            self::STATUS_REVERSED => 'bg-dark',
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
            self::STATUS_POSTED => 'Posted',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_REVERSED => 'Reversed',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get source type display label.
     */
    public function getSourceTypeLabelAttribute(): string
    {
        if (!$this->source_type) {
            return 'Manual Entry';
        }

        return match ($this->source_type) {
            self::SOURCE_PAYMENT => 'Payment',
            self::SOURCE_PURCHASE_ORDER => 'Purchase Order',
            self::SOURCE_EXPENSE => 'Expense',
            self::SOURCE_PAYROLL => 'Payroll',
            self::SOURCE_CREDIT_NOTE => 'Credit Note/Refund',
            default => class_basename($this->source_type),
        };
    }
}
