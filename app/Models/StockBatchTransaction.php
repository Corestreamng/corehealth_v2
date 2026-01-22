<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Model: StockBatchTransaction
 *
 * Plan Reference: Phase 2 - Models
 * Purpose: Audit trail for all stock batch movements
 *
 * Transaction Types:
 * - in: Stock added (from PO receiving, manual adjustment)
 * - out: Stock removed (sales, dispensing)
 * - adjustment: Manual adjustment (positive or negative)
 * - transfer_out: Transferred to another store
 * - transfer_in: Received from another store
 * - return: Customer return
 * - expired: Written off due to expiry
 * - damaged: Written off due to damage
 *
 * Related Models: StockBatch, User
 * Related Files:
 * - app/Services/StockService.php
 * - database/migrations/2026_01_21_100004_create_stock_batch_transactions_table.php
 */
class StockBatchTransaction extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'stock_batch_id',
        'type',
        'qty',
        'balance_after',
        'reference_type',
        'reference_id',
        'notes',
        'performed_by',
    ];

    protected $casts = [
        'qty' => 'integer',
        'balance_after' => 'integer',
    ];

    /**
     * Transaction type constants
     */
    const TYPE_IN = 'in';
    const TYPE_OUT = 'out';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_TRANSFER_OUT = 'transfer_out';
    const TYPE_TRANSFER_IN = 'transfer_in';
    const TYPE_RETURN = 'return';
    const TYPE_EXPIRED = 'expired';
    const TYPE_DAMAGED = 'damaged';

    /**
     * Get all transaction types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_IN => 'Stock In',
            self::TYPE_OUT => 'Stock Out',
            self::TYPE_ADJUSTMENT => 'Adjustment',
            self::TYPE_TRANSFER_OUT => 'Transfer Out',
            self::TYPE_TRANSFER_IN => 'Transfer In',
            self::TYPE_RETURN => 'Return',
            self::TYPE_EXPIRED => 'Expired',
            self::TYPE_DAMAGED => 'Damaged',
        ];
    }

    // ===== RELATIONSHIPS =====

    /**
     * Get the stock batch
     */
    public function stockBatch()
    {
        return $this->belongsTo(StockBatch::class);
    }

    /**
     * Get the user who performed this transaction
     */
    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Get the polymorphic reference (e.g., Sale, ProductRequest, StoreRequisition)
     */
    public function reference()
    {
        return $this->morphTo();
    }

    // ===== SCOPES =====

    /**
     * Scope for transactions of a specific type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for inbound transactions (stock additions)
     */
    public function scopeInbound($query)
    {
        return $query->whereIn('type', [self::TYPE_IN, self::TYPE_TRANSFER_IN, self::TYPE_RETURN]);
    }

    /**
     * Scope for outbound transactions (stock reductions)
     */
    public function scopeOutbound($query)
    {
        return $query->whereIn('type', [self::TYPE_OUT, self::TYPE_TRANSFER_OUT, self::TYPE_EXPIRED, self::TYPE_DAMAGED]);
    }

    /**
     * Scope for transactions in a date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // ===== ACCESSORS =====

    /**
     * Get human-readable type label
     */
    public function getTypeLabelAttribute(): string
    {
        return self::getTypes()[$this->type] ?? $this->type;
    }

    /**
     * Get badge class for UI display
     */
    public function getTypeBadgeClassAttribute(): string
    {
        return match($this->type) {
            self::TYPE_IN, self::TYPE_TRANSFER_IN, self::TYPE_RETURN => 'badge-success',
            self::TYPE_OUT, self::TYPE_TRANSFER_OUT => 'badge-warning',
            self::TYPE_EXPIRED, self::TYPE_DAMAGED => 'badge-danger',
            self::TYPE_ADJUSTMENT => 'badge-info',
            default => 'badge-secondary',
        };
    }

    /**
     * Check if this is an inbound transaction
     */
    public function getIsInboundAttribute(): bool
    {
        return in_array($this->type, [self::TYPE_IN, self::TYPE_TRANSFER_IN, self::TYPE_RETURN]);
    }

    /**
     * Check if this is an outbound transaction
     */
    public function getIsOutboundAttribute(): bool
    {
        return in_array($this->type, [self::TYPE_OUT, self::TYPE_TRANSFER_OUT, self::TYPE_EXPIRED, self::TYPE_DAMAGED]);
    }
}
