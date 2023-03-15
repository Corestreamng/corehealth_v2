<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOrder extends Model
{
    use HasFactory;


    protected $fillable = [
        'invoice_id',
        'product_id',
        'order_quantity',
        'total_amount',
        'store_id',
        'stock_date',

    ];

    // One stock_order has many StockOther...
    public function invoice()
    {
        return $this->belongsTo(StockInvoice::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
