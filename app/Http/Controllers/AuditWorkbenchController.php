<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Staff;
use App\Models\StaffBill;
use App\Models\AuditStamp;
use App\Models\Bank;
use App\Models\Payment;
use App\Models\AdmissionRequest;
use App\Models\DoctorAppointment;
use App\Models\DoctorQueue;
use App\Models\Procedure;
use App\Models\ProcedureItem;
use App\Models\StoreRequisition;
use App\Models\LabServiceRequest;
use App\Models\ImagingServiceRequest;
use App\Models\MaternityEnrollment;
use App\Models\MorgueAdmission;
use App\Models\DeathRecord;
use App\Models\ProductRequest;
use App\Models\VitalSign;
use App\Models\Encounter;
use App\Models\Store;
use App\Models\StockBatch;
use App\Models\StockBatchTransaction;
use App\Models\StoreStock;
use App\Models\StoreDamage;
use App\Models\StoreLanePolicy;
use App\Models\StoreRequisitionReturn;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderReturn;
use App\Models\PurchaseOrderPayment;
use App\Models\Supplier;
use App\Models\HR\StaffSalaryProfile;
use App\Models\Accounting\Account;
use App\Enums\QueueStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

class AuditWorkbenchController extends Controller
{
    public static $responsibilities = [
        'financial' => [
            'cash_and_billing_audit' => 'Cash Book & Billing Reconciliations',
            'bank_reconciliation' => 'Bank Statements & POS Reconciliations',
            'hmo_nhis_verification' => 'HMO/NHIS Claims & Capitation',
            'discounts_refunds_debt' => 'Discounts, Refunds & Debt Recovery',
            'payroll_expenses_ledger' => 'Payroll, Deductions & Expenses'
        ],
        'clinical' => [
            'consulting_clinics_flow' => 'Consulting Clinics & Patient Flow',
            'inpatient_ward_income' => 'Ward Income & Discharge Clearance',
            'theatre_bundles_audit' => 'Theatre Bundles & Procedure Revenue',
            'maternity_morgue_audit' => 'Maternity Enrollments & Mortuary Register'
        ],
        'diagnostics_pharmacy' => [
            'laboratory_register' => 'Laboratory Register & Reagent Usage',
            'imaging_register' => 'Imaging Register & Consumables Usage',
            'pharmacy_prescriptions' => 'Pharmacy Prescriptions, Returns & Damages'
        ],
        'inventory' => [
            'central_store_stock_check' => 'Central Store Stock & PO Price Variance',
            'departmental_ward_stores' => 'Departmental/Ward Stock & Requisitions'
        ]
    ];

    /**
     * Display the Audit Workbench Dashboard
     */
    public function index(Request $request)
    {
        // Gating
        if (!auth()->user()->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin']) && !auth()->user()->hasRole('AUDITOR')) {
            abort(403, 'Unauthorized access to Internal Audit.');
        }

        $startDate = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->filled('end_date') ? Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        // 1. Staff Receivables Data
        $staffWithBills = User::where('is_admin', '!=', 19)
            ->whereHas('staff')
            ->whereHas('staffBills', function ($q) {
                $q->where('outstanding_amount', '>', 0);
            })
            ->with(['staffBills' => function ($q) {
                $q->where('outstanding_amount', '>', 0)->with(['patient.user', 'checkoutPayment']);
            }, 'staff'])
            ->get()
            ->map(function ($user) {
                $user->total_outstanding = $user->staffBills->sum('outstanding_amount');
                return $user;
            });

        $allStaffBills = StaffBill::with([
                'patient.user',
                'staffUser.staff',
                'checkoutPayment',
                'payments.bank',
                'payments.journalEntry.lines.account'
            ])
            ->orderBy('created_at', 'desc')
            ->limit(150)
            ->get();

        $activeBanks = Bank::active()->get();

        // 2. Stamps for the Period
        $stamps = AuditStamp::with('auditor')
            ->whereBetween('stamped_at', [$startDate, $endDate])
            ->get()
            ->groupBy('responsibility_key');

        // 3. Module Calculations for all 13 worksheets
        
        // A. Cashier Performance
        $cashierSummary = DB::table('payments')
            ->select('user_id', 
                DB::raw('COUNT(*) as txn_count'),
                DB::raw('SUM(total) as total_collected'),
                DB::raw("SUM(CASE WHEN payment_method = 'CASH' THEN total ELSE 0 END) as cash_collected"),
                DB::raw("SUM(CASE WHEN payment_method IN ('POS', 'CARD', 'BANK_TRANSFER', 'TRANSFER') THEN total ELSE 0 END) as bank_collected"),
                DB::raw("SUM(CASE WHEN payment_method = 'BILL_TO_STAFF' THEN total ELSE 0 END) as staff_receivable")
            )
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('user_id')
            ->get()
            ->map(function ($row) {
                $cashier = User::find($row->user_id);
                $row->cashier_name = $cashier ? $this->formatStaffNameThree($cashier) : 'Unknown Cashier';
                return $row;
            });

        // B. HMO claims nhis matching
        $hmoClaims = DB::table('product_or_service_requests as posr')
            ->join('hmos', 'posr.hmo_id', '=', 'hmos.id')
            ->join('hmo_schemes as hs', 'hmos.hmo_scheme_id', '=', 'hs.id')
            ->select('hmos.name as hmo_name',
                DB::raw('COUNT(*) as claim_count'),
                DB::raw('SUM(posr.payable_amount) as total_payable'),
                DB::raw('SUM(posr.claims_amount) as total_claim')
            )
            ->where(function($q) {
                $q->where('hs.name', 'LIKE', '%NHIS%')
                  ->orWhere('hs.name', 'LIKE', '%NHIA%')
                  ->orWhere('hs.name', 'LIKE', '%SHIS%')
                  ->orWhere('hs.name', 'LIKE', '%PLASCHEMA%')
                  ->orWhere('hs.code', 'LIKE', '%NHIS%')
                  ->orWhere('hs.code', 'LIKE', '%NHIA%')
                  ->orWhere('hs.code', 'LIKE', '%SHIS%')
                  ->orWhere('hs.code', 'LIKE', '%PLASCHEMA%');
            })
            ->whereBetween('posr.created_at', [$startDate, $endDate])
            ->groupBy('hmos.name')
            ->get();

        // C. Payroll breakdown
        $payrollBreakdown = Staff::with(['user', 'department', 'salaryProfiles' => function($q) {
                $q->where('is_active', true);
            }])
            ->where('status', 'active')
            ->whereHas('department', function($q) {
                $q->where('name', 'NOT LIKE', '%midwifery%');
            })
            ->get()
            ->groupBy(fn($item) => $item->department->name ?? 'Unassigned')
            ->map(function($staffList) {
                return [
                    'count' => $staffList->count(),
                    'basic_salary' => $staffList->sum(fn($s) => optional($s->salaryProfiles->first())->basic_salary ?? 0),
                    'gross_salary' => $staffList->sum(fn($s) => optional($s->salaryProfiles->first())->gross_salary ?? 0),
                    'net_salary' => $staffList->sum(fn($s) => optional($s->salaryProfiles->first())->net_salary ?? 0),
                ];
            });

        // D. Consulting Queue count
        $consultingQueues = DB::table('doctor_appointments')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('status')
            ->get();

        // E. Inpatient Stays
        $inpatientCount = DB::table('admission_requests')
            ->where('status', 'admitted')
            ->count();

        $occupiedBedsCount = DB::table('beds')
            ->where('status', 'occupied')
            ->count();

        $totalBedsCount = max(DB::table('beds')->count(), 1);

        // F. Theatre Procedures
        $theatreBundles = DB::table('procedure_items')
            ->where('is_bundled', true)
            ->count();

        // G. Morgue Releases
        $morgueCount = DB::table('morgue_admissions')->count();

        // H. Lab and Radiology requisitions vs billed
        $labStoresRequisitions = DB::table('store_requisitions')
            ->join('stores as to_store', 'store_requisitions.to_store_id', '=', 'to_store.id')
            ->select(DB::raw('COUNT(*) as req_count'))
            ->where(function($q) {
                $q->where('to_store.store_name', 'LIKE', '%LAB%')
                  ->orWhere('to_store.store_name', 'LIKE', '%x-ray%')
                  ->orWhere('to_store.store_name', 'LIKE', '%scan%');
            })
            ->whereBetween('store_requisitions.created_at', [$startDate, $endDate])
            ->first();

        $labServiceCount = LabServiceRequest::whereBetween('created_at', [$startDate, $endDate])->count();
        $imagingServiceCount = ImagingServiceRequest::whereBetween('created_at', [$startDate, $endDate])->count();

        $reconciliationKPIs = [
            'total_cash_collected' => DB::table('payments')->where('payment_method', 'CASH')->whereBetween('created_at', [$startDate, $endDate])->sum('total'),
            'total_pos_collected' => DB::table('payments')->whereIn('payment_method', ['POS', 'TRANSFER', 'BANK_TRANSFER'])->whereBetween('created_at', [$startDate, $endDate])->sum('total'),
            'total_staff_receivables' => StaffBill::whereBetween('created_at', [$startDate, $endDate])->sum('total_amount'),
            'unpaid_staff_receivables' => StaffBill::where('status', 'pending')->sum('outstanding_amount'),
            'reconciled_stamps_count' => AuditStamp::whereBetween('stamped_at', [$startDate, $endDate])->count()
        ];

        $responsibilities = self::$responsibilities;

        return view('admin.audit.workbench', compact(
            'startDate', 'endDate',
            'staffWithBills', 'allStaffBills', 'activeBanks', 'stamps',
            'cashierSummary', 'hmoClaims', 'payrollBreakdown',
            'consultingQueues', 'inpatientCount', 'occupiedBedsCount', 'totalBedsCount',
            'theatreBundles', 'morgueCount', 'labStoresRequisitions',
            'labServiceCount', 'imagingServiceCount', 'reconciliationKPIs',
            'responsibilities'
        ));
    }

    /**
     * POST settle outstanding staff bills
     */
    public function settleBills(Request $request)
    {
        if (!auth()->user()->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin', 'ACCOUNTS', 'accounts', 'AUDITOR', 'auditor'])) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'staff_id' => 'required|exists:users,id',
            'bill_ids' => 'required|array',
            'bill_ids.*' => 'exists:staff_bills,id',
            'payment_method' => 'required|in:CASH,POS,TRANSFER,MOBILE',
            'bank_id' => 'required_if:payment_method,POS,TRANSFER,MOBILE|nullable|exists:banks,id',
            'amount_paid' => 'required|numeric|min:0.01',
            'discount_amount' => 'nullable|numeric|min:0',
        ]);

        $staffId = $request->staff_id;
        $billIds = $request->bill_ids;
        $paymentMethod = $request->payment_method;
        $bankId = $request->bank_id;
        $amountPaid = floatval($request->amount_paid);
        $discountAmount = floatval($request->discount_amount ?? 0);

        $staff = User::findOrFail($staffId);
        $bills = StaffBill::whereIn('id', $billIds)
            ->where('staff_user_id', $staffId)
            ->where('outstanding_amount', '>', 0)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($bills->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No outstanding bills found for settlement.'], 422);
        }

        // Create the clearing payment transaction in database
        $payment = DB::transaction(function() use ($bills, $staff, $paymentMethod, $bankId, $amountPaid, $discountAmount) {
            $ref = 'SETTL-' . strtoupper(uniqid());
            $patients = $bills->map(fn($b) => $b->patient?->fullname ?? 'N/A')->unique()->implode(', ');

            $payment = Payment::create([
                'payment_type' => 'STAFF_BILL_SETTLEMENT',
                'payment_method' => $paymentMethod,
                'bank_id' => $bankId,
                'total' => $amountPaid,
                'total_discount' => $discountAmount,
                'reference_no' => $ref,
                'status' => 'settled',
                'user_id' => auth()->id(),
                'notes' => 'Settlement of Staff Bills for patients: ' . $patients . ($discountAmount > 0 ? " (with discount of ₦" . number_format($discountAmount, 2) . ")" : ""),
            ]);

            // Allocate amount sequentially across selected bills
            $remainingPayment = $amountPaid;
            $remainingDiscount = $discountAmount;

            foreach ($bills as $bill) {
                if ($remainingPayment <= 0 && $remainingDiscount <= 0) {
                    break;
                }

                $outstanding = floatval($bill->outstanding_amount);
                
                // Max we can allocate to this bill is its outstanding amount
                $allocatedDiscount = min($remainingDiscount, $outstanding);
                $remainingForPayment = $outstanding - $allocatedDiscount;
                $allocatedPayment = min($remainingPayment, $remainingForPayment);

                $totalAllocated = $allocatedDiscount + $allocatedPayment;

                $bill->outstanding_amount = $outstanding - $totalAllocated;
                $bill->discount_amount = floatval($bill->discount_amount) + $allocatedDiscount;

                if ($bill->outstanding_amount <= 0) {
                    $bill->status = 'paid';
                    $bill->settled_at = now();
                } else {
                    $bill->status = 'pending';
                }

                $bill->settlement_payment_id = $payment->id;
                $bill->save();

                DB::table('staff_bill_payment_allocations')->insert([
                    'staff_bill_id'      => $bill->id,
                    'payment_id'        => $payment->id,
                    'amount_allocated'   => $allocatedPayment,
                    'discount_allocated' => $allocatedDiscount,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                $remainingDiscount -= $allocatedDiscount;
                $remainingPayment -= $allocatedPayment;
            }

            return $payment;
        });

        return response()->json([
            'success' => true,
            'message' => 'Staff bills settled successfully. Double-entry ledger updated.',
            'payment_id' => $payment->id,
            'reference' => $payment->reference_no,
        ]);
    }

    /**
     * POST stamp/approve a period for a responsibility
     */
    public function stampPeriod(Request $request)
    {
        if (!auth()->user()->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin']) && !auth()->user()->hasRole('AUDITOR')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'responsibility_key' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $stamp = AuditStamp::create([
            'user_id' => auth()->id(),
            'responsibility_key' => $request->responsibility_key,
            'from_date' => $request->start_date,
            'to_date' => $request->end_date,
            'status' => 'approved',
            'notes' => $request->notes,
            'stamped_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Audit stamp applied successfully for the selected period.',
            'stamp' => $stamp->load('auditor'),
        ]);
    }

