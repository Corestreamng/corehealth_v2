<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [

        'product_or_service_requests_id',
        'product_id',
        'service_id',
        'supply',
        'supply_date',
        'serial_no',
        'quantity_buy',
        'sale_price',
        'pieces_quantity',
        'supply',
        'pieces_sales_price',
        'gain',
        'loss',
        'sale_date',
        'budget_year_id',
        'user_id',
        'total_amount',
        'store_id',
        'promo_qt',
    ];

    // One invoices has many StockOther...
    public function product()
    {
        return $this->belongsTo(Product::class,'product_id','id');
    }
    public function service()
    {
        return $this->belongsTo(Service::class,'service_id','id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function product_or_service_request()
    {
        return $this->belongsTo(ProductOrServiceRequest::class,'product_or_service_requests_id','id');
    }
    // public function mode_of_payment()
    // {
    //     return $this->belongsTo('App\ModeOfPayment');
    // }
    public function store()
    {
        return $this->belongsTo(Store::class,'store_id','id');
    }
}
