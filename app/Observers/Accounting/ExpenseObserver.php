<?php

namespace App\Observers\Accounting;

use App\Models\Expense;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Expense Observer
 *
 * Reference: Accounting System Plan §6.2 - Automated Journal Entries
 *
 * Creates journal entries when expenses are approved.
 *
 * DEBIT:  Expense Account (by category)
 * CREDIT: Cash / Bank / Accounts Payable
 */
class ExpenseObserver
{
    /**
     * Handle the Expense "updated" event.
     */
    public function updated(Expense $expense): void
    {
        // Only create journal entry when expense is approved
        if ($expense->isDirty('status') && $expense->status === Expense::STATUS_APPROVED) {
            try {
                $this->createExpenseJournalEntry($expense);
            } catch (\Exception $e) {
                Log::error('Failed to create journal entry for expense', [
                    'expense_id' => $expense->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Create journal entry for approved expense.
     */
    protected function createExpenseJournalEntry(Expense $expense): void
    {
        $accountingService = App::make(AccountingService::class);

        $debitAccountCode = $this->getDebitAccountCode($expense);
        $creditAccountCode = $this->getCreditAccountCode($expense);

        $debitAccount = Account::where('account_code', $debitAccountCode)->first();
        $creditAccount = Account::where('account_code', $creditAccountCode)->first();

        if (!$debitAccount || !$creditAccount) {
            Log::warning('Expense journal entry skipped - accounts not configured', [
                'expense_id' => $expense->id,
                'debit_code' => $debitAccountCode,
                'credit_code' => $creditAccountCode
            ]);
            return;
        }

        $description = $this->buildDescription($expense);

        $lines = [
            [
                'account_id' => $debitAccount->id,
                'debit_amount' => $expense->amount,
                'credit_amount' => 0,
                'description' => "Expense: {$expense->category}"
            ],
            [
                'account_id' => $creditAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $expense->amount,
                'description' => 'Payment / Liability'
            ]
        ];

        $entry = $accountingService->createJournalEntry([
            'entry_type' => JournalEntry::TYPE_AUTOMATED,
            'source_type' => Expense::class,
            'source_id' => $expense->id,
            'description' => $description,
            'status' => JournalEntry::STATUS_POSTED,
            'memo' => "Ref: {$expense->expense_number}"
        ], $lines);

        // Update expense with journal entry reference
        $expense->journal_entry_id = $entry->id;
        $expense->saveQuietly();
    }

    /**
     * Get debit account code based on expense category.
     */
    protected function getDebitAccountCode(Expense $expense): string
    {
        return match ($expense->category) {
            Expense::CATEGORY_PURCHASE_ORDER => '5010', // Cost of Goods Sold / Inventory
            Expense::CATEGORY_STORE_EXPENSE => '6010', // Store Operating Expenses
            Expense::CATEGORY_MAINTENANCE => '6020', // Maintenance & Repairs
            Expense::CATEGORY_UTILITIES => '6030', // Utilities Expense
            Expense::CATEGORY_SALARIES => '6040', // Salaries & Wages
            Expense::CATEGORY_OTHER => '6090', // Miscellaneous Expenses
            default => '6090'
        };
    }

    /**
     * Get credit account code based on payment method.
     */
    protected function getCreditAccountCode(Expense $expense): string
    {
        // If supplier is involved and not paid immediately, credit AP
        if ($expense->supplier_id && !$expense->payment_method) {
            return '2100'; // Accounts Payable
        }

        return match ($expense->payment_method) {
            'cash' => '1010', // Cash in Hand
            'bank_transfer', 'bank', 'transfer' => '1020', // Bank Account
            'cheque', 'check' => '1020', // Bank Account
            default => '1010' // Default to Cash
        };
    }

    /**
     * Build description for journal entry.
     */
    protected function buildDescription(Expense $expense): string
    {
        $parts = [
            "Expense: {$expense->title}",
            "Ref: {$expense->expense_number}"
        ];

        if ($expense->supplier) {
            $parts[] = "Supplier: {$expense->supplier->name}";
        }

        return implode(' | ', $parts);
    }
}
