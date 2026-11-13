<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\Account;
use App\Models\StockBatch;
use App\Models\Store;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\SubAccountService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Stock Batch Accounting Observer
 *
 * Handles automated General Ledger posting for manual stock batch intakes
 * (Practice A: Express Stock Receipt / Direct Purchase).
 *
 * Double-Entry Matrix:
 *   - On Credit (default):
 *       DR: Inventory (1300 Pharmacy / 1310 Supplies / 1320 Lab)
 *       CR: Accounts Payable (2110/2100) + Supplier Sub-Account
 *   - Cash / Petty Cash:
 *       DR: Inventory
 *       CR: Petty Cash (1030) or Cash in Hand (1010)
 *   - Opening Stock:
 *       DR: Inventory
 *       CR: Retained Earnings (3100) / Owner's Capital (3000)
 *   - Donation (₦0 cost):
 *       Physical count only; no financial payable generated.
 */
class StockBatchAccountingObserver
{
    private const INVENTORY_PHARMACY = '1300';
    private const INVENTORY_SUPPLIES = '1310';
    private const INVENTORY_LAB = '1320';

    private const AP_SUPPLIERS = '2110';
    private const AP_GENERAL = '2100';

    private const PETTY_CASH = '1030';
    private const CASH_IN_HAND = '1010';

    private const RETAINED_EARNINGS = '3100';
    private const OWNERS_CAPITAL = '3000';

    public function created(StockBatch $batch): void
    {
        // Only process manual entries and opening stock
        if (!in_array($batch->source, [StockBatch::SOURCE_MANUAL, StockBatch::SOURCE_OPENING_STOCK], true)) {
            return;
        }

        // Donations and zero-cost items have no financial payable
        if ($batch->is_donation || (float) $batch->cost_price <= 0 || (int) $batch->initial_qty <= 0) {
            return;
        }

        if ($batch->journal_entry_id) {
            return;
        }

        try {
            $this->createIntakeJournalEntry($batch);
        } catch (\Exception $e) {
            Log::error('StockBatchAccountingObserver: Failed to create journal entry', [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function createIntakeJournalEntry(StockBatch $batch): void
    {
        $accountingService = App::make(AccountingService::class);
        $subAccountService = App::make(SubAccountService::class);

        $totalValue = round((float) $batch->cost_price * (int) $batch->initial_qty, 2);
        if ($totalValue <= 0) {
            return;
        }

        // 1. Resolve Inventory Debit Account based on store type
        $inventoryCode = $this->resolveInventoryCode($batch->store_id);
        $inventoryAccount = Account::where('code', $inventoryCode)->first()
            ?? Account::where('code', self::INVENTORY_SUPPLIES)->first()
            ?? Account::where('code', self::INVENTORY_PHARMACY)->first();

        if (!$inventoryAccount) {
            Log::warning('StockBatchAccountingObserver: Inventory account not found', [
                'batch_id' => $batch->id,
                'resolved_code' => $inventoryCode,
            ]);

            return;
        }

        // 2. Resolve Credit Account & Sub-Account based on acquisition type
        $acquisitionType = $batch->acquisition_type ?? StockBatch::ACQUISITION_CREDIT;
        $creditAccount = null;
        $supplierSubAccountId = null;

        if ($acquisitionType === StockBatch::ACQUISITION_CASH) {
            $cashCode = $batch->payment_account_code ?? self::PETTY_CASH;
            $creditAccount = Account::where('code', $cashCode)->first()
                ?? Account::where('code', self::PETTY_CASH)->first()
                ?? Account::where('code', self::CASH_IN_HAND)->first();
        } elseif ($acquisitionType === StockBatch::ACQUISITION_OPENING_STOCK || $batch->source === StockBatch::SOURCE_OPENING_STOCK) {
            $creditAccount = Account::where('code', self::RETAINED_EARNINGS)->first()
                ?? Account::where('code', self::OWNERS_CAPITAL)->first();
        } else {
            // Default: Credit (Supplier Debt)
            if ($batch->supplier_id && $batch->supplier) {
                $subAccount = $subAccountService->getOrCreateSupplierSubAccount($batch->supplier);
                if ($subAccount) {
                    $creditAccount = Account::find($subAccount->account_id);
                    $supplierSubAccountId = $subAccount->id;
                }
            }
            if (!$creditAccount) {
                $creditAccount = Account::where('code', self::AP_GENERAL)->first()
                    ?? Account::where('code', self::AP_SUPPLIERS)->first();
            }
        }

        if (!$creditAccount) {
            Log::warning('StockBatchAccountingObserver: Credit account not found', [
                'batch_id' => $batch->id,
                'acquisition_type' => $acquisitionType,
            ]);

            return;
        }

        $storeName = $batch->store?->store_name ?? 'Store #' . $batch->store_id;
        $productName = $batch->product?->product_name ?? 'Product #' . $batch->product_id;
        $supplierName = $batch->supplier?->company_name ?? 'Supplier #' . $batch->supplier_id;

        $description = sprintf(
            'Stock Intake [%s]: %s × %d units @ ₦%s in %s (Batch: %s)',
            ucfirst($acquisitionType),
            $productName,
            $batch->initial_qty,
            number_format($batch->cost_price, 2),
            $storeName,
            $batch->batch_number
        );

        $lines = [
            [
                'account_id' => $inventoryAccount->id,
                'debit_amount' => $totalValue,
                'credit_amount' => 0,
                'description' => "Stock received in {$storeName} (Batch {$batch->batch_number})",
                'product_id' => $batch->product_id,
                'supplier_id' => $batch->supplier_id,
                'category' => 'inventory_receipt',
            ],
            [
                'account_id' => $creditAccount->id,
                'sub_account_id' => $supplierSubAccountId,
                'debit_amount' => 0,
                'credit_amount' => $totalValue,
                'description' => $acquisitionType === StockBatch::ACQUISITION_CASH
                    ? "Cash payment for stock batch {$batch->batch_number}"
                    : ($acquisitionType === StockBatch::ACQUISITION_OPENING_STOCK
                        ? "Opening stock equity for batch {$batch->batch_number}"
                        : "Payable to {$supplierName} for batch {$batch->batch_number}"),
                'product_id' => $batch->product_id,
                'supplier_id' => $batch->supplier_id,
                'category' => 'inventory_receipt',
            ],
        ];

        $journalEntry = $accountingService->createAndPostAutomatedEntry(
            StockBatch::class,
            $batch->id,
            $description,
            $lines,
            now()->toDateString(),
            auth()->id() ?? $batch->created_by ?? 1
        );

        $batch->journal_entry_id = $journalEntry->id;
        $batch->saveQuietly();

        Log::info('StockBatchAccountingObserver: Journal entry created successfully', [
            'batch_id' => $batch->id,
            'batch_number' => $batch->batch_number,
            'journal_entry_id' => $journalEntry->id,
            'amount' => $totalValue,
            'acquisition_type' => $acquisitionType,
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
