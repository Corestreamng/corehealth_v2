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
            'lab_imaging_register' => 'Lab/Imaging Register & Reagent Usage',
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
        $staffWithBills = User::whereHas('staff_profile')
            ->whereHas('staffBills', function ($q) {
                $q->where('outstanding_amount', '>', 0);
            })
            ->with(['staffBills' => function ($q) {
                $q->where('outstanding_amount', '>', 0)->with(['patient.user', 'checkoutPayment']);
            }, 'staff_profile'])
            ->get()
            ->map(function ($user) {
                $user->total_outstanding = $user->staffBills->sum('outstanding_amount');
                return $user;
            });

        $allStaffBills = StaffBill::with([
                'patient.user',
                'staffUser.staff_profile',
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
                $row->cashier_name = $cashier ? trim($cashier->surname . ' ' . $cashier->firstname) : 'Unknown Cashier';
                return $row;
            });

        // B. HMO claims nhis matching
        $hmoClaims = DB::table('product_or_service_requests as posr')
            ->join('hmos', 'posr.hmo_id', '=', 'hmos.id')
            ->select('hmos.name as hmo_name',
                DB::raw('COUNT(*) as claim_count'),
                DB::raw('SUM(posr.payable_amount) as total_payable'),
                DB::raw('SUM(posr.claims_amount) as total_claim')
            )
            ->where(function($q) {
                $q->where('hmos.name', 'LIKE', '%NHIS%')
                  ->orWhere('hmos.name', 'LIKE', '%NHIA%')
                  ->orWhere('hmos.name', 'LIKE', '%SHIS%')
                  ->orWhere('hmos.name', 'LIKE', '%PLASCHEMA%');
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

        $responsibility_key = trim($responsibility_key);

        if ($responsibility_key === 'cash_and_billing_audit' || $responsibility_key === 'discounts_refunds_debt') {
            $cashiers = User::select('id', 'surname', 'firstname')->orderBy('surname')->get();
            foreach ($cashiers as $c) {
                $cashierOptions[$c->id] = trim($c->surname . ' ' . $c->firstname);
            }
        }
        
        if ($responsibility_key === 'consulting_clinics_flow') {
            $clinics = \App\Models\Clinic::select('id', 'name')->orderBy('name')->get();
            foreach ($clinics as $c) {
                $clinicOptions[$c->id] = $c->name;
            }
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
        
        if ($responsibility_key === 'hmo_nhis_verification') {
            $hmos = \App\Models\Hmo::select('id', 'name')->orderBy('name')->get();
            foreach ($hmos as $h) {
                $hmoOptions[$h->id] = $h->name;
            }
        }
        
        if ($responsibility_key === 'central_store_stock_check' || $responsibility_key === 'departmental_ward_stores') {
            $stores = Store::select('id', 'store_name')->orderBy('store_name')->get();
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

                // 2. Memory-Efficient Raw DB Queries for detailed ledger tables (Filtered)
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
                        'cashier_user.surname as cashier_surname',
                        'cashier_user.firstname as cashier_firstname',
                        'patient_user.surname as patient_surname',
                        'patient_user.firstname as patient_firstname'
                    ])
                    ->orderBy('payments.created_at', 'desc')
                    ->limit(1000)
                    ->get();

                $depositsQuery = $depositsQueryBuilder->select([
                        'patient_deposits.id',
                        'patient_deposits.deposit_number',
                        'patient_deposits.payment_method',
                        'patient_deposits.amount',
                        'patient_deposits.deposit_date',
                        'receiver_user.surname as receiver_surname',
                        'receiver_user.firstname as receiver_firstname',
                        'patient_user.surname as patient_surname',
                        'patient_user.firstname as patient_firstname'
                    ])
                    ->orderBy('patient_deposits.deposit_date', 'desc')
                    ->limit(1000)
                    ->get();

                $receipts = collect();
                foreach ($paymentsQuery as $p) {
                    $receipts->push([
                        'reference' => $p->reference_no ?? 'N/A',
                        'cashier' => ($p->cashier_surname || $p->cashier_firstname) ? trim($p->cashier_surname . ' ' . $p->cashier_firstname) : 'System',
                        'patient' => ($p->patient_surname || $p->patient_firstname) ? trim($p->patient_surname . ' ' . $p->patient_firstname) : 'Walk-in',
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
                        'cashier' => ($d->receiver_surname || $d->receiver_firstname) ? trim($d->receiver_surname . ' ' . $d->receiver_firstname) : 'System',
                        'patient' => ($d->patient_surname || $d->patient_firstname) ? trim($d->patient_surname . ' ' . $d->patient_firstname) : 'N/A',
                        'type' => 'Account Deposit',
                        'method' => $d->payment_method ?? 'N/A',
                        'amount' => $d->amount,
                        'date' => Carbon::parse($d->deposit_date)->format('Y-m-d H:i'),
                        'datetime' => $d->deposit_date
                    ]);
                }

                $receipts = $receipts->sortByDesc('datetime')->take(1000);

                $receiptRows = [];
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

                // 3. Memory-Efficient Raw DB Query for Revenue Leakage (Limit 1,000 records)
                $leakageQuery = DB::table('product_or_service_requests as posr')
                    ->leftJoin('users as u', 'posr.user_id', '=', 'u.id')
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
                        'u.surname as user_surname',
                        'u.firstname as user_firstname',
                        'pr.product_name as product_name',
                        'sv.service_name as service_name'
                    ])
                    ->orderBy('posr.created_at', 'desc')
                    ->limit(1000)
                    ->get();
                
                $leakageRows = [];
                foreach ($leakageQuery as $r) {
                    $amt = $r->payable_amount > 0 ? $r->payable_amount : $r->amount;
                    $itemName = $r->product_name ?: ($r->service_name ?: 'N/A');
                    $patientName = ($r->user_surname || $r->user_firstname) ? trim($r->user_surname . ' ' . $r->user_firstname) : 'N/A';
                    
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

                $kpis = [
                    ['label' => 'Gross Collections (Payments)', 'value' => '₦' . number_format($grossPayments, 2), 'class' => 'text-success'],
                    ['label' => 'Total Account Deposits', 'value' => '₦' . number_format($grossDeposits, 2), 'class' => 'text-info'],
                    ['label' => 'Registration Fees', 'value' => '₦' . number_format($regFees, 2), 'class' => 'text-primary'],
                    ['label' => 'Unbilled Value (Leakage)', 'value' => '₦' . number_format($leakageTotal, 2), 'class' => 'text-danger']
                ];

                $tabbedData = [
                    'unified_receipts' => [
                        'label' => 'Unified Daily Receipts (Showing recent 1,000)',
                        'headers' => ['Reference No', 'Cashier', 'Patient', 'Type', 'Method', 'Amount', 'Date'],
                        'rows' => $receiptRows
                    ],
                    'revenue_leakage' => [
                        'label' => 'Unbilled Self/Private Services (Showing recent 1,000)',
                        'headers' => ['Req ID', 'Patient', 'Item', 'Original Price', 'Discount', 'Leakage Value', 'Date'],
                        'rows' => $leakageRows
                    ]
                ];
                break;

            case 'bank_reconciliation':
                $reconciliations = \App\Models\Accounting\BankReconciliation::with(['bank', 'preparedBy', 'fiscalPeriod'])
                    ->whereBetween('statement_date', [$startDate, $endDate])
                    ->orderBy('statement_date', 'desc')
                    ->get();

                $bankDeposits = \App\Models\Payment::with(['patient.user', 'staff_user', 'bank'])
                    ->whereIn('payment_method', ['POS', 'TRANSFER', 'BANK_TRANSFER'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at', 'desc')
                    ->get();

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
                        $p->staff_user ? trim($p->staff_user->surname . ' ' . $p->staff_user->firstname) : 'System',
                        $p->patient && $p->patient->user ? trim($p->patient->user->surname . ' ' . $p->patient->user->firstname) : 'Walk-in',
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
                $claims = \App\Models\ProductOrServiceRequest::with(['user', 'hmo.scheme', 'product', 'service'])
                    ->whereNotNull('hmo_id')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at', 'desc')
                    ->get();

                $remittances = \App\Models\HmoRemittance::with(['hmo.scheme'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at', 'desc')
                    ->get();

                $kpis = [
                    ['label' => 'Total HMO Claims Value', 'value' => '₦' . number_format($claims->sum('claims_amount'), 2), 'class' => 'text-purple'],
                    ['label' => 'Capitation / Remitted', 'value' => '₦' . number_format($remittances->sum('amount'), 2), 'class' => 'text-success'],
                    ['label' => 'Claims Count', 'value' => $claims->count() . ' Claims', 'class' => 'text-info']
                ];

                $claimsRows = [];
                foreach ($claims as $c) {
                    $claimsRows[] = [
                        $c->id,
                        $c->user ? trim($c->user->surname . ' ' . $c->user->firstname) : 'N/A',
                        $c->hmo ? $c->hmo->name : 'N/A',
                        $c->product ? ('Drug: '.$c->product->name) : ($c->service ? ('Service: '.$c->service->name) : 'N/A'),
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
                $checkoutDiscounts = \App\Models\Payment::with(['patient.user', 'staff_user'])
                    ->where('total_discount', '>', 0)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at', 'desc')
                    ->get();

                $staffDebts = \App\Models\StaffBill::with(['patient.user', 'staffUser'])
                    ->where('outstanding_amount', '>', 0)
                    ->get(); // Debt is cumulative, not just within range

                $refundedDeposits = \App\Models\Accounting\PatientDeposit::with(['patient', 'refunder'])
                    ->where(fn($q) => $q->where('status', 'refunded')->orWhere('refunded_amount', '>', 0))
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at', 'desc')
                    ->get();

                $kpis = [
                    ['label' => 'Checkout Waivers', 'value' => '₦' . number_format($checkoutDiscounts->sum('total_discount'), 2), 'class' => 'text-info'],
                    ['label' => 'Staff/Company Debt', 'value' => '₦' . number_format($staffDebts->sum('outstanding_amount'), 2), 'class' => 'text-danger'],
                    ['label' => 'Patient Refunds', 'value' => '₦' . number_format($refundedDeposits->sum('refunded_amount'), 2), 'class' => 'text-warning']
                ];

                $checkoutRows = [];
                foreach ($checkoutDiscounts as $p) {
                    $checkoutRows[] = [
                        $p->reference_no ?? 'N/A',
                        $p->patient && $p->patient->user ? trim($p->patient->user->surname . ' ' . $p->patient->user->firstname) : 'Walk-in',
                        $p->staff_user ? trim($p->staff_user->surname . ' ' . $p->staff_user->firstname) : 'System',
                        '₦' . number_format($p->total + $p->total_discount, 2),
                        '₦' . number_format($p->total_discount, 2),
                        $p->created_at->format('Y-m-d H:i')
                    ];
                }

                $staffRows = [];
                foreach ($staffDebts as $s) {
                    $staffRows[] = [
                        $s->id,
                        $s->staffUser ? trim($s->staffUser->surname . ' ' . $s->staffUser->firstname) : 'N/A',
                        $s->patient && $s->patient->user ? trim($s->patient->user->surname . ' ' . $s->patient->user->firstname) : 'N/A',
                        '₦' . number_format($s->amount ?? 0, 2),
                        '₦' . number_format($s->outstanding_amount, 2),
                        $s->created_at->format('Y-m-d H:i')
                    ];
                }
                
                $depositRows = [];
                foreach ($refundedDeposits as $d) {
                    $depositRows[] = [
                        $d->deposit_number ?? 'N/A',
                        $d->patient ? trim($d->patient->surname . ' ' . $d->patient->firstname) : 'N/A',
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
                $batches = \App\Models\HR\PayrollBatch::with(['createdBy', 'approvedBy'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at', 'desc')
                    ->get();

                $deductions = \App\Models\Accounting\StatutoryRemittance::with(['payHead', 'bank'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at', 'desc')
                    ->get();

                $expenses = \App\Models\Expense::with(['supplier', 'store', 'bank', 'recorder'])
                    ->whereBetween('expense_date', [$startDate, $endDate])
                    ->orderBy('expense_date', 'desc')
                    ->get();
                    
                $pettyCash = \App\Models\Accounting\PettyCashTransaction::with(['fund'])
                    ->whereBetween('transaction_date', [$startDate, $endDate])
                    ->orderBy('transaction_date', 'desc')
                    ->get();

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
                $queues = \App\Models\DoctorQueue::with(['patient.user', 'clinic', 'doctor.user'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at', 'desc')
                    ->get();
                    
                $appointments = \App\Models\DoctorAppointment::with(['patient.user', 'doctor.user'])
                    ->whereBetween('appointment_date', [$startDate, $endDate])
                    ->orderBy('appointment_date', 'desc')
                    ->get();

                // To find unbilled consultations: Completed queues without linked payment/invoice?
                // Depending on the logic, usually we check ProductOrServiceRequest for consultation items.
                // We will just do a rough matching using the patient and date.

                $queueRows = [];
                foreach ($queues as $q) {
                    $queueRows[] = [
                        $q->patient && $q->patient->user ? trim($q->patient->user->surname . ' ' . $q->patient->user->firstname) : 'N/A',
                        $q->clinic ? $q->clinic->clinic_name : 'N/A',
                        ($q->doctor && $q->doctor->user) ? trim($q->doctor->user->surname . ' ' . $q->doctor->user->firstname) : 'N/A',
                        \App\Enums\QueueStatus::badge($q->status),
                        $q->priority ?? 'N/A',
                        $q->created_at->format('Y-m-d H:i')
                    ];
                }

                $apptRows = [];
                foreach ($appointments as $a) {
                    $apptRows[] = [
                        $a->patient && $a->patient->user ? trim($a->patient->user->surname . ' ' . $a->patient->user->firstname) : 'N/A',
                        ($a->doctor && $a->doctor->user) ? trim($a->doctor->user->surname . ' ' . $a->doctor->user->firstname) : 'N/A',
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
                $admissions = \App\Models\AdmissionRequest::with(['patient.user', 'preferredWard', 'bed.wardRelation'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at', 'desc')
                    ->get();
                
                // Active Admissions across time (not just within range)
                $activeAdmissions = \App\Models\AdmissionRequest::with(['patient.user', 'preferredWard', 'bed.wardRelation'])
                    ->where('discharged', 0)
                    ->get();

                // Ward Income (from Payments linked to active wards) - approximating by using the patient's active ward
                $wardPayments = \App\Models\Payment::with(['patient.user'])
                    ->whereIn('patient_id', function($query) {
                        $query->select('patient_id')
                              ->from('admission_requests')
                              ->where('discharged', 0);
                    })
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();
                
                $kpis = [
                    ['label' => 'Active Admissions', 'value' => $activeAdmissions->count(), 'class' => 'text-primary'],
                    ['label' => 'Discharges (Period)', 'value' => $admissions->where('discharged', 1)->count(), 'class' => 'text-success'],
                    ['label' => 'Pending Clearance', 'value' => $activeAdmissions->where('discharge_requested', 1)->count(), 'class' => 'text-warning'],
                    ['label' => 'Est. Ward Income', 'value' => '₦' . number_format($wardPayments->sum('total'), 2), 'class' => 'text-info']
                ];

                $activeRows = [];
                foreach ($activeAdmissions as $a) {
                    $activeRows[] = [
                        $a->patient && $a->patient->user ? trim($a->patient->user->surname . ' ' . $a->patient->user->firstname) : 'N/A',
                        ($a->bed && $a->bed->wardRelation) ? $a->bed->wardRelation->name : ($a->preferredWard ? $a->preferredWard->name : 'N/A'),
                        $a->bed ? $a->bed->bed_name : 'N/A',
                        $a->discharge_requested ? '<span class="badge badge-warning">Clearance Pending</span>' : '<span class="badge badge-success">Admitted</span>',
                        $a->created_at->format('Y-m-d H:i')
                    ];
                }

                $paymentRows = [];
                foreach ($wardPayments as $p) {
                    // Try to fetch patient's active admission at payment time
                    $paymentRows[] = [
                        $p->reference_no ?? 'N/A',
                        $p->patient && $p->patient->user ? trim($p->patient->user->surname . ' ' . $p->patient->user->firstname) : 'N/A',
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
                $procedures = \App\Models\Procedure::with(['patient.user', 'service', 'requestedByUser'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('created_at', 'desc')
                    ->get();
                
                $procedureItems = \App\Models\ProcedureItem::with(['procedure.patient.user', 'productRequest.product', 'labServiceRequest.service', 'imagingServiceRequest.service'])
                    ->where('is_bundled', 1)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();

                $kpis = [
                    ['label' => 'Total Procedures', 'value' => $procedures->count(), 'class' => 'text-primary'],
                    ['label' => 'Completed Procedures', 'value' => $procedures->where('status', 'completed')->count(), 'class' => 'text-success'],
                    ['label' => 'Bundled Items Used', 'value' => $procedureItems->sum('qty'), 'class' => 'text-warning'],
                    ['label' => 'Scheduled Procedures', 'value' => $procedures->where('status', 'scheduled')->count(), 'class' => 'text-info']
                ];

                $procRows = [];
                foreach ($procedures as $p) {
                    $procRows[] = [
                        $p->patient && $p->patient->user ? trim($p->patient->user->surname . ' ' . $p->patient->user->firstname) : 'N/A',
                        $p->service ? $p->service->service_name : 'N/A',
                        $p->requestedByUser ? trim($p->requestedByUser->surname . ' ' . $p->requestedByUser->firstname) : 'N/A',
                        '<span class="badge badge-primary">' . ucfirst($p->status) . '</span>',
                        $p->scheduled_date ? $p->scheduled_date->format('Y-m-d H:i') : 'N/A'
                    ];
                }

                $itemRows = [];
                foreach ($procedureItems as $item) {
                    $itemRows[] = [
                        $item->procedure && $item->procedure->patient && $item->procedure->patient->user ? trim($item->procedure->patient->user->surname . ' ' . $item->procedure->patient->user->firstname) : 'N/A',
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
                $enrollments = \App\Models\MaternityEnrollment::with(['patient.user'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();

                $deliveries = \App\Models\DeliveryRecord::with(['patient.user'])
                    ->whereBetween('delivery_date', [$startDate, $endDate])
                    ->get();
                    
                $morgue = \App\Models\MorgueAdmission::with(['patient.user'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();

                $kpis = [
                    ['label' => 'New ANC Enrollments', 'value' => $enrollments->count(), 'class' => 'text-primary'],
                    ['label' => 'Total Deliveries', 'value' => $deliveries->count(), 'class' => 'text-success'],
                    ['label' => 'Morgue Admissions', 'value' => $morgue->count(), 'class' => 'text-dark']
                ];

                $deliveryRows = [];
                foreach ($deliveries as $d) {
                    $deliveryRows[] = [
                        $d->patient && $d->patient->user ? trim($d->patient->user->surname . ' ' . $d->patient->user->firstname) : 'N/A',
                        $d->delivery_mode ?? 'N/A',
                        $d->outcome ?? 'N/A',
                        $d->delivery_date ? $d->delivery_date->format('Y-m-d H:i') : 'N/A'
                    ];
                }

                $morgueRows = [];
                foreach ($morgue as $m) {
                    $morgueRows[] = [
                        $m->patient && $m->patient->user ? trim($m->patient->user->surname . ' ' . $m->patient->user->firstname) : ($m->decedent_name ?? 'Unknown'),
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



            case 'lab_imaging_register':
                $labRequests = \App\Models\LabServiceRequest::with(['patient.user', 'service'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();
                    
                $imagingRequests = \App\Models\ImagingServiceRequest::with(['patient.user', 'service'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();

                // Reagent usage (from stores with lab/imaging roles)
                $labStores = \App\Models\Store::where('distribution_role', \App\Models\Store::ROLE_LAB)->pluck('id');
                $imagingStores = \App\Models\Store::where('distribution_role', \App\Models\Store::ROLE_IMAGING)->pluck('id');
                $diagnosticStoreIds = $labStores->merge($imagingStores);

                $reagentUsage = \App\Models\StockBatchTransaction::with(['stockBatch.product', 'stockBatch.store', 'performer'])
                    ->whereHas('stockBatch', function($q) use ($diagnosticStoreIds) {
                        $q->whereIn('store_id', $diagnosticStoreIds);
                    })
                    ->where('type', \App\Models\StockBatchTransaction::TYPE_OUT)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();

                $kpis = [
                    ['label' => 'Total Lab Requests', 'value' => $labRequests->count(), 'class' => 'text-primary'],
                    ['label' => 'Total Imaging Requests', 'value' => $imagingRequests->count(), 'class' => 'text-info'],
                    ['label' => 'Reagents/Consumables Used', 'value' => $reagentUsage->sum('quantity'), 'class' => 'text-warning']
                ];

                $diagnosticRows = [];
                foreach ($labRequests as $l) {
                    $diagnosticRows[] = [
                        $l->patient && $l->patient->user ? trim($l->patient->user->surname . ' ' . $l->patient->user->firstname) : 'N/A',
                        'Lab: ' . ($l->service ? $l->service->service_name : 'N/A'),
                        ucfirst($l->status ?? 'pending'),
                        $l->approval_status ? ucfirst($l->approval_status) : 'N/A (Optional)',
                        $l->created_at->format('Y-m-d H:i')
                    ];
                }
                foreach ($imagingRequests as $i) {
                    $diagnosticRows[] = [
                        $i->patient && $i->patient->user ? trim($i->patient->user->surname . ' ' . $i->patient->user->firstname) : 'N/A',
                        'Imaging: ' . ($i->service ? $i->service->service_name : 'N/A'),
                        ucfirst($i->status ?? 'pending'),
                        $i->approval_status ? ucfirst($i->approval_status) : 'N/A (Optional)',
                        $i->created_at->format('Y-m-d H:i')
                    ];
                }

                $usageRows = [];
                foreach ($reagentUsage as $r) {
                    $usageRows[] = [
                        ($r->stockBatch && $r->stockBatch->product) ? $r->stockBatch->product->product_name : 'N/A',
                        ($r->stockBatch && $r->stockBatch->store) ? $r->stockBatch->store->store_name : 'N/A',
                        $r->qty,
                        $r->performer ? trim($r->performer->surname . ' ' . $r->performer->firstname) : 'System',
                        $r->notes ?? 'N/A',
                        $r->created_at->format('Y-m-d H:i')
                    ];
                }

                $tabbedData = [
                    'diagnostics_register' => [
                        'label' => 'Lab & Imaging Register',
                        'headers' => ['Patient', 'Test/Scan', 'Processing Status', 'Approval Status', 'Requested At'],
                        'rows' => $diagnosticRows
                    ],
                    'reagent_usage' => [
                        'label' => 'Reagents & Consumables Usage',
                        'headers' => ['Product', 'Diagnostic Store', 'Quantity Dispensed', 'Dispensed By', 'Notes', 'Date'],
                        'rows' => $usageRows
                    ]
                ];
                break;

            case 'pharmacy_prescriptions':
                $pharmacyStores = \App\Models\Store::whereIn('distribution_role', [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE])->pluck('id');

                $prescriptions = \App\Models\ProductRequest::with(['patient.user', 'product'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();
                
                $returns = \App\Models\StoreRequisitionReturn::with(['product', 'sourceStore', 'creator'])
                    ->whereIn('source_store_id', $pharmacyStores)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();

                $damages = \App\Models\StoreDamage::with(['product', 'store', 'creator'])
                    ->whereIn('store_id', $pharmacyStores)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();

                $kpis = [
                    ['label' => 'Total Prescriptions', 'value' => $prescriptions->count(), 'class' => 'text-primary'],
                    ['label' => 'Dispensed', 'value' => $prescriptions->where('status', 'dispensed')->count(), 'class' => 'text-success'],
                    ['label' => 'Pharmacy Returns', 'value' => $returns->count(), 'class' => 'text-info'],
                    ['label' => 'Damaged/Expired (Qty)', 'value' => $damages->sum('quantity'), 'class' => 'text-danger']
                ];

                $rxRows = [];
                foreach ($prescriptions as $p) {
                    $rxRows[] = [
                        $p->patient && $p->patient->user ? trim($p->patient->user->surname . ' ' . $p->patient->user->firstname) : 'N/A',
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
                        $r->creator ? trim($r->creator->surname . ' ' . $r->creator->firstname) : 'N/A',
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
                $mainStoreId = \App\Models\Store::where('distribution_role', \App\Models\Store::ROLE_CENTRAL)->value('id');

                // Re-use store stock base query
                $stockQuery = \App\Models\StoreStock::with(['product.category', 'product.price', 'product.packagings'])
                    ->where('store_id', $mainStoreId);
                
                // Allow Dual-Axis Filtering from Request if passed (mocking here for auditor view)
                if ($request->filled('product_type')) {
                    $stockQuery->whereHas('product', fn($q) => $q->where('product_type', $request->product_type));
                }
                if ($request->filled('category_id')) {
                    $stockQuery->whereHas('product', fn($q) => $q->where('category_id', $request->category_id));
                }

                $stocks = $stockQuery->get();

                // PO Price Variance
                $poItems = \App\Models\PurchaseOrderItem::with(['purchaseOrder.supplier', 'product.price'])
                    ->whereHas('purchaseOrder', function($q) use ($startDate, $endDate) {
                        $q->whereBetween('created_at', [$startDate, $endDate])
                          ->whereIn('status', ['received', 'partially_received']);
                    })->get();

                // Manual Batch Price Variance (Source Manual)
                $manualBatches = \App\Models\StockBatch::with(['product.price', 'store', 'creator'])
                    ->where('source', \App\Models\StockBatch::SOURCE_MANUAL)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();
                    
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
                    $actualCost = $pi->base_unit_cost ?? 0; // Using base unit cost from PO receive normalization
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
                        $mb->creator ? trim($mb->creator->surname . ' ' . $mb->creator->firstname) : 'System'
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
                // User can select a specific store via request. If none, we take all non-main.
                $storeId = $request->get('store_id');
                $storeQuery = \App\Models\Store::where('distribution_role', '!=', \App\Models\Store::ROLE_CENTRAL);
                if ($storeId) {
                    $storeQuery->where('id', $storeId);
                }
                $decentralizedStoreIds = $storeQuery->pluck('id');

                $stockQuery = \App\Models\StoreStock::with(['product.category', 'product.price', 'store'])
                    ->whereIn('store_id', $decentralizedStoreIds);
                
                if ($request->filled('product_type')) {
                    $stockQuery->whereHas('product', fn($q) => $q->where('product_type', $request->product_type));
                }
                if ($request->filled('category_id')) {
                    $stockQuery->whereHas('product', fn($q) => $q->where('category_id', $request->category_id));
                }
                $stocks = $stockQuery->get();

                $requisitions = \App\Models\StoreRequisition::with(['toStore', 'fromStore', 'items.product', 'requester'])
                    ->whereIn('to_store_id', $decentralizedStoreIds)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();
                    
                $damages = \App\Models\StoreDamage::with(['product', 'store', 'creator'])
                    ->whereIn('store_id', $decentralizedStoreIds)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();

                $kpis = [
                    ['label' => 'Decentralized Stock Value', 'value' => '₦' . number_format($stocks->sum(fn($s) => $s->quantity * optional(optional($s->product)->price)->initial_buy_price), 2), 'class' => 'text-primary'],
                    ['label' => 'Requisitions Made', 'value' => $requisitions->count(), 'class' => 'text-info'],
                    ['label' => 'Damaged/Expired (Qty)', 'value' => $damages->sum('quantity'), 'class' => 'text-danger']
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
                        $r->requester ? trim($r->requester->surname . ' ' . $r->requester->firstname) : 'N/A',
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
            $patientName = $bill->patient && $bill->patient->user 
                ? trim($bill->patient->user->surname . ' ' . $bill->patient->user->firstname . ' ' . $bill->patient->user->othername) 
                : ($bill->patient?->fullname ?? 'N/A');

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
                'settled_by' => $payment->staff_user ? trim($payment->staff_user->surname . ' ' . $payment->staff_user->firstname) : 'System Admin'
            ],
            'bills' => $billsData
        ]);
    }
}
