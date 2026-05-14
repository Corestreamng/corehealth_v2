<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


use OwenIt\Auditing\Contracts\Auditable;
class Service extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope('active', function ($builder) {
            $builder->where('status', 1);
        });
    }

    protected $fillable = [
        'user_id',
        'category_id',
        'service_name',
        'service_code',
        'price_assign',
        'status',
        'result_template_v2',
        'is_combo',
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

    /**
     * Get the procedure definition (if this service is a procedure).
     * Only services with category_id = procedure_category_id will have this.
     */
    public function procedureDefinition()
    {
        return $this->hasOne(ProcedureDefinition::class, 'service_id', 'id');
    }

    /**
     * Check if this service is a lab (investigation) service.
     */
    public function isLab(): bool
    {
        return (int) $this->category_id === (int) appsettings('investigation_category_id', 2);
    }

    /**
     * Check if this service is an imaging service.
     */
    public function isImaging(): bool
    {
        return (int) $this->category_id === (int) appsettings('imaging_category_id', 6);
    }

    /**
     * Check if this service is a procedure.
     */
    public function isProcedure()
    {
        return $this->procedureDefinition()->exists();
    }

    /**
     * Get the items that make up this combo service.
     */
    public function bundleItems()
    {
        return $this->hasMany(ServiceBundleItem::class, 'parent_service_id', 'id')->orderBy('sort_order');
    }
}
