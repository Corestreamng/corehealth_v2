<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Model: StockBatch
 *
 * Plan Reference: Phase 2 - Models
 * Purpose: Represents a batch of stock for a product in a specific store
 *
 * Key Features:
 * - FIFO support via received_date ordering
 * - Tracks cost price for each batch (for COGS calculation)
 * - Expiry tracking for perishable items
 * - Links to source (PO, transfer, manual entry)
 *
 * Related Models: Product, Store, PurchaseOrderItem, StoreRequisition, StockBatchTransaction
 * Related Files:
 * - app/Services/StockService.php
 * - app/Helpers/BatchHelper.php
 * - database/migrations/2026_01_21_100003_create_stock_batches_table.php
 */
class StockBatch extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'product_id',
        'store_id',
        'supplier_id',
        'batch_name',
        'batch_number',
        'initial_qty',
        'current_qty',
        'sold_qty',
        'cost_price',
        'expiry_date',
        'received_date',
        'source',
        'purchase_order_item_id',
        'source_requisition_id',
        'created_by',
        'is_active',
    ];

    protected $casts = [
        'initial_qty' => 'integer',
        'current_qty' => 'integer',
        'sold_qty' => 'integer',
        'cost_price' => 'decimal:2',
        'expiry_date' => 'date',
        'received_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Source type constants
     */
    const SOURCE_PURCHASE_ORDER = 'purchase_order';
    const SOURCE_MANUAL = 'manual';
    const SOURCE_TRANSFER_IN = 'transfer_in';
    const SOURCE_OPENING_STOCK = 'opening_stock';

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->batch_number)) {
                $model->batch_number = self::generateBatchNumber($model->product_id, $model->store_id);
            }
            if (empty($model->received_date)) {
                $model->received_date = now();
            }
            if (empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
            // Initialize sold_qty
            if ($model->sold_qty === null) {
                $model->sold_qty = 0;
            }
        });
    }

    /**
     * Generate a unique batch number
     */
    public static function generateBatchNumber(int $productId, int $storeId): string
    {
        $prefix = 'BTH';
        $date = date('Ymd');

        $count = self::whereDate('created_at', today())
            ->where('product_id', $productId)
            ->where('store_id', $storeId)
            ->count();

        return sprintf('%s-%d-%d-%s-%03d', $prefix, $storeId, $productId, $date, $count + 1);
    }

    // ===== RELATIONSHIPS =====

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the store
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the purchase order item (if sourced from PO)
     */
    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    /**
     * Get the source requisition (if sourced from transfer)
     */
    public function sourceRequisition()
    {
        return $this->belongsTo(StoreRequisition::class, 'source_requisition_id');
    }

    /**
     * Get the supplier (if assigned)
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the user who created this batch
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all transactions for this batch
     */
    public function transactions()
    {
        return $this->hasMany(StockBatchTransaction::class);
    }

    /**
     * Get product requests dispensed from this batch
     */
    public function productRequests()
    {
        return $this->hasMany(ProductRequest::class, 'dispensed_from_batch_id');
    }

    // ===== SCOPES =====

    /**
     * Scope for active batches
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for batches with available stock
     */
    public function scopeHasStock($query)
    {
        return $query->where('current_qty', '>', 0);
    }

    /**
     * Scope for batches in a specific store
     */
    public function scopeInStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Scope for batches of a specific product
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope for FIFO ordering (oldest first by received_date)
     */
    public function scopeFifoOrder($query)
    {
        return $query->orderBy('received_date', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Scope for batches expiring soon
     */
    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>', now());
    }

    /**
     * Scope for expired batches
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now());
    }

    // ===== ACCESSORS =====

    /**
     * Get available quantity (current - reserved if implemented)
     */
    public function getAvailableQtyAttribute(): int
    {
        return $this->current_qty;
    }

    /**
     * Get total value of remaining stock
     */
    public function getStockValueAttribute(): float
    {
        return $this->current_qty * $this->cost_price;
    }

    /**
     * Check if batch is expired
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date < now();
    }

    /**
     * Get days until expiry (negative if expired)
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expiry_date) {
            return null;
        }
        return now()->diffInDays($this->expiry_date, false);
    }

    // ===== HELPERS =====

    /**
     * Deduct stock from this batch
     *
     * @param int $qty Quantity to deduct
     * @param string $type Transaction type
     * @param string|null $referenceType Polymorphic reference type
     * @param int|null $referenceId Polymorphic reference ID
     * @param string|null $notes Additional notes
     * @return StockBatchTransaction
     */
    public function deductStock(int $qty, string $type = 'out', ?string $referenceType = null, ?int $referenceId = null, ?string $notes = null): StockBatchTransaction
    {
        if ($qty > $this->current_qty) {
            throw new \InvalidArgumentException("Cannot deduct {$qty} from batch. Only {$this->current_qty} available.");
        }

        $this->current_qty -= $qty;
        $this->sold_qty += $qty;
        $this->save();

        return $this->recordTransaction($type, -$qty, $referenceType, $referenceId, $notes);
    }

    /**
     * Add stock to this batch
     *
     * @param int $qty Quantity to add
     * @param string $type Transaction type
     * @param string|null $referenceType Polymorphic reference type
     * @param int|null $referenceId Polymorphic reference ID
     * @param string|null $notes Additional notes
     * @return StockBatchTransaction
     */
    public function addStock(int $qty, string $type = 'in', ?string $referenceType = null, ?int $referenceId = null, ?string $notes = null): StockBatchTransaction
    {
        $this->current_qty += $qty;
        $this->save();

        return $this->recordTransaction($type, $qty, $referenceType, $referenceId, $notes);
    }

    /**
     * Record a transaction for this batch
     */
    protected function recordTransaction(string $type, int $qty, ?string $referenceType = null, ?int $referenceId = null, ?string $notes = null): StockBatchTransaction
    {
        return StockBatchTransaction::create([
            'stock_batch_id' => $this->id,
            'type' => $type,
            'qty' => abs($qty),
            'balance_after' => $this->current_qty,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'performed_by' => auth()->id() ?? 1, // fallback to system user for CLI/queue contexts
        ]);
    }

    /**
     * Check if this batch can fulfill a quantity
     */
    public function canFulfill(int $qty): bool
    {
        return $this->is_active && !$this->is_expired && $this->current_qty >= $qty;
    }
}
