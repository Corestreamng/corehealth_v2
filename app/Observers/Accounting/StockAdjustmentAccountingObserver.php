<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\StockBatchTransaction;
use App\Models\Store;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Stock Adjustment Accounting Observer
 *
 * Handles automated GL entries for audited physical count adjustments (+ / -)
 * and clean reversals when adjustments are voided as "recorded in error".
 *
 * Accounting Rules:
 *   - Positive Adjustment (+ Surplus items found):
 *       DR: Inventory (1300 Pharmacy / 1310 Supplies / 1320 Lab)
 *       CR: Stock Variance Surplus Gain (4230 / 4210 Other Income)
 *   - Negative Adjustment (- Shortage / Missing items):
 *       DR: Inventory Shrinkage Loss (5050 Theft/Shrinkage)
 *       CR: Inventory (1300 Pharmacy / 1310 Supplies / 1320 Lab)
 *   - Voiding Erroneous Adjustment:
 *       Exact automated reversal of original adjustment JE.
 */
class StockAdjustmentAccountingObserver
{
    private const INVENTORY_PHARMACY = '1300';
    private const INVENTORY_SUPPLIES = '1310';
    private const INVENTORY_LAB = '1320';

    private const VARIANCE_GAIN = '4230';
    private const OTHER_INCOME = '4210';

    private const SHRINKAGE_LOSS = '5050';
    private const GENERAL_COGS = '5000';

    public function created(StockBatchTransaction $tx): void
    {
        if ($tx->type !== StockBatchTransaction::TYPE_ADJUSTMENT || $tx->journal_entry_id || $tx->is_voided) {
            return;
        }

        try {
            $batch = $tx->stockBatch;
            if (!$batch || (float) $batch->cost_price <= 0 || (int) $tx->qty <= 0) {
                return;
            }

            $totalValue = round((float) $batch->cost_price * (int) $tx->qty, 2);
            if ($totalValue <= 0) {
                return;
            }

            $accountingService = App::make(AccountingService::class);
            $inventoryCode = $this->resolveInventoryCode($batch->store_id);
            $inventoryAccount = Account::where('code', $inventoryCode)->first()
                ?? Account::where('code', self::INVENTORY_SUPPLIES)->first()
                ?? Account::where('code', self::INVENTORY_PHARMACY)->first();

            if (!$inventoryAccount) {
                return;
            }

            $isPositive = str_contains(strtolower($tx->notes ?? ''), 'positive');

            if ($isPositive) {
                $gainAccount = Account::where('code', self::VARIANCE_GAIN)->first()
                    ?? Account::where('code', self::OTHER_INCOME)->first();

                if (!$gainAccount) {
                    return;
                }

                $description = sprintf(
                    'Stock Adjustment (+) Surplus: %s × %d in %s (Batch: %s)',
                    $batch->product?->product_name ?? 'Product #' . $batch->product_id,
                    $tx->qty,
                    $batch->store?->store_name ?? 'Store #' . $batch->store_id,
                    $batch->batch_number
                );

                $lines = [
                    [
                        'account_id' => $inventoryAccount->id,
                        'debit_amount' => $totalValue,
                        'credit_amount' => 0,
                        'description' => "Stock count surplus (+{$tx->qty}) - Batch {$batch->batch_number}",
                        'product_id' => $batch->product_id,
                        'category' => 'inventory_adjustment',
                    ],
                    [
                        'account_id' => $gainAccount->id,
                        'debit_amount' => 0,
                        'credit_amount' => $totalValue,
                        'description' => "Inventory count variance gain: {$batch->product?->product_name}",
                        'product_id' => $batch->product_id,
                        'category' => 'inventory_adjustment',
                    ],
                ];
            } else {
                $lossAccount = Account::where('code', self::SHRINKAGE_LOSS)->first()
                    ?? Account::where('code', self::GENERAL_COGS)->first();

                if (!$lossAccount) {
                    return;
                }

                $description = sprintf(
                    'Stock Adjustment (-) Shortage: %s × %d in %s (Batch: %s)',
                    $batch->product?->product_name ?? 'Product #' . $batch->product_id,
                    $tx->qty,
                    $batch->store?->store_name ?? 'Store #' . $batch->store_id,
                    $batch->batch_number
                );

                $lines = [
                    [
                        'account_id' => $lossAccount->id,
                        'debit_amount' => $totalValue,
                        'credit_amount' => 0,
                        'description' => "Inventory shrinkage shortage (-{$tx->qty}) - Batch {$batch->batch_number}",
                        'product_id' => $batch->product_id,
                        'category' => 'inventory_adjustment',
                    ],
                    [
                        'account_id' => $inventoryAccount->id,
                        'debit_amount' => 0,
                        'credit_amount' => $totalValue,
                        'description' => "Stock deduction for physical shortage - Batch {$batch->batch_number}",
                        'product_id' => $batch->product_id,
                        'category' => 'inventory_adjustment',
                    ],
                ];
            }

            $journalEntry = $accountingService->createAndPostAutomatedEntry(
                StockBatchTransaction::class,
                $tx->id,
                $description,
                $lines,
                now()->toDateString(),
                auth()->id() ?? $tx->performed_by ?? 1
            );

            $tx->journal_entry_id = $journalEntry->id;
            $tx->saveQuietly();

            Log::info('StockAdjustmentAccountingObserver: Adjustment JE created', [
                'tx_id' => $tx->id,
                'batch_id' => $batch->id,
                'is_positive' => $isPositive,
                'total_value' => $totalValue,
                'journal_entry_id' => $journalEntry->id,
            ]);
        } catch (\Exception $e) {
            Log::error('StockAdjustmentAccountingObserver: Failed to create adjustment JE', [
                'tx_id' => $tx->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(StockBatchTransaction $tx): void
    {
        // Only trigger when is_voided was toggled to true and reversal JE not yet created
        if (!($tx->wasChanged('is_voided') && $tx->is_voided && $tx->journal_entry_id && !$tx->void_reversal_je_id)) {
            return;
        }

        try {
            $journalEntry = JournalEntry::find($tx->journal_entry_id);
            if (!$journalEntry) {
                return;
            }

            $accountingService = App::make(AccountingService::class);
            $userId = auth()->id() ?? $tx->voided_by ?? 1;
            $reason = $tx->void_reason ?? 'Adjustment voided as recorded in error';

            $reversingEntry = $accountingService->reverseEntry($journalEntry, $userId, $reason);

            $tx->void_reversal_je_id = $reversingEntry->id;
            $tx->saveQuietly();

            Log::info('StockAdjustmentAccountingObserver: Reversal JE created for voided adjustment', [
                'tx_id' => $tx->id,
                'original_je_id' => $journalEntry->id,
                'reversal_je_id' => $reversingEntry->id,
            ]);
        } catch (\Exception $e) {
            Log::error('StockAdjustmentAccountingObserver: Failed to reverse adjustment JE', [
                'tx_id' => $tx->id,
                'error' => $e->getMessage(),
            ]);
        }
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
