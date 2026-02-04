<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\PettyCashReconciliation;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Petty Cash Reconciliation Observer
 *
 * Creates adjustment journal entries when reconciliations with variances are approved:
 *
 * SHORTAGE (Expected > Actual, cash is missing):
 *   DEBIT:  Cash Over/Short (Expense - 6270)
 *   CREDIT: Petty Cash (Asset - Fund's account)
 *
 * OVERAGE (Expected < Actual, extra cash found):
 *   DEBIT:  Petty Cash (Asset - Fund's account)
 *   CREDIT: Cash Over/Short (Expense - 6270)
 *
 * Note: Cash Over/Short is an expense account (debit normal).
 * - Shortages increase the expense (debit)
 * - Overages decrease the expense (credit), effectively income
 */
class PettyCashReconciliationObserver
{
    /**
     * Handle the PettyCashReconciliation "updated" event.
     */
    public function updated(PettyCashReconciliation $reconciliation): void
    {
        // Only create journal entry when approval_status changes to approved
        // and there's a variance to adjust
        if ($reconciliation->wasChanged('approval_status')
            && $reconciliation->approval_status === PettyCashReconciliation::APPROVAL_APPROVED
            && $reconciliation->hasVariance()
        ) {
            // Skip if already has adjustment JE
            if ($reconciliation->adjustment_entry_id) {
                Log::info('PettyCashReconciliationObserver: Reconciliation already has adjustment JE', [
                    'reconciliation_id' => $reconciliation->id,
                    'adjustment_entry_id' => $reconciliation->adjustment_entry_id
                ]);
                return;
            }

            Log::info('PettyCashReconciliationObserver: Creating adjustment JE for approved reconciliation', [
                'reconciliation_id' => $reconciliation->id,
                'reconciliation_number' => $reconciliation->reconciliation_number,
                'variance' => $reconciliation->variance,
                'status' => $reconciliation->status
            ]);

            try {
                $this->createAdjustmentEntry($reconciliation);
            } catch (\Exception $e) {
                Log::error('PettyCashReconciliationObserver: Failed to create adjustment entry', [
                    'reconciliation_id' => $reconciliation->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Create adjustment journal entry for variance.
     */
    protected function createAdjustmentEntry(PettyCashReconciliation $reconciliation): void
    {
        $accountingService = App::make(AccountingService::class);

        // Get fund's GL account (petty cash asset)
        $fund = $reconciliation->fund;
        if (!$fund || !$fund->account_id) {
            Log::warning('PettyCashReconciliationObserver: Fund or account not configured', [
                'reconciliation_id' => $reconciliation->id
            ]);
            return;
        }

        $pettyCashAccount = $fund->account;

        // Get Cash Over/Short account (6270)
        $cashOverShortAccount = Account::where('code', PettyCashReconciliation::CASH_OVER_SHORT_ACCOUNT_CODE)->first();

        if (!$cashOverShortAccount) {
            Log::error('PettyCashReconciliationObserver: Cash Over/Short account not found', [
                'expected_code' => PettyCashReconciliation::CASH_OVER_SHORT_ACCOUNT_CODE
            ]);
            return;
        }

        $absVariance = abs($reconciliation->variance);

        // Variance = Expected - Actual
        // If variance > 0: shortage (expected > actual, cash missing)
        //   -> Debit Cash Over/Short (expense), Credit Petty Cash
        // If variance < 0: overage (expected < actual, extra cash)
        //   -> Debit Petty Cash, Credit Cash Over/Short

        if ($reconciliation->hasShortage()) {
            // SHORTAGE: Cash is missing
            $description = sprintf(
                'Petty Cash Shortage Adjustment: %s - Physical count ₦%s vs Book ₦%s',
                $reconciliation->reconciliation_number,
                number_format($reconciliation->actual_cash_count, 2),
                number_format($reconciliation->expected_balance, 2)
            );

            $lines = [
                [
                    'account_id' => $cashOverShortAccount->id,
                    'debit_amount' => $absVariance,
                    'credit_amount' => 0,
                    'description' => 'Cash Shortage - ' . ($reconciliation->notes ?? 'Reconciliation adjustment'),
                    'category' => 'petty_cash_adjustment',
                ],
                [
                    'account_id' => $pettyCashAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $absVariance,
                    'description' => 'Petty Cash Adjustment - Shortage',
                    'category' => 'petty_cash_adjustment',
                ]
            ];
        } else {
            // OVERAGE: Extra cash found
            $description = sprintf(
                'Petty Cash Overage Adjustment: %s - Physical count ₦%s vs Book ₦%s',
                $reconciliation->reconciliation_number,
                number_format($reconciliation->actual_cash_count, 2),
                number_format($reconciliation->expected_balance, 2)
            );

            $lines = [
                [
                    'account_id' => $pettyCashAccount->id,
                    'debit_amount' => $absVariance,
                    'credit_amount' => 0,
                    'description' => 'Petty Cash Adjustment - Overage',
                    'category' => 'petty_cash_adjustment',
                ],
                [
                    'account_id' => $cashOverShortAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $absVariance,
                    'description' => 'Cash Overage - ' . ($reconciliation->notes ?? 'Reconciliation adjustment'),
                    'category' => 'petty_cash_adjustment',
                ]
            ];
        }

        $entry = $accountingService->createAndPostAutomatedEntry(
            PettyCashReconciliation::class,
            $reconciliation->id,
            $description,
            $lines
        );

        // Link adjustment entry to reconciliation
        $reconciliation->adjustment_entry_id = $entry->id;
        $reconciliation->saveQuietly();

        // Update fund's cached balance to match actual count
        $fund->current_balance = $fund->getBalanceFromJournalEntries();
        $fund->saveQuietly();

        Log::info('PettyCashReconciliationObserver: Adjustment entry created', [
            'reconciliation_id' => $reconciliation->id,
            'journal_entry_id' => $entry->id,
            'variance' => $reconciliation->variance,
            'status' => $reconciliation->status,
        ]);
    }
}
