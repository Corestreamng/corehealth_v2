<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockInvoice extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = [

		  'invoice_no',
		  'supplier_id', 
		  'invoice_date', 
		  'created_by',
		  'number_of_products', 
		  'total_amount', 
		  'visible',
		  'created_by',
    ];

    // One invoices has many StockOther...
    public function supplier() {
        return $this->belongsTo(Supplier::class);
    }
     
    public function category () {
        return $this->belongsTo(Supplier::class);
    }

    public function stock_order () {
        return $this->hasMany(StockOrder::class);
    }
}
