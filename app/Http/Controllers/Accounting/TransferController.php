<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\InterAccountTransfer;
use App\Models\Bank;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\ExcelExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Inter-Account Transfer Controller
 *
 * Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 2
 *
 * Handles bank-to-bank transfers with approval workflow
 * and clearance tracking.
 *
 * Access: SUPERADMIN|ADMIN|ACCOUNTS roles
 */
class TransferController extends Controller
{
    protected AccountingService $accountingService;
    protected ExcelExportService $excelService;

    public function __construct(AccountingService $accountingService, ExcelExportService $excelService)
    {
        $this->accountingService = $accountingService;
        $this->excelService = $excelService;
    }

    /**
     * Display transfers list with stats.
     */
    public function index()
    {
        $stats = $this->getDashboardStats();
        $banks = Bank::where('is_active', true)->orderBy('name')->get();

        // Get unique initiators from transfers
        $initiatorIds = InterAccountTransfer::distinct()->pluck('initiated_by')->filter();
        $initiators = \App\Models\User::whereIn('id', $initiatorIds)
            ->orderBy('surname')
            ->get(['id', 'surname', 'firstname', 'othername'])
            ->map(function ($user) {
                $user->full_name = trim("{$user->surname} {$user->firstname} {$user->othername}");
                return $user;
            });

        return view('accounting.transfers.index', compact('stats', 'banks', 'initiators'));
    }

    /**
     * DataTable endpoint for transfers.
     */
    public function datatable(Request $request)
    {
        $query = InterAccountTransfer::with(['fromBank', 'toBank', 'initiator', 'approver'])
            ->orderBy('transfer_date', 'desc');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_bank_id')) {
            $query->where('from_bank_id', $request->from_bank_id);
        }

        if ($request->filled('to_bank_id')) {
            $query->where('to_bank_id', $request->to_bank_id);
        }

        if ($request->filled('transfer_method')) {
            $query->where('transfer_method', $request->transfer_method);
        }

        if ($request->filled('initiated_by')) {
            $query->where('initiated_by', $request->initiated_by);
        }

        if ($request->filled('amount_min')) {
            $query->where('amount', '>=', $request->amount_min);
        }

        if ($request->filled('amount_max')) {
            $query->where('amount', '<=', $request->amount_max);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('transfer_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transfer_date', '<=', $request->date_to);
        }

