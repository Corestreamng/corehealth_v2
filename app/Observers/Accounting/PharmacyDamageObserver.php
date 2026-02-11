<?php

namespace App\Observers\Accounting;

use App\Models\PharmacyDamage;
use App\Models\Product;
use App\Models\StoreStock;
use App\Models\StockBatch;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pharmacy Damage Observer
 *
 * Creates journal entries when damage reports are approved and writes off inventory.
 *
 * IMPORTANT: This writes off inventory value from the balance sheet.
 * Study PurchaseOrderObserver pattern - when stock is added:
 *   DR: Inventory - Pharmacy (1300)
 *   CR: Accounts Payable (2100)
 *
 * On Damage Approved:
 *   For Regular Damage (broken, contaminated):
 *     DR: Damaged Goods Write-off (5030) - [total_value]
 *     CR: Inventory - Pharmacy (1300) - [total_value]
 *
 *   For Expired Stock:
 *     DR: Expired Stock Write-off (5040) - [total_value]
 *     CR: Inventory - Pharmacy (1300) - [total_value]
 *
 *   For Theft/Shrinkage:
 *     DR: Theft/Shrinkage (5050) - [total_value]
 *     CR: Inventory - Pharmacy (1300) - [total_value]
 *
 * STOCK DEDUCTION:
 * - Deduct qty_damaged from ProductStock.current_quantity
 * - If batch_id specified, deduct from that batch's available qty
 * - Set stock_deducted = true to prevent duplicate deductions
 *
 * DUPLICATE JE PREVENTION:
 * - Check if JE already exists before creating new one
 * - Store journal_entry_id in pharmacy_damages table
 * - Check stock_deducted flag before deducting stock
 */
class PharmacyDamageObserver
{
    // Account codes
    private const INVENTORY_PHARMACY = '1300';
    private const DAMAGED_GOODS = '5030';
    private const EXPIRED_STOCK = '5040';
    private const THEFT_SHRINKAGE = '5050';

