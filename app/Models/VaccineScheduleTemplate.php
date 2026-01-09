<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class VaccineScheduleTemplate extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'description',
        'is_default',
        'is_active',
        'country',
        'created_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the schedule items for this template.
     */
    public function items()
    {
        return $this->hasMany(VaccineScheduleItem::class, 'template_id')
            ->orderBy('age_days')
            ->orderBy('sort_order');
    }

    /**
     * Get the user who created this template.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the default template.
     */
    public static function getDefault()
    {
        return self::where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all active templates.
     */
    public static function getActive()
    {
        return self::where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Set this template as the default (unset others).
     */
    public function setAsDefault()
    {
        self::where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->update(['is_default' => true]);
    }

    /**
     * Get unique vaccine names from this schedule.
     */
    public function getUniqueVaccines()
    {
        return $this->items()
            ->select('vaccine_name', 'vaccine_code')
            ->distinct()
            ->get();
    }
}
