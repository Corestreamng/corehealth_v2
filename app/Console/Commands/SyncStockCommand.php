<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\StockBatch;
use App\Models\StoreStock;
use App\Models\Stock;
use App\Models\Store;
use App\Models\Product;
use Illuminate\Support\Str;

/**
 * Command: stock:sync
 *
 * Purpose: One-time (or periodic) bulk recalculation that ensures:
 *   1. store_stocks.current_quantity = SUM(stock_batches.current_qty) for each product+store
 *   2. stocks.current_quantity       = SUM across all stores
 *   3. Any store_stock rows that have qty but NO batches get a reconciliation batch created
 *
 * Safe to run multiple times — idempotent.
 *
 * Usage:
 *   php artisan stock:sync                  — sync all products
 *   php artisan stock:sync --store=3        — sync only store ID 3
 *   php artisan stock:sync --product=42     — sync only product ID 42
 *   php artisan stock:sync --create-batches — also create reconciliation batches for unbatched stock
 *   php artisan stock:sync --dry-run        — preview changes without writing
 */
class SyncStockCommand extends Command
{
    protected $signature = 'stock:sync
                            {--store= : Sync only a specific store ID}
                            {--product= : Sync only a specific product ID}
                            {--create-batches : Create reconciliation batches for store_stocks qty that has no matching batch}
                            {--dry-run : Preview changes without writing to the database}';

    protected $description = 'Recalculate store_stocks and global stocks from stock_batches totals. Fixes desync issues.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $createBatches = $this->option('create-batches');
        $storeId = $this->option('store');
        $productId = $this->option('product');

        if ($dryRun) {
            $this->warn('⚠  DRY RUN — no changes will be written.');
        }

        // ──────────────────────────────────────────────
        // Step 1: Sync store_stocks from stock_batches
        // ──────────────────────────────────────────────
        $this->info('');
        $this->info('══════════════════════════════════════════════');
        $this->info('  Step 1: Sync store_stocks from batch totals');
        $this->info('══════════════════════════════════════════════');

        $batchTotals = StockBatch::select(
                'product_id',
                'store_id',
                DB::raw('SUM(current_qty) as batch_total')
            )
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->when($productId, fn($q) => $q->where('product_id', $productId))
            ->groupBy('product_id', 'store_id')
            ->get();

        $syncedCount = 0;
        $mismatchCount = 0;
        $mismatches = [];

        foreach ($batchTotals as $row) {
            $storeStock = StoreStock::where('product_id', $row->product_id)
                ->where('store_id', $row->store_id)
                ->first();

            $currentVal = $storeStock ? (int) $storeStock->current_quantity : 0;
            $batchVal = (int) $row->batch_total;

            if ($currentVal !== $batchVal) {
                $mismatchCount++;
                $product = Product::find($row->product_id);
                $productName = $product ? ($product->product_name ?? "ID:{$row->product_id}") : "[DELETED] ID:{$row->product_id}";
                $storeName = Store::find($row->store_id)->store_name ?? "ID:{$row->store_id}";

                $mismatches[] = [
                    $productName,
                    $storeName,
                    $currentVal,
                    $batchVal,
                    $batchVal - $currentVal,
                ];

                if (!$dryRun) {
                    StoreStock::updateOrCreate(
                        ['product_id' => $row->product_id, 'store_id' => $row->store_id],
                        [
                            'current_quantity' => $batchVal,
                            'last_restocked_at' => $batchVal > 0 ? now() : null,
                            'is_active' => true,
                        ]
                    );
                }
                $syncedCount++;
            }
        }

        if (count($mismatches) > 0) {
            $this->table(
                ['Product', 'Store', 'Old Qty', 'Batch Total', 'Diff'],
                $mismatches
            );
            $this->info("→ {$syncedCount} store_stocks rows " . ($dryRun ? 'would be' : 'were') . " corrected.");
        } else {
            $this->info('✓ All store_stocks rows are already in sync with batch totals.');
        }

        // ──────────────────────────────────────────────
        // Step 2: Find store_stocks with qty but NO batches
        // ──────────────────────────────────────────────
        $this->info('');
        $this->info('══════════════════════════════════════════════');
        $this->info('  Step 2: Detect unbatched stock');
        $this->info('══════════════════════════════════════════════');

