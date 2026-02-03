<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Budget;
use App\Models\Accounting\BudgetLine;
use App\Models\Accounting\Account;
use App\Models\Department;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\JournalEntryLine;
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
        $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
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

        // Total budget for current year (approved and locked)
        $totalBudget = Budget::when($fiscalYear, function($q) use ($fiscalYear) {
            $q->where('fiscal_year_id', $fiscalYear->id);
        })->whereIn('status', ['approved', 'locked'])->sum('total_budgeted');

        // YTD actual expenses (from JE lines)
        $ytdActual = JournalEntryLine::whereHas('journalEntry', function($q) use ($currentYear) {
            $q->where('status', 'posted')
              ->whereYear('entry_date', $currentYear);
        })
        ->whereHas('account.accountGroup.accountClass', function($q) {
            $q->where('name', 'Expenses');
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
        ->whereHas('account.accountGroup.accountClass', function($q) {
            $q->where('name', 'Expenses');
        })
        ->sum('debit');

        // Budget utilization
        $utilization = $totalBudget > 0 ? ($ytdActual / $totalBudget) * 100 : 0;

        // Expected YTD (prorated)
        $expectedYtd = ($monthlyBudget * $currentMonth);
        $variance = $expectedYtd - $ytdActual;

        // Budget count - use budget.year directly
        $budgetCount = Budget::where('year', $currentYear)->count();

        $approvedCount = Budget::where('year', $currentYear)
            ->whereIn('status', ['approved', 'locked'])
            ->count();

        $pendingCount = Budget::where('year', $currentYear)
            ->where('status', 'pending_approval')
            ->count();

        // Top spending departments (group organization-wide budgets separately)
        $topDepartments = Budget::with('department')
            ->where('year', $currentYear)
            ->whereIn('status', ['approved', 'locked'])
            ->orderByDesc('total_budgeted')
            ->limit(10) // Get more to ensure we have 5 after filtering
            ->get()
            ->map(function($budget) use ($currentYear) {
                $actual = $this->getDepartmentActual($budget->department_id, $currentYear);
                return [
                    'department_id' => $budget->department_id,
                    'department' => $budget->department_id ? ($budget->department->name ?? 'Unknown') : 'Organization-wide',
                    'budget' => $budget->total_budgeted,
                    'actual' => $actual,
                    'utilization' => $budget->total_budgeted > 0 ? ($actual / $budget->total_budgeted) * 100 : 0
                ];
            })
            ->groupBy('department')
            ->map(function($group) {
                // Sum budgets for same department
                return [
                    'department' => $group->first()['department'],
                    'budget' => $group->sum('budget'),
                    'actual' => $group->sum('actual'),
                    'utilization' => $group->sum('budget') > 0 ? ($group->sum('actual') / $group->sum('budget')) * 100 : 0
                ];
            })
            ->sortByDesc('budget')
            ->take(5)
            ->values();

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
                $q->where('budget_name', 'like', "%{$search}%")
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
        $columns = ['id', 'budget_name', 'department_id', 'total_budgeted', 'status', 'created_at'];

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
                'pending_approval' => 'warning',
                'approved' => 'success',
                'locked' => 'dark'
            ];

            return [
                'id' => $budget->id,
                'name' => $budget->budget_name,
                'fiscal_year' => $budget->fiscalYear->year_name ?? 'N/A',
                'department' => $budget->department->name ?? 'Organization-wide',
                'total_amount' => '₦' . number_format($budget->total_budgeted, 2),
                'status' => '<span class="badge badge-' . ($statusColors[$budget->status] ?? 'secondary') . '">' . ucfirst(str_replace('_', ' ', $budget->status)) . '</span>',
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

        if ($budget->status == 'pending_approval' && auth()->user()->hasRole(['SUPERADMIN', 'ADMIN'])) {
            $buttons .= '<button class="btn btn-success approve-budget" data-id="' . $budget->id . '" title="Approve"><i class="mdi mdi-check"></i></button>';
            $buttons .= '<button class="btn btn-danger reject-budget" data-id="' . $budget->id . '" title="Reject"><i class="mdi mdi-close"></i></button>';
        }

        if ($budget->isApprovedOnly() && auth()->user()->hasRole('SUPERADMIN')) {
            $buttons .= '<button class="btn btn-warning unapprove-budget" data-id="' . $budget->id . '" title="Unapprove"><i class="mdi mdi-undo"></i></button>';
            $buttons .= '<button class="btn btn-dark lock-budget" data-id="' . $budget->id . '" title="Lock"><i class="mdi mdi-lock"></i></button>';
        }

        if ($budget->isLocked()) {
            $buttons .= '<span class="badge badge-dark ml-1"><i class="mdi mdi-lock"></i> Locked</span>';
        }

        $buttons .= '</div>';
        return $buttons;
    }

    /**
     * Create form
     */
    public function create()
    {
        $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $expenseAccounts = Account::with('accountGroup')
                                         ->whereHas('accountGroup.accountClass', function($q) {
                                            $q->where('name', 'Expenses');
                                         })
                                         ->where('is_active', true)
                                         ->orderBy('code')
                                         ->get()
                                         ->groupBy(fn($account) => $account->accountGroup->name ?? 'Other');

        return view('accounting.budgets.create', compact('fiscalYears', 'departments', 'expenseAccounts'));
    }

    /**
     * Store budget
     */
    public function store(Request $request)
    {
        $request->validate([
            'budget_name' => 'required|string|max:255',
            'fiscal_year_id' => 'required|exists:fiscal_years,id',
            'department_id' => 'nullable|exists:departments,id',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.account_id' => 'required|exists:accounts,id',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            // Calculate total
            $totalAmount = collect($request->items)->sum('amount');

            // Get fiscal year to extract year
            $fiscalYear = FiscalYear::findOrFail($request->fiscal_year_id);
            $year = $fiscalYear->start_date->year;

            $budget = Budget::create([
                'budget_name' => $request->budget_name,
                'fiscal_year_id' => $request->fiscal_year_id,
                'year' => $year,
                'department_id' => $request->department_id,
                'notes' => $request->description,
                'total_budgeted' => $totalAmount,
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
            return redirect()->route('accounting.budgets.index')
                           ->with('success', 'Budget created successfully');

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error('Budget creation failed: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
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
                  ->whereYear('entry_date', $budget->year);
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
        // Use the year field from budget table
        $year = $budget->year ?? date('Y');
        $monthlyBudget = $budget->total_budgeted / 12;

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
        $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $expenseAccounts = Account::with('accountGroup')
                                         ->whereHas('accountGroup.accountClass', function($q) {
                                            $q->where('name', 'Expenses');
                                         })
                                         ->where('is_active', true)
                                         ->orderBy('code')
                                         ->get()
                                         ->groupBy(fn($account) => $account->accountGroup->name ?? 'Other');

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
            'budget_name' => 'required|string|max:255',
            'fiscal_year_id' => 'required|exists:fiscal_years,id',
            'department_id' => 'nullable|exists:departments,id',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.account_id' => 'required|exists:accounts,id',
            'items.*.amount' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string'
        ]);

        DB::beginTransaction();
        try {
            $totalAmount = collect($request->items)->sum('amount');

            // Get fiscal year to extract year if changed
            $fiscalYear = FiscalYear::findOrFail($request->fiscal_year_id);
            $year = $fiscalYear->start_date->year;

            $budget->update([
                'budget_name' => $request->budget_name,
                'fiscal_year_id' => $request->fiscal_year_id,
                'year' => $year,
                'department_id' => $request->department_id,
                'notes' => $request->description,
                'total_budgeted' => $totalAmount
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

        $budget->update(['status' => 'pending_approval']);

        return response()->json(['success' => true, 'message' => 'Budget submitted for approval']);
    }

    /**
     * Approve budget
     */
    public function approve(Request $request, Budget $budget)
    {
        if ($budget->status != 'pending_approval') {
            return response()->json(['success' => false, 'message' => 'Only pending budgets can be approved']);
        }

        if ($budget->isLocked()) {
            return response()->json(['success' => false, 'message' => 'Locked budgets cannot be modified']);
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
        if ($budget->status != 'pending_approval') {
            return response()->json(['success' => false, 'message' => 'Only pending budgets can be rejected']);
        }

        $request->validate([
            'reason' => 'required|string'
        ]);

        $budget->update([
            'status' => 'draft',
            'rejection_reason' => $request->reason
        ]);

        return response()->json(['success' => true, 'message' => 'Budget rejected and returned to draft']);
    }

    /**
     * Unapprove budget (SUPERADMIN only)
     */
    public function unapprove(Request $request, Budget $budget)
    {
        // Check if user is SUPERADMIN
        if (!auth()->user()->hasRole('SUPERADMIN')) {
            return response()->json(['success' => false, 'message' => 'Only SUPERADMIN can unapprove budgets']);
        }

        if ($budget->status == 'locked') {
            return response()->json(['success' => false, 'message' => 'Locked budgets cannot be unapproved. Period has closed.']);
        }

        if ($budget->status != 'approved') {
            return response()->json(['success' => false, 'message' => 'Only approved budgets can be unapproved']);
        }

        $request->validate([
            'reason' => 'required|string|min:10'
        ]);

        $budget->update([
            'status' => 'draft',
            'unapproval_reason' => $request->reason,
            'unapproved_by' => Auth::id(),
            'unapproved_at' => now(),
            'approved_by' => null,
            'approved_at' => null
        ]);

        return response()->json(['success' => true, 'message' => 'Budget unapproved and returned to draft']);
    }

    /**
     * Lock budget (prevents further changes)
     */
    public function lock(Request $request, Budget $budget)
    {
        // Check if user is SUPERADMIN
        if (!auth()->user()->hasRole('SUPERADMIN')) {
            return response()->json(['success' => false, 'message' => 'Only SUPERADMIN can lock budgets']);
        }

        if (!$budget->isApprovedOnly()) {
            return response()->json(['success' => false, 'message' => 'Only approved budgets can be locked']);
        }

        $budget->update([
            'status' => 'locked',
            'locked_by' => Auth::id(),
            'locked_at' => now()
        ]);

        return response()->json(['success' => true, 'message' => 'Budget locked successfully']);
    }

    /**
     * Variance report
     */
    public function varianceReport(Request $request)
    {
        $fiscalYearId = $request->fiscal_year_id;
        $departmentId = $request->department_id;

        $query = Budget::with(['fiscalYear', 'department', 'items.account'])
                       ->whereIn('status', ['approved', 'locked']);

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
                      ->whereYear('entry_date', $budget->year);
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
                'budget_name' => $budget->budget_name,
                'department' => $budget->department->name ?? 'Organization-wide',
                'total_budgeted' => $budget->total_budgeted,
                'total_actual' => $totalActual,
                'total_variance' => $budget->total_budgeted - $totalActual,
                'items' => $items
            ];
        });

        $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();

        return view('accounting.budgets.variance-report', compact(
            'reportData', 'fiscalYears', 'departments', 'fiscalYearId', 'departmentId'
        ));
    }

    /**
     * Export variance report
     */
    public function varianceReportExport(Request $request)
    {
        $fiscalYearId = $request->fiscal_year_id;
        $departmentId = $request->department_id;
        $format = $request->format ?? 'excel';

        $query = Budget::with(['fiscalYear', 'department', 'items.account'])
                       ->whereIn('status', ['approved', 'locked']);

        if ($fiscalYearId) {
            $query->where('fiscal_year_id', $fiscalYearId);
            $fiscalYear = FiscalYear::find($fiscalYearId);
        } else {
            // Default to current fiscal year
            $fiscalYear = FiscalYear::current();
            if ($fiscalYear) {
                $query->where('fiscal_year_id', $fiscalYear->id);
            }
        }

        if ($departmentId) {
            $query->where('department_id', $departmentId);
            $department = Department::find($departmentId);
        } else {
            $department = null;
        }

        $budgets = $query->get();

        // Calculate actuals and variances
        $reportData = $budgets->map(function($budget) {
            $totalActual = 0;
            $items = $budget->items->map(function($item) use ($budget, &$totalActual) {
                $actual = JournalEntryLine::whereHas('journalEntry', function($q) use ($budget) {
                    $q->where('status', 'posted')
                      ->whereYear('entry_date', $budget->year);
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
                'budget_name' => $budget->budget_name,
                'department' => $budget->department->name ?? 'Organization-wide',
                'fiscal_year' => $budget->fiscalYear->year_name ?? '',
                'total_budgeted' => $budget->total_budgeted,
                'total_actual' => $totalActual,
                'total_variance' => $budget->total_budgeted - $totalActual,
                'variance_percent' => $budget->total_budgeted > 0
                    ? (($budget->total_budgeted - $totalActual) / $budget->total_budgeted) * 100
                    : 0,
                'items' => $items
            ];
        });

        // Calculate summary statistics
        $totalBudgeted = $reportData->sum('total_budgeted');
        $totalActual = $reportData->sum('total_actual');
        $totalVariance = $totalBudgeted - $totalActual;
        $avgVariancePercent = $totalBudgeted > 0 ? ($totalVariance / $totalBudgeted) * 100 : 0;

        $summary = [
            'total_budgeted' => $totalBudgeted,
            'total_actual' => $totalActual,
            'total_variance' => $totalVariance,
            'variance_percent' => $avgVariancePercent
        ];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('accounting.budgets.variance-report-pdf', compact(
                'reportData', 'summary', 'fiscalYear', 'department'
            ));
            return $pdf->download('variance-report-' . now()->format('Y-m-d') . '.pdf');
        }

        // Default to Excel
        $excelService = app(ExcelExportService::class);
        return $excelService->varianceReport($reportData->toArray(), $summary, $fiscalYear, $department ?? null);
    }

    /**
     * Export budget
     */
    public function export(Request $request, Budget $budget = null)
    {
        // If exporting a single budget
        if ($budget) {
            $budget->load(['fiscalYear', 'department', 'items.account']);

            // Calculate actual spending for this budget
            $actualSpending = 0;
            foreach ($budget->items as $item) {
                $actual = JournalEntryLine::whereHas('journalEntry', function($q) use ($budget) {
                    $q->where('status', 'posted')
                      ->whereYear('entry_date', $budget->year);
                })
                ->where('account_id', $item->account_id)
                ->sum('debit');

                $actualSpending += $actual;
            }

            // Set the total_actual for export
            $budget->total_actual = $actualSpending;
            $budget->total_variance = $budget->total_budgeted - $actualSpending;

            $budgets = collect([$budget]);
            $fiscalYear = $budget->year;
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

            // Calculate actuals for each budget
            foreach ($budgets as $budget) {
                $actualSpending = 0;
                foreach ($budget->items as $item) {
                    $actual = JournalEntryLine::whereHas('journalEntry', function($q) use ($budget) {
                        $q->where('status', 'posted')
                          ->whereYear('entry_date', $budget->year);
                    })
                    ->where('account_id', $item->account_id)
                    ->sum('debit');

                    $actualSpending += $actual;
                }

                $budget->total_actual = $actualSpending;
                $budget->total_variance = $budget->total_budgeted - $actualSpending;
            }

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
