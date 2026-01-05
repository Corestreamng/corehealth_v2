<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class service extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;
protected $fillable = [
        'user_id',
        'category_id',
        'service_name',
        'service_code',
        'price_assign',
        'status',
        'result_template_v2',
    ];

    protected $casts = [
        'result_template_v2' => 'array',
    ];

    public function requests(){
        return $this->hasMany(ProductOrServiceRequest::class,'product_id','id');
    }

    public function price()
    {
        return $this->hasOne(ServicePrice::class,'service_id','id');
    }

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id','id');
    }

    /**
     * Get the prices that owns the service
     *
     */
    public function prices()
    {
        return $this->belongsTo(price_list::class,'price_list_id','id');
    }
}
