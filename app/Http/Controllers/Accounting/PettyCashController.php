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
use App\Services\Accounting\ExcelExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
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
    protected ExcelExportService $excelService;

    public function __construct(PettyCashService $pettyCashService, ExcelExportService $excelService)
    {
        $this->pettyCashService = $pettyCashService;
        $this->excelService = $excelService;
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
                    'approved' => 'info',
                    'disbursed' => 'success',
                    'rejected' => 'danger',
                    'voided' => 'secondary',
                ];
                $color = $colors[$t->status] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . ucfirst($t->status) . '</span>';
            })
            ->addColumn('requested_by_name', fn($t) => $t->requestedBy?->name ?? '-')
            ->addColumn('je_link', function ($t) {
                if ($t->journal_entry_id) {
                    return '<a href="' . route('accounting.journal-entries.show', $t->journal_entry_id) . '" class="btn btn-outline-secondary btn-sm" title="View Journal Entry"><i class="mdi mdi-book-open-variant"></i></a>';
                }
                return '-';
            })
            ->addColumn('actions', function ($t) {
                $actions = '<div class="btn-group btn-group-sm">';
                $actions .= '<a href="' . route('accounting.petty-cash.funds.show', $t->fund_id) . '" class="btn btn-outline-info" title="View Fund"><i class="mdi mdi-eye"></i></a>';

                if ($t->journal_entry_id) {
                    $actions .= '<a href="' . route('accounting.journal-entries.show', $t->journal_entry_id) . '" class="btn btn-outline-secondary" title="View JE"><i class="mdi mdi-book-open-variant"></i></a>';
                }

                if ($t->status === 'pending') {
                    $actions .= '<button class="btn btn-outline-success approve-btn" data-id="' . $t->id . '" title="Approve"><i class="mdi mdi-check"></i></button>';
                    $actions .= '<button class="btn btn-outline-danger reject-btn" data-id="' . $t->id . '" title="Reject"><i class="mdi mdi-close"></i></button>';
                }

                if ($t->status === 'approved') {
                    $actions .= '<button class="btn btn-outline-primary disburse-btn" data-id="' . $t->id . '" title="Disburse"><i class="mdi mdi-cash-check"></i></button>';
                }

                $actions .= '</div>';
                return $actions;
            })
            ->rawColumns(['type_badge', 'status_badge', 'je_link', 'actions'])
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
                $actions .= '<a href="' . route('accounting.petty-cash.funds.show', $f->id) . '" class="btn btn-outline-info" title="View"><i class="mdi mdi-eye mr-1"></i>View</a>';
                $actions .= '<a href="' . route('accounting.petty-cash.funds.edit', $f->id) . '" class="btn btn-outline-primary" title="Edit"><i class="mdi mdi-pencil mr-1"></i>Edit</a>';
                $actions .= '<a href="' . route('accounting.petty-cash.disbursement.create', $f->id) . '" class="btn btn-outline-danger" title="Disburse"><i class="mdi mdi-cash-remove mr-1"></i>Disburse</a>';
                $actions .= '<a href="' . route('accounting.petty-cash.replenishment.create', $f->id) . '" class="btn btn-outline-success" title="Replenish"><i class="mdi mdi-cash-refund mr-1"></i>Replenish</a>';
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
        // Get asset accounts (class code '1' = ASSET)
        $accounts = Account::where('is_active', true)
            ->whereHas('accountGroup.accountClass', function ($q) {
                $q->where('code', '1'); // ASSET class
            })
            ->orderBy('code')
            ->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $custodians = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['SUPERADMIN', 'ADMIN', 'ACCOUNTS']);
        })->orderBy('firstname')->get();

        // Get banks for initial funding source
        $banks = Bank::with('account')
            ->whereNotNull('account_id')
            ->orderBy('name')
            ->get();

        return view('accounting.petty-cash.funds.create', compact('accounts', 'departments', 'custodians', 'banks'));
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
            // Initial funding fields
            'initial_funding' => 'nullable|numeric|min:0',
            'funding_source' => 'required_with:initial_funding|nullable|in:cash,bank',
            'funding_bank_id' => 'required_if:funding_source,bank|nullable|exists:banks,id',
        ]);

        $validated['current_balance'] = 0;
        $validated['status'] = 'active';

        $fund = $this->pettyCashService->createFund($validated);

        // Process initial funding if provided
        $initialFunding = (float) ($request->initial_funding ?? 0);
        if ($initialFunding > 0) {
            $fundingData = [
                'amount' => $initialFunding,
                'description' => 'Initial fund establishment',
                'payment_method' => $request->funding_source === 'bank' ? 'bank_transfer' : 'cash',
                'bank_id' => $request->funding_bank_id,
            ];

            try {
                $this->pettyCashService->processReplenishment($fund, $fundingData);
                return redirect()
                    ->route('accounting.petty-cash.funds.show', $fund->id)
                    ->with('success', 'Petty cash fund created and funded with ₦' . number_format($initialFunding, 2));
            } catch (\Exception $e) {
                // Fund created but funding failed
                return redirect()
                    ->route('accounting.petty-cash.funds.show', $fund->id)
                    ->with('warning', 'Fund created but initial funding failed: ' . $e->getMessage() . '. Please create a replenishment manually.');
            }
        }

        return redirect()
            ->route('accounting.petty-cash.funds.show', $fund->id)
            ->with('success', 'Petty cash fund created successfully. Remember to fund it via a replenishment.');
    }

    /**
     * Show fund details with transactions.
     */
    public function fundsShow(PettyCashFund $fund)
    {
        $fund->load(['custodian', 'department', 'account']);

        $stats = [
            'total_disbursements' => $fund->transactions()
                ->where('transaction_type', 'disbursement')
                ->where('status', 'disbursed')
                ->sum('amount'),
            'total_replenishments' => $fund->transactions()
                ->where('transaction_type', 'replenishment')
                ->where('status', 'disbursed')
                ->sum('amount'),
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
        // Get asset accounts (class code '1' = ASSET)
        $accounts = Account::where('is_active', true)
            ->whereHas('accountGroup.accountClass', function ($q) {
                $q->where('code', '1'); // ASSET class
            })
            ->orderBy('code')
            ->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $custodians = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['SUPERADMIN', 'ADMIN', 'ACCOUNTS']);
        })->orderBy('firstname')->get();

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
                    'approved' => 'info',
                    'disbursed' => 'success',
                    'rejected' => 'danger',
                    'voided' => 'secondary',
                ];
                $color = $colors[$t->status] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . ucfirst($t->status) . '</span>';
            })
            ->addColumn('requested_by_name', fn($t) => $t->requestedBy?->name ?? '-')
            ->addColumn('actions', function ($t) {
                $actions = '<div class="btn-group btn-group-sm">';

                if ($t->journal_entry_id) {
                    $actions .= '<a href="' . route('accounting.journal-entries.show', $t->journal_entry_id) . '" class="btn btn-outline-secondary" title="View JE"><i class="mdi mdi-book-open-variant"></i></a>';
                }

                if ($t->status === 'pending') {
                    $actions .= '<button class="btn btn-outline-success approve-btn" data-id="' . $t->id . '" title="Approve"><i class="mdi mdi-check"></i></button>';
                    $actions .= '<button class="btn btn-outline-danger reject-btn" data-id="' . $t->id . '" title="Reject"><i class="mdi mdi-close"></i></button>';
                }

                if ($t->status === 'approved') {
                    $actions .= '<button class="btn btn-outline-primary disburse-btn" data-id="' . $t->id . '" title="Disburse"><i class="mdi mdi-cash-check"></i></button>';
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
        // Get expense accounts (class code '5' = EXPENSE)
        $expenseAccounts = Account::where('is_active', true)
            ->whereHas('accountGroup.accountClass', function ($q) {
                $q->where('code', '5'); // EXPENSE class
            })
            ->orderBy('code')
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
                $validated['rejection_reason'],
                Auth::id()
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

    /**
     * Disburse an approved transaction.
     * This creates the journal entry and updates fund balance.
     */
    public function disburse(Request $request, PettyCashTransaction $transaction)
    {
        try {
            $this->pettyCashService->disburseTransaction($transaction);

            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Transaction disbursed successfully.']);
            }

            return back()->with('success', 'Transaction disbursed successfully.');
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
            $reconciliation = $this->pettyCashService->startReconciliation(
                $fund,
                (float) $validated['physical_count'],
                Auth::id(),
                $validated['notes'] ?? null
            );

            $message = $reconciliation->isBalanced()
                ? 'Reconciliation completed. Fund is balanced.'
                : 'Reconciliation submitted for approval. Variance: ₦' . number_format(abs($reconciliation->variance), 2) . ' (' . ucfirst($reconciliation->status) . ')';

            return redirect()
                ->route('accounting.petty-cash.funds.show', $fund->id)
                ->with('success', $message);
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * List all reconciliations with pending approvals highlighted.
     */
    public function reconciliationsIndex()
    {
        $pendingCount = PettyCashReconciliation::pendingApproval()->count();

        return view('accounting.petty-cash.reconciliations.index', compact('pendingCount'));
    }

    /**
     * Datatable for reconciliations.
     */
    public function reconciliationsDatatable(Request $request)
    {
        $query = PettyCashReconciliation::with(['fund', 'reconciledBy', 'reviewedBy', 'adjustmentEntry'])
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->filled('fund_id')) {
            $query->where('fund_id', $request->fund_id);
        }

        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return DataTables::of($query)
            ->addColumn('reconciliation_date_formatted', fn($r) => Carbon::parse($r->reconciliation_date)->format('M d, Y'))
            ->addColumn('fund_name', fn($r) => $r->fund?->fund_name ?? '-')
            ->addColumn('expected_formatted', fn($r) => '₦' . number_format($r->expected_balance, 2))
            ->addColumn('actual_formatted', fn($r) => '₦' . number_format($r->actual_cash_count, 2))
            ->addColumn('variance_formatted', function ($r) {
                $prefix = $r->variance > 0 ? '-' : '+';
                $color = $r->variance == 0 ? 'success' : ($r->variance > 0 ? 'danger' : 'warning');
                return '<span class="text-' . $color . '">' . $prefix . '₦' . number_format(abs($r->variance), 2) . '</span>';
            })
            ->addColumn('status_badge', function ($r) {
                $colors = [
                    'balanced' => 'success',
                    'shortage' => 'danger',
                    'overage' => 'warning',
                ];
                $color = $colors[$r->status] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . ucfirst($r->status) . '</span>';
            })
            ->addColumn('approval_badge', function ($r) {
                $colors = [
                    'pending_approval' => 'warning',
                    'approved' => 'success',
                    'rejected' => 'danger',
                ];
                $labels = [
                    'pending_approval' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ];
                $color = $colors[$r->approval_status] ?? 'secondary';
                $label = $labels[$r->approval_status] ?? $r->approval_status;
                return '<span class="badge badge-' . $color . '">' . $label . '</span>';
            })
            ->addColumn('reconciled_by_name', fn($r) => $r->reconciledBy?->name ?? '-')
            ->addColumn('actions', function ($r) {
                $actions = '<div class="btn-group btn-group-sm">';

                // View fund
                $actions .= '<a href="' . route('accounting.petty-cash.funds.show', $r->fund_id) . '" class="btn btn-outline-info" title="View Fund"><i class="mdi mdi-eye"></i></a>';

                // View adjustment JE if exists
                if ($r->adjustment_entry_id) {
                    $actions .= '<a href="' . route('accounting.journal-entries.show', $r->adjustment_entry_id) . '" class="btn btn-outline-secondary" title="View Adjustment JE"><i class="mdi mdi-book-open-variant"></i></a>';
                }

                // Approve/Reject for pending
                if ($r->isPendingApproval()) {
                    $actions .= '<button class="btn btn-outline-success approve-btn" data-id="' . $r->id . '" title="Approve"><i class="mdi mdi-check"></i></button>';
                    $actions .= '<button class="btn btn-outline-danger reject-btn" data-id="' . $r->id . '" title="Reject"><i class="mdi mdi-close"></i></button>';
                }

                $actions .= '</div>';
                return $actions;
            })
            ->rawColumns(['variance_formatted', 'status_badge', 'approval_badge', 'actions'])
            ->make(true);
    }

    /**
     * Approve a reconciliation.
     */
    public function approveReconciliation(PettyCashReconciliation $reconciliation)
    {
        try {
            $this->pettyCashService->approveReconciliation($reconciliation, Auth::id());

            return response()->json([
                'success' => true,
                'message' => 'Reconciliation approved. Adjustment journal entry created.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reject a reconciliation.
     */
    public function rejectReconciliation(Request $request, PettyCashReconciliation $reconciliation)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        try {
            $this->pettyCashService->rejectReconciliation(
                $reconciliation,
                Auth::id(),
                $validated['rejection_reason']
            );

            return response()->json([
                'success' => true,
                'message' => 'Reconciliation rejected.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
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

        $dateFrom = $request->date_from ? Carbon::parse($request->date_from)->format('M d, Y') : 'Start';
        $dateTo = $request->date_to ? Carbon::parse($request->date_to)->format('M d, Y') : now()->format('M d, Y');

        $pdf = Pdf::loadView('accounting.petty-cash.reports.fund-report', [
            'fund' => $fund,
            'transactions' => $transactions,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);

        return $pdf->download('petty-cash-' . $fund->fund_code . '-report.pdf');
    }

    /**
     * Export fund report as Excel.
     */
    public function exportExcel(Request $request, PettyCashFund $fund)
    {
        $fund->load(['custodian', 'department', 'account']);

        $transactions = $fund->transactions()
            ->with(['requestedBy', 'approvedBy', 'expenseAccount'])
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('transaction_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('transaction_date', '<=', $request->date_to))
            ->orderBy('transaction_date', 'desc')
            ->get();

        $dateFrom = $request->date_from ? Carbon::parse($request->date_from)->format('M d, Y') : 'Start';
        $dateTo = $request->date_to ? Carbon::parse($request->date_to)->format('M d, Y') : now()->format('M d, Y');

        return $this->excelService->pettyCashFundReport($fund, $transactions, $dateFrom, $dateTo);
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
