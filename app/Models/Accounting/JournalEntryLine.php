<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Journal Entry Line Model
 *
 * Reference: Accounting System Plan §4.1 - Eloquent Models
 *
 * Individual debit/credit line within a journal entry.
 * Each line affects exactly one account (and optionally a sub-account).
 */
class JournalEntryLine extends Model implements Auditable
{
    use HasFactory, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'journal_entry_id',
        'line_number',
        'account_id',
        'sub_account_id',
        'narration',
        'debit',
        'credit',
        'cash_flow_category',
    ];

    protected $casts = [
        'debit' => 'decimal:4',
        'credit' => 'decimal:4',
        'line_number' => 'integer',
    ];

    // =========================================
    // ATTRIBUTE ALIASES (for backward compatibility)
    // =========================================

    /**
     * Alias: debit_amount -> debit
     */
    public function getDebitAmountAttribute(): float
    {
        return (float) $this->debit;
    }

    /**
     * Alias: credit_amount -> credit
     */
    public function getCreditAmountAttribute(): float
    {
        return (float) $this->credit;
    }

    /**
     * Alias: description -> narration
     */
    public function getDescriptionAttribute(): ?string
    {
        return $this->narration;
    }

    /**
     * Alias: line_order -> line_number
     */
    public function getLineOrderAttribute(): ?int
    {
        return $this->line_number;
    }

    /**
     * Alias: account_sub_account_id -> sub_account_id
     */
    public function getAccountSubAccountIdAttribute(): ?int
    {
        return $this->sub_account_id;
    }

    // =========================================
    // RELATIONSHIPS
    // =========================================

    /**
     * Get the journal entry this line belongs to.
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get the account for this line.
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the sub-account for this line (optional).
     */
    public function subAccount(): BelongsTo
    {
        return $this->belongsTo(AccountSubAccount::class, 'sub_account_id');
    }

    /**
     * Check if this is a debit line.
     */
    public function isDebit(): bool
    {
        return $this->debit > 0;
    }

    /**
     * Check if this is a credit line.
     */
    public function isCredit(): bool
    {
        return $this->credit > 0;
    }

    /**
     * Get the line amount (debit or credit, whichever is non-zero).
     */
    public function getAmountAttribute(): float
    {
        return $this->isDebit() ? (float) $this->debit : (float) $this->credit;
    }

    /**
     * Get the line type (debit or credit).
     */
    public function getTypeAttribute(): string
    {
        return $this->isDebit() ? 'debit' : 'credit';
    }

    /**
     * Validate that line has either debit or credit, not both.
     */
    public function isValid(): bool
    {
        $hasDebit = $this->debit > 0;
        $hasCredit = $this->credit > 0;

        // Must have exactly one of debit or credit
        return ($hasDebit xor $hasCredit);
    }

    /**
     * Scope to get debit lines only.
     */
    public function scopeDebits($query)
    {
        return $query->where('debit', '>', 0);
    }

    /**
     * Scope to get credit lines only.
     */
    public function scopeCredits($query)
    {
        return $query->where('credit', '>', 0);
    }

    /**
     * Scope to filter by account.
     */
    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    /**
     * Scope to filter by sub-account.
     */
    public function scopeForSubAccount($query, int $subAccountId)
    {
        return $query->where('sub_account_id', $subAccountId);
    }

    /**
     * Scope to order by line number.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('line_number');
    }

    /**
     * Get formatted debit amount for display.
     */
    public function getFormattedDebitAttribute(): string
    {
        return $this->debit > 0 ? number_format($this->debit, 2) : '';
    }

    /**
     * Get formatted credit amount for display.
     */
    public function getFormattedCreditAttribute(): string
    {
        return $this->credit > 0 ? number_format($this->credit, 2) : '';
    }

    /**
     * Get the account display name with code.
     */
    public function getAccountDisplayAttribute(): string
    {
        if (!$this->account) {
            return '';
        }

        $display = $this->account->display_name;

        if ($this->subAccount) {
            $display .= ' → ' . $this->subAccount->display_name;
        }

        return $display;
    }
}
