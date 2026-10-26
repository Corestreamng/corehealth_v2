<?php

namespace App\Http\Controllers;

use App\Models\Accounting\PatientDeposit;
use App\Models\Accounting\PatientDepositApplication;
use App\Models\Department;
use App\Models\Hmo;
use App\Models\HmoClaim;
use App\Models\Patient;
use App\Models\PatientAccount;
use App\Models\Payment;
use App\Models\PaymentSplit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductOrServiceRequest;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
     * Get active staff list for checkout selection
     */
    public function getStaffList()
    {
        $staff = \App\Models\User::whereHas('staff_profile')
            ->where('status', '>', 0)
            ->with('staff_profile')
            ->get()
            ->map(function ($user) {
                $profile = $user->staff_profile;
                $empId = $profile ? $profile->employee_id : '';
                $name = trim($user->surname . ' ' . $user->firstname . ' ' . $user->othername);

                return [
                    'id' => $user->id,
                    'name' => $name,
                    'employee_id' => $empId,
                    'text' => $name . ($empId ? ' (Code: ' . $empId . ')' : ''),
                ];
            })
            ->sortBy('name')
            ->values();

        return response()->json($staff);
    }

    /**
     * Get active organizations list for checkout selection
     */
    public function getOrganizationList()
    {
        $organizations = \App\Models\Organization::active()
            ->orderBy('name')
            ->get()
            ->map(function ($org) {
                return [
                    'id' => $org->id,
                    'name' => $org->name,
                    'text' => $org->name,
                    'outstanding' => $org->total_outstanding,
                ];
            })
            ->values();

        return response()->json($organizations);
    }

    /**
     * Start a billing shift for the current user
     */
    public function startBillingShift(Request $request)
    {
        try {
            $shift = \App\Models\NursingShift::startShift(Auth::id(), [
                'shift_type' => $request->shift_type ?? \App\Models\NursingShift::determineShiftType(),
                'ward_id' => null, // Billing staff don't have wards
            ]);

            // Set the context to 'billing'
            $shift->update(['context' => 'billing']);

            return response()->json([
                'success' => true,
                'message' => 'Billing shift started successfully',
                'shift' => [
                    'id' => $shift->id,
                    'shift_type' => $shift->shift_type,
                    'shift_type_label' => $shift->shift_type_label,
                    'started_at' => $shift->started_at->format('g:i A'),
                    'scheduled_end_at' => $shift->scheduled_end_at->format('g:i A'),
                    'status' => $shift->status,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to start billing shift: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * End the active billing shift for the current user
     */
    public function endBillingShift(Request $request)
    {
        try {
            $shift = \App\Models\NursingShift::forUser(Auth::id())
                ->active()
                ->forContext('billing')
                ->first();

            if (!$shift) {
                return response()->json(['success' => false, 'message' => 'No active billing shift found'], 404);
            }

            $shift->endShift(false);

            return response()->json([
                'success' => true,
                'message' => 'Billing shift ended successfully',
                'shift' => [
                    'id' => $shift->id,
                    'duration' => $shift->duration,
                    'payments_count' => $shift->payments_count,
                    'total_collected' => number_format($shift->total_collected, 2),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to end billing shift: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get the active billing shift status for the current user
     */
    public function getActiveBillingShift()
    {
        $shift = \App\Models\NursingShift::forUser(Auth::id())
            ->active()
            ->forContext('billing')
            ->first();

        if (!$shift) {
            return response()->json(['active' => false]);
        }

        return response()->json([
            'active' => true,
            'shift' => [
                'id' => $shift->id,
                'shift_type' => $shift->shift_type,
                'shift_type_label' => $shift->shift_type_label,
                'started_at' => $shift->started_at->format('g:i A'),
                'scheduled_end_at' => $shift->scheduled_end_at->format('g:i A'),
                'elapsed_seconds' => $shift->elapsed_seconds,
                'remaining_seconds' => $shift->remaining_seconds,
                'payments_count' => $shift->payments_count,
                'total_collected' => number_format($shift->total_collected, 2),
                'status' => $shift->status,
            ],
        ]);
    }

    /**
     * Get patient's default billing preferences (for auto-selecting payment method)
     */
    public function getPatientBillingDefaults($patientId)
    {
        $patient = Patient::find($patientId);
        if (!$patient || !$patient->default_billing_mode) {
            return response()->json(['has_default' => false]);
        }

        $defaultName = null;
        if ($patient->default_billing_mode === 'BILL_TO_STAFF' && $patient->default_billing_id) {
            $staff = \App\Models\User::find($patient->default_billing_id);
            $defaultName = $staff ? trim($staff->surname . ' ' . $staff->firstname . ' ' . ($staff->othername ?? '')) : null;
        } elseif ($patient->default_billing_mode === 'BILL_TO_ORGANIZATION' && $patient->default_billing_id) {
            $org = \App\Models\Organization::find($patient->default_billing_id);
            $defaultName = $org ? $org->name : null;
        }

        return response()->json([
            'has_default' => true,
            'mode' => $patient->default_billing_mode,
            'billing_id' => $patient->default_billing_id,
            'billing_name' => $defaultName,
        ]);
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
            ->searchByTerm($term)
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
                'photo' => $patient->user && $patient->user->filename ? asset('storage/image/user/' . $patient->user->filename) : asset('assets/images/default-avatar.png'),
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

        $query = \App\Models\BillingQueue::with(['patient.hmo', 'user']);

        // Apply filters
        if ($filter === 'hmo') {
            $query->where('hmo_items_count', '>', 0);
        } elseif ($filter === 'credit') {
            // Filter for patients with credit accounts
            $creditPatientUserIds = PatientAccount::where('balance', '>', 0)
                ->pluck('patient_id');
            $creditUserIds = Patient::whereIn('id', $creditPatientUserIds)->pluck('user_id');
            $query->whereIn('user_id', $creditUserIds);
        }

        $results = $query
            ->orderByDesc('is_emergency')
            ->orderByDesc('latest_item_at')
            ->get();

        $queue = $results->map(function ($item) {
            $patient = $item->patient;

            // Skip if patient record not found
            if (!$patient) {
                return null;
            }

            return [
                'patient_id' => $patient->id,
                'patient_name' => trim($item->user->surname . ' ' . $item->user->firstname . ' ' . $item->user->othername),
                'file_no' => $patient->file_no,
                'unpaid_count' => $item->unpaid_items_count,
                'hmo_items' => $item->hmo_items_count,
                'hmo' => optional($patient->hmo)->name,
                'is_emergency' => $item->is_emergency,
            ];
        })->filter(); // Remove null entries

        return response()->json($queue->values());
    }

    /**
     * Get queue counts for filters
     */
    public function getQueueCounts()
    {
        $unpaidCount = \App\Models\BillingQueue::count();

        $hmoCount = \App\Models\BillingQueue::where('hmo_items_count', '>', 0)->count();

        $creditPatientUserIds = PatientAccount::where('balance', '>', 0)
            ->pluck('patient_id');
        $creditUserIds = Patient::whereIn('id', $creditPatientUserIds)->pluck('user_id');
        $creditCount = \App\Models\BillingQueue::whereIn('user_id', $creditUserIds)->count();

        return response()->json([
            'unpaid' => $unpaidCount,
            'hmo' => $hmoCount,
            'credit' => $creditCount,
            'total' => $unpaidCount,
            'emergency' => $this->getEmergencyBillingCount(),
        ]);
    }

    /**
     * Exclude fully HMO-covered items from billing queue queries.
     *
     * These are items where: payable_amount is 0/NULL, claims_amount > 0,
     * and validation_status = 'approved'. They are fully covered by HMO
     * and should not appear as unpaid items in the billing queue.
     */
    private function excludeFullyHmoCovered($query)
    {
        // Only exclude items that are FULLY covered by HMO (approved status and no patient payable)
        // Self-pay items or items awaiting HMO approval should always be visible in the billing queue.
        return $query->whereRaw(
            'NOT ((payable_amount IS NULL OR payable_amount = 0) AND (claims_amount > 0 AND validation_status = ?))',
            ['approved']
        );
    }

    /**
     * Count patients with unpaid bills who are emergency patients
     */
    private function getEmergencyBillingCount()
    {
        return \App\Models\BillingQueue::where('is_emergency', true)->count();
    }

    /**
     * Get patient's billing data (unpaid items)
     */
    public function getPatientBillingData($patientId)
    {
        $patient = Patient::with('hmo')->findOrFail($patientId);

        $itemsQuery = ProductOrServiceRequest::with([
            'department',
            'service.department',
            'service.category.department',
            'service.price',
            'service.category',
            'product.department',
            'product.category.department',
            'product.price',
            'product.category',
            'user',
            'productRequest.treatmentPlan',
            'labRequest.treatmentPlan',
            'imagingRequest.treatmentPlan',
        ])
            ->whereIn('user_id', $patient->family_user_ids)
            ->whereNull('payment_id')
            ->whereNull('invoice_id')
            ->where('is_bundle_item', false); // Exclude child items from billing list
        // Exclude fully HMO-covered approved items
        $this->excludeFullyHmoCovered($itemsQuery);
        $items = $itemsQuery
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($row) {
                $isFreeForm = (bool) $row->is_free_form;
                $isService = $row->service_id !== null;
                $basePrice = $isFreeForm
                    ? (float) ($row->payable_amount ?? 0)
                    : ($isService
                        ? optional(optional($row->service)->price)->sale_price
                        : optional(optional($row->product)->price)->current_sale_price);

                $storedQty = $row->qty ?? 1;
                $totalPayable = $row->payable_amount !== null ? $row->payable_amount : (($basePrice ?? 0) * $storedQty);
                $unitPrice = $storedQty > 0 ? $totalPayable / $storedQty : $totalPayable;

                // Get treatment plan from associated clinical requests
                $tp = null;
                if ($row->productRequest && $row->productRequest->treatmentPlan) {
                    $tp = $row->productRequest->treatmentPlan;
                } elseif ($row->labRequest && $row->labRequest->treatmentPlan) {
                    $tp = $row->labRequest->treatmentPlan;
                } elseif ($row->imagingRequest && $row->imagingRequest->treatmentPlan) {
                    $tp = $row->imagingRequest->treatmentPlan;
                }

                return [
                    'id' => $row->id,
                    'type' => $isFreeForm ? 'custom' : ($isService ? 'service' : 'product'),
                    'name' => getItemDisplayName($row),
                    'code' => $isFreeForm ? 'CUSTOM' : ($isService ? optional($row->service)->service_code : optional($row->product)->product_code),
                    'category' => $isFreeForm ? 'Custom Items' : ($isService ? optional(optional($row->service)->category)->category_name : optional(optional($row->product)->category)->category_name),
                    'department_id' => $row->getEffectiveDepartmentId(),
                    'department_name' => $row->getEffectiveDepartmentName(),
                    'qty' => $storedQty,
                    'price' => $unitPrice,
                    'unit_price' => $unitPrice,
                    'base_price' => $basePrice ?? 0,
                    'payable_amount' => $row->payable_amount,
                    'claims_amount' => $row->claims_amount,
                    'coverage_mode' => $row->coverage_mode,
                    'validation_status' => $row->validation_status,
                    'discount' => $row->discount ?? 0,
                    'created_at' => $row->created_at ? $row->created_at->toIso8601String() : null,
                    'created_at_formatted' => $row->created_at ? $row->created_at->format('d/m/Y H:i') : 'N/A',
                    'user_id' => $row->user_id,
                    'patient_name' => optional($row->user)->firstname . ' ' . optional($row->user)->surname,
                    'treatment_plan_id' => ($tp && $tp->isAccessibleBy(Auth::user(), 'billing')) ? $tp->id : null,
                    'treatment_plan_name' => ($tp && $tp->isAccessibleBy(Auth::user(), 'billing')) ? $tp->name : null,
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
                'dob' => $patient->dob ? Carbon::parse($patient->dob)->format('Y-m-d') : '',
                'blood_group' => $patient->blood_group ?? 'N/A',
                'genotype' => $patient->genotype ?? 'N/A',
                'phone' => $patient->phone_no ?? 'N/A',
                'address' => $patient->address ?? 'N/A',
                'nationality' => $patient->nationality ?? 'N/A',
                'ethnicity' => $patient->ethnicity ?? 'N/A',
                'disability' => $patient->disability == 1 ? 'Yes' : 'No',
                'next_of_kin_name' => $patient->next_of_kin_name ?? '',
                'next_of_kin_phone' => $patient->next_of_kin_phone ?? '',
                'next_of_kin_address' => $patient->next_of_kin_address ?? '',
                'allergies' => $patient->allergies ?? [],
                'medical_history' => $patient->medical_history ?? 'N/A',
                'misc' => $patient->misc ?? 'N/A',
                'registration_workbench' => $patient->registration_workbench ?? 'reception',
                'registration_workbench_label' => $patient->registration_workbench_label,
                'registered_on' => $patient->registered_on,
                'registered_by' => $patient->registered_by,
                'registered_by_name' => $patient->registered_by_name,
                'can_edit' => $patient->can_edit,
                'hmo_name' => optional($patient->hmo)->name,
                'hmo_no' => $patient->hmo_no,
                'photo' => $patient->user->photo ?? 'avatar.png',
                'family_members' => \App\Models\Patient::whereIn('user_id', $patient->family_user_ids)->get()->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'user_id' => $p->user_id,
                        'name' => userfullname($p->user_id),
                        'file_no' => $p->file_no,
                        'is_principal' => $p->is_family_principal,
                    ];
                }),
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
        $patient = Patient::findOrFail($patientId);
        $receipts = collect();

        // Get filter values
        $fromDate = $request->from_date;
        $toDate = $request->to_date;
        $paymentTypeFilter = $request->payment_type;

        // 1. Get payments from payment table
        $paymentQuery = Payment::with(['splits.bank', 'bank'])->where('patient_id', $patientId);

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
                $items = ProductOrServiceRequest::with([
                    'department',
                    'service.department',
                    'service.category.department',
                    'product.department',
                    'product.category.department',
                    'service',
                    'product',
                ])
                    ->where('payment_id', $payment->id)
                    ->get();

                $departments = $items->map(function ($item) {
                    return $item->getEffectiveDepartmentName();
                })->unique()->filter()->values()->all();

                $receipts->push([
                    'id' => 'pay_' . $payment->id,
                    'source' => 'payment',
                    'payment_id' => $payment->id,
                    'reference_no' => $payment->reference_no,
                    'payment_type' => $payment->payment_type,
                    'payment_type_label' => formatPaymentMethod($payment),
                    'total' => $payment->total,
                    'total_discount' => $payment->total_discount ?? 0,
                    'created_at' => $payment->created_at->format('Y-m-d H:i'),
                    'datetime' => $payment->created_at,
                    'created_by' => userfullname($payment->user_id),
                    'item_count' => $items->count(),
                    'departments' => $departments,
                    'department_summary' => !empty($departments) ? implode(', ', $departments) : 'General',
                    'is_voided' => (bool) $payment->is_voided,
                    'voided_at' => $payment->voided_at ? $payment->voided_at->format('Y-m-d H:i') : null,
                    'void_reason' => $payment->void_reason,
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
                    'is_voided' => false,
                    'voided_at' => null,
                    'void_reason' => null,
                ]);
            }
        }

        // Sort by datetime descending
        $receipts = $receipts->sortByDesc('datetime')->values();

        // Calculate stats (excluding voided)
        $activeReceipts = $receipts->where('is_voided', false);
        $stats = [
            'count' => $activeReceipts->count(),
            'total' => $activeReceipts->sum('total'),
            'discounts' => $activeReceipts->sum('total_discount'),
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
        $patient = Patient::findOrFail($patientId);

        $query = Payment::where('patient_id', $patientId);

        // Apply filters
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay(),
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
        $patient = Patient::findOrFail($patientId);
        $billingPatientId = $patient->billing_patient_id;
        $account = PatientAccount::where('patient_id', $billingPatientId)->first();

        // Apply date filters - default to current month if not provided
        $fromDate = $request->from_date ?: now()->startOfMonth()->format('Y-m-d');
        $toDate = $request->to_date ?: now()->format('Y-m-d');
        $txTypeFilter = $request->tx_type ?: null;

        $transactionsFormatted = collect();

        // 1. Get deposits from patient_deposits table (Accounts module deposits)
        if (!$txTypeFilter || $txTypeFilter === 'ACC_DEPOSIT') {
            $deposits = PatientDeposit::where('patient_id', $billingPatientId)
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
        $paymentQuery = Payment::where('patient_id', $billingPatientId)
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
        $totalDeposits = PatientDeposit::where('patient_id', $billingPatientId)->sum('amount');

        // Withdrawals from payments (stored as negative values)
        $totalWithdrawals = abs(Payment::where('patient_id', $billingPatientId)
            ->where('payment_type', 'ACC_WITHDRAW')
            ->sum('total'));

        return response()->json([
            'transactions' => $transactionsFormatted,
            'summary' => [
                'total_deposits' => $totalDeposits,
                'total_withdrawals' => $totalWithdrawals,
                'current_balance' => $account ? $account->balance : 0,
                'transaction_count' => count($transactionsFormatted),
            ],
        ]);
    }

    /**
     * Get patient's account summary
     */
    public function getPatientAccountSummary($patientId)
    {
        $patient = Patient::with('hmo')->findOrFail($patientId);
        $billingPatientId = $patient->billing_patient_id;

        $account = PatientAccount::where('patient_id', $billingPatientId)->first();
        $balance = $account ? $account->balance : 0;

        $totalPaid = Payment::where('patient_id', $billingPatientId)->sum('total');

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
            'staff_user_id' => 'nullable|required_if:payment_method,BILL_TO_STAFF|required_if:payment_type,BILL_TO_STAFF|integer|exists:users,id',
            'organization_id' => 'nullable|required_if:payment_method,BILL_TO_ORGANIZATION|required_if:payment_type,BILL_TO_ORGANIZATION|integer|exists:organizations,id',
            'reference_no' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer',
            'items.*.qty' => 'required|numeric|min:1',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'is_split_payment' => 'nullable|boolean',
            'splits' => 'nullable|array',
            'splits.*.payment_method' => 'required_with:splits|string',
            'splits.*.bank_id' => 'nullable|integer|exists:banks,id',
            'splits.*.amount' => 'required_with:splits|numeric|min:0.01',
            'splits.*.reference_no' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $patient = Patient::with('hmo')->findOrFail($data['patient_id']);

            $ids = collect($data['items'])->pluck('id')->all();

            $rows = ProductOrServiceRequest::with(['service.price', 'product.price', 'user', 'productRequest'])
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
                if (!$row || !in_array($row->user_id, $patient->family_user_ids)) {
                    throw new \Exception('Item does not belong to patient or family, or is missing.');
                }
                if ($row->payment_id !== null || $row->invoice_id !== null) {
                    throw new \Exception('One of the items is already paid or invoiced.');
                }

                $isFreeForm = (bool) $row->is_free_form;
                $isService = $row->service_id !== null;
                $basePrice = $isFreeForm
                    ? (float) ($row->payable_amount ?? 0)
                    : ($isService
                        ? optional(optional($row->service)->price)->sale_price
                        : optional(optional($row->product)->price)->current_sale_price);

                $storedQty = $row->qty ?? 1;
                $storedPayable = $row->payable_amount !== null ? $row->payable_amount : (($basePrice ?? 0) * $storedQty);
                $unitPrice = $storedQty > 0 ? $storedPayable / $storedQty : $storedPayable;

                $qty = $itemPayload['qty'];
                $discountPercent = isset($itemPayload['discount']) ? $itemPayload['discount'] : 0;
                $discountAmount = ($unitPrice * $qty) * ($discountPercent / 100);
                $lineTotal = ($unitPrice * $qty) - $discountAmount;

                $total += $lineTotal;
                $totalDiscount += $discountAmount;

                // Persist qty/discount to request row
                $row->qty = $qty;
                $row->discount = $discountPercent;
                $row->save();

                if ($row->claims_amount > 0) {
                    $unitClaims = $storedQty > 0 ? ($row->claims_amount / $storedQty) : $row->claims_amount;
                    $claimsTotal += $unitClaims * $qty;
                }

                $receiptDetails[] = [
                    'type' => $isFreeForm ? 'Custom' : ($isService ? 'Service' : 'Product'),
                    'name' => getItemDisplayName($row),
                    'patient_name' => userfullname($row->user_id),
                    'patient_user_id' => $row->user_id,
                    'price' => $unitPrice,
                    'qty' => $qty,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'amount_paid' => $lineTotal,
                    'dose' => (!$isService && !$isFreeForm && $row->productRequest) ? $row->productRequest->dose : null,
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
                $billingPatientId = $patient->billing_patient_id;
                $account = PatientAccount::where('patient_id', $billingPatientId)->first();

                // Create account if it doesn't exist
                if (!$account) {
                    $account = PatientAccount::create([
                        'patient_id' => $billingPatientId,
                        'balance' => 0,
                    ]);
                }

                // Deduct from account (can go negative - credit facility / overdraw)
                $account->balance -= $total;
                $account->save();

                // FIFO: Get all active deposits ordered by date (oldest first)
                // We'll apply the payment against deposits until the amount is covered
                $activeDeposits = PatientDeposit::where('patient_id', $billingPatientId)
                    ->where('status', 'active')
                    ->orderBy('deposit_date', 'asc') // FIFO - oldest first
                    ->orderBy('id', 'asc')
                    ->get();

                $remainingToApply = $total;

                foreach ($activeDeposits as $deposit) {
                    if ($remainingToApply <= 0) {
                        break;
                    }

                    // Calculate available balance for this deposit
                    $availableBalance = $deposit->balance; // Uses the accessor

                    if ($availableBalance <= 0) {
                        continue;
                    }

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

            // Link payment to active billing shift (if billing shift is active for this cashier)
            $activeShift = \App\Models\NursingShift::forUser(Auth::id())
                ->active()
                ->forContext('billing')
                ->first();

            $isSplit = !empty($data['is_split_payment']);
            if ($isSplit) {
                if (empty($data['splits']) || count($data['splits']) < 2) {
                    throw new \Exception('Split payments must have at least two payment method splits.');
                }
                $splitSum = collect($data['splits'])->sum(fn ($s) => (float) ($s['amount'] ?? 0));
                if (abs($splitSum - $total) > 0.05) {
                    throw new \Exception('Split sum (₦' . number_format($splitSum, 2) . ') does not equal total amount (₦' . number_format($total, 2) . ').');
                }

                $accountLeg = collect($data['splits'])->firstWhere('payment_method', 'ACCOUNT');
                if ($accountLeg) {
                    $legAmount = (float) ($accountLeg['amount'] ?? 0);
                    if ($legAmount > 0) {
                        $billingPatientId = $patient->billing_patient_id;
                        $account = PatientAccount::firstOrCreate(['patient_id' => $billingPatientId], ['balance' => 0]);
                        $account->balance -= $legAmount;
                        $account->save();

                        $activeDeposits = PatientDeposit::where('patient_id', $billingPatientId)
                            ->where('status', 'active')
                            ->orderBy('deposit_date', 'asc')
                            ->orderBy('id', 'asc')
                            ->get();

                        $remainingToApply = $legAmount;
                        foreach ($activeDeposits as $deposit) {
                            if ($remainingToApply <= 0) {
                                break;
                            }
                            $availableBalance = $deposit->available_balance;
                            if ($availableBalance <= 0) {
                                continue;
                            }
                            $applyAmount = min($availableBalance, $remainingToApply);
                            $depositsToApply->push([
                                'deposit' => $deposit,
                                'amount' => $applyAmount,
                            ]);
                            $remainingToApply -= $applyAmount;
                        }
                    }
                }
            }

            // Create payment entry
            $payment = new Payment([
                'payment_type' => $paymentType,
                'payment_method' => $isSplit ? 'SPLIT' : ($data['payment_method'] ?? $data['payment_type']),
                'bank_id' => $isSplit ? null : ($data['bank_id'] ?? null),
                'total' => $paymentTotal,
                'total_discount' => $totalDiscount,
                'reference_no' => $data['reference_no'] ?? ('REF-' . date('YmdHis') . '-' . rand(100, 999)),
                'user_id' => Auth::id(),
                'patient_id' => $patient->id,
                'shift_id' => $activeShift?->id,
                'is_split_payment' => $isSplit,
            ]);

            if ($isSplit && !empty($data['splits'])) {
                $payment->pending_splits = collect($data['splits']);
            }

            $payment->save();

            if ($isSplit && !empty($data['splits'])) {
                foreach ($data['splits'] as $splitData) {
                    PaymentSplit::create([
                        'payment_id' => $payment->id,
                        'payment_method' => $splitData['payment_method'],
                        'bank_id' => $splitData['bank_id'] ?? null,
                        'amount' => $splitData['amount'],
                        'reference_no' => $splitData['reference_no'] ?? null,
                    ]);
                }
            }

            // Update shift counters if we have an active billing shift
            if ($activeShift) {
                $activeShift->increment('payments_count');
                $activeShift->increment('total_collected', abs($paymentTotal));
            }

            $effectiveMethod = $data['payment_method'] ?? $data['payment_type'] ?? null;

            // Create Staff Bill if payment method is BILL_TO_STAFF
            if ($effectiveMethod === 'BILL_TO_STAFF') {
                $staffUserId = $data['staff_user_id'] ?? $request->input('staff_user_id');
                if (!$staffUserId) {
                    throw new \Exception('Please select a valid staff member to bill.');
                }
                \App\Models\StaffBill::create([
                    'patient_id' => $patient->id,
                    'staff_user_id' => $staffUserId,
                    'payment_id' => $payment->id,
                    'total_amount' => $total,
                    'discount_amount' => $totalDiscount,
                    'outstanding_amount' => $total,
                    'status' => 'pending_audit',
                ]);
            }

            // Create Organization Bill if payment method is BILL_TO_ORGANIZATION
            if ($effectiveMethod === 'BILL_TO_ORGANIZATION') {
                $organizationId = $data['organization_id'] ?? $request->input('organization_id');
                if (!$organizationId) {
                    throw new \Exception('Please select a valid organization to bill.');
                }
                \App\Models\OrganizationBill::create([
                    'patient_id' => $patient->id,
                    'organization_id' => $organizationId,
                    'payment_id' => $payment->id,
                    'total_amount' => $total,
                    'discount_amount' => $totalDiscount,
                    'outstanding_amount' => $total,
                    'status' => 'pending_audit',
                ]);
            }

            // Mark items as paid — covers primary patient AND all family members
            ProductOrServiceRequest::whereIn('id', $ids)
                ->whereIn('user_id', $patient->family_user_ids)
                ->whereNull('invoice_id')
                ->update(['payment_id' => $payment->id]);

            // Auto-advance associated clinical requests past billing stage directly to execution
            $staffId = Auth::id() ?? $payment->user_id;
            $now = now();

            \App\Models\LabServiceRequest::whereIn('service_request_id', $ids)
                ->where('status', 1)
                ->update([
                    'status' => 2,
                    'billed_by' => $staffId,
                    'billed_date' => $now,
                    'self_perform_intent' => true,
                ]);

            \App\Models\ImagingServiceRequest::whereIn('service_request_id', $ids)
                ->where('status', 1)
                ->update([
                    'status' => 2,
                    'billed_by' => $staffId,
                    'billed_date' => $now,
                    'self_perform_intent' => true,
                ]);

            \App\Models\ProductRequest::whereIn('product_request_id', $ids)
                ->where('status', 1)
                ->update([
                    'status' => 2,
                    'billed_by' => $staffId,
                    'billed_date' => $now,
                ]);

            \App\Models\Procedure::whereIn('product_or_service_request_id', $ids)
                ->whereNull('billed_on')
                ->update([
                    'billed_by' => $staffId,
                    'billed_on' => $now,
                ]);

            // Create deposit application entries if paying from account (FIFO)
            // Only create applications for the portion covered by actual deposits
            // Overdraw amounts don't get application entries (they're credit facility usage)
            if (($payingFromAccount || $isSplit) && $depositsToApply->isNotEmpty()) {
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
            $ref = $data['reference_no'] ?? ($payment->reference_no ?? 'REF-' . date('YmdHis'));

            // Detect if this is a cross-family payment (items for multiple patients)
            $uniquePatientUserIds = collect($receiptDetails)->pluck('patient_user_id')->unique();
            $isFamilyPayment = $uniquePatientUserIds->count() > 1;
            $familyPatientNames = $isFamilyPayment
                ? \App\Models\Patient::whereIn('user_id', $uniquePatientUserIds->all())
                ->get()->map(fn ($p) => userfullname($p->user_id) . ' (' . $p->file_no . ')')
                ->join(', ')
                : null;

            $amountParts = explode('.', number_format((float) $total, 2, '.', ''));
            $nairaWords = convert_number_to_words((int) $amountParts[0]);
            $koboWords = ((int) $amountParts[1]) > 0 ? ' and ' . convert_number_to_words((int) $amountParts[1]) . ' Kobo' : '';
            $amountInWords = ucwords($nairaWords . ' Naira' . $koboWords);

            $a4 = View::make('admin.Accounts.receipt_a4', [
                'site' => $site,
                'patientName' => $patientName,
                'patientFileNo' => $patientFileNo,
                'isFamilyPayment' => $isFamilyPayment,
                'familyPatientNames' => $familyPatientNames,
                'date' => $date,
                'ref' => $ref,
                'receiptDetails' => $receiptDetails,
                'totalDiscount' => $totalDiscount,
                'totalPaid' => $total,
                'amountInWords' => $amountInWords,
                'paymentType' => $isSplit ? 'SPLIT' : $data['payment_type'],
                'payment' => $payment,
                'isSplitPayment' => $isSplit,
                'splits' => $payment->splits,
                'notes' => '',
                'currentUserName' => $currentUserName,
            ])->render();

            $thermal = View::make('admin.Accounts.receipt_thermal', [
                'site' => $site,
                'patientName' => $patientName,
                'patientFileNo' => $patientFileNo,
                'isFamilyPayment' => $isFamilyPayment,
                'familyPatientNames' => $familyPatientNames,
                'date' => $date,
                'ref' => $ref,
                'receiptDetails' => $receiptDetails,
                'totalDiscount' => $totalDiscount,
                'totalPaid' => $total,
                'amountInWords' => $amountInWords,
                'paymentType' => $isSplit ? 'SPLIT' : $data['payment_type'],
                'payment' => $payment,
                'isSplitPayment' => $isSplit,
                'splits' => $payment->splits,
                'notes' => '',
                'currentUserName' => $currentUserName,
                'thermalWidth' => getThermalPrinterWidth(),
            ])->render();

            DB::commit();

            return response()->json([
                'success' => true,
                'payment_id' => $payment->id,
                'total' => $total,
                'total_discount' => $totalDiscount,
                'receipt_a4' => $a4,
                'receipt_thermal' => $thermal,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Billing workbench payment failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Print receipt for selected payment(s)
     */
    public function printReceipt(Request $request)
    {
        if ($request->filled('payment_id') && !$request->filled('payment_ids')) {
            $request->merge(['payment_ids' => [(int) $request->payment_id]]);
        }
        if (!$request->filled('patient_id') && $request->filled('payment_ids')) {
            $firstPayment = Payment::find($request->payment_ids[0] ?? null);
            if ($firstPayment && $firstPayment->patient_id) {
                $request->merge(['patient_id' => $firstPayment->patient_id]);
            }
        }

        $request->validate([
            'patient_id' => 'nullable|integer',
            'payment_ids' => 'required|array|min:1',
            'payment_ids.*' => 'integer|exists:payments,id',
        ]);

        $patient = $request->patient_id ? Patient::find($request->patient_id) : null;
        $payments = Payment::whereIn('id', $request->payment_ids)->get();

        // Aggregate items from selected payments
        $allItems = ProductOrServiceRequest::whereIn('payment_id', $request->payment_ids)
            ->where('is_bundle_item', false) // Exclude child items from receipt
            ->with(['service.price', 'product.price', 'productRequest'])
            ->get();

        $receiptDetails = [];
        $totalAmount = 0;
        $totalDiscount = 0;

        foreach ($allItems as $row) {
            $isFreeForm = (bool) $row->is_free_form;
            $isService = $row->service_id !== null;
            $basePrice = $isFreeForm
                ? (float) ($row->payable_amount ?? 0)
                : ($isService
                    ? optional(optional($row->service)->price)->sale_price
                    : optional(optional($row->product)->price)->current_sale_price);
            $qty = $row->qty ?? 1;
            $storedPayable = $row->payable_amount !== null ? $row->payable_amount : (($basePrice ?? 0) * $qty);
            $unitPrice = $qty > 0 ? $storedPayable / $qty : $storedPayable;
            $discountPercent = $row->discount ?? 0;
            $discountAmount = ($unitPrice * $qty) * ($discountPercent / 100);
            $lineTotal = ($unitPrice * $qty) - $discountAmount;

            $totalAmount += $lineTotal;
            $totalDiscount += $discountAmount;

            $receiptDetails[] = [
                'type' => $isFreeForm ? 'Custom' : ($isService ? 'Service' : 'Product'),
                'name' => getItemDisplayName($row),
                'patient_name' => userfullname($row->user_id),
                'patient_user_id' => $row->user_id,
                'price' => $unitPrice,
                'qty' => $qty,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'amount_paid' => $lineTotal,
                'dose' => (!$isService && !$isFreeForm && $row->productRequest) ? $row->productRequest->dose : null,
            ];
        }

        $site = appsettings();
        $patientName = $patient ? userfullname($patient->user_id) : ($firstPayment?->patient_name ?? ($firstPayment?->user_id ? userfullname($firstPayment->user_id) : 'Walk-in Customer'));
        $patientFileNo = $patient?->file_no ?? 'WALK-IN';
        $currentUserName = Auth::user() ? userfullname(Auth::id()) : '';
        $date = now()->format('Y-m-d H:i');
        $firstPayment = $payments->first();
        $ref = $firstPayment->reference_no ?? 'COMBINED';

        // Detect if this is a cross-family payment (items for multiple patients)
        $uniquePatientUserIds = collect($receiptDetails)->pluck('patient_user_id')->unique();
        $isFamilyPayment = $uniquePatientUserIds->count() > 1;
        $familyPatientNames = $isFamilyPayment
            ? \App\Models\Patient::whereIn('user_id', $uniquePatientUserIds->all())
            ->get()->map(fn ($p) => userfullname($p->user_id) . ' (' . $p->file_no . ')')
            ->join(', ')
            : null;

        $amountParts = explode('.', number_format((float) $totalAmount, 2, '.', ''));
        $nairaWords = convert_number_to_words((int) $amountParts[0]);
        $koboWords = ((int) $amountParts[1]) > 0 ? ' and ' . convert_number_to_words((int) $amountParts[1]) . ' Kobo' : '';
        $amountInWords = ucwords($nairaWords . ' Naira' . $koboWords);

        $paymentType = $firstPayment ? formatPaymentMethod($firstPayment) : 'N/A';
        $isSplit = (bool) ($firstPayment?->is_split_payment);

        $a4 = View::make('admin.Accounts.receipt_a4', [
            'site' => $site,
            'patientName' => $patientName,
            'patientFileNo' => $patientFileNo,
            'isFamilyPayment' => $isFamilyPayment,
            'familyPatientNames' => $familyPatientNames,
            'date' => $date,
            'ref' => $ref,
            'receiptDetails' => $receiptDetails,
            'totalDiscount' => $totalDiscount,
            'totalPaid' => $totalAmount,
            'amountInWords' => $amountInWords,
            'paymentType' => $paymentType,
            'payment' => $firstPayment,
            'isSplitPayment' => $isSplit,
            'splits' => $firstPayment?->splits,
            'notes' => '',
            'currentUserName' => $currentUserName,
        ])->render();

        $thermal = View::make('admin.Accounts.receipt_thermal', [
            'site' => $site,
            'patientName' => $patientName,
            'patientFileNo' => $patientFileNo,
            'isFamilyPayment' => $isFamilyPayment,
            'familyPatientNames' => $familyPatientNames,
            'date' => $date,
            'ref' => $ref,
            'receiptDetails' => $receiptDetails,
            'totalDiscount' => $totalDiscount,
            'totalPaid' => $totalAmount,
            'amountInWords' => $amountInWords,
            'paymentType' => $paymentType,
            'payment' => $firstPayment,
            'isSplitPayment' => $isSplit,
            'splits' => $firstPayment?->splits,
            'notes' => '',
            'currentUserName' => $currentUserName,
            'thermalWidth' => getThermalPrinterWidth(),
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

        $patient = Patient::with('hmo')->findOrFail($request->patient_id);

        // Get the unpaid items
        $items = ProductOrServiceRequest::whereIn('id', $request->item_ids)
            ->whereNull('payment_id')
            ->whereNull('invoice_id')
            ->where('is_bundle_item', false) // Exclude child items from invoice
            ->with(['service.price', 'product.price', 'productRequest'])
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
            $qty = $row->qty ?? 1;
            $storedPayable = $row->payable_amount !== null ? $row->payable_amount : (($basePrice ?? 0) * $qty);
            $unitPrice = $qty > 0 ? $storedPayable / $qty : $storedPayable;
            $discountPercent = $row->discount ?? 0;
            $subtotal = $unitPrice * $qty;
            $discountAmount = $subtotal * ($discountPercent / 100);
            $lineTotal = $subtotal - $discountAmount;
            $hmoCoverage = $row->claims_amount ?? 0;

            $totalAmount += $lineTotal;
            $totalDiscount += $discountAmount;
            $totalHmoCoverage += $hmoCoverage;

            $invoiceDetails[] = [
                'type' => $isService ? 'Service' : 'Product',
                'name' => $isService ? optional($row->service)->service_name : optional($row->product)->product_name,
                'price' => $unitPrice,
                'qty' => $qty,
                'discount_percent' => $discountPercent,
                'discount_amount' => $discountAmount,
                'hmo_coverage' => $hmoCoverage,
                'amount' => $lineTotal,
                'date' => $row->created_at ? $row->created_at->format('d/m/Y H:i') : 'N/A',
                'dose' => (!$isService && $row->productRequest) ? $row->productRequest->dose : null,
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
            'thermalWidth' => getThermalPrinterWidth(),
        ])->render();

        return response()->json([
            'invoice_a4' => $a4,
            'invoice_thermal' => $thermal,
            'invoice_no' => $invoiceNo,
        ]);
    }

    /**
     * Get current user's transactions (for report modal) with line items, category KPIs, and filters
     */
    public function getMyTransactions(Request $request)
    {
        $query = Payment::with([
            'patient.user',
            'bank',
            'splits.bank',
            'product_or_service_request' => function ($q) {
                $q->with([
                    'service.category.department',
                    'service.department',
                    'product.category.department',
                    'product.department',
                    'department',
                    'user',
                ]);
            },
        ]);

        $isAuditUser = $request->boolean('is_audit')
            || (Auth::check() && (
                Auth::user()->hasAnyRole(['SUPERADMIN', 'ADMIN', 'ACCOUNTS', 'INTERNAL AUDITOR', 'AUDITOR'])
                || Auth::user()->can('view-audit')
            ));

        $cashierId = $request->input('cashier_id');
        $staffName = 'All Cashiers';

        if ($isAuditUser) {
            if ($request->filled('cashier_id') && $cashierId !== 'all') {
                $query->where('payments.user_id', $cashierId);
                $cashierUser = User::find($cashierId);
                $staffName = $cashierUser
                    ? ($cashierUser->surname . ' ' . $cashierUser->firstname . ($cashierUser->othername ? ' ' . $cashierUser->othername : ''))
                    : ('Cashier #' . $cashierId);
            } else {
                $staffName = 'All Cashiers (Facility-Wide)';
            }
        } else {
            $query->where('payments.user_id', Auth::id());
            $staffUser = Auth::user();
            $staffName = $staffUser
                ? ($staffUser->surname . ' ' . $staffUser->firstname . ($staffUser->othername ? ' ' . $staffUser->othername : ''))
                : 'Current Staff';
        }

        // Date filtering - default to today, preserving time if provided (e.g. datetime-local format)
        $from = $request->input('from', $request->input('from_date', now()->format('Y-m-d 00:00:00')));
        $to = $request->input('to', $request->input('to_date', now()->format('Y-m-d 23:59:59')));

        $fromCarbon = (str_contains($from, ':') || str_contains($from, 'T'))
            ? Carbon::parse(str_replace('T', ' ', $from))
            : Carbon::parse($from)->startOfDay();

        $toCarbon = (str_contains($to, ':') || str_contains($to, 'T'))
            ? Carbon::parse(str_replace('T', ' ', $to))
            : Carbon::parse($to)->endOfDay();

        $query->whereBetween('payments.created_at', [$fromCarbon, $toCarbon]);

        // Payment method / type filter
        if ($request->filled('payment_method')) {
            $pm = $request->payment_method;
            if ($pm === 'SPLIT') {
                $query->where('payments.is_split_payment', true);
            } elseif ($pm === 'REFUND') {
                $query->where(function ($q) {
                    $q->where('payments.total', '<', 0)
                        ->orWhereIn('payments.payment_type', ['ACC_WITHDRAW', 'REFUND'])
                        ->orWhere('payments.payment_method', 'REFUND');
                });
            } else {
                $query->where(function ($q) use ($pm) {
                    $q->where('payments.payment_method', $pm)
                        ->orWhere('payments.payment_type', $pm)
                        ->orWhereHas('splits', fn ($sq) => $sq->where('payment_splits.payment_method', $pm));
                });
            }
        } elseif ($request->filled('payment_type')) {
            $pt = $request->payment_type;
            if ($pt === 'SPLIT') {
                $query->where('payments.is_split_payment', true);
            } elseif ($pt === 'REFUND') {
                $query->where(function ($q) {
                    $q->where('payments.total', '<', 0)
                        ->orWhereIn('payments.payment_type', ['ACC_WITHDRAW', 'REFUND'])
                        ->orWhere('payments.payment_method', 'REFUND');
                });
            } else {
                $query->where(function ($q) use ($pt) {
                    $q->where('payments.payment_type', $pt)
                        ->orWhere('payments.payment_method', $pt)
                        ->orWhereHas('splits', fn ($sq) => $sq->where('payment_splits.payment_method', $pt));
                });
            }
        }

        // Bank filter
        if ($request->filled('bank_id')) {
            $bankId = $request->bank_id;
            $query->where(function ($q) use ($bankId) {
                $q->where('payments.bank_id', $bankId)
                    ->orWhereHas('splits', fn ($sq) => $sq->where('payment_splits.bank_id', $bankId));
            });
        }

        // Department filter - pre-resolve matching service and product IDs to eliminate nested subqueries
        if ($request->filled('department_id')) {
            $deptId = $request->department_id;
            $serviceCatIds = ServiceCategory::where('department_id', $deptId)->pluck('id');
            $serviceIds = Service::where('department_id', $deptId)
                ->when($serviceCatIds->isNotEmpty(), fn ($q) => $q->orWhereIn('category_id', $serviceCatIds))
                ->pluck('id');

            $productCatIds = ProductCategory::where('department_id', $deptId)->pluck('id');
            $productIds = Product::where('department_id', $deptId)
                ->when($productCatIds->isNotEmpty(), fn ($q) => $q->orWhereIn('category_id', $productCatIds))
                ->pluck('id');

            $query->whereHas('product_or_service_request', function ($q) use ($deptId, $serviceIds, $productIds) {
                $q->where('department_id', $deptId)
                    ->when($serviceIds->isNotEmpty(), fn ($sq) => $sq->orWhereIn('service_id', $serviceIds))
                    ->when($productIds->isNotEmpty(), fn ($pq) => $pq->orWhereIn('product_id', $productIds));
            });
        }

        // Item type filter: 'product', 'service', or 'custom'
        if ($request->filled('item_type')) {
            $itemType = strtolower($request->item_type);
            if ($itemType === 'product') {
                $query->whereHas('product_or_service_request', fn ($q) => $q->whereNotNull('product_id'));
            } elseif ($itemType === 'service') {
                $query->whereHas('product_or_service_request', fn ($q) => $q->whereNotNull('service_id'));
            } elseif ($itemType === 'custom') {
                $query->whereHas('product_or_service_request', fn ($q) => $q->where('is_free_form', true));
            }
        }

        // Product category filter
        if ($request->filled('product_category_id')) {
            $catId = $request->product_category_id;
            $prodIds = Product::where('category_id', $catId)->orWhere('product_category_id', $catId)->pluck('id');
            $query->whereHas('product_or_service_request', fn ($q) => $q->whereIn('product_id', $prodIds));
        }

        // Service category filter
        if ($request->filled('service_category_id')) {
            $catId = $request->service_category_id;
            $srvIds = Service::where('category_id', $catId)->pluck('id');
            $query->whereHas('product_or_service_request', fn ($q) => $q->whereIn('service_id', $srvIds));
        }

        // Category filter by category_name (e.g. "Consultation", "Tablets", "Custom Items")
        if ($request->filled('category')) {
            $catFilterName = $request->category;
            if ($catFilterName === 'Custom Items') {
                $query->whereHas('product_or_service_request', fn ($q) => $q->where('is_free_form', true));
            } else {
                $scatIds = ServiceCategory::where('category_name', $catFilterName)->pluck('id');
                $srvIds = Service::whereIn('category_id', $scatIds)->pluck('id');
                $pcatIds = ProductCategory::where('category_name', $catFilterName)->pluck('id');
                $prodIds = Product::whereIn('category_id', $pcatIds)->pluck('id');

                $query->whereHas('product_or_service_request', function ($q) use ($srvIds, $prodIds) {
                    $q->when($srvIds->isNotEmpty(), fn ($sq) => $sq->whereIn('service_id', $srvIds))
                        ->when($prodIds->isNotEmpty(), fn ($pq) => $pq->orWhereIn('product_id', $prodIds));
                });
            }
        }

        // Specific product filter
        if ($request->filled('product_id')) {
            $prodId = $request->product_id;
            $query->whereHas('product_or_service_request', fn ($q) => $q->where('product_id', $prodId));
        }

        // Specific service filter
        if ($request->filled('service_id')) {
            $srvId = $request->service_id;
            $query->whereHas('product_or_service_request', fn ($q) => $q->where('service_id', $srvId));
        }

        // Catalog lookups for category dropdowns and department filter pills
        $allServiceCats = \App\Models\ServiceCategory::where('status', 1)->orderBy('category_name')->pluck('category_name')->all();
        $allProductCats = \App\Models\ProductCategory::where('status', 1)->orderBy('category_name')->pluck('category_name')->all();
        $allDepartments = \App\Models\Department::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code'])->toArray();

        // If request is from Yajra DataTables (server-side pagination, sorting & search)
        if ($request->has('draw')) {
            $summary = $this->calculateMyTransactionsSqlSummary($query, $allServiceCats, $allProductCats, $allDepartments);

            return datatables()->of($query)
                ->addIndexColumn()
                ->editColumn('created_at', function ($p) {
                    return $p->created_at ? $p->created_at->format('Y-m-d H:i') : '-';
                })
                ->addColumn('reference_badge', function ($p) {
                    $void = $p->voided_at ? ' <span class="badge bg-danger text-white font-10 ms-1">VOIDED</span>' : '';

                    return '<span class="fw-bold font-11">' . e($p->reference_no ?: '-') . '</span>' . $void;
                })
                ->addColumn('patient_info', function ($p) {
                    $patientUser = $p->patient?->user;
                    $name = $patientUser
                        ? ($patientUser->surname . ' ' . $patientUser->firstname . ($patientUser->othername ? ' ' . $patientUser->othername : ''))
                        : ($p->patient ? userfullname($p->patient->user_id) : 'Unknown');
                    $fileNo = $p->patient?->file_no ?? 'N/A';

                    return '<div class="fw-bold font-11">' . e($name) . '</div><small class="text-muted font-10">File: ' . e($fileNo) . '</small>';
                })
                ->addColumn('payment_method_badge', function ($p) {
                    if ((float) $p->total < 0 || in_array($p->payment_type, ['ACC_WITHDRAW', 'REFUND']) || $p->payment_method === 'REFUND') {
                        $badgeLabel = ($p->payment_type === 'ACC_WITHDRAW') ? 'DEPOSIT WITHDRAWAL' : 'REFUND / PAYOUT';

                        return '<span class="badge bg-danger text-white font-11"><i class="mdi mdi-cash-refund me-1"></i>' . $badgeLabel . '</span>';
                    }

                    $display = formatPaymentMethod($p);
                    if ($p->is_split_payment && $p->splits && $p->splits->isNotEmpty()) {
                        $splitLines = $p->splits->map(function ($s) {
                            $mName = $s->payment_method;
                            $bName = $s->bank?->name ? ' (' . $s->bank->name . ')' : '';

                            return $mName . $bName . ': ₦' . number_format($s->amount, 2);
                        })->implode(', ');

                        return '<span class="badge bg-purple text-white font-11" title="' . e($splitLines) . '">Split (' . $p->splits->count() . ' tenders)</span><small class="d-block font-10 text-muted">' . e($splitLines) . '</small>';
                    }

                    return '<span class="badge bg-secondary font-11">' . e($display) . '</span>';
                })
                ->addColumn('items_summary', function ($p) {
                    $items = $p->product_or_service_request;
                    if (!$items || $items->isEmpty()) {
                        return '<span class="text-muted fst-italic font-11">No item details</span>';
                    }
                    $count = $items->count();
                    $badgeList = $items->map(function ($it) {
                        $type = $it->is_free_form ? 'Custom' : ($it->service_id ? 'Service' : 'Product');
                        $typeClass = $it->is_free_form ? 'bg-warning text-dark' : ($it->service_id ? 'bg-info text-white' : 'bg-success text-white');
                        $effectiveDept = $it->department
                            ?? $it->service?->department
                            ?? $it->service?->category?->department
                            ?? $it->product?->department
                            ?? $it->product?->category?->department;
                        $deptName = $effectiveDept?->name;
                        $deptBadge = $deptName ? ' <span class="badge bg-light text-dark border font-10 ms-1"><i class="mdi mdi-domain me-1"></i>' . e($deptName) . '</span>' : '';
                        $itemName = getItemDisplayName($it);
                        $qty = $it->qty ?? 1;
                        $amt = number_format($it->payable_amount ?? 0, 2);

                        return '<div class="mb-1 d-flex justify-content-between align-items-center"><span><span class="badge ' . $typeClass . ' font-10">' . $type . '</span>' . $deptBadge . ' <strong>' . e($itemName) . '</strong> <small class="text-muted">(Qty: ' . $qty . ')</small></span><span class="text-primary fw-bold font-11 ms-2">₦' . $amt . '</span></div>';
                    })->implode('');

                    $boxId = 'dt-items-box-' . $p->id;

                    return '<div class="items-summary-container">'
                        . '<div class="d-flex align-items-center justify-content-between">'
                        . '<span class="font-11 text-dark fw-bold">' . $count . ' item(s)</span>'
                        . '<button type="button" class="btn btn-xs btn-outline-primary btn-toggle-items-breakdown py-0 px-1 font-11" data-details-target="#' . $boxId . '" data-target="#' . $boxId . '" data-bs-target="#' . $boxId . '">Details <i class="mdi mdi-chevron-down"></i></button>'
                        . '</div>'
                        . '<div class="p-2 border rounded bg-light mt-1 font-11 dt-items-box shadow-sm" id="' . $boxId . '" style="display: none; max-height: 250px; overflow-y: auto;">'
                        . $badgeList
                        . '</div>'
                        . '</div>';
                })
                ->addColumn('total_formatted', function ($p) {
                    $val = (float) $p->total;
                    if ($val < 0 || in_array($p->payment_type, ['ACC_WITHDRAW', 'REFUND']) || $p->payment_method === 'REFUND') {
                        return '<span class="text-end font-12 fw-bold text-danger d-block">-₦' . number_format(abs($val), 2) . '</span>';
                    }

                    return '<span class="text-end font-12 fw-bold text-primary d-block">₦' . number_format($val, 2) . '</span>';
                })
                ->addColumn('actions', function ($p) {
                    return '<div class="text-center"><button class="btn btn-xs btn-outline-info btn-print-tx-receipt" data-id="' . $p->id . '" title="Print Receipt"><i class="mdi mdi-printer"></i></button></div>';
                })
                ->filterColumn('patient_info', function ($query, $keyword) {
                    $query->where(function ($q) use ($keyword) {
                        $q->whereHas('patient.user', function ($uq) use ($keyword) {
                            $uq->where('firstname', 'like', "%{$keyword}%")
                                ->orWhere('surname', 'like', "%{$keyword}%")
                                ->orWhere('othername', 'like', "%{$keyword}%");
                        })->orWhereHas('patient', function ($pq) use ($keyword) {
                            $pq->where('file_no', 'like', "%{$keyword}%");
                        });
                    });
                })
                ->filterColumn('reference_badge', function ($query, $keyword) {
                    $query->where('payments.reference_no', 'like', "%{$keyword}%");
                })
                ->setRowClass(function ($p) {
                    if ($p->voided_at) {
                        return 'table-danger';
                    }
                    if ((float) $p->total < 0 || in_array($p->payment_type, ['ACC_WITHDRAW', 'REFUND']) || $p->payment_method === 'REFUND') {
                        return 'table-warning';
                    }

                    return '';
                })
                ->rawColumns(['reference_badge', 'patient_info', 'payment_method_badge', 'items_summary', 'total_formatted', 'actions'])
                ->with('summary', $summary)
                ->with('all_departments', $allDepartments)
                ->with('all_categories', [
                    'services' => $allServiceCats,
                    'products' => $allProductCats,
                ])
                ->make(true);
        }

        // High-performance thresholding: count total records
        $limit = min((int) $request->input('limit', 250), 500);
        $totalCount = (clone $query)->count();
        $isTruncated = ($totalCount > $limit);

        if ($isTruncated) {
            // For large datasets, compute accurate period KPIs via direct SQL aggregation (<1.5s vs 30s timeout)
            $summary = $this->calculateMyTransactionsSqlSummary($query, $allServiceCats, $allProductCats, $allDepartments);
            $transactions = (clone $query)->orderBy('payments.created_at', 'desc')->limit($limit)->get();
        } else {
            // For typical day-to-day ranges (<=250 tx), load records and compute in-memory (<15ms)
            $transactions = (clone $query)->orderBy('payments.created_at', 'desc')->get();

            $productCategoryKpis = [];
            $serviceCategoryKpis = [];
            $departmentKpis = [];
            $productTotalAmount = 0;
            $productTotalCount = 0;
            $serviceTotalAmount = 0;
            $serviceTotalCount = 0;

            foreach ($transactions as $tx) {
                if ($tx->voided_at) {
                    continue; // Exclude voided from active KPI totals
                }
                foreach ($tx->product_or_service_request as $item) {
                    $amt = (float) ($item->payable_amount ?? 0);

                    // Department revenue attribution rollup
                    $effectiveDept = $item->department
                        ?? $item->service?->department
                        ?? $item->service?->category?->department
                        ?? $item->product?->department
                        ?? $item->product?->category?->department;
                    $deptId = $effectiveDept ? (int) $effectiveDept->id : 0;
                    $deptName = $effectiveDept ? $effectiveDept->name : 'General / Unassigned';
                    if (!isset($departmentKpis[$deptId])) {
                        $departmentKpis[$deptId] = [
                            'id' => $deptId,
                            'name' => $deptName,
                            'count' => 0,
                            'amount' => 0,
                        ];
                    }
                    $departmentKpis[$deptId]['count']++;
                    $departmentKpis[$deptId]['amount'] += $amt;

                    if ($item->product_id) {
                        $productTotalAmount += $amt;
                        $productTotalCount++;
                        $catName = $item->product?->category?->category_name ?? $item->product?->category?->name ?? 'Uncategorized';
                        if (!isset($productCategoryKpis[$catName])) {
                            $productCategoryKpis[$catName] = ['name' => $catName, 'count' => 0, 'amount' => 0];
                        }
                        $productCategoryKpis[$catName]['count']++;
                        $productCategoryKpis[$catName]['amount'] += $amt;
                    } elseif ($item->service_id) {
                        $serviceTotalAmount += $amt;
                        $serviceTotalCount++;
                        $catName = $item->service?->category?->category_name ?? 'General Services';
                        if (!isset($serviceCategoryKpis[$catName])) {
                            $serviceCategoryKpis[$catName] = ['name' => $catName, 'count' => 0, 'amount' => 0];
                        }
                        $serviceCategoryKpis[$catName]['count']++;
                        $serviceCategoryKpis[$catName]['amount'] += $amt;
                    } elseif ($item->is_free_form) {
                        $serviceTotalAmount += $amt;
                        $serviceTotalCount++;
                        $catName = 'Custom Items';
                        if (!isset($serviceCategoryKpis[$catName])) {
                            $serviceCategoryKpis[$catName] = ['name' => $catName, 'count' => 0, 'amount' => 0];
                        }
                        $serviceCategoryKpis[$catName]['count']++;
                        $serviceCategoryKpis[$catName]['amount'] += $amt;
                    }
                }
            }

            $activeTransactions = $transactions->whereNull('voided_at');

            $refundAmount = 0.0;
            $refundCount = 0;
            $cashInflowAmount = 0.0;
            $cashInflowCount = 0;

            foreach ($activeTransactions as $tx) {
                $total = (float) $tx->total;
                $isRefund = ($total < 0 || in_array($tx->payment_type, ['ACC_WITHDRAW', 'REFUND']) || $tx->payment_method === 'REFUND');
                if ($isRefund) {
                    $refundCount++;
                    $refundAmount -= abs($total);
                } else {
                    $m = strtoupper(trim($tx->payment_method ?: $tx->payment_type ?: 'CASH'));
                    if ($m === 'CASH') {
                        $cashInflowCount++;
                        $cashInflowAmount += $total;
                    } elseif ($tx->is_split_payment && $tx->splits) {
                        foreach ($tx->splits as $split) {
                            if (strtoupper(trim($split->payment_method ?? '')) === 'CASH') {
                                $cashInflowCount++;
                                $cashInflowAmount += (float) ($split->amount ?? 0);
                            }
                        }
                    }
                }
            }

            $paymentMethodKpis = [
                'CASH' => ['label' => 'Cash in Till', 'count' => 0, 'amount' => 0, 'banks' => []],
                'POS' => ['label' => 'POS', 'count' => 0, 'amount' => 0, 'banks' => []],
                'TRANSFER' => ['label' => 'Bank Transfer', 'count' => 0, 'amount' => 0, 'banks' => []],
                'MOBILE' => ['label' => 'Mobile Money', 'count' => 0, 'amount' => 0, 'banks' => []],
                'DEPOSIT' => ['label' => 'Patient Deposit', 'count' => 0, 'amount' => 0, 'banks' => []],
                'REFUND' => ['label' => 'Refunds & Withdrawals', 'count' => $refundCount, 'amount' => $refundAmount, 'banks' => []],
                'OTHER' => ['label' => 'Other Methods', 'count' => 0, 'amount' => 0, 'banks' => []],
            ];

            $splitTransactionsCount = 0;
            $splitTransactionsAmount = 0;

            $recordTender = function (string $rawMethod, ?string $bankName, float $amount) use (&$paymentMethodKpis) {
                $m = strtoupper(trim($rawMethod));
                if ($m === 'CASH') {
                    $code = 'CASH';
                } elseif ($m === 'POS') {
                    $code = 'POS';
                } elseif (in_array($m, ['TRANSFER', 'BANK_TRANSFER'])) {
                    $code = 'TRANSFER';
                } elseif ($m === 'MOBILE') {
                    $code = 'MOBILE';
                } elseif (in_array($m, ['DEPOSIT', 'ACC_DEPOSIT', 'ACCOUNT'])) {
                    $code = 'DEPOSIT';
                } elseif (in_array($m, ['REFUND', 'ACC_WITHDRAW']) || $amount < 0) {
                    $code = 'REFUND';
                } else {
                    $code = 'OTHER';
                }

                if ($code !== 'REFUND') {
                    $paymentMethodKpis[$code]['count']++;
                    $paymentMethodKpis[$code]['amount'] += $amount;
                }

                if (!in_array($code, ['CASH', 'DEPOSIT', 'REFUND'])) {
                    $bName = trim($bankName ?: 'Direct');
                    if (!isset($paymentMethodKpis[$code]['banks'][$bName])) {
                        $paymentMethodKpis[$code]['banks'][$bName] = [
                            'name' => $bName,
                            'count' => 0,
                            'amount' => 0,
                        ];
                    }
                    $paymentMethodKpis[$code]['banks'][$bName]['count']++;
                    $paymentMethodKpis[$code]['banks'][$bName]['amount'] += $amount;
                }
            };

            foreach ($activeTransactions as $tx) {
                $total = (float) $tx->total;
                if ($tx->is_split_payment) {
                    $splitTransactionsCount++;
                    $splitTransactionsAmount += $total;

                    if ($tx->splits && $tx->splits->isNotEmpty()) {
                        foreach ($tx->splits as $split) {
                            $recordTender(
                                $split->payment_method ?? '',
                                $split->bank?->name,
                                (float) ($split->amount ?? 0)
                            );
                        }
                    } else {
                        $recordTender(
                            $tx->payment_method ?: $tx->payment_type ?: 'OTHER',
                            $tx->bank?->name,
                            $total
                        );
                    }
                } else {
                    $recordTender(
                        $tx->payment_method ?: $tx->payment_type ?: 'CASH',
                        $tx->bank?->name,
                        $total
                    );
                }
            }

            foreach ($paymentMethodKpis as $code => $data) {
                $paymentMethodKpis[$code]['banks'] = array_values($data['banks']);
            }

            $byTypeSummary = collect($paymentMethodKpis)
                ->filter(fn ($kpi) => $kpi['amount'] != 0 || $kpi['count'] > 0)
                ->map(fn ($kpi) => [
                    'label' => $kpi['label'],
                    'count' => $kpi['count'],
                    'amount' => $kpi['amount'],
                    'banks' => $kpi['banks'],
                    'discount' => 0,
                ]);

            $summary = [
                'total_amount' => (float) $activeTransactions->sum('total'),
                'total_discount' => (float) $activeTransactions->sum('total_discount'),
                'count' => (int) $activeTransactions->count(),
                'voided_count' => (int) $transactions->whereNotNull('voided_at')->count(),
                'refund_amount' => $refundAmount,
                'refund_count' => $refundCount,
                'cash_inflow' => $cashInflowAmount,
                'cash_inflow_count' => $cashInflowCount,
                'split_transactions_count' => $splitTransactionsCount,
                'split_transactions_amount' => $splitTransactionsAmount,
                'by_type' => $byTypeSummary,
                'payment_method_kpis' => $paymentMethodKpis,
                'product_total' => $productTotalAmount,
                'service_total' => $serviceTotalAmount,
                'product_kpi' => [
                    'count' => $productTotalCount,
                    'amount' => $productTotalAmount,
                ],
                'service_kpi' => [
                    'count' => $serviceTotalCount,
                    'amount' => $serviceTotalAmount,
                ],
                'product_category_kpis' => array_values($productCategoryKpis),
                'service_category_kpis' => array_values($serviceCategoryKpis),
                'department_kpis' => array_values($departmentKpis),
                'all_categories' => [
                    'services' => $allServiceCats,
                    'products' => $allProductCats,
                ],
                'all_departments' => $allDepartments,
            ];
        }

        // Format transactions for list view using preloaded relations to eliminate N+1 queries
        $transactionsFormatted = $transactions->map(function ($tx) {
            $itemsFormatted = $tx->product_or_service_request->map(function ($item) {
                $isService = (bool) $item->service_id;
                $isFree = (bool) $item->is_free_form;
                $qty = (float) ($item->qty ?? 1);
                $amt = (float) ($item->payable_amount ?? 0);
                $unitPrice = $qty > 0 ? $amt / $qty : $amt;

                $catName = $isFree ? 'Custom Items' : ($item->service?->category?->category_name ?? $item->product?->category?->category_name ?? $item->product?->category?->name ?? 'General Items');

                $effectiveDept = $item->department
                    ?? $item->service?->department
                    ?? $item->service?->category?->department
                    ?? $item->product?->department
                    ?? $item->product?->category?->department;

                $itemUserName = $item->user
                    ? ($item->user->surname . ' ' . $item->user->firstname . ($item->user->othername ? ' ' . $item->user->othername : ''))
                    : userfullname($item->user_id);

                return [
                    'id' => $item->id,
                    'type' => $isFree ? 'Custom' : ($isService ? 'Service' : 'Product'),
                    'name' => getItemDisplayName($item),
                    'category' => $catName,
                    'department_id' => $effectiveDept?->id,
                    'department_name' => $effectiveDept?->name ?? 'General',
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'amount' => $amt,
                    'discount' => $item->discount ?? 0,
                    'patient_name' => $itemUserName,
                ];
            });

            $patientUser = $tx->patient?->user;
            $patientName = $patientUser
                ? ($patientUser->surname . ' ' . $patientUser->firstname . ($patientUser->othername ? ' ' . $patientUser->othername : ''))
                : ($tx->patient ? userfullname($tx->patient->user_id) : 'Unknown');

            return [
                'id' => $tx->id,
                'reference_no' => $tx->reference_no,
                'patient_name' => $patientName,
                'file_no' => $tx->patient?->file_no ?? 'N/A',
                'payment_type' => $tx->payment_type,
                'payment_method' => $tx->payment_method,
                'payment_method_display' => formatPaymentMethod($tx),
                'is_split_payment' => (bool) $tx->is_split_payment,
                'splits' => $tx->splits->map(function ($s) {
                    return [
                        'payment_method' => $s->payment_method,
                        'bank_name' => $s->bank?->name,
                        'amount' => (float) $s->amount,
                        'reference_no' => $s->reference_no,
                    ];
                }),
                'bank_name' => $tx->bank ? $tx->bank->name : null,
                'total' => (float) $tx->total,
                'total_discount' => (float) $tx->total_discount,
                'is_voided' => (bool) $tx->voided_at,
                'voided_at' => $tx->voided_at ? $tx->voided_at->format('Y-m-d H:i') : null,
                'void_reason' => $tx->void_reason,
                'items' => $itemsFormatted,
                'created_at' => $tx->created_at->format('Y-m-d H:i'),
            ];
        });

        // If print requested, return branded A4 printout
        if ($request->input('action') === 'print' || $request->input('format') === 'print' || $request->boolean('print')) {
            $site = appsettings();

            return view('admin.Accounts.my_transactions_print', [
                'site' => $site,
                'staffName' => $staffName,
                'from' => $from,
                'to' => $to,
                'transactions' => $transactions,
                'transactionsFormatted' => $transactionsFormatted,
                'summary' => $summary,
                'paymentType' => $request->payment_type ?? $request->payment_method ?? 'All Methods',
                'isTruncated' => $isTruncated,
            ]);
        }

        return response()->json([
            'success' => true,
            'is_truncated' => $isTruncated,
            'total_count' => $totalCount,
            'showing_count' => $transactions->count(),
            'transactions' => $transactionsFormatted,
            'summary' => $summary,
            'all_categories' => [
                'services' => $allServiceCats,
                'products' => $allProductCats,
            ],
            'all_departments' => $allDepartments,
            'from' => $from,
            'to' => $to,
        ]);
    }

    /**
     * Compute summary KPIs via direct SQL aggregation for large datasets
     */
    protected function calculateMyTransactionsSqlSummary($query, $allServiceCats, $allProductCats, $allDepartments)
    {
        $activeQuery = (clone $query)->whereNull('payments.voided_at');

        // 1. Overall active & voided totals
        $overall = (clone $activeQuery)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(payments.total), 0) as total_amount, COALESCE(SUM(payments.total_discount), 0) as total_discount')
            ->first();

        $voidedCount = (clone $query)->whereNotNull('payments.voided_at')->count();

        // 2. Split transaction stats
        $splitStats = (clone $activeQuery)
            ->where('payments.is_split_payment', true)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(payments.total), 0) as amount')
            ->first();

        $splitTransactionsCount = (int) ($splitStats->count ?? 0);
        $splitTransactionsAmount = (float) ($splitStats->amount ?? 0);

        // 2b. Refunds / Withdrawals stats
        $refundStats = (clone $activeQuery)
            ->where(function ($q) {
                $q->where('payments.total', '<', 0)
                    ->orWhereIn('payments.payment_type', ['ACC_WITHDRAW', 'REFUND'])
                    ->orWhere('payments.payment_method', 'REFUND');
            })
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(payments.total), 0) as amount')
            ->first();

        $refundCount = (int) ($refundStats->count ?? 0);
        $refundAmount = (float) ($refundStats->amount ?? 0);
        if ($refundAmount > 0) {
            $refundAmount = -$refundAmount;
        }

        // 2c. Gross Cash Inflow stats (positive cash payments excluding refunds/withdrawals)
        $cashInflowStats = (clone $activeQuery)
            ->where('payments.total', '>', 0)
            ->whereNotIn('payments.payment_type', ['ACC_WITHDRAW', 'REFUND'])
            ->where('payments.payment_method', '!=', 'REFUND')
            ->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->where(function ($nq) {
                        $nq->whereNull('payments.is_split_payment')->orWhere('payments.is_split_payment', false);
                    })->whereRaw("UPPER(COALESCE(payments.payment_method, payments.payment_type, 'CASH')) = 'CASH'");
                })->orWhereHas('splits', function ($sq) {
                    $sq->whereRaw("UPPER(COALESCE(payment_splits.payment_method, '')) = 'CASH'");
                });
            })
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(payments.total), 0) as amount')
            ->first();

        $cashInflowCount = (int) ($cashInflowStats->count ?? 0);
        $cashInflowAmount = (float) ($cashInflowStats->amount ?? 0);

        // 3. Payment Method KPIs (non-split & split)
        $paymentMethodKpis = [
            'CASH' => ['label' => 'Cash in Till', 'count' => 0, 'amount' => 0, 'banks' => []],
            'POS' => ['label' => 'POS', 'count' => 0, 'amount' => 0, 'banks' => []],
            'TRANSFER' => ['label' => 'Bank Transfer', 'count' => 0, 'amount' => 0, 'banks' => []],
            'MOBILE' => ['label' => 'Mobile Money', 'count' => 0, 'amount' => 0, 'banks' => []],
            'DEPOSIT' => ['label' => 'Patient Deposit', 'count' => 0, 'amount' => 0, 'banks' => []],
            'REFUND' => ['label' => 'Refunds & Withdrawals', 'count' => $refundCount, 'amount' => $refundAmount, 'banks' => []],
            'OTHER' => ['label' => 'Other Methods', 'count' => 0, 'amount' => 0, 'banks' => []],
        ];

        $recordTender = function (string $rawMethod, ?string $bankName, float $amount, int $count = 1) use (&$paymentMethodKpis) {
            $m = strtoupper(trim($rawMethod));
            if ($m === 'CASH') {
                $code = 'CASH';
            } elseif ($m === 'POS') {
                $code = 'POS';
            } elseif (in_array($m, ['TRANSFER', 'BANK_TRANSFER'])) {
                $code = 'TRANSFER';
            } elseif ($m === 'MOBILE') {
                $code = 'MOBILE';
            } elseif (in_array($m, ['DEPOSIT', 'ACC_DEPOSIT', 'ACCOUNT'])) {
                $code = 'DEPOSIT';
            } elseif (in_array($m, ['REFUND', 'ACC_WITHDRAW']) || $amount < 0) {
                $code = 'REFUND';
            } else {
                $code = 'OTHER';
            }

            if ($code !== 'REFUND') {
                $paymentMethodKpis[$code]['count'] += $count;
                $paymentMethodKpis[$code]['amount'] += $amount;
            }

            if (!in_array($code, ['CASH', 'DEPOSIT', 'REFUND'])) {
                $bName = trim($bankName ?: 'Direct');
                if (!isset($paymentMethodKpis[$code]['banks'][$bName])) {
                    $paymentMethodKpis[$code]['banks'][$bName] = [
                        'name' => $bName,
                        'count' => 0,
                        'amount' => 0,
                    ];
                }
                $paymentMethodKpis[$code]['banks'][$bName]['count'] += $count;
                $paymentMethodKpis[$code]['banks'][$bName]['amount'] += $amount;
            }
        };

        // Non-split payments aggregation
        $nonSplitTenders = (clone $activeQuery)
            ->where(function ($q) {
                $q->whereNull('payments.is_split_payment')->orWhere('payments.is_split_payment', false);
            })
            ->leftJoin('banks', 'payments.bank_id', '=', 'banks.id')
            ->selectRaw("UPPER(COALESCE(payments.payment_method, payments.payment_type, 'CASH')) as method, banks.name as bank_name, COUNT(*) as cnt, COALESCE(SUM(payments.total), 0) as amt")
            ->groupBy(DB::raw("UPPER(COALESCE(payments.payment_method, payments.payment_type, 'CASH'))"), 'banks.name')
            ->get();

        foreach ($nonSplitTenders as $row) {
            $recordTender($row->method, $row->bank_name, (float) $row->amt, (int) $row->cnt);
        }

        // Split payments aggregation
        $filteredPaymentSubquery = (clone $activeQuery)->select('payments.id');

        $splitTenders = DB::table('payment_splits')
            ->joinSub($filteredPaymentSubquery, 'filtered_p', 'payment_splits.payment_id', '=', 'filtered_p.id')
            ->leftJoin('banks', 'payment_splits.bank_id', '=', 'banks.id')
            ->selectRaw("UPPER(COALESCE(payment_splits.payment_method, 'OTHER')) as method, banks.name as bank_name, COUNT(*) as cnt, COALESCE(SUM(payment_splits.amount), 0) as amt")
            ->groupBy(DB::raw("UPPER(COALESCE(payment_splits.payment_method, 'OTHER'))"), 'banks.name')
            ->get();

        foreach ($splitTenders as $row) {
            $recordTender($row->method, $row->bank_name, (float) $row->amt, (int) $row->cnt);
        }

        foreach ($paymentMethodKpis as $code => $data) {
            $paymentMethodKpis[$code]['banks'] = array_values($data['banks']);
        }

        $byTypeSummary = collect($paymentMethodKpis)
            ->filter(fn ($kpi) => $kpi['amount'] != 0 || $kpi['count'] > 0)
            ->map(fn ($kpi) => [
                'label' => $kpi['label'],
                'count' => $kpi['count'],
                'amount' => $kpi['amount'],
                'banks' => $kpi['banks'],
                'discount' => 0,
            ]);

        // 4. Items aggregation scoped to matching payments
        $productCats = DB::table('product_or_service_requests as posr')
            ->joinSub($filteredPaymentSubquery, 'filtered_p', 'posr.payment_id', '=', 'filtered_p.id')
            ->leftJoin('products as pr', 'posr.product_id', '=', 'pr.id')
            ->leftJoin('product_categories as pc', 'pr.category_id', '=', 'pc.id')
            ->whereNotNull('posr.product_id')
            ->selectRaw("COALESCE(pc.category_name, 'Uncategorized') as name, COUNT(*) as count, COALESCE(SUM(posr.payable_amount), 0) as amount")
            ->groupBy(DB::raw("COALESCE(pc.category_name, 'Uncategorized')"))
            ->get();

        $productCategoryKpis = $productCats->map(fn ($r) => [
            'name' => $r->name,
            'count' => (int) $r->count,
            'amount' => (float) $r->amount,
        ])->all();

        $productTotalAmount = (float) $productCats->sum('amount');
        $productTotalCount = (int) $productCats->sum('count');

        $serviceCats = DB::table('product_or_service_requests as posr')
            ->joinSub($filteredPaymentSubquery, 'filtered_p', 'posr.payment_id', '=', 'filtered_p.id')
            ->leftJoin('services as s', 'posr.service_id', '=', 's.id')
            ->leftJoin('service_categories as sc', 's.category_id', '=', 'sc.id')
            ->where(function ($q) {
                $q->whereNotNull('posr.service_id')->orWhere('posr.is_free_form', 1);
            })
            ->selectRaw("CASE WHEN posr.is_free_form = 1 THEN 'Custom Items' ELSE COALESCE(sc.category_name, 'General Services') END as name, COUNT(*) as count, COALESCE(SUM(posr.payable_amount), 0) as amount")
            ->groupBy(DB::raw("CASE WHEN posr.is_free_form = 1 THEN 'Custom Items' ELSE COALESCE(sc.category_name, 'General Services') END"))
            ->get();

        $serviceCategoryKpis = $serviceCats->map(fn ($r) => [
            'name' => $r->name,
            'count' => (int) $r->count,
            'amount' => (float) $r->amount,
        ])->all();

        $serviceTotalAmount = (float) $serviceCats->sum('amount');
        $serviceTotalCount = (int) $serviceCats->sum('count');

        // Department breakdown
        $deptRaw = DB::table('product_or_service_requests as posr')
            ->joinSub($filteredPaymentSubquery, 'filtered_p', 'posr.payment_id', '=', 'filtered_p.id')
            ->leftJoin('services as s', 'posr.service_id', '=', 's.id')
            ->leftJoin('service_categories as sc', 's.category_id', '=', 'sc.id')
            ->leftJoin('products as pr', 'posr.product_id', '=', 'pr.id')
            ->leftJoin('product_categories as pc', 'pr.category_id', '=', 'pc.id')
            ->selectRaw('COALESCE(posr.department_id, s.department_id, sc.department_id, pr.department_id, pc.department_id, 0) as dept_id, COUNT(*) as count, COALESCE(SUM(posr.payable_amount), 0) as amount')
            ->groupBy(DB::raw('COALESCE(posr.department_id, s.department_id, sc.department_id, pr.department_id, pc.department_id, 0)'))
            ->get();

        $deptIds = $deptRaw->pluck('dept_id')->filter()->all();
        $deptNames = Department::whereIn('id', $deptIds)->pluck('name', 'id');

        $departmentKpis = $deptRaw->map(function ($r) use ($deptNames) {
            $id = (int) $r->dept_id;

            return [
                'id' => $id,
                'name' => ($id && isset($deptNames[$id])) ? $deptNames[$id] : 'General / Unassigned',
                'count' => (int) $r->count,
                'amount' => (float) $r->amount,
            ];
        })->all();

        return [
            'total_amount' => (float) ($overall->total_amount ?? 0),
            'total_discount' => (float) ($overall->total_discount ?? 0),
            'count' => (int) ($overall->count ?? 0),
            'voided_count' => $voidedCount,
            'refund_amount' => $refundAmount,
            'refund_count' => $refundCount,
            'cash_inflow' => $cashInflowAmount,
            'cash_inflow_count' => $cashInflowCount,
            'split_transactions_count' => $splitTransactionsCount,
            'split_transactions_amount' => $splitTransactionsAmount,
            'by_type' => $byTypeSummary,
            'payment_method_kpis' => $paymentMethodKpis,
            'product_total' => $productTotalAmount,
            'service_total' => $serviceTotalAmount,
            'product_kpi' => [
                'count' => $productTotalCount,
                'amount' => $productTotalAmount,
            ],
            'service_kpi' => [
                'count' => $serviceTotalCount,
                'amount' => $serviceTotalAmount,
            ],
            'product_category_kpis' => $productCategoryKpis,
            'service_category_kpis' => $serviceCategoryKpis,
            'department_kpis' => $departmentKpis,
            'all_categories' => [
                'services' => $allServiceCats,
                'products' => $allProductCats,
            ],
            'all_departments' => $allDepartments,
        ];
    }

    /**
     * Get transactions for Manage Transactions DataTables grid
     */
    public function getManageTransactions(Request $request)
    {
        $user = Auth::user();
        $isPrivileged = $user->hasAnyRole(['Accountant', 'Admin', 'Super-Admin']);

        $query = Payment::query()->with(['patient.user', 'bank', 'splits.bank', 'staff_user']);

        // Scope: billers see only own transactions; privileged users can toggle 'all' or 'own'
        $scope = $request->input('scope', $isPrivileged ? 'all' : 'own');
        if (!$isPrivileged || $scope === 'own') {
            $query->where('user_id', $user->id);
        }

        // Date range
        if ($request->filled('from') && $request->filled('to')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->from)->startOfDay(),
                Carbon::parse($request->to)->endOfDay(),
            ]);
        }

        // Payment method filter
        if ($request->filled('payment_method')) {
            $pm = $request->payment_method;
            if ($pm === 'SPLIT') {
                $query->where('is_split_payment', true);
            } else {
                $query->where(function ($q) use ($pm) {
                    $q->where('payment_method', $pm)
                        ->orWhereHas('splits', fn ($sq) => $sq->where('payment_method', $pm));
                });
            }
        }

        // Department filter
        if ($request->filled('department_id')) {
            $deptId = $request->department_id;
            $query->whereHas('product_or_service_request', function ($q) use ($deptId) {
                $q->where('department_id', $deptId)
                    ->orWhereHas('service', fn ($sq) => $sq->where('department_id', $deptId)->orWhereHas('category', fn ($c) => $c->where('department_id', $deptId)))
                    ->orWhereHas('product', fn ($pq) => $pq->where('department_id', $deptId)->orWhereHas('category', fn ($c) => $c->where('department_id', $deptId)));
            });
        }

        // Status filter: all, active, voided
        if ($request->input('status') === 'voided') {
            $query->whereNotNull('voided_at');
        } elseif ($request->input('status') === 'active') {
            $query->whereNull('voided_at');
        }

        return datatables()->of($query)
            ->addColumn('patient_name', function ($p) {
                return $p->patient ? userfullname($p->patient->user_id) : 'N/A';
            })
            ->addColumn('file_no', function ($p) {
                return $p->patient?->file_no ?? 'N/A';
            })
            ->addColumn('payment_method_display', function ($p) {
                return formatPaymentMethod($p);
            })
            ->addColumn('total_formatted', function ($p) {
                return '₦' . number_format(abs($p->total), 2);
            })
            ->addColumn('status_badge', function ($p) {
                if ($p->voided_at) {
                    return '<span class="badge badge-danger" title="' . e($p->void_reason) . '">VOIDED</span>';
                }

                return '<span class="badge badge-success">ACTIVE</span>';
            })
            ->addColumn('actions', function ($p) use ($isPrivileged, $user) {
                $canEdit = !$p->voided_at && ($isPrivileged || $p->user_id === $user->id);
                $html = '<div class="btn-group btn-group-sm">';
                $html .= '<button type="button" class="btn btn-outline-info btn-xs btn-view-receipt" data-id="' . $p->id . '" title="View / Print Receipt"><i class="mdi mdi-printer"></i></button>';
                if ($canEdit) {
                    $html .= '<button type="button" class="btn btn-outline-warning btn-xs btn-adjust-payment ms-1" data-id="' . $p->id . '" title="Adjust Payment Method"><i class="mdi mdi-tune"></i></button>';
                    $html .= '<button type="button" class="btn btn-outline-danger btn-xs btn-void-payment ms-1" data-id="' . $p->id . '" title="Void Transaction"><i class="mdi mdi-delete-outline"></i></button>';
                }
                $html .= '</div>';

                return $html;
            })
            ->rawColumns(['status_badge', 'actions'])
            ->make(true);
    }

    /**
     * Adjust payment method for a transaction
     */
    public function adjustPaymentMethod(Request $request, \App\Services\Accounting\PaymentReversalService $reversalService)
    {
        if (!$request->filled('reason') && $request->filled('adjustment_reason')) {
            $request->merge(['reason' => $request->input('adjustment_reason')]);
        }

        $data = $request->validate([
            'payment_id' => 'required|integer|exists:payments,id',
            'reason' => 'required|string|min:5',
            'is_split' => 'nullable|boolean',
            'payment_method' => 'nullable|string',
            'bank_id' => 'nullable|integer|exists:banks,id',
            'splits' => 'nullable|array',
            'splits.*.payment_method' => 'required_with:splits|string',
            'splits.*.bank_id' => 'nullable|integer|exists:banks,id',
            'splits.*.amount' => 'required_with:splits|numeric|min:0.01',
            'splits.*.reference_no' => 'nullable|string',
        ]);

        $payment = Payment::with('splits')->findOrFail($data['payment_id']);

        $user = Auth::user();
        $isPrivileged = $user->hasAnyRole(['Accountant', 'Admin', 'Super-Admin']);

        if (!$isPrivileged && $payment->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized: You can only adjust your own transactions.'], 403);
        }

        try {
            $adjustment = $reversalService->adjustPayment(
                $payment,
                $data,
                $user->id,
                $data['reason']
            );

            return response()->json([
                'success' => true,
                'message' => 'Payment method adjusted successfully. Journal entry updated.',
                'adjustment_id' => $adjustment->id,
                'payment' => [
                    'id' => $payment->id,
                    'payment_method_display' => formatPaymentMethod($payment->refresh()),
                    'is_split_payment' => (bool) $payment->is_split_payment,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Billing workbench adjust payment failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Void a transaction
     */
    public function voidTransaction(Request $request, \App\Services\Accounting\PaymentReversalService $reversalService)
    {
        if (!$request->filled('reason') && $request->filled('void_reason')) {
            $request->merge(['reason' => $request->input('void_reason')]);
        }

        $data = $request->validate([
            'payment_id' => 'required|integer|exists:payments,id',
            'reason' => 'required|string|min:5',
        ]);

        $payment = Payment::findOrFail($data['payment_id']);

        $user = Auth::user();
        $isPrivileged = $user->hasAnyRole(['Accountant', 'Admin', 'Super-Admin']);

        if (!$isPrivileged && $payment->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized: You can only void your own transactions.'], 403);
        }

        try {
            $reversalService->voidPayment($payment, $user->id, $data['reason']);

            return response()->json([
                'success' => true,
                'message' => 'Transaction voided successfully. Journal entries and items reversed.',
            ]);
        } catch (\Exception $e) {
            Log::error('Billing workbench void transaction failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Get full details of a payment for modal inspection/adjustment
     */
    public function getPaymentDetails($paymentId)
    {
        $payment = Payment::with([
            'patient.user',
            'bank',
            'splits.bank',
            'adjustments.adjuster',
            'product_or_service_request' => fn ($q) => $q->with(['service', 'product', 'user']),
        ])->findOrFail($paymentId);

        return response()->json([
            'id' => $payment->id,
            'reference_no' => $payment->reference_no,
            'patient_name' => $payment->patient ? userfullname($payment->patient->user_id) : 'Unknown',
            'file_no' => $payment->patient?->file_no ?? 'N/A',
            'payment_type' => $payment->payment_type,
            'payment_method' => $payment->payment_method,
            'payment_method_display' => formatPaymentMethod($payment),
            'bank_id' => $payment->bank_id,
            'bank_name' => $payment->bank?->name,
            'total' => (float) $payment->total,
            'total_discount' => (float) $payment->total_discount,
            'is_split_payment' => (bool) $payment->is_split_payment,
            'is_voided' => (bool) $payment->voided_at,
            'voided_at' => $payment->voided_at ? $payment->voided_at->format('Y-m-d H:i') : null,
            'void_reason' => $payment->void_reason,
            'splits' => $payment->splits->map(function ($s) {
                return [
                    'id' => $s->id,
                    'payment_method' => $s->payment_method,
                    'bank_id' => $s->bank_id,
                    'bank_name' => $s->bank?->name,
                    'amount' => (float) $s->amount,
                    'reference_no' => $s->reference_no,
                ];
            }),
            'items' => $payment->product_or_service_request->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => getItemDisplayName($item),
                    'type' => $item->is_free_form ? 'Custom' : ($item->service_id ? 'Service' : 'Product'),
                    'qty' => $item->qty,
                    'amount' => (float) $item->payable_amount,
                ];
            }),
            'adjustments' => $payment->adjustments->map(function ($adj) {
                return [
                    'id' => $adj->id,
                    'original_payment_method' => $adj->original_payment_method,
                    'new_payment_method' => $adj->new_payment_method,
                    'reason' => $adj->reason,
                    'adjusted_by' => $adj->adjuster ? userfullname($adj->adjusted_by) : 'User',
                    'adjusted_at' => $adj->adjusted_at ? $adj->adjusted_at->format('Y-m-d H:i') : $adj->created_at->format('Y-m-d H:i'),
                ];
            }),
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
                ],
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
                ],
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
            $payment = Payment::create([
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
            $payment = Payment::create([
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
                $billingPatientId = Patient::find($request->patient_id)->billing_patient_id ?? $request->patient_id;
                $deposit = PatientDeposit::create([
                    'patient_id' => $billingPatientId,
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
            'thermalWidth' => getThermalPrinterWidth(),
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

        $patient = Patient::with(['hmo', 'user'])->findOrFail($patientId);
        $billingPatientId = $patient->billing_patient_id ?? $patient->id;
        $account = PatientAccount::where('patient_id', $billingPatientId)->first();
        $patientIds = array_values(array_unique(array_filter([$patientId, $billingPatientId])));

        $dateFrom = Carbon::parse($request->date_from)->startOfDay();
        $dateTo = Carbon::parse($request->date_to)->endOfDay();

        // Get all transactions for the statement
        $transactions = collect();

        // Include deposits (ACC_DEPOSIT from payments OR from patient_deposits)
        if ($includeDeposits) {
            $deposits = PatientDeposit::where('patient_id', $billingPatientId)
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
            $directPayments = Payment::whereIn('patient_id', $patientIds)
                ->whereNull('voided_at')
                ->whereNotIn('payment_type', ['ACC_DEPOSIT', 'ACC_WITHDRAW', 'ACC_ADJUSTMENT'])
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->with(['product_or_service_request.service', 'product_or_service_request.product', 'splits.bank', 'bank'])
                ->orderBy('created_at')
                ->get();

            foreach ($directPayments as $pmt) {
                $items = $pmt->product_or_service_request->reject(fn ($i) => $i->is_bundle_item)->map(function ($item) {
                    return getItemDisplayName($item);
                })->implode(', ');

                $transactions->push([
                    'date' => $pmt->created_at->format('M d, Y'),
                    'short_date' => $pmt->created_at->format('m/d'),
                    'time' => $pmt->created_at->format('h:i A'),
                    'datetime' => $pmt->created_at,
                    'reference' => $pmt->reference_no,
                    'type' => 'payment',
                    'type_class' => 'payment',
                    'type_label' => 'Payment (' . formatPaymentMethod($pmt) . ')',
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
            $withdrawals = Payment::whereIn('patient_id', $patientIds)
                ->whereNull('voided_at')
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
            $applications = \App\Models\Accounting\PatientDepositApplication::whereHas('deposit', function ($q) use ($billingPatientId) {
                $q->where('patient_id', $billingPatientId);
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
        $depositsBeforePeriod = PatientDeposit::where('patient_id', $billingPatientId)
            ->where('deposit_date', '<', $dateFrom)
            ->sum('amount');

        $withdrawalsBeforePeriod = abs(Payment::whereIn('patient_id', $patientIds)
            ->whereNull('voided_at')
            ->where('payment_type', 'ACC_WITHDRAW')
            ->where('created_at', '<', $dateFrom)
            ->sum('total'));

        $adjustmentsBeforePeriod = Payment::whereIn('patient_id', $patientIds)
            ->whereNull('voided_at')
            ->where('payment_type', 'ACC_ADJUSTMENT')
            ->where('created_at', '<', $dateFrom)
            ->sum('total');

        $applicationsBeforePeriod = \App\Models\Accounting\PatientDepositApplication::whereHas('deposit', function ($q) use ($billingPatientId) {
            $q->where('patient_id', $billingPatientId);
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
            'thermalWidth' => getThermalPrinterWidth(),
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
        $patient = Patient::find($patientId);
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
        if (!$admitDate) {
            return 0;
        }

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
        // For emergency intake admissions, bed assignment is skipped — fall back to created_at
        $admitDate = $admission->bed_assign_date ?? $admission->created_at;
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
                $admitStart = Carbon::parse($admitDate)->startOfDay();
                $itemDay = Carbon::parse($item->created_at)->startOfDay();
                $dayDiff = (int) $admitStart->diffInDays($itemDay, false);
                $dayNumber = max(1, $dayDiff + 1);

                $timeline[$dayKey] = [
                    'date' => Carbon::parse($item->created_at)->format('D, d M Y'),
                    'day_number' => $dayNumber,
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
        uasort($categories, fn ($a, $b) => $b['total'] <=> $a['total']);

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
