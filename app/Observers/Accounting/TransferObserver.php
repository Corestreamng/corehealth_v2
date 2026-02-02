<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\Account;
use App\Models\InterAccountTransfer;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Transfer Observer
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 6.14
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 1.6
 *
 * Creates journal entries when inter-account transfers are cleared:
 *
 * CLEARED TRANSFER:
 *   DEBIT:  Destination Bank Account
 *   CREDIT: Source Bank Account
 *
 * If transfer fee exists:
 *   DEBIT:  Bank Charges Expense
 *   CREDIT: Source Bank Account (additional)
 */
class TransferObserver
{
    /**
     * Handle the InterAccountTransfer "updated" event.
     */
    public function updated(InterAccountTransfer $transfer): void
    {
        // Only create journal entry when transfer is cleared
        if ($transfer->isDirty('status') && $transfer->status === InterAccountTransfer::STATUS_CLEARED) {
            try {
                $this->createTransferJournalEntry($transfer);
            } catch (\Exception $e) {
                Log::error('TransferObserver: Failed to create journal entry', [
                    'transfer_id' => $transfer->id,
                    'transfer_number' => $transfer->transfer_number,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Create journal entry for cleared transfer.
     */
    protected function createTransferJournalEntry(InterAccountTransfer $transfer): void
    {
        $accountingService = App::make(AccountingService::class);

        // Validate accounts
        $fromAccount = $transfer->fromAccount;
        $toAccount = $transfer->toAccount;

        if (!$fromAccount || !$toAccount) {
            Log::warning('TransferObserver: Source or destination account not configured', [
                'transfer_id' => $transfer->id,
                'from_account_id' => $transfer->from_account_id,
                'to_account_id' => $transfer->to_account_id,
            ]);
            return;
        }

        $description = sprintf(
            'Inter-Account Transfer: %s - %s to %s',
            $transfer->transfer_number,
            $transfer->fromBank?->name ?? 'Unknown',
            $transfer->toBank?->name ?? 'Unknown'
        );

        $lines = [
            // DEBIT destination (money coming in)
            [
                'account_id' => $toAccount->id,
                'debit_amount' => $transfer->amount,
                'credit_amount' => 0,
                'description' => sprintf(
                    'Transfer received from %s',
                    $transfer->fromBank?->name ?? 'Unknown'
                ),
                // METADATA
                'category' => 'inter_account_transfer',
            ],
            // CREDIT source (money going out)
            [
                'account_id' => $fromAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $transfer->amount,
                'description' => sprintf(
                    'Transfer sent to %s',
                    $transfer->toBank?->name ?? 'Unknown'
                ),
                // METADATA
                'category' => 'inter_account_transfer',
            ]
        ];

        // Add transfer fee entry if applicable
        if ($transfer->transfer_fee > 0) {
            $feeAccount = $transfer->feeAccount ?? Account::where('code', '6100')->first(); // Bank Charges

            if ($feeAccount) {
                // DEBIT bank charges expense
                $lines[] = [
                    'account_id' => $feeAccount->id,
                    'debit_amount' => $transfer->transfer_fee,
                    'credit_amount' => 0,
                    'description' => sprintf(
                        'Transfer fee - %s (%s)',
                        $transfer->transfer_number,
                        $transfer->transfer_method_label
                    ),
                    'category' => 'bank_charges',
                ];

                // CREDIT source bank for fee
                $lines[] = [
                    'account_id' => $fromAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $transfer->transfer_fee,
                    'description' => 'Transfer fee deducted',
                    'category' => 'bank_charges',
                ];
            }
        }

        $entry = $accountingService->createAndPostAutomatedEntry(
            InterAccountTransfer::class,
            $transfer->id,
            $description,
            $lines
        );

        // Link journal entry to transfer
        $transfer->journal_entry_id = $entry->id;
        $transfer->cleared_at = now();
        $transfer->actual_clearance_date = now()->toDateString();
        $transfer->saveQuietly();

        Log::info('TransferObserver: Transfer journal entry created', [
            'transfer_id' => $transfer->id,
            'transfer_number' => $transfer->transfer_number,
            'journal_entry_id' => $entry->id,
            'amount' => $transfer->amount,
            'fee' => $transfer->transfer_fee,
        ]);
    }
}
