<?php

namespace App\Observers\Accounting;

use App\Models\StoreDamage;
use App\Models\Accounting\Account;
use App\Services\Accounting\AccountingService;
use App\Services\StockService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Store Damage Observer
 *
 * Mirrors PharmacyDamageObserver for non-pharmacy stores.
 *
 * On Damage Approved:
 *   For broken/contaminated/spoiled/other:
 *     DR: Damaged Goods Write-off (5030)
 *     CR: Inventory - Medical Supplies (1310)   ← store inventory account
 *
 *   For expired:
 *     DR: Expired Stock Write-off (5040)
 *     CR: Inventory - Medical Supplies (1310)
 *
 *   For theft:
 *     DR: Theft/Shrinkage (5050)
 *     CR: Inventory - Medical Supplies (1310)
 */
class StoreDamageObserver
{
    // Account codes
    private const INVENTORY_STORE  = '1310'; // Inventory - Medical Supplies (general store inventory)
    private const DAMAGED_GOODS    = '5030';
    private const EXPIRED_STOCK    = '5040';
    private const THEFT_SHRINKAGE  = '5050';

    public function updated(StoreDamage $damage): void
    {
        if (!($damage->isDirty('status') && $damage->status === 'approved')) {
            return;
        }

        try {
            if ($damage->journal_entry_id) {
                Log::info('StoreDamageObserver: JE already exists, skipping', ['damage_id' => $damage->id]);
                return;
            }

            DB::transaction(function () use ($damage) {
                $this->createJournalEntry($damage);
                
                $stockService = App::make(StockService::class);
                $stockService->writeOffStoreDamage($damage);

                $damage->update([
                    'stock_deducted'    => true,
                    'stock_deducted_at' => now(),
                ]);
            });

        } catch (\Exception $e) {
            Log::error('StoreDamageObserver: Failed', [
                'damage_id' => $damage->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    protected function createJournalEntry(StoreDamage $damage): void
    {
        $accountingService = App::make(AccountingService::class);

        $expenseAccount   = Account::where('code', $this->getExpenseCode($damage->damage_type))->first();
        $inventoryAccount = Account::where('code', self::INVENTORY_STORE)->first();

        if (!$expenseAccount || !$inventoryAccount) {
            Log::warning('StoreDamageObserver: Required accounts not found', [
                'damage_id'      => $damage->id,
                'expense_code'   => $this->getExpenseCode($damage->damage_type),
                'inventory_code' => self::INVENTORY_STORE,
            ]);
            return;
        }

        $description = sprintf(
            'Store Damage Write-off #%d — %s × %s (%s)',
            $damage->id,
            $damage->qty_damaged,
            $damage->product->product_name ?? 'Product #' . $damage->product_id,
            ucfirst($damage->damage_type)
        );

        $lines = [
            [
                'account_id'    => $expenseAccount->id,
                'debit_amount'  => $damage->total_value,
                'credit_amount' => 0,
                'description'   => "Inventory loss ({$damage->damage_type}): {$damage->product->product_name}",
                'product_id'    => $damage->product_id,
                'category'      => 'inventory_damage',
            ],
            [
                'account_id'    => $inventoryAccount->id,
                'debit_amount'  => 0,
                'credit_amount' => $damage->total_value,
                'description'   => "Write-off from {$damage->store->store_name}",
                'product_id'    => $damage->product_id,
                'category'      => 'inventory_damage',
            ],
        ];

        $journalEntry = $accountingService->createAndPostAutomatedEntry(
            StoreDamage::class,
            $damage->id,
            $description,
            $lines,
            now()->toDateString(),
            auth()->id() ?? $damage->approved_by ?? 1
        );

        $damage->update(['journal_entry_id' => $journalEntry->id]);

        Log::info('StoreDamageObserver: JE created', [
            'damage_id'       => $damage->id,
            'journal_entry_id'=> $journalEntry->id,
            'total_value'     => $damage->total_value,
        ]);
    }

    private function getExpenseCode(string $damageType): string
    {
        return match ($damageType) {
            'expired'                   => self::EXPIRED_STOCK,
            'theft'                     => self::THEFT_SHRINKAGE,
            default                     => self::DAMAGED_GOODS, // broken, contaminated, spoiled, other
        };
    }
}
