<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\LiabilitySchedule;
use App\Models\Accounting\LiabilityPaymentSchedule;
use App\Models\Accounting\Account;
use App\Services\Accounting\ExcelExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            ->whereNull('payment_date')
            ->sum('scheduled_payment');

        // Overdue payments
        $overduePayments = DB::table('liability_payment_schedules')
            ->where('due_date', '<', Carbon::now())
            ->whereNull('payment_date')
            ->sum('scheduled_payment');

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
                DB::raw("CONCAT(users.firstname, ' ', users.surname) as created_by_name"),
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
        // Get Liability accounts (class code 2 = Liabilities)
        $accounts = DB::table('accounts')
            ->join('account_groups', 'accounts.account_group_id', '=', 'account_groups.id')
            ->join('account_classes', 'account_groups.account_class_id', '=', 'account_classes.id')
            ->where('accounts.is_active', true)
            ->where('account_classes.code', '2') // Liabilities class
            ->select('accounts.id', 'accounts.code', 'accounts.name')
            ->orderBy('accounts.code')
            ->get();

        // Get Expense accounts (class code 5 = Expenses)
        $expenseAccounts = DB::table('accounts')
            ->join('account_groups', 'accounts.account_group_id', '=', 'account_groups.id')
            ->join('account_classes', 'account_groups.account_class_id', '=', 'account_classes.id')
            ->where('accounts.is_active', true)
            ->where('account_classes.code', '5') // Expenses class
            ->select('accounts.id', 'accounts.code', 'accounts.name')
            ->orderBy('accounts.code')
            ->get();

        $banks = DB::table('banks')->where('is_active', true)->orderBy('name')->get();

        return view('accounting.liabilities.create', compact('accounts', 'expenseAccounts', 'banks'));
    }

    /**
     * Store new liability.
     * Uses Eloquent to trigger LiabilityScheduleObserver which:
     * 1. Creates initial JE: DEBIT Bank, CREDIT Liability Account
     * 2. Generates the amortization schedule
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'liability_type' => 'required|in:loan,mortgage,overdraft,credit_line,bond,deferred_revenue,other',
            'creditor_name' => 'required|string|max:255',
            'creditor_contact' => 'nullable|string|max:255',
            'reference_number' => 'nullable|string|max:100',
            'principal_amount' => 'required|numeric|min:0',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'interest_type' => 'required|in:simple,compound,flat',
            'start_date' => 'required|date',
            'maturity_date' => 'required|date|after:start_date',
            'term_months' => 'required|integer|min:1',
            'payment_frequency' => 'required|in:weekly,bi_weekly,monthly,quarterly,semi_annually,annually,at_maturity',
            'account_id' => 'required|exists:accounts,id',
            'interest_expense_account_id' => 'required|exists:accounts,id',
            'bank_account_id' => 'nullable|exists:accounts,id',
            'collateral_description' => 'nullable|string|max:500',
            'collateral_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            // Calculate regular payment amount (for display - observer recalculates)
            $regularPayment = $this->calculateRegularPayment(
                $validated['principal_amount'],
                $validated['interest_rate'],
                $validated['term_months'],
                $validated['payment_frequency']
            );

            // Create liability using Eloquent - triggers LiabilityScheduleObserver
            $liability = LiabilitySchedule::create([
                'liability_number' => LiabilitySchedule::generateNumber(),
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
                'bank_account_id' => $validated['bank_account_id'],
                'collateral_description' => $validated['collateral_description'],
                'collateral_value' => $validated['collateral_value'],
                'current_portion' => $this->calculateCurrentPortion($validated['principal_amount'], $validated['term_months'], 12),
                'non_current_portion' => $this->calculateNonCurrentPortion($validated['principal_amount'], $validated['term_months'], 12),
                'status' => 'active',
                'notes' => $validated['notes'],
                'created_by' => Auth::id(),
            ]);

            return redirect()
                ->route('accounting.liabilities.show', $liability->id)
                ->with('success', "Liability {$liability->liability_number} created successfully. Journal entry and payment schedule generated automatically.");

        } catch (\Exception $e) {
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
                DB::raw("CONCAT(users.firstname, ' ', users.surname) as created_by_name"),
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

        // Payment summary - use correct column names from migration
        $paidCount = $paymentSchedule->whereNotNull('payment_date')->count();
        $totalPaid = $paymentSchedule->whereNotNull('payment_date')->sum('actual_payment');
        $pendingCount = $paymentSchedule->whereNull('payment_date')->count();
        $overdueCount = $paymentSchedule->where('due_date', '<', now()->toDateString())->whereNull('payment_date')->count();

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

        // Get payment counts for warning message
        $paidPayments = DB::table('liability_payment_schedules')
            ->where('liability_id', $id)
            ->whereNotNull('payment_date')
            ->count();

        $totalPayments = DB::table('liability_payment_schedules')
            ->where('liability_id', $id)
            ->count();

        // Get Liability accounts (class code 2 = Liabilities)
        $accounts = DB::table('accounts')
            ->join('account_groups', 'accounts.account_group_id', '=', 'account_groups.id')
            ->join('account_classes', 'account_groups.account_class_id', '=', 'account_classes.id')
            ->where('accounts.is_active', true)
            ->where('account_classes.code', '2') // Liabilities class
            ->select('accounts.id', 'accounts.code', 'accounts.name')
            ->orderBy('accounts.code')
            ->get();

        // Get Expense accounts (class code 5 = Expenses)
        $expenseAccounts = DB::table('accounts')
            ->join('account_groups', 'accounts.account_group_id', '=', 'account_groups.id')
            ->join('account_classes', 'account_groups.account_class_id', '=', 'account_classes.id')
            ->where('accounts.is_active', true)
            ->where('account_classes.code', '5') // Expenses class
            ->select('accounts.id', 'accounts.code', 'accounts.name')
            ->orderBy('accounts.code')
            ->get();

        $banks = DB::table('banks')->where('is_active', true)->orderBy('name')->get();

        return view('accounting.liabilities.edit', compact(
            'liability',
            'accounts',
            'expenseAccounts',
            'banks',
            'paidPayments',
            'totalPayments'
        ));
    }

    /**
     * Update liability.
     */
    /**
     * Update liability details.
     *
     * Best Practice for Interest Rate Changes:
     * - If interest rate changes, only FUTURE (unpaid) payments are recalculated
     * - Already paid payments remain unchanged (JEs already posted)
     * - Uses current balance as base for recalculation (not original principal)
     * - Preserves payment history and audit trail
     */
    public function update(Request $request, $id)
    {
        $liability = LiabilitySchedule::find($id);

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

        $oldInterestRate = (float) $liability->interest_rate;
        $newInterestRate = (float) $validated['interest_rate'];
        $interestRateChanged = abs($oldInterestRate - $newInterestRate) > 0.0001;

        try {
            DB::beginTransaction();

            // Update liability details
            $liability->update([
                'creditor_name' => $validated['creditor_name'],
                'creditor_contact' => $validated['creditor_contact'],
                'reference_number' => $validated['reference_number'],
                'interest_rate' => $validated['interest_rate'],
                'collateral_description' => $validated['collateral_description'],
                'collateral_value' => $validated['collateral_value'],
                'notes' => $validated['notes'],
            ]);

            // If interest rate changed, recalculate FUTURE payments only
            if ($interestRateChanged) {
                $this->recalculateFuturePayments($liability, $oldInterestRate, $newInterestRate);
            }

            DB::commit();

            $message = 'Liability updated successfully.';
            if ($interestRateChanged) {
                $message .= ' Future payment schedule has been recalculated with the new interest rate.';
            }

            return redirect()
                ->route('accounting.liabilities.show', $id)
                ->with('success', $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withInput()
                ->with('error', 'Failed to update liability: ' . $e->getMessage());
        }
    }

    /**
     * Recalculate future (unpaid) payments when interest rate changes.
     *
     * This follows the Prospective Adjustment approach:
     * - Already paid payments are NOT touched (preserves audit trail)
     * - Only unpaid payments are recalculated
     * - Uses current balance as the base for remaining amortization
     */
    protected function recalculateFuturePayments(LiabilitySchedule $liability, float $oldRate, float $newRate): void
    {
        // Get all unpaid payment schedules
        $unpaidSchedules = LiabilityPaymentSchedule::where('liability_id', $liability->id)
            ->whereNull('payment_date')
            ->orderBy('due_date')
            ->get();

        if ($unpaidSchedules->isEmpty()) {
            return; // No future payments to adjust
        }

        // Use current balance as the remaining principal
        $remainingBalance = (float) $liability->current_balance;
        $remainingPayments = $unpaidSchedules->count();
        $frequency = $liability->payment_frequency;
        $interestType = $liability->interest_type;

        // Calculate periods per year
        $periodsPerYear = match($frequency) {
            'weekly' => 52,
            'bi_weekly' => 26,
            'monthly' => 12,
            'quarterly' => 4,
            'semi_annually' => 2,
            'annually' => 1,
            'at_maturity' => 1,
            default => 12,
        };

        $periodicRate = ($newRate / 100) / $periodsPerYear;

        // Calculate new regular payment amount using amortization formula
        if ($frequency === 'at_maturity') {
            // Bullet payment
            $remainingTermMonths = Carbon::now()->diffInMonths(Carbon::parse($liability->maturity_date));
            $totalInterest = $remainingBalance * ($newRate / 100) * ($remainingTermMonths / 12);
            $newRegularPayment = $remainingBalance + $totalInterest;
        } elseif ($periodicRate > 0 && $remainingPayments > 0) {
            // Standard amortization formula
            $newRegularPayment = $remainingBalance * ($periodicRate * pow(1 + $periodicRate, $remainingPayments))
                               / (pow(1 + $periodicRate, $remainingPayments) - 1);
        } else {
            // Zero interest
            $newRegularPayment = $remainingBalance / max(1, $remainingPayments);
        }

        $newRegularPayment = round($newRegularPayment, 2);
        $balance = $remainingBalance;

        // Recalculate each unpaid payment
        foreach ($unpaidSchedules as $index => $schedule) {
            // Calculate interest portion
            if ($interestType === 'flat') {
                $interestPortion = round(($remainingBalance * ($newRate / 100) * ($liability->term_months / 12)) / $remainingPayments, 2);
            } else {
                $interestPortion = round($balance * $periodicRate, 2);
            }

            // Calculate principal portion
            $principalPortion = $newRegularPayment - $interestPortion;

            // Adjust last payment for rounding
            if ($index == $unpaidSchedules->count() - 1) {
                $principalPortion = $balance;
                $newRegularPayment = $principalPortion + $interestPortion;
            }

            $openingBalance = $balance;
            $balance = max(0, round($balance - $principalPortion, 2));

            // Update the schedule (without triggering observers)
            $schedule->updateQuietly([
                'scheduled_payment' => round($newRegularPayment, 2),
                'principal_portion' => round($principalPortion, 2),
                'interest_portion' => round($interestPortion, 2),
                'opening_balance' => round($openingBalance, 2),
                'closing_balance' => round($balance, 2),
            ]);

            // Reset for next iteration (use the same regular payment for amortization)
            if ($index < $unpaidSchedules->count() - 2) {
                $newRegularPayment = round($newRegularPayment, 2); // Keep consistent
            }
        }

        // Update liability's regular payment amount
        $liability->updateQuietly([
            'regular_payment_amount' => $unpaidSchedules->first()->scheduled_payment,
        ]);

        // Update current/non-current portions
        $liability->updatePortions();

        Log::info('LiabilityController: Future payments recalculated', [
            'liability_id' => $liability->id,
            'old_rate' => $oldRate,
            'new_rate' => $newRate,
            'remaining_payments' => $remainingPayments,
            'new_regular_payment' => $newRegularPayment,
        ]);
    }

    /**
     * Show payment recording form.
     */
    public function payment($id)
    {
        $liability = LiabilitySchedule::with(['account', 'interestExpenseAccount'])->find($id);

        if (!$liability) {
            abort(404);
        }

        // Get next unpaid scheduled payment using Eloquent
        $nextPayment = LiabilityPaymentSchedule::where('liability_id', $id)
            ->whereNull('payment_date')
            ->orderBy('due_date')
            ->first();

        $banks = DB::table('banks')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Get accounts for JE preview
        $liabilityAccount = $liability->account;
        $interestAccount = $liability->interestExpenseAccount ?? Account::where('code', '6300')->first();

        return view('accounting.liabilities.payment', compact('liability', 'nextPayment', 'banks', 'liabilityAccount', 'interestAccount'));
    }

    /**
     * Record liability payment.
     * Uses Eloquent to trigger LiabilityPaymentObserver which creates JE:
     *   DEBIT: Liability Account (principal reduction)
     *   DEBIT: Interest Expense (interest portion)
     *   CREDIT: Bank Account (cash outflow)
     */
    public function recordPayment(Request $request, $id)
    {
        Log::info('LiabilityController::recordPayment called', [
            'liability_id' => $id,
            'request_data' => $request->all(),
        ]);

        $liability = LiabilitySchedule::find($id);

        if (!$liability) {
            Log::error('LiabilityController::recordPayment - Liability not found', ['id' => $id]);
            abort(404);
        }

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'actual_payment' => 'required|numeric|min:0.01',
            'principal_portion' => 'required|numeric|min:0',
            'interest_portion' => 'required|numeric|min:0',
            'late_fee' => 'nullable|numeric|min:0',
            'bank_id' => 'required|exists:banks,id',
            'payment_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        Log::info('LiabilityController::recordPayment - Validation passed', ['validated' => $validated]);

        try {
            // Find next unpaid schedule entry using Eloquent
            $schedule = LiabilityPaymentSchedule::where('liability_id', $id)
                ->whereNull('payment_date')
                ->orderBy('due_date')
                ->first();

            if (!$schedule) {
                Log::warning('LiabilityController::recordPayment - No pending payments', ['liability_id' => $id]);
                return back()->with('error', 'No pending payments found for this liability.');
            }

            Log::info('LiabilityController::recordPayment - Found schedule', [
                'schedule_id' => $schedule->id,
                'payment_number' => $schedule->payment_number,
            ]);

            // Update bank_account_id on liability if not set (for JE credit account)
            if (!$liability->bank_account_id && $validated['bank_id']) {
                $bank = DB::table('banks')->find($validated['bank_id']);
                if ($bank && $bank->account_id) {
                    $liability->bank_account_id = $bank->account_id;
                    $liability->saveQuietly();
                    Log::info('LiabilityController::recordPayment - Updated bank_account_id', [
                        'bank_id' => $validated['bank_id'],
                        'account_id' => $bank->account_id,
                    ]);
                }
            }

            // Update the payment schedule - triggers LiabilityPaymentObserver
            // The observer will create the JE when payment_date is set
            $schedule->update([
                'payment_date' => $validated['payment_date'],
                'actual_payment' => $validated['actual_payment'],
                'principal_portion' => $validated['principal_portion'],
                'interest_portion' => $validated['interest_portion'],
                'late_fee' => $validated['late_fee'] ?? 0,
                'status' => 'paid',
                'payment_reference' => $validated['payment_reference'],
                'notes' => $validated['notes'],
            ]);

            Log::info('LiabilityController::recordPayment - Payment recorded successfully', [
                'schedule_id' => $schedule->id,
                'journal_entry_id' => $schedule->fresh()->journal_entry_id,
            ]);

            return redirect()
                ->route('accounting.liabilities.show', $id)
                ->with('success', 'Payment recorded successfully. Journal entry created automatically.');

        } catch (\Exception $e) {
            Log::error('LiabilityController::recordPayment - Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
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
            'weekly' => 52,
            'bi_weekly' => 26,
            'monthly' => 12,
            'quarterly' => 4,
            'semi_annually' => 2,
            'annually' => 1,
            'at_maturity' => 1,
            default => 12,
        };

        $totalPayments = ($termMonths / 12) * $periodsPerYear;
        if ($totalPayments < 1) $totalPayments = 1;

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
            'weekly' => 52,
            'bi_weekly' => 26,
            'monthly' => 12,
            'quarterly' => 4,
            'semi_annually' => 2,
            'annually' => 1,
            'at_maturity' => 1,
            default => 12,
        };

        $totalPayments = (int)(($data['term_months'] / 12) * $periodsPerYear);
        if ($totalPayments < 1) $totalPayments = 1;

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
            'weekly' => 0, // handled differently
            'bi_weekly' => 0, // handled differently
            'monthly' => 1,
            'quarterly' => 3,
            'semi_annually' => 6,
            'annually' => 12,
            'at_maturity' => $data['term_months'],
            default => 1,
        };

        for ($i = 1; $i <= $totalPayments; $i++) {
            // Calculate next payment date based on frequency
            if ($data['payment_frequency'] === 'weekly') {
                $paymentDate = $paymentDate->copy()->addWeek();
            } elseif ($data['payment_frequency'] === 'bi_weekly') {
                $paymentDate = $paymentDate->copy()->addWeeks(2);
            } else {
                $paymentDate = $paymentDate->copy()->addMonths($monthsToAdd);
            }

            $openingBalance = $balance;
            $interestAmount = round($balance * $periodicRate, 2);
            $principalAmount = round($payment - $interestAmount, 2);

            // Last payment adjustment
            if ($i == $totalPayments) {
                $principalAmount = $balance;
                $payment = $principalAmount + $interestAmount;
            }

            $balance -= $principalAmount;
            if ($balance < 0) $balance = 0;

            // Use correct column names from migration
            DB::table('liability_payment_schedules')->insert([
                'liability_id' => $liabilityId,
                'payment_number' => $i,
                'due_date' => $paymentDate->toDateString(),
                'scheduled_payment' => $payment,
                'principal_portion' => $principalAmount,
                'interest_portion' => $interestAmount,
                'opening_balance' => $openingBalance,
                'closing_balance' => $balance,
                'status' => 'scheduled',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
