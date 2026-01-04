<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_request_id',
        'billed_by',
        'billed_date',
        'dispensed_by',
        'dispense_date',
        'product_id',
        'encounter_id',
        'patient_id',
        'doctor_id',
        'dose',
        'status',
        'deleted_by',
        'deletion_reason'
    ];

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
}
