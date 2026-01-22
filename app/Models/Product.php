<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class Product extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'user_id',
        'category_id',
        'product_name',
        'product_code',
        'reorder_alert',
        'has_have',
        'has_piece',
        'howmany_to',
        'visible',
        'current_quantity',
        'promotion',
    ];

    // public function stoke_other()
    // {
    //     return $this->hasMany('App\StokeOther');
    // }


    public function stock()
    {
        return $this->hasOne(Stock::class);
    }

    public function price()
    {
        return $this->hasOne(Price::class);
    }

    public function product()
    {
        return $this->hasMany(Promotion::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // public function stock_ledge()
    // {
    //     return $this->hasMany('App\StockLedge');
    // }

    public function storeStock()
    {
        return $this->hasMany(StoreStock::class, 'product_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id','id');
    }

    public function requests(){
        return $this->hasMany(ProductOrServiceRequest::class,'product_id','id');
    }

    // ===== NEW INVENTORY MANAGEMENT RELATIONSHIPS =====

    /**
     * Get all stock batches for this product
     */
    public function stockBatches()
    {
        return $this->hasMany(StockBatch::class);
    }

    /**
     * Get active stock batches with available stock (FIFO order)
     */
    public function availableBatches()
    {
        return $this->hasMany(StockBatch::class)
            ->active()
            ->hasStock()
            ->fifoOrder();
    }

    /**
     * Get stock batches for a specific store
     */
    public function batchesInStore(int $storeId)
    {
        return $this->stockBatches()
            ->where('store_id', $storeId)
            ->active()
            ->hasStock()
            ->fifoOrder();
    }

    /**
     * Get purchase order items for this product
     */
    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Get requisition items for this product
     */
    public function requisitionItems()
    {
        return $this->hasMany(StoreRequisitionItem::class);
    }

    /**
     * Get total available quantity across all stores
     */
    public function getTotalAvailableQtyAttribute(): int
    {
        return $this->stockBatches()
            ->active()
            ->where('current_qty', '>', 0)
            ->sum('current_qty');
    }

    /**
     * Get available quantity in a specific store
     */
    public function getAvailableQtyInStore(int $storeId): int
    {
        return $this->stockBatches()
            ->where('store_id', $storeId)
            ->active()
            ->where('current_qty', '>', 0)
            ->sum('current_qty');
    }

    /**
     * Get batches expiring within given days
     */
    public function getExpiringBatches(int $days = 30)
    {
        return $this->stockBatches()
            ->active()
            ->hasStock()
            ->expiringSoon($days)
            ->get();
    }
}
