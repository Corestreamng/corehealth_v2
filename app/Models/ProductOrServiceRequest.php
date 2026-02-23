<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class ProductOrServiceRequest extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'type',
        'invoice_id',
        'user_id',
        'encounter_id',
        'admission_request_id',
        'staff_user_id',
        'created_by',
        'order_date',
        'dispensed_from_store_id',
        'product_id',
        'payment_id',
        'qty',
        'amount',
        'service_id',
        'discount',
        'payable_amount',
        'claims_amount',
        'coverage_mode',
        'hmo_id',
        'validation_status',
        'auth_code',
        'validated_by',
        'validated_at',
        'validation_notes',
        'hmo_remittance_id',
        'submitted_to_hmo_at',
        'hmo_submission_batch',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
    public function service()
    {
        return $this->belongsTo(service::class, 'service_id', 'id');
    }

    public function invoice()
    {
        return $this->belongsTo(invoice::class, 'invoice_id', 'id');
    }

    public function payment()
    {
        return $this->belongsTo(payment::class, 'payment_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_user_id', 'id');
    }

    public function sale()
    {
        return $this->hasOne(Sale::class, 'product_or_service_requests_id', 'id');
    }

    public function doctor_queue_entry()
    {
        return $this->hasOne(DoctorQueue::class, 'request_entry_id', 'id');
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    // Add this relationship to get the doctor's order (dose/freq)
    public function productRequest()
    {
        return $this->hasOne(ProductRequest::class, 'product_request_id', 'id');
    }

    /**
     * Get the HMO remittance associated with this request.
     */
    public function remittance()
    {
        return $this->belongsTo(HmoRemittance::class, 'hmo_remittance_id');
    }

    /**
     * Get the store from which this item was dispensed.
     */
    public function dispensedFromStore()
    {
        return $this->belongsTo(Store::class, 'dispensed_from_store_id');
    }

    /**
     * Get the procedure associated with this billing entry.
     */
    public function procedure()
    {
        return $this->hasOne(Procedure::class, 'product_or_service_request_id', 'id');
    }

    /**
     * Get the HMO associated with this request.
     */
    public function hmo()
    {
        return $this->belongsTo(Hmo::class, 'hmo_id');
    }
}
