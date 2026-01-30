<?php

namespace App\Observers\Accounting;

use App\Models\Expense;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubAccount;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\SubAccountService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Expense Observer (UPDATED with Metadata + Sub-Account)
 *
 * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Part 7.3.3
 *
 * Creates journal entries when expenses are approved.
 *
 * DEBIT:  Expense Account (by category)
 * CREDIT: Cash / Bank / Accounts Payable (with Supplier Sub-Account)
 *
 * METADATA CAPTURED:
 * - supplier_id: Always if expense has supplier
 * - category: Expense category (utilities, maintenance, etc.)
 *
 * SUB-ACCOUNT: Creates/uses supplier sub-account under AP (2100)
 */
class ExpenseObserver
{
    protected SubAccountService $subAccountService;

    public function __construct(SubAccountService $subAccountService)
    {
        $this->subAccountService = $subAccountService;
    }

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
                Log::error('ExpenseObserver: Failed to create journal entry', [
                    'expense_id' => $expense->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
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

        $debitAccount = Account::where('code', $debitAccountCode)->first();
        $creditAccount = Account::where('code', $creditAccountCode)->first();

        if (!$debitAccount || !$creditAccount) {
            Log::warning('ExpenseObserver: Skipped - accounts not configured', [
                'expense_id' => $expense->id,
                'debit_code' => $debitAccountCode,
                'credit_code' => $creditAccountCode
            ]);
            return;
        }

        // Get or create supplier sub-account if this is AP
        $supplierSubAccount = null;
        if ($expense->supplier_id && $creditAccountCode === '2100') {
            $supplierSubAccount = $this->subAccountService->getOrCreateSupplierSubAccount($expense->supplier);
        }

        $description = $this->buildDescription($expense);

        // Get category string for filtering
        $categoryString = $expense->category ?? 'general_expense';

        $lines = [
            [
                'account_id' => $debitAccount->id,
                'debit_amount' => $expense->amount,
                'credit_amount' => 0,
                'description' => $this->buildLineDescription($expense, 'debit'),
                // METADATA
                'supplier_id' => $expense->supplier_id,
                'category' => $categoryString,
            ],
            [
                'account_id' => $creditAccount->id,
                'sub_account_id' => $supplierSubAccount?->id, // Supplier sub-account if AP
                'debit_amount' => 0,
                'credit_amount' => $expense->amount,
                'description' => $this->buildLineDescription($expense, 'credit'),
                // METADATA
                'supplier_id' => $expense->supplier_id,
                'category' => $categoryString,
            ]
        ];

        $entry = $accountingService->createAndPostAutomatedEntry(
            Expense::class,
            $expense->id,
            $description,
            $lines
        );

        // Update expense with journal entry reference
        $expense->journal_entry_id = $entry->id;
        $expense->saveQuietly();

        Log::info('ExpenseObserver: Journal entry created', [
            'expense_id' => $expense->id,
            'journal_entry_id' => $entry->id,
            'amount' => $expense->amount,
            'supplier_id' => $expense->supplier_id,
        ]);
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
            "Expense: " . ($expense->title ?? 'Untitled'),
            "Category: " . ($expense->category ?? 'Other'),
            "Ref: " . ($expense->expense_number ?? 'N/A'),
            "Amount: " . number_format($expense->amount, 2)
        ];

        if ($expense->supplier) {
            $parts[] = "Supplier: " . ($expense->supplier->name ?? 'Unknown');
        }

        if ($expense->description) {
            $desc = strip_tags($expense->description);
            if (strlen($desc) > 2000) {
                $desc = substr($desc, 0, 1997) . '...';
            }
            $parts[] = "Details: {$desc}";
        }

        if ($expense->payment_method) {
            $parts[] = "Payment: {$expense->payment_method}";
            if ($expense->payment_reference) {
                $parts[] = "Payment Ref: {$expense->payment_reference}";
            }
        }

        if ($expense->store) {
            $parts[] = "Store: " . ($expense->store->name ?? 'Unknown');
        }

        return implode(' | ', $parts);
    }

    /**
     * Build description for journal entry line (max 255 chars).
     */
    protected function buildLineDescription(Expense $expense, string $side): string
    {
        if ($side === 'debit') {
            $desc = "Expense: " . ($expense->title ?? 'Untitled') . " (" . ($expense->category ?? 'Other') . ")";
            if ($expense->supplier) {
                $desc .= " - Supplier: " . ($expense->supplier->name ?? 'Unknown');
            }
        } else {
            if ($expense->supplier_id && !$expense->payment_method) {
                $desc = "Accounts Payable - " . ($expense->supplier->name ?? 'Supplier');
            } else {
                $desc = "Paid via " . ($expense->payment_method ?? 'Unknown');
                if ($expense->payment_reference) {
                    $desc .= " (Ref: {$expense->payment_reference})";
                }
            }
        }

        return strlen($desc) > 255 ? substr($desc, 0, 252) . '...' : $desc;
    }
}
