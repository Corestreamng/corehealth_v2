<?php

namespace App\Models;

use App\Models\Accounting\JournalEntry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class StockBatchReturn extends Model implements Auditable
{
    use HasFactory;
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'return_number',
        'stock_batch_id',
        'product_id',
        'store_id',
        'supplier_id',
        'qty_returned',
        'unit_cost',
        'total_value',
        'return_reason',
        'settlement_type',
        'return_notes',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'approval_notes',
        'stock_deducted',
        'stock_deducted_at',
        'journal_entry_id',
    ];

    protected $casts = [
        'qty_returned' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_value' => 'decimal:2',
        'stock_deducted' => 'boolean',
        'approved_at' => 'datetime',
        'stock_deducted_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const SETTLEMENT_AP_CREDIT_NOTE = 'ap_credit_note';
    public const SETTLEMENT_CASH_REFUND = 'cash_refund';
    public const SETTLEMENT_RECEIVABLE = 'receivable';
    public const SETTLEMENT_NO_FINANCIAL_IMPACT = 'no_financial_impact';

    public const REASON_DAMAGED = 'damaged';
    public const REASON_EXPIRED_NEAR = 'expired_near';
    public const REASON_RECALLED = 'recalled';
    public const REASON_WRONG_ITEM = 'wrong_item';
    public const REASON_EXCESS = 'excess';
    public const REASON_QUALITY_ISSUE = 'quality_issue';
    public const REASON_OTHER = 'other';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->return_number)) {
                $date = now()->format('Ymd');
                $count = self::whereDate('created_at', today())->count();
                $model->return_number = sprintf('RET-BAT-%s-%04d', $date, $count + 1);
            }
            if (empty($model->created_by)) {
                $model->created_by = auth()->id() ?? 1;
            }
        });
    }

    // ===== RELATIONSHIPS =====

    public function stockBatch()
    {
        return $this->belongsTo(StockBatch::class, 'stock_batch_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
