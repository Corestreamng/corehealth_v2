<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class Promotion extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'product_id',
        'promotion_name',
        'quantity_to_buy',
        'quantity_to_give',
        'promotion_total_quantity',
        'start_date',
        'end_date',
        'current_qt',
        'give_qt',
        'visible',
    ];

    // One transaction id has many products sales...

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function promo_sale()
    {
        return $this->hasMany(PromoSale::class);
    }
}
