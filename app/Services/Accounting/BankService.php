<?php

namespace App\Services\Accounting;

use App\Models\Bank;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bank Service
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 8.2
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 1.2
 *
 * Manages bank operations with JE-centric balance calculations.
 */
class BankService
{
    /**
     * Get all banks with their computed balances.
     */
    public function getAll(bool $activeOnly = true): Collection
    {
        $query = Bank::with(['account.accountGroup']);

        if ($activeOnly) {
            $query->active();
        }

        return $query->get()->map(function ($bank) {
            return $this->enrichBankData($bank);
        });
    }

    /**
     * Get bank by ID with balance.
     */
    public function getById(int $id): ?Bank
    {
        $bank = Bank::with(['account.accountGroup'])->find($id);

        if ($bank) {
            return $this->enrichBankData($bank);
        }

        return null;
    }

    /**
     * Get banks by type.
     */
    public function getByType(string $type): Collection
    {
        return Bank::with(['account'])
            ->active()
            ->ofType($type)
            ->get()
            ->map(function ($bank) {
                return $this->enrichBankData($bank);
            });
    }

    /**
     * Calculate balance from journal entries.
     *
     * CRITICAL: This is the ONLY source of truth.
     */
    public function calculateBalance(Bank $bank, ?string $asOfDate = null): float
    {
        if (!$bank->account_id) {
            return 0.0;
        }

        return $bank->account->getBalance(null, $asOfDate);
    }

    /**
     * Get balance from journal entries for a specific account.
     */
    public function getBalanceFromJournalEntries(int $accountId, ?string $asOfDate = null): float
    {
        $account = Account::find($accountId);

        if (!$account) {
            return 0.0;
        }

        return $account->getBalance(null, $asOfDate);
    }

