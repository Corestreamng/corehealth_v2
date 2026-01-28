<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Price;
use App\Models\Stock;
use App\Models\StoreStock;
use App\Models\Store;
use App\Services\ImportProgressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const BATCH_SIZE = 400;

    protected string $importId;
    protected string $filePath;
    protected ?int $defaultStoreId;
    protected int $userId;
    protected string $duplicateAction;

    public $timeout = 7200; // 2 hours for large files
    public $tries = 1;

    public function __construct(string $importId, string $filePath, ?int $defaultStoreId, int $userId, string $duplicateAction = 'update')
    {
        $this->importId = $importId;
        $this->filePath = $filePath;
        $this->defaultStoreId = $defaultStoreId;
        $this->userId = $userId;
        $this->duplicateAction = $duplicateAction;
    }

    public function handle(): void
    {
        // Disable PHP execution time limit for large imports
        set_time_limit(0);
        ini_set('memory_limit', '1G');

        $fullPath = storage_path('app/' . $this->filePath);

        try {
            // Parse file in chunks using generator
            $generator = $this->parseFileInChunks($fullPath);

            // Pre-fetch lookup data
            $existingProducts = Product::pluck('id', 'product_code')->toArray();
            $categories = ProductCategory::pluck('id', 'category_name')->toArray();
            $stores = Store::pluck('id', 'store_name')->toArray();

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $errors = [];
            $processed = 0;
            $batch = [];
            $batchIndex = 0;

            foreach ($generator as $rowNum => $row) {
                $batch[] = ['row' => $row, 'rowNum' => $rowNum];

                if (count($batch) >= self::BATCH_SIZE) {
                    // Check for cancellation
                    if (ImportProgressService::isCancelled($this->importId)) {
                        $errors[] = 'Import was cancelled by user';
                        break;
                    }

                    $result = $this->processBatch($batch, $existingProducts, $categories, $stores, $batchIndex, $this->duplicateAction);
                    $created += $result['created'];
                    $updated += $result['updated'];
                    $skipped += $result['skipped'];
                    $processed += count($batch);

                    // Limit error accumulation to prevent memory issues
                    if (count($errors) < 500) {
                        $errors = array_merge($errors, array_slice($result['errors'], 0, 50));
                    }

                    // Merge back any new products/categories
                    $existingProducts = array_merge($existingProducts, $result['new_products']);
                    $categories = array_merge($categories, $result['new_categories']);

                    // Update progress
                    ImportProgressService::updateProgress(
                        $this->importId, $processed, $created, $updated, $skipped,
                        array_slice($errors, 0, 100), $batchIndex + 1
                    );

                    $batch = [];
                    $batchIndex++;

                    // Free memory periodically
                    if ($batchIndex % 10 === 0) {
                        gc_collect_cycles();
                    }
                }
            }

            // Process remaining batch
            if (!empty($batch) && !ImportProgressService::isCancelled($this->importId)) {
                $result = $this->processBatch($batch, $existingProducts, $categories, $stores, $batchIndex, $this->duplicateAction);
                $created += $result['created'];
                $updated += $result['updated'];
                $skipped += $result['skipped'];
                $processed += count($batch);

                if (count($errors) < 500) {
                    $errors = array_merge($errors, array_slice($result['errors'], 0, 50));
                }

                ImportProgressService::updateProgress(
                    $this->importId, $processed, $created, $updated, $skipped,
                    array_slice($errors, 0, 100), $batchIndex + 1
                );
            }

            // Complete
            ImportProgressService::completeImport($this->importId, [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => array_slice($errors, 0, 100), // Limit errors to 100
            ]);

        } catch (\Exception $e) {
            Log::error("Product import job failed: " . $e->getMessage());
            ImportProgressService::failImport($this->importId, $e->getMessage());
        } finally {
            // Clean up the temp file
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

    /**
     * Parse file in chunks using a generator (memory efficient)
     */
    private function parseFileInChunks(string $filePath): \Generator
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($extension, ['xlsx', 'xls'])) {
            yield from $this->parseExcelInChunks($filePath);
        } else {
            yield from $this->parseCsvInChunks($filePath);
        }
    }

    private function parseExcelInChunks(string $filePath): \Generator
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [];
        $rowIndex = 0;

        foreach ($sheet->getRowIterator() as $row) {
            $rowIndex++;
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }

            if ($rowIndex === 1) {
                // Headers
                $headers = array_map(function($h) {
                    return strtolower(trim(str_replace(['"', "'"], '', $h ?? '')));
                }, $rowData);
                continue;
            }

            // Skip empty rows
            if (empty(array_filter($rowData))) {
                continue;
            }

            $assocRow = [];
            foreach ($headers as $i => $header) {
                if (!empty($header)) {
                    $assocRow[$header] = isset($rowData[$i]) ? trim((string)$rowData[$i]) : '';
                }
            }

            yield $rowIndex => $assocRow;
        }

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function parseCsvInChunks(string $filePath): \Generator
    {
        $handle = fopen($filePath, 'r');
        $headers = [];
        $rowIndex = 0;

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $rowIndex++;

            if ($rowIndex === 1) {
                $headers = array_map(function($h) {
                    return strtolower(trim(str_replace(['"', "'"], '', $h ?? '')));
                }, $row);
                continue;
            }

            if (empty(array_filter($row))) {
                continue;
            }

            $assocRow = [];
            foreach ($headers as $i => $header) {
                if (!empty($header)) {
                    $assocRow[$header] = isset($row[$i]) ? trim($row[$i]) : '';
                }
            }

            yield $rowIndex => $assocRow;
        }

        fclose($handle);
    }

    private function processBatch(array $batch, array &$existingProducts, array &$categories, array $stores, int $batchIndex, string $duplicateAction = 'update'): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $newProducts = [];
        $newCategories = [];

        DB::beginTransaction();
        try {
            foreach ($batch as $item) {
                $row = $item['row'];
                $rowNum = $item['rowNum'];

                if (empty($row['product_name']) || empty($row['product_code'])) {
                    $errors[] = "Row {$rowNum}: Missing product_name or product_code";
                    $skipped++;
                    continue;
                }

                $productCode = trim($row['product_code']);
                $productName = trim($row['product_name']);

                // Get or create category
                $categoryId = null;
                if (!empty($row['category_name'])) {
                    $categoryName = trim($row['category_name']);
                    if (!isset($categories[$categoryName])) {
                        $category = ProductCategory::create([
                            'category_name' => $categoryName,
                            'category_description' => 'Auto-created during import'
                        ]);
                        $categories[$categoryName] = $category->id;
                        $newCategories[$categoryName] = $category->id;
                    }
                    $categoryId = $categories[$categoryName];
                }

                // Determine store
                $storeId = null;
                if (!empty($row['store_name']) && isset($stores[trim($row['store_name'])])) {
                    $storeId = $stores[trim($row['store_name'])];
                }
                $storeId = $storeId ?? $this->defaultStoreId;

                $costPrice = floatval($row['cost_price'] ?? 0);
                $salePrice = floatval($row['sale_price'] ?? 0);
                $initialQty = intval($row['initial_quantity'] ?? 0);
                $reorderLevel = intval($row['reorder_level'] ?? 10);
                $isActive = ($row['is_active'] ?? 1) == 1;

                if (isset($existingProducts[$productCode])) {
                    // DUPLICATE FOUND
                    if ($duplicateAction === 'skip') {
                        // Skip existing records
                        $skipped++;
                        continue;
                    }

                    // UPDATE existing record
                    $productId = $existingProducts[$productCode];

                    Product::where('id', $productId)->update([
                        'product_name' => $productName,
                        'category_id' => $categoryId,
                        'reorder_alert' => $reorderLevel,
                        'status' => $isActive ? 1 : 0,
                    ]);

                    Price::updateOrCreate(
                        ['product_id' => $productId],
                        [
                            'pr_buy_price' => $costPrice,
                            'current_sale_price' => $salePrice,
                            'initial_sale_price' => $salePrice,
                            'max_discount' => 0,
                            'status' => 1,
                        ]
                    );

                    $updated++;
                } else {
                    // CREATE
                    $product = Product::create([
                        'user_id' => $this->userId,
                        'category_id' => $categoryId,
                        'product_name' => $productName,
                        'product_code' => $productCode,
                        'reorder_alert' => $reorderLevel,
                        'status' => $isActive ? 1 : 0,
                        'current_quantity' => $initialQty,
                    ]);

                    Price::create([
                        'product_id' => $product->id,
                        'pr_buy_price' => $costPrice,
                        'initial_sale_price' => $salePrice,
                        'current_sale_price' => $salePrice,
                        'max_discount' => 0,
                        'status' => 1,
                    ]);

                    Stock::create([
                        'product_id' => $product->id,
                        'initial_quantity' => $initialQty,
                        'current_quantity' => $initialQty,
                        'quantity_sale' => 0,
                        'order_quantity' => 0,
                    ]);

                    if ($storeId) {
                        StoreStock::create([
                            'store_id' => $storeId,
                            'product_id' => $product->id,
                            'initial_quantity' => $initialQty,
                            'current_quantity' => $initialQty,
                            'quantity_sale' => 0,
                            'order_quantity' => 0,
                            'reorder_level' => $reorderLevel,
                            'is_active' => true,
                        ]);
                    }

                    $existingProducts[$productCode] = $product->id;
                    $newProducts[$productCode] = $product->id;
                    $created++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $errors[] = "Batch " . ($batchIndex + 1) . " failed: " . $e->getMessage();
            Log::error("Product import batch {$batchIndex} failed: " . $e->getMessage());
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'new_products' => $newProducts,
            'new_categories' => $newCategories,
        ];
    }

    public function failed(\Throwable $exception): void
    {
        ImportProgressService::failImport($this->importId, $exception->getMessage());

        // Clean up temp file on failure
        $fullPath = storage_path('app/' . $this->filePath);
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }
}
