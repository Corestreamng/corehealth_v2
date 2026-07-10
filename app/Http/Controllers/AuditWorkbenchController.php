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
use App\Services\AuditReportService;

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
            'departmental_stores' => 'Departmental Stock & Requisitions',
            'ward_stores' => 'Ward Stock & Requisitions',
            'procurement_lifecycle' => 'Procurement Lifecycle (PO → Payment → Delivery)',
            'requisition_fulfillment' => 'Requisition & Fulfillment by Store Role',
            'physical_stock_verification' => 'Physical Stock Verification & Count'
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
        $itemOptions = [];
        $bankOptions = [];

        $responsibility_key = trim($responsibility_key);

        if ($responsibility_key === 'cash_and_billing_audit' || $responsibility_key === 'discounts_refunds_debt') {
            // Use raw DB query instead of Eloquent to avoid Model hydration memory overhead
            $cashiers = DB::table('users')->select('id', 'surname', 'firstname')->orderBy('surname')->get();
            foreach ($cashiers as $c) {
                $cashierOptions[$c->id] = trim($c->surname . ' ' . $c->firstname);
            }
            unset($cashiers);

            if ($responsibility_key === 'cash_and_billing_audit') {
                // Load product categories
                $prodCats = DB::table('product_categories')->select('id', 'category_name')->where('status', 1)->orderBy('category_name')->get();
                foreach ($prodCats as $pc) {
                    $categoryOptions['prod_' . $pc->id] = '[Product] ' . $pc->category_name;
                }
                // Load service categories
                $servCats = DB::table('service_categories')->select('id', 'category_name')->where('status', 1)->orderBy('category_name')->get();
                foreach ($servCats as $sc) {
                    $categoryOptions['serv_' . $sc->id] = '[Service] ' . $sc->category_name;
                }
                // Add wallet deposit & staff settlement categories
                $categoryOptions['wallet'] = '[Wallet] Wallet Top-up';
                $categoryOptions['settlement'] = '[Settlement] Staff Bill Settlement';

                // Load products
                $products = DB::table('products')->select('id', 'product_name')->where('status', 1)->orderBy('product_name')->get();
                foreach ($products as $pr) {
                    $itemOptions['prod_' . $pr->id] = '[Product] ' . $pr->product_name;
                }
                // Load services
                $services = DB::table('services')->select('id', 'service_name')->where('status', 1)->orderBy('service_name')->get();
                foreach ($services as $sv) {
                    $itemOptions['serv_' . $sv->id] = '[Service] ' . $sv->service_name;
                }
            }
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

        if ($responsibility_key === 'central_store_stock_check') {
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
                    ],
                    [
                        'name' => 'item_type',
                        'label' => 'Item Type',
                        'type' => 'select',
                        'options' => [
                            'product' => 'Product',
                            'service' => 'Service',
                            'wallet' => 'Wallet Deposit',
                            'settlement' => 'Staff Settlement'
                        ],
                        'value' => $request->get('item_type')
                    ],
                    [
                        'name' => 'item_category_id',
                        'label' => 'Item Category',
                        'type' => 'select',
                        'options' => $categoryOptions,
                        'value' => $request->get('item_category_id')
                    ],
                    [
                        'name' => 'item_id',
                        'label' => 'Specific Item/Service',
                        'type' => 'select',
                        'options' => $itemOptions,
                        'value' => $request->get('item_id')
                    ]
                ];

                $method = $request->get('payment_method');
                $cashierId = $request->get('cashier_id');
                $minAmount = $request->get('min_amount');
                $maxAmount = $request->get('max_amount');
                $itemType = $request->get('item_type');
                $itemCategoryId = $request->get('item_category_id');
                $itemId = $request->get('item_id');

                $fetchLimit = $request->get('length');
                if (is_null($fetchLimit)) {
                    $fetchLimit = $request->get('max_rows', 500);
                }
                $fetchLimit = (int) $fetchLimit;
                if ($fetchLimit <= 0) $fetchLimit = 10000;

                $filtersData = [
                    'payment_method' => $method,
                    'cashier_id' => $cashierId,
                    'min_amount' => $minAmount,
                    'max_amount' => $maxAmount,
                    'item_type' => $itemType,
                    'item_category_id' => $itemCategoryId,
                    'item_id' => $itemId,
                ];

                $reportService = new AuditReportService();

                // 1. Calculate Filtered KPIs dynamically
                $grossPayments = 0;
                $grossDeposits = 0;
                $regFees = 0;

                // A. Total Account Deposits
                $depQuery = $reportService->getWalletDepositsQuery($startDate, $endDate, $filtersData);
                if ($depQuery) {
                    $grossDeposits = $depQuery->sum('patient_deposits.amount');
                }

                // B. Gross Collections (Payments) & Registration Fees
                $hasItemFilters = !empty($itemType) || !empty($itemCategoryId) || !empty($itemId);
                if ($hasItemFilters) {
                    if ($itemType === 'wallet') {
                        $grossPayments = 0;
                    } elseif ($itemType === 'settlement') {
                        $settleQuery = $reportService->getSettlementsQuery($startDate, $endDate, $filtersData);
                        if ($settleQuery) {
                            $grossPayments = $settleQuery->sum('payments.total');
                        }
                    } else {
                        $reqQuery = $reportService->getUnifiedReceiptsQuery($startDate, $endDate, $filtersData);
                        if ($reqQuery) {
                            $grossPayments = $reqQuery->sum(DB::raw('COALESCE(posr.payable_amount, posr.amount)'));
                            $regFees = (clone $reqQuery)
                                ->where('sv.service_code', 'LIKE', '%REG%')
                                ->sum(DB::raw('COALESCE(posr.payable_amount, posr.amount)'));
                        }
                    }
                } else {
                    $kpiPayments = DB::table('payments')->whereBetween('created_at', [$startDate, $endDate]);
                    if ($method) {
                        $kpiPayments->where('payment_method', $method);
                    }
                    if ($cashierId) {
                        $kpiPayments->where('user_id', $cashierId);
                    }
                    if ($minAmount) {
                        $kpiPayments->where('total', '>=', $minAmount);
                    }
                    if ($maxAmount) {
                        $kpiPayments->where('total', '<=', $maxAmount);
                    }

                    $paymentsStats = $kpiPayments->selectRaw("
                        SUM(total) as gross_payments,
                        SUM(CASE WHEN payment_type = 'REGISTRATION' THEN total ELSE 0 END) as reg_fees
                    ")->first();

                    $grossPayments = $paymentsStats->gross_payments ?? 0;
                    $regFees = $paymentsStats->reg_fees ?? 0;
                }

                // C. Unbilled Value (Leakage)
                $leakageQueryBuilder = DB::table('product_or_service_requests as posr')
                    ->leftJoin('products as pr', 'posr.product_id', '=', 'pr.id')
                    ->leftJoin('services as sv', 'posr.service_id', '=', 'sv.id')
                    ->whereBetween('posr.created_at', [$startDate, $endDate])
                    ->whereNull('posr.payment_id')
                    ->whereNull('posr.invoice_id')
                    ->whereRaw('(posr.payable_amount > 0 OR posr.amount > 0)')
                    ->whereRaw('NOT ((posr.payable_amount IS NULL OR posr.payable_amount = 0) AND (posr.claims_amount > 0 AND posr.validation_status = ?))', ['approved'])
                    ->where(function($q) {
                        $q->whereNull('posr.hmo_id')->orWhere('posr.hmo_id', 1)->orWhere('posr.coverage_mode', 'cash');
                    });

                if (!empty($itemType)) {
                    if ($itemType === 'product') {
                        $leakageQueryBuilder->whereNotNull('posr.product_id');
                    } elseif ($itemType === 'service') {
                        $leakageQueryBuilder->whereNotNull('posr.service_id');
                    } else {
                        $leakageQueryBuilder->whereRaw('1 = 0');
                    }
                }
                if (!empty($itemCategoryId)) {
                    if (str_starts_with($itemCategoryId, 'prod_')) {
                        $catId = substr($itemCategoryId, 5);
                        $leakageQueryBuilder->where('pr.category_id', $catId);
                    } elseif (str_starts_with($itemCategoryId, 'serv_')) {
                        $catId = substr($itemCategoryId, 5);
                        $leakageQueryBuilder->where('sv.category_id', $catId);
                    } else {
                        $leakageQueryBuilder->whereRaw('1 = 0');
                    }
                }
                if (!empty($itemId)) {
                    if (str_starts_with($itemId, 'prod_')) {
                        $itmId = substr($itemId, 5);
                        $leakageQueryBuilder->where('posr.product_id', $itmId);
                    } elseif (str_starts_with($itemId, 'serv_')) {
                        $itmId = substr($itemId, 5);
                        $leakageQueryBuilder->where('posr.service_id', $itmId);
                    } else {
                        $leakageQueryBuilder->whereRaw('1 = 0');
                    }
                }

                $leakageTotal = $leakageQueryBuilder->sum(DB::raw('CASE WHEN posr.payable_amount > 0 THEN posr.payable_amount ELSE posr.amount END'));

                // 2. Row data is ONLY needed for AJAX (DataTables server-side) requests.
                $receiptRows = [];
                $leakageRows = [];

                if ($request->ajax()) {
                    $tab = $request->get('datatable_tab', 'default');

                    if ($tab === 'unified_receipts' || $tab === 'default') {
                        $receipts = collect();

                        // A. Fetch requests
                        $reqQuery = $reportService->getUnifiedReceiptsQuery($startDate, $endDate, $filtersData);
                        if ($reqQuery) {
                            $reqs = $reqQuery->select([
                                'posr.id',
                                'p.reference_no',
                                'p.payment_type',
                                'p.payment_method',
                                'posr.payable_amount',
                                'posr.amount',
                                'p.created_at',
                                'posr.user_id',
                                'cashier_user.surname as cashier_surname',
                                'cashier_user.firstname as cashier_firstname',
                                'cashier_user.othername as cashier_othername',
                                'patient_user.surname as patient_surname',
                                'patient_user.firstname as patient_firstname',
                                'patient_user.othername as patient_othername',
                                'pt.file_no as patient_file_no',
                                'pr.product_name',
                                'pc.category_name as product_category_name',
                                'sv.service_name',
                                'sc.category_name as service_category_name'
                            ])
                            ->orderBy('p.created_at', 'desc')
                            ->limit($fetchLimit)
                            ->get();

                            foreach ($reqs as $r) {
                                $isProd = !empty($r->product_name);
                                $receipts->push([
                                    'reference' => $r->reference_no ?? 'N/A',
                                    'cashier' => $this->formatStaffRawName($r->cashier_surname, $r->cashier_firstname, $r->cashier_othername),
                                    'patient' => $this->formatPatientNameLink($r->user_id, $r->patient_surname, $r->patient_firstname, $r->patient_othername, $r->patient_file_no),
                                    'type' => ucfirst(str_replace('_', ' ', $r->payment_type ?? 'N/A')),
                                    'item_type' => $isProd ? 'Product' : 'Service',
                                    'category' => $isProd ? ($r->product_category_name ?? 'Uncategorized') : ($r->service_category_name ?? 'Uncategorized'),
                                    'item_name' => $isProd ? ($r->product_name ?? 'N/A') : ($r->service_name ?? 'N/A'),
                                    'method' => $r->payment_method ?? 'N/A',
                                    'amount' => $r->payable_amount > 0 ? $r->payable_amount : $r->amount,
                                    'date' => Carbon::parse($r->created_at)->format('Y-m-d H:i'),
                                    'datetime' => $r->created_at
                                ]);
                            }
                        }

                        // B. Fetch deposits
                        $depQuery = $reportService->getWalletDepositsQuery($startDate, $endDate, $filtersData);
                        if ($depQuery) {
                            $deps = $depQuery->select([
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
                            ->limit($fetchLimit)
                            ->get();

                            foreach ($deps as $d) {
                                $receipts->push([
                                    'reference' => $d->deposit_number ?? 'N/A',
                                    'cashier' => $this->formatStaffRawName($d->receiver_surname, $d->receiver_firstname, $d->receiver_othername),
                                    'patient' => $this->formatPatientNameLink($d->patient_id, $d->patient_surname, $d->patient_firstname, $d->patient_othername, $d->patient_file_no),
                                    'type' => 'Account Deposit',
                                    'item_type' => 'Wallet Deposit',
                                    'category' => 'Wallet Top-up',
                                    'item_name' => 'N/A',
                                    'method' => $d->payment_method ?? 'N/A',
                                    'amount' => $d->amount,
                                    'date' => Carbon::parse($d->deposit_date)->format('Y-m-d H:i'),
                                    'datetime' => $d->deposit_date
                                ]);
                            }
                        }

                        // C. Fetch settlements
                        $settleQuery = $reportService->getSettlementsQuery($startDate, $endDate, $filtersData, true);
                        if ($settleQuery) {
                            $settles = $settleQuery->select([
                                'payments.id',
                                'payments.reference_no',
                                'payments.payment_method',
                                'payments.total',
                                'payments.created_at',
                                'cashier_user.surname as cashier_surname',
                                'cashier_user.firstname as cashier_firstname',
                                'cashier_user.othername as cashier_othername',
                                'patient_user.surname as patient_surname',
                                'patient_user.firstname as patient_firstname',
                                'patient_user.othername as patient_othername',
                                'pt.id as patient_id',
                                'pt.file_no as patient_file_no',
                                'orig_pay.reference_no as original_reference_no',
                                'sbpa.amount_allocated'
                            ])
                            ->orderBy('payments.created_at', 'desc')
                            ->limit($fetchLimit)
                            ->get();

                            foreach ($settles as $s) {
                                $patientName = $this->formatPatientNameLink(
                                    $s->patient_id,
                                    $s->patient_surname,
                                    $s->patient_firstname,
                                    $s->patient_othername,
                                    $s->patient_file_no
                                );

                                $receipts->push([
                                    'reference' => $s->reference_no ?? 'N/A',
                                    'cashier' => $this->formatStaffRawName($s->cashier_surname, $s->cashier_firstname, $s->cashier_othername),
                                    'patient' => $patientName,
                                    'type' => 'Staff Settlement',
                                    'item_type' => 'Staff Settlement',
                                    'category' => 'Staff Bill Settlement',
                                    'item_name' => $s->original_reference_no ? 'Settlement for ' . $s->original_reference_no : 'Staff Bill Settlement',
                                    'method' => $s->payment_method ?? 'N/A',
                                    'amount' => $s->amount_allocated ?? $s->total,
                                    'date' => Carbon::parse($s->created_at)->format('Y-m-d H:i'),
                                    'datetime' => $s->created_at
                                ]);
                            }
                        }

                        $receipts = $receipts->sortByDesc('datetime')->take(500);

                        foreach ($receipts as $r) {
                            $receiptRows[] = [
                                $r['reference'],
                                $r['cashier'],
                                $r['patient'],
                                $r['type'],
                                $r['item_type'],
                                $r['category'],
                                $r['item_name'],
                                $r['method'],
                                '₦' . number_format($r['amount'], 2),
                                $r['date']
                            ];
                        }
                        return DataTables::of($receiptRows)->escapeColumns([])->make(true);
                    }

                    if ($tab === 'revenue_leakage') {
                        $leakageQuery = $leakageQueryBuilder
                            ->select([
                                'posr.id',
                                'posr.payable_amount',
                                'posr.amount',
                                'posr.discount',
                                'posr.created_at',
                                'pt.id as patient_id',
                                'u.surname as user_surname',
                                'u.firstname as user_firstname',
                                'u.othername as user_othername',
                                'pt.file_no as patient_file_no',
                                'pr.product_name as product_name',
                                'sv.service_name as service_name'
                            ])
                            ->leftJoin('patients as pt', 'posr.user_id', '=', 'pt.user_id')
                            ->leftJoin('users as u', 'posr.user_id', '=', 'u.id')
                            ->orderBy('posr.created_at', 'desc')
                            ->limit($fetchLimit)
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
                        return DataTables::of($leakageRows)->escapeColumns([])->make(true);
                    }

                    if ($tab === 'type_performance') {
                        $typeStats = $reportService->getPerformanceByType($startDate, $endDate, $filtersData);
                        $typeRows = [];
                        foreach ($typeStats as $ts) {
                            $typeRows[] = [
                                $ts['type'],
                                $ts['count'],
                                '₦' . number_format($ts['revenue'], 2)
                            ];
                        }
                        return DataTables::of($typeRows)->escapeColumns([])->make(true);
                    }

                    if ($tab === 'category_performance') {
                        $catStats = $reportService->getPerformanceByCategory($startDate, $endDate, $filtersData);
                        $catRows = [];
                        foreach ($catStats as $cs) {
                            $catRows[] = [
                                $cs['type'],
                                $cs['category'],
                                $cs['count'],
                                '₦' . number_format($cs['revenue'], 2)
                            ];
                        }
                        return DataTables::of($catRows)->escapeColumns([])->make(true);
                    }

                    if ($tab === 'item_performance') {
                        $itemStats = $reportService->getPerformanceByItem($startDate, $endDate, $filtersData);
                        $itemRows = [];
                        foreach ($itemStats as $its) {
                            $itemRows[] = [
                                $its['type'],
                                $its['name'],
                                $its['count'],
                                '₦' . number_format($its['revenue'], 2)
                            ];
                        }
                        return DataTables::of($itemRows)->escapeColumns([])->make(true);
                    }
                } // end if ($request->ajax())

                $kpis = [
                    ['label' => 'Gross Collections (Payments)', 'value' => '₦' . number_format($grossPayments, 2), 'class' => 'text-success'],
                    ['label' => 'Total Account Deposits', 'value' => '₦' . number_format($grossDeposits, 2), 'class' => 'text-info'],
                    ['label' => 'Registration Fees', 'value' => '₦' . number_format($regFees, 2), 'class' => 'text-primary'],
                    ['label' => 'Unbilled Value (Leakage)', 'value' => '₦' . number_format($leakageTotal, 2), 'class' => 'text-danger']
                ];

                $shiftReconRows = $this->getShiftRevenueReconciliationData($startDate, $endDate);

                $tabbedData = [
                    'unified_receipts' => [
                        'label' => 'Unified Daily Receipts (Showing max ' . ($fetchLimit == 10000 ? 'All' : $fetchLimit) . ')',
                        'headers' => ['Reference No', 'Cashier', 'Patient', 'Type', 'Item/Service Type', 'Category', 'Item/Service Name', 'Method', 'Amount', 'Date'],
                        'rows' => $receiptRows
                    ],
                    'revenue_leakage' => [
                        'label' => 'Unbilled Self/Private Services (Showing max ' . ($fetchLimit == 10000 ? 'All' : $fetchLimit) . ')',
                        'headers' => ['Req ID', 'Patient', 'Item', 'Original Price', 'Discount', 'Leakage Value', 'Date'],
                        'rows' => $leakageRows
                    ],
                    'shift_revenue_recon' => [
                        'label' => 'Shift Revenue Reconciliation',
                        'headers' => ['Metric / Department', 'Amount (₦)'],
                        'rows' => $shiftReconRows
                    ],
                    'type_performance' => [
                        'label' => 'Performance by Transaction Type',
                        'headers' => ['Transaction Type', 'Transaction Count', 'Total Revenue'],
                        'rows' => []
                    ],
                    'category_performance' => [
                        'label' => 'Performance by Category',
                        'headers' => ['Category Type', 'Category Name', 'Transaction Count', 'Total Revenue'],
                        'rows' => []
                    ],
                    'item_performance' => [
                        'label' => 'Performance by Item / Service (Top 100)',
                        'headers' => ['Item Type', 'Item Name', 'Transaction Count', 'Total Revenue'],
                        'rows' => []
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

                $wardSummaryRows = $this->getWardSummaryData($startDate, $endDate);

                $tabbedData = [
                    'ward_summary' => [
                        'label' => 'Ward Admission/Discharge Summary',
                        'headers' => ['Ward Name', 'Admissions (Period)', 'Discharges (Period)', 'Currently Active', 'Est. Income'],
                        'rows' => $wardSummaryRows
                    ],
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
                    $proceduresQuery->where('procedure_status', $procStatus);
                    $procedureItemsQuery->whereHas('procedure', fn($pq) => $pq->where('procedure_status', $procStatus));
                }
                if ($minQty) {
                    $procedureItemsQuery->where('qty', '>=', $minQty);
                }

                $procedures = $proceduresQuery->orderBy('created_at', 'desc')->get();
                $procedureItems = $procedureItemsQuery->get();

                $kpis = [
                    ['label' => 'Total Procedures', 'value' => $procedures->count(), 'class' => 'text-primary'],
                    ['label' => 'Completed Procedures', 'value' => $procedures->where('procedure_status', 'completed')->count(), 'class' => 'text-success'],
                    ['label' => 'Bundled Items Used', 'value' => $procedureItems->sum('qty'), 'class' => 'text-warning'],
                    ['label' => 'Scheduled Procedures', 'value' => $procedures->where('procedure_status', 'scheduled')->count(), 'class' => 'text-info']
                ];

                $procRows = [];
                foreach ($procedures as $p) {
                    $procRows[] = [
                        $this->formatPatientModelLink($p->patient),
                        $p->service ? $p->service->service_name : 'N/A',
                        $this->formatStaffNameThree($p->requestedByUser),
                        '<span class="badge badge-primary">' . ucfirst($p->procedure_status) . '</span>',
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
                    'hmo_utilization' => [
                        'label' => 'HMO Scheme Utilization',
                        'headers' => ['Scheme Name', 'Procedures Done', 'Completed', 'Bundled Items Qty'],
                        'rows' => $this->getTheatreHmoUtilizationData($startDate, $endDate)
                    ],
                    'procedure_register' => [
                        'label' => 'Theatre Procedure Register',
                        'headers' => ['Patient', 'Procedure', 'Surgeon/Doctor', 'Status', 'Scheduled Date'],
                        'rows' => $procRows
                    ],
                    'bundled_consumables' => [
                        'label' => 'Bundled Consumables Consumption',
                        'headers' => ['Patient', 'Consumable Item', 'Quantity Used', 'Usage Date'],
                        'rows' => $itemRows
                    ],
                    'income_vs_consumption' => [
                        'label' => 'Income vs. Consumption',
                        'headers' => ['Category', 'Amount (₦)'],
                        'rows' => $this->getIncomeVsConsumptionData($startDate, $endDate, 'theatre')
                    ]
                ];
                break;

            case 'maternity_morgue_audit':
                $filters = [
                    [
                        'name' => 'type_of_delivery',
                        'label' => 'Delivery Type',
                        'type' => 'select',
                        'options' => [
                            'svd' => 'Spontaneous Vaginal Delivery',
                            'elective_cs' => 'Elective CS',
                            'emergency_cs' => 'Emergency CS',
                            'assisted_vaginal' => 'Assisted Vaginal',
                            'vacuum' => 'Vacuum',
                            'forceps' => 'Forceps'
                        ],
                        'value' => $request->get('type_of_delivery')
                    ],
                    [
                        'name' => 'morgue_status',
                        'label' => 'Morgue Status',
                        'type' => 'select',
                        'options' => ['stored' => 'Currently Admitted / Stored', 'released' => 'Released'],
                        'value' => $request->get('morgue_status')
                    ]
                ];

                $typeOfDelivery = $request->get('type_of_delivery');
                $morgueStatus = $request->get('morgue_status');

                $enrollments = \App\Models\MaternityEnrollment::with(['patient.user'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();

                $deliveriesQuery = \App\Models\DeliveryRecord::with(['patient.user'])
                    ->whereBetween('delivery_date', [$startDate, $endDate]);

                $morgueQuery = \App\Models\MorgueAdmission::with(['patient.user'])
                    ->whereBetween('created_at', [$startDate, $endDate]);

                if ($typeOfDelivery) {
                    $deliveriesQuery->where('type_of_delivery', $typeOfDelivery);
                }
                if ($morgueStatus) {
                    $morgueQuery->where('status', $morgueStatus);
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
                        ucwords(str_replace('_', ' ', $d->type_of_delivery)) ?? 'N/A',
                        $d->delivery_date ? $d->delivery_date->format('Y-m-d') : 'N/A'
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
                        'headers' => ['Patient', 'Delivery Type', 'Delivery Date'],
                        'rows' => $deliveryRows
                    ],
                    'mortuary_register' => [
                        'label' => 'Mortuary Register',
                        'headers' => ['Decedent Name', 'Admission Date', 'Release Date', 'Status'],
                        'rows' => $morgueRows
                    ],
                    'income_vs_consumption' => [
                        'label' => 'Income vs. Consumption (Morgue)',
                        'headers' => ['Category', 'Amount (₦)'],
                        'rows' => $this->getIncomeVsConsumptionData($startDate, $endDate, 'morgue')
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

                $incConsLab = $this->getIncomeVsConsumptionData($startDate, $endDate, 'lab');
                $kpis[] = ['label' => 'Total Reagents Cost', 'value' => '₦' . number_format($incConsLab['kpis']['total_consumption_value'], 2), 'class' => 'text-warning'];

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
                    ],
                    'income_vs_consumption' => [
                        'label' => 'Income vs Consumption (Margin)',
                        'headers' => ['Store', 'Product/Reagent', 'Qty Used', 'Unit Cost', 'Total Cost', 'Patient (Ref)', 'Billed Income', 'Gross Margin', 'Date'],
                        'rows' => $incConsLab['rows']
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

                $incConsImg = $this->getIncomeVsConsumptionData($startDate, $endDate, 'imaging');
                $kpis[] = ['label' => 'Total Consumables Cost', 'value' => '₦' . number_format($incConsImg['kpis']['total_consumption_value'], 2), 'class' => 'text-warning'];

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
                    ],
                    'income_vs_consumption' => [
                        'label' => 'Income vs Consumption (Margin)',
                        'headers' => ['Store', 'Product/Reagent', 'Qty Used', 'Unit Cost', 'Total Cost', 'Patient (Ref)', 'Billed Income', 'Gross Margin', 'Date'],
                        'rows' => $incConsImg['rows']
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

                $incConsPharm = $this->getIncomeVsConsumptionData($startDate, $endDate, 'pharmacy');
                $kpis[] = ['label' => 'Total Consumed Value', 'value' => '₦' . number_format($incConsPharm['kpis']['total_consumption_value'], 2), 'class' => 'text-warning'];

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
                    ],
                    'income_vs_consumption' => [
                        'label' => 'Income vs Consumption (Margin)',
                        'headers' => ['Store', 'Product/Reagent', 'Qty Used', 'Unit Cost', 'Total Cost', 'Patient (Ref)', 'Billed Income', 'Gross Margin', 'Date'],
                        'rows' => $incConsPharm['rows']
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

            case 'physical_stock_verification':
                $stores = \App\Models\Store::all();
                $storeOptions = [];
                foreach ($stores as $st) { $storeOptions[$st->id] = $st->store_name; }
                
                $storeId = $request->get('store_id') ?? ($stores->first()->id ?? null);
                $filters = [
                    [
                        'name' => 'store_id',
                        'label' => 'Store to Verify',
                        'type' => 'select',
                        'options' => $storeOptions,
                        'value' => $storeId
                    ]
                ];
                
                $stocks = \App\Models\StoreStock::with(['product.category', 'store'])
                    ->where('store_id', $storeId)
                    ->get();
                    
                $reconciliations = \App\Models\AuditReconciliation::with(['product', 'auditor'])
                    ->where('store_id', $storeId)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->get();

                $kpis = [
                    ['label' => 'Total Products in Store', 'value' => $stocks->count(), 'class' => 'text-primary'],
                    ['label' => 'Items Verified', 'value' => $reconciliations->count(), 'class' => 'text-success'],
                    ['label' => 'Net Variance Qty', 'value' => $reconciliations->sum('variance'), 'class' => 'text-warning']
                ];
                
                $verificationRows = [];
                foreach ($stocks as $s) {
                    $prodName = $s->product ? $s->product->product_name : 'Unknown';
                    $actionHtml = '<div class="d-flex gap-2">
                        <input type="number" step="any" class="form-control form-control-sm physical-count-input" id="phys_count_'.$s->id.'" value="'.$s->current_quantity.'" style="width:80px;">
                        <button class="btn btn-sm btn-outline-primary save-physical-count-btn" data-store="'.$storeId.'" data-product="'.$s->product_id.'" data-stock-id="'.$s->id.'" data-system="'.$s->current_quantity.'">Save</button>
                    </div>';
                    
                    $verificationRows[] = [
                        $prodName,
                        $s->product && $s->product->category ? $s->product->category->category_name : 'N/A',
                        '<span class="font-weight-bold" id="sys_qty_'.$s->id.'">'.$s->current_quantity.'</span>',
                        $actionHtml
                    ];
                }
                
                $historyRows = [];
                foreach ($reconciliations as $r) {
                    $historyRows[] = [
                        $r->product ? $r->product->product_name : 'N/A',
                        $r->system_value,
                        $r->physical_value,
                        $r->variance,
                        $r->notes ?? 'N/A',
                        $r->auditor ? $r->auditor->surname : 'N/A',
                        $r->created_at->format('Y-m-d H:i')
                    ];
                }
                
                $tabbedData = [
                    'verification_form' => [
                        'label' => 'Physical Count Form',
                        'headers' => ['Product', 'Category', 'System Quantity', 'Actual Physical Count'],
                        'rows' => $verificationRows
                    ],
                    'reconciliation_history' => [
                        'label' => 'Reconciliation History',
                        'headers' => ['Product', 'System Qty', 'Physical Qty', 'Variance', 'Notes', 'Auditor', 'Date'],
                        'rows' => $historyRows
                    ]
                ];
                break;

            case 'procurement_lifecycle':
                $filters = [
                    [
                        'name' => 'supplier_id',
                        'label' => 'Supplier',
                        'type' => 'select',
                        'options' => \App\Models\Supplier::pluck('company_name', 'id')->toArray(),
                        'value' => $request->get('supplier_id')
                    ],
                    [
                        'name' => 'status',
                        'label' => 'Delivery Status',
                        'type' => 'select',
                        'options' => \App\Models\PurchaseOrder::getStatuses(),
                        'value' => $request->get('status')
                    ],
                    [
                        'name' => 'payment_status',
                        'label' => 'Payment Status',
                        'type' => 'select',
                        'options' => \App\Models\PurchaseOrder::getPaymentStatuses(),
                        'value' => $request->get('payment_status')
                    ]
                ];

                $q = \App\Models\PurchaseOrder::with(['supplier', 'creator', 'targetStore'])
                    ->whereBetween('created_at', [$startDate, $endDate]);

                if ($request->filled('supplier_id')) $q->where('supplier_id', $request->get('supplier_id'));
                if ($request->filled('status')) $q->where('status', $request->get('status'));
                if ($request->filled('payment_status')) $q->where('payment_status', $request->get('payment_status'));

                $pos = $q->orderBy('created_at', 'desc')->get();

                $kpis = [
                    ['label' => 'Total POs', 'value' => $pos->count(), 'class' => 'text-primary'],
                    ['label' => 'Total Value', 'value' => '₦' . number_format($pos->sum('total_amount'), 2), 'class' => 'text-info'],
                    ['label' => 'Amount Paid', 'value' => '₦' . number_format($pos->sum('amount_paid'), 2), 'class' => 'text-success'],
                    ['label' => 'Outstanding Balance', 'value' => '₦' . number_format($pos->sum('total_amount') - $pos->sum('amount_paid'), 2), 'class' => 'text-danger']
                ];

                $poRows = [];
                foreach ($pos as $po) {
                    $deliveryBadge = match($po->status) {
                        'received' => '<span class="badge bg-success text-white">Received</span>',
                        'partial' => '<span class="badge bg-warning text-dark">Partially Received</span>',
                        'cancelled' => '<span class="badge bg-danger text-white">Cancelled</span>',
                        default => '<span class="badge bg-secondary text-white">'.ucfirst($po->status).'</span>',
                    };
                    $paymentBadge = match($po->payment_status) {
                        'paid' => '<span class="badge bg-success text-white">Paid</span>',
                        'partial' => '<span class="badge bg-warning text-dark">Partially Paid</span>',
                        default => '<span class="badge bg-danger text-white">Unpaid</span>',
                    };

                    $poRows[] = [
                        $po->po_number,
                        $po->supplier ? $po->supplier->company_name : 'N/A',
                        $po->targetStore ? $po->targetStore->store_name : 'N/A',
                        '₦' . number_format($po->total_amount, 2),
                        $deliveryBadge,
                        $paymentBadge,
                        $po->created_at->format('Y-m-d H:i')
                    ];
                }

                $tabbedData = [
                    'lifecycle' => [
                        'label' => 'Procurement Lifecycle',
                        'headers' => ['PO Number', 'Supplier', 'Target Store', 'Total Value', 'Delivery Status', 'Payment Status', 'Created Date'],
                        'rows' => $poRows
                    ]
                ];
                break;

            case 'requisition_fulfillment':
                $storeOptionsArr = \App\Models\Store::pluck('store_name', 'id')->toArray();
                $filters = [
                    [
                        'name' => 'from_store_id',
                        'label' => 'Requesting Store (From)',
                        'type' => 'select',
                        'options' => $storeOptionsArr,
                        'value' => $request->get('from_store_id')
                    ],
                    [
                        'name' => 'to_store_id',
                        'label' => 'Fulfilling Store (To)',
                        'type' => 'select',
                        'options' => $storeOptionsArr,
                        'value' => $request->get('to_store_id')
                    ],
                    [
                        'name' => 'status',
                        'label' => 'Status',
                        'type' => 'select',
                        'options' => ['pending'=>'Pending', 'approved'=>'Approved', 'partial'=>'Partial', 'fulfilled'=>'Fulfilled', 'rejected'=>'Rejected'],
                        'value' => $request->get('status')
                    ]
                ];

                $q = \App\Models\StoreRequisition::with(['fromStore', 'toStore', 'requester', 'items'])
                    ->whereBetween('created_at', [$startDate, $endDate]);

                if ($request->filled('from_store_id')) $q->where('from_store_id', $request->get('from_store_id'));
                if ($request->filled('to_store_id')) $q->where('to_store_id', $request->get('to_store_id'));
                if ($request->filled('status')) $q->where('status', $request->get('status'));

                $reqs = $q->orderBy('created_at', 'desc')->get();

                $kpis = [
                    ['label' => 'Total Requisitions', 'value' => $reqs->count(), 'class' => 'text-primary'],
                    ['label' => 'Fulfilled', 'value' => $reqs->where('status', 'fulfilled')->count(), 'class' => 'text-success'],
                    ['label' => 'Pending/Partial', 'value' => $reqs->whereIn('status', ['pending','partial'])->count(), 'class' => 'text-warning'],
                    ['label' => 'Rejected', 'value' => $reqs->where('status', 'rejected')->count(), 'class' => 'text-danger']
                ];

                $reqRows = [];
                foreach ($reqs as $r) {
                    $badge = match($r->status) {
                        'fulfilled' => '<span class="badge bg-success text-white">Fulfilled</span>',
                        'partial' => '<span class="badge bg-warning text-dark">Partial</span>',
                        'rejected' => '<span class="badge bg-danger text-white">Rejected</span>',
                        'approved' => '<span class="badge bg-info text-white">Approved</span>',
                        default => '<span class="badge bg-secondary text-white">Pending</span>',
                    };

                    $reqRows[] = [
                        $r->requisition_number,
                        $r->fromStore ? $r->fromStore->store_name : 'N/A',
                        $r->toStore ? $r->toStore->store_name : 'N/A',
                        $r->items->count(),
                        $badge,
                        $r->requester ? $this->formatStaffNameThree($r->requester) : 'N/A',
                        $r->created_at->format('Y-m-d H:i')
                    ];
                }

                $tabbedData = [
                    'fulfillment' => [
                        'label' => 'Requisition Fulfillment',
                        'headers' => ['Req Number', 'Requesting Store', 'Fulfilling Store', 'Items Count', 'Status', 'Requested By', 'Date'],
                        'rows' => $reqRows
                    ]
                ];
                break;

            case 'departmental_stores':
                $stores = \App\Models\Store::where(function($q) {
                    $q->where('distribution_role', \App\Models\Store::ROLE_DEPARTMENT)
                      ->orWhere('store_type', 'theatre');
                })->active()->orderBy('store_name')->get();
                
                $tabbedData = [];
                $totalStockValue = 0;
                $totalReqs = 0;
                $totalDamages = 0;
                $totalReturns = 0;

                foreach ($stores as $store) {
                    $stocks = \App\Models\StoreStock::with(['product.category', 'product.price'])
                        ->where('store_id', $store->id)->get();
                    $reqs = \App\Models\StoreRequisition::with(['toStore', 'fromStore', 'items.product', 'requester'])
                        ->where('to_store_id', $store->id)
                        ->whereBetween('created_at', [$startDate, $endDate])->get();
                    $damages = \App\Models\StoreDamage::with(['product', 'creator'])
                        ->where('store_id', $store->id)
                        ->whereBetween('created_at', [$startDate, $endDate])->get();
                    $returns = \App\Models\StoreRequisitionReturn::with(['product', 'creator'])
                        ->where('source_store_id', $store->id)
                        ->whereBetween('created_at', [$startDate, $endDate])->get();

                    $totalReqs += $reqs->count();
                    $totalDamages += $damages->sum('qty_damaged');
                    $totalReturns += $returns->sum('qty_returned');

                    $stockRows = [];
                    foreach ($stocks as $s) {
                        $val = $s->quantity * optional(optional($s->product)->price)->initial_buy_price;
                        $totalStockValue += $val;
                        $stockRows[] = [
                            $s->product ? $s->product->product_name : 'N/A',
                            $s->product ? ucfirst($s->product->product_type) : 'N/A',
                            $s->product && $s->product->category ? $s->product->category->category_name : 'N/A',
                            $s->quantity,
                            '₦' . number_format(optional(optional($s->product)->price)->initial_buy_price ?? 0, 2)
                        ];
                    }
                    
                    $reqRows = [];
                    foreach ($reqs as $r) {
                        $reqRows[] = [
                            $r->requisition_number ?? 'N/A',
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
                            $d->qty_damaged,
                            ucfirst($d->damage_type ?? 'N/A'),
                            $d->notes ?? 'N/A',
                            $d->created_at->format('Y-m-d H:i')
                        ];
                    }

                    if (count($stockRows) > 0) {
                        $tabbedData['dept_stock_'.$store->id] = [
                            'label' => $store->store_name . ' (Stock)',
                            'headers' => ['Product', 'Classification', 'Category', 'Current Qty', 'Sys Buy Price'],
                            'rows' => $stockRows
                        ];
                    }
                    if (count($reqRows) > 0) {
                        $tabbedData['dept_req_'.$store->id] = [
                            'label' => $store->store_name . ' (Reqs)',
                            'headers' => ['Req Number', 'Supplying Store', 'Items Count', 'Status', 'Requested By', 'Date'],
                            'rows' => $reqRows
                        ];
                    }
                    if (count($damageRows) > 0) {
                        $tabbedData['dept_damages_'.$store->id] = [
                            'label' => $store->store_name . ' (Damages)',
                            'headers' => ['Product', 'Quantity', 'Type', 'Notes', 'Date'],
                            'rows' => $damageRows
                        ];
                    }
                    
                    $returnRows = [];
                    foreach ($returns as $r) {
                        $returnRows[] = [
                            $r->product ? $r->product->product_name : 'N/A',
                            $r->qty_returned,
                            ucfirst($r->status ?? 'pending'),
                            $r->return_reason ?? 'N/A',
                            $this->formatStaffNameThree($r->creator),
                            $r->created_at->format('Y-m-d H:i')
                        ];
                    }
                    if (count($returnRows) > 0) {
                        $tabbedData['dept_returns_'.$store->id] = [
                            'label' => $store->store_name . ' (Returns)',
                            'headers' => ['Product', 'Quantity', 'Status', 'Reason', 'Returned By', 'Date'],
                            'rows' => $returnRows
                        ];
                    }
                }

                $kpis = [
                    ['label' => 'Total Stock Value', 'value' => '₦' . number_format($totalStockValue, 2), 'class' => 'text-primary'],
                    ['label' => 'Total Requisitions', 'value' => $totalReqs, 'class' => 'text-info'],
                    ['label' => 'Total Returns (Qty)', 'value' => $totalReturns, 'class' => 'text-warning'],
                    ['label' => 'Total Damaged/Expired', 'value' => $totalDamages, 'class' => 'text-danger']
                ];
                break;

            case 'ward_stores':
                $stores = \App\Models\Store::where('distribution_role', \App\Models\Store::ROLE_WARD)
                    ->active()->orderBy('store_name')->get();
                
                $tabbedData = [];
                $totalStockValue = 0;
                $totalReqs = 0;
                $totalDamages = 0;
                $totalReturns = 0;

                foreach ($stores as $store) {
                    $stocks = \App\Models\StoreStock::with(['product.category', 'product.price'])
                        ->where('store_id', $store->id)->get();
                    $reqs = \App\Models\StoreRequisition::with(['toStore', 'fromStore', 'items.product', 'requester'])
                        ->where('to_store_id', $store->id)
                        ->whereBetween('created_at', [$startDate, $endDate])->get();
                    $damages = \App\Models\StoreDamage::with(['product', 'creator'])
                        ->where('store_id', $store->id)
                        ->whereBetween('created_at', [$startDate, $endDate])->get();
                    $returns = \App\Models\StoreRequisitionReturn::with(['product', 'creator'])
                        ->where('source_store_id', $store->id)
                        ->whereBetween('created_at', [$startDate, $endDate])->get();

                    $totalReqs += $reqs->count();
                    $totalDamages += $damages->sum('qty_damaged');
                    $totalReturns += $returns->sum('qty_returned');

                    $stockRows = [];
                    foreach ($stocks as $s) {
                        $val = $s->quantity * optional(optional($s->product)->price)->initial_buy_price;
                        $totalStockValue += $val;
                        $stockRows[] = [
                            $s->product ? $s->product->product_name : 'N/A',
                            $s->product ? ucfirst($s->product->product_type) : 'N/A',
                            $s->product && $s->product->category ? $s->product->category->category_name : 'N/A',
                            $s->quantity,
                            '₦' . number_format(optional(optional($s->product)->price)->initial_buy_price ?? 0, 2)
                        ];
                    }
                    
                    $reqRows = [];
                    foreach ($reqs as $r) {
                        $reqRows[] = [
                            $r->requisition_number ?? 'N/A',
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
                            $d->qty_damaged,
                            ucfirst($d->damage_type ?? 'N/A'),
                            $d->notes ?? 'N/A',
                            $d->created_at->format('Y-m-d H:i')
                        ];
                    }

                    if (count($stockRows) > 0) {
                        $tabbedData['ward_stock_'.$store->id] = [
                            'label' => $store->store_name . ' (Stock)',
                            'headers' => ['Product', 'Classification', 'Category', 'Current Qty', 'Sys Buy Price'],
                            'rows' => $stockRows
                        ];
                    }
                    if (count($reqRows) > 0) {
                        $tabbedData['ward_req_'.$store->id] = [
                            'label' => $store->store_name . ' (Reqs)',
                            'headers' => ['Req Number', 'Supplying Store', 'Items Count', 'Status', 'Requested By', 'Date'],
                            'rows' => $reqRows
                        ];
                    }
                    if (count($damageRows) > 0) {
                        $tabbedData['ward_damages_'.$store->id] = [
                            'label' => $store->store_name . ' (Damages)',
                            'headers' => ['Product', 'Quantity', 'Type', 'Notes', 'Date'],
                            'rows' => $damageRows
                        ];
                    }

                    $returnRows = [];
                    foreach ($returns as $r) {
                        $returnRows[] = [
                            $r->product ? $r->product->product_name : 'N/A',
                            $r->qty_returned,
                            ucfirst($r->status ?? 'pending'),
                            $r->return_reason ?? 'N/A',
                            $this->formatStaffNameThree($r->creator),
                            $r->created_at->format('Y-m-d H:i')
                        ];
                    }
                    if (count($returnRows) > 0) {
                        $tabbedData['ward_returns_'.$store->id] = [
                            'label' => $store->store_name . ' (Returns)',
                            'headers' => ['Product', 'Quantity', 'Status', 'Reason', 'Returned By', 'Date'],
                            'rows' => $returnRows
                        ];
                    }
                }

                $kpis = [
                    ['label' => 'Total Stock Value', 'value' => '₦' . number_format($totalStockValue, 2), 'class' => 'text-primary'],
                    ['label' => 'Total Requisitions', 'value' => $totalReqs, 'class' => 'text-info'],
                    ['label' => 'Total Returns (Qty)', 'value' => $totalReturns, 'class' => 'text-warning'],
                    ['label' => 'Total Damaged/Expired', 'value' => $totalDamages, 'class' => 'text-danger']
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
        $chartHandled = false;
        
        switch ($responsibility_key) {
            case 'cash_and_billing_audit':
                $dailySums = [];
                $filtersData = [
                    'payment_method' => $request->get('payment_method'),
                    'cashier_id' => $request->get('cashier_id'),
                    'min_amount' => $request->get('min_amount'),
                    'max_amount' => $request->get('max_amount'),
                    'item_type' => $request->get('item_type'),
                    'item_category_id' => $request->get('item_category_id'),
                    'item_id' => $request->get('item_id'),
                ];
                $reportService = new AuditReportService();

                // A. Requests
                $reqQ = $reportService->getUnifiedReceiptsQuery($startDate, $endDate, $filtersData);
                if ($reqQ) {
                    $reqDaily = $reqQ->select([
                            DB::raw("DATE(p.created_at) as day_str"),
                            DB::raw("SUM(COALESCE(posr.payable_amount, posr.amount)) as day_sum")
                        ])
                        ->groupBy('day_str')
                        ->get();
                    foreach ($reqDaily as $rd) {
                        $dailySums[$rd->day_str] = ($dailySums[$rd->day_str] ?? 0) + (float)$rd->day_sum;
                    }
                }

                // B. Deposits
                $depQ = $reportService->getWalletDepositsQuery($startDate, $endDate, $filtersData);
                if ($depQ) {
                    $depDaily = $depQ->select([
                            DB::raw("DATE(patient_deposits.deposit_date) as day_str"),
                            DB::raw("SUM(patient_deposits.amount) as day_sum")
                        ])
                        ->groupBy('day_str')
                        ->get();
                    foreach ($depDaily as $dd) {
                        $dailySums[$dd->day_str] = ($dailySums[$dd->day_str] ?? 0) + (float)$dd->day_sum;
                    }
                }

                // C. Settlements
                $settleQ = $reportService->getSettlementsQuery($startDate, $endDate, $filtersData);
                if ($settleQ) {
                    $settleDaily = $settleQ->select([
                            DB::raw("DATE(payments.created_at) as day_str"),
                            DB::raw("SUM(payments.total) as day_sum")
                        ])
                        ->groupBy('day_str')
                        ->get();
                    foreach ($settleDaily as $sd) {
                        $dailySums[$sd->day_str] = ($dailySums[$sd->day_str] ?? 0) + (float)$sd->day_sum;
                    }
                }

                while ($current->lte($endDate)) {
                    $dayStr = $current->format('Y-m-d');
                    $chartLabels[] = $current->format('M d');
                    $chartDatasets[] = floatval($dailySums[$dayStr] ?? 0);
                    $current->addDay();
                }
                $chartHandled = true;
                break;
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

        if (!$chartHandled) {
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

    /**
     * Print report logic handling dynamic selection of tabs.
     * Uses sub-requests to retrieve the data for each selected tab natively.
     */
    public function printReport(Request $request, $responsibility_key)
    {
        if (!auth()->user()->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin']) && !auth()->user()->hasRole('AUDITOR')) {
            abort(403, 'Unauthorized access to Internal Audit.');
        }

        // Get base view data (KPIs, labels, structure)
        $viewResponse = $this->showReport($request, $responsibility_key);
        $viewData = $viewResponse->getData();
        
        $selectedTabs = $request->get('tabs', []);
        $maxRows = $request->get('max_rows', -1);
        
        // If tabs are present in tabbedData but none selected, default to all available
        if (empty($selectedTabs) && isset($viewData['tabbedData'])) {
            $selectedTabs = array_keys($viewData['tabbedData']);
        }
        $viewData['selectedTabs'] = $selectedTabs;

        // For each selected tab, simulate an AJAX request to pull all records (-1)
        if (isset($viewData['tabbedData'])) {
            foreach ($selectedTabs as $tabId) {
                if (isset($viewData['tabbedData'][$tabId])) {
                    $subRequest = $request->duplicate();
                    $subRequest->merge([
                        'datatable_tab' => $tabId,
                        'draw' => 1,
                        'start' => 0,
                        'length' => $maxRows
                    ]);
                    $subRequest->headers->set('X-Requested-With', 'XMLHttpRequest');

                    $ajaxResponse = $this->showReport($subRequest, $responsibility_key);
                    $json = json_decode($ajaxResponse->getContent(), true);

                    if (isset($json['data'])) {
                        $viewData['tabbedData'][$tabId]['rows'] = $json['data'];
                    }
                }
            }
        } else {
            // For single-table worksheet fallback, just simulate a general AJAX call
            $subRequest = $request->duplicate();
            $subRequest->merge(['draw' => 1, 'start' => 0, 'length' => $maxRows]);
            $subRequest->headers->set('X-Requested-With', 'XMLHttpRequest');

            $ajaxResponse = $this->showReport($subRequest, $responsibility_key);
            $json = json_decode($ajaxResponse->getContent(), true);
            
            if (isset($json['data'])) {
                $viewData['rows'] = $json['data'];
            }
        }

        return view('admin.audit.reports.print', $viewData);
    }

    /**
     * Helper to compute Income vs Consumption for various modules
     */
    private function getIncomeVsConsumptionData($startDate, $endDate, $moduleType)
    {
        $storeRoles = [];
        if ($moduleType === 'pharmacy') {
            $storeRoles = [\App\Models\Store::ROLE_PHARMACY_HUB, \App\Models\Store::ROLE_PHARMACY_SATELLITE];
        } elseif ($moduleType === 'lab') {
            $storeRoles = [\App\Models\Store::ROLE_LAB];
        } elseif ($moduleType === 'imaging') {
            $storeRoles = [\App\Models\Store::ROLE_IMAGING];
        } elseif ($moduleType === 'ward') {
            $storeRoles = [\App\Models\Store::ROLE_WARD];
        } elseif ($moduleType === 'theatre') {
            $storeIds = \App\Models\Store::where('store_type', 'theatre')->pluck('id');
        } elseif ($moduleType === 'morgue') {
            $storeIds = \App\Models\Store::where('store_name', 'like', '%morgue%')->pluck('id');
        }

        if (!isset($storeIds)) {
            $storeIds = \App\Models\Store::whereIn('distribution_role', $storeRoles)->pluck('id');
        }

        $consumptions = \App\Models\StockBatchTransaction::with(['stockBatch.product.price', 'stockBatch.store'])
            ->where('type', \App\Models\StockBatchTransaction::TYPE_OUT)
            ->whereHas('stockBatch', function($q) use ($storeIds) {
                $q->whereIn('store_id', $storeIds);
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        $consumptionRows = [];
        $totalConsumptionValue = 0;
        $totalItemsDispensed = 0;

        foreach ($consumptions as $c) {
            $qty = (float)$c->qty;
            $costPrice = (float)($c->stockBatch->cost_price ?? 0);
            if ($costPrice <= 0 && $c->stockBatch->product && $c->stockBatch->product->price) {
                $costPrice = (float)$c->stockBatch->product->price->initial_buy_price;
            }
            
            $value = $qty * $costPrice;
            $totalConsumptionValue += $value;
            $totalItemsDispensed += $qty;

            $incomeValue = 0;
            $patientName = 'Unknown';
            $billRef = 'N/A';

            if ($c->reference_type === 'ProductRequest' && $c->reference_id) {
                $pr = \App\Models\ProductRequest::with(['productOrServiceRequest', 'patient.user'])->find($c->reference_id);
                if ($pr) {
                    $patientName = $pr->patient && $pr->patient->user ? $pr->patient->user->surname . ' ' . $pr->patient->user->firstname : 'Unknown';
                    if ($pr->productOrServiceRequest) {
                        $incomeValue = (float)$pr->productOrServiceRequest->payable_amount;
                        $billRef = $pr->productOrServiceRequest->request_number ?? 'Billed';
                    }
                }
            } elseif ($c->reference_type === 'ProductOrServiceRequest' && $c->reference_id) {
                $posr = \App\Models\ProductOrServiceRequest::with('patient.user')->find($c->reference_id);
                if ($posr) {
                    $patientName = $posr->patient && $posr->patient->user ? $posr->patient->user->surname . ' ' . $posr->patient->user->firstname : 'Unknown';
                    $incomeValue = (float)$posr->payable_amount;
                    $billRef = $posr->request_number ?? 'Billed';
                }
            } elseif ($c->reference_type === 'LabServiceRequest' && $c->reference_id) {
                 $lsr = \App\Models\LabServiceRequest::with(['productOrServiceRequest', 'patient.user'])->find($c->reference_id);
                 if ($lsr) {
                    $patientName = $lsr->patient && $lsr->patient->user ? $lsr->patient->user->surname . ' ' . $lsr->patient->user->firstname : 'Unknown';
                    if ($lsr->productOrServiceRequest) {
                        $incomeValue = (float)$lsr->productOrServiceRequest->payable_amount;
                        $billRef = $lsr->productOrServiceRequest->request_number ?? 'Billed';
                    }
                 }
            } elseif ($c->reference_type === 'ImagingServiceRequest' && $c->reference_id) {
                 $isr = \App\Models\ImagingServiceRequest::with(['productOrServiceRequest', 'patient.user'])->find($c->reference_id);
                 if ($isr) {
                    $patientName = $isr->patient && $isr->patient->user ? $isr->patient->user->surname . ' ' . $isr->patient->user->firstname : 'Unknown';
                    if ($isr->productOrServiceRequest) {
                        $incomeValue = (float)$isr->productOrServiceRequest->payable_amount;
                        $billRef = $isr->productOrServiceRequest->request_number ?? 'Billed';
                    }
                 }
            }

            $margin = $incomeValue - $value;

            $consumptionRows[] = [
                $c->stockBatch->store->store_name ?? 'Unknown Store',
                $c->stockBatch->product->product_name ?? 'Unknown Product',
                number_format($qty, 2),
                '₦' . number_format($costPrice, 2),
                '₦' . number_format($value, 2),
                $patientName . ' (' . $billRef . ')',
                '₦' . number_format($incomeValue, 2),
                '<span class="' . ($margin >= 0 ? 'text-success' : 'text-danger') . ' font-weight-bold">₦' . number_format($margin, 2) . '</span>',
                $c->created_at->format('Y-m-d H:i')
            ];
        }

        return [
            'rows' => $consumptionRows,
            'kpis' => [
                'total_items_dispensed' => $totalItemsDispensed,
                'total_consumption_value' => $totalConsumptionValue
            ]
        ];
    }

    private function getShiftRevenueReconciliationData($startDate, $endDate)
    {
        $posrs = \App\Models\ProductOrServiceRequest::with(['service.category'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where(function($q) {
                 $q->whereNull('hmo_id')->orWhere('hmo_id', 1)->orWhere('coverage_mode', 'cash');
            })
            ->get();
            
        $labExpected = 0;
        $pharmExpected = 0;
        $imagingExpected = 0;
        $regExpected = 0;
        $otherExpected = 0;
        
        foreach($posrs as $p) {
            $amt = $p->payable_amount > 0 ? (float)$p->payable_amount : (float)$p->amount;
            if ($p->product_id) {
                $pharmExpected += $amt;
            } elseif ($p->service_id) {
                $catName = strtolower($p->service->category->category_name ?? '');
                if (str_contains($catName, 'lab') || str_contains($catName, 'pathology')) {
                    $labExpected += $amt;
                } elseif (str_contains($catName, 'scan') || str_contains($catName, 'imaging') || str_contains($catName, 'x-ray')) {
                    $imagingExpected += $amt;
                } elseif (str_contains($catName, 'registration') || str_contains($catName, 'consultation')) {
                    $regExpected += $amt;
                } else {
                    $otherExpected += $amt;
                }
            } else {
                $otherExpected += $amt;
            }
        }
        
        $totalExpected = $labExpected + $pharmExpected + $imagingExpected + $regExpected + $otherExpected;
        
        $payments = \App\Models\Payment::whereBetween('created_at', [$startDate, $endDate])->get();
        $cashCollected = (float)$payments->where('payment_method', 'CASH')->sum('total');
        $posCollected = (float)$payments->whereIn('payment_method', ['POS', 'TRANSFER', 'CARD'])->sum('total');
        
        $variance = $totalExpected - ($cashCollected + $posCollected);
        
        return [
            ['Expected Revenue: Lab', '₦' . number_format($labExpected, 2)],
            ['Expected Revenue: Pharmacy', '₦' . number_format($pharmExpected, 2)],
            ['Expected Revenue: Imaging', '₦' . number_format($imagingExpected, 2)],
            ['Expected Revenue: Registration/Consultation', '₦' . number_format($regExpected, 2)],
            ['Expected Revenue: Others', '₦' . number_format($otherExpected, 2)],
            ['<strong>Total Expected System Revenue</strong>', '<strong>₦' . number_format($totalExpected, 2) . '</strong>'],
            ['Actual Cash Collected', '₦' . number_format($cashCollected, 2)],
            ['Actual POS/Bank Collected', '₦' . number_format($posCollected, 2)],
            ['<strong>Variance (Expected - Actual)</strong>', '<strong class="' . ($variance > 0 ? 'text-danger' : 'text-success') . '">₦' . number_format($variance, 2) . '</strong>']
        ];
    }
    
    public function approveStaffBill($id)
    {
        if (!auth()->user()->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin']) && !auth()->user()->hasRole('AUDITOR')) {
            abort(403, 'Unauthorized action.');
        }
        
        $bill = \App\Models\StaffBill::findOrFail($id);
        if ($bill->status !== 'pending_audit') {
            return response()->json(['message' => 'Bill is not in pending audit state.'], 400);
        }
        
        $bill->status = 'pending'; // Approved and now acts as receivable
        $bill->save();
        
        return response()->json(['success' => true, 'message' => 'Staff bill audited and approved as receivable.']);
    }

    private function getWardSummaryData($startDate, $endDate)
    {
        $wards = \App\Models\Ward::all();
        $wardRows = [];
        
        foreach ($wards as $ward) {
            $admissionsPeriod = \App\Models\AdmissionRequest::where('preferred_ward_id', $ward->id)
                ->whereBetween('created_at', [$startDate, $endDate])->count();
                
            $dischargesPeriod = \App\Models\AdmissionRequest::where('preferred_ward_id', $ward->id)
                ->where('discharged', 1)
                ->whereBetween('updated_at', [$startDate, $endDate])->count();
                
            $activeCount = \App\Models\AdmissionRequest::where('preferred_ward_id', $ward->id)
                ->where('discharged', 0)->count();
                
            $income = \App\Models\Payment::whereIn('patient_id', function($query) use ($ward) {
                        $query->select('patient_id')
                              ->from('admission_requests')
                              ->where('discharged', 0)
                              ->where('preferred_ward_id', $ward->id);
                    })->whereBetween('created_at', [$startDate, $endDate])->sum('total');
                    
            $wardRows[] = [
                $ward->name,
                $admissionsPeriod,
                $dischargesPeriod,
                $activeCount,
                '₦' . number_format((float)$income, 2)
            ];
        }
        return $wardRows;
    }

    private function getTheatreHmoUtilizationData($startDate, $endDate)
    {
        $schemes = \App\Models\HmoScheme::all();
        $rows = [];

        foreach ($schemes as $scheme) {
            $procedures = \App\Models\Procedure::whereHas('patient.hmo', function($q) use ($scheme) {
                $q->where('hmo_scheme_id', $scheme->id);
            })->whereBetween('created_at', [$startDate, $endDate])->get();

            $totalProcedures = $procedures->count();
            if ($totalProcedures === 0) continue;

            $completedCount = $procedures->where('status', 'completed')->count();

            $itemsQty = \App\Models\ProcedureItem::whereIn('procedure_id', $procedures->pluck('id'))
                ->where('is_bundled', 1)
                ->sum('qty');

            $rows[] = [
                $scheme->name,
                $totalProcedures,
                $completedCount,
                $itemsQty
            ];
        }

        $privateProcedures = \App\Models\Procedure::whereHas('patient', function($q) {
            $q->whereNull('hmo_id');
        })->whereBetween('created_at', [$startDate, $endDate])->get();

        if ($privateProcedures->count() > 0) {
            $privateItemsQty = \App\Models\ProcedureItem::whereIn('procedure_id', $privateProcedures->pluck('id'))
                ->where('is_bundled', 1)
                ->sum('qty');
            $rows[] = [
                'Private / Out-of-Pocket',
                $privateProcedures->count(),
                $privateProcedures->where('status', 'completed')->count(),
                $privateItemsQty
            ];
        }

        return $rows;
    }

    public function savePhysicalCount(\Illuminate\Http\Request $request)
    {
        if (!auth()->user()->hasAnyRole(['SUPERADMIN', 'ADMIN', 'super-admin']) && !auth()->user()->hasRole('AUDITOR')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'store_id' => 'required|integer',
            'product_id' => 'required|integer',
            'system_value' => 'required|numeric',
            'physical_value' => 'required|numeric'
        ]);

        $variance = $request->physical_value - $request->system_value;

        \App\Models\AuditReconciliation::create([
            'type' => 'physical_stock_verification',
            'store_id' => $request->store_id,
            'product_id' => $request->product_id,
            'system_value' => $request->system_value,
            'physical_value' => $request->physical_value,
            'variance' => $variance,
            'notes' => 'Recorded via physical stock verification worksheet.',
            'auditor_id' => auth()->id(),
        ]);
        
        // Also update the store stock physical quantity to match reality
        $stock = \App\Models\StoreStock::where('store_id', $request->store_id)
            ->where('product_id', $request->product_id)
            ->first();
            
        if ($stock) {
            $stock->current_quantity = $request->physical_value;
            $stock->save();
        }

        return response()->json(['success' => true, 'message' => 'Physical count saved and variance recorded.']);
    }
}
