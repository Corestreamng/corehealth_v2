<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\StatutoryRemittance;
use App\Models\Bank;
use App\Models\HR\PayHead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Statutory Remittance Controller
 *
 * Handles CRUD operations for statutory remittances (PAYE, Pension, NHF, etc.)
 * These are payments made to regulatory bodies for payroll deductions.
 */
class StatutoryRemittanceController extends Controller
{
    /**
     * Display listing of statutory remittances.
     */
    public function index(Request $request)
    {
        $query = StatutoryRemittance::with(['payHead', 'bank', 'preparedBy', 'approvedBy'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by pay head
        if ($request->filled('pay_head_id')) {
            $query->where('pay_head_id', $request->pay_head_id);
        }

        // Filter by period
        if ($request->filled('period_from')) {
            $query->where('period_from', '>=', $request->period_from);
        }
        if ($request->filled('period_to')) {
            $query->where('period_to', '<=', $request->period_to);
        }

        $remittances = $query->paginate(20);

        // Get pay heads that are deductions with liability accounts
        $payHeads = PayHead::where('type', 'deduction')
            ->whereNotNull('liability_account_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Get banks for payment
        $banks = Bank::where('active', true)->orderBy('bank_name')->get();

        // Summary stats
        $stats = [
            'total_pending' => StatutoryRemittance::whereIn('status', ['draft', 'pending', 'approved'])->sum('amount'),
            'total_paid_this_month' => StatutoryRemittance::where('status', 'paid')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('amount'),
            'overdue_count' => StatutoryRemittance::overdue()->count(),
        ];

        return view('admin.accounting.statutory-remittances.index', compact(
            'remittances',
            'payHeads',
            'banks',
            'stats'
        ));
    }

    /**
     * Show create form.
     */
    public function create()
    {
        $payHeads = PayHead::where('type', 'deduction')
            ->whereNotNull('liability_account_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $banks = Bank::where('active', true)->orderBy('bank_name')->get();

        return view('admin.accounting.statutory-remittances.create', compact('payHeads', 'banks'));
    }

    /**
     * Store a new statutory remittance.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pay_head_id' => 'required|exists:pay_heads,id',
            'period_from' => 'required|date',
            'period_to' => 'required|date|after_or_equal:period_from',
            'due_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0.01',
            'payee_name' => 'required|string|max:255',
            'payee_account_number' => 'nullable|string|max:50',
            'payee_bank_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        try {
            $remittance = StatutoryRemittance::create([
                'pay_head_id' => $request->pay_head_id,
                'reference_number' => StatutoryRemittance::generateReferenceNumber(),
                'period_from' => $request->period_from,
                'period_to' => $request->period_to,
                'due_date' => $request->due_date,
                'amount' => $request->amount,
                'payee_name' => $request->payee_name,
                'payee_account_number' => $request->payee_account_number,
                'payee_bank_name' => $request->payee_bank_name,
                'notes' => $request->notes,
                'status' => StatutoryRemittance::STATUS_DRAFT,
                'prepared_by' => Auth::id(),
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Statutory remittance created successfully',
                    'data' => $remittance
                ]);
            }

            return redirect()
                ->route('accounting.statutory-remittances.show', $remittance)
                ->with('success', 'Statutory remittance created successfully.');

        } catch (\Exception $e) {
            Log::error('Error creating statutory remittance: ' . $e->getMessage());

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create remittance'
                ], 500);
            }

            return back()->with('error', 'Failed to create remittance. Please try again.')->withInput();
        }
    }

    /**
     * Display a statutory remittance.
     */
    public function show(StatutoryRemittance $statutoryRemittance)
    {
        $statutoryRemittance->load([
            'payHead.liabilityAccount',
            'bank',
            'journalEntry.lines.account',
            'preparedBy',
            'approvedBy',
            'paidBy',
            'voidedBy'
        ]);

        // Banks for the payment modal
        $banks = Bank::where('active', true)->orderBy('bank_name')->get();

        $remittance = $statutoryRemittance;

        return view('admin.accounting.statutory-remittances.show', compact('remittance', 'banks'));
    }

    /**
     * Show edit form.
     */
    public function edit(StatutoryRemittance $statutoryRemittance)
    {
        if (!$statutoryRemittance->canEdit()) {
            return back()->with('error', 'This remittance cannot be edited.');
        }

        $payHeads = PayHead::where('type', 'deduction')
            ->whereNotNull('liability_account_id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $banks = Bank::where('active', true)->orderBy('bank_name')->get();

        $remittance = $statutoryRemittance;

        return view('admin.accounting.statutory-remittances.edit', compact(
            'remittance',
            'payHeads',
            'banks'
        ));
    }

    /**
     * Update a statutory remittance.
     */
    public function update(Request $request, StatutoryRemittance $statutoryRemittance)
    {
        if (!$statutoryRemittance->canEdit()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This remittance cannot be edited'
                ], 422);
            }
            return back()->with('error', 'This remittance cannot be edited.');
        }

        $validator = Validator::make($request->all(), [
            'pay_head_id' => 'required|exists:pay_heads,id',
            'period_from' => 'required|date',
            'period_to' => 'required|date|after_or_equal:period_from',
            'due_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0.01',
            'payee_name' => 'required|string|max:255',
            'payee_account_number' => 'nullable|string|max:50',
            'payee_bank_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        try {
            $statutoryRemittance->update([
                'pay_head_id' => $request->pay_head_id,
                'period_from' => $request->period_from,
                'period_to' => $request->period_to,
                'due_date' => $request->due_date,
                'amount' => $request->amount,
                'payee_name' => $request->payee_name,
                'payee_account_number' => $request->payee_account_number,
                'payee_bank_name' => $request->payee_bank_name,
                'notes' => $request->notes,
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Statutory remittance updated successfully'
                ]);
            }

            return redirect()
                ->route('accounting.statutory-remittances.show', $statutoryRemittance)
                ->with('success', 'Statutory remittance updated successfully.');

        } catch (\Exception $e) {
            Log::error('Error updating statutory remittance: ' . $e->getMessage());

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update remittance'
                ], 500);
            }

            return back()->with('error', 'Failed to update remittance. Please try again.')->withInput();
        }
    }

    /**
     * Submit remittance for approval.
     */
    public function submit(StatutoryRemittance $statutoryRemittance)
    {
        if ($statutoryRemittance->status !== StatutoryRemittance::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft remittances can be submitted'
            ], 422);
        }

        $statutoryRemittance->update(['status' => StatutoryRemittance::STATUS_PENDING]);

        return response()->json([
            'success' => true,
            'message' => 'Remittance submitted for approval'
        ]);
    }

    /**
     * Approve a statutory remittance.
     */
    public function approve(StatutoryRemittance $statutoryRemittance)
    {
        if (!$statutoryRemittance->canApprove()) {
            return response()->json([
                'success' => false,
                'message' => 'This remittance cannot be approved'
            ], 422);
        }

        $statutoryRemittance->update([
            'status' => StatutoryRemittance::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Remittance approved successfully'
        ]);
    }

    /**
     * Mark remittance as paid.
     * This triggers the observer to create a journal entry.
     */
    public function pay(Request $request, StatutoryRemittance $statutoryRemittance)
    {
        if (!$statutoryRemittance->canPay()) {
            return response()->json([
                'success' => false,
                'message' => 'This remittance cannot be marked as paid'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:bank_transfer,cheque,cash',
            'bank_id' => 'required_if:payment_method,bank_transfer,cheque|exists:banks,id',
            'cheque_number' => 'required_if:payment_method,cheque|nullable|string|max:50',
            'transaction_reference' => 'nullable|string|max:100',
            'remittance_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $statutoryRemittance->update([
                'status' => StatutoryRemittance::STATUS_PAID,
                'payment_method' => $request->payment_method,
                'bank_id' => $request->bank_id,
                'cheque_number' => $request->cheque_number,
                'transaction_reference' => $request->transaction_reference,
                'remittance_date' => $request->remittance_date,
                'paid_by' => Auth::id(),
                'paid_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Remittance marked as paid. Journal entry created.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error marking remittance as paid: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment'
            ], 500);
        }
    }

    /**
     * Void a statutory remittance.
     */
    public function void(Request $request, StatutoryRemittance $statutoryRemittance)
    {
        if (!$statutoryRemittance->canVoid()) {
            return response()->json([
                'success' => false,
                'message' => 'This remittance cannot be voided'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'void_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide a reason for voiding',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $statutoryRemittance->update([
                'status' => StatutoryRemittance::STATUS_VOIDED,
                'voided_by' => Auth::id(),
                'voided_at' => now(),
                'void_reason' => $request->void_reason,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Remittance voided successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error voiding remittance: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to void remittance'
            ], 500);
        }
    }

    /**
     * Get outstanding liability balances for each pay head.
     * This helps identify how much needs to be remitted.
     */
    public function getOutstandingBalances()
    {
        $payHeads = PayHead::with('liabilityAccount')
            ->where('type', 'deduction')
            ->whereNotNull('liability_account_id')
            ->where('is_active', true)
            ->get()
            ->map(function ($payHead) {
                $liabilityAccount = $payHead->liabilityAccount;
                $balance = $liabilityAccount ? $liabilityAccount->getCurrentBalance() : 0;

                return [
                    'pay_head_id' => $payHead->id,
                    'pay_head_name' => $payHead->name,
                    'pay_head_code' => $payHead->code,
                    'liability_account_id' => $liabilityAccount?->id,
                    'liability_account_code' => $liabilityAccount?->code,
                    'liability_account_name' => $liabilityAccount?->name,
                    'outstanding_balance' => abs($balance), // Liabilities have credit balance
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $payHeads
        ]);
    }

    /**
     * Delete a draft remittance.
     */
    public function destroy(StatutoryRemittance $statutoryRemittance)
    {
        if ($statutoryRemittance->status !== StatutoryRemittance::STATUS_DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Only draft remittances can be deleted'
            ], 422);
        }

        $statutoryRemittance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Remittance deleted successfully'
        ]);
    }
}
