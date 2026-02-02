<?php

namespace App\Models\Accounting;

use App\Models\User;
use App\Models\Bank;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fixed Asset Disposal Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 4.1B
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 5
 *
 * Tracks disposal of fixed assets with gain/loss calculation.
 */
class FixedAssetDisposal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fixed_asset_disposals';

    // Disposal Types
    public const TYPE_SALE = 'sale';
    public const TYPE_SCRAPPED = 'scrapped';
    public const TYPE_DONATED = 'donated';
    public const TYPE_TRADE_IN = 'trade_in';
    public const TYPE_THEFT_LOSS = 'theft_loss';
    public const TYPE_INSURANCE_CLAIM = 'insurance_claim';

    // Status
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    // Payment Methods
    public const METHOD_CASH = 'cash';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';

    protected $fillable = [
        'fixed_asset_id',
        'journal_entry_id',
        'disposal_date',
        'disposal_type',
        'disposal_proceeds',
        'book_value_at_disposal',
        'gain_loss_on_disposal',
        'disposal_costs',
        'buyer_name',
        'invoice_number',
        'reason',
        'approved_by',
        'approved_at',
        'status',
        'payment_method',
        'bank_id',
    ];

    protected $casts = [
        'disposal_date' => 'date',
        'disposal_proceeds' => 'decimal:2',
        'book_value_at_disposal' => 'decimal:2',
        'gain_loss_on_disposal' => 'decimal:2',
        'disposal_costs' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function fixedAsset()
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Bank used for receiving disposal proceeds.
     */
    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    // ==========================================
    // CALCULATIONS
    // ==========================================

    /**
     * Calculate gain or loss on disposal.
     */
    public function calculateGainLoss(): float
    {
        $netProceeds = $this->disposal_proceeds - $this->disposal_costs;
        return round($netProceeds - $this->book_value_at_disposal, 2);
    }

    /**
     * Check if disposal resulted in a gain.
     */
    public function hasGain(): bool
    {
        return $this->gain_loss_on_disposal > 0;
    }

    /**
     * Check if disposal resulted in a loss.
     */
    public function hasLoss(): bool
    {
        return $this->gain_loss_on_disposal < 0;
    }

    // ==========================================
    // STATUS METHODS
    // ==========================================

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function approve(int $approvedBy): void
    {
        if (!$this->canBeApproved()) {
            throw new \InvalidArgumentException('Disposal cannot be approved');
        }

        $this->status = self::STATUS_APPROVED;
        $this->approved_by = $approvedBy;
        $this->approved_at = now();
        $this->save();
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeForPeriod($query, string $fromDate, string $toDate)
    {
        return $query->whereBetween('disposal_date', [$fromDate, $toDate]);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    /**
     * Get disposal type label.
     */
    public function getDisposalTypeLabelAttribute(): string
    {
        return match ($this->disposal_type) {
            self::TYPE_SALE => 'Sale',
            self::TYPE_SCRAPPED => 'Scrapped',
            self::TYPE_DONATED => 'Donated',
            self::TYPE_TRADE_IN => 'Trade-in',
            self::TYPE_THEFT_LOSS => 'Theft/Loss',
            self::TYPE_INSURANCE_CLAIM => 'Insurance Claim',
            default => ucfirst(str_replace('_', ' ', $this->disposal_type ?? '')),
        };
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($this->status ?? 'Unknown'),
        };
    }

    /**
     * Get gain/loss label with sign.
     */
    public function getGainLossLabelAttribute(): string
    {
        if ($this->gain_loss_on_disposal > 0) {
            return 'Gain on Disposal';
        } elseif ($this->gain_loss_on_disposal < 0) {
            return 'Loss on Disposal';
        }
        return 'No Gain/Loss';
    }
}