        $storeStocksQuery = StoreStock::where('current_quantity', '>', 0)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->when($productId, fn($q) => $q->where('product_id', $productId))
            ->get();

        $unbatchedRows = [];
        $orphanedRows = [];

        foreach ($storeStocksQuery as $ss) {
            // Skip orphaned store_stocks where product no longer exists
            $product = Product::find($ss->product_id);
            if (!$product) {
                $storeName = $ss->store->store_name ?? "ID:{$ss->store_id}";
                $orphanedRows[] = [
                    'product_id' => $ss->product_id,
                    'store_id' => $ss->store_id,
                    'store_name' => $storeName,
                    'qty' => (int) $ss->current_quantity,
                ];
                continue;
            }

            $batchSum = StockBatch::where('product_id', $ss->product_id)
                ->where('store_id', $ss->store_id)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->sum('current_qty');

            $unbatched = (int) $ss->current_quantity - (int) $batchSum;

            if ($unbatched > 0) {
                $productName = $product->product_name ?? "ID:{$ss->product_id}";
                $storeName = $ss->store->store_name ?? "ID:{$ss->store_id}";

                $unbatchedRows[] = [
                    'product_id' => $ss->product_id,
                    'store_id' => $ss->store_id,
                    'product_name' => $productName,
                    'store_name' => $storeName,
                    'store_qty' => (int) $ss->current_quantity,
                    'batch_sum' => (int) $batchSum,
                    'unbatched' => $unbatched,
                ];
            }
        }

        // Report orphaned store_stocks (product deleted but store_stock remains)
        if (count($orphanedRows) > 0) {
            $this->error("⚠  Found " . count($orphanedRows) . " orphaned store_stock rows (product no longer exists):");
            $this->table(
                ['Product ID', 'Store', 'Qty (orphaned)'],
                collect($orphanedRows)->map(fn($r) => [$r['product_id'], $r['store_name'], $r['qty']])->toArray()
            );
            $this->warn('→ These rows were SKIPPED. Consider cleaning them up manually: DELETE FROM store_stocks WHERE product_id NOT IN (SELECT id FROM products);');
        }

        if (count($unbatchedRows) > 0) {
            $this->warn("Found " . count($unbatchedRows) . " product+store combos with stock that has no matching batch:");
            $this->table(
                ['Product', 'Store', 'StoreStock Qty', 'Batch Sum', 'Unbatched'],
                collect($unbatchedRows)->map(fn($r) => [
                    $r['product_name'], $r['store_name'], $r['store_qty'], $r['batch_sum'], $r['unbatched']
                ])->toArray()
            );

            if ($createBatches) {
                $createdCount = 0;
                foreach ($unbatchedRows as $row) {
                    if (!$dryRun) {
                        // Get cost price from prices table
                        $costPrice = DB::table('prices')
                            ->where('product_id', $row['product_id'])
                            ->value('pr_buy_price') ?? 0;

                        StockBatch::create([
                            'product_id' => $row['product_id'],
                            'store_id' => $row['store_id'],
                            'batch_name' => 'Reconciliation - ' . now()->format('M d, Y h:i A'),
                            'batch_number' => 'RECON-' . strtoupper(Str::random(6)),
                            'initial_qty' => $row['unbatched'],
                            'current_qty' => $row['unbatched'],
                            'sold_qty' => 0,
                            'cost_price' => $costPrice,
                            'received_date' => now(),
                            'source' => 'manual',
                            'is_active' => true,
                            'created_by' => 1, // system
                        ]);
                        $createdCount++;
                    }
                }
                $this->info("→ {$createdCount} reconciliation batches " . ($dryRun ? 'would be' : 'were') . " created.");
            } else {
                $this->warn('→ Run with --create-batches to auto-create reconciliation batches for unbatched stock.');
            }
        } else {
            $this->info('✓ No unbatched stock found — every store_stock row has matching batch coverage.');
        }

