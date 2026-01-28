<?php

namespace App\Jobs;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Services\ImportProgressService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportServicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const BATCH_SIZE = 400;

    protected string $importId;
    protected string $filePath;
    protected int $userId;
    protected string $duplicateAction;

    public $timeout = 7200;
    public $tries = 1;

    public function __construct(string $importId, string $filePath, int $userId, string $duplicateAction = 'update')
    {
        $this->importId = $importId;
        $this->filePath = $filePath;
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
            $generator = $this->parseFileInChunks($fullPath);

            $existingServices = Service::pluck('id', 'service_code')->toArray();
            $categories = ServiceCategory::pluck('id', 'category_name')->toArray();

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
                    if (ImportProgressService::isCancelled($this->importId)) {
                        $errors[] = 'Import was cancelled by user';
                        break;
                    }

                    $result = $this->processBatch($batch, $existingServices, $categories, $batchIndex, $this->duplicateAction);
                    $created += $result['created'];
                    $updated += $result['updated'];
                    $skipped += $result['skipped'];
                    $processed += count($batch);

                    // Limit error accumulation to prevent memory issues
                    if (count($errors) < 500) {
                        $errors = array_merge($errors, array_slice($result['errors'], 0, 50));
                    }

                    $existingServices = array_merge($existingServices, $result['new_services']);
                    $categories = array_merge($categories, $result['new_categories']);

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

            if (!empty($batch) && !ImportProgressService::isCancelled($this->importId)) {
                $result = $this->processBatch($batch, $existingServices, $categories, $batchIndex, $this->duplicateAction);
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

            ImportProgressService::completeImport($this->importId, [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => array_slice($errors, 0, 100),
            ]);

        } catch (\Exception $e) {
            Log::error("Service import job failed: " . $e->getMessage());
            ImportProgressService::failImport($this->importId, $e->getMessage());
        } finally {
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }
    }

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
                $headers = array_map(function($h) {
                    return strtolower(trim(str_replace(['"', "'"], '', $h ?? '')));
                }, $rowData);
                continue;
            }

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

    private function processBatch(array $batch, array &$existingServices, array &$categories, int $batchIndex, string $duplicateAction = 'update'): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];
        $newServices = [];
        $newCategories = [];

        DB::beginTransaction();
        try {
            foreach ($batch as $item) {
                $row = $item['row'];
                $rowNum = $item['rowNum'];

                if (empty($row['service_name']) || empty($row['service_code'])) {
                    $errors[] = "Row {$rowNum}: Missing service_name or service_code";
                    $skipped++;
                    continue;
                }

                $serviceCode = trim($row['service_code']);
                $serviceName = trim($row['service_name']);

                // Get or create category
                $categoryId = null;
                if (!empty($row['category_name'])) {
                    $categoryName = trim($row['category_name']);
                    if (!isset($categories[$categoryName])) {
                        $category = ServiceCategory::create([
                            'category_name' => $categoryName,
                            'category_description' => 'Auto-created during import'
                        ]);
                        $categories[$categoryName] = $category->id;
                        $newCategories[$categoryName] = $category->id;
                    }
                    $categoryId = $categories[$categoryName];
                }

                $costPrice = floatval($row['cost_price'] ?? 0);
                $salePrice = floatval($row['price'] ?? $row['sale_price'] ?? 0);
                $isActive = ($row['is_active'] ?? 1) == 1;

                if (isset($existingServices[$serviceCode])) {
                    // DUPLICATE FOUND
                    if ($duplicateAction === 'skip') {
                        // Skip existing records
                        $skipped++;
                        continue;
                    }

                    // UPDATE existing record
                    $serviceId = $existingServices[$serviceCode];

                    Service::where('id', $serviceId)->update([
                        'service_name' => $serviceName,
                        'category_id' => $categoryId,
                        'status' => $isActive ? 1 : 0,
                    ]);

                    ServicePrice::updateOrCreate(
                        ['service_id' => $serviceId],
                        [
                            'cost_price' => $costPrice,
                            'sale_price' => $salePrice,
                            'status' => 1,
                        ]
                    );

                    $updated++;
                } else {
                    // CREATE
                    $service = Service::create([
                        'user_id' => $this->userId,
                        'category_id' => $categoryId,
                        'service_name' => $serviceName,
                        'service_code' => $serviceCode,
                        'status' => $isActive ? 1 : 0,
                    ]);

                    ServicePrice::create([
                        'service_id' => $service->id,
                        'cost_price' => $costPrice,
                        'sale_price' => $salePrice,
                        'status' => 1,
                    ]);

                    $existingServices[$serviceCode] = $service->id;
                    $newServices[$serviceCode] = $service->id;
                    $created++;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $errors[] = "Batch " . ($batchIndex + 1) . " failed: " . $e->getMessage();
            Log::error("Service import batch {$batchIndex} failed: " . $e->getMessage());
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'new_services' => $newServices,
            'new_categories' => $newCategories,
        ];
    }

    public function failed(\Throwable $exception): void
    {
        ImportProgressService::failImport($this->importId, $exception->getMessage());

        $fullPath = storage_path('app/' . $this->filePath);
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }
}
