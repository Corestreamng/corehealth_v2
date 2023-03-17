<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'service_name',
        'service_code',
        'price_assign',
        'status',
    ];

    public function requests(){
        return $this->hasMany(ProductOrServiceRequest::class,'product_id','id');
    }

    public function price()
    {
        return $this->hasOne(ServicePrice::class,'service_id','id');
    }

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id','id');
    }
}
