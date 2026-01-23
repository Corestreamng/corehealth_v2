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
        'pay_frequency',
        'effective_from',
        'effective_to',
        'is_active',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
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
     */
    public function calculateGrossSalary(): float
    {
        $gross = $this->basic_salary;

        foreach ($this->items()->whereHas('payHead', function ($q) {
            $q->where('type', PayHead::TYPE_ADDITION);
        })->get() as $item) {
            $gross += $item->calculateAmount($this->basic_salary, $gross);
        }

        return round($gross, 2);
    }

    /**
     * Calculate total deductions
     */
    public function calculateTotalDeductions(): float
    {
        $gross = $this->calculateGrossSalary();
        $deductions = 0;

        foreach ($this->items()->whereHas('payHead', function ($q) {
            $q->where('type', PayHead::TYPE_DEDUCTION);
        })->get() as $item) {
            $deductions += $item->calculateAmount($this->basic_salary, $gross);
        }

        return round($deductions, 2);
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
