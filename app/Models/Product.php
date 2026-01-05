<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class Product extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'user_id',
        'category_id',
        'product_name',
        'product_code',
        'reorder_alert',
        'has_have',
        'has_piece',
        'howmany_to',
        'visible',
        'current_quantity',
        'promotion',
    ];

    // public function stoke_other()
    // {
    //     return $this->hasMany('App\StokeOther');
    // }


    public function stock()
    {
        return $this->hasOne(Stock::class);
    }

    public function price()
    {
        return $this->hasOne(Price::class);
    }

    public function product()
    {
        return $this->hasMany(Promotion::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // public function stock_ledge()
    // {
    //     return $this->hasMany('App\StockLedge');
    // }

    public function storeStock()
    {
        return $this->hasMany(StoreStock::class, 'product_id', 'id');
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id','id');
    }

    public function requests(){
        return $this->hasMany(ProductOrServiceRequest::class,'product_id','id');
    }
}
