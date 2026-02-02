<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\InterAccountTransfer;
use App\Models\Bank;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

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

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Display transfers list with stats.
     */
    public function index()
    {
        $stats = $this->getDashboardStats();

        return view('accounting.transfers.index', compact('stats'));
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
            ->addColumn('method_badge', function ($t) {
                $colors = [
                    'internal' => 'info',
                    'wire' => 'primary',
                    'eft' => 'success',
                    'cheque' => 'warning',
                    'rtgs' => 'dark',
                    'neft' => 'secondary',
                ];
                $color = $colors[$t->transfer_method] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . strtoupper($t->transfer_method) . '</span>';
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
                $color = $colors[$t->status] ?? 'secondary';
                $label = str_replace('_', ' ', ucwords($t->status, '_'));
                return '<span class="badge badge-' . $color . '">' . $label . '</span>';
            })
            ->addColumn('initiator_name', fn($t) => $t->initiator?->name ?? '-')
            ->addColumn('actions', function ($t) {
                $actions = '<div class="btn-group btn-group-sm">';
                $actions .= '<a href="' . route('accounting.transfers.show', $t->id) . '" class="btn btn-outline-info" title="View"><i class="mdi mdi-eye"></i></a>';

                if ($t->status === 'pending_approval') {
                    $actions .= '<button class="btn btn-outline-success approve-btn" data-id="' . $t->id . '" title="Approve"><i class="mdi mdi-check"></i></button>';
                    $actions .= '<button class="btn btn-outline-danger reject-btn" data-id="' . $t->id . '" title="Reject"><i class="mdi mdi-close"></i></button>';
                }

                if (in_array($t->status, ['approved', 'initiated', 'in_transit'])) {
                    $actions .= '<button class="btn btn-outline-success clearance-btn" data-id="' . $t->id . '" title="Confirm Clearance"><i class="mdi mdi-bank-check"></i></button>';
                }

                if (in_array($t->status, ['draft', 'pending_approval'])) {
                    $actions .= '<button class="btn btn-outline-dark cancel-btn" data-id="' . $t->id . '" title="Cancel"><i class="mdi mdi-cancel"></i></button>';
                }

                $actions .= '</div>';
                return $actions;
            })
            ->rawColumns(['method_badge', 'status_badge', 'actions'])
            ->make(true);
    }

    /**
     * Show create transfer form.
     */
    public function create()
    {
        $banks = Bank::where('is_active', true)->orderBy('name')->get();
        $feeAccounts = Account::where('is_active', true)
            ->where('account_type', 'expense')
            ->orderBy('account_number')
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
     */
    public function approve(Request $request, InterAccountTransfer $transfer)
    {
        if ($transfer->status !== InterAccountTransfer::STATUS_PENDING_APPROVAL) {
            return $this->errorResponse('Transfer is not pending approval.', $request);
        }

        try {
            DB::beginTransaction();

            // Create journal entry
            $journalEntry = $this->createTransferJournalEntry($transfer);

            $transfer->update([
                'status' => InterAccountTransfer::STATUS_APPROVED,
                'journal_entry_id' => $journalEntry->id,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            DB::commit();

            return $this->successResponse('Transfer approved successfully.', $request);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to approve: ' . $e->getMessage(), $request);
        }
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
     * Export PDF.
     */
    public function exportPdf(Request $request)
    {
        $query = InterAccountTransfer::with(['fromBank', 'toBank', 'initiator'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('transfer_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('transfer_date', '<=', $request->date_to))
            ->orderBy('transfer_date', 'desc')
            ->get();

        $pdf = \PDF::loadView('accounting.transfers.reports.transfers-report', [
            'transfers' => $query,
            'dateFrom' => $request->date_from ?? 'Start',
            'dateTo' => $request->date_to ?? 'Present',
        ]);

        return $pdf->download('inter-account-transfers-report.pdf');
    }

    /**
     * Export Excel.
     */
    public function exportExcel(Request $request)
    {
        $transfers = InterAccountTransfer::with(['fromBank', 'toBank', 'initiator'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('transfer_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('transfer_date', '<=', $request->date_to))
            ->orderBy('transfer_date', 'desc')
            ->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $headers = ['Transfer #', 'Date', 'From Bank', 'To Bank', 'Amount', 'Method', 'Status', 'Reference', 'Initiated By'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Data
        $row = 2;
        foreach ($transfers as $t) {
            $sheet->setCellValue('A' . $row, $t->transfer_number);
            $sheet->setCellValue('B' . $row, $t->transfer_date->format('Y-m-d'));
            $sheet->setCellValue('C' . $row, $t->fromBank?->bank_name);
            $sheet->setCellValue('D' . $row, $t->toBank?->bank_name);
            $sheet->setCellValue('E' . $row, $t->amount);
            $sheet->setCellValue('F' . $row, strtoupper($t->transfer_method));
            $sheet->setCellValue('G' . $row, ucfirst($t->status));
            $sheet->setCellValue('H' . $row, $t->reference);
            $sheet->setCellValue('I' . $row, $t->initiator?->name);
            $row++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = 'transfers-' . now()->format('Y-m-d') . '.xlsx';

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
        return [
            'total_transfers' => InterAccountTransfer::count(),
            'pending_approval' => InterAccountTransfer::where('status', 'pending_approval')->count(),
            'in_transit' => InterAccountTransfer::whereIn('status', ['approved', 'initiated', 'in_transit'])->count(),
            'cleared_today' => InterAccountTransfer::where('status', 'cleared')
                ->whereDate('cleared_at', today())
                ->count(),
            'today_amount' => InterAccountTransfer::whereDate('transfer_date', today())->sum('amount'),
            'month_amount' => InterAccountTransfer::whereMonth('transfer_date', now()->month)
                ->whereYear('transfer_date', now()->year)
                ->sum('amount'),
            'pending_clearance_amount' => InterAccountTransfer::whereIn('status', ['approved', 'initiated', 'in_transit'])
                ->sum('amount'),
        ];
    }

    /**
     * Create journal entry for transfer.
     */
    protected function createTransferJournalEntry(InterAccountTransfer $transfer): JournalEntry
    {
        // Create journal entry
        $entry = JournalEntry::create([
            'entry_number' => $this->accountingService->generateEntryNumber(),
            'entry_date' => $transfer->transfer_date,
            'entry_type' => JournalEntry::TYPE_AUTOMATED,
            'description' => "Inter-account transfer: {$transfer->description}",
            'reference' => $transfer->transfer_number,
            'status' => JournalEntry::STATUS_POSTED,
            'created_by' => Auth::id(),
            'posted_at' => now(),
        ]);

        // Debit destination account (money coming in)
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $transfer->to_account_id,
            'debit' => $transfer->amount,
            'credit' => 0,
            'description' => "Transfer from {$transfer->fromBank->bank_name}",
        ]);

        // Credit source account (money going out)
        JournalEntryLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $transfer->from_account_id,
            'debit' => 0,
            'credit' => $transfer->amount,
            'description' => "Transfer to {$transfer->toBank->bank_name}",
        ]);

        // Handle transfer fee if applicable
        if ($transfer->transfer_fee > 0 && $transfer->fee_account_id) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $transfer->fee_account_id,
                'debit' => $transfer->transfer_fee,
                'credit' => 0,
                'description' => "Transfer fee",
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $transfer->from_account_id,
                'debit' => 0,
                'credit' => $transfer->transfer_fee,
                'description' => "Transfer fee deducted",
            ]);
        }

        return $entry;
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
