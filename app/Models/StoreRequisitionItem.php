<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Model: StoreRequisitionItem
 *
 * Plan Reference: Phase 2 - Models
 * Purpose: Represents a line item in a store requisition
 *
 * Related Models: StoreRequisition, Product, StockBatch
 * Related Files:
 * - app/Services/RequisitionService.php
 * - database/migrations/2026_01_21_100006_create_store_requisition_items_table.php
 */
class StoreRequisitionItem extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'store_requisition_id',
        'product_id',
        'requested_qty',
        'approved_qty',
        'fulfilled_qty',
        'source_batch_id',
        'destination_batch_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'requested_qty' => 'integer',
        'approved_qty' => 'integer',
        'fulfilled_qty' => 'integer',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PARTIAL = 'partial';
    const STATUS_FULFILLED = 'fulfilled';
    const STATUS_CANCELLED = 'cancelled';

    // ===== RELATIONSHIPS =====

    /**
     * Get the parent requisition
     */
    public function requisition()
    {
        return $this->belongsTo(StoreRequisition::class, 'store_requisition_id');
    }

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the source batch (from which items are taken)
     */
    public function sourceBatch()
    {
        return $this->belongsTo(StockBatch::class, 'source_batch_id');
    }

    /**
     * Get the destination batch (created when items are received)
     */
    public function destinationBatch()
    {
        return $this->belongsTo(StockBatch::class, 'destination_batch_id');
    }

    // ===== ACCESSORS =====

    /**
     * Get the quantity to fulfill (approved or requested)
     */
    public function getQtyToFulfillAttribute(): int
    {
        return $this->approved_qty ?? $this->requested_qty;
    }

    /**
     * Get remaining quantity to fulfill
     */
    public function getRemainingQtyAttribute(): int
    {
        $target = $this->approved_qty ?? $this->requested_qty;
        return max(0, $target - ($this->fulfilled_qty ?? 0));
    }

    // ===== HELPERS =====

    /**
     * Check if item is fully fulfilled
     */
    public function isFullyFulfilled(): bool
    {
        $target = $this->approved_qty ?? $this->requested_qty;
        return ($this->fulfilled_qty ?? 0) >= $target;
    }

    /**
     * Check if item is partially fulfilled
     */
    public function isPartiallyFulfilled(): bool
    {
        $fulfilled = $this->fulfilled_qty ?? 0;
        $target = $this->approved_qty ?? $this->requested_qty;
        return $fulfilled > 0 && $fulfilled < $target;
    }

    /**
     * Fulfill a quantity for this item
     *
     * @param int $qty Quantity to fulfill
     * @param StockBatch $sourceBatch Source batch from which items are taken
     * @param StockBatch|null $destinationBatch Destination batch (will be created if null)
     */
    public function fulfill(int $qty, StockBatch $sourceBatch, ?StockBatch $destinationBatch = null): void
    {
        $this->fulfilled_qty = ($this->fulfilled_qty ?? 0) + $qty;
        $this->source_batch_id = $sourceBatch->id;

        if ($destinationBatch) {
            $this->destination_batch_id = $destinationBatch->id;
        }

        if ($this->isFullyFulfilled()) {
            $this->status = self::STATUS_FULFILLED;
        } elseif ($this->fulfilled_qty > 0) {
            $this->status = self::STATUS_PARTIAL;
        }

        $this->save();
    }
}
