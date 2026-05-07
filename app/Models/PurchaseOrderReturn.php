<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class PurchaseOrderReturn extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'return_number',
        'purchase_order_id',
        'purchase_order_item_id',
        'product_id',
        'store_id',
        'batch_id',
        'qty_returned',
        'unit_cost',
        'total_value',
        'return_reason',
        'return_notes',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'approval_notes',
        'payment_status_at_return',
        'journal_entry_id',
        'expense_adjusted',
        'expense_adjusted_at',
        'stock_deducted',
        'stock_deducted_at',
    ];

    protected $casts = [
        'qty_returned'         => 'integer',
        'unit_cost'            => 'decimal:2',
        'total_value'          => 'decimal:2',
        'expense_adjusted'     => 'boolean',
        'stock_deducted'       => 'boolean',
        'approved_at'          => 'datetime',
        'expense_adjusted_at'  => 'datetime',
        'stock_deducted_at'    => 'datetime',
    ];

    const STATUS_PENDING  = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    const REASON_WRONG_ITEM    = 'wrong_item';
    const REASON_DAMAGED       = 'damaged';
    const REASON_EXCESS        = 'excess';
    const REASON_QUALITY_ISSUE = 'quality_issue';
    const REASON_OTHER         = 'other';

    // ===== RELATIONSHIPS =====

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
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

    public function journalEntry()
    {
        return $this->belongsTo(\App\Models\Accounting\JournalEntry::class, 'journal_entry_id');
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

    /**
     * Generate a unique return number.
     */
    public static function generateReturnNumber(): string
    {
        $prefix = 'POR';
        $year   = now()->format('Y');
        $last   = static::whereYear('created_at', $year)->max('return_number');

        if ($last) {
            preg_match('/(\d+)$/', $last, $m);
            $seq = (int)($m[1] ?? 0) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . $year . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
