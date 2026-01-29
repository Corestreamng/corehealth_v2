<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\JournalEntryEdit;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountingPeriod;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

/**
 * Journal Entry Controller
 *
 * Reference: Accounting System Plan §7.2 - Controllers
 *
 * Handles CRUD operations for journal entries.
 */
class JournalEntryController extends Controller
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;

        // Permission middleware
        $this->middleware('permission:accounting.journal.view')->only(['index', 'show', 'datatable']);
        $this->middleware('permission:accounting.journal.create')->only(['create', 'store']);
        $this->middleware('permission:accounting.journal.edit')->only(['edit', 'update']);
        $this->middleware('permission:accounting.journal.delete')->only(['destroy']);
        $this->middleware('permission:accounting.journal.submit')->only(['submit']);
        $this->middleware('permission:accounting.journal.approve')->only(['approve', 'bulkApprove']);
        $this->middleware('permission:accounting.journal.reject')->only(['reject']);
        $this->middleware('permission:accounting.journal.post')->only(['post', 'bulkPost']);
        $this->middleware('permission:accounting.journal.reverse')->only(['reverse']);
    }

    /**
     * Display journal entries list with stats for dashboard cards.
     */
    public function index(Request $request)
    {
        // Get current period
        $currentPeriod = AccountingPeriod::where('is_closed', false)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        // Calculate stats for the current period
        $periodStart = $currentPeriod?->start_date ?? now()->startOfMonth();
        $periodEnd = $currentPeriod?->end_date ?? now()->endOfMonth();

        $stats = [
            'total' => JournalEntry::whereBetween('entry_date', [$periodStart, $periodEnd])->count(),
            'draft' => JournalEntry::where('status', JournalEntry::STATUS_DRAFT)
                ->whereBetween('entry_date', [$periodStart, $periodEnd])->count(),
            'pending' => JournalEntry::where('status', JournalEntry::STATUS_PENDING)
                ->whereBetween('entry_date', [$periodStart, $periodEnd])->count(),
            'approved' => JournalEntry::where('status', JournalEntry::STATUS_APPROVED)
                ->whereBetween('entry_date', [$periodStart, $periodEnd])->count(),
            'posted' => JournalEntry::where('status', JournalEntry::STATUS_POSTED)
                ->whereBetween('entry_date', [$periodStart, $periodEnd])->count(),
            'reversed' => JournalEntry::where('status', JournalEntry::STATUS_REVERSED)
                ->whereBetween('entry_date', [$periodStart, $periodEnd])->count(),
            'automated' => JournalEntry::where('entry_type', JournalEntry::TYPE_AUTOMATED)
                ->whereBetween('entry_date', [$periodStart, $periodEnd])->count(),
            'total_debits' => JournalEntryLine::whereHas('journalEntry', function ($q) use ($periodStart, $periodEnd) {
                $q->where('status', JournalEntry::STATUS_POSTED)
                    ->whereBetween('entry_date', [$periodStart, $periodEnd]);
            })->sum('debit_amount'),
        ];

        $periods = AccountingPeriod::orderBy('start_date', 'desc')->get();

        return view('accounting.journal-entries.index', compact('stats', 'periods', 'currentPeriod'));
    }

    /**
     * Server-side DataTables endpoint for journal entries.
     */
    public function datatable(Request $request)
    {
        $query = JournalEntry::with(['createdBy', 'lines'])
            ->orderBy('entry_date', 'desc')
            ->orderBy('created_at', 'desc');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('entry_type')) {
            $query->where('entry_type', $request->entry_type);
        }

        if ($request->filled('period_id')) {
            $period = AccountingPeriod::find($request->period_id);
            if ($period) {
                $query->whereBetween('entry_date', [$period->start_date, $period->end_date]);
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('entry_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('entry_date', '<=', $request->date_to);
        }

        return DataTables::of($query)
            ->addColumn('checkbox', function ($entry) {
                // Only allow selection for actionable entries
                if (in_array($entry->status, ['pending', 'approved'])) {
                    return '<input type="checkbox" class="row-checkbox" value="' . $entry->id . '">';
                }
                return '';
            })
            ->addColumn('entry_number', function ($entry) {
                return '<a href="' . route('accounting.journal-entries.show', $entry->id) . '"><code>' . $entry->entry_number . '</code></a>';
            })
            ->addColumn('entry_date_formatted', function ($entry) {
                return $entry->entry_date->format('M d, Y');
            })
            ->addColumn('entry_type_badge', function ($entry) {
                $colors = [
                    'manual' => 'info',
                    'automated' => 'secondary',
                    'adjustment' => 'warning',
                    'reversal' => 'dark',
                    'opening' => 'primary',
                    'closing' => 'danger',
                ];
                $color = $colors[$entry->entry_type] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . ucfirst($entry->entry_type) . '</span>';
            })
            ->addColumn('total_debit_formatted', function ($entry) {
                return '₦' . number_format($entry->lines->sum('debit_amount'), 2);
            })
            ->addColumn('total_credit_formatted', function ($entry) {
                return '₦' . number_format($entry->lines->sum('credit_amount'), 2);
            })
            ->addColumn('status_badge', function ($entry) {
                $colors = [
                    'draft' => 'secondary',
                    'pending' => 'warning',
                    'approved' => 'info',
                    'rejected' => 'danger',
                    'posted' => 'success',
                    'reversed' => 'dark',
                ];
                $color = $colors[$entry->status] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . ucfirst($entry->status) . '</span>';
            })
            ->addColumn('created_by_name', function ($entry) {
                return $entry->createdBy->name ?? 'System';
            })
            ->addColumn('actions', function ($entry) {
                $actions = '<a href="' . route('accounting.journal-entries.show', $entry->id) . '" class="btn btn-sm btn-info" title="View"><i class="mdi mdi-eye"></i></a> ';

                if ($entry->status === 'draft') {
                    if (auth()->user()->can('accounting.journal.edit')) {
                        $actions .= '<a href="' . route('accounting.journal-entries.edit', $entry->id) . '" class="btn btn-sm btn-warning" title="Edit"><i class="mdi mdi-pencil"></i></a> ';
                    }
                    if (auth()->user()->can('accounting.journal.submit')) {
                        $actions .= '<button type="button" class="btn btn-sm btn-primary" onclick="submitEntry(' . $entry->id . ')" title="Submit"><i class="mdi mdi-send"></i></button> ';
                    }
                }

                if ($entry->status === 'pending') {
                    if (auth()->user()->can('accounting.journal.approve')) {
                        $actions .= '<button type="button" class="btn btn-sm btn-success" onclick="approveEntry(' . $entry->id . ')" title="Approve"><i class="mdi mdi-check"></i></button> ';
                        $actions .= '<button type="button" class="btn btn-sm btn-danger" onclick="rejectEntry(' . $entry->id . ', \'' . addslashes($entry->entry_number) . '\')" title="Reject"><i class="mdi mdi-close"></i></button> ';
                    }
                }

                if ($entry->status === 'approved') {
                    if (auth()->user()->can('accounting.journal.post')) {
                        $actions .= '<button type="button" class="btn btn-sm btn-success" onclick="postEntry(' . $entry->id . ')" title="Post"><i class="mdi mdi-book-check"></i></button> ';
                    }
                }

                if ($entry->status === 'posted') {
                    if (auth()->user()->can('accounting.journal.reverse')) {
                        $actions .= '<button type="button" class="btn btn-sm btn-dark" onclick="reverseEntry(' . $entry->id . ')" title="Reverse"><i class="mdi mdi-undo"></i></button> ';
                    }
                }

                return $actions;
            })
            ->rawColumns(['checkbox', 'entry_number', 'entry_type_badge', 'status_badge', 'actions'])
            ->make(true);
    }

    /**
     * Show form for creating a new journal entry.
     */
    public function create()
    {
        $accounts = Account::where('is_active', true)
            ->orderBy('account_code')
            ->get()
            ->map(function ($account) {
                return [
                    'id' => $account->id,
                    'code' => $account->account_code,
                    'name' => $account->name,
                    'full_name' => "{$account->account_code} - {$account->name}",
                    'normal_balance' => $account->normal_balance,
                ];
            });

        return view('accounting.journal-entries.create', compact('accounts'));
    }

    /**
     * Store a new journal entry.
     */
    public function store(Request $request)
    {
        $request->validate([
            'entry_date' => 'required|date',
            'entry_type' => 'nullable|string|in:standard,adjusting,closing,reversing,manual',
            'reference' => 'nullable|string|max:100',
            'description' => 'required|string|max:500',
            'memo' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.debit_amount' => 'required|numeric|min:0',
            'lines.*.credit_amount' => 'required|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:255',
        ]);

        // Validate debits = credits
        $totalDebits = collect($request->lines)->sum('debit_amount');
        $totalCredits = collect($request->lines)->sum('credit_amount');

        if (round($totalDebits, 2) !== round($totalCredits, 2)) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total debits must equal total credits.'
                ], 422);
            }
            return redirect()->back()
                ->withInput()
                ->with('error', 'Total debits must equal total credits.');
        }

        // Check if there's an open period for the entry date
        $period = AccountingPeriod::where('start_date', '<=', $request->entry_date)
            ->where('end_date', '>=', $request->entry_date)
            ->where('is_closed', false)
            ->first();

        if (!$period) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No open accounting period for the selected date.'
                ], 422);
            }
            return redirect()->back()
                ->withInput()
                ->with('error', 'No open accounting period for the selected date.');
        }

        try {
            // Map entry type
            $entryType = match($request->entry_type) {
                'standard', 'manual' => JournalEntry::TYPE_MANUAL,
                'adjusting' => 'adjustment',
                'closing' => 'closing',
                'reversing' => 'reversal',
                default => JournalEntry::TYPE_MANUAL
            };

            $entry = $this->accountingService->createJournalEntry([
                'entry_date' => $request->entry_date,
                'entry_type' => $entryType,
                'reference' => $request->reference,
                'description' => $request->description,
                'memo' => $request->memo,
                'status' => JournalEntry::STATUS_DRAFT,
            ], $request->lines);

            // If submit after save is requested
            if ($request->submit_after_save) {
                $entry->update([
                    'status' => JournalEntry::STATUS_PENDING,
                    'submitted_at' => now(),
                    'submitted_by' => Auth::id(),
                ]);
            }

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Journal entry created successfully.',
                    'entry_id' => $entry->id,
                    'redirect' => route('accounting.journal-entries.show', $entry->id)
                ]);
            }

            return redirect()->route('accounting.journal-entries.show', $entry->id)
                ->with('success', 'Journal entry created successfully.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error creating journal entry: ' . $e->getMessage()
                ], 500);
            }
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error creating journal entry: ' . $e->getMessage());
        }
    }

    /**
     * Display a journal entry.
     */
    public function show($id)
    {
        $entry = JournalEntry::with([
            'lines.account.accountGroup.accountClass',
            'createdBy',
            'approvedBy',
            'postedBy',
            'reversedBy',
            'reversalEntry',
            'originalEntry',
            'edits.requestedBy',
            'edits.approvedBy'
        ])->findOrFail($id);

        return view('accounting.journal-entries.show', compact('entry'));
    }

    /**
     * Show form for editing a journal entry (only for drafts).
     */
    public function edit($id)
    {
        $entry = JournalEntry::with('lines')->findOrFail($id);

        if ($entry->status !== JournalEntry::STATUS_DRAFT) {
            return redirect()->route('accounting.journal-entries.show', $id)
                ->with('error', 'Only draft entries can be edited directly.');
        }

        $accounts = Account::where('is_active', true)
            ->orderBy('account_code')
            ->get();

        return view('accounting.journal-entries.edit', compact('entry', 'accounts'));
    }

    /**
     * Update a journal entry (only for drafts).
     */
    public function update(Request $request, $id)
    {
        $entry = JournalEntry::findOrFail($id);

        if ($entry->status !== JournalEntry::STATUS_DRAFT) {
            return redirect()->route('accounting.journal-entries.show', $id)
                ->with('error', 'Only draft entries can be edited directly.');
        }

        $request->validate([
            'entry_date' => 'required|date',
            'description' => 'required|string|max:500',
            'memo' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.debit_amount' => 'required|numeric|min:0',
            'lines.*.credit_amount' => 'required|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:255',
        ]);

        // Validate debits = credits
        $totalDebits = collect($request->lines)->sum('debit_amount');
        $totalCredits = collect($request->lines)->sum('credit_amount');

        if (round($totalDebits, 2) !== round($totalCredits, 2)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Total debits must equal total credits.');
        }

        DB::transaction(function () use ($entry, $request) {
            // Update entry
            $entry->update([
                'entry_date' => $request->entry_date,
                'description' => $request->description,
                'memo' => $request->memo,
            ]);

            // Delete old lines
            $entry->lines()->delete();

            // Create new lines
            foreach ($request->lines as $line) {
                if ($line['debit_amount'] > 0 || $line['credit_amount'] > 0) {
                    $entry->lines()->create([
                        'account_id' => $line['account_id'],
                        'debit_amount' => $line['debit_amount'] ?: 0,
                        'credit_amount' => $line['credit_amount'] ?: 0,
                        'description' => $line['description'] ?? null,
                    ]);
                }
            }
        });

        return redirect()->route('accounting.journal-entries.show', $id)
            ->with('success', 'Journal entry updated successfully.');
    }

    /**
     * Delete a journal entry (only drafts).
     */
    public function destroy($id)
    {
        $entry = JournalEntry::findOrFail($id);

        if ($entry->status !== JournalEntry::STATUS_DRAFT) {
            return redirect()->back()->with('error', 'Only draft entries can be deleted.');
        }

        $entry->lines()->delete();
        $entry->delete();

        return redirect()->route('accounting.journal-entries.index')
            ->with('success', 'Journal entry deleted.');
    }

    /**
     * Submit entry for approval.
     */
    public function submit(Request $request, $id)
    {
        $entry = JournalEntry::findOrFail($id);

        if ($entry->status !== JournalEntry::STATUS_DRAFT) {
            if ($request->ajax()) {
                return response()->json(['message' => 'Only draft entries can be submitted.'], 400);
            }
            return redirect()->back()->with('error', 'Only draft entries can be submitted.');
        }

        try {
            $this->accountingService->submitForApproval($entry);
            if ($request->ajax()) {
                return response()->json(['message' => 'Entry submitted for approval.']);
            }
            return redirect()->back()->with('success', 'Entry submitted for approval.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Approve a pending entry.
     */
    public function approve(Request $request, $id)
    {
        $entry = JournalEntry::findOrFail($id);

        if ($entry->status !== JournalEntry::STATUS_PENDING) {
            if ($request->ajax()) {
                return response()->json(['message' => 'Only pending entries can be approved.'], 400);
            }
            return redirect()->back()->with('error', 'Only pending entries can be approved.');
        }

        try {
            $this->accountingService->approveEntry($entry);
            if ($request->ajax()) {
                return response()->json(['message' => 'Entry approved successfully.']);
            }
            return redirect()->back()->with('success', 'Entry approved successfully.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reject a pending entry.
     */
    public function reject(Request $request, $id)
    {
        $entry = JournalEntry::findOrFail($id);

        if ($entry->status !== JournalEntry::STATUS_PENDING) {
            if ($request->ajax()) {
                return response()->json(['message' => 'Only pending entries can be rejected.'], 400);
            }
            return redirect()->back()->with('error', 'Only pending entries can be rejected.');
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        try {
            $this->accountingService->rejectEntry($entry, $request->rejection_reason);
            if ($request->ajax()) {
                return response()->json(['message' => 'Entry rejected.']);
            }
            return redirect()->back()->with('success', 'Entry rejected.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Post an approved entry.
     */
    public function post(Request $request, $id)
    {
        $entry = JournalEntry::findOrFail($id);

        if ($entry->status !== JournalEntry::STATUS_APPROVED) {
            if ($request->ajax()) {
                return response()->json(['message' => 'Only approved entries can be posted.'], 400);
            }
            return redirect()->back()->with('error', 'Only approved entries can be posted.');
        }

        try {
            $this->accountingService->postEntry($entry);
            if ($request->ajax()) {
                return response()->json(['message' => 'Entry posted to ledger.']);
            }
            return redirect()->back()->with('success', 'Entry posted to ledger.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Reverse a posted entry.
     */
    public function reverse(Request $request, $id)
    {
        $entry = JournalEntry::findOrFail($id);

        if ($entry->status !== JournalEntry::STATUS_POSTED) {
            if ($request->ajax()) {
                return response()->json(['message' => 'Only posted entries can be reversed.'], 400);
            }
            return redirect()->back()->with('error', 'Only posted entries can be reversed.');
        }

        $request->validate([
            'reversal_reason' => 'required|string|max:500',
        ]);

        try {
            $reversalEntry = $this->accountingService->reverseEntry($entry, $request->reversal_reason);
            if ($request->ajax()) {
                return response()->json([
                    'message' => 'Entry reversed successfully.',
                    'reversal_entry_id' => $reversalEntry->id,
                ]);
            }
            return redirect()->route('accounting.journal-entries.show', $reversalEntry->id)
                ->with('success', 'Entry reversed successfully. Reversal entry created.');
        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Bulk approve multiple pending entries.
     */
    public function bulkApprove(Request $request)
    {
        $request->validate([
            'entry_ids' => 'required|array',
            'entry_ids.*' => 'exists:journal_entries,id',
        ]);

        $approvedCount = 0;
        $errors = [];

        foreach ($request->entry_ids as $id) {
            $entry = JournalEntry::find($id);
            if ($entry && $entry->status === JournalEntry::STATUS_PENDING) {
                try {
                    $this->accountingService->approveEntry($entry);
                    $approvedCount++;
                } catch (\Exception $e) {
                    $errors[] = "Entry {$entry->entry_number}: {$e->getMessage()}";
                }
            }
        }

        return response()->json([
            'message' => "{$approvedCount} entries approved.",
            'approved_count' => $approvedCount,
            'errors' => $errors,
        ]);
    }

    /**
     * Bulk post multiple approved entries.
     */
    public function bulkPost(Request $request)
    {
        $request->validate([
            'entry_ids' => 'required|array',
            'entry_ids.*' => 'exists:journal_entries,id',
        ]);

        $postedCount = 0;
        $errors = [];

        foreach ($request->entry_ids as $id) {
            $entry = JournalEntry::find($id);
            if ($entry && $entry->status === JournalEntry::STATUS_APPROVED) {
                try {
                    $this->accountingService->postEntry($entry);
                    $postedCount++;
                } catch (\Exception $e) {
                    $errors[] = "Entry {$entry->entry_number}: {$e->getMessage()}";
                }
            }
        }

        return response()->json([
            'message' => "{$postedCount} entries posted to ledger.",
            'posted_count' => $postedCount,
            'errors' => $errors,
        ]);
    }

    /**
     * Request edit for a posted entry.
     */
    public function requestEdit(Request $request, $id)
    {
        $entry = JournalEntry::findOrFail($id);

        if ($entry->status !== JournalEntry::STATUS_POSTED) {
            if ($request->ajax()) {
                return response()->json(['message' => 'Edit requests are only for posted entries.'], 400);
            }
            return redirect()->back()->with('error', 'Edit requests are only for posted entries.');
        }

        $request->validate([
            'reason' => 'required|string|max:500',
            'proposed_changes' => 'required|string',
        ]);

        $edit = JournalEntryEdit::create([
            'journal_entry_id' => $entry->id,
            'requested_by' => Auth::id(),
            'reason' => $request->reason,
            'proposed_changes' => $request->proposed_changes,
            'status' => JournalEntryEdit::STATUS_PENDING,
        ]);

        if ($request->ajax()) {
            return response()->json(['message' => 'Edit request submitted for approval.']);
        }

        return redirect()->back()
            ->with('success', 'Edit request submitted for approval.');
    }
}
