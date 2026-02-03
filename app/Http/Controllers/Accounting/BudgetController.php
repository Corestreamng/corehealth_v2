<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Budget;
use App\Models\Accounting\BudgetLine;
use App\Models\ChartOfAccount;
use App\Models\Department;
use App\Models\Accounting\FiscalYear;
use App\Models\JournalEntryLine;
use App\Services\Accounting\ExcelExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

/**
 * BudgetController
 *
 * Manages organizational budgets with variance analysis
 * Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.10
 * Access: SUPERADMIN|ADMIN|ACCOUNTS
 */
class BudgetController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS']);
    }

    /**
     * Budget dashboard with overview
     */
    public function index()
    {
        $stats = $this->getDashboardStats();
        $fiscalYears = FiscalYear::orderBy('year', 'desc')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();

        return view('accounting.budgets.index', compact('stats', 'fiscalYears', 'departments'));
    }

    /**
     * Get dashboard statistics
     */
    protected function getDashboardStats()
    {
        $currentYear = date('Y');
        $currentMonth = date('n');

        // Get current fiscal year (or use FiscalYear::current())
        $fiscalYear = FiscalYear::current();

        // Total budget for current year
        $totalBudget = Budget::when($fiscalYear, function($q) use ($fiscalYear) {
            $q->where('fiscal_year_id', $fiscalYear->id);
        })->where('status', 'approved')->sum('total_amount');

        // YTD actual expenses (from JE lines)
        $ytdActual = JournalEntryLine::whereHas('journalEntry', function($q) use ($currentYear) {
            $q->where('status', 'posted')
              ->whereYear('entry_date', $currentYear);
        })
        ->whereHas('account', function($q) {
            $q->where('account_type', 'expense');
        })
        ->sum('debit');

        // Monthly budget
        $monthlyBudget = $totalBudget / 12;

        // MTD actual
        $mtdActual = JournalEntryLine::whereHas('journalEntry', function($q) use ($currentYear, $currentMonth) {
            $q->where('status', 'posted')
              ->whereYear('entry_date', $currentYear)
              ->whereMonth('entry_date', $currentMonth);
        })
        ->whereHas('account', function($q) {
            $q->where('account_type', 'expense');
        })
        ->sum('debit');

        // Budget utilization
        $utilization = $totalBudget > 0 ? ($ytdActual / $totalBudget) * 100 : 0;

        // Expected YTD (prorated)
        $expectedYtd = ($monthlyBudget * $currentMonth);
        $variance = $expectedYtd - $ytdActual;

        // Budget count
        $budgetCount = Budget::whereHas('fiscalYear', function($q) use ($currentYear) {
            $q->where('year', $currentYear);
        })->count();

        $approvedCount = Budget::whereHas('fiscalYear', function($q) use ($currentYear) {
            $q->where('year', $currentYear);
        })->where('status', 'approved')->count();

        $pendingCount = Budget::whereHas('fiscalYear', function($q) use ($currentYear) {
            $q->where('year', $currentYear);
        })->where('status', 'pending')->count();

        // Top spending departments
        $topDepartments = Budget::with('department')
            ->whereHas('fiscalYear', function($q) use ($currentYear) {
                $q->where('year', $currentYear);
            })
            ->where('status', 'approved')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get()
            ->map(function($budget) use ($currentYear) {
                $actual = $this->getDepartmentActual($budget->department_id, $currentYear);
                return [
                    'department' => $budget->department->name ?? 'Unknown',
                    'budget' => $budget->total_amount,
                    'actual' => $actual,
                    'utilization' => $budget->total_amount > 0 ? ($actual / $budget->total_amount) * 100 : 0
                ];
            });

        return [
            'total_budget' => $totalBudget,
            'ytd_actual' => $ytdActual,
            'mtd_actual' => $mtdActual,
            'monthly_budget' => $monthlyBudget,
            'utilization' => $utilization,
            'variance' => $variance,
            'budget_count' => $budgetCount,
            'approved_count' => $approvedCount,
            'pending_count' => $pendingCount,
            'top_departments' => $topDepartments,
            'fiscal_year' => $fiscalYear
        ];
    }

    /**
     * Get department actual spending
     */
    protected function getDepartmentActual($departmentId, $year)
    {
        // This would need department-account mapping or cost center
        // For now, return 0 - implement based on your cost center structure
        return 0;
    }

    /**
     * DataTable for budgets
     */
    public function datatable(Request $request)
    {
        $query = Budget::with(['fiscalYear', 'department', 'createdBy']);

        // Filters
        if ($request->fiscal_year_id) {
            $query->where('fiscal_year_id', $request->fiscal_year_id);
        }

        if ($request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search['value']) {
            $search = $request->search['value'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('department', function($dq) use ($search) {
                      $dq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $totalRecords = Budget::count();
        $filteredRecords = $query->count();

        // Ordering
        $orderColumn = $request->order[0]['column'] ?? 0;
        $orderDir = $request->order[0]['dir'] ?? 'desc';
        $columns = ['id', 'name', 'department_id', 'total_amount', 'status', 'created_at'];

        if (isset($columns[$orderColumn])) {
            $query->orderBy($columns[$orderColumn], $orderDir);
        }

        // Pagination
        $budgets = $query->skip($request->start ?? 0)
                         ->take($request->length ?? 10)
                         ->get();

        $data = $budgets->map(function($budget) {
            $statusColors = [
                'draft' => 'secondary',
                'pending' => 'warning',
                'approved' => 'success',
                'rejected' => 'danger',
                'closed' => 'dark'
            ];

            return [
                'id' => $budget->id,
                'name' => $budget->name,
                'fiscal_year' => $budget->fiscalYear->year ?? 'N/A',
                'department' => $budget->department->name ?? 'Organization-wide',
                'total_amount' => number_format($budget->total_amount, 2),
                'status' => '<span class="badge badge-' . ($statusColors[$budget->status] ?? 'secondary') . '">' . ucfirst($budget->status) . '</span>',
                'created_by' => $budget->createdBy->name ?? 'System',
                'created_at' => $budget->created_at->format('M d, Y'),
                'actions' => $this->getActionButtons($budget)
            ];
        });

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data
        ]);
    }

    /**
     * Generate action buttons
     */
    protected function getActionButtons($budget)
    {
        $buttons = '<div class="btn-group btn-group-sm">';
        $buttons .= '<a href="' . route('accounting.budgets.show', $budget->id) . '" class="btn btn-info" title="View"><i class="mdi mdi-eye"></i></a>';

        if ($budget->status == 'draft') {
            $buttons .= '<a href="' . route('accounting.budgets.edit', $budget->id) . '" class="btn btn-primary" title="Edit"><i class="mdi mdi-pencil"></i></a>';
            $buttons .= '<button class="btn btn-success submit-budget" data-id="' . $budget->id . '" title="Submit"><i class="mdi mdi-send"></i></button>';
        }

        if ($budget->status == 'pending' && auth()->user()->hasRole(['SUPERADMIN', 'ADMIN'])) {
            $buttons .= '<button class="btn btn-success approve-budget" data-id="' . $budget->id . '" title="Approve"><i class="mdi mdi-check"></i></button>';
            $buttons .= '<button class="btn btn-danger reject-budget" data-id="' . $budget->id . '" title="Reject"><i class="mdi mdi-close"></i></button>';
        }

        $buttons .= '</div>';
        return $buttons;
    }

    /**
     * Create form
     */
    public function create()
    {
        $fiscalYears = FiscalYear::orderBy('year', 'desc')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $expenseAccounts = ChartOfAccount::where('account_type', 'expense')
                                         ->where('is_active', true)
                                         ->orderBy('code')
                                         ->get();

        return view('accounting.budgets.create', compact('fiscalYears', 'departments', 'expenseAccounts'));
    }

    /**
     * Store budget
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'fiscal_year_id' => 'required|exists:fiscal_years,id',
            'department_id' => 'nullable|exists:departments,id',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.account_id' => 'required|exists:chart_of_accounts,id',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            // Calculate total
            $totalAmount = collect($request->items)->sum('amount');

            $budget = Budget::create([
                'name' => $request->name,
                'fiscal_year_id' => $request->fiscal_year_id,
                'department_id' => $request->department_id,
                'description' => $request->description,
                'total_amount' => $totalAmount,
                'status' => 'draft',
                'created_by' => Auth::id()
            ]);

            // Create line items
            foreach ($request->items as $item) {
                BudgetLine::create([
                    'budget_id' => $budget->id,
                    'account_id' => $item['account_id'],
                    'budgeted_amount' => $item['amount'],
                    'notes' => $item['notes'] ?? null
                ]);
            }

            DB::commit();
            return redirect()->route('accounting.budgets.show', $budget->id)
                           ->with('success', 'Budget created successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->with('error', 'Failed to create budget: ' . $e->getMessage());
        }
    }

    /**
     * Show budget details
     */
    public function show(Budget $budget)
    {
        $budget->load(['fiscalYear', 'department', 'createdBy', 'approvedBy', 'items.account']);

        // Get actual spending per line item
        $itemsWithActuals = $budget->items->map(function($item) use ($budget) {
            $actual = JournalEntryLine::whereHas('journalEntry', function($q) use ($budget) {
                $q->where('status', 'posted')
                  ->whereYear('entry_date', $budget->fiscalYear->year ?? date('Y'));
            })
            ->where('account_id', $item->account_id)
            ->sum('debit');

            $item->actual_amount = $actual;
            $item->variance = $item->budgeted_amount - $actual;
            $item->utilization = $item->budgeted_amount > 0 ? ($actual / $item->budgeted_amount) * 100 : 0;

            return $item;
        });

        // Monthly breakdown
        $monthlyData = $this->getMonthlyBreakdown($budget);

        return view('accounting.budgets.show', compact('budget', 'itemsWithActuals', 'monthlyData'));
    }

    /**
     * Get monthly breakdown for budget
     */
    protected function getMonthlyBreakdown($budget)
    {
        $year = $budget->fiscalYear->year ?? date('Y');
        $monthlyBudget = $budget->total_amount / 12;

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $actual = JournalEntryLine::whereHas('journalEntry', function($q) use ($year, $m) {
                $q->where('status', 'posted')
                  ->whereYear('entry_date', $year)
                  ->whereMonth('entry_date', $m);
            })
            ->whereIn('account_id', $budget->items->pluck('account_id'))
            ->sum('debit');

            $months[] = [
                'month' => Carbon::createFromDate($year, $m, 1)->format('M'),
                'budget' => $monthlyBudget,
                'actual' => $actual,
                'variance' => $monthlyBudget - $actual
            ];
        }

        return $months;
    }

    /**
     * Edit form
     */
    public function edit(Budget $budget)
    {
        if ($budget->status != 'draft') {
            return back()->with('error', 'Only draft budgets can be edited');
        }

        $budget->load('items.account');
        $fiscalYears = FiscalYear::orderBy('year', 'desc')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $expenseAccounts = ChartOfAccount::where('account_type', 'expense')
                                         ->where('is_active', true)
                                         ->orderBy('code')
                                         ->get();

        return view('accounting.budgets.edit', compact('budget', 'fiscalYears', 'departments', 'expenseAccounts'));
    }

    /**
     * Update budget
     */
    public function update(Request $request, Budget $budget)
    {
        if ($budget->status != 'draft') {
            return back()->with('error', 'Only draft budgets can be edited');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'fiscal_year_id' => 'required|exists:fiscal_years,id',
            'department_id' => 'nullable|exists:departments,id',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.account_id' => 'required|exists:chart_of_accounts,id',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $totalAmount = collect($request->items)->sum('amount');

            $budget->update([
                'name' => $request->name,
                'fiscal_year_id' => $request->fiscal_year_id,
                'department_id' => $request->department_id,
                'description' => $request->description,
                'total_amount' => $totalAmount
            ]);

            // Delete existing items and recreate
            $budget->items()->delete();

            foreach ($request->items as $item) {
                BudgetLine::create([
                    'budget_id' => $budget->id,
                    'account_id' => $item['account_id'],
                    'budgeted_amount' => $item['amount'],
                    'notes' => $item['notes'] ?? null
                ]);
            }

            DB::commit();
            return redirect()->route('accounting.budgets.show', $budget->id)
                           ->with('success', 'Budget updated successfully');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->with('error', 'Failed to update budget: ' . $e->getMessage());
        }
    }

    /**
     * Submit budget for approval
     */
    public function submit(Request $request, Budget $budget)
    {
        if ($budget->status != 'draft') {
            return response()->json(['success' => false, 'message' => 'Only draft budgets can be submitted']);
        }

        $budget->update(['status' => 'pending']);

        return response()->json(['success' => true, 'message' => 'Budget submitted for approval']);
    }

    /**
     * Approve budget
     */
    public function approve(Request $request, Budget $budget)
    {
        if ($budget->status != 'pending') {
            return response()->json(['success' => false, 'message' => 'Only pending budgets can be approved']);
        }

        $budget->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now()
        ]);

        return response()->json(['success' => true, 'message' => 'Budget approved successfully']);
    }

    /**
     * Reject budget
     */
    public function reject(Request $request, Budget $budget)
    {
        if ($budget->status != 'pending') {
            return response()->json(['success' => false, 'message' => 'Only pending budgets can be rejected']);
        }

        $request->validate([
            'reason' => 'required|string'
        ]);

        $budget->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason
        ]);

        return response()->json(['success' => true, 'message' => 'Budget rejected']);
    }

    /**
     * Variance report
     */
    public function varianceReport(Request $request)
    {
        $fiscalYearId = $request->fiscal_year_id;
        $departmentId = $request->department_id;

        $query = Budget::with(['fiscalYear', 'department', 'items.account'])
                       ->where('status', 'approved');

        if ($fiscalYearId) {
            $query->where('fiscal_year_id', $fiscalYearId);
        } else {
            // Default to current fiscal year
            $currentFY = FiscalYear::current();
            if ($currentFY) {
                $query->where('fiscal_year_id', $currentFY->id);
            }
        }

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $budgets = $query->get();

        // Calculate actuals and variances
        $reportData = $budgets->map(function($budget) {
            $totalActual = 0;
            $items = $budget->items->map(function($item) use ($budget, &$totalActual) {
                $actual = JournalEntryLine::whereHas('journalEntry', function($q) use ($budget) {
                    $q->where('status', 'posted')
                      ->whereYear('entry_date', $budget->fiscalYear->year ?? date('Y'));
                })
                ->where('account_id', $item->account_id)
                ->sum('debit');

                $totalActual += $actual;

                return [
                    'account_code' => $item->account->code ?? '',
                    'account_name' => $item->account->name ?? '',
                    'budgeted' => $item->budgeted_amount,
                    'actual' => $actual,
                    'variance' => $item->budgeted_amount - $actual,
                    'variance_percent' => $item->budgeted_amount > 0
                        ? (($item->budgeted_amount - $actual) / $item->budgeted_amount) * 100
                        : 0
                ];
            });

            return [
                'budget_name' => $budget->name,
                'department' => $budget->department->name ?? 'Organization',
                'total_budgeted' => $budget->total_amount,
                'total_actual' => $totalActual,
                'total_variance' => $budget->total_amount - $totalActual,
                'items' => $items
            ];
        });

        $fiscalYears = FiscalYear::orderBy('year', 'desc')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();

        return view('accounting.budgets.variance-report', compact(
            'reportData', 'fiscalYears', 'departments', 'fiscalYearId', 'departmentId'
        ));
    }

    /**
     * Export budget
     */
    public function export(Request $request, Budget $budget = null)
    {
        // If exporting a single budget
        if ($budget) {
            $budget->load(['fiscalYear', 'department', 'items.account']);
            $budgets = collect([$budget]);
            $fiscalYear = $budget->fiscalYear->year ?? date('Y');
        } else {
            // Export all budgets with filters
            $query = Budget::with(['fiscalYear', 'department', 'items.account']);

            if ($request->filled('fiscal_year')) {
                $query->whereHas('fiscalYear', fn($q) => $q->where('year', $request->fiscal_year));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $budgets = $query->orderBy('created_at', 'desc')->get();
            $fiscalYear = $request->fiscal_year ?? date('Y');
        }

        // Check export format
        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('accounting.budgets.export-pdf', compact('budgets', 'fiscalYear'));
            return $pdf->download('budgets-' . now()->format('Y-m-d') . '.pdf');
        }

        // Default to Excel
        $excelService = app(ExcelExportService::class);
        return $excelService->budgets($budgets, $fiscalYear);
    }
}
