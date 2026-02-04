<?php

namespace App\Services\Accounting;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel Export Service for Accounting Reports
 *
 * Uses PhpSpreadsheet to generate Excel files with hospital branding.
 */
class ExcelExportService
{
    protected Spreadsheet $spreadsheet;
    protected $activeSheet;
    protected string $hospitalName;
    protected string $hospitalColor;
    protected string $hospitalAddress;
    protected string $hospitalPhone;
    protected string $hospitalEmail;
    protected int $currentRow = 1;

    public function __construct()
    {
        $sett = appsettings();
        $this->hospitalName = $sett->site_name ?? config('app.name', 'CoreHealth Hospital');
        $this->hospitalColor = $sett->hos_color ?? '#007bff';
        $this->hospitalAddress = $sett->contact_address ?? '';
        $this->hospitalPhone = $sett->contact_phones ?? '';
        $this->hospitalEmail = $sett->contact_emails ?? '';
    }

    /**
     * Initialize a new spreadsheet with hospital branding header.
     */
    protected function initSpreadsheet(string $reportTitle, ?string $subtitle = null): void
    {
        $this->spreadsheet = new Spreadsheet();
        $this->activeSheet = $this->spreadsheet->getActiveSheet();
        $this->currentRow = 1;

        // Hospital Name
        $this->activeSheet->setCellValue('A1', $this->hospitalName);
        $this->activeSheet->mergeCells('A1:H1');
        $this->activeSheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => ltrim($this->hospitalColor, '#')]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Hospital Address & Contact
        $contactInfo = [];
        if ($this->hospitalAddress) {
            $contactInfo[] = $this->hospitalAddress;
        }
        if ($this->hospitalPhone) {
            $contactInfo[] = 'Tel: ' . $this->hospitalPhone;
        }
        if ($this->hospitalEmail) {
            $contactInfo[] = 'Email: ' . $this->hospitalEmail;
        }

        if (!empty($contactInfo)) {
            $this->activeSheet->setCellValue('A2', implode(' | ', $contactInfo));
            $this->activeSheet->mergeCells('A2:H2');
            $this->activeSheet->getStyle('A2')->applyFromArray([
                'font' => ['size' => 10, 'color' => ['rgb' => '666666']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $this->currentRow = 3;
        } else {
            $this->currentRow = 2;
        }

        // Separator line
        $this->activeSheet->setCellValue('A' . $this->currentRow, '');
        $this->activeSheet->mergeCells('A' . $this->currentRow . ':H' . $this->currentRow);
        $this->activeSheet->getStyle('A' . $this->currentRow)->applyFromArray([
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => ltrim($this->hospitalColor, '#')]]],
        ]);
        $this->currentRow++;

        // Report Title
        $this->activeSheet->setCellValue('A' . $this->currentRow, $reportTitle);
        $this->activeSheet->mergeCells('A' . $this->currentRow . ':H' . $this->currentRow);
        $this->activeSheet->getStyle('A' . $this->currentRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $this->currentRow++;

        // Subtitle (date range, etc.)
        if ($subtitle) {
            $this->activeSheet->setCellValue('A' . $this->currentRow, $subtitle);
            $this->activeSheet->mergeCells('A' . $this->currentRow . ':H' . $this->currentRow);
            $this->activeSheet->getStyle('A' . $this->currentRow)->applyFromArray([
                'font' => ['italic' => true, 'size' => 11, 'color' => ['rgb' => '666666']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);
            $this->currentRow++;
        }

        // Empty row
        $this->currentRow++;
    }

    /**
     * Add a header row with styling.
     */
    protected function addHeaderRow(array $headers, int $startColumn = 1): void
    {
        $col = $startColumn;
        foreach ($headers as $header) {
            $this->activeSheet->setCellValueByColumnAndRow($col, $this->currentRow, $header);
            $col++;
        }

        $endCol = $startColumn + count($headers) - 1;
        $range = $this->getColumnLetter($startColumn) . $this->currentRow . ':' . $this->getColumnLetter($endCol) . $this->currentRow;

        $this->activeSheet->getStyle($range)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => ltrim($this->hospitalColor, '#')]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        $this->currentRow++;
    }

    /**
     * Add a data row.
     */
    protected function addDataRow(array $data, int $startColumn = 1, bool $isTotal = false): void
    {
        $col = $startColumn;
        foreach ($data as $value) {
            $this->activeSheet->setCellValueByColumnAndRow($col, $this->currentRow, $value);
            $col++;
        }

        $endCol = $startColumn + count($data) - 1;
        $range = $this->getColumnLetter($startColumn) . $this->currentRow . ':' . $this->getColumnLetter($endCol) . $this->currentRow;

        $style = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
        ];

        if ($isTotal) {
            $style['font'] = ['bold' => true];
            $style['fill'] = ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F5F5']];
        }

        $this->activeSheet->getStyle($range)->applyFromArray($style);
        $this->currentRow++;
    }

    /**
     * Auto-size columns.
     */
    protected function autoSizeColumns(int $startCol, int $endCol): void
    {
        for ($i = $startCol; $i <= $endCol; $i++) {
            $this->activeSheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
    }

    /**
     * Get column letter from number.
     */
    protected function getColumnLetter(int $col): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
    }

    /**
     * Download the spreadsheet.
     */
    protected function download(string $filename): StreamedResponse
    {
        // Add footer with generation info
        $this->currentRow += 2;
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'Generated: ' . now()->format('F d, Y H:i:s'));
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('999999');

        $writer = new Xlsx($this->spreadsheet);

