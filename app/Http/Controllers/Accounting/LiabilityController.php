<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\ExcelExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

/**
 * Liability Controller
 *
 * Reference: ACCOUNTING_UI_IMPLEMENTATION_PLAN.md - Section 7
 *
 * Handles loan/liability management including:
 * - Liability registration and tracking
 * - Payment schedules and amortization
 * - Payment recording
 * - Balance tracking
 *
 * Access: SUPERADMIN|ADMIN|ACCOUNTS roles
 */
class LiabilityController extends Controller
{
    /**
     * Display liabilities list with dashboard stats.
     */
    public function index()
    {
        $stats = $this->getDashboardStats();

        return view('accounting.liabilities.index', compact('stats'));
    }

    /**
     * Get dashboard statistics for liabilities.
     */
    protected function getDashboardStats(): array
    {
        $liabilities = DB::table('liability_schedules')
            ->whereNull('deleted_at')
            ->get();

        $activeCount = $liabilities->where('status', 'active')->count();
        $totalBalance = $liabilities->where('status', 'active')->sum('current_balance');
        $currentPortion = $liabilities->where('status', 'active')->sum('current_portion');
        $nonCurrentPortion = $liabilities->where('status', 'active')->sum('non_current_portion');

        // This month's payments
        $thisMonthStart = Carbon::now()->startOfMonth();
        $thisMonthEnd = Carbon::now()->endOfMonth();

        $paymentsThisMonth = DB::table('liability_payment_schedules')
            ->whereBetween('due_date', [$thisMonthStart, $thisMonthEnd])
            ->whereNull('paid_date')
            ->sum('payment_amount');

        // Overdue payments
        $overduePayments = DB::table('liability_payment_schedules')
            ->where('due_date', '<', Carbon::now())
            ->whereNull('paid_date')
            ->sum('payment_amount');

        // By type breakdown
        $byType = $liabilities->where('status', 'active')
            ->groupBy('liability_type')
            ->map(fn($items) => [
                'count' => $items->count(),
                'balance' => $items->sum('current_balance'),
            ])
            ->toArray();

        return [
            'active_count' => $activeCount,
            'total_balance' => $totalBalance,
            'current_portion' => $currentPortion,
            'non_current_portion' => $nonCurrentPortion,
            'payments_due_this_month' => $paymentsThisMonth,
            'overdue_payments' => $overduePayments,
            'by_type' => $byType,
        ];
    }

    /**
     * DataTable endpoint for liabilities.
     */
    public function datatable(Request $request)
    {
        $query = DB::table('liability_schedules')
            ->leftJoin('accounts', 'liability_schedules.account_id', '=', 'accounts.id')
            ->leftJoin('users', 'liability_schedules.created_by', '=', 'users.id')
            ->whereNull('liability_schedules.deleted_at')
            ->select([
                'liability_schedules.*',
                'accounts.name as account_name',
                'accounts.code as account_code',
                'users.name as created_by_name',
            ])
            ->orderBy('liability_schedules.created_at', 'desc');

        // Apply filters
        if ($request->filled('status')) {
            $query->where('liability_schedules.status', $request->status);
        }

        if ($request->filled('liability_type')) {
            $query->where('liability_schedules.liability_type', $request->liability_type);
        }

        if ($request->filled('creditor')) {
            $query->where('liability_schedules.creditor_name', 'like', '%' . $request->creditor . '%');
        }

        return DataTables::of($query)
            ->addColumn('start_date_formatted', fn($l) => Carbon::parse($l->start_date)->format('M d, Y'))
            ->addColumn('maturity_date_formatted', fn($l) => Carbon::parse($l->maturity_date)->format('M d, Y'))
            ->addColumn('principal_formatted', fn($l) => '₦' . number_format($l->principal_amount, 2))
            ->addColumn('balance_formatted', fn($l) => '₦' . number_format($l->current_balance, 2))
            ->addColumn('type_badge', function ($l) {
                $colors = [
                    'loan' => 'primary',
                    'mortgage' => 'info',
                    'bond' => 'dark',
                    'deferred_revenue' => 'warning',
                    'other' => 'secondary',
                ];
                $color = $colors[$l->liability_type] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . ucfirst(str_replace('_', ' ', $l->liability_type)) . '</span>';
            })
            ->addColumn('status_badge', function ($l) {
                $colors = [
                    'active' => 'success',
                    'paid_off' => 'info',
                    'restructured' => 'warning',
                    'defaulted' => 'danger',
                    'cancelled' => 'secondary',
                ];
                $color = $colors[$l->status] ?? 'secondary';
                return '<span class="badge badge-' . $color . '">' . ucfirst(str_replace('_', ' ', $l->status)) . '</span>';
            })
            ->addColumn('actions', function ($l) {
                return '
                    <div class="btn-group btn-group-sm">
                        <a href="' . route('accounting.liabilities.show', $l->id) . '" class="btn btn-info" title="View">
                            <i class="mdi mdi-eye"></i>
                        </a>
                        <a href="' . route('accounting.liabilities.edit', $l->id) . '" class="btn btn-warning" title="Edit">
                            <i class="mdi mdi-pencil"></i>
                        </a>
                        <a href="' . route('accounting.liabilities.payment', $l->id) . '" class="btn btn-success" title="Record Payment">
                            <i class="mdi mdi-cash-plus"></i>
                        </a>
                    </div>
                ';
            })
            ->rawColumns(['type_badge', 'status_badge', 'actions'])
            ->make(true);
    }

