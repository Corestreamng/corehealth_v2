<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\patient;
use App\Models\ProductOrServiceRequest;
use App\Models\payment;
use App\Models\PatientAccount;
use App\Models\HmoClaim;
use App\Models\Hmo;
use App\Models\DoctorQueue;
use App\Models\AdmissionRequest;
use App\Models\Accounting\PatientDeposit;
use App\Models\Accounting\PatientDepositApplication;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;

class BillingWorkbenchController extends Controller
{
    /**
     * Display the billing workbench main page
     */
    public function index()
    {
        return view('admin.billing.workbench');
    }

    /**
     * Search for patients (autocomplete)
     */
    public function searchPatients(Request $request)
    {
        $term = $request->get('term', '');

        if (strlen($term) < 2) {
            return response()->json([]);
        }

        $patients = patient::with('user', 'hmo')
            ->where(function ($query) use ($term) {
                $query->whereHas('user', function ($userQuery) use ($term) {
                    $userQuery->where('surname', 'like', "%{$term}%")
                        ->orWhere('firstname', 'like', "%{$term}%")
                        ->orWhere('othername', 'like', "%{$term}%");
                })
                ->orWhere('file_no', 'like', "%{$term}%")
                ->orWhere('phone_no', 'like', "%{$term}%");
            })
            ->limit(10)
            ->get();

        $results = $patients->map(function ($patient) {
            $pendingCount = ProductOrServiceRequest::where('user_id', $patient->user_id)
                ->whereNull('payment_id')
                ->whereNull('invoice_id')
                ->count();

            return [
                'id' => $patient->id,
                'user_id' => $patient->user_id,
                'name' => userfullname($patient->user_id),
                'file_no' => $patient->file_no,
                'age' => $patient->dob ? Carbon::parse($patient->dob)->age : 'N/A',
                'gender' => $patient->gender ?? 'N/A',
                'phone' => $patient->phone_no ?? 'N/A',
                'photo' => $patient->user->photo ?? 'avatar.png',
                'hmo' => optional($patient->hmo)->name ?? 'Self',
                'hmo_no' => $patient->hmo_no ?? '',
                'pending_count' => $pendingCount,
            ];
        });

        return response()->json($results);
    }

    /**
     * Get payment queue with optional filters
     */
    public function getPaymentQueue(Request $request)
    {
        $filter = $request->get('filter', 'all');

        $query = ProductOrServiceRequest::query()
            ->whereNull('payment_id')
            ->whereNull('invoice_id');

        // Apply filters
        if ($filter === 'hmo') {
            $query->where('claims_amount', '>', 0);
        } elseif ($filter === 'credit') {
            // Filter for patients with credit accounts
            $creditPatientUserIds = PatientAccount::where('balance', '>', 0)
                ->pluck('patient_id');
            $creditUserIds = patient::whereIn('id', $creditPatientUserIds)->pluck('user_id');
            $query->whereIn('user_id', $creditUserIds);
        }

        $results = $query
            ->select([
                'user_id',
                DB::raw('COUNT(*) as unpaid_count'),
                DB::raw('SUM(CASE WHEN claims_amount > 0 THEN 1 ELSE 0 END) as hmo_items'),
                DB::raw('MAX(created_at) as last_created')
            ])
            ->groupBy('user_id')
            ->orderByDesc('last_created')
            ->get();

        // Preload patient data
        $patients = patient::with('user', 'hmo')
            ->whereIn('user_id', $results->pluck('user_id'))
            ->get()
            ->keyBy('user_id');

        // Detect emergency patients (have active emergency DoctorQueue or AdmissionRequest)
        $patientIds = $patients->pluck('id')->toArray();
        $emergencyPatientIds = collect();
        if (!empty($patientIds)) {
            $emergencyFromQueue = DoctorQueue::where('priority', 'emergency')
                ->whereIn('patient_id', $patientIds)
                ->whereIn('status', [1, 2, 3])
                ->pluck('patient_id');
            $emergencyFromAdmission = AdmissionRequest::where('priority', 'emergency')
                ->whereIn('patient_id', $patientIds)
                ->where('discharged', 0)
                ->pluck('patient_id');
            $emergencyPatientIds = $emergencyFromQueue->merge($emergencyFromAdmission)->unique();
        }

        $queue = $results->map(function ($item) use ($patients, $emergencyPatientIds) {
            $patient = $patients->get($item->user_id);

            // Skip if patient record not found
            if (!$patient) {
                return null;
            }

            return [
                'patient_id' => $patient->id,
                'patient_name' => userfullname($item->user_id),
                'file_no' => $patient->file_no,
                'unpaid_count' => $item->unpaid_count,
                'hmo_items' => $item->hmo_items,
                'hmo' => optional($patient->hmo)->name,
                'is_emergency' => $emergencyPatientIds->contains($patient->id),
            ];
        })->filter(); // Remove null entries

        // Sort emergency patients first
        $queue = $queue->sortByDesc('is_emergency')->values();

        return response()->json($queue->values());
    }

    /**
     * Get queue counts for filters
     */
    public function getQueueCounts()
    {
        $unpaidCount = ProductOrServiceRequest::whereNull('payment_id')
            ->whereNull('invoice_id')
            ->select('user_id')
            ->distinct()
            ->count();

        $hmoCount = ProductOrServiceRequest::whereNull('payment_id')
            ->whereNull('invoice_id')
            ->where('claims_amount', '>', 0)
            ->select('user_id')
            ->distinct()
            ->count();

        $creditPatientUserIds = PatientAccount::where('balance', '>', 0)
            ->pluck('patient_id');
        $creditUserIds = patient::whereIn('id', $creditPatientUserIds)->pluck('user_id');
        $creditCount = ProductOrServiceRequest::whereNull('payment_id')
            ->whereNull('invoice_id')
            ->whereIn('user_id', $creditUserIds)
            ->select('user_id')
            ->distinct()
            ->count();

        return response()->json([
            'unpaid' => $unpaidCount,
            'hmo' => $hmoCount,
            'credit' => $creditCount,
            'total' => $unpaidCount,
            'emergency' => $this->getEmergencyBillingCount(),
        ]);
    }

    /**
     * Count patients with unpaid bills who are emergency patients
     */
    private function getEmergencyBillingCount()
    {
        $unpaidPatientUserIds = ProductOrServiceRequest::whereNull('payment_id')
            ->whereNull('invoice_id')
            ->distinct()
            ->pluck('user_id');

        $unpaidPatientIds = patient::whereIn('user_id', $unpaidPatientUserIds)->pluck('id');

        $fromQueue = DoctorQueue::where('priority', 'emergency')
            ->whereIn('patient_id', $unpaidPatientIds)
            ->whereIn('status', [1, 2, 3])
            ->distinct()
            ->pluck('patient_id');

        $fromAdmission = AdmissionRequest::where('priority', 'emergency')
            ->whereIn('patient_id', $unpaidPatientIds)
            ->where('discharged', 0)
            ->distinct()
            ->pluck('patient_id');

        return $fromQueue->merge($fromAdmission)->unique()->count();
    }

