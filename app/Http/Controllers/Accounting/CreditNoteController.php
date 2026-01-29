<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CreditNote;
use App\Models\Accounting\CreditNoteItem;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\patient;
use App\Models\invoice;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

/**
 * Credit Note Controller
 *
 * Reference: Accounting System Plan §7.5 - Controllers
 *
 * Manages credit notes (patient refunds).
 */
class CreditNoteController extends Controller
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;

        // Permission middleware (§7.6 - Access Control)
        $this->middleware('permission:credit-notes.view')->only(['index', 'show', 'datatable']);
        $this->middleware('permission:credit-notes.create')->only(['create', 'store']);
        $this->middleware('permission:credit-notes.edit')->only(['edit', 'update']);
        $this->middleware('permission:credit-notes.approve')->only(['approve', 'reject']);
        $this->middleware('permission:credit-notes.apply')->only(['apply']);
    }

    /**
     * Display credit notes list.
     */
    public function index(Request $request)
    {
        // Statistics for stat cards
        $stats = [
            'total' => CreditNote::count(),
            'pending' => CreditNote::where('status', CreditNote::STATUS_PENDING)->count(),
            'approved' => CreditNote::where('status', CreditNote::STATUS_APPROVED)->count(),
            'applied' => CreditNote::where('status', CreditNote::STATUS_APPLIED)->count(),
            'rejected' => CreditNote::where('status', CreditNote::STATUS_REJECTED)->count(),
            'total_amount' => CreditNote::sum('total_amount'),
            'pending_amount' => CreditNote::where('status', CreditNote::STATUS_PENDING)->sum('total_amount'),
            'applied_amount' => CreditNote::where('status', CreditNote::STATUS_APPLIED)->sum('total_amount'),
        ];

        return view('accounting.credit-notes.index', compact('stats'));
    }

    /**
     * DataTable server-side processing for credit notes.
     * Reference: §8.2 - Server-Side DataTables
     */
    public function datatable(Request $request)
    {
        $query = CreditNote::with(['patient.user', 'invoice', 'createdBy', 'approvedBy'])
            ->select('credit_notes.*');

        return DataTables::eloquent($query)
            ->filter(function ($query) use ($request) {
                // Status filter
                if ($request->filled('status')) {
                    $query->where('status', $request->status);
                }

                // Date range filter
                if ($request->filled('from_date')) {
                    $query->where('credit_note_date', '>=', $request->from_date);
                }
                if ($request->filled('to_date')) {
                    $query->where('credit_note_date', '<=', $request->to_date);
                }

                // Patient filter
                if ($request->filled('patient_id')) {
                    $query->where('patient_id', $request->patient_id);
                }

                // Search
                if ($request->filled('search.value')) {
                    $search = $request->input('search.value');
                    $query->where(function ($q) use ($search) {
                        $q->where('credit_note_number', 'like', "%{$search}%")
                            ->orWhere('reason', 'like', "%{$search}%")
                            ->orWhereHas('patient', function ($pq) use ($search) {
                                $pq->whereHas('user', function ($uq) use ($search) {
                                    $uq->where('name', 'like', "%{$search}%");
                                })->orWhere('mrn', 'like', "%{$search}%");
                            });
                    });
                }
            })
            ->addColumn('checkbox', function ($row) {
                return '<input type="checkbox" class="row-checkbox" value="' . $row->id . '">';
            })
            ->addColumn('credit_note_link', function ($row) {
                return '<a href="' . route('accounting.credit-notes.show', $row->id) . '" class="fw-bold">'
                    . e($row->credit_note_number) . '</a>';
            })
            ->addColumn('patient_name', function ($row) {
                if ($row->patient && $row->patient->user) {
                    return '<strong>' . e($row->patient->user->name) . '</strong>'
                        . '<br><small class="text-muted">' . e($row->patient->mrn ?? 'N/A') . '</small>';
                }
                return '<span class="text-muted">-</span>';
            })
            ->addColumn('invoice_number', function ($row) {
                return $row->invoice ? e($row->invoice->invoice_number ?? "INV-{$row->invoice->id}") : '-';
            })
            ->addColumn('formatted_date', function ($row) {
                return $row->credit_note_date ? Carbon::parse($row->credit_note_date)->format('M d, Y') : '-';
            })
            ->addColumn('formatted_amount', function ($row) {
                return number_format($row->total_amount, 2);
            })
            ->addColumn('status_badge', function ($row) {
                $badges = [
                    'pending' => '<span class="badge badge-warning">Pending</span>',
                    'approved' => '<span class="badge badge-success">Approved</span>',
                    'applied' => '<span class="badge badge-info">Applied</span>',
                    'rejected' => '<span class="badge badge-danger">Rejected</span>',
                ];
                return $badges[$row->status] ?? '<span class="badge badge-secondary">Unknown</span>';
            })
            ->addColumn('actions', function ($row) {
                $html = '<div class="dropdown">';
                $html .= '<button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-toggle="dropdown">Actions</button>';
                $html .= '<div class="dropdown-menu dropdown-menu-right">';
                $html .= '<a class="dropdown-item" href="' . route('accounting.credit-notes.show', $row->id) . '"><i class="mdi mdi-eye mr-2"></i>View</a>';

                if ($row->status === 'pending') {
                    $html .= '<a class="dropdown-item" href="' . route('accounting.credit-notes.edit', $row->id) . '"><i class="mdi mdi-pencil mr-2"></i>Edit</a>';
                    $html .= '<a class="dropdown-item text-success btn-approve" href="#" data-id="' . $row->id . '"><i class="mdi mdi-check mr-2"></i>Approve</a>';
                    $html .= '<a class="dropdown-item text-danger btn-reject" href="#" data-id="' . $row->id . '" data-number="' . e($row->credit_note_number) . '"><i class="mdi mdi-close mr-2"></i>Reject</a>';
                }

                if ($row->status === 'approved') {
                    $html .= '<a class="dropdown-item text-primary btn-apply" href="#" data-id="' . $row->id . '" data-number="' . e($row->credit_note_number) . '" data-amount="' . $row->total_amount . '"><i class="mdi mdi-cash-refund mr-2"></i>Apply Refund</a>';
                }

                $html .= '</div></div>';
                return $html;
            })
            ->rawColumns(['checkbox', 'credit_note_link', 'patient_name', 'status_badge', 'actions'])
            ->toJson();
    }

    /**
     * Show form for creating a new credit note.
     */
    public function create(Request $request)
    {
        $patients = patient::with('user')
            ->whereHas('user')
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();

        $selectedPatient = null;
        $invoices = collect();

        if ($request->filled('patient_id')) {
            $selectedPatient = patient::with('user')->find($request->patient_id);
            if ($selectedPatient) {
                $invoices = invoice::where('patient_id', $selectedPatient->id)
                    ->where('status', 'paid')
                    ->orderBy('created_at', 'desc')
                    ->get();
            }
        }

        return view('accounting.credit-notes.create', compact('patients', 'selectedPatient', 'invoices'));
    }

    /**
     * Store a new credit note.
     */
    public function store(Request $request)
    {
        // Support both item-based and simple amount approaches
        $hasItems = $request->has('items') && is_array($request->items) && count($request->items) > 0;

        $rules = [
            'patient_id' => 'required|exists:patients,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'reason_type' => 'nullable|string|max:100',
            'reason' => 'required|string|max:500',
            'supporting_documents' => 'nullable|string|max:1000',
        ];

        if ($hasItems) {
            $rules['items'] = 'required|array|min:1';
            $rules['items.*.description'] = 'required|string|max:255';
            $rules['items.*.amount'] = 'required|numeric|min:0.01';
            $rules['items.*.reference_type'] = 'nullable|string|max:100';
            $rules['items.*.reference_id'] = 'nullable|integer';
        } else {
            $rules['amount'] = 'required|numeric|min:0.01';
        }

        // Handle date field (supports both 'date' and 'credit_note_date')
        if ($request->has('date')) {
            $rules['date'] = 'required|date';
        } else {
            $rules['credit_note_date'] = 'required|date';
        }

        $request->validate($rules);

        try {
            DB::beginTransaction();

            // Generate credit note number or use provided one
            $creditNoteNumber = $request->credit_note_number;
            if (!$creditNoteNumber) {
                $lastCN = CreditNote::whereYear('created_at', now()->year)
                    ->orderBy('id', 'desc')
                    ->first();
                $sequence = $lastCN ? (intval(substr($lastCN->credit_note_number, -5)) + 1) : 1;
                $creditNoteNumber = 'CN-' . now()->format('Y') . '-' . str_pad($sequence, 5, '0', STR_PAD_LEFT);
            }

            // Calculate total amount
            $totalAmount = $hasItems
                ? collect($request->items)->sum('amount')
                : $request->amount;

            // Get date value
            $creditNoteDate = $request->date ?? $request->credit_note_date;

            $creditNote = CreditNote::create([
                'credit_note_number' => $creditNoteNumber,
                'patient_id' => $request->patient_id,
                'invoice_id' => $request->invoice_id,
                'credit_note_date' => $creditNoteDate,
                'total_amount' => $totalAmount,
                'reason_type' => $request->reason_type,
                'reason' => $request->reason,
                'notes' => $request->supporting_documents ?? $request->notes,
                'status' => $request->action === 'submit' ? CreditNote::STATUS_PENDING : CreditNote::STATUS_PENDING, // Always pending since draft isn't a typical status
                'created_by' => Auth::id(),
            ]);

            // Create items
            if ($hasItems) {
                foreach ($request->items as $item) {
                    CreditNoteItem::create([
                        'credit_note_id' => $creditNote->id,
                        'description' => $item['description'],
                        'amount' => $item['amount'],
                        'reference_type' => $item['reference_type'] ?? null,
                        'reference_id' => $item['reference_id'] ?? null,
                    ]);
                }
            } else {
                // Create single item from simple amount
                $reasonTypeLabel = $request->reason_type
                    ? str_replace('_', ' ', ucfirst($request->reason_type))
                    : 'Credit Note';

                CreditNoteItem::create([
                    'credit_note_id' => $creditNote->id,
                    'description' => $reasonTypeLabel . ': ' . ($request->reason ?? 'Credit adjustment'),
                    'amount' => $request->amount,
                ]);
            }

            DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Credit note {$creditNoteNumber} created and pending approval.",
                    'credit_note_id' => $creditNote->id,
                    'redirect' => route('accounting.credit-notes.show', $creditNote->id)
                ]);
            }

            return redirect()->route('accounting.credit-notes.show', $creditNote->id)
                ->with('success', "Credit note {$creditNoteNumber} created and pending approval.");

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error creating credit note: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error creating credit note: ' . $e->getMessage());
        }
    }

    /**
     * Display a credit note.
     */
    public function show($id)
    {
        $creditNote = CreditNote::with([
            'patient.user',
            'invoice',
            'items',
            'createdBy',
            'approvedBy',
            'appliedBy',
            'journalEntry.lines.account'
        ])->findOrFail($id);

        return view('accounting.credit-notes.show', compact('creditNote'));
    }

    /**
     * Approve a credit note.
     */
    public function approve(Request $request, $id)
    {
        $creditNote = CreditNote::findOrFail($id);

        if ($creditNote->status !== CreditNote::STATUS_PENDING) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Only pending credit notes can be approved.'], 400);
            }
            return redirect()->back()->with('error', 'Only pending credit notes can be approved.');
        }

        $creditNote->update([
            'status' => CreditNote::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Credit note {$creditNote->credit_note_number} approved."
            ]);
        }

        return redirect()->back()->with('success', 'Credit note approved.');
    }

    /**
     * Reject a credit note.
     */
    public function reject(Request $request, $id)
    {
        $creditNote = CreditNote::findOrFail($id);

        if ($creditNote->status !== CreditNote::STATUS_PENDING) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Only pending credit notes can be rejected.'], 400);
            }
            return redirect()->back()->with('error', 'Only pending credit notes can be rejected.');
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $creditNote->update([
            'status' => CreditNote::STATUS_REJECTED,
            'rejection_reason' => $request->rejection_reason,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Credit note {$creditNote->credit_note_number} rejected."
            ]);
        }

        return redirect()->back()->with('success', 'Credit note rejected.');
    }

    /**
     * Apply a credit note (process refund and create journal entry).
     */
    public function apply(Request $request, $id)
    {
        $creditNote = CreditNote::findOrFail($id);

        if ($creditNote->status !== CreditNote::STATUS_APPROVED) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Only approved credit notes can be applied.'], 400);
            }
            return redirect()->back()->with('error', 'Only approved credit notes can be applied.');
        }

        $request->validate([
            'refund_method' => 'required|in:cash,bank_transfer,cheque,wallet_credit',
            'refund_reference' => 'nullable|string|max:100',
        ]);

        try {
            DB::beginTransaction();

            // Get accounts based on refund method
            $revenueAccount = Account::where('account_code', '4000')->first(); // Revenue
            $refundAccount = $this->getRefundAccount($request->refund_method);

            if (!$revenueAccount || !$refundAccount) {
                throw new \Exception('Required accounts not configured.');
            }

            // Create journal entry for refund
            // DEBIT: Revenue (reduce income)
            // CREDIT: Cash/Bank (outflow)
            $lines = [
                [
                    'account_id' => $revenueAccount->id,
                    'debit_amount' => $creditNote->total_amount,
                    'credit_amount' => 0,
                    'description' => 'Revenue reversal - refund'
                ],
                [
                    'account_id' => $refundAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $creditNote->total_amount,
                    'description' => "Refund via {$request->refund_method}"
                ]
            ];

            $entry = $this->accountingService->createJournalEntry([
                'entry_type' => JournalEntry::TYPE_AUTOMATED,
                'source_type' => CreditNote::class,
                'source_id' => $creditNote->id,
                'description' => "Credit Note Refund: {$creditNote->credit_note_number}",
                'status' => JournalEntry::STATUS_POSTED,
                'memo' => "Patient refund - {$creditNote->reason}"
            ], $lines);

            // Update credit note
            $creditNote->update([
                'status' => CreditNote::STATUS_APPLIED,
                'applied_by' => Auth::id(),
                'applied_at' => now(),
                'refund_method' => $request->refund_method,
                'refund_reference' => $request->refund_reference,
                'journal_entry_id' => $entry->id,
            ]);

            DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Credit note {$creditNote->credit_note_number} applied and refund processed.",
                    'journal_entry_id' => $entry->id
                ]);
            }

            return redirect()->back()
                ->with('success', 'Credit note applied and refund processed.');

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Error applying credit note: ' . $e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'Error applying credit note: ' . $e->getMessage());
        }
    }

    /**
     * Get refund account based on method.
     */
    protected function getRefundAccount(string $method): ?Account
    {
        $code = match ($method) {
            'cash' => '1010',
            'bank_transfer' => '1020',
            'cheque' => '1020',
            'wallet_credit' => '2200', // Customer Credits/Deposits
            default => '1010'
        };

        return Account::where('account_code', $code)->first();
    }

    /**
     * Bulk approve credit notes.
     * Reference: §8.4 - Bulk Actions
     */
    public function bulkApprove(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:credit_notes,id'
        ]);

        $approved = 0;
        $failed = 0;

        foreach ($request->ids as $id) {
            $creditNote = CreditNote::find($id);
            if ($creditNote && $creditNote->status === CreditNote::STATUS_PENDING) {
                $creditNote->update([
                    'status' => CreditNote::STATUS_APPROVED,
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                ]);
                $approved++;
            } else {
                $failed++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$approved} credit note(s) approved" . ($failed > 0 ? ", {$failed} skipped (not pending)" : "")
        ]);
    }

    /**
     * Get patient invoices for AJAX.
     */
    public function getPatientInvoices($patientId)
    {
        $invoices = invoice::where('patient_id', $patientId)
            ->where('status', 'paid')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($inv) {
                return [
                    'id' => $inv->id,
                    'number' => $inv->invoice_number ?? "INV-{$inv->id}",
                    'date' => $inv->created_at->format('Y-m-d'),
                    'amount' => $inv->total,
                ];
            });

        return response()->json($invoices);
    }
}