    /**
     * Get bank dashboard data.
     */
    public function getBankDashboardData(): array
    {
        $banks = $this->getAll(true);

        $totalBalance = $banks->sum('computed_balance');
        $totalAvailable = $banks->sum('available_balance');

        // Group by bank type
        $byType = $banks->groupBy('bank_type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'total_balance' => $group->sum('computed_balance'),
            ];
        });

        // Banks needing attention
        $lowBalance = $banks->filter(function ($bank) {
            return $bank->computed_balance < ($bank->minimum_balance ?? 0);
        });

        $inOverdraft = $banks->filter(function ($bank) {
            return $bank->computed_balance < 0;
        });

        // Recent transactions (last 7 days)
        $recentTransactions = $this->getRecentTransactions(7);

        return [
            'total_banks' => $banks->count(),
            'total_balance' => round($totalBalance, 2),
            'total_available' => round($totalAvailable, 2),
            'by_type' => $byType->toArray(),
            'low_balance_count' => $lowBalance->count(),
            'overdraft_count' => $inOverdraft->count(),
            'recent_transaction_count' => $recentTransactions->count(),
            'banks' => $banks->toArray(),
        ];
    }

    /**
     * Sync balance from journal entries to cached field.
     *
     * This updates the cached balance for quick display.
     * The authoritative balance is ALWAYS from JE.
     */
    public function syncBalanceFromJournalEntries(Bank $bank): void
    {
        if (!$bank->account_id) {
            return;
        }

        // Balance is computed on-the-fly via accessor
        // No cached field to update in enhanced model
    }

    /**
     * Get transaction history for a bank.
     */
    public function getTransactions(
        Bank $bank,
        ?string $fromDate = null,
        ?string $toDate = null,
        ?int $limit = null
    ): Collection {
        if (!$bank->account_id) {
            return collect();
        }

        $query = JournalEntryLine::query()
            ->with(['journalEntry', 'subAccount'])
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $bank->account_id)
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

    /**
     * Get recent transactions across all banks.
     */
    public function getRecentTransactions(int $days = 7): Collection
    {
        $bankAccountIds = Bank::active()
            ->whereNotNull('account_id')
            ->pluck('account_id');

        if ($bankAccountIds->isEmpty()) {
            return collect();
        }

        return JournalEntryLine::query()
            ->with(['journalEntry', 'account'])
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entry_lines.account_id', $bankAccountIds)
            ->where('journal_entries.status', JournalEntry::STATUS_POSTED)
            ->where('journal_entries.entry_date', '>=', now()->subDays($days)->toDateString())
            ->select('journal_entry_lines.*')
            ->orderBy('journal_entries.entry_date', 'desc')
            ->orderBy('journal_entries.id', 'desc')
            ->limit(100)
            ->get();
    }

    /**
     * Get cash flow summary for a bank.
     */
    public function getCashFlowSummary(
        Bank $bank,
        string $fromDate,
        string $toDate
    ): array {
        if (!$bank->account_id) {
            return [
                'inflows' => 0,
                'outflows' => 0,
                'net_flow' => 0,
                'opening_balance' => 0,
                'closing_balance' => 0,
            ];
        }

        $openingBalance = $bank->account->getBalance(null, date('Y-m-d', strtotime($fromDate) - 86400));

        $transactions = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $bank->account_id)
            ->where('journal_entries.status', JournalEntry::STATUS_POSTED)
            ->whereBetween('journal_entries.entry_date', [$fromDate, $toDate])
            ->select([
                DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
            ])
            ->first();

        $inflows = (float) ($transactions->total_debit ?? 0);
        $outflows = (float) ($transactions->total_credit ?? 0);
        $netFlow = $inflows - $outflows;
        $closingBalance = $openingBalance + $netFlow;

        return [
            'inflows' => round($inflows, 2),
            'outflows' => round($outflows, 2),
            'net_flow' => round($netFlow, 2),
            'opening_balance' => round($openingBalance, 2),
            'closing_balance' => round($closingBalance, 2),
        ];
    }

    /**
     * Get banks requiring attention (low balance, overdraft, etc.).
     */
    public function getBanksRequiringAttention(): Collection
    {
        $banks = $this->getAll(true);

        return $banks->filter(function ($bank) {
            // Check minimum balance
            if ($bank->minimum_balance > 0 && $bank->computed_balance < $bank->minimum_balance) {
                return true;
            }

            // Check overdraft
            if ($bank->computed_balance < 0) {
                return true;
            }

            // Check pending reconciliation (statement date is past)
            if ($bank->last_statement_date && $bank->last_statement_date->addMonth()->isPast()) {
                return true;
            }

            return false;
        });
    }

    /**
     * Get bank for dropdown selection.
     */
    public function getBankOptions(): Collection
    {
        return Bank::active()
            ->select('id', 'name', 'account_number', 'bank_type', 'account_id')
            ->orderBy('name')
            ->get()
            ->map(function ($bank) {
                return [
                    'id' => $bank->id,
                    'name' => $bank->name,
                    'account_number' => $bank->masked_account_number,
                    'type' => $bank->bank_type_label,
                    'full_name' => $bank->full_name,
                ];
            });
    }

    /**
     * Enrich bank data with computed values.
     */
    protected function enrichBankData(Bank $bank): Bank
    {
        // Add computed balance for easy access
        $bank->computed_balance = $this->calculateBalance($bank);

        return $bank;
    }

    /**
     * Validate bank can be used for transaction.
     */
    public function validateBankForTransaction(Bank $bank, float $amount, string $type = 'debit'): array
    {
        $errors = [];

        if (!$bank->is_active) {
            $errors[] = 'Bank account is not active.';
        }

        if (!$bank->account_id) {
            $errors[] = 'Bank is not linked to a GL account.';
        }

        if ($type === 'debit') {
            $available = $bank->available_balance;
            if ($amount > $available) {
                $errors[] = sprintf(
                    'Insufficient funds. Available: %s, Required: %s',
                    number_format($available, 2),
                    number_format($amount, 2)
                );
            }
        }

        return $errors;
    }
}
