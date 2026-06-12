<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class StockUtilization extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'product_id',
        'store_id',
        'qty',
        'unit',
        'reason',
        'utilization_type',
        'patient_id',
        'is_billed',
        'product_or_service_request_id',
        'start_date',
        'end_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'qty' => 'integer',
        'is_billed' => 'boolean',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    /**
     * Get the product being utilized.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the store from which the stock was utilized.
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the patient (if utilized for a patient).
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the user who recorded the utilization.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the associated billing request.
     */
    public function billingRequest()
    {
        return $this->belongsTo(ProductOrServiceRequest::class, 'product_or_service_request_id');
    }
}
