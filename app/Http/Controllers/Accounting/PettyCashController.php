<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\PettyCashFund;
use App\Models\Accounting\PettyCashTransaction;
use App\Models\Accounting\PettyCashReconciliation;
use App\Models\Accounting\Account;
use App\Models\Bank;
use App\Models\Department;
use App\Models\User;
use App\Services\Accounting\PettyCashService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

/**
 * Petty Cash Controller
 *
 * Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 1
 *
 * Handles petty cash fund management, disbursements,
 * replenishments, and reconciliations.
 *
 * Access: SUPERADMIN|ADMIN|ACCOUNTS roles
 */
class PettyCashController extends Controller
{
    protected PettyCashService $pettyCashService;

    public function __construct(PettyCashService $pettyCashService)
    {
        $this->pettyCashService = $pettyCashService;
        // Role-based access is handled in routes via middleware
    }

    // ==========================================
    // DASHBOARD / INDEX
    // ==========================================

    /**
     * Petty Cash module dashboard with overview stats.
     */
    public function index()
    {
        $stats = $this->getDashboardStats();
        $recentTransactions = PettyCashTransaction::with(['fund', 'requestedBy'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('accounting.petty-cash.index', compact('stats', 'recentTransactions'));
    }

    /**
     * DataTable endpoint for all transactions across funds.
     */
    public function datatable(Request $request)
    {
        $query = PettyCashTransaction::with(['fund', 'requestedBy', 'approvedBy', 'expenseAccount'])
            ->orderBy('transaction_date', 'desc');

        // Apply filters
        if ($request->filled('fund_id')) {
            $query->where('fund_id', $request->fund_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        return DataTables::of($query)
            ->addColumn('fund_name', fn($t) => $t->fund?->fund_name ?? '-')
            ->addColumn('transaction_date_formatted', fn($t) => Carbon::parse($t->transaction_date)->format('M d, Y'))
            ->addColumn('amount_formatted', fn($t) => '₦' . number_format($t->amount, 2))
            ->addColumn('type_badge', function ($t) {
                $colors = [
                    'disbursement' => 'danger',
                    'replenishment' => 'success',
                    'adjustment' => 'warning',
                ];
                $color = $colors[$t->transaction_type] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . ucfirst($t->transaction_type) . '</span>';
            })
            ->addColumn('status_badge', function ($t) {
                $colors = [
                    'pending' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    'voided' => 'secondary',
                ];
                $color = $colors[$t->status] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . ucfirst($t->status) . '</span>';
            })
            ->addColumn('requested_by_name', fn($t) => $t->requestedBy?->name ?? '-')
            ->addColumn('actions', function ($t) {
                $actions = '<div class="btn-group btn-group-sm">';
                $actions .= '<a href="' . route('accounting.petty-cash.funds.show', $t->fund_id) . '" class="btn btn-outline-info" title="View Fund"><i class="mdi mdi-eye"></i></a>';

                if ($t->status === 'pending') {
                    $actions .= '<button class="btn btn-outline-success approve-btn" data-id="' . $t->id . '" title="Approve"><i class="mdi mdi-check"></i></button>';
                    $actions .= '<button class="btn btn-outline-danger reject-btn" data-id="' . $t->id . '" title="Reject"><i class="mdi mdi-close"></i></button>';
                }

                $actions .= '</div>';
                return $actions;
            })
            ->rawColumns(['type_badge', 'status_badge', 'actions'])
            ->make(true);
    }

    // ==========================================
    // FUNDS MANAGEMENT
    // ==========================================

    /**
     * List all petty cash funds.
     */
    public function fundsIndex()
    {
        $stats = [
            'total_funds' => PettyCashFund::count(),
            'active_funds' => PettyCashFund::where('status', 'active')->count(),
            'total_balance' => PettyCashFund::where('status', 'active')->sum('current_balance'),
            'total_limit' => PettyCashFund::where('status', 'active')->sum('fund_limit'),
        ];

        return view('accounting.petty-cash.funds.index', compact('stats'));
    }

    /**
     * DataTable for funds list.
     */
    public function fundsDatatable(Request $request)
    {
        $query = PettyCashFund::with(['custodian', 'department', 'account'])
            ->withCount('transactions');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        return DataTables::of($query)
            ->addColumn('custodian_name', fn($f) => $f->custodian?->name ?? '-')
            ->addColumn('department_name', fn($f) => $f->department?->name ?? '-')
            ->addColumn('balance_formatted', fn($f) => '₦' . number_format($f->current_balance, 2))
            ->addColumn('limit_formatted', fn($f) => '₦' . number_format($f->fund_limit, 2))
            ->addColumn('utilization', function ($f) {
                $pct = $f->fund_limit > 0 ? (($f->fund_limit - $f->current_balance) / $f->fund_limit) * 100 : 0;
                $color = $pct > 80 ? 'danger' : ($pct > 50 ? 'warning' : 'success');
                return '<div class="progress" style="height: 20px;">
                    <div class="progress-bar bg-' . $color . '" style="width: ' . $pct . '%">' . round($pct) . '%</div>
                </div>';
            })
            ->addColumn('status_badge', function ($f) {
                $colors = [
                    'active' => 'success',
                    'suspended' => 'warning',
                    'closed' => 'secondary',
                ];
                $color = $colors[$f->status] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . ucfirst($f->status) . '</span>';
            })
            ->addColumn('actions', function ($f) {
                $actions = '<div class="btn-group btn-group-sm">';
                $actions .= '<a href="' . route('accounting.petty-cash.funds.show', $f->id) . '" class="btn btn-outline-info" title="View"><i class="mdi mdi-eye"></i></a>';
                $actions .= '<a href="' . route('accounting.petty-cash.funds.edit', $f->id) . '" class="btn btn-outline-primary" title="Edit"><i class="mdi mdi-pencil"></i></a>';
                $actions .= '<a href="' . route('accounting.petty-cash.disbursement.create', $f->id) . '" class="btn btn-outline-danger" title="Disburse"><i class="mdi mdi-cash-minus"></i></a>';
                $actions .= '<a href="' . route('accounting.petty-cash.replenishment.create', $f->id) . '" class="btn btn-outline-success" title="Replenish"><i class="mdi mdi-cash-plus"></i></a>';
                $actions .= '</div>';
                return $actions;
            })
            ->rawColumns(['utilization', 'status_badge', 'actions'])
            ->make(true);
    }

    /**
     * Show create fund form.
     */
    public function fundsCreate()
    {
        $accounts = Account::where('is_active', true)
            ->where('account_type', 'asset')
            ->orderBy('account_number')
            ->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $custodians = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['SUPERADMIN', 'ADMIN', 'ACCOUNTS']);
        })->orderBy('name')->get();

        return view('accounting.petty-cash.funds.create', compact('accounts', 'departments', 'custodians'));
    }

    /**
     * Store new fund.
     */
    public function fundsStore(Request $request)
    {
        $validated = $request->validate([
            'fund_name' => 'required|string|max:255',
            'fund_code' => 'nullable|string|max:50|unique:petty_cash_funds,fund_code',
            'account_id' => 'required|exists:accounts,id',
            'custodian_user_id' => 'required|exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
            'fund_limit' => 'required|numeric|min:0',
            'transaction_limit' => 'required|numeric|min:0',
            'requires_approval' => 'boolean',
            'approval_threshold' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $validated['current_balance'] = 0;
        $validated['status'] = 'active';

        $fund = $this->pettyCashService->createFund($validated);

        return redirect()
            ->route('accounting.petty-cash.funds.show', $fund->id)
            ->with('success', 'Petty cash fund created successfully.');
    }

    /**
     * Show fund details with transactions.
     */
    public function fundsShow(PettyCashFund $fund)
    {
        $fund->load(['custodian', 'department', 'account']);

        $stats = [
            'total_disbursements' => $fund->transactions()->where('transaction_type', 'disbursement')->where('status', 'approved')->sum('amount'),
            'total_replenishments' => $fund->transactions()->where('transaction_type', 'replenishment')->where('status', 'approved')->sum('amount'),
            'pending_count' => $fund->transactions()->where('status', 'pending')->count(),
            'je_balance' => $fund->getBalanceFromJournalEntries(),
        ];

        $recentTransactions = $fund->transactions()
            ->with(['requestedBy', 'approvedBy'])
            ->orderBy('transaction_date', 'desc')
            ->limit(20)
            ->get();

        return view('accounting.petty-cash.funds.show', compact('fund', 'stats', 'recentTransactions'));
    }

    /**
     * Show edit fund form.
     */
    public function fundsEdit(PettyCashFund $fund)
    {
        $accounts = Account::where('is_active', true)
            ->where('account_type', 'asset')
            ->orderBy('account_number')
            ->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $custodians = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['SUPERADMIN', 'ADMIN', 'ACCOUNTS']);
        })->orderBy('name')->get();

        return view('accounting.petty-cash.funds.edit', compact('fund', 'accounts', 'departments', 'custodians'));
    }

