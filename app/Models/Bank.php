<?php

namespace App\Models;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Bank Model (Enhanced)
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 1
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 1.1
 *
 * CRITICAL: Balance is ALWAYS calculated from journal_entries.
 * The cached fields are for reference only.
 */
class Bank extends Model
{
    use HasFactory;

    // Bank Types
    public const TYPE_CURRENT = 'current';
    public const TYPE_SAVINGS = 'savings';
    public const TYPE_FIXED_DEPOSIT = 'fixed_deposit';
    public const TYPE_MONEY_MARKET = 'money_market';

    // Status
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'name',
        'account_number',
        'account_name',
        'bank_code',
        'description',
        'is_active',
        // Enhanced fields
        'account_id',
        'bank_type',
        'last_statement_date',
        'last_statement_balance',
        'statement_closing_day',
        'overdraft_limit',
        'minimum_balance',
        'swift_code',
        'branch_name',
        'branch_code',
        'contact_person',
        'contact_phone',
        'contact_email',
        'signatories',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_statement_date' => 'date',
        'last_statement_balance' => 'decimal:2',
        'statement_closing_day' => 'integer',
        'overdraft_limit' => 'decimal:2',
        'minimum_balance' => 'decimal:2',
        'signatories' => 'array',
    ];

    protected $appends = [
        'current_balance',
        'available_balance',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    /**
     * GL Account linked to this bank.
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Payments received via this bank.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Transfers FROM this bank.
     */
    public function outgoingTransfers()
    {
        return $this->hasMany(InterAccountTransfer::class, 'from_bank_id');
    }

    /**
     * Transfers TO this bank.
     */
    public function incomingTransfers()
    {
        return $this->hasMany(InterAccountTransfer::class, 'to_bank_id');
    }

    // ==========================================
    // BALANCE CALCULATIONS (JE CENTRIC)
    // ==========================================

    /**
     * Get current balance from journal entries.
     *
     * CRITICAL: This is the ONLY source of truth for balance.
     *
     * @param string|null $asOfDate
     * @return float
     */
    public function getBalanceFromJournalEntries(?string $asOfDate = null): float
    {
        if (!$this->account_id) {
            return 0.0;
        }

        return $this->account->getBalance(null, $asOfDate);
    }

    /**
     * Accessor for current_balance.
     */
    public function getCurrentBalanceAttribute(): float
    {
        return $this->getBalanceFromJournalEntries();
    }

    /**
     * Get available balance (current - minimum + overdraft).
     */
    public function getAvailableBalanceAttribute(): float
    {
        $current = $this->current_balance;
        $minimum = (float) ($this->minimum_balance ?? 0);
        $overdraft = (float) ($this->overdraft_limit ?? 0);

        return $current - $minimum + $overdraft;
    }

    /**
     * Check if balance is below minimum.
     */
    public function isBelowMinimum(): bool
    {
        return $this->current_balance < ($this->minimum_balance ?? 0);
    }

    /**
     * Check if bank is in overdraft.
     */
    public function isInOverdraft(): bool
    {
        return $this->current_balance < 0;
    }

    /**
     * Get balance for a specific date range.
     */
    public function getBalanceForPeriod(string $fromDate, string $toDate): float
    {
        if (!$this->account_id) {
            return 0.0;
        }

        return $this->account->getBalance($fromDate, $toDate);
    }

    /**
     * Get transaction history from journal entries.
     */
    public function getTransactions(?string $fromDate = null, ?string $toDate = null, ?int $limit = null)
    {
        if (!$this->account_id) {
            return collect();
        }

        $query = JournalEntryLine::query()
            ->with(['journalEntry', 'subAccount'])
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $this->account_id)
            ->where('journal_entries.status', JournalEntry::STATUS_POSTED)
            ->select('journal_entry_lines.*')
            ->orderBy('journal_entries.entry_date', 'desc')
            ->orderBy('journal_entries.id', 'desc');

        if ($fromDate) {
            $query->where('journal_entries.entry_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->where('journal_entries.entry_date', '<=', $toDate);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    // ==========================================
    // RECONCILIATION HELPERS
    // ==========================================

    /**
     * Get unreconciled transactions.
     */
    public function getUnreconciledTransactions(?string $fromDate = null, ?string $toDate = null)
    {
        // This will be populated when reconciliation module is complete
        return $this->getTransactions($fromDate, $toDate);
    }

    /**
     * Get statement balance variance.
     */
    public function getStatementVariance(): float
    {
        if (!$this->last_statement_balance) {
            return 0.0;
        }

        $glBalance = $this->getBalanceFromJournalEntries($this->last_statement_date?->format('Y-m-d'));
        return round((float)$this->last_statement_balance - $glBalance, 2);
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('bank_type', $type);
    }

    public function scopeWithGlAccount($query)
    {
        return $query->whereNotNull('account_id');
    }

    public function scopeBelowMinimum($query)
    {
        // This requires a subquery for JE-based balance
        return $query->whereHas('account', function ($q) {
            // Complex subquery - simplified for now
        });
    }

    // ==========================================
    // HELPERS
    // ==========================================

    /**
     * Get bank type label.
     */
    public function getBankTypeLabelAttribute(): string
    {
        return match ($this->bank_type) {
            self::TYPE_CURRENT => 'Current Account',
            self::TYPE_SAVINGS => 'Savings Account',
            self::TYPE_FIXED_DEPOSIT => 'Fixed Deposit',
            self::TYPE_MONEY_MARKET => 'Money Market',
            default => ucfirst($this->bank_type ?? 'Unknown'),
        };
    }

    /**
     * Get full bank display name.
     */
    public function getFullNameAttribute(): string
    {
        $parts = [$this->name];

        if ($this->account_number) {
            $parts[] = '(' . substr($this->account_number, -4) . ')';
        }

        return implode(' ', $parts);
    }

    /**
     * Get masked account number.
     */
    public function getMaskedAccountNumberAttribute(): string
    {
        if (!$this->account_number) {
            return 'N/A';
        }

        $length = strlen($this->account_number);
        if ($length <= 4) {
            return $this->account_number;
        }

        return str_repeat('*', $length - 4) . substr($this->account_number, -4);
    }

    /**
     * Alias for name (backward compatibility).
     */
    public function getBankNameAttribute(): string
    {
        return $this->name;
    }
}
