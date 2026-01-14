<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HmoTariff extends Model
{
    use HasFactory;

    protected $table = 'hmo_tariffs';

    protected $fillable = [
        'hmo_id',
        'product_id',
        'service_id',
        'claims_amount',
        'payable_amount',
        'coverage_mode',
    ];

    protected $casts = [
        'claims_amount' => 'decimal:2',
        'payable_amount' => 'decimal:2',
    ];

    /**
     * Validation: Either product_id OR service_id must be set, not both, not neither
     */
    public static function boot()
    {
        parent::boot();

        static::saving(function ($tariff) {
            // Ensure either product_id OR service_id is set, not both
            if (($tariff->product_id && $tariff->service_id) || (!$tariff->product_id && !$tariff->service_id)) {
                throw new \Exception('Either product_id OR service_id must be set, not both, not neither.');
            }
        });
    }

    /**
     * Get the HMO that owns the tariff.
     */
    public function hmo()
    {
        return $this->belongsTo(Hmo::class, 'hmo_id');
    }

    /**
     * Get the product that this tariff is for.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get the service that this tariff is for.
     */
    public function service()
    {
        return $this->belongsTo(service::class, 'service_id');
    }

    /**
     * Scope to filter by HMO
     */
    public function scopeForHmo($query, $hmoId)
    {
        return $query->where('hmo_id', $hmoId);
    }

    /**
     * Scope to filter by product
     */
    public function scopeForProduct($query, $productId)
    {
        return $query->where('product_id', $productId)->whereNull('service_id');
    }

    /**
     * Scope to filter by service
     */
    public function scopeForService($query, $serviceId)
    {
        return $query->where('service_id', $serviceId)->whereNull('product_id');
    }
}
