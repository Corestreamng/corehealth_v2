<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\Account;
use App\Models\Accounting\SavedReportFilter;
use App\Services\Accounting\ReportService;
use App\Services\Accounting\ExcelExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Report Controller
 *
 * Reference: Accounting System Plan §7.4 - Controllers
 *
 * Handles all financial report generation.
 */
class ReportController extends Controller
{
    protected ReportService $reportService;
    protected ExcelExportService $excelService;

    public function __construct(ReportService $reportService, ExcelExportService $excelService)
    {
        $this->reportService = $reportService;
        $this->excelService = $excelService;

        // Permission middleware (§7.6 - Access Control)
        $this->middleware('permission:reports.view');
        $this->middleware('permission:reports.trial-balance')->only(['trialBalance']);
        $this->middleware('permission:reports.profit-loss')->only(['profitAndLoss']);
        $this->middleware('permission:reports.balance-sheet')->only(['balanceSheet']);
        $this->middleware('permission:reports.cash-flow')->only(['cashFlow']);
        $this->middleware('permission:reports.general-ledger')->only(['generalLedger']);
        $this->middleware('permission:reports.account-activity')->only(['accountActivity']);
        $this->middleware('permission:reports.daily-audit')->only(['dailyAudit']);
    }

    /**
     * Reports index - list available reports.
     */
    public function index()
    {
        $savedFilters = SavedReportFilter::where('created_by', Auth::id())
            ->orWhere('is_shared', true)
            ->orderBy('name')
            ->get();

        $periods = AccountingPeriod::orderBy('start_date', 'desc')->get();
        $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();

        return view('accounting.reports.index', compact('savedFilters', 'periods', 'fiscalYears'));
    }

    /**
     * Trial Balance Report.
     */
    public function trialBalance(Request $request)
    {
        $request->validate([
            'period_id' => 'nullable|exists:accounting_periods,id',
            'as_of_date' => 'nullable|date',
        ]);

        $asOfDate = $request->filled('as_of_date')
            ? Carbon::parse($request->as_of_date)
            : now();

        // Find period for the date
        $period = $request->filled('period_id')
            ? AccountingPeriod::find($request->period_id)
            : AccountingPeriod::where('start_date', '<=', $asOfDate)
                ->where('end_date', '>=', $asOfDate)
                ->first();

        if (!$period) {
            return redirect()->back()->with('error', 'No accounting period found for the selected date.');
        }

        $report = $this->reportService->generateTrialBalance($asOfDate->format('Y-m-d'), false, $period->fiscal_year_id);
        $periods = AccountingPeriod::orderBy('start_date', 'desc')->get();

        if ($request->has('export')) {
            if ($request->export === 'pdf') {
                $pdf = Pdf::loadView('accounting.reports.pdf.trial-balance', compact('report', 'period', 'asOfDate'));
                return $pdf->download("trial-balance-{$asOfDate->format('Y-m-d')}.pdf");
            }
            if ($request->export === 'excel') {
                return $this->excelService->trialBalance($report, $asOfDate);
            }
        }

        return view('accounting.reports.trial-balance', compact('report', 'period', 'asOfDate', 'periods'));
    }

