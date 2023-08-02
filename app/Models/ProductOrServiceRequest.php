<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductOrServiceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'user_id',
        'staff_user_id',
        'product_id',
        'payment_id',
        'qty',
        'service_id'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class,'product_id','id');
    }
    public function service()
    {
        return $this->belongsTo(service::class,'service_id','id');
    }

    public function invoice(){
        return $this->belongsTo(invoice::class,'invoice_id','id');
    }

    public function payment(){
        return $this->belongsTo(payment::class,'payment_id','id');
    }

    public function user(){
        return $this->belongsTo(User::class,'user_id');
    }

    public function staff(){
        return $this->belongsTo(User::class,'staff_user_id','id');
    }

    public function sale(){
        return $this->hasOne(Sale::class,'product_or_service_requests_id','id');
    }

    public function doctor_queue_entry(){
        return $this->hasOne(DoctorQueue::class,'request_entry_id','id');
    }
}
