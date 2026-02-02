<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\BankReconciliation;
use App\Models\Accounting\BankReconciliationItem;
use App\Models\Accounting\BankStatementImport;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\AccountingPeriod;
use App\Models\Bank;
use App\Services\Accounting\StatementParserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

/**
 * Bank Reconciliation Controller
 *
 * Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 3
 *
 * Manages bank statement reconciliation with GL entries.
 *
 * Access: SUPERADMIN|ADMIN|ACCOUNTS|AUDIT roles
 */
class BankReconciliationController extends Controller
{
    protected StatementParserService $statementParser;

    public function __construct(StatementParserService $statementParser)
    {
        $this->statementParser = $statementParser;
    }

    /**
     * Display reconciliation dashboard.
     */
    public function index()
    {
        $stats = $this->getDashboardStats();
        $banks = Bank::where('is_active', true)->orderBy('name')->get();

        return view('accounting.bank-reconciliation.index', compact('stats', 'banks'));
    }

    /**
     * DataTable endpoint for reconciliations.
     */
    public function datatable(Request $request)
    {
        $query = BankReconciliation::with(['bank', 'preparedBy', 'approvedBy'])
            ->orderBy('statement_date', 'desc');

        if ($request->filled('bank_id')) {
            $query->where('bank_id', $request->bank_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('statement_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('statement_date', '<=', $request->date_to);
        }

        return DataTables::of($query)
            ->addColumn('statement_date_formatted', fn($r) => $r->statement_date->format('M d, Y'))
            ->addColumn('bank_name', fn($r) => $r->bank?->bank_name ?? '-')
            ->addColumn('period', fn($r) => $r->statement_period_from->format('M d') . ' - ' . $r->statement_period_to->format('M d, Y'))
            ->addColumn('opening_balance_formatted', fn($r) => '₦' . number_format($r->statement_opening_balance, 2))
            ->addColumn('statement_balance_formatted', fn($r) => '₦' . number_format($r->statement_closing_balance, 2))
            ->addColumn('gl_balance_formatted', fn($r) => '₦' . number_format($r->gl_closing_balance, 2))
            ->addColumn('variance_formatted', function ($r) {
                $class = abs($r->variance) < 0.01 ? 'text-success' : 'text-danger';
                return '<span class="' . $class . '">₦' . number_format($r->variance, 2) . '</span>';
            })
            ->addColumn('status_badge', function ($r) {
                $colors = [
                    'draft' => 'secondary',
                    'in_progress' => 'info',
                    'pending_review' => 'warning',
                    'approved' => 'primary',
                    'finalized' => 'success',
                ];
                $color = $colors[$r->status] ?? 'secondary';
                $label = str_replace('_', ' ', ucwords($r->status, '_'));
                return '<span class="badge badge-' . $color . '">' . $label . '</span>';
            })
            ->addColumn('prepared_by_name', fn($r) => $r->preparedBy?->name ?? '-')
            ->addColumn('actions', function ($r) {
                $actions = '<div class="btn-group btn-group-sm">';
                $actions .= '<a href="' . route('accounting.bank-reconciliation.show', $r->id) . '" class="btn btn-outline-info" title="View"><i class="mdi mdi-eye"></i></a>';

                if (in_array($r->status, ['draft', 'in_progress'])) {
                    $actions .= '<a href="' . route('accounting.bank-reconciliation.edit', $r->id) . '" class="btn btn-outline-primary" title="Continue"><i class="mdi mdi-pencil"></i></a>';
                }

                if ($r->status === 'pending_review') {
                    $actions .= '<button class="btn btn-outline-success approve-btn" data-id="' . $r->id . '" title="Approve"><i class="mdi mdi-check"></i></button>';
                }

                $actions .= '</div>';
                return $actions;
            })
            ->rawColumns(['variance_formatted', 'status_badge', 'actions'])
            ->make(true);
    }

    /**
     * Show create reconciliation form.
     */
    public function create(Bank $bank)
    {
        \Log::info('BankReconciliation create() called', [
            'bank_id' => $bank->id,
            'bank_name' => $bank->name,
            'account_id' => $bank->account_id
        ]);

        if (!$bank->account_id) {
            \Log::warning('Bank has no account_id, redirecting back', ['bank_id' => $bank->id]);
            return redirect()->route('accounting.bank-reconciliation.index')
                ->with('error', 'The selected bank does not have a GL account linked. Please assign an account to this bank before reconciliation.');
        }

        $banks = Bank::where('is_active', true)->orderBy('name')->get();
        $periods = AccountingPeriod::where('status', AccountingPeriod::STATUS_OPEN)->orderBy('start_date', 'desc')->get();
        $selectedBank = $bank;  // Renamed to avoid collision with @foreach($banks as $bank)

        return view('accounting.bank-reconciliation.create', compact('banks', 'periods', 'selectedBank'));
    }

    /**
     * Store new reconciliation.
     */
    public function store(Request $request, Bank $bank)
    {
        \Log::info('BankReconciliation store() called', [
            'route_bank_id' => $bank->id,
            'route_bank_name' => $bank->name,
            'form_bank_id' => $request->input('bank_id'),
            'all_input' => $request->all()
        ]);

        $validated = $request->validate([
            'statement_date' => 'required|date',
            'statement_period_from' => 'required|date',
            'statement_period_to' => 'required|date|after_or_equal:statement_period_from',
            'statement_opening_balance' => 'required|numeric',
            'statement_closing_balance' => 'required|numeric',
            'fiscal_period_id' => 'nullable|exists:accounting_periods,id',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Use the bank from route model binding instead of form input
            if (!$bank->account_id) {
                return back()->with('error', 'The selected bank does not have a GL account linked. Please assign an account to this bank before reconciliation.');
            }

            // Generate reconciliation number
            $reconciliationNumber = 'RECON-' . date('Ymd') . '-' . str_pad(
                BankReconciliation::whereDate('created_at', today())->count() + 1,
                4, '0', STR_PAD_LEFT
            );

            // Get GL balance for the bank account
            $glBalance = $this->calculateGLBalance($bank->account_id, $validated['statement_period_to']);

            $reconciliation = BankReconciliation::create([
                'bank_id' => $bank->id,  // Use route-bound bank, not form input
                'account_id' => $bank->account_id,
                'fiscal_period_id' => $validated['fiscal_period_id'],
                'reconciliation_number' => $reconciliationNumber,
                'statement_date' => $validated['statement_date'],
                'statement_period_from' => $validated['statement_period_from'],
                'statement_period_to' => $validated['statement_period_to'],
                'statement_opening_balance' => $validated['statement_opening_balance'],
                'statement_closing_balance' => $validated['statement_closing_balance'],
                'gl_opening_balance' => 0, // Will be calculated
                'gl_closing_balance' => $glBalance,
                'outstanding_deposits' => 0,
                'outstanding_checks' => 0,
                'deposits_in_transit' => 0,
                'unrecorded_charges' => 0,
                'unrecorded_credits' => 0,
                'bank_errors' => 0,
                'book_errors' => 0,
                'variance' => $validated['statement_closing_balance'] - $glBalance,
                'status' => BankReconciliation::STATUS_DRAFT,
                'notes' => $validated['notes'],
                'prepared_by' => Auth::id(),
            ]);

            // Load GL transactions into reconciliation items
            $this->loadGLTransactions($reconciliation);

            DB::commit();

            return redirect()
                ->route('accounting.bank-reconciliation.edit', $reconciliation)
                ->with('success', 'Reconciliation created. Please match transactions.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'Failed to create reconciliation: ' . $e->getMessage());
        }
    }

    /**
     * Show reconciliation details.
     */
    public function show(BankReconciliation $reconciliation)
    {
        $reconciliation->load(['bank', 'items', 'preparedBy', 'reviewedBy', 'approvedBy']);

        $unmatchedGL = $reconciliation->items()
            ->where('source', BankReconciliationItem::SOURCE_GL)
            ->where('is_matched', false)
            ->get();

        $unmatchedStatement = $reconciliation->items()
            ->where('source', BankReconciliationItem::SOURCE_STATEMENT)
            ->where('is_matched', false)
            ->get();

        $matchedItems = $reconciliation->items()
            ->where('is_matched', true)
            ->get();

        return view('accounting.bank-reconciliation.show', compact(
            'reconciliation',
            'unmatchedGL',
            'unmatchedStatement',
            'matchedItems'
        ));
    }

    /**
     * Show edit/matching interface.
     */
    public function edit(BankReconciliation $reconciliation)
    {
        if (in_array($reconciliation->status, ['approved', 'finalized'])) {
            return redirect()
                ->route('accounting.bank-reconciliation.show', $reconciliation)
                ->with('error', 'Cannot edit approved/finalized reconciliation.');
        }

        $reconciliation->load(['bank', 'items']);

        $glItems = $reconciliation->items()
            ->where('source', BankReconciliationItem::SOURCE_GL)
            ->orderBy('transaction_date')
            ->get();

        $statementItems = $reconciliation->items()
            ->where('source', BankReconciliationItem::SOURCE_STATEMENT)
            ->orderBy('transaction_date')
            ->get();

        return view('accounting.bank-reconciliation.edit', compact(
            'reconciliation',
            'glItems',
            'statementItems'
        ));
    }

    /**
     * Update reconciliation.
     */
    public function update(Request $request, BankReconciliation $reconciliation)
    {
        $validated = $request->validate([
            'notes' => 'nullable|string',
            'outstanding_deposits' => 'nullable|numeric',
            'outstanding_checks' => 'nullable|numeric',
            'deposits_in_transit' => 'nullable|numeric',
            'unrecorded_charges' => 'nullable|numeric',
            'unrecorded_credits' => 'nullable|numeric',
        ]);

        $reconciliation->update($validated);
        $this->recalculateVariance($reconciliation);

        return back()->with('success', 'Reconciliation updated.');
    }

    /**
     * Import bank statement.
     */
    public function importStatement(Request $request, BankReconciliation $reconciliation)
    {
        $request->validate([
            'statement_file' => 'required|file|mimes:csv,xlsx,xls|max:5120',
        ]);

        try {
            DB::beginTransaction();

            $file = $request->file('statement_file');
            $import = BankStatementImport::create([
                'bank_id' => $reconciliation->bank_id,
                'filename' => $file->getClientOriginalName(),
                'file_path' => $file->store('bank-statements'),
                'statement_date' => $reconciliation->statement_date,
                'status' => 'processing',
                'uploaded_by' => Auth::id(),
            ]);

            // Parse file and create statement items
            $rows = $this->parseStatementFile($file);
            $import->update(['row_count' => count($rows)]);

            foreach ($rows as $row) {
                BankReconciliationItem::create([
                    'reconciliation_id' => $reconciliation->id,
                    'source' => BankReconciliationItem::SOURCE_STATEMENT,
                    'item_type' => $this->determineItemType($row),
                    'transaction_date' => Carbon::parse($row['date']),
                    'reference' => $row['reference'] ?? null,
                    'description' => $row['description'] ?? '',
                    'amount' => abs($row['amount']),
                    'amount_type' => $row['amount'] >= 0 ? 'credit' : 'debit',
                    'is_matched' => false,
                    'is_reconciled' => false,
                    'is_outstanding' => false,
                ]);
            }

            $import->update(['status' => 'completed', 'processed_count' => count($rows)]);

            DB::commit();

            return back()->with('success', count($rows) . ' statement transactions imported.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Upload statement file for visual reconciliation.
     */
    public function uploadStatement(Request $request, BankReconciliation $reconciliation)
    {
        $request->validate([
            'statement_file' => 'required|file|max:10240', // 10MB max
        ]);

        try {
            $file = $request->file('statement_file');
            $import = $this->statementParser->processUpload(
                $file,
                $reconciliation->bank_id,
                $reconciliation->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Statement uploaded successfully.',
                'import' => [
                    'id' => $import->id,
                    'file_name' => $import->file_name,
                    'file_format' => $import->file_format,
                    'status' => $import->status,
                    'viewer_type' => $this->statementParser->getViewerType($import),
                    'viewer_url' => $this->statementParser->getViewerUrl($import),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get statement content for viewing.
     */
    public function getStatementContent(BankReconciliation $reconciliation, BankStatementImport $import)
    {
        if ($import->reconciliation_id !== $reconciliation->id) {
            return response()->json(['success' => false, 'message' => 'Import does not belong to this reconciliation.'], 400);
        }

        $viewerType = $this->statementParser->getViewerType($import);

        if ($viewerType === 'table') {
            return response()->json([
                'success' => true,
                'viewer_type' => 'table',
                'content' => $this->statementParser->getAsHtmlTable($import),
            ]);
        }

        return response()->json([
            'success' => true,
            'viewer_type' => $viewerType,
            'viewer_url' => $this->statementParser->getViewerUrl($import),
            'file_name' => $import->file_name,
        ]);
    }

    /**
     * Get list of uploaded statements for a reconciliation.
     */
    public function getStatements(BankReconciliation $reconciliation)
    {
        $imports = BankStatementImport::where('reconciliation_id', $reconciliation->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($import) {
                return [
                    'id' => $import->id,
                    'file_name' => $import->file_name,
                    'file_format' => $import->file_format,
                    'status' => $import->status,
                    'viewer_type' => $this->statementParser->getViewerType($import),
                    'viewer_url' => $this->statementParser->getViewerUrl($import),
                    'created_at' => $import->created_at->format('M d, Y H:i'),
                ];
            });

        return response()->json(['success' => true, 'statements' => $imports]);
    }

    /**
     * Delete uploaded statement.
     */
    public function deleteStatement(BankReconciliation $reconciliation, BankStatementImport $import)
    {
        if ($import->reconciliation_id !== $reconciliation->id) {
            return response()->json(['success' => false, 'message' => 'Import does not belong to this reconciliation.'], 400);
        }

        $this->statementParser->deleteImport($import);

        return response()->json(['success' => true, 'message' => 'Statement deleted.']);
    }

    /**
     * Update reconciliation details (dates, balances).
     */
    public function updateDetails(Request $request, BankReconciliation $reconciliation)
    {
        $validated = $request->validate([
            'statement_date' => 'required|date',
            'statement_period_from' => 'required|date',
            'statement_period_to' => 'required|date|after_or_equal:statement_period_from',
            'statement_opening_balance' => 'required|numeric',
            'statement_closing_balance' => 'required|numeric',
        ]);

        try {
            $reconciliation->update([
                'statement_date' => $validated['statement_date'],
                'statement_period_from' => $validated['statement_period_from'],
                'statement_period_to' => $validated['statement_period_to'],
                'statement_opening_balance' => $validated['statement_opening_balance'],
                'statement_closing_balance' => $validated['statement_closing_balance'],
            ]);

            // Recalculate variance
            $reconciliation->variance = $reconciliation->statement_closing_balance - $reconciliation->gl_closing_balance
                - $reconciliation->outstanding_deposits + $reconciliation->outstanding_checks;
            $reconciliation->save();

            return response()->json([
                'success' => true,
                'message' => 'Reconciliation details updated successfully.',
                'reconciliation' => [
                    'statement_date' => $reconciliation->statement_date->format('Y-m-d'),
                    'statement_period_from' => $reconciliation->statement_period_from->format('Y-m-d'),
                    'statement_period_to' => $reconciliation->statement_period_to->format('Y-m-d'),
                    'statement_opening_balance' => $reconciliation->statement_opening_balance,
                    'statement_closing_balance' => $reconciliation->statement_closing_balance,
                    'variance' => $reconciliation->variance,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add manual statement item from visual selection.
     */
    public function addStatementItem(Request $request, BankReconciliation $reconciliation)
    {
        $validated = $request->validate([
            'transaction_date' => 'required|date',
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric',
            'amount_type' => 'required|in:debit,credit',
            'reference' => 'nullable|string|max:100',
            'row_data' => 'nullable|array', // Original row data from statement
        ]);

        try {
            $item = BankReconciliationItem::create([
                'reconciliation_id' => $reconciliation->id,
                'source' => BankReconciliationItem::SOURCE_STATEMENT,
                'item_type' => $this->determineItemType([
                    'description' => $validated['description'],
                    'amount' => $validated['amount_type'] === 'credit' ? $validated['amount'] : -$validated['amount'],
                ]),
                'transaction_date' => $validated['transaction_date'],
                'reference' => $validated['reference'],
                'description' => $validated['description'],
                'amount' => abs($validated['amount']),
                'amount_type' => $validated['amount_type'],
                'is_matched' => false,
                'is_reconciled' => false,
                'is_outstanding' => false,
                'metadata' => $validated['row_data'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Statement item added.',
                'item' => [
                    'id' => $item->id,
                    'transaction_date' => $item->transaction_date->format('Y-m-d'),
                    'description' => $item->description,
                    'amount' => $item->amount,
                    'amount_type' => $item->amount_type,
                    'reference' => $item->reference,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add item: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Match items.
     */
    public function matchItems(Request $request, BankReconciliation $reconciliation)
    {
        $validated = $request->validate([
            'gl_item_id' => 'required|exists:bank_reconciliation_items,id',
            'statement_item_id' => 'required|exists:bank_reconciliation_items,id',
        ]);

        try {
            $glItem = BankReconciliationItem::findOrFail($validated['gl_item_id']);
            $statementItem = BankReconciliationItem::findOrFail($validated['statement_item_id']);

            if ($glItem->reconciliation_id !== $reconciliation->id || $statementItem->reconciliation_id !== $reconciliation->id) {
                return response()->json(['success' => false, 'message' => 'Items do not belong to this reconciliation.'], 400);
            }

            // Match the items
            $glItem->update([
                'is_matched' => true,
                'matched_with_id' => $statementItem->id,
                'matched_date' => now(),
                'is_reconciled' => true,
            ]);

            $statementItem->update([
                'is_matched' => true,
                'matched_with_id' => $glItem->id,
                'matched_date' => now(),
                'is_reconciled' => true,
            ]);

            $this->recalculateVariance($reconciliation);

            return response()->json(['success' => true, 'message' => 'Items matched successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Unmatch items.
     */
    public function unmatchItems(Request $request, BankReconciliation $reconciliation)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:bank_reconciliation_items,id',
        ]);

        try {
            $item = BankReconciliationItem::findOrFail($validated['item_id']);
            $matchedItem = $item->matchedWith;

            $item->update([
                'is_matched' => false,
                'matched_with_id' => null,
                'matched_date' => null,
                'is_reconciled' => false,
            ]);

            if ($matchedItem) {
                $matchedItem->update([
                    'is_matched' => false,
                    'matched_with_id' => null,
                    'matched_date' => null,
                    'is_reconciled' => false,
                ]);
            }

            $this->recalculateVariance($reconciliation);

            return response()->json(['success' => true, 'message' => 'Items unmatched.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Mark item as outstanding.
     */
    public function markOutstanding(Request $request, BankReconciliation $reconciliation)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:bank_reconciliation_items,id',
            'expected_clear_date' => 'nullable|date',
        ]);

        $item = BankReconciliationItem::findOrFail($validated['item_id']);
        $item->update([
            'is_outstanding' => true,
            'expected_clear_date' => $validated['expected_clear_date'],
        ]);

        $this->recalculateVariance($reconciliation);

        return response()->json(['success' => true, 'message' => 'Item marked as outstanding.']);
    }

    /**
     * Submit for review.
     */
    public function submitForReview(BankReconciliation $reconciliation)
    {
        if ($reconciliation->status !== BankReconciliation::STATUS_IN_PROGRESS && $reconciliation->status !== BankReconciliation::STATUS_DRAFT) {
            return back()->with('error', 'Cannot submit this reconciliation for review.');
        }

        $reconciliation->update([
            'status' => BankReconciliation::STATUS_PENDING_REVIEW,
        ]);

        return back()->with('success', 'Reconciliation submitted for review.');
    }

    /**
     * Approve reconciliation.
     */
    public function approve(Request $request, BankReconciliation $reconciliation)
    {
        if ($reconciliation->status !== BankReconciliation::STATUS_PENDING_REVIEW) {
            return $this->errorResponse('Reconciliation is not pending review.', $request);
        }

        $reconciliation->update([
            'status' => BankReconciliation::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return $this->successResponse('Reconciliation approved.', $request);
    }

    /**
     * Finalize reconciliation.
     */
    public function finalize(BankReconciliation $reconciliation)
    {
        if ($reconciliation->status !== BankReconciliation::STATUS_APPROVED) {
            return back()->with('error', 'Only approved reconciliations can be finalized.');
        }

        if (abs($reconciliation->variance) > 0.01) {
            return back()->with('error', 'Cannot finalize with non-zero variance.');
        }

        $reconciliation->update([
            'status' => BankReconciliation::STATUS_FINALIZED,
            'finalized_at' => now(),
        ]);

        return back()->with('success', 'Reconciliation finalized.');
    }

    /**
     * Export to PDF.
     */
    public function exportPdf(BankReconciliation $reconciliation)
    {
        $reconciliation->load(['bank', 'items', 'preparedBy', 'approvedBy']);

        $pdf = \PDF::loadView('accounting.bank-reconciliation.reports.reconciliation-report', [
            'reconciliation' => $reconciliation,
        ]);

        return $pdf->download('reconciliation-' . $reconciliation->reconciliation_number . '.pdf');
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
            'total_reconciliations' => BankReconciliation::count(),
            'draft' => BankReconciliation::where('status', 'draft')->count(),
            'in_progress' => BankReconciliation::where('status', 'in_progress')->count(),
            'pending_review' => BankReconciliation::where('status', 'pending_review')->count(),
            'finalized_this_month' => BankReconciliation::where('status', 'finalized')
                ->whereMonth('finalized_at', now()->month)
                ->whereYear('finalized_at', now()->year)
                ->count(),
            'unmatched_items' => BankReconciliationItem::where('is_matched', false)->count(),
        ];
    }

    /**
     * Calculate GL balance for an account as of a date.
     */
    protected function calculateGLBalance(int $accountId, string $asOfDate): float
    {
        $debits = JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('status', 'posted')
                    ->whereDate('entry_date', '<=', $asOfDate);
            })
            ->sum('debit');

        $credits = JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('status', 'posted')
                    ->whereDate('entry_date', '<=', $asOfDate);
            })
            ->sum('credit');

        // Bank accounts are assets, so debit increases, credit decreases
        return $debits - $credits;
    }

    /**
     * Load GL transactions into reconciliation items.
     */
    protected function loadGLTransactions(BankReconciliation $reconciliation): void
    {
        $lines = JournalEntryLine::where('account_id', $reconciliation->account_id)
            ->whereHas('journalEntry', function ($q) use ($reconciliation) {
                $q->where('status', 'posted')
                    ->whereBetween('entry_date', [
                        $reconciliation->statement_period_from,
                        $reconciliation->statement_period_to
                    ]);
            })
            ->with('journalEntry')
            ->get();

        foreach ($lines as $line) {
            BankReconciliationItem::create([
                'reconciliation_id' => $reconciliation->id,
                'journal_entry_line_id' => $line->id,
                'source' => BankReconciliationItem::SOURCE_GL,
                'item_type' => $this->determineGLItemType($line),
                'transaction_date' => $line->journalEntry->entry_date,
                'reference' => $line->journalEntry->entry_number,
                'description' => $line->description ?: $line->journalEntry->description,
                'amount' => max($line->debit, $line->credit),
                'amount_type' => $line->debit > 0 ? 'debit' : 'credit',
                'is_matched' => false,
                'is_reconciled' => false,
                'is_outstanding' => false,
            ]);
        }
    }

    /**
     * Determine GL item type from journal entry line.
     */
    protected function determineGLItemType(JournalEntryLine $line): string
    {
        $description = strtolower($line->description . ' ' . $line->journalEntry->description);

        if (str_contains($description, 'deposit')) return BankReconciliationItem::TYPE_DEPOSIT;
        if (str_contains($description, 'check') || str_contains($description, 'cheque')) return BankReconciliationItem::TYPE_CHECK;
        if (str_contains($description, 'transfer')) return BankReconciliationItem::TYPE_TRANSFER;
        if (str_contains($description, 'charge') || str_contains($description, 'fee')) return BankReconciliationItem::TYPE_BANK_CHARGE;
        if (str_contains($description, 'interest')) return BankReconciliationItem::TYPE_INTEREST;

        return $line->debit > 0 ? BankReconciliationItem::TYPE_OTHER_DEBIT : BankReconciliationItem::TYPE_OTHER_CREDIT;
    }

    /**
     * Determine item type from statement row.
     */
    protected function determineItemType(array $row): string
    {
        $description = strtolower($row['description'] ?? '');

        if (str_contains($description, 'deposit')) return BankReconciliationItem::TYPE_DEPOSIT;
        if (str_contains($description, 'check') || str_contains($description, 'cheque')) return BankReconciliationItem::TYPE_CHECK;
        if (str_contains($description, 'transfer')) return BankReconciliationItem::TYPE_TRANSFER;
        if (str_contains($description, 'charge') || str_contains($description, 'fee')) return BankReconciliationItem::TYPE_BANK_CHARGE;
        if (str_contains($description, 'interest')) return BankReconciliationItem::TYPE_INTEREST;

        return $row['amount'] >= 0 ? BankReconciliationItem::TYPE_OTHER_CREDIT : BankReconciliationItem::TYPE_OTHER_DEBIT;
    }

    /**
     * Parse statement file.
     */
    protected function parseStatementFile($file): array
    {
        $rows = [];
        $extension = $file->getClientOriginalExtension();

        if ($extension === 'csv') {
            $handle = fopen($file->getRealPath(), 'r');
            $header = fgetcsv($handle);

            while (($data = fgetcsv($handle)) !== false) {
                $row = array_combine($header, $data);
                $rows[] = [
                    'date' => $row['Date'] ?? $row['date'] ?? now()->toDateString(),
                    'reference' => $row['Reference'] ?? $row['reference'] ?? $row['Ref'] ?? null,
                    'description' => $row['Description'] ?? $row['description'] ?? $row['Narration'] ?? '',
                    'amount' => floatval(str_replace([',', ' '], '', $row['Amount'] ?? $row['amount'] ?? 0)),
                ];
            }
            fclose($handle);
        }

        return $rows;
    }

    /**
     * Recalculate variance.
     */
    protected function recalculateVariance(BankReconciliation $reconciliation): void
    {
        $unmatchedGL = $reconciliation->items()
            ->where('source', BankReconciliationItem::SOURCE_GL)
            ->where('is_matched', false)
            ->where('is_outstanding', false)
            ->get();

        $unmatchedStatement = $reconciliation->items()
            ->where('source', BankReconciliationItem::SOURCE_STATEMENT)
            ->where('is_matched', false)
            ->where('is_outstanding', false)
            ->get();

        $outstandingGL = $reconciliation->items()
            ->where('source', BankReconciliationItem::SOURCE_GL)
            ->where('is_outstanding', true)
            ->get();

        // Calculate outstanding deposits and checks
        $outstandingDeposits = $outstandingGL->where('amount_type', 'debit')->sum('amount');
        $outstandingChecks = $outstandingGL->where('amount_type', 'credit')->sum('amount');

        // Calculate variance
        $adjustedGLBalance = $reconciliation->gl_closing_balance
            - $outstandingDeposits
            + $outstandingChecks
            + $reconciliation->unrecorded_credits
            - $reconciliation->unrecorded_charges;

        $variance = $reconciliation->statement_closing_balance - $adjustedGLBalance;

        $reconciliation->update([
            'outstanding_deposits' => $outstandingDeposits,
            'outstanding_checks' => $outstandingChecks,
            'variance' => $variance,
            'status' => $reconciliation->status === 'draft' ? 'in_progress' : $reconciliation->status,
        ]);
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
