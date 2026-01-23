<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * HRMS Implementation Plan - Section 5.2
 * Payroll Item Detail - Line items for each staff's payroll
 */
class PayrollItemDetail extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'payroll_item_id',
        'pay_head_id',
        'type',
        'pay_head_name',
        'amount'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Get the payroll item
     */
    public function payrollItem()
    {
        return $this->belongsTo(PayrollItem::class);
    }

    /**
     * Get the pay head
     */
    public function payHead()
    {
        return $this->belongsTo(PayHead::class);
    }

    /**
     * Check if this is an addition
     */
    public function isAddition(): bool
    {
        return $this->type === PayHead::TYPE_ADDITION;
    }

    /**
     * Check if this is a deduction
     */
    public function isDeduction(): bool
    {
        return $this->type === PayHead::TYPE_DEDUCTION;
    }

    /**
     * Get formatted amount
     */
    public function getFormattedAmountAttribute(): string
    {
        return '₦' . number_format($this->amount, 2);
    }

    /**
     * Get type badge
     */
    public function getTypeBadgeAttribute(): string
    {
        return $this->isAddition() ? 'success' : 'danger';
    }
}
