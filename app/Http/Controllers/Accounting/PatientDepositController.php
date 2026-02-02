<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\PatientDeposit;
use App\Models\Accounting\PatientDepositApplication;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\Account;
use App\Models\PatientAccount;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Bank;
use App\Models\AdmissionRequest;
use App\Services\Accounting\AccountingService;
use App\Services\Accounting\ExcelExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;

/**
 * Patient Deposit Controller
 *
 * Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 4
 * Access: SUPERADMIN|ADMIN|ACCOUNTS|BILLER
 *
 * This controller manages patient deposits with two integration points:
 *
 * 1. LEGACY SYSTEM (PatientAccount + Payment model):
 *    - Quick operations from Billing Workbench
 *    - PatientAccount.balance tracks running balance
 *    - Payment records with ACC_DEPOSIT/ACC_WITHDRAW track transactions
 *    - PaymentObserver creates journal entries automatically
 *
 * 2. NEW ACCOUNTING SYSTEM (PatientDeposit model):
 *    - Detailed deposit tracking by type (admission, procedure, surgery)
 *    - Direct journal entry creation via PatientDepositObserver
 *    - Application tracking via PatientDepositApplication
 *
 * The controller synchronizes both systems:
 * - When creating a PatientDeposit, also update PatientAccount.balance
 * - When applying/refunding, update both systems
 *
 * Integration with Reports:
 * - Positive PatientAccount.balance → Aged Payables (hospital owes patient)
 * - Negative PatientAccount.balance → Aged Receivables (patient owes hospital)
 */
