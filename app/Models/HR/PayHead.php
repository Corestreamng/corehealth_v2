<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * HRMS Implementation Plan - Section 5.2
 * Pay Head Model for Additions and Deductions
 */
class PayHead extends Model implements Auditable
{
    use SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'code',
        'description',
        'type',
        'calculation_type',
        'calculation_base',
        'default_value',
        'is_taxable',
        'is_mandatory',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'default_value' => 'decimal:2',
        'is_taxable' => 'boolean',
        'is_mandatory' => 'boolean',
        'is_active' => 'boolean',
    ];

    const TYPE_ADDITION = 'addition';
    const TYPE_DEDUCTION = 'deduction';

    const CALC_FIXED = 'fixed';
    const CALC_PERCENTAGE = 'percentage';
    const CALC_FORMULA = 'formula';

    const BASE_BASIC_SALARY = 'basic_salary';
    const BASE_GROSS_SALARY = 'gross_salary';

    /**
     * Get salary profile items using this pay head
     */
    public function salaryProfileItems()
    {
        return $this->hasMany(StaffSalaryProfileItem::class);
    }

    /**
     * Get payroll item details using this pay head
     */
    public function payrollItemDetails()
    {
        return $this->hasMany(PayrollItemDetail::class);
    }

    /**
     * Scope for active pay heads
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for additions
     */
    public function scopeAdditions($query)
    {
        return $query->where('type', self::TYPE_ADDITION);
    }

    /**
     * Scope for deductions
     */
    public function scopeDeductions($query)
    {
        return $query->where('type', self::TYPE_DEDUCTION);
    }

    /**
     * Scope for mandatory pay heads
     */
    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }

    /**
     * Check if this is an addition
     */
    public function isAddition(): bool
    {
        return $this->type === self::TYPE_ADDITION;
    }

    /**
     * Check if this is a deduction
     */
    public function isDeduction(): bool
    {
        return $this->type === self::TYPE_DEDUCTION;
    }

    /**
     * Get type badge
     */
    public function getTypeBadgeAttribute(): string
    {
        return $this->isAddition() ? 'success' : 'danger';
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute(): string
    {
        return $this->isAddition() ? 'Addition' : 'Deduction';
    }

    /**
     * Get static types
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_ADDITION => 'Addition (Earnings)',
            self::TYPE_DEDUCTION => 'Deduction',
        ];
    }

    /**
     * Get static calculation types
     */
    public static function getCalculationTypes(): array
    {
        return [
            self::CALC_FIXED => 'Fixed Amount',
            self::CALC_PERCENTAGE => 'Percentage',
            self::CALC_FORMULA => 'Formula',
        ];
    }

    /**
     * Get static calculation bases
     */
    public static function getCalculationBases(): array
    {
        return [
            self::BASE_BASIC_SALARY => 'Basic Salary',
            self::BASE_GROSS_SALARY => 'Gross Salary',
        ];
    }
}
