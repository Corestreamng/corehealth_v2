<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\FixedAssetDepreciation;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fixed Asset Depreciation Observer
 *
 * Reference: ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md - Section 4.1B
 * Reference: ACCOUNTING_IMPLEMENTATION_CHECKLIST.md - Phase 5.5
 *
 * Creates journal entries for monthly depreciation.
 * Following JE-Centric Architecture - ALL numbers derive from journal_entries.
 *
 * Journal Entry on depreciation:
 *   DEBIT:  Depreciation Expense (6200)
 *   CREDIT: Accumulated Depreciation (1410)
 */
class DepreciationObserver
{
    /**
     * Handle the FixedAssetDepreciation "created" event.
     * Creates JE: DEBIT Depreciation Expense, CREDIT Accumulated Depreciation
     */
    public function created(FixedAssetDepreciation $depreciation): void
    {
        if ($depreciation->journal_entry_id) {
            return; // Already has JE
        }

        try {
            DB::beginTransaction();

            $asset = $depreciation->fixedAsset;
            $category = $asset->category;

            if (!$category) {
                Log::error('DepreciationObserver: Category not found', [
                    'asset_id' => $asset->id,
                ]);
                DB::rollBack();
                return;
            }

            $expenseAccount = $category->expenseAccount;          // e.g., 6200 Depreciation Expense
            $accumDepAccount = $category->depreciationAccount;    // e.g., 1410 Accumulated Depreciation

            if (!$expenseAccount || !$accumDepAccount) {
                Log::error('DepreciationObserver: Required accounts not found', [
                    'expense_account_id' => $category->expense_account_id,
                    'depreciation_account_id' => $category->depreciation_account_id,
                ]);
                DB::rollBack();
                return;
            }

            // Create journal entry
            $journalEntry = JournalEntry::create([
                'entry_date' => $depreciation->depreciation_date,
                'reference_number' => "DEP-{$asset->asset_number}-{$depreciation->year_number}-{$depreciation->month_number}",
                'reference_type' => 'fixed_asset_depreciation',
                'reference_id' => $depreciation->id,
                'description' => "Monthly depreciation: {$asset->name} ({$asset->asset_number}) - Y{$depreciation->year_number}M{$depreciation->month_number}",
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'created_by' => $depreciation->processed_by,
            ]);

            // DEBIT: Depreciation Expense
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $expenseAccount->id,
                'debit_amount' => $depreciation->depreciation_amount,
                'credit_amount' => 0,
                'description' => "Depreciation expense: {$asset->name}",
                'metadata' => [
                    'fixed_asset_id' => $asset->id,
                    'asset_number' => $asset->asset_number,
                    'category_id' => $category->id,
                    'department_id' => $asset->department_id,
                    'year_number' => $depreciation->year_number,
                    'month_number' => $depreciation->month_number,
                ],
            ]);

            // CREDIT: Accumulated Depreciation
            JournalEntryLine::create([
                'journal_entry_id' => $journalEntry->id,
                'account_id' => $accumDepAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $depreciation->depreciation_amount,
                'description' => "Accumulated depreciation: {$asset->name}",
                'metadata' => [
                    'fixed_asset_id' => $asset->id,
                    'asset_number' => $asset->asset_number,
                    'opening_book_value' => $depreciation->opening_book_value,
                    'closing_book_value' => $depreciation->closing_book_value,
                ],
            ]);

            // Link JE to depreciation record
            $depreciation->updateQuietly(['journal_entry_id' => $journalEntry->id]);

            DB::commit();

            Log::info('DepreciationObserver: JE created for depreciation', [
                'depreciation_id' => $depreciation->id,
                'asset_id' => $asset->id,
                'journal_entry_id' => $journalEntry->id,
                'amount' => $depreciation->depreciation_amount,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DepreciationObserver: Failed to create JE', [
                'depreciation_id' => $depreciation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
