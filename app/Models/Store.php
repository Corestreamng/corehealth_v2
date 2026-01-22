<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class Store extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'store_name',
        'code',
        'description',
        'location',
        'store_type',
        'is_default',
        'manager_id',
        'status',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'status' => 'boolean',
    ];

    // ===== EXISTING RELATIONSHIPS =====

    /**
     * Get store stocks (legacy)
     */
    public function stock() {
        return $this->hasMany(StoreStock::class,'store_id','id');
    }

    // ===== NEW INVENTORY MANAGEMENT RELATIONSHIPS =====

    /**
     * Get the store manager
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Get all stock batches in this store
     */
    public function stockBatches()
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * Get active stock batches with available stock
     */
    public function availableBatches()
    {
        return $this->hasMany(StockBatch::class)
            ->active()
            ->hasStock()
            ->fifoOrder();
    }

    /**
     * Get purchase orders targeting this store
     */
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class, 'target_store_id');
    }

    /**
     * Get requisitions requesting items FROM this store
     */
    public function outgoingRequisitions()
    {
        return $this->hasMany(StoreRequisition::class, 'from_store_id');
    }

    /**
     * Get requisitions requesting items TO this store
     */
    public function incomingRequisitions()
    {
        return $this->hasMany(StoreRequisition::class, 'to_store_id');
    }

    /**
     * Get expenses for this store
     */
    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    // ===== SCOPES =====

    /**
     * Scope for active stores
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope for pharmacy stores
     */
    public function scopePharmacy($query)
    {
        return $query->where('store_type', 'pharmacy');
    }

    /**
     * Scope for warehouse stores
     */
    public function scopeWarehouse($query)
    {
        return $query->where('store_type', 'warehouse');
    }

    // ===== HELPERS =====

    /**
     * Get the default pharmacy store
     */
    public static function getDefaultPharmacy(): ?self
    {
        return self::where('store_type', 'pharmacy')
            ->where('is_default', true)
            ->first()
            ?? self::where('store_type', 'pharmacy')->first();
    }

    /**
     * Get the central/warehouse store
     */
    public static function getCentralStore(): ?self
    {
        return self::where('store_type', 'warehouse')
            ->first();
    }

    /**
     * Get available quantity for a product in this store
     */
    public function getProductQty(int $productId): int
    {
        return $this->stockBatches()
            ->where('product_id', $productId)
            ->active()
            ->sum('current_qty');
    }

    /**
     * Get batches for a product in this store (FIFO order)
     */
    public function getProductBatches(int $productId)
    {
        return $this->stockBatches()
            ->where('product_id', $productId)
            ->active()
            ->hasStock()
            ->fifoOrder()
            ->get();
    }

    /**
     * Get products with low stock in this store
     */
    public function getLowStockProducts()
    {
        return StoreStock::where('store_id', $this->id)
            ->where('is_active', true)
            ->whereRaw('current_quantity <= reorder_level')
            ->with('product')
            ->get();
    }

    /**
     * Get expiring batches in this store
     */
    public function getExpiringBatches(int $days = 30)
    {
        return $this->stockBatches()
            ->active()
            ->hasStock()
            ->expiringSoon($days)
            ->with('product')
            ->get();
    }
}
