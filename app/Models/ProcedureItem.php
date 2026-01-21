<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ProcedureItem extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'procedure_id',
        'lab_service_request_id',
        'imaging_service_request_id',
        'product_request_id',
        'misc_bill_id',
        'product_or_service_request_id',
        'is_bundled',
    ];

    protected $casts = [
        'is_bundled' => 'boolean',
    ];

    /**
     * Get the procedure.
     */
    public function procedure()
    {
        return $this->belongsTo(Procedure::class, 'procedure_id', 'id');
    }

    /**
     * Get the lab service request (if this is a lab item).
     */
    public function labServiceRequest()
    {
        return $this->belongsTo(LabServiceRequest::class, 'lab_service_request_id', 'id');
    }

    /**
     * Get the imaging service request (if this is an imaging item).
     */
    public function imagingServiceRequest()
    {
        return $this->belongsTo(ImagingServiceRequest::class, 'imaging_service_request_id', 'id');
    }

    /**
     * Get the product request (if this is a medication/product item).
     */
    public function productRequest()
    {
        return $this->belongsTo(ProductRequest::class, 'product_request_id', 'id');
    }

    /**
     * Get the billing entry (only for non-bundled items).
     */
    public function productOrServiceRequest()
    {
        return $this->belongsTo(ProductOrServiceRequest::class, 'product_or_service_request_id', 'id');
    }

    /**
     * Get the item type (lab, imaging, product, misc).
     */
    public function getItemTypeAttribute()
    {
        if ($this->lab_service_request_id) return 'lab';
        if ($this->imaging_service_request_id) return 'imaging';
        if ($this->product_request_id) return 'product';
        if ($this->misc_bill_id) return 'misc';
        return 'unknown';
    }

    /**
     * Get the associated request model based on type.
     */
    public function getRequestAttribute()
    {
        return match ($this->item_type) {
            'lab' => $this->labServiceRequest,
            'imaging' => $this->imagingServiceRequest,
            'product' => $this->productRequest,
            default => null,
        };
    }

    /**
     * Get item name for display.
     */
    public function getNameAttribute()
    {
        return match ($this->item_type) {
            'lab' => $this->labServiceRequest?->labService?->service?->service_name,
            'imaging' => $this->imagingServiceRequest?->imagingService?->service?->service_name,
            'product' => $this->productRequest?->product?->product_name,
            default => 'Unknown Item',
        };
    }

    /**
     * Check if this item has been delivered/completed.
     */
    public function isDelivered()
    {
        return match ($this->item_type) {
            'lab' => in_array($this->labServiceRequest?->status, ['completed', 'validated']),
            'imaging' => in_array($this->imagingServiceRequest?->status, ['completed', 'reported']),
            'product' => $this->productRequest?->status === 'dispensed',
            default => false,
        };
    }

    /**
     * Scope for bundled items.
     */
    public function scopeBundled($query)
    {
        return $query->where('is_bundled', true);
    }

    /**
     * Scope for separately billed items.
     */
    public function scopeSeparatelyBilled($query)
    {
        return $query->where('is_bundled', false);
    }

    /**
     * Scope for lab items.
     */
    public function scopeLabs($query)
    {
        return $query->whereNotNull('lab_service_request_id');
    }

    /**
     * Scope for imaging items.
     */
    public function scopeImaging($query)
    {
        return $query->whereNotNull('imaging_service_request_id');
    }

    /**
     * Scope for product/medication items.
     */
    public function scopeProducts($query)
    {
        return $query->whereNotNull('product_request_id');
    }
}
