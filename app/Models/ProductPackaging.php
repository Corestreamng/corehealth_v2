<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ProductPackaging extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'product_id',
        'name',
        'description',
        'level',
        'parent_packaging_id',
        'units_in_parent',
        'base_unit_qty',
        'is_default_purchase',
        'is_default_dispense',
        'barcode',
    ];

    protected $casts = [
        'level' => 'integer',
        'units_in_parent' => 'decimal:4',
        'base_unit_qty' => 'decimal:4',
        'is_default_purchase' => 'boolean',
        'is_default_dispense' => 'boolean',
    ];

    // ===== RELATIONSHIPS =====

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_packaging_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_packaging_id');
    }

    // ===== HELPERS =====

    /**
     * Convert a quantity in this packaging to base units.
     */
    public function toBaseUnits(float $qty): float
    {
        return $qty * $this->base_unit_qty;
    }

    /**
     * Convert a base-unit quantity into this packaging.
     */
    public function fromBaseUnits(float $baseQty): float
    {
        return $this->base_unit_qty > 0
            ? $baseQty / $this->base_unit_qty
            : 0;
    }
}