    /**
     * Show create liability form.
     */
    public function create()
    {
        $accounts = DB::table('accounts')
            ->where('is_active', true)
            ->where('account_type', 'Liability')
            ->orderBy('code')
            ->get();

        $expenseAccounts = DB::table('accounts')
            ->where('is_active', true)
            ->where('account_type', 'Expense')
            ->orderBy('code')
            ->get();

        return view('accounting.liabilities.create', compact('accounts', 'expenseAccounts'));
    }

    /**
     * Store new liability.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'liability_type' => 'required|in:loan,mortgage,bond,deferred_revenue,other',
            'creditor_name' => 'required|string|max:255',
            'creditor_contact' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:100',
            'principal_amount' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'interest_type' => 'required|in:fixed,variable',
            'start_date' => 'required|date',
            'maturity_date' => 'required|date|after:start_date',
            'term_months' => 'required|integer|min:1',
            'payment_frequency' => 'required|in:monthly,quarterly,semi_annually,annually',
            'account_id' => 'required|exists:accounts,id',
            'interest_expense_account_id' => 'required|exists:accounts,id',
            'collateral_description' => 'nullable|string|max:500',
            'collateral_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            // Generate liability number
            $lastLiability = DB::table('liability_schedules')->orderBy('id', 'desc')->first();
            $nextNum = $lastLiability ? intval(substr($lastLiability->liability_number, 4)) + 1 : 1;
            $liabilityNumber = 'LIB-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

            // Calculate regular payment amount
            $regularPayment = $this->calculateRegularPayment(
                $validated['principal_amount'],
                $validated['interest_rate'],
                $validated['term_months'],
                $validated['payment_frequency']
            );

            // Create liability
            $liabilityId = DB::table('liability_schedules')->insertGetId([
                'liability_number' => $liabilityNumber,
                'liability_type' => $validated['liability_type'],
                'creditor_name' => $validated['creditor_name'],
                'creditor_contact' => $validated['creditor_contact'],
                'reference_number' => $validated['reference_number'],
                'principal_amount' => $validated['principal_amount'],
                'current_balance' => $validated['principal_amount'],
                'interest_rate' => $validated['interest_rate'],
                'interest_type' => $validated['interest_type'],
                'start_date' => $validated['start_date'],
                'maturity_date' => $validated['maturity_date'],
                'term_months' => $validated['term_months'],
                'payment_frequency' => $validated['payment_frequency'],
                'regular_payment_amount' => $regularPayment,
                'next_payment_date' => $this->calculateNextPaymentDate($validated['start_date'], $validated['payment_frequency']),
                'account_id' => $validated['account_id'],
                'interest_expense_account_id' => $validated['interest_expense_account_id'],
                'collateral_description' => $validated['collateral_description'],
                'collateral_value' => $validated['collateral_value'],
                'current_portion' => $this->calculateCurrentPortion($validated['principal_amount'], $validated['term_months'], 12),
                'non_current_portion' => $this->calculateNonCurrentPortion($validated['principal_amount'], $validated['term_months'], 12),
                'status' => 'active',
                'notes' => $validated['notes'],
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Generate payment schedule
            $this->generatePaymentSchedule($liabilityId, $validated);

            DB::commit();

            return redirect()
                ->route('accounting.liabilities.show', $liabilityId)
                ->with('success', "Liability {$liabilityNumber} created successfully with payment schedule.");

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'Failed to create liability: ' . $e->getMessage());
        }
    }

    /**
     * Show liability details with payment schedule.
     */
    public function show($id)
    {
        $liability = DB::table('liability_schedules')
            ->leftJoin('accounts', 'liability_schedules.account_id', '=', 'accounts.id')
            ->leftJoin('accounts as expense_acc', 'liability_schedules.interest_expense_account_id', '=', 'expense_acc.id')
            ->leftJoin('users', 'liability_schedules.created_by', '=', 'users.id')
            ->where('liability_schedules.id', $id)
            ->whereNull('liability_schedules.deleted_at')
            ->select([
                'liability_schedules.*',
                'accounts.name as account_name',
                'accounts.code as account_code',
                'expense_acc.name as interest_account_name',
                'expense_acc.code as interest_account_code',
                'users.name as created_by_name',
            ])
            ->first();

        if (!$liability) {
            abort(404);
        }

        // Get payment schedule
        $paymentSchedule = DB::table('liability_payment_schedules')
            ->where('liability_id', $id)
            ->orderBy('due_date')
            ->get();

        // Payment summary
        $paidCount = $paymentSchedule->whereNotNull('paid_date')->count();
        $totalPaid = $paymentSchedule->whereNotNull('paid_date')->sum('amount_paid');
        $pendingCount = $paymentSchedule->whereNull('paid_date')->count();
        $overdueCount = $paymentSchedule->where('due_date', '<', now()->toDateString())->whereNull('paid_date')->count();

        return view('accounting.liabilities.show', compact(
            'liability',
            'paymentSchedule',
            'paidCount',
            'totalPaid',
            'pendingCount',
            'overdueCount'
        ));
    }

