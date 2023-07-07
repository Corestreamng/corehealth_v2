<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_request_id',
        'billed_by',
        'billed_date',
        'product_id',
        'encounter_id',
        'patient_id',
        'doctor_id',
        'dose',
        'status'
    ];

    public function productOrServiceRequest(){
        return $this->belongsTo(ProductOrServiceRequest::class,'product_request_id','id');
    }

    public function product(){
        return $this->belongsTo(Product::class, 'product_id','id');
    }

    public function encounter(){
        return $this->belongsTo(Encounter::class, 'encounter_id','id');
    }

    public function patient(){
        return $this->belongsTo(patient::class, 'patient_id','id');
    }

    public function doctor(){
        return $this->belongsTo(User::class, 'doctor_id','id');
    }
}
