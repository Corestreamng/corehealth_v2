<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\PettyCashTransaction;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Petty Cash Observer
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.7
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 1.5
 *
 * Creates journal entries when petty cash transactions are processed:
 *
 * DISBURSEMENT (when disbursed):
 *   DEBIT:  Expense Account (based on category)
 *   CREDIT: Petty Cash (Asset) - Fund's account
 *
 * REPLENISHMENT (when disbursed):
 *   DEBIT:  Petty Cash (Asset) - Fund's account
 *   CREDIT: Bank Account (from which replenishment is made)
 *
 * ADJUSTMENT (when disbursed):
 *   Shortage: DEBIT Cash Short/Over Expense, CREDIT Petty Cash
 *   Overage:  DEBIT Petty Cash, CREDIT Cash Short/Over Income
 */
class PettyCashObserver
{
    /**
     * Handle the PettyCashTransaction "updated" event.
     */
    public function updated(PettyCashTransaction $transaction): void
    {
        // Only create journal entry when transaction is disbursed
        if ($transaction->isDirty('status') && $transaction->status === PettyCashTransaction::STATUS_DISBURSED) {
            try {
                $this->createJournalEntry($transaction);
            } catch (\Exception $e) {
                Log::error('PettyCashObserver: Failed to create journal entry', [
                    'transaction_id' => $transaction->id,
                    'voucher_number' => $transaction->voucher_number,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Create journal entry based on transaction type.
     */
    protected function createJournalEntry(PettyCashTransaction $transaction): void
    {
        $accountingService = App::make(AccountingService::class);

        // Get fund's GL account (petty cash asset)
        $fund = $transaction->fund;
        if (!$fund || !$fund->account_id) {
            Log::warning('PettyCashObserver: Fund or account not configured', [
                'transaction_id' => $transaction->id
            ]);
            return;
        }

        $pettyCashAccount = $fund->account;

        match ($transaction->transaction_type) {
            PettyCashTransaction::TYPE_DISBURSEMENT => $this->createDisbursementEntry($transaction, $accountingService, $pettyCashAccount),
            PettyCashTransaction::TYPE_REPLENISHMENT => $this->createReplenishmentEntry($transaction, $accountingService, $pettyCashAccount),
            PettyCashTransaction::TYPE_ADJUSTMENT => $this->createAdjustmentEntry($transaction, $accountingService, $pettyCashAccount),
            default => Log::warning('PettyCashObserver: Unknown transaction type', [
                'type' => $transaction->transaction_type
            ])
        };
    }

    /**
     * Create disbursement journal entry.
     *
     * DEBIT:  Expense Account
     * CREDIT: Petty Cash
     */
    protected function createDisbursementEntry(
        PettyCashTransaction $transaction,
        AccountingService $accountingService,
        Account $pettyCashAccount
    ): void {
        // Get expense account
        $expenseAccount = $transaction->expense_account_id
            ? Account::find($transaction->expense_account_id)
            : $this->getDefaultExpenseAccount($transaction->expense_category);

        if (!$expenseAccount) {
            Log::warning('PettyCashObserver: Expense account not found', [
                'transaction_id' => $transaction->id,
                'expense_category' => $transaction->expense_category
            ]);
            // Use miscellaneous expense as fallback
            $expenseAccount = Account::where('code', '6090')->first();
        }

        if (!$expenseAccount) {
            Log::error('PettyCashObserver: No expense account available');
            return;
        }

        $description = sprintf(
            'Petty Cash Disbursement: %s - %s',
            $transaction->voucher_number,
            $transaction->description
        );

        $lines = [
            [
                'account_id' => $expenseAccount->id,
                'debit_amount' => $transaction->amount,
                'credit_amount' => 0,
                'description' => 'Expense: ' . ($transaction->payee_name ?? $transaction->description),
                // METADATA
                'category' => $transaction->expense_category ?? 'petty_cash',
            ],
            [
                'account_id' => $pettyCashAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $transaction->amount,
                'description' => 'Petty Cash Disbursement',
                // METADATA
                'category' => 'petty_cash',
            ]
        ];

        $entry = $accountingService->createAndPostAutomatedEntry(
            PettyCashTransaction::class,
            $transaction->id,
            $description,
            $lines
        );

        // Link journal entry to transaction
        $transaction->journal_entry_id = $entry->id;
        $transaction->saveQuietly();

        // Update fund's cached balance
        $transaction->fund->current_balance = $transaction->fund->getBalanceFromJournalEntries();
        $transaction->fund->saveQuietly();

        Log::info('PettyCashObserver: Disbursement entry created', [
            'transaction_id' => $transaction->id,
            'journal_entry_id' => $entry->id,
            'amount' => $transaction->amount,
        ]);
    }

    /**
     * Create replenishment journal entry.
     *
     * DEBIT:  Petty Cash
     * CREDIT: Bank Account or Cash in Hand
     *
     * Per IAS 7 (Cash Flow Statement) & internal controls:
     * - If bank_id is set, use that bank's GL account
     * - If payment_method is 'cash', use Cash in Hand (1010)
     * - Otherwise fall back to generic Bank Account (1020)
     */
    protected function createReplenishmentEntry(
        PettyCashTransaction $transaction,
        AccountingService $accountingService,
        Account $pettyCashAccount
    ): void {
        // Determine credit account based on payment method/bank
        $creditAccount = $this->getReplenishmentSourceAccount($transaction);

        if (!$creditAccount) {
            Log::error('PettyCashObserver: Source account not found for replenishment', [
                'transaction_id' => $transaction->id,
                'payment_method' => $transaction->payment_method,
                'bank_id' => $transaction->bank_id,
            ]);
            return;
        }

        $sourceLabel = $this->getSourceLabel($transaction, $creditAccount);

        $description = sprintf(
            'Petty Cash Replenishment: %s - %s',
            $transaction->voucher_number,
            $transaction->description
        );

        $lines = [
            [
                'account_id' => $pettyCashAccount->id,
                'debit_amount' => $transaction->amount,
                'credit_amount' => 0,
                'description' => 'Petty Cash Replenishment',
                // METADATA
                'category' => 'petty_cash_replenishment',
            ],
            [
                'account_id' => $creditAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $transaction->amount,
                'description' => "Replenishment to Petty Cash Fund from {$sourceLabel}",
                // METADATA
                'category' => 'petty_cash_replenishment',
                'bank_id' => $transaction->bank_id,
            ]
        ];

        $entry = $accountingService->createAndPostAutomatedEntry(
            PettyCashTransaction::class,
            $transaction->id,
            $description,
            $lines
        );

        // Link journal entry to transaction
        $transaction->journal_entry_id = $entry->id;
        $transaction->saveQuietly();

        // Update fund's cached balance
        $transaction->fund->current_balance = $transaction->fund->getBalanceFromJournalEntries();
        $transaction->fund->saveQuietly();

        Log::info('PettyCashObserver: Replenishment entry created', [
            'transaction_id' => $transaction->id,
            'journal_entry_id' => $entry->id,
            'amount' => $transaction->amount,
            'source_account' => $creditAccount->code,
            'bank_id' => $transaction->bank_id,
        ]);
    }

    /**
     * Get the GL account for replenishment source (bank or cash).
     */
    protected function getReplenishmentSourceAccount(PettyCashTransaction $transaction): ?Account
    {
        // If bank_id is set, use that bank's GL account
        if ($transaction->bank_id) {
            $bank = $transaction->bank;
            if ($bank && $bank->account_id) {
                $account = Account::find($bank->account_id);
                if ($account) {
                    Log::info('PettyCashObserver: Using bank-specific GL account', [
                        'transaction_id' => $transaction->id,
                        'bank_id' => $bank->id,
                        'bank_name' => $bank->name,
                        'account_code' => $account->code,
                    ]);
                    return $account;
                }
            }
        }

        // If payment_method is 'cash', use Cash in Hand
        if (strtolower($transaction->payment_method ?? '') === 'cash') {
            return Account::where('code', '1010')->first(); // Cash in Hand
        }

        // Fallback to generic Bank Account
        return Account::where('code', '1020')->first();
    }

    /**
     * Get human-readable source label for journal entry description.
     */
    protected function getSourceLabel(PettyCashTransaction $transaction, Account $account): string
    {
        if ($transaction->bank_id && $transaction->bank) {
            return $transaction->bank->name;
        }

        if (strtolower($transaction->payment_method ?? '') === 'cash') {
            return 'Cash';
        }

        return $account->name ?? 'Bank';
    }

    /**
     * Create adjustment journal entry.
     *
     * Shortage: DEBIT Cash Short/Over, CREDIT Petty Cash
     * Overage:  DEBIT Petty Cash, CREDIT Cash Short/Over
     */
    protected function createAdjustmentEntry(
        PettyCashTransaction $transaction,
        AccountingService $accountingService,
        Account $pettyCashAccount
    ): void {
        // Get Cash Short/Over account (usually 6095 or similar)
        $shortOverAccount = Account::where('code', '6095')->first();

        if (!$shortOverAccount) {
            // Try to find by name pattern
            $shortOverAccount = Account::where('name', 'like', '%short%over%')->first();
        }

        if (!$shortOverAccount) {
            Log::error('PettyCashObserver: Cash short/over account not found');
            return;
        }

        $amount = abs($transaction->amount);
        $isShortage = $transaction->amount < 0;

        $description = sprintf(
            'Petty Cash %s: %s - %s',
            $isShortage ? 'Shortage' : 'Overage',
            $transaction->voucher_number,
            $transaction->description
        );

        if ($isShortage) {
            // Shortage: DEBIT Expense, CREDIT Petty Cash
            $lines = [
                [
                    'account_id' => $shortOverAccount->id,
                    'debit_amount' => $amount,
                    'credit_amount' => 0,
                    'description' => 'Cash Shortage Adjustment',
                    'category' => 'petty_cash_adjustment',
                ],
                [
                    'account_id' => $pettyCashAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $amount,
                    'description' => 'Petty Cash Shortage',
                    'category' => 'petty_cash_adjustment',
                ]
            ];
        } else {
            // Overage: DEBIT Petty Cash, CREDIT Income
            $lines = [
                [
                    'account_id' => $pettyCashAccount->id,
                    'debit_amount' => $amount,
                    'credit_amount' => 0,
                    'description' => 'Petty Cash Overage',
                    'category' => 'petty_cash_adjustment',
                ],
                [
                    'account_id' => $shortOverAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $amount,
                    'description' => 'Cash Overage Adjustment',
                    'category' => 'petty_cash_adjustment',
                ]
            ];
        }

        $entry = $accountingService->createAndPostAutomatedEntry(
            PettyCashTransaction::class,
            $transaction->id,
            $description,
            $lines
        );

        // Link journal entry to transaction
        $transaction->journal_entry_id = $entry->id;
        $transaction->saveQuietly();

        // Update fund's cached balance
        $transaction->fund->current_balance = $transaction->fund->getBalanceFromJournalEntries();
        $transaction->fund->saveQuietly();

        Log::info('PettyCashObserver: Adjustment entry created', [
            'transaction_id' => $transaction->id,
            'journal_entry_id' => $entry->id,
            'amount' => $transaction->amount,
            'type' => $isShortage ? 'shortage' : 'overage',
        ]);
    }

    /**
     * Get default expense account based on category.
     */
    protected function getDefaultExpenseAccount(?string $category): ?Account
    {
        if (!$category) {
            return Account::where('code', '6090')->first(); // Miscellaneous
        }

        // Map categories to account codes
        $categoryMap = [
            'transport' => '6050',      // Transportation
            'transportation' => '6050',
            'office_supplies' => '6060', // Office Supplies
            'supplies' => '6060',
            'refreshment' => '6070',    // Refreshment & Entertainment
            'entertainment' => '6070',
            'meals' => '6070',
            'maintenance' => '6020',    // Maintenance & Repairs
            'repairs' => '6020',
            'postage' => '6080',        // Postage & Courier
            'courier' => '6080',
            'utilities' => '6030',      // Utilities
            'cleaning' => '6085',       // Cleaning
            'miscellaneous' => '6090',  // Miscellaneous
            'other' => '6090',
        ];

        $code = $categoryMap[strtolower($category)] ?? '6090';
        return Account::where('code', $code)->first();
    }
}
