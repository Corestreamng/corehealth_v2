<?php

namespace App\Models\HR;

use App\Models\Staff;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * HRMS Implementation Plan - Section 5.2
 * Payroll Item - Individual staff entry in a batch
 */
class PayrollItem extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'payroll_batch_id',
        'staff_id',
        'salary_profile_id',
        'days_in_month',
        'days_worked',
        'basic_salary',
        'full_gross_salary',
        'gross_salary',
        'total_additions',
        'total_deductions',
        'net_salary',
        'full_net_salary',
        'bank_name',
        'bank_account_number',
        'bank_account_name'
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'full_gross_salary' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'total_additions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
        'full_net_salary' => 'decimal:2',
    ];

    /**
     * Get the payroll batch
     */
    public function payrollBatch()
    {
        return $this->belongsTo(PayrollBatch::class);
    }

    /**
     * Get the staff member
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get the salary profile used
     */
    public function salaryProfile()
    {
        return $this->belongsTo(StaffSalaryProfile::class);
    }

    /**
     * Get the line item details
     */
    public function details()
    {
        return $this->hasMany(PayrollItemDetail::class);
    }

    /**
     * Get additions details
     */
    public function additions()
    {
        return $this->details()->where('type', PayHead::TYPE_ADDITION);
    }

    /**
     * Get deductions details
     */
    public function deductions()
    {
        return $this->details()->where('type', PayHead::TYPE_DEDUCTION);
    }

    /**
     * Get formatted net salary
     */
    public function getFormattedNetSalaryAttribute(): string
    {
        return '₦' . number_format($this->net_salary, 2);
    }

    /**
     * Get bank details summary
     */
    public function getBankDetailsSummaryAttribute(): string
    {
        if (!$this->bank_name || !$this->bank_account_number) {
            return 'No bank details';
        }

        $masked = '****' . substr($this->bank_account_number, -4);
        return "{$this->bank_name} - {$masked}";
    }
}
