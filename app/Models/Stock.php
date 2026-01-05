<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class Stock extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'product_id',
        'initial_quantity',
        'order_quantity',
        'current_quantity',
        'quantity_sale'
    ];

    public function product(){
        return $this->belongsTo(Product::class,'product_id','id');
    }
}
