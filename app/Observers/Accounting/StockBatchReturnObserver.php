<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\Account;
use App\Models\StockBatchReturn;
use App\Models\StockBatchTransaction;
use App\Models\Store;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\SubAccountService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stock Batch Return Observer
 *
 * Handles physical stock deduction and GL entries when a manual batch return
 * to supplier is approved.
 *
 * Settlement Accounting:
 *   - ap_credit_note (Supplier Debt Reduction):
 *       DR: Accounts Payable - Suppliers (2110) + Supplier Sub-Account
 *       CR: Inventory (1300 Pharmacy / 1310 Supplies / 1320 Lab)
 *   - cash_refund (Cash Received from Supplier):
 *       DR: Cash in Hand (1010) / Petty Cash (1030)
 *       CR: Inventory
 *   - receivable (Supplier Refund Pending):
 *       DR: Accounts Receivable (1200) + Supplier Sub-Account
 *       CR: Inventory
 */
class StockBatchReturnObserver
{
    private const INVENTORY_PHARMACY = '1300';
    private const INVENTORY_SUPPLIES = '1310';
    private const INVENTORY_LAB = '1320';

    private const AP_SUPPLIERS = '2110';
    private const AP_GENERAL = '2100';

    private const CASH_IN_HAND = '1010';
    private const PETTY_CASH = '1030';

    private const AR_GENERAL = '1200';

    public function created(StockBatchReturn $return): void
    {
        if ($return->status === StockBatchReturn::STATUS_APPROVED) {
            $this->processApprovedReturn($return);
        }
    }

    public function updated(StockBatchReturn $return): void
    {
        if ($return->isDirty('status') && $return->status === StockBatchReturn::STATUS_APPROVED) {
            $this->processApprovedReturn($return);
        }
    }

    protected function processApprovedReturn(StockBatchReturn $return): void
    {
        if ($return->stock_deducted) {
            return;
        }

        try {
            DB::transaction(function () use ($return) {
                // 1. Deduct physical stock from batch
                $batch = $return->stockBatch;
                if ($batch) {
                    $batch->deductStock(
                        $return->qty_returned,
                        StockBatchTransaction::TYPE_BATCH_RETURN,
                        StockBatchReturn::class,
                        $return->id,
                        "Return to supplier #{$return->return_number} ({$return->return_reason})"
                    );
                }

                // 2. Post financial entry if goods have monetary value
                $journalEntryId = null;
                if ((float) $return->total_value > 0 && $return->settlement_type !== StockBatchReturn::SETTLEMENT_NO_FINANCIAL_IMPACT) {
                    $journalEntryId = $this->createReturnJournalEntry($return);
                }

                $return->updateQuietly([
                    'stock_deducted' => true,
                    'stock_deducted_at' => now(),
                    'journal_entry_id' => $journalEntryId,
                ]);
            });

            Log::info('StockBatchReturnObserver: Processed batch return successfully', [
                'return_id' => $return->id,
                'return_number' => $return->return_number,
                'qty' => $return->qty_returned,
            ]);
        } catch (\Exception $e) {
            Log::error('StockBatchReturnObserver: Failed to process return', [
                'return_id' => $return->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function createReturnJournalEntry(StockBatchReturn $return): ?int
    {
        $accountingService = App::make(AccountingService::class);
        $subAccountService = App::make(SubAccountService::class);

        // 1. Resolve Inventory CR Account
        $inventoryCode = $this->resolveInventoryCode($return->store_id);
        $inventoryAccount = Account::where('code', $inventoryCode)->first()
            ?? Account::where('code', self::INVENTORY_SUPPLIES)->first()
            ?? Account::where('code', self::INVENTORY_PHARMACY)->first();

        if (!$inventoryAccount) {
            return null;
        }

        // 2. Resolve DR Account based on settlement type
        $debitAccount = null;
        $subAccountId = null;

        if ($return->settlement_type === StockBatchReturn::SETTLEMENT_CASH_REFUND) {
            $debitAccount = Account::where('code', self::CASH_IN_HAND)->first()
                ?? Account::where('code', self::PETTY_CASH)->first();
        } elseif ($return->settlement_type === StockBatchReturn::SETTLEMENT_RECEIVABLE) {
            $debitAccount = Account::where('code', self::AR_GENERAL)->first();
            if ($return->supplier_id && $return->supplier) {
                $subAccount = $subAccountService->getOrCreateSupplierSubAccount($return->supplier);
                $subAccountId = $subAccount?->id;
            }
        } else {
            // Default: AP Credit Note (reduce debt to supplier)
            if ($return->supplier_id && $return->supplier) {
                $subAccount = $subAccountService->getOrCreateSupplierSubAccount($return->supplier);
                if ($subAccount) {
                    $debitAccount = Account::find($subAccount->account_id);
                    $subAccountId = $subAccount->id;
                }
            }
            if (!$debitAccount) {
                $debitAccount = Account::where('code', self::AP_GENERAL)->first()
                    ?? Account::where('code', self::AP_SUPPLIERS)->first();
            }
        }

        if (!$debitAccount) {
            return null;
        }

        $supplierName = $return->supplier?->company_name ?? 'Supplier #' . $return->supplier_id;
        $productName = $return->product?->product_name ?? 'Product #' . $return->product_id;

        $description = sprintf(
            'Batch Return #%s: %s × %d units returned to %s (Value: ₦%s, Reason: %s)',
            $return->return_number,
            $productName,
            $return->qty_returned,
            $supplierName,
            number_format($return->total_value, 2),
            $return->return_reason
        );

        $lines = [
            [
                'account_id' => $debitAccount->id,
                'sub_account_id' => $subAccountId,
                'debit_amount' => $return->total_value,
                'credit_amount' => 0,
                'description' => "Return to {$supplierName} — Ref #{$return->return_number}",
                'product_id' => $return->product_id,
                'supplier_id' => $return->supplier_id,
                'category' => 'inventory_return',
            ],
            [
                'account_id' => $inventoryAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $return->total_value,
                'description' => "Inventory reduction for return #{$return->return_number}",
                'product_id' => $return->product_id,
                'supplier_id' => $return->supplier_id,
                'category' => 'inventory_return',
            ],
        ];

        $journalEntry = $accountingService->createAndPostAutomatedEntry(
            StockBatchReturn::class,
            $return->id,
            $description,
            $lines,
            now()->toDateString(),
            auth()->id() ?? $return->approved_by ?? $return->created_by ?? 1
        );

        return $journalEntry->id;
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
