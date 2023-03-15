<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use HasFactory;
    protected $fillable = [
        'category_name',
        'category_code',
        'category_description',
        'status_id',
    ];

    public function products()
    {
        return $this->hasMany(Product::class,'category_id','id');
    }
}
