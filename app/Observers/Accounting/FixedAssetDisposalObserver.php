<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\FixedAssetDisposal;
use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Bank;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fixed Asset Disposal Observer
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 4.1B
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 5
 * Reference: BANK_CASH_STATEMENT_IMPLEMENTATION.md - Bank Selection
 *
 * Creates journal entries when asset disposal is completed.
 * Following JE-Centric Architecture - ALL numbers derive from journal_entries.
 *
 * Journal Entry on disposal (sale with gain):
 *   DEBIT:  Cash/Bank (proceeds) - uses selected bank or cash account
 *   DEBIT:  Accumulated Depreciation (to date)
 *   CREDIT: Fixed Asset (original cost)
 *   CREDIT: Gain on Disposal (if proceeds > book value)
 *
 * Journal Entry on disposal (sale with loss):
 *   DEBIT:  Cash/Bank (proceeds) - uses selected bank or cash account
 *   DEBIT:  Accumulated Depreciation (to date)
 *   DEBIT:  Loss on Disposal (if proceeds < book value)
 *   CREDIT: Fixed Asset (original cost)
 */
class FixedAssetDisposalObserver
{
    // Account codes
    private const CASH_ACCOUNT = '1010';
    private const BANK_ACCOUNT = '1020';
    private const GAIN_ON_DISPOSAL = '4220';   // Gain on Disposal of Assets (Other Income)
    private const LOSS_ON_DISPOSAL = '6900';   // Loss on Disposal of Assets (Administrative Expenses)

    /**
     * Handle the FixedAssetDisposal "created" event.
     * Creates JE if disposal is created with 'completed' status.
     */
    public function created(FixedAssetDisposal $disposal): void
    {
        if ($disposal->status === FixedAssetDisposal::STATUS_COMPLETED &&
            !$disposal->journal_entry_id) {

            $this->createDisposalJournalEntry($disposal);
        }
    }

    /**
     * Handle the FixedAssetDisposal "updated" event.
     * Creates JE when status changes to 'completed'.
     */
    public function updated(FixedAssetDisposal $disposal): void
    {
        if ($disposal->isDirty('status') &&
            $disposal->status === FixedAssetDisposal::STATUS_COMPLETED &&
            !$disposal->journal_entry_id) {

            $this->createDisposalJournalEntry($disposal);
        }
    }

    /**
     * Create disposal journal entry.
     */
    private function createDisposalJournalEntry(FixedAssetDisposal $disposal): void
    {
        try {
            DB::beginTransaction();

            $asset = $disposal->fixedAsset;
            $category = $asset->category;

            if (!$category) {
                Log::error('FixedAssetDisposalObserver: Category not found');
                DB::rollBack();
                return;
            }

            // Get required accounts
            $assetAccount = $category->assetAccount;              // Fixed Asset account
            $accumDepAccount = $category->depreciationAccount;    // Accumulated Depreciation

            // Determine cash/bank account based on payment source
            $proceedsAccount = $this->getProceedsAccount($disposal);
            $proceedsLabel = $this->getProceedsLabel($disposal);

            $gainAccount = Account::where('code', self::GAIN_ON_DISPOSAL)->first();
            $lossAccount = Account::where('code', self::LOSS_ON_DISPOSAL)->first();

            if (!$assetAccount || !$accumDepAccount || !$proceedsAccount) {
                Log::error('FixedAssetDisposalObserver: Required accounts not found', [
                    'asset_account_found' => !is_null($assetAccount),
                    'accum_dep_account_found' => !is_null($accumDepAccount),
                    'proceeds_account_found' => !is_null($proceedsAccount),
                ]);
                DB::rollBack();
                return;
            }

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber(),
                'accounting_period_id' => AccountingPeriod::current()?->id,
                'entry_date' => $disposal->disposal_date,
                'reference_number' => "DISP-{$asset->asset_number}",
                'reference_type' => 'fixed_asset_disposal',
                'reference_id' => $disposal->id,
                'description' => "Disposal of fixed asset: {$asset->name} ({$asset->asset_number}) - " . ucfirst($disposal->disposal_type),
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'created_by' => $disposal->approved_by ?? auth()->id() ?? 1,
            ]);

            $totalDebits = 0;
            $totalCredits = 0;
            $lineNumber = 1;

