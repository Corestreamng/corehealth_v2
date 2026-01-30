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
}
