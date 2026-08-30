<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

class Price extends Model implements Auditable
{
    use HasFactory;
    use HasRoles;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'product_id',
        'pr_buy_price',
        'initial_sale_price',
        'current_sale_price',
        'max_discount',
        'status',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stock()
    {
        return $this->belongsTo(Stock::class, 'product_id');
    }
}