class PatientDepositController extends Controller
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
        $this->middleware('role:SUPERADMIN|ADMIN|ACCOUNTS|BILLER');
    }

    /**
     * Display deposit management dashboard.
     */
    public function index(Request $request)
    {
        // Dashboard stats
        $stats = $this->getDashboardStats();

        // For filters
        $depositTypes = [
            PatientDeposit::TYPE_ADMISSION => 'Admission Deposit',
            PatientDeposit::TYPE_PROCEDURE => 'Procedure Deposit',
            PatientDeposit::TYPE_SURGERY => 'Surgery Deposit',
            PatientDeposit::TYPE_INVESTIGATION => 'Investigation Deposit',
            PatientDeposit::TYPE_GENERAL => 'General Advance',
            PatientDeposit::TYPE_OTHER => 'Other',
        ];

        $statusOptions = [
            PatientDeposit::STATUS_ACTIVE => 'Active',
            PatientDeposit::STATUS_FULLY_APPLIED => 'Fully Applied',
            PatientDeposit::STATUS_REFUNDED => 'Refunded',
            PatientDeposit::STATUS_CANCELLED => 'Cancelled',
        ];

        return view('accounting.patient-deposits.index', compact('stats', 'depositTypes', 'statusOptions'));
    }

    /**
     * Get dashboard statistics.
     */
    protected function getDashboardStats(): array
    {
        $today = now()->toDateString();
        $thisMonth = now()->startOfMonth()->toDateString();

        // From PatientDeposit (new system)
        $totalDeposits = PatientDeposit::active()->sum(DB::raw('amount - utilized_amount - refunded_amount'));
        $activeDepositsCount = PatientDeposit::active()->withBalance()->count();

        // From PatientAccount (legacy system) - for complete picture
        $patientAccountsPositive = PatientAccount::where('balance', '>', 0)->sum('balance');
        $patientAccountsNegative = abs(PatientAccount::where('balance', '<', 0)->sum('balance'));
        $patientsWithCredit = PatientAccount::where('balance', '>', 0)->count();
        $patientsWithDebt = PatientAccount::where('balance', '<', 0)->count();

        // Today's activity
        $todayDeposits = PatientDeposit::whereDate('deposit_date', $today)->sum('amount');
        $todayRefunds = PatientDeposit::whereDate('refunded_at', $today)->sum('refunded_amount');

        // This month's activity
        $monthDeposits = PatientDeposit::whereDate('deposit_date', '>=', $thisMonth)->sum('amount');
        $monthApplications = PatientDepositApplication::whereDate('application_date', '>=', $thisMonth)
            ->where('status', 'applied')
            ->sum('amount');

        // Recent deposits pending application
        $pendingApplication = PatientDeposit::active()
            ->where('utilized_amount', 0)
            ->where('refunded_amount', 0)
            ->count();

        return [
            'total_active_deposits' => $totalDeposits,
            'active_deposits_count' => $activeDepositsCount,
            'patient_credits' => $patientAccountsPositive, // Hospital liability
            'patient_debts' => $patientAccountsNegative,   // Receivable
            'patients_with_credit' => $patientsWithCredit,
            'patients_with_debt' => $patientsWithDebt,
            'today_deposits' => $todayDeposits,
            'today_refunds' => $todayRefunds,
            'month_deposits' => $monthDeposits,
            'month_applications' => $monthApplications,
            'pending_application' => $pendingApplication,
        ];
    }

    /**
     * DataTables server-side processing for deposits.
     */
    public function datatable(Request $request)
    {
        $query = PatientDeposit::with(['patient.user', 'receiver', 'bank', 'admission'])
            ->select('patient_deposits.*');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('deposit_type')) {
            $query->where('deposit_type', $request->deposit_type);
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('deposit_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('deposit_date', '<=', $request->date_to);
        }

        if ($request->filled('search_term')) {
            $term = $request->search_term;
            $query->where(function ($q) use ($term) {
                $q->where('deposit_number', 'like', "%{$term}%")
                    ->orWhereHas('patient.user', function ($q) use ($term) {
                        $q->where('name', 'like', "%{$term}%");
                    })
                    ->orWhereHas('patient', function ($q) use ($term) {
                        $q->where('file_no', 'like', "%{$term}%");
                    });
            });
        }

        return DataTables::of($query)
            ->addColumn('patient_name', function ($deposit) {
                $patient = $deposit->patient;
                if (!$patient) return 'N/A';
                return $patient->user?->name ?? $patient->full_name ?? 'Unknown';
            })
            ->addColumn('file_no', fn($d) => $d->patient?->file_no ?? 'N/A')
            ->addColumn('balance', fn($d) => $d->balance)
            ->addColumn('utilization_percent', fn($d) => $d->utilization_percentage)
            ->addColumn('status_badge', function ($d) {
                $colors = [
                    'active' => 'success',
                    'fully_applied' => 'info',
                    'refunded' => 'warning',
                    'cancelled' => 'danger',
                ];
                return '<span class="badge badge-' . ($colors[$d->status] ?? 'secondary') . '">'
                    . ucfirst(str_replace('_', ' ', $d->status)) . '</span>';
            })
            ->addColumn('actions', function ($d) {
                $actions = '<div class="btn-group btn-group-sm">';
                $actions .= '<a href="' . route('accounting.patient-deposits.show', $d) . '" class="btn btn-info" title="View"><i class="mdi mdi-eye"></i></a>';
                if ($d->isActive() && $d->balance > 0) {
                    $actions .= '<button type="button" class="btn btn-warning btn-apply" data-id="' . $d->id . '" title="Apply to Bill"><i class="mdi mdi-credit-card"></i></button>';
                    $actions .= '<button type="button" class="btn btn-danger btn-refund" data-id="' . $d->id . '" data-balance="' . $d->balance . '" title="Refund"><i class="mdi mdi-cash-refund"></i></button>';
                }
                $actions .= '</div>';
                return $actions;
            })
            ->rawColumns(['status_badge', 'actions'])
            ->make(true);
    }

    /**
     * Show form for creating new deposit.
     */
    public function create(Request $request)
    {
        $patient = null;
        $admission = null;
        $patientAccount = null;

        if ($request->filled('patient_id')) {
            $patient = Patient::with(['user', 'hmo'])->find($request->patient_id);
            $patientAccount = PatientAccount::where('patient_id', $request->patient_id)->first();
        }

        if ($request->filled('admission_id')) {
            $admission = AdmissionRequest::with(['patient.user'])->find($request->admission_id);
            if ($admission && !$patient) {
                $patient = $admission->patient;
                $patientAccount = PatientAccount::where('patient_id', $patient->id)->first();
            }
        }

        $depositTypes = [
            PatientDeposit::TYPE_ADMISSION => 'Admission Deposit',
            PatientDeposit::TYPE_PROCEDURE => 'Procedure Deposit',
            PatientDeposit::TYPE_SURGERY => 'Surgery Deposit',
            PatientDeposit::TYPE_INVESTIGATION => 'Investigation Deposit',
            PatientDeposit::TYPE_GENERAL => 'General Advance',
            PatientDeposit::TYPE_OTHER => 'Other',
        ];

        $paymentMethods = [
            PatientDeposit::METHOD_CASH => 'Cash',
            PatientDeposit::METHOD_POS => 'POS',
            PatientDeposit::METHOD_TRANSFER => 'Bank Transfer',
            PatientDeposit::METHOD_CHEQUE => 'Cheque',
        ];

        $banks = Bank::orderBy('bank_name')->get();

        return view('accounting.patient-deposits.create', compact(
            'patient',
            'admission',
            'patientAccount',
            'depositTypes',
            'paymentMethods',
            'banks'
        ));
    }

    /**
     * Store new patient deposit.
     * Creates PatientDeposit record and syncs with PatientAccount.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'admission_id' => 'nullable|exists:admission_requests,id',
            'amount' => 'required|numeric|min:0.01',
            'deposit_type' => 'required|in:' . implode(',', [
                PatientDeposit::TYPE_ADMISSION,
                PatientDeposit::TYPE_PROCEDURE,
                PatientDeposit::TYPE_SURGERY,
                PatientDeposit::TYPE_INVESTIGATION,
                PatientDeposit::TYPE_GENERAL,
                PatientDeposit::TYPE_OTHER,
            ]),
            'payment_method' => 'required|in:' . implode(',', [
                PatientDeposit::METHOD_CASH,
                PatientDeposit::METHOD_POS,
                PatientDeposit::METHOD_TRANSFER,
                PatientDeposit::METHOD_CHEQUE,
            ]),
            'bank_id' => 'nullable|exists:banks,id',
            'payment_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();

        try {
            // Generate deposit number
            $depositNumber = PatientDeposit::generateNumber();

            // Create PatientDeposit record
            $deposit = PatientDeposit::create([
                'patient_id' => $validated['patient_id'],
                'admission_id' => $validated['admission_id'],
                'deposit_number' => $depositNumber,
                'deposit_date' => now(),
                'amount' => $validated['amount'],
                'utilized_amount' => 0,
                'refunded_amount' => 0,
                'deposit_type' => $validated['deposit_type'],
                'payment_method' => $validated['payment_method'],
                'bank_id' => $validated['bank_id'],
                'payment_reference' => $validated['payment_reference'],
                'receipt_number' => $depositNumber, // Can be customized
                'received_by' => Auth::id(),
                'status' => PatientDeposit::STATUS_ACTIVE,
                'notes' => $validated['notes'],
            ]);
            // Observer creates journal entry automatically

            // SYNC WITH LEGACY SYSTEM: Update PatientAccount balance
            $patientAccount = PatientAccount::firstOrCreate(
                ['patient_id' => $validated['patient_id']],
                ['balance' => 0]
            );

            $patientAccount->balance += $validated['amount'];
            $patientAccount->save();

            // Also create Payment record for legacy tracking (optional - JE already created by observer)
            // We skip this to avoid duplicate JEs since PatientDepositObserver already creates one
            // Payment::create([
            //     'patient_id' => $validated['patient_id'],
            //     'user_id' => Auth::id(),
            //     'total' => $validated['amount'],
            //     'reference_no' => $depositNumber,
            //     'payment_type' => 'ACC_DEPOSIT',
            //     'payment_method' => $validated['payment_method'],
            //     'bank_id' => $validated['bank_id'],
            // ]);

            DB::commit();

            return redirect()
                ->route('accounting.patient-deposits.show', $deposit)
                ->with('success', "Deposit {$depositNumber} created successfully. Amount: ₦" . number_format($validated['amount'], 2));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create patient deposit', [
                'error' => $e->getMessage(),
                'data' => $validated,
            ]);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create deposit: ' . $e->getMessage());
        }
    }

    /**
     * Display deposit details.
     */
    public function show(PatientDeposit $patientDeposit)
    {
        $patientDeposit->load([
            'patient.user',
            'patient.hmo',
            'admission',
            'bank',
            'receiver',
            'refunder',
            'journalEntry.lines.account',
            'refundJournalEntry.lines.account',
            'applications.payment',
            'applications.appliedByUser',
        ]);

        // Get patient's account balance (legacy system)
        $patientAccount = PatientAccount::where('patient_id', $patientDeposit->patient_id)->first();

        // Get related deposits for same patient
        $otherDeposits = PatientDeposit::where('patient_id', $patientDeposit->patient_id)
            ->where('id', '!=', $patientDeposit->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('accounting.patient-deposits.show', compact('patientDeposit', 'patientAccount', 'otherDeposits'));
    }

    /**
     * Apply deposit to a payment/bill (AJAX).
     */
    public function apply(Request $request, PatientDeposit $patientDeposit)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_id' => 'nullable|exists:payments,id',
            'notes' => 'nullable|string|max:255',
        ]);

        if (!$patientDeposit->isActive()) {
            return response()->json(['message' => 'Deposit is not active'], 422);
        }

        if ($validated['amount'] > $patientDeposit->balance) {
            return response()->json(['message' => 'Amount exceeds available balance'], 422);
        }

        DB::beginTransaction();

        try {
            // Create application record
            $application = PatientDepositApplication::create([
                'deposit_id' => $patientDeposit->id,
                'payment_id' => $validated['payment_id'],
                'application_type' => PatientDepositApplication::TYPE_BILL_PAYMENT,
                'amount' => $validated['amount'],
                'application_date' => now(),
                'applied_by' => Auth::id(),
                'status' => PatientDepositApplication::STATUS_APPLIED,
                'notes' => $validated['notes'],
            ]);

            // Update deposit utilized amount
            $patientDeposit->utilized_amount += $validated['amount'];
            if ($patientDeposit->balance <= 0.01) {
                $patientDeposit->status = PatientDeposit::STATUS_FULLY_APPLIED;
            }
            $patientDeposit->save();

            // SYNC WITH LEGACY: Update PatientAccount
            $patientAccount = PatientAccount::where('patient_id', $patientDeposit->patient_id)->first();
            if ($patientAccount) {
                $patientAccount->balance -= $validated['amount'];
                $patientAccount->save();
            }

            // Create journal entry for application
            // DEBIT: Patient Deposits Liability (2350)
            // CREDIT: Accounts Receivable (1200) or Revenue (if direct payment)
            $this->createApplicationJournalEntry($patientDeposit, $application);

            DB::commit();

            return response()->json([
                'message' => 'Deposit applied successfully',
                'new_balance' => $patientDeposit->balance,
                'application_id' => $application->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to apply deposit', [
                'deposit_id' => $patientDeposit->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Failed to apply deposit'], 500);
        }
    }

    /**
     * Refund deposit balance (AJAX).
     */
    public function refund(Request $request, PatientDeposit $patientDeposit)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
        ]);

        if (!$patientDeposit->canBeRefunded()) {
            return response()->json(['message' => 'Deposit cannot be refunded'], 422);
        }

        if ($validated['amount'] > $patientDeposit->balance) {
            return response()->json(['message' => 'Refund amount exceeds available balance'], 422);
        }

        DB::beginTransaction();

        try {
            // Process refund on deposit
            $patientDeposit->refunded_amount += $validated['amount'];
            $patientDeposit->refund_reason = $validated['reason'];
            $patientDeposit->refunded_by = Auth::id();
            $patientDeposit->refunded_at = now();

            if ($patientDeposit->balance <= 0.01) {
                $patientDeposit->status = PatientDeposit::STATUS_REFUNDED;
            }

            $patientDeposit->save();
            // Observer creates refund journal entry automatically on status change to REFUNDED

            // If partial refund, manually create JE since observer only triggers on full refund
            if ($patientDeposit->status !== PatientDeposit::STATUS_REFUNDED) {
                $this->createPartialRefundJournalEntry($patientDeposit, $validated['amount']);
            }

            // SYNC WITH LEGACY: Update PatientAccount
            $patientAccount = PatientAccount::where('patient_id', $patientDeposit->patient_id)->first();
            if ($patientAccount) {
                $patientAccount->balance -= $validated['amount'];
                $patientAccount->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Refund processed successfully',
                'new_balance' => $patientDeposit->fresh()->balance,
                'refunded_amount' => $validated['amount'],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process refund', [
                'deposit_id' => $patientDeposit->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Failed to process refund'], 500);
        }
    }

    /**
     * Get patient's deposits and account summary (AJAX).
     */
    public function getPatientSummary($patientId)
    {
        $patient = Patient::with(['user', 'hmo'])->findOrFail($patientId);

        // Legacy system balance
        $patientAccount = PatientAccount::where('patient_id', $patientId)->first();
        $accountBalance = $patientAccount ? $patientAccount->balance : 0;

        // New system deposits
        $activeDeposits = PatientDeposit::where('patient_id', $patientId)
            ->active()
            ->withBalance()
            ->get();

        $totalActiveDeposits = $activeDeposits->sum('balance');

        // Transaction history summary
        $totalDeposited = PatientDeposit::where('patient_id', $patientId)->sum('amount');
        $totalUtilized = PatientDeposit::where('patient_id', $patientId)->sum('utilized_amount');
        $totalRefunded = PatientDeposit::where('patient_id', $patientId)->sum('refunded_amount');

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'name' => $patient->user?->name ?? $patient->full_name,
                'file_no' => $patient->file_no,
                'hmo' => $patient->hmo?->name,
            ],
            'account' => [
                'balance' => $accountBalance,
                'is_credit' => $accountBalance > 0,
                'is_debt' => $accountBalance < 0,
            ],
            'deposits' => [
                'active_count' => $activeDeposits->count(),
                'total_active_balance' => $totalActiveDeposits,
                'total_deposited' => $totalDeposited,
                'total_utilized' => $totalUtilized,
                'total_refunded' => $totalRefunded,
            ],
            'active_deposits' => $activeDeposits->map(fn($d) => [
                'id' => $d->id,
                'deposit_number' => $d->deposit_number,
                'type' => $d->deposit_type_label,
                'amount' => $d->amount,
                'balance' => $d->balance,
                'date' => $d->deposit_date->format('M d, Y'),
            ]),
        ]);
    }

    /**
     * Search patients for deposit (AJAX).
     */
    public function searchPatients(Request $request)
    {
        $term = $request->input('q', '');

        $patients = Patient::with(['user', 'hmo'])
            ->whereHas('user', function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%");
            })
            ->orWhere('file_no', 'like', "%{$term}%")
            ->orWhere('phone_no', 'like', "%{$term}%")
            ->limit(20)
            ->get();

        return response()->json([
            'results' => $patients->map(fn($p) => [
                'id' => $p->id,
                'text' => ($p->user?->name ?? 'Unknown') . " ({$p->file_no})" . ($p->hmo ? " - {$p->hmo->name}" : ''),
                'name' => $p->user?->name,
                'file_no' => $p->file_no,
                'phone' => $p->phone_no,
                'hmo' => $p->hmo?->name,
            ]),
        ]);
    }

    /**
     * Create journal entry for deposit application.
     */
    protected function createApplicationJournalEntry(PatientDeposit $deposit, PatientDepositApplication $application): void
    {
        $liabilityAccount = Account::where('account_code', '2350')->first();
        $revenueAccount = Account::where('account_code', '4000')->first();

        if (!$liabilityAccount || !$revenueAccount) {
            Log::warning('PatientDepositController: Accounts not found for application JE');
            return;
        }

        $patientName = $deposit->patient?->full_name ?? 'Unknown';

        $entry = $this->accountingService->createAndPostAutomatedEntry(
            PatientDepositApplication::class,
            $application->id,
            "Deposit application: {$deposit->deposit_number} - {$patientName}",
            [
                [
                    'account_id' => $liabilityAccount->id,
                    'debit_amount' => $application->amount,
                    'credit_amount' => 0,
                    'description' => "Apply deposit to services: {$patientName}",
                    'patient_id' => $deposit->patient_id,
                    'category' => 'deposit_application',
                ],
                [
                    'account_id' => $revenueAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $application->amount,
                    'description' => "Revenue from deposit application",
                    'patient_id' => $deposit->patient_id,
                    'category' => 'deposit_application',
                ],
            ]
        );

        $application->journal_entry_id = $entry->id;
        $application->saveQuietly();
    }

    /**
     * Create journal entry for partial refund.
     */
    protected function createPartialRefundJournalEntry(PatientDeposit $deposit, float $amount): void
    {
        $liabilityAccount = Account::where('account_code', '2350')->first();
        $cashAccount = Account::where('account_code', '1010')->first();

        if (!$liabilityAccount || !$cashAccount) {
            Log::warning('PatientDepositController: Accounts not found for partial refund JE');
            return;
        }

        $patientName = $deposit->patient?->full_name ?? 'Unknown';

        $this->accountingService->createAndPostAutomatedEntry(
            PatientDeposit::class,
            $deposit->id,
            "Partial refund: {$deposit->deposit_number} - {$patientName}",
            [
                [
                    'account_id' => $liabilityAccount->id,
                    'debit_amount' => $amount,
                    'credit_amount' => 0,
                    'description' => "Partial refund to patient: {$patientName}",
                    'patient_id' => $deposit->patient_id,
                    'category' => 'deposit_refund',
                ],
                [
                    'account_id' => $cashAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $amount,
                    'description' => "Cash refund: {$deposit->deposit_number}",
                    'patient_id' => $deposit->patient_id,
                    'category' => 'deposit_refund',
                ],
            ]
        );
    }

    /**
     * Export deposits to Excel or PDF.
     */
    public function export(Request $request)
    {
        $query = PatientDeposit::with(['patient', 'paymentMethod'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->when($request->filled('type'), fn($q) => $q->where('deposit_type', $request->type))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('deposit_date', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('deposit_date', '<=', $request->date_to));

        $deposits = $query->orderBy('deposit_date', 'desc')->get();

        $stats = [
            'total' => $deposits->count(),
            'active' => $deposits->where('status', 'active')->count(),
            'total_deposits' => $deposits->sum('amount'),
            'total_applied' => $deposits->sum('applied_amount'),
            'total_balance' => $deposits->sum('balance'),
            'total_refunded' => $deposits->where('status', 'refunded')->sum('amount'),
        ];

        // Check export format
        if ($request->format === 'pdf') {
            $pdf = Pdf::loadView('accounting.patient-deposits.export-pdf', compact('deposits', 'stats'));
            return $pdf->download('patient-deposits-' . now()->format('Y-m-d') . '.pdf');
        }

        // Default to Excel
        $excelService = app(ExcelExportService::class);
        return $excelService->patientDeposits($deposits, $stats);
    }
}
