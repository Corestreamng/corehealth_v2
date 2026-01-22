<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use OwenIt\Auditing\Contracts\Auditable;

/**
 * Model: StoreStock
 *
 * Plan Reference: Phase 2 - Models (Modified)
 * Purpose: Aggregate stock quantities per product per store
 *
 * Note: This model is maintained for quick lookups. Actual stock movements
 * are tracked via StockBatch and StockBatchTransaction models.
 *
 * Related Models: Store, Product, StockBatch
 * Related Files:
 * - app/Services/StockService.php (syncStoreStock method)
 * - database/migrations/2026_01_21_100009_add_fields_to_store_stocks_table.php
 */
class StoreStock extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'store_id',
        'product_id',
        'initial_quantity',
        'quantity_sale',
        'order_quantity',
        'current_quantity',
        // New fields from migration 100009
        'reserved_qty',
        'reorder_level',
        'max_stock_level',
        'is_active',
        'last_restocked_at',
        'last_sold_at',
    ];

    protected $casts = [
        'initial_quantity' => 'integer',
        'quantity_sale' => 'integer',
        'order_quantity' => 'integer',
        'current_quantity' => 'integer',
        'reserved_qty' => 'integer',
        'reorder_level' => 'integer',
        'max_stock_level' => 'integer',
        'is_active' => 'boolean',
        'last_restocked_at' => 'datetime',
        'last_sold_at' => 'datetime',
    ];

    // ===== RELATIONSHIPS =====

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    /**
     * Get all batches for this store-product combination
     */
    public function batches()
    {
        return $this->hasMany(StockBatch::class, 'product_id', 'product_id')
            ->where('store_id', $this->store_id);
    }

    // ===== SCOPES =====

    /**
     * Scope for active stock items
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for low stock items (below reorder level)
     */
    public function scopeLowStock($query)
    {
        return $query->whereColumn('current_quantity', '<=', 'reorder_level');
    }

    /**
     * Scope for out of stock items
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('current_quantity', '<=', 0);
    }

    // ===== ACCESSORS =====

    /**
     * Get available quantity (current minus reserved)
     */
    public function getAvailableQtyAttribute(): int
    {
        return max(0, $this->current_quantity - ($this->reserved_qty ?? 0));
    }

    /**
     * Check if stock is low
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->current_quantity <= ($this->reorder_level ?? 10);
    }

    /**
     * Check if stock is out
     */
    public function getIsOutOfStockAttribute(): bool
    {
        return $this->current_quantity <= 0;
    }

    // ===== METHODS =====

    /**
     * Reserve stock for a pending order
     */
    public function reserve(int $qty): bool
    {
        if ($this->available_qty < $qty) {
            return false;
        }
        $this->reserved_qty = ($this->reserved_qty ?? 0) + $qty;
        return $this->save();
    }

    /**
     * Release reserved stock
     */
    public function releaseReservation(int $qty): bool
    {
        $this->reserved_qty = max(0, ($this->reserved_qty ?? 0) - $qty);
        return $this->save();
    }
}
