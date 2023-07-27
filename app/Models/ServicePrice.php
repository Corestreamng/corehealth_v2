<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'cost_price',
        'sale_price',
        'max_discount',
        'status'
    ];

    public function service() {
        return $this->belongsTo(service::class,'service_id','id');
    }
}
