<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class InjectionAdministration extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'patient_id',
        'product_id',
        'product_or_service_request_id',
        'dose',
        'route',
        'site',
        'administered_at',
        'administered_by',
        'drug_source',
        'product_request_id',
        'external_drug_name',
        'external_qty',
        'external_batch_number',
        'external_expiry_date',
        'external_source_note',
        'dispensed_from_store_id',
        'notes',
        'batch_number',
        'expiry_date',
    ];

    protected $casts = [
        'administered_at' => 'datetime',
        'expiry_date' => 'date',
    ];

    /**
     * Get the patient that received the injection.
     */
    public function patient()
    {
        return $this->belongsTo(patient::class, 'patient_id');
    }

    /**
     * Get the product (injectable drug) used.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the prescription record for this injection.
     */
    public function productRequest()
    {
        return $this->belongsTo(ProductRequest::class, 'product_request_id');
    }

    /**
     * Get the billing record for this injection.
     */
    public function productOrServiceRequest()
    {
        return $this->belongsTo(ProductOrServiceRequest::class, 'product_or_service_request_id');
    }

    /**
     * Get the nurse who administered the injection.
     */
    public function administeredBy()
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    /**
     * Alias for administeredBy for backward compatibility.
     */
    public function nurse()
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    /**
     * Get the store from which the injection was dispensed.
     */
    public function dispensedFromStore()
    {
        return $this->belongsTo(Store::class, 'dispensed_from_store_id');
    }
}
