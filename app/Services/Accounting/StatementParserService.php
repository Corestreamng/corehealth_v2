<?php

namespace App\Services\Accounting;

use App\Models\Accounting\BankStatementImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Statement Parser Service
 *
 * Handles parsing and viewing of bank statements in multiple formats:
 * - Excel (.xlsx, .xls)
 * - CSV
 * - PDF (view only)
 * - Word (.docx)
 * - Images (view only)
 */
class StatementParserService
{
    protected string $storagePath = 'bank-statements';

    /**
     * Process an uploaded statement file.
     */
    public function processUpload(
        UploadedFile $file,
        int $bankId,
        ?int $reconciliationId = null
    ): BankStatementImport {
        $format = $this->determineFormat($file);
        $originalName = $file->getClientOriginalName();

        // Store the file
        $path = $file->store($this->storagePath, 'public');

        // Create import record
        $import = BankStatementImport::create([
            'bank_id' => $bankId,
            'reconciliation_id' => $reconciliationId,
            'file_name' => $originalName,
            'file_path' => $path,
            'file_format' => $format,
            'statement_date' => now()->toDateString(),
            'period_from' => now()->startOfMonth()->toDateString(),
            'period_to' => now()->toDateString(),
            'opening_balance' => 0,
            'closing_balance' => 0,
            'status' => BankStatementImport::STATUS_UPLOADED,
            'imported_by' => auth()->id(),
            'imported_at' => now(),
        ]);

        // Try to parse if supported
        if ($this->supportsDataExtraction($format)) {
            try {
                $import->status = BankStatementImport::STATUS_PARSING;
                $import->save();

                $data = $this->parseFile($import);

                $import->total_transactions = $data['total_transactions'] ?? 0;
                $import->imported_transactions = $data['imported_transactions'] ?? 0;
                $import->failed_transactions = $data['failed_transactions'] ?? 0;
                $import->opening_balance = $data['opening_balance'] ?? $import->opening_balance ?? 0;
                $import->closing_balance = $data['closing_balance'] ?? $import->closing_balance ?? 0;
                $import->parsed_at = now();
                $import->status = BankStatementImport::STATUS_PARSED;
                $import->save();
            } catch (\Exception $e) {
                Log::error('Statement parsing failed', [
                    'import_id' => $import->id,
                    'error' => $e->getMessage()
                ]);

                $import->status = BankStatementImport::STATUS_FAILED;
                $import->error_log = $e->getMessage();
                $import->save();
            }
        }

        return $import;
    }

    /**
     * Determine file format from uploaded file.
     */
    public function determineFormat(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        // Excel formats
        if (in_array($extension, ['xlsx', 'xls']) ||
            str_contains($mimeType, 'spreadsheet') ||
            str_contains($mimeType, 'excel')) {
            return BankStatementImport::FORMAT_EXCEL;
        }

        // CSV format
        if ($extension === 'csv' || str_contains($mimeType, 'csv')) {
            return BankStatementImport::FORMAT_CSV;
        }

        // PDF format
        if ($extension === 'pdf' || str_contains($mimeType, 'pdf')) {
            return BankStatementImport::FORMAT_PDF;
        }

        // Word format
        if (in_array($extension, ['docx', 'doc']) ||
            str_contains($mimeType, 'word') ||
            str_contains($mimeType, 'document')) {
            return BankStatementImport::FORMAT_WORD;
        }

        // Image formats
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']) ||
            str_starts_with($mimeType, 'image/')) {
            return BankStatementImport::FORMAT_IMAGE;
        }

