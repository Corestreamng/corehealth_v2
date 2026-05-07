<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class StoreDamage extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'product_id',
        'store_id',
        'batch_id',
        'qty_damaged',
        'unit_cost',
        'total_value',
        'damage_type',
        'damage_reason',
        'discovered_date',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'approval_notes',
        'journal_entry_id',
        'stock_deducted',
        'stock_deducted_at',
    ];

    protected $casts = [
        'qty_damaged'      => 'decimal:2',
        'unit_cost'        => 'decimal:2',
        'total_value'      => 'decimal:2',
        'stock_deducted'   => 'boolean',
        'discovered_date'  => 'date',
        'approved_at'      => 'datetime',
        'stock_deducted_at'=> 'datetime',
    ];

    const STATUS_PENDING    = 'pending';
    const STATUS_APPROVED   = 'approved';
    const STATUS_REJECTED   = 'rejected';
    const STATUS_WRITTEN_OFF = 'written_off';

    const TYPE_EXPIRED      = 'expired';
    const TYPE_BROKEN       = 'broken';
    const TYPE_CONTAMINATED = 'contaminated';
    const TYPE_SPOILED      = 'spoiled';
    const TYPE_THEFT        = 'theft';
    const TYPE_OTHER        = 'other';

    // ===== RELATIONSHIPS =====

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
}
