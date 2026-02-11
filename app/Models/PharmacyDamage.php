<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class PharmacyDamage extends Model implements Auditable
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
        'discovered_date' => 'date',
        'approved_at' => 'datetime',
        'stock_deducted' => 'boolean',
        'stock_deducted_at' => 'datetime',
        'qty_damaged' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_value' => 'decimal:2',
    ];

    /**
     * Get the product
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the store
     */
    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    /**
     * Get the batch
     */
    public function batch()
    {
        return $this->belongsTo(StockBatch::class, 'batch_id');
    }

    /**
     * Get the creator
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the approver
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the journal entry
     */
    public function journalEntry()
    {
        return $this->belongsTo(\App\Models\Accounting\JournalEntry::class, 'journal_entry_id');
    }
}
