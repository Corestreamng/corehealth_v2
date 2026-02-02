<?php

namespace App\Models\Accounting;

use App\Models\User;
use App\Models\Department;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * Fixed Asset Model
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 4.1B, 6.6
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 5.3
 *
 * Fixed Assets Register with IAS 16 compliant depreciation.
 * All book values are validated against journal entries.
 */
class FixedAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'fixed_assets';

    // Depreciation Methods
    public const METHOD_STRAIGHT_LINE = 'straight_line';
    public const METHOD_DECLINING_BALANCE = 'declining_balance';
    public const METHOD_DOUBLE_DECLINING = 'double_declining';
    public const METHOD_SUM_OF_YEARS = 'sum_of_years';
    public const METHOD_UNITS_OF_PRODUCTION = 'units_of_production';

    // Status
    public const STATUS_ACTIVE = 'active';
    public const STATUS_FULLY_DEPRECIATED = 'fully_depreciated';
    public const STATUS_DISPOSED = 'disposed';
    public const STATUS_IMPAIRED = 'impaired';
    public const STATUS_UNDER_MAINTENANCE = 'under_maintenance';
    public const STATUS_IDLE = 'idle';

    protected $fillable = [
        'asset_number',
        'name',
        'description',
        'category_id',
        'account_id',
        'journal_entry_id',
        'source_type',
        'source_id',
        'acquisition_cost',
        'additional_costs',
        'total_cost',
        'salvage_value',
        'depreciable_amount',
        'accumulated_depreciation',
        'book_value',
        'depreciation_method',
        'useful_life_years',
        'useful_life_months',
        'monthly_depreciation',
        'acquisition_date',
        'in_service_date',
        'last_depreciation_date',
        'disposal_date',
        'serial_number',
        'model_number',
        'manufacturer',
        'location',
        'department_id',
        'custodian_user_id',
        'warranty_expiry_date',
        'warranty_provider',
        'insurance_policy_number',
        'insurance_expiry_date',
        'supplier_id',
        'invoice_number',
        'status',
        'notes',
    ];

    protected $casts = [
        'acquisition_cost' => 'decimal:2',
        'additional_costs' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'depreciable_amount' => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'book_value' => 'decimal:2',
        'monthly_depreciation' => 'decimal:2',
        'useful_life_years' => 'integer',
        'useful_life_months' => 'integer',
        'acquisition_date' => 'date',
        'in_service_date' => 'date',
        'last_depreciation_date' => 'date',
        'disposal_date' => 'date',
        'warranty_expiry_date' => 'date',
        'insurance_expiry_date' => 'date',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function category()
    {
        return $this->belongsTo(FixedAssetCategory::class, 'category_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function custodian()
    {
        return $this->belongsTo(User::class, 'custodian_user_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function depreciations()
    {
        return $this->hasMany(FixedAssetDepreciation::class, 'fixed_asset_id');
    }

    public function disposals()
    {
        return $this->hasMany(FixedAssetDisposal::class, 'fixed_asset_id');
    }

    public function transfers()
    {
        return $this->hasMany(FixedAssetTransfer::class, 'fixed_asset_id');
    }

    public function maintenanceSchedules()
    {
        return $this->hasMany(EquipmentMaintenanceSchedule::class, 'fixed_asset_id');
    }

    /**
     * Get the source model (polymorphic).
     */
    public function source()
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }

    // ==========================================
    // DEPRECIATION CALCULATIONS
    // ==========================================

    /**
     * Calculate total cost from acquisition and additional costs.
     */
    public function calculateTotalCost(): float
    {
        return round($this->acquisition_cost + $this->additional_costs, 2);
    }

    /**
     * Calculate depreciable amount (total cost - salvage value).
     */
    public function calculateDepreciableAmount(): float
    {
        return round($this->total_cost - $this->salvage_value, 2);
    }

    /**
     * Calculate monthly depreciation based on method.
     */
    public function calculateMonthlyDepreciation(): float
    {
        $totalMonths = $this->useful_life_months ?? ($this->useful_life_years * 12);

        return match ($this->depreciation_method) {
            self::METHOD_STRAIGHT_LINE => $this->calculateStraightLineDepreciation($totalMonths),
            self::METHOD_DECLINING_BALANCE => $this->calculateDecliningBalanceDepreciation(),
            self::METHOD_DOUBLE_DECLINING => $this->calculateDoubleDecliningDepreciation(),
            default => $this->calculateStraightLineDepreciation($totalMonths),
        };
    }

    /**
     * Straight-line depreciation.
     */
    private function calculateStraightLineDepreciation(int $totalMonths): float
    {
        if ($totalMonths <= 0) {
            return 0;
        }
        return round($this->depreciable_amount / $totalMonths, 2);
    }

    /**
     * Declining balance depreciation (current month).
     */
    private function calculateDecliningBalanceDepreciation(): float
    {
        $rate = 1 / $this->useful_life_years;
        $currentBookValue = $this->book_value;

        // Don't depreciate below salvage value
        $maxDepreciation = max(0, $currentBookValue - $this->salvage_value);
        $calculatedDepreciation = round($currentBookValue * $rate / 12, 2);

        return min($calculatedDepreciation, $maxDepreciation);
    }

    /**
     * Double declining balance depreciation.
     */
    private function calculateDoubleDecliningDepreciation(): float
    {
        $rate = 2 / $this->useful_life_years;
        $currentBookValue = $this->book_value;

        // Don't depreciate below salvage value
        $maxDepreciation = max(0, $currentBookValue - $this->salvage_value);
        $calculatedDepreciation = round($currentBookValue * $rate / 12, 2);

        return min($calculatedDepreciation, $maxDepreciation);
    }

    /**
     * Calculate remaining months of useful life.
     */
    public function getRemainingLifeMonthsAttribute(): int
    {
        if (!$this->in_service_date) {
            return $this->useful_life_months ?? ($this->useful_life_years * 12);
        }

        $totalMonths = $this->useful_life_months ?? ($this->useful_life_years * 12);
        $monthsInService = Carbon::parse($this->in_service_date)->diffInMonths(now());

        return max(0, $totalMonths - $monthsInService);
    }

    /**
     * Check if asset needs depreciation this month.
     */
    public function needsDepreciation(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->book_value <= $this->salvage_value) {
            return false;
        }

        if (!$this->last_depreciation_date) {
            return $this->in_service_date && $this->in_service_date->lte(now());
        }

        // Check if already depreciated this month
        return $this->last_depreciation_date->format('Y-m') < now()->format('Y-m');
    }

    // ==========================================
    // BOOK VALUE MANAGEMENT
    // ==========================================

    /**
     * Record depreciation for this asset.
     */
    public function recordDepreciation(float $amount, ?int $processedBy = null): FixedAssetDepreciation
    {
        $depreciation = FixedAssetDepreciation::create([
            'fixed_asset_id' => $this->id,
            'depreciation_date' => now()->toDateString(),
            'year_number' => $this->getDepreciationYearNumber(),
            'month_number' => now()->month,
            'opening_book_value' => $this->book_value,
            'depreciation_amount' => $amount,
            'closing_book_value' => $this->book_value - $amount,
            'accumulated_depreciation_to_date' => $this->accumulated_depreciation + $amount,
            'calculation_method' => 'scheduled',
            'processed_by' => $processedBy ?? auth()->id(),
        ]);

        // Update asset
        $this->accumulated_depreciation += $amount;
        $this->book_value = $this->total_cost - $this->accumulated_depreciation;
        $this->last_depreciation_date = now();

        // Check if fully depreciated
        if ($this->book_value <= $this->salvage_value) {
            $this->status = self::STATUS_FULLY_DEPRECIATED;
        }

        $this->save();

        return $depreciation;
    }

    /**
     * Get current depreciation year number.
     */
    private function getDepreciationYearNumber(): int
    {
        if (!$this->in_service_date) {
            return 1;
        }

        return Carbon::parse($this->in_service_date)->diffInYears(now()) + 1;
    }

    // ==========================================
    // NUMBER GENERATION
    // ==========================================

    /**
     * Generate asset number.
     */
    public static function generateAssetNumber(?string $categoryCode = null): string
    {
        $prefix = $categoryCode ? strtoupper(substr($categoryCode, 0, 3)) : 'AST';
        $year = now()->format('Y');

        $lastAsset = self::where('asset_number', 'like', "{$prefix}-{$year}-%")
            ->orderBy('asset_number', 'desc')
            ->first();

        if ($lastAsset) {
            $lastNumber = (int) substr($lastAsset->asset_number, -5);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return "{$prefix}-{$year}-" . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    // ==========================================
    // STATUS HELPERS
    // ==========================================

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isFullyDepreciated(): bool
    {
        return $this->status === self::STATUS_FULLY_DEPRECIATED ||
               $this->book_value <= $this->salvage_value;
    }

    public function isDisposed(): bool
    {
        return $this->status === self::STATUS_DISPOSED;
    }

    public function canBeDisposed(): bool
    {
        return in_array($this->status, [
            self::STATUS_ACTIVE,
            self::STATUS_FULLY_DEPRECIATED,
            self::STATUS_IDLE,
        ]);
    }

    /**
     * Get status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_FULLY_DEPRECIATED => 'Fully Depreciated',
            self::STATUS_DISPOSED => 'Disposed',
            self::STATUS_IMPAIRED => 'Impaired',
            self::STATUS_UNDER_MAINTENANCE => 'Under Maintenance',
            self::STATUS_IDLE => 'Idle',
            default => ucfirst($this->status ?? 'Unknown'),
        };
    }

    /**
     * Get status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'success',
            self::STATUS_FULLY_DEPRECIATED => 'info',
            self::STATUS_DISPOSED => 'secondary',
            self::STATUS_IMPAIRED => 'warning',
            self::STATUS_UNDER_MAINTENANCE => 'primary',
            self::STATUS_IDLE => 'dark',
            default => 'secondary',
        };
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDepreciable($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE])
            ->whereColumn('book_value', '>', 'salvage_value');
    }

    public function scopeInCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeInDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeWarrantyExpiring($query, int $daysAhead = 30)
    {
        return $query->whereNotNull('warranty_expiry_date')
            ->whereBetween('warranty_expiry_date', [now(), now()->addDays($daysAhead)]);
    }

    public function scopeMaintenanceDue($query)
    {
        return $query->whereHas('maintenanceSchedules', function ($q) {
            $q->where('status', 'scheduled')
              ->where('scheduled_date', '<=', now());
        });
    }
}
