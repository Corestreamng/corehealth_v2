<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Accounting\CostCenter;
use App\Models\Accounting\CostCenterBudget;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\FiscalYear;
use App\Models\Department;
use App\Models\User;
use App\Services\Accounting\ExcelExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

/**
 * Cost Center Controller
 *
 * Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.11
 * Access: SUPERADMIN|ADMIN|ACCOUNTS
 *
 * Manages cost centers for tracking expenses and budgets by department/project.
 */
class CostCenterController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:SUPERADMIN|ADMIN|ACCOUNTS');
    }

    /**
     * Display cost centers dashboard.
     */
    public function index(Request $request)
    {
        $stats = $this->getDashboardStats();

        $centerTypes = [
            CostCenter::TYPE_REVENUE => 'Revenue Center',
            CostCenter::TYPE_COST => 'Cost Center',
            CostCenter::TYPE_SERVICE => 'Service Center',
            CostCenter::TYPE_PROJECT => 'Project',
        ];

        $departments = Department::orderBy('name')->get();

        return view('accounting.cost-centers.index', compact('stats', 'centerTypes', 'departments'));
    }

    /**
     * Get dashboard statistics.
     */
    protected function getDashboardStats(): array
    {
        $currentYear = now()->year;
        $currentMonth = now()->format('Y-m');
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();
        $startOfYear = now()->startOfYear()->toDateString();
        $endOfYear = now()->endOfYear()->toDateString();

        // Active cost centers
        $totalCenters = CostCenter::where('is_active', true)->count();

        // By type
        $byType = CostCenter::where('is_active', true)
            ->select('center_type', DB::raw('COUNT(*) as count'))
            ->groupBy('center_type')
            ->pluck('count', 'center_type')
            ->toArray();

        // MTD and YTD expenses
        $mtdExpenses = JournalEntryLine::whereNotNull('cost_center_id')
            ->whereHas('journalEntry', fn($q) => $q
                ->where('status', JournalEntry::STATUS_POSTED)
                ->whereBetween('entry_date', [$startOfMonth, $endOfMonth]))
            ->whereHas('account.accountGroup.accountClass', fn($q) => $q->where('name', 'EXPENSE'))
            ->sum('debit');

        $ytdExpenses = JournalEntryLine::whereNotNull('cost_center_id')
            ->whereHas('journalEntry', fn($q) => $q
                ->where('status', JournalEntry::STATUS_POSTED)
                ->whereBetween('entry_date', [$startOfYear, $endOfYear]))
            ->whereHas('account.accountGroup.accountClass', fn($q) => $q->where('name', 'EXPENSE'))
            ->sum('debit');

        // Top cost centers by expense
        $topCenters = CostCenter::where('is_active', true)
            ->withSum(['journalEntryLines' => function ($q) use ($startOfYear, $endOfYear) {
                $q->whereHas('journalEntry', fn($je) => $je
                    ->where('status', JournalEntry::STATUS_POSTED)
                    ->whereBetween('entry_date', [$startOfYear, $endOfYear]))
                  ->whereHas('account.accountGroup.accountClass', fn($a) => $a->where('name', 'EXPENSE'));
            }], 'debit')
            ->orderByDesc('journal_entry_lines_sum_debit')
            ->limit(5)
            ->get();

        // Budget utilization
        $currentFiscalYear = FiscalYear::where('status', 'open')->first();
        $budgetData = [
            'total_budget' => 0,
            'utilized' => 0,
            'remaining' => 0,
        ];

        if ($currentFiscalYear) {
            $budgetData['total_budget'] = CostCenterBudget::where('fiscal_year_id', $currentFiscalYear->id)
                ->sum('budgeted_amount');
            $budgetData['utilized'] = $ytdExpenses;
            $budgetData['remaining'] = max(0, $budgetData['total_budget'] - $budgetData['utilized']);
        }

        return [
            'total_centers' => $totalCenters,
            'by_type' => $byType,
            'mtd_expenses' => $mtdExpenses,
            'ytd_expenses' => $ytdExpenses,
            'top_centers' => $topCenters,
            'budget_data' => $budgetData,
        ];
    }

    /**
     * DataTables server-side processing.
     */
    public function datatable(Request $request)
    {
        $query = CostCenter::with(['department', 'manager', 'parent'])
            ->select('cost_centers.*');

        // Apply filters
        if ($request->filled('center_type')) {
            $query->where('center_type', $request->center_type);
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->active == '1');
        }

        // Calculate YTD expenses for each center
        $startOfYear = now()->startOfYear()->toDateString();
        $endOfYear = now()->endOfYear()->toDateString();

        return DataTables::of($query)
            ->addColumn('department_name', fn($c) => $c->department?->name ?? 'N/A')
            ->addColumn('manager_name', fn($c) => $c->manager ? ($c->manager->surname . ' ' . $c->manager->firstname) : 'N/A')
            ->addColumn('parent_name', fn($c) => $c->parent?->name ?? '-')
            ->addColumn('ytd_expenses', function ($c) use ($startOfYear, $endOfYear) {
                return $c->getExpensesForPeriod($startOfYear, $endOfYear);
            })
            ->addColumn('type_badge', function ($c) {
                $colors = [
                    'revenue' => 'success',
                    'cost' => 'primary',
                    'service' => 'info',
                    'project' => 'warning',
                ];
                return '<span class="badge badge-' . ($colors[$c->center_type] ?? 'secondary') . '">'
                    . ucfirst($c->center_type) . '</span>';
            })
            ->addColumn('status_badge', function ($c) {
                return $c->is_active
                    ? '<span class="badge badge-success">Active</span>'
                    : '<span class="badge badge-secondary">Inactive</span>';
            })
            ->addColumn('actions', function ($c) {
                $actions = '<div class="btn-group btn-group-sm">';
                $actions .= '<a href="' . route('accounting.cost-centers.show', $c) . '" class="btn btn-info" title="View"><i class="mdi mdi-eye"></i></a>';
                $actions .= '<a href="' . route('accounting.cost-centers.edit', $c) . '" class="btn btn-warning" title="Edit"><i class="mdi mdi-pencil"></i></a>';
                $actions .= '<a href="' . route('accounting.cost-centers.report', $c) . '" class="btn btn-primary" title="Report"><i class="mdi mdi-file-chart"></i></a>';
                $actions .= '</div>';
                return $actions;
            })
            ->rawColumns(['type_badge', 'status_badge', 'actions'])
            ->make(true);
    }

    /**
     * Show form for creating new cost center.
     */
    public function create()
    {
        $centerTypes = [
            CostCenter::TYPE_REVENUE => 'Revenue Center',
            CostCenter::TYPE_COST => 'Cost Center',
            CostCenter::TYPE_SERVICE => 'Service Center',
            CostCenter::TYPE_PROJECT => 'Project',
        ];

        $departments = Department::orderBy('name')->get();
        $managers = User::where('status', 1)->orderBy('surname')->get(['id', 'surname', 'firstname', 'othername', 'email']);
        $parentCenters = CostCenter::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']);

        return view('accounting.cost-centers.create', compact('centerTypes', 'departments', 'managers', 'parentCenters'));
    }

    /**
     * Store new cost center.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:20|unique:cost_centers,code',
            'name' => 'required|string|max:100',
            'center_type' => 'required|in:' . implode(',', [
                CostCenter::TYPE_REVENUE,
                CostCenter::TYPE_COST,
                CostCenter::TYPE_SERVICE,
                CostCenter::TYPE_PROJECT,
            ]),
            'department_id' => 'nullable|exists:departments,id',
            'manager_user_id' => 'nullable|exists:users,id',
            'parent_cost_center_id' => 'nullable|exists:cost_centers,id',
            'description' => 'nullable|string|max:500',
        ]);

        // Calculate hierarchy level
        $hierarchyLevel = 1;
        if (!empty($validated['parent_cost_center_id'])) {
            $parent = CostCenter::find($validated['parent_cost_center_id']);
            $hierarchyLevel = $parent->hierarchy_level + 1;
        }

        $validated['hierarchy_level'] = $hierarchyLevel;
        $validated['is_active'] = true;

        $costCenter = CostCenter::create($validated);

        return redirect()
            ->route('accounting.cost-centers.show', $costCenter)
            ->with('success', "Cost center '{$costCenter->name}' created successfully.");
    }

    /**
     * Display cost center details.
     */
    public function show(CostCenter $costCenter)
    {
        $costCenter->load(['department', 'manager', 'parent', 'children', 'budgets.fiscalYear']);

        // Get period data
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();
        $startOfYear = now()->startOfYear()->toDateString();
        $endOfYear = now()->endOfYear()->toDateString();

        $mtdExpenses = $costCenter->getExpensesForPeriod($startOfMonth, $endOfMonth);
        $ytdExpenses = $costCenter->getExpensesForPeriod($startOfYear, $endOfYear);
        $mtdRevenue = $costCenter->getRevenueForPeriod($startOfMonth, $endOfMonth);
        $ytdRevenue = $costCenter->getRevenueForPeriod($startOfYear, $endOfYear);

        // Recent transactions
        $recentTransactions = JournalEntryLine::where('cost_center_id', $costCenter->id)
            ->with(['journalEntry', 'account'])
            ->whereHas('journalEntry', fn($q) => $q->where('status', JournalEntry::STATUS_POSTED))
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Budget comparison
        $currentFiscalYear = FiscalYear::where('status', 'open')->first();
        $budget = $currentFiscalYear
            ? CostCenterBudget::where('cost_center_id', $costCenter->id)
                ->where('fiscal_year_id', $currentFiscalYear->id)
                ->first()
            : null;

        // Build periodData array for view compatibility
        $periodData = [
            'mtd_revenue' => $mtdRevenue,
            'mtd_expenses' => $mtdExpenses,
            'ytd_revenue' => $ytdRevenue,
            'ytd_expenses' => $ytdExpenses,
            'budget' => $budget ? $budget->budgeted_amount : 0,
            'transaction_count' => $recentTransactions->count(),
        ];

        return view('accounting.cost-centers.show', compact(
            'costCenter',
            'periodData',
            'recentTransactions',
            'budget',
            'currentFiscalYear'
        ));
    }

    /**
     * Show form for editing cost center.
     */
    public function edit(CostCenter $costCenter)
    {
        $centerTypes = [
            CostCenter::TYPE_REVENUE => 'Revenue Center',
            CostCenter::TYPE_COST => 'Cost Center',
            CostCenter::TYPE_SERVICE => 'Service Center',
            CostCenter::TYPE_PROJECT => 'Project',
        ];

        $departments = Department::orderBy('name')->get();
        $managers = User::where('status', 1)->orderBy('surname')->get(['id', 'surname', 'firstname', 'othername', 'email']);
        $parentCenters = CostCenter::where('is_active', true)
            ->where('id', '!=', $costCenter->id)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return view('accounting.cost-centers.edit', compact('costCenter', 'centerTypes', 'departments', 'managers', 'parentCenters'));
    }

    /**
     * Update cost center.
     */
    public function update(Request $request, CostCenter $costCenter)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'department_id' => 'nullable|exists:departments,id',
            'manager_user_id' => 'nullable|exists:users,id',
            'parent_cost_center_id' => 'nullable|exists:cost_centers,id',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]);

        // Don't allow changing center_type or code after creation
        unset($validated['center_type']);
        unset($validated['code']);

        // Recalculate hierarchy level if parent changed
        if (isset($validated['parent_cost_center_id']) && $validated['parent_cost_center_id'] != $costCenter->parent_cost_center_id) {
            if ($validated['parent_cost_center_id']) {
                $parent = CostCenter::find($validated['parent_cost_center_id']);
                $validated['hierarchy_level'] = $parent->hierarchy_level + 1;
            } else {
                $validated['hierarchy_level'] = 1;
            }
        }

        $costCenter->update($validated);

        return redirect()
            ->route('accounting.cost-centers.show', $costCenter)
            ->with('success', 'Cost center updated successfully.');
    }

    /**
     * Show cost center report.
     */
    public function report(Request $request, CostCenter $costCenter)
    {
        $fromDate = $request->get('from_date', now()->startOfYear()->toDateString());
        $toDate = $request->get('to_date', now()->toDateString());

        // Get expenses by account
        $expensesByAccount = JournalEntryLine::where('cost_center_id', $costCenter->id)
            ->whereHas('journalEntry', fn($q) => $q
                ->where('status', JournalEntry::STATUS_POSTED)
                ->whereBetween('entry_date', [$fromDate, $toDate]))
            ->with('account')
            ->selectRaw('account_id,
                SUM(debit) as total_debit,
                SUM(credit) as total_credit')
            ->groupBy('account_id')
            ->get()
            ->map(function($item) {
                $item->account_code = $item->account->code ?? 'N/A';
                $item->account_name = $item->account->name ?? 'Unknown';
                return $item;
            });

        // Monthly trend - get both revenue and expenses
        $monthlyTrend = JournalEntryLine::where('cost_center_id', $costCenter->id)
            ->whereHas('journalEntry', fn($q) => $q
                ->where('status', JournalEntry::STATUS_POSTED)
                ->whereBetween('entry_date', [$fromDate, $toDate]))
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->join('account_groups', 'accounts.account_group_id', '=', 'account_groups.id')
            ->join('account_classes', 'account_groups.account_class_id', '=', 'account_classes.id')
            ->selectRaw("DATE_FORMAT(journal_entries.entry_date, '%Y-%m') as month,
                SUM(CASE WHEN account_classes.name = 'EXPENSE' THEN journal_entry_lines.debit ELSE 0 END) as expenses,
                SUM(CASE WHEN account_classes.name = 'INCOME' THEN journal_entry_lines.credit ELSE 0 END) as revenue")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Budget comparison
        $currentFiscalYear = FiscalYear::where('status', 'open')->first();
        $budget = $currentFiscalYear
            ? CostCenterBudget::where('cost_center_id', $costCenter->id)
                ->where('fiscal_year_id', $currentFiscalYear->id)
                ->first()
            : null;

        // Get all transactions for the period
        $transactions = JournalEntryLine::where('cost_center_id', $costCenter->id)
            ->whereHas('journalEntry', fn($q) => $q
                ->where('status', JournalEntry::STATUS_POSTED)
                ->whereBetween('entry_date', [$fromDate, $toDate]))
            ->with(['journalEntry', 'account'])
            ->orderBy('journal_entry_id', 'desc')
            ->get();

        // Calculate summary data
        $summary = [
            'total_revenue' => $costCenter->getRevenueForPeriod($fromDate, $toDate),
            'total_expenses' => $costCenter->getExpensesForPeriod($fromDate, $toDate),
            'transaction_count' => $transactions->count()
        ];

        return view('accounting.cost-centers.report', compact(
            'costCenter',
            'fromDate',
            'toDate',
            'expensesByAccount',
            'monthlyTrend',
            'budget',
            'summary',
            'transactions'
        ))->with([
            'startDate' => \Carbon\Carbon::parse($fromDate),
            'endDate' => \Carbon\Carbon::parse($toDate),
        ]);
    }

    /**     * Export cost center report to PDF.
     */
    public function exportReportPdf(Request $request, CostCenter $costCenter)
    {
        $fromDate = $request->get('from_date', now()->startOfYear()->toDateString());
        $toDate = $request->get('to_date', now()->toDateString());

        // Get data (same as report method)
        $expensesByAccount = JournalEntryLine::where('cost_center_id', $costCenter->id)
            ->whereHas('journalEntry', fn($q) => $q
                ->where('status', JournalEntry::STATUS_POSTED)
                ->whereBetween('entry_date', [$fromDate, $toDate]))
            ->with('account')
            ->selectRaw('account_id,
                SUM(debit) as total_debit,
                SUM(credit) as total_credit')
            ->groupBy('account_id')
            ->get()
            ->map(function($item) {
                $item->account_code = $item->account->code ?? 'N/A';
                $item->account_name = $item->account->name ?? 'Unknown';
                return $item;
            });

        $monthlyTrend = JournalEntryLine::where('cost_center_id', $costCenter->id)
            ->whereHas('journalEntry', fn($q) => $q
                ->where('status', JournalEntry::STATUS_POSTED)
                ->whereBetween('entry_date', [$fromDate, $toDate]))
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->join('account_groups', 'accounts.account_group_id', '=', 'account_groups.id')
            ->join('account_classes', 'account_groups.account_class_id', '=', 'account_classes.id')
            ->selectRaw("DATE_FORMAT(journal_entries.entry_date, '%Y-%m') as month,
                SUM(CASE WHEN account_classes.name = 'EXPENSE' THEN journal_entry_lines.debit ELSE 0 END) as expenses,
                SUM(CASE WHEN account_classes.name = 'INCOME' THEN journal_entry_lines.credit ELSE 0 END) as revenue")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $currentFiscalYear = FiscalYear::where('status', 'open')->first();
        $budget = $currentFiscalYear
            ? CostCenterBudget::where('cost_center_id', $costCenter->id)
                ->where('fiscal_year_id', $currentFiscalYear->id)
                ->first()
            : null;

        $transactions = JournalEntryLine::where('cost_center_id', $costCenter->id)
            ->whereHas('journalEntry', fn($q) => $q
                ->where('status', JournalEntry::STATUS_POSTED)
                ->whereBetween('entry_date', [$fromDate, $toDate]))
            ->with(['journalEntry', 'account'])
            ->orderBy('journal_entry_id', 'desc')
            ->get();

        $summary = [
            'total_revenue' => $costCenter->getRevenueForPeriod($fromDate, $toDate),
            'total_expenses' => $costCenter->getExpensesForPeriod($fromDate, $toDate),
            'transaction_count' => $transactions->count()
        ];

        $pdf = Pdf::loadView('accounting.cost-centers.report-pdf', compact(
            'costCenter',
            'fromDate',
            'toDate',
            'expensesByAccount',
            'monthlyTrend',
            'budget',
            'summary',
            'transactions'
        ));

        return $pdf->download('cost-center-report-' . $costCenter->code . '-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Export cost center report to Excel.
     */
    public function exportReportExcel(Request $request, CostCenter $costCenter, ExcelExportService $excelService)
    {
        $fromDate = $request->get('from_date', now()->startOfYear()->toDateString());
        $toDate = $request->get('to_date', now()->toDateString());

        // Get data
        $expensesByAccount = JournalEntryLine::where('cost_center_id', $costCenter->id)
            ->whereHas('journalEntry', fn($q) => $q
                ->where('status', JournalEntry::STATUS_POSTED)
                ->whereBetween('entry_date', [$fromDate, $toDate]))
            ->with('account')
            ->selectRaw('account_id,
                SUM(debit) as total_debit,
                SUM(credit) as total_credit')
            ->groupBy('account_id')
            ->get()
            ->map(function($item) {
                $item->account_code = $item->account->code ?? 'N/A';
                $item->account_name = $item->account->name ?? 'Unknown';
                return $item;
            });

        $transactions = JournalEntryLine::where('cost_center_id', $costCenter->id)
            ->whereHas('journalEntry', fn($q) => $q
                ->where('status', JournalEntry::STATUS_POSTED)
                ->whereBetween('entry_date', [$fromDate, $toDate]))
            ->with(['journalEntry', 'account'])
            ->orderBy('journal_entry_id', 'desc')
            ->get();

        $summary = [
            'total_revenue' => $costCenter->getRevenueForPeriod($fromDate, $toDate),
            'total_expenses' => $costCenter->getExpensesForPeriod($fromDate, $toDate),
            'transaction_count' => $transactions->count()
        ];

        return $excelService->exportCostCenterReport(
            $costCenter,
            $fromDate,
            $toDate,
            $expensesByAccount,
            $transactions,
            $summary
        );
    }

    /**     * Manage budgets for a cost center.
     */
    public function budgets(CostCenter $costCenter)
    {
        $budgets = CostCenterBudget::where('cost_center_id', $costCenter->id)
            ->with(['fiscalYear', 'account'])
            ->orderByDesc('year')
            ->orderByDesc('created_at')
            ->get()
            ->map(function($budget) {
                $budget->fiscal_year = $budget->year; // Add fiscal_year property for view compatibility
                return $budget;
            });

        $fiscalYears = FiscalYear::orderByDesc('start_date')->get();

        // Get all accounts grouped by account group for budget allocation
        $accounts = Account::with('accountGroup')
            ->whereHas('accountGroup')
            ->orderBy('code')
            ->get()
            ->groupBy(fn($account) => $account->accountGroup ? $account->accountGroup->name : 'Uncategorized');

        return view('accounting.cost-centers.budgets', compact('costCenter', 'budgets', 'fiscalYears', 'accounts'));
    }

    /**
     * Store budget for a cost center.
     */
    public function storeBudget(Request $request, CostCenter $costCenter)
    {
        $validated = $request->validate([
            'fiscal_year_id' => 'required|exists:fiscal_years,id',
            'account_id' => 'required|exists:accounts,id',
            'budget_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        // Get fiscal year to extract year
        $fiscalYear = FiscalYear::findOrFail($validated['fiscal_year_id']);
        $year = Carbon::parse($fiscalYear->start_date)->year;

        // Check for existing budget
        $existing = CostCenterBudget::where('cost_center_id', $costCenter->id)
            ->where('fiscal_year_id', $validated['fiscal_year_id'])
            ->where('account_id', $validated['account_id'])
            ->first();

        if ($existing) {
            $existing->update([
                'budgeted_amount' => $validated['budget_amount'],
                'notes' => $validated['notes'],
            ]);
            $message = 'Budget updated successfully.';
        } else {
            CostCenterBudget::create([
                'cost_center_id' => $costCenter->id,
                'fiscal_year_id' => $validated['fiscal_year_id'],
                'account_id' => $validated['account_id'],
                'year' => $year,
                'budgeted_amount' => $validated['budget_amount'],
                'notes' => $validated['notes'],
            ]);
            $message = 'Budget created successfully.';
        }

        return redirect()
            ->back()
            ->with('success', $message);
    }

    /**
     * Update budget for a cost center.
     */
    public function updateBudget(Request $request, CostCenter $costCenter, CostCenterBudget $budget)
    {
        $validated = $request->validate([
            'budget_amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $budget->update([
            'budgeted_amount' => $validated['budget_amount'],
            'notes' => $validated['notes'],
        ]);

        return redirect()
            ->back()
            ->with('success', 'Budget updated successfully.');
    }

    /**
     * Display cost allocation management page.
     */
    public function allocations(Request $request)
    {
        $costCenters = CostCenter::where('is_active', true)
            ->orderBy('name')
            ->get();

        $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();

        // Get recent allocations - check if table exists first
        $recentAllocations = collect([]);
        $stats = [
            'total_allocations' => 0,
            'active_cost_centers' => $costCenters->count(),
            'this_month' => 0,
        ];

        try {
            if (DB::getSchemaBuilder()->hasTable('cost_allocations')) {
                $recentAllocations = DB::table('cost_allocations')
                    ->join('cost_centers as source', 'cost_allocations.source_cost_center_id', '=', 'source.id')
                    ->join('cost_centers as target', 'cost_allocations.target_cost_center_id', '=', 'target.id')
                    ->select([
                        'cost_allocations.*',
                        'source.name as source_name',
                        'source.code as source_code',
                        'target.name as target_name',
                        'target.code as target_code',
                    ])
                    ->orderBy('cost_allocations.created_at', 'desc')
                    ->limit(20)
                    ->get();

                $stats = [
                    'total_allocations' => DB::table('cost_allocations')->count(),
                    'active_cost_centers' => $costCenters->count(),
                    'this_month' => DB::table('cost_allocations')
                        ->whereMonth('allocation_date', now()->month)
                        ->whereYear('allocation_date', now()->year)
                        ->sum('amount'),
                ];
            }
        } catch (\Exception $e) {
            Log::warning('Cost allocations table not found: ' . $e->getMessage());
        }

        return view('accounting.cost-centers.allocations', compact('costCenters', 'fiscalYears', 'recentAllocations', 'stats'));
    }

    /**
     * Store a new cost allocation.
     */
    public function storeAllocation(Request $request)
    {
        // Check if cost_allocations table exists
        if (!DB::getSchemaBuilder()->hasTable('cost_allocations')) {
            return redirect()
                ->back()
                ->with('error', 'Cost allocations feature is not yet available. The database table needs to be created.');
        }

        $validated = $request->validate([
            'source_cost_center_id' => 'required|exists:cost_centers,id|different:target_cost_center_id',
            'target_cost_center_id' => 'required|exists:cost_centers,id',
            'amount' => 'required|numeric|min:0.01',
            'allocation_date' => 'required|date',
            'description' => 'required|string|max:500',
            'allocation_basis' => 'required|string|max:100',
        ]);

        try {
            DB::beginTransaction();

            DB::table('cost_allocations')->insert([
                'source_cost_center_id' => $validated['source_cost_center_id'],
                'target_cost_center_id' => $validated['target_cost_center_id'],
                'amount' => $validated['amount'],
                'allocation_date' => $validated['allocation_date'],
                'description' => $validated['description'],
                'allocation_basis' => $validated['allocation_basis'],
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return redirect()
                ->back()
                ->with('success', 'Cost allocation recorded successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()
                ->back()
                ->with('error', 'Failed to record allocation: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Run automatic cost allocation based on predefined rules.
     */
    public function runAllocation(Request $request)
    {
        $validated = $request->validate([
            'allocation_date' => 'required|date',
            'period' => 'required|in:monthly,quarterly',
        ]);

        // This is a placeholder for automatic allocation logic
        // In a real implementation, this would:
        // 1. Load allocation rules (e.g., allocate 30% of IT costs to Sales, 40% to Operations, 30% to Admin)
        // 2. Calculate amounts based on actual costs
        // 3. Create allocation entries

        return redirect()
            ->back()
            ->with('info', 'Automatic allocation feature is under development.');
    }

    /**
     * Export cost centers to PDF.
     */
    public function exportPdf(Request $request)
    {
        $costCenters = CostCenter::with(['department', 'manager'])
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', $request->is_active))
            ->orderBy('code')
            ->get();

        $stats = [
            'total' => $costCenters->count(),
            'active' => $costCenters->where('is_active', true)->count(),
            'total_budget' => DB::table('cost_center_budgets')->sum('budgeted_amount'),
        ];

        $pdf = Pdf::loadView('accounting.cost-centers.export-pdf', compact('costCenters', 'stats'));
        return $pdf->download('cost-centers-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Export cost centers to Excel.
     */
    public function exportExcel(Request $request)
    {
        $costCenters = CostCenter::with(['department', 'manager'])
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->when($request->is_active !== null, fn($q) => $q->where('is_active', $request->is_active))
            ->orderBy('code')
            ->get();

        $stats = [
            'total' => $costCenters->count(),
            'active' => $costCenters->where('is_active', true)->count(),
            'total_budget' => DB::table('cost_center_budgets')->sum('budgeted_amount'),
        ];

        $excelService = app(ExcelExportService::class);
        return $excelService->costCenters($costCenters, $stats);
    }
}