        return new StreamedResponse(
            function () use ($writer) {
                $writer->save('php://output');
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '.xlsx"',
                'Cache-Control' => 'max-age=0',
            ]
        );
    }

    /**
     * Export Trial Balance to Excel.
     */
    public function trialBalance(array $report, Carbon $asOfDate): StreamedResponse
    {
        $this->initSpreadsheet('Trial Balance', 'As of ' . $asOfDate->format('F d, Y'));

        $this->addHeaderRow(['Account Code', 'Account Name', 'Debit', 'Credit']);

        foreach ($report['accounts'] ?? [] as $account) {
            $this->addDataRow([
                $account['account_code'] ?? $account['code'] ?? '',
                $account['account_name'] ?? $account['name'] ?? '',
                ($account['debit'] ?? 0) > 0 ? number_format($account['debit'], 2) : '-',
                ($account['credit'] ?? 0) > 0 ? number_format($account['credit'], 2) : '-',
            ]);
        }

        $this->addDataRow([
            '',
            'TOTAL',
            number_format($report['total_debit'] ?? $report['total_debits'] ?? 0, 2),
            number_format($report['total_credit'] ?? $report['total_credits'] ?? 0, 2),
        ], 1, true);

        $this->autoSizeColumns(1, 4);

        return $this->download('trial-balance-' . $asOfDate->format('Y-m-d'));
    }

    /**
     * Export Profit & Loss to Excel.
     */
    public function profitAndLoss(array $report, Carbon $startDate, Carbon $endDate): StreamedResponse
    {
        $this->initSpreadsheet('Profit & Loss Statement', $startDate->format('F d, Y') . ' to ' . $endDate->format('F d, Y'));

        // Income Section
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'INCOME');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
        $this->currentRow++;

        $this->addHeaderRow(['Account Code', 'Account Name', 'Amount']);

        $totalIncome = 0;
        foreach ($report['income']['groups'] ?? [] as $groupName => $group) {
            if (isset($group['accounts'])) {
                foreach ($group['accounts'] as $account) {
                    $this->addDataRow([
                        $account['account_code'] ?? '',
                        $account['account_name'] ?? '',
                        number_format($account['balance'] ?? 0, 2),
                    ]);
                    $totalIncome += ($account['balance'] ?? 0);
                }
            }
        }
        $this->addDataRow(['', 'Total Income', number_format($report['income']['total'] ?? $totalIncome, 2)], 1, true);

        $this->currentRow++;

        // Expenses Section
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'EXPENSES');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
        $this->currentRow++;

        $this->addHeaderRow(['Account Code', 'Account Name', 'Amount']);

        $totalExpenses = 0;
        foreach ($report['expenses']['groups'] ?? [] as $groupName => $group) {
            if (isset($group['accounts'])) {
                foreach ($group['accounts'] as $account) {
                    $this->addDataRow([
                        $account['account_code'] ?? '',
                        $account['account_name'] ?? '',
                        number_format($account['balance'] ?? 0, 2),
                    ]);
                    $totalExpenses += ($account['balance'] ?? 0);
                }
            }
        }
        $this->addDataRow(['', 'Total Expenses', number_format($report['expenses']['total'] ?? $totalExpenses, 2)], 1, true);

        $this->currentRow++;

        // Net Income
        $netIncome = $report['net_income'] ?? (($report['income']['total'] ?? $totalIncome) - ($report['expenses']['total'] ?? $totalExpenses));
        $this->addDataRow(['', 'NET INCOME', number_format($netIncome, 2)], 1, true);

        $this->autoSizeColumns(1, 3);

        return $this->download('profit-loss-' . $startDate->format('Y-m-d') . '-to-' . $endDate->format('Y-m-d'));
    }

    /**
     * Export Balance Sheet to Excel.
     */
    public function balanceSheet(array $report, Carbon $asOfDate): StreamedResponse
    {
        $this->initSpreadsheet('Balance Sheet', 'As of ' . $asOfDate->format('F d, Y'));

        // Assets Section
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'ASSETS');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
        $this->currentRow++;

        $this->addHeaderRow(['Account Name', 'Amount']);

        $totalAssets = 0;
        foreach (['current_assets', 'fixed_assets', 'other_assets'] as $assetType) {
            foreach ($report[$assetType] ?? $report['assets'][$assetType] ?? [] as $item) {
                $this->addDataRow([
                    $item['account_name'] ?? $item['name'] ?? '',
                    number_format($item['balance'] ?? 0, 2),
                ]);
                $totalAssets += ($item['balance'] ?? 0);
            }
        }
        $this->addDataRow(['Total Assets', number_format($report['total_assets'] ?? $totalAssets, 2)], 1, true);

        $this->currentRow++;

        // Liabilities Section
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'LIABILITIES');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
        $this->currentRow++;

        $this->addHeaderRow(['Account Name', 'Amount']);

        $totalLiabilities = 0;
        foreach (['current_liabilities', 'long_term_liabilities'] as $liabType) {
            foreach ($report[$liabType] ?? $report['liabilities'][$liabType] ?? [] as $item) {
                $this->addDataRow([
                    $item['account_name'] ?? $item['name'] ?? '',
                    number_format($item['balance'] ?? 0, 2),
                ]);
                $totalLiabilities += ($item['balance'] ?? 0);
            }
        }
        $this->addDataRow(['Total Liabilities', number_format($report['total_liabilities'] ?? $totalLiabilities, 2)], 1, true);

        $this->currentRow++;

        // Equity Section
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'EQUITY');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
        $this->currentRow++;

        $this->addHeaderRow(['Account Name', 'Amount']);

        $totalEquity = 0;
        foreach ($report['equity'] ?? $report['equity']['accounts'] ?? [] as $item) {
            $this->addDataRow([
                $item['account_name'] ?? $item['name'] ?? '',
                number_format($item['balance'] ?? 0, 2),
            ]);
            $totalEquity += ($item['balance'] ?? 0);
        }
        $this->addDataRow(['Total Equity', number_format($report['total_equity'] ?? $totalEquity, 2)], 1, true);

        $this->autoSizeColumns(1, 2);

        return $this->download('balance-sheet-' . $asOfDate->format('Y-m-d'));
    }

    /**
     * Export Cash Flow Statement to Excel.
     */
    public function cashFlow(array $report, Carbon $startDate, Carbon $endDate): StreamedResponse
    {
        $this->initSpreadsheet('Cash Flow Statement', $startDate->format('F d, Y') . ' to ' . $endDate->format('F d, Y'));

        // Operating Activities
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'OPERATING ACTIVITIES');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
        $this->currentRow++;

        $this->addHeaderRow(['Description', 'Amount']);

        foreach ($report['operating_activities'] ?? [] as $item) {
            $this->addDataRow([
                $item['name'] ?? $item['description'] ?? 'Operating Activity',
                number_format($item['amount'] ?? 0, 2),
            ]);
        }
        $this->addDataRow(['Net Cash from Operating', number_format($report['net_operating'] ?? 0, 2)], 1, true);

        $this->currentRow++;

        // Investing Activities
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'INVESTING ACTIVITIES');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
        $this->currentRow++;

        $this->addHeaderRow(['Description', 'Amount']);

        foreach ($report['investing_activities'] ?? [] as $item) {
            $this->addDataRow([
                $item['name'] ?? $item['description'] ?? 'Investing Activity',
                number_format($item['amount'] ?? 0, 2),
            ]);
        }
        $this->addDataRow(['Net Cash from Investing', number_format($report['net_investing'] ?? 0, 2)], 1, true);

        $this->currentRow++;

        // Financing Activities
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'FINANCING ACTIVITIES');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
        $this->currentRow++;

        $this->addHeaderRow(['Description', 'Amount']);

        foreach ($report['financing_activities'] ?? [] as $item) {
            $this->addDataRow([
                $item['name'] ?? $item['description'] ?? 'Financing Activity',
                number_format($item['amount'] ?? 0, 2),
            ]);
        }
        $this->addDataRow(['Net Cash from Financing', number_format($report['net_financing'] ?? 0, 2)], 1, true);

        $this->currentRow += 2;

        // Summary
        $this->addDataRow(['Net Change in Cash', number_format($report['net_change_in_cash'] ?? 0, 2)], 1, true);
        $this->addDataRow(['Beginning Cash', number_format($report['beginning_cash'] ?? 0, 2)]);
        $this->addDataRow(['Ending Cash', number_format($report['ending_cash'] ?? 0, 2)], 1, true);

        $this->autoSizeColumns(1, 2);

        return $this->download('cash-flow-' . $startDate->format('Y-m-d') . '-to-' . $endDate->format('Y-m-d'));
    }

    /**
     * Export General Ledger to Excel.
     */
    public function generalLedger(array $ledgerData, Carbon $startDate, Carbon $endDate): StreamedResponse
    {
        $this->initSpreadsheet('General Ledger', $startDate->format('F d, Y') . ' to ' . $endDate->format('F d, Y'));

        foreach ($ledgerData as $accountData) {
            $account = $accountData['account'];
            $this->activeSheet->setCellValue('A' . $this->currentRow, ($account->code ?? $account->account_code ?? '') . ' - ' . ($account->name ?? $account->account_name ?? ''));
            $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
            $this->currentRow++;

            $this->activeSheet->setCellValue('A' . $this->currentRow, 'Opening Balance: ' . number_format($accountData['opening_balance'] ?? 0, 2));
            $this->currentRow++;

            $this->addHeaderRow(['Date', 'Entry #', 'Description', 'Reference', 'Debit', 'Credit', 'Balance']);

            $runningBalance = $accountData['opening_balance'] ?? 0;
            foreach ($accountData['transactions'] ?? [] as $transaction) {
                $runningBalance += ($transaction['debit'] ?? 0) - ($transaction['credit'] ?? 0);
                $this->addDataRow([
                    Carbon::parse($transaction['date'])->format('M d, Y'),
                    $transaction['entry_number'] ?? '-',
                    $transaction['description'] ?? '',
                    $transaction['reference'] ?? '-',
                    ($transaction['debit'] ?? 0) > 0 ? number_format($transaction['debit'], 2) : '-',
                    ($transaction['credit'] ?? 0) > 0 ? number_format($transaction['credit'], 2) : '-',
                    number_format($runningBalance, 2),
                ]);
            }

            $this->addDataRow([
                '', '', '', 'Closing Balance',
                number_format($accountData['total_debit'] ?? 0, 2),
                number_format($accountData['total_credit'] ?? 0, 2),
                number_format($accountData['closing_balance'] ?? 0, 2),
            ], 1, true);

            $this->currentRow += 2;
        }

        $this->autoSizeColumns(1, 7);

        return $this->download('general-ledger-' . $startDate->format('Y-m-d') . '-to-' . $endDate->format('Y-m-d'));
    }

    /**
     * Export Aged Receivables to Excel.
     */
    public function agedReceivables(array $report, Carbon $asOfDate): StreamedResponse
    {
        $this->initSpreadsheet('Aged Receivables Report', 'As of ' . $asOfDate->format('F d, Y'));

        // Export by categories if present (new structure)
        if (isset($report['categories'])) {
            foreach ($report['categories'] as $catKey => $category) {
                if (empty($category['details'])) continue;

                // Category header
                $this->activeSheet->setCellValue('A' . $this->currentRow, strtoupper($category['label'] ?? ucwords(str_replace('_', ' ', $catKey))));
                $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
                $this->currentRow++;

                // Add appropriate headers based on category
                if ($catKey === 'patient_overdrafts') {
                    $this->addHeaderRow(['File No', 'Patient Name', 'Phone', 'HMO', 'Amount', 'Last Activity', 'Aging']);
                    foreach ($category['details'] as $item) {
                        $this->addDataRow([
                            $item['patient_file_no'] ?? '',
                            $item['patient_name'] ?? 'Unknown',
                            $item['patient_phone'] ?? '',
                            $item['hmo_name'] ?? 'Self-Pay',
                            number_format($item['amount'] ?? 0, 2),
                            $item['last_activity'] ?? '',
                            ucwords(str_replace('_', ' ', $item['aging_bucket'] ?? 'current')),
                        ]);
                    }
                } elseif ($catKey === 'hmo_claims') {
                    $this->addHeaderRow(['HMO Name', 'Claims Count', 'Total Amount', 'Aging']);
                    foreach ($category['details'] as $item) {
                        $this->addDataRow([
                            $item['hmo_name'] ?? 'Unknown HMO',
                            $item['claims_count'] ?? 0,
                            number_format($item['total_amount'] ?? 0, 2),
                            ucwords(str_replace('_', ' ', $item['aging_bucket'] ?? 'current')),
                        ]);
                    }
                } elseif ($catKey === 'gl_receivables') {
                    $this->addHeaderRow(['Account Code', 'Account Name', 'Balance', 'Aging']);
                    foreach ($category['details'] as $item) {
                        $this->addDataRow([
                            $item['account_code'] ?? '',
                            $item['account_name'] ?? '',
                            number_format($item['balance'] ?? 0, 2),
                            ucwords(str_replace('_', ' ', $item['aging_bucket'] ?? 'current')),
                        ]);
                    }
                } else {
                    // Generic fallback
                    $this->addHeaderRow(['Reference', 'Description', 'Amount', 'Aging']);
                    foreach ($category['details'] as $item) {
                        $this->addDataRow([
                            $item['id'] ?? $item['reference'] ?? '',
                            $item['name'] ?? $item['description'] ?? '',
                            number_format($item['amount'] ?? $item['balance'] ?? 0, 2),
                            ucwords(str_replace('_', ' ', $item['aging_bucket'] ?? 'current')),
                        ]);
                    }
                }

                // Category subtotal
                $this->addDataRow([
                    '', 'Subtotal', '', '',
                    number_format($category['total'] ?? 0, 2),
                ], 1, true);

                $this->currentRow++;
            }

            // Grand total
            $this->addDataRow([
                '', 'GRAND TOTAL', '', '',
                number_format($report['total'] ?? 0, 2),
            ], 1, true);

            $this->autoSizeColumns(1, 7);
        } else {
            // Legacy format fallback
            $this->addHeaderRow(['Account Code', 'Account Name', 'Current', '1-30 Days', '31-60 Days', '61-90 Days', 'Over 90', 'Total']);

            foreach ($report['details'] ?? [] as $item) {
                $this->addDataRow([
                    $item['patient_file_no'] ?? $item['account_code'] ?? $item['reference'] ?? '',
                    $item['patient_name'] ?? $item['account_name'] ?? $item['hmo_name'] ?? $item['name'] ?? '',
                    number_format($item['current'] ?? 0, 2),
                    number_format($item['1_30'] ?? 0, 2),
                    number_format($item['31_60'] ?? 0, 2),
                    number_format($item['61_90'] ?? 0, 2),
                    number_format($item['over_90'] ?? 0, 2),
                    number_format($item['amount'] ?? $item['balance'] ?? 0, 2),
                ]);
            }

            $this->addDataRow([
                '', 'TOTAL',
                number_format($report['totals']['current'] ?? 0, 2),
                number_format($report['totals']['1_30'] ?? 0, 2),
                number_format($report['totals']['31_60'] ?? 0, 2),
                number_format($report['totals']['61_90'] ?? 0, 2),
                number_format($report['totals']['over_90'] ?? 0, 2),
                number_format($report['total'] ?? 0, 2),
            ], 1, true);

            $this->autoSizeColumns(1, 8);
        }

        return $this->download('aged-receivables-' . $asOfDate->format('Y-m-d'));
    }

    /**
     * Export Aged Payables to Excel.
     */
    public function agedPayables(array $report, Carbon $asOfDate): StreamedResponse
    {
        $this->initSpreadsheet('Aged Payables Report', 'As of ' . $asOfDate->format('F d, Y'));

        // Export by categories if present (new structure)
        if (isset($report['categories'])) {
            foreach ($report['categories'] as $catKey => $category) {
                if (empty($category['details'])) continue;

                // Category header
                $this->activeSheet->setCellValue('A' . $this->currentRow, strtoupper($category['label'] ?? ucwords(str_replace('_', ' ', $catKey))));
                $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
                $this->currentRow++;

                // Add appropriate headers based on category
                if ($catKey === 'supplier_payables') {
                    $this->addHeaderRow(['Supplier Name', 'Contact Person', 'Phone', 'PO Count', 'Outstanding Amount', 'Aging']);
                    foreach ($category['details'] as $item) {
                        $this->addDataRow([
                            $item['supplier_name'] ?? 'Unknown',
                            $item['contact_person'] ?? '',
                            $item['phone'] ?? '',
                            $item['po_count'] ?? 0,
                            number_format($item['outstanding_amount'] ?? 0, 2),
                            ucwords(str_replace('_', ' ', $item['aging_bucket'] ?? 'current')),
                        ]);
                    }
                } elseif ($catKey === 'patient_deposits') {
                    $this->addHeaderRow(['File No', 'Patient Name', 'Phone', 'HMO', 'Deposit Amount', 'Last Activity', 'Aging']);
                    foreach ($category['details'] as $item) {
                        $this->addDataRow([
                            $item['patient_file_no'] ?? '',
                            $item['patient_name'] ?? 'Unknown',
                            $item['patient_phone'] ?? '',
                            $item['hmo_name'] ?? 'Self-Pay',
                            number_format($item['amount'] ?? 0, 2),
                            $item['last_activity'] ?? '',
                            ucwords(str_replace('_', ' ', $item['aging_bucket'] ?? 'current')),
                        ]);
                    }
                } elseif ($catKey === 'supplier_credits') {
                    $this->addHeaderRow(['Supplier Name', 'Contact Person', 'Phone', 'Credit Amount', 'Aging']);
                    foreach ($category['details'] as $item) {
                        $this->addDataRow([
                            $item['supplier_name'] ?? 'Unknown',
                            $item['contact_person'] ?? '',
                            $item['phone'] ?? '',
                            number_format($item['credit_amount'] ?? $item['amount'] ?? 0, 2),
                            ucwords(str_replace('_', ' ', $item['aging_bucket'] ?? 'current')),
                        ]);
                    }
                } elseif ($catKey === 'gl_payables') {
                    $this->addHeaderRow(['Account Code', 'Account Name', 'Balance', 'Aging']);
                    foreach ($category['details'] as $item) {
                        $this->addDataRow([
                            $item['account_code'] ?? '',
                            $item['account_name'] ?? '',
                            number_format($item['balance'] ?? 0, 2),
                            ucwords(str_replace('_', ' ', $item['aging_bucket'] ?? 'current')),
                        ]);
                    }
                } else {
                    // Generic fallback
                    $this->addHeaderRow(['Reference', 'Description', 'Amount', 'Aging']);
                    foreach ($category['details'] as $item) {
                        $this->addDataRow([
                            $item['id'] ?? $item['reference'] ?? '',
                            $item['name'] ?? $item['description'] ?? '',
                            number_format($item['amount'] ?? $item['balance'] ?? $item['outstanding_amount'] ?? 0, 2),
                            ucwords(str_replace('_', ' ', $item['aging_bucket'] ?? 'current')),
                        ]);
                    }
                }

                // Category subtotal
                $this->addDataRow([
                    '', 'Subtotal', '', '',
                    number_format($category['total'] ?? 0, 2),
                ], 1, true);

                $this->currentRow++;
            }

            // Grand total
            $this->addDataRow([
                '', 'GRAND TOTAL', '', '',
                number_format($report['total'] ?? 0, 2),
            ], 1, true);

            $this->autoSizeColumns(1, 7);
        } else {
            // Legacy format fallback
            $this->addHeaderRow(['Code/File No', 'Name', 'Current', '1-30 Days', '31-60 Days', '61-90 Days', 'Over 90', 'Total']);

            foreach ($report['details'] ?? [] as $item) {
                $this->addDataRow([
                    $item['patient_file_no'] ?? $item['supplier_id'] ?? $item['account_code'] ?? '',
                    $item['patient_name'] ?? $item['supplier_name'] ?? $item['account_name'] ?? '',
                    number_format($item['current'] ?? 0, 2),
                    number_format($item['1_30'] ?? 0, 2),
                    number_format($item['31_60'] ?? 0, 2),
                    number_format($item['61_90'] ?? 0, 2),
                    number_format($item['over_90'] ?? 0, 2),
                    number_format($item['amount'] ?? $item['outstanding_amount'] ?? $item['balance'] ?? 0, 2),
                ]);
            }

            $this->addDataRow([
                '', 'TOTAL',
                number_format($report['totals']['current'] ?? 0, 2),
                number_format($report['totals']['1_30'] ?? 0, 2),
                number_format($report['totals']['31_60'] ?? 0, 2),
                number_format($report['totals']['61_90'] ?? 0, 2),
                number_format($report['totals']['over_90'] ?? 0, 2),
                number_format($report['total'] ?? 0, 2),
            ], 1, true);

            $this->autoSizeColumns(1, 8);
        }

        return $this->download('aged-payables-' . $asOfDate->format('Y-m-d'));
    }

    /**
     * Export Daily Audit to Excel.
     */
    public function dailyAudit($entries, array $stats, Carbon $date): StreamedResponse
    {
        $this->initSpreadsheet('Daily Audit Report', $date->format('l, F d, Y'));

        // Summary Statistics
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'SUMMARY');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
        $this->currentRow++;

        $this->addDataRow(['Total Entries', $stats['total_entries'] ?? 0]);
        $this->addDataRow(['Posted Entries', $stats['posted_entries'] ?? 0]);
        $this->addDataRow(['Pending Entries', $stats['pending_entries'] ?? 0]);
        $this->addDataRow(['Total Debits', number_format($stats['total_debits'] ?? 0, 2)]);
        $this->addDataRow(['Total Credits', number_format($stats['total_credits'] ?? 0, 2)]);

        $this->currentRow += 2;

        // Journal Entries
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'JOURNAL ENTRIES');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
        $this->currentRow++;

        $this->addHeaderRow(['Entry #', 'Status', 'Type', 'Description', 'Debit', 'Credit', 'Created By']);

        foreach ($entries as $entry) {
            $this->addDataRow([
                $entry->entry_number,
                strtoupper($entry->status),
                ucfirst($entry->entry_type ?? '-'),
                $entry->description ?? '',
                number_format($entry->lines->sum('debit'), 2),
                number_format($entry->lines->sum('credit'), 2),
                $entry->createdBy->name ?? 'System',
            ]);
        }

        $this->autoSizeColumns(1, 7);

        return $this->download('daily-audit-' . $date->format('Y-m-d'));
    }

    /**
     * Export Bank Statement to Excel.
     */
    public function bankStatement(array $exportData, Carbon $startDate, Carbon $endDate): StreamedResponse
    {
        $account = is_array($exportData['account']) ? (object) $exportData['account'] : $exportData['account'];
        $accountCode = $account->code ?? $account->account_code ?? '';
        $accountName = $account->name ?? $account->account_name ?? '';
        $accountGroup = is_object($account) && isset($account->accountGroup) ? $account->accountGroup->name : ($account->account_group_name ?? 'Bank Account');

        // Get bank information
        $bank = null;
        if (is_object($account) && isset($account->bank)) {
            $bank = $account->bank;
        } elseif (isset($exportData['bank'])) {
            $bank = is_array($exportData['bank']) ? (object) $exportData['bank'] : $exportData['bank'];
        }

        $this->initSpreadsheet(
            'Bank Statement Report',
            $accountCode . ' - ' . $accountName . ' | ' . $startDate->format('M d, Y') . ' to ' . $endDate->format('M d, Y')
        );

        // Bank Information Section
        if ($bank) {
            $this->activeSheet->setCellValue('A' . $this->currentRow, 'BANK INFORMATION');
            $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
            $this->currentRow++;

            $this->addDataRow(['Bank Name:', $bank->name ?? '']);
            if (isset($bank->account_number)) {
                $this->addDataRow(['Bank Account Number:', $bank->account_number]);
            }
            if (isset($bank->account_name)) {
                $this->addDataRow(['Bank Account Name:', $bank->account_name]);
            }
            if (isset($bank->bank_code)) {
                $this->addDataRow(['Bank Code:', $bank->bank_code]);
            }

            $this->currentRow += 2;
        }

        // Account Information Section
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'LEDGER ACCOUNT INFORMATION');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
        $this->currentRow++;

        $this->addDataRow(['Ledger Account:', $accountCode . ' - ' . $accountName]);
        $this->addDataRow(['Account Type:', $accountGroup]);
        $this->addDataRow(['Period:', $startDate->format('M d, Y') . ' to ' . $endDate->format('M d, Y')]);
        $this->addDataRow(['Report Date:', now()->format('M d, Y h:i A')]);

        $this->currentRow += 2;

        // Summary Section
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'SUMMARY');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
        $this->currentRow++;

        $this->addDataRow(['Opening Balance', number_format($exportData['opening_balance'], 2)]);
        $this->addDataRow(['Total Deposits', number_format($exportData['total_deposits'], 2)]);
        $this->addDataRow(['Total Withdrawals', number_format($exportData['total_withdrawals'], 2)]);
        $this->addDataRow(['Closing Balance', number_format($exportData['closing_balance'], 2)]);
        $netMovement = $exportData['closing_balance'] - $exportData['opening_balance'];
        $this->addDataRow(['Net Movement', ($netMovement >= 0 ? '+' : '') . number_format($netMovement, 2)]);

        $this->currentRow += 2;

        // Transactions Table
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'TRANSACTIONS');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
        $this->currentRow++;

        $this->addHeaderRow(['Date', 'Entry #', 'Description', 'Reference', 'Deposits', 'Withdrawals', 'Balance']);

        $runningBalance = $exportData['opening_balance'];

        foreach ($exportData['transactions'] as $transaction) {
            $runningBalance += ($transaction['debit'] - $transaction['credit']);
            $isDeposit = $transaction['debit'] > 0;

            $this->addDataRow([
                Carbon::parse($transaction['date'])->format('M d, Y'),
                $transaction['entry_number'] ?? '-',
                $transaction['description'] ?? '',
                $transaction['reference'] ?? '-',
                $isDeposit ? number_format($transaction['debit'], 2) : '-',
                !$isDeposit ? number_format($transaction['credit'], 2) : '-',
                number_format($runningBalance, 2),
            ]);
        }

        // Totals Row
        $this->addDataRow([
            '', '', '', 'TOTALS:',
            number_format($exportData['total_deposits'], 2),
            number_format($exportData['total_withdrawals'], 2),
            number_format($exportData['closing_balance'], 2),
        ], 1, true);

        $this->addDataRow([
            '', '', '', '', '', 'NET MOVEMENT:',
            ($netMovement >= 0 ? '+' : '') . number_format($netMovement, 2),
        ], 1, true);

        $this->autoSizeColumns(1, 7);

        return $this->download('bank-statement-' . $accountCode . '-' . $startDate->format('Y-m-d') . '-to-' . $endDate->format('Y-m-d'));
    }

    /**
     * Export Leases to Excel.
     */
    public function leases($leases, ?string $filterStatus = null): StreamedResponse
    {
        $subtitle = $filterStatus ? 'Status: ' . ucfirst($filterStatus) : 'All Leases';
        $this->initSpreadsheet('Lease Management Report', $subtitle . ' | Generated: ' . now()->format('F d, Y'));

        $this->addHeaderRow([
            'Lease Number', 'Type', 'Leased Item', 'Lessor', 'Department',
            'Monthly Payment', 'ROU Asset', 'Lease Liability',
            'Commencement', 'End Date', 'Status'
        ]);

        $totalMonthly = 0;
        $totalROU = 0;
        $totalLiability = 0;

        foreach ($leases as $lease) {
            $this->addDataRow([
                $lease->lease_number ?? '',
                ucfirst($lease->lease_type ?? ''),
                $lease->leased_item ?? '',
                $lease->lessor_name ?? '',
                $lease->department_name ?? 'N/A',
                number_format($lease->monthly_payment ?? 0, 2),
                number_format($lease->current_rou_asset_value ?? 0, 2),
                number_format($lease->current_lease_liability ?? 0, 2),
                $lease->commencement_date ? Carbon::parse($lease->commencement_date)->format('M d, Y') : '',
                $lease->end_date ? Carbon::parse($lease->end_date)->format('M d, Y') : '',
                ucfirst($lease->status ?? ''),
            ]);

            $totalMonthly += $lease->monthly_payment ?? 0;
            $totalROU += $lease->current_rou_asset_value ?? 0;
            $totalLiability += $lease->current_lease_liability ?? 0;
        }

        $this->addDataRow([
            '', '', '', '', 'TOTALS:',
            number_format($totalMonthly, 2),
            number_format($totalROU, 2),
            number_format($totalLiability, 2),
            '', '', ''
        ], 1, true);

        $this->autoSizeColumns(1, 11);

        return $this->download('leases-' . now()->format('Y-m-d'));
    }

    /**
     * Export Cost Centers to Excel.
     */
    public function costCenters($costCenters, array $stats = []): StreamedResponse
    {
        $this->initSpreadsheet('Cost Centers Report', 'Generated: ' . now()->format('F d, Y'));

        // Summary section
        if (!empty($stats)) {
            $this->activeSheet->setCellValue('A' . $this->currentRow, 'SUMMARY');
            $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
            $this->currentRow++;

            $this->addDataRow(['Total Cost Centers', $stats['total'] ?? count($costCenters)]);
            $this->addDataRow(['Active', $stats['active'] ?? 0]);
            $this->addDataRow(['Total Budget', number_format($stats['total_budget'] ?? 0, 2)]);
            $this->currentRow++;
        }

        $this->addHeaderRow([
            'Code', 'Name', 'Type', 'Department', 'Manager',
            'Budget Amount', 'Status', 'Description'
        ]);

        foreach ($costCenters as $center) {
            $this->addDataRow([
                $center->code ?? '',
                $center->name ?? '',
                ucfirst($center->type ?? ''),
                $center->department->name ?? ($center->department_name ?? 'N/A'),
                $center->manager->name ?? ($center->manager_name ?? 'N/A'),
                number_format($center->budget_amount ?? 0, 2),
                ($center->is_active ?? true) ? 'Active' : 'Inactive',
                $center->description ?? '',
            ]);
        }

        $this->autoSizeColumns(1, 8);

        return $this->download('cost-centers-' . now()->format('Y-m-d'));
    }

    /**
     * Export KPIs to Excel.
     */
    public function kpis($kpiData): StreamedResponse
    {
        $this->initSpreadsheet('Financial KPI Report', 'Generated: ' . now()->format('F d, Y H:i'));

        foreach ($kpiData as $category => $kpis) {
            $this->activeSheet->setCellValue('A' . $this->currentRow, strtoupper($category));
            $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
            $this->currentRow++;

            $this->addHeaderRow(['KPI Code', 'Name', 'Current Value', 'Target', 'Status', 'Last Calculated']);

            foreach ($kpis as $item) {
                $kpi = $item['kpi'];
                $latest = $item['latest'] ?? null;
                $status = $item['status'] ?? 'N/A';

                $this->addDataRow([
                    $kpi->kpi_code ?? '',
                    $kpi->kpi_name ?? '',
                    $latest ? number_format($latest->value, 2) . ' ' . ($kpi->unit ?? '') : 'Not calculated',
                    ($kpi->target_value ?? '-') . ' ' . ($kpi->unit ?? ''),
                    ucfirst($status),
                    $latest ? Carbon::parse($latest->calculation_date)->format('M d, Y H:i') : 'Never',
                ]);
            }

            $this->currentRow++;
        }

        $this->autoSizeColumns(1, 6);

        return $this->download('kpi-report-' . now()->format('Y-m-d'));
    }

    /**
     * Export Liabilities to Excel.
     */
    public function liabilities($liabilities, array $stats = []): StreamedResponse
    {
        $this->initSpreadsheet('Liabilities Register', 'Generated: ' . now()->format('F d, Y'));

        $this->addHeaderRow([
            'Reference', 'Type', 'Creditor', 'Description',
            'Original Amount', 'Interest Rate', 'Outstanding Balance',
            'Start Date', 'Maturity Date', 'Status'
        ]);

        $totalOriginal = 0;
        $totalOutstanding = 0;

        foreach ($liabilities as $liability) {
            $this->addDataRow([
                $liability->reference_number ?? '',
                ucfirst(str_replace('_', ' ', $liability->liability_type ?? '')),
                $liability->creditor_name ?? '',
                $liability->description ?? '',
                number_format($liability->original_amount ?? 0, 2),
                ($liability->interest_rate ?? 0) . '%',
                number_format($liability->outstanding_balance ?? 0, 2),
                $liability->start_date ? Carbon::parse($liability->start_date)->format('M d, Y') : '',
                $liability->maturity_date ? Carbon::parse($liability->maturity_date)->format('M d, Y') : '',
                ucfirst($liability->status ?? ''),
            ]);

            $totalOriginal += $liability->original_amount ?? 0;
            $totalOutstanding += $liability->outstanding_balance ?? 0;
        }

        $this->addDataRow([
            '', '', '', 'TOTALS:',
            number_format($totalOriginal, 2), '',
            number_format($totalOutstanding, 2),
            '', '', ''
        ], 1, true);

        $this->autoSizeColumns(1, 10);

        return $this->download('liabilities-' . now()->format('Y-m-d'));
    }

    /**
     * Export Fixed Assets to Excel.
     */
    public function fixedAssets($assets, array $stats = []): StreamedResponse
    {
        $this->initSpreadsheet('Fixed Assets Register', 'Generated: ' . now()->format('F d, Y'));

        $this->addHeaderRow([
            'Asset Number', 'Name', 'Category', 'Location',
            'Acquisition Date', 'Original Cost', 'Accumulated Depreciation',
            'Net Book Value', 'Useful Life', 'Status'
        ]);

        $totalCost = 0;
        $totalDepreciation = 0;
        $totalNBV = 0;

        foreach ($assets as $asset) {
            $nbv = ($asset->acquisition_cost ?? 0) - ($asset->accumulated_depreciation ?? 0);
            $this->addDataRow([
                $asset->asset_number ?? '',
                $asset->name ?? '',
                $asset->category_name ?? ($asset->category->name ?? 'N/A'),
                $asset->location ?? '',
                $asset->acquisition_date ? Carbon::parse($asset->acquisition_date)->format('M d, Y') : '',
                number_format($asset->acquisition_cost ?? 0, 2),
                number_format($asset->accumulated_depreciation ?? 0, 2),
                number_format($nbv, 2),
                ($asset->useful_life_years ?? 0) . ' years',
                ucfirst($asset->status ?? ''),
            ]);

            $totalCost += $asset->acquisition_cost ?? 0;
            $totalDepreciation += $asset->accumulated_depreciation ?? 0;
            $totalNBV += $nbv;
        }

        $this->addDataRow([
            '', '', '', 'TOTALS:', '',
            number_format($totalCost, 2),
            number_format($totalDepreciation, 2),
            number_format($totalNBV, 2),
            '', ''
        ], 1, true);

        $this->autoSizeColumns(1, 10);

        return $this->download('fixed-assets-' . now()->format('Y-m-d'));
    }

    /**
     * Export Budgets to Excel.
     */
    public function budgets($budgets, ?int $fiscalYear = null): StreamedResponse
    {
        $subtitle = $fiscalYear ? 'Fiscal Year: ' . $fiscalYear : 'All Budgets';
        $this->initSpreadsheet('Budget Report', $subtitle);

        $this->addHeaderRow([
            'Budget Name', 'Fiscal Year', 'Department', 'Category',
            'Budgeted Amount', 'Actual Spent', 'Variance', 'Utilization %', 'Status'
        ]);

        $totalBudgeted = 0;
        $totalActual = 0;

        foreach ($budgets as $budget) {
            $variance = ($budget->total_budgeted ?? 0) - ($budget->total_actual ?? 0);
            $utilization = ($budget->total_budgeted ?? 0) > 0
                ? (($budget->total_actual ?? 0) / $budget->total_budgeted) * 100
                : 0;

            $this->addDataRow([
                $budget->budget_name ?? '',
                $budget->fiscalYear->year_name ?? '',
                $budget->department->name ?? 'Organization-wide',
                $budget->budget_type ?? 'operating',
                number_format($budget->total_budgeted ?? 0, 2),
                number_format($budget->total_actual ?? 0, 2),
                number_format($variance, 2),
                number_format($utilization, 1) . '%',
                ucfirst($budget->status ?? ''),
            ]);

            $totalBudgeted += $budget->total_budgeted ?? 0;
            $totalActual += $budget->total_actual ?? 0;
        }

        $totalVariance = $totalBudgeted - $totalActual;
        $totalUtilization = $totalBudgeted > 0 ? ($totalActual / $totalBudgeted) * 100 : 0;

        $this->addDataRow([
            '', '', '', 'TOTALS:',
            number_format($totalBudgeted, 2),
            number_format($totalActual, 2),
            number_format($totalVariance, 2),
            number_format($totalUtilization, 1) . '%',
            ''
        ], 1, true);

        $this->autoSizeColumns(1, 9);

        return $this->download('budgets-' . ($fiscalYear ?? now()->year));
    }

    /**
     * Export Budget Variance Report to Excel.
     */
    public function varianceReport(array $reportData, array $summary, $fiscalYear = null, $department = null): StreamedResponse
    {
        $subtitle = '';
        if ($fiscalYear) {
            $subtitle = 'Fiscal Year: ' . ($fiscalYear->year_name ?? $fiscalYear);
        }
        if ($department) {
            $subtitle .= ($subtitle ? ' | ' : '') . 'Department: ' . ($department->name ?? $department);
        }
        if (!$subtitle) {
            $subtitle = 'All Approved & Locked Budgets';
        }

        $this->initSpreadsheet('Budget Variance Report', $subtitle);

        // Add summary section
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'SUMMARY');
        $this->activeSheet->getStyle('A' . $this->currentRow)->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8E8E8']],
        ]);
        $this->currentRow++;

        $this->addDataRow([
            'Total Budgeted:',
            number_format($summary['total_budgeted'] ?? 0, 2)
        ]);
        $this->addDataRow([
            'Total Actual:',
            number_format($summary['total_actual'] ?? 0, 2)
        ]);
        $this->addDataRow([
            'Total Variance:',
            number_format($summary['total_variance'] ?? 0, 2)
        ]);
        $this->addDataRow([
            'Variance %:',
            number_format($summary['variance_percent'] ?? 0, 1) . '%'
        ]);

        $this->currentRow += 2;

        // Budget details
        foreach ($reportData as $budget) {
            // Budget header
            $this->activeSheet->setCellValue('A' . $this->currentRow,
                $budget['budget_name'] . ' - ' . $budget['department'] . ' (' . $budget['fiscal_year'] . ')');
            $this->activeSheet->mergeCells('A' . $this->currentRow . ':G' . $this->currentRow);
            $this->activeSheet->getStyle('A' . $this->currentRow)->applyFromArray([
                'font' => ['bold' => true, 'size' => 11],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'D9E1F2']],
            ]);
            $this->currentRow++;

            // Line items header
            $this->addHeaderRow([
                'Account Code', 'Account Name', 'Budgeted', 'Actual',
                'Variance', 'Variance %', 'Utilization %'
            ]);

            // Line items
            foreach ($budget['items'] as $item) {
                $utilization = $item['budgeted'] > 0
                    ? ($item['actual'] / $item['budgeted']) * 100
                    : 0;

                $this->addDataRow([
                    $item['account_code'],
                    $item['account_name'],
                    number_format($item['budgeted'], 2),
                    number_format($item['actual'], 2),
                    number_format($item['variance'], 2),
                    number_format($item['variance_percent'], 1) . '%',
                    number_format($utilization, 1) . '%',
                ]);
            }

            // Budget total
            $utilization = $budget['total_budgeted'] > 0
                ? ($budget['total_actual'] / $budget['total_budgeted']) * 100
                : 0;

            $this->addDataRow([
                '', 'BUDGET TOTAL:',
                number_format($budget['total_budgeted'], 2),
                number_format($budget['total_actual'], 2),
                number_format($budget['total_variance'], 2),
                number_format($budget['variance_percent'], 1) . '%',
                number_format($utilization, 1) . '%',
            ], 1, true);

            $this->currentRow += 2;
        }

        $this->autoSizeColumns(1, 7);

        return $this->download('variance-report-' . now()->format('Y-m-d'));
    }

    /**
     * Export Capex to Excel.
     */
    public function capex($capexList, ?int $fiscalYear = null): StreamedResponse
    {
        $subtitle = $fiscalYear ? 'Fiscal Year: ' . $fiscalYear : 'All Capital Expenditures';
        $this->initSpreadsheet('Capital Expenditure Report', $subtitle);

        $this->addHeaderRow([
            'Reference', 'Title', 'Category', 'Department',
            'Requested Amount', 'Approved Amount', 'Spent',
            'Priority', 'Status', 'Requested By'
        ]);

        $totalRequested = 0;
        $totalApproved = 0;
        $totalSpent = 0;

        foreach ($capexList as $capex) {
            $this->addDataRow([
                $capex->reference_number ?? '',
                $capex->title ?? '',
                $capex->category ?? '',
                $capex->department_name ?? ($capex->department->name ?? 'N/A'),
                number_format($capex->requested_amount ?? 0, 2),
                number_format($capex->approved_amount ?? 0, 2),
                number_format($capex->amount_spent ?? 0, 2),
                ucfirst($capex->priority ?? ''),
                ucfirst(str_replace('_', ' ', $capex->status ?? '')),
                $capex->requestedBy->name ?? ($capex->requested_by_name ?? 'N/A'),
            ]);

            $totalRequested += $capex->requested_amount ?? 0;
            $totalApproved += $capex->approved_amount ?? 0;
            $totalSpent += $capex->amount_spent ?? 0;
        }

        $this->addDataRow([
            '', '', '', 'TOTALS:',
            number_format($totalRequested, 2),
            number_format($totalApproved, 2),
            number_format($totalSpent, 2),
            '', '', ''
        ], 1, true);

        $this->autoSizeColumns(1, 10);

        return $this->download('capex-' . ($fiscalYear ?? now()->year));
    }

    /**
     * Export Patient Deposits to Excel.
     */
    public function patientDeposits($deposits, array $stats = []): StreamedResponse
    {
        $this->initSpreadsheet('Patient Deposits Report', 'Generated: ' . now()->format('F d, Y'));

        // Summary section
        if (!empty($stats)) {
            $this->activeSheet->setCellValue('A' . $this->currentRow, 'SUMMARY');
            $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
            $this->currentRow++;

            $this->addDataRow(['Total Deposits', number_format($stats['total_deposits'] ?? 0, 2)]);
            $this->addDataRow(['Applied Amount', number_format($stats['total_applied'] ?? 0, 2)]);
            $this->addDataRow(['Available Balance', number_format($stats['available_balance'] ?? 0, 2)]);
            $this->currentRow++;
        }

        $this->addHeaderRow([
            'Deposit Number', 'Patient Name', 'File No', 'Deposit Type',
            'Amount', 'Applied', 'Balance', 'Payment Method', 'Date', 'Status'
        ]);

        foreach ($deposits as $deposit) {
            $this->addDataRow([
                $deposit->deposit_number ?? '',
                $deposit->patient_name ?? ($deposit->patient->name ?? ''),
                $deposit->file_no ?? ($deposit->patient->file_no ?? ''),
                ucfirst(str_replace('_', ' ', $deposit->deposit_type ?? '')),
                number_format($deposit->amount ?? 0, 2),
                number_format($deposit->applied_amount ?? 0, 2),
                number_format(($deposit->amount ?? 0) - ($deposit->applied_amount ?? 0), 2),
                ucfirst($deposit->payment_method ?? ''),
                $deposit->deposit_date ? Carbon::parse($deposit->deposit_date)->format('M d, Y') : '',
                ucfirst($deposit->status ?? ''),
            ]);
        }

        $this->autoSizeColumns(1, 10);

        return $this->download('patient-deposits-' . now()->format('Y-m-d'));
    }

    /**
     * Export Cost Center Report to Excel
     */
    public function exportCostCenterReport(
        $costCenter,
        string $fromDate,
        string $toDate,
        $expensesByAccount,
        $transactions,
        array $summary
    ): StreamedResponse {
        $reportTitle = 'Cost Center Report: ' . $costCenter->code . ' - ' . $costCenter->name;
        $subtitle = 'Period: ' . Carbon::parse($fromDate)->format('M d, Y') . ' to ' . Carbon::parse($toDate)->format('M d, Y');

        $this->initSpreadsheet($reportTitle, $subtitle);

        // Summary Section
        $this->currentRow += 2;
        $this->addSectionHeader('Summary');

        $this->activeSheet->setCellValue('A' . $this->currentRow, 'Total Revenue:');
        $this->activeSheet->setCellValue('B' . $this->currentRow, '₦' . number_format($summary['total_revenue'], 2));
        $this->styleCell('A' . $this->currentRow, true);
        $this->styleCell('B' . $this->currentRow, false, 'text', '28a745');
        $this->currentRow++;

        $this->activeSheet->setCellValue('A' . $this->currentRow, 'Total Expenses:');
        $this->activeSheet->setCellValue('B' . $this->currentRow, '₦' . number_format($summary['total_expenses'], 2));
        $this->styleCell('A' . $this->currentRow, true);
        $this->styleCell('B' . $this->currentRow, false, 'text', 'dc3545');
        $this->currentRow++;

        $netAmount = $summary['total_revenue'] - $summary['total_expenses'];
        $this->activeSheet->setCellValue('A' . $this->currentRow, $netAmount >= 0 ? 'Net Surplus:' : 'Net Deficit:');
        $this->activeSheet->setCellValue('B' . $this->currentRow, '₦' . number_format(abs($netAmount), 2));
        $this->styleCell('A' . $this->currentRow, true);
        $this->styleCell('B' . $this->currentRow, false, 'text', $netAmount >= 0 ? '28a745' : 'dc3545');
        $this->currentRow++;

        $this->activeSheet->setCellValue('A' . $this->currentRow, 'Total Transactions:');
        $this->activeSheet->setCellValue('B' . $this->currentRow, $summary['transaction_count']);
        $this->styleCell('A' . $this->currentRow, true);
        $this->currentRow++;

        // Breakdown by Account
        $this->currentRow += 2;
        $this->addSectionHeader('Breakdown by Account');
        $this->addTableHeader(['Code', 'Account Name', 'Debit', 'Credit']);

        foreach ($expensesByAccount as $item) {
            $this->addDataRow([
                $item->account_code,
                $item->account_name,
                $item->total_debit > 0 ? number_format($item->total_debit, 2) : '-',
                $item->total_credit > 0 ? number_format($item->total_credit, 2) : '-',
            ]);
        }

        // Transaction Details
        $this->currentRow += 2;
        $this->addSectionHeader('Transaction Details');
        $this->addTableHeader(['Date', 'JE #', 'Account', 'Description', 'Debit', 'Credit']);

        foreach ($transactions as $txn) {
            $this->addDataRow([
                Carbon::parse($txn->journalEntry->entry_date)->format('M d, Y'),
                $txn->journalEntry->entry_number,
                $txn->account->code ?? 'N/A',
                $txn->description ?? $txn->journalEntry->description ?? '',
                $txn->debit > 0 ? number_format($txn->debit, 2) : '-',
                $txn->credit > 0 ? number_format($txn->credit, 2) : '-',
            ]);
        }

        $this->autoSizeColumns(1, 6);

        return $this->download('cost-center-report-' . $costCenter->code . '-' . now()->format('Y-m-d'));
    }

    /**
     * Export Fixed Assets Register to Excel.
     */
    public function fixedAssetsRegister($assets, array $stats): StreamedResponse
    {
        $this->initSpreadsheet('Fixed Assets Register', 'As of ' . now()->format('F d, Y'));

        // Summary section
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'Summary');
        $this->activeSheet->mergeCells('A' . $this->currentRow . ':I' . $this->currentRow);
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true);
        $this->currentRow++;

        $this->addDataRow(['Total Assets:', $stats['total_assets'], 'Total Cost:', '₦' . number_format($stats['total_cost'], 2), 'Book Value:', '₦' . number_format($stats['total_book_value'], 2), 'Accum. Depr.:', '₦' . number_format($stats['total_accum_depreciation'], 2)]);
        $this->currentRow++;

        // Assets table
        $this->addHeaderRow(['Asset #', 'Name', 'Category', 'Department', 'Acquisition Date', 'Total Cost', 'Book Value', 'Accum. Depreciation', 'Status']);

        foreach ($assets as $asset) {
            $this->addDataRow([
                $asset->asset_number,
                $asset->name,
                $asset->category?->name ?? '-',
                $asset->department?->name ?? '-',
                $asset->acquisition_date?->format('M d, Y') ?? '-',
                number_format($asset->total_cost, 2),
                number_format($asset->book_value, 2),
                number_format($asset->accumulated_depreciation, 2),
                ucfirst(str_replace('_', ' ', $asset->status)),
            ]);
        }

        // Totals row
        $this->currentRow++;
        $totalRow = $this->currentRow;
        $this->activeSheet->setCellValue('A' . $totalRow, 'TOTALS:');
        $this->activeSheet->setCellValue('F' . $totalRow, number_format($assets->sum('total_cost'), 2));
        $this->activeSheet->setCellValue('G' . $totalRow, number_format($assets->sum('book_value'), 2));
        $this->activeSheet->setCellValue('H' . $totalRow, number_format($assets->sum('accumulated_depreciation'), 2));
        $this->activeSheet->getStyle('A' . $totalRow . ':I' . $totalRow)->getFont()->setBold(true);
        $this->activeSheet->getStyle('A' . $totalRow . ':I' . $totalRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE9ECEF');

        $this->autoSizeColumns(1, 9);

        return $this->download('fixed-assets-register-' . now()->format('Y-m-d'));
    }

    /**
     * Export Fixed Asset Detail to Excel.
     */
    public function fixedAssetDetail($asset, array $depreciationSchedule, $depreciationHistory): StreamedResponse
    {
        $this->initSpreadsheet('Fixed Asset Details', $asset->asset_number . ' - ' . $asset->name);

        // Asset information section header
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'Asset Information');
        $this->activeSheet->mergeCells('A' . $this->currentRow . ':B' . $this->currentRow);
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true);
        $this->currentRow++;

        $infoRows = [
            ['Asset Number:', $asset->asset_number],
            ['Name:', $asset->name],
            ['Category:', $asset->category?->name ?? '-'],
            ['Department:', $asset->department?->name ?? '-'],
            ['Status:', ucfirst(str_replace('_', ' ', $asset->status))],
            ['', ''],
            ['Cost Information', ''],
            ['Acquisition Cost:', '₦' . number_format($asset->acquisition_cost, 2)],
            ['Additional Costs:', '₦' . number_format($asset->additional_costs, 2)],
            ['Total Cost:', '₦' . number_format($asset->total_cost, 2)],
            ['Salvage Value:', '₦' . number_format($asset->salvage_value, 2)],
            ['Depreciable Amount:', '₦' . number_format($asset->depreciable_amount, 2)],
            ['Accumulated Depreciation:', '₦' . number_format($asset->accumulated_depreciation, 2)],
            ['Book Value:', '₦' . number_format($asset->book_value, 2)],
            ['', ''],
            ['Depreciation Settings', ''],
            ['Method:', ucfirst(str_replace('_', ' ', $asset->depreciation_method))],
            ['Useful Life:', $asset->useful_life_years . ' years (' . $asset->useful_life_months . ' months)'],
            ['Monthly Depreciation:', '₦' . number_format($asset->monthly_depreciation, 2)],
        ];

        foreach ($infoRows as $row) {
            $this->addDataRow($row);
        }

        // Depreciation history
        if ($depreciationHistory->count() > 0) {
            $this->currentRow += 2;
            $this->activeSheet->setCellValue('A' . $this->currentRow, 'Depreciation History');
            $this->activeSheet->mergeCells('A' . $this->currentRow . ':D' . $this->currentRow);
            $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true);
            $this->currentRow++;

            $this->addHeaderRow(['Date', 'Amount', 'Book Value After', 'Journal Entry']);

            foreach ($depreciationHistory as $dep) {
                $this->addDataRow([
                    $dep->depreciation_date->format('M d, Y'),
                    number_format($dep->amount, 2),
                    number_format($dep->book_value_after, 2),
                    $dep->journalEntry?->entry_number ?? '-',
                ]);
            }
        }

        $this->autoSizeColumns(1, 4);

        return $this->download('asset-' . $asset->asset_number . '-' . now()->format('Y-m-d'));
    }

    /**
     * Export Cash Flow Forecast to Excel.
     */
    public function cashFlowForecast($forecast, $currentCash): StreamedResponse
    {
        $subtitle = $forecast->forecast_start_date && $forecast->forecast_end_date
            ? $forecast->forecast_start_date->format('F d, Y') . ' to ' . $forecast->forecast_end_date->format('F d, Y')
            : null;

        $this->initSpreadsheet('Cash Flow Forecast: ' . $forecast->forecast_name, $subtitle);

        // Forecast Summary Section
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'FORECAST DETAILS');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
        $this->currentRow++;

        $detailsStartRow = $this->currentRow;
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'Forecast Name:');
        $this->activeSheet->setCellValue('B' . $this->currentRow, $forecast->forecast_name);
        $this->currentRow++;

        $this->activeSheet->setCellValue('A' . $this->currentRow, 'Forecast Type:');
        $this->activeSheet->setCellValue('B' . $this->currentRow, ucwords(str_replace('_', ' ', $forecast->forecast_type)));
        $this->currentRow++;

        $this->activeSheet->setCellValue('A' . $this->currentRow, 'Frequency:');
        $this->activeSheet->setCellValue('B' . $this->currentRow, ucfirst($forecast->frequency));
        $this->currentRow++;

        $this->activeSheet->setCellValue('A' . $this->currentRow, 'Status:');
        $this->activeSheet->setCellValue('B' . $this->currentRow, ucfirst($forecast->status));
        $this->currentRow++;

        $this->activeSheet->setCellValue('A' . $this->currentRow, 'Opening Balance:');
        $this->activeSheet->setCellValue('B' . $this->currentRow, '₦' . number_format($currentCash, 2));
        $this->currentRow++;

        if ($forecast->approved_by) {
            $this->activeSheet->setCellValue('A' . $this->currentRow, 'Approved By:');
            $this->activeSheet->setCellValue('B' . $this->currentRow,
                optional($forecast->approver)->name . ' on ' . optional($forecast->approved_at)->format('M d, Y'));
            $this->currentRow++;
        }

        // Style the details section
        $detailsEndRow = $this->currentRow - 1;
        $this->activeSheet->getStyle('A' . $detailsStartRow . ':A' . $detailsEndRow)->getFont()->setBold(true);
        $this->activeSheet->getStyle('A' . $detailsStartRow . ':B' . $detailsEndRow)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
        ]);

        $this->currentRow++;

        // Period Summary Table
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'PERIOD SUMMARY');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
        $this->currentRow++;

        $this->addHeaderRow([
            'Period',
            'Start Date',
            'End Date',
            'Opening Balance',
            'Forecasted Inflows',
            'Forecasted Outflows',
            'Net Cash Flow',
            'Closing Balance',
            'Actual Closing',
            'Variance'
        ]);

        $runningBalance = $currentCash;
        foreach ($forecast->periods()->orderBy('period_start_date')->get() as $period) {
            $netFlow = $period->forecasted_inflows - $period->forecasted_outflows;
            $periodClosing = $runningBalance + $netFlow;

            $this->addDataRow([
                $period->period_name,
                optional($period->period_start_date)->format('M d, Y'),
                optional($period->period_end_date)->format('M d, Y'),
                '₦' . number_format($runningBalance, 2),
                '₦' . number_format($period->forecasted_inflows, 2),
                '₦' . number_format($period->forecasted_outflows, 2),
                '₦' . number_format($netFlow, 2),
                '₦' . number_format($periodClosing, 2),
                $period->actual_closing_balance !== null ? '₦' . number_format($period->actual_closing_balance, 2) : '-',
                $period->actual_closing_balance !== null ? '₦' . number_format($period->variance, 2) : '-',
            ]);

            $runningBalance = $periodClosing;
        }

        $this->autoSizeColumns(1, 10);
        $this->currentRow += 2;

        // Detailed Period Breakdown
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'PERIOD-BY-PERIOD BREAKDOWN');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(12);
        $this->currentRow++;

        foreach ($forecast->periods()->orderBy('period_start_date')->get() as $period) {
            // Period header
            $this->activeSheet->setCellValue('A' . $this->currentRow,
                $period->period_name . ' (' . optional($period->period_start_date)->format('M d, Y') . ' - ' .
                optional($period->period_end_date)->format('M d, Y') . ')');
            $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
            $this->currentRow++;

            $items = $period->items()->orderBy('cash_flow_category')->orderBy('item_description')->get();
            $inflows = $items->whereIn('cash_flow_category', ['operating_inflow', 'investing_inflow', 'financing_inflow']);
            $outflows = $items->whereIn('cash_flow_category', ['operating_outflow', 'investing_outflow', 'financing_outflow']);

            if ($items->isEmpty()) {
                $this->activeSheet->setCellValue('A' . $this->currentRow, 'No line items defined for this period');
                $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setItalic(true)->getColor()->setRGB('999999');
                $this->currentRow += 2;
                continue;
            }

            // Inflows
            if ($inflows->isNotEmpty()) {
                $this->activeSheet->setCellValue('A' . $this->currentRow, 'CASH INFLOWS');
                $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->getColor()->setRGB('28a745');
                $this->currentRow++;

                $this->addHeaderRow(['Category', 'Description', 'Source', 'Amount']);

                $currentCategory = null;
                $categoryTotal = 0;
                $grandTotal = 0;

                foreach ($inflows as $item) {
                    if ($currentCategory !== $item->cash_flow_category) {
                        if ($currentCategory !== null) {
                            $this->addDataRow([
                                '',
                                ucwords(str_replace('_', ' ', $currentCategory)) . ' Subtotal',
                                '',
                                '₦' . number_format($categoryTotal, 2),
                            ], 1, true);
                            $categoryTotal = 0;
                        }
                        $currentCategory = $item->cash_flow_category;
                    }

                    $this->addDataRow([
                        ucwords(str_replace('_', ' ', $item->cash_flow_category)),
                        $item->item_description,
                        ucwords(str_replace('_', ' ', $item->source_type)),
                        '₦' . number_format($item->forecasted_amount, 2),
                    ]);

                    $categoryTotal += $item->forecasted_amount;
                    $grandTotal += $item->forecasted_amount;
                }

                // Last category subtotal
                $this->addDataRow([
                    '',
                    ucwords(str_replace('_', ' ', $currentCategory)) . ' Subtotal',
                    '',
                    '₦' . number_format($categoryTotal, 2),
                ], 1, true);

                // Grand total
                $this->addDataRow([
                    '',
                    'Total Inflows',
                    '',
                    '₦' . number_format($grandTotal, 2),
                ], 1, true);

                $this->currentRow++;
            }

            // Outflows
            if ($outflows->isNotEmpty()) {
                $this->activeSheet->setCellValue('A' . $this->currentRow, 'CASH OUTFLOWS');
                $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->getColor()->setRGB('dc3545');
                $this->currentRow++;

                $this->addHeaderRow(['Category', 'Description', 'Source', 'Amount']);

                $currentCategory = null;
                $categoryTotal = 0;
                $grandTotal = 0;

                foreach ($outflows as $item) {
                    if ($currentCategory !== $item->cash_flow_category) {
                        if ($currentCategory !== null) {
                            $this->addDataRow([
                                '',
                                ucwords(str_replace('_', ' ', $currentCategory)) . ' Subtotal',
                                '',
                                '₦' . number_format($categoryTotal, 2),
                            ], 1, true);
                            $categoryTotal = 0;
                        }
                        $currentCategory = $item->cash_flow_category;
                    }

                    $this->addDataRow([
                        ucwords(str_replace('_', ' ', $item->cash_flow_category)),
                        $item->item_description,
                        ucwords(str_replace('_', ' ', $item->source_type)),
                        '₦' . number_format($item->forecasted_amount, 2),
                    ]);

                    $categoryTotal += $item->forecasted_amount;
                    $grandTotal += $item->forecasted_amount;
                }

                // Last category subtotal
                $this->addDataRow([
                    '',
                    ucwords(str_replace('_', ' ', $currentCategory)) . ' Subtotal',
                    '',
                    '₦' . number_format($categoryTotal, 2),
                ], 1, true);

                // Grand total
                $this->addDataRow([
                    '',
                    'Total Outflows',
                    '',
                    '₦' . number_format($grandTotal, 2),
                ], 1, true);

                $this->currentRow++;
            }

            // Period Summary
            $this->activeSheet->setCellValue('A' . $this->currentRow, 'Period Summary');
            $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(10);
            $this->currentRow++;

            $summaryStartRow = $this->currentRow;
            $this->addDataRow([
                'Total Inflows:',
                '₦' . number_format($period->forecasted_inflows, 2)
            ]);
            $this->addDataRow([
                'Total Outflows:',
                '₦' . number_format($period->forecasted_outflows, 2)
            ]);
            $this->addDataRow([
                'Net Cash Flow:',
                '₦' . number_format($period->forecasted_inflows - $period->forecasted_outflows, 2)
            ], 1, true);

            if ($period->actual_closing_balance !== null) {
                $this->addDataRow([
                    'Actual Closing Balance:',
                    '₦' . number_format($period->actual_closing_balance, 2)
                ]);
                $this->addDataRow([
                    'Variance:',
                    '₦' . number_format($period->variance, 2)
                ]);
            }

            $summaryEndRow = $this->currentRow - 1;
            $this->activeSheet->getStyle('A' . $summaryStartRow . ':A' . $summaryEndRow)->getFont()->setBold(true);
            $this->activeSheet->getStyle('A' . $summaryStartRow . ':B' . $summaryEndRow)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F5F5F5']],
            ]);

            $this->currentRow += 2;
        }

        $this->autoSizeColumns(1, 4);

        return $this->download('cash-flow-forecast-' . $forecast->id . '-' . now()->format('Y-m-d'));
    }

    /**
     * Export Lease Portfolio to Excel.
     *
     * @param \Illuminate\Support\Collection $leases
     * @param array $stats Dashboard statistics
     * @return StreamedResponse
     */
    public function leasePortfolio($leases, array $stats): StreamedResponse
    {
        $this->initSpreadsheet('Lease Portfolio Report', 'As of ' . now()->format('F d, Y'));

        // Summary Section
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'PORTFOLIO SUMMARY');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
        $this->currentRow++;

        $this->addDataRow(['Active Leases:', $stats['active_count'] ?? 0]);
        $this->addDataRow(['Total ROU Assets:', '₦' . number_format($stats['total_rou_asset'] ?? 0, 2)]);
        $this->addDataRow(['Total Lease Liability:', '₦' . number_format($stats['total_liability'] ?? 0, 2)]);
        $this->addDataRow(['Monthly Depreciation:', '₦' . number_format($stats['monthly_depreciation'] ?? 0, 2)]);
        $this->addDataRow(['Payments Due This Month:', '₦' . number_format($stats['payments_due_this_month'] ?? 0, 2)]);
        $this->addDataRow(['Overdue Payments:', '₦' . number_format($stats['overdue_payments'] ?? 0, 2)]);
        $this->addDataRow(['Expiring in 90 Days:', $stats['expiring_soon'] ?? 0]);

        $this->currentRow += 2;

        // By Type Summary
        if (!empty($stats['by_type'])) {
            $this->activeSheet->setCellValue('A' . $this->currentRow, 'SUMMARY BY LEASE TYPE');
            $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
            $this->currentRow++;

            $this->addHeaderRow(['Lease Type', 'Count', 'ROU Asset Value', 'Lease Liability']);

            foreach ($stats['by_type'] as $type => $data) {
                $this->addDataRow([
                    ucfirst(str_replace('_', ' ', $type)),
                    $data['count'] ?? 0,
                    '₦' . number_format($data['rou_asset'] ?? 0, 2),
                    '₦' . number_format($data['liability'] ?? 0, 2),
                ]);
            }

            $this->currentRow += 2;
        }

        // Lease Details
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'LEASE PORTFOLIO DETAILS');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
        $this->currentRow++;

        $this->addHeaderRow([
            'Lease #',
            'Lease Type',
            'Status',
            'Leased Item',
            'Lessor',
            'Start Date',
            'End Date',
            'Monthly Payment',
            'ROU Asset',
            'Lease Liability',
            'IBR %',
        ]);

        foreach ($leases as $lease) {
            $this->addDataRow([
                $lease->lease_number,
                ucfirst(str_replace('_', ' ', $lease->lease_type)),
                ucfirst($lease->status),
                $lease->leased_item,
                $lease->supplier_name ?? $lease->lessor_name ?? '-',
                Carbon::parse($lease->commencement_date)->format('M d, Y'),
                Carbon::parse($lease->end_date)->format('M d, Y'),
                '₦' . number_format($lease->monthly_payment, 2),
                '₦' . number_format($lease->current_rou_asset_value, 2),
                '₦' . number_format($lease->current_lease_liability, 2),
                number_format($lease->incremental_borrowing_rate, 2) . '%',
            ]);
        }

        // Totals
        $this->addDataRow([
            '', '', '', '', '', '', 'TOTALS',
            '',
            '₦' . number_format($stats['total_rou_asset'] ?? 0, 2),
            '₦' . number_format($stats['total_liability'] ?? 0, 2),
            '',
        ], 1, true);

        $this->autoSizeColumns(1, 11);

        return $this->download('lease-portfolio-' . now()->format('Y-m-d'));
    }

    /**
     * Export Single Lease Details to Excel.
     *
     * @param object $lease Lease object
     * @param \Illuminate\Support\Collection $schedule Payment schedule
     * @param object|null $paymentSummary Payment summary stats
     * @param \Illuminate\Support\Collection|null $journalEntries Related journal entries
     * @return StreamedResponse
     */
    public function leaseDetail($lease, $schedule, $paymentSummary = null, $journalEntries = null): StreamedResponse
    {
        $this->initSpreadsheet('Lease Detail - ' . $lease->lease_number, 'Generated ' . now()->format('F d, Y'));

        // Lease Information
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'LEASE INFORMATION');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
        $this->currentRow++;

        $this->addDataRow(['Lease Number:', $lease->lease_number]);
        $this->addDataRow(['Leased Item:', $lease->leased_item]);
        $this->addDataRow(['Lease Type:', ucfirst(str_replace('_', ' ', $lease->lease_type))]);
        $this->addDataRow(['Status:', ucfirst($lease->status)]);
        $this->addDataRow(['Lessor:', $lease->supplier_name ?? $lease->lessor_name ?? '-']);
        $this->addDataRow(['Department:', $lease->department_name ?? '-']);
        $this->addDataRow(['Commencement Date:', Carbon::parse($lease->commencement_date)->format('M d, Y')]);
        $this->addDataRow(['End Date:', Carbon::parse($lease->end_date)->format('M d, Y')]);
        $this->addDataRow(['Lease Term:', $lease->lease_term_months . ' months']);
        $this->addDataRow(['Monthly Payment:', '₦' . number_format($lease->monthly_payment, 2)]);
        $this->addDataRow(['Incremental Borrowing Rate:', number_format($lease->incremental_borrowing_rate, 2) . '%']);

        $this->currentRow += 2;

        // IFRS 16 Values
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'IFRS 16 RECOGNITION VALUES');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
        $this->currentRow++;

        $this->addDataRow(['Initial ROU Asset:', '₦' . number_format($lease->initial_rou_asset_value, 2)]);
        $this->addDataRow(['Current ROU Asset:', '₦' . number_format($lease->current_rou_asset_value, 2)]);
        $this->addDataRow(['Accumulated Depreciation:', '₦' . number_format($lease->accumulated_rou_depreciation, 2)]);
        $this->addDataRow(['Initial Lease Liability:', '₦' . number_format($lease->initial_lease_liability, 2)]);
        $this->addDataRow(['Current Lease Liability:', '₦' . number_format($lease->current_lease_liability, 2)]);

        $this->currentRow += 2;

        // Payment Summary
        if ($paymentSummary) {
            $this->activeSheet->setCellValue('A' . $this->currentRow, 'PAYMENT SUMMARY');
            $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
            $this->currentRow++;

            $this->addDataRow(['Total Payments:', $paymentSummary->total_payments ?? 0]);
            $this->addDataRow(['Paid Payments:', $paymentSummary->paid_count ?? 0]);
            $this->addDataRow(['Total Paid:', '₦' . number_format($paymentSummary->total_paid ?? 0, 2)]);
            $this->addDataRow(['Total Scheduled:', '₦' . number_format($paymentSummary->total_scheduled ?? 0, 2)]);
            $this->addDataRow(['Remaining:', '₦' . number_format(($paymentSummary->total_scheduled ?? 0) - ($paymentSummary->total_paid ?? 0), 2)]);

            $this->currentRow += 2;
        }

        // Payment Schedule (First 12)
        if ($schedule && $schedule->count() > 0) {
            $this->activeSheet->setCellValue('A' . $this->currentRow, 'PAYMENT SCHEDULE (FIRST 12)');
            $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
            $this->currentRow++;

            $this->addHeaderRow([
                '#', 'Due Date', 'Payment', 'Principal', 'Interest',
                'Opening Liability', 'Closing Liability', 'ROU Depreciation', 'Status',
            ]);

            foreach ($schedule->take(12) as $payment) {
                $isPaid = !is_null($payment->payment_date);
                $isOverdue = !$isPaid && Carbon::parse($payment->due_date)->lt(now());
                $status = $isPaid ? 'Paid' : ($isOverdue ? 'Overdue' : 'Scheduled');

                $this->addDataRow([
                    $payment->payment_number,
                    Carbon::parse($payment->due_date)->format('M d, Y'),
                    '₦' . number_format($payment->payment_amount, 2),
                    '₦' . number_format($payment->principal_portion, 2),
                    '₦' . number_format($payment->interest_portion, 2),
                    '₦' . number_format($payment->opening_liability, 2),
                    '₦' . number_format($payment->closing_liability, 2),
                    '₦' . number_format($payment->rou_depreciation, 2),
                    $status,
                ]);
            }

            $this->currentRow += 2;
        }

        // Journal Entries
        if ($journalEntries && $journalEntries->count() > 0) {
            $this->activeSheet->setCellValue('A' . $this->currentRow, 'RELATED JOURNAL ENTRIES');
            $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
            $this->currentRow++;

            $this->addHeaderRow(['Entry #', 'Date', 'Description', 'Amount']);

            foreach ($journalEntries as $je) {
                $this->addDataRow([
                    $je->entry_number,
                    Carbon::parse($je->entry_date)->format('M d, Y'),
                    $je->description,
                    '₦' . number_format($je->total_debit, 2),
                ]);
            }
        }

        $this->autoSizeColumns(1, 9);

        return $this->download('lease-detail-' . $lease->lease_number . '-' . now()->format('Y-m-d'));
    }

    /**
     * Export Full Lease Payment Schedule to Excel.
     *
     * @param object $lease Lease object
     * @param \Illuminate\Support\Collection $schedule Full payment schedule
     * @return StreamedResponse
     */
    public function leaseSchedule($lease, $schedule): StreamedResponse
    {
        $this->initSpreadsheet('Payment Schedule - ' . $lease->lease_number, 'Generated ' . now()->format('F d, Y'));

        // Lease Summary
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'LEASE SUMMARY');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
        $this->currentRow++;

        $this->addDataRow(['Lease Number:', $lease->lease_number]);
        $this->addDataRow(['Leased Item:', $lease->leased_item]);
        $this->addDataRow(['Lessor:', $lease->supplier_name ?? $lease->lessor_name ?? '-']);
        $this->addDataRow(['Lease Term:', Carbon::parse($lease->commencement_date)->format('M Y') . ' - ' . Carbon::parse($lease->end_date)->format('M Y')]);
        $this->addDataRow(['Monthly Payment:', '₦' . number_format($lease->monthly_payment, 2)]);
        $this->addDataRow(['IBR:', number_format($lease->incremental_borrowing_rate, 2) . '%']);

        $this->currentRow += 2;

        // Schedule Totals
        $totalPayments = $schedule->sum('payment_amount');
        $totalPrincipal = $schedule->sum('principal_portion');
        $totalInterest = $schedule->sum('interest_portion');
        $totalDepreciation = $schedule->sum('rou_depreciation');
        $paidPayments = $schedule->whereNotNull('payment_date');
        $paidCount = $paidPayments->count();
        $paidAmount = $paidPayments->sum('actual_payment') ?: $paidPayments->sum('payment_amount');
        $remainingPayments = $schedule->whereNull('payment_date');
        $remainingAmount = $remainingPayments->sum('payment_amount');

        $this->activeSheet->setCellValue('A' . $this->currentRow, 'SCHEDULE TOTALS');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
        $this->currentRow++;

        $this->addDataRow(['Total Payments:', $schedule->count()]);
        $this->addDataRow(['Total Amount:', '₦' . number_format($totalPayments, 2)]);
        $this->addDataRow(['Total Principal:', '₦' . number_format($totalPrincipal, 2)]);
        $this->addDataRow(['Total Interest:', '₦' . number_format($totalInterest, 2)]);
        $this->addDataRow(['Total Depreciation:', '₦' . number_format($totalDepreciation, 2)]);
        $this->addDataRow(['Paid Payments:', $paidCount . ' (₦' . number_format($paidAmount, 2) . ')']);
        $this->addDataRow(['Remaining Payments:', $remainingPayments->count() . ' (₦' . number_format($remainingAmount, 2) . ')']);

        $this->currentRow += 2;

        // Full Schedule
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'FULL PAYMENT SCHEDULE');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
        $this->currentRow++;

        $this->addHeaderRow([
            '#', 'Due Date', 'Payment', 'Principal', 'Interest',
            'Opening Liability', 'Closing Liability', 'ROU Depreciation',
            'Opening ROU', 'Closing ROU', 'Status',
        ]);

        foreach ($schedule as $payment) {
            $isPaid = !is_null($payment->payment_date);
            $isOverdue = !$isPaid && Carbon::parse($payment->due_date)->lt(now());
            $isCurrent = !$isPaid && Carbon::parse($payment->due_date)->isSameMonth(now());
            $status = $isPaid ? 'PAID' : ($isOverdue ? 'OVERDUE' : ($isCurrent ? 'DUE' : 'SCHEDULED'));

            $this->addDataRow([
                $payment->payment_number,
                Carbon::parse($payment->due_date)->format('M d, Y'),
                '₦' . number_format($payment->payment_amount, 2),
                '₦' . number_format($payment->principal_portion, 2),
                '₦' . number_format($payment->interest_portion, 2),
                '₦' . number_format($payment->opening_liability, 2),
                '₦' . number_format($payment->closing_liability, 2),
                '₦' . number_format($payment->rou_depreciation, 2),
                '₦' . number_format($payment->opening_rou_value, 2),
                '₦' . number_format($payment->closing_rou_value, 2),
                $status,
            ]);
        }

        // Totals Row
        $this->addDataRow([
            '', 'TOTALS',
            '₦' . number_format($totalPayments, 2),
            '₦' . number_format($totalPrincipal, 2),
            '₦' . number_format($totalInterest, 2),
            '-', '₦0.00',
            '₦' . number_format($totalDepreciation, 2),
            '-', '₦0.00', '',
        ], 1, true);

        $this->autoSizeColumns(1, 11);

        return $this->download('lease-schedule-' . $lease->lease_number . '-' . now()->format('Y-m-d'));
    }

    /**
     * Export Petty Cash Fund Report to Excel.
     */
    public function pettyCashFundReport($fund, $transactions, string $dateFrom, string $dateTo): StreamedResponse
    {
        $this->initSpreadsheet(
            'Petty Cash Fund Report',
            $fund->fund_name . ' | ' . $dateFrom . ' to ' . $dateTo
        );

        // Fund Summary Section
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'FUND SUMMARY');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
        $this->currentRow++;

        $this->addDataRow(['Fund Code:', $fund->fund_code, 'Fund Limit:', '₦' . number_format($fund->fund_limit, 2)]);
        $this->addDataRow(['Department:', $fund->department?->name ?? 'N/A', 'Current Balance:', '₦' . number_format($fund->current_balance, 2)]);
        $this->addDataRow(['Custodian:', $fund->custodian?->name ?? 'N/A', 'Transaction Limit:', '₦' . number_format($fund->transaction_limit, 2)]);
        $this->addDataRow(['GL Account:', ($fund->account?->code ?? '') . ' - ' . ($fund->account?->name ?? 'N/A'), 'Status:', ucfirst($fund->status)]);

        $this->currentRow++;

        // Transaction Totals
        $totalDisbursements = $transactions->where('transaction_type', 'disbursement')->where('status', 'disbursed')->sum('amount');
        $totalReplenishments = $transactions->where('transaction_type', 'replenishment')->where('status', 'disbursed')->sum('amount');

        $this->addDataRow(['Total Disbursements:', '-₦' . number_format($totalDisbursements, 2), 'Total Replenishments:', '+₦' . number_format($totalReplenishments, 2)], 1, true);
        $this->addDataRow(['Net Movement:', '₦' . number_format($totalReplenishments - $totalDisbursements, 2)], 1, true);

        $this->currentRow += 2;

        // Transactions Section
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'TRANSACTIONS');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
        $this->currentRow++;

        // Headers
        $this->addHeaderRow(['Date', 'Voucher #', 'Type', 'Description', 'Amount', 'Status', 'Requested By', 'Approved By']);

        // Data rows
        foreach ($transactions as $transaction) {
            $amountDisplay = $transaction->transaction_type === 'disbursement'
                ? '-₦' . number_format($transaction->amount, 2)
                : '+₦' . number_format($transaction->amount, 2);

            $this->addDataRow([
                Carbon::parse($transaction->transaction_date)->format('M d, Y'),
                $transaction->voucher_number ?? '-',
                ucfirst($transaction->transaction_type),
                $transaction->description,
                $amountDisplay,
                ucfirst($transaction->status),
                $transaction->requestedBy?->name ?? '-',
                $transaction->approvedBy?->name ?? '-',
            ]);
        }

        // Summary footer
        $this->currentRow++;
        $this->addDataRow(['', '', '', 'Total Disbursements:', '-₦' . number_format($totalDisbursements, 2), '', '', ''], 1, true);
        $this->addDataRow(['', '', '', 'Total Replenishments:', '+₦' . number_format($totalReplenishments, 2), '', '', ''], 1, true);
        $this->addDataRow(['', '', '', 'Net Movement:', '₦' . number_format($totalReplenishments - $totalDisbursements, 2), '', '', ''], 1, true);

        $this->autoSizeColumns(1, 8);

        return $this->download('petty-cash-' . $fund->fund_code . '-' . now()->format('Y-m-d'));
    }

    /**
     * Inter-Account Transfers Report
     */
    public function interAccountTransfersReport($transfers, string $dateFrom, string $dateTo, string $statusFilter = 'All Statuses'): StreamedResponse
    {
        $this->initSpreadsheet(
            'Inter-Account Transfers Report',
            $dateFrom . ' to ' . $dateTo . ' | ' . $statusFilter
        );

        // Summary Section
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'SUMMARY');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
        $this->currentRow++;

        $totalAmount = $transfers->sum('amount');
        $totalFees = $transfers->sum('transfer_fee');
        $clearedTransfers = $transfers->where('status', 'cleared');
        $pendingTransfers = $transfers->whereIn('status', ['pending_approval', 'approved', 'initiated', 'in_transit']);
        $failedTransfers = $transfers->where('status', 'failed');

        $this->addDataRow(['Total Transfers:', $transfers->count(), 'Total Amount:', '₦' . number_format($totalAmount, 2)]);
        $this->addDataRow(['Cleared:', $clearedTransfers->count(), 'Cleared Amount:', '₦' . number_format($clearedTransfers->sum('amount'), 2)]);
        $this->addDataRow(['Pending/In-Transit:', $pendingTransfers->count(), 'Pending Amount:', '₦' . number_format($pendingTransfers->sum('amount'), 2)]);
        $this->addDataRow(['Failed:', $failedTransfers->count(), 'Failed Amount:', '₦' . number_format($failedTransfers->sum('amount'), 2)]);
        $this->addDataRow(['Total Fees:', '', '', '₦' . number_format($totalFees, 2)]);

        $this->currentRow += 2;

        // Method Breakdown
        $byMethod = $transfers->groupBy('transfer_method');
        if ($byMethod->isNotEmpty()) {
            $this->activeSheet->setCellValue('A' . $this->currentRow, 'BY TRANSFER METHOD');
            $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
            $this->currentRow++;

            $this->addHeaderRow(['Method', 'Count', 'Amount']);
            foreach ($byMethod as $method => $group) {
                $this->addDataRow([
                    strtoupper($method),
                    $group->count(),
                    '₦' . number_format($group->sum('amount'), 2),
                ]);
            }
            $this->currentRow += 2;
        }

        // Transfers Detail Section
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'TRANSFER DETAILS');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
        $this->currentRow++;

        // Headers
        $this->addHeaderRow([
            'Transfer #',
            'Date',
            'From Account',
            'From Type',
            'To Account',
            'To Type',
            'Amount',
            'Fee',
            'Method',
            'Status',
            'Reference',
            'Initiated By',
            'Approved By',
            'JE #',
            'Description'
        ]);

        // Data rows
        foreach ($transfers as $transfer) {
            $fromType = $transfer->fromBank?->is_cash_account ? 'Cash' : 'Bank';
            $toType = $transfer->toBank?->is_cash_account ? 'Cash' : 'Bank';

            $initiatorName = $transfer->initiator
                ? trim(($transfer->initiator->surname ?? '') . ' ' . ($transfer->initiator->firstname ?? ''))
                : '-';

            $approverName = $transfer->approver
                ? trim(($transfer->approver->surname ?? '') . ' ' . ($transfer->approver->firstname ?? ''))
                : '-';

            $this->addDataRow([
                $transfer->transfer_number,
                Carbon::parse($transfer->transfer_date)->format('M d, Y'),
                $transfer->fromBank?->bank_name ?? 'N/A',
                $fromType,
                $transfer->toBank?->bank_name ?? 'N/A',
                $toType,
                '₦' . number_format($transfer->amount, 2),
                $transfer->transfer_fee > 0 ? '₦' . number_format($transfer->transfer_fee, 2) : '-',
                strtoupper($transfer->transfer_method),
                ucwords(str_replace('_', ' ', $transfer->status)),
                $transfer->reference ?? '-',
                $initiatorName,
                $approverName,
                $transfer->journalEntry?->entry_number ?? '-',
                $transfer->description ?? '-',
            ]);
        }

        // Summary footer
        $this->currentRow++;
        $this->addDataRow([
            'TOTAL',
            $transfers->count() . ' transfers',
            '', '', '', '',
            '₦' . number_format($totalAmount, 2),
            '₦' . number_format($totalFees, 2),
            '', '', '', '', '', '', ''
        ], 1, true);

        $this->autoSizeColumns(1, 15);

        return $this->download('inter-account-transfers-' . now()->format('Y-m-d'));
    }

    /**
     * Single Transfer Report (Voucher)
     */
    public function singleTransferReport($transfer): StreamedResponse
    {
        $this->initSpreadsheet(
            'Transfer Voucher',
            $transfer->transfer_number . ' | ' . Carbon::parse($transfer->transfer_date)->format('F d, Y')
        );

        // Transfer Header
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'TRANSFER DETAILS');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
        $this->currentRow++;

        $fromType = $transfer->fromBank?->is_cash_account ? 'Cash Account' : 'Bank Account';
        $toType = $transfer->toBank?->is_cash_account ? 'Cash Account' : 'Bank Account';

        $initiatorName = $transfer->initiator
            ? trim(($transfer->initiator->surname ?? '') . ' ' . ($transfer->initiator->firstname ?? ''))
            : 'System';

        $approverName = $transfer->approver
            ? trim(($transfer->approver->surname ?? '') . ' ' . ($transfer->approver->firstname ?? ''))
            : 'N/A';

        $this->addDataRow(['Transfer Number:', $transfer->transfer_number, 'Status:', ucwords(str_replace('_', ' ', $transfer->status))]);
        $this->addDataRow(['Transfer Date:', Carbon::parse($transfer->transfer_date)->format('F d, Y'), 'Transfer Method:', strtoupper($transfer->transfer_method)]);
        $this->addDataRow(['Reference:', $transfer->reference ?? 'N/A', 'Same Bank:', $transfer->is_same_bank ? 'Yes' : 'No']);

        $this->currentRow++;

        // Source & Destination
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'SOURCE & DESTINATION');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
        $this->currentRow++;

        $this->addDataRow(['From Bank:', $transfer->fromBank?->bank_name ?? 'N/A', 'Account Number:', $transfer->fromBank?->account_number ?? 'N/A']);
        $this->addDataRow(['Account Type:', $fromType, '', '']);
        $this->addDataRow(['To Bank:', $transfer->toBank?->bank_name ?? 'N/A', 'Account Number:', $transfer->toBank?->account_number ?? 'N/A']);
        $this->addDataRow(['Account Type:', $toType, '', '']);

        $this->currentRow++;

        // Amount Details
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'AMOUNT DETAILS');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
        $this->currentRow++;

        $this->addDataRow(['Transfer Amount:', '₦' . number_format($transfer->amount, 2)], 1, true);
        if ($transfer->transfer_fee > 0) {
            $this->addDataRow(['Transfer Fee:', '₦' . number_format($transfer->transfer_fee, 2)]);
            $this->addDataRow(['Fee Account:', $transfer->feeAccount?->account_name ?? 'N/A']);
            $this->addDataRow(['Total Deduction:', '₦' . number_format($transfer->amount + $transfer->transfer_fee, 2)], 1, true);
        }

        $this->currentRow++;

        // Authorization
        $this->activeSheet->setCellValue('A' . $this->currentRow, 'AUTHORIZATION');
        $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
        $this->currentRow++;

        $this->addDataRow(['Initiated By:', $initiatorName, 'Initiated On:', Carbon::parse($transfer->created_at)->format('M d, Y h:i A')]);
        $this->addDataRow(['Approved By:', $approverName, 'Approved On:', $transfer->approved_at ? Carbon::parse($transfer->approved_at)->format('M d, Y h:i A') : 'N/A']);

        if ($transfer->actual_clearance_date) {
            $this->addDataRow(['Cleared On:', Carbon::parse($transfer->actual_clearance_date)->format('M d, Y'), '', '']);
        }

        $this->currentRow++;

        // Description
        if ($transfer->description) {
            $this->activeSheet->setCellValue('A' . $this->currentRow, 'DESCRIPTION');
            $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
            $this->currentRow++;
            $this->addDataRow([$transfer->description]);
            $this->currentRow++;
        }

        // Journal Entry
        if ($transfer->journalEntry) {
            $this->activeSheet->setCellValue('A' . $this->currentRow, 'JOURNAL ENTRY - ' . $transfer->journalEntry->entry_number);
            $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11);
            $this->currentRow++;

            $this->addHeaderRow(['Account Code', 'Account Name', 'Description', 'Debit', 'Credit']);

            foreach ($transfer->journalEntry->lines as $line) {
                $this->addDataRow([
                    $line->account?->account_number ?? '-',
                    $line->account?->account_name ?? '-',
                    $line->description ?? '-',
                    $line->debit > 0 ? '₦' . number_format($line->debit, 2) : '-',
                    $line->credit > 0 ? '₦' . number_format($line->credit, 2) : '-',
                ]);
            }

            // Totals
            $this->addDataRow([
                '', 'TOTAL', '',
                '₦' . number_format($transfer->journalEntry->lines->sum('debit'), 2),
                '₦' . number_format($transfer->journalEntry->lines->sum('credit'), 2),
            ], 1, true);
        }

        // Failure reason if failed
        if ($transfer->status === 'failed' && $transfer->failure_reason) {
            $this->currentRow++;
            $this->activeSheet->setCellValue('A' . $this->currentRow, 'FAILURE REASON');
            $this->activeSheet->getStyle('A' . $this->currentRow)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('dc3545');
            $this->currentRow++;
            $this->addDataRow([$transfer->failure_reason]);
        }

        $this->autoSizeColumns(1, 5);

        return $this->download('transfer-voucher-' . $transfer->transfer_number);
    }
}
