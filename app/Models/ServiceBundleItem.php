<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ServiceBundleItem extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'parent_service_id',
        'item_type',
        'item_id',
        'qty',
        'dose',
        'note',
        'sort_order',
    ];

    /**
     * Get the parent service (the combo).
     */
    public function parentService()
    {
        return $this->belongsTo(Service::class, 'parent_service_id');
    }

    /**
     * Get the actual service instance.
     */
    public function service()
    {
        return $this->belongsTo(Service::class, 'item_id');
    }

    /**
     * Get the actual product instance.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'item_id');
    }

    /**
     * Get the actual product or service instance.
     */
    public function item()
    {
        if ($this->item_type === 'product') {
            return $this->product();
        }
        return $this->service();
    }

    /**
     * Helper to get display name of the item.
     */
    public function getDisplayNameAttribute()
    {
        if ($this->item_type === 'product') {
            return $this->product->product_name ?? 'Unknown Product';
        }
        return $this->service->service_name ?? 'Unknown Service';
    }
}