        return DataTables::of($query)
            ->addColumn('transfer_date_formatted', fn($t) => $t->transfer_date->format('M d, Y'))
            ->addColumn('amount_formatted', fn($t) => '₦' . number_format($t->amount, 2))
            ->addColumn('from_bank_name', fn($t) => $t->fromBank?->bank_name ?? '-')
            ->addColumn('to_bank_name', fn($t) => $t->toBank?->bank_name ?? '-')
            ->addColumn('bank_flow', function ($t) {
                $from = $t->fromBank?->bank_name ?? 'N/A';
                $to = $t->toBank?->bank_name ?? 'N/A';
                return '<span class="text-danger">' . $from . '</span> <i class="mdi mdi-arrow-right text-muted"></i> <span class="text-success">' . $to . '</span>';
            })
            ->addColumn('method_badge', function ($t) {
                $colors = [
                    'internal' => 'info',
                    'wire' => 'primary',
                    'eft' => 'success',
                    'cheque' => 'warning',
                    'rtgs' => 'dark',
                    'neft' => 'secondary',
                ];
                $icons = [
                    'internal' => 'mdi-bank',
                    'wire' => 'mdi-access-point-network',
                    'eft' => 'mdi-credit-card-wireless',
                    'cheque' => 'mdi-checkbook',
                    'rtgs' => 'mdi-lightning-bolt',
                    'neft' => 'mdi-swap-horizontal',
                ];
                $color = $colors[$t->transfer_method] ?? 'secondary';
                $icon = $icons[$t->transfer_method] ?? 'mdi-bank-transfer';
                return '<span class="badge badge-' . $color . '"><i class="mdi ' . $icon . ' mr-1"></i>' . strtoupper($t->transfer_method) . '</span>';
            })
            ->addColumn('status_badge', function ($t) {
                $colors = [
                    'draft' => 'secondary',
                    'pending_approval' => 'warning',
                    'approved' => 'info',
                    'initiated' => 'primary',
                    'in_transit' => 'info',
                    'cleared' => 'success',
                    'failed' => 'danger',
                    'cancelled' => 'dark',
                ];
                $icons = [
                    'draft' => 'mdi-file-document-outline',
                    'pending_approval' => 'mdi-clock-outline',
                    'approved' => 'mdi-check',
                    'initiated' => 'mdi-send',
                    'in_transit' => 'mdi-truck-delivery',
                    'cleared' => 'mdi-check-all',
                    'failed' => 'mdi-alert-circle',
                    'cancelled' => 'mdi-cancel',
                ];
                $color = $colors[$t->status] ?? 'secondary';
                $icon = $icons[$t->status] ?? 'mdi-help-circle';
                $label = str_replace('_', ' ', ucwords($t->status, '_'));
                return '<span class="badge badge-' . $color . '"><i class="mdi ' . $icon . ' mr-1"></i>' . $label . '</span>';
            })
            ->addColumn('initiator_name', fn($t) => $t->initiator ? trim("{$t->initiator->surname} {$t->initiator->firstname}") : '-')
            ->addColumn('actions', function ($t) {
                $actions = '<div class="btn-group btn-group-sm">';
                $actions .= '<a href="' . route('accounting.transfers.show', $t->id) . '" class="btn btn-outline-info" title="View"><i class="mdi mdi-eye"></i></a>';

                if ($t->status === 'pending_approval') {
                    $actions .= '<button class="btn btn-outline-success approve-btn" data-id="' . $t->id . '" title="Approve"><i class="mdi mdi-check"></i></button>';
                    $actions .= '<button class="btn btn-outline-danger reject-btn" data-id="' . $t->id . '" title="Reject"><i class="mdi mdi-close"></i></button>';
                }

                if (in_array($t->status, ['approved', 'initiated', 'in_transit'])) {
                    $actions .= '<button class="btn btn-outline-success clearance-btn" data-id="' . $t->id . '" title="Confirm Clearance"><i class="mdi mdi-bank-check"></i></button>';
                    $actions .= '<button class="btn btn-outline-warning mark-failed-btn" data-id="' . $t->id . '" title="Mark Failed"><i class="mdi mdi-alert"></i></button>';
                }

                if (in_array($t->status, ['draft', 'pending_approval'])) {
                    $actions .= '<button class="btn btn-outline-dark cancel-btn" data-id="' . $t->id . '" title="Cancel"><i class="mdi mdi-cancel"></i></button>';
                }

                $actions .= '</div>';
                return $actions;
            })
            ->rawColumns(['bank_flow', 'method_badge', 'status_badge', 'actions'])
            ->make(true);
    }

    /**
     * Show create transfer form.
     */
    public function create()
    {
        $banks = Bank::where('is_active', true)->orderBy('name')->get();

        // Get expense accounts through group -> class relationship
        $feeAccounts = Account::where('is_active', true)
            ->whereHas('accountGroup.accountClass', function ($query) {
                $query->where('name', 'Expenses');
            })
            ->orderBy('code')
            ->get();

        return view('accounting.transfers.create', compact('banks', 'feeAccounts'));
    }

    /**
     * Store new transfer.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_bank_id' => 'required|exists:banks,id',
            'to_bank_id' => 'required|exists:banks,id|different:from_bank_id',
            'transfer_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'transfer_method' => 'required|in:internal,wire,eft,cheque,rtgs,neft',
            'reference' => 'nullable|string|max:100',
            'description' => 'required|string|max:500',
            'transfer_fee' => 'nullable|numeric|min:0',
            'fee_account_id' => 'nullable|exists:accounts,id',
            'expected_clearance_date' => 'nullable|date|after_or_equal:transfer_date',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $fromBank = Bank::findOrFail($validated['from_bank_id']);
            $toBank = Bank::findOrFail($validated['to_bank_id']);

            // Generate transfer number
            $transferNumber = 'TRF-' . date('Ymd') . '-' . str_pad(
                InterAccountTransfer::whereDate('created_at', today())->count() + 1,
                4, '0', STR_PAD_LEFT
            );

            $transfer = InterAccountTransfer::create([
                'transfer_number' => $transferNumber,
                'from_bank_id' => $validated['from_bank_id'],
                'to_bank_id' => $validated['to_bank_id'],
                'from_account_id' => $fromBank->account_id,
                'to_account_id' => $toBank->account_id,
                'transfer_date' => $validated['transfer_date'],
                'amount' => $validated['amount'],
                'transfer_method' => $validated['transfer_method'],
                'is_same_bank' => $fromBank->bank_name === $toBank->bank_name,
                'reference' => $validated['reference'],
                'description' => $validated['description'],
                'transfer_fee' => $validated['transfer_fee'] ?? 0,
                'fee_account_id' => $validated['fee_account_id'],
                'expected_clearance_date' => $validated['expected_clearance_date'],
                'notes' => $validated['notes'],
                'status' => InterAccountTransfer::STATUS_PENDING_APPROVAL,
                'initiated_by' => Auth::id(),
            ]);

            DB::commit();

            return redirect()
                ->route('accounting.transfers.show', $transfer)
                ->with('success', 'Transfer request created successfully. Awaiting approval.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'Failed to create transfer: ' . $e->getMessage());
        }
    }

    /**
     * Show transfer details.
     */
    public function show(InterAccountTransfer $transfer)
    {
        $transfer->load(['fromBank', 'toBank', 'fromAccount', 'toAccount', 'journalEntry.lines', 'initiator', 'approver']);

        return view('accounting.transfers.show', compact('transfer'));
    }

    /**
     * Approve transfer.
     * Note: JE is created by TransferObserver when status changes to CLEARED.
     */
    public function approve(Request $request, InterAccountTransfer $transfer)
    {
        if ($transfer->status !== InterAccountTransfer::STATUS_PENDING_APPROVAL) {
            return $this->errorResponse('Transfer is not pending approval.', $request);
        }

        $transfer->update([
            'status' => InterAccountTransfer::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return $this->successResponse('Transfer approved. Awaiting clearance confirmation.', $request);
    }

    /**
     * Reject transfer.
     */
    public function reject(Request $request, InterAccountTransfer $transfer)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        if ($transfer->status !== InterAccountTransfer::STATUS_PENDING_APPROVAL) {
            return $this->errorResponse('Transfer is not pending approval.', $request);
        }

        $transfer->update([
            'status' => InterAccountTransfer::STATUS_CANCELLED,
            'failure_reason' => $validated['rejection_reason'],
            'cancelled_by' => Auth::id(),
            'cancelled_at' => now(),
        ]);

        return $this->successResponse('Transfer rejected.', $request);
    }

    /**
     * Confirm clearance.
     */
    public function confirmClearance(Request $request, InterAccountTransfer $transfer)
    {
        $validated = $request->validate([
            'clearance_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        if (!in_array($transfer->status, [
            InterAccountTransfer::STATUS_APPROVED,
            InterAccountTransfer::STATUS_INITIATED,
            InterAccountTransfer::STATUS_IN_TRANSIT
        ])) {
            return $this->errorResponse('Transfer cannot be cleared in current status.', $request);
        }

        $transfer->update([
            'status' => InterAccountTransfer::STATUS_CLEARED,
            'actual_clearance_date' => $validated['clearance_date'] ?? now()->toDateString(),
            'cleared_at' => now(),
            'notes' => $transfer->notes . "\nClearance confirmed: " . ($validated['notes'] ?? ''),
        ]);

        return $this->successResponse('Transfer cleared successfully.', $request);
    }

    /**
     * Cancel transfer.
     */
    public function cancel(Request $request, InterAccountTransfer $transfer)
    {
        if (!in_array($transfer->status, [
            InterAccountTransfer::STATUS_DRAFT,
            InterAccountTransfer::STATUS_PENDING_APPROVAL
        ])) {
            return $this->errorResponse('Transfer cannot be cancelled in current status.', $request);
        }

        $transfer->update([
            'status' => InterAccountTransfer::STATUS_CANCELLED,
            'cancelled_by' => Auth::id(),
            'cancelled_at' => now(),
        ]);

        return $this->successResponse('Transfer cancelled.', $request);
    }

    /**
     * Mark transfer as failed.
     * Used when a transfer that was approved fails during processing.
     */
    public function markFailed(Request $request, InterAccountTransfer $transfer)
    {
        $validated = $request->validate([
            'failure_reason' => 'required|string|max:500',
        ]);

        // Can only mark as failed if it's in approved, initiated, or in_transit status
        if (!in_array($transfer->status, [
            InterAccountTransfer::STATUS_APPROVED,
            InterAccountTransfer::STATUS_INITIATED,
            InterAccountTransfer::STATUS_IN_TRANSIT
        ])) {
            return $this->errorResponse('Transfer cannot be marked as failed in current status.', $request);
        }

        $transfer->update([
            'status' => InterAccountTransfer::STATUS_FAILED,
            'failure_reason' => $validated['failure_reason'],
            'failed_at' => now(),
        ]);

        return $this->successResponse('Transfer marked as failed.', $request);
    }

    // ==========================================
    // EXPORT METHODS
    // ==========================================

    /**
     * Export transfers report as PDF.
     */
    public function exportPdf(Request $request)
    {
        $transfers = $this->getFilteredTransfers($request);

        $dateFrom = $request->date_from ? Carbon::parse($request->date_from)->format('M d, Y') : 'Start';
        $dateTo = $request->date_to ? Carbon::parse($request->date_to)->format('M d, Y') : now()->format('M d, Y');

        // Calculate summary stats
        $summary = [
            'total_count' => $transfers->count(),
            'total_amount' => $transfers->sum('amount'),
            'total_fees' => $transfers->sum('transfer_fee'),
            'cleared_count' => $transfers->where('status', 'cleared')->count(),
            'cleared_amount' => $transfers->where('status', 'cleared')->sum('amount'),
            'pending_count' => $transfers->whereIn('status', ['pending_approval', 'approved', 'initiated', 'in_transit'])->count(),
            'pending_amount' => $transfers->whereIn('status', ['pending_approval', 'approved', 'initiated', 'in_transit'])->sum('amount'),
            'failed_count' => $transfers->where('status', 'failed')->count(),
            'failed_amount' => $transfers->where('status', 'failed')->sum('amount'),
        ];

        // Group by method for breakdown
        $byMethod = $transfers->groupBy('transfer_method')->map(function ($group, $method) {
            return [
                'method' => strtoupper($method),
                'count' => $group->count(),
                'amount' => $group->sum('amount'),
            ];
        })->values();

        $pdf = Pdf::loadView('accounting.transfers.reports.transfers-report', [
            'transfers' => $transfers,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'summary' => $summary,
            'byMethod' => $byMethod,
            'statusFilter' => $request->status ? ucwords(str_replace('_', ' ', $request->status)) : 'All Statuses',
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->download('inter-account-transfers-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Export transfers report as Excel.
     */
    public function exportExcel(Request $request)
    {
        $transfers = $this->getFilteredTransfers($request);

        $dateFrom = $request->date_from ? Carbon::parse($request->date_from)->format('M d, Y') : 'Start';
        $dateTo = $request->date_to ? Carbon::parse($request->date_to)->format('M d, Y') : now()->format('M d, Y');
        $statusFilter = $request->status ? ucwords(str_replace('_', ' ', $request->status)) : 'All Statuses';

        return $this->excelService->interAccountTransfersReport($transfers, $dateFrom, $dateTo, $statusFilter);
    }

    /**
     * Export single transfer as PDF voucher.
     */
    public function exportSinglePdf(InterAccountTransfer $transfer)
    {
        $transfer->load(['fromBank', 'toBank', 'fromAccount', 'toAccount', 'feeAccount', 'journalEntry.lines.account', 'initiator', 'approver']);

        $pdf = Pdf::loadView('accounting.transfers.reports.transfer-detail', [
            'transfer' => $transfer,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf->download('transfer-voucher-' . $transfer->transfer_number . '.pdf');
    }

    /**
     * Export single transfer as Excel.
     */
    public function exportSingleExcel(InterAccountTransfer $transfer)
    {
        $transfer->load(['fromBank', 'toBank', 'fromAccount', 'toAccount', 'feeAccount', 'journalEntry.lines.account', 'initiator', 'approver']);

        return $this->excelService->singleTransferReport($transfer);
    }

    /**
     * Get filtered transfers for export.
     */
    protected function getFilteredTransfers(Request $request)
    {
        return InterAccountTransfer::with(['fromBank', 'toBank', 'initiator', 'approver', 'journalEntry'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('from_bank_id'), fn($q) => $q->where('from_bank_id', $request->from_bank_id))
            ->when($request->filled('to_bank_id'), fn($q) => $q->where('to_bank_id', $request->to_bank_id))
            ->when($request->filled('transfer_method'), fn($q) => $q->where('transfer_method', $request->transfer_method))
            ->when($request->filled('initiated_by'), fn($q) => $q->where('initiated_by', $request->initiated_by))
            ->when($request->filled('amount_min'), fn($q) => $q->where('amount', '>=', $request->amount_min))
            ->when($request->filled('amount_max'), fn($q) => $q->where('amount', '<=', $request->amount_max))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('transfer_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('transfer_date', '<=', $request->date_to))
            ->orderBy('transfer_date', 'desc')
            ->get();
    }

    // ==========================================
    // HELPERS
    // ==========================================

    /**
     * Get dashboard statistics.
     */
    protected function getDashboardStats(): array
    {
        $now = Carbon::now();
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $startOfWeek = $now->copy()->startOfWeek();
        $startOfLastWeek = $now->copy()->subWeek()->startOfWeek();
        $endOfLastWeek = $now->copy()->subWeek()->endOfWeek();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        // Status counts and amounts
        $pendingApproval = InterAccountTransfer::where('status', 'pending_approval');
        $approved = InterAccountTransfer::where('status', 'approved');
        $inTransit = InterAccountTransfer::whereIn('status', ['initiated', 'in_transit']);
        $cleared = InterAccountTransfer::where('status', 'cleared');
        $failed = InterAccountTransfer::where('status', 'failed');

        // Today's stats
        $todayTransfers = InterAccountTransfer::whereDate('transfer_date', $today);
        $yesterdayAmount = InterAccountTransfer::whereDate('transfer_date', $yesterday)->sum('amount');

        // Week stats
        $weekTransfers = InterAccountTransfer::whereDate('transfer_date', '>=', $startOfWeek);
        $lastWeekAmount = InterAccountTransfer::whereBetween('transfer_date', [$startOfLastWeek, $endOfLastWeek])->sum('amount');

        // Month stats
        $monthTransfers = InterAccountTransfer::whereDate('transfer_date', '>=', $startOfMonth);
        $lastMonthAmount = InterAccountTransfer::whereBetween('transfer_date', [$startOfLastMonth, $endOfLastMonth])->sum('amount');

        // Calculate trends
        $todayAmount = $todayTransfers->sum('amount');
        $weekAmount = $weekTransfers->sum('amount');
        $monthAmount = $monthTransfers->sum('amount');

        $todayVsYesterday = $yesterdayAmount > 0 ? round((($todayAmount - $yesterdayAmount) / $yesterdayAmount) * 100) : 0;
        $weekVsLast = $lastWeekAmount > 0 ? round((($weekAmount - $lastWeekAmount) / $lastWeekAmount) * 100) : 0;
        $monthVsLast = $lastMonthAmount > 0 ? round((($monthAmount - $lastMonthAmount) / $lastMonthAmount) * 100) : 0;

        // Pending trend (vs last week)
        $lastWeekPending = InterAccountTransfer::where('status', 'pending_approval')
            ->whereBetween('created_at', [$startOfLastWeek, $endOfLastWeek])
            ->count();
        $currentPending = $pendingApproval->count();
        $pendingTrend = $lastWeekPending > 0 ? round((($currentPending - $lastWeekPending) / $lastWeekPending) * 100) : 0;

        return [
            // Total
            'total_transfers' => InterAccountTransfer::count(),

            // Status counts
            'pending_approval' => $currentPending,
            'pending_amount' => $pendingApproval->sum('amount'),
            'pending_trend' => $pendingTrend,

            'approved_count' => $approved->count(),
            'approved_amount' => $approved->sum('amount'),

            'in_transit' => $inTransit->count(),
            'transit_amount' => $inTransit->sum('amount'),

            'cleared_today' => $cleared->clone()->whereDate('cleared_at', $today)->count(),
            'cleared_month' => $cleared->clone()->whereDate('cleared_at', '>=', $startOfMonth)->count(),
            'cleared_month_amount' => $cleared->clone()->whereDate('cleared_at', '>=', $startOfMonth)->sum('amount'),

            'failed_count' => $failed->count(),
            'failed_amount' => $failed->sum('amount'),

            // Volume stats
            'today_amount' => $todayAmount,
            'today_count' => $todayTransfers->count(),
            'today_vs_yesterday' => $todayVsYesterday,

            'week_amount' => $weekAmount,
            'week_count' => $weekTransfers->count(),
            'week_vs_last' => $weekVsLast,

            'month_amount' => $monthAmount,
            'month_count' => $monthTransfers->count(),
            'month_vs_last' => $monthVsLast,

            // Legacy (keeping for compatibility)
            'pending_clearance_amount' => InterAccountTransfer::whereIn('status', ['approved', 'initiated', 'in_transit'])->sum('amount'),
        ];
    }

    /**
     * Return success response.
     */
    protected function successResponse(string $message, Request $request)
    {
        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => $message]);
        }
        return back()->with('success', $message);
    }

    /**
     * Return error response.
     */
    protected function errorResponse(string $message, Request $request, int $code = 400)
    {
        if ($request->ajax()) {
            return response()->json(['success' => false, 'message' => $message], $code);
        }
        return back()->with('error', $message);
    }
}
