<?php

namespace App\Services\Accounting;

use App\Models\Bank;
use App\Models\Accounting\Account;
use App\Models\Accounting\BankReconciliation;
use App\Models\Accounting\BankReconciliationItem;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bank Reconciliation Service
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 2
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 2.2
 *
 * Handles bank statement reconciliation with GL entries.
 */
class ReconciliationService
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Start a new reconciliation for a bank.
     */
    public function startReconciliation(
        Bank $bank,
        string $statementDate,
        string $periodFrom,
        string $periodTo,
        float $statementOpeningBalance,
        float $statementClosingBalance,
        int $preparedBy
    ): BankReconciliation {
        if (!$bank->account_id) {
            throw new \InvalidArgumentException('Bank is not linked to a GL account');
        }

        // Calculate GL balances from journal entries
        $glOpeningBalance = $bank->account->getBalance(null, date('Y-m-d', strtotime($periodFrom) - 86400));
        $glClosingBalance = $bank->account->getBalance(null, $periodTo);

        $reconciliation = BankReconciliation::create([
            'bank_id' => $bank->id,
            'account_id' => $bank->account_id,
            'reconciliation_number' => BankReconciliation::generateNumber($bank->id),
            'statement_date' => $statementDate,
            'statement_period_from' => $periodFrom,
            'statement_period_to' => $periodTo,
            'statement_opening_balance' => $statementOpeningBalance,
            'statement_closing_balance' => $statementClosingBalance,
            'gl_opening_balance' => $glOpeningBalance,
            'gl_closing_balance' => $glClosingBalance,
            'status' => BankReconciliation::STATUS_DRAFT,
            'prepared_by' => $preparedBy,
        ]);

        // Import GL transactions for the period
        $this->importGLTransactions($reconciliation);

        // Calculate initial variance
        $reconciliation->calculateVariance();
        $reconciliation->save();

        return $reconciliation;
    }

    /**
     * Import GL transactions for the reconciliation period.
     */
    public function importGLTransactions(BankReconciliation $reconciliation): int
    {
        $lines = JournalEntryLine::query()
            ->with('journalEntry')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $reconciliation->account_id)
            ->where('journal_entries.status', JournalEntry::STATUS_POSTED)
            ->whereBetween('journal_entries.entry_date', [
                $reconciliation->statement_period_from,
                $reconciliation->statement_period_to,
            ])
            ->select('journal_entry_lines.*')
            ->orderBy('journal_entries.entry_date')
            ->get();

        $count = 0;
        foreach ($lines as $line) {
            $itemType = $this->determineItemType($line);
            $amountType = $line->debit > 0
                ? BankReconciliationItem::AMOUNT_DEBIT
                : BankReconciliationItem::AMOUNT_CREDIT;

            BankReconciliationItem::create([
                'reconciliation_id' => $reconciliation->id,
                'journal_entry_line_id' => $line->id,
                'source' => BankReconciliationItem::SOURCE_GL,
                'item_type' => $itemType,
                'transaction_date' => $line->journalEntry->entry_date,
                'reference' => $line->journalEntry->reference,
                'description' => $line->description ?: $line->journalEntry->description,
                'amount' => max($line->debit, $line->credit),
                'amount_type' => $amountType,
                'is_matched' => false,
                'is_reconciled' => false,
                'is_outstanding' => false,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * Import statement transactions (manual entry).
     */
    public function importStatementTransaction(
        BankReconciliation $reconciliation,
        array $data
    ): BankReconciliationItem {
        return BankReconciliationItem::create([
            'reconciliation_id' => $reconciliation->id,
            'source' => BankReconciliationItem::SOURCE_STATEMENT,
            'item_type' => $data['item_type'],
            'transaction_date' => $data['transaction_date'],
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'],
            'amount' => $data['amount'],
            'amount_type' => $data['amount_type'],
            'is_matched' => false,
            'is_reconciled' => false,
            'is_outstanding' => false,
        ]);
    }

    /**
     * Get unreconciled transactions for an account.
     */
    public function getUnreconciledTransactions(
        Account $account,
        string $fromDate,
        string $toDate
    ): Collection {
        return JournalEntryLine::query()
            ->with('journalEntry')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $account->id)
            ->where('journal_entries.status', JournalEntry::STATUS_POSTED)
            ->whereBetween('journal_entries.entry_date', [$fromDate, $toDate])
            ->select('journal_entry_lines.*')
            ->orderBy('journal_entries.entry_date')
            ->get();
    }

    /**
     * Match a GL item with a statement item.
     */
    public function matchTransaction(
        BankReconciliationItem $glItem,
        BankReconciliationItem $statementItem
    ): bool {
        if (!$glItem->canBeMatched() || !$statementItem->canBeMatched()) {
            return false;
        }

        if ($glItem->source !== BankReconciliationItem::SOURCE_GL ||
            $statementItem->source !== BankReconciliationItem::SOURCE_STATEMENT) {
            return false;
        }

        return $glItem->matchWith($statementItem);
    }

    /**
     * Auto-match transactions based on amount and date.
     */
    public function autoMatch(BankReconciliation $reconciliation): int
    {
        $glItems = $reconciliation->items()
            ->fromGL()
            ->unmatched()
            ->get();

        $statementItems = $reconciliation->items()
            ->fromStatement()
            ->unmatched()
            ->get();

        $matchCount = 0;

        foreach ($glItems as $glItem) {
            // Try to find exact match by amount and date
            $match = $statementItems->first(function ($stItem) use ($glItem) {
                if ($stItem->is_matched) {
                    return false;
                }

                // Amount must match
                if (abs($glItem->amount - $stItem->amount) > 0.01) {
                    return false;
                }

                // Date should be within 3 days (for clearance delay)
                $dateDiff = abs($glItem->transaction_date->diffInDays($stItem->transaction_date));
                if ($dateDiff > 3) {
                    return false;
                }

                // Amount type must be opposite (debit in GL = credit in statement for deposits)
                // This depends on the bank statement format - adjust as needed
                return true;
            });

            if ($match) {
                $glItem->matchWith($match);
                $matchCount++;
            }
        }

        // Update reconciliation totals
        $this->updateReconciliationTotals($reconciliation);

        return $matchCount;
    }

    /**
     * Mark outstanding items.
     */
    public function markOutstandingItems(BankReconciliation $reconciliation): void
    {
        // GL items not matched = outstanding deposits or checks
        $unmatchedGl = $reconciliation->items()
            ->fromGL()
            ->unmatched()
            ->get();

        $outstandingDeposits = 0;
        $outstandingChecks = 0;

        foreach ($unmatchedGl as $item) {
            $item->markOutstanding();

            if ($item->isDeposit()) {
                $outstandingDeposits += $item->amount;
            } else {
                $outstandingChecks += $item->amount;
            }
        }

        // Statement items not matched = deposits in transit or unrecorded charges
        $unmatchedStatement = $reconciliation->items()
            ->fromStatement()
            ->unmatched()
            ->get();

        $depositsInTransit = 0;
        $unrecordedCharges = 0;
        $unrecordedCredits = 0;

        foreach ($unmatchedStatement as $item) {
            if ($item->item_type === BankReconciliationItem::TYPE_BANK_CHARGE) {
                $unrecordedCharges += $item->amount;
            } elseif ($item->item_type === BankReconciliationItem::TYPE_INTEREST) {
                $unrecordedCredits += $item->amount;
            } elseif ($item->isDeposit()) {
                $depositsInTransit += $item->amount;
            }
        }

        $reconciliation->update([
            'outstanding_deposits' => $outstandingDeposits,
            'outstanding_checks' => $outstandingChecks,
            'deposits_in_transit' => $depositsInTransit,
            'unrecorded_charges' => $unrecordedCharges,
            'unrecorded_credits' => $unrecordedCredits,
        ]);

        $reconciliation->calculateVariance();
        $reconciliation->save();
    }

    /**
     * Create adjustment entry for unrecorded items.
     */
    public function createAdjustmentEntry(
        BankReconciliation $reconciliation,
        BankReconciliationItem $item,
        int $createdBy
    ): JournalEntry {
        if ($item->source !== BankReconciliationItem::SOURCE_STATEMENT) {
            throw new \InvalidArgumentException('Can only create adjustments for statement items');
        }

        $bank = $reconciliation->bank;
        $bankAccount = $reconciliation->account;

        // Determine contra account based on item type
        $contraAccount = $this->getContraAccountForItemType($item->item_type);

        $description = sprintf(
            'Reconciliation Adjustment: %s - %s',
            $reconciliation->reconciliation_number,
            $item->description
        );

        // Build entry lines
        if ($item->amount_type === BankReconciliationItem::AMOUNT_DEBIT) {
            // Debit bank, credit contra
            $lines = [
                [
                    'account_id' => $bankAccount->id,
                    'debit_amount' => $item->amount,
                    'credit_amount' => 0,
                    'description' => $item->description,
                    'category' => 'reconciliation_adjustment',
                ],
                [
                    'account_id' => $contraAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $item->amount,
                    'description' => $item->description,
                    'category' => 'reconciliation_adjustment',
                ]
            ];
        } else {
            // Credit bank, debit contra
            $lines = [
                [
                    'account_id' => $contraAccount->id,
                    'debit_amount' => $item->amount,
                    'credit_amount' => 0,
                    'description' => $item->description,
                    'category' => 'reconciliation_adjustment',
                ],
                [
                    'account_id' => $bankAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $item->amount,
                    'description' => $item->description,
                    'category' => 'reconciliation_adjustment',
                ]
            ];
        }

        $entry = $this->accountingService->createAndPostAutomatedEntry(
            BankReconciliation::class,
            $reconciliation->id,
            $description,
            $lines
        );

        // Link to item
        $item->update([
            'adjustment_entry_id' => $entry->id,
            'is_reconciled' => true,
        ]);

        // Add to reconciliation's adjustment list
        $existingIds = $reconciliation->adjustment_entry_ids ?? [];
        $existingIds[] = $entry->id;
        $reconciliation->update(['adjustment_entry_ids' => $existingIds]);

        return $entry;
    }

    /**
     * Finalize reconciliation.
     */
    public function finalizeReconciliation(
        BankReconciliation $reconciliation,
        int $approvedBy
    ): bool {
        if (!$reconciliation->isReconciled()) {
            throw new \InvalidArgumentException(
                'Cannot finalize reconciliation with variance of ' .
                number_format($reconciliation->variance, 2)
            );
        }

        // Mark all matched items as reconciled
        $reconciliation->items()
            ->matched()
            ->update([
                'is_reconciled' => true,
                'cleared_date' => $reconciliation->statement_date,
            ]);

        // Update bank's last statement info
        $reconciliation->bank->update([
            'last_statement_date' => $reconciliation->statement_date,
            'last_statement_balance' => $reconciliation->statement_closing_balance,
        ]);

        $reconciliation->update([
            'status' => BankReconciliation::STATUS_FINALIZED,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'finalized_at' => now(),
        ]);

        return true;
    }

    /**
     * Update reconciliation totals from items.
     */
    public function updateReconciliationTotals(BankReconciliation $reconciliation): void
    {
        // Calculate outstanding deposits
        $outstandingDeposits = $reconciliation->items()
            ->fromGL()
            ->unmatched()
            ->deposits()
            ->sum('amount');

        // Calculate outstanding checks
        $outstandingChecks = $reconciliation->items()
            ->fromGL()
            ->unmatched()
            ->withdrawals()
            ->sum('amount');

        $reconciliation->update([
            'outstanding_deposits' => $outstandingDeposits,
            'outstanding_checks' => $outstandingChecks,
        ]);

        $reconciliation->calculateVariance();
        $reconciliation->save();
    }

    /**
     * Get reconciliation summary.
     */
    public function getReconciliationSummary(BankReconciliation $reconciliation): array
    {
        $glItems = $reconciliation->items()->fromGL()->get();
        $statementItems = $reconciliation->items()->fromStatement()->get();

        return [
            'reconciliation_id' => $reconciliation->id,
            'reconciliation_number' => $reconciliation->reconciliation_number,
            'bank_name' => $reconciliation->bank?->name,
            'statement_date' => $reconciliation->statement_date?->format('Y-m-d'),
            'period' => $reconciliation->statement_period_from?->format('Y-m-d') .
                ' to ' . $reconciliation->statement_period_to?->format('Y-m-d'),
            'statement_balance' => $reconciliation->statement_closing_balance,
            'gl_balance' => $reconciliation->gl_closing_balance,
            'adjusted_bank_balance' => $reconciliation->adjusted_bank_balance,
            'adjusted_book_balance' => $reconciliation->adjusted_book_balance,
            'variance' => $reconciliation->variance,
            'is_reconciled' => $reconciliation->isReconciled(),
            'status' => $reconciliation->status,
            'gl_items_count' => $glItems->count(),
            'gl_items_matched' => $glItems->where('is_matched', true)->count(),
            'statement_items_count' => $statementItems->count(),
            'statement_items_matched' => $statementItems->where('is_matched', true)->count(),
            'outstanding_deposits' => $reconciliation->outstanding_deposits,
            'outstanding_checks' => $reconciliation->outstanding_checks,
            'unrecorded_charges' => $reconciliation->unrecorded_charges,
            'unrecorded_credits' => $reconciliation->unrecorded_credits,
        ];
    }

    /**
     * Determine item type from JE line.
     */
    protected function determineItemType(JournalEntryLine $line): string
    {
        $category = $line->category ?? '';
        $description = strtolower($line->description ?? '');

        // Check for common patterns
        if (str_contains($description, 'check') || str_contains($description, 'cheque')) {
            return BankReconciliationItem::TYPE_CHECK;
        }

        if (str_contains($description, 'transfer')) {
            return BankReconciliationItem::TYPE_TRANSFER;
        }

        if (str_contains($description, 'bank charge') || str_contains($description, 'bank fee')) {
            return BankReconciliationItem::TYPE_BANK_CHARGE;
        }

        if (str_contains($description, 'interest')) {
            return BankReconciliationItem::TYPE_INTEREST;
        }

        // Based on category
        if (in_array($category, ['payment', 'revenue', 'deposit'])) {
            return BankReconciliationItem::TYPE_DEPOSIT;
        }

        if (in_array($category, ['expense', 'payroll', 'purchase'])) {
            return BankReconciliationItem::TYPE_CHECK;
        }

        // Default based on debit/credit
        return $line->debit > 0
            ? BankReconciliationItem::TYPE_DEPOSIT
            : BankReconciliationItem::TYPE_CHECK;
    }

    /**
     * Get contra account for adjustment.
     */
    protected function getContraAccountForItemType(string $itemType): Account
    {
        $code = match ($itemType) {
            BankReconciliationItem::TYPE_BANK_CHARGE => '6100', // Bank Charges Expense
            BankReconciliationItem::TYPE_INTEREST => '4500',   // Interest Income
            default => '6090', // Miscellaneous Expense
        };

        $account = Account::where('code', $code)->first();

        if (!$account) {
            // Fallback to miscellaneous
            $account = Account::where('code', '6090')->first();
        }

        if (!$account) {
            throw new \RuntimeException("Could not find contra account for adjustment");
        }

        return $account;
    }
}
