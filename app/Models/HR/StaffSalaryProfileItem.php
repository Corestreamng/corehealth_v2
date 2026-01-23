<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * HRMS Implementation Plan - Section 5.2
 * Staff Salary Profile Item - Pay head mapping to profile
 */
class StaffSalaryProfileItem extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'salary_profile_id',
        'pay_head_id',
        'calculation_type',
        'calculation_base',
        'value'
    ];

    protected $casts = [
        'value' => 'decimal:4',
    ];

    /**
     * Get the salary profile
     */
    public function salaryProfile()
    {
        return $this->belongsTo(StaffSalaryProfile::class, 'salary_profile_id');
    }

    /**
     * Get the pay head
     */
    public function payHead()
    {
        return $this->belongsTo(PayHead::class);
    }

    /**
     * Calculate the amount based on calculation type
     */
    public function calculateAmount(float $basicSalary, float $grossSalary): float
    {
        switch ($this->calculation_type) {
            case PayHead::CALC_PERCENTAGE:
                $base = $this->calculation_base === PayHead::BASE_BASIC_SALARY
                    ? $basicSalary
                    : $grossSalary;
                return round($base * ($this->value / 100), 2);

            case PayHead::CALC_FIXED:
            default:
                return round($this->value, 2);
        }
    }

    /**
     * Get formatted value display
     */
    public function getFormattedValueAttribute(): string
    {
        if ($this->calculation_type === PayHead::CALC_PERCENTAGE) {
            return number_format($this->value, 2) . '%';
        }

        return '₦' . number_format($this->value, 2);
    }

    /**
     * Get calculation description
     */
    public function getCalculationDescriptionAttribute(): string
    {
        if ($this->calculation_type === PayHead::CALC_PERCENTAGE) {
            $base = $this->calculation_base === PayHead::BASE_BASIC_SALARY
                ? 'Basic Salary'
                : 'Gross Salary';
            return "{$this->value}% of {$base}";
        }

        return 'Fixed: ₦' . number_format($this->value, 2);
    }
}
