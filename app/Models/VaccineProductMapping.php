<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class VaccineProductMapping extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'vaccine_name',
        'product_id',
        'is_primary',
        'is_active',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the product for this mapping.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Get all active mappings for a vaccine name.
     */
    public static function getProductsForVaccine($vaccineName)
    {
        return self::where('vaccine_name', $vaccineName)
            ->where('is_active', true)
            ->with('product')
            ->orderBy('is_primary', 'desc')
            ->get();
    }

    /**
     * Get the primary product for a vaccine.
     */
    public static function getPrimaryProduct($vaccineName)
    {
        $mapping = self::where('vaccine_name', $vaccineName)
            ->where('is_primary', true)
            ->where('is_active', true)
            ->with('product')
            ->first();

        return $mapping?->product;
    }

    /**
     * Set this mapping as primary for its vaccine (unset others).
     */
    public function setAsPrimary()
    {
        self::where('vaccine_name', $this->vaccine_name)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        $this->update(['is_primary' => true]);
    }

    /**
     * Get all unique vaccine names that have product mappings.
     */
    public static function getMappedVaccineNames()
    {
        return self::where('is_active', true)
            ->distinct()
            ->pluck('vaccine_name');
    }

    /**
     * Check if a vaccine has at least one product mapped.
     */
    public static function hasMapping($vaccineName)
    {
        return self::where('vaccine_name', $vaccineName)
            ->where('is_active', true)
            ->exists();
    }
}