    /**
     * Show edit liability form.
     */
    public function edit($id)
    {
        $liability = DB::table('liability_schedules')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$liability) {
            abort(404);
        }

        $accounts = DB::table('accounts')
            ->where('is_active', true)
            ->where('account_type', 'Liability')
            ->orderBy('code')
            ->get();

        $expenseAccounts = DB::table('accounts')
            ->where('is_active', true)
            ->where('account_type', 'Expense')
            ->orderBy('code')
            ->get();

        return view('accounting.liabilities.edit', compact('liability', 'accounts', 'expenseAccounts'));
    }

    /**
     * Update liability.
     */
    public function update(Request $request, $id)
    {
        $liability = DB::table('liability_schedules')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$liability) {
            abort(404);
        }

        $validated = $request->validate([
            'creditor_name' => 'required|string|max:255',
            'creditor_contact' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:100',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'collateral_description' => 'nullable|string|max:500',
            'collateral_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::table('liability_schedules')
            ->where('id', $id)
            ->update([
                'creditor_name' => $validated['creditor_name'],
                'creditor_contact' => $validated['creditor_contact'],
                'reference_number' => $validated['reference_number'],
                'interest_rate' => $validated['interest_rate'],
                'collateral_description' => $validated['collateral_description'],
                'collateral_value' => $validated['collateral_value'],
                'notes' => $validated['notes'],
                'updated_at' => now(),
            ]);

        return redirect()
            ->route('accounting.liabilities.show', $id)
            ->with('success', 'Liability updated successfully.');
    }

    /**
     * Show payment recording form.
     */
    public function payment($id)
    {
        $liability = DB::table('liability_schedules')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$liability) {
            abort(404);
        }

        // Get next unpaid scheduled payment
        $nextPayment = DB::table('liability_payment_schedules')
            ->where('liability_id', $id)
            ->whereNull('paid_date')
            ->orderBy('due_date')
            ->first();

        $banks = DB::table('banks')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('accounting.liabilities.payment', compact('liability', 'nextPayment', 'banks'));
    }

    /**
     * Record liability payment.
     */
    public function recordPayment(Request $request, $id)
    {
        $liability = DB::table('liability_schedules')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$liability) {
            abort(404);
        }

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount_paid' => 'required|numeric|min:0.01',
            'principal_paid' => 'required|numeric|min:0',
            'interest_paid' => 'required|numeric|min:0',
            'bank_id' => 'required|exists:banks,id',
            'payment_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            // Find next unpaid schedule entry and update
            $schedule = DB::table('liability_payment_schedules')
                ->where('liability_id', $id)
                ->whereNull('paid_date')
                ->orderBy('due_date')
                ->first();

            if ($schedule) {
                DB::table('liability_payment_schedules')
                    ->where('id', $schedule->id)
                    ->update([
                        'paid_date' => $validated['payment_date'],
                        'amount_paid' => $validated['amount_paid'],
                        'principal_paid' => $validated['principal_paid'],
                        'interest_paid' => $validated['interest_paid'],
                        'payment_reference' => $validated['payment_reference'],
                        'updated_at' => now(),
                    ]);
            }

            // Update liability balance
            $newBalance = $liability->current_balance - $validated['principal_paid'];

            // Calculate next payment date
            $nextPayment = DB::table('liability_payment_schedules')
                ->where('liability_id', $id)
                ->whereNull('paid_date')
                ->orderBy('due_date')
                ->first();

            $status = $newBalance <= 0 ? 'paid_off' : 'active';

            DB::table('liability_schedules')
                ->where('id', $id)
                ->update([
                    'current_balance' => max(0, $newBalance),
                    'next_payment_date' => $nextPayment?->due_date,
                    'status' => $status,
                    'updated_at' => now(),
                ]);

            // TODO: Create journal entry for payment via observer

            DB::commit();

            return redirect()
                ->route('accounting.liabilities.show', $id)
                ->with('success', 'Payment recorded successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'Failed to record payment: ' . $e->getMessage());
        }
    }

    /**
     * View amortization schedule.
     */
    public function amortizationSchedule($id)
    {
        $liability = DB::table('liability_schedules')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$liability) {
            abort(404);
        }

        $schedule = DB::table('liability_payment_schedules')
            ->where('liability_id', $id)
            ->orderBy('due_date')
            ->get();

        return view('accounting.liabilities.schedule', compact('liability', 'schedule'));
    }

    /**
     * Export liabilities to PDF.
     */
    public function exportPdf(Request $request)
    {
        $query = DB::table('liability_schedules')
            ->leftJoin('vendors', 'liability_schedules.vendor_id', '=', 'vendors.id')
            ->select(
                'liability_schedules.*',
                'vendors.name as vendor_name'
            )
            ->whereNull('liability_schedules.deleted_at');

        if ($request->filled('status')) {
            $query->where('liability_schedules.status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('liability_schedules.liability_type', $request->type);
        }

        $liabilities = $query->orderBy('created_at', 'desc')->get();

        $stats = [
            'total' => $liabilities->count(),
            'active' => $liabilities->where('status', 'active')->count(),
            'total_principal' => $liabilities->sum('principal_amount'),
            'total_balance' => $liabilities->sum('current_balance'),
            'current_portion' => $liabilities->where('status', 'active')->sum('current_portion'),
            'non_current' => $liabilities->where('status', 'active')->sum('non_current_portion'),
        ];

        $pdf = Pdf::loadView('accounting.liabilities.export-pdf', compact('liabilities', 'stats'));
        return $pdf->download('liabilities-' . now()->format('Y-m-d') . '.pdf');
    }

    /**
     * Export liabilities to Excel.
     */
    public function exportExcel(Request $request)
    {
        $query = DB::table('liability_schedules')
            ->leftJoin('vendors', 'liability_schedules.vendor_id', '=', 'vendors.id')
            ->select(
                'liability_schedules.*',
                'vendors.name as vendor_name'
            )
            ->whereNull('liability_schedules.deleted_at');

        if ($request->filled('status')) {
            $query->where('liability_schedules.status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('liability_schedules.liability_type', $request->type);
        }

        $liabilities = $query->orderBy('created_at', 'desc')->get();

        $stats = [
            'total' => $liabilities->count(),
            'active' => $liabilities->where('status', 'active')->count(),
            'total_principal' => $liabilities->sum('principal_amount'),
            'total_balance' => $liabilities->sum('current_balance'),
        ];

        $excelService = app(ExcelExportService::class);
        return $excelService->liabilities($liabilities, $stats);
    }

    // ==========================================
    // HELPER METHODS
    // ==========================================

    /**
     * Calculate regular payment amount using PMT formula.
     */
    protected function calculateRegularPayment(float $principal, float $annualRate, int $termMonths, string $frequency): float
    {
        $periodsPerYear = match($frequency) {
            'monthly' => 12,
            'quarterly' => 4,
            'semi_annually' => 2,
            'annually' => 1,
            default => 12,
        };

        $totalPayments = ($termMonths / 12) * $periodsPerYear;
        $periodicRate = ($annualRate / 100) / $periodsPerYear;

        if ($periodicRate == 0) {
            return $principal / $totalPayments;
        }

        // PMT formula: P * [r(1+r)^n] / [(1+r)^n - 1]
        $payment = $principal * ($periodicRate * pow(1 + $periodicRate, $totalPayments))
                   / (pow(1 + $periodicRate, $totalPayments) - 1);

        return round($payment, 2);
    }

    /**
     * Calculate next payment date based on frequency.
     */
    protected function calculateNextPaymentDate(string $startDate, string $frequency): string
    {
        $date = Carbon::parse($startDate);

        return match($frequency) {
            'monthly' => $date->addMonth()->toDateString(),
            'quarterly' => $date->addMonths(3)->toDateString(),
            'semi_annually' => $date->addMonths(6)->toDateString(),
            'annually' => $date->addYear()->toDateString(),
            default => $date->addMonth()->toDateString(),
        };
    }

    /**
     * Calculate current portion (due within 12 months).
     */
    protected function calculateCurrentPortion(float $principal, int $termMonths, int $monthsToConsider = 12): float
    {
        if ($termMonths <= $monthsToConsider) {
            return $principal;
        }
        return round($principal * ($monthsToConsider / $termMonths), 2);
    }

    /**
     * Calculate non-current portion (due after 12 months).
     */
    protected function calculateNonCurrentPortion(float $principal, int $termMonths, int $monthsToConsider = 12): float
    {
        if ($termMonths <= $monthsToConsider) {
            return 0;
        }
        return round($principal * (($termMonths - $monthsToConsider) / $termMonths), 2);
    }

    /**
     * Generate payment schedule for liability.
     */
    protected function generatePaymentSchedule(int $liabilityId, array $data): void
    {
        $periodsPerYear = match($data['payment_frequency']) {
            'monthly' => 12,
            'quarterly' => 4,
            'semi_annually' => 2,
            'annually' => 1,
            default => 12,
        };

        $totalPayments = (int)(($data['term_months'] / 12) * $periodsPerYear);
        $periodicRate = ($data['interest_rate'] / 100) / $periodsPerYear;
        $balance = $data['principal_amount'];
        $payment = $this->calculateRegularPayment(
            $data['principal_amount'],
            $data['interest_rate'],
            $data['term_months'],
            $data['payment_frequency']
        );

        $paymentDate = Carbon::parse($data['start_date']);
        $monthsToAdd = match($data['payment_frequency']) {
            'monthly' => 1,
            'quarterly' => 3,
            'semi_annually' => 6,
            'annually' => 12,
            default => 1,
        };

        for ($i = 1; $i <= $totalPayments; $i++) {
            $paymentDate = $paymentDate->copy()->addMonths($monthsToAdd);

            $interestAmount = round($balance * $periodicRate, 2);
            $principalAmount = round($payment - $interestAmount, 2);

            // Last payment adjustment
            if ($i == $totalPayments) {
                $principalAmount = $balance;
                $payment = $principalAmount + $interestAmount;
            }

            $balance -= $principalAmount;
            if ($balance < 0) $balance = 0;

            DB::table('liability_payment_schedules')->insert([
                'liability_id' => $liabilityId,
                'payment_number' => $i,
                'due_date' => $paymentDate->toDateString(),
                'payment_amount' => $payment,
                'principal_amount' => $principalAmount,
                'interest_amount' => $interestAmount,
                'balance_after_payment' => $balance,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
