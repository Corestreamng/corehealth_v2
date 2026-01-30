<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\CreditNote;
use App\Models\Accounting\Account;
use App\Models\Bank;
use App\Models\Patient;
use App\Services\Accounting\AccountingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'pending' => CreditNote::where('status', CreditNote::STATUS_PENDING_APPROVAL)->count(),
            'approved' => CreditNote::where('status', CreditNote::STATUS_APPROVED)->count(),
            'processed' => CreditNote::where('status', CreditNote::STATUS_PROCESSED)->count(),
            'voided' => CreditNote::where('status', CreditNote::STATUS_VOID)->count(),
            'total_amount' => CreditNote::sum('amount'),
            'pending_amount' => CreditNote::where('status', CreditNote::STATUS_PENDING_APPROVAL)->sum('amount'),
            'processed_amount' => CreditNote::where('status', CreditNote::STATUS_PROCESSED)->sum('amount'),
        ];

        return view('accounting.credit-notes.index', compact('stats'));
    }

    /**
     * DataTable server-side processing for credit notes.
     * Reference: §8.2 - Server-Side DataTables
     */
    public function datatable(Request $request)
    {
        $query = CreditNote::with(['patient.user', 'originalPayment', 'createdBy', 'approvedBy'])
            ->select('credit_notes.*');

        return DataTables::eloquent($query)
            ->filter(function ($query) use ($request) {
                // Status filter
                if ($request->filled('status')) {
                    $query->where('status', $request->status);
                }

                // Date range filter
                if ($request->filled('from_date')) {
                    $query->where('created_at', '>=', $request->from_date);
                }
                if ($request->filled('to_date')) {
                    $query->where('created_at', '<=', $request->to_date);
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
            ->addColumn('payment_reference', function ($row) {
                return $row->originalPayment ? e($row->originalPayment->reference ?? "PAY-{$row->originalPayment->id}") : '-';
            })
            ->addColumn('formatted_date', function ($row) {
                return $row->created_at ? $row->created_at->format('M d, Y') : '-';
            })
            ->addColumn('formatted_amount', function ($row) {
                return number_format($row->amount, 2);
            })
            ->addColumn('status_badge', function ($row) {
                $badges = [
                    'draft' => '<span class="badge badge-secondary">Draft</span>',
                    'pending_approval' => '<span class="badge badge-warning">Pending</span>',
                    'approved' => '<span class="badge badge-success">Approved</span>',
                    'processed' => '<span class="badge badge-info">Processed</span>',
                    'void' => '<span class="badge badge-dark">Voided</span>',
                ];
                return $badges[$row->status] ?? '<span class="badge badge-secondary">' . ucfirst($row->status) . '</span>';
            })
            ->addColumn('actions', function ($row) {
                $html = '<div class="dropdown">';
                $html .= '<button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-toggle="dropdown">Actions</button>';
                $html .= '<div class="dropdown-menu dropdown-menu-right">';
                $html .= '<a class="dropdown-item" href="' . route('accounting.credit-notes.show', $row->id) . '"><i class="mdi mdi-eye mr-2"></i>View</a>';

                if ($row->status === CreditNote::STATUS_DRAFT) {
                    $html .= '<a class="dropdown-item" href="' . route('accounting.credit-notes.edit', $row->id) . '"><i class="mdi mdi-pencil mr-2"></i>Edit</a>';
                    $html .= '<a class="dropdown-item text-primary btn-submit" href="#" data-id="' . $row->id . '"><i class="mdi mdi-send mr-2"></i>Submit</a>';
                }

                if ($row->status === CreditNote::STATUS_PENDING_APPROVAL) {
                    $html .= '<a class="dropdown-item text-success btn-approve" href="#" data-id="' . $row->id . '"><i class="mdi mdi-check mr-2"></i>Approve</a>';
                    $html .= '<a class="dropdown-item text-danger btn-void" href="#" data-id="' . $row->id . '" data-number="' . e($row->credit_note_number) . '"><i class="mdi mdi-close mr-2"></i>Void</a>';
                }

                if ($row->status === CreditNote::STATUS_APPROVED) {
                    $html .= '<a class="dropdown-item text-primary btn-process" href="#" data-id="' . $row->id . '" data-number="' . e($row->credit_note_number) . '" data-amount="' . $row->amount . '"><i class="mdi mdi-cash-refund mr-2"></i>Process Refund</a>';
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
        $patients = Patient::with('user')
            ->whereHas('user')
            ->orderBy('id', 'desc')
            ->limit(100)
            ->get();

        $selectedPatient = null;
        $payments = collect();

        if ($request->filled('patient_id')) {
            $selectedPatient = Patient::with('user')->find($request->patient_id);
            if ($selectedPatient) {
                // Get payments that can be refunded
                $payments = \App\Models\Payment::where('patient_id', $selectedPatient->id)
                    ->where('status', 'completed')
                    ->orderBy('created_at', 'desc')
                    ->get();
            }
        }

        $banks = \App\Models\Bank::where('is_active', true)->get();

        return view('accounting.credit-notes.create', compact('patients', 'selectedPatient', 'payments', 'banks'));
    }

    /**
     * Store a new credit note.
     */
    public function store(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'original_payment_id' => 'required|exists:payments,id',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:1000',
            'refund_method' => 'required|in:cash,bank,account_credit',
            'bank_id' => 'required_if:refund_method,bank|nullable|exists:banks,id',
        ]);

        try {
            DB::beginTransaction();

            // Generate credit note number
            $lastCN = CreditNote::whereYear('created_at', now()->year)
                ->orderBy('id', 'desc')
                ->first();
            $sequence = $lastCN ? (intval(substr($lastCN->credit_note_number, -4)) + 1) : 1;
            $creditNoteNumber = 'CN' . now()->format('Ym') . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);

            $creditNote = CreditNote::create([
                'credit_note_number' => $creditNoteNumber,
                'patient_id' => $request->patient_id,
                'original_payment_id' => $request->original_payment_id,
                'amount' => $request->amount,
                'reason' => $request->reason,
                'refund_method' => $request->refund_method,
                'bank_id' => $request->refund_method === 'bank' ? $request->bank_id : null,
                'status' => CreditNote::STATUS_DRAFT,
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Credit note {$creditNoteNumber} created as draft.",
                    'credit_note_id' => $creditNote->id,
                    'redirect' => route('accounting.credit-notes.show', $creditNote->id)
                ]);
            }

            return redirect()->route('accounting.credit-notes.show', $creditNote->id)
                ->with('success', "Credit note {$creditNoteNumber} created as draft. Please submit for approval.");

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
            'originalPayment',
            'bank',
            'createdBy',
            'submittedBy',
            'approvedBy',
            'processedBy',
            'voidedBy',
            'journalEntry.lines.account'
        ])->findOrFail($id);

        // Get banks for the process refund modal
        $banks = Bank::where('is_active', true)->orderBy('bank_name')->get();

        return view('accounting.credit-notes.show', compact('creditNote', 'banks'));
    }

    /**
     * Submit a credit note for approval.
     */
    public function submit(Request $request, $id)
    {
        $creditNote = CreditNote::findOrFail($id);

        if ($creditNote->status !== CreditNote::STATUS_DRAFT) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Only draft credit notes can be submitted.'], 400);
            }
            return redirect()->back()->with('error', 'Only draft credit notes can be submitted.');
        }

        $creditNote->update([
            'status' => CreditNote::STATUS_PENDING_APPROVAL,
            'submitted_by' => Auth::id(),
            'submitted_at' => now(),
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Credit note {$creditNote->credit_note_number} submitted for approval."
            ]);
        }

        return redirect()->back()->with('success', 'Credit note submitted for approval.');
    }

    /**
     * Approve a credit note.
     */
    public function approve(Request $request, $id)
    {
        $creditNote = CreditNote::findOrFail($id);

        if ($creditNote->status !== CreditNote::STATUS_PENDING_APPROVAL) {
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
     * Void a credit note.
     */
    public function void(Request $request, $id)
    {
        $creditNote = CreditNote::findOrFail($id);

        if (!in_array($creditNote->status, [CreditNote::STATUS_DRAFT, CreditNote::STATUS_PENDING_APPROVAL, CreditNote::STATUS_APPROVED])) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'This credit note cannot be voided.'], 400);
            }
            return redirect()->back()->with('error', 'This credit note cannot be voided.');
        }

        $request->validate([
            'void_reason' => 'required|string|max:500',
        ]);

        $creditNote->update([
            'status' => CreditNote::STATUS_VOID,
            'voided_by' => Auth::id(),
            'voided_at' => now(),
            'void_reason' => $request->void_reason,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Credit note {$creditNote->credit_note_number} voided."
            ]);
        }

        return redirect()->back()->with('success', 'Credit note voided.');
    }

    /**
     * Process a credit note (apply refund and create journal entry).
     */
    public function process(Request $request, $id)
    {
        $creditNote = CreditNote::findOrFail($id);

        if ($creditNote->status !== CreditNote::STATUS_APPROVED) {
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Only approved credit notes can be processed.'], 400);
            }
            return redirect()->back()->with('error', 'Only approved credit notes can be processed.');
        }

        try {
            DB::beginTransaction();

            // Get accounts based on refund method
            $revenueAccount = Account::where('code', '4000')->first(); // Revenue
            $refundAccount = $this->getRefundAccount($creditNote->refund_method);

            if (!$revenueAccount || !$refundAccount) {
                throw new \Exception('Required accounts not configured.');
            }

            // Create journal entry for refund
            // DEBIT: Revenue (reduce income)
            // CREDIT: Cash/Bank (outflow)
            $lines = [
                [
                    'account_id' => $revenueAccount->id,
                    'debit_amount' => $creditNote->amount,
                    'credit_amount' => 0,
                    'description' => 'Revenue reversal - refund'
                ],
                [
                    'account_id' => $refundAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $creditNote->amount,
                    'description' => "Refund via {$creditNote->refund_method}"
                ]
            ];

            $entry = $this->accountingService->createAndPostAutomatedEntry(
                CreditNote::class,
                $creditNote->id,
                "Credit Note Refund: {$creditNote->credit_note_number} - Patient refund - {$creditNote->reason}",
                $lines
            );

            // Update credit note
            $creditNote->update([
                'status' => CreditNote::STATUS_PROCESSED,
                'processed_by' => Auth::id(),
                'processed_at' => now(),
                'journal_entry_id' => $entry->id,
            ]);

            DB::commit();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Credit note {$creditNote->credit_note_number} processed and refund completed.",
                    'journal_entry_id' => $entry->id
                ]);
            }

            return redirect()->back()
                ->with('success', 'Credit note processed and refund completed.');

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => 'Error processing credit note: ' . $e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'Error processing credit note: ' . $e->getMessage());
        }
    }

    /**
     * Get refund account based on method.
     */
    protected function getRefundAccount(string $method): ?Account
    {
        $code = match ($method) {
            'cash' => '1010',
            'bank' => '1020',
            'account_credit' => '2200', // Customer Credits/Deposits
            default => '1010'
        };

        return Account::where('code', $code)->first();
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
            if ($creditNote && $creditNote->status === CreditNote::STATUS_PENDING_APPROVAL) {
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
     * Get patient payments for AJAX.
     */
    public function getPatientPayments($patientId)
    {
        $payments = \App\Models\Payment::where('patient_id', $patientId)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($pay) {
                return [
                    'id' => $pay->id,
                    'reference' => $pay->reference ?? "PAY-{$pay->id}",
                    'date' => $pay->created_at->format('Y-m-d'),
                    'amount' => $pay->amount,
                ];
            });

        return response()->json($payments);
    }
}
