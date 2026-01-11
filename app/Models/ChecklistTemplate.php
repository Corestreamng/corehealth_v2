<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * ChecklistTemplate Model
 *
 * Defines reusable checklist templates for admission/discharge processes.
 * Templates contain items that are copied to actual checklists when used.
 *
 * @see App\Models\ChecklistTemplateItem
 * @see App\Models\AdmissionChecklist
 * @see App\Models\DischargeChecklist
 */
class ChecklistTemplate extends Model implements Auditable
{
    use HasFactory;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'type',
        'description',
        'is_default',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Template types
     */
    public const TYPE_ADMISSION = 'admission';
    public const TYPE_DISCHARGE = 'discharge';

    // =====================
    // Relationships
    // =====================

    /**
     * Items in this template
     */
    public function items()
    {
        return $this->hasMany(ChecklistTemplateItem::class, 'template_id')
            ->orderBy('sort_order');
    }

    /**
     * User who created this template
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // =====================
    // Scopes
    // =====================

    /**
     * Filter active templates only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Filter by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Filter admission templates
     */
    public function scopeAdmission($query)
    {
        return $query->where('type', self::TYPE_ADMISSION);
    }

    /**
     * Filter discharge templates
     */
    public function scopeDischarge($query)
    {
        return $query->where('type', self::TYPE_DISCHARGE);
    }

    // =====================
    // Static Helpers
    // =====================

    /**
     * Get default admission template
     */
    public static function getDefaultAdmission()
    {
        return self::active()
            ->admission()
            ->where('is_default', true)
            ->first()
            ?? self::active()->admission()->first();
    }

    /**
     * Get default discharge template
     */
    public static function getDefaultDischarge()
    {
        return self::active()
            ->discharge()
            ->where('is_default', true)
            ->first()
            ?? self::active()->discharge()->first();
    }
}
