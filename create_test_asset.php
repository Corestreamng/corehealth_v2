<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Accounting\FixedAsset;
use App\Models\Accounting\FixedAssetCategory;
use App\Models\Accounting\Account;
use App\Services\Accounting\FixedAssetService;
use Illuminate\Support\Facades\DB;

echo "=== CREATE TEST ASSET FOR DEPRECIATION ===\n\n";

try {
    DB::beginTransaction();

    // Get the first active category
    $category = FixedAssetCategory::where('is_active', true)->first();

    if (!$category) {
        echo "❌ No active categories found. Run: php artisan db:seed --class=FixedAssetCategorySeeder\n";
        exit;
    }

    echo "Using category: {$category->name}\n";
    echo "Asset Account: {$category->assetAccount->code} - {$category->assetAccount->name}\n";
    echo "Depreciation Account: {$category->depreciationAccount->code} - {$category->depreciationAccount->name}\n";
    echo "Expense Account: {$category->expenseAccount->code} - {$category->expenseAccount->name}\n\n";

    // Get a bank account for the payment
    $bankAccount = Account::where('name', 'LIKE', '%Bank%')->first()
                   ?? Account::where('code', 'LIKE', '1%')->first();

    if (!$bankAccount) {
        echo "❌ No bank account found\n";
        exit;
    }

    // Create the asset using the service
    $service = new FixedAssetService();

    $data = [
        'category_id' => $category->id,
        'name' => 'Test Laptop Computer',
        'description' => 'Dell Latitude 5520 - Test Asset for Depreciation',
        'serial_number' => 'TEST-' . time(),
        'acquisition_cost' => 150000,
        'acquisition_date' => now()->subMonths(2)->toDateString(), // Acquired 2 months ago
        'supplier_id' => null,
        'invoice_number' => 'INV-TEST-001',
        'payment_method' => 'bank_transfer',
        'bank_account_id' => $bankAccount->id,
        'custodian_id' => 1, // User ID 1
        'department_id' => null,
        'location' => 'IT Department',
        'useful_life_years' => $category->default_useful_life_years,
        'salvage_value' => 7500, // 5% salvage
        'depreciation_method' => $category->default_depreciation_method,
        'in_service_date' => now()->subMonths(2)->toDateString(), // In service 2 months ago
        'status' => FixedAsset::STATUS_ACTIVE,
    ];

    echo "Creating asset...\n";
    $asset = $service->createAsset($data, 1);

    echo "\n✅ Asset Created Successfully!\n";
    echo "Asset Number: {$asset->asset_number}\n";
    echo "Name: {$asset->name}\n";
    echo "Cost: ₦" . number_format($asset->acquisition_cost, 2) . "\n";
    echo "Book Value: ₦" . number_format($asset->book_value, 2) . "\n";
    echo "Monthly Depreciation: ₦" . number_format($asset->monthly_depreciation, 2) . "\n";
    echo "Useful Life: {$asset->useful_life_years} years\n";
    echo "In Service: {$asset->in_service_date->format('Y-m-d')}\n";
    echo "Status: {$asset->status}\n";
    echo "Needs Depreciation: " . ($asset->needsDepreciation() ? 'Yes' : 'No') . "\n";

    if ($asset->journalEntry) {
        echo "\n✅ Acquisition Journal Entry Created: {$asset->journalEntry->entry_number}\n";
        foreach ($asset->journalEntry->lines as $line) {
            $type = $line->debit_amount > 0 ? 'DR' : 'CR';
            $amount = $line->debit_amount > 0 ? $line->debit_amount : $line->credit_amount;
            echo "   {$type} {$line->account->code} {$line->account->name}: ₦" . number_format($amount, 2) . "\n";
        }
    }

    DB::commit();
    echo "\n✅ Test asset created successfully! You can now run: php test_depreciation.php\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== DONE ===\n";
