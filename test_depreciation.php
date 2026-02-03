<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\FixedAssetCategory;
use App\Models\Accounting\FixedAssetDepreciation;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Accounting\FixedAssetService;
use Illuminate\Support\Facades\DB;

echo "=== DEPRECIATION TEST ===\n\n";

// 1. Check if we have active, depreciable assets
$assets = FixedAsset::depreciable()
    ->with('category')
    ->get();

echo "1. Total Active Depreciable Assets: " . $assets->count() . "\n\n";

if ($assets->isEmpty()) {
    echo "No active depreciable assets found. Create a test asset first.\n";
    exit;
}

// 2. Show first asset details
$testAsset = $assets->first();
echo "2. Test Asset Details:\n";
echo "   Asset Number: {$testAsset->asset_number}\n";
echo "   Name: {$testAsset->name}\n";
echo "   Category: {$testAsset->category->name}\n";
echo "   Acquisition Cost: ₦" . number_format($testAsset->acquisition_cost, 2) . "\n";
echo "   Current Book Value: ₦" . number_format($testAsset->book_value, 2) . "\n";
echo "   Accumulated Depreciation: ₦" . number_format($testAsset->accumulated_depreciation, 2) . "\n";
echo "   Monthly Depreciation: ₦" . number_format($testAsset->monthly_depreciation, 2) . "\n";
echo "   Last Depreciation: " . ($testAsset->last_depreciation_date ? $testAsset->last_depreciation_date->format('Y-m-d') : 'Never') . "\n";
echo "   Needs Depreciation: " . ($testAsset->needsDepreciation() ? 'Yes' : 'No') . "\n\n";

// 3. Check category GL account mapping
echo "3. Category GL Account Mapping:\n";
$category = $testAsset->category;
echo "   Asset Account: " . ($category->assetAccount ? "{$category->assetAccount->code} - {$category->assetAccount->name}" : "NOT SET") . "\n";
echo "   Accumulated Depreciation Account: " . ($category->depreciationAccount ? "{$category->depreciationAccount->code} - {$category->depreciationAccount->name}" : "NOT SET") . "\n";
echo "   Depreciation Expense Account: " . ($category->expenseAccount ? "{$category->expenseAccount->code} - {$category->expenseAccount->name}" : "NOT SET") . "\n\n";

// 4. Check existing depreciation history
$depreciationCount = FixedAssetDepreciation::where('fixed_asset_id', $testAsset->id)->count();
echo "4. Existing Depreciation Records: {$depreciationCount}\n";

if ($depreciationCount > 0) {
    $lastDep = FixedAssetDepreciation::where('fixed_asset_id', $testAsset->id)
        ->with('journalEntry.lines.account')
        ->latest('depreciation_date')
        ->first();

    echo "   Last Depreciation:\n";
    echo "   - Date: {$lastDep->depreciation_date}\n";
    echo "   - Amount: ₦" . number_format($lastDep->depreciation_amount, 2) . "\n";
    echo "   - Journal Entry: " . ($lastDep->journal_entry_id ? "#{$lastDep->journal_entry_id}" : "NOT CREATED") . "\n";

    if ($lastDep->journalEntry) {
        echo "\n   Journal Entry Lines:\n";
        foreach ($lastDep->journalEntry->lines as $line) {
            $type = $line->debit_amount > 0 ? 'DR' : 'CR';
            $amount = $line->debit_amount > 0 ? $line->debit_amount : $line->credit_amount;
            echo "   - {$type} {$line->account->code} {$line->account->name}: ₦" . number_format($amount, 2) . "\n";
        }
    }
}
echo "\n";

