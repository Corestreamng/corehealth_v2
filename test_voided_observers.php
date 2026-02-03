<?php

/**
 * Test to verify observers properly handle voided assets
 *
 * Tests:
 * 1. Voided asset doesn't get acquisition JE when created
 * 2. Voided asset doesn't get depreciation JE when depreciation recorded
 * 3. Active asset that gets voided stops receiving depreciation
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\FixedAssetCategory;
use App\Models\Accounting\FixedAssetDepreciation;
use App\Models\Accounting\JournalEntry;
use App\Models\Department;
use App\Services\Accounting\FixedAssetService;
use Illuminate\Support\Facades\DB;

echo "=== VOIDED ASSET OBSERVER TEST ===\n\n";

try {
    // Get necessary data
    $category = FixedAssetCategory::where('name', 'like', '%Building%')
        ->orWhere('name', 'like', '%Furniture%')
        ->first();

    if (!$category) {
        echo "❌ No suitable category found.\n";
        exit(1);
    }

    $department = Department::first();
    if (!$department) {
        echo "❌ No department found.\n";
        exit(1);
    }

    echo "Using Category: {$category->name}\n";
    echo "Department: {$department->name}\n\n";

    // ========================================
    // TEST 1: Active asset gets acquisition JE
    // ========================================
    echo "TEST 1: Verify active asset gets acquisition JE...\n";

    $service = new FixedAssetService();
    $activeAsset = $service->createAsset([
        'name' => 'TEST OBSERVER - Active Asset ' . date('YmdHis'),
        'category_id' => $category->id,
        'department_id' => $department->id,
        'acquisition_date' => date('Y-m-d'),
        'acquisition_cost' => 10000.00,
        'additional_costs' => 0,
        'salvage_value' => 1000.00,
        'useful_life_years' => 5,
        'description' => 'Test active asset',
        'serial_number' => 'TEST-OBS-ACTIVE-' . time(),
    ]);

    $activeAsset->refresh();

    if (!$activeAsset->journal_entry_id) {
        echo "❌ Active asset should have acquisition JE!\n";
        exit(1);
    }

    $activeJE = JournalEntry::find($activeAsset->journal_entry_id);
    if (!$activeJE) {
        echo "❌ Acquisition JE not found in database!\n";
        exit(1);
    }

    echo "✓ Active asset correctly received acquisition JE\n";
    echo "  Asset ID: {$activeAsset->id}\n";
    echo "  JE ID: {$activeJE->id}\n";
    echo "  JE Number: {$activeJE->entry_number}\n\n";

    // ========================================
    // TEST 2: Manually void the asset
    // ========================================
    echo "TEST 2: Void the asset and verify it can't be depreciated...\n";

    $voidResult = $service->voidAsset($activeAsset, "Test voiding for observer check", 1);
    $activeAsset->refresh();

    if ($activeAsset->status !== FixedAsset::STATUS_VOIDED) {
        echo "❌ Asset not voided!\n";
        exit(1);
    }

    echo "✓ Asset successfully voided\n";
    echo "  New status: {$activeAsset->status}\n\n";

    // ========================================
    // TEST 3: Verify voided asset excluded from depreciation run
    // ========================================
    echo "TEST 3: Verify voided asset excluded from depreciation...\n";

    // Check if asset needs depreciation
    if ($activeAsset->needsDepreciation()) {
        echo "❌ Voided asset should NOT need depreciation!\n";
        echo "  needsDepreciation() returned true\n";
        exit(1);
    }

    echo "✓ Voided asset correctly excluded by needsDepreciation()\n";

    // Check depreciable scope
    $depreciableCount = FixedAsset::depreciable()
        ->where('id', $activeAsset->id)
        ->count();

    if ($depreciableCount > 0) {
        echo "❌ Voided asset should NOT be in depreciable scope!\n";
        exit(1);
    }

    echo "✓ Voided asset correctly excluded by depreciable() scope\n\n";

    // ========================================
    // TEST 4: Try to manually create depreciation (should be blocked by observer)
    // ========================================
    echo "TEST 4: Verify observer blocks depreciation JE for voided asset...\n";

    // Manually create a depreciation record (bypassing service layer checks)
    $manualDepreciation = FixedAssetDepreciation::create([
        'fixed_asset_id' => $activeAsset->id,
        'depreciation_date' => now(),
        'year_number' => 1,
        'month_number' => 1,
        'depreciation_amount' => 100.00,
        'accumulated_depreciation_to_date' => 100.00,
        'opening_book_value' => $activeAsset->total_cost,
        'closing_book_value' => $activeAsset->total_cost - 100,
        'processed_by' => 1,
    ]);

    // Observer should NOT create JE for this
    $manualDepreciation->refresh();

    if ($manualDepreciation->journal_entry_id) {
        echo "❌ Observer should NOT create JE for voided asset depreciation!\n";
        echo "  JE ID: {$manualDepreciation->journal_entry_id}\n";

        // Clean up the wrong JE
        JournalEntry::find($manualDepreciation->journal_entry_id)->delete();
        $manualDepreciation->delete();
        exit(1);
    }

    echo "✓ Observer correctly blocked JE creation for voided asset\n";
    echo "  Depreciation record has no journal_entry_id\n";

    // Clean up the depreciation record
    $manualDepreciation->delete();
    echo "\n";

    // ========================================
    // TEST 5: Create asset directly with VOIDED status (edge case)
    // ========================================
    echo "TEST 5: Verify asset created with VOIDED status doesn't get acquisition JE...\n";

    $voidedAsset = FixedAsset::create([
        'asset_number' => FixedAsset::generateAssetNumber($category->code),
        'name' => 'TEST OBSERVER - Pre-Voided Asset ' . date('YmdHis'),
        'category_id' => $category->id,
        'account_id' => $category->asset_account_id,
        'department_id' => $department->id,
        'acquisition_date' => date('Y-m-d'),
        'acquisition_cost' => 5000.00,
        'additional_costs' => 0,
        'total_cost' => 5000.00,
        'salvage_value' => 500.00,
        'depreciable_amount' => 4500.00,
        'accumulated_depreciation' => 0,
        'book_value' => 5000.00,
        'depreciation_method' => 'straight_line',
        'useful_life_years' => 5,
        'useful_life_months' => 60,
        'monthly_depreciation' => 75.00,
        'in_service_date' => date('Y-m-d'),
        'status' => FixedAsset::STATUS_VOIDED, // Created as voided
        'created_by' => 1,
    ]);

    $voidedAsset->refresh();

    if ($voidedAsset->journal_entry_id) {
        echo "❌ Pre-voided asset should NOT have acquisition JE!\n";
        echo "  JE ID: {$voidedAsset->journal_entry_id}\n";
        exit(1);
    }

    echo "✓ Pre-voided asset correctly has no acquisition JE\n";
    echo "  Asset ID: {$voidedAsset->id}\n";
    echo "  Status: {$voidedAsset->status}\n";
    echo "  journal_entry_id: NULL\n\n";

    // ========================================
    // SUMMARY
    // ========================================
    echo "======================\n";
    echo "✅ ALL TESTS PASSED!\n";
    echo "======================\n\n";
    echo "Summary:\n";
    echo "  ✓ Active assets receive acquisition JE\n";
    echo "  ✓ Voided assets excluded by needsDepreciation()\n";
    echo "  ✓ Voided assets excluded by depreciable() scope\n";
    echo "  ✓ DepreciationObserver blocks JE for voided assets\n";
    echo "  ✓ FixedAssetObserver blocks JE for pre-voided assets\n\n";

    echo "Test Asset IDs (for cleanup if needed):\n";
    echo "  Active Asset: {$activeAsset->id}\n";
    echo "  Pre-Voided Asset: {$voidedAsset->id}\n\n";

} catch (\Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
