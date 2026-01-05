<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class StoreStock extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'store_id',
        'product_id',
        'initial_quantity',
        'quantity_sale',
        'order_quantity',
        'current_quantity'
    ];

    public function store(){
        return $this->belongsTo(Store::class, 'store_id','id');
    }

    public function product(){
        return $this->belongsTo(Product::class, 'product_id','id');
    }
}
