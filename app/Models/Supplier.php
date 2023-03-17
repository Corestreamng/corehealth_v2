<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_name',
        'address',
        'phone',
        'created_by',
        'last_payment',
        'last_payment_date',
        'last_buy_date',
        'last_buy_amount',
        'credit',
        'deposit',
        'tootal_deposite',
        'date_line',
        'visible',
    ];

    // One narrator has many tales...
    public function invoices()
    {
        return $this->hasMany(StockInvoice::class);
    }

    public function category()
    {
        return $this->belongsTo(ProductCategory::class);
    }
}
