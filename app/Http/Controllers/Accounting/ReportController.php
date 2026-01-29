<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\Account;
use App\Models\Accounting\SavedReportFilter;
use App\Services\Accounting\ReportService;
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

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;

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

        if ($request->has('export') && $request->export === 'pdf') {
            $pdf = Pdf::loadView('accounting.reports.trial-balance-pdf', compact('report', 'period', 'asOfDate'));
            return $pdf->download("trial-balance-{$asOfDate->format('Y-m-d')}.pdf");
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

        if ($request->has('export') && $request->export === 'pdf') {
            $pdf = Pdf::loadView('accounting.reports.profit-loss-pdf', compact('report', 'startDate', 'endDate'));
            return $pdf->download("profit-loss-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.pdf");
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

        if ($request->has('export') && $request->export === 'pdf') {
            $pdf = Pdf::loadView('accounting.reports.balance-sheet-pdf', compact('report', 'asOfDate'));
            return $pdf->download("balance-sheet-{$asOfDate->format('Y-m-d')}.pdf");
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

        if ($request->has('export') && $request->export === 'pdf') {
            $pdf = Pdf::loadView('accounting.reports.cash-flow-pdf', compact('report', 'startDate', 'endDate'));
            return $pdf->download("cash-flow-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.pdf");
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
            $selectedAccount = Account::findOrFail($accountId);
            $report = $this->reportService->getGeneralLedger($accountId, $startDate, $endDate);
            $ledgerData = $report; // Alias for view compatibility

            if ($request->has('export') && $request->export === 'pdf') {
                $pdf = Pdf::loadView('accounting.reports.general-ledger-pdf', compact('report', 'selectedAccount', 'startDate', 'endDate'));
                return $pdf->download("general-ledger-{$selectedAccount->account_code}-{$startDate->format('Y-m-d')}-to-{$endDate->format('Y-m-d')}.pdf");
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
            'account_id' => 'required|exists:accounts,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $account = Account::findOrFail($request->account_id);
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->start_date)
            : now()->startOfMonth();
        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->end_date)
            : now();

        $activity = $this->reportService->getGeneralLedger($account->id, $startDate, $endDate);

        return view('accounting.reports.account-activity', compact('account', 'activity', 'startDate', 'endDate'));
    }

    /**
     * Aged Receivables Report.
     */
    public function agedReceivables(Request $request)
    {
        $request->validate([
            'as_of_date' => 'nullable|date',
        ]);

        $asOfDate = $request->filled('as_of_date')
            ? Carbon::parse($request->as_of_date)
            : now();

        $report = $this->reportService->getAgedReceivables($asOfDate->format('Y-m-d'));
        $fiscalPeriods = AccountingPeriod::orderBy('start_date', 'desc')->get();

        if ($request->has('export') && $request->export === 'pdf') {
            $pdf = Pdf::loadView('accounting.reports.aged-receivables-pdf', compact('report', 'asOfDate'));
            return $pdf->download("aged-receivables-{$asOfDate->format('Y-m-d')}.pdf");
        }

        return view('accounting.reports.aged-receivables', compact('report', 'asOfDate', 'fiscalPeriods'));
    }

    /**
     * Aged Payables Report.
     */
    public function agedPayables(Request $request)
    {
        $request->validate([
            'as_of_date' => 'nullable|date',
        ]);

        $asOfDate = $request->filled('as_of_date')
            ? Carbon::parse($request->as_of_date)
            : now();

        $report = $this->reportService->getAgedPayables($asOfDate->format('Y-m-d'));
        $fiscalPeriods = AccountingPeriod::orderBy('start_date', 'desc')->get();

        if ($request->has('export') && $request->export === 'pdf') {
            $pdf = Pdf::loadView('accounting.reports.aged-payables-pdf', compact('report', 'asOfDate'));
            return $pdf->download("aged-payables-{$asOfDate->format('Y-m-d')}.pdf");
        }

        return view('accounting.reports.aged-payables', compact('report', 'asOfDate', 'fiscalPeriods'));
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

        if ($request->has('export') && $request->export === 'pdf') {
            $pdf = Pdf::loadView('accounting.reports.pdf.daily-audit', compact('entries', 'stats', 'date'));
            return $pdf->download("daily-audit-{$date->format('Y-m-d')}.pdf");
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
}
