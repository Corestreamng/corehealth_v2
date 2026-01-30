<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Account Class Model
 *
 * Reference: Accounting System Plan §4.1 - Eloquent Models
 *
 * Represents the five main account classifications:
 * - ASSET (normal balance: debit)
 * - LIABILITY (normal balance: credit)
 * - EQUITY (normal balance: credit)
 * - INCOME (normal balance: credit, temporary)
 * - EXPENSE (normal balance: debit, temporary)
 */
class AccountClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'normal_balance',
        'display_order',
        'is_temporary',
        'cash_flow_category',
    ];

    protected $appends = ['class_code'];

    protected $casts = [
        'is_temporary' => 'boolean',
    ];

    // Normal balance constants
    const BALANCE_DEBIT = 'debit';
    const BALANCE_CREDIT = 'credit';

    // Cash flow categories
    const CASH_FLOW_OPERATING = 'operating';
    const CASH_FLOW_INVESTING = 'investing';
    const CASH_FLOW_FINANCING = 'financing';

    // Standard class codes
    const CODE_ASSET = '1';
    const CODE_LIABILITY = '2';
    const CODE_EQUITY = '3';
    const CODE_INCOME = '4';
    const CODE_EXPENSE = '5';

    /**
     * Accessor for backward compatibility with code using class_code.
     */
    public function getClassCodeAttribute(): string
    {
        return $this->code ?? '';
    }

    /**
     * Get the groups for this class.
     */
    public function groups(): HasMany
    {
        return $this->hasMany(AccountGroup::class)->orderBy('display_order');
    }

    /**
     * Check if this is a temporary account class (Income/Expense).
     * Temporary accounts are closed to retained earnings at year end.
     */
    public function isTemporary(): bool
    {
        return $this->is_temporary;
    }

    /**
     * Check if this class has a debit normal balance.
     */
    public function isDebitBalance(): bool
    {
        return $this->normal_balance === self::BALANCE_DEBIT;
    }

    /**
     * Check if this class has a credit normal balance.
     */
    public function isCreditBalance(): bool
    {
        return $this->normal_balance === self::BALANCE_CREDIT;
    }

    /**
     * Scope to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    /**
     * Scope to get temporary account classes (Income, Expense).
     */
    public function scopeTemporary($query)
    {
        return $query->where('is_temporary', true);
    }

    /**
     * Scope to get permanent account classes (Asset, Liability, Equity).
     */
    public function scopePermanent($query)
    {
        return $query->where('is_temporary', false);
    }
}
