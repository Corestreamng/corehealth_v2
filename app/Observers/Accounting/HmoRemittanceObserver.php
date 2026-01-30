<?php

namespace App\Observers\Accounting;

use App\Models\HmoRemittance;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubAccount;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\SubAccountService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * HMO Remittance Observer
 *
 * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Part 7.2.2
 *
 * ACCRUAL ACCOUNTING: Creates journal entry when HMO remits payment.
 * This records cash received and offsets the AR-HMO that was created
 * when claims were validated.
 *
 * On Remittance Created:
 * DEBIT:  Bank Account (1020 or specific bank)
 * CREDIT: Accounts Receivable - HMO (1110) + HMO Sub-Account
 *
 * METADATA CAPTURED:
 * - hmo_id (for HMO-specific reports)
 * - category ('hmo_remittance')
 */
class HmoRemittanceObserver
{
    protected SubAccountService $subAccountService;

    public function __construct(SubAccountService $subAccountService)
    {
        $this->subAccountService = $subAccountService;
    }

    /**
     * Handle the HmoRemittance "created" event.
     */
    public function created(HmoRemittance $remittance): void
    {
        try {
            $this->createRemittanceJournalEntry($remittance);
        } catch (\Exception $e) {
            Log::error('HmoRemittanceObserver: Failed to create journal entry', [
                'remittance_id' => $remittance->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Create the remittance journal entry.
     */
    protected function createRemittanceJournalEntry(HmoRemittance $remittance): void
    {
        $accountingService = App::make(AccountingService::class);

        // Get bank account - prefer specific, fallback to generic
        $bankAccount = $this->getBankAccount($remittance);
        $arHmo = Account::where('code', '1110')->first(); // AR - HMO

        if (!$bankAccount || !$arHmo) {
            Log::warning('HmoRemittanceObserver: Skipped - accounts not configured', [
                'remittance_id' => $remittance->id,
                'bank_account_found' => !is_null($bankAccount),
                'ar_hmo_found' => !is_null($arHmo),
            ]);
            return;
        }

        // Get or create HMO sub-account for AR tracking
        $hmoSubAccount = $this->subAccountService->getOrCreateHmoSubAccount($remittance->hmo);

        $description = $this->buildDescription($remittance);

        // Format period dates safely
        $periodFrom = $remittance->period_from
            ? (is_string($remittance->period_from) ? $remittance->period_from : $remittance->period_from->format('Y-m-d'))
            : 'N/A';
        $periodTo = $remittance->period_to
            ? (is_string($remittance->period_to) ? $remittance->period_to : $remittance->period_to->format('Y-m-d'))
            : 'N/A';

        // Build lines WITH METADATA
        $lines = [
            [
                'account_id' => $bankAccount->id,
                'sub_account_id' => null,
                'debit_amount' => $remittance->amount,
                'credit_amount' => 0,
                'description' => "HMO Remittance received: " . ($remittance->hmo?->name ?? 'Unknown HMO'),
                // METADATA
                'hmo_id' => $remittance->hmo_id,
                'category' => 'hmo_remittance',
            ],
            [
                'account_id' => $arHmo->id,
                'sub_account_id' => $hmoSubAccount?->id,
                'debit_amount' => 0,
                'credit_amount' => $remittance->amount,
                'description' => "Claims settled - Period: {$periodFrom} to {$periodTo}",
                // METADATA
                'hmo_id' => $remittance->hmo_id,
                'category' => 'hmo_remittance',
            ]
        ];

        $entry = $accountingService->createAndPostAutomatedEntry(
            HmoRemittance::class,
            $remittance->id,
            $description,
            $lines
        );

        // Link journal entry back to remittance
        $remittance->journal_entry_id = $entry->id;
        $remittance->saveQuietly();

        Log::info('HmoRemittanceObserver: Journal entry created', [
            'remittance_id' => $remittance->id,
            'journal_entry_id' => $entry->id,
            'hmo_id' => $remittance->hmo_id,
            'amount' => $remittance->amount,
        ]);
    }

    /**
     * Get the bank account for this remittance.
     */
    protected function getBankAccount(HmoRemittance $remittance): ?Account
    {
        // If specific account_id set, use it
        if ($remittance->account_id) {
            return Account::find($remittance->account_id);
        }

        // If bank_id set, find its linked account
        if ($remittance->bank_id) {
            $bank = $remittance->bank;
            if ($bank && $bank->account_id) {
                return Account::find($bank->account_id);
            }
        }

        // Fallback based on payment method
        $code = match ($remittance->payment_method ?? 'bank_transfer') {
            'cash' => '1010',
            'cheque', 'check' => '1020',
            'bank_transfer', 'transfer' => '1020',
            default => '1020'
        };

        return Account::where('code', $code)->first();
    }

    /**
     * Build description for journal entry.
     */
    protected function buildDescription(HmoRemittance $remittance): string
    {
        // Format period dates safely
        $periodFrom = $remittance->period_from
            ? (is_string($remittance->period_from) ? $remittance->period_from : $remittance->period_from->format('Y-m-d'))
            : 'N/A';
        $periodTo = $remittance->period_to
            ? (is_string($remittance->period_to) ? $remittance->period_to : $remittance->period_to->format('Y-m-d'))
            : 'N/A';

        $parts = [
            "HMO Remittance Received",
            "HMO: " . ($remittance->hmo?->name ?? 'Unknown'),
            "Amount: " . number_format($remittance->amount, 2),
            "Period: {$periodFrom} to {$periodTo}",
        ];

        if ($remittance->reference_number) {
            $parts[] = "Ref: {$remittance->reference_number}";
        }

        return implode(' | ', $parts);
    }
}