    /**
     * Update fund.
     */
    public function fundsUpdate(Request $request, PettyCashFund $fund)
    {
        $validated = $request->validate([
            'fund_name' => 'required|string|max:255',
            'fund_code' => 'nullable|string|max:50|unique:petty_cash_funds,fund_code,' . $fund->id,
            'account_id' => 'required|exists:accounts,id',
            'custodian_user_id' => 'required|exists:users,id',
            'department_id' => 'nullable|exists:departments,id',
            'fund_limit' => 'required|numeric|min:0',
            'transaction_limit' => 'required|numeric|min:0',
            'requires_approval' => 'boolean',
            'approval_threshold' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,suspended,closed',
            'notes' => 'nullable|string',
        ]);

        $fund->update($validated);

        return redirect()
            ->route('accounting.petty-cash.funds.show', $fund->id)
            ->with('success', 'Petty cash fund updated successfully.');
    }

    // ==========================================
    // TRANSACTIONS
    // ==========================================

    /**
     * Transactions list for a fund.
     */
    public function transactionsIndex(PettyCashFund $fund)
    {
        return view('accounting.petty-cash.transactions.index', compact('fund'));
    }

    /**
     * DataTable for fund transactions.
     */
    public function transactionsDatatable(Request $request, PettyCashFund $fund)
    {
        $query = $fund->transactions()
            ->with(['requestedBy', 'approvedBy', 'expenseAccount'])
            ->orderBy('transaction_date', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('transaction_type')) {
            $query->where('transaction_type', $request->transaction_type);
        }

        return DataTables::of($query)
            ->addColumn('transaction_date_formatted', fn($t) => Carbon::parse($t->transaction_date)->format('M d, Y'))
            ->addColumn('amount_formatted', fn($t) => '₦' . number_format($t->amount, 2))
            ->addColumn('type_badge', function ($t) {
                $colors = [
                    'disbursement' => 'danger',
                    'replenishment' => 'success',
                    'adjustment' => 'warning',
                ];
                $color = $colors[$t->transaction_type] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . ucfirst($t->transaction_type) . '</span>';
            })
            ->addColumn('status_badge', function ($t) {
                $colors = [
                    'pending' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger',
                    'voided' => 'secondary',
                ];
                $color = $colors[$t->status] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . ucfirst($t->status) . '</span>';
            })
            ->addColumn('requested_by_name', fn($t) => $t->requestedBy?->name ?? '-')
            ->addColumn('actions', function ($t) {
                $actions = '<div class="btn-group btn-group-sm">';

                if ($t->status === 'pending') {
                    $actions .= '<button class="btn btn-outline-success approve-btn" data-id="' . $t->id . '" title="Approve"><i class="mdi mdi-check"></i></button>';
                    $actions .= '<button class="btn btn-outline-danger reject-btn" data-id="' . $t->id . '" title="Reject"><i class="mdi mdi-close"></i></button>';
                }

                $actions .= '</div>';
                return $actions;
            })
            ->rawColumns(['type_badge', 'status_badge', 'actions'])
            ->make(true);
    }