    /**
     * Get patient's billing data (unpaid items)
     */
    public function getPatientBillingData($patientId)
    {
        $patient = patient::with('hmo')->findOrFail($patientId);

        $items = ProductOrServiceRequest::with([
                'service.price',
                'service.category',
                'product.price',
                'product.category',
            ])
            ->where('user_id', $patient->user_id)
            ->whereNull('payment_id')
            ->whereNull('invoice_id')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($row) {
                $isService = $row->service_id !== null;
                $basePrice = $isService
                    ? optional(optional($row->service)->price)->sale_price
                    : optional(optional($row->product)->price)->current_sale_price;

                $price = $row->payable_amount !== null ? $row->payable_amount : ($basePrice ?? 0);

                return [
                    'id' => $row->id,
                    'type' => $isService ? 'service' : 'product',
                    'name' => $isService ? optional($row->service)->service_name : optional($row->product)->product_name,
                    'code' => $isService ? optional($row->service)->service_code : optional($row->product)->product_code,
                    'category' => $isService ? optional(optional($row->service)->category)->category_name : optional(optional($row->product)->category)->category_name,
                    'qty' => $row->qty ?? 1,
                    'price' => $price,
                    'base_price' => $basePrice ?? 0,
                    'payable_amount' => $row->payable_amount,
                    'claims_amount' => $row->claims_amount,
                    'coverage_mode' => $row->coverage_mode,
                    'validation_status' => $row->validation_status,
                    'discount' => $row->discount ?? 0,
                    'created_at' => $row->created_at ? $row->created_at->toIso8601String() : null,
                    'created_at_formatted' => $row->created_at ? $row->created_at->format('d/m/Y H:i') : 'N/A',
                ];
            });

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'user_id' => $patient->user_id,
                'name' => userfullname($patient->user_id),
                'file_no' => $patient->file_no,
                'age' => $patient->dob ? Carbon::parse($patient->dob)->age : 'N/A',
                'gender' => $patient->gender ?? 'N/A',
                'hmo_name' => optional($patient->hmo)->name,
                'hmo_no' => $patient->hmo_no,
                'photo' => $patient->user->photo ?? 'avatar.png',
            ],
            'items' => $items,
        ]);
    }

    /**
     * Get patient's receipts (paid items grouped by payment)
     * Includes both payments and deposits from accounting module
     */
    public function getPatientReceipts($patientId, Request $request)
    {
        $patient = patient::findOrFail($patientId);
        $receipts = collect();

        // Get filter values
        $fromDate = $request->from_date;
        $toDate = $request->to_date;
        $paymentTypeFilter = $request->payment_type;

        // 1. Get payments from payment table
        $paymentQuery = payment::where('patient_id', $patientId);

        if ($fromDate) {
            $paymentQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $paymentQuery->whereDate('created_at', '<=', $toDate);
        }
        if ($paymentTypeFilter && !in_array($paymentTypeFilter, ['DEPOSIT', 'ACC_DEPOSIT'])) {
            $paymentQuery->where('payment_type', $paymentTypeFilter);
        }

        // Skip deposits if filtering for deposits only
        if (!$paymentTypeFilter || !in_array($paymentTypeFilter, ['DEPOSIT', 'ACC_DEPOSIT'])) {
            $payments = $paymentQuery->orderBy('created_at', 'desc')->get();

            foreach ($payments as $payment) {
                $items = ProductOrServiceRequest::with(['service', 'product'])
                    ->where('payment_id', $payment->id)
                    ->get();

                $receipts->push([
                    'id' => 'pay_' . $payment->id,
                    'source' => 'payment',
                    'payment_id' => $payment->id,
                    'reference_no' => $payment->reference_no,
                    'payment_type' => $payment->payment_type,
                    'payment_type_label' => $this->getPaymentTypeLabel($payment->payment_type),
                    'total' => $payment->total,
                    'total_discount' => $payment->total_discount ?? 0,
                    'created_at' => $payment->created_at->format('Y-m-d H:i'),
                    'datetime' => $payment->created_at,
                    'created_by' => userfullname($payment->user_id),
                    'item_count' => $items->count(),
                ]);
            }
        }

        // 2. Get deposits from patient_deposits table (Accounting module)
        if (!$paymentTypeFilter || in_array($paymentTypeFilter, ['DEPOSIT', 'ACC_DEPOSIT', ''])) {
            $depositQuery = PatientDeposit::where('patient_id', $patientId);

            if ($fromDate) {
                $depositQuery->whereDate('deposit_date', '>=', $fromDate);
            }
            if ($toDate) {
                $depositQuery->whereDate('deposit_date', '<=', $toDate);
            }

            $deposits = $depositQuery->orderBy('deposit_date', 'desc')->get();

            foreach ($deposits as $deposit) {
                $receipts->push([
                    'id' => 'dep_' . $deposit->id,
                    'source' => 'deposit',
                    'payment_id' => null,
                    'deposit_id' => $deposit->id,
                    'reference_no' => $deposit->deposit_number,
                    'payment_type' => 'ACC_DEPOSIT',
                    'payment_type_label' => $this->getDepositTypeLabel($deposit->deposit_type),
                    'total' => $deposit->amount,
                    'total_discount' => 0,
                    'created_at' => $deposit->deposit_date->format('Y-m-d H:i'),
                    'datetime' => $deposit->deposit_date,
                    'created_by' => userfullname($deposit->received_by),
                    'item_count' => 0,
                    'deposit_type' => $deposit->deposit_type,
                    'payment_method' => $deposit->payment_method,
                ]);
            }
        }

        // Sort by datetime descending
        $receipts = $receipts->sortByDesc('datetime')->values();

        // Calculate stats
        $stats = [
            'count' => $receipts->count(),
            'total' => $receipts->sum('total'),
            'discounts' => $receipts->sum('total_discount'),
        ];

        return response()->json([
            'patient' => [
                'id' => $patient->id,
                'name' => userfullname($patient->user_id),
                'file_no' => $patient->file_no,
            ],
            'receipts' => $receipts,
            'stats' => $stats,
        ]);
    }

    /**
     * Get friendly label for payment type
     */
    private function getPaymentTypeLabel($paymentType)
    {
        $labels = [
            'CASH' => 'Cash Payment',
            'POS' => 'POS/Card Payment',
            'TRANSFER' => 'Bank Transfer',
            'MOBILE' => 'Mobile Payment',
            'ACCOUNT' => 'Account Payment',
            'CHEQUE' => 'Cheque Payment',
            'TELLER' => 'Teller Payment',
            'ACC_DEPOSIT' => 'Account Deposit',
            'ACC_WITHDRAW' => 'Account Withdrawal',
            'ACC_ADJUSTMENT' => 'Account Adjustment',
        ];
        return $labels[$paymentType] ?? ucfirst(strtolower($paymentType));
    }

    /**
     * Get patient's transaction history
     */
    public function getPatientTransactions($patientId, Request $request)
    {
        $patient = patient::findOrFail($patientId);

        $query = payment::where('patient_id', $patientId);

        // Apply filters
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay()
            ]);
        }

        if ($request->has('payment_type') && $request->payment_type) {
            $query->where('payment_type', $request->payment_type);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        $transactionsFormatted = $transactions->map(function ($tx) {
            return [
                'id' => $tx->id,
                'reference_no' => $tx->reference_no,
                'payment_type' => $tx->payment_type,
                'total' => $tx->total,
                'total_discount' => $tx->total_discount,
                'created_at' => $tx->created_at->format('Y-m-d H:i'),
                'cashier' => userfullname($tx->user_id),
            ];
        });

        return response()->json([
            'transactions' => $transactionsFormatted,
            'total_amount' => $transactions->sum('total'),
            'total_discount' => $transactions->sum('total_discount'),
            'count' => $transactions->count(),
        ]);
    }

    /**
     * Get patient's account-specific transactions (deposits, withdrawals, adjustments)
     * Now fetches from BOTH payment table AND patient_deposits table for unified view
     */
    public function getAccountTransactions($patientId, Request $request)
    {
        $patient = patient::findOrFail($patientId);
        $account = PatientAccount::where('patient_id', $patientId)->first();

        // Apply date filters - default to current month if not provided
        $fromDate = $request->from_date ?: now()->startOfMonth()->format('Y-m-d');
        $toDate = $request->to_date ?: now()->format('Y-m-d');
        $txTypeFilter = $request->tx_type ?: null;

        $transactionsFormatted = collect();

        // 1. Get deposits from patient_deposits table (Accounts module deposits)
        if (!$txTypeFilter || $txTypeFilter === 'ACC_DEPOSIT') {
            $deposits = PatientDeposit::where('patient_id', $patientId)
                ->whereDate('deposit_date', '>=', $fromDate)
                ->whereDate('deposit_date', '<=', $toDate)
                ->orderBy('deposit_date', 'desc')
                ->get();

            foreach ($deposits as $dep) {
                $transactionsFormatted->push([
                    'id' => 'dep_' . $dep->id,
                    'source' => 'patient_deposit',
                    'reference_no' => $dep->deposit_number,
                    'payment_type' => 'ACC_DEPOSIT',
                    'tx_type' => 'Deposit',
                    'tx_icon' => 'mdi-arrow-down-bold-circle',
                    'tx_color' => 'success',
                    'amount' => $dep->amount,
                    'description' => $this->getDepositTypeLabel($dep->deposit_type) . ($dep->notes ? ' - ' . $dep->notes : ''),
                    'running_balance' => 0, // Will calculate later
                    'created_at' => $dep->deposit_date->format('M d, Y'),
                    'created_time' => $dep->created_at->format('h:i A'),
                    'cashier' => userfullname($dep->received_by),
                    'datetime' => $dep->deposit_date,
                ]);
            }
        }

        // 2. Get withdrawals and adjustments from payment table
        // Skip ACC_DEPOSIT from payment table if the deposit has source_payment_id (to avoid duplicates)
        $paymentQuery = payment::where('patient_id', $patientId)
            ->whereIn('payment_type', ['ACC_WITHDRAW', 'ACC_ADJUSTMENT'])
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate);

        if ($txTypeFilter && $txTypeFilter !== 'ACC_DEPOSIT') {
            $paymentQuery->where('payment_type', $txTypeFilter);
        }

        $payments = $paymentQuery->orderBy('created_at', 'desc')->get();

        foreach ($payments as $tx) {
            $txType = 'Adjustment';
            $txIcon = 'mdi-swap-horizontal';
            $txColor = 'info';

            if ($tx->payment_type === 'ACC_WITHDRAW') {
                $txType = 'Withdrawal';
                $txIcon = 'mdi-arrow-up-bold-circle';
                $txColor = 'danger';
            }

            $transactionsFormatted->push([
                'id' => 'pay_' . $tx->id,
                'source' => 'payment',
                'reference_no' => $tx->reference_no,
                'payment_type' => $tx->payment_type,
                'tx_type' => $txType,
                'tx_icon' => $txIcon,
                'tx_color' => $txColor,
                'amount' => $tx->total,
                'description' => $tx->notes ?? '',
                'running_balance' => 0,
                'created_at' => $tx->created_at->format('M d, Y'),
                'created_time' => $tx->created_at->format('h:i A'),
                'cashier' => userfullname($tx->user_id),
                'datetime' => $tx->created_at,
            ]);
        }

        // Sort all transactions by datetime descending
        $transactionsFormatted = $transactionsFormatted->sortByDesc('datetime')->values();

        // Calculate running balance (from oldest to newest, then we keep desc order for display)
        $sortedAsc = $transactionsFormatted->sortBy('datetime')->values();
        $runningBalance = 0;
        $balanceMap = [];

        foreach ($sortedAsc as $tx) {
            $runningBalance += $tx['amount'];
            $balanceMap[$tx['id']] = $runningBalance;
        }

        // Apply running balance to formatted transactions
        $transactionsFormatted = $transactionsFormatted->map(function ($tx) use ($balanceMap) {
            $tx['running_balance'] = $balanceMap[$tx['id']] ?? 0;
            unset($tx['datetime']); // Remove datetime from response
            return $tx;
        })->values()->toArray();

        // Calculate summary stats from patient_deposits and payments
        $totalDeposits = PatientDeposit::where('patient_id', $patientId)->sum('amount');

        // Withdrawals from payments (stored as negative values)
        $totalWithdrawals = abs(payment::where('patient_id', $patientId)
            ->where('payment_type', 'ACC_WITHDRAW')
            ->sum('total'));

        return response()->json([
            'transactions' => $transactionsFormatted,
            'summary' => [
                'total_deposits' => $totalDeposits,
                'total_withdrawals' => $totalWithdrawals,
                'current_balance' => $account ? $account->balance : 0,
                'transaction_count' => count($transactionsFormatted),
            ]
        ]);
    }

    /**
     * Get patient's account summary
     */
    public function getPatientAccountSummary($patientId)
    {
        $patient = patient::with('hmo')->findOrFail($patientId);

        $account = PatientAccount::where('patient_id', $patientId)->first();
        $balance = $account ? $account->balance : 0;

        $totalPaid = payment::where('patient_id', $patientId)->sum('total');

        $totalClaims = HmoClaim::where('patient_id', $patientId)->sum('claims_amount');
        $pendingClaims = HmoClaim::where('patient_id', $patientId)
            ->where('status', 'pending')
            ->sum('claims_amount');

        $unpaidTotal = ProductOrServiceRequest::where('user_id', $patient->user_id)
            ->whereNull('payment_id')
            ->whereNull('invoice_id')
            ->sum(DB::raw('COALESCE(payable_amount, 0)'));

        return response()->json([
            'balance' => $balance,
            'total_paid' => $totalPaid,
            'total_claims' => $totalClaims,
            'pending_claims' => $pendingClaims,
            'unpaid_total' => $unpaidTotal,
            'hmo' => optional($patient->hmo)->name,
            'account' => $account ? [
                'id' => $account->id,
                'balance' => $account->balance,
                'updated_at' => $account->updated_at,
            ] : null,
        ]);
    }

    /**
     * Process payment (reuse existing ajaxPay logic)
     */
    public function processPayment(Request $request)
    {
        $data = $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'payment_type' => 'required|string',
            'payment_method' => 'nullable|string',
            'bank_id' => 'nullable|integer|exists:banks,id',
            'reference_no' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:1',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::beginTransaction();

        try {
            $patient = patient::with('hmo')->findOrFail($data['patient_id']);

            $ids = collect($data['items'])->pluck('id')->all();

            $rows = ProductOrServiceRequest::with(['service.price', 'product.price', 'user'])
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get();

            if ($rows->count() !== count($ids)) {
                throw new \Exception('Some selected items could not be found.');
            }

            $total = 0;
            $totalDiscount = 0;
            $receiptDetails = [];
            $claimsTotal = 0;

            // Validate ownership and build totals
            foreach ($data['items'] as $itemPayload) {
                $row = $rows->firstWhere('id', $itemPayload['id']);
                if (!$row || $row->user_id != $patient->user_id) {
                    throw new \Exception('Item does not belong to patient or is missing.');
                }
                if ($row->payment_id !== null || $row->invoice_id !== null) {
                    throw new \Exception('One of the items is already paid or invoiced.');
                }

                $isService = $row->service_id !== null;
                $basePrice = $isService
                    ? optional(optional($row->service)->price)->sale_price
                    : optional(optional($row->product)->price)->current_sale_price;
                $price = $row->payable_amount !== null ? $row->payable_amount : ($basePrice ?? 0);

                $qty = $itemPayload['qty'];
                $discountPercent = isset($itemPayload['discount']) ? $itemPayload['discount'] : 0;
                $discountAmount = ($price * $qty) * ($discountPercent / 100);
                $lineTotal = ($price * $qty) - $discountAmount;

                $total += $lineTotal;
                $totalDiscount += $discountAmount;

                // Persist qty/discount to request row
                $row->qty = $qty;
                $row->discount = $discountPercent;
                $row->save();

                if ($row->claims_amount > 0) {
                    $claimsTotal += $row->claims_amount * $qty;
                }

                $receiptDetails[] = [
                    'type' => $isService ? 'Service' : 'Product',
                    'name' => $isService ? optional($row->service)->service_name : optional($row->product)->product_name,
                    'price' => $price,
                    'qty' => $qty,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'amount_paid' => $lineTotal,
                ];
            }

            // If paying from account, deduct balance (credit facility: can go negative)
            // Match original payment summary behavior: use ACC_WITHDRAW and store negative total
            $paymentType = $data['payment_type'];
            $paymentTotal = $total;

            // Track if we're paying from account for deposit application creation later
            $payingFromAccount = ($data['payment_type'] === 'ACCOUNT');
            $depositsToApply = collect(); // Will hold deposits with amounts to apply

            if ($payingFromAccount) {
                $account = PatientAccount::where('patient_id', $patient->id)->first();

                // Create account if it doesn't exist
                if (!$account) {
                    $account = PatientAccount::create([
                        'patient_id' => $patient->id,
                        'balance' => 0,
                    ]);
                }

                // Deduct from account (can go negative - credit facility / overdraw)
                $account->balance -= $total;
                $account->save();

                // FIFO: Get all active deposits ordered by date (oldest first)
                // We'll apply the payment against deposits until the amount is covered
                $activeDeposits = PatientDeposit::where('patient_id', $patient->id)
                    ->where('status', 'active')
                    ->orderBy('deposit_date', 'asc') // FIFO - oldest first
                    ->orderBy('id', 'asc')
                    ->get();

                $remainingToApply = $total;

                foreach ($activeDeposits as $deposit) {
                    if ($remainingToApply <= 0) break;

                    // Calculate available balance for this deposit
                    $availableBalance = $deposit->balance; // Uses the accessor

                    if ($availableBalance <= 0) continue;

                    // Determine how much to apply from this deposit
                    $applyAmount = min($availableBalance, $remainingToApply);

                    $depositsToApply->push([
                        'deposit' => $deposit,
                        'amount' => $applyAmount,
                    ]);

                    $remainingToApply -= $applyAmount;
                }

                // Note: If $remainingToApply > 0 after loop, it means we're using credit/overdraw
                // This is allowed - the patient account has gone negative (credit facility)

                // Use ACC_WITHDRAW payment type and negative total (matches original system)
                $paymentType = 'ACC_WITHDRAW';
                $paymentTotal = 0 - $total;
            }

            // Create payment entry
            $payment = payment::create([
                'payment_type' => $paymentType,
                'payment_method' => $data['payment_method'] ?? $data['payment_type'],
                'bank_id' => $data['bank_id'] ?? null,
                'total' => $paymentTotal,
                'total_discount' => $totalDiscount,
                'reference_no' => $data['reference_no'],
                'user_id' => Auth::id(),
                'patient_id' => $patient->id,
            ]);

            // Mark items as paid
            ProductOrServiceRequest::whereIn('id', $ids)
                ->where('user_id', $patient->user_id)
                ->whereNull('invoice_id')
                ->update(['payment_id' => $payment->id]);

            // Create deposit application entries if paying from account (FIFO)
            // Only create applications for the portion covered by actual deposits
            // Overdraw amounts don't get application entries (they're credit facility usage)
            if ($payingFromAccount && $depositsToApply->isNotEmpty()) {
                foreach ($depositsToApply as $depositData) {
                    $deposit = $depositData['deposit'];
                    $applyAmount = $depositData['amount'];

                    // Create the application record
                    PatientDepositApplication::create([
                        'deposit_id' => $deposit->id,
                        'payment_id' => $payment->id,
                        'application_number' => PatientDepositApplication::generateNumber(),
                        'application_type' => PatientDepositApplication::TYPE_BILL_PAYMENT,
                        'amount' => $applyAmount,
                        'application_date' => now(),
                        'applied_by' => Auth::id(),
                        'status' => PatientDepositApplication::STATUS_APPLIED,
                        'notes' => 'Payment from deposit balance via Billing Workbench',
                    ]);

                    // Update the deposit's utilized amount
                    $deposit->utilized_amount += $applyAmount;

                    // Check if deposit is fully utilized
                    if ($deposit->balance <= 0) {
                        $deposit->status = PatientDeposit::STATUS_FULLY_APPLIED;
                    }

                    $deposit->save();
                }
            }

            // Create HMO claim if applicable
            if ($patient->hmo_id && $claimsTotal > 0) {
                HmoClaim::create([
                    'hmo_id' => $patient->hmo_id,
                    'patient_id' => $patient->id,
                    'payment_id' => $payment->id,
                    'claims_amount' => $claimsTotal,
                    'status' => 'pending',
                    'created_by' => Auth::id(),
                ]);
            }

            $site = appsettings();
            $patientName = userfullname($patient->user_id);
            $patientFileNo = $patient->file_no ?? 'N/A';
            $currentUserName = Auth::user() ? userfullname(Auth::id()) : '';
            $date = now()->format('Y-m-d H:i');
            $ref = $data['reference_no'];

            $amountParts = explode('.', number_format((float) $total, 2, '.', ''));
            $nairaWords = convert_number_to_words((int) $amountParts[0]);
            $koboWords = ((int) $amountParts[1]) > 0 ? ' and ' . convert_number_to_words((int) $amountParts[1]) . ' Kobo' : '';
            $amountInWords = ucwords($nairaWords . ' Naira' . $koboWords);

            $a4 = View::make('admin.Accounts.receipt_a4', [
                'site' => $site,
                'patientName' => $patientName,
                'patientFileNo' => $patientFileNo,
                'date' => $date,
                'ref' => $ref,
                'receiptDetails' => $receiptDetails,
                'totalDiscount' => $totalDiscount,
                'totalPaid' => $total,
                'amountInWords' => $amountInWords,
                'paymentType' => $data['payment_type'],
                'notes' => '',
                'currentUserName' => $currentUserName,
            ])->render();

            $thermal = View::make('admin.Accounts.receipt_thermal', [
                'site' => $site,
                'patientName' => $patientName,
                'patientFileNo' => $patientFileNo,
                'date' => $date,
                'ref' => $ref,
                'receiptDetails' => $receiptDetails,
                'totalDiscount' => $totalDiscount,
                'totalPaid' => $total,
                'amountInWords' => $amountInWords,
                'paymentType' => $data['payment_type'],
                'notes' => '',
                'currentUserName' => $currentUserName,
            ])->render();

            DB::commit();

            return response()->json([
                'payment_id' => $payment->id,
                'total' => $total,
                'total_discount' => $totalDiscount,
                'receipt_a4' => $a4,
                'receipt_thermal' => $thermal,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Billing workbench payment failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Print receipt for selected payment(s)
     */
    public function printReceipt(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'payment_ids' => 'required|array|min:1',
            'payment_ids.*' => 'integer|exists:payments,id',
        ]);

        $patient = patient::findOrFail($request->patient_id);
        $payments = payment::whereIn('id', $request->payment_ids)->get();

        // Aggregate items from selected payments
        $allItems = ProductOrServiceRequest::whereIn('payment_id', $request->payment_ids)
            ->with(['service.price', 'product.price'])
            ->get();

        $receiptDetails = [];
        $totalAmount = 0;
        $totalDiscount = 0;

        foreach ($allItems as $row) {
            $isService = $row->service_id !== null;
            $basePrice = $isService
                ? optional(optional($row->service)->price)->sale_price
                : optional(optional($row->product)->price)->current_sale_price;
            $price = $row->payable_amount !== null ? $row->payable_amount : ($basePrice ?? 0);
            $qty = $row->qty ?? 1;
            $discountPercent = $row->discount ?? 0;
            $discountAmount = ($price * $qty) * ($discountPercent / 100);
            $lineTotal = ($price * $qty) - $discountAmount;

            $totalAmount += $lineTotal;
            $totalDiscount += $discountAmount;

            $receiptDetails[] = [
                'type' => $isService ? 'Service' : 'Product',
                'name' => $isService ? optional($row->service)->service_name : optional($row->product)->product_name,
                'price' => $price,
                'qty' => $qty,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'amount_paid' => $lineTotal,
            ];
        }

        $site = appsettings();
        $patientName = userfullname($patient->user_id);
        $patientFileNo = $patient->file_no ?? 'N/A';
        $currentUserName = Auth::user() ? userfullname(Auth::id()) : '';
        $date = now()->format('Y-m-d H:i');
        $ref = $payments->first()->reference_no ?? 'COMBINED';

        $amountParts = explode('.', number_format((float) $totalAmount, 2, '.', ''));
        $nairaWords = convert_number_to_words((int) $amountParts[0]);
        $koboWords = ((int) $amountParts[1]) > 0 ? ' and ' . convert_number_to_words((int) $amountParts[1]) . ' Kobo' : '';
        $amountInWords = ucwords($nairaWords . ' Naira' . $koboWords);

        $paymentType = $payments->first()->payment_type ?? 'N/A';

        $a4 = View::make('admin.Accounts.receipt_a4', [
            'site' => $site,
            'patientName' => $patientName,
            'patientFileNo' => $patientFileNo,
            'date' => $date,
            'ref' => $ref,
            'receiptDetails' => $receiptDetails,
            'totalDiscount' => $totalDiscount,
            'totalPaid' => $totalAmount,
            'amountInWords' => $amountInWords,
            'paymentType' => $paymentType,
            'notes' => '',
            'currentUserName' => $currentUserName,
        ])->render();

        $thermal = View::make('admin.Accounts.receipt_thermal', [
            'site' => $site,
            'patientName' => $patientName,
            'patientFileNo' => $patientFileNo,
            'date' => $date,
            'ref' => $ref,
            'receiptDetails' => $receiptDetails,
            'totalDiscount' => $totalDiscount,
            'totalPaid' => $totalAmount,
            'amountInWords' => $amountInWords,
            'paymentType' => $paymentType,
            'notes' => '',
            'currentUserName' => $currentUserName,
        ])->render();

        return response()->json([
            'receipt_a4' => $a4,
            'receipt_thermal' => $thermal,
        ]);
    }

    /**
     * Print invoice for selected unpaid items
     */
    public function printInvoice(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'integer|exists:product_or_service_requests,id',
        ]);

        $patient = patient::with('hmo')->findOrFail($request->patient_id);

        // Get the unpaid items
        $items = ProductOrServiceRequest::whereIn('id', $request->item_ids)
            ->whereNull('payment_id')
            ->whereNull('invoice_id')
            ->with(['service.price', 'product.price'])
            ->get();

        if ($items->isEmpty()) {
            return response()->json(['message' => 'No valid unpaid items found'], 422);
        }

        $invoiceDetails = [];
        $totalAmount = 0;
        $totalDiscount = 0;
        $totalHmoCoverage = 0;

        foreach ($items as $row) {
            $isService = $row->service_id !== null;
            $basePrice = $isService
                ? optional(optional($row->service)->price)->sale_price
                : optional(optional($row->product)->price)->current_sale_price;
            $price = $row->payable_amount !== null ? $row->payable_amount : ($basePrice ?? 0);
            $qty = $row->qty ?? 1;
            $discountPercent = $row->discount ?? 0;
            $subtotal = $price * $qty;
            $discountAmount = $subtotal * ($discountPercent / 100);
            $lineTotal = $subtotal - $discountAmount;
            $hmoCoverage = $row->claims_amount ?? 0;

            $totalAmount += $lineTotal;
            $totalDiscount += $discountAmount;
            $totalHmoCoverage += $hmoCoverage;

            $invoiceDetails[] = [
                'type' => $isService ? 'Service' : 'Product',
                'name' => $isService ? optional($row->service)->service_name : optional($row->product)->product_name,
                'price' => $price,
                'qty' => $qty,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'hmo_coverage' => $hmoCoverage,
                'amount' => $lineTotal,
                'date' => $row->created_at ? $row->created_at->format('d/m/Y H:i') : 'N/A',
            ];
        }

        $site = appsettings();
        $patientName = userfullname($patient->user_id);
        $patientFileNo = $patient->file_no ?? 'N/A';
        $hmoName = $patient->hmo ? $patient->hmo->name : null;
        $hmoNo = $patient->hmo_no ?? null;
        $currentUserName = Auth::user() ? userfullname(Auth::id()) : '';
        $date = now()->format('Y-m-d H:i');
        $invoiceNo = 'INV-' . strtoupper(substr(md5(time() . rand()), 0, 8));

        // Calculate patient payable (total minus HMO coverage)
        $patientPayable = $totalAmount - $totalHmoCoverage;

        $amountParts = explode('.', number_format((float) $patientPayable, 2, '.', ''));
        $nairaWords = convert_number_to_words((int) $amountParts[0]);
        $koboWords = ((int) $amountParts[1]) > 0 ? ' and ' . convert_number_to_words((int) $amountParts[1]) . ' Kobo' : '';
        $amountInWords = ucwords($nairaWords . ' Naira' . $koboWords);

        // Use invoice views (same as receipt but titled as Invoice)
        $a4 = View::make('admin.Accounts.invoice_a4', [
            'site' => $site,
            'patientName' => $patientName,
            'patientFileNo' => $patientFileNo,
            'hmoName' => $hmoName,
            'hmoNo' => $hmoNo,
            'date' => $date,
            'invoiceNo' => $invoiceNo,
            'invoiceDetails' => $invoiceDetails,
            'totalDiscount' => $totalDiscount,
            'totalAmount' => $totalAmount,
            'totalHmoCoverage' => $totalHmoCoverage,
            'patientPayable' => $patientPayable,
            'amountInWords' => $amountInWords,
            'notes' => 'This is a proforma invoice. Payment is required to confirm services.',
            'currentUserName' => $currentUserName,
        ])->render();

        $thermal = View::make('admin.Accounts.invoice_thermal', [
            'site' => $site,
            'patientName' => $patientName,
            'patientFileNo' => $patientFileNo,
            'hmoName' => $hmoName,
            'hmoNo' => $hmoNo,
            'date' => $date,
            'invoiceNo' => $invoiceNo,
            'invoiceDetails' => $invoiceDetails,
            'totalDiscount' => $totalDiscount,
            'totalAmount' => $totalAmount,
            'totalHmoCoverage' => $totalHmoCoverage,
            'patientPayable' => $patientPayable,
            'amountInWords' => $amountInWords,
            'notes' => '',
            'currentUserName' => $currentUserName,
        ])->render();

        return response()->json([
            'invoice_a4' => $a4,
            'invoice_thermal' => $thermal,
            'invoice_no' => $invoiceNo,
        ]);
    }

    /**
     * Get current user's transactions (for report modal)
     */
    public function getMyTransactions(Request $request)
    {
        $userId = Auth::id();

        $query = payment::where('user_id', $userId)->with(['patient.user', 'bank']);

        // Date filtering - default to today
        $from = $request->input('from', now()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $query->whereBetween('created_at', [
            Carbon::parse($from)->startOfDay(),
            Carbon::parse($to)->endOfDay()
        ]);

        // Payment type filter
        if ($request->has('payment_type') && $request->payment_type) {
            $query->where('payment_type', $request->payment_type);
        }

        // Bank filter
        if ($request->has('bank_id') && $request->bank_id) {
            $query->where('bank_id', $request->bank_id);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        $summary = [
            'total_amount' => $transactions->sum('total'),
            'total_discount' => $transactions->sum('total_discount'),
            'count' => $transactions->count(),
            'by_type' => $transactions->groupBy('payment_type')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'amount' => $group->sum('total'),
                    'discount' => $group->sum('total_discount')
                ];
            })
        ];

        $transactionsFormatted = $transactions->map(function ($tx) {
            return [
                'id' => $tx->id,
                'reference_no' => $tx->reference_no,
                'patient_name' => userfullname($tx->patient->user_id),
                'file_no' => $tx->patient->file_no,
                'payment_type' => $tx->payment_type,
                'payment_method' => $tx->payment_method,
                'bank_name' => $tx->bank ? $tx->bank->name : null,
                'total' => $tx->total,
                'total_discount' => $tx->total_discount,
                'created_at' => $tx->created_at->format('Y-m-d H:i'),
            ];
        });

        return response()->json([
            'transactions' => $transactionsFormatted,
            'summary' => $summary,
            'from' => $from,
            'to' => $to
        ]);
    }

    /**
     * Create patient account (AJAX)
     */
    public function createPatientAccount(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
        ]);

        // Check if account already exists
        $existingAccount = PatientAccount::where('patient_id', $request->patient_id)->first();
        if ($existingAccount) {
            return response()->json([
                'message' => 'Account already exists for this patient',
                'account' => [
                    'id' => $existingAccount->id,
                    'balance' => $existingAccount->balance,
                    'updated_at' => $existingAccount->updated_at,
                ]
            ]);
        }

        try {
            $account = PatientAccount::create([
                'patient_id' => $request->patient_id,
                'balance' => 0,
            ]);

            return response()->json([
                'message' => 'Account created successfully',
                'account' => [
                    'id' => $account->id,
                    'balance' => $account->balance,
                    'updated_at' => $account->updated_at,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create patient account', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create account'], 500);
        }
    }

    /**
     * Make deposit to patient account (AJAX)
     */
    public function makeAccountDeposit(Request $request)
    {
        $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'amount' => 'required|numeric',
            'description' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $account = PatientAccount::where('patient_id', $request->patient_id)->first();

            if (!$account) {
                return response()->json(['message' => 'Account not found'], 404);
            }

            $newBalance = $account->balance + $request->amount;
            $account->update(['balance' => $newBalance]);

            // Create payment record for tracking
            $payment = payment::create([
                'patient_id' => $request->patient_id,
                'user_id' => Auth::id(),
                'total' => $request->amount,
                'reference_no' => generate_invoice_no(),
                'payment_type' => $request->amount >= 0 ? 'ACC_DEPOSIT' : 'ACC_DEBIT',
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Transaction saved successfully',
                'new_balance' => $newBalance,
                'payment_id' => $payment->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to make deposit', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to save transaction'], 500);
        }
    }

    /**
     * Process account transaction (deposit, withdraw, adjust) - AJAX
     */
    public function processAccountTransaction(Request $request)
    {
        // Different validation rules based on transaction type
        $amountRule = $request->transaction_type === 'adjust'
            ? 'required|numeric|not_in:0' // Allow negative for adjustments, but not zero
            : 'required|numeric|min:0.01'; // Positive only for deposit/withdraw

        $request->validate([
            'patient_id' => 'required|integer|exists:patients,id',
            'transaction_type' => 'required|in:deposit,withdraw,adjust',
            'amount' => $amountRule,
            'description' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|in:CASH,POS,TRANSFER,MOBILE',
            'bank_id' => 'nullable|integer|exists:banks,id',
        ]);

        // Require description for adjustments
        if ($request->transaction_type === 'adjust' && empty($request->description)) {
            return response()->json(['message' => 'Description is required for adjustments'], 422);
        }

        DB::beginTransaction();

        try {
            $account = PatientAccount::where('patient_id', $request->patient_id)->first();

            if (!$account) {
                return response()->json(['message' => 'Account not found. Please create an account first.'], 404);
            }

            $amount = abs($request->amount);
            $paymentType = '';
            $balanceChange = 0;
            $notes = $request->description ?? '';

            switch ($request->transaction_type) {
                case 'deposit':
                    $balanceChange = $amount;
                    $paymentType = 'ACC_DEPOSIT';
                    $notes = $notes ?: 'Account deposit';
                    break;

                case 'withdraw':
                    $balanceChange = -$amount;
                    $paymentType = 'ACC_WITHDRAW';
                    $notes = $notes ?: 'Account withdrawal';
                    break;

                case 'adjust':
                    // For adjustments, amount can be positive (credit) or negative (debit)
                    // Frontend sends positive always, so we check if description hints at debit
                    $balanceChange = $request->amount; // Keep sign as sent
                    $paymentType = 'ACC_ADJUSTMENT';
                    break;
            }

            $newBalance = $account->balance + $balanceChange;
            $previousBalance = $account->balance;
            $account->update(['balance' => $newBalance]);

            // Create payment record for tracking
            // Note: We store description in reference_no field as notes field doesn't exist
            $refNo = generate_invoice_no();
            $payment = payment::create([
                'patient_id' => $request->patient_id,
                'user_id' => Auth::id(),
                'total' => $balanceChange,
                'reference_no' => $refNo,
                'payment_type' => $paymentType,
                'payment_method' => $request->payment_method ?? ($paymentType === 'ACC_ADJUSTMENT' ? null : 'CASH'),
                'bank_id' => $request->bank_id,
            ]);

            // UNIFIED SYSTEM: Also create PatientDeposit record for deposits
            // This ensures deposits are tracked in both legacy (PatientAccount) and modern (PatientDeposit) systems
            // The PatientDepositObserver will create the journal entry, so PaymentObserver will skip
            $deposit = null;
            if ($paymentType === 'ACC_DEPOSIT') {
                $depositNumber = PatientDeposit::generateNumber();
                $deposit = PatientDeposit::create([
                    'patient_id' => $request->patient_id,
                    'deposit_number' => $depositNumber,
                    'deposit_date' => now(),
                    'amount' => $amount, // Always positive for deposits
                    'utilized_amount' => 0,
                    'refunded_amount' => 0,
                    'source_payment_id' => $payment->id, // Link to prevent duplicate JE
                    'deposit_type' => $request->deposit_type ?? PatientDeposit::TYPE_GENERAL,
                    'payment_method' => strtolower($request->payment_method ?? 'cash'),
                    'bank_id' => $request->bank_id,
                    'payment_reference' => $request->payment_reference,
                    'receipt_number' => $refNo,
                    'received_by' => Auth::id(),
                    'status' => PatientDeposit::STATUS_ACTIVE,
                    'notes' => $notes,
                ]);
            }

            DB::commit();

            $typeLabels = [
                'deposit' => 'Deposit',
                'withdraw' => 'Withdrawal',
                'adjust' => 'Adjustment',
            ];

            // Generate receipts for deposits
            $response = [
                'message' => $typeLabels[$request->transaction_type] . ' saved successfully',
                'new_balance' => $newBalance,
                'payment_id' => $payment->id,
            ];

            if ($deposit) {
                $receipts = $this->generateDepositReceipts($deposit, $previousBalance, $newBalance);
                $response['deposit_id'] = $deposit->id;
                $response['deposit_number'] = $deposit->deposit_number;
                $response['receipt_a4'] = $receipts['a4'];
                $response['receipt_thermal'] = $receipts['thermal'];
            }

            return response()->json($response);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process account transaction', ['error' => $e->getMessage(), 'type' => $request->transaction_type]);
            return response()->json(['message' => 'Failed to save transaction'], 500);
        }
    }

    /**
     * Print deposit receipt by deposit ID (for reprinting from receipts list).
     */
    public function printDepositReceipt($depositId)
    {
        $deposit = PatientDeposit::with(['patient', 'bank'])->findOrFail($depositId);

        // Calculate what the balance was before and after this deposit
        $account = PatientAccount::where('patient_id', $deposit->patient_id)->first();
        $currentBalance = $account ? $account->balance : 0;

        // Get the total of all deposits after this one to calculate the balance at time of deposit
        $laterDeposits = PatientDeposit::where('patient_id', $deposit->patient_id)
            ->where('deposit_date', '>', $deposit->deposit_date)
            ->sum('amount');

        $newBalance = $currentBalance + $laterDeposits;
        $previousBalance = $newBalance - $deposit->amount;

        $receipts = $this->generateDepositReceipts($deposit, $previousBalance, $newBalance);

        return response()->json([
            'receipt_a4' => $receipts['a4'],
            'receipt_thermal' => $receipts['thermal'],
        ]);
    }

    /**
     * Generate deposit receipts (A4 and Thermal).
     */
    protected function generateDepositReceipts(PatientDeposit $deposit, float $previousBalance, float $newBalance): array
    {
        $site = appsettings();
        $patient = $deposit->patient;
        $patientName = userfullname($patient->user_id);
        $patientFileNo = $patient->file_no ?? 'N/A';
        $receivedBy = userfullname($deposit->received_by);

        // Amount in words
        $amountParts = explode('.', number_format((float) $deposit->amount, 2, '.', ''));
        $nairaWords = convert_number_to_words((int) $amountParts[0]);
        $koboWords = ((int) $amountParts[1]) > 0 ? ' and ' . convert_number_to_words((int) $amountParts[1]) . ' Kobo' : '';
        $amountInWords = ucwords($nairaWords . ' Naira' . $koboWords) . ' Only';

        // Deposit type label
        $depositTypes = [
            PatientDeposit::TYPE_ADMISSION => 'Admission Deposit',
            PatientDeposit::TYPE_PROCEDURE => 'Procedure Deposit',
            PatientDeposit::TYPE_SURGERY => 'Surgery Deposit',
            PatientDeposit::TYPE_INVESTIGATION => 'Investigation Deposit',
            PatientDeposit::TYPE_GENERAL => 'General Advance',
            PatientDeposit::TYPE_OTHER => 'Other Deposit',
        ];
        $depositType = $depositTypes[$deposit->deposit_type] ?? $deposit->deposit_type;

        // Payment method label
        $paymentMethods = [
            PatientDeposit::METHOD_CASH => 'Cash',
            PatientDeposit::METHOD_POS => 'POS/Card',
            PatientDeposit::METHOD_TRANSFER => 'Bank Transfer',
            PatientDeposit::METHOD_CHEQUE => 'Cheque',
            'cash' => 'Cash',
            'pos' => 'POS/Card',
            'transfer' => 'Bank Transfer',
            'cheque' => 'Cheque',
        ];
        $paymentMethod = $paymentMethods[$deposit->payment_method] ?? $deposit->payment_method;

        // Bank name
        $bank = $deposit->bank?->name ?? null;

        $viewData = [
            'site' => $site,
            'depositNumber' => $deposit->deposit_number,
            'patientName' => $patientName,
            'patientFileNo' => $patientFileNo,
            'date' => $deposit->deposit_date->format('Y-m-d H:i'),
            'amount' => $deposit->amount,
            'amountInWords' => $amountInWords,
            'depositType' => $depositType,
            'paymentMethod' => $paymentMethod,
            'bank' => $bank,
            'paymentReference' => $deposit->payment_reference,
            'receivedBy' => $receivedBy,
            'previousBalance' => $previousBalance,
            'newBalance' => $newBalance,
            'notes' => $deposit->notes,
        ];

        return [
            'a4' => View::make('admin.Accounts.deposit_receipt_a4', $viewData)->render(),
            'thermal' => View::make('admin.Accounts.deposit_receipt_thermal', $viewData)->render(),
        ];
    }

    /**
     * Generate comprehensive account statement (AJAX).
     */
    public function generateStatement(Request $request, $patientId)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'include_deposits' => 'nullable',
            'include_payments' => 'nullable',
            'include_withdrawals' => 'nullable',
            'include_services' => 'nullable',
        ]);

        // Convert string booleans to actual booleans
        $includeDeposits = filter_var($request->include_deposits, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        $includePayments = filter_var($request->include_payments, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        $includeWithdrawals = filter_var($request->include_withdrawals, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
        $includeServices = filter_var($request->include_services, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;

        $patient = patient::with(['hmo', 'user'])->findOrFail($patientId);
        $account = PatientAccount::where('patient_id', $patientId)->first();

        $dateFrom = Carbon::parse($request->date_from)->startOfDay();
        $dateTo = Carbon::parse($request->date_to)->endOfDay();

        // Get all transactions for the statement
        $transactions = collect();

        // Include deposits (ACC_DEPOSIT from payments OR from patient_deposits)
        if ($includeDeposits) {
            $deposits = PatientDeposit::where('patient_id', $patientId)
                ->whereBetween('deposit_date', [$dateFrom, $dateTo])
                ->orderBy('deposit_date')
                ->get();

            foreach ($deposits as $dep) {
                $transactions->push([
                    'date' => $dep->deposit_date->format('M d, Y'),
                    'short_date' => $dep->deposit_date->format('m/d'),
                    'time' => $dep->deposit_date->format('h:i A'),
                    'datetime' => $dep->deposit_date,
                    'reference' => $dep->deposit_number,
                    'type' => 'deposit',
                    'type_class' => 'deposit',
                    'type_label' => 'Deposit',
                    'short_type' => 'DEP',
                    'description' => $this->getDepositTypeLabel($dep->deposit_type) . ' - ' . ucfirst($dep->payment_method),
                    'items' => $dep->notes,
                    'debit' => 0,
                    'credit' => $dep->amount,
                    'running_balance' => 0, // Will calculate later
                ]);
            }
        }

        // Include direct payments (CASH, POS, TRANSFER - not from account)
        if ($includePayments) {
            $directPayments = payment::where('patient_id', $patientId)
                ->whereNotIn('payment_type', ['ACC_DEPOSIT', 'ACC_WITHDRAW', 'ACC_ADJUSTMENT'])
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->with(['product_or_service_request.service', 'product_or_service_request.product'])
                ->orderBy('created_at')
                ->get();

            foreach ($directPayments as $pmt) {
                $items = $pmt->product_or_service_request->map(function ($item) {
                    if ($item->service_id) {
                        return $item->service?->service_name ?? 'Service';
                    }
                    return $item->product?->product_name ?? 'Product';
                })->implode(', ');

                $transactions->push([
                    'date' => $pmt->created_at->format('M d, Y'),
                    'short_date' => $pmt->created_at->format('m/d'),
                    'time' => $pmt->created_at->format('h:i A'),
                    'datetime' => $pmt->created_at,
                    'reference' => $pmt->reference_no,
                    'type' => 'payment',
                    'type_class' => 'payment',
                    'type_label' => 'Payment (' . ($pmt->payment_method ?? 'Cash') . ')',
                    'short_type' => 'PAY',
                    'description' => 'Direct payment for services',
                    'items' => $items ?: null,
                    'debit' => abs($pmt->total),
                    'credit' => 0,
                    'running_balance' => 0,
                ]);
            }
        }

        // Include account withdrawals/payments from balance
        if ($includeWithdrawals) {
            $withdrawals = payment::where('patient_id', $patientId)
                ->whereIn('payment_type', ['ACC_WITHDRAW', 'ACC_ADJUSTMENT'])
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->orderBy('created_at')
                ->get();

            foreach ($withdrawals as $wd) {
                $isAdjustment = $wd->payment_type === 'ACC_ADJUSTMENT';
                $isCredit = $wd->total > 0;

                $transactions->push([
                    'date' => $wd->created_at->format('M d, Y'),
                    'short_date' => $wd->created_at->format('m/d'),
                    'time' => $wd->created_at->format('h:i A'),
                    'datetime' => $wd->created_at,
                    'reference' => $wd->reference_no,
                    'type' => $isAdjustment ? 'adjustment' : 'withdrawal',
                    'type_class' => $isAdjustment ? 'adjustment' : 'withdrawal',
                    'type_label' => $isAdjustment ? 'Adjustment' : 'Withdrawal',
                    'short_type' => $isAdjustment ? 'ADJ' : 'WDR',
                    'description' => $isAdjustment
                        ? ($isCredit ? 'Credit adjustment' : 'Debit adjustment')
                        : 'Account withdrawal/refund',
                    'items' => null,
                    'debit' => $isCredit ? 0 : abs($wd->total),
                    'credit' => $isCredit ? $wd->total : 0,
                    'running_balance' => 0,
                ]);
            }
        }

        // Include services paid from deposit (via deposit applications)
        if ($includeServices) {
            $applications = \App\Models\Accounting\PatientDepositApplication::whereHas('deposit', function ($q) use ($patientId) {
                $q->where('patient_id', $patientId);
            })
                ->whereBetween('application_date', [$dateFrom, $dateTo])
                ->where('status', 'applied')
                ->with(['deposit', 'payment.product_or_service_request.service', 'payment.product_or_service_request.product'])
                ->orderBy('application_date')
                ->get();

            foreach ($applications as $app) {
                $items = '';
                if ($app->payment) {
                    $items = $app->payment->product_or_service_request->map(function ($item) {
                        if ($item->service_id) {
                            return $item->service?->service_name ?? 'Service';
                        }
                        return $item->product?->product_name ?? 'Product';
                    })->implode(', ');
                }

                $transactions->push([
                    'date' => $app->application_date->format('M d, Y'),
                    'short_date' => $app->application_date->format('m/d'),
                    'time' => $app->created_at->format('h:i A'),
                    'datetime' => $app->application_date,
                    'reference' => $app->deposit->deposit_number ?? 'N/A',
                    'type' => 'service',
                    'type_class' => 'service',
                    'type_label' => 'Deposit Applied',
                    'short_type' => 'SVC',
                    'description' => 'Payment from deposit balance',
                    'items' => $items ?: $app->notes,
                    'debit' => $app->amount,
                    'credit' => 0,
                    'running_balance' => 0,
                ]);
            }
        }

        // Sort by datetime
        $transactions = $transactions->sortBy('datetime')->values();

        // Calculate running balance
        // First, calculate opening balance (balance before the period)
        $depositsBeforePeriod = PatientDeposit::where('patient_id', $patientId)
            ->where('deposit_date', '<', $dateFrom)
            ->sum('amount');

        $withdrawalsBeforePeriod = abs(payment::where('patient_id', $patientId)
            ->where('payment_type', 'ACC_WITHDRAW')
            ->where('created_at', '<', $dateFrom)
            ->sum('total'));

        $adjustmentsBeforePeriod = payment::where('patient_id', $patientId)
            ->where('payment_type', 'ACC_ADJUSTMENT')
            ->where('created_at', '<', $dateFrom)
            ->sum('total');

        $applicationsBeforePeriod = \App\Models\Accounting\PatientDepositApplication::whereHas('deposit', function ($q) use ($patientId) {
            $q->where('patient_id', $patientId);
        })
            ->where('application_date', '<', $dateFrom)
            ->where('status', 'applied')
            ->sum('amount');

        $openingBalance = $depositsBeforePeriod - $withdrawalsBeforePeriod + $adjustmentsBeforePeriod - $applicationsBeforePeriod;

        // Calculate running balance for each transaction
        $runningBalance = $openingBalance;
        $transactions = $transactions->map(function ($tx) use (&$runningBalance) {
            $runningBalance += $tx['credit'] - $tx['debit'];
            $tx['running_balance'] = $runningBalance;
            return $tx;
        });

        // Calculate summary
        $periodCredits = $transactions->sum('credit');
        $periodDebits = $transactions->sum('debit');
        $closingBalance = $account ? $account->balance : ($openingBalance + $periodCredits - $periodDebits);

        $summary = [
            'opening_balance' => $openingBalance,
            'total_deposits' => $transactions->where('type', 'deposit')->sum('credit'),
            'total_payments' => $transactions->where('type', 'payment')->sum('debit') + $transactions->where('type', 'service')->sum('debit'),
            'total_withdrawals' => $transactions->whereIn('type', ['withdrawal', 'adjustment'])->sum('debit'),
            'period_credits' => $periodCredits,
            'period_debits' => $periodDebits,
            'closing_balance' => $closingBalance,
        ];

        // Prepare view data
        $site = appsettings();
        $viewData = [
            'site' => $site,
            'patientName' => userfullname($patient->user_id),
            'patientFileNo' => $patient->file_no ?? 'N/A',
            'patientHmo' => $patient->hmo?->name ?? 'Private / Self-Pay',
            'patientPhone' => $patient->phone_no ?? 'N/A',
            'dateFrom' => $dateFrom->format('M d, Y'),
            'dateTo' => $dateTo->format('M d, Y'),
            'transactions' => $transactions->toArray(),
            'summary' => $summary,
            'showOpeningBalance' => $openingBalance != 0,
            'preparedBy' => userfullname(Auth::id()),
        ];

        return response()->json([
            'success' => true,
            'statement_a4' => View::make('admin.Accounts.account_statement_a4', $viewData)->render(),
            'statement_thermal' => View::make('admin.Accounts.account_statement_thermal', $viewData)->render(),
            'summary' => $summary,
            'transaction_count' => $transactions->count(),
        ]);
    }

    /**
     * Get deposit type label.
     */
    protected function getDepositTypeLabel(string $type): string
    {
        return match ($type) {
            'admission' => 'Admission Deposit',
            'procedure' => 'Procedure Deposit',
            'surgery' => 'Surgery Deposit',
            'investigation' => 'Investigation Deposit',
            'general' => 'General Advance',
            'other' => 'Other Deposit',
            default => ucfirst($type),
        };
    }

    /**
     * Get patient admission history
     */
    public function getAdmissionHistory($patientId)
    {
        $patient = patient::find($patientId);
        if (!$patient) {
            return response()->json(['error' => 'Patient not found'], 404);
        }

        $admissions = \App\Models\AdmissionRequest::where('patient_id', $patientId)
            ->with(['bed.wardRelation'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($admission) use ($patient) {
                $admitDate = $admission->bed_assign_date;
                $dischargeDate = $admission->discharge_date;

                // Calculate length of stay
                $los = 0;
                if ($admitDate) {
                    $endDate = $dischargeDate ?? now();
                    $los = Carbon::parse($admitDate)->diffInDays(Carbon::parse($endDate)) + 1;
                }

                // Calculate total bill for this admission period
                $totalBill = $this->calculateAdmissionBillTotal($patient->user_id, $admitDate, $dischargeDate);

                // Get doctor name - doctor_id stores Staff ID, not User ID
                $doctorStaffId = $admission->doctor_id;
                $doctorName = 'N/A';
                if ($doctorStaffId) {
                    $doctorStaff = \App\Models\Staff::find($doctorStaffId);
                    if ($doctorStaff && $doctorStaff->user_id) {
                        $doctorName = userfullname($doctorStaff->user_id);
                    }
                }

                return [
                    'id' => $admission->id,
                    'admitted_date' => $admitDate ? Carbon::parse($admitDate)->format('d/m/Y H:i') : 'Pending',
                    'discharge_date' => $dischargeDate ? Carbon::parse($dischargeDate)->format('d/m/Y H:i') : ($admission->discharged ? 'Unknown' : null),
                    'los' => $los,
                    'ward' => optional(optional($admission->bed)->wardRelation)->name ?? ($admission->bed->ward ?? 'N/A'),
                    'bed' => optional($admission->bed)->name ?? 'N/A',
                    'doctor' => $doctorName,
                    'reason' => $admission->admission_reason ?? $admission->note ?? 'N/A',
                    'status' => $admission->discharged ? 'discharged' : 'admitted',
                    'total_bill' => $totalBill,
                ];
            });

        return response()->json([
            'admissions' => $admissions,
            'count' => $admissions->count(),
        ]);
    }

    /**
     * Calculate total bill amount for an admission period
     */
    protected function calculateAdmissionBillTotal($userId, $admitDate, $dischargeDate)
    {
        if (!$admitDate) return 0;

        $query = ProductOrServiceRequest::where('user_id', $userId)
            ->where('created_at', '>=', $admitDate);

        if ($dischargeDate) {
            $query->where('created_at', '<=', Carbon::parse($dischargeDate)->endOfDay());
        }

        return $query->sum(DB::raw('COALESCE(payable_amount, amount * qty)'));
    }

    /**
     * Get detailed admission bill (categorized)
     */
    public function getAdmissionBillDetail($admissionId)
    {
        $admission = \App\Models\AdmissionRequest::with(['bed.wardRelation', 'patient'])->find($admissionId);

        if (!$admission) {
            return response()->json(['error' => 'Admission not found'], 404);
        }

        $patient = $admission->patient;
        $admitDate = $admission->bed_assign_date;
        $dischargeDate = $admission->discharge_date;

        if (!$admitDate) {
            return response()->json(['error' => 'Admission date not set'], 400);
        }

        // Get all billing items during admission period
        $query = ProductOrServiceRequest::with(['service.category', 'product.category', 'payment'])
            ->where('user_id', $patient->user_id)
            ->where('created_at', '>=', $admitDate);

        if ($dischargeDate) {
            $query->where('created_at', '<=', Carbon::parse($dischargeDate)->endOfDay());
        }

        $billingItems = $query->orderBy('created_at', 'asc')->get();

        // Define category mappings (customize based on your categories)
        $categoryMap = [
            'accommodation' => ['bed', 'ward', 'room', 'accommodation'],
            'nursing' => ['nursing', 'nurse'],
            'consultation' => ['consultation', 'doctor', 'visit'],
            'laboratory' => ['lab', 'laboratory', 'test', 'investigation'],
            'radiology' => ['radiology', 'xray', 'x-ray', 'scan', 'imaging', 'ultrasound', 'ct', 'mri'],
            'pharmacy' => ['drug', 'pharmacy', 'medication', 'medicine'],
            'procedure' => ['procedure', 'surgery', 'operation', 'theatre'],
            'consumables' => ['consumable', 'supply', 'supplies', 'material'],
        ];

        $categoryIcons = [
            'accommodation' => 'mdi-bed',
            'nursing' => 'mdi-account-nurse',
            'consultation' => 'mdi-stethoscope',
            'laboratory' => 'mdi-flask',
            'radiology' => 'mdi-radiology-box',
            'pharmacy' => 'mdi-pill',
            'procedure' => 'mdi-medical-bag',
            'consumables' => 'mdi-bandage',
            'other' => 'mdi-file-document',
        ];

        $categoryLabels = [
            'accommodation' => 'Accommodation & Bed',
            'nursing' => 'Nursing Care',
            'consultation' => 'Consultations',
            'laboratory' => 'Laboratory',
            'radiology' => 'Radiology/Imaging',
            'pharmacy' => 'Pharmacy/Medications',
            'procedure' => 'Procedures',
            'consumables' => 'Consumables/Supplies',
            'other' => 'Other Services',
        ];

        // Categorize items
        $categories = [];
        $grossTotal = 0;
        $totalDiscount = 0;
        $totalHmo = 0;
        $totalPaid = 0;
        $timeline = [];

        foreach ($billingItems as $item) {
            // Determine category
            $itemCategory = 'other';
            $categoryName = '';

            if ($item->service_id && $item->service) {
                $categoryName = strtolower($item->service->category->category_name ?? '');
            } elseif ($item->product_id && $item->product) {
                $categoryName = strtolower($item->product->category->category_name ?? 'pharmacy');
                // Products are typically pharmacy
                $itemCategory = 'pharmacy';
            }

            // Match category
            foreach ($categoryMap as $cat => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains($categoryName, $keyword)) {
                        $itemCategory = $cat;
                        break 2;
                    }
                }
            }

            // Calculate amounts
            $qty = $item->qty ?? 1;
            $price = $item->amount ?? 0;
            $subtotal = $price * $qty;
            $discount = $item->discount ?? 0;
            $discountAmount = $subtotal * ($discount / 100);
            $payable = $item->payable_amount ?? ($subtotal - $discountAmount);
            $hmo = $item->claims_amount ?? 0;
            $paid = $item->payment_id ? $payable : 0;

            $grossTotal += $subtotal;
            $totalDiscount += $discountAmount;
            $totalHmo += $hmo;
            $totalPaid += $paid;

            // Add to category
            if (!isset($categories[$itemCategory])) {
                $categories[$itemCategory] = [
                    'name' => $categoryLabels[$itemCategory] ?? ucfirst($itemCategory),
                    'icon' => $categoryIcons[$itemCategory] ?? 'mdi-file-document',
                    'items' => [],
                    'total' => 0,
                    'count' => 0,
                ];
            }

            $itemName = $item->service_id
                ? ($item->service->service_name ?? 'Service')
                : ($item->product->name ?? 'Product');

            $categories[$itemCategory]['items'][] = [
                'name' => $itemName,
                'qty' => $qty,
                'price' => $price,
                'amount' => $payable,
                'date' => Carbon::parse($item->created_at)->format('d/m H:i'),
                'paid' => $paid > 0,
            ];
            $categories[$itemCategory]['total'] += $payable;
            $categories[$itemCategory]['count']++;

            // Add to timeline
            $dayKey = Carbon::parse($item->created_at)->format('Y-m-d');
            if (!isset($timeline[$dayKey])) {
                $timeline[$dayKey] = [
                    'date' => Carbon::parse($item->created_at)->format('D, d M Y'),
                    'day_number' => Carbon::parse($admitDate)->diffInDays(Carbon::parse($item->created_at)) + 1,
                    'items' => [],
                    'total' => 0,
                ];
            }
            $timeline[$dayKey]['items'][] = [
                'name' => $itemName,
                'amount' => $payable,
            ];
            $timeline[$dayKey]['total'] += $payable;
        }

        // Sort categories by total (highest first)
        uasort($categories, fn($a, $b) => $b['total'] <=> $a['total']);

        // Sort timeline by date
        ksort($timeline);

        $balanceDue = $grossTotal - $totalDiscount - $totalHmo - $totalPaid;

        // Get doctor name - doctor_id stores Staff ID
        $doctorName = 'N/A';
        if ($admission->doctor_id) {
            $doctorStaff = \App\Models\Staff::find($admission->doctor_id);
            if ($doctorStaff && $doctorStaff->user_id) {
                $doctorName = userfullname($doctorStaff->user_id);
            }
        }

        return response()->json([
            'admission' => [
                'id' => $admission->id,
                'patient_name' => userfullname($patient->user_id),
                'patient_file_no' => $patient->file_no,
                'admitted_date' => $admitDate ? Carbon::parse($admitDate)->format('d/m/Y H:i') : 'N/A',
                'discharge_date' => $dischargeDate ? Carbon::parse($dischargeDate)->format('d/m/Y H:i') : 'Currently Admitted',
                'los' => $admitDate ? (Carbon::parse($admitDate)->diffInDays($dischargeDate ? Carbon::parse($dischargeDate) : now()) + 1) . ' days' : 'N/A',
                'ward' => optional(optional($admission->bed)->wardRelation)->name ?? ($admission->bed->ward ?? 'N/A'),
                'bed' => optional($admission->bed)->name ?? 'N/A',
                'doctor' => $doctorName,
                'reason' => $admission->admission_reason ?? $admission->note ?? 'N/A',
                'status' => $admission->discharged ? 'discharged' : 'admitted',
            ],
            'categories' => array_values($categories),
            'timeline' => array_values($timeline),
            'totals' => [
                'gross' => $grossTotal,
                'discount' => $totalDiscount,
                'hmo' => $totalHmo,
                'paid' => $totalPaid,
                'balance' => $balanceDue,
            ],
        ]);
    }

    /**
     * Print admission bill
     */
    public function printAdmissionBill($admissionId)
    {
        $detailResponse = $this->getAdmissionBillDetail($admissionId);
        $data = json_decode($detailResponse->getContent(), true);

        if (isset($data['error'])) {
            return response()->json($data, 400);
        }

        $site = \App\Models\ApplicationStatu::first();
        $currentUserName = Auth::user() ? (Auth::user()->surname . ' ' . Auth::user()->firstname) : 'System';
        $date = now()->format('d/m/Y H:i');

        $admission = $data['admission'];
        $categories = $data['categories'];
        $totals = $data['totals'];
        $timeline = $data['timeline'];

        // Generate admission bill number
        $billNo = 'ADM-' . $admissionId . '-' . now()->format('YmdHis');

        // Amount in words
        $amountParts = explode('.', number_format((float) $totals['balance'], 2, '.', ''));
        $nairaWords = convert_number_to_words((int) $amountParts[0]);
        $koboWords = ((int) $amountParts[1]) > 0 ? ' and ' . convert_number_to_words((int) $amountParts[1]) . ' Kobo' : '';
        $amountInWords = ucwords($nairaWords . ' Naira' . $koboWords);

        $a4 = View::make('admin.Accounts.admission_bill_a4', [
            'site' => $site,
            'admission' => $admission,
            'categories' => $categories,
            'timeline' => $timeline,
            'totals' => $totals,
            'billNo' => $billNo,
            'date' => $date,
            'amountInWords' => $amountInWords,
            'currentUserName' => $currentUserName,
        ])->render();

        $thermal = View::make('admin.Accounts.admission_bill_thermal', [
            'site' => $site,
            'admission' => $admission,
            'categories' => $categories,
            'totals' => $totals,
            'billNo' => $billNo,
            'date' => $date,
            'currentUserName' => $currentUserName,
        ])->render();

        return response()->json([
            'bill_a4' => $a4,
            'bill_thermal' => $thermal,
            'bill_no' => $billNo,
        ]);
    }
}
