<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fixed Asset Observer
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 4.1B
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 5
 *
 * Creates journal entries for fixed asset acquisition.
 * Following JE-Centric Architecture - ALL numbers derive from journal_entries.
 *
 * Journal Entry on acquisition:
 *   DEBIT:  Fixed Asset Account (from category)
 *   CREDIT: Cash/Bank Account (from asset's bank_account_id)
 */
class FixedAssetObserver
{
    /**
     * Handle the FixedAsset "created" event.
     * Creates JE: DEBIT Fixed Asset, CREDIT Bank/Cash
     */
    public function created(FixedAsset $asset): void
    {
        Log::info('FixedAssetObserver: created() called', ['asset_id' => $asset->id]);

        if ($asset->journal_entry_id) {
            Log::info('FixedAssetObserver: Already has JE', ['journal_entry_id' => $asset->journal_entry_id]);
            return; // Already has JE
        }

        // Don't create acquisition JE for voided assets
        if ($asset->status === FixedAsset::STATUS_VOIDED) {
            Log::info('FixedAssetObserver: Skipping JE creation for voided asset', ['asset_id' => $asset->id]);
            return;
        }

        try {
            DB::beginTransaction();

            Log::info('FixedAssetObserver: Starting JE creation', ['asset_id' => $asset->id]);

            $category = $asset->category;

            if (!$category) {
                Log::error('FixedAssetObserver: Category not found', [
                    'asset_id' => $asset->id,
                ]);
                DB::rollBack();
                return;
            }

            $assetAccount = $category->assetAccount;

            if (!$assetAccount) {
                Log::error('FixedAssetObserver: Asset account not found', [
                    'category_id' => $category->id,
                    'asset_account_id' => $category->asset_account_id,
                ]);
                DB::rollBack();
                return;
            }

            // Determine credit account (bank or cash)
            $creditAccount = null;

            if ($asset->bank_account_id) {
                $creditAccount = Account::find($asset->bank_account_id);
            }

            // Fallback to default bank account
            if (!$creditAccount) {
                $creditAccount = Account::where('code', '1020')->first(); // Bank
            }

            // Last resort fallback to cash
            if (!$creditAccount) {
                $creditAccount = Account::where('code', '1010')->first(); // Cash
            }

            if (!$creditAccount) {
                Log::error('FixedAssetObserver: No credit account found', [
                    'asset_id' => $asset->id,
                    'bank_account_id' => $asset->bank_account_id,
                ]);
                DB::rollBack();
                return;
            }

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber(),
                'accounting_period_id' => AccountingPeriod::current()?->id,
                'entry_date' => $asset->acquisition_date,
                'reference_number' => "ACQ-{$asset->asset_number}",
                'reference_type' => 'fixed_asset_acquisition',
                'reference_id' => $asset->id,
                'description' => "Acquisition of fixed asset: {$asset->name} ({$asset->asset_number})",
                'entry_type' => JournalEntry::TYPE_AUTO,
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'created_by' => $asset->created_by ?? auth()->id() ?? 1,
            ]);

            // DEBIT: Fixed Asset Account
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'line_number' => 1,
                'account_id' => $assetAccount->id,
                'debit' => $asset->total_cost,
                'credit' => 0,
                'narration' => "Acquisition: {$asset->name}",
                'metadata' => [
                    'fixed_asset_id' => $asset->id,
                    'asset_number' => $asset->asset_number,
                    'category_id' => $category->id,
                    'department_id' => $asset->department_id,
                    'acquisition_cost' => $asset->acquisition_cost,
                    'additional_costs' => $asset->additional_costs,
                ],
            ]);

            // CREDIT: Bank/Cash Account
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'line_number' => 2,
                'account_id' => $creditAccount->id,
                'debit' => 0,
                'credit' => $asset->total_cost,
                'narration' => "Payment for: {$asset->name}",
                'metadata' => [
                    'fixed_asset_id' => $asset->id,
                    'asset_number' => $asset->asset_number,
                    'payment_method' => $asset->payment_method,
                    'invoice_number' => $asset->invoice_number,
                ],
            ]);

            // Link JE to asset
            $asset->updateQuietly(['journal_entry_id' => $journalEntry->id]);

            DB::commit();

            Log::info('FixedAssetObserver: JE created for acquisition', [
                'asset_id' => $asset->id,
                'asset_number' => $asset->asset_number,
                'journal_entry_id' => $journalEntry->id,
                'amount' => $asset->total_cost,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FixedAssetObserver: Failed to create JE', [
                'asset_id' => $asset->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
