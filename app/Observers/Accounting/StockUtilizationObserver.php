<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\Account;
use App\Models\Product;
use App\Models\StockUtilization;
use App\Models\Store;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Stock Utilization Observer
 *
 * Handles automated General Ledger posting when ward or departmental stock
 * is consumed internally without direct patient billing (e.g. ward supplies,
 * cleaning items, lab consumables).
 *
 * Accounting Rules:
 *   - Internal Consumption / Unbilled Inpatient Use:
 *       DR: Cost of Medical Supplies Used (5020)
 *       CR: Inventory (1300 Pharmacy / 1310 Supplies / 1320 Lab)
 */
class StockUtilizationObserver
{
    private const SUPPLIES_USED = '5020';
    private const GENERAL_COGS = '5000';

    private const INVENTORY_PHARMACY = '1300';
    private const INVENTORY_SUPPLIES = '1310';
    private const INVENTORY_LAB = '1320';

    public function created(StockUtilization $utilization): void
    {
        // If billed to patient, POSR and Payment observers handle revenue & COGS
        if ($utilization->is_billed) {
            return;
        }

        try {
            $this->createUtilizationJournalEntry($utilization);
        } catch (\Exception $e) {
            Log::error('StockUtilizationObserver: Failed to create utilization JE', [
                'utilization_id' => $utilization->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function createUtilizationJournalEntry(StockUtilization $utilization): void
    {
        $product = Product::with('price')->find($utilization->product_id);
        if (!$product) {
            return;
        }

        // Determine unit cost (use price model buy price, current_cost_price, or active batch)
        $unitCost = (float) ($product->price?->pr_buy_price ?? $product->price?->current_cost_price ?? 0);
        if ($unitCost <= 0) {
            // Fallback: check last active batch for this product in store
            $lastBatch = \App\Models\StockBatch::where('product_id', $utilization->product_id)
                ->where('store_id', $utilization->store_id)
                ->orderBy('id', 'desc')
                ->first();
            $unitCost = (float) ($lastBatch?->cost_price ?? 0);
        }

        $totalValue = round($unitCost * (int) $utilization->qty, 2);
        if ($totalValue <= 0) {
            return;
        }

        $accountingService = App::make(AccountingService::class);

        $expenseAccount = Account::where('code', self::SUPPLIES_USED)->first()
            ?? Account::where('code', self::GENERAL_COGS)->first();

        $inventoryCode = $this->resolveInventoryCode($utilization->store_id);
        $inventoryAccount = Account::where('code', $inventoryCode)->first()
            ?? Account::where('code', self::INVENTORY_SUPPLIES)->first()
            ?? Account::where('code', self::INVENTORY_PHARMACY)->first();

        if (!$expenseAccount || !$inventoryAccount) {
            return;
        }

        $storeName = $utilization->store?->store_name ?? 'Store #' . $utilization->store_id;
        $productName = $product->product_name;

        $description = sprintf(
            'Stock Utilization [%s]: %s × %d in %s (%s)',
            ucfirst($utilization->utilization_type),
            $productName,
            $utilization->qty,
            $storeName,
            $utilization->reason
        );

        $lines = [
            [
                'account_id' => $expenseAccount->id,
                'debit_amount' => $totalValue,
                'credit_amount' => 0,
                'description' => "Department supplies consumed: {$productName} ({$utilization->reason})",
                'product_id' => $utilization->product_id,
                'category' => 'inventory_utilization',
            ],
            [
                'account_id' => $inventoryAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $totalValue,
                'description' => "Stock deduction for internal use from {$storeName}",
                'product_id' => $utilization->product_id,
                'category' => 'inventory_utilization',
            ],
        ];

        $accountingService->createAndPostAutomatedEntry(
            StockUtilization::class,
            $utilization->id,
            $description,
            $lines,
            now()->toDateString(),
            auth()->id() ?? $utilization->created_by ?? 1
        );

        Log::info('StockUtilizationObserver: Utilization JE created successfully', [
            'utilization_id' => $utilization->id,
            'total_value' => $totalValue,
        ]);
    }

    private function resolveInventoryCode(?int $storeId): string
    {
        if (!$storeId) {
            return self::INVENTORY_SUPPLIES;
        }

        $store = Store::find($storeId);
        if (!$store) {
            return self::INVENTORY_SUPPLIES;
        }

        if (in_array($store->distribution_role, [Store::ROLE_PHARMACY_HUB, Store::ROLE_PHARMACY_SATELLITE], true)) {
            return self::INVENTORY_PHARMACY;
        }

        if ($store->distribution_role === Store::ROLE_LAB) {
            return self::INVENTORY_LAB;
        }

        return self::INVENTORY_SUPPLIES;
    }
}
