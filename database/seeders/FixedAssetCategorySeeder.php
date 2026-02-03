<?php

namespace Database\Seeders;

use App\Models\Accounting\Account;
use App\Models\Accounting\FixedAssetCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FixedAssetCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Find or create necessary accounts for fixed assets
        // These accounts should exist from ChartOfAccountsSeeder

        // Try to find Fixed Assets accounts in the chart of accounts
        $buildingAccount = Account::where('name', 'LIKE', '%Building%')->first();
        $equipmentAccount = Account::where('name', 'LIKE', '%Equipment%')
            ->where('name', 'NOT LIKE', '%Computer%')
            ->where('name', 'NOT LIKE', '%Office%')
            ->first();
        $computerAccount = Account::where('name', 'LIKE', '%Computer%')->first();
        $officeEquipmentAccount = Account::where('name', 'LIKE', '%Office%Equipment%')->first();
        $furnitureAccount = Account::where('name', 'LIKE', '%Furniture%')->first();
        $vehicleAccount = Account::where('name', 'LIKE', '%Vehicle%')->first();

        // Depreciation accounts
        $accumulatedDepreciationAccount = Account::where('name', 'LIKE', '%Accumulated Depreciation%')->first();

        // Expense accounts
        $depreciationExpenseAccount = Account::where('name', 'LIKE', '%Depreciation%')
            ->where('name', 'NOT LIKE', '%Accumulated%')
            ->first();

        // If accounts don't exist, use a default account (first active account)
        $defaultAssetAccount = $buildingAccount ?? Account::where('is_active', true)->first();
        $defaultDepreciationAccount = $accumulatedDepreciationAccount ?? Account::where('is_active', true)->skip(1)->first();
        $defaultExpenseAccount = $depreciationExpenseAccount ?? Account::where('is_active', true)->skip(2)->first();

        if (!$defaultAssetAccount || !$defaultDepreciationAccount || !$defaultExpenseAccount) {
            $this->command->warn('Warning: Could not find suitable accounts. Please update category accounts manually.');
            return;
        }

        $categories = [
            [
                'code' => 'BLDG',
                'name' => 'Buildings & Structures',
                'description' => 'Buildings, warehouses, and permanent structures',
                'asset_account_id' => $buildingAccount?->id ?? $defaultAssetAccount->id,
                'depreciation_account_id' => $defaultDepreciationAccount->id,
                'expense_account_id' => $defaultExpenseAccount->id,
                'default_useful_life_years' => 40,
                'default_depreciation_method' => FixedAssetCategory::METHOD_STRAIGHT_LINE,
                'default_salvage_percentage' => 10.00,
                'is_depreciable' => true,
                'is_active' => true,
            ],
            [
                'code' => 'COMP',
                'name' => 'Computer Equipment',
                'description' => 'Computers, laptops, servers, and IT hardware',
                'asset_account_id' => $computerAccount?->id ?? $equipmentAccount?->id ?? $defaultAssetAccount->id,
                'depreciation_account_id' => $defaultDepreciationAccount->id,
                'expense_account_id' => $defaultExpenseAccount->id,
                'default_useful_life_years' => 3,
                'default_depreciation_method' => FixedAssetCategory::METHOD_STRAIGHT_LINE,
                'default_salvage_percentage' => 5.00,
                'is_depreciable' => true,
                'is_active' => true,
            ],
            [
                'code' => 'FURN',
                'name' => 'Furniture & Fixtures',
                'description' => 'Office furniture, desks, chairs, and fixtures',
                'asset_account_id' => $furnitureAccount?->id ?? $defaultAssetAccount->id,
                'depreciation_account_id' => $defaultDepreciationAccount->id,
                'expense_account_id' => $defaultExpenseAccount->id,
                'default_useful_life_years' => 10,
                'default_depreciation_method' => FixedAssetCategory::METHOD_STRAIGHT_LINE,
                'default_salvage_percentage' => 10.00,
                'is_depreciable' => true,
                'is_active' => true,
            ],
            [
                'code' => 'MED',
                'name' => 'Medical Equipment',
                'description' => 'Medical devices, diagnostic equipment, and healthcare tools',
                'asset_account_id' => $equipmentAccount?->id ?? $defaultAssetAccount->id,
                'depreciation_account_id' => $defaultDepreciationAccount->id,
                'expense_account_id' => $defaultExpenseAccount->id,
                'default_useful_life_years' => 7,
                'default_depreciation_method' => FixedAssetCategory::METHOD_STRAIGHT_LINE,
                'default_salvage_percentage' => 10.00,
                'is_depreciable' => true,
                'is_active' => true,
            ],
            [
                'code' => 'VEH',
                'name' => 'Vehicles',
                'description' => 'Cars, vans, ambulances, and other vehicles',
                'asset_account_id' => $vehicleAccount?->id ?? $defaultAssetAccount->id,
                'depreciation_account_id' => $defaultDepreciationAccount->id,
                'expense_account_id' => $defaultExpenseAccount->id,
                'default_useful_life_years' => 5,
                'default_depreciation_method' => FixedAssetCategory::METHOD_DECLINING_BALANCE,
                'default_salvage_percentage' => 15.00,
                'is_depreciable' => true,
                'is_active' => true,
            ],
            [
                'code' => 'OFFEQ',
                'name' => 'Office Equipment',
                'description' => 'Printers, copiers, phones, and office machines',
                'asset_account_id' => $officeEquipmentAccount?->id ?? $equipmentAccount?->id ?? $defaultAssetAccount->id,
                'depreciation_account_id' => $defaultDepreciationAccount->id,
                'expense_account_id' => $defaultExpenseAccount->id,
                'default_useful_life_years' => 5,
                'default_depreciation_method' => FixedAssetCategory::METHOD_STRAIGHT_LINE,
                'default_salvage_percentage' => 5.00,
                'is_depreciable' => true,
                'is_active' => true,
            ],
            [
                'code' => 'LAND',
                'name' => 'Land',
                'description' => 'Land and property (non-depreciable)',
                'asset_account_id' => $buildingAccount?->id ?? $defaultAssetAccount->id,
                'depreciation_account_id' => $defaultDepreciationAccount->id,
                'expense_account_id' => $defaultExpenseAccount->id,
                'default_useful_life_years' => 0,
                'default_depreciation_method' => FixedAssetCategory::METHOD_STRAIGHT_LINE,
                'default_salvage_percentage' => 0.00,
                'is_depreciable' => false,
                'is_active' => true,
            ],
            [
                'code' => 'LEASEHOLD',
                'name' => 'Leasehold Improvements',
                'description' => 'Improvements made to rented/leased properties',
                'asset_account_id' => $buildingAccount?->id ?? $defaultAssetAccount->id,
                'depreciation_account_id' => $defaultDepreciationAccount->id,
                'expense_account_id' => $defaultExpenseAccount->id,
                'default_useful_life_years' => 10,
                'default_depreciation_method' => FixedAssetCategory::METHOD_STRAIGHT_LINE,
                'default_salvage_percentage' => 0.00,
                'is_depreciable' => true,
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            FixedAssetCategory::updateOrCreate(
                ['code' => $category['code']],
                $category
            );
        }

        $this->command->info('Fixed Asset Categories seeded successfully!');
        $this->command->info('Note: All categories share Accumulated Depreciation and Depreciation Expense accounts (standard practice).');
    }
}