    /**
     * Profit & Loss (Income Statement) Report.
     */
    public function profitAndLoss(Request $request)
    {
        $request->validate([
            'period_id' => 'nullable|exists:accounting_periods,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // Determine date range
        if ($request->filled('period_id')) {
            $period = AccountingPeriod::findOrFail($request->period_id);
            $startDate = $period->start_date;
            $endDate = $period->end_date;
        } else {
            $startDate = $request->filled('start_date')
                ? Carbon::parse($request->start_date)
                : now()->startOfMonth();
            $endDate = $request->filled('end_date')
                ? Carbon::parse($request->end_date)
                : now();
        }

        $report = $this->reportService->generateProfitAndLoss($startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
        $periods = AccountingPeriod::orderBy('start_date', 'desc')->get();
        $fiscalPeriods = $periods; // Alias for view compatibility

        if ($request->has('export')) {
            if ($request->export === 'pdf') {
                $pdf = Pdf::loadView('accounting.reports.pdf.profit-loss', compact('report', 'startDate', 'endDate'));
                return $pdf->download("profit-loss-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.pdf");
            }
            if ($request->export === 'excel') {
                return $this->excelService->profitAndLoss($report, $startDate, $endDate);
            }
        }

        return view('accounting.reports.profit-loss', compact('report', 'startDate', 'endDate', 'periods', 'fiscalPeriods'));
    }

    /**
     * Balance Sheet Report.
     */
    public function balanceSheet(Request $request)
    {
        $request->validate([
            'as_of_date' => 'nullable|date',
        ]);

        $asOfDate = $request->filled('as_of_date')
            ? Carbon::parse($request->as_of_date)
            : now();

        $report = $this->reportService->generateBalanceSheet($asOfDate->format('Y-m-d'));
        $fiscalPeriods = AccountingPeriod::orderBy('start_date', 'desc')->get();

        if ($request->has('export')) {
            if ($request->export === 'pdf') {
                $pdf = Pdf::loadView('accounting.reports.pdf.balance-sheet', compact('report', 'asOfDate'));
                return $pdf->download("balance-sheet-{$asOfDate->format('Y-m-d')}.pdf");
            }
            if ($request->export === 'excel') {
                return $this->excelService->balanceSheet($report, $asOfDate);
            }
        }

        return view('accounting.reports.balance-sheet', compact('report', 'asOfDate', 'fiscalPeriods'));
    }

    /**
     * Cash Flow Statement Report.
     */
    public function cashFlow(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->start_date)
            : now()->startOfMonth();
        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->end_date)
            : now();

        $report = $this->reportService->generateCashFlowStatement($startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
        $fiscalPeriods = AccountingPeriod::orderBy('start_date', 'desc')->get();

        if ($request->has('export')) {
            if ($request->export === 'pdf') {
                $pdf = Pdf::loadView('accounting.reports.pdf.cash-flow', compact('report', 'startDate', 'endDate'));
                return $pdf->download("cash-flow-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.pdf");
            }
            if ($request->export === 'excel') {
                return $this->excelService->cashFlow($report, $startDate, $endDate);
            }
        }

        return view('accounting.reports.cash-flow', compact('report', 'startDate', 'endDate', 'fiscalPeriods'));
    }

    /**
     * General Ledger Report.
     */
    public function generalLedger(Request $request)
    {
        $request->validate([
            'account_id' => 'nullable|exists:accounts,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->start_date)
            : now()->startOfMonth();
        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->end_date)
            : now();

        $accountId = $request->account_id;
        $accounts = Account::where('is_active', true)->orderBy('code')->get();
        $fiscalPeriods = AccountingPeriod::orderBy('start_date', 'desc')->get();

        $report = null;
        $ledgerData = [];
        $selectedAccount = null;

        if ($accountId) {
            // Single account selected
            $selectedAccount = Account::findOrFail($accountId);
            $report = $this->reportService->generateGeneralLedger($accountId, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
            $ledgerData = [$report]; // Wrap in array for view iteration
        } else {
            // All accounts - get accounts with activity in the date range
            $accountsWithActivity = Account::where('is_active', true)
                ->whereHas('journalLines', function ($q) use ($startDate, $endDate) {
                    $q->whereHas('journalEntry', function ($q2) use ($startDate, $endDate) {
                        $q2->where('status', \App\Models\Accounting\JournalEntry::STATUS_POSTED)
                            ->whereBetween('entry_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
                    });
                })
                ->orderBy('code')
                ->get();

            foreach ($accountsWithActivity as $account) {
                $ledgerData[] = $this->reportService->generateGeneralLedger($account->id, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
            }
        }

        // Handle exports
        if ($request->has('export') && !empty($ledgerData)) {
            if ($request->export === 'pdf') {
                $pdf = Pdf::loadView('accounting.reports.pdf.general-ledger', compact('ledgerData', 'selectedAccount', 'startDate', 'endDate'));
                $filename = $selectedAccount
                    ? "general-ledger-{$selectedAccount->code}-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.pdf"
                    : "general-ledger-all-accounts-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.pdf";
                return $pdf->download($filename);
            }
            if ($request->export === 'excel') {
                return $this->excelService->generalLedger($ledgerData, $startDate, $endDate);
            }
        }

        return view('accounting.reports.general-ledger', compact('report', 'ledgerData', 'selectedAccount', 'accounts', 'startDate', 'endDate', 'fiscalPeriods'));
    }

    /**
     * Account Activity Report (detailed transactions for an account).
     */
    public function accountActivity(Request $request)
    {
        $request->validate([
            'account_id' => 'nullable|exists:accounts,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $accounts = Account::where('is_active', true)->orderBy('code')->get();

        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->start_date)
            : now()->startOfMonth();
        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->end_date)
            : now();

        $account = null;
        $activity = null;

        // Only fetch data if account is selected
        if ($request->filled('account_id')) {
            $account = Account::findOrFail($request->account_id);
            $activity = $this->reportService->generateGeneralLedger($account->id, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));

            // Handle exports
            if ($request->has('export')) {
                $ledgerDataForExport = [$activity]; // Wrap for view compatibility
                if ($request->export === 'pdf') {
                    $ledgerData = $ledgerDataForExport;
                    $selectedAccount = $account;
                    $pdf = Pdf::loadView('accounting.reports.pdf.general-ledger', compact('ledgerData', 'selectedAccount', 'startDate', 'endDate'));
                    return $pdf->download("account-activity-{$account->code}-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.pdf");
                }
                if ($request->export === 'excel') {
                    return $this->excelService->generalLedger($ledgerDataForExport, $startDate, $endDate);
                }
            }
        }

        return view('accounting.reports.account-activity', compact('account', 'activity', 'startDate', 'endDate', 'accounts'));
    }

    /**
     * Aged Receivables Report - Comprehensive view.
     *
     * Includes:
     * - Patient overdrafts (patients who owe hospital)
     * - HMO claims validated but no remittance
     * - GL Accounts Receivable
     */
    public function agedReceivables(Request $request)
    {
        $request->validate([
            'as_of_date' => 'nullable|date',
            'hmo_id' => 'nullable|integer|exists:hmos,id',
            'receivable_type' => 'nullable|in:all,patient_overdrafts,hmo_claims,gl_receivables',
            'min_amount' => 'nullable|numeric|min:0',
        ]);

        $asOfDate = $request->filled('as_of_date')
            ? Carbon::parse($request->as_of_date)
            : now();

        // Build filters from request
        $filters = array_filter([
            'hmo_id' => $request->hmo_id,
            'receivable_type' => $request->receivable_type,
            'min_amount' => $request->min_amount,
        ]);

        $report = $this->reportService->getAgedReceivables($asOfDate->format('Y-m-d'), $filters);
        $fiscalPeriods = AccountingPeriod::orderBy('start_date', 'desc')->get();

        // Get filter options
        $hmos = \App\Models\Hmo::orderBy('name')->get(['id', 'name']);

        if ($request->has('export')) {
            if ($request->export === 'pdf') {
                $pdf = Pdf::loadView('accounting.reports.pdf.aged-receivables', compact('report', 'asOfDate'));
                return $pdf->download("aged-receivables-{$asOfDate->format('Y-m-d')}.pdf");
            }
            if ($request->export === 'excel') {
                return $this->excelService->agedReceivables($report, $asOfDate);
            }
        }

        return view('accounting.reports.aged-receivables', compact('report', 'asOfDate', 'fiscalPeriods', 'hmos', 'filters'));
    }

    /**
     * Aged Payables Report - Comprehensive view.
     *
     * Includes:
     * - Supplier POs received but not paid
     * - Patient deposits (hospital liability)
     * - Supplier credit balances
     * - GL Accounts Payable
     */
    public function agedPayables(Request $request)
    {
        $request->validate([
            'as_of_date' => 'nullable|date',
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
            'payable_type' => 'nullable|in:all,supplier_payables,patient_deposits,supplier_credits,gl_payables',
            'min_amount' => 'nullable|numeric|min:0',
        ]);

        $asOfDate = $request->filled('as_of_date')
            ? Carbon::parse($request->as_of_date)
            : now();

        // Build filters from request
        $filters = array_filter([
            'supplier_id' => $request->supplier_id,
            'payable_type' => $request->payable_type,
            'min_amount' => $request->min_amount,
        ]);

        $report = $this->reportService->getAgedPayables($asOfDate->format('Y-m-d'), $filters);
        $fiscalPeriods = AccountingPeriod::orderBy('start_date', 'desc')->get();

        // Get filter options
        $suppliers = \App\Models\Supplier::orderBy('company_name')->get(['id', 'company_name']);

        if ($request->has('export')) {
            if ($request->export === 'pdf') {
                $pdf = Pdf::loadView('accounting.reports.pdf.aged-payables', compact('report', 'asOfDate'));
                return $pdf->download("aged-payables-{$asOfDate->format('Y-m-d')}.pdf");
            }
            if ($request->export === 'excel') {
                return $this->excelService->agedPayables($report, $asOfDate);
            }
        }

        return view('accounting.reports.aged-payables', compact('report', 'asOfDate', 'fiscalPeriods', 'suppliers', 'filters'));
    }

    /**
     * Daily Audit Report.
     */
    public function dailyAudit(Request $request)
    {
        $request->validate([
            'date' => 'nullable|date',
        ]);

        $date = $request->filled('date')
            ? Carbon::parse($request->date)
            : now();

        // Get all journal entries for the selected date
        $entries = \App\Models\Accounting\JournalEntry::with(['lines.account', 'createdBy', 'approvedBy', 'postedBy'])
            ->whereDate('entry_date', $date)
            ->orderBy('created_at')
            ->get();

        // Calculate summary stats
        $stats = [
            'total_entries' => $entries->count(),
            'posted_entries' => $entries->where('status', 'posted')->count(),
            'pending_entries' => $entries->whereIn('status', ['draft', 'pending', 'approved'])->count(),
            'total_debits' => $entries->where('status', 'posted')->flatMap->lines->sum('debit'),
            'total_credits' => $entries->where('status', 'posted')->flatMap->lines->sum('credit'),
            'by_type' => $entries->groupBy('entry_type')->map->count(),
            'by_user' => $entries->groupBy(function($entry) {
                return $entry->createdBy?->name ?? 'System';
            })->map->count(),
        ];

        if ($request->has('export')) {
            if ($request->export === 'pdf') {
                $pdf = Pdf::loadView('accounting.reports.pdf.daily-audit', compact('entries', 'stats', 'date'));
                return $pdf->download("daily-audit-{$date->format('Y-m-d')}.pdf");
            }
            if ($request->export === 'excel') {
                return $this->excelService->dailyAudit($entries, $stats, $date);
            }
        }

        return view('accounting.reports.daily-audit', compact('entries', 'stats', 'date'));
    }

    /**
     * Save report filter configuration.
     */
    public function saveFilter(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'report_type' => 'required|string|max:50',
            'filters' => 'required|array',
            'is_shared' => 'boolean',
        ]);

        $filter = SavedReportFilter::create([
            'created_by' => Auth::id(),
            'name' => $request->name,
            'report_type' => $request->report_type,
            'filters' => $request->filters,
            'is_shared' => $request->boolean('is_shared'),
        ]);

        return response()->json([
            'success' => true,
            'filter' => $filter,
            'message' => 'Filter saved successfully.',
        ]);
    }

    /**
     * Delete a saved filter.
     */
    public function deleteFilter($id)
    {
        $filter = SavedReportFilter::where('id', $id)
            ->where('created_by', Auth::id())
            ->firstOrFail();

        $filter->delete();

        return response()->json([
            'success' => true,
            'message' => 'Filter deleted.',
        ]);
    }

    /**
     * Load a saved filter.
     */
    public function loadFilter($id)
    {
        $filter = SavedReportFilter::where('id', $id)
            ->where(function ($q) {
                $q->where('created_by', Auth::id())
                    ->orWhere('is_shared', true);
            })
            ->firstOrFail();

        // Update last_used_at
        $filter->update(['last_used_at' => now()]);

        return response()->json([
            'success' => true,
            'filter' => $filter,
        ]);
    }

    /**
     * Bank Statement Report - Detailed bank account transactions.
     */
    public function bankStatement(Request $request)
    {
        $request->validate([
            'bank_id' => 'nullable|exists:banks,id',
            'bank_account_id' => 'nullable|exists:accounts,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'min_amount' => 'nullable|numeric',
            'max_amount' => 'nullable|numeric',
            'transaction_type' => 'nullable|in:all,deposits,withdrawals',
            'reconciliation_status' => 'nullable|in:all,reconciled,unreconciled',
        ]);

        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->start_date)
            : now()->startOfMonth();
        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->end_date)
            : now();

        // Get all banks
        $banks = \App\Models\Bank::where('is_active', true)
            ->orderBy('name')
            ->get();

        // Get bank accounts with bank relationship
        $bankAccountsQuery = Account::with('bank', 'accountGroup')
            ->where('is_bank_account', true)
            ->where('is_active', true);

        // Filter by bank if selected
        if ($request->filled('bank_id')) {
            $bankAccountsQuery->where('bank_id', $request->bank_id);
        }

        $bankAccounts = $bankAccountsQuery->orderBy('code')->get();

        $selectedAccount = null;
        $selectedBank = null;
        $statement = null;

        if ($request->filled('bank_account_id')) {
            $selectedAccount = Account::with('bank', 'accountGroup')->findOrFail($request->bank_account_id);
            $selectedBank = $selectedAccount->bank;

            // Get bank statement data from report service
            $statement = $this->reportService->generateGeneralLedger(
                $selectedAccount->id,
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            );

            // Apply additional filters
            if ($request->filled('min_amount') || $request->filled('max_amount') ||
                $request->filled('transaction_type') || $request->filled('reconciliation_status')) {

                $statement['transactions'] = collect($statement['transactions'])->filter(function ($txn) use ($request) {
                    // Amount filters
                    if ($request->filled('min_amount')) {
                        $amount = max($txn['debit'], $txn['credit']);
                        if ($amount < $request->min_amount) return false;
                    }
                    if ($request->filled('max_amount')) {
                        $amount = max($txn['debit'], $txn['credit']);
                        if ($amount > $request->max_amount) return false;
                    }

                    // Transaction type filter
                    if ($request->filled('transaction_type') && $request->transaction_type != 'all') {
                        if ($request->transaction_type == 'deposits' && $txn['debit'] == 0) return false;
                        if ($request->transaction_type == 'withdrawals' && $txn['credit'] == 0) return false;
                    }

                    return true;
                })->values()->all();

                // Recalculate totals after filtering
                $statement['total_debit'] = collect($statement['transactions'])->sum('debit');
                $statement['total_credit'] = collect($statement['transactions'])->sum('credit');
            }

            // Handle exports
            if ($request->has('export')) {
                $exportData = [
                    'account' => $selectedAccount,
                    'bank' => $selectedBank,
                    'transactions' => $statement['transactions'],
                    'opening_balance' => $statement['opening_balance'],
                    'closing_balance' => $statement['closing_balance'],
                    'total_deposits' => $statement['total_debit'],
                    'total_withdrawals' => $statement['total_credit'],
                ];

                if ($request->export === 'pdf') {
                    $pdf = Pdf::loadView('accounting.reports.pdf.bank-statement', compact('exportData', 'startDate', 'endDate'))
                        ->setPaper('a4', 'portrait')
                        ->setOption('margin-top', '10mm')
                        ->setOption('margin-bottom', '10mm')
                        ->setOption('margin-left', '10mm')
                        ->setOption('margin-right', '10mm');
                    return $pdf->download("bank-statement-{$selectedAccount->code}-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.pdf");
                }

                if ($request->export === 'excel') {
                    return $this->excelService->bankStatement($exportData, $startDate, $endDate);
                }
            }
        }

        return view('accounting.reports.bank-statement', compact(
            'banks',
            'bankAccounts',
            'selectedAccount',
            'selectedBank',
            'statement',
            'startDate',
            'endDate'
        ));
    }
}
