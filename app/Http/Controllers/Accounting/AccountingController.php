<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Account;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * Accounting Dashboard Controller
 *
 * Reference: Accounting System Plan §7.1 - Controllers
 *
 * Handles the main accounting dashboard and overview pages.
 */
class AccountingController extends Controller
{
    protected AccountingService $accountingService;
    protected ReportService $reportService;

    public function __construct(AccountingService $accountingService, ReportService $reportService)
    {
        $this->accountingService = $accountingService;
        $this->reportService = $reportService;

        // Permission middleware (§7.6 - Access Control)
        $this->middleware('permission:journal.view')->only(['index']);
        $this->middleware('permission:periods.view')->only(['periods']);
        $this->middleware('permission:periods.create')->only(['createFiscalYear']);
        $this->middleware('permission:periods.close')->only(['closePeriod', 'closeFiscalYear']);
    }

    /**
     * Display the main accounting dashboard.
     */
    public function index(Request $request)
    {
        $currentPeriod = $this->accountingService->getCurrentPeriod();
        $currentYear = FiscalYear::where('status', 'open')->first();

        // Get summary statistics
        $stats = $this->getDashboardStats($currentPeriod);

        // Get recent journal entries
        $recentEntries = JournalEntry::with(['createdBy', 'lines.account'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Get pending entries for approval
        $pendingEntries = JournalEntry::with(['createdBy'])
            ->where('status', JournalEntry::STATUS_PENDING)
            ->orderBy('created_at', 'asc')
            ->limit(5)
            ->get();

        // Quick trial balance for dashboard
        $trialBalance = null;
        if ($currentPeriod) {
            $trialBalance = $this->reportService->generateTrialBalance($currentPeriod->end_date->toDateString());
        }

        return view('accounting.dashboard', compact(
            'currentPeriod',
            'currentYear',
            'stats',
            'recentEntries',
            'pendingEntries',
            'trialBalance'
        ));
    }

    /**
     * Get dashboard statistics.
     */
    protected function getDashboardStats(?AccountingPeriod $period): array
    {
        $stats = [
            'total_entries' => 0,
            'pending_entries' => 0,
            'posted_entries' => 0,
            'total_debits' => 0,
            'total_credits' => 0,
            'monthly_revenue' => 0,
            'monthly_expenses' => 0,
        ];

        if (!$period) {
            return $stats;
        }

        // Entry counts
        $stats['total_entries'] = JournalEntry::whereBetween('entry_date', [
            $period->start_date,
            $period->end_date
        ])->count();

        $stats['pending_entries'] = JournalEntry::where('status', JournalEntry::STATUS_PENDING)->count();

        $stats['posted_entries'] = JournalEntry::where('status', JournalEntry::STATUS_POSTED)
            ->whereBetween('entry_date', [$period->start_date, $period->end_date])
            ->count();

        // Calculate totals from posted entries
        $posted = JournalEntry::with('lines')
            ->where('status', JournalEntry::STATUS_POSTED)
            ->whereBetween('entry_date', [$period->start_date, $period->end_date])
            ->get();

        foreach ($posted as $entry) {
            foreach ($entry->lines as $line) {
                $stats['total_debits'] += $line->debit;
                $stats['total_credits'] += $line->credit;
            }
        }

        // Monthly revenue (Income accounts - class 4)
        $incomeAccounts = Account::whereHas('accountGroup.accountClass', function ($q) {
            $q->where('code', '4');
        })->pluck('id');

        $stats['monthly_revenue'] = \DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entry_lines.account_id', $incomeAccounts)
            ->where('journal_entries.status', JournalEntry::STATUS_POSTED)
            ->whereBetween('journal_entries.entry_date', [$period->start_date, $period->end_date])
            ->sum('journal_entry_lines.credit');

        // Monthly expenses (Expense accounts - class 5 and 6)
        $expenseAccounts = Account::whereHas('accountGroup.accountClass', function ($q) {
            $q->whereIn('code', ['5', '6']);
        })->pluck('id');

        $stats['monthly_expenses'] = \DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->whereIn('journal_entry_lines.account_id', $expenseAccounts)
            ->where('journal_entries.status', JournalEntry::STATUS_POSTED)
            ->whereBetween('journal_entries.entry_date', [$period->start_date, $period->end_date])
            ->sum('journal_entry_lines.debit');

        return $stats;
    }

    /**
     * Period management page.
     */
    public function periods(Request $request)
    {
        $fiscalYears = FiscalYear::with('periods')
            ->orderBy('start_date', 'desc')
            ->get();

        return view('accounting.periods.index', compact('fiscalYears'));
    }

    /**
     * Create a new fiscal year.
     */
    public function createFiscalYear(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $fiscalYear = $this->accountingService->createFiscalYear(
            $request->name,
            Carbon::parse($request->start_date),
            Carbon::parse($request->end_date)
        );

        return redirect()->route('accounting.periods')
            ->with('success', "Fiscal year '{$fiscalYear->name}' created with monthly periods.");
    }

    /**
     * Close an accounting period.
     */
    public function closePeriod(Request $request, $periodId)
    {
        $period = AccountingPeriod::findOrFail($periodId);

        try {
            $this->accountingService->closePeriod($period);
            return redirect()->back()->with('success', "Period '{$period->name}' has been closed.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Close a fiscal year.
     */
    public function closeFiscalYear(Request $request, $yearId)
    {
        $fiscalYear = FiscalYear::findOrFail($yearId);

        if (!$fiscalYear->retained_earnings_account_id) {
            return redirect()->back()->with('error', 'Please set a retained earnings account before closing the year.');
        }

        try {
            $this->accountingService->closeYear($fiscalYear);
            return redirect()->back()->with('success', "Fiscal year '{$fiscalYear->name}' has been closed.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
