<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class VaccineScheduleItem extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'template_id',
        'vaccine_name',
        'vaccine_code',
        'dose_number',
        'dose_label',
        'age_days',
        'age_display',
        'route',
        'site',
        'notes',
        'sort_order',
        'is_required',
    ];

    protected $casts = [
        'dose_number' => 'integer',
        'age_days' => 'integer',
        'sort_order' => 'integer',
        'is_required' => 'boolean',
    ];

    /**
     * Get the template this item belongs to.
     */
    public function template()
    {
        return $this->belongsTo(VaccineScheduleTemplate::class, 'template_id');
    }

    /**
     * Get the product mappings for this vaccine.
     */
    public function productMappings()
    {
        return VaccineProductMapping::where('vaccine_name', $this->vaccine_name)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get the primary product for this vaccine.
     */
    public function getPrimaryProduct()
    {
        return VaccineProductMapping::where('vaccine_name', $this->vaccine_name)
            ->where('is_primary', true)
            ->where('is_active', true)
            ->with('product')
            ->first()?->product;
    }

    /**
     * Calculate due date based on patient date of birth.
     */
    public function calculateDueDate($patientDob)
    {
        return \Carbon\Carbon::parse($patientDob)->addDays($this->age_days);
    }

    /**
     * Get display label (e.g., "BCG" or "OPV-1").
     */
    public function getDisplayLabelAttribute()
    {
        return $this->dose_label ?: $this->vaccine_name;
    }

    /**
     * Scope to filter by age range.
     */
    public function scopeForAge($query, $ageDays)
    {
        return $query->where('age_days', '<=', $ageDays);
    }

    /**
     * Scope to get items due within a range.
     */
    public function scopeDueWithin($query, $startDays, $endDays)
    {
        return $query->whereBetween('age_days', [$startDays, $endDays]);
    }
}