        return BankStatementImport::FORMAT_PDF; // Default fallback
    }

    /**
     * Check if format supports data extraction.
     */
    public function supportsDataExtraction(string $format): bool
    {
        return in_array($format, [
            BankStatementImport::FORMAT_EXCEL,
            BankStatementImport::FORMAT_CSV,
        ]);
    }

    /**
     * Check if format is view-only.
     */
    public function isViewOnly(string $format): bool
    {
        return in_array($format, [
            BankStatementImport::FORMAT_PDF,
            BankStatementImport::FORMAT_WORD,
            BankStatementImport::FORMAT_IMAGE,
        ]);
    }

    /**
     * Parse file and extract data.
     */
    public function parseFile(BankStatementImport $import): array
    {
        $fullPath = Storage::disk('public')->path($import->file_path);

        switch ($import->file_format) {
            case BankStatementImport::FORMAT_EXCEL:
                return $this->parseExcel($fullPath);
            case BankStatementImport::FORMAT_CSV:
                return $this->parseCSV($fullPath);
            default:
                return [
                    'total_transactions' => 0,
                    'imported_transactions' => 0,
                    'failed_transactions' => 0,
                    'rows' => [],
                ];
        }
    }

    /**
     * Parse Excel file.
     */
    protected function parseExcel(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = [];
        $totalTransactions = 0;
        $importedTransactions = 0;
        $failedTransactions = 0;

        foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }

            // Skip empty rows
            if (empty(array_filter($rowData))) {
                continue;
            }

            $rows[] = [
                'row_number' => $rowIndex,
                'data' => $rowData,
                'selected' => false,
                'matched' => false,
            ];

            // Count as transaction if row has numeric value (basic heuristic)
            foreach ($rowData as $value) {
                if (is_numeric($value) && abs($value) > 0) {
                    $totalTransactions++;
                    $importedTransactions++;
                    break;
                }
            }
        }

        return [
            'total_transactions' => $totalTransactions,
            'imported_transactions' => $importedTransactions,
            'failed_transactions' => $failedTransactions,
            'rows' => $rows,
            'headers' => $rows[0]['data'] ?? [],
        ];
    }

    /**
     * Parse CSV file.
     */
    protected function parseCSV(string $filePath): array
    {
        $rows = [];
        $totalTransactions = 0;
        $importedTransactions = 0;
        $failedTransactions = 0;
        $rowIndex = 0;

        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($data = fgetcsv($handle, 0, ',')) !== false) {
                $rowIndex++;

                // Skip empty rows
                if (empty(array_filter($data))) {
                    continue;
                }

                $rows[] = [
                    'row_number' => $rowIndex,
                    'data' => $data,
                    'selected' => false,
                    'matched' => false,
                ];

                // Count as transaction if row has numeric value
                foreach ($data as $value) {
                    if (is_numeric($value) && abs($value) > 0) {
                        $totalTransactions++;
                        $importedTransactions++;
                        break;
                    }
                }
            }
            fclose($handle);
        }

        return [
            'total_transactions' => $totalTransactions,
            'imported_transactions' => $importedTransactions,
            'failed_transactions' => $failedTransactions,
            'rows' => $rows,
            'headers' => $rows[0]['data'] ?? [],
        ];
    }

    /**
     * Get file content as HTML table for viewing.
     */
    public function getAsHtmlTable(BankStatementImport $import): string
    {
        $fullPath = Storage::disk('public')->path($import->file_path);

        switch ($import->file_format) {
            case BankStatementImport::FORMAT_EXCEL:
                return $this->excelToHtml($fullPath);
            case BankStatementImport::FORMAT_CSV:
                return $this->csvToHtml($fullPath);
            default:
                return '<div class="alert alert-info">This file format cannot be displayed as a table. Please use the document viewer.</div>';
        }
    }

    /**
     * Convert Excel to interactive HTML table.
     */
    protected function excelToHtml(string $filePath): string
    {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            $html = '<table class="table table-bordered table-hover statement-table" id="statementTable">';
            $html .= '<thead class="bg-light"><tr>';

            $isFirstRow = true;
            $columnCount = 0;

            foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                if ($isFirstRow) {
                    // Header row
                    foreach ($cellIterator as $cell) {
                        $value = $cell->getValue();
                        $html .= '<th>' . htmlspecialchars($value ?? '') . '</th>';
                        $columnCount++;
                    }
                    $html .= '<th width="80">Actions</th></tr></thead><tbody>';
                    $isFirstRow = false;
                } else {
                    // Data rows - make them selectable
                    $cellIterator->rewind();
                    $rowData = [];
                    foreach ($cellIterator as $cell) {
                        $rowData[] = $cell->getValue();
                    }

                    // Skip empty rows
                    if (empty(array_filter($rowData))) {
                        continue;
                    }

                    $dataJson = htmlspecialchars(json_encode($rowData), ENT_QUOTES, 'UTF-8');
                    $html .= '<tr class="statement-row selectable-row" data-row="' . $rowIndex . '" data-values="' . $dataJson . '">';

                    foreach ($rowData as $value) {
                        $formattedValue = $this->formatCellValue($value);
                        $html .= '<td>' . htmlspecialchars($formattedValue) . '</td>';
                    }

                    $html .= '<td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-primary select-row-btn" title="Select for matching">
                            <i class="fas fa-check"></i>
                        </button>
                    </td></tr>';
                }
            }

            $html .= '</tbody></table>';
            return $html;
        } catch (\Exception $e) {
            Log::error('Excel to HTML conversion failed', ['error' => $e->getMessage()]);
            return '<div class="alert alert-danger">Failed to render Excel file: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    /**
     * Convert CSV to interactive HTML table.
     */
    protected function csvToHtml(string $filePath): string
    {
        try {
            $html = '<table class="table table-bordered table-hover statement-table" id="statementTable">';
            $html .= '<thead class="bg-light"><tr>';

            $isFirstRow = true;
            $rowIndex = 0;

            if (($handle = fopen($filePath, 'r')) !== false) {
                while (($data = fgetcsv($handle, 0, ',')) !== false) {
                    $rowIndex++;

                    // Skip empty rows
                    if (empty(array_filter($data))) {
                        continue;
                    }

                    if ($isFirstRow) {
                        // Header row
                        foreach ($data as $header) {
                            $html .= '<th>' . htmlspecialchars($header ?? '') . '</th>';
                        }
                        $html .= '<th width="80">Actions</th></tr></thead><tbody>';
                        $isFirstRow = false;
                    } else {
                        // Data rows
                        $dataJson = htmlspecialchars(json_encode($data), ENT_QUOTES, 'UTF-8');
                        $html .= '<tr class="statement-row selectable-row" data-row="' . $rowIndex . '" data-values="' . $dataJson . '">';

                        foreach ($data as $value) {
                            $formattedValue = $this->formatCellValue($value);
                            $html .= '<td>' . htmlspecialchars($formattedValue) . '</td>';
                        }

                        $html .= '<td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary select-row-btn" title="Select for matching">
                                <i class="fas fa-check"></i>
                            </button>
                        </td></tr>';
                    }
                }
                fclose($handle);
            }

            $html .= '</tbody></table>';
            return $html;
        } catch (\Exception $e) {
            Log::error('CSV to HTML conversion failed', ['error' => $e->getMessage()]);
            return '<div class="alert alert-danger">Failed to render CSV file: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }

    /**
     * Format cell value for display.
     */
    protected function formatCellValue($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Format numbers with thousand separators
        if (is_numeric($value)) {
            $floatValue = (float) $value;
            if (floor($floatValue) == $floatValue) {
                return number_format($floatValue, 0, '.', ',');
            }
            return number_format($floatValue, 2, '.', ',');
        }

        return (string) $value;
    }

    /**
     * Get URL for viewing PDF/Image/Word files.
     */
    public function getViewerUrl(BankStatementImport $import): ?string
    {
        if (!$import->file_path) {
            return null;
        }

        // Build URL from request to ensure correct host/port
        // This handles cases like localhost:8000 properly
        $baseUrl = request()->getSchemeAndHttpHost();
        return $baseUrl . '/storage/' . $import->file_path;
    }

    /**
     * Get viewer type based on format.
     */
    public function getViewerType(BankStatementImport $import): string
    {
        switch ($import->file_format) {
            case BankStatementImport::FORMAT_PDF:
                return 'pdf';
            case BankStatementImport::FORMAT_IMAGE:
                return 'image';
            case BankStatementImport::FORMAT_WORD:
                return 'iframe'; // Office Online or Google Docs viewer
            case BankStatementImport::FORMAT_EXCEL:
            case BankStatementImport::FORMAT_CSV:
                return 'table';
            default:
                return 'download';
        }
    }

    /**
     * Delete statement file and record.
     */
    public function deleteImport(BankStatementImport $import): bool
    {
        try {
            // Delete file from storage
            if ($import->file_path) {
                Storage::disk('public')->delete($import->file_path);
            }

            // Delete record
            return $import->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete statement import', [
                'import_id' => $import->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Extract transactions from parsed data (for auto-matching).
     */
    public function extractTransactions(BankStatementImport $import, array $columnMapping = []): array
    {
        $fullPath = Storage::disk('public')->path($import->file_path);
        $transactions = [];

        switch ($import->file_format) {
            case BankStatementImport::FORMAT_EXCEL:
                $data = $this->parseExcel($fullPath);
                break;
            case BankStatementImport::FORMAT_CSV:
                $data = $this->parseCSV($fullPath);
                break;
            default:
                return [];
        }

        // Default column mapping if not provided
        if (empty($columnMapping)) {
            $columnMapping = [
                'date' => 0,
                'description' => 1,
                'debit' => 2,
                'credit' => 3,
                'balance' => 4,
            ];
        }

        // Skip header row
        $rows = array_slice($data['rows'], 1);

        foreach ($rows as $row) {
            $rowData = $row['data'];

            $transaction = [
                'row_number' => $row['row_number'],
                'date' => $rowData[$columnMapping['date']] ?? null,
                'description' => $rowData[$columnMapping['description']] ?? '',
                'debit' => $this->parseAmount($rowData[$columnMapping['debit']] ?? 0),
                'credit' => $this->parseAmount($rowData[$columnMapping['credit']] ?? 0),
                'balance' => $this->parseAmount($rowData[$columnMapping['balance']] ?? null),
                'raw_data' => $rowData,
            ];

            // Calculate net amount (credit - debit from bank's perspective)
            $transaction['amount'] = $transaction['credit'] - $transaction['debit'];

            if ($transaction['date'] && ($transaction['debit'] || $transaction['credit'])) {
                $transactions[] = $transaction;
            }
        }

        return $transactions;
    }

    /**
     * Parse amount string to float.
     */
    protected function parseAmount($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        // Remove currency symbols, spaces, and thousand separators
        $cleaned = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $value));

        return (float) $cleaned;
    }
}
