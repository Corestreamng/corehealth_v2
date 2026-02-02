<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\ExcelExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Capital Expenditure (Capex) Controller
 * Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 6.7
 *
 * Manages capital expenditure tracking, approval workflows,
 * and integration with Fixed Assets module.
 *
 * Access: SUPERADMIN|ADMIN|ACCOUNTS
 */
class CapexController extends Controller
{
    public function __construct()
    {
        $this->middleware(['role:SUPERADMIN|ADMIN|ACCOUNTS']);
    }

    /**
     * Display Capex dashboard with statistics and listing
     */
    public function index()
    {
        $stats = $this->getDashboardStats();
        $fiscalYears = $this->getFiscalYears();
        $categories = $this->getCapexCategories();

        return view('accounting.capex.index', compact('stats', 'fiscalYears', 'categories'));
    }

    /**
     * Get dashboard statistics
     */
    protected function getDashboardStats()
    {
        $currentYear = date('Y');

        // Get total approved budget for capex this year
        $totalBudget = DB::table('capex_budgets')
            ->where('fiscal_year', $currentYear)
            ->where('status', 'approved')
            ->sum('amount');

        // Get total committed (approved requests)
        $totalCommitted = DB::table('capex_requests')
            ->where('fiscal_year', $currentYear)
            ->whereIn('status', ['approved', 'in_progress', 'completed'])
            ->sum('approved_amount');

        // Get total spent (from journal entries or completed capex)
        $totalSpent = DB::table('capex_requests')
            ->where('fiscal_year', $currentYear)
            ->where('status', 'completed')
            ->sum('actual_amount');

        // Pending approvals
        $pendingApprovals = DB::table('capex_requests')
            ->where('status', 'pending')
            ->count();

        // In progress items
        $inProgress = DB::table('capex_requests')
            ->where('status', 'in_progress')
            ->count();

        // By category
        $byCategory = DB::table('capex_requests')
            ->select('category',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(CASE WHEN status IN ("approved", "in_progress", "completed") THEN approved_amount ELSE 0 END) as committed'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN actual_amount ELSE 0 END) as spent'))
            ->where('fiscal_year', $currentYear)
            ->groupBy('category')
            ->get();

        // Monthly spending trend
        $monthlyTrend = DB::table('capex_requests')
            ->select(
                DB::raw('MONTH(completion_date) as month'),
                DB::raw('SUM(actual_amount) as spent'))
            ->where('fiscal_year', $currentYear)
            ->where('status', 'completed')
            ->whereNotNull('completion_date')
            ->groupBy(DB::raw('MONTH(completion_date)'))
            ->orderBy('month')
            ->get();

