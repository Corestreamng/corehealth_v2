<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fixed Asset Category Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.6
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 5.2
 *
 * Categories for fixed assets with default depreciation settings.
 */
class FixedAssetCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fixed_asset_categories';

    // Depreciation Methods
    public const METHOD_STRAIGHT_LINE = 'straight_line';
    public const METHOD_DECLINING_BALANCE = 'declining_balance';
    public const METHOD_DOUBLE_DECLINING = 'double_declining';
    public const METHOD_SUM_OF_YEARS = 'sum_of_years';
    public const METHOD_UNITS_OF_PRODUCTION = 'units_of_production';

    protected $fillable = [
        'code',
        'name',
        'asset_account_id',
        'depreciation_account_id',
        'expense_account_id',
        'default_useful_life_years',
        'default_depreciation_method',
        'default_salvage_percentage',
        'is_depreciable',
        'description',
        'is_active',
    ];

    protected $casts = [
        'default_useful_life_years' => 'integer',
        'default_salvage_percentage' => 'decimal:2',
        'is_depreciable' => 'boolean',
        'is_active' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function assetAccount()
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    public function depreciationAccount()
    {
        return $this->belongsTo(Account::class, 'depreciation_account_id');
    }

    public function expenseAccount()
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    public function fixedAssets()
    {
        return $this->hasMany(FixedAsset::class, 'category_id');
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(\App\Models\PurchaseOrderItem::class, 'fixed_asset_category_id');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDepreciable($query)
    {
        return $query->where('is_depreciable', true);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    /**
     * Calculate default salvage value for a given cost.
     */
    public function calculateDefaultSalvageValue(float $cost): float
    {
        return round($cost * ($this->default_salvage_percentage / 100), 2);
    }

    /**
     * Calculate monthly depreciation using default settings.
     */
    public function calculateMonthlyDepreciation(float $depreciableAmount): float
    {
        if (!$this->is_depreciable) {
            return 0;
        }

        $totalMonths = $this->default_useful_life_years * 12;

        return match ($this->default_depreciation_method) {
            self::METHOD_STRAIGHT_LINE => round($depreciableAmount / $totalMonths, 2),
            default => round($depreciableAmount / $totalMonths, 2), // Default to straight-line
        };
    }

    /**
     * Get method label.
     */
    public function getDepreciationMethodLabelAttribute(): string
    {
        return match ($this->default_depreciation_method) {
            self::METHOD_STRAIGHT_LINE => 'Straight Line',
            self::METHOD_DECLINING_BALANCE => 'Declining Balance',
            self::METHOD_DOUBLE_DECLINING => 'Double Declining Balance',
            self::METHOD_SUM_OF_YEARS => 'Sum of Years Digits',
            self::METHOD_UNITS_OF_PRODUCTION => 'Units of Production',
            default => ucfirst(str_replace('_', ' ', $this->default_depreciation_method ?? '')),
        };
    }
}
