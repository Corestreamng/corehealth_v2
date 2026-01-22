<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Model: PurchaseOrderItem
 *
 * Plan Reference: Phase 2 - Models
 * Purpose: Represents a line item in a purchase order
 *
 * Related Models: PurchaseOrder, Product, StockBatch
 * Related Files:
 * - app/Services/PurchaseOrderService.php
 * - database/migrations/2026_01_21_100002_create_purchase_order_items_table.php
 */
class PurchaseOrderItem extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'ordered_qty',
        'received_qty',
        'unit_cost',
        'actual_unit_cost',
        'status',
    ];

    protected $casts = [
        'ordered_qty' => 'integer',
        'received_qty' => 'integer',
        'unit_cost' => 'decimal:2',
        'actual_unit_cost' => 'decimal:2',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PARTIAL = 'partial';
    const STATUS_RECEIVED = 'received';
    const STATUS_CANCELLED = 'cancelled';

    // ===== RELATIONSHIPS =====

    /**
     * Get the parent purchase order
     */
    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get stock batches created from this item
     */
    public function stockBatches()
    {
        return $this->hasMany(StockBatch::class, 'purchase_order_item_id');
    }

    // ===== ACCESSORS =====

    /**
     * Get line total (ordered qty * unit cost)
     */
    public function getLineTotalAttribute(): float
    {
        return $this->ordered_qty * $this->unit_cost;
    }

    /**
     * Get actual line total (received qty * actual unit cost)
     */
    public function getActualLineTotalAttribute(): float
    {
        $cost = $this->actual_unit_cost ?? $this->unit_cost;
        return $this->received_qty * $cost;
    }

    /**
     * Get remaining quantity to receive
     */
    public function getRemainingQtyAttribute(): int
    {
        return max(0, $this->ordered_qty - ($this->received_qty ?? 0));
    }

    // ===== HELPERS =====

    /**
     * Check if item is fully received
     */
    public function isFullyReceived(): bool
    {
        return $this->received_qty >= $this->ordered_qty;
    }

    /**
     * Check if item is partially received
     */
    public function isPartiallyReceived(): bool
    {
        return $this->received_qty > 0 && $this->received_qty < $this->ordered_qty;
    }

    /**
     * Receive quantity for this item
     */
    public function receive(int $qty, ?float $actualCost = null): void
    {
        $this->received_qty = ($this->received_qty ?? 0) + $qty;

        if ($actualCost !== null) {
            $this->actual_unit_cost = $actualCost;
        }

        if ($this->isFullyReceived()) {
            $this->status = self::STATUS_RECEIVED;
        } elseif ($this->received_qty > 0) {
            $this->status = self::STATUS_PARTIAL;
        }

        $this->save();
    }
}
