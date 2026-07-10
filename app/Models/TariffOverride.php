<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TariffOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'hmo_id',
        'hmo_scheme_id',
        'target_type',
        'target_id',
        'override_type',
        'amount',
        'is_active',
    ];

    public function hmo()
    {
        return $this->belongsTo(Hmo::class);
    }

    public function scheme()
    {
        return $this->belongsTo(HmoScheme::class, 'hmo_scheme_id');
    }

    public function getTargetNameAttribute()
    {
        switch ($this->target_type) {
            case 'product':
                $product = Product::find($this->target_id);
                return $product ? $product->product_name : 'Unknown Product';
            case 'service':
                $service = Service::find($this->target_id);
                return $service ? $service->service_name : 'Unknown Service';
            case 'product_category':
                $category = ProductCategory::find($this->target_id);
                return $category ? $category->category_name : 'Unknown Product Category';
            case 'service_category':
                $category = ServiceCategory::find($this->target_id);
                return $category ? $category->category_name : 'Unknown Service Category';
            default:
                return 'Unknown';
        }
    }
}
