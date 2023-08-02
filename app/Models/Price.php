<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Traits\HasRoles;

class Price extends Model
{
    use HasFactory, HasRoles;

    public function product() {
        return $this->belongsTo(Product::class);
    }
     
    public function stock() {
        return $this->belongsTo(Stock::class,'product_id');
    }

}
