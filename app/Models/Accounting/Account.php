<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Account Model (General Ledger Account)
 *
 * Reference: Accounting System Plan §4.1 - Eloquent Models
 *
 * Core account in the Chart of Accounts.
 * All journal entry lines reference this model.
 */
class Account extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'account_group_id',
        'code',
        'name',
        'description',
        'bank_id',
        'is_active',
        'is_system',
        'is_bank_account',
        'cash_flow_category_override',
    ];

    protected $appends = ['account_code'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'is_bank_account' => 'boolean',
    ];

    /**
     * Get the group this account belongs to.
     */
    public function accountGroup(): BelongsTo
    {
        return $this->belongsTo(AccountGroup::class);
    }

    /**
     * Get the bank this account is associated with.
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Bank::class);
    }

    /**
     * Get the account class through the group.
     */
    public function accountClass(): BelongsTo
    {
        return $this->accountGroup->accountClass();
    }

    /**
     * Get the sub-accounts for this account.
     */
    public function subAccounts(): HasMany
    {
        return $this->hasMany(AccountSubAccount::class);
    }

    /**
     * Get all journal entry lines for this account.
     */
    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * Accessor for backward compatibility with views using account_code.
     */
    public function getAccountCodeAttribute(): string
    {
        return $this->code ?? '';
    }

    /**
     * Get the normal balance for this account (inherited from class).
     */
    public function getNormalBalanceAttribute(): string
    {
        return $this->accountGroup->accountClass->normal_balance ?? AccountClass::BALANCE_DEBIT;
    }

    /**
     * Check if this account has a debit normal balance.
     */
    public function isDebitBalance(): bool
    {
        return $this->normal_balance === AccountClass::BALANCE_DEBIT;
    }

    /**
     * Check if this account is a temporary account.
     */
    public function isTemporary(): bool
    {
        return $this->accountGroup->accountClass->is_temporary ?? false;
    }

    /**
     * Calculate account balance for a date range.
     *
     * THE CORE METHOD - Journal entries are the single source of truth.
     * Only POSTED journal entries are included in balance calculations.
     *
     * @param string|null $fromDate Start date (Y-m-d format, null = beginning of time)
     * @param string|null $toDate End date (Y-m-d format, null = today)
     * @param int|null $subAccountId Optional sub-account filter
     * @return float Balance (positive for normal balance, negative for contra)
     */
    public function getBalance(?string $fromDate = null, ?string $toDate = null, ?int $subAccountId = null): float
    {
        $query = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $this->id)
            ->where('journal_entries.status', JournalEntry::STATUS_POSTED);

        if ($fromDate) {
            $query->where('journal_entries.entry_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('journal_entries.entry_date', '<=', $toDate);
        }

        if ($subAccountId) {
            $query->where('journal_entry_lines.sub_account_id', $subAccountId);
        }

        $totals = $query->select([
            DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
            DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
        ])->first();

        $totalDebit = (float) ($totals->total_debit ?? 0);
        $totalCredit = (float) ($totals->total_credit ?? 0);

        // Return balance based on normal balance type
        // Debit-normal accounts: Debits increase, Credits decrease
        // Credit-normal accounts: Credits increase, Debits decrease
        if ($this->isDebitBalance()) {
            $balance = $totalDebit - $totalCredit;
        } else {
            $balance = $totalCredit - $totalDebit;
        }

        // Add opening balance if no from date is specified
        if (!$fromDate && $this->opening_balance) {
            $balance += (float) $this->opening_balance;
        }

        return round($balance, 4);
    }

    /**
     * Get debit total for a date range.
     *
     * @param string|null $fromDate
     * @param string|null $toDate
     * @return float
     */
    public function getDebitTotal(?string $fromDate = null, ?string $toDate = null): float
    {
        $query = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $this->id)
            ->where('journal_entries.status', JournalEntry::STATUS_POSTED);

        if ($fromDate) {
            $query->where('journal_entries.entry_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('journal_entries.entry_date', '<=', $toDate);
        }

        return (float) $query->sum('journal_entry_lines.debit');
    }

    /**
     * Get credit total for a date range.
     *
     * @param string|null $fromDate
     * @param string|null $toDate
     * @return float
     */
    public function getCreditTotal(?string $fromDate = null, ?string $toDate = null): float
    {
        $query = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $this->id)
            ->where('journal_entries.status', JournalEntry::STATUS_POSTED);

        if ($fromDate) {
            $query->where('journal_entries.entry_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('journal_entries.entry_date', '<=', $toDate);
        }

        return (float) $query->sum('journal_entry_lines.credit');
    }

    /**
     * Get account activity (all posted journal lines) for a date range.
     *
     * @param string|null $fromDate
     * @param string|null $toDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActivity(?string $fromDate = null, ?string $toDate = null)
    {
        $query = JournalEntryLine::query()
            ->with(['journalEntry', 'subAccount'])
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $this->id)
            ->where('journal_entries.status', JournalEntry::STATUS_POSTED)
            ->select('journal_entry_lines.*');

        if ($fromDate) {
            $query->where('journal_entries.entry_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('journal_entries.entry_date', '<=', $toDate);
        }

        return $query->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.entry_number')
            ->get();
    }

    /**
     * Check if account can be deleted (no journal lines).
     */
    public function canDelete(): bool
    {
        return !$this->is_system && $this->journalLines()->count() === 0;
    }

    /**
     * Check if account can be deactivated.
     */
    public function canDeactivate(): bool
    {
        // Can deactivate if no pending journal entries
        return $this->journalLines()
            ->whereHas('journalEntry', function ($q) {
                $q->whereIn('status', [JournalEntry::STATUS_DRAFT, JournalEntry::STATUS_PENDING]);
            })
            ->count() === 0;
    }

    /**
     * Scope to get active accounts only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get bank accounts only.
     */
    public function scopeBankAccounts($query)
    {
        return $query->where('is_bank_account', true);
    }

    /**
     * Scope to get accounts allowing sub-accounts.
     */
    public function scopeWithSubAccounts($query)
    {
        return $query->where('allow_sub_accounts', true);
    }

    /**
     * Get full account code with class prefix.
     */
    public function getFullCodeAttribute(): string
    {
        $classCode = $this->accountGroup->accountClass->code ?? '';
        $groupCode = $this->accountGroup->code ?? '';
        return $classCode . $groupCode . $this->code;
    }

    /**
     * Get full display name with code.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->full_code . ' - ' . $this->name;
    }
}