        // ──────────────────────────────────────────────
        // Step 3: Sync global stocks table
        // ──────────────────────────────────────────────
        $this->info('');
        $this->info('══════════════════════════════════════════════');
        $this->info('  Step 3: Sync legacy global stocks table');
        $this->info('══════════════════════════════════════════════');

        $productIds = $productId
            ? [$productId]
            : StockBatch::select('product_id')->distinct()->pluck('product_id')->toArray();

        $globalSynced = 0;
        $globalMismatches = [];

        foreach ($productIds as $pid) {
            $batchTotal = StockBatch::where('product_id', $pid)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->sum('current_qty');

            $globalStock = Stock::where('product_id', $pid)->first();
            $globalCurrent = $globalStock ? (int) $globalStock->current_quantity : 0;

            if ($globalCurrent !== (int) $batchTotal) {
                $product = Product::find($pid);
                $productName = $product ? ($product->product_name ?? "ID:{$pid}") : "[DELETED] ID:{$pid}";
                $globalMismatches[] = [$productName, $globalCurrent, (int) $batchTotal, (int) $batchTotal - $globalCurrent];

                if (!$dryRun && $globalStock) {
                    $globalStock->update(['current_quantity' => $batchTotal]);
                }
                $globalSynced++;
            }
        }

        if (count($globalMismatches) > 0) {
            $this->table(['Product', 'Old Global', 'Batch Total', 'Diff'], $globalMismatches);
            $this->info("→ {$globalSynced} global stock rows " . ($dryRun ? 'would be' : 'were') . " corrected.");
        } else {
            $this->info('✓ Global stocks table is already in sync.');
        }

        // ──────────────────────────────────────────────
        // Step 4 – Sync product buy prices from latest batch cost
        // ──────────────────────────────────────────────
        $this->info('');
        $this->info('Step 4: Syncing product buy prices from latest batch cost...');

        $priceSynced = 0;
        $stockService = app(\App\Services\StockService::class);

        foreach ($productIds as $pid) {
            $latestBatch = StockBatch::where('product_id', $pid)
                ->where('cost_price', '>', 0)
                ->orderByDesc('received_date')
                ->orderByDesc('id')
                ->first();

            if (!$latestBatch) continue;

            $price = \App\Models\Price::where('product_id', $pid)->first();
            if ($price && (float) $price->pr_buy_price !== (float) $latestBatch->cost_price) {
                if (!$dryRun) {
                    $price->update(['pr_buy_price' => $latestBatch->cost_price]);
                }
                $product = Product::find($pid);
                $productName = $product ? ($product->product_name ?? "ID:{$pid}") : "ID:{$pid}";
                $this->line("  {$productName}: ₦{$price->pr_buy_price} → ₦{$latestBatch->cost_price}");
                $priceSynced++;
            }
        }

        if ($priceSynced > 0) {
            $this->info("→ {$priceSynced} product prices " . ($dryRun ? 'would be' : 'were') . ' updated to latest batch cost.');
        } else {
            $this->info('✓ All product buy prices are already in sync with latest batch cost.');
        }

        // ──────────────────────────────────────────────
        // Summary
        // ──────────────────────────────────────────────
        $this->info('');
        $this->info('══════════════════════════════════════════════');
        $this->info('  Summary');
        $this->info('══════════════════════════════════════════════');
        $this->info("Store-stock mismatches fixed:  {$mismatchCount}");
        $this->info("Unbatched stock rows found:    " . count($unbatchedRows));
        $this->info("Orphaned store_stocks skipped: " . count($orphanedRows));
        $this->info("Global stock mismatches fixed:  " . count($globalMismatches));
        $this->info("Product prices synced:          {$priceSynced}");

        if ($dryRun) {
            $this->warn('No changes written (dry run). Remove --dry-run to apply.');
        } else {
            $this->info('✅ Stock sync complete.');
        }

        Log::info('stock:sync completed', [
            'dry_run' => $dryRun,
            'store_stock_fixes' => $mismatchCount,
            'unbatched_found' => count($unbatchedRows),
            'orphaned_skipped' => count($orphanedRows),
            'global_fixes' => count($globalMismatches),
            'prices_synced' => $priceSynced,
        ]);

        return Command::SUCCESS;
    }
}
