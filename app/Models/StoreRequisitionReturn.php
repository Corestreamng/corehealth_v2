<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class StoreRequisitionReturn extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'store_requisition_id',
        'store_requisition_item_id',
        'product_id',
        'source_store_id',
        'destination_store_id',
        'batch_id',
        'qty_returned',
        'return_condition',
        'return_reason',
        'restock',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'approval_notes',
        'stock_adjusted',
        'stock_adjusted_at',
    ];

    protected $casts = [
        'qty_returned'      => 'integer',
        'restock'           => 'boolean',
        'stock_adjusted'    => 'boolean',
        'approved_at'       => 'datetime',
        'stock_adjusted_at' => 'datetime',
    ];

    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    const CONDITION_GOOD    = 'good';
    const CONDITION_DAMAGED = 'damaged';
    const CONDITION_PARTIAL = 'partial';

    // ===== RELATIONSHIPS =====

    public function requisition()
    {
        return $this->belongsTo(StoreRequisition::class, 'store_requisition_id');
    }

    public function requisitionItem()
    {
        return $this->belongsTo(StoreRequisitionItem::class, 'store_requisition_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function sourceStore()
    {
        return $this->belongsTo(Store::class, 'source_store_id');
    }

    public function destinationStore()
    {
        return $this->belongsTo(Store::class, 'destination_store_id');
    }

    public function batch()
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    // ===== SCOPES =====

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    // ===== HELPERS =====

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