// 5. Test running depreciation
echo "5. Testing Depreciation Run...\n";
if (!$testAsset->needsDepreciation()) {
    echo "   ⚠️  Asset does not need depreciation right now.\n";
    echo "   (Already depreciated this month or not yet in service)\n\n";
} else {
    echo "   Asset needs depreciation. Processing...\n\n";

    try {
        DB::beginTransaction();

        $service = new FixedAssetService();
        $result = $service->runMonthlyDepreciation(auth()->id() ?? 1);

        echo "   ✅ Depreciation Run Complete!\n";
        echo "   - Processed: {$result['processed']} assets\n";
        echo "   - Skipped: {$result['skipped']} assets\n";
        echo "   - Errors: {$result['errors']}\n";
        echo "   - Total Depreciation: ₦" . number_format($result['total_depreciation'], 2) . "\n\n";

        // 6. Verify the journal entry was created
        $latestDep = FixedAssetDepreciation::where('fixed_asset_id', $testAsset->id)
            ->with('journalEntry.lines.account')
            ->latest('created_at')
            ->first();

        if ($latestDep && $latestDep->journalEntry) {
            echo "6. ✅ Journal Entry Created Successfully!\n";
            echo "   Entry Number: {$latestDep->journalEntry->entry_number}\n";
            echo "   Entry Date: {$latestDep->journalEntry->entry_date}\n";
            echo "   Status: {$latestDep->journalEntry->status}\n";
            echo "   Reference: {$latestDep->journalEntry->reference_number}\n\n";

            echo "   Journal Entry Lines:\n";
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($latestDep->journalEntry->lines as $line) {
                $type = $line->debit_amount > 0 ? 'DEBIT' : 'CREDIT';
                $amount = $line->debit_amount > 0 ? $line->debit_amount : $line->credit_amount;

                echo "   {$type}: {$line->account->code} - {$line->account->name}\n";
                echo "          Amount: ₦" . number_format($amount, 2) . "\n";
                echo "          Description: {$line->description}\n\n";

                $totalDebit += $line->debit_amount;
                $totalCredit += $line->credit_amount;
            }

            echo "   Total Debits:  ₦" . number_format($totalDebit, 2) . "\n";
            echo "   Total Credits: ₦" . number_format($totalCredit, 2) . "\n";
            echo "   Balanced: " . ($totalDebit == $totalCredit ? '✅ Yes' : '❌ NO - UNBALANCED!') . "\n\n";

            // 7. Verify correct accounts were used
            echo "7. Account Verification:\n";
            $debitLine = $latestDep->journalEntry->lines->where('debit_amount', '>', 0)->first();
            $creditLine = $latestDep->journalEntry->lines->where('credit_amount', '>', 0)->first();

            $expectedExpenseAccount = $category->expenseAccount;
            $expectedDepreciationAccount = $category->depreciationAccount;

            if ($debitLine && $debitLine->account_id == $expectedExpenseAccount->id) {
                echo "   ✅ DEBIT to correct Depreciation Expense account: {$expectedExpenseAccount->code} - {$expectedExpenseAccount->name}\n";
            } else {
                echo "   ❌ DEBIT to WRONG account!\n";
                echo "      Expected: {$expectedExpenseAccount->code} - {$expectedExpenseAccount->name}\n";
                echo "      Got: " . ($debitLine ? "{$debitLine->account->code} - {$debitLine->account->name}" : "None") . "\n";
            }

            if ($creditLine && $creditLine->account_id == $expectedDepreciationAccount->id) {
                echo "   ✅ CREDIT to correct Accumulated Depreciation account: {$expectedDepreciationAccount->code} - {$expectedDepreciationAccount->name}\n";
            } else {
                echo "   ❌ CREDIT to WRONG account!\n";
                echo "      Expected: {$expectedDepreciationAccount->code} - {$expectedDepreciationAccount->name}\n";
                echo "      Got: " . ($creditLine ? "{$creditLine->account->code} - {$creditLine->account->name}" : "None") . "\n";
            }

            echo "\n";
        } else {
            echo "6. ❌ ERROR: Depreciation record created but NO journal entry!\n";
            echo "   Check DepreciationObserver logs for errors.\n\n";
        }

        // Rollback to avoid affecting production data
        DB::rollBack();
        echo "✅ Test complete (transaction rolled back - no data changed)\n";

    } catch (\Exception $e) {
        DB::rollBack();
        echo "   ❌ ERROR: " . $e->getMessage() . "\n";
        echo "   File: " . $e->getFile() . "\n";
        echo "   Line: " . $e->getLine() . "\n";
    }
}

echo "\n=== TEST COMPLETE ===\n";
