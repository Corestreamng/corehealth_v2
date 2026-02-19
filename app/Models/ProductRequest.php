<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\StockBatch;

use OwenIt\Auditing\Contracts\Auditable;

class ProductRequest extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
        'product_request_id',
        'billed_by',
        'billed_date',
        'dispensed_by',
        'dispense_date',
        'dispensed_from_store_id',
        'dispensed_from_batch_id',
        'product_id',
        'encounter_id',
        'patient_id',
        'doctor_id',
        'dose',
        'qty',
        'status',
        'deleted_by',
        'deletion_reason',
        'adapted_from_product_id',
        'adaptation_note',
        'adapted_at',
        'adapted_by',
        'qty_adjusted_from',
        'qty_adjustment_reason',
        'qty_adjusted_at',
        'qty_adjusted_by',
    ];

    protected $casts = [
        'adapted_at' => 'datetime',
        'qty_adjusted_at' => 'datetime',
        'dispense_date' => 'datetime',
        'billed_date' => 'datetime',
    ];

    /**
     * Get the user who adapted the prescription
     */
    public function adapter()
    {
        return $this->belongsTo(User::class, 'adapted_by', 'id');
    }

    /**
     * Get the user who adjusted the quantity
     */
    public function qtyAdjuster()
    {
        return $this->belongsTo(User::class, 'qty_adjusted_by', 'id');
    }

    public function productOrServiceRequest()
    {
        return $this->belongsTo(ProductOrServiceRequest::class, 'product_request_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class, 'encounter_id', 'id');
    }

    public function patient()
    {
        return $this->belongsTo(patient::class, 'patient_id', 'id');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id', 'id');
    }

    public function biller()
    {
        return $this->belongsTo(User::class, 'billed_by', 'id');
    }

    public function dispenser()
    {
        return $this->belongsTo(User::class, 'dispensed_by', 'id');
    }

    /**
     * Get the store from which this item was dispensed.
     */
    public function dispensedFromStore()
    {
        return $this->belongsTo(Store::class, 'dispensed_from_store_id', 'id');
    }

    /**
     * Get the batch from which this item was dispensed.
     * NEW: For batch-based inventory tracking
     */
    public function dispensedFromBatch()
    {
        return $this->belongsTo(StockBatch::class, 'dispensed_from_batch_id', 'id');
    }

    /**
     * Get the original product if this was adapted/changed.
     * NEW: For product adaptation tracking
     */
    public function adaptedFromProduct()
    {
        return $this->belongsTo(Product::class, 'adapted_from_product_id', 'id');
    }

    /**
     * Check if this product request was adapted from another product.
     */
    public function isAdapted(): bool
    {
        return !is_null($this->adapted_from_product_id);
    }

    /**
     * Get the procedure item if this product is part of a procedure.
     * Spec Reference: PROCEDURE_MODULE_DESIGN_PLAN.md Part 3.7
     */
    public function procedureItem()
    {
        return $this->hasOne(ProcedureItem::class, 'product_request_id', 'id');
    }
}
