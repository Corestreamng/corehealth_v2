<?php

/**
 * Test script to verify asset void functionality
 * Tests:
 * 1. Create a test asset (triggers acquisition JE via observer)
 * 2. Verify acquisition JE was created
 * 3. Void the asset
 * 4. Verify reversal JE was created
 * 5. Verify asset status changed to VOIDED
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\FixedAssetCategory;
use App\Models\Accounting\JournalEntry;
use App\Models\Department;
use App\Services\Accounting\FixedAssetService;
use Illuminate\Support\Facades\DB;

echo "=== FIXED ASSET VOID TEST ===\n\n";

// Note: This test will actually create and void an asset (not rolled back)
// to properly test the void functionality
echo "⚠️  NOTE: This test will create real data (test asset + journal entries)\n";
echo "   The data will be marked as test data but will remain in the database.\n\n";

try {
    // Get necessary data
    $category = FixedAssetCategory::where('name', 'like', '%Building%')
        ->orWhere('name', 'like', '%Furniture%')
        ->first();

    if (!$category) {
        echo "❌ No suitable category found. Please create a category first.\n";
        exit(1);
    }

    $department = Department::first();
    if (!$department) {
        echo "❌ No department found. Please create a department first.\n";
        exit(1);
    }

    echo "Using Category: {$category->name}\n";
    echo "Department: {$department->name}\n\n";

    // Step 1: Create test asset
    echo "Step 1: Creating test asset...\n";
    $assetData = [
        'name' => 'TEST VOID - Office Desk ' . date('YmdHis'),
        'category_id' => $category->id,
        'department_id' => $department->id,
        'acquisition_date' => date('Y-m-d'),
        'acquisition_cost' => 25000.00,
        'additional_costs' => 0,
        'salvage_value' => 2500.00,
        'useful_life_years' => 5,
        'description' => 'Test asset for void functionality',
        'serial_number' => 'TEST-VOID-' . time(),
        'supplier' => 'Test Supplier',
        'payment_method' => 'bank_transfer',
        'bank_id' => 1, // Assuming bank exists
    ];

    $service = new FixedAssetService();
    $asset = $service->createAsset($assetData);

    // Reload asset to get updated fields from observer
    $asset->refresh();

    echo "✓ Asset created: {$asset->name} (ID: {$asset->id})\n";
    echo "  Asset Number: {$asset->asset_number}\n";
    echo "  Cost: ₦" . number_format($asset->total_cost, 2) . "\n";
    echo "  Status: {$asset->status}\n";
    echo "  Journal Entry ID: " . ($asset->journal_entry_id ?? 'NULL') . "\n\n";

    // Step 2: Verify acquisition JE was created
    echo "Step 2: Verifying acquisition journal entry...\n";

    // First check using journal_entry_id from asset
    if ($asset->journal_entry_id) {
        $acquisitionJE = JournalEntry::find($asset->journal_entry_id);
        echo "  Found JE via asset->journal_entry_id: {$asset->journal_entry_id}\n";
    } else {
        // Fallback to querying by reference
        $acquisitionJE = JournalEntry::where('reference_type', FixedAsset::class)
            ->where('reference_id', $asset->id)
            ->where('description', 'like', '%acquisition%')
            ->where('status', JournalEntry::STATUS_POSTED)
            ->first();
        echo "  Searched by reference_type/reference_id\n";
    }

    if (!$acquisitionJE) {
        echo "❌ Acquisition journal entry not found!\n";
        // Removed transaction rollback
        exit(1);
    }

    echo "✓ Acquisition JE found: {$acquisitionJE->entry_number}\n";
    echo "  Date: {$acquisitionJE->entry_date}\n";
    echo "  Description: {$acquisitionJE->description}\n";
    echo "  Lines:\n";
    foreach ($acquisitionJE->lines as $line) {
        $account = $line->account;
        $type = $line->debit > 0 ? 'DEBIT' : 'CREDIT';
        $amount = $line->debit > 0 ? $line->debit : $line->credit;
        echo "    {$type}: {$account->code} - {$account->name} = ₦" . number_format($amount, 2) . "\n";
    }
    echo "\n";

    // Step 3: Check if asset can be voided
    echo "Step 3: Checking if asset can be voided...\n";
    if (!$asset->canBeVoided()) {
        echo "❌ Asset cannot be voided!\n";
        echo "  Reasons:\n";
        if ($asset->isVoided()) echo "    - Already voided\n";
        if ($asset->status === FixedAsset::STATUS_DISPOSED) echo "    - Already disposed\n";
        if ($asset->depreciations()->exists()) echo "    - Has depreciation records\n";
        if (!$acquisitionJE->canReverse()) echo "    - Acquisition JE cannot be reversed\n";
        // Removed transaction rollback
        exit(1);
    }
    echo "✓ Asset can be voided\n\n";

    // Step 4: Void the asset
    echo "Step 4: Voiding asset...\n";
    $voidReason = "Test void operation - verifying functionality";
    $voidedBy = 1; // Assuming user ID 1 exists
    $result = $service->voidAsset($asset, $voidReason, $voidedBy);

    echo "✓ Asset voided successfully\n";
    echo "  Void reason: {$voidReason}\n";
    echo "  Voided by user ID: {$voidedBy}\n\n";

    // Step 5: Verify asset status changed
    echo "Step 5: Verifying asset status...\n";
    $asset->refresh();
    echo "  New status: {$asset->status}\n";

    if ($asset->status !== FixedAsset::STATUS_VOIDED) {
        echo "❌ Asset status not updated to VOIDED!\n";
        // Removed transaction rollback
        exit(1);
    }
    echo "✓ Asset status correctly set to VOIDED\n\n";

    // Step 6: Verify reversal JE was created
    echo "Step 6: Verifying reversal journal entry...\n";
    $acquisitionJE->refresh();

    if ($acquisitionJE->status !== JournalEntry::STATUS_REVERSED) {
        echo "❌ Acquisition JE not reversed!\n";
        echo "  Current status: {$acquisitionJE->status}\n";
        // Removed transaction rollback
        exit(1);
    }

    // Get reversal JE via reversed_by_id
    $reversalJE = JournalEntry::find($acquisitionJE->reversed_by_id);
    if (!$reversalJE) {
        echo "❌ Reversal JE not found!\n";
        // Removed transaction rollback
        exit(1);
    }

    echo "✓ Reversal JE created: {$reversalJE->entry_number}\n";
    echo "  Date: {$reversalJE->entry_date}\n";
    echo "  Description: {$reversalJE->description}\n";
    echo "  Lines:\n";
    foreach ($reversalJE->lines as $line) {
        $account = $line->account;
        $type = $line->debit > 0 ? 'DEBIT' : 'CREDIT';
        $amount = $line->debit > 0 ? $line->debit : $line->credit;
        echo "    {$type}: {$account->code} - {$account->name} = ₦" . number_format($amount, 2) . "\n";
    }
    echo "\n";

    // Step 7: Verify amounts are reversed correctly
    echo "Step 7: Verifying reversal amounts...\n";
    $originalDebit = $acquisitionJE->lines->where('debit', '>', 0)->first();
    $originalCredit = $acquisitionJE->lines->where('credit', '>', 0)->first();
    $reversalDebit = $reversalJE->lines->where('debit', '>', 0)->first();
    $reversalCredit = $reversalJE->lines->where('credit', '>', 0)->first();

    if (!$originalDebit || !$originalCredit || !$reversalDebit || !$reversalCredit) {
        echo "❌ Missing journal entry lines!\n";
        // Removed transaction rollback
        exit(1);
    }

    // Original: DR Asset, CR Bank
    // Reversal should be: DR Bank, CR Asset
    echo "  Original Debit Account: {$originalDebit->account->code}\n";
    echo "  Reversal Credit Account: {$reversalCredit->account->code}\n";
    if ($originalDebit->account_id !== $reversalCredit->account_id) {
        echo "❌ Reversal credit account doesn't match original debit account!\n";
        // Removed transaction rollback
        exit(1);
    }

    echo "  Original Credit Account: {$originalCredit->account->code}\n";
    echo "  Reversal Debit Account: {$reversalDebit->account->code}\n";
    if ($originalCredit->account_id !== $reversalDebit->account_id) {
        echo "❌ Reversal debit account doesn't match original credit account!\n";
        // Removed transaction rollback
        exit(1);
    }

    if ($originalDebit->debit != $reversalCredit->credit) {
        echo "❌ Reversal amounts don't match!\n";
        // Removed transaction rollback
        exit(1);
    }

    echo "✓ Reversal amounts are correct\n";
    echo "  Original: DR ₦" . number_format($originalDebit->debit, 2) . " / CR ₦" . number_format($originalCredit->credit, 2) . "\n";
    echo "  Reversal: DR ₦" . number_format($reversalDebit->debit, 2) . " / CR ₦" . number_format($reversalCredit->credit, 2) . "\n\n";

    // Step 8: Try to void again (should fail)
    echo "Step 8: Testing double-void prevention...\n";
    if ($asset->canBeVoided()) {
        echo "❌ Asset can still be voided after already being voided!\n";
        exit(1);
    }
    echo "✓ Double-void correctly prevented\n\n";

    echo "======================\n";
    echo "✅ ALL TESTS PASSED!\n";
    echo "======================\n\n";
    echo "Summary:\n";
    echo "  ✓ Asset created successfully\n";
    echo "  ✓ Acquisition JE created by observer\n";
    echo "  ✓ Asset can be voided (validation works)\n";
    echo "  ✓ Void operation executed successfully\n";
    echo "  ✓ Asset status changed to VOIDED\n";
    echo "  ✓ Reversal JE created with correct amounts\n";
    echo "  ✓ Double-void prevention works\n\n";

    echo "Test Asset Details:\n";
    echo "  Asset ID: {$asset->id}\n";
    echo "  Asset Number: {$asset->asset_number}\n";
    echo "  Acquisition JE: {$acquisitionJE->entry_number}\n";
    echo "  Reversal JE: {$reversalJE->entry_number}\n\n";

} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
