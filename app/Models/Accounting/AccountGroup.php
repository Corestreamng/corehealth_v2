<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Account Group Model
 *
 * Reference: Accounting System Plan §4.1 - Eloquent Models
 *
 * Groups accounts within a class for reporting purposes.
 * Examples: Current Assets, Fixed Assets, Operating Income, etc.
 */
class AccountGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_class_id',
        'code',
        'name',
        'description',
        'display_order',
    ];

    protected $appends = ['group_code'];

    /**
     * Accessor for backward compatibility with code using group_code.
     */
    public function getGroupCodeAttribute(): string
    {
        return $this->code ?? '';
    }

    /**
     * Get the class this group belongs to.
     */
    public function accountClass(): BelongsTo
    {
        return $this->belongsTo(AccountClass::class);
    }

    /**
     * Get the accounts in this group.
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class)->orderBy('code');
    }

    /**
     * Get the normal balance inherited from the class.
     */
    public function getNormalBalanceAttribute(): string
    {
        return $this->accountClass->normal_balance ?? AccountClass::BALANCE_DEBIT;
    }

    /**
     * Check if this group contains temporary accounts.
     */
    public function isTemporary(): bool
    {
        return $this->accountClass->isTemporary();
    }

    /**
     * Scope to order by display order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    /**
     * Scope to get groups for a specific class.
     */
    public function scopeForClass($query, $classId)
    {
        return $query->where('account_class_id', $classId);
    }

    /**
     * Calculate total balance for all accounts in this group.
     *
     * @param string|null $fromDate
     * @param string|null $toDate
     * @return float
     */
    public function getBalance(?string $fromDate = null, ?string $toDate = null): float
    {
        $balance = 0;

        foreach ($this->accounts as $account) {
            $balance += $account->getBalance($fromDate, $toDate);
        }

        return $balance;
    }
}
