<?php

namespace App\Models\Accounting;

use App\Models\User;
use App\Models\Department;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

/**
 * Lease Model (IFRS 16 Compliant)
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.13
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 6.2
 *
 * Manages leases with IFRS 16 Right-of-Use asset and Lease Liability tracking.
 */
class Lease extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'leases';

    // Lease Types
    public const TYPE_OPERATING = 'operating';
    public const TYPE_FINANCE = 'finance';
    public const TYPE_SHORT_TERM = 'short_term';
    public const TYPE_LOW_VALUE = 'low_value';

    // Status
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_TERMINATED = 'terminated';
    public const STATUS_PURCHASED = 'purchased';

    protected $fillable = [
        'lease_number',
        'lease_type',
        'leased_item',
        'description',
        'lessor_id',
        'lessor_name',
        'lessor_contact',
        'rou_asset_account_id',
        'lease_liability_account_id',
        'depreciation_account_id',
        'interest_account_id',
        'commencement_date',
        'end_date',
        'lease_term_months',
        'monthly_payment',
        'annual_rent_increase_rate',
        'incremental_borrowing_rate',
        'total_lease_payments',
        'initial_rou_asset_value',
        'initial_lease_liability',
        'current_rou_asset_value',
        'accumulated_rou_depreciation',
        'current_lease_liability',
        'initial_direct_costs',
        'lease_incentives_received',
        'has_purchase_option',
        'purchase_option_amount',
        'purchase_option_reasonably_certain',
        'has_termination_option',
        'earliest_termination_date',
        'termination_penalty',
        'residual_value_guarantee',
        'asset_location',
        'department_id',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'monthly_payment' => 'decimal:2',
        'annual_rent_increase_rate' => 'decimal:2',
        'incremental_borrowing_rate' => 'decimal:4',
        'total_lease_payments' => 'decimal:2',
        'initial_rou_asset_value' => 'decimal:2',
        'initial_lease_liability' => 'decimal:2',
        'current_rou_asset_value' => 'decimal:2',
        'accumulated_rou_depreciation' => 'decimal:2',
        'current_lease_liability' => 'decimal:2',
        'initial_direct_costs' => 'decimal:2',
        'lease_incentives_received' => 'decimal:2',
        'purchase_option_amount' => 'decimal:2',
        'termination_penalty' => 'decimal:2',
        'residual_value_guarantee' => 'decimal:2',
        'commencement_date' => 'date',
        'end_date' => 'date',
        'earliest_termination_date' => 'date',
        'lease_term_months' => 'integer',
        'has_purchase_option' => 'boolean',
        'purchase_option_reasonably_certain' => 'boolean',
        'has_termination_option' => 'boolean',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function lessor()
    {
        return $this->belongsTo(Supplier::class, 'lessor_id');
    }

    public function rouAssetAccount()
    {
        return $this->belongsTo(Account::class, 'rou_asset_account_id');
    }

    public function leaseLiabilityAccount()
    {
        return $this->belongsTo(Account::class, 'lease_liability_account_id');
    }

    public function depreciationAccount()
    {
        return $this->belongsTo(Account::class, 'depreciation_account_id');
    }

    public function interestAccount()
    {
        return $this->belongsTo(Account::class, 'interest_account_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paymentSchedules()
    {
        return $this->hasMany(LeasePaymentSchedule::class, 'lease_id');
    }

    public function modifications()
    {
        return $this->hasMany(LeaseModification::class, 'lease_id');
    }

    // ==========================================
    // IFRS 16 CALCULATIONS
    // ==========================================

    /**
     * Calculate present value of lease payments (initial measurement).
     */
    public function calculateInitialLeaseLiability(): float
    {
        $monthlyRate = ($this->incremental_borrowing_rate / 100) / 12;
        $pv = 0;

        for ($i = 1; $i <= $this->lease_term_months; $i++) {
            // Account for annual escalation
            $year = ceil($i / 12);
            $escalationFactor = pow(1 + ($this->annual_rent_increase_rate / 100), $year - 1);
            $adjustedPayment = $this->monthly_payment * $escalationFactor;

            $pv += $adjustedPayment / pow(1 + $monthlyRate, $i);
        }

        // Add purchase option if reasonably certain
        if ($this->has_purchase_option && $this->purchase_option_reasonably_certain) {
            $pv += $this->purchase_option_amount / pow(1 + $monthlyRate, $this->lease_term_months);
        }

        // Add residual value guarantee
        if ($this->residual_value_guarantee > 0) {
            $pv += $this->residual_value_guarantee / pow(1 + $monthlyRate, $this->lease_term_months);
        }

        return round($pv, 2);
    }

    /**
     * Calculate initial ROU asset value.
     */
    public function calculateInitialRouAsset(): float
    {
        return round(
            $this->initial_lease_liability +
            $this->initial_direct_costs -
            $this->lease_incentives_received,
            2
        );
    }

    /**
     * Calculate monthly ROU depreciation (straight-line).
     */
    public function calculateMonthlyRouDepreciation(): float
    {
        if ($this->lease_term_months <= 0) {
            return 0;
        }

        return round($this->initial_rou_asset_value / $this->lease_term_months, 2);
    }

    /**
     * Calculate interest expense for current period.
     */
    public function calculateCurrentInterest(): float
    {
        $monthlyRate = ($this->incremental_borrowing_rate / 100) / 12;
        return round($this->current_lease_liability * $monthlyRate, 2);
    }

    /**
     * Check if lease qualifies for IFRS 16 exemption.
     */
    public function isExemptFromIfrs16(): bool
    {
        return in_array($this->lease_type, [self::TYPE_SHORT_TERM, self::TYPE_LOW_VALUE]);
    }

    // ==========================================
    // NUMBER GENERATION
    // ==========================================

    public static function generateNumber(): string
    {
        $prefix = 'LSE-';
        $year = now()->format('Y');

        $last = self::where('lease_number', 'like', $prefix . $year . '%')
            ->orderBy('lease_number', 'desc')
            ->first();

        $nextNumber = $last ? ((int) substr($last->lease_number, -5)) + 1 : 1;

        return $prefix . $year . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    // ==========================================
    // LIFECYCLE METHODS
    // ==========================================

    /**
     * Initialize IFRS 16 values on lease commencement.
     */
    public function commence(): void
    {
        if (!$this->isExemptFromIfrs16()) {
            $this->initial_lease_liability = $this->calculateInitialLeaseLiability();
            $this->initial_rou_asset_value = $this->calculateInitialRouAsset();
            $this->current_lease_liability = $this->initial_lease_liability;
            $this->current_rou_asset_value = $this->initial_rou_asset_value;
        }

        $this->total_lease_payments = $this->monthly_payment * $this->lease_term_months;
        $this->status = self::STATUS_ACTIVE;
        $this->save();
    }

    /**
     * Get remaining months.
     */
    public function getRemainingMonthsAttribute(): int
    {
        return max(0, Carbon::parse($this->end_date)->diffInMonths(now()));
    }

    /**
     * Get net ROU asset value.
     */
    public function getNetRouAssetValueAttribute(): float
    {
        return round($this->current_rou_asset_value - $this->accumulated_rou_depreciation, 2);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeRequiresIfrs16($query)
    {
        return $query->whereNotIn('lease_type', [self::TYPE_SHORT_TERM, self::TYPE_LOW_VALUE]);
    }

    public function scopeExpiringWithin($query, int $days)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereBetween('end_date', [now(), now()->addDays($days)]);
    }

    // ==========================================
    // HELPERS
    // ==========================================

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_TERMINATED => 'Terminated',
            self::STATUS_PURCHASED => 'Purchased',
            default => ucfirst($this->status ?? 'Unknown'),
        };
    }

    public function getLeaseTypeLabelAttribute(): string
    {
        return match ($this->lease_type) {
            self::TYPE_OPERATING => 'Operating Lease',
            self::TYPE_FINANCE => 'Finance Lease',
            self::TYPE_SHORT_TERM => 'Short-term (IFRS 16 Exempt)',
            self::TYPE_LOW_VALUE => 'Low-value (IFRS 16 Exempt)',
            default => ucfirst($this->lease_type ?? 'Unknown'),
        };
    }
}
