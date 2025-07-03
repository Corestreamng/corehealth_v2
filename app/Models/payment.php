<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class payment extends Model
{
    use HasFactory;

    protected $fillable = ['reference_no', 'total', 'payment_type', 'invoice_id', 'patient_id', 'user_id', 'hmo_id', 'total_discount'];



    /**
     * Get the invoice associated with the payment
     *
     *
     */
    public function invoice()
    {
        return $this->hasOne(invoice::class);
    }

    public function patient()
    {
        return $this->belongsTo(patient::class, 'patient_id', 'id');
    }

    public function staff_user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function product_or_service_request()
    {
        return $this->hasMany(ProductOrServiceRequest::class, 'payment_id', 'id');
    }
}
