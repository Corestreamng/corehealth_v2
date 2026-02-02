<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\StatutoryRemittance;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Statutory Remittance Observer
 *
 * Creates journal entries when statutory remittances are paid.
 *
 * On Payment (status = paid):
 * DEBIT:  Liability Account (from PayHead) - clearing the withheld amount
 * CREDIT: Bank Account - the payment outflow
 *
 * On Void (with existing payment):
 * Reverses the journal entry to restore the liability
 */
class StatutoryRemittanceObserver
{
    /**
     * Handle the StatutoryRemittance "updated" event.
     */
    public function updated(StatutoryRemittance $remittance): void
    {
        // When status changes to 'paid' - create journal entry
        if ($remittance->isDirty('status') && $remittance->status === StatutoryRemittance::STATUS_PAID) {
            try {
                $this->createPaymentJournalEntry($remittance);
            } catch (\Exception $e) {
                Log::error('StatutoryRemittanceObserver: Failed to create payment journal entry', [
                    'remittance_id' => $remittance->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        // When status changes to 'voided' - reverse the journal entry
        if ($remittance->isDirty('status') && $remittance->status === StatutoryRemittance::STATUS_VOIDED) {
            try {
                $this->reversePaymentJournalEntry($remittance);
            } catch (\Exception $e) {
                Log::error('StatutoryRemittanceObserver: Failed to reverse payment journal entry', [
                    'remittance_id' => $remittance->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Create journal entry when remittance is paid.
     *
     * DEBIT:  Liability Account (from PayHead) - clearing the withheld amount
     * CREDIT: Bank Account - the payment
     */
    protected function createPaymentJournalEntry(StatutoryRemittance $remittance): void
    {
        // Get the liability account from the pay head
        $liabilityAccount = $remittance->payHead?->liabilityAccount;

        if (!$liabilityAccount) {
            Log::warning('StatutoryRemittanceObserver: PayHead has no liability account linked', [
                'remittance_id' => $remittance->id,
                'pay_head_id' => $remittance->pay_head_id,
                'pay_head_name' => $remittance->payHead?->name
            ]);
            return;
        }

        // Get the bank account
        $bankAccount = $remittance->bank?->account;

        if (!$bankAccount) {
            // Fall back to Cash account if no bank specified
            $bankAccount = Account::where('code', '1010')->first(); // Cash Account

            if (!$bankAccount) {
                Log::error('StatutoryRemittanceObserver: Neither bank account nor cash account found', [
                    'remittance_id' => $remittance->id,
                    'bank_id' => $remittance->bank_id
                ]);
                return;
            }
        }

        $accountingService = App::make(AccountingService::class);

        $description = sprintf(
            "Statutory Remittance: %s | Ref: %s | Period: %s | Payee: %s",
            $remittance->payHead->name,
            $remittance->reference_number,
            $remittance->period_string,
            $remittance->payee_name
        );

        $lines = [
            // DEBIT: Clear the liability (reduce what we owe to statutory body)
            [
                'account_id' => $liabilityAccount->id,
                'sub_account_id' => null,
                'debit_amount' => $remittance->amount,
                'credit_amount' => 0,
                'description' => "Clear {$remittance->payHead->name} liability",
            ],
            // CREDIT: Bank/Cash outflow
            [
                'account_id' => $bankAccount->id,
                'sub_account_id' => null,
                'debit_amount' => 0,
                'credit_amount' => $remittance->amount,
                'description' => "Payment to {$remittance->payee_name}",
            ]
        ];

        // Create and post the journal entry
        $journalEntry = $accountingService->createAndPostAutomatedEntry(
            StatutoryRemittance::class,
            $remittance->id,
            $description,
            $lines
        );

        // Link journal entry to remittance
        $remittance->update(['journal_entry_id' => $journalEntry->id]);

        Log::info('StatutoryRemittanceObserver: Journal entry created for remittance payment', [
            'remittance_id' => $remittance->id,
            'journal_entry_id' => $journalEntry->id,
            'amount' => $remittance->amount,
            'pay_head' => $remittance->payHead->name
        ]);
    }

    /**
     * Reverse journal entry when remittance is voided.
     */
    protected function reversePaymentJournalEntry(StatutoryRemittance $remittance): void
    {
        if (!$remittance->journal_entry_id) {
            Log::info('StatutoryRemittanceObserver: No journal entry to reverse', [
                'remittance_id' => $remittance->id
            ]);
            return;
        }

        $journalEntry = $remittance->journalEntry;

        if (!$journalEntry || !$journalEntry->canReverse()) {
            Log::info('StatutoryRemittanceObserver: Journal entry cannot be reversed', [
                'remittance_id' => $remittance->id,
                'journal_entry_id' => $remittance->journal_entry_id
            ]);
            return;
        }

        $accountingService = App::make(AccountingService::class);

        $reversalDescription = sprintf(
            "Reversal: Statutory Remittance Voided | Ref: %s | Reason: %s",
            $remittance->reference_number,
            $remittance->void_reason ?? 'No reason provided'
        );

        $reversalEntry = $accountingService->reverseEntry(
            $journalEntry,
            $remittance->voided_by ?? auth()->id() ?? 1,
            $reversalDescription
        );

        Log::info('StatutoryRemittanceObserver: Journal entry reversed for voided remittance', [
            'remittance_id' => $remittance->id,
            'original_je_id' => $journalEntry->id,
            'reversal_je_id' => $reversalEntry->id
        ]);
    }
}
