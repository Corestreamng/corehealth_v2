<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Journal Entry Edit Request Model
 *
 * Reference: Accounting System Plan §4.1 - Eloquent Models
 *
 * Tracks edit requests for posted journal entries.
 * Posted entries cannot be directly edited - instead, an edit request
 * must be submitted and approved.
 */
class JournalEntryEdit extends Model implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'journal_entry_id',
        'original_data',
        'edited_data',
        'edit_reason',
        'status',
        'requested_by',
        'requested_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'original_data' => 'array',
        'edited_data' => 'array',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    /**
     * Get the journal entry being edited.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get the user who requested the edit.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user who approved the request.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who rejected the request.
     */
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Get the reviewer (approver or rejecter based on status).
     */
    public function getReviewerAttribute()
    {
        if ($this->status === self::STATUS_APPROVED) {
            return $this->approver;
        } elseif ($this->status === self::STATUS_REJECTED) {
            return $this->rejecter;
        }
        return null;
    }

    /**
     * Get the reviewer's user ID.
     */
    public function getReviewedByAttribute()
    {
        if ($this->status === self::STATUS_APPROVED) {
            return $this->approved_by;
        } elseif ($this->status === self::STATUS_REJECTED) {
            return $this->rejected_by;
        }
        return null;
    }

    /**
     * Check if request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if request was approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if request was rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if edit has been applied.
     */
    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    /**
     * Approve the edit request.
     */
    public function approve(int $userId, ?string $notes = null): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->status = self::STATUS_APPROVED;
        $this->reviewed_by = $userId;
        $this->reviewed_at = now();
        $this->review_notes = $notes;

        return $this->save();
    }

    /**
     * Reject the edit request.
     */
    public function reject(int $userId, string $reason): bool
    {
        if (!$this->isPending()) {
            return false;
        }

        $this->status = self::STATUS_REJECTED;
        $this->reviewed_by = $userId;
        $this->reviewed_at = now();
        $this->review_notes = $reason;

        return $this->save();
    }

    /**
     * Mark edit as applied.
     */
    public function markAsApplied(): bool
    {
        if (!$this->isApproved()) {
            return false;
        }

        $this->status = self::STATUS_APPLIED;
        return $this->save();
    }

    /**
     * Scope to get pending requests.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get approved requests.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Get status badge class for UI.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'bg-warning text-dark',
            self::STATUS_APPROVED => 'bg-success',
            self::STATUS_REJECTED => 'bg-danger',
            self::STATUS_APPLIED => 'bg-info',
            default => 'bg-secondary',
        };
    }

    /**
     * Get status display label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending Review',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_APPLIED => 'Applied',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get summary of proposed changes.
     */
    public function getChangesSummaryAttribute(): array
    {
        $changes = [];
        $original = $this->original_data ?? [];
        $proposed = $this->proposed_data ?? [];

        // Compare header fields
        $headerFields = ['entry_date', 'description'];
        foreach ($headerFields as $field) {
            if (($original[$field] ?? null) !== ($proposed[$field] ?? null)) {
                $changes[] = [
                    'field' => $field,
                    'original' => $original[$field] ?? null,
                    'proposed' => $proposed[$field] ?? null,
                ];
            }
        }

        // Compare lines
        $originalLines = $original['lines'] ?? [];
        $proposedLines = $proposed['lines'] ?? [];

        if (count($originalLines) !== count($proposedLines)) {
            $changes[] = [
                'field' => 'lines_count',
                'original' => count($originalLines),
                'proposed' => count($proposedLines),
            ];
        }

        return $changes;
    }
}
