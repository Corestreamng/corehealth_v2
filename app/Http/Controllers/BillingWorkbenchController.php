<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\ProductOrServiceRequest;
use App\Models\payment;
use App\Models\PatientAccount;
use App\Models\HmoClaim;
use App\Models\Hmo;
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

        $patients = Patient::with('user', 'hmo')
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
            $creditUserIds = Patient::whereIn('id', $creditPatientUserIds)->pluck('user_id');
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
        $patients = Patient::with('user', 'hmo')
            ->whereIn('user_id', $results->pluck('user_id'))
            ->get()
            ->keyBy('user_id');

        $queue = $results->map(function ($item) use ($patients) {
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
            ];
        })->filter(); // Remove null entries

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
        $creditUserIds = Patient::whereIn('id', $creditPatientUserIds)->pluck('user_id');
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
        ]);
    }

    /**
     * Get patient's billing data (unpaid items)
     */
    public function getPatientBillingData($patientId)
    {
        $patient = Patient::with('hmo')->findOrFail($patientId);

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
                    'created_at' => $row->created_at,
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
     */
    public function getPatientReceipts($patientId, Request $request)
    {
        $patient = Patient::findOrFail($patientId);

        $query = payment::where('patient_id', $patientId);

        // Apply date filters
        if ($request->has('from_date') && $request->from_date) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->has('to_date') && $request->to_date) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Apply payment type filter
        if ($request->has('payment_type') && $request->payment_type) {
            $query->where('payment_type', $request->payment_type);
        }

        $payments = $query->orderBy('created_at', 'desc')->get();

        $receipts = $payments->map(function ($payment) use ($patient) {
            $items = ProductOrServiceRequest::with(['service', 'product'])
                ->where('payment_id', $payment->id)
                ->get();

            return [
                'payment_id' => $payment->id,
                'reference_no' => $payment->reference_no,
                'payment_type' => $payment->payment_type,
                'total' => $payment->total,
                'total_discount' => $payment->total_discount ?? 0,
                'created_at' => $payment->created_at->format('Y-m-d H:i'),
                'created_by' => userfullname($payment->user_id),
                'item_count' => $items->count(),
            ];
        });

        // Calculate stats
        $stats = [
            'count' => $receipts->count(),
            'total' => $payments->sum('total'),
            'discounts' => $payments->sum('total_discount') ?? 0,
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
     * Get patient's transaction history
     */
    public function getPatientTransactions($patientId, Request $request)
    {
        $patient = Patient::findOrFail($patientId);

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
     */
    public function getAccountTransactions($patientId, Request $request)
    {
        $patient = Patient::findOrFail($patientId);
        $account = PatientAccount::where('patient_id', $patientId)->first();

        // Get account-related transactions (deposits, withdrawals/payments, adjustments)
        // Note: ACC_WITHDRAW is used for both manual withdrawals AND payments from account balance
        $query = payment::where('patient_id', $patientId)
            ->whereIn('payment_type', ['ACC_DEPOSIT', 'ACC_WITHDRAW', 'ACC_ADJUSTMENT']);

        // Apply date filters - default to current month if not provided
        $fromDate = $request->from_date ?: now()->startOfMonth()->format('Y-m-d');
        $toDate = $request->to_date ?: now()->format('Y-m-d');

        $query->whereDate('created_at', '>=', $fromDate);
        $query->whereDate('created_at', '<=', $toDate);

        // Apply transaction type filter
        if ($request->has('tx_type') && $request->tx_type) {
            $query->where('payment_type', $request->tx_type);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        // Calculate running balance
        $runningBalance = $account ? $account->balance : 0;
        $transactionsFormatted = [];

        // We need to calculate running balance from earliest to latest, then reverse
        $reversedTransactions = $transactions->reverse()->values();
        $balanceHistory = [];
        $currentBalance = 0;

        foreach ($reversedTransactions as $tx) {
            $currentBalance += $tx->total;
            $balanceHistory[$tx->id] = $currentBalance;
        }

        foreach ($transactions as $tx) {
            $txType = 'Adjustment';
            $txIcon = 'mdi-swap-horizontal';
            $txColor = 'info';

            if ($tx->payment_type === 'ACC_DEPOSIT') {
                $txType = 'Deposit';
                $txIcon = 'mdi-arrow-down-bold-circle';
                $txColor = 'success';
            } elseif ($tx->payment_type === 'ACC_WITHDRAW') {
                // ACC_WITHDRAW is used for both manual withdrawals and account payments
                // Negative total = account payment, check if there's an associated payment item
                $txType = 'Withdrawal';
                $txIcon = 'mdi-arrow-up-bold-circle';
                $txColor = 'danger';
            }

            $transactionsFormatted[] = [
                'id' => $tx->id,
                'reference_no' => $tx->reference_no,
                'payment_type' => $tx->payment_type,
                'tx_type' => $txType,
                'tx_icon' => $txIcon,
                'tx_color' => $txColor,
                'amount' => $tx->total, // Already stored as negative for withdrawals/payments
                'description' => $tx->notes ?? '',
                'running_balance' => $balanceHistory[$tx->id] ?? 0,
                'created_at' => $tx->created_at->format('M d, Y'),
                'created_time' => $tx->created_at->format('h:i A'),
                'cashier' => userfullname($tx->user_id),
            ];
        }

        // Calculate summary stats
        $totalDeposits = payment::where('patient_id', $patientId)
            ->where('payment_type', 'ACC_DEPOSIT')
            ->sum('total');

        // Withdrawals (stored as negative values, so abs to get positive)
        $totalWithdrawals = abs(payment::where('patient_id', $patientId)
            ->where('payment_type', 'ACC_WITHDRAW')
            ->sum('total'));

        return response()->json([
            'transactions' => $transactionsFormatted,
            'summary' => [
                'total_deposits' => $totalDeposits,
                'total_withdrawals' => $totalWithdrawals,
                'current_balance' => $account ? $account->balance : 0,
                'transaction_count' => $transactions->count(),
            ]
        ]);
    }

    /**
     * Get patient's account summary
     */
    public function getPatientAccountSummary($patientId)
    {
        $patient = Patient::with('hmo')->findOrFail($patientId);

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
            $patient = Patient::with('hmo')->findOrFail($data['patient_id']);

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

            // If paying from account, verify and deduct balance
            // Match original payment summary behavior: use ACC_WITHDRAW and store negative total
            $paymentType = $data['payment_type'];
            $paymentTotal = $total;

            if ($data['payment_type'] === 'ACCOUNT') {
                $account = PatientAccount::where('patient_id', $patient->id)->first();
                if (!$account || $account->balance < $total) {
                    throw new \Exception('Insufficient account balance. Available: ₦' . number_format($account ? $account->balance : 0, 2));
                }
                // Deduct from account
                $account->balance -= $total;
                $account->save();

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

        $patient = Patient::findOrFail($request->patient_id);
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

            DB::commit();

            $typeLabels = [
                'deposit' => 'Deposit',
                'withdraw' => 'Withdrawal',
                'adjust' => 'Adjustment',
            ];

            return response()->json([
                'message' => $typeLabels[$request->transaction_type] . ' saved successfully',
                'new_balance' => $newBalance,
                'payment_id' => $payment->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process account transaction', ['error' => $e->getMessage(), 'type' => $request->transaction_type]);
            return response()->json(['message' => 'Failed to save transaction'], 500);
        }
    }
}
