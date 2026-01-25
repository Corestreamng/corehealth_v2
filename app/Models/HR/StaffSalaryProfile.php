<?php

namespace App\Models\HR;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * HRMS Implementation Plan - Section 5.2
 * Staff Salary Profile Model with versioning
 */
class StaffSalaryProfile extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'staff_id',
        'basic_salary',
        'gross_salary',
        'total_deductions',
        'net_salary',
        'pay_frequency',
        'effective_from',
        'effective_to',
        'is_active',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
    ];

    const FREQUENCY_MONTHLY = 'monthly';
    const FREQUENCY_BI_WEEKLY = 'bi_weekly';
    const FREQUENCY_WEEKLY = 'weekly';

    /**
     * Get the staff member
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get the creator
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get profile items (pay head mappings)
     */
    public function items()
    {
        return $this->hasMany(StaffSalaryProfileItem::class, 'salary_profile_id');
    }

    /**
     * Get payroll items generated from this profile
     */
    public function payrollItems()
    {
        return $this->hasMany(PayrollItem::class, 'salary_profile_id');
    }

    /**
     * Scope for active profiles
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for current profiles (no end date or end date in future)
     */
    public function scopeCurrent($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', now());
            });
    }

    /**
     * Calculate gross salary based on profile items
     * Uses two-pass approach: Fixed/Basic first, then Gross-based
     */
    public function calculateGrossSalary(): float
    {
        $basic = (float) $this->basic_salary;
        
        // Get all addition items
        $additionItems = $this->items()->whereHas('payHead', function ($q) {
            $q->where('type', PayHead::TYPE_ADDITION);
        })->get();

        // Pass 1: Calculate fixed and basic-percentage additions
        $fixedAndBasicTotal = 0;
        $grossBasedItems = [];
        
        foreach ($additionItems as $item) {
            if ($item->calculation_type === PayHead::CALC_PERCENTAGE) {
                if ($item->calculation_base === PayHead::BASE_BASIC_SALARY || $item->calculation_base === 'basic') {
                    // Percentage of basic
                    $fixedAndBasicTotal += round($basic * ($item->value / 100), 2);
                } else {
                    // Percentage of gross - save for pass 2
                    $grossBasedItems[] = $item;
                }
            } else {
                // Fixed amount
                $fixedAndBasicTotal += round((float) $item->value, 2);
            }
        }

        // Intermediate gross for gross-based calculations
        $intermediateGross = $basic + $fixedAndBasicTotal;

        // Pass 2: Calculate gross-percentage additions using intermediate gross
        $grossBasedTotal = 0;
        foreach ($grossBasedItems as $item) {
            $grossBasedTotal += round($intermediateGross * ($item->value / 100), 2);
        }

        return round($basic + $fixedAndBasicTotal + $grossBasedTotal, 2);
    }

    /**
     * Calculate total deductions
     * Fixed/Basic deductions first, then Gross-based (using final gross)
     */
    public function calculateTotalDeductions(): float
    {
        $basic = (float) $this->basic_salary;
        $gross = $this->calculateGrossSalary();
        
        // Get all deduction items
        $deductionItems = $this->items()->whereHas('payHead', function ($q) {
            $q->where('type', PayHead::TYPE_DEDUCTION);
        })->get();

        $totalDeductions = 0;
        
        foreach ($deductionItems as $item) {
            if ($item->calculation_type === PayHead::CALC_PERCENTAGE) {
                if ($item->calculation_base === PayHead::BASE_BASIC_SALARY || $item->calculation_base === 'basic') {
                    // Percentage of basic
                    $totalDeductions += round($basic * ($item->value / 100), 2);
                } else {
                    // Percentage of gross - use final gross
                    $totalDeductions += round($gross * ($item->value / 100), 2);
                }
            } else {
                // Fixed amount
                $totalDeductions += round((float) $item->value, 2);
            }
        }

        return round($totalDeductions, 2);
    }

    /**
     * Calculate net salary
     */
    public function calculateNetSalary(): float
    {
        return $this->calculateGrossSalary() - $this->calculateTotalDeductions();
    }

    /**
     * Get pay frequencies
     */
    public static function getPayFrequencies(): array
    {
        return [
            self::FREQUENCY_MONTHLY => 'Monthly',
            self::FREQUENCY_BI_WEEKLY => 'Bi-Weekly',
            self::FREQUENCY_WEEKLY => 'Weekly',
        ];
    }
}