            // DEBIT: Cash/Bank (if there are proceeds)
            if ($disposal->disposal_proceeds > 0) {
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'line_number' => $lineNumber++,
                    'account_id' => $proceedsAccount->id,
                    'debit' => $disposal->disposal_proceeds,
                    'credit' => 0,
                    'narration' => "Proceeds from disposal via {$proceedsLabel}: {$asset->name}",
                    'metadata' => [
                        'fixed_asset_id' => $asset->id,
                        'disposal_type' => $disposal->disposal_type,
                        'buyer_name' => $disposal->buyer_name,
                        'payment_method' => $disposal->payment_method,
                        'bank_id' => $disposal->bank_id,
                    ],
                ]);
                $totalDebits += $disposal->disposal_proceeds;
            }

            // DEBIT: Accumulated Depreciation (remove from contra account)
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'line_number' => $lineNumber++,
                'account_id' => $accumDepAccount->id,
                'debit' => $asset->accumulated_depreciation,
                'credit' => 0,
                'narration' => "Remove accumulated depreciation: {$asset->name}",
                'metadata' => [
                    'fixed_asset_id' => $asset->id,
                ],
            ]);
            $totalDebits += $asset->accumulated_depreciation;

            // CREDIT: Fixed Asset (remove asset at original cost)
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'line_number' => $lineNumber++,
                'account_id' => $assetAccount->id,
                'debit' => 0,
                'credit' => $asset->total_cost,
                'narration' => "Remove fixed asset: {$asset->name}",
                'metadata' => [
                    'fixed_asset_id' => $asset->id,
                    'original_cost' => $asset->total_cost,
                ],
            ]);
            $totalCredits += $asset->total_cost;

            // Handle gain or loss
            $gainLoss = $disposal->gain_loss_on_disposal;

            if ($gainLoss > 0 && $gainAccount) {
                // CREDIT: Gain on Disposal
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'line_number' => $lineNumber++,
                    'account_id' => $gainAccount->id,
                    'debit' => 0,
                    'credit' => $gainLoss,
                    'narration' => "Gain on disposal: {$asset->name}",
                    'metadata' => [
                        'fixed_asset_id' => $asset->id,
                    ],
                ]);
                $totalCredits += $gainLoss;
            } elseif ($gainLoss < 0 && $lossAccount) {
                // DEBIT: Loss on Disposal
                $loss = abs($gainLoss);
                JournalEntryLine::create([
                    'journal_entry_id' => $journalEntry->id,
                    'line_number' => $lineNumber++,
                    'account_id' => $lossAccount->id,
                    'debit' => $loss,
                    'credit' => 0,
                    'narration' => "Loss on disposal: {$asset->name}",
                    'metadata' => [
                        'fixed_asset_id' => $asset->id,
                    ],
                ]);
                $totalDebits += $loss;
            }

            // Verify debits = credits
            if (abs($totalDebits - $totalCredits) > 0.01) {
                Log::warning('FixedAssetDisposalObserver: JE imbalance', [
                    'debits' => $totalDebits,
                    'credits' => $totalCredits,
                    'difference' => $totalDebits - $totalCredits,
                ]);
            }

            // Link JE to disposal
            $disposal->updateQuietly(['journal_entry_id' => $journalEntry->id]);

            // Update asset status
            $asset->status = FixedAsset::STATUS_DISPOSED;
            $asset->disposal_date = $disposal->disposal_date;
            $asset->save();

            DB::commit();

            Log::info('FixedAssetDisposalObserver: JE created for disposal', [
                'disposal_id' => $disposal->id,
                'asset_id' => $asset->id,
                'journal_entry_id' => $journalEntry->id,
                'gain_loss' => $gainLoss,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FixedAssetDisposalObserver: Failed to create JE', [
                'disposal_id' => $disposal->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the account for receiving disposal proceeds.
     * Priority: bank_id → payment_method → default bank account
     */
    private function getProceedsAccount(FixedAssetDisposal $disposal): ?Account
    {
        // 1. If bank_id is set, use the bank's linked GL account
        if ($disposal->bank_id) {
            $bank = Bank::find($disposal->bank_id);
            if ($bank && $bank->account_id) {
                return Account::find($bank->account_id);
            }
        }

        // 2. If payment_method is cash, use Cash in Hand
        if ($disposal->payment_method === FixedAssetDisposal::METHOD_CASH) {
            return Account::where('code', self::CASH_ACCOUNT)->first();
        }

        // 3. Default to generic Bank Account (1020)
        return Account::where('code', self::BANK_ACCOUNT)->first();
    }

    /**
     * Get a label describing the proceeds source for JE description.
     */
    private function getProceedsLabel(FixedAssetDisposal $disposal): string
    {
        if ($disposal->bank_id) {
            $bank = Bank::find($disposal->bank_id);
            return $bank ? $bank->name : 'Bank';
        }

        if ($disposal->payment_method === FixedAssetDisposal::METHOD_CASH) {
            return 'Cash';
        }

        return 'Bank';
    }
}