    /**
     * GET stamp history
     */
    public function stampHistory()
    {
        $stamps = AuditStamp::with('auditor')
            ->orderBy('stamped_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'stamps' => $stamps
        ]);
    }

    /**
     * GET dynamic audit report view for any of the 33 responsibilities
     */
    public function showReport(Request $request, $responsibility_key)
    {
        if (!auth()->user()->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin']) && !auth()->user()->hasRole('AUDITOR')) {
            abort(403, 'Unauthorized access to Internal Audit.');
        }

        $startDate = $request->filled('start_date') ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->filled('end_date') ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        // 1. Resolve category label and name
        $categoryLabel = 'Operational';
        $reportLabel = 'Audit Worksheet';
        foreach (self::$responsibilities as $cat => $list) {
            if (isset($list[$responsibility_key])) {
                $categoryLabel = ucfirst($cat);
                $reportLabel = $list[$responsibility_key];
                break;
            }
        }

        // Check if period stamp is already applied
        $stamp = AuditStamp::where('responsibility_key', $responsibility_key)
            ->where('from_date', $startDate->format('Y-m-d'))
            ->where('to_date', $endDate->format('Y-m-d'))
            ->first();

        // 2. Build report data dynamically based on key
        $kpis = [];
        $headers = [];
        $rows = [];
        $chart = [
            'labels' => [],
            'datasets' => []
        ];
        $tabbedData = [];
        $filters = [];

        // Selective high-performance lookup loading based on active worksheet
        $cashierOptions = [];
        $clinicOptions = [];
        $doctorOptions = [];
        $wardOptions = [];
        $hmoOptions = [];
        $storeOptions = [];
        $categoryOptions = [];
        $bankOptions = [];

        $responsibility_key = trim($responsibility_key);

        if ($responsibility_key === 'cash_and_billing_audit' || $responsibility_key === 'discounts_refunds_debt') {
            // Use raw DB query instead of Eloquent to avoid Model hydration memory overhead
            $cashiers = DB::table('users')->select('id', 'surname', 'firstname')->orderBy('surname')->get();
            foreach ($cashiers as $c) {
                $cashierOptions[$c->id] = trim($c->surname . ' ' . $c->firstname);
            }
            unset($cashiers);
        }
        
        if ($responsibility_key === 'bank_reconciliation') {
            $banks = \App\Models\Bank::select('id', 'name')->orderBy('name')->get();
            foreach ($banks as $b) {
                $bankOptions[$b->id] = $b->name;
            }
        }
        
        if ($responsibility_key === 'hmo_nhis_verification') {
            $hmos = \App\Models\Hmo::select('id', 'name')->orderBy('name')->get();
            foreach ($hmos as $h) {
                $hmoOptions[$h->id] = $h->name;
            }
        }

        if ($responsibility_key === 'consulting_clinics_flow') {
            $clinics = \App\Models\Clinic::select('id', 'name')->orderBy('name')->get();
            foreach ($clinics as $c) {
                $clinicOptions[$c->id] = $c->name;
            }
        }

        if ($responsibility_key === 'consulting_clinics_flow' || $responsibility_key === 'theatre_bundles_audit') {
            $doctors = User::select('id', 'surname', 'firstname')->orderBy('surname')->get();
            foreach ($doctors as $d) {
                $doctorOptions[$d->id] = trim($d->surname . ' ' . $d->firstname);
            }
        }
        
        if ($responsibility_key === 'inpatient_ward_income') {
            $wards = \App\Models\Ward::select('id', 'name')->orderBy('name')->get();
            foreach ($wards as $w) {
                $wardOptions[$w->id] = $w->name;
            }
        }
        
        if ($responsibility_key === 'pharmacy_prescriptions') {
            $pStores = \App\Models\Store::whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE])->get();
            foreach ($pStores as $s) {
                $storeOptions[$s->id] = $s->store_name;
            }
        }

        if ($responsibility_key === 'laboratory_register') {
            $labStores = \App\Models\Store::where('distribution_role', \App\Models\Store::ROLE_LAB)->get();
            foreach ($labStores as $s) {
                $storeOptions[$s->id] = $s->store_name;
            }
        }

        if ($responsibility_key === 'imaging_register') {
            $imgStores = \App\Models\Store::where('distribution_role', \App\Models\Store::ROLE_IMAGING)->get();
            foreach ($imgStores as $s) {
                $storeOptions[$s->id] = $s->store_name;
            }
        }

        if ($responsibility_key === 'central_store_stock_check' || $responsibility_key === 'departmental_ward_stores') {
            $stores = Store::where('distribution_role', '!=', \App\Models\Store::ROLE_CENTRAL)->select('id', 'store_name')->orderBy('store_name')->get();
            foreach ($stores as $s) {
                $storeOptions[$s->id] = $s->store_name;
            }
            $categories = \App\Models\ProductCategory::select('id', 'category_name')->orderBy('category_name')->get();
            foreach ($categories as $cat) {
                $categoryOptions[$cat->id] = $cat->category_name;
            }
        }

        switch ($responsibility_key) {


            case 'cash_and_billing_audit':
                // 0. Set up Context-Aware Filters
                $filters = [
                    [
                        'name' => 'payment_method',
                        'label' => 'Payment Method',
                        'type' => 'select',
                        'options' => ['CASH' => 'Cash', 'POS' => 'POS', 'TRANSFER' => 'Bank Transfer', 'CHEQUE' => 'Cheque'],
                        'value' => $request->get('payment_method')
                    ],
                    [
                        'name' => 'cashier_id',
                        'label' => 'Cashier',
                        'type' => 'select',
                        'options' => $cashierOptions,
                        'value' => $request->get('cashier_id')
                    ],
                    [
                        'name' => 'min_amount',
                        'label' => 'Min Amount',
                        'type' => 'number',
                        'value' => $request->get('min_amount')
                    ],
                    [
                        'name' => 'max_amount',
                        'label' => 'Max Amount',
                        'type' => 'number',
                        'value' => $request->get('max_amount')
                    ]
                ];

                $method = $request->get('payment_method');
                $cashierId = $request->get('cashier_id');
                $minAmount = $request->get('min_amount');
                $maxAmount = $request->get('max_amount');

                // 1. Database-Level Sums for KPIs (Filtered, Extremely Fast)
                $kpiPayments = DB::table('payments')->whereBetween('created_at', [$startDate, $endDate]);
                $kpiDeposits = DB::table('patient_deposits')->whereBetween('deposit_date', [$startDate, $endDate]);

                if ($method) {
                    $kpiPayments->where('payment_method', $method);
                    $kpiDeposits->where('payment_method', $method);
                }
                if ($cashierId) {
                    $kpiPayments->where('user_id', $cashierId);
                    $kpiDeposits->where('received_by', $cashierId);
                }
                if ($minAmount) {
                    $kpiPayments->where('total', '>=', $minAmount);
                    $kpiDeposits->where('amount', '>=', $minAmount);
                }
                if ($maxAmount) {
                    $kpiPayments->where('total', '<=', $maxAmount);
                    $kpiDeposits->where('amount', '<=', $maxAmount);
                }

                $paymentsStats = $kpiPayments->selectRaw("
                        SUM(total) as gross_payments,
                        SUM(CASE WHEN payment_type = 'REGISTRATION' THEN total ELSE 0 END) as reg_fees
                    ")->first();
                $grossPayments = $paymentsStats->gross_payments ?? 0;
                $regFees = $paymentsStats->reg_fees ?? 0;
                $grossDeposits = $kpiDeposits->sum('amount');

                $leakageStats = DB::table('product_or_service_requests')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->whereNull('payment_id')
                    ->whereNull('invoice_id')
                    ->whereRaw('NOT ((payable_amount IS NULL OR payable_amount = 0) AND (claims_amount > 0 AND validation_status = ?))', ['approved'])
                    ->where(function($q) {
                        $q->whereNull('hmo_id')->orWhere('hmo_id', 1)->orWhere('coverage_mode', 'cash');
                    })
                    ->selectRaw("SUM(CASE WHEN payable_amount > 0 THEN payable_amount ELSE amount END) as total_leakage")
                    ->first();
                $leakageTotal = $leakageStats->total_leakage ?? 0;

                // 2. Row data is ONLY needed for AJAX (DataTables server-side) requests.
                //    The initial page load only renders KPIs + tab headers; <tbody> is empty.
                //    Deferring row-building to AJAX saves ~60MB on the initial page load.
                $receiptRows = [];
                $leakageRows = [];

                if ($request->ajax()) {
                    $paymentsQueryBuilder = DB::table('payments')
                        ->leftJoin('patients', 'payments.patient_id', '=', 'patients.id')
                        ->leftJoin('users as patient_user', 'patients.user_id', '=', 'patient_user.id')
                        ->leftJoin('users as cashier_user', 'payments.user_id', '=', 'cashier_user.id')
                        ->whereBetween('payments.created_at', [$startDate, $endDate]);

                    $depositsQueryBuilder = DB::table('patient_deposits')
                        ->leftJoin('patients', 'patient_deposits.patient_id', '=', 'patients.id')
                        ->leftJoin('users as patient_user', 'patients.user_id', '=', 'patient_user.id')
                        ->leftJoin('users as receiver_user', 'patient_deposits.received_by', '=', 'receiver_user.id')
                        ->whereBetween('patient_deposits.deposit_date', [$startDate, $endDate]);

                    if ($method) {
                        $paymentsQueryBuilder->where('payments.payment_method', $method);
                        $depositsQueryBuilder->where('patient_deposits.payment_method', $method);
                    }
                    if ($cashierId) {
                        $paymentsQueryBuilder->where('payments.user_id', $cashierId);
                        $depositsQueryBuilder->where('patient_deposits.received_by', $cashierId);
                    }
                    if ($minAmount) {
                        $paymentsQueryBuilder->where('payments.total', '>=', $minAmount);
                        $depositsQueryBuilder->where('patient_deposits.amount', '>=', $minAmount);
                    }
                    if ($maxAmount) {
                        $paymentsQueryBuilder->where('payments.total', '<=', $maxAmount);
                        $depositsQueryBuilder->where('patient_deposits.amount', '<=', $maxAmount);
                    }

                    $paymentsQuery = $paymentsQueryBuilder->select([
                            'payments.id',
                            'payments.reference_no',
                            'payments.payment_type',
                            'payments.payment_method',
                            'payments.total',
                            'payments.created_at',
                            'payments.patient_id',
                            'cashier_user.surname as cashier_surname',
                            'cashier_user.firstname as cashier_firstname',
                            'cashier_user.othername as cashier_othername',
                            'patient_user.surname as patient_surname',
                            'patient_user.firstname as patient_firstname',
                            'patient_user.othername as patient_othername',
                            'patients.file_no as patient_file_no'
                        ])
                        ->orderBy('payments.created_at', 'desc')
                        ->limit(500)
                        ->get();

                    $depositsQuery = $depositsQueryBuilder->select([
                            'patient_deposits.id',
                            'patient_deposits.deposit_number',
                            'patient_deposits.payment_method',
                            'patient_deposits.amount',
                            'patient_deposits.deposit_date',
                            'patient_deposits.patient_id',
                            'receiver_user.surname as receiver_surname',
                            'receiver_user.firstname as receiver_firstname',
                            'receiver_user.othername as receiver_othername',
                            'patient_user.surname as patient_surname',
                            'patient_user.firstname as patient_firstname',
                            'patient_user.othername as patient_othername',
                            'patients.file_no as patient_file_no'
                        ])
                        ->orderBy('patient_deposits.deposit_date', 'desc')
                        ->limit(500)
                        ->get();

                    $receipts = collect();
                    foreach ($paymentsQuery as $p) {
                        $receipts->push([
                            'reference' => $p->reference_no ?? 'N/A',
                            'cashier' => $this->formatStaffRawName($p->cashier_surname, $p->cashier_firstname, $p->cashier_othername),
                            'patient' => $this->formatPatientNameLink($p->patient_id, $p->patient_surname, $p->patient_firstname, $p->patient_othername, $p->patient_file_no),
                            'type' => ucfirst(str_replace('_', ' ', $p->payment_type ?? 'N/A')),
                            'method' => $p->payment_method ?? 'N/A',
                            'amount' => $p->total,
                            'date' => Carbon::parse($p->created_at)->format('Y-m-d H:i'),
                            'datetime' => $p->created_at
                        ]);
                    }

                    foreach ($depositsQuery as $d) {
                        $receipts->push([
                            'reference' => $d->deposit_number ?? 'N/A',
                            'cashier' => $this->formatStaffRawName($d->receiver_surname, $d->receiver_firstname, $d->receiver_othername),
                            'patient' => $this->formatPatientNameLink($d->patient_id, $d->patient_surname, $d->patient_firstname, $d->patient_othername, $d->patient_file_no),
                            'type' => 'Account Deposit',
                            'method' => $d->payment_method ?? 'N/A',
                            'amount' => $d->amount,
                            'date' => Carbon::parse($d->deposit_date)->format('Y-m-d H:i'),
                            'datetime' => $d->deposit_date
                        ]);
                    }

                    $receipts = $receipts->sortByDesc('datetime')->take(500);

                    foreach ($receipts as $r) {
                        $receiptRows[] = [
                            $r['reference'],
                            $r['cashier'],
                            $r['patient'],
                            $r['type'],
                            $r['method'],
                            '₦' . number_format($r['amount'], 2),
                            $r['date']
                        ];
                    }
                    unset($paymentsQuery, $depositsQuery, $receipts);

                    // Revenue Leakage rows
                    $leakageQuery = DB::table('product_or_service_requests as posr')
                        ->leftJoin('patients as pt', 'posr.patient_id', '=', 'pt.id')
                        ->leftJoin('users as u', 'pt.user_id', '=', 'u.id')
                        ->leftJoin('products as pr', 'posr.product_id', '=', 'pr.id')
                        ->leftJoin('services as sv', 'posr.service_id', '=', 'sv.id')
                        ->whereBetween('posr.created_at', [$startDate, $endDate])
                        ->whereNull('posr.payment_id')
                        ->whereNull('posr.invoice_id')
                        ->whereRaw('NOT ((posr.payable_amount IS NULL OR posr.payable_amount = 0) AND (posr.claims_amount > 0 AND posr.validation_status = ?))', ['approved'])
                        ->where(function($q) {
                            $q->whereNull('posr.hmo_id')->orWhere('posr.hmo_id', 1)->orWhere('posr.coverage_mode', 'cash');
                        })
                        ->select([
                            'posr.id',
                            'posr.payable_amount',
                            'posr.amount',
                            'posr.discount',
                            'posr.created_at',
                            'posr.patient_id',
                            'u.surname as user_surname',
                            'u.firstname as user_firstname',
                            'u.othername as user_othername',
                            'pt.file_no as patient_file_no',
                            'pr.product_name as product_name',
                            'sv.service_name as service_name'
                        ])
                        ->orderBy('posr.created_at', 'desc')
                        ->limit(500)
                        ->get();

                    foreach ($leakageQuery as $r) {
                        $amt = $r->payable_amount > 0 ? $r->payable_amount : $r->amount;
                        $itemName = $r->product_name ?: ($r->service_name ?: 'N/A');
                        $patientName = $this->formatPatientNameLink($r->patient_id, $r->user_surname, $r->user_firstname, $r->user_othername, $r->patient_file_no);

                        $leakageRows[] = [
                            $r->id,
                            $patientName,
                            $itemName,
                            '₦' . number_format($r->amount, 2),
                            '₦' . number_format($r->discount ?? 0, 2),
                            '<span class="text-danger font-weight-bold">₦' . number_format($amt, 2) . '</span>',
                            Carbon::parse($r->created_at)->format('Y-m-d H:i')
                        ];
                    }
                    unset($leakageQuery);
                } // end if ($request->ajax())

                $kpis = [
                    ['label' => 'Gross Collections (Payments)', 'value' => '₦' . number_format($grossPayments, 2), 'class' => 'text-success'],
                    ['label' => 'Total Account Deposits', 'value' => '₦' . number_format($grossDeposits, 2), 'class' => 'text-info'],
                    ['label' => 'Registration Fees', 'value' => '₦' . number_format($regFees, 2), 'class' => 'text-primary'],
                    ['label' => 'Unbilled Value (Leakage)', 'value' => '₦' . number_format($leakageTotal, 2), 'class' => 'text-danger']
                ];

                $tabbedData = [
                    'unified_receipts' => [
                        'label' => 'Unified Daily Receipts (Showing recent 500)',
                        'headers' => ['Reference No', 'Cashier', 'Patient', 'Type', 'Method', 'Amount', 'Date'],
                        'rows' => $receiptRows
                    ],
                    'revenue_leakage' => [
                        'label' => 'Unbilled Self/Private Services (Showing recent 500)',
                        'headers' => ['Req ID', 'Patient', 'Item', 'Original Price', 'Discount', 'Leakage Value', 'Date'],
                        'rows' => $leakageRows
                    ]
                ];
                break;

            case 'bank_reconciliation':
                $filters = [
                    [
                        'name' => 'bank_id',
                        'label' => 'Bank Account',
                        'type' => 'select',
                        'options' => $bankOptions,
                        'value' => $request->get('bank_id')
                    ],
                    [
                        'name' => 'status',
                        'label' => 'Status',
                        'type' => 'select',
                        'options' => ['draft' => 'Draft', 'finalized' => 'Finalized'],
                        'value' => $request->get('status')
                    ],
                    [
                        'name' => 'min_amount',
                        'label' => 'Min Amount',
                        'type' => 'number',
                        'value' => $request->get('min_amount')
                    ],
                    [
                        'name' => 'payment_method',
                        'label' => 'Payment Method',
                        'type' => 'select',
                        'options' => ['POS' => 'POS', 'TRANSFER' => 'Bank Transfer', 'BANK_TRANSFER' => 'Bank Transfer'],
                        'value' => $request->get('payment_method')
                    ]
                ];

                $bankId = $request->get('bank_id');
                $status = $request->get('status');
                $minAmount = $request->get('min_amount');
                $method = $request->get('payment_method');

                $reconciliationsQuery = \App\Models\Accounting\BankReconciliation::with(['bank', 'preparedBy', 'fiscalPeriod'])
                    ->whereBetween('statement_date', [$startDate, $endDate]);

                $bankDepositsQuery = \App\Models\Payment::with(['patient.user', 'staff_user', 'bank'])
                    ->whereIn('payment_method', ['POS', 'TRANSFER', 'BANK_TRANSFER'])
                    ->whereBetween('created_at', [$startDate, $endDate]);

                if ($bankId) {
                    $reconciliationsQuery->where('bank_id', $bankId);
                    $bankDepositsQuery->where('bank_id', $bankId);
                }
                if ($status) {
                    $reconciliationsQuery->where('status', $status);
                }
                if ($minAmount) {
                    $reconciliationsQuery->where('statement_closing_balance', '>=', $minAmount);
                    $bankDepositsQuery->where('total', '>=', $minAmount);
                }
                if ($method) {
                    $bankDepositsQuery->where('payment_method', $method);
                }

                $reconciliations = $reconciliationsQuery->orderBy('statement_date', 'desc')->get();
                $bankDeposits = $bankDepositsQuery->orderBy('created_at', 'desc')->get();

                $kpis = [
                    ['label' => 'Bank/POS Collections', 'value' => '₦' . number_format($bankDeposits->sum('total'), 2), 'class' => 'text-success'],
                    ['label' => 'Audited Variance', 'value' => '₦' . number_format($reconciliations->sum('variance'), 2), 'class' => 'text-danger'],
                    ['label' => 'Reconciled Statements', 'value' => $reconciliations->where('status', 'finalized')->count() . ' Finalized', 'class' => 'text-info']
                ];

                $reconciliationRows = [];
                foreach ($reconciliations as $r) {
                    $reconciliationRows[] = [
                        $r->reconciliation_number ?? 'N/A',
                        $r->bank ? $r->bank->name : 'N/A',
                        $r->fiscalPeriod ? $r->fiscalPeriod->name : 'N/A',
                        '₦' . number_format($r->statement_closing_balance ?? 0, 2),
                        '₦' . number_format($r->gl_closing_balance ?? 0, 2),
                        '₦' . number_format($r->variance ?? 0, 2),
                        ucfirst($r->status),
                        $r->statement_date ? $r->statement_date->format('Y-m-d') : 'N/A'
                    ];
                }

                $depositRows = [];
                foreach ($bankDeposits as $p) {
                    $depositRows[] = [
                        $p->reference_no ?? 'N/A',
                        $p->bank ? $p->bank->name : 'N/A',
                        $this->formatStaffNameThree($p->staff_user),
                        $this->formatPatientModelLink($p->patient),
                        '₦' . number_format($p->total, 2),
                        $p->created_at->format('Y-m-d H:i')
                    ];
                }

                $tabbedData = [
                    'bank_reconciliations' => [
                        'label' => 'Bank Reconciliations',
                        'headers' => ['Reconciliation No', 'Bank Name', 'Period', 'Statement Closing', 'GL Closing', 'Variance', 'Status', 'Date'],
                        'rows' => $reconciliationRows
                    ],
                    'bank_deposits' => [
                        'label' => 'POS/Bank Collections',
                        'headers' => ['Reference No', 'Bank Account', 'Cashier', 'Patient', 'Amount', 'Transaction Date'],
                        'rows' => $depositRows
                    ]
                ];
                break;

            case 'hmo_nhis_verification':
                $filters = [
                    [
                        'name' => 'hmo_id',
                        'label' => 'HMO Scheme',
                        'type' => 'select',
                        'options' => $hmoOptions,
                        'value' => $request->get('hmo_id')
                    ],
                    [
                        'name' => 'validation_status',
                        'label' => 'Validation Status',
                        'type' => 'select',
                        'options' => ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'],
                        'value' => $request->get('validation_status')
                    ],
                    [
                        'name' => 'min_claims',
                        'label' => 'Min Claims Value',
                        'type' => 'number',
                        'value' => $request->get('min_claims')
                    ],
                    [
                        'name' => 'coverage_mode',
                        'label' => 'Coverage Mode',
                        'type' => 'select',
                        'options' => ['hmo' => 'HMO Scheme', 'nhis' => 'NHIS Scheme'],
                        'value' => $request->get('coverage_mode')
                    ]
                ];

                $hmoId = $request->get('hmo_id');
                $valStatus = $request->get('validation_status');
                $minClaims = $request->get('min_claims');
                $coverageMode = $request->get('coverage_mode');

                $claimsQuery = \App\Models\ProductOrServiceRequest::with(['user', 'patient.user', 'hmo.scheme', 'product', 'service'])
                    ->whereNotNull('hmo_id')
                    ->whereBetween('created_at', [$startDate, $endDate]);

                $remittancesQuery = \App\Models\HmoRemittance::with(['hmo.scheme'])
                    ->whereBetween('created_at', [$startDate, $endDate]);

                if ($hmoId) {
                    $claimsQuery->where('hmo_id', $hmoId);
                    $remittancesQuery->where('hmo_id', $hmoId);
                }
                if ($valStatus) {
                    $claimsQuery->where('validation_status', $valStatus);
                }
                if ($minClaims) {
                    $claimsQuery->where('claims_amount', '>=', $minClaims);
                    $remittancesQuery->where('amount', '>=', $minClaims);
                }
                if ($coverageMode) {
                    $claimsQuery->where('coverage_mode', $coverageMode);
                }

                $claims = $claimsQuery->orderBy('created_at', 'desc')->get();
                $remittances = $remittancesQuery->orderBy('created_at', 'desc')->get();

                $kpis = [
                    ['label' => 'Total HMO Claims Value', 'value' => '₦' . number_format($claims->sum('claims_amount'), 2), 'class' => 'text-purple'],
                    ['label' => 'Capitation / Remitted', 'value' => '₦' . number_format($remittances->sum('amount'), 2), 'class' => 'text-success'],
                    ['label' => 'Claims Count', 'value' => $claims->count() . ' Claims', 'class' => 'text-info']
                ];

                $claimsRows = [];
                foreach ($claims as $c) {
                    $claimsRows[] = [
                        $c->id,
                        $this->formatPatientUserLink($c->user, $c->patient),
                        $c->hmo ? $c->hmo->name : 'N/A',
                        $c->product ? ('Drug: '.$c->product->product_name) : ($c->service ? ('Service: '.$c->service->service_name) : 'N/A'),
                        '₦' . number_format($c->claims_amount, 2),
                        ucfirst($c->validation_status ?? 'pending'),
                        $c->created_at->format('Y-m-d H:i')
                    ];
                }

                $remittanceRows = [];
                foreach ($remittances as $r) {
                    $remittanceRows[] = [
                        $r->reference_number ?? 'N/A',
                        $r->hmo ? $r->hmo->name : 'N/A',
                        $r->hmo && $r->hmo->scheme ? $r->hmo->scheme->name : 'N/A',
                        '₦' . number_format($r->amount, 2),
                        $r->payment_method ?? 'N/A',
                        $r->payment_date ? $r->payment_date->format('Y-m-d') : 'N/A'
                    ];
                }

                $tabbedData = [
                    'hmo_claims' => [
                        'label' => 'HMO Services Billed',
                        'headers' => ['Request ID', 'Patient', 'HMO', 'Item', 'Claims Amount', 'Validation', 'Date'],
                        'rows' => $claimsRows
                    ],
                    'hmo_remittances' => [
                        'label' => 'Capitation & Remittances',
                        'headers' => ['Reference No', 'HMO', 'HMO Scheme', 'Amount Received', 'Payment Method', 'Date Received'],
                        'rows' => $remittanceRows
                    ]
                ];
                break;

            case 'discounts_refunds_debt':
                $filters = [
                    [
                        'name' => 'cashier_id',
                        'label' => 'Authorized By',
                        'type' => 'select',
                        'options' => $cashierOptions,
                        'value' => $request->get('cashier_id')
                    ],
                    [
                        'name' => 'min_amount',
                        'label' => 'Min Amount',
                        'type' => 'number',
                        'value' => $request->get('min_amount')
                    ],
                    [
                        'name' => 'refund_reason',
                        'label' => 'Refund Reason',
                        'type' => 'text',
                        'value' => $request->get('refund_reason')
                    ]
                ];

                $cashierId = $request->get('cashier_id');
                $minWaiver = $request->get('min_amount');
                $refundReason = $request->get('refund_reason');

                $checkoutQuery = \App\Models\Payment::with(['patient.user', 'staff_user'])
                    ->where('total_discount', '>', 0)
                    ->whereBetween('created_at', [$startDate, $endDate]);

                $staffQuery = \App\Models\StaffBill::with(['patient.user', 'staffUser'])
                    ->where('outstanding_amount', '>', 0);

                $refundedQuery = \App\Models\Accounting\PatientDeposit::with(['patient', 'refunder'])
                    ->where(fn($q) => $q->where('status', 'refunded')->orWhere('refunded_amount', '>', 0))
                    ->whereBetween('created_at', [$startDate, $endDate]);

                if ($cashierId) {
                    $checkoutQuery->where('user_id', $cashierId);
                    $staffQuery->where('staff_user_id', $cashierId);
                }
                if ($minWaiver) {
                    $checkoutQuery->where('total_discount', '>=', $minWaiver);
                    $staffQuery->where('outstanding_amount', '>=', $minWaiver);
                    $refundedQuery->where('refunded_amount', '>=', $minWaiver);
                }
                if ($refundReason) {
                    $refundedQuery->where('refund_reason', 'LIKE', '%' . $refundReason . '%');
                }

                $checkoutDiscounts = $checkoutQuery->orderBy('created_at', 'desc')->get();
                $staffDebts = $staffQuery->get();
                $refundedDeposits = $refundedQuery->orderBy('created_at', 'desc')->get();

                $kpis = [
                    ['label' => 'Checkout Waivers', 'value' => '₦' . number_format($checkoutDiscounts->sum('total_discount'), 2), 'class' => 'text-info'],
                    ['label' => 'Staff/Company Debt', 'value' => '₦' . number_format($staffDebts->sum('outstanding_amount'), 2), 'class' => 'text-danger'],
                    ['label' => 'Patient Refunds', 'value' => '₦' . number_format($refundedDeposits->sum('refunded_amount'), 2), 'class' => 'text-warning']
                ];

                $checkoutRows = [];
                foreach ($checkoutDiscounts as $p) {
                    $checkoutRows[] = [
                        $p->reference_no ?? 'N/A',
                        $this->formatPatientModelLink($p->patient),
                        $this->formatStaffNameThree($p->staff_user),
                        '₦' . number_format($p->total + $p->total_discount, 2),
                        '₦' . number_format($p->total_discount, 2),
                        $p->created_at->format('Y-m-d H:i')
                    ];
                }

                $staffRows = [];
                foreach ($staffDebts as $s) {
                    $staffRows[] = [
                        $s->id,
                        $this->formatStaffNameThree($s->staffUser),
                        $this->formatPatientModelLink($s->patient),
                        '₦' . number_format($s->total_amount ?? 0, 2),
                        '₦' . number_format($s->outstanding_amount, 2),
                        $s->created_at->format('Y-m-d H:i')
                    ];
                }
                
                $depositRows = [];
                foreach ($refundedDeposits as $d) {
                    $depositRows[] = [
                        $d->deposit_number ?? 'N/A',
                        $this->formatPatientModelLink($d->patient),
                        '₦' . number_format($d->amount, 2),
                        '₦' . number_format($d->refunded_amount, 2),
                        $d->refund_reason ?? 'N/A',
                        $d->refunded_at ? $d->refunded_at->format('Y-m-d H:i') : 'N/A'
                    ];
                }

                $tabbedData = [
                    'checkout_discounts' => [
                        'label' => 'Checkout Waivers',
                        'headers' => ['Reference No', 'Patient', 'Cashier', 'Gross Amount', 'Discount Applied', 'Date'],
                        'rows' => $checkoutRows
                    ],
                    'staff_debts' => [
                        'label' => 'Staff/Company Debt',
                        'headers' => ['Bill ID', 'Staff Member', 'Patient Name', 'Total Incurred', 'Outstanding Amount', 'Date'],
                        'rows' => $staffRows
                    ],
                    'refunded_deposits' => [
                        'label' => 'Refunded Deposits',
                        'headers' => ['Deposit No', 'Patient', 'Original Deposit', 'Refunded Amount', 'Reason', 'Refunded Date'],
                        'rows' => $depositRows
                    ]
                ];
                break;

            case 'payroll_expenses_ledger':
                $filters = [
                    [
                        'name' => 'category',
                        'label' => 'Expense Category',
                        'type' => 'select',
                        'options' => ['travel' => 'Travel & Transport', 'supplies' => 'Supplies & Logistics', 'utilities' => 'Utilities & Power', 'repairs' => 'Repairs & Maintenance', 'other' => 'Other Expenses'],
                        'value' => $request->get('category')
                    ],
                    [
                        'name' => 'status',
                        'label' => 'Status',
                        'type' => 'select',
                        'options' => ['pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid'],
                        'value' => $request->get('status')
                    ],
                    [
                        'name' => 'min_amount',
                        'label' => 'Min Amount',
                        'type' => 'number',
                        'value' => $request->get('min_amount')
                    ]
                ];

                $expenseCat = $request->get('category');
                $expenseStatus = $request->get('status');
                $minAmt = $request->get('min_amount');

                $batchesQuery = \App\Models\HR\PayrollBatch::with(['createdBy', 'approvedBy'])
                    ->whereBetween('created_at', [$startDate, $endDate]);

                $deductionsQuery = \App\Models\Accounting\StatutoryRemittance::with(['payHead', 'bank'])
                    ->whereBetween('created_at', [$startDate, $endDate]);

                $expensesQuery = \App\Models\Expense::with(['supplier', 'store', 'bank', 'recorder'])
                    ->whereBetween('expense_date', [$startDate, $endDate]);

                $pettyCashQuery = \App\Models\Accounting\PettyCashTransaction::with(['fund'])
                    ->whereBetween('transaction_date', [$startDate, $endDate]);

                if ($expenseCat) {
                    $expensesQuery->where('category', $expenseCat);
                }
                if ($expenseStatus) {
                    $expensesQuery->where('status', $expenseStatus);
                    $batchesQuery->where('status', $expenseStatus);
                    $deductionsQuery->where('status', $expenseStatus);
                }
                if ($minAmt) {
                    $expensesQuery->where('amount', '>=', $minAmt);
                    $batchesQuery->where('total_net', '>=', $minAmt);
                    $deductionsQuery->where('amount', '>=', $minAmt);
                    $pettyCashQuery->where('amount', '>=', $minAmt);
                }

                $batches = $batchesQuery->orderBy('created_at', 'desc')->get();
                $deductions = $deductionsQuery->orderBy('created_at', 'desc')->get();
                $expenses = $expensesQuery->orderBy('expense_date', 'desc')->get();
                $pettyCash = $pettyCashQuery->orderBy('transaction_date', 'desc')->get();

                $kpis = [
                    ['label' => 'Net Salaries Paid', 'value' => '₦' . number_format($batches->where('status', 'paid')->sum('total_net'), 2), 'class' => 'text-success'],
                    ['label' => 'Statutory Deductions', 'value' => '₦' . number_format($deductions->where('status', 'paid')->sum('amount'), 2), 'class' => 'text-info'],
                    ['label' => 'Operational Expenses', 'value' => '₦' . number_format($expenses->sum('amount'), 2), 'class' => 'text-warning'],
                    ['label' => 'Petty Cash Disbursed', 'value' => '₦' . number_format($pettyCash->where('transaction_type', 'disbursement')->sum('amount'), 2), 'class' => 'text-purple']
                ];

                $batchRows = [];
                foreach ($batches as $b) {
                    $batchRows[] = [
                        $b->batch_number ?? 'N/A',
                        $b->name ?? 'N/A',
                        $b->total_staff ?? 0,
                        '₦' . number_format($b->total_gross ?? 0, 2),
                        '₦' . number_format($b->total_net ?? 0, 2),
                        ucfirst($b->status),
                        $b->approved_at ? $b->approved_at->format('Y-m-d') : 'N/A'
                    ];
                }

                $deductionRows = [];
                foreach ($deductions as $d) {
                    $deductionRows[] = [
                        $d->reference_number ?? 'N/A',
                        $d->payHead ? $d->payHead->name : 'N/A',
                        '₦' . number_format($d->amount, 2),
                        ucfirst($d->status),
                        $d->remittance_date ? $d->remittance_date->format('Y-m-d') : 'N/A'
                    ];
                }
                
                $expenseRows = [];
                foreach ($expenses as $e) {
                    $expenseRows[] = [
                        $e->expense_number ?? 'N/A',
                        ucfirst(str_replace('_', ' ', $e->category ?? 'N/A')),
                        '₦' . number_format($e->amount, 2),
                        $e->supplier ? $e->supplier->name : 'N/A',
                        '<span class="badge badge-' . ($e->status === 'approved' ? 'success' : 'warning') . '">' . ucfirst($e->status) . '</span>',
                        $e->expense_date ? $e->expense_date->format('Y-m-d') : 'N/A'
                    ];
                }

                $tabbedData = [
                    'payroll_batches' => [
                        'label' => 'Payroll Batches',
                        'headers' => ['Batch No', 'Name', 'Total Staff', 'Gross Salary', 'Net Paid', 'Status', 'Date Approved'],
                        'rows' => $batchRows
                    ],
                    'statutory_deductions' => [
                        'label' => 'Statutory Deductions',
                        'headers' => ['Reference No', 'Deduction Type', 'Amount', 'Status', 'Remittance Date'],
                        'rows' => $deductionRows
                    ],
                    'operational_expenses' => [
                        'label' => 'Operational Expenses',
                        'headers' => ['Expense No', 'Category', 'Amount', 'Supplier', 'Status', 'Date'],
                        'rows' => $expenseRows
                    ]
                ];
                break;



            case 'consulting_clinics_flow':
                $filters = [
                    [
                        'name' => 'clinic_id',
                        'label' => 'Clinic',
                        'type' => 'select',
                        'options' => $clinicOptions,
                        'value' => $request->get('clinic_id')
                    ],
                    [
                        'name' => 'doctor_id',
                        'label' => 'Doctor',
                        'type' => 'select',
                        'options' => $doctorOptions,
                        'value' => $request->get('doctor_id')
                    ],
                    [
                        'name' => 'queue_status',
                        'label' => 'Queue Status',
                        'type' => 'select',
                        'options' => ['queued' => 'Queued', 'active' => 'Active', 'completed' => 'Completed', 'no-show' => 'No Show'],
                        'value' => $request->get('queue_status')
                    ],
                    [
                        'name' => 'priority',
                        'label' => 'Priority',
                        'type' => 'select',
                        'options' => ['normal' => 'Normal', 'emergency' => 'Emergency', 'vip' => 'VIP'],
                        'value' => $request->get('priority')
                    ]
                ];

                $clinicId = $request->get('clinic_id');
                $doctorId = $request->get('doctor_id');
                $queueStatus = $request->get('queue_status');
                $priority = $request->get('priority');

                $queuesQuery = \App\Models\DoctorQueue::with(['patient.user', 'clinic', 'doctor.user'])
                    ->whereBetween('created_at', [$startDate, $endDate]);

                $appointmentsQuery = \App\Models\DoctorAppointment::with(['patient.user', 'doctor.user'])
                    ->whereBetween('appointment_date', [$startDate, $endDate]);

                if ($clinicId) {
                    $queuesQuery->where('clinic_id', $clinicId);
                    $appointmentsQuery->where('clinic_id', $clinicId);
                }
                if ($doctorId) {
                    $queuesQuery->where('doctor_id', $doctorId);
                    $appointmentsQuery->where('doctor_id', $doctorId);
                }
                if ($queueStatus) {
                    $queuesQuery->where('status', $queueStatus);
                }
                if ($priority) {
                    $queuesQuery->where('priority', $priority);
                }

                $queues = $queuesQuery->orderBy('created_at', 'desc')->get();
                $appointments = $appointmentsQuery->orderBy('appointment_date', 'desc')->get();

                $queueRows = [];
                foreach ($queues as $q) {
                    $queueRows[] = [
                        $this->formatPatientModelLink($q->patient),
                        $q->clinic ? ($q->clinic->name ?? $q->clinic->clinic_name) : 'N/A',
                        ($q->doctor && $q->doctor->user) ? $this->formatStaffNameThree($q->doctor->user) : 'N/A',
                        \App\Enums\QueueStatus::badge($q->status),
                        $q->priority ?? 'N/A',
                        $q->created_at->format('Y-m-d H:i')
                    ];
                }

                $apptRows = [];
                foreach ($appointments as $a) {
                    $apptRows[] = [
                        $this->formatPatientModelLink($a->patient),
                        ($a->doctor && $a->doctor->user) ? $this->formatStaffNameThree($a->doctor->user) : 'N/A',
                        ucfirst($a->status ?? 'pending'),
                        $a->appointment_date ? $a->appointment_date->format('Y-m-d H:i') : 'N/A'
                    ];
                }

                $kpis = [
                    ['label' => 'Total Queued', 'value' => $queues->count(), 'class' => 'text-primary'],
                    ['label' => 'Completed Consults', 'value' => $queues->where('status', \App\Enums\QueueStatus::COMPLETED)->count(), 'class' => 'text-success'],
                    ['label' => 'No-Shows / Missed', 'value' => $queues->where('status', \App\Enums\QueueStatus::NO_SHOW)->count(), 'class' => 'text-danger'],
                    ['label' => 'Total Appointments', 'value' => $appointments->count(), 'class' => 'text-info']
                ];

                $tabbedData = [
                    'consulting_queue' => [
                        'label' => 'Consulting Queue',
                        'headers' => ['Patient', 'Clinic', 'Assigned Doctor', 'Status', 'Priority', 'Queued At'],
                        'rows' => $queueRows
                    ],
                    'appointments' => [
                        'label' => 'Appointments Register',
                        'headers' => ['Patient', 'Doctor', 'Status', 'Appointment Date'],
                        'rows' => $apptRows
                    ]
                ];
                break;

            case 'inpatient_ward_income':
                $filters = [
                    [
                        'name' => 'ward_id',
                        'label' => 'Ward',
                        'type' => 'select',
                        'options' => $wardOptions,
                        'value' => $request->get('ward_id')
                    ],
                    [
                        'name' => 'admission_status',
                        'label' => 'Admission Status',
                        'type' => 'select',
                        'options' => ['admitted' => 'Currently Admitted', 'discharge_pending' => 'Clearance Pending', 'discharged' => 'Discharged'],
                        'value' => $request->get('admission_status')
                    ],
                    [
                        'name' => 'min_amount',
                        'label' => 'Min Income Value',
                        'type' => 'number',
                        'value' => $request->get('min_amount')
                    ],
                    [
                        'name' => 'bed_type',
                        'label' => 'Bed Type',
                        'type' => 'select',
                        'options' => ['regular' => 'Regular', 'icu' => 'ICU', 'private' => 'Private'],
                        'value' => $request->get('bed_type')
                    ]
                ];

                $wardId = $request->get('ward_id');
                $admStatus = $request->get('admission_status');
                $minAmt = $request->get('min_amount');
                $bedType = $request->get('bed_type');

                $admissionsQuery = \App\Models\AdmissionRequest::with(['patient.user', 'preferredWard', 'bed.wardRelation'])
                    ->whereBetween('created_at', [$startDate, $endDate]);

                $activeAdmissionsQuery = \App\Models\AdmissionRequest::with(['patient.user', 'preferredWard', 'bed.wardRelation']);

                if ($admStatus === 'discharged') {
                    $activeAdmissionsQuery->where('discharged', 1);
                } elseif ($admStatus === 'discharge_pending') {
                    $activeAdmissionsQuery->where('discharged', 0)->where('discharge_requested', 1);
                } elseif ($admStatus === 'admitted') {
                    $activeAdmissionsQuery->where('discharged', 0);
                } else {
                    $activeAdmissionsQuery->where('discharged', 0);
                }

                if ($wardId) {
                    $activeAdmissionsQuery->where(function($q) use ($wardId) {
                        $q->where('preferred_ward_id', $wardId)
                          ->orWhereHas('bed.wardRelation', fn($bq) => $bq->where('id', $wardId));
                    });
                    $admissionsQuery->where(function($q) use ($wardId) {
                        $q->where('preferred_ward_id', $wardId)
                          ->orWhereHas('bed.wardRelation', fn($bq) => $bq->where('id', $wardId));
                    });
                }
                if ($bedType) {
                    $activeAdmissionsQuery->whereHas('bed', fn($bq) => $bq->where('bed_type', $bedType));
                    $admissionsQuery->whereHas('bed', fn($bq) => $bq->where('bed_type', $bedType));
                }

                $activeAdmissions = $activeAdmissionsQuery->get();
                $admissions = $admissionsQuery->get();

                $wardPaymentsQuery = \App\Models\Payment::with(['patient.user'])
                    ->whereIn('patient_id', function($query) use ($wardId) {
                        $query->select('patient_id')
                              ->from('admission_requests')
                              ->where('discharged', 0);
                        if ($wardId) {
                            $query->where('preferred_ward_id', $wardId);
                        }
                    })
                    ->whereBetween('created_at', [$startDate, $endDate]);

                if ($minAmt) {
                    $wardPaymentsQuery->where('total', '>=', $minAmt);
                }

                $wardPayments = $wardPaymentsQuery->get();
                
                $kpis = [
                    ['label' => 'Active Admissions', 'value' => $activeAdmissions->count(), 'class' => 'text-primary'],
                    ['label' => 'Discharges (Period)', 'value' => $admissions->where('discharged', 1)->count(), 'class' => 'text-success'],
                    ['label' => 'Pending Clearance', 'value' => $activeAdmissions->where('discharge_requested', 1)->count(), 'class' => 'text-warning'],
                    ['label' => 'Est. Ward Income', 'value' => '₦' . number_format($wardPayments->sum('total'), 2), 'class' => 'text-info']
                ];

                $activeRows = [];
                foreach ($activeAdmissions as $a) {
                    $activeRows[] = [
                        $this->formatPatientModelLink($a->patient),
                        ($a->bed && $a->bed->wardRelation) ? $a->bed->wardRelation->name : ($a->preferredWard ? $a->preferredWard->name : 'N/A'),
                        $a->bed ? $a->bed->bed_name : 'N/A',
                        $a->discharge_requested ? '<span class="badge badge-warning">Clearance Pending</span>' : '<span class="badge badge-success">Admitted</span>',
                        $a->created_at->format('Y-m-d H:i')
                    ];
                }

                $paymentRows = [];
                foreach ($wardPayments as $p) {
                    $paymentRows[] = [
                        $p->reference_no ?? 'N/A',
                        $this->formatPatientModelLink($p->patient),
                        '₦' . number_format($p->total, 2),
                        $p->payment_method ?? 'N/A',
                        $p->created_at->format('Y-m-d H:i')
                    ];
                }

                $tabbedData = [
                    'active_admissions' => [
                        'label' => 'Active Admissions & Clearances',
                        'headers' => ['Patient', 'Ward', 'Bed', 'Status', 'Admitted At'],
                        'rows' => $activeRows
                    ],
                    'ward_income' => [
                        'label' => 'Ward Income (Payments During Admission)',
                        'headers' => ['Reference', 'Patient', 'Amount', 'Method', 'Payment Date'],
                        'rows' => $paymentRows
                    ]
                ];
                break;

            case 'theatre_bundles_audit':
                $filters = [
                    [
                        'name' => 'surgeon_id',
                        'label' => 'Surgeon / Doctor',
                        'type' => 'select',
                        'options' => $doctorOptions,
                        'value' => $request->get('surgeon_id')
                    ],
                    [
                        'name' => 'procedure_status',
                        'label' => 'Procedure Status',
                        'type' => 'select',
                        'options' => ['scheduled' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled'],
                        'value' => $request->get('procedure_status')
                    ],
                    [
                        'name' => 'min_qty',
                        'label' => 'Min Consumables Qty',
                        'type' => 'number',
                        'value' => $request->get('min_qty')
                    ]
                ];

                $surgeonId = $request->get('surgeon_id');
                $procStatus = $request->get('procedure_status');
                $minQty = $request->get('min_qty');

                $proceduresQuery = \App\Models\Procedure::with(['patient.user', 'service', 'requestedByUser'])
                    ->whereBetween('created_at', [$startDate, $endDate]);

                $procedureItemsQuery = \App\Models\ProcedureItem::with(['procedure.patient.user', 'productRequest.product', 'labServiceRequest.service', 'imagingServiceRequest.service'])
                    ->where('is_bundled', 1)
                    ->whereBetween('created_at', [$startDate, $endDate]);

                if ($surgeonId) {
                    $proceduresQuery->where('requested_by', $surgeonId);
                    $procedureItemsQuery->whereHas('procedure', fn($pq) => $pq->where('requested_by', $surgeonId));
                }
                if ($procStatus) {
                    $proceduresQuery->where('status', $procStatus);
                    $procedureItemsQuery->whereHas('procedure', fn($pq) => $pq->where('status', $procStatus));
                }
                if ($minQty) {
                    $procedureItemsQuery->where('qty', '>=', $minQty);
                }

                $procedures = $proceduresQuery->orderBy('created_at', 'desc')->get();
                $procedureItems = $procedureItemsQuery->get();

                $kpis = [
                    ['label' => 'Total Procedures', 'value' => $procedures->count(), 'class' => 'text-primary'],
                    ['label' => 'Completed Procedures', 'value' => $procedures->where('status', 'completed')->count(), 'class' => 'text-success'],
                    ['label' => 'Bundled Items Used', 'value' => $procedureItems->sum('qty'), 'class' => 'text-warning'],
                    ['label' => 'Scheduled Procedures', 'value' => $procedures->where('status', 'scheduled')->count(), 'class' => 'text-info']
                ];

                $procRows = [];
                foreach ($procedures as $p) {
                    $procRows[] = [
                        $this->formatPatientModelLink($p->patient),
                        $p->service ? $p->service->service_name : 'N/A',
                        $this->formatStaffNameThree($p->requestedByUser),
                        '<span class="badge badge-primary">' . ucfirst($p->status) . '</span>',
                        $p->scheduled_date ? $p->scheduled_date->format('Y-m-d H:i') : 'N/A'
                    ];
                }

                $itemRows = [];
                foreach ($procedureItems as $item) {
                    $itemRows[] = [
                        $this->formatPatientModelLink($item->procedure ? $item->procedure->patient : null),
                        $item->name ?? 'N/A',
                        $item->qty,
                        $item->created_at->format('Y-m-d H:i')
                    ];
                }

                $tabbedData = [
                    'procedure_register' => [
                        'label' => 'Theatre Procedure Register',
                        'headers' => ['Patient', 'Procedure', 'Surgeon/Doctor', 'Status', 'Scheduled Date'],
                        'rows' => $procRows
                    ],
                    'bundled_consumables' => [
                        'label' => 'Bundled Consumables Consumption',
                        'headers' => ['Patient', 'Consumable Item', 'Quantity Used', 'Usage Date'],
                        'rows' => $itemRows
                    ]
                ];
                break;

            case 'maternity_morgue_audit':
                $filters = [
                    [
                        'name' => 'delivery_mode',
                        'label' => 'Delivery Mode',
                        'type' => 'select',
                        'options' => ['spontaneous' => 'Spontaneous Vaginal Delivery', 'caesarean' => 'Caesarean Section', 'assisted' => 'Assisted (Forceps/Vacuum)'],
                        'value' => $request->get('delivery_mode')
                    ],
                    [
                        'name' => 'morgue_status',
                        'label' => 'Morgue Status',
                        'type' => 'select',
                        'options' => ['admitted' => 'Currently Admitted', 'released' => 'Released'],
                        'value' => $request->get('morgue_status')
                    ],
                    [
                        'name' => 'delivery_outcome',
                        'label' => 'Delivery Outcome',
                        'type' => 'select',
                        'options' => ['live_birth' => 'Live Birth', 'still_birth' => 'Still Birth'],
                        'value' => $request->get('delivery_outcome')
                    ]
                ];

                $deliveryMode = $request->get('delivery_mode');
                $morgueStatus = $request->get('morgue_status');
                $outcome = $request->get('delivery_outcome');

                $enrollments = \App\Models\MaternityEnrollment::with(['patient.user'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();

                $deliveriesQuery = \App\Models\DeliveryRecord::with(['patient.user'])
                    ->whereBetween('delivery_date', [$startDate, $endDate]);

                $morgueQuery = \App\Models\MorgueAdmission::with(['patient.user'])
                    ->whereBetween('created_at', [$startDate, $endDate]);

                if ($deliveryMode) {
                    $deliveriesQuery->where('delivery_mode', $deliveryMode);
                }
                if ($morgueStatus) {
                    $morgueQuery->where('status', $morgueStatus);
                }
                if ($outcome) {
                    $deliveriesQuery->where('outcome', $outcome);
                }

                $deliveries = $deliveriesQuery->get();
                $morgue = $morgueQuery->get();

                $kpis = [
                    ['label' => 'New ANC Enrollments', 'value' => $enrollments->count(), 'class' => 'text-primary'],
                    ['label' => 'Total Deliveries', 'value' => $deliveries->count(), 'class' => 'text-success'],
                    ['label' => 'Morgue Admissions', 'value' => $morgue->count(), 'class' => 'text-dark']
                ];

                $deliveryRows = [];
                foreach ($deliveries as $d) {
                    $deliveryRows[] = [
                        $this->formatPatientModelLink($d->patient),
                        $d->delivery_mode ?? 'N/A',
                        $d->outcome ?? 'N/A',
                        $d->delivery_date ? $d->delivery_date->format('Y-m-d H:i') : 'N/A'
                    ];
                }

                $morgueRows = [];
                foreach ($morgue as $m) {
                    $morgueRows[] = [
                        $m->patient ? $this->formatPatientModelLink($m->patient) : ($m->decedent_name ?? 'Unknown'),
                        $m->admission_date ? $m->admission_date->format('Y-m-d H:i') : 'N/A',
                        $m->release_date ? $m->release_date->format('Y-m-d H:i') : 'Pending',
                        $m->status ?? 'N/A'
                    ];
                }

                $tabbedData = [
                    'maternity_deliveries' => [
                        'label' => 'Maternity Deliveries',
                        'headers' => ['Patient', 'Delivery Mode', 'Outcome', 'Delivery Date'],
                        'rows' => $deliveryRows
                    ],
                    'mortuary_register' => [
                        'label' => 'Mortuary Register',
                        'headers' => ['Decedent Name', 'Admission Date', 'Release Date', 'Status'],
                        'rows' => $morgueRows
                    ]
                ];
                break;

            case 'laboratory_register':
                $labStatusOptions = [
                    '1' => 'Awaiting Billing',
                    '2' => 'Awaiting Sample Collection',
                    '3' => 'Awaiting Results',
                    '4' => 'Completed'
                ];
                if (appsettings('lab_results_require_approval')) {
                    $labStatusOptions['5'] = 'Pending Approval';
                    $labStatusOptions['6'] = 'Rejected';
                }

                $filters = [
                    [
                        'name' => 'processing_status',
                        'label' => 'Processing Status',
                        'type' => 'select',
                        'options' => array_merge(['all' => 'All Statuses'], $labStatusOptions),
                        'value' => $request->get('processing_status')
                    ],
                    [
                        'name' => 'reagent_store_id',
                        'label' => 'Laboratory Store',
                        'type' => 'select',
                        'options' => $storeOptions,
                        'value' => $request->get('reagent_store_id')
                    ]
                ];

                $procStatus = $request->get('processing_status');
                $reagentStoreId = $request->get('reagent_store_id');

                $labRequestsQuery = \App\Models\LabServiceRequest::with(['patient.user', 'service'])
                    ->whereBetween('created_at', [$startDate, $endDate]);

                if ($procStatus && $procStatus !== 'all') {
                    $labRequestsQuery->where('status', $procStatus);
                }

                $labRequests = $labRequestsQuery->orderBy('created_at', 'desc')->get();

                $labStores = \App\Models\Store::where('distribution_role', \App\Models\Store::ROLE_LAB)->pluck('id');
                $reagentUsageQuery = \App\Models\StockBatchTransaction::with(['stockBatch.product', 'stockBatch.store', 'performer'])
                    ->where('type', \App\Models\StockBatchTransaction::TYPE_OUT)
                    ->whereBetween('created_at', [$startDate, $endDate]);

                if ($reagentStoreId) {
                    $reagentUsageQuery->whereHas('stockBatch', fn($q) => $q->where('store_id', $reagentStoreId));
                } else {
                    $reagentUsageQuery->whereHas('stockBatch', fn($q) => $q->whereIn('store_id', $labStores));
                }

                $reagentUsage = $reagentUsageQuery->orderBy('created_at', 'desc')->get();

                $kpis = [
                    ['label' => 'Total Lab Requests', 'value' => $labRequests->count(), 'class' => 'text-primary'],
                    ['label' => 'Completed Tests', 'value' => $labRequests->where('status', 4)->count(), 'class' => 'text-success'],
                    ['label' => 'Reagents Used', 'value' => $reagentUsage->sum('qty'), 'class' => 'text-warning']
                ];

                $statusMapping = [
                    1 => '<span class="badge badge-warning">Awaiting Billing</span>',
                    2 => '<span class="badge badge-info">Awaiting Sample</span>',
                    3 => '<span class="badge badge-primary">Awaiting Results</span>',
                    4 => '<span class="badge badge-success">Completed</span>',
                    5 => '<span class="badge badge-dark">Pending Approval</span>',
                    6 => '<span class="badge badge-danger">Rejected</span>'
                ];

                $diagnosticRows = [];
                foreach ($labRequests as $l) {
                    $diagnosticRows[] = [
                        $this->formatPatientModelLink($l->patient),
                        $l->service ? $l->service->service_name : 'N/A',
                        $statusMapping[$l->status] ?? ucfirst($l->status ?? 'pending'),
                        $l->approval_status ? ucfirst($l->approval_status) : 'N/A',
                        $l->created_at->format('Y-m-d H:i')
                    ];
                }

                $usageRows = [];
                foreach ($reagentUsage as $r) {
                    $usageRows[] = [
                        ($r->stockBatch && $r->stockBatch->product) ? $r->stockBatch->product->product_name : 'N/A',
                        ($r->stockBatch && $r->stockBatch->store) ? $r->stockBatch->store->store_name : 'N/A',
                        $r->qty,
                        $this->formatStaffNameThree($r->performer),
                        $r->notes ?? 'N/A',
                        $r->created_at->format('Y-m-d H:i')
                    ];
                }

                $tabbedData = [
                    'laboratory_register' => [
                        'label' => 'Laboratory Register',
                        'headers' => ['Patient', 'Test', 'Processing Status', 'Approval Status', 'Requested At'],
                        'rows' => $diagnosticRows
                    ],
                    'reagent_usage' => [
                        'label' => 'Reagents Usage',
                        'headers' => ['Product', 'Laboratory Store', 'Quantity Dispensed', 'Dispensed By', 'Notes', 'Date'],
                        'rows' => $usageRows
                    ]
                ];
                break;

            case 'imaging_register':
                $imgStatusOptions = [
                    '1' => 'Awaiting Billing',
                    '2' => 'Awaiting Results',
                    '4' => 'Completed',
                    '0' => 'Dismissed'
                ];
                if (appsettings('imaging_results_require_approval')) {
                    $imgStatusOptions['5'] = 'Pending Approval';
                    $imgStatusOptions['6'] = 'Rejected';
                }

                $filters = [
                    [
                        'name' => 'processing_status',
                        'label' => 'Processing Status',
                        'type' => 'select',
                        'options' => array_merge(['all' => 'All Statuses'], $imgStatusOptions),
                        'value' => $request->get('processing_status')
                    ],
                    [
                        'name' => 'consumable_store_id',
                        'label' => 'Imaging Store',
                        'type' => 'select',
                        'options' => $storeOptions,
                        'value' => $request->get('consumable_store_id')
                    ]
                ];

                $procStatus = $request->get('processing_status');
                $consumableStoreId = $request->get('consumable_store_id');

                $imagingRequestsQuery = \App\Models\ImagingServiceRequest::with(['patient.user', 'service'])
                    ->whereBetween('created_at', [$startDate, $endDate]);

                if ($procStatus && $procStatus !== 'all') {
                    $imagingRequestsQuery->where('status', $procStatus);
                }

                $imagingRequests = $imagingRequestsQuery->orderBy('created_at', 'desc')->get();

                $imagingStores = \App\Models\Store::where('distribution_role', \App\Models\Store::ROLE_IMAGING)->pluck('id');
                $usageQuery = \App\Models\StockBatchTransaction::with(['stockBatch.product', 'stockBatch.store', 'performer'])
                    ->where('type', \App\Models\StockBatchTransaction::TYPE_OUT)
                    ->whereBetween('created_at', [$startDate, $endDate]);

                if ($consumableStoreId) {
                    $usageQuery->whereHas('stockBatch', fn($q) => $q->where('store_id', $consumableStoreId));
                } else {
                    $usageQuery->whereHas('stockBatch', fn($q) => $q->whereIn('store_id', $imagingStores));
                }

                $reagentUsage = $usageQuery->orderBy('created_at', 'desc')->get();

                $kpis = [
                    ['label' => 'Total Imaging Requests', 'value' => $imagingRequests->count(), 'class' => 'text-primary'],
                    ['label' => 'Completed Scans', 'value' => $imagingRequests->where('status', 4)->count(), 'class' => 'text-success'],
                    ['label' => 'Consumables Used', 'value' => $reagentUsage->sum('qty'), 'class' => 'text-warning']
                ];

                $statusMapping = [
                    1 => '<span class="badge badge-warning">Awaiting Billing</span>',
                    2 => '<span class="badge badge-info">Awaiting Results</span>',
                    4 => '<span class="badge badge-success">Completed</span>',
                    5 => '<span class="badge badge-dark">Pending Approval</span>',
                    6 => '<span class="badge badge-danger">Rejected</span>',
                    0 => '<span class="badge badge-secondary">Dismissed</span>'
                ];

                $diagnosticRows = [];
                foreach ($imagingRequests as $i) {
                    $diagnosticRows[] = [
                        $this->formatPatientModelLink($i->patient),
                        $i->service ? $i->service->service_name : 'N/A',
                        $statusMapping[$i->status] ?? ucfirst($i->status ?? 'pending'),
                        $i->approval_status ? ucfirst($i->approval_status) : 'N/A',
                        $i->created_at->format('Y-m-d H:i')
                    ];
                }

                $usageRows = [];
                foreach ($reagentUsage as $r) {
                    $usageRows[] = [
                        ($r->stockBatch && $r->stockBatch->product) ? $r->stockBatch->product->product_name : 'N/A',
                        ($r->stockBatch && $r->stockBatch->store) ? $r->stockBatch->store->store_name : 'N/A',
                        $r->qty,
                        $this->formatStaffNameThree($r->performer),
                        $r->notes ?? 'N/A',
                        $r->created_at->format('Y-m-d H:i')
                    ];
                }

                $tabbedData = [
                    'imaging_register' => [
                        'label' => 'Imaging Register',
                        'headers' => ['Patient', 'Scan', 'Processing Status', 'Approval Status', 'Requested At'],
                        'rows' => $diagnosticRows
                    ],
                    'consumables_usage' => [
                        'label' => 'Consumables Usage',
                        'headers' => ['Product', 'Imaging Store', 'Quantity Dispensed', 'Dispensed By', 'Notes', 'Date'],
                        'rows' => $usageRows
                    ]
                ];
                break;

            case 'pharmacy_prescriptions':
                $filters = [
                    [
                        'name' => 'pharmacy_store_id',
                        'label' => 'Pharmacy Store',
                        'type' => 'select',
                        'options' => $storeOptions,
                        'value' => $request->get('pharmacy_store_id')
                    ],
                    [
                        'name' => 'prescription_status',
                        'label' => 'Prescription Status',
                        'type' => 'select',
                        'options' => ['pending' => 'Pending', 'dispensed' => 'Dispensed', 'cancelled' => 'Cancelled'],
                        'value' => $request->get('prescription_status')
                    ],
                    [
                        'name' => 'damage_type',
                        'label' => 'Damage Type',
                        'type' => 'select',
                        'options' => ['expired' => 'Expired', 'damaged' => 'Damaged', 'lost' => 'Lost', 'stolen' => 'Stolen'],
                        'value' => $request->get('damage_type')
                    ]
                ];

                $pharmStoreId = $request->get('pharmacy_store_id');
                $rxStatus = $request->get('prescription_status');
                $damageType = $request->get('damage_type');

                $pharmacyStores = \App\Models\Store::whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE])->pluck('id');

                $prescriptionsQuery = \App\Models\ProductRequest::with(['patient.user', 'product'])
                    ->whereBetween('created_at', [$startDate, $endDate]);

                $returnsQuery = \App\Models\StoreRequisitionReturn::with(['product', 'sourceStore', 'creator'])
                    ->whereBetween('created_at', [$startDate, $endDate]);

                $damagesQuery = \App\Models\StoreDamage::with(['product', 'store', 'creator'])
                    ->whereBetween('created_at', [$startDate, $endDate]);

                if ($pharmStoreId) {
                    $returnsQuery->where('source_store_id', $pharmStoreId);
                    $damagesQuery->where('store_id', $pharmStoreId);
                } else {
                    $returnsQuery->whereIn('source_store_id', $pharmacyStores);
                    $damagesQuery->whereIn('store_id', $pharmacyStores);
                }

                if ($rxStatus) {
                    $prescriptionsQuery->where('status', $rxStatus);
                }
                if ($damageType) {
                    $damagesQuery->where('damage_type', $damageType);
                }

                $prescriptions = $prescriptionsQuery->get();
                $returns = $returnsQuery->get();
                $damages = $damagesQuery->get();

                $kpis = [
                    ['label' => 'Total Prescriptions', 'value' => $prescriptions->count(), 'class' => 'text-primary'],
                    ['label' => 'Dispensed', 'value' => $prescriptions->where('status', 'dispensed')->count(), 'class' => 'text-success'],
                    ['label' => 'Pharmacy Returns', 'value' => $returns->count(), 'class' => 'text-info'],
                    ['label' => 'Damaged/Expired (Qty)', 'value' => $damages->sum('qty_damaged'), 'class' => 'text-danger']
                ];

                $rxRows = [];
                foreach ($prescriptions as $p) {
                    $rxRows[] = [
                        $this->formatPatientModelLink($p->patient),
                        $p->product ? $p->product->product_name : 'N/A',
                        $p->qty,
                        ucfirst($p->status ?? 'pending'),
                        $p->created_at->format('Y-m-d H:i')
                    ];
                }

                $returnRows = [];
                foreach ($returns as $r) {
                    $returnRows[] = [
                        $r->product ? $r->product->product_name : 'N/A',
                        $r->sourceStore ? $r->sourceStore->store_name : 'N/A',
                        $r->qty_returned,
                        $r->return_reason ?? 'N/A',
                        $this->formatStaffNameThree($r->creator),
                        $r->created_at->format('Y-m-d H:i')
                    ];
                }
                
                $damageRows = [];
                foreach ($damages as $d) {
                    $damageRows[] = [
                        $d->product ? $d->product->product_name : 'N/A',
                        $d->store ? $d->store->store_name : 'N/A',
                        $d->qty_damaged,
                        ucfirst($d->damage_type ?? 'N/A'),
                        $d->notes ?? 'N/A',
                        $d->created_at->format('Y-m-d H:i')
                    ];
                }

                $tabbedData = [
                    'prescription_workflow' => [
                        'label' => 'Prescription Workflow',
                        'headers' => ['Patient', 'Product', 'Quantity', 'Status', 'Requested At'],
                        'rows' => $rxRows
                    ],
                    'pharmacy_returns' => [
                        'label' => 'Pharmacy Returns',
                        'headers' => ['Product', 'Store', 'Quantity', 'Reason', 'Returned By', 'Date'],
                        'rows' => $returnRows
                    ],
                    'pharmacy_damages' => [
                        'label' => 'Damages & Expiries',
                        'headers' => ['Product', 'Store', 'Quantity', 'Type', 'Notes', 'Date'],
                        'rows' => $damageRows
                    ]
                ];
                break;



            case 'central_store_stock_check':
                $filters = [
                    [
                        'name' => 'product_type',
                        'label' => 'Product Type',
                        'type' => 'select',
                        'options' => ['drug' => 'Drugs', 'consumable' => 'Consumables', 'reagent' => 'Reagents', 'equipment' => 'Equipment'],
                        'value' => $request->get('product_type')
                    ],
                    [
                        'name' => 'category_id',
                        'label' => 'Category',
                        'type' => 'select',
                        'options' => $categoryOptions,
                        'value' => $request->get('category_id')
                    ],
                    [
                        'name' => 'stock_level',
                        'label' => 'Stock Status',
                        'type' => 'select',
                        'options' => ['all' => 'All Stocks', 'low' => 'Below Reorder Alert', 'out' => 'Out of Stock'],
                        'value' => $request->get('stock_level')
                    ],
                    [
                        'name' => 'min_qty',
                        'label' => 'Min Quantity',
                        'type' => 'number',
                        'value' => $request->get('min_qty')
                    ]
                ];

                $prodType = $request->get('product_type');
                $catId = $request->get('category_id');
                $stockLvl = $request->get('stock_level');
                $minQty = $request->get('min_qty');

                $mainStoreId = \App\Models\Store::where('distribution_role', \App\Models\Store::ROLE_CENTRAL)->value('id');

                $stockQuery = \App\Models\StoreStock::with(['product.category', 'product.price', 'product.packagings'])
                    ->where('store_id', $mainStoreId);
                
                $poItemsQuery = \App\Models\PurchaseOrderItem::with(['purchaseOrder.supplier', 'product.price'])
                    ->whereHas('purchaseOrder', function($q) use ($startDate, $endDate) {
                        $q->whereBetween('created_at', [$startDate, $endDate])
                          ->whereIn('status', ['received', 'partially_received']);
                    });

                $manualBatchesQuery = \App\Models\StockBatch::with(['product.price', 'store', 'creator'])
                    ->where('source', \App\Models\StockBatch::SOURCE_MANUAL)
                    ->whereBetween('created_at', [$startDate, $endDate]);

                if ($prodType) {
                    $stockQuery->whereHas('product', fn($q) => $q->where('product_type', $prodType));
                    $poItemsQuery->whereHas('product', fn($q) => $q->where('product_type', $prodType));
                    $manualBatchesQuery->whereHas('product', fn($q) => $q->where('product_type', $prodType));
                }
                if ($catId) {
                    $stockQuery->whereHas('product', fn($q) => $q->where('category_id', $catId));
                    $poItemsQuery->whereHas('product', fn($q) => $q->where('category_id', $catId));
                    $manualBatchesQuery->whereHas('product', fn($q) => $q->where('category_id', $catId));
                }
                if ($minQty) {
                    $stockQuery->where('quantity', '>=', $minQty);
                    $poItemsQuery->where('qty', '>=', $minQty);
                    $manualBatchesQuery->where('qty', '>=', $minQty);
                }
                if ($stockLvl === 'low') {
                    $stockQuery->whereRaw('quantity <= (select reorder_alert from products where products.id = store_stocks.product_id)');
                } elseif ($stockLvl === 'out') {
                    $stockQuery->where('quantity', '<=', 0);
                }

                $stocks = $stockQuery->get();
                $poItems = $poItemsQuery->get();
                $manualBatches = $manualBatchesQuery->get();
                    
                $kpis = [
                    ['label' => 'Total Stock Value', 'value' => '₦' . number_format($stocks->sum(fn($s) => $s->quantity * optional(optional($s->product)->price)->initial_buy_price), 2), 'class' => 'text-primary'],
                    ['label' => 'Below Reorder', 'value' => $stocks->filter(fn($s) => $s->quantity <= optional($s->product)->reorder_alert)->count(), 'class' => 'text-danger'],
                    ['label' => 'PO Deliveries', 'value' => $poItems->count(), 'class' => 'text-success'],
                    ['label' => 'Manual Batches', 'value' => $manualBatches->count(), 'class' => 'text-warning']
                ];

                $stockRows = [];
                foreach ($stocks as $s) {
                    $stockRows[] = [
                        $s->product ? $s->product->product_name : 'N/A',
                        $s->product ? ucfirst($s->product->product_type) : 'N/A',
                        $s->product && $s->product->category ? $s->product->category->category_name : 'N/A',
                        $s->quantity,
                        $s->product ? $s->product->reorder_alert : 'N/A',
                        '₦' . number_format(optional(optional($s->product)->price)->initial_buy_price ?? 0, 2)
                    ];
                }

                $poRows = [];
                foreach ($poItems as $pi) {
                    $sysCost = optional(optional($pi->product)->price)->initial_buy_price ?? 0;
                    $actualCost = $pi->base_unit_cost ?? 0;
                    $variance = $actualCost - $sysCost;
                    
                    $poRows[] = [
                        $pi->purchaseOrder ? $pi->purchaseOrder->po_number : 'N/A',
                        $pi->product ? $pi->product->product_name : 'N/A',
                        '₦' . number_format($sysCost, 2),
                        '₦' . number_format($actualCost, 2),
                        '<span class="'.($variance > 0 ? 'text-danger' : ($variance < 0 ? 'text-success' : '')).' font-weight-bold">₦' . number_format($variance, 2) . '</span>',
                        $pi->purchaseOrder && $pi->purchaseOrder->supplier ? $pi->purchaseOrder->supplier->name : 'N/A'
                    ];
                }

                $manualRows = [];
                foreach ($manualBatches as $mb) {
                    $sysCost = optional(optional($mb->product)->price)->initial_buy_price ?? 0;
                    $actualCost = $mb->cost_price ?? 0;
                    $variance = $actualCost - $sysCost;
                    
                    $manualRows[] = [
                        $mb->batch_number ?? 'N/A',
                        $mb->product ? $mb->product->product_name : 'N/A',
                        $mb->store ? $mb->store->store_name : 'N/A',
                        '₦' . number_format($sysCost, 2),
                        '₦' . number_format($actualCost, 2),
                        '<span class="'.($variance > 0 ? 'text-danger' : ($variance < 0 ? 'text-success' : '')).' font-weight-bold">₦' . number_format($variance, 2) . '</span>',
                        $this->formatStaffNameThree($mb->creator)
                    ];
                }

                $tabbedData = [
                    'central_stock_overview' => [
                        'label' => 'Central Store Stock (Filtered)',
                        'headers' => ['Product', 'Classification', 'Category', 'Current Qty', 'Reorder Level', 'Sys Buy Price'],
                        'rows' => $stockRows
                    ],
                    'po_price_variance' => [
                        'label' => 'PO Price Variance',
                        'headers' => ['PO Number', 'Product', 'System Cost', 'Actual Received Cost', 'Variance', 'Supplier'],
                        'rows' => $poRows
                    ],
                    'manual_batch_variance' => [
                        'label' => 'Manual Batch Price Variance',
                        'headers' => ['Batch No', 'Product', 'Store', 'System Cost', 'Entered Cost', 'Variance', 'Added By'],
                        'rows' => $manualRows
                    ]
                ];
                break;

            case 'departmental_ward_stores':
                $filters = [
                    [
                        'name' => 'store_id',
                        'label' => 'Departmental Store',
                        'type' => 'select',
                        'options' => $storeOptions,
                        'value' => $request->get('store_id')
                    ],
                    [
                        'name' => 'product_type',
                        'label' => 'Product Type',
                        'type' => 'select',
                        'options' => ['drug' => 'Drugs', 'consumable' => 'Consumables', 'reagent' => 'Reagents', 'equipment' => 'Equipment'],
                        'value' => $request->get('product_type')
                    ],
                    [
                        'name' => 'category_id',
                        'label' => 'Category',
                        'type' => 'select',
                        'options' => $categoryOptions,
                        'value' => $request->get('category_id')
                    ]
                ];

                $storeId = $request->get('store_id');
                $prodType = $request->get('product_type');
                $catId = $request->get('category_id');

                $storeQuery = \App\Models\Store::where('distribution_role', '!=', \App\Models\Store::ROLE_CENTRAL);
                if ($storeId) {
                    $storeQuery->where('id', $storeId);
                }
                $decentralizedStoreIds = $storeQuery->pluck('id');

                $stockQuery = \App\Models\StoreStock::with(['product.category', 'product.price', 'store'])
                    ->whereIn('store_id', $decentralizedStoreIds);
                
                $requisitionsQuery = \App\Models\StoreRequisition::with(['toStore', 'fromStore', 'items.product', 'requester'])
                    ->whereIn('to_store_id', $decentralizedStoreIds)
                    ->whereBetween('created_at', [$startDate, $endDate]);
                    
                $damagesQuery = \App\Models\StoreDamage::with(['product', 'store', 'creator'])
                    ->whereIn('store_id', $decentralizedStoreIds)
                    ->whereBetween('created_at', [$startDate, $endDate]);

                if ($prodType) {
                    $stockQuery->whereHas('product', fn($q) => $q->where('product_type', $prodType));
                    $requisitionsQuery->whereHas('items.product', fn($q) => $q->where('product_type', $prodType));
                    $damagesQuery->whereHas('product', fn($q) => $q->where('product_type', $prodType));
                }
                if ($catId) {
                    $stockQuery->whereHas('product', fn($q) => $q->where('category_id', $catId));
                    $requisitionsQuery->whereHas('items.product', fn($q) => $q->where('category_id', $catId));
                    $damagesQuery->whereHas('product', fn($q) => $q->where('category_id', $catId));
                }

                $stocks = $stockQuery->get();
                $requisitions = $requisitionsQuery->get();
                $damages = $damagesQuery->get();

                $kpis = [
                    ['label' => 'Decentralized Stock Value', 'value' => '₦' . number_format($stocks->sum(fn($s) => $s->quantity * optional(optional($s->product)->price)->initial_buy_price), 2), 'class' => 'text-primary'],
                    ['label' => 'Requisitions Made', 'value' => $requisitions->count(), 'class' => 'text-info'],
                    ['label' => 'Damaged/Expired (Qty)', 'value' => $damages->sum('qty_damaged'), 'class' => 'text-danger']
                ];

                $stockRows = [];
                foreach ($stocks as $s) {
                    $stockRows[] = [
                        $s->store ? $s->store->store_name : 'N/A',
                        $s->product ? $s->product->product_name : 'N/A',
                        $s->product ? ucfirst($s->product->product_type) : 'N/A',
                        $s->product && $s->product->category ? $s->product->category->category_name : 'N/A',
                        $s->quantity,
                        '₦' . number_format(optional(optional($s->product)->price)->initial_buy_price ?? 0, 2)
                    ];
                }

                $reqRows = [];
                foreach ($requisitions as $r) {
                    $reqRows[] = [
                        $r->requisition_number ?? 'N/A',
                        $r->toStore ? $r->toStore->store_name : 'N/A',
                        $r->fromStore ? $r->fromStore->store_name : 'Main Store',
                        $r->items ? $r->items->count() : 0,
                        ucfirst($r->status),
                        $this->formatStaffNameThree($r->requester),
                        $r->created_at->format('Y-m-d H:i')
                    ];
                }

                $damageRows = [];
                foreach ($damages as $d) {
                    $damageRows[] = [
                        $d->product ? $d->product->product_name : 'N/A',
                        $d->store ? $d->store->store_name : 'N/A',
                        $d->qty_damaged,
                        ucfirst($d->damage_type ?? 'N/A'),
                        $d->notes ?? 'N/A',
                        $d->created_at->format('Y-m-d H:i')
                    ];
                }

                $tabbedData = [
                    'ward_stock_overview' => [
                        'label' => 'Ward/Dept Stock Overview',
                        'headers' => ['Store', 'Product', 'Classification', 'Category', 'Current Qty', 'Sys Buy Price'],
                        'rows' => $stockRows
                    ],
                    'requisition_fulfillment' => [
                        'label' => 'Requisition Fulfillment',
                        'headers' => ['Req Number', 'Requesting Store', 'Supplying Store', 'Items Count', 'Status', 'Requested By', 'Date'],
                        'rows' => $reqRows
                    ],
                    'departmental_damages' => [
                        'label' => 'Departmental Damages',
                        'headers' => ['Product', 'Store', 'Quantity', 'Type', 'Notes', 'Date'],
                        'rows' => $damageRows
                    ]
                ];
                break;

        }

        // Populate chart time series dynamically based on date interval
        $chartLabels = [];
        $chartDatasets = [];
        $current = $startDate->copy();
        
        $dateField = 'created_at';
        $sumField = 'amount';
        $useCount = false;
        $modelClass = null;
        
        switch ($responsibility_key) {
            case 'cash_reconciliation':
            case 'discount_authorization':
                $modelClass = \App\Models\Payment::class;
                $sumField = 'total';
                break;
            case 'hmo_claims_nhis':
                $modelClass = \App\Models\ProductOrServiceRequest::class;
                $sumField = 'claims_amount';
                break;
            case 'payroll_dept':
                $modelClass = \App\Models\HR\PayrollBatch::class;
                $sumField = 'total_net';
                break;
            case 'revenue_leakage':
                $modelClass = \App\Models\ProductOrServiceRequest::class;
                $sumField = 'payable_amount';
                break;
            case 'expense_vouchers':
                $modelClass = \App\Models\Expense::class;
                $dateField = 'expense_date';
                $sumField = 'amount';
                break;
            case 'refund_claims':
                $modelClass = \App\Models\Accounting\PatientDeposit::class;
                $sumField = 'refunded_amount';
                break;
            case 'debt_aging':
                $modelClass = \App\Models\StaffBill::class;
                $sumField = 'outstanding_amount';
                break;
            case 'bank_statement_match':
                $modelClass = \App\Models\Accounting\BankReconciliation::class;
                $dateField = 'statement_date';
                $sumField = 'variance';
                break;
            case 'petty_cash':
                $modelClass = \App\Models\Accounting\PettyCashTransaction::class;
                $dateField = 'transaction_date';
                $sumField = 'amount';
                break;
            case 'statutory_deductions':
                $modelClass = \App\Models\Accounting\StatutoryRemittance::class;
                $dateField = 'period_from';
                $sumField = 'amount';
                break;
            // ── Clinical submodules (count-based charts) ────────────
            case 'consulting_queues':
                $modelClass = DoctorQueue::class;
                $sumField = 'id';
                $useCount = true;
                break;
            case 'inpatient_stays':
                $modelClass = AdmissionRequest::class;
                $sumField = 'id';
                $useCount = true;
                break;
            case 'theatre_bundles':
                $modelClass = Procedure::class;
                $sumField = 'id';
                $useCount = true;
                break;
            case 'morgue_releases':
                $modelClass = MorgueAdmission::class;
                $dateField = 'arrival_time';
                $sumField = 'id';
                $useCount = true;
                break;
            case 'clinical_notes_audit':
                $modelClass = Encounter::class;
                $sumField = 'id';
                $useCount = true;
                break;
            case 'maternity_deliveries':
                $modelClass = MaternityEnrollment::class;
                $sumField = 'id';
                $useCount = true;
                break;
            case 'prescription_fills':
                $modelClass = ProductRequest::class;
                $sumField = 'id';
                $useCount = true;
                break;
            case 'treatment_plans':
                $modelClass = LabServiceRequest::class;
                $sumField = 'id';
                $useCount = true;
                break;
            case 'laboratory_register':
                $modelClass = \App\Models\LabServiceRequest::class;
                $sumField = 'id';
                $useCount = true;
                break;
            case 'imaging_register':
                $modelClass = \App\Models\ImagingServiceRequest::class;
                $sumField = 'id';
                $useCount = true;
                break;
            case 'nursing_vitals':
                $modelClass = VitalSign::class;
                $sumField = 'id';
                $useCount = true;
                break;
            case 'discharge_clearance':
                $modelClass = AdmissionRequest::class;
                $sumField = 'id';
                $useCount = true;
                break;
                break;
            case 'emergency_triage':
                $modelClass = DoctorQueue::class;
                $sumField = 'id';
                $useCount = true;
                break;
            // ── Inventory submodule charts ────────────
            case 'stock_variance':
                $modelClass = StockBatchTransaction::class;
                $sumField = 'id';
                $useCount = true;
                break;
            case 'purchase_price_var':
                $modelClass = PurchaseOrderItem::class;
                $sumField = 'id';
                $useCount = true;
                break;
            case 'dispensing_errors':
                $modelClass = StockBatch::class;
                $dateField = 'expiry_date';
                $sumField = 'id';
                $useCount = true;
                break;
            case 'requisition_fulfill':
                $modelClass = StoreRequisition::class;
                $sumField = 'id';
                $useCount = true;
                break;
            case 'damaged_goods':
                $modelClass = StoreDamage::class;
                $sumField = 'id';
                $useCount = true;
                break;
            case 'supplier_invoice':
                $modelClass = PurchaseOrder::class;
                $sumField = 'total_amount';
                break;
            case 'pharmacy_returns':
                $modelClass = PurchaseOrderReturn::class;
                $sumField = 'id';
                $useCount = true;
                break;
            case 'procurement_contracts':
                $modelClass = PurchaseOrder::class;
                $sumField = 'total_amount';
                break;
                break;
        }

        if ($modelClass) {
            $dailyQuery = $modelClass::whereBetween($dateField, [$startDate, $endDate]);
            if ($responsibility_key === 'hmo_claims_nhis') {
                $dailyQuery->whereNotNull('hmo_id');
            } elseif ($responsibility_key === 'discount_authorization') {
                $dailyQuery->where('total_discount', '>', 0);
            } elseif ($responsibility_key === 'refund_claims') {
                $dailyQuery->where('status', 'refunded')->orWhere('refunded_amount', '>', 0);
            } elseif ($responsibility_key === 'petty_cash') {
                $dailyQuery->where('transaction_type', 'disbursement');
            } elseif ($responsibility_key === 'emergency_triage') {
                $dailyQuery->where('source', 'emergency_intake');
            } elseif ($responsibility_key === 'discharge_clearance') {
                $dailyQuery->where('discharged', 1);
            }
            
            $dailySums = $dailyQuery->select(
                    DB::raw("DATE($dateField) as day_str"),
                    DB::raw($useCount ? "COUNT(*) as day_sum" : "SUM($sumField) as day_sum")
                )
                ->groupBy('day_str')
                ->pluck('day_sum', 'day_str')
                ->toArray();
                
            while ($current->lte($endDate)) {
                $dayStr = $current->format('Y-m-d');
                $chartLabels[] = $current->format('M d');
                $chartDatasets[] = floatval($dailySums[$dayStr] ?? 0);
                $current->addDay();
            }
        } else {
            while ($current->lte($endDate)) {
                $chartLabels[] = $current->format('M d');
                $chartDatasets[] = 0;
                $current->addDay();
            }
        }
        
        $chart = [
            'labels' => $chartLabels,
            'datasets' => $chartDatasets
        ];

        if ($request->ajax()) {
            $tab = $request->get('datatable_tab', 'default');
            if (isset($tabbedData) && isset($tabbedData[$tab])) {
                return DataTables::of($tabbedData[$tab]['rows'])->escapeColumns([])->make(true);
            }
            return DataTables::of($rows)->escapeColumns([])->make(true);
        }

        return view('admin.audit.reports.show', compact(
            'responsibility_key', 'categoryLabel', 'reportLabel',
            'startDate', 'endDate', 'stamp', 'kpis', 'headers', 'rows', 'chart', 'tabbedData', 'filters'
        ));
    }

    /**
     * Display a JSON breakdown of all staff bills settled by a specific payment transaction.
     */
    public function settlementBreakdown($paymentId)
    {
        $payment = \App\Models\Payment::with(['bank', 'staff_user'])->findOrFail($paymentId);

        // Fetch all staff bills allocated in this payment transaction
        $bills = \App\Models\StaffBill::whereHas('payments', function ($q) use ($paymentId) {
                $q->where('payments.id', $paymentId);
            })
            ->with(['patient.user', 'checkoutPayment', 'payments' => function ($q) use ($paymentId) {
                $q->where('payments.id', $paymentId);
            }])
            ->get();

        // Map the bills details beautifully
        $billsData = $bills->map(function ($bill) {
            $patientName = $this->formatPatientModelLink($bill->patient);

            $allocationPivot = $bill->payments->first()?->pivot;
            $allocatedPaid = $allocationPivot ? floatval($allocationPivot->amount_allocated) : 0.00;
            $allocatedDiscount = $allocationPivot ? floatval($allocationPivot->discount_allocated) : 0.00;

            return [
                'id' => $bill->id,
                'incurred_date' => $bill->created_at->format('Y-m-d H:i'),
                'patient_name' => $patientName,
                'file_no' => $bill->patient?->file_no ?? 'N/A',
                'reference' => $bill->checkoutPayment?->reference_no ?? 'N/A',
                'original_amount' => floatval($bill->total_amount),
                'allocated_paid' => $allocatedPaid,
                'allocated_discount' => $allocatedDiscount,
                'remaining_balance' => floatval($bill->outstanding_amount),
                'status' => $bill->status
            ];
        });

        return response()->json([
            'success' => true,
            'payment' => [
                'id' => $payment->id,
                'reference_no' => $payment->reference_no,
                'payment_method' => $payment->payment_method,
                'bank_name' => $payment->bank?->name ?? 'N/A',
                'total_paid' => floatval($payment->total),
                'total_discount' => floatval($payment->total_discount),
                'settled_at' => $payment->created_at->format('Y-m-d H:i'),
                'settled_by' => $this->formatStaffNameThree($payment->staff_user)
            ],
            'bills' => $billsData
        ]);
    }

    private function formatPatientNameLink($patientId, $surname, $firstname, $othername, $fileNo) {
        // Cache the route URL pattern once to avoid thousands of route() calls (memory optimisation)
        static $routePrefix = null;
        if ($routePrefix === null) {
            $routePrefix = route('patient.show', '__PATIENT_ID__');
        }

        $names = [];
        if (!empty($surname)) $names[] = trim($surname);
        if (!empty($firstname)) $names[] = trim($firstname);
        if (!empty($othername)) $names[] = trim($othername);
        $fullName = count($names) > 0 ? implode(' ', $names) : '';
        
        if (empty($fullName)) {
            return 'Walk-in';
        }
        
        $label = $fullName;
        if (!empty($fileNo)) {
            $label .= ' (' . $fileNo . ')';
        }
        
        if ($patientId) {
            $url = str_replace('__PATIENT_ID__', $patientId, $routePrefix);
            return '<a href="' . $url . '" class="text-primary font-weight-bold" target="_blank">' . e($label) . '</a>';
        }
        
        return e($label);
    }

    private function formatPatientModelLink($patient) {
        if (!$patient) return 'Walk-in';
        $user = $patient->user ?? null;
        $surname = $user ? $user->surname : ($patient->surname ?? '');
        $firstname = $user ? $user->firstname : ($patient->firstname ?? '');
        $othername = $user ? $user->othername : ($patient->othername ?? '');
        $fileNo = $patient->file_no ?? '';
        $patientId = $patient->id ?? null;
        
        return $this->formatPatientNameLink($patientId, $surname, $firstname, $othername, $fileNo);
    }

    private function formatPatientUserLink($user, $patient = null) {
        if (!$user && !$patient) return 'Walk-in';
        $surname = $user ? $user->surname : ($patient ? $patient->surname : '');
        $firstname = $user ? $user->firstname : ($patient ? $patient->firstname : '');
        $othername = $user ? $user->othername : ($patient ? $patient->othername : '');
        $fileNo = $patient ? $patient->file_no : '';
        $patientId = $patient ? $patient->id : null;
        
        return $this->formatPatientNameLink($patientId, $surname, $firstname, $othername, $fileNo);
    }

    private function formatStaffNameThree($user) {
        if (!$user) return 'System';
        $names = [];
        if (!empty($user->surname)) $names[] = trim($user->surname);
        if (!empty($user->firstname)) $names[] = trim($user->firstname);
        if (!empty($user->othername)) $names[] = trim($user->othername);
        return count($names) > 0 ? implode(' ', $names) : 'System';
    }

    private function formatStaffRawName($surname, $firstname, $othername = null) {
        $names = [];
        if (!empty($surname)) $names[] = trim($surname);
        if (!empty($firstname)) $names[] = trim($firstname);
        if (!empty($othername)) $names[] = trim($othername);
        return count($names) > 0 ? implode(' ', $names) : 'System';
    }
}