    /**
     * Show disbursement form.
     */
    public function disbursementCreate(PettyCashFund $fund)
    {
        $expenseAccounts = Account::where('is_active', true)
            ->where('account_type', 'expense')
            ->orderBy('account_number')
            ->get();

        return view('accounting.petty-cash.transactions.disbursement', compact('fund', 'expenseAccounts'));
    }

    /**
     * Store disbursement.
     */
    public function disbursementStore(Request $request, PettyCashFund $fund)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
            'expense_account_id' => 'required|exists:accounts,id',
            'transaction_date' => 'required|date',
            'payee_name' => 'nullable|string|max:255',
            'receipt_number' => 'nullable|string|max:100',
            'expense_category' => 'nullable|string|max:100',
        ]);

        try {
            $transaction = $this->pettyCashService->createDisbursement(
                $fund,
                $validated,
                Auth::id()
            );

            return redirect()
                ->route('accounting.petty-cash.funds.show', $fund->id)
                ->with('success', 'Disbursement request created successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Show replenishment form.
     */
    public function replenishmentCreate(PettyCashFund $fund)
    {
        $fund->load(['account']);
        $suggestedAmount = $fund->fund_limit - $fund->current_balance;

        // Get active banks with their GL accounts
        $banks = Bank::with('account')
            ->whereNotNull('account_id')
            ->orderBy('name')
            ->get();

        return view('accounting.petty-cash.transactions.replenishment', compact('fund', 'suggestedAmount', 'banks'));
    }

    /**
     * Store replenishment.
     */
    public function replenishmentStore(Request $request, PettyCashFund $fund)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:500',
            'payment_method' => 'required|in:cash,bank_transfer',
            'bank_id' => 'required_if:payment_method,bank_transfer|nullable|exists:banks,id',
        ], [
            'payment_method.required' => 'Please select the replenishment source (Cash or Bank).',
            'bank_id.required_if' => 'Please select a bank for bank transfer replenishment.',
        ]);

        try {
            $transaction = $this->pettyCashService->createReplenishment(
                $fund,
                $validated['amount'],
                $validated['description'],
                Auth::id(),
                $validated['payment_method'],
                $validated['bank_id'] ?? null
            );

            return redirect()
                ->route('accounting.petty-cash.funds.show', $fund->id)
                ->with('success', 'Replenishment request created successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Approve transaction.
     */
    public function approve(Request $request, PettyCashTransaction $transaction)
    {
        try {
            $this->pettyCashService->approveTransaction($transaction, Auth::id());

            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Transaction approved successfully.']);
            }

            return back()->with('success', 'Transaction approved successfully.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reject transaction.
     */
    public function reject(Request $request, PettyCashTransaction $transaction)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        try {
            $this->pettyCashService->rejectTransaction(
                $transaction,
                Auth::id(),
                $validated['rejection_reason']
            );

            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Transaction rejected.']);
            }

            return back()->with('success', 'Transaction rejected.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
            }

            return back()->with('error', $e->getMessage());
        }
    }

    // ==========================================
    // RECONCILIATION
    // ==========================================

    /**
     * Show reconciliation form.
     */
    public function reconcile(PettyCashFund $fund)
    {
        $fund->load(['account', 'transactions' => function ($q) {
            $q->where('status', 'approved')
                ->orderBy('transaction_date', 'desc');
        }]);

        $jeBalance = $fund->getBalanceFromJournalEntries();
        $pendingDisbursements = $fund->getPendingDisbursements();

        return view('accounting.petty-cash.reconcile', compact('fund', 'jeBalance', 'pendingDisbursements'));
    }

    /**
     * Store reconciliation.
     */
    public function storeReconciliation(Request $request, PettyCashFund $fund)
    {
        $validated = $request->validate([
            'physical_count' => 'required|numeric|min:0',
            'reconciliation_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $reconciliation = $this->pettyCashService->reconcile(
                $fund,
                $validated['physical_count'],
                Auth::id(),
                $validated['notes'] ?? null,
                Carbon::parse($validated['reconciliation_date'])
            );

            return redirect()
                ->route('accounting.petty-cash.funds.show', $fund->id)
                ->with('success', 'Reconciliation completed successfully.');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    // ==========================================
    // EXPORT
    // ==========================================

    /**
     * Export fund report as PDF.
     */
    public function exportPdf(Request $request, PettyCashFund $fund)
    {
        $fund->load(['custodian', 'department', 'account']);

        $transactions = $fund->transactions()
            ->with(['requestedBy', 'approvedBy'])
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('transaction_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('transaction_date', '<=', $request->date_to))
            ->orderBy('transaction_date', 'desc')
            ->get();

        $pdf = \PDF::loadView('accounting.petty-cash.reports.fund-report', [
            'fund' => $fund,
            'transactions' => $transactions,
            'dateFrom' => $request->date_from ?? 'Start',
            'dateTo' => $request->date_to ?? 'Present',
        ]);

        return $pdf->download('petty-cash-' . $fund->fund_code . '-report.pdf');
    }

    /**
     * Export fund report as Excel.
     */
    public function exportExcel(Request $request, PettyCashFund $fund)
    {
        $transactions = $fund->transactions()
            ->with(['requestedBy', 'approvedBy', 'expenseAccount'])
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('transaction_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('transaction_date', '<=', $request->date_to))
            ->orderBy('transaction_date', 'desc')
            ->get();

        // Create export using PhpSpreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $sheet->setCellValue('A1', 'Date');
        $sheet->setCellValue('B1', 'Voucher #');
        $sheet->setCellValue('C1', 'Type');
        $sheet->setCellValue('D1', 'Description');
        $sheet->setCellValue('E1', 'Amount');
        $sheet->setCellValue('F1', 'Status');
        $sheet->setCellValue('G1', 'Requested By');
        $sheet->setCellValue('H1', 'Approved By');

        // Data
        $row = 2;
        foreach ($transactions as $t) {
            $sheet->setCellValue('A' . $row, $t->transaction_date);
            $sheet->setCellValue('B' . $row, $t->voucher_number);
            $sheet->setCellValue('C' . $row, ucfirst($t->transaction_type));
            $sheet->setCellValue('D' . $row, $t->description);
            $sheet->setCellValue('E' . $row, $t->amount);
            $sheet->setCellValue('F' . $row, ucfirst($t->status));
            $sheet->setCellValue('G' . $row, $t->requestedBy?->name);
            $sheet->setCellValue('H' . $row, $t->approvedBy?->name);
            $row++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'petty-cash-' . $fund->fund_code . '-' . now()->format('Y-m-d') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    // ==========================================
    // HELPERS
    // ==========================================

    /**
     * Get dashboard statistics.
     */
    protected function getDashboardStats(): array
    {
        $activeFunds = PettyCashFund::where('status', 'active')->get();

        return [
            'total_funds' => PettyCashFund::count(),
            'active_funds' => $activeFunds->count(),
            'total_balance' => $activeFunds->sum('current_balance'),
            'total_limit' => $activeFunds->sum('fund_limit'),
            'pending_transactions' => PettyCashTransaction::where('status', 'pending')->count(),
            'today_disbursements' => PettyCashTransaction::where('transaction_type', 'disbursement')
                ->whereDate('transaction_date', today())
                ->where('status', 'approved')
                ->sum('amount'),
            'month_disbursements' => PettyCashTransaction::where('transaction_type', 'disbursement')
                ->whereMonth('transaction_date', now()->month)
                ->whereYear('transaction_date', now()->year)
                ->where('status', 'approved')
                ->sum('amount'),
            'low_balance_funds' => $activeFunds->filter(fn($f) => $f->current_balance < ($f->fund_limit * 0.2))->count(),
        ];
    }
}