    /**
     * Handle the PharmacyDamage "updated" event.
     */
    public function updated(PharmacyDamage $damage): void
    {
        // Only process when status changes to 'approved'
        if ($damage->isDirty('status') && $damage->status === 'approved') {
            try {
                // DUPLICATE CHECK: Skip if JE already exists
                if ($damage->journal_entry_id) {
                    Log::info('PharmacyDamageObserver: Journal entry already exists, skipping', [
                        'damage_id' => $damage->id,
                        'existing_je_id' => $damage->journal_entry_id,
                    ]);
                    return;
                }

                // Use DB transaction to ensure both JE and stock deduction succeed
                DB::transaction(function () use ($damage) {
                    $this->createDamageJournalEntry($damage);
                    $this->deductStock($damage);
                });
            } catch (\Exception $e) {
                Log::error('PharmacyDamageObserver: Failed to process damage approval', [
                    'damage_id' => $damage->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Create the damage write-off journal entry.
     */
    protected function createDamageJournalEntry(PharmacyDamage $damage): void
    {
        $accountingService = App::make(AccountingService::class);

        // Get expense account based on damage type
        $expenseAccount = $this->getExpenseAccount($damage->damage_type);
        $inventoryAccount = Account::where('code', self::INVENTORY_PHARMACY)->first();

        if (!$expenseAccount || !$inventoryAccount) {
            Log::warning('PharmacyDamageObserver: Required accounts not found', [
                'damage_id' => $damage->id,
                'damage_type' => $damage->damage_type,
                'expense_found' => !is_null($expenseAccount),
                'inventory_found' => !is_null($inventoryAccount),
            ]);
            return;
        }

        $description = $this->buildDescription($damage);

        $lines = [
            [
                'account_id' => $expenseAccount->id,
                'debit_amount' => $damage->total_value,
                'credit_amount' => 0,
                'description' => "Inventory loss - {$damage->damage_type}: {$damage->product->product_name}",
                'product_id' => $damage->product_id,
                'category' => 'inventory_damage',
            ],
            [
                'account_id' => $inventoryAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $damage->total_value,
                'description' => "Inventory write-off: {$damage->product->product_name} from {$damage->store->store_name}",
                'product_id' => $damage->product_id,
                'category' => 'inventory_damage',
            ],
        ];

        // Create the journal entry
        $journalEntry = $accountingService->createAndPostAutomatedEntry(
            PharmacyDamage::class,
            $damage->id,
            $description,
            $lines,
            now()->toDateString(),
            auth()->id() ?? $damage->approved_by ?? 1
        );

        // Store JE reference to prevent duplicates
        $damage->update(['journal_entry_id' => $journalEntry->id]);

        Log::info('PharmacyDamageObserver: Journal entry created', [
            'damage_id' => $damage->id,
            'journal_entry_id' => $journalEntry->id,
            'total_value' => $damage->total_value,
            'damage_type' => $damage->damage_type,
        ]);
    }

    /**
     * Deduct stock from inventory via batch system.
     *
     * Uses StockBatch::deductStock() which:
     *   1. Decrements batch current_qty
     *   2. Records a StockBatchTransaction for audit
     *   3. Triggers StockBatchObserver → syncStoreStock() → auto-syncs
     *      store_stocks.current_quantity AND global stocks.current_quantity
     *
     * Falls back to direct store_stock/global deduction if no batch is specified
     * (legacy path for damages reported against unbatched stock).
     */
    protected function deductStock(PharmacyDamage $damage): void
    {
        // DUPLICATE CHECK: Skip if stock already deducted
        if ($damage->stock_deducted) {
            Log::info('PharmacyDamageObserver: Stock already deducted, skipping', [
                'damage_id' => $damage->id,
            ]);
            return;
        }

        if ($damage->batch_id) {
            // ── Batch-aware path (preferred) ──
            $batch = StockBatch::find($damage->batch_id);
            if ($batch) {
                $deductQty = min($damage->qty_damaged, $batch->current_qty);
                if ($deductQty > 0) {
                    // This saves the batch, records transaction, and triggers StockBatchObserver
                    // which auto-syncs store_stocks + global stocks via syncStoreStock()
                    $batch->deductStock(
                        $deductQty,
                        'damaged',
                        PharmacyDamage::class,
                        $damage->id,
                        "Damage #{$damage->id} - {$damage->damage_type}: {$damage->damage_reason}"
                    );

                    Log::info('PharmacyDamageObserver: Stock deducted from batch (with transaction)', [
                        'damage_id' => $damage->id,
                        'batch_id' => $batch->id,
                        'qty_deducted' => $deductQty,
                        'batch_remaining' => $batch->current_qty,
                    ]);
                }
            } else {
                Log::warning('PharmacyDamageObserver: Batch not found, falling back to direct deduction', [
                    'damage_id' => $damage->id,
                    'batch_id' => $damage->batch_id,
                ]);
                $this->directDeductFallback($damage);
            }
        } else {
            // ── No batch specified — try to find and deduct from available batches (FIFO) ──
            $availableBatch = StockBatch::where('product_id', $damage->product_id)
                ->where('store_id', $damage->store_id)
                ->where('is_active', true)
                ->where('current_qty', '>', 0)
                ->orderBy('received_date', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            if ($availableBatch) {
                $deductQty = min($damage->qty_damaged, $availableBatch->current_qty);
                $availableBatch->deductStock(
                    $deductQty,
                    'damaged',
                    PharmacyDamage::class,
                    $damage->id,
                    "Damage #{$damage->id} - {$damage->damage_type}: {$damage->damage_reason} (auto-assigned batch)"
                );

                // Update the damage record with the auto-assigned batch
                $damage->updateQuietly(['batch_id' => $availableBatch->id]);

                Log::info('PharmacyDamageObserver: Auto-assigned batch and deducted stock', [
                    'damage_id' => $damage->id,
                    'auto_batch_id' => $availableBatch->id,
                    'qty_deducted' => $deductQty,
                ]);
            } else {
                // No batches at all — truly legacy
                Log::info('PharmacyDamageObserver: No batches available, using direct deduction fallback', [
                    'damage_id' => $damage->id,
                ]);
                $this->directDeductFallback($damage);
            }
        }

        // Mark as deducted to prevent duplicate deductions
        $damage->updateQuietly([
            'stock_deducted' => true,
            'stock_deducted_at' => now(),
        ]);
    }

    /**
     * Fallback: directly deduct from store_stocks and global stock
     * when no batch is available. This is the legacy path.
     */
    protected function directDeductFallback(PharmacyDamage $damage): void
    {
        // Deduct from store-specific stock
        $storeStock = StoreStock::where('product_id', $damage->product_id)
            ->where('store_id', $damage->store_id)
            ->first();
        if ($storeStock) {
            $newQty = max(0, $storeStock->current_quantity - $damage->qty_damaged);
            $storeStock->update(['current_quantity' => $newQty]);
        }

        // Deduct from global stock
        $product = Product::with('stock')->find($damage->product_id);
        if ($product && $product->stock) {
            $newGlobalQty = max(0, $product->stock->current_quantity - $damage->qty_damaged);
            $product->stock->update(['current_quantity' => $newGlobalQty]);
        }
    }

    /**
     * Get the appropriate expense account based on damage type.
     */
    protected function getExpenseAccount(string $damageType): ?Account
    {
        return match($damageType) {
            'expired' => Account::where('code', self::EXPIRED_STOCK)->first(),
            'theft' => Account::where('code', self::THEFT_SHRINKAGE)->first(),
            default => Account::where('code', self::DAMAGED_GOODS)->first(),
        };
    }

    /**
     * Build the journal entry description.
     */
    protected function buildDescription(PharmacyDamage $damage): string
    {
        $productName = $damage->product->product_name ?? 'Unknown Product';
        $storeName = $damage->store->store_name ?? 'Unknown Store';
        $damageType = ucfirst($damage->damage_type);

        return "Inventory Write-off - {$damageType} | Damage #{$damage->id} | Product: {$productName} | Store: {$storeName} | Qty: {$damage->qty_damaged} | Value: ₦{$damage->total_value}";
    }
}