        return [
            'total_budget' => $totalBudget,
            'total_committed' => $totalCommitted,
            'total_spent' => $totalSpent,
            'remaining_budget' => $totalBudget - $totalCommitted,
            'utilization' => $totalBudget > 0 ? round(($totalCommitted / $totalBudget) * 100, 1) : 0,
            'pending_approvals' => $pendingApprovals,
            'in_progress' => $inProgress,
            'by_category' => $byCategory,
            'monthly_trend' => $monthlyTrend,
        ];
    }

    /**
     * Get Capex requests for DataTable
     */
    public function datatable(Request $request)
    {
        $query = DB::table('capex_requests')
            ->leftJoin('users as requestor', 'capex_requests.requested_by', '=', 'requestor.id')
            ->leftJoin('users as approver', 'capex_requests.approved_by', '=', 'approver.id')
            ->leftJoin('cost_centers', 'capex_requests.cost_center_id', '=', 'cost_centers.id')
            ->select(
                'capex_requests.*',
                'requestor.name as requestor_name',
                'approver.name as approver_name',
                'cost_centers.name as cost_center_name'
            );

        // Filters
        if ($request->filled('fiscal_year')) {
            $query->where('capex_requests.fiscal_year', $request->fiscal_year);
        }
        if ($request->filled('status')) {
            $query->where('capex_requests.status', $request->status);
        }
        if ($request->filled('category')) {
            $query->where('capex_requests.category', $request->category);
        }
        if ($request->filled('priority')) {
            $query->where('capex_requests.priority', $request->priority);
        }

        // Search
        if ($request->filled('search.value')) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('capex_requests.title', 'like', "%{$search}%")
                    ->orWhere('capex_requests.reference_number', 'like', "%{$search}%")
                    ->orWhere('capex_requests.description', 'like', "%{$search}%");
            });
        }

        // Total count before pagination
        $totalRecords = $query->count();

        // Sorting
        $orderColumn = $request->input('order.0.column', 0);
        $orderDir = $request->input('order.0.dir', 'desc');
        $columns = ['reference_number', 'title', 'category', 'requested_amount', 'status', 'created_at'];
        $query->orderBy($columns[$orderColumn] ?? 'created_at', $orderDir);

        // Pagination
        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        $data = $query->skip($start)->take($length)->get();

        return response()->json([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data,
        ]);
    }

    /**
     * Show create form
     */
    public function create()
    {
        $categories = $this->getCapexCategories();
        $costCenters = DB::table('cost_centers')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $vendors = DB::table('suppliers')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $fiscalYears = $this->getFiscalYears();

        return view('accounting.capex.create', compact('categories', 'costCenters', 'vendors', 'fiscalYears'));
    }

    /**
     * Store new Capex request
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'fiscal_year' => 'required|integer',
            'requested_amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'justification' => 'required|string',
            'cost_center_id' => 'nullable|exists:cost_centers,id',
            'expected_start_date' => 'nullable|date',
            'expected_completion_date' => 'nullable|date|after_or_equal:expected_start_date',
            'priority' => 'required|in:low,medium,high,critical',
        ]);

        DB::beginTransaction();
        try {
            $referenceNumber = $this->generateReferenceNumber();

            $id = DB::table('capex_requests')->insertGetId([
                'reference_number' => $referenceNumber,
                'title' => $request->title,
                'description' => $request->description,
                'category' => $request->category,
                'fiscal_year' => $request->fiscal_year,
                'requested_amount' => $request->requested_amount,
                'justification' => $request->justification,
                'cost_center_id' => $request->cost_center_id,
                'vendor_id' => $request->vendor_id,
                'expected_start_date' => $request->expected_start_date,
                'expected_completion_date' => $request->expected_completion_date,
                'priority' => $request->priority,
                'status' => 'draft',
                'requested_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Store line items if provided
            if ($request->has('items')) {
                foreach ($request->items as $item) {
                    if (!empty($item['description']) && !empty($item['amount'])) {
                        DB::table('capex_request_items')->insert([
                            'capex_request_id' => $id,
                            'description' => $item['description'],
                            'quantity' => $item['quantity'] ?? 1,
                            'unit_cost' => $item['unit_cost'] ?? $item['amount'],
                            'amount' => $item['amount'],
                            'notes' => $item['notes'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('accounting.capex.show', $id)
                ->with('success', 'Capex request created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to create Capex request: ' . $e->getMessage());
        }
    }

    /**
     * Show Capex request details
     */
    public function show($id)
    {
        $capex = DB::table('capex_requests')
            ->leftJoin('users as requestor', 'capex_requests.requested_by', '=', 'requestor.id')
            ->leftJoin('users as approver', 'capex_requests.approved_by', '=', 'approver.id')
            ->leftJoin('cost_centers', 'capex_requests.cost_center_id', '=', 'cost_centers.id')
            ->leftJoin('suppliers', 'capex_requests.vendor_id', '=', 'suppliers.id')
            ->select(
                'capex_requests.*',
                'requestor.name as requestor_name',
                'approver.name as approver_name',
                'cost_centers.name as cost_center_name',
                'suppliers.name as vendor_name'
            )
            ->where('capex_requests.id', $id)
            ->first();

        if (!$capex) {
            return redirect()->route('accounting.capex.index')
                ->with('error', 'Capex request not found.');
        }

        // Get line items
        $items = DB::table('capex_request_items')
            ->where('capex_request_id', $id)
            ->get();

        // Get expenses/disbursements linked to this capex
        $expenses = DB::table('capex_expenses')
            ->leftJoin('users', 'capex_expenses.created_by', '=', 'users.id')
            ->select('capex_expenses.*', 'users.name as created_by_name')
            ->where('capex_request_id', $id)
            ->orderBy('expense_date', 'desc')
            ->get();

        // Get linked fixed assets (if completed)
        $assets = DB::table('fixed_assets')
            ->where('capex_request_id', $id)
            ->get();

        // Get approval history
        $approvalHistory = DB::table('capex_approval_history')
            ->leftJoin('users', 'capex_approval_history.user_id', '=', 'users.id')
            ->select('capex_approval_history.*', 'users.name as user_name')
            ->where('capex_request_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('accounting.capex.show', compact('capex', 'items', 'expenses', 'assets', 'approvalHistory'));
    }

    /**
     * Show edit form
     */
    public function edit($id)
    {
        $capex = DB::table('capex_requests')->where('id', $id)->first();

        if (!$capex) {
            return redirect()->route('accounting.capex.index')
                ->with('error', 'Capex request not found.');
        }

        if (!in_array($capex->status, ['draft', 'revision'])) {
            return redirect()->route('accounting.capex.show', $id)
                ->with('error', 'Only draft or revision requests can be edited.');
        }

        $items = DB::table('capex_request_items')
            ->where('capex_request_id', $id)
            ->get();

        $categories = $this->getCapexCategories();
        $costCenters = DB::table('cost_centers')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $vendors = DB::table('suppliers')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
        $fiscalYears = $this->getFiscalYears();

        return view('accounting.capex.edit', compact('capex', 'items', 'categories', 'costCenters', 'vendors', 'fiscalYears'));
    }

    /**
     * Update Capex request
     */
    public function update(Request $request, $id)
    {
        $capex = DB::table('capex_requests')->where('id', $id)->first();

        if (!$capex || !in_array($capex->status, ['draft', 'revision'])) {
            return redirect()->route('accounting.capex.index')
                ->with('error', 'Cannot update this request.');
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'requested_amount' => 'required|numeric|min:0',
            'justification' => 'required|string',
            'priority' => 'required|in:low,medium,high,critical',
        ]);

        DB::beginTransaction();
        try {
            DB::table('capex_requests')->where('id', $id)->update([
                'title' => $request->title,
                'description' => $request->description,
                'category' => $request->category,
                'requested_amount' => $request->requested_amount,
                'justification' => $request->justification,
                'cost_center_id' => $request->cost_center_id,
                'vendor_id' => $request->vendor_id,
                'expected_start_date' => $request->expected_start_date,
                'expected_completion_date' => $request->expected_completion_date,
                'priority' => $request->priority,
                'updated_at' => now(),
            ]);

            // Update line items
            DB::table('capex_request_items')->where('capex_request_id', $id)->delete();

            if ($request->has('items')) {
                foreach ($request->items as $item) {
                    if (!empty($item['description']) && !empty($item['amount'])) {
                        DB::table('capex_request_items')->insert([
                            'capex_request_id' => $id,
                            'description' => $item['description'],
                            'quantity' => $item['quantity'] ?? 1,
                            'unit_cost' => $item['unit_cost'] ?? $item['amount'],
                            'amount' => $item['amount'],
                            'notes' => $item['notes'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('accounting.capex.show', $id)
                ->with('success', 'Capex request updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Failed to update request: ' . $e->getMessage());
        }
    }

    /**
     * Submit Capex request for approval
     */
    public function submit($id)
    {
        $capex = DB::table('capex_requests')->where('id', $id)->first();

        if (!$capex || $capex->status !== 'draft') {
            return response()->json(['success' => false, 'message' => 'Cannot submit this request.']);
        }

        DB::table('capex_requests')->where('id', $id)->update([
            'status' => 'pending',
            'submitted_at' => now(),
            'updated_at' => now(),
        ]);

        // Log approval history
        DB::table('capex_approval_history')->insert([
            'capex_request_id' => $id,
            'user_id' => Auth::id(),
            'action' => 'submitted',
            'notes' => 'Request submitted for approval',
            'created_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Request submitted for approval.']);
    }

    /**
     * Approve Capex request
     */
    public function approve(Request $request, $id)
    {
        $capex = DB::table('capex_requests')->where('id', $id)->first();

        if (!$capex || $capex->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Cannot approve this request.']);
        }

        $approvedAmount = $request->input('approved_amount', $capex->requested_amount);

        DB::table('capex_requests')->where('id', $id)->update([
            'status' => 'approved',
            'approved_amount' => $approvedAmount,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'updated_at' => now(),
        ]);

        // Log approval history
        DB::table('capex_approval_history')->insert([
            'capex_request_id' => $id,
            'user_id' => Auth::id(),
            'action' => 'approved',
            'notes' => $request->input('notes', 'Request approved'),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Request approved successfully.']);
    }

    /**
     * Reject Capex request
     */
    public function reject(Request $request, $id)
    {
        $capex = DB::table('capex_requests')->where('id', $id)->first();

        if (!$capex || $capex->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Cannot reject this request.']);
        }

        DB::table('capex_requests')->where('id', $id)->update([
            'status' => 'rejected',
            'rejection_reason' => $request->input('reason'),
            'updated_at' => now(),
        ]);

        // Log approval history
        DB::table('capex_approval_history')->insert([
            'capex_request_id' => $id,
            'user_id' => Auth::id(),
            'action' => 'rejected',
            'notes' => $request->input('reason'),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Request rejected.']);
    }

    /**
     * Request revision for Capex
     */
    public function requestRevision(Request $request, $id)
    {
        $capex = DB::table('capex_requests')->where('id', $id)->first();

        if (!$capex || $capex->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Cannot request revision for this request.']);
        }

        DB::table('capex_requests')->where('id', $id)->update([
            'status' => 'revision',
            'revision_notes' => $request->input('notes'),
            'updated_at' => now(),
        ]);

        // Log approval history
        DB::table('capex_approval_history')->insert([
            'capex_request_id' => $id,
            'user_id' => Auth::id(),
            'action' => 'revision_requested',
            'notes' => $request->input('notes'),
            'created_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Revision requested.']);
    }

    /**
     * Start execution of approved Capex
     */
    public function startExecution($id)
    {
        $capex = DB::table('capex_requests')->where('id', $id)->first();

        if (!$capex || $capex->status !== 'approved') {
            return response()->json(['success' => false, 'message' => 'Cannot start execution.']);
        }

        DB::table('capex_requests')->where('id', $id)->update([
            'status' => 'in_progress',
            'actual_start_date' => now(),
            'updated_at' => now(),
        ]);

        // Log history
        DB::table('capex_approval_history')->insert([
            'capex_request_id' => $id,
            'user_id' => Auth::id(),
            'action' => 'started',
            'notes' => 'Execution started',
            'created_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Execution started.']);
    }

    /**
     * Record expense against Capex
     */
    public function recordExpense(Request $request, $id)
    {
        $capex = DB::table('capex_requests')->where('id', $id)->first();

        if (!$capex || !in_array($capex->status, ['approved', 'in_progress'])) {
            return response()->json(['success' => false, 'message' => 'Cannot record expense for this request.']);
        }

        $request->validate([
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'description' => 'required|string',
            'payment_reference' => 'nullable|string',
        ]);

        DB::table('capex_expenses')->insert([
            'capex_request_id' => $id,
            'expense_date' => $request->expense_date,
            'amount' => $request->amount,
            'description' => $request->description,
            'payment_reference' => $request->payment_reference,
            'vendor_id' => $request->vendor_id,
            'created_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update actual amount
        $totalExpenses = DB::table('capex_expenses')
            ->where('capex_request_id', $id)
            ->sum('amount');

        DB::table('capex_requests')->where('id', $id)->update([
            'actual_amount' => $totalExpenses,
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Expense recorded successfully.']);
    }

    /**
     * Complete Capex and optionally create fixed asset
     */
    public function complete(Request $request, $id)
    {
        $capex = DB::table('capex_requests')->where('id', $id)->first();

        if (!$capex || $capex->status !== 'in_progress') {
            return response()->json(['success' => false, 'message' => 'Cannot complete this request.']);
        }

        DB::beginTransaction();
        try {
            DB::table('capex_requests')->where('id', $id)->update([
                'status' => 'completed',
                'completion_date' => now(),
                'updated_at' => now(),
            ]);

            // Log history
            DB::table('capex_approval_history')->insert([
                'capex_request_id' => $id,
                'user_id' => Auth::id(),
                'action' => 'completed',
                'notes' => $request->input('notes', 'Capex completed'),
                'created_at' => now(),
            ]);

            // Create fixed asset if requested
            if ($request->input('create_asset')) {
                DB::table('fixed_assets')->insert([
                    'name' => $capex->title,
                    'asset_code' => $this->generateAssetCode(),
                    'category_id' => $request->input('asset_category_id'),
                    'acquisition_cost' => $capex->actual_amount ?? $capex->approved_amount,
                    'acquisition_date' => now(),
                    'capex_request_id' => $id,
                    'cost_center_id' => $capex->cost_center_id,
                    'status' => 'active',
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Capex completed successfully.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to complete: ' . $e->getMessage()]);
        }
    }

    /**
     * Get Capex budget overview
     */
    public function budgetOverview(Request $request)
    {
        $fiscalYear = $request->input('fiscal_year', date('Y'));

        // Get budgets
        $budgets = DB::table('capex_budgets')
            ->leftJoin('cost_centers', 'capex_budgets.cost_center_id', '=', 'cost_centers.id')
            ->select(
                'capex_budgets.*',
                'cost_centers.name as cost_center_name'
            )
            ->where('capex_budgets.fiscal_year', $fiscalYear)
            ->get();

        // Get spending by category
        $byCategory = DB::table('capex_requests')
            ->select('category',
                DB::raw('SUM(CASE WHEN status IN ("approved", "in_progress", "completed") THEN approved_amount ELSE 0 END) as committed'),
                DB::raw('SUM(CASE WHEN status = "completed" THEN actual_amount ELSE 0 END) as spent'))
            ->where('fiscal_year', $fiscalYear)
            ->groupBy('category')
            ->get();

        $fiscalYears = $this->getFiscalYears();
        $categories = $this->getCapexCategories();

        return view('accounting.capex.budget-overview', compact('budgets', 'byCategory', 'fiscalYear', 'fiscalYears', 'categories'));
    }

    /**
     * Export Capex report
     */
    public function export(Request $request)
    {
        $query = DB::table('capex_requests')
            ->leftJoin('cost_centers', 'capex_requests.cost_center_id', '=', 'cost_centers.id')
            ->select(
                'capex_requests.reference_number',
                'capex_requests.title',
                'capex_requests.category',
                'capex_requests.fiscal_year',
                'capex_requests.requested_amount',
                'capex_requests.approved_amount',
                'capex_requests.actual_amount',
                'capex_requests.status',
                'capex_requests.priority',
                'cost_centers.name as cost_center',
                'capex_requests.created_at'
            );

        if ($request->filled('fiscal_year')) {
            $query->where('capex_requests.fiscal_year', $request->fiscal_year);
        }
        if ($request->filled('status')) {
            $query->where('capex_requests.status', $request->status);
        }

        $capexList = $query->orderBy('capex_requests.created_at', 'desc')->get();

        // Calculate stats for export
        $stats = [
            'total' => $capexList->count(),
            'total_requested' => $capexList->sum('requested_amount'),
            'total_approved' => $capexList->sum('approved_amount'),
            'total_actual' => $capexList->sum('actual_amount'),
            'approved' => $capexList->where('status', 'approved')->count(),
            'pending' => $capexList->where('status', 'pending')->count(),
        ];

        $fiscalYear = $request->fiscal_year ?? date('Y');

        // Check export format
        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('accounting.capex.export-pdf', compact('capexList', 'stats', 'fiscalYear'));
            return $pdf->download('capex-report-' . now()->format('Y-m-d') . '.pdf');
        }

        // Default to Excel
        $excelService = app(ExcelExportService::class);
        return $excelService->capex($capexList, $fiscalYear);
    }

    /**
     * Generate unique reference number
     */
    protected function generateReferenceNumber()
    {
        $prefix = 'CAPEX';
        $year = date('Y');
        $lastRef = DB::table('capex_requests')
            ->where('reference_number', 'like', "{$prefix}-{$year}-%")
            ->max('reference_number');

        if ($lastRef) {
            $lastNum = intval(substr($lastRef, -4));
            $newNum = str_pad($lastNum + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNum = '0001';
        }

        return "{$prefix}-{$year}-{$newNum}";
    }

    /**
     * Generate unique asset code
     */
    protected function generateAssetCode()
    {
        $prefix = 'FA';
        $year = date('Y');
        $lastCode = DB::table('fixed_assets')
            ->where('asset_code', 'like', "{$prefix}-{$year}-%")
            ->max('asset_code');

        if ($lastCode) {
            $lastNum = intval(substr($lastCode, -5));
            $newNum = str_pad($lastNum + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $newNum = '00001';
        }

        return "{$prefix}-{$year}-{$newNum}";
    }

    /**
     * Get Capex categories
     */
    protected function getCapexCategories()
    {
        return [
            'equipment' => 'Equipment',
            'technology' => 'Technology',
            'facilities' => 'Facilities',
            'vehicles' => 'Vehicles',
            'furniture' => 'Furniture & Fixtures',
            'software' => 'Software',
            'construction' => 'Construction',
            'renovation' => 'Renovation',
            'other' => 'Other',
        ];
    }

    /**
     * Get fiscal years for dropdown
     */
    protected function getFiscalYears()
    {
        $currentYear = date('Y');
        return range($currentYear - 2, $currentYear + 1);
    }
}
