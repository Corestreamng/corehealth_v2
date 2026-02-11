<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class PharmacyReturn extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'product_request_id',
        'product_or_service_request_id',
        'patient_id',
        'product_id',
        'store_id',
        'batch_id',
        'qty_returned',
        'original_qty',
        'refund_amount',
        'original_amount',
        'return_condition',
        'return_reason',
        'restock',
        'refund_to_patient',
        'refund_to_hmo',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'approval_notes',
        'journal_entry_id',
    ];

    protected $casts = [
        'restock' => 'boolean',
        'approved_at' => 'datetime',
        'qty_returned' => 'decimal:2',
        'original_qty' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'refund_to_patient' => 'decimal:2',
        'refund_to_hmo' => 'decimal:2',
    ];

    /**
     * Get the original product request (dispensed item)
     */
    public function productRequest()
    {
        return $this->belongsTo(ProductRequest::class, 'product_request_id');
    }

    /**
     * Get the original billing record
     */
    public function billRequest()
    {
        return $this->belongsTo(ProductOrServiceRequest::class, 'product_or_service_request_id');
    }

    /**
     * Get the patient
     */
    public function patient()
    {
        return $this->belongsTo(patient::class, 'patient_id');
    }

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
     * Get the batch (if restocked)
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
