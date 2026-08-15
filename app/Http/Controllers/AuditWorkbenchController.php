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
    /**
     * Standardized helper to render patient details across all audit tables.
     */
    protected function renderPatientDetails($patient, $defaultName = 'Patient')
    {
        if (!$patient) {
            return '<div class="font-weight-bold text-dark">' . $defaultName . '</div>';
        }

        $name = $patient->user->name ?? $defaultName;
        $fileNo = $patient->file_no ?? 'N/A';
        $hmoName = $patient->hmo->name ?? 'Private/Cash';
        $schemeName = $patient->hmo->scheme->name ?? '';
        $hmoNo = $patient->hmo_no ?? 'N/A';

        $html = '<div class="font-weight-bold text-dark"><i class="mdi mdi-account"></i> ' . $name . '</div>';
        $html .= '<small class="text-muted d-block" style="line-height: 1.2;"><i class="mdi mdi-folder-account"></i> File: #' . $fileNo . '</small>';

        if ($patient->hmo_id) {
            $schemeDisplay = $schemeName ? ' - ' . $schemeName : '';
            $html .= '<small class="text-info d-block" style="line-height: 1.2;"><i class="mdi mdi-shield-account"></i> ' . $hmoName . $schemeDisplay . ' (ID: ' . $hmoNo . ')</small>';
        } else {
            $html .= '<small class="text-success d-block" style="line-height: 1.2;"><i class="mdi mdi-cash"></i> ' . $hmoName . '</small>';
        }

        return $html;
    }

    /**
     * Helper to render patient details from database row query objects in drilldown modals.
     */
    protected function renderPatientDetailsFromRow($r)
    {
        if (!$r) {
            return '<div class="font-weight-bold text-dark">Walk-in / N/A</div>';
        }

        $name = !empty(trim($r->patient_name ?? '')) ? trim($r->patient_name) : (!empty(trim($r->patient_user_name ?? '')) ? trim($r->patient_user_name) : 'Walk-in / N/A');
        $fileNo = $r->file_no ?? 'N/A';
        $hmoName = $r->hmo_name ?? 'Private/Cash';
        $schemeName = $r->scheme_name ?? '';
        $hmoNo = $r->hmo_no ?? 'N/A';

        if ($name === 'Walk-in / N/A' && $fileNo === 'N/A' && empty($r->hmo_name)) {
            return '<div class="font-weight-bold text-dark">Walk-in / N/A</div>';
        }

        $html = '<div class="font-weight-bold text-dark"><i class="mdi mdi-account"></i> ' . e($name) . '</div>';
        $html .= '<small class="text-muted d-block" style="line-height: 1.2;"><i class="mdi mdi-folder-account"></i> File: #' . e($fileNo) . '</small>';

        if (!empty($r->hmo_name) || !empty($r->hmo_id)) {
            $schemeDisplay = $schemeName ? ' - ' . $schemeName : '';
            $html .= '<small class="text-info d-block" style="line-height: 1.2;"><i class="mdi mdi-shield-account"></i> ' . e($hmoName) . e($schemeDisplay) . ' (ID: ' . e($hmoNo) . ')</small>';
        } else {
            $html .= '<small class="text-success d-block" style="line-height: 1.2;"><i class="mdi mdi-cash"></i> ' . e($hmoName) . '</small>';
        }

        return $html;
    }

    /**
     * Standardized helper to render payment entity details (Patient, Organization, or Staff)
     */
    protected function renderPaymentEntityDetails($r, $defaultName = 'Walk-in / N/A')
    {
        if (!$r) {
            return '<div class="font-weight-bold text-dark">' . $defaultName . '</div>';
        }

        $isStaff = ($r->payment_type === 'STAFF_BILL_SETTLEMENT' || $r->payment_method === 'BILL_TO_STAFF' || $r->payment_method === 'STAFF_BILL');
        $isOrg = ($r->payment_type === 'ORGANIZATION_BILL_SETTLEMENT' || $r->payment_method === 'BILL_TO_ORG' || $r->payment_method === 'ORG_BILL');

        if ($isStaff) {
            $staffBill = \App\Models\StaffBill::where('settlement_payment_id', $r->id)->orWhere('payment_id', $r->id)->with('staffUser')->first();
            if (!$staffBill) {
                $alloc = \DB::table('staff_bill_payment_allocations')->where('payment_id', $r->id)->first();
                if ($alloc) {
                    $staffBill = \App\Models\StaffBill::with('staffUser')->find($alloc->staff_bill_id);
                }
            }
            if (!$staffBill) {
                $staffBill = \App\Models\StaffBill::with('staffUser')->first();
            }
            $name = $staffBill->staffUser->name ?? 'Staff Member';
            return '<div class="font-weight-bold text-dark"><i class="mdi mdi-account-tie text-primary"></i> ' . e($name) . '</div><small class="badge bg-primary text-white mt-1">Staff Bill</small>';
        }

        if ($isOrg) {
            $orgBill = \App\Models\OrganizationBill::where('settlement_payment_id', $r->id)->orWhere('payment_id', $r->id)->with('organization')->first();
            if (!$orgBill) {
                $alloc = \DB::table('organization_bill_payment_allocations')->where('payment_id', $r->id)->first();
                if ($alloc) {
                    $orgBill = \App\Models\OrganizationBill::with('organization')->find($alloc->organization_bill_id);
                }
            }
            if (!$orgBill) {
                $posr = \App\Models\ProductOrServiceRequest::where('payment_id', $r->id)->with('organization')->first();
                if ($posr && $posr->organization) {
                    $name = $posr->organization->name ?? $posr->organization->company_name;
                    return '<div class="font-weight-bold text-dark"><i class="mdi mdi-domain text-info"></i> ' . e($name) . '</div><small class="badge bg-info text-white mt-1">Corporate Retainership</small>';
                }
            }
            if (!$orgBill) {
                $orgBill = \App\Models\OrganizationBill::with('organization')->first();
            }
            $name = $orgBill->organization->name ?? $orgBill->organization->company_name ?? 'Organization';
            return '<div class="font-weight-bold text-dark"><i class="mdi mdi-domain text-info"></i> ' . e($name) . '</div><small class="badge bg-info text-white mt-1">Corporate Retainership</small>';
        }

        $patient = $r->patient;
        if (!$patient) {
            $posr = \App\Models\ProductOrServiceRequest::where('payment_id', $r->id)->with('patient.user', 'patient.hmo.scheme')->first();
            if ($posr && $posr->patient) {
                $patient = $posr->patient;
            }
        }

        return $this->renderPatientDetails($patient, $defaultName);
    }

    /**
     * Standardized helper to resolve and render associated service(s) / product(s) / items for ANY payment.
     */
    protected function renderPaymentItemDetails($r)
    {
        if (!$r) {
            return '<div class="font-weight-bold text-dark"><i class="mdi mdi-cash-register text-muted me-1"></i> Medical Service Checkout</div><small class="text-muted d-block mt-0.5" style="line-height:1.2; font-size:0.75rem;"><i class="mdi mdi-tag-outline me-1"></i> Service • Checkout</small>';
        }

        $items = $r->product_or_service_request;

        if ($items && $items->count() > 0) {
            $firstItem = $items->first();
            $firstName = $firstItem->service->service_name ?? $firstItem->product->product_name ?? 'Medical Request Item';
            $isService = !empty($firstItem->service_id) || !empty($firstItem->service);
            $isProduct = !empty($firstItem->product_id) || !empty($firstItem->product);
            
            $itemTypeTag = $isService ? 'Service' : ($isProduct ? 'Product' : 'Medical Item');
            $icon = $isService ? 'mdi-stethoscope text-primary' : ($isProduct ? 'mdi-pill text-info' : 'mdi-medical-bag text-primary');
            $catName = $firstItem->service->category->category_name ?? $firstItem->service->category->name ?? $firstItem->product->category->category_name ?? $firstItem->product->category->name ?? ($isService ? 'Clinical Service' : ($isProduct ? 'Pharmacy / Inventory' : 'Healthcare'));
            $subtext = '<small class="text-muted d-block mt-0.5" style="line-height:1.2; font-size:0.75rem;"><i class="mdi ' . $icon . ' me-1"></i> ' . e($itemTypeTag) . ' • ' . e($catName) . '</small>';


            if ($items->count() == 1) {
                return '<div class="font-weight-bold text-dark"><i class="mdi mdi-medical-bag text-primary me-1"></i> ' . e($firstName) . '</div>' . $subtext;
            } else {
                return '<div class="font-weight-bold text-dark"><i class="mdi mdi-medical-bag text-primary me-1"></i> ' . e($firstName) . '</div>' . $subtext . '<small class="badge bg-light text-dark border mt-1">+ ' . ($items->count() - 1) . ' other item(s)</small>';
            }
        }

        if ($r->payment_type === 'STAFF_BILL_SETTLEMENT' || $r->payment_method === 'BILL_TO_STAFF') {
            $staffBill = \App\Models\StaffBill::where('settlement_payment_id', $r->id)->orWhere('payment_id', $r->id)->first();
            if (!$staffBill) { $staffBill = \App\Models\StaffBill::first(); }
            $code = $staffBill ? ($staffBill->bill_code ?? ('SB-' . $staffBill->id)) : 'N/A';
            return '<div class="font-weight-bold text-dark"><i class="mdi mdi-receipt text-primary me-1"></i> Staff Bill Settlement</div><small class="text-muted d-block mt-0.5" style="line-height:1.2; font-size:0.75rem;"><i class="mdi mdi-barcode"></i> Service • Code: #' . e($code) . '</small>';
        }

        if ($r->payment_type === 'ORGANIZATION_BILL_SETTLEMENT' || $r->payment_method === 'BILL_TO_ORG') {
            $orgBill = \App\Models\OrganizationBill::where('settlement_payment_id', $r->id)->orWhere('payment_id', $r->id)->first();
            if (!$orgBill) { $orgBill = \App\Models\OrganizationBill::first(); }
            $code = $orgBill ? ($orgBill->bill_code ?? ('OB-' . $orgBill->id)) : 'N/A';
            return '<div class="font-weight-bold text-dark"><i class="mdi mdi-domain text-info me-1"></i> Corporate Bill Settlement</div><small class="text-muted d-block mt-0.5" style="line-height:1.2; font-size:0.75rem;"><i class="mdi mdi-barcode"></i> Service • Code: #' . e($code) . '</small>';
        }

        if (in_array($r->payment_type, ['ACC_DEPOSIT', 'WALLET_DEPOSIT'])) {
            return '<div class="font-weight-bold text-success"><i class="mdi mdi-wallet text-success me-1"></i> Patient Account Deposit</div><small class="text-muted d-block mt-0.5" style="line-height:1.2; font-size:0.75rem;"><i class="mdi mdi-tag-outline me-1"></i> Service • Wallet Credit</small>';
        }

        if (in_array($r->payment_type, ['ACC_WITHDRAW', 'REFUND'])) {
            return '<div class="font-weight-bold text-warning"><i class="mdi mdi-cash-refund text-warning me-1"></i> Patient Account Refund / Withdraw</div><small class="text-muted d-block mt-0.5" style="line-height:1.2; font-size:0.75rem;"><i class="mdi mdi-tag-outline me-1"></i> Service • Wallet Debit</small>';
        }

        return '<div class="font-weight-bold text-dark"><i class="mdi mdi-cash-register text-muted me-1"></i> Medical Service Checkout</div><small class="text-muted d-block mt-0.5" style="line-height:1.2; font-size:0.75rem;"><i class="mdi mdi-tag-outline me-1"></i> Service • Checkout</small>';
    }

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
            ->select(
                'user_id',
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
            ->select(
                'hmos.name as hmo_name',
                DB::raw('COUNT(*) as claim_count'),
                DB::raw('SUM(posr.payable_amount) as total_payable'),
                DB::raw('SUM(posr.claims_amount) as total_claim')
            )
            ->where(function ($q) {
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
        $payrollBreakdown = Staff::with(['user', 'department', 'salaryProfiles' => function ($q) {
            $q->where('is_active', true);
        }])
            ->where('status', 'active')
            ->whereHas('department', function ($q) {
                $q->where('name', 'NOT LIKE', '%midwifery%');
            })
            ->get()
            ->groupBy(fn($item) => $item->department->name ?? 'Unassigned')
            ->map(function ($staffList) {
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
            ->where(function ($q) {
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

        return view('admin.audit_workbench.index', compact(
            'startDate',
            'endDate',
            'staffWithBills',
            'allStaffBills',
            'activeBanks',
            'stamps',
            'cashierSummary',
            'hmoClaims',
            'payrollBreakdown',
            'consultingQueues',
            'inpatientCount',
            'occupiedBedsCount',
            'totalBedsCount',
            'theatreBundles',
            'morgueCount',
            'labStoresRequisitions',
            'labServiceCount',
            'imagingServiceCount',
            'reconciliationKPIs',
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
        $payment = DB::transaction(function () use ($bills, $staff, $paymentMethod, $bankId, $amountPaid, $discountAmount) {
            $ref = 'SETTL-' . strtoupper(uniqid());
            $patients = $bills->map(fn($b) => $b->patient?->user?->name ?? 'N/A')->unique()->implode(', ');

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
                    ->where(function ($q) {
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
                        $c->product ? ('Drug: ' . $c->product->product_name) : ($c->service ? ('Service: ' . $c->service->service_name) : 'N/A'),
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
                    $activeAdmissionsQuery->where(function ($q) use ($wardId) {
                        $q->where('preferred_ward_id', $wardId)
                            ->orWhereHas('bed.wardRelation', fn($bq) => $bq->where('id', $wardId));
                    });
                    $admissionsQuery->where(function ($q) use ($wardId) {
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
                    ->whereIn('patient_id', function ($query) use ($wardId) {
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
                    ->whereHas('purchaseOrder', function ($q) use ($startDate, $endDate) {
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
                        '<span class="' . ($variance > 0 ? 'text-danger' : ($variance < 0 ? 'text-success' : '')) . ' font-weight-bold">₦' . number_format($variance, 2) . '</span>',
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
                        '<span class="' . ($variance > 0 ? 'text-danger' : ($variance < 0 ? 'text-success' : '')) . ' font-weight-bold">₦' . number_format($variance, 2) . '</span>',
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
                foreach ($stores as $st) {
                    $storeOptions[$st->id] = $st->store_name;
                }

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
                        <input type="number" step="any" class="form-control form-control-sm physical-count-input" id="phys_count_' . $s->id . '" value="' . $s->current_quantity . '" style="width:80px;">
                        <button class="btn btn-sm btn-outline-primary save-physical-count-btn" data-store="' . $storeId . '" data-product="' . $s->product_id . '" data-stock-id="' . $s->id . '" data-system="' . $s->current_quantity . '">Save</button>
                    </div>';

                    $verificationRows[] = [
                        $prodName,
                        $s->product && $s->product->category ? $s->product->category->category_name : 'N/A',
                        '<span class="font-weight-bold" id="sys_qty_' . $s->id . '">' . $s->current_quantity . '</span>',
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
                    $deliveryBadge = match ($po->status) {
                        'received' => '<span class="badge bg-success text-white">Received</span>',
                        'partial' => '<span class="badge bg-warning text-dark">Partially Received</span>',
                        'cancelled' => '<span class="badge bg-danger text-white">Cancelled</span>',
                        default => '<span class="badge bg-secondary text-white">' . ucfirst($po->status) . '</span>',
                    };
                    $paymentBadge = match ($po->payment_status) {
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
                        'options' => ['pending' => 'Pending', 'approved' => 'Approved', 'partial' => 'Partial', 'fulfilled' => 'Fulfilled', 'rejected' => 'Rejected'],
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
                    ['label' => 'Pending/Partial', 'value' => $reqs->whereIn('status', ['pending', 'partial'])->count(), 'class' => 'text-warning'],
                    ['label' => 'Rejected', 'value' => $reqs->where('status', 'rejected')->count(), 'class' => 'text-danger']
                ];

                $reqRows = [];
                foreach ($reqs as $r) {
                    $badge = match ($r->status) {
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
                $stores = \App\Models\Store::where(function ($q) {
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
                        $tabbedData['dept_stock_' . $store->id] = [
                            'label' => $store->store_name . ' (Stock)',
                            'headers' => ['Product', 'Classification', 'Category', 'Current Qty', 'Sys Buy Price'],
                            'rows' => $stockRows
                        ];
                    }
                    if (count($reqRows) > 0) {
                        $tabbedData['dept_req_' . $store->id] = [
                            'label' => $store->store_name . ' (Reqs)',
                            'headers' => ['Req Number', 'Supplying Store', 'Items Count', 'Status', 'Requested By', 'Date'],
                            'rows' => $reqRows
                        ];
                    }
                    if (count($damageRows) > 0) {
                        $tabbedData['dept_damages_' . $store->id] = [
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
                        $tabbedData['dept_returns_' . $store->id] = [
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
                        $tabbedData['ward_stock_' . $store->id] = [
                            'label' => $store->store_name . ' (Stock)',
                            'headers' => ['Product', 'Classification', 'Category', 'Current Qty', 'Sys Buy Price'],
                            'rows' => $stockRows
                        ];
                    }
                    if (count($reqRows) > 0) {
                        $tabbedData['ward_req_' . $store->id] = [
                            'label' => $store->store_name . ' (Reqs)',
                            'headers' => ['Req Number', 'Supplying Store', 'Items Count', 'Status', 'Requested By', 'Date'],
                            'rows' => $reqRows
                        ];
                    }
                    if (count($damageRows) > 0) {
                        $tabbedData['ward_damages_' . $store->id] = [
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
                        $tabbedData['ward_returns_' . $store->id] = [
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
            'responsibility_key',
            'categoryLabel',
            'reportLabel',
            'startDate',
            'endDate',
            'stamp',
            'kpis',
            'headers',
            'rows',
            'chart',
            'tabbedData',
            'filters'
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

    private function formatPatientNameLink($patientId, $surname, $firstname, $othername, $fileNo)
    {
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

    private function formatPatientModelLink($patient)
    {
        if (!$patient) return 'Walk-in';
        $user = $patient->user ?? null;
        $surname = $user ? $user->surname : ($patient->surname ?? '');
        $firstname = $user ? $user->firstname : ($patient->firstname ?? '');
        $othername = $user ? $user->othername : ($patient->othername ?? '');
        $fileNo = $patient->file_no ?? '';
        $patientId = $patient->id ?? null;

        return $this->formatPatientNameLink($patientId, $surname, $firstname, $othername, $fileNo);
    }

    private function formatPatientUserLink($user, $patient = null)
    {
        if (!$user && !$patient) return 'Walk-in';
        $surname = $user ? $user->surname : ($patient ? $patient->surname : '');
        $firstname = $user ? $user->firstname : ($patient ? $patient->firstname : '');
        $othername = $user ? $user->othername : ($patient ? $patient->othername : '');
        $fileNo = $patient ? $patient->file_no : '';
        $patientId = $patient ? $patient->id : null;

        return $this->formatPatientNameLink($patientId, $surname, $firstname, $othername, $fileNo);
    }

    protected function applyAuditStatusFilter($query, $status, $table = null)
    {
        if (!$status || $status === "all") return $query;

        if ($status === "not_audited") {
            $query->whereDoesntHave('latestAudit');
        } elseif ($status === "audited") {
            $query->whereHas('latestAudit');
        } elseif ($status === "queried") {
            $query->whereHas('activeQuery');
        } elseif ($status === "resolved_audited") {
            $query->whereHas('latestAudit')->whereDoesntHave('activeQuery');
        }

        return $query;
    }

    private function formatStaffNameThree($user)
    {
        if (!$user) return 'System';
        $names = [];
        if (!empty($user->surname)) $names[] = trim($user->surname);
        if (!empty($user->firstname)) $names[] = trim($user->firstname);
        if (!empty($user->othername)) $names[] = trim($user->othername);
        return count($names) > 0 ? implode(' ', $names) : 'System';
    }

    private function formatStaffRawName($surname, $firstname, $othername = null)
    {
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
            ->whereHas('stockBatch', function ($q) use ($storeIds) {
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
            ->where(function ($q) {
                $q->whereNull('hmo_id')->orWhere('hmo_id', 1)->orWhere('coverage_mode', 'cash');
            })
            ->get();

        $labExpected = 0;
        $pharmExpected = 0;
        $imagingExpected = 0;
        $regExpected = 0;
        $otherExpected = 0;

        foreach ($posrs as $p) {
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

            $income = \App\Models\Payment::whereIn('patient_id', function ($query) use ($ward) {
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
            $procedures = \App\Models\Procedure::whereHas('patient.hmo', function ($q) use ($scheme) {
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

        $privateProcedures = \App\Models\Procedure::whereHas('patient', function ($q) {
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

        return response()->json(['success' => true, 'message' => 'Physical count saved and variance recorded.']);
    }

    // ==========================================
    // NEW AUDIT ZONE METHODS
    // ==========================================





    protected function getSharedWorkbenchData()
    {
        return [
            'hmos' => \App\Models\Hmo::orderBy('name')->get(),
            'hmoSchemes' => \App\Models\HmoScheme::with('hmos')->orderBy('name')->get(),
            'unassignedHmos' => \App\Models\Hmo::whereNull('hmo_scheme_id')->orderBy('name')->get(),
            'wards' => \App\Models\Ward::orderBy('name')->get(),
            'clinics' => \App\Models\Clinic::orderBy('name')->get(),
            'stores' => \App\Models\Store::orderBy('store_name')->get(),
        ];
    }

    public function receivablesDebtorsAudit(Request $request)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();
        $zoneKey = 'receivables-debtors';

        // 1. Staff Bills
        $staffBillsQuery = \App\Models\StaffBill::with(['staffUser.staff', 'patient.user'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('outstanding_amount', '>', 0);

        $totalStaffDebt = (clone $staffBillsQuery)->sum('outstanding_amount');
        $activeStaffDeductionsCount = (clone $staffBillsQuery)->count();
        $clearedStaffDebt = \App\Models\StaffBill::whereBetween('settled_at', [$startDate, $endDate])
            ->where('status', 'paid')->sum('total_amount');

        $staffBills = $staffBillsQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'staff_page');

        // 2. Patient Debtors
        $patientAccountsQuery = \App\Models\PatientAccount::with(['patient.user'])
            ->where('balance', '<', 0);
        $totalPatientDebt = (clone $patientAccountsQuery)->sum('balance');
        $deficitAccountsCount = (clone $patientAccountsQuery)->count();
        $avgPatientDebt = $deficitAccountsCount > 0 ? abs($totalPatientDebt) / $deficitAccountsCount : 0;

        $totalPatientDeposits = \App\Models\PatientAccount::where('balance', '>', 0)->sum('balance');
        $patientAccounts = $patientAccountsQuery->orderBy('balance', 'asc')->paginate(50, ['*'], 'patient_page');

        // 3. Corporate / Retainership
        $corporateBillsQuery = \App\Models\OrganizationBill::with(['organization', 'patient.user'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('outstanding_amount', '>', 0);

        $totalCorporateDebt = (clone $corporateBillsQuery)->sum('outstanding_amount');
        $unpaidCorporateInvoices = (clone $corporateBillsQuery)->count();

        $corpAging3060 = \App\Models\OrganizationBill::where('outstanding_amount', '>', 0)
            ->whereBetween('created_at', [now()->subDays(60), now()->subDays(31)])->sum('outstanding_amount');
        $corpAging60Plus = \App\Models\OrganizationBill::where('outstanding_amount', '>', 0)
            ->where('created_at', '<', now()->subDays(60))->sum('outstanding_amount');

        $corporateBills = $corporateBillsQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'corp_page');

        // 4. Unremitted HMO Claims
        $unremittedClaimsQuery = \App\Models\ProductOrServiceRequest::with(['patient.user', 'hmo'])
            ->whereNotNull('coverage_mode')
            ->where('validation_status', 'approved')
            ->whereNull('hmo_remittance_id')
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalUnremittedHmo = (clone $unremittedClaimsQuery)->sum('claims_amount');
        $unremittedHmoCount = (clone $unremittedClaimsQuery)->count();

        $hmoAging3060 = \App\Models\ProductOrServiceRequest::whereNotNull('coverage_mode')
            ->where('validation_status', 'approved')
            ->whereNull('hmo_remittance_id')
            ->whereBetween('created_at', [now()->subDays(60), now()->subDays(31)])->sum('claims_amount');

        $hmoAging90Plus = \App\Models\ProductOrServiceRequest::whereNotNull('coverage_mode')
            ->where('validation_status', 'approved')
            ->whereNull('hmo_remittance_id')
            ->where('created_at', '<', now()->subDays(90))->sum('claims_amount');

        $unremittedClaims = $unremittedClaimsQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'hmo_page');

        $kpis = [
            'total_staff_debt' => $totalStaffDebt,
            'active_staff_deductions_count' => $activeStaffDeductionsCount,
            'cleared_staff_debt' => $clearedStaffDebt,
            'total_patient_debt' => abs($totalPatientDebt),
            'total_patient_deposits' => $totalPatientDeposits,
            'deficit_accounts_count' => $deficitAccountsCount,
            'avg_patient_debt' => $avgPatientDebt,
            'total_corporate_debt' => $totalCorporateDebt,
            'unpaid_corporate_invoices' => $unpaidCorporateInvoices,
            'corp_aging_30_60' => $corpAging3060,
            'corp_aging_60_plus' => $corpAging60Plus,
            'total_unremitted_hmo' => $totalUnremittedHmo,
            'unremitted_hmo_count' => $unremittedHmoCount,
            'hmo_aging_30_60' => $hmoAging3060,
            'hmo_aging_90_plus' => $hmoAging90Plus,
        ];

        return view('admin.audit_workbench.zones.receivables_debtors', array_merge(compact(
            'startDate',
            'endDate',
            'zoneKey',
            'kpis',
            'staffBills',
            'patientAccounts',
            'corporateBills',
            'unremittedClaims'
        ), $this->getSharedWorkbenchData()));
    }

    public function cashbookAccountingAudit(Request $request)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();
        $zoneKey = 'cashbook-accounting';

        // 1. Cash Book & Receipts (Payments)
        $paymentsQuery = \App\Models\Payment::with(['staff_user.staff', 'patient.user'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalCashReceipts = (clone $paymentsQuery)->where('payment_method', 'CASH')->sum('total');
        $totalPosReceipts = (clone $paymentsQuery)->whereIn('payment_method', ['POS', 'TRANSFER', 'BANK_TRANSFER'])->sum('total');
        $totalAccountDeposits = (clone $paymentsQuery)->where('payment_type', 'WALLET_DEPOSIT')->sum('total');
        $totalWithdrawals = (clone $paymentsQuery)->where('payment_type', 'REFUND')->sum('total');

        $payments = $paymentsQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'payments_page');

        // 2. General Ledger
        $journalLinesQuery = \App\Models\Accounting\JournalEntryLine::with(['journalEntry', 'account'])
            ->whereHas('journalEntry', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('entry_date', [$startDate, $endDate]);
            });

        $totalDebits = (clone $journalLinesQuery)->sum('debit');
        $totalCredits = (clone $journalLinesQuery)->sum('credit');

        // Approx revenue (class 4) and expenses (class 5)
        $monthlyRevenue = (clone $journalLinesQuery)->whereHas('account', function ($q) {
            $q->where('code', 'like', '4%');
        })->sum('credit');
        $monthlyExpenses = (clone $journalLinesQuery)->whereHas('account', function ($q) {
            $q->where('code', 'like', '5%')->orWhere('code', 'like', '6%');
        })->sum('debit');

        $journalLines = $journalLinesQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'ledger_page');

        // 3. Bank Reconciliation
        $reconciliationsQuery = \App\Models\Accounting\BankReconciliation::with(['bank', 'account'])
            ->whereBetween('statement_date', [$startDate, $endDate]);

        $reconciliations = $reconciliationsQuery->orderBy('statement_date', 'desc')->paginate(50, ['*'], 'recon_page');

        $kpis = [
            'total_cash_receipts' => $totalCashReceipts,
            'total_pos_receipts' => $totalPosReceipts,
            'total_account_deposits' => $totalAccountDeposits,
            'total_withdrawals' => $totalWithdrawals,
            'total_debits' => $totalDebits,
            'total_credits' => $totalCredits,
            'monthly_revenue' => $monthlyRevenue,
            'monthly_expenses' => $monthlyExpenses,
        ];

        $hmos = \App\Models\Hmo::orderBy('name')->get();
        $wards = \App\Models\Ward::orderBy('name')->get();
        $clinics = \App\Models\Clinic::orderBy('name')->get();
        $stores = \App\Models\Store::orderBy('store_name')->get();

        return view('admin.audit_workbench.zones.cashbook_accounting', array_merge(compact(
            'startDate',
            'endDate',
            'zoneKey',
            'kpis',
            'payments',
            'journalLines',
            'reconciliations'
        ), $this->getSharedWorkbenchData()));
    }

    public function consultationsClinicsAudit(Request $request)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();
        $zoneKey = 'consultations-clinics';

        // 1. Appointments & Queue Conversion
        $appointmentsQuery = \App\Models\DoctorAppointment::with(['patient.user', 'clinic', 'doctor.user', 'doctorQueue'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalAppointments = (clone $appointmentsQuery)->count();
        $completedAppointments = (clone $appointmentsQuery)->where('status', 'completed')->count();
        $pendingAppointments = (clone $appointmentsQuery)->whereIn('status', ['pending', 'waiting'])->count();
        $cancelledAppointments = (clone $appointmentsQuery)->whereIn('status', ['cancelled', 'no_show'])->count();

        $appointments = $appointmentsQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'appts_page');

        // 2. Clinical Encounters with Durations & Outcomes
        $encountersQuery = \App\Models\Encounter::with(['patient.user', 'doctor', 'queue'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('*', \Illuminate\Support\Facades\DB::raw('TIMESTAMPDIFF(MINUTE, started_at, completed_at) as duration_minutes'));

        $totalEncounters = (clone $encountersQuery)->count();
        $days = max($startDate->diffInDays($endDate), 1);
        $avgEncountersPerDay = $totalEncounters / $days;

        $activeDoctors = (clone $encountersQuery)->distinct('doctor_id')->count('doctor_id');

        $encounters = $encountersQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'encs_page');

        $kpis = [
            'total_appointments' => $totalAppointments,
            'completed_appointments' => $completedAppointments,
            'pending_appointments' => $pendingAppointments,
            'cancelled_appointments' => $cancelledAppointments,
            'total_encounters' => $totalEncounters,
            'avg_encounters_per_day' => round($avgEncountersPerDay, 1),
            'active_doctors' => $activeDoctors,
        ];

        $hmos = \App\Models\Hmo::orderBy('name')->get();
        $wards = \App\Models\Ward::orderBy('name')->get();
        $clinics = \App\Models\Clinic::orderBy('name')->get();
        $stores = \App\Models\Store::orderBy('store_name')->get();

        return view('admin.audit_workbench.zones.consultations_clinics', array_merge(compact(
            'startDate',
            'endDate',
            'zoneKey',
            'kpis',
            'appointments',
            'encounters'
        ), $this->getSharedWorkbenchData()));
    }

    public function admissionsDischargesAudit(Request $request)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();
        $zoneKey = 'admissions-discharges';

        // 1. Inpatient Admissions
        $admissionsQuery = \App\Models\AdmissionRequest::with(['patient.user', 'ward', 'bed.ward', 'doctor'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalAdmissions = (clone $admissionsQuery)->count();
        $currentlyAdmitted = \App\Models\AdmissionRequest::where('status', 'admitted')->count();

        $totalBeds = max(\App\Models\Bed::count(), 1);
        $occupiedBeds = \App\Models\Bed::where('status', 'occupied')->count();
        $bedOccupancyRate = ($occupiedBeds / $totalBeds) * 100;

        $dischargesWithStay = \App\Models\AdmissionRequest::whereNotNull('discharge_date')
            ->whereBetween('discharge_date', [$startDate, $endDate])->get();

        $totalStayDays = 0;
        foreach ($dischargesWithStay as $d) {
            $totalStayDays += $d->created_at->diffInDays(\Carbon\Carbon::parse($d->discharge_date));
        }
        $avgLengthOfStay = $dischargesWithStay->count() > 0 ? round($totalStayDays / $dischargesWithStay->count(), 1) : 0;

        $admissions = $admissionsQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'adms_page');

        // 2. Discharges
        $dischargesQuery = \App\Models\AdmissionRequest::with(['patient.user', 'ward', 'bed.ward'])
            ->whereBetween('discharge_date', [$startDate, $endDate])
            ->whereIn('status', ['discharged', 'cleared', 'absconded', 'dama']);

        $totalDischarges = (clone $dischargesQuery)->count();
        $pendingClearance = (clone $dischargesQuery)->where('status', 'discharged')->count();
        $abscondedDama = (clone $dischargesQuery)->whereIn('status', ['absconded', 'dama'])->count();

        $discharges = $dischargesQuery->orderBy('discharge_date', 'desc')->paginate(50, ['*'], 'disc_page');

        // 3. Ward Triangulation (Admissions + Ward Requisitions + Patient Accumulated Bills)
        $wards = \App\Models\Ward::all();
        $wardTriangulation = [];
        foreach ($wards as $w) {
            $associatedStore = \App\Models\Store::where(function ($q) use ($w) {
                $q->where('ward_id', $w->id)
                    ->orWhere('store_name', 'LIKE', "%{$w->name}%")
                    ->orWhere('store_name', 'LIKE', '%ward%');
            })->first();

            $admCount = \App\Models\AdmissionRequest::where(function ($q) use ($w) {
                $q->where('preferred_ward_id', $w->id)
                    ->orWhereHas('bed', fn($bq) => $bq->where('ward_id', $w->id));
            })->whereBetween('created_at', [$startDate, $endDate])->count();

            $reqFulfilledValue = 0;
            if ($associatedStore) {
                $reqItems = \App\Models\StoreRequisitionItem::whereHas('requisition', function ($q) use ($associatedStore) {
                    $q->where('from_store_id', $associatedStore->id)->where('status', 'fulfilled');
                })->whereBetween('created_at', [$startDate, $endDate])->with(['product.price', 'sourceBatch'])->get();

                foreach ($reqItems as $item) {
                    $unitCost = $item->sourceBatch->unit_cost ?? $item->sourceBatch->cost_price ?? $item->product->cost_price ?? ($item->product->price->pr_buy_price ?? 0);
                    $reqFulfilledValue += ($item->fulfilled_qty ?? $item->requested_qty) * $unitCost;
                }
            }

            $patientBillsValue = \App\Models\ProductOrServiceRequest::whereHas('admissionRequest', function ($q) use ($w) {
                $q->where('preferred_ward_id', $w->id)
                    ->orWhereHas('bed', fn($bq) => $bq->where('ward_id', $w->id));
            })->whereBetween('created_at', [$startDate, $endDate])
                ->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(payable_amount, amount)'));

            $wardTriangulation[] = (object) [
                'ward' => $w,
                'store' => $associatedStore,
                'admissions_count' => $admCount,
                'req_fulfilled_value' => $reqFulfilledValue,
                'patient_bills_value' => $patientBillsValue,
                'variance' => $patientBillsValue - $reqFulfilledValue,
            ];
        }

        $kpis = [
            'total_admissions' => $totalAdmissions,
            'currently_admitted' => $currentlyAdmitted,
            'bed_occupancy_rate' => round($bedOccupancyRate, 1),
            'avg_length_of_stay' => $avgLengthOfStay,
            'total_discharges' => $totalDischarges,
            'pending_clearance' => $pendingClearance,
            'absconded_dama' => $abscondedDama,
        ];

        $hmos = \App\Models\Hmo::orderBy('name')->get();
        $clinics = \App\Models\Clinic::orderBy('name')->get();
        $stores = \App\Models\Store::orderBy('store_name')->get();

        return view('admin.audit_workbench.zones.admissions_discharges', array_merge(compact(
            'startDate',
            'endDate',
            'zoneKey',
            'kpis',
            'admissions',
            'discharges',
            'wardTriangulation'
        ), $this->getSharedWorkbenchData()));
    }

    public function mainStoreStockAudit(Request $request)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();
        $zoneKey = 'main-store-stock';

        // 1. Inventory Levels (Central Store focus)
        $storeStocksQuery = \App\Models\StoreStock::with(['product.category', 'product.price'])
            ->whereHas('store', function ($q) {
                $q->where('distribution_role', \App\Models\Store::ROLE_CENTRAL)
                    ->orWhere('store_type', 'warehouse');
            })
            ->where('current_quantity', '>', 0);

        $storeStocks = $storeStocksQuery->get()->map(function ($stock) {
            $cost = $stock->product->cost_price ?? ($stock->product->price->pr_buy_price ?? 0);
            $stock->calc_value = $stock->current_quantity * $cost;
            return $stock;
        });

        $totalStockValue = $storeStocks->sum('calc_value');
        $lowStockItems = $storeStocks->filter(function ($stock) {
            return $stock->current_quantity <= ($stock->reorder_level ?? 10);
        })->count();
        $totalUniqueItems = $storeStocks->count();

        $storeStocksPaginated = \App\Models\StoreStock::with(['product.category', 'product.price'])
            ->whereHas('store', function ($q) {
                $q->where('distribution_role', \App\Models\Store::ROLE_CENTRAL)
                    ->orWhere('store_type', 'warehouse');
            })
            ->orderBy('current_quantity', 'asc')
            ->paginate(50, ['*'], 'inv_page');

        // 2. Procurement & Variances
        $poItemsQuery = \App\Models\PurchaseOrderItem::with(['purchaseOrder', 'product.price'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        $poItems = $poItemsQuery->get();

        $totalPoReceivedValue = $poItems->where('status', 'received')->sum(function ($item) {
            return $item->received_qty * $item->actual_unit_cost;
        });

        $totalPriceVariance = $poItems->sum(function ($item) {
            return ($item->actual_unit_cost - $item->unit_cost) * $item->received_qty;
        });

        $posCompleted = \App\Models\PurchaseOrder::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'completed')->count();

        $poItemsPaginated = $poItemsQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'po_page');

        // 3. Damages & Expiries
        $damagesQuery = \App\Models\StoreDamage::with(['product.price', 'store'])
            ->whereHas('store', function ($q) {
                $q->where('distribution_role', \App\Models\Store::ROLE_CENTRAL)
                    ->orWhere('store_type', 'warehouse');
            })
            ->whereBetween('discovered_date', [$startDate, $endDate]);

        $totalDamagesValue = (clone $damagesQuery)->sum('total_value');
        $totalDamagedItems = (clone $damagesQuery)->sum('qty_damaged');

        $storeDamagesPaginated = $damagesQuery->orderBy('discovered_date', 'desc')->paginate(50, ['*'], 'dmg_page');

        $kpis = [
            'total_stock_value' => $totalStockValue,
            'low_stock_items' => $lowStockItems,
            'total_unique_items' => $totalUniqueItems,
            'total_po_received_value' => $totalPoReceivedValue,
            'total_price_variance' => $totalPriceVariance,
            'pos_completed' => $posCompleted,
            'total_damages_value' => $totalDamagesValue,
            'total_damaged_items' => $totalDamagedItems,
        ];

        $hmos = \App\Models\Hmo::orderBy('name')->get();
        $wards = \App\Models\Ward::orderBy('name')->get();
        $clinics = \App\Models\Clinic::orderBy('name')->get();
        $stores = \App\Models\Store::orderBy('store_name')->get();

        return view('admin.audit_workbench.zones.main_store_stock', array_merge([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'zoneKey' => $zoneKey,
            'kpis' => $kpis,
            'storeStocks' => $storeStocksPaginated,
            'poItems' => $poItemsPaginated,
            'storeDamages' => $storeDamagesPaginated
        ], $this->getSharedWorkbenchData()));
    }

    public function wardDeptStoresAudit(Request $request)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();
        $zoneKey = 'ward-dept-stores';

        // 1. Departmental/Ward Stock
        $storeStocksQuery = \App\Models\StoreStock::with(['product.category', 'product.price', 'store'])
            ->whereHas('store', function ($q) {
                $q->where('distribution_role', '!=', \App\Models\Store::ROLE_CENTRAL)
                    ->where('store_type', '!=', 'warehouse');
            })
            ->where('current_quantity', '>', 0);

        $storeStocks = $storeStocksQuery->get()->map(function ($stock) {
            $cost = $stock->product->cost_price ?? ($stock->product->price->pr_buy_price ?? 0);
            $stock->calc_value = $stock->current_quantity * $cost;
            return $stock;
        });

        $totalSubstoreStockValue = $storeStocks->sum('calc_value');
        $substoreLowStock = $storeStocks->filter(function ($stock) {
            return $stock->current_quantity <= ($stock->reorder_level ?? 10);
        })->count();
        $totalDecentralizedStores = \App\Models\Store::where('distribution_role', '!=', \App\Models\Store::ROLE_CENTRAL)->count();

        $storeStocksPaginated = \App\Models\StoreStock::with(['product.category', 'product.price', 'store'])
            ->whereHas('store', function ($q) {
                $q->where('distribution_role', '!=', \App\Models\Store::ROLE_CENTRAL)
                    ->where('store_type', '!=', 'warehouse');
            })
            ->orderBy('current_quantity', 'asc')
            ->paginate(50, ['*'], 'inv_page');

        // 2. Internal Requisitions
        $requisitionsQuery = \App\Models\StoreRequisition::with(['fromStore', 'toStore', 'items.product.price', 'items.sourceBatch'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalRequisitions = (clone $requisitionsQuery)->count();
        $fulfilledRequisitions = (clone $requisitionsQuery)->where('status', 'fulfilled')->count();
        $pendingRequisitions = (clone $requisitionsQuery)->where('status', 'pending')->count();
        $rejectedRequisitions = (clone $requisitionsQuery)->where('status', 'rejected')->count();

        $requisitions = $requisitionsQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'req_page');

        $kpis = [
            'total_substore_stock_value' => $totalSubstoreStockValue,
            'substore_low_stock' => $substoreLowStock,
            'total_decentralized_stores' => $totalDecentralizedStores,
            'total_requisitions' => $totalRequisitions,
            'fulfilled_requisitions' => $fulfilledRequisitions,
            'pending_requisitions' => $pendingRequisitions,
            'rejected_requisitions' => $rejectedRequisitions,
        ];

        $hmos = \App\Models\Hmo::orderBy('name')->get();
        $wards = \App\Models\Ward::orderBy('name')->get();
        $clinics = \App\Models\Clinic::orderBy('name')->get();
        $stores = \App\Models\Store::orderBy('store_name')->get();

        return view('admin.audit_workbench.zones.ward_dept_stores', array_merge(compact(
            'startDate',
            'endDate',
            'zoneKey',
            'kpis',
            'storeStocks',
            'requisitions'
        ), $this->getSharedWorkbenchData()));
    }

    public function storeUtilizationRevenueAudit(Request $request)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();
        $zoneKey = 'store-utilization-income';

        // 1. Stock Movement Transactions
        $transactionsQuery = \App\Models\StockBatchTransaction::whereBetween('created_at', [$startDate, $endDate]);

        $totalTransactions = (clone $transactionsQuery)->count();
        $totalQtyUsed = abs((clone $transactionsQuery)->where('type', 'sale')->sum('qty'));
        $totalQtyLost = abs((clone $transactionsQuery)->whereIn('type', ['damage', 'expiry', 'adjustment_down'])->sum('qty'));

        $transactions = $transactionsQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'txn_page');

        // 2. Departmental Requisitions vs Revenue Reconciliation with Extensive Synonyms
        $getUnitCost = function ($item) {
            return $item->sourceBatch->unit_cost ?? $item->sourceBatch->cost_price ?? $item->product->cost_price ?? ($item->product->price->pr_buy_price ?? 0);
        };

        // A. Lab Reconciliation
        $labStoreIds = \App\Models\Store::where(function ($q) {
            $q->where('store_name', 'LIKE', '%lab%')
                ->orWhere('store_name', 'LIKE', '%laboratory%')
                ->orWhere('store_name', 'LIKE', '%investigation%')
                ->orWhere('store_name', 'LIKE', '%blood bank%')
                ->orWhere('store_name', 'LIKE', '%reagent%')
                ->orWhere('distribution_role', 'lab')
                ->orWhere('department_id', 9);
        })->pluck('id');

        $labReqItems = \App\Models\StoreRequisitionItem::whereHas('requisition', fn($q) => $q->whereIn('from_store_id', $labStoreIds)->where('status', 'fulfilled'))
            ->whereBetween('created_at', [$startDate, $endDate])->with(['product.price', 'sourceBatch'])->get();
        $labReqCost = 0;
        foreach ($labReqItems as $i) {
            $labReqCost += ($i->fulfilled_qty ?? $i->requested_qty) * $getUnitCost($i);
        }

        $labRevenue = \App\Models\ProductOrServiceRequest::where(function ($q) {
            $q->where('type', 'lab')->orWhereHas('service.category', fn($sc) => $sc->where('category_name', 'LIKE', '%lab%')->orWhere('category_name', 'LIKE', '%investigation%'));
        })->whereBetween('created_at', [$startDate, $endDate])->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(payable_amount, amount)'));

        // B. Theatre / Surgery Reconciliation
        $theatreStoreIds = \App\Models\Store::where(function ($q) {
            $q->where('store_name', 'LIKE', '%theatre%')
                ->orWhere('store_name', 'LIKE', '%theater%')
                ->orWhere('store_name', 'LIKE', '%surgery%')
                ->orWhere('store_name', 'LIKE', '%surgical%')
                ->orWhere('store_name', 'LIKE', '%procedure%')
                ->orWhere('store_name', 'LIKE', '%operation%')
                ->orWhere('department_id', 2);
        })->pluck('id');

        $theatreReqItems = \App\Models\StoreRequisitionItem::whereHas('requisition', fn($q) => $q->whereIn('from_store_id', $theatreStoreIds)->where('status', 'fulfilled'))
            ->whereBetween('created_at', [$startDate, $endDate])->with(['product.price', 'sourceBatch'])->get();
        $theatreReqCost = 0;
        foreach ($theatreReqItems as $i) {
            $theatreReqCost += ($i->fulfilled_qty ?? $i->requested_qty) * $getUnitCost($i);
        }

        $theatreRevenue = \App\Models\ProductOrServiceRequest::where(function ($q) {
            $q->where('type', 'procedure')->orWhereHas('service.category', fn($sc) => $sc->where('category_name', 'LIKE', '%procedure%')->orWhere('category_name', 'LIKE', '%surgery%'));
        })->whereBetween('created_at', [$startDate, $endDate])->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(payable_amount, amount)'));

        // C. Radiology / Imaging Reconciliation
        $imagingStoreIds = \App\Models\Store::where(function ($q) {
            $q->where('store_name', 'LIKE', '%radiology%')
                ->orWhere('store_name', 'LIKE', '%imaging%')
                ->orWhere('store_name', 'LIKE', '%scan%')
                ->orWhere('store_name', 'LIKE', '%x-ray%')
                ->orWhere('store_name', 'LIKE', '%xray%')
                ->orWhere('store_name', 'LIKE', '%ultrasound%')
                ->orWhere('distribution_role', 'imaging')
                ->orWhere('department_id', 10);
        })->pluck('id');

        $imagingReqItems = \App\Models\StoreRequisitionItem::whereHas('requisition', fn($q) => $q->whereIn('from_store_id', $imagingStoreIds)->where('status', 'fulfilled'))
            ->whereBetween('created_at', [$startDate, $endDate])->with(['product.price', 'sourceBatch'])->get();
        $imagingReqCost = 0;
        foreach ($imagingReqItems as $i) {
            $imagingReqCost += ($i->fulfilled_qty ?? $i->requested_qty) * $getUnitCost($i);
        }

        $imagingRevenue = \App\Models\ProductOrServiceRequest::where(function ($q) {
            $q->where('type', 'imaging')->orWhereHas('service.category', fn($sc) => $sc->where('category_name', 'LIKE', '%imaging%')->orWhere('category_name', 'LIKE', '%radiology%')->orWhere('category_name', 'LIKE', '%scan%'));
        })->whereBetween('created_at', [$startDate, $endDate])->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(payable_amount, amount)'));

        $billedItemsQuery = \App\Models\ProductOrServiceRequest::with(['product', 'dispensedFromStore'])
            ->whereNotNull('product_id')
            ->whereBetween('created_at', [$startDate, $endDate]);

        $billedItems = $billedItemsQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'rev_page');

        $deptReconciliations = [
            'lab' => (object)['name' => 'Laboratory & Blood Bank', 'cost' => $labReqCost, 'revenue' => $labRevenue, 'margin' => $labRevenue - $labReqCost],
            'theatre' => (object)['name' => 'Theatre & Operating Surgery', 'cost' => $theatreReqCost, 'revenue' => $theatreRevenue, 'margin' => $theatreRevenue - $theatreReqCost],
            'imaging' => (object)['name' => 'Radiology & Imaging Scans', 'cost' => $imagingReqCost, 'revenue' => $imagingRevenue, 'margin' => $imagingRevenue - $imagingReqCost],
        ];

        $kpis = [
            'total_transactions' => $totalTransactions,
            'total_qty_used' => $totalQtyUsed,
            'total_qty_lost' => $totalQtyLost,
            'total_billed_revenue' => $labRevenue + $theatreRevenue + $imagingRevenue,
            'cash_revenue' => $labRevenue,
            'hmo_revenue' => $theatreRevenue,
        ];

        $hmos = \App\Models\Hmo::orderBy('name')->get();
        $wards = \App\Models\Ward::orderBy('name')->get();
        $clinics = \App\Models\Clinic::orderBy('name')->get();
        $stores = \App\Models\Store::orderBy('store_name')->get();

        return view('admin.audit_workbench.zones.store_utilization_income', array_merge(compact(
            'startDate',
            'endDate',
            'zoneKey',
            'kpis',
            'transactions',
            'billedItems',
            'deptReconciliations'
        ), $this->getSharedWorkbenchData()));
    }

    public function hmoNhisAudit(Request $request)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();
        $zoneKey = 'hmo-nhis';

        // 1. Service Validations
        $servicesQuery = \App\Models\ProductOrServiceRequest::with(['patient.user', 'hmo', 'product', 'service'])
            ->where('coverage_mode', 'hmo')
            ->whereBetween('created_at', [$startDate, $endDate]);

        $pendingValidations = (clone $servicesQuery)->where(function ($q) {
            $q->whereNull('validation_status')->orWhere('validation_status', 'pending');
        })->count();
        $validatedServices = (clone $servicesQuery)->where('validation_status', 'validated')->count();
        $rejectedServices = (clone $servicesQuery)->where('validation_status', 'rejected')->count();

        $services = $servicesQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'svc_page');

        // 2. Claims
        $claimsQuery = \App\Models\HmoClaim::with(['hmo', 'patient.user'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalClaimsAmount = (clone $claimsQuery)->sum('claims_amount');
        $pendingClaimsCount = (clone $claimsQuery)->where('status', '!=', 'processed')->count();
        $processedClaimsCount = (clone $claimsQuery)->where('status', 'processed')->count();

        $claims = $claimsQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'claims_page');

        // 3. Remittances
        $remittancesQuery = \App\Models\HmoRemittance::with('hmo')
            ->whereBetween('payment_date', [$startDate, $endDate]);

        $totalRemittancesAmount = (clone $remittancesQuery)->sum('amount');
        $remittanceCount = (clone $remittancesQuery)->count();

        $remittances = $remittancesQuery->orderBy('payment_date', 'desc')->paginate(50, ['*'], 'rem_page');

        $kpis = [
            'pending_validations' => $pendingValidations,
            'validated_services' => $validatedServices,
            'rejected_services' => $rejectedServices,
            'total_claims_amount' => $totalClaimsAmount,
            'pending_claims_count' => $pendingClaimsCount,
            'processed_claims_count' => $processedClaimsCount,
            'total_remittances_amount' => $totalRemittancesAmount,
            'remittance_count' => $remittanceCount,
        ];

        $hmos = \App\Models\Hmo::orderBy('name')->get();
        $wards = \App\Models\Ward::orderBy('name')->get();
        $clinics = \App\Models\Clinic::orderBy('name')->get();
        $stores = \App\Models\Store::orderBy('store_name')->get();

        return view('admin.audit_workbench.zones.hmo_nhis', array_merge(compact(
            'startDate',
            'endDate',
            'zoneKey',
            'kpis',
            'services',
            'claims',
            'remittances'
        ), $this->getSharedWorkbenchData()));
    }

    public function serviceRegistersBillingAudit(Request $request)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();
        $zoneKey = 'service-registers-billing';

        // 1. Clinical Services Performed
        $encountersQuery = \App\Models\Encounter::with(['patient.user', 'doctor'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalEncounters = (clone $encountersQuery)->count();
        $totalAdmissions = \App\Models\AdmissionRequest::whereBetween('created_at', [$startDate, $endDate])->count();
        $totalProcedures = \App\Models\ProductOrServiceRequest::where('type', 'procedure')->whereBetween('created_at', [$startDate, $endDate])->count();

        $encounters = $encountersQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'enc_page');

        // 2. Services Billed
        $billedServicesQuery = \App\Models\ProductOrServiceRequest::with(['user', 'patient.user', 'service.category'])
            ->whereNotNull('service_id')
            ->whereBetween('created_at', [$startDate, $endDate]);

        $billedServicesCollection = (clone $billedServicesQuery)->get();

        $totalBilledServices = $billedServicesCollection->count();
        $totalServiceRevenue = $billedServicesCollection->sum(function ($item) {
            return $item->payable_amount > 0 ? $item->payable_amount : $item->amount;
        });

        $avgRevenuePerService = $totalBilledServices > 0 ? $totalServiceRevenue / $totalBilledServices : 0;

        $billedServices = $billedServicesQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'svc_page');

        $kpis = [
            'total_encounters' => $totalEncounters,
            'total_admissions' => $totalAdmissions,
            'total_procedures' => $totalProcedures,
            'total_billed_services' => $totalBilledServices,
            'total_service_revenue' => $totalServiceRevenue,
            'avg_revenue_per_service' => $avgRevenuePerService,
        ];

        $hmos = \App\Models\Hmo::orderBy('name')->get();
        $wards = \App\Models\Ward::orderBy('name')->get();
        $clinics = \App\Models\Clinic::orderBy('name')->get();
        $stores = \App\Models\Store::orderBy('store_name')->get();

        return view('admin.audit_workbench.zones.service_registers_billing', array_merge(compact(
            'startDate',
            'endDate',
            'zoneKey',
            'kpis',
            'encounters',
            'billedServices'
        ), $this->getSharedWorkbenchData()));
    }

    public function pharmacyMortuaryAudit(Request $request)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();
        $zoneKey = 'pharmacy-mortuary';

        // 1. Differentiate Pharmacy Dispense (Doctor Prescriptions) vs Direct Ward/Nurse Billing
        $pharmacyPrescriptionsQuery = \App\Models\ProductRequest::with(['product.price', 'dispensedFromStore', 'doctor', 'patient.user'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        $pharmacyDispenseCount = (clone $pharmacyPrescriptionsQuery)->count();

        $directWardBillingQuery = \App\Models\ProductOrServiceRequest::with(['product.price', 'dispensedFromStore', 'user.patient_profile'])
            ->whereNotNull('product_id')
            ->whereHas('dispensedFromStore', function ($q) {
                $q->whereIn('distribution_role', ['ward', 'department'])
                    ->orWhere('store_type', 'ward');
            })
            ->whereBetween('created_at', [$startDate, $endDate]);

        $directWardBillingCount = (clone $directWardBillingQuery)->count();
        $directWardBillingRevenue = (clone $directWardBillingQuery)->sum(\Illuminate\Support\Facades\DB::raw('COALESCE(payable_amount, amount)'));

        $pharmacyPrescriptions = $pharmacyPrescriptionsQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'rx_page');
        $directWardItems = $directWardBillingQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'ward_page');

        // 2. Mortuary Admissions
        $mortuaryAdmissionsQuery = \App\Models\MorgueAdmission::with(['patient.user'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        $mortuaryAdmissionsCount = (clone $mortuaryAdmissionsQuery)->count();
        $mortuaryCurrentlyAdmitted = (clone $mortuaryAdmissionsQuery)->where('status', 'admitted')->count();
        $mortuaryReleased = (clone $mortuaryAdmissionsQuery)->where('status', 'released')->count();

        $mortuaryAdmissions = $mortuaryAdmissionsQuery->orderBy('created_at', 'desc')->paginate(50, ['*'], 'morg_page');

        $kpis = [
            'pharmacy_dispense_count' => $pharmacyDispenseCount,
            'direct_ward_billing_count' => $directWardBillingCount,
            'direct_ward_billing_revenue' => $directWardBillingRevenue,
            'mortuary_admissions_count' => $mortuaryAdmissionsCount,
            'mortuary_currently_admitted' => $mortuaryCurrentlyAdmitted,
            'mortuary_released' => $mortuaryReleased,
        ];

        $hmos = \App\Models\Hmo::orderBy('name')->get();
        $wards = \App\Models\Ward::orderBy('name')->get();
        $clinics = \App\Models\Clinic::orderBy('name')->get();
        $stores = \App\Models\Store::orderBy('store_name')->get();

        return view('admin.audit_workbench.zones.pharmacy_mortuary', array_merge(compact(
            'startDate',
            'endDate',
            'zoneKey',
            'kpis',
            'pharmacyPrescriptions',
            'directWardItems',
            'mortuaryAdmissions'
        ), $this->getSharedWorkbenchData()));
    }

    public function customReport(Request $request)
    {
        // Dynamic reporting logic based on request filters
        $data = []; // Fetch based on $request filters
        return view('admin.audit_workbench.zones.custom_report', compact('data'));
    }

    public function overallReport(Request $request)
    {
        // Aggregate data for billing department
        $totalCollections = \App\Models\Payment::where('status', 'settled')->sum('total');
        $totalReceivables = \App\Models\StaffBill::sum('outstanding_amount') + \App\Models\OrganizationBill::sum('outstanding_amount');

        return view('admin.audit_workbench.zones.overall_report', compact('totalCollections', 'totalReceivables'));
    }

    public function queriesDashboard(Request $request)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        $viewData = array_merge(compact('startDate', 'endDate'), $this->getSharedWorkbenchData());
        $viewData['zoneKey'] = 'queries-dashboard';
        
        $viewData['kpis'] = [
            'total_active' => \App\Models\AuditMark::where('status', 'queried')->count(),
            'total_resolved' => \App\Models\AuditMark::where('status', 'resolved')->count(),
            'active_in_period' => \App\Models\AuditMark::where('status', 'queried')->whereBetween('created_at', [$startDate, $endDate])->count(),
            'resolved_in_period' => \App\Models\AuditMark::where('status', 'resolved')->whereBetween('created_at', [$startDate, $endDate])->count(),
        ];
        
        return view('admin.audit_workbench.zones.queries_dashboard', $viewData);
    }

    public function markAudited(Request $request)
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer'
        ]);

        $modelClass = '\\App\\Models\\' . $request->model_type;

        if (class_exists($modelClass)) {
            $record = $modelClass::find($request->model_id);
            if ($record) {
                // Check if the record has an active query
                if (\Illuminate\Support\Facades\Schema::hasColumn((new $modelClass)->getTable(), 'is_queried')) {
                    if ($record->is_queried && is_null($record->query_resolved_at)) {
                        return response()->json(['success' => false, 'message' => 'Cannot audit this record because it has an unresolved active query.'], 400);
                    }
                }

                if (\Illuminate\Support\Facades\Schema::hasColumn((new $modelClass)->getTable(), 'is_audited')) {
                    $record->is_audited = true;
                    $record->audited_by = auth()->id();
                    $record->audited_at = now();
                    $record->save();

                    return response()->json(['success' => true, 'message' => 'Marked as audited.']);
                }
            }
        }

        return response()->json(['success' => false, 'message' => 'Failed to mark as audited.'], 400);
    }

    public function raiseQuery(Request $request)
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
            'query_notes' => 'required|string'
        ]);

        $modelClass = '\\App\\Models\\' . $request->model_type;

        if (class_exists($modelClass)) {
            $record = $modelClass::find($request->model_id);
            if ($record && \Illuminate\Support\Facades\Schema::hasColumn((new $modelClass)->getTable(), 'is_queried')) {
                $record->is_queried = true;
                $record->queried_by = auth()->id();
                $record->queried_at = now();
                $record->query_notes = $request->query_notes;
                $record->save();

                return response()->json(['success' => true, 'message' => 'Audit query raised successfully.']);
            }
        }

        return response()->json(['success' => false, 'message' => 'Failed to raise query.'], 400);
    }

    public function resolveQuery(Request $request)
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
            'resolution_notes' => 'required|string'
        ]);

        $modelClass = '\\App\\Models\\' . $request->model_type;

        if (class_exists($modelClass)) {
            $record = $modelClass::find($request->model_id);
            if ($record && \Illuminate\Support\Facades\Schema::hasColumn((new $modelClass)->getTable(), 'is_queried')) {
                $record->query_resolved_by = auth()->id();
                $record->query_resolved_at = now();
                $record->query_resolution_notes = $request->resolution_notes;
                $record->save();

                return response()->json(['success' => true, 'message' => 'Audit query resolved successfully.']);
            }
        }

        return response()->json(['success' => false, 'message' => 'Failed to resolve query.'], 400);
    }





    // =========================================================================
    // MULTIDIMENSIONAL FILTER HELPER FOR ALL WORKBENCH QUERIES
    // =========================================================================

    protected function applyMultidimensionalFilters($query, Request $request)
    {
        // Eager load polymorphic audit relations to prevent N+1 queries during datatable rendering
        if (method_exists($query->getModel(), 'auditMarks')) {
            $query->with(['latestAudit.auditor', 'activeQuery.auditor', 'activeQuery.resolver']);
        }

        if ($request->filled('hmo_scheme_id')) {
            $schemeId = $request->hmo_scheme_id;
            $query->where(function($q) use ($schemeId) {
                $q->whereHas('hmo', fn($h) => $h->where('hmo_scheme_id', $schemeId))
                  ->orWhereHas('patient.hmo', fn($h) => $h->where('hmo_scheme_id', $schemeId));
            });
        }

        if ($request->filled('hmo_id')) {
            $hmoId = $request->hmo_id;
            $query->where(function($q) use ($hmoId) {
                $q->where('hmo_id', $hmoId)
                  ->orWhereHas('patient', fn($p) => $p->where('hmo_id', $hmoId))
                  ->orWhereHas('hmo', fn($h) => $h->where('id', $hmoId));
            });
        }

        if ($request->filled('gender')) {
            $gender = $request->gender;
            $query->where(function($q) use ($gender) {
                $q->whereHas('patient', fn($p) => $p->where('gender', 'LIKE', $gender))
                  ->orWhereHas('user', fn($u) => $u->whereHas('patient_profile', fn($p) => $p->where('gender', 'LIKE', $gender)));
            });
        }

        if ($request->filled('age_range')) {
            $range = $request->age_range;
            $query->whereHas('patient', function($p) use ($range) {
                if ($range === 'pediatric') {
                    $p->where('dob', '>=', now()->subYears(17)->startOfDay());
                } elseif ($range === 'adult') {
                    $p->whereBetween('dob', [now()->subYears(49)->startOfDay(), now()->subYears(18)->endOfDay()]);
                } elseif ($range === 'senior') {
                    $p->where('dob', '<=', now()->subYears(50)->endOfDay());
                }
            });
        }

        if ($request->filled('request_type')) {
            $query->where('request_type', $request->request_type);
        }

        if ($request->filled('validation_status')) {
            $query->where('validation_status', $request->validation_status);
        }

        return $query;
    }

    // Helper for robust start/end datetime parsing from filter inputs
    protected function parseAuditPeriod(Request $request)
    {
        $startStr = trim($request->input('start_date', ''));
        $endStr = trim($request->input('end_date', ''));

        if (!empty($startStr)) {
            $startDate = \Carbon\Carbon::parse($startStr);
            if (strlen($startStr) <= 10) {
                $startDate = $startDate->startOfDay();
            }
        } else {
            $startDate = now()->subDays(30)->startOfDay();
        }

        if (!empty($endStr)) {
            $endDate = \Carbon\Carbon::parse($endStr);
            if (strlen($endStr) <= 10 || str_ends_with($endStr, 'T00:00') || str_ends_with($endStr, ' 00:00:00')) {
                $endDate = $endDate->endOfDay();
            }
        } else {
            $endDate = now()->endOfDay();
        }

        return [$startDate, $endDate];
    }

    public function cashbookStoryData(Request $request, $story)
    {
        [$startDate, $endDate] = $this->parseAuditPeriod($request);

        switch ($story) {
            case 'channel-breakdown':
                $rows = \App\Models\Payment::with('bank')
                    ->selectRaw('payment_method, bank_id, COUNT(*) as txn_count, SUM(total) as total_amount')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->groupBy('payment_method', 'bank_id')
                    ->get();

                $claimsByMethod = \App\Models\ProductOrServiceRequest::whereNotNull('payment_id')
                    ->whereHas('payment', fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                    ->join('payments', 'payments.id', '=', 'product_or_service_requests.payment_id')
                    ->selectRaw('payments.payment_method, SUM(product_or_service_requests.claims_amount) as total_claims')
                    ->groupBy('payments.payment_method')
                    ->pluck('total_claims', 'payment_method');

                $formattedRows = $rows->map(function($r) use ($claimsByMethod) {
                    $method = strtoupper($r->payment_method ?? 'CASH');
                    $label = $method . ($r->bank ? ' (' . $r->bank->name . ')' : '');
                    $claims = round($claimsByMethod[$r->payment_method] ?? 0, 2);
                    $total = round($r->total_amount, 2);

                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="cashbook" data-story="channel-breakdown" data-key="' . e($r->payment_method ?? 'CASH') . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'channel' => '<span class="badge bg-primary text-white font-weight-bold px-2 py-1"><i class="mdi mdi-credit-card"></i> ' . e($label) . '</span>',
                        'txn_count' => '<span class="badge bg-light text-dark border font-weight-bold px-2 py-1"><i class="mdi mdi-swap-horizontal"></i> ' . (int)$r->txn_count . ' Txns</span>',
                        'total_amount' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($total, 2) . '</span>',
                        'claims_amount' => '<span class="font-weight-bold text-info">₦' . number_format($claims, 2) . '</span>',
                    ];
                });

                $cards = [
                    ['label' => 'Total Collections', 'value' => '₦' . number_format($rows->sum('total_amount'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Total Claims Value', 'value' => '₦' . number_format($claimsByMethod->sum(), 2), 'class' => 'bg-info text-white'],
                    ['label' => 'Unique Channels', 'value' => $rows->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Transactions', 'value' => $rows->sum('txn_count'), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Channel', 'Transactions', 'Amount Collected', 'Claims Amount']]);

            case 'payment-type':
                $rows = \App\Models\Payment::selectRaw("payment_type, COUNT(*) as txn_count, SUM(total) as total_amount")
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->where('payment_type', '!=', 'HMO_FULL_COVER')
                    ->groupBy('payment_type')
                    ->get();

                $formattedRows = $rows->map(function($r) {
                    $label = str_replace('_', ' ', $r->payment_type);
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="cashbook" data-story="payment-type" data-key="' . e($r->payment_type) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'payment_type' => '<span class="badge bg-primary text-white px-2 py-1"><i class="mdi mdi-tag-outline"></i> ' . e($label) . '</span>',
                        'txn_count' => '<span class="badge bg-light text-dark border font-weight-bold px-2 py-1">' . (int)$r->txn_count . ' Txns</span>',
                        'total_amount' => '<span class="font-weight-bold text-dark" style="font-size:1.05rem;">₦' . number_format($r->total_amount, 2) . '</span>',
                    ];
                });

                $cards = [
                    ['label' => 'Service Checkouts', 'value' => '₦' . number_format($rows->where('payment_type', 'SERVICE_CHECKOUT')->sum('total_amount'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Wallet Deposits', 'value' => '₦' . number_format($rows->where('payment_type', 'ACC_DEPOSIT')->sum('total_amount'), 2), 'class' => 'bg-info text-white'],
                    ['label' => 'Wallet Withdrawals', 'value' => '₦' . number_format($rows->where('payment_type', 'ACC_WITHDRAW')->sum('total_amount'), 2), 'class' => 'bg-warning text-dark'],
                    ['label' => 'Org Settlements', 'value' => '₦' . number_format($rows->where('payment_type', 'ORGANIZATION_BILL_SETTLEMENT')->sum('total_amount'), 2), 'class' => 'bg-primary text-white'],
                    ['label' => 'Staff Settlements', 'value' => '₦' . number_format($rows->where('payment_type', 'STAFF_BILL_SETTLEMENT')->sum('total_amount'), 2), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Payment Type', 'Transactions', 'Total Amount']]);

            case 'revenue-attribution':
                $serviceRevenue = \App\Models\ProductOrServiceRequest::whereNotNull('service_id')
                    ->whereNotNull('payment_id')
                    ->whereHas('payment', fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                    ->with('service:id,service_name,category_id')
                    ->selectRaw('service_id, COUNT(*) as cnt, SUM(payable_amount) as total_revenue, SUM(claims_amount) as total_claims')
                    ->groupBy('service_id')
                    ->orderByDesc('total_revenue')
                    ->get();

                $productRevenue = \App\Models\ProductOrServiceRequest::whereNotNull('product_id')
                    ->whereNotNull('payment_id')
                    ->whereHas('payment', fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                    ->with('product:id,product_name,category_id')
                    ->selectRaw('product_id, COUNT(*) as cnt, SUM(payable_amount) as total_revenue, SUM(claims_amount) as total_claims')
                    ->groupBy('product_id')
                    ->orderByDesc('total_revenue')
                    ->get();

                $totalServiceRev = $serviceRevenue->sum('total_revenue');
                $totalProductRev = $productRevenue->sum('total_revenue');
                $totalClaims = $serviceRevenue->sum('total_claims') + $productRevenue->sum('total_claims');

                $sRows = $serviceRevenue->map(fn($r) => [
                    'raw_rev' => (float)$r->total_revenue,
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="cashbook" data-story="revenue-attribution" data-key="service|' . $r->service_id . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'item' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-medical-bag text-info"></i> ' . e($r->service->service_name ?? 'Unknown Service') . '</div>',
                    'type' => '<span class="badge bg-info text-white"><i class="mdi mdi-cube-outline"></i> Service</span>',
                    'qty' => '<span class="badge bg-light text-dark border font-weight-bold">' . (int)$r->cnt . ' Qty</span>',
                    'revenue' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->total_revenue, 2) . '</span>',
                    'claims' => '<span class="font-weight-bold text-info">₦' . number_format($r->total_claims, 2) . '</span>',
                ]);

                $pRows = $productRevenue->map(fn($r) => [
                    'raw_rev' => (float)$r->total_revenue,
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="cashbook" data-story="revenue-attribution" data-key="product|' . $r->product_id . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'item' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-pill text-primary"></i> ' . e($r->product->product_name ?? 'Unknown Product') . '</div>',
                    'type' => '<span class="badge bg-primary text-white"><i class="mdi mdi-package-variant-closed"></i> Product</span>',
                    'qty' => '<span class="badge bg-light text-dark border font-weight-bold">' . (int)$r->cnt . ' Qty</span>',
                    'revenue' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->total_revenue, 2) . '</span>',
                    'claims' => '<span class="font-weight-bold text-info">₦' . number_format($r->total_claims, 2) . '</span>',
                ]);

                $rows = $sRows->merge($pRows)->sortByDesc('raw_rev')->map(function($r) {
                    unset($r['raw_rev']);
                    return $r;
                })->values();

                $totalQty = $serviceRevenue->sum('cnt') + $productRevenue->sum('cnt');
                $cards = [
                    ['label' => 'Total Service Revenue', 'value' => '₦' . number_format($totalServiceRev, 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Total Product Revenue', 'value' => '₦' . number_format($totalProductRev, 2), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Claims Value', 'value' => '₦' . number_format($totalClaims, 2), 'class' => 'bg-info text-white'],
                    ['label' => 'Avg Ticket Size', 'value' => '₦' . number_format($totalQty > 0 ? ($totalServiceRev + $totalProductRev) / $totalQty : 0, 2), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $rows, 'headers' => ['Action', 'Item', 'Type', 'Qty Sold', 'Revenue', 'Claims Amount']]);

            case 'cashier-performance':
                $rows = \App\Models\Payment::with('staff_user')
                    ->selectRaw('user_id, COUNT(*) as txn_count, SUM(total) as total_collected, SUM(total_discount) as total_discount')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->groupBy('user_id')
                    ->get();

                $formattedRows = $rows->map(function($r) use ($startDate, $endDate) {
                    $claimsAmount = \App\Models\ProductOrServiceRequest::whereHas('payment', fn($q) => $q->where('user_id', $r->user_id)->whereBetween('created_at', [$startDate, $endDate]))->sum('claims_amount');
                    $avg = $r->txn_count > 0 ? round($r->total_collected / $r->txn_count, 2) : 0;
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="cashbook" data-story="cashier-performance" data-key="' . e($r->user_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'cashier' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-account-tie text-primary"></i> ' . e($r->staff_user->name ?? 'System Cashier') . '</div>',
                        'txn_count' => '<span class="badge bg-light text-dark border font-weight-bold px-2 py-1">' . (int)$r->txn_count . ' Txns</span>',
                        'total_collected' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->total_collected, 2) . '</span>',
                        'total_discount' => '<span class="font-weight-bold text-danger">₦' . number_format($r->total_discount, 2) . '</span>',
                        'claims_amount' => '<span class="font-weight-bold text-info">₦' . number_format($claimsAmount, 2) . '</span>',
                        'avg_per_txn' => '<span class="text-muted font-weight-bold">₦' . number_format($avg, 2) . '</span>',
                    ];
                });

                $cards = [
                    ['label' => 'Active Cashiers', 'value' => $rows->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Collected', 'value' => '₦' . number_format($rows->sum('total_collected'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Total Discounts Given', 'value' => '₦' . number_format($rows->sum('total_discount'), 2), 'class' => 'bg-danger text-white'],
                    ['label' => 'Active Cashier Count', 'value' => $rows->count(), 'class' => 'bg-info text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Cashier', 'Transactions', 'Total Collected', 'Total Discount', 'Claims Amount', 'Avg Per Txn']]);

            case 'daily-cashflow':
                $rows = \App\Models\Payment::selectRaw('DATE(created_at) as date, COUNT(*) as txn_count, SUM(total) as total_in')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->groupByRaw('DATE(created_at)')
                    ->orderBy('date')
                    ->get();

                $formattedRows = $rows->map(function($r) {
                    $avg = $r->txn_count > 0 ? round($r->total_in / $r->txn_count, 2) : 0;
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="cashbook" data-story="daily-cashflow" data-key="' . e($r->date) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'date' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-calendar-clock text-primary"></i> ' . e($r->date) . '</div>',
                        'txn_count' => '<span class="badge bg-light text-dark border font-weight-bold px-2 py-1">' . (int)$r->txn_count . ' Txns</span>',
                        'total_in' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->total_in, 2) . '</span>',
                        'avg_per_txn' => '<span class="text-muted font-weight-bold">₦' . number_format($avg, 2) . '</span>',
                    ];
                });

                $peakDay = $rows->sortByDesc('total_in')->first();
                $cards = [
                    ['label' => 'Period Total', 'value' => '₦' . number_format($rows->sum('total_in'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Peak Day', 'value' => ($peakDay->date ?? 'N/A') . ' (₦' . number_format($peakDay->total_in ?? 0, 2) . ')', 'class' => 'bg-primary text-white'],
                    ['label' => 'Daily Average', 'value' => '₦' . number_format($rows->count() > 0 ? $rows->sum('total_in') / $rows->count() : 0, 2), 'class' => 'bg-info text-white'],
                    ['label' => 'Active Days', 'value' => $rows->count(), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Date', 'Transactions', 'Total Revenue', 'Avg Per Txn'], 'chart' => true]);

            case 'gl-summary':
                $rows = \App\Models\Accounting\JournalEntryLine::with('account')
                    ->whereHas('journalEntry', fn($q) => $q->whereBetween('entry_date', [$startDate, $endDate]))
                    ->selectRaw('account_id, SUM(debit) as total_debit, SUM(credit) as total_credit')
                    ->groupBy('account_id')
                    ->get();

                $formattedRows = $rows->map(function($r) {
                    $net = round($r->total_debit - $r->total_credit, 2);
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="cashbook" data-story="gl-summary" data-key="' . e($r->account_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'account_code' => '<span class="badge bg-secondary font-weight-bold"><i class="mdi mdi-pound"></i> ' . e($r->account->code ?? '??') . '</span>',
                        'account_name' => '<div class="font-weight-bold text-dark">' . e($r->account->name ?? 'Unknown') . '</div>',
                        'total_debit' => '<span class="font-weight-bold text-primary">₦' . number_format($r->total_debit, 2) . '</span>',
                        'total_credit' => '<span class="font-weight-bold text-success">₦' . number_format($r->total_credit, 2) . '</span>',
                        'net' => '<span class="font-weight-bold ' . ($net < 0 ? 'text-danger' : 'text-dark') . '">₦' . number_format($net, 2) . '</span>',
                    ];
                });

                $totalDebits = $rows->sum('total_debit');
                $totalCredits = $rows->sum('total_credit');
                $cards = [
                    ['label' => 'Total Debits', 'value' => '₦' . number_format($totalDebits, 2), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Credits', 'value' => '₦' . number_format($totalCredits, 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Balance (Dr - Cr)', 'value' => '₦' . number_format($totalDebits - $totalCredits, 2), 'class' => abs($totalDebits - $totalCredits) < 1 ? 'bg-success text-white' : 'bg-danger text-white'],
                    ['label' => 'Accounts Hit', 'value' => $rows->count(), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Account Code', 'Account Name', 'Total Debit', 'Total Credit', 'Net']]);

            case 'hourly-heatmap':
                $rows = \App\Models\Payment::selectRaw('HOUR(created_at) as hour, COUNT(*) as txn_count, SUM(total) as total_amount')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->groupByRaw('HOUR(created_at)')
                    ->orderBy('hour')
                    ->get();

                $formattedRows = $rows->map(function($r) {
                    $hStr = sprintf('%02d:00', $r->hour);
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="cashbook" data-story="hourly-heatmap" data-key="' . e($r->hour) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'hour' => '<span class="badge bg-dark px-2 py-1"><i class="mdi mdi-clock-outline text-warning"></i> ' . e($hStr) . '</span>',
                        'txn_count' => '<span class="badge bg-light text-dark border font-weight-bold px-2 py-1">' . (int)$r->txn_count . ' Txns</span>',
                        'total_amount' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->total_amount, 2) . '</span>',
                    ];
                });

                $peak = $rows->sortByDesc('total_amount')->first();
                $cards = [
                    ['label' => 'Peak Hour', 'value' => ($peak ? sprintf('%02d:00', $peak->hour) : 'N/A'), 'class' => 'bg-primary text-white'],
                    ['label' => 'Peak Hour Revenue', 'value' => '₦' . number_format($peak->total_amount ?? 0, 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Active Hours', 'value' => $rows->count(), 'class' => 'bg-info text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Hour', 'Transactions', 'Total Revenue'], 'chart' => true]);

            case 'bank-recon':
                $rows = \App\Models\Accounting\BankReconciliation::with('bank')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderBy('statement_date', 'desc')
                    ->get();

                $formattedRows = $rows->map(function($r) {
                    $date = $r->statement_date ? \Carbon\Carbon::parse($r->statement_date)->format('Y-m-d') : 'N/A';
                    $status = ucfirst($r->status);
                    $var = round($r->variance, 2);
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="cashbook" data-story="bank-recon" data-key="' . e($r->bank_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'bank' => '<div class="font-weight-bold text-primary"><i class="mdi mdi-bank"></i> ' . e($r->bank->name ?? 'Unknown Bank') . '</div>',
                        'statement_date' => '<div class="font-weight-bold text-dark">' . e($date) . '</div>',
                        'gl_closing' => '<span class="font-weight-bold text-dark">₦' . number_format($r->gl_closing_balance, 2) . '</span>',
                        'stmt_closing' => '<span class="font-weight-bold text-info">₦' . number_format($r->statement_closing_balance, 2) . '</span>',
                        'variance' => '<span class="badge ' . ($var == 0 ? 'bg-success' : 'bg-danger') . ' font-weight-bold">₦' . number_format($var, 2) . '</span>',
                        'status' => '<span class="badge ' . ($status === 'Finalized' ? 'bg-success' : 'bg-warning text-dark') . '">' . e($status) . '</span>',
                    ];
                });

                $totalVariance = $rows->sum('variance');
                $cards = [
                    ['label' => 'Active Reconciliations', 'value' => $rows->where('status', 'draft')->count(), 'class' => 'bg-warning text-dark'],
                    ['label' => 'Total Variance', 'value' => '₦' . number_format(abs($totalVariance), 2), 'class' => $totalVariance == 0 ? 'bg-success text-white' : 'bg-danger text-white'],
                    ['label' => 'Banks Covered', 'value' => $rows->pluck('bank.name')->filter()->unique()->count(), 'class' => 'bg-primary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Bank', 'Statement Date', 'GL Closing', 'Statement Closing', 'Variance', 'Status']]);


            default:
                return response()->json(['error' => 'Unknown story: ' . $story], 404);
        }
    }

    public function receivablesStoryData(Request $request, $story)
    {
        [$startDate, $endDate] = $this->parseAuditPeriod($request);

        switch ($story) {
            case 'corporate-exposure':
                $rows = \App\Models\OrganizationBill::with('organization')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->selectRaw('organization_id, COUNT(*) as bill_count, SUM(total_amount) as total_billed, SUM(outstanding_amount) as total_outstanding')
                    ->groupBy('organization_id')
                    ->orderByDesc('total_outstanding')
                    ->get();

                $formattedRows = $rows->map(function($r) {
                    $collected = $r->total_billed - $r->total_outstanding;
                    $pct = $r->total_billed > 0 ? round(($collected / $r->total_billed) * 100, 1) : 0;
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="receivables" data-story="corporate-exposure" data-key="' . e($r->organization_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'organization' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-domain text-primary"></i> ' . e($r->organization->name ?? 'Unknown Organization') . '</div>',
                        'bill_count' => '<span class="badge bg-light text-dark border font-weight-bold px-2 py-1">' . (int)$r->bill_count . ' Bills</span>',
                        'total_billed' => '<span class="font-weight-bold text-dark">₦' . number_format($r->total_billed, 2) . '</span>',
                        'total_outstanding' => '<span class="font-weight-bold text-danger" style="font-size:1.05rem;">₦' . number_format($r->total_outstanding, 2) . '</span>',
                        'total_collected' => '<span class="font-weight-bold text-success">₦' . number_format($collected, 2) . '</span>',
                        'recovery_pct' => '<div class="d-flex align-items-center"><span class="font-weight-bold me-2 ' . ($pct >= 100 ? 'text-success' : 'text-warning') . '">' . $pct . '%</span><div class="progress flex-grow-1" style="height:6px;"><div class="progress-bar ' . ($pct >= 100 ? 'bg-success' : 'bg-warning') . '" style="width:' . min($pct, 100) . '%"></div></div></div>',
                    ];
                });

                $cards = [
                    ['label' => 'Total Corporate Debt', 'value' => '₦' . number_format($rows->sum('total_outstanding'), 2), 'class' => 'bg-danger text-white'],
                    ['label' => 'Total Billed', 'value' => '₦' . number_format($rows->sum('total_billed'), 2), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Collected', 'value' => '₦' . number_format($rows->sum('total_billed') - $rows->sum('total_outstanding'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Organizations', 'value' => $rows->count(), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Organization', 'Bills', 'Total Billed', 'Outstanding', 'Collected', 'Recovery %']]);

            case 'hmo-claims-aging':
                $schemeFilter = $request->hmo_scheme_id;
                $hmoFilter = $request->hmo_id;

                $query = \App\Models\ProductOrServiceRequest::query()
                    ->join('patients', 'product_or_service_requests.patient_id', '=', 'patients.id')
                    ->whereRaw('COALESCE(product_or_service_requests.hmo_id, patients.hmo_id) != 1')
                    ->whereNull('product_or_service_requests.hmo_remittance_id')
                    ->whereBetween('product_or_service_requests.created_at', [$startDate, $endDate]);

                if ($hmoFilter) {
                    $query->whereRaw('COALESCE(product_or_service_requests.hmo_id, patients.hmo_id) = ?', [$hmoFilter]);
                }
                if ($schemeFilter) {
                    $query->whereHas('patient.hmo', fn($q) => $q->where('hmo_scheme_id', $schemeFilter));
                }

                $rows = $query->selectRaw("
                        COALESCE(product_or_service_requests.hmo_id, patients.hmo_id) as effective_hmo_id,
                        COUNT(*) as claim_count,
                        SUM(CASE WHEN product_or_service_requests.claims_amount > 0 THEN product_or_service_requests.claims_amount ELSE product_or_service_requests.payable_amount END) as total_claims,
                        SUM(CASE WHEN DATEDIFF(NOW(), product_or_service_requests.created_at) <= 30 THEN (CASE WHEN product_or_service_requests.claims_amount > 0 THEN product_or_service_requests.claims_amount ELSE product_or_service_requests.payable_amount END) ELSE 0 END) as aging_0_30,
                        SUM(CASE WHEN DATEDIFF(NOW(), product_or_service_requests.created_at) BETWEEN 31 AND 60 THEN (CASE WHEN product_or_service_requests.claims_amount > 0 THEN product_or_service_requests.claims_amount ELSE product_or_service_requests.payable_amount END) ELSE 0 END) as aging_31_60,
                        SUM(CASE WHEN DATEDIFF(NOW(), product_or_service_requests.created_at) BETWEEN 61 AND 90 THEN (CASE WHEN product_or_service_requests.claims_amount > 0 THEN product_or_service_requests.claims_amount ELSE product_or_service_requests.payable_amount END) ELSE 0 END) as aging_61_90,
                        SUM(CASE WHEN DATEDIFF(NOW(), product_or_service_requests.created_at) > 90 THEN (CASE WHEN product_or_service_requests.claims_amount > 0 THEN product_or_service_requests.claims_amount ELSE product_or_service_requests.payable_amount END) ELSE 0 END) as aging_90_plus
                    ")
                    ->groupBy(\DB::raw('COALESCE(product_or_service_requests.hmo_id, patients.hmo_id)'))
                    ->get();

                $formattedRows = $rows->map(function($r) {
                    $hmo = \App\Models\Hmo::with('scheme')->find($r->effective_hmo_id);
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="receivables" data-story="hmo-claims-aging" data-key="' . e($r->effective_hmo_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'hmo' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-shield-check text-info"></i> ' . e($hmo->name ?? 'Unknown HMO') . '</div>',
                        'scheme' => '<span class="badge bg-light text-dark border"><i class="mdi mdi-office-building"></i> ' . e($hmo->scheme->name ?? 'N/A') . '</span>',
                        'claim_count' => '<span class="badge bg-light text-dark border font-weight-bold">' . (int)$r->claim_count . ' Claims</span>',
                        'total_claims' => '<span class="font-weight-bold text-primary" style="font-size:1.05rem;">₦' . number_format($r->total_claims, 2) . '</span>',
                        'aging_0_30' => '<span class="text-success font-weight-bold">₦' . number_format($r->aging_0_30, 2) . '</span>',
                        'aging_31_60' => '<span class="text-warning font-weight-bold">₦' . number_format($r->aging_31_60, 2) . '</span>',
                        'aging_61_90' => '<span class="text-info font-weight-bold">₦' . number_format($r->aging_61_90, 2) . '</span>',
                        'aging_90_plus' => '<span class="badge bg-danger font-weight-bold">₦' . number_format($r->aging_90_plus, 2) . '</span>',
                    ];
                });

                $cards = [
                    ['label' => 'Total Unremitted', 'value' => '₦' . number_format($rows->sum('total_claims'), 2), 'class' => 'bg-danger text-white'],
                    ['label' => '0–30 Days', 'value' => '₦' . number_format($rows->sum('aging_0_30'), 2), 'class' => 'bg-success text-white'],
                    ['label' => '31–60 Days', 'value' => '₦' . number_format($rows->sum('aging_31_60'), 2), 'class' => 'bg-warning text-dark'],
                    ['label' => '61–90 Days', 'value' => '₦' . number_format($rows->sum('aging_61_90'), 2), 'class' => 'bg-info text-white'],
                    ['label' => '90+ Days (Critical)', 'value' => '₦' . number_format($rows->sum('aging_90_plus'), 2), 'class' => 'bg-danger text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'HMO Provider', 'Scheme', 'Claims Count', 'Total Amount', '0-30d', '31-60d', '61-90d', '90+d (Critical)']]);

            case 'staff-debt-ledger':
                $rows = \App\Models\StaffBill::with('staffUser')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->selectRaw('staff_user_id, COUNT(*) as bill_count, SUM(total_amount) as total_billed, SUM(outstanding_amount) as total_outstanding, SUM(total_amount - outstanding_amount) as total_paid')
                    ->groupBy('staff_user_id')
                    ->get();

                $formattedRows = $rows->map(function($r) {
                    $pct = $r->total_billed > 0 ? round(($r->total_paid / $r->total_billed) * 100, 1) : 0;
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="receivables" data-story="staff-debt-ledger" data-key="' . e($r->staff_user_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'staff' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-account-tie text-primary"></i> ' . e($r->staffUser->name ?? 'Unknown Staff') . '</div>',
                        'bill_count' => '<span class="badge bg-light text-dark border font-weight-bold">' . (int)$r->bill_count . ' Bills</span>',
                        'total_billed' => '<span class="font-weight-bold text-dark">₦' . number_format($r->total_billed, 2) . '</span>',
                        'total_outstanding' => '<span class="font-weight-bold text-danger" style="font-size:1.05rem;">₦' . number_format($r->total_outstanding, 2) . '</span>',
                        'total_paid' => '<span class="font-weight-bold text-success">₦' . number_format($r->total_paid, 2) . '</span>',
                        'recovery_pct' => '<span class="badge ' . ($pct >= 100 ? 'bg-success' : 'bg-warning text-dark') . ' font-weight-bold">' . $pct . '%</span>',
                    ];
                });

                $totalBilled = $rows->sum('total_billed');
                $totalPaid = $rows->sum('total_paid');
                $cards = [
                    ['label' => 'Total Staff Debt', 'value' => '₦' . number_format($rows->sum('total_outstanding'), 2), 'class' => 'bg-danger text-white'],
                    ['label' => 'Staff With Debt', 'value' => $rows->where('total_outstanding', '>', 0)->count(), 'class' => 'bg-warning text-dark'],
                    ['label' => 'Total Recovered', 'value' => '₦' . number_format($totalPaid, 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Recovery Rate', 'value' => ($totalBilled > 0 ? round(($totalPaid / $totalBilled) * 100, 1) : 0) . '%', 'class' => 'bg-info text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Staff Member', 'Bills Count', 'Total Billed', 'Outstanding', 'Paid', 'Recovery %']]);

            case 'patient-wallet':
                $surplus = \App\Models\PatientAccount::with('patient.user', 'patient.hmo')
                    ->where('balance', '>', 0)
                    ->whereBetween('updated_at', [$startDate, $endDate])
                    ->orderByDesc('balance')
                    ->get();

                $deficit = \App\Models\PatientAccount::with('patient.user', 'patient.hmo')
                    ->where('balance', '<', 0)
                    ->whereBetween('updated_at', [$startDate, $endDate])
                    ->orderBy('balance')
                    ->get();

                // If period filter yields no updated records, fallback to active non-zero balances
                if ($surplus->isEmpty() && $deficit->isEmpty()) {
                    $surplus = \App\Models\PatientAccount::with('patient.user', 'patient.hmo')->where('balance', '>', 0)->orderByDesc('balance')->get();
                    $deficit = \App\Models\PatientAccount::with('patient.user', 'patient.hmo')->where('balance', '<', 0)->orderBy('balance')->get();
                }

                $sFormatted = $surplus->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="receivables" data-story="patient-wallet" data-key="' . e($r->patient_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'patient' => $this->renderPatientDetails($r->patient, 'Patient'),
                    'file_no' => '<small class="badge bg-light text-dark border">' . e($r->patient->file_no ?? 'N/A') . '</small>',
                    'hmo' => '<span class="badge bg-soft-info text-info border"><i class="mdi mdi-shield"></i> ' . e($r->patient->hmo->name ?? 'Private') . '</span>',
                    'balance' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->balance, 2) . '</span>',
                    'type' => '<span class="badge bg-success"><i class="mdi mdi-plus-circle"></i> Surplus</span>',
                ])->values()->all();

                $dFormatted = $deficit->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="receivables" data-story="patient-wallet" data-key="' . e($r->patient_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'patient' => $this->renderPatientDetails($r->patient, 'Patient'),
                    'file_no' => '<small class="badge bg-light text-dark border">' . e($r->patient->file_no ?? 'N/A') . '</small>',
                    'hmo' => '<span class="badge bg-soft-info text-info border"><i class="mdi mdi-shield"></i> ' . e($r->patient->hmo->name ?? 'Private') . '</span>',
                    'balance' => '<span class="font-weight-bold text-danger" style="font-size:1.05rem;">Due: ₦' . number_format(abs($r->balance), 2) . '</span>',
                    'type' => '<span class="badge bg-danger"><i class="mdi mdi-minus-circle"></i> Deficit</span>',
                ])->values()->all();

                $rows = array_merge($dFormatted, $sFormatted);

                $cards = [
                    ['label' => 'Total Surplus (Deposits)', 'value' => '₦' . number_format($surplus->sum('balance'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Total Deficit (Debtors)', 'value' => '₦' . number_format(abs($deficit->sum('balance')), 2), 'class' => 'bg-danger text-white'],
                    ['label' => 'Patients With Surplus', 'value' => $surplus->count(), 'class' => 'bg-info text-white'],
                    ['label' => 'Patients With Deficit', 'value' => $deficit->count(), 'class' => 'bg-warning text-dark'],
                    ['label' => 'Net Position', 'value' => '₦' . number_format($surplus->sum('balance') + $deficit->sum('balance'), 2), 'class' => 'bg-primary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $rows, 'headers' => ['Action', 'Patient Name', 'File No', 'HMO / Coverage', 'Wallet Balance', 'Position Type']]);

            case 'settlement-activity':
                $rows = \App\Models\Payment::with('staff_user')
                    ->whereIn('payment_type', ['STAFF_BILL_SETTLEMENT', 'ORGANIZATION_BILL_SETTLEMENT'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderByDesc('created_at')
                    ->get();

                $formattedRows = $rows->map(function($r) {
                    $debtorName = 'Settlement Client';
                    if ($r->payment_type === 'STAFF_BILL_SETTLEMENT') {
                        $staffBill = \App\Models\StaffBill::where('settlement_payment_id', $r->id)->orWhere('payment_id', $r->id)->with('staffUser')->first();
                        if (!$staffBill) {
                            $alloc = \DB::table('staff_bill_payment_allocations')->where('payment_id', $r->id)->first();
                            if ($alloc) {
                                $staffBill = \App\Models\StaffBill::with('staffUser')->find($alloc->staff_bill_id);
                            }
                        }
                        if (!$staffBill) {
                            $staffBill = \App\Models\StaffBill::with('staffUser')->first();
                        }
                        $debtorName = $staffBill->staffUser->name ?? 'Staff Member';

                    } elseif ($r->payment_type === 'ORGANIZATION_BILL_SETTLEMENT') {
                        $orgBill = \App\Models\OrganizationBill::where('settlement_payment_id', $r->id)->orWhere('payment_id', $r->id)->with('organization')->first();
                        if (!$orgBill) {
                            $alloc = \DB::table('organization_bill_payment_allocations')->where('payment_id', $r->id)->first();
                            if ($alloc) {
                                $orgBill = \App\Models\OrganizationBill::with('organization')->find($alloc->organization_bill_id);
                            }
                        }
                        if (!$orgBill) {
                            $orgBill = \App\Models\OrganizationBill::with('organization')->first();
                        }
                        $debtorName = $orgBill->organization->name ?? 'Organization';
                    }


                    $typeLabel = str_replace('_', ' ', $r->payment_type);
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="receivables" data-story="settlement-activity" data-key="' . e($r->id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'date' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-clock-outline text-muted"></i> ' . e($r->created_at->format('M d, Y h:i A')) . '</div>',
                        'reference' => '<span class="badge bg-light text-dark border">' . e($r->reference_no ?? ('#' . $r->id)) . '</span>',
                        'debtor' => '<div class="font-weight-bold text-primary">' . e($debtorName) . '</div>',
                        'type' => '<span class="badge bg-info"><i class="mdi mdi-tag"></i> ' . e($typeLabel) . '</span>',
                        'amount' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->total, 2) . '</span>',
                        'method' => '<span class="badge bg-secondary">' . e($r->payment_method ?? 'CASH') . '</span>',
                        'cashier' => '<div class="text-dark"><i class="mdi mdi-account-check"></i> ' . e($r->staff_user->name ?? 'System Cashier') . '</div>',
                    ];
                });

                $orgTotal = $rows->where('payment_type', 'ORGANIZATION_BILL_SETTLEMENT')->sum('total');
                $staffTotal = $rows->where('payment_type', 'STAFF_BILL_SETTLEMENT')->sum('total');
                $cards = [
                    ['label' => 'Org Settlements', 'value' => '₦' . number_format($orgTotal, 2), 'class' => 'bg-primary text-white'],
                    ['label' => 'Staff Settlements', 'value' => '₦' . number_format($staffTotal, 2), 'class' => 'bg-info text-white'],
                    ['label' => 'Total Recovered', 'value' => '₦' . number_format($orgTotal + $staffTotal, 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Transactions', 'value' => $rows->count(), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Date & Time', 'Reference', 'Debtor', 'Settlement Type', 'Amount Settled', 'Payment Method', 'Receiving Cashier']]);

            default:
                return response()->json(['error' => 'Unknown story: ' . $story], 404);
        }
    }

    public function storyRowDetails(Request $request, $zone, $story)
    {
        [$startDate, $endDate] = $this->parseAuditPeriod($request);
        $key = $request->input('key');

        $rows = collect([]);
        $cards = [];
        $headers = [];
        $title = 'Story Transaction Details';
        $subtitle = 'Period: ' . $startDate->format('M d, Y H:i') . ' – ' . $endDate->format('M d, Y H:i');

        switch ($story) {
            case 'channel-breakdown':

                $title = 'Channel Breakdown Transactions: ' . strtoupper($key ?? 'CASH');
                $query = \App\Models\Payment::with(['staff_user', 'bank', 'patient.user', 'product_or_service_request.service.category', 'product_or_service_request.product.category'])
                    ->where('payment_method', $key)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderByDesc('created_at');

                $records = $query->get();
                $rows = $records->map(fn($r) => [
                    'date' => $r->created_at->format('Y-m-d H:i'),
                    'ref' => '<span class="badge bg-light text-dark border">' . e($r->reference_no ?? ('#' . $r->id)) . '</span>',
                    'patient' => $this->renderPaymentEntityDetails($r, 'Walk-in / N/A'),
                    'item' => $this->renderPaymentItemDetails($r),
                    'type' => str_replace('_', ' ', $r->payment_type),
                    'total' => '<span class="font-weight-bold text-success">₦' . number_format($r->total, 2) . '</span>',
                    'cashier' => e($r->staff_user->name ?? 'System'),
                    'bank' => e($r->bank->name ?? 'N/A'),
                ]);

                $cards = [
                    ['label' => 'Total Amount', 'value' => '₦' . number_format($records->sum('total'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Transactions', 'value' => $records->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Avg Per Txn', 'value' => '₦' . number_format($records->count() > 0 ? $records->sum('total') / $records->count() : 0, 2), 'class' => 'bg-info text-white'],
                ];
                $headers = ['Date & Time', 'Reference', 'Patient/Client', 'Service / Item(s)', 'Type', 'Amount', 'Cashier', 'Bank'];
                break;

            case 'payment-type':
                $title = 'Payment Classification: ' . str_replace('_', ' ', strtoupper($key ?? ''));
                $records = \App\Models\Payment::with(['staff_user', 'bank', 'patient.user', 'product_or_service_request.service.category', 'product_or_service_request.product.category'])
                    ->where('payment_type', $key)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderByDesc('created_at')
                    ->get();

                $rows = $records->map(fn($r) => [
                    'date' => $r->created_at->format('Y-m-d H:i'),
                    'ref' => '<span class="badge bg-light text-dark border">' . e($r->reference_no ?? ('#' . $r->id)) . '</span>',
                    'patient' => $this->renderPaymentEntityDetails($r, 'System Client'),
                    'item' => $this->renderPaymentItemDetails($r),
                    'method' => e($r->payment_method ?? 'CASH'),
                    'total' => '<span class="font-weight-bold text-dark">₦' . number_format($r->total, 2) . '</span>',
                    'cashier' => e($r->staff_user->name ?? 'System'),
                ]);
                $cards = [
                    ['label' => 'Total Classification Value', 'value' => '₦' . number_format($records->sum('total'), 2), 'class' => 'bg-primary text-white'],
                    ['label' => 'Transaction Count', 'value' => $records->count(), 'class' => 'bg-info text-white'],
                ];
                $headers = ['Date & Time', 'Reference', 'Patient / Client', 'Service / Item(s)', 'Method', 'Total Amount', 'Cashier'];
                break;

            case 'revenue-attribution':
                $keyParts = explode('|', $key . '|');
                $itemType = $keyParts[0];
                $itemId = $keyParts[1];
                $query = \App\Models\ProductOrServiceRequest::whereNotNull('payment_id')
                    ->whereHas('payment', fn($q) => $q->whereBetween('created_at', [$startDate, $endDate]))
                    ->with(['service', 'product', 'patient.user', 'payment.staff_user']);

                if ($itemType === 'service') {
                    $query->where('service_id', $itemId);
                } else {
                    $query->where('product_id', $itemId);
                }

                $records = $query->orderByDesc('created_at')->get();
                $firstItem = $records->first();
                $itemName = $itemType === 'service' ? ($firstItem->service->service_name ?? 'Service #' . $itemId) : ($firstItem->product->product_name ?? 'Product #' . $itemId);
                $title = 'Revenue Detail: ' . $itemName;

                $rows = $records->map(fn($r) => [
                    'date' => $r->created_at->format('Y-m-d H:i'),
                    'code' => '<span class="badge bg-light text-dark border">' . e($r->request_code ?? ('#' . $r->id)) . '</span>',
                    'patient' => $this->renderPatientDetails($r->patient, 'N/A'),
                    'qty' => (int)($r->quantity ?? 1),
                    'payable' => '<span class="font-weight-bold text-success">₦' . number_format($r->payable_amount, 2) . '</span>',
                    'claims' => '<span class="font-weight-bold text-info">₦' . number_format($r->claims_amount, 2) . '</span>',
                    'cashier' => e($r->payment->staff_user->name ?? 'System'),
                ]);
                $cards = [
                    ['label' => 'Total Item Revenue', 'value' => '₦' . number_format($records->sum('payable_amount'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Total Claims Amount', 'value' => '₦' . number_format($records->sum('claims_amount'), 2), 'class' => 'bg-info text-white'],
                    ['label' => 'Quantity Sold', 'value' => $records->sum('quantity'), 'class' => 'bg-primary text-white'],
                ];
                $headers = ['Date & Time', 'Request Code', 'Patient Name', 'Qty', 'Payable Amount', 'Claims Amount', 'Cashier'];
                break;

            case 'cashier-performance':
                $cashierUser = \App\Models\User::find($key);
                $title = 'Cashier Transactions Audit: ' . ($cashierUser->name ?? ('User #' . $key));
                $records = \App\Models\Payment::with(['patient.user', 'bank', 'product_or_service_request.service.category', 'product_or_service_request.product.category'])
                    ->where('user_id', $key)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderByDesc('created_at')
                    ->get();

                $rows = $records->map(fn($r) => [
                    'date' => $r->created_at->format('Y-m-d H:i'),
                    'ref' => '<span class="badge bg-light text-dark border">' . e($r->reference_no ?? ('#' . $r->id)) . '</span>',
                    'patient' => $this->renderPaymentEntityDetails($r, 'Walk-in / N/A'),
                    'item' => $this->renderPaymentItemDetails($r),
                    'type' => str_replace('_', ' ', $r->payment_type),
                    'method' => e($r->payment_method ?? 'CASH'),
                    'discount' => '<span class="text-danger">₦' . number_format($r->total_discount, 2) . '</span>',
                    'total' => '<span class="font-weight-bold text-success">₦' . number_format($r->total, 2) . '</span>',
                ]);
                $cards = [
                    ['label' => 'Total Cashier Collections', 'value' => '₦' . number_format($records->sum('total'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Discounts Granted', 'value' => '₦' . number_format($records->sum('total_discount'), 2), 'class' => 'bg-danger text-white'],
                    ['label' => 'Transaction Count', 'value' => $records->count(), 'class' => 'bg-primary text-white'],
                ];
                $headers = ['Date & Time', 'Reference', 'Patient Name', 'Service / Item(s)', 'Payment Type', 'Method', 'Discount', 'Total Collected'];
                break;

            case 'daily-cashflow':
                $title = 'Daily Cash Flow Audit: ' . e($key);
                $records = \App\Models\Payment::with(['staff_user', 'bank', 'patient.user', 'product_or_service_request.service.category', 'product_or_service_request.product.category'])
                    ->whereDate('created_at', $key)
                    ->orderByDesc('created_at')
                    ->get();

                $rows = $records->map(fn($r) => [
                    'time' => $r->created_at->format('H:i:s'),
                    'ref' => '<span class="badge bg-light text-dark border">' . e($r->reference_no ?? ('#' . $r->id)) . '</span>',
                    'patient' => $this->renderPaymentEntityDetails($r, 'Walk-in / N/A'),
                    'item' => $this->renderPaymentItemDetails($r),
                    'type' => str_replace('_', ' ', $r->payment_type),
                    'method' => e($r->payment_method ?? 'CASH'),
                    'total' => '<span class="font-weight-bold text-success">₦' . number_format($r->total, 2) . '</span>',
                    'cashier' => e($r->staff_user->name ?? 'System'),
                ]);
                $cards = [
                    ['label' => 'Day Total Collections', 'value' => '₦' . number_format($records->sum('total'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Transactions on Date', 'value' => $records->count(), 'class' => 'bg-info text-white'],
                ];
                $headers = ['Time', 'Reference', 'Patient / Client', 'Service / Item(s)', 'Payment Type', 'Method', 'Amount', 'Cashier'];
                break;

            case 'gl-summary':
                $account = \App\Models\Accounting\ChartOfAccount::find($key);
                $title = 'GL Ledger Entries: ' . ($account->code ?? '') . ' - ' . ($account->name ?? ('Account #' . $key));
                $records = \App\Models\Accounting\JournalEntryLine::with(['account', 'journalEntry.user'])
                    ->where('account_id', $key)
                    ->whereHas('journalEntry', fn($q) => $q->whereBetween('entry_date', [$startDate, $endDate]))
                    ->orderByDesc('created_at')
                    ->get();

                $rows = $records->map(fn($r) => [
                    'date' => $r->journalEntry->entry_date ?? $r->created_at->format('Y-m-d'),
                    'code' => '<span class="badge bg-light text-dark border">' . e($r->journalEntry->entry_number ?? ('#' . $r->journal_entry_id)) . '</span>',
                    'desc' => e($r->description ?? $r->journalEntry->description ?? 'Journal Entry'),
                    'debit' => '<span class="font-weight-bold text-primary">₦' . number_format($r->debit, 2) . '</span>',
                    'credit' => '<span class="font-weight-bold text-success">₦' . number_format($r->credit, 2) . '</span>',
                ]);
                $totalDr = $records->sum('debit');
                $totalCr = $records->sum('credit');
                $cards = [
                    ['label' => 'Total Debits', 'value' => '₦' . number_format($totalDr, 2), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Credits', 'value' => '₦' . number_format($totalCr, 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Net Balance', 'value' => '₦' . number_format($totalDr - $totalCr, 2), 'class' => 'bg-secondary text-white'],
                ];
                $headers = ['Entry Date', 'Journal Code', 'Description', 'Debit (Dr)', 'Credit (Cr)'];
                break;

            case 'hourly-heatmap':
                $hStr = sprintf('%02d:00', (int)$key);
                $title = 'Operating Hour Transactions Audit: ' . $hStr;
                $records = \App\Models\Payment::with(['staff_user', 'bank', 'patient.user', 'product_or_service_request.service.category', 'product_or_service_request.product.category'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->whereRaw('HOUR(created_at) = ?', [(int)$key])
                    ->orderByDesc('created_at')
                    ->get();

                $rows = $records->map(fn($r) => [
                    'date' => $r->created_at->format('Y-m-d H:i:s'),
                    'ref' => '<span class="badge bg-light text-dark border">' . e($r->reference_no ?? ('#' . $r->id)) . '</span>',
                    'patient' => $this->renderPaymentEntityDetails($r, 'Walk-in / N/A'),
                    'item' => $this->renderPaymentItemDetails($r),
                    'method' => e($r->payment_method ?? 'CASH'),
                    'total' => '<span class="font-weight-bold text-success">₦' . number_format($r->total, 2) . '</span>',
                    'cashier' => e($r->staff_user->name ?? 'System'),
                ]);
                $cards = [
                    ['label' => 'Hour Total Collected', 'value' => '₦' . number_format($records->sum('total'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Transactions in Hour', 'value' => $records->count(), 'class' => 'bg-info text-white'],
                ];
                $headers = ['Date & Time', 'Reference', 'Patient / Client', 'Service / Item(s)', 'Method', 'Total Amount', 'Cashier'];
                break;

            case 'bank-recon':
                $bank = \App\Models\Accounting\Bank::find($key);
                $title = 'Bank Reconciliation Statements: ' . ($bank->name ?? ('Bank #' . $key));
                $records = \App\Models\Accounting\BankReconciliation::with(['bank', 'creator'])
                    ->where('bank_id', $key)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderByDesc('statement_date')
                    ->get();

                $rows = $records->map(fn($r) => [
                    'date' => $r->statement_date ? \Carbon\Carbon::parse($r->statement_date)->format('Y-m-d') : 'N/A',
                    'gl' => '<span class="font-weight-bold text-dark">₦' . number_format($r->gl_closing_balance, 2) . '</span>',
                    'stmt' => '<span class="font-weight-bold text-info">₦' . number_format($r->statement_closing_balance, 2) . '</span>',
                    'var' => '<span class="badge ' . ($r->variance == 0 ? 'bg-success' : 'bg-danger') . ' font-weight-bold">₦' . number_format($r->variance, 2) . '</span>',
                    'status' => '<span class="badge ' . ($r->status === 'finalized' ? 'bg-success' : 'bg-warning text-dark') . '">' . e(ucfirst($r->status)) . '</span>',
                ]);
                $cards = [
                    ['label' => 'Reconciliation Count', 'value' => $records->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Variance', 'value' => '₦' . number_format(abs($records->sum('variance')), 2), 'class' => 'bg-danger text-white'],
                ];
                $headers = ['Statement Date', 'GL Closing', 'Statement Closing', 'Variance', 'Status'];
                break;

            case 'corporate-exposure':
                $org = \App\Models\Organization::find($key);
                $title = 'Corporate Client Bills: ' . ($org->name ?? ('Organization #' . $key));
                $records = \App\Models\OrganizationBill::with(['organization', 'settlementPayment'])
                    ->where('organization_id', $key)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderByDesc('created_at')
                    ->get();

                $rows = $records->map(fn($r) => [
                    'date' => $r->created_at->format('Y-m-d H:i'),
                    'code' => '<span class="badge bg-light text-dark border">' . e($r->bill_code ?? ('#' . $r->id)) . '</span>',
                    'total' => '<span class="font-weight-bold text-dark">₦' . number_format($r->total_amount, 2) . '</span>',
                    'outstanding' => '<span class="font-weight-bold text-danger">₦' . number_format($r->outstanding_amount, 2) . '</span>',
                    'paid' => '<span class="font-weight-bold text-success">₦' . number_format($r->total_amount - $r->outstanding_amount, 2) . '</span>',
                    'status' => '<span class="badge ' . ($r->outstanding_amount <= 0 ? 'bg-success' : 'bg-warning text-dark') . '">' . ($r->outstanding_amount <= 0 ? 'PAID' : 'OUTSTANDING') . '</span>',
                ]);
                $cards = [
                    ['label' => 'Total Billed', 'value' => '₦' . number_format($records->sum('total_amount'), 2), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Outstanding', 'value' => '₦' . number_format($records->sum('outstanding_amount'), 2), 'class' => 'bg-danger text-white'],
                    ['label' => 'Total Collected', 'value' => '₦' . number_format($records->sum('total_amount') - $records->sum('outstanding_amount'), 2), 'class' => 'bg-success text-white'],
                ];
                $headers = ['Bill Date', 'Bill Code', 'Total Billed', 'Outstanding Amount', 'Paid Amount', 'Status'];
                break;

            case 'hmo-claims-aging':
                $hmo = $key ? \App\Models\Hmo::with('scheme')->find($key) : null;
                $hmoName = $hmo->name ?? ($key ? ('HMO #' . $key) : 'All HMO Providers');
                $schemeName = (isset($hmo->scheme) && isset($hmo->scheme->name)) ? ' (' . $hmo->scheme->name . ')' : '';
                $title = 'Unremitted HMO Claims: ' . $hmoName . $schemeName;

                $query = \App\Models\ProductOrServiceRequest::query()
                    ->join('patients', 'product_or_service_requests.patient_id', '=', 'patients.id')
                    ->with(['patient.user', 'patient.hmo.scheme', 'service', 'product'])
                    ->whereNull('product_or_service_requests.hmo_remittance_id')
                    ->whereBetween('product_or_service_requests.created_at', [$startDate, $endDate])
                    ->select('product_or_service_requests.*')
                    ->orderByDesc('product_or_service_requests.created_at');

                if ($key) {
                    $query->whereRaw('COALESCE(product_or_service_requests.hmo_id, patients.hmo_id) = ?', [$key]);
                } else {
                    $query->whereRaw('COALESCE(product_or_service_requests.hmo_id, patients.hmo_id) != 1');
                }

                $records = $query->get();

                $rows = $records->map(function($r) {
                    $days = now()->diffInDays($r->created_at);
                    $claimVal = $r->claims_amount > 0 ? $r->claims_amount : $r->payable_amount;
                    return [
                        'date' => $r->created_at->format('Y-m-d H:i'),
                        'code' => '<span class="badge bg-light text-dark border">' . e($r->request_code ?? ('#' . $r->id)) . '</span>',
                        'patient' => $this->renderPatientDetails($r->patient, 'HMO Patient'),
                        'item' => e($r->service->service_name ?? $r->product->product_name ?? 'Medical Request'),
                        'claims' => '<span class="font-weight-bold text-primary" style="font-size:1.05rem;">₦' . number_format($claimVal, 2) . '</span>',
                        'days' => '<span class="badge ' . ($days > 90 ? 'bg-danger' : ($days > 60 ? 'bg-warning text-dark' : 'bg-info')) . '">' . $days . ' Days</span>',
                    ];
                });
                
                $totalSum = $records->sum(fn($r) => $r->claims_amount > 0 ? $r->claims_amount : $r->payable_amount);
                $cards = [
                    ['label' => 'Unremitted Claims Sum', 'value' => '₦' . number_format($totalSum, 2), 'class' => 'bg-danger text-white'],
                    ['label' => 'Claims Count', 'value' => $records->count(), 'class' => 'bg-info text-white'],
                ];
                $headers = ['Request Date', 'Request Code', 'Patient Name', 'Service / Item', 'Claims Amount', 'Days Pending'];
                break;

            case 'staff-debt-ledger':
                $staff = \App\Models\User::find($key);
                $title = 'Staff Member Debt Bills: ' . ($staff->name ?? ('Staff #' . $key));
                $records = \App\Models\StaffBill::with(['staffUser', 'settlementPayment'])
                    ->where('staff_user_id', $key)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderByDesc('created_at')
                    ->get();

                $rows = $records->map(fn($r) => [
                    'date' => $r->created_at->format('Y-m-d H:i'),
                    'code' => '<span class="badge bg-light text-dark border">' . e($r->bill_code ?? ('#' . $r->id)) . '</span>',
                    'total' => '<span class="font-weight-bold text-dark">₦' . number_format($r->total_amount, 2) . '</span>',
                    'outstanding' => '<span class="font-weight-bold text-danger">₦' . number_format($r->outstanding_amount, 2) . '</span>',
                    'paid' => '<span class="font-weight-bold text-success">₦' . number_format($r->total_amount - $r->outstanding_amount, 2) . '</span>',
                    'status' => '<span class="badge ' . ($r->outstanding_amount <= 0 ? 'bg-success' : 'bg-warning text-dark') . '">' . ($r->outstanding_amount <= 0 ? 'PAID' : 'OUTSTANDING') . '</span>',
                ]);
                $cards = [
                    ['label' => 'Total Billed', 'value' => '₦' . number_format($records->sum('total_amount'), 2), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Outstanding', 'value' => '₦' . number_format($records->sum('outstanding_amount'), 2), 'class' => 'bg-danger text-white'],
                    ['label' => 'Total Paid', 'value' => '₦' . number_format($records->sum('total_amount') - $records->sum('outstanding_amount'), 2), 'class' => 'bg-success text-white'],
                ];
                $headers = ['Bill Date', 'Bill Code', 'Total Billed', 'Outstanding Amount', 'Paid Amount', 'Status'];
                break;

            case 'patient-wallet':
                $title = 'Patient Wallet Activity: Patient #' . $key;
                $records = \App\Models\Payment::with(['staff_user', 'bank', 'patient.user'])
                    ->where('patient_id', $key)
                    ->whereIn('payment_type', ['ACC_DEPOSIT', 'ACC_WITHDRAW'])
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->orderByDesc('created_at')
                    ->get();

                $firstRecord = $records->first();
                if ($firstRecord && isset($firstRecord->patient->user->name)) {
                    $title = 'Patient Wallet Activity: ' . $firstRecord->patient->user->name;
                }


                $rows = $records->map(fn($r) => [
                    'date' => $r->created_at->format('Y-m-d H:i'),
                    'ref' => '<span class="badge bg-light text-dark border">' . e($r->reference_no ?? ('#' . $r->id)) . '</span>',
                    'type' => '<span class="badge ' . ($r->payment_type === 'ACC_DEPOSIT' ? 'bg-success' : 'bg-warning text-dark') . '">' . str_replace('_', ' ', $r->payment_type) . '</span>',
                    'amount' => '<span class="font-weight-bold ' . ($r->payment_type === 'ACC_DEPOSIT' ? 'text-success' : 'text-danger') . '">₦' . number_format($r->total, 2) . '</span>',
                    'method' => e($r->payment_method ?? 'CASH'),
                    'cashier' => e($r->staff_user->name ?? 'System'),
                ]);
                $cards = [
                    ['label' => 'Total Wallet Activity', 'value' => $records->count() . ' Txns', 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Deposited', 'value' => '₦' . number_format($records->where('payment_type', 'ACC_DEPOSIT')->sum('total'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Total Withdrawn', 'value' => '₦' . number_format($records->where('payment_type', 'ACC_WITHDRAW')->sum('total'), 2), 'class' => 'bg-warning text-dark'],
                ];
                $headers = ['Date & Time', 'Reference', 'Type', 'Amount', 'Method', 'Cashier'];
                break;

            case 'settlement-activity':
                $title = 'Settlement Payment Details: Txn #' . $key;
                $records = \App\Models\Payment::with(['staff_user', 'bank', 'patient.user', 'product_or_service_request.service.category', 'product_or_service_request.product.category'])
                    ->where('id', $key)
                    ->take(1)->get();

                $rows = $records->map(fn($r) => [
                    'date' => $r->created_at->format('Y-m-d H:i'),
                    'ref' => '<span class="badge bg-light text-dark border">' . e($r->reference_no ?? ('#' . $r->id)) . '</span>',
                    'type' => str_replace('_', ' ', $r->payment_type),
                    'item' => $this->renderPaymentItemDetails($r),
                    'method' => e($r->payment_method ?? 'CASH'),
                    'amount' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->total, 2) . '</span>',
                    'cashier' => e($r->staff_user->name ?? 'System Cashier'),
                ]);
                $cards = [
                    ['label' => 'Settlement Amount', 'value' => '₦' . number_format($records->sum('total'), 2), 'class' => 'bg-success text-white'],
                ];
                $headers = ['Date & Time', 'Reference', 'Settlement Type', 'Service / Item(s)', 'Payment Method', 'Settled Amount', 'Receiving Cashier'];
                break;

            // ==================== INVENTORY & HMO DRILL-DOWN CASES ====================
            case 'batch-valuation':
            case 'product-turnover-rate':
            case 'substore-valuation':
                $title = 'Stock Batches Drill-down (Key: ' . e($key) . ')';
                $q = \DB::table('stock_batches as sb')
                    ->join('products as p', 'sb.product_id', '=', 'p.id')
                    ->join('stores as s', 'sb.store_id', '=', 's.id')
                    ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
                    ->select('sb.*', 'p.product_name', 's.store_name', \DB::raw('COALESCE(NULLIF(sb.cost_price,0), pp.pr_buy_price, 0) as calc_cost'))
                    ->whereBetween('sb.created_at', [$startDate, $endDate]);

                if ($story === 'batch-valuation') {
                    $q->where('p.category_id', $key);
                    if ($zone === 'main-store') {
                        $q->where(function ($sq) {
                            $sq->where('s.distribution_role', 'central')
                                ->orWhere('s.store_type', 'warehouse');
                        });
                    }
                } elseif ($story === 'substore-valuation') {
                    $q->where('sb.store_id', $key);
                } else {
                    $q->where('sb.product_id', $key);
                }

                $records = $q->orderByDesc('sb.created_at')->get();
                $rows = $records->map(fn($r) => [
                    'batch' => '<span class="badge bg-light text-dark border font-weight-bold">' . e($r->batch_number ?? ('#' . $r->id)) . '</span>',
                    'product' => e($r->product_name),
                    'store' => e($r->store_name),
                    'qty' => '<span class="badge ' . ($r->current_qty < $r->initial_qty ? 'bg-warning text-dark' : 'bg-info text-white') . ' font-weight-bold">' . number_format($r->current_qty) . '</span> / <span class="text-muted">' . number_format($r->initial_qty) . '</span>',
                    'cost' => '<span class="font-weight-bold text-success">₦' . number_format($r->calc_cost, 2) . '</span>',
                    'expiry' => $r->expiry_date ? \Carbon\Carbon::parse($r->expiry_date)->format('Y-m-d') : 'N/A',
                ]);
                $cards = [
                    ['label' => 'Total Batches', 'value' => $records->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Current Qty', 'value' => number_format($records->sum('current_qty')), 'class' => 'bg-info text-white'],
                    ['label' => 'Total Stock Value ₦', 'value' => '₦' . number_format($records->sum(fn($r) => $r->current_qty * $r->calc_cost), 2), 'class' => 'bg-success text-white'],
                ];
                $headers = ['Batch #', 'Product', 'Store', 'Qty (Left / Init)', 'Cost Price ₦', 'Expiry Date'];
                break;

            case 'hmo-claims-by-provider':
            case 'validation-status-aging':
            case 'scheme-breakdown':
            case 'coverage-mode-analysis':
            case 'remittance-vs-claims-matching':
            case 'dispensing-revenue-attribution':
            case 'store-dispensing-contribution':
            case 'service-category-revenue':
            case 'doctor-referral-billing':
            case 'service-vs-hmo-compliance':
                $title = 'Medical Request / Claims Details (' . ucfirst(str_replace('-', ' ', $story)) . ' : ' . e($key) . ')';
                $q = \DB::table('product_or_service_requests as posr')
                    ->leftJoin('products as p', 'posr.product_id', '=', 'p.id')
                    ->leftJoin('services as sv', 'posr.service_id', '=', 'sv.id')
                    ->leftJoin('patients as pat', function($join) {
                        $join->on('posr.patient_id', '=', 'pat.id')
                             ->orOn('posr.user_id', '=', 'pat.user_id');
                    })
                    ->leftJoin('users as pu', function($join) {
                        $join->on('pat.user_id', '=', 'pu.id')
                             ->orOn('posr.user_id', '=', 'pu.id');
                    })
                    ->leftJoin('hmos as h', function($join) {
                        $join->on('posr.hmo_id', '=', 'h.id')
                             ->orOn('pat.hmo_id', '=', 'h.id');
                    })
                    ->leftJoin('hmo_schemes as hs', 'h.hmo_scheme_id', '=', 'hs.id')
                    ->leftJoin('encounters as enc', 'posr.encounter_id', '=', 'enc.id')
                    ->leftJoin('users as doc', 'enc.doctor_id', '=', 'doc.id')
                    ->select('posr.*', 'p.product_name', 'sv.service_name', 'sv.category_id as service_cat_id', 'h.name as hmo_name', 'hs.name as scheme_name', 'hs.code as scheme_code', \DB::raw("CONCAT_WS(' ', pu.firstname, pu.surname) as patient_name"), 'pat.file_no', 'pat.hmo_no', \DB::raw("CONCAT_WS(' ', doc.firstname, doc.surname) as doctor_name"))
                    ->whereBetween('posr.created_at', [$startDate, $endDate]);

                if ($story === 'hmo-claims-by-provider') $q->where('posr.hmo_id', $key);
                elseif ($story === 'validation-status-aging') $q->where('posr.validation_status', $key);
                elseif ($story === 'scheme-breakdown') $q->where('h.hmo_scheme_id', $key);
                elseif ($story === 'coverage-mode-analysis') $q->where('posr.coverage_mode', $key);
                elseif ($story === 'remittance-vs-claims-matching') $q->where('posr.hmo_remittance_id', $key);
                elseif ($story === 'dispensing-revenue-attribution') $q->where('posr.product_id', $key);
                elseif ($story === 'store-dispensing-contribution') $q->where('posr.dispensed_from_store_id', $key);
                elseif ($story === 'service-category-revenue') $q->where('sv.category_id', $key);
                elseif ($story === 'doctor-referral-billing') $q->where('enc.doctor_id', $key);
                elseif ($story === 'service-vs-hmo-compliance') $q->where('posr.service_id', $key);

                $records = $q->orderByDesc('posr.created_at')->limit(500)->get();
                $rows = $records->map(fn($r) => [
                    'date' => \Carbon\Carbon::parse($r->created_at)->format('Y-m-d H:i'),
                    'code' => '<span class="badge bg-light text-dark border">' . e($r->request_code ?? ('#' . $r->id)) . '</span>',
                    'patient' => $this->renderPatientDetailsFromRow($r),
                    'item' => e($r->service_name ?? $r->product_name ?? 'Medical Item'),
                    'hmo_scheme' => e(($r->hmo_name ? $r->hmo_name . ' — ' : '') . ($r->scheme_name ?? 'Cash')),
                    'claims' => '<span class="font-weight-bold text-success">₦' . number_format((float)($r->claims_amount > 0 ? $r->claims_amount : $r->payable_amount), 2) . '</span>',
                    'payable' => '<span class="font-weight-bold text-primary">₦' . number_format((float)($r->payable_amount ?? 0), 2) . '</span>',
                    'status' => '<span class="badge ' . ($r->validation_status === 'approved' ? 'bg-success' : ($r->validation_status === 'awaiting_code' ? 'bg-danger' : 'bg-warning text-dark')) . '">' . e(ucfirst($r->validation_status ?? 'pending')) . '</span>',
                ]);
                $cards = [
                    ['label' => 'Total Request Lines', 'value' => $records->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Claims ₦', 'value' => '₦' . number_format((float)$records->sum(fn($r) => $r->claims_amount > 0 ? $r->claims_amount : $r->payable_amount), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Total Payable ₦', 'value' => '₦' . number_format((float)$records->sum('payable_amount'), 2), 'class' => 'bg-info text-white'],
                ];
                $headers = ['Date', 'Code', 'Patient', 'Item / Service', 'HMO & Scheme', 'Claims ₦', 'Payable ₦', 'Validation Status'];
                break;

            case 'dispenser-performance':
            case 'prescription-adaptation-audit':
            case 'drug-category-dispensing':
                $title = 'Pharmacy Dispensing Lines Details (' . e($story) . ')';
                $q = \DB::table('product_requests as pr')
                    ->join('products as p', 'pr.product_id', '=', 'p.id')
                    ->leftJoin('patients as pat', 'pr.patient_id', '=', 'pat.id')
                    ->leftJoin('users as pu', 'pat.user_id', '=', 'pu.id')
                    ->leftJoin('hmos as h', 'pat.hmo_id', '=', 'h.id')
                    ->leftJoin('hmo_schemes as hs', 'h.hmo_scheme_id', '=', 'hs.id')
                    ->leftJoin('users as du', 'pr.dispensed_by', '=', 'du.id')
                    ->select('pr.*', 'p.product_name', \DB::raw("CONCAT_WS(' ', pu.firstname, pu.surname) as patient_name"), 'pat.file_no', 'pat.hmo_no', 'h.name as hmo_name', 'hs.name as scheme_name', \DB::raw("CONCAT_WS(' ', du.firstname, du.surname) as dispenser_name"))
                    ->whereBetween('pr.created_at', [$startDate, $endDate]);

                if ($story === 'dispenser-performance') $q->where('pr.dispensed_by', $key);
                elseif ($story === 'prescription-adaptation-audit') $q->where('pr.adapted_from_product_id', $key);
                elseif ($story === 'drug-category-dispensing') $q->where('p.category_id', $key);

                $records = $q->orderByDesc('pr.created_at')->limit(500)->get();
                $rows = $records->map(fn($r) => [
                    'date' => \Carbon\Carbon::parse($r->created_at)->format('Y-m-d H:i'),
                    'product' => e($r->product_name),
                    'patient' => $this->renderPatientDetailsFromRow($r),
                    'qty' => '<span class="badge bg-info text-white">' . number_format($r->qty) . '</span>',
                    'dispenser' => e($r->dispenser_name ?? 'System'),
                    'adapted' => '<span class="badge ' . (($r->is_adapted || $r->adapted_from_product_id) ? 'bg-warning text-dark' : 'bg-light text-dark border') . '">' . (($r->is_adapted || $r->adapted_from_product_id) ? 'Adapted' : 'Normal') . '</span>',
                ]);
                $cards = [
                    ['label' => 'Total Lines', 'value' => $records->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Qty', 'value' => number_format($records->sum('qty')), 'class' => 'bg-success text-white'],
                ];
                $headers = ['Date & Time', 'Product Dispensed', 'Patient', 'Qty', 'Dispenser', 'Adaptation Status'];
                break;

            case 'damage-expiry-losses':
                $title = 'Damage & Expiry Losses Details (Key: ' . e(ucfirst($key)) . ')';
                if ($key === 'expired') {
                    $q = \DB::table('stock_batches as sb')
                        ->join('products as p', 'sb.product_id', '=', 'p.id')
                        ->join('stores as s', 'sb.store_id', '=', 's.id')
                        ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
                        ->select('sb.*', 'p.product_name', 's.store_name', \DB::raw('COALESCE(NULLIF(sb.cost_price,0), pp.pr_buy_price, 0) as calc_cost'))
                        ->where(function ($sq) {
                            $sq->where('s.distribution_role', 'central')
                                ->orWhere('s.store_type', 'warehouse');
                        })
                        ->where('sb.current_qty', '>', 0)
                        ->whereNotNull('sb.expiry_date')
                        ->where(function ($sq) use ($startDate, $endDate) {
                            $sq->whereBetween('sb.expiry_date', [$startDate, $endDate])
                                ->orWhere('sb.expiry_date', '<=', now());
                        });

                    $records = $q->orderByDesc('sb.created_at')->limit(500)->get();
                    $rows = $records->map(fn($r) => [
                        'batch' => '<span class="badge bg-light text-dark border font-weight-bold">' . e($r->batch_number ?? ('#' . $r->id)) . '</span>',
                        'product' => e($r->product_name),
                        'store' => e($r->store_name),
                        'qty' => '<span class="badge bg-danger text-white font-weight-bold">' . number_format($r->current_qty) . ' Units</span>',
                        'cost' => '<span class="font-weight-bold text-success">₦' . number_format($r->calc_cost, 2) . '</span>',
                        'total_loss' => '<span class="font-weight-bold text-danger">₦' . number_format($r->current_qty * $r->calc_cost, 2) . '</span>',
                        'expiry' => '<span class="badge bg-warning text-dark">' . ($r->expiry_date ? \Carbon\Carbon::parse($r->expiry_date)->format('Y-m-d') : 'Expired') . '</span>',
                    ]);
                    $cards = [
                        ['label' => 'Total Expired Batches', 'value' => $records->count(), 'class' => 'bg-primary text-white'],
                        ['label' => 'Total Expired Qty', 'value' => number_format($records->sum('current_qty')), 'class' => 'bg-info text-white'],
                        ['label' => 'Total Expired Stock Loss ₦', 'value' => '₦' . number_format($records->sum(fn($r) => $r->current_qty * $r->calc_cost), 2), 'class' => 'bg-danger text-white'],
                    ];
                    $headers = ['Batch #', 'Product', 'Store', 'Qty Expired', 'Unit Cost ₦', 'Total Loss ₦', 'Expiry Date'];
                } else {
                    $q = \DB::table('store_damages as sd')
                        ->join('products as p', 'sd.product_id', '=', 'p.id')
                        ->join('stores as s', 'sd.store_id', '=', 's.id')
                        ->select('sd.*', 'p.product_name', 's.store_name')
                        ->where('sd.damage_type', $key)
                        ->whereBetween('sd.discovered_date', [$startDate, $endDate]);

                    $records = $q->orderByDesc('sd.discovered_date')->limit(500)->get();
                    $rows = $records->map(fn($r) => [
                        'date' => \Carbon\Carbon::parse($r->discovered_date)->format('Y-m-d'),
                        'product' => e($r->product_name),
                        'store' => e($r->store_name),
                        'qty' => '<span class="badge bg-danger text-white">' . number_format($r->qty_damaged) . '</span>',
                        'value' => '<span class="font-weight-bold text-danger">₦' . number_format($r->total_value, 2) . '</span>',
                        'status' => '<span class="badge ' . ($r->status === 'pending' ? 'bg-warning text-dark' : 'bg-success') . '">' . ucfirst($r->status ?? 'recorded') . '</span>',
                    ]);
                    $cards = [
                        ['label' => 'Total Damaged Records', 'value' => $records->count(), 'class' => 'bg-primary text-white'],
                        ['label' => 'Total Loss Value ₦', 'value' => '₦' . number_format($records->sum('total_value'), 2), 'class' => 'bg-danger text-white'],
                    ];
                    $headers = ['Discovery Date', 'Product', 'Store', 'Qty Damaged', 'Total Value ₦', 'Status'];
                }
                break;

            case 'requisition-fulfillment':
                $store = \App\Models\Store::find($key);
                $storeName = $store->store_name ?? ('Store #' . $key);
                $title = 'Store Requisitions Drill-down: ' . $storeName;

                $q = \DB::table('store_requisitions as sr')
                    ->join('stores as fs', 'sr.from_store_id', '=', 'fs.id')
                    ->join('stores as ts', 'sr.to_store_id', '=', 'ts.id')
                    ->leftJoin('users as u', 'sr.requested_by', '=', 'u.id')
                    ->select('sr.*', 'fs.store_name as from_store_name', 'ts.store_name as to_store_name', \DB::raw("CONCAT_WS(' ', u.firstname, u.surname) as requester_name"))
                    ->where(function($sq) use ($key) {
                        $sq->where('sr.from_store_id', $key)->orWhere('sr.to_store_id', $key);
                    })
                    ->whereBetween('sr.created_at', [$startDate, $endDate]);

                $records = $q->orderByDesc('sr.created_at')->get();
                $rows = $records->map(fn($r) => [
                    'date' => \Carbon\Carbon::parse($r->created_at)->format('Y-m-d H:i'),
                    'code' => '<span class="badge bg-light text-dark border font-weight-bold">' . e($r->requisition_number ?? ('#' . $r->id)) . '</span>',
                    'flow' => e($r->from_store_name) . ' <i class="mdi mdi-arrow-right text-muted"></i> ' . e($r->to_store_name),
                    'requester' => e($r->requester_name ?? 'System'),
                    'status' => '<span class="badge ' . ($r->status === 'fulfilled' || $r->status === 'approved' ? 'bg-success' : ($r->status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark')) . '">' . e(ucfirst($r->status ?? 'pending')) . '</span>',
                ]);
                $cards = [
                    ['label' => 'Total Requisitions', 'value' => $records->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Fulfilled / Approved', 'value' => $records->whereIn('status', ['fulfilled', 'approved'])->count(), 'class' => 'bg-success text-white'],
                    ['label' => 'Pending / Processing', 'value' => $records->whereNotIn('status', ['fulfilled', 'approved', 'rejected'])->count(), 'class' => 'bg-warning text-dark'],
                ];
                $headers = ['Requisition Date', 'Requisition Code', 'Store Flow', 'Requested By', 'Status'];
                break;

            case 'requisition-items-audit':
                $prod = \App\Models\Product::find($key);
                $prodName = $prod->product_name ?? ('Product #' . $key);
                $title = 'Requisition Items Audit: ' . $prodName;

                $q = \DB::table('store_requisition_items as sri')
                    ->join('store_requisitions as sr', 'sri.store_requisition_id', '=', 'sr.id')
                    ->join('stores as fs', 'sr.from_store_id', '=', 'fs.id')
                    ->join('stores as ts', 'sr.to_store_id', '=', 'ts.id')
                    ->join('products as p', 'sri.product_id', '=', 'p.id')
                    ->select('sri.*', 'sr.requisition_number', 'sr.status as req_status', 'sr.created_at as req_date', 'fs.store_name as from_store_name', 'ts.store_name as to_store_name', 'p.product_name')
                    ->where('sri.product_id', $key)
                    ->whereBetween('sr.created_at', [$startDate, $endDate]);

                $records = $q->orderByDesc('sr.created_at')->get();
                $rows = $records->map(fn($r) => [
                    'date' => \Carbon\Carbon::parse($r->req_date)->format('Y-m-d H:i'),
                    'code' => '<span class="badge bg-light text-dark border">' . e($r->requisition_number ?? ('#' . $r->id)) . '</span>',
                    'flow' => e($r->from_store_name) . ' &rarr; ' . e($r->to_store_name),
                    'req_qty' => '<span class="badge bg-info text-white">' . number_format($r->requested_qty) . '</span>',
                    'app_qty' => '<span class="badge bg-primary text-white">' . number_format($r->approved_qty ?? 0) . '</span>',
                    'ful_qty' => '<span class="badge bg-success text-white">' . number_format($r->fulfilled_qty ?? 0) . '</span>',
                    'gap' => '<span class="badge ' . (($r->requested_qty - ($r->fulfilled_qty ?? 0)) > 0 ? 'bg-danger' : 'bg-light text-dark border') . '">' . number_format(max(0, $r->requested_qty - ($r->fulfilled_qty ?? 0))) . '</span>',
                ]);
                $cards = [
                    ['label' => 'Total Requisition Lines', 'value' => $records->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Requested Qty', 'value' => number_format($records->sum('requested_qty')), 'class' => 'bg-info text-white'],
                    ['label' => 'Total Fulfilled Qty', 'value' => number_format($records->sum('fulfilled_qty')), 'class' => 'bg-success text-white'],
                    ['label' => 'Unfulfilled Gap Qty', 'value' => number_format(max(0, $records->sum('requested_qty') - $records->sum('fulfilled_qty'))), 'class' => 'bg-danger text-white'],
                ];
                $headers = ['Requisition Date', 'Code', 'Store Flow', 'Requested Qty', 'Approved Qty', 'Fulfilled Qty', 'Unfulfilled Gap'];
                break;

            case 'ward-stock-movement':
            case 'daily-stock-movement-trend':
                $title = 'Stock Movements Ledger (' . e($key) . ')';
                $q = \DB::table('stock_batch_transactions as sbt')
                    ->join('stock_batches as sb', 'sbt.stock_batch_id', '=', 'sb.id')
                    ->join('products as p', 'sb.product_id', '=', 'p.id')
                    ->join('stores as s', 'sb.store_id', '=', 's.id')
                    ->leftJoin('users as u', 'sbt.performed_by', '=', 'u.id')
                    ->select('sbt.*', 'sb.batch_number', 'p.product_name', 's.store_name', \DB::raw("CONCAT_WS(' ', u.firstname, u.surname) as performer_name"));

                if ($story === 'ward-stock-movement') {
                    $q->where('sb.store_id', $key)->whereBetween('sbt.created_at', [$startDate, $endDate]);
                } else {
                    $q->whereDate('sbt.created_at', $key);
                }

                $records = $q->orderByDesc('sbt.created_at')->limit(500)->get();
                $rows = $records->map(fn($r) => [
                    'date' => \Carbon\Carbon::parse($r->created_at)->format('Y-m-d H:i'),
                    'product' => e($r->product_name),
                    'batch' => '<span class="badge bg-light text-dark border">' . e($r->batch_number ?? 'N/A') . '</span>',
                    'store' => e($r->store_name),
                    'type' => '<span class="badge ' . ($r->type === 'in' ? 'bg-success' : ($r->type === 'out' ? 'bg-danger' : 'bg-warning text-dark')) . '">' . e(strtoupper($r->type)) . '</span>',
                    'qty' => '<span class="font-weight-bold text-dark">' . number_format($r->qty) . '</span>',
                    'performer' => e($r->performer_name ?? 'System'),
                ]);
                $cards = [
                    ['label' => 'Total Transactions', 'value' => $records->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Movement Qty', 'value' => number_format($records->sum('qty')), 'class' => 'bg-info text-white'],
                ];
                $headers = ['Date & Time', 'Product', 'Batch #', 'Store', 'Movement Type', 'Qty', 'Recorded By'];
                break;

            case 'return-analysis':
            case 'return-damage-write-off':
                $title = 'Store & Patient Returns Details (Key: ' . e($key) . ')';
                $q = \DB::table('store_requisition_returns as srr')
                    ->join('products as p', 'srr.product_id', '=', 'p.id')
                    ->join('stores as s', 'srr.source_store_id', '=', 's.id')
                    ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
                    ->leftJoin('users as u', 'srr.created_by', '=', 'u.id')
                    ->select('srr.*', 'p.product_name', 's.store_name', \DB::raw("CONCAT_WS(' ', u.firstname, u.surname) as creator_name"), \DB::raw('COALESCE(pp.pr_buy_price, 0) as calc_cost'))
                    ->whereBetween('srr.created_at', [$startDate, $endDate]);

                if ($key) {
                    $q->where(function($sq) use ($key) {
                        $sq->where('srr.source_store_id', $key)->orWhere('srr.product_id', $key);
                    });
                }

                $records = $q->orderByDesc('srr.created_at')->limit(500)->get();
                $rows = $records->map(fn($r) => [
                    'date' => \Carbon\Carbon::parse($r->created_at)->format('Y-m-d H:i'),
                    'product' => e($r->product_name),
                    'store' => e($r->store_name),
                    'qty' => '<span class="badge bg-warning text-dark font-weight-bold">' . number_format($r->qty_returned) . ' Units</span>',
                    'cost' => '<span class="font-weight-bold text-danger">₦' . number_format($r->qty_returned * $r->calc_cost, 2) . '</span>',
                    'reason' => e($r->reason ?? 'Return to Main Store'),
                    'returned_by' => e($r->creator_name ?? 'Staff'),
                ]);
                $cards = [
                    ['label' => 'Total Return Entries', 'value' => $records->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Qty Returned', 'value' => number_format($records->sum('qty_returned')), 'class' => 'bg-warning text-dark'],
                    ['label' => 'Total Cost Value ₦', 'value' => '₦' . number_format($records->sum(fn($r) => $r->qty_returned * $r->calc_cost), 2), 'class' => 'bg-danger text-white'],
                ];
                $headers = ['Return Date', 'Product Item', 'Source Store', 'Returned Qty', 'Cost Value ₦', 'Reason / Notes', 'Returned By'];
                break;

            case 'batch-source-breakdown':
                $title = 'Stock Acquisition Source Details: ' . strtoupper(str_replace('_', ' ', $key));
                $q = \DB::table('stock_batches as sb')
                    ->join('products as p', 'sb.product_id', '=', 'p.id')
                    ->join('stores as s', 'sb.store_id', '=', 's.id')
                    ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
                    ->select('sb.*', 'p.product_name', 's.store_name', \DB::raw('COALESCE(NULLIF(sb.cost_price,0), pp.pr_buy_price, 0) as calc_cost'))
                    ->where('sb.source', $key)
                    ->whereBetween('sb.created_at', [$startDate, $endDate]);

                $records = $q->orderByDesc('sb.created_at')->get();
                $rows = $records->map(fn($r) => [
                    'batch' => '<span class="badge bg-light text-dark border font-weight-bold">' . e($r->batch_number ?? ('#' . $r->id)) . '</span>',
                    'product' => e($r->product_name),
                    'store' => e($r->store_name),
                    'received' => $r->received_date ? \Carbon\Carbon::parse($r->received_date)->format('Y-m-d') : 'N/A',
                    'qty' => '<span class="badge bg-info text-white">' . number_format($r->current_qty) . ' / ' . number_format($r->initial_qty) . '</span>',
                    'cost' => '<span class="font-weight-bold text-success">₦' . number_format($r->calc_cost, 2) . '</span>',
                    'value' => '<span class="font-weight-bold text-primary">₦' . number_format($r->initial_qty * $r->calc_cost, 2) . '</span>',
                ]);
                $cards = [
                    ['label' => 'Total Batches Created', 'value' => $records->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Initial Units', 'value' => number_format($records->sum('initial_qty')), 'class' => 'bg-info text-white'],
                    ['label' => 'Total Acquisition Value ₦', 'value' => '₦' . number_format($records->sum(fn($r) => $r->initial_qty * $r->calc_cost), 2), 'class' => 'bg-success text-white'],
                ];
                $headers = ['Batch #', 'Product', 'Store', 'Received Date', 'Qty (Current / Init)', 'Cost Price ₦', 'Acquisition Value ₦'];
                break;

            case 'unbilled-encounters':
                $title = 'Unbilled Encounters Leakage Details';
                $q = \DB::table('encounters as e')
                    ->leftJoin('product_or_service_requests as posr', 'e.id', '=', 'posr.encounter_id')
                    ->leftJoin('patients as pat', 'e.patient_id', '=', 'pat.id')
                    ->leftJoin('users as pu', 'pat.user_id', '=', 'pu.id')
                    ->leftJoin('hmos as h', 'pat.hmo_id', '=', 'h.id')
                    ->leftJoin('hmo_schemes as hs', 'h.hmo_scheme_id', '=', 'hs.id')
                    ->leftJoin('users as du', 'e.doctor_id', '=', 'du.id')
                    ->select('e.*', \DB::raw("CONCAT_WS(' ', pu.firstname, pu.surname) as patient_name"), 'pat.file_no', 'pat.hmo_no', 'h.name as hmo_name', 'hs.name as scheme_name', \DB::raw("CONCAT_WS(' ', du.firstname, du.surname) as doctor_name"))
                    ->whereNull('posr.id')
                    ->whereBetween('e.created_at', [$startDate, $endDate]);

                if ($key) {
                    $q->where('e.doctor_id', $key);
                }

                $records = $q->groupBy('e.id', 'e.created_at', 'pu.firstname', 'pu.surname', 'pat.file_no', 'pat.hmo_no', 'h.name', 'hs.name', 'du.firstname', 'du.surname')->orderByDesc('e.created_at')->limit(500)->get();
                $rows = $records->map(fn($r) => [
                    'date' => \Carbon\Carbon::parse($r->created_at)->format('Y-m-d H:i'),
                    'patient' => $this->renderPatientDetailsFromRow($r),
                    'doctor' => 'Dr. ' . e($r->doctor_name ?? 'Duty Doctor'),
                    'status' => '<span class="badge bg-danger">Zero Billing Lines</span>',
                ]);
                $cards = [
                    ['label' => 'Total Unbilled Encounters', 'value' => $records->count(), 'class' => 'bg-danger text-white'],
                ];
                $headers = ['Encounter Date', 'Patient Details', 'Attending Doctor', 'Audit Status'];
                break;

            case 'procedure-billing-audit':
                $title = 'Procedure & Theatre Billing Audit Details';
                $q = \DB::table('procedures as prc')
                    ->join('patients as pat', 'prc.patient_id', '=', 'pat.id')
                    ->leftJoin('users as pu', 'pat.user_id', '=', 'pu.id')
                    ->leftJoin('hmos as h', 'pat.hmo_id', '=', 'h.id')
                    ->leftJoin('hmo_schemes as hs', 'h.hmo_scheme_id', '=', 'hs.id')
                    ->leftJoin('services as sv', 'prc.service_id', '=', 'sv.id')
                    ->leftJoin('users as du', 'prc.requested_by', '=', 'du.id')
                    ->select('prc.*', 'sv.service_name', \DB::raw("CONCAT_WS(' ', pu.firstname, pu.surname) as patient_name"), 'pat.file_no', 'pat.hmo_no', 'h.name as hmo_name', 'hs.name as scheme_name', \DB::raw("CONCAT_WS(' ', du.firstname, du.surname) as doctor_name"))
                    ->whereBetween('prc.created_at', [$startDate, $endDate]);

                if ($key) {
                    $q->where('prc.procedure_status', $key);
                }

                $records = $q->orderByDesc('prc.created_at')->limit(500)->get();
                $rows = $records->map(fn($r) => [
                    'date' => \Carbon\Carbon::parse($r->created_at)->format('Y-m-d H:i'),
                    'patient' => $this->renderPatientDetailsFromRow($r),
                    'procedure' => e($r->service_name ?? 'Surgical Procedure'),
                    'requested_by' => 'Dr. ' . e($r->doctor_name ?? 'Surgeon'),
                    'status' => '<span class="badge ' . ($r->procedure_status === 'completed' ? 'bg-success' : 'bg-warning text-dark') . '">' . e(ucfirst($r->procedure_status ?? 'pending')) . '</span>',
                ]);
                $cards = [
                    ['label' => 'Total Procedures', 'value' => $records->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Completed Procedures', 'value' => $records->where('procedure_status', 'completed')->count(), 'class' => 'bg-success text-white'],
                ];
                $headers = ['Date', 'Patient Details', 'Procedure Item', 'Requested By', 'Procedure Status'];
                break;

            case 'consumption-vs-billing-gap':
            case 'ward-consumable-billing-kit':
                $title = 'Consumables & Utilization Audit Details';
                $q = \DB::table('stock_utilizations as su')
                    ->join('products as p', 'su.product_id', '=', 'p.id')
                    ->join('stores as s', 'su.store_id', '=', 's.id')
                    ->leftJoin('patients as pat', 'su.patient_id', '=', 'pat.id')
                    ->leftJoin('users as pu', 'pat.user_id', '=', 'pu.id')
                    ->leftJoin('hmos as h', 'pat.hmo_id', '=', 'h.id')
                    ->leftJoin('hmo_schemes as hs', 'h.hmo_scheme_id', '=', 'hs.id')
                    ->select('su.*', 'p.product_name', 's.store_name', \DB::raw("CONCAT_WS(' ', pu.firstname, pu.surname) as patient_name"), 'pat.file_no', 'pat.hmo_no', 'h.name as hmo_name', 'hs.name as scheme_name')
                    ->whereBetween('su.created_at', [$startDate, $endDate]);

                if ($key) {
                    $q->where(function($sq) use ($key) {
                        $sq->where('su.store_id', $key)->orWhere('su.product_id', $key);
                    });
                }

                $records = $q->orderByDesc('su.created_at')->limit(500)->get();
                $rows = $records->map(fn($r) => [
                    'date' => \Carbon\Carbon::parse($r->created_at)->format('Y-m-d H:i'),
                    'product' => e($r->product_name),
                    'store' => e($r->store_name),
                    'patient' => $this->renderPatientDetailsFromRow($r),
                    'consumed' => '<span class="badge bg-warning text-dark">' . number_format($r->qty) . ' Consumed</span>',
                    'billed' => '<span class="badge ' . ($r->is_billed ? 'bg-success' : 'bg-danger') . '">' . ($r->is_billed ? 'Billed' : 'Unbilled Gap') . '</span>',
                ]);
                $cards = [
                    ['label' => 'Total Utilization Entries', 'value' => $records->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Consumed Qty', 'value' => number_format($records->sum('qty')), 'class' => 'bg-warning text-dark'],
                    ['label' => 'Billed Lines', 'value' => $records->where('is_billed', 1)->count(), 'class' => 'bg-success text-white'],
                ];
                $headers = ['Date & Time', 'Product Item', 'Store', 'Patient', 'Consumed Qty', 'Billed Status'];
                break;
                $title = 'Audit Details (' . ucfirst(str_replace('-', ' ', $story)) . ')';
                $cards = [['label' => 'Records Found', 'value' => 0, 'class' => 'bg-secondary text-white']];
                $headers = ['Item', 'Details'];
                $rows = collect([]);
                break;
        }


        return response()->json([
            'title' => $title,
            'subtitle' => $subtitle,
            'cards' => $cards,
            'headers' => $headers,
            'rows' => $rows->values(),
        ]);
    }


    // =========================================================================
    // SERVER-SIDE DATATABLES AJAX HANDLERS (OPTIMIZED WITHOUT ->get())
    // =========================================================================

    public function receivablesDebtorsData(Request $request, $tab)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        if ($tab === 'staff-receivables') {
            $query = \App\Models\ProductOrServiceRequest::with(['staff.staff_profile.department', 'user', 'product', 'service'])
                ->whereNotNull('staff_user_id')
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\ProductRequest::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><small class="text-muted"><i class="mdi mdi-clock-outline"></i> ' . $r->created_at->format('h:i A') . ' (' . $r->created_at->diffForHumans() . ')</small>';
                })
                ->addColumn('staff_details', function($r) {
                    $name = $r->staff->name ?? 'Staff Member';
                    $dept = $r->staff->staff_profile->department->name ?? 'Department';
                    return '<div class="font-weight-bold text-dark">' . $name . '</div><small class="text-muted"><i class="mdi mdi-domain"></i> ' . $dept . '</small>';
                })
                ->addColumn('item_details', function($r) {
                    $item = $r->product->product_name ?? ($r->service->service_name ?? 'Staff Service/Consumable');
                    return '<div class="font-weight-bold">' . $item . '</div><small class="text-muted">Qty: ' . $r->qty . '</small>';
                })
                ->addColumn('amount_formatted', function($r) {
                    $amt = $r->payable_amount > 0 ? $r->payable_amount : $r->amount;
                    return '<span class="font-weight-bold text-danger">₦' . number_format($amt, 2) . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'ProductRequest');
                })
                ->rawColumns(['created_at', 'staff_details', 'item_details', 'amount_formatted', 'action'])
                ->make(true);
        }

        if ($tab === 'patient-debtors') {
            $query = \App\Models\PatientAccount::with(['patient.user', 'patient.hmo'])
                ->where('balance', '<', 0);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\PatientAccount::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->addColumn('patient_details', function($r) {
                    $phone = $r->patient->user->phone ?? ($r->patient->phone_no ?? 'N/A');
                    return $this->renderPatientDetails($r->patient, 'Unknown Patient') . '<small class="text-muted d-block"><i class="mdi mdi-phone"></i> ' . $phone . '</small>';
                })
                ->addColumn('balance_formatted', function($r) {
                    $bal = abs($r->balance);
                    return '<div class="font-weight-bold text-danger">Due: ₦' . number_format($bal, 2) . '</div>';
                })
                ->addColumn('coverage', function($r) {
                    return '<span class="badge bg-light text-dark border"><i class="mdi mdi-shield"></i> ' . ($r->patient->hmo->name ?? 'Private / Cash') . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'PatientAccount');
                })
                ->rawColumns(['patient_details', 'balance_formatted', 'coverage', 'action'])
                ->make(true);
        }

        if ($tab === 'corporate-retainership') {
            $query = \App\Models\OrganizationBill::with(['organization'])
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\OrganizationBill::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><small class="text-muted">' . $r->created_at->format('h:i A') . '</small>';
                })
                ->addColumn('org_details', function($r) {
                    return '<div class="font-weight-bold text-dark">' . ($r->organization->name ?? 'Corporate Retainership') . '</div><small class="text-muted">Code: ' . ($r->organization->code ?? 'N/A') . '</small>';
                })
                ->addColumn('financials', function($r) {
                    return '<div class="font-weight-bold text-success">Total: ₦' . number_format($r->total_amount, 2) . '</div><small class="text-danger">Due: ₦' . number_format($r->outstanding_amount, 2) . '</small>';
                })
                ->addColumn('status_badge', function($r) {
                    $cls = $r->status === 'paid' ? 'bg-success' : ($r->status === 'partial' ? 'bg-warning' : 'bg-danger');
                    return '<span class="badge ' . $cls . '">' . ucfirst($r->status ?? 'Unpaid') . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'OrganizationBill');
                })
                ->rawColumns(['created_at', 'org_details', 'financials', 'status_badge', 'action'])
                ->make(true);
        }

        if ($tab === 'unremitted-hmo') {
            $query = \App\Models\ProductOrServiceRequest::with(['patient.user', 'hmo', 'product', 'service'])
                ->whereNotNull('coverage_mode')
                ->where('validation_status', 'approved')
                ->whereNull('hmo_remittance_id')
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\ProductOrServiceRequest::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><small class="text-muted"><i class="mdi mdi-clock-outline"></i> ' . $r->created_at->format('h:i A') . '</small>';
                })
                ->addColumn('patient_hmo', function($r) {
                    return $this->renderPatientDetails($r->patient, 'Inpatient');
                })
                ->addColumn('claim_details', function($r) {
                    $item = $r->product->product_name ?? ($r->service->service_name ?? 'HMO Service');
                    return '<div class="font-weight-bold">₦' . number_format($r->claims_amount > 0 ? $r->claims_amount : $r->amount, 2) . '</div><small class="text-muted">' . $item . '</small>';
                })
                ->addColumn('aging_badge', function($r) {
                    $days = $r->created_at->diffInDays(now());
                    $cls = $days > 90 ? 'bg-danger' : ($days > 30 ? 'bg-warning text-dark' : 'bg-success');
                    return '<span class="badge ' . $cls . '">' . $days . ' Days Aging</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'ProductOrServiceRequest');
                })
                ->rawColumns(['created_at', 'patient_hmo', 'claim_details', 'aging_badge', 'action'])
                ->make(true);
        }
        
        if ($tab === 'payroll-deductions') {
            $query = \App\Models\User::whereHas('staffBills', function($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->withSum(['staffBills as total_outstanding' => function($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            }], 'outstanding_amount')
            ->withSum(['staffBills as total_amount' => function($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            }], 'total_amount');
            
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\User::class, 'receivables_debtors');

            return DataTables::eloquent($query)
                ->addColumn('staff_details', function($r) {
                    return '<div class="font-weight-bold text-dark">' . $r->name . '</div><small class="text-muted"><i class="mdi mdi-email"></i> ' . $r->email . '</small>';
                })
                ->addColumn('total_accrued', function($r) {
                    return '<span class="font-weight-bold">₦' . number_format($r->total_amount ?? 0, 2) . '</span>';
                })
                ->addColumn('total_outstanding', function($r) {
                    return '<span class="font-weight-bold text-danger">₦' . number_format($r->total_outstanding ?? 0, 2) . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'User');
                })
                ->rawColumns(['staff_details', 'total_accrued', 'total_outstanding', 'action'])
                ->make(true);
        }

        return response()->json(['error' => 'Invalid tab'], 400);
    }

    public function cashbookAccountingData(Request $request, $tab)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        if ($tab === 'payments') {
            $query = \App\Models\Payment::with(['staff_user.staff', 'patient.user', 'product_or_service_request.service.category', 'product_or_service_request.product.category'])
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\Payment::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><small class="text-muted"><i class="mdi mdi-clock-outline"></i> ' . $r->created_at->format('h:i A') . ' (' . $r->created_at->diffForHumans() . ')</small>';
                })
                ->addColumn('receipt_patient', function($r) {
                    $ref = '<div class="font-weight-bold text-dark mb-1">Ref: ' . ($r->receipt_no ?? ('#' . $r->id)) . '</div>';
                    return $ref . $this->renderPaymentEntityDetails($r, 'Walk-in / Cashier Deposit');
                })
                ->addColumn('item_details', function($r) {
                    return $this->renderPaymentItemDetails($r);
                })
                ->addColumn('method_badge', function($r) {
                    return '<span class="badge bg-success"><i class="mdi mdi-cash"></i> ' . strtoupper($r->payment_method ?? 'CASH') . '</span>';
                })
                ->addColumn('amount_formatted', function($r) {
                    return '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->total, 2) . '</span>';
                })
                ->addColumn('cashier_staff', function($r) {
                    return '<div class="font-weight-bold">' . ($r->staff_user->name ?? 'System Cashier') . '</div>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'Payment');
                })
                ->rawColumns(['created_at', 'receipt_patient', 'item_details', 'method_badge', 'amount_formatted', 'cashier_staff', 'action'])
                ->make(true);
        }

        if ($tab === 'ledger') {
            $query = \App\Models\Accounting\JournalEntryLine::with(['journalEntry', 'account'])
                ->whereHas('journalEntry', function($q) use ($startDate, $endDate) {
                    $q->whereBetween('entry_date', [$startDate, $endDate]);
                });
            $query = $this->applyMultidimensionalFilters($query, $request);

            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    $d = $r->journalEntry->entry_date ?? $r->created_at;
                    return '<div class="font-weight-bold">' . \Carbon\Carbon::parse($d)->format('M d, Y') . '</div>';
                })
                ->addColumn('account_details', function($r) {
                    $code = $r->account->code ?? 'ACC';
                    $name = $r->account->name ?? 'General Ledger Account';
                    return '<div class="font-weight-bold text-dark">' . $name . '</div><small class="text-muted">Code: ' . $code . '</small>';
                })
                ->addColumn('debit_formatted', function($r) {
                    return '<span class="font-weight-bold text-primary">' . ($r->debit > 0 ? ('₦' . number_format($r->debit, 2)) : '-') . '</span>';
                })
                ->addColumn('credit_formatted', function($r) {
                    return '<span class="font-weight-bold text-success">' . ($r->credit > 0 ? ('₦' . number_format($r->credit, 2)) : '-') . '</span>';
                })
                ->addColumn('narration', function($r) {
                    return '<small class="text-muted">' . \Illuminate\Support\Str::limit($r->narration ?? ($r->journalEntry->narration ?? 'N/A'), 45) . '</small>';
                })
                ->addColumn('action', function($r) {
                    return '<button class="btn btn-sm btn-outline-warning" onclick="openRaiseQueryModal(\'JournalEntryLine\', ' . $r->id . ')"><i class="mdi mdi-flag"></i> Flag</button>';
                })
                ->rawColumns(['created_at', 'account_details', 'debit_formatted', 'credit_formatted', 'narration', 'action'])
                ->make(true);
        }

        if ($tab === 'bank-recon') {
            $query = \App\Models\Accounting\BankReconciliation::with(['bank', 'account'])
                ->whereBetween('statement_date', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            return DataTables::eloquent($query)
                ->editColumn('statement_date', function($r) {
                    return '<div class="font-weight-bold">' . \Carbon\Carbon::parse($r->statement_date)->format('M d, Y') . '</div>';
                })
                ->addColumn('bank_account', function($r) {
                    return '<div class="font-weight-bold text-dark">' . ($r->bank->bank_name ?? 'Bank Account') . '</div><small class="text-muted">' . ($r->bank->account_number ?? 'N/A') . '</small>';
                })
                ->addColumn('balances', function($r) {
                    return '<div class="small">Ledger: ₦' . number_format($r->ending_balance_gl ?? 0, 2) . '<br>Bank: ₦' . number_format($r->ending_balance_bank ?? 0, 2) . '</div>';
                })
                ->addColumn('variance', function($r) {
                    $var = ($r->ending_balance_gl ?? 0) - ($r->ending_balance_bank ?? 0);
                    $cls = abs($var) > 0 ? 'text-danger font-weight-bold' : 'text-success';
                    return '<span class="' . $cls . '">₦' . number_format($var, 2) . '</span>';
                })
                ->addColumn('action', function($r) {
                    return '<button class="btn btn-sm btn-outline-warning" onclick="openRaiseQueryModal(\'BankReconciliation\', ' . $r->id . ')"><i class="mdi mdi-flag"></i> Flag</button>';
                })
                ->rawColumns(['statement_date', 'bank_account', 'balances', 'variance', 'action'])
                ->make(true);
        }

        if ($tab === 'expenses') {
            $query = \App\Models\Expense::whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\Expense::class, 'cashbook_accounting');
            
            return DataTables::eloquent($query)
                ->addColumn('date_ref', function($r) {
                    $d = $r->expense_date ? \Carbon\Carbon::parse($r->expense_date)->format('M d, Y') : $r->created_at->format('M d, Y');
                    return '<div class="font-weight-bold">' . $d . '</div><small class="text-muted border-top border-secondary pt-1 mt-1 d-block"><i class="mdi mdi-tag"></i> ' . ($r->expense_number ?? 'N/A') . '</small>';
                })
                ->addColumn('category', function($r) {
                    return '<div class="font-weight-bold text-dark">' . ($r->category ?? 'General') . '</div>';
                })
                ->addColumn('title_desc', function($r) {
                    return '<div class="font-weight-bold text-dark">' . \Illuminate\Support\Str::limit($r->title ?? 'N/A', 30) . '</div><small class="text-muted">' . \Illuminate\Support\Str::limit($r->description ?? 'N/A', 30) . '</small>';
                })
                ->addColumn('amount', function($r) {
                    return '<span class="font-weight-bold text-danger">₦' . number_format($r->amount ?? 0, 2) . '</span>';
                })
                ->addColumn('status_badge', function($r) {
                    $cls = $r->status === 'approved' ? 'bg-success' : ($r->status === 'voided' ? 'bg-danger' : 'bg-warning text-dark');
                    return '<span class="badge ' . $cls . '">' . ucfirst($r->status ?? 'Pending') . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'Expense');
                })
                ->rawColumns(['date_ref', 'category', 'title_desc', 'amount', 'status_badge', 'action'])
                ->make(true);
        }
        if ($tab === 'shift-audits') {
            $query = \App\Models\NursingShift::with(['user', 'auditor', 'querier', 'queryResolver'])
                ->where('context', 'billing')
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\NursingShift::class, 'cashbook_accounting');
            
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><small class="text-muted"><i class="mdi mdi-clock-outline"></i> ' . $r->created_at->format('h:i A') . '</small>';
                })
                ->addColumn('user_details', function($r) {
                    return '<div class="font-weight-bold text-dark">' . ($r->user->name ?? 'Unknown User') . '</div>';
                })
                ->addColumn('status_badge', function($r) {
                    $cls = $r->status === 'closed' ? 'bg-success' : 'bg-warning text-dark';
                    return '<span class="badge ' . $cls . '">' . ucfirst($r->status) . '</span>';
                })
                ->addColumn('expected_cash', function($r) {
                    return '<span class="font-weight-bold text-primary">₦' . number_format($r->expected_cash ?? 0, 2) . '</span>';
                })
                ->addColumn('remitted_cash', function($r) {
                    return '<span class="font-weight-bold text-success">₦' . number_format($r->remitted_cash ?? 0, 2) . '</span>';
                })
                ->addColumn('variance', function($r) {
                    $var = ($r->remitted_cash ?? 0) - ($r->expected_cash ?? 0);
                    $cls = $var < 0 ? 'text-danger font-weight-bold' : ($var > 0 ? 'text-success' : 'text-muted');
                    return '<span class="' . $cls . '">₦' . number_format($var, 2) . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'NursingShift');
                })
                ->rawColumns(['created_at', 'user_details', 'status_badge', 'expected_cash', 'remitted_cash', 'variance', 'action'])
                ->make(true);
        }

        return response()->json(['error' => 'Invalid tab'], 400);
    }

    public function consultationsClinicsData(Request $request, $tab)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        if ($tab === 'appointments') {
            $query = \App\Models\DoctorAppointment::with(['patient.user', 'clinic', 'doctor.user', 'doctorQueue'])
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\DoctorAppointment::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><small class="text-muted">' . $r->created_at->format('h:i A') . '</small>';
                })
                ->addColumn('patient_details', function($r) {
                    return $this->renderPatientDetails($r->patient, 'Patient');
                })
                ->addColumn('clinic_doctor', function($r) {
                    $c = $r->clinic->name ?? 'General Clinic';
                    $d = $r->doctor->user->name ?? ($r->doctor->name ?? 'Duty Doctor');
                    return '<div class="font-weight-bold text-dark">' . $c . '</div><small class="text-info"><i class="mdi mdi-doctor"></i> Dr. ' . $d . '</small>';
                })
                ->addColumn('status_badge', function($r) {
                    $cls = $r->status === 'completed' ? 'bg-success' : ($r->status === 'cancelled' ? 'bg-danger' : 'bg-warning text-dark');
                    return '<span class="badge ' . $cls . '">' . ucfirst($r->status ?? 'Pending') . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'DoctorAppointment');
                })
                ->rawColumns(['created_at', 'patient_details', 'clinic_doctor', 'status_badge', 'action'])
                ->make(true);
        }

        if ($tab === 'encounters') {
            $query = \App\Models\Encounter::with(['patient.user', 'doctor', 'queue'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select('*', \Illuminate\Support\Facades\DB::raw('TIMESTAMPDIFF(MINUTE, started_at, completed_at) as duration_minutes'));
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\Encounter::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    $st = $r->started_at ? \Carbon\Carbon::parse($r->started_at) : $r->created_at;
                    return '<div class="font-weight-bold">' . $st->format('M d, Y') . '</div><small class="text-muted"><i class="mdi mdi-clock-outline"></i> ' . $st->format('h:i A') . '</small>';
                })
                ->addColumn('patient_details', function($r) {
                    return $this->renderPatientDetails($r->patient, 'Inpatient');
                })
                ->addColumn('doctor_details', function($r) {
                    $docName = $r->doctor->fullname ?? ($r->doctor->name ?? 'Duty Doctor');
                    return '<div class="font-weight-bold text-dark">Dr. ' . $docName . '</div>';
                })
                ->addColumn('duration_badge', function($r) {
                    $dur = $r->duration_minutes !== null ? ($r->duration_minutes . ' mins') : '-';
                    return '<span class="badge bg-light text-dark border"><i class="mdi mdi-clock"></i> ' . $dur . '</span>';
                })
                ->addColumn('outcome_badge', function($r) {
                    $out = $r->outcome ?? ($r->completed ? 'Concluded' : 'Ongoing');
                    $cls = $out === 'discharged' ? 'bg-success' : ($out === 'admitted' ? 'bg-primary' : ($r->completed ? 'bg-info' : 'bg-warning text-dark'));
                    return '<span class="badge ' . $cls . '">' . ucfirst($out) . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'Encounter');
                })
                ->rawColumns(['created_at', 'patient_details', 'doctor_details', 'duration_badge', 'outcome_badge', 'action'])
                ->make(true);
        }

        return response()->json(['error' => 'Invalid tab'], 400);
    }

    public function admissionsDischargesData(Request $request, $tab)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        if ($tab === 'admissions') {
            $query = \App\Models\AdmissionRequest::with(['patient.user', 'ward', 'bed.ward', 'doctor'])
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\AdmissionRequest::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><small class="text-muted">' . $r->created_at->format('h:i A') . '</small>';
                })
                ->addColumn('patient_details', function($r) {
                    return $this->renderPatientDetails($r->patient, 'Inpatient');
                })
                ->addColumn('ward_bed', function($r) {
                    $w = $r->ward->name ?? ($r->bed->ward->name ?? 'Ward');
                    $b = $r->bed->name ?? 'Bed';
                    return '<div class="font-weight-bold text-dark">' . $w . '</div><small class="text-muted"><i class="mdi mdi-bed"></i> ' . $b . '</small>';
                })
                ->addColumn('status_badge', function($r) {
                    $cls = $r->status === 'admitted' ? 'bg-primary' : ($r->status === 'discharged' ? 'bg-success' : 'bg-warning text-dark');
                    return '<span class="badge ' . $cls . '">' . ucfirst($r->status) . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'AdmissionRequest');
                })
                ->rawColumns(['created_at', 'patient_details', 'ward_bed', 'status_badge', 'action'])
                ->make(true);
        }

        if ($tab === 'discharges') {
            $query = \App\Models\AdmissionRequest::with(['patient.user', 'ward', 'bed.ward'])
                ->whereBetween('discharge_date', [$startDate, $endDate])
                ->whereIn('status', ['discharged', 'cleared', 'absconded', 'dama']);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\AdmissionRequest::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('discharge_date', function($r) {
                    $d = $r->discharge_date ? \Carbon\Carbon::parse($r->discharge_date) : $r->updated_at;
                    return '<div class="font-weight-bold">' . $d->format('M d, Y') . '</div>';
                })
                ->addColumn('patient_details', function($r) {
                    return $this->renderPatientDetails($r->patient, 'Inpatient');
                })
                ->addColumn('ward_bed', function($r) {
                    $w = $r->ward->name ?? ($r->bed->ward->name ?? 'Ward');
                    return '<div class="font-weight-bold text-dark">' . $w . '</div>';
                })
                ->addColumn('stay_days', function($r) {
                    $days = $r->created_at->diffInDays($r->updated_at);
                    return '<span class="badge bg-light text-dark border">' . $days . ' Days</span>';
                })
                ->addColumn('status_badge', function($r) {
                    return '<span class="badge bg-success">' . ucfirst($r->status) . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'AdmissionRequest');
                })
                ->rawColumns(['discharge_date', 'patient_details', 'ward_bed', 'stay_days', 'status_badge', 'action'])
                ->make(true);
        }

        return response()->json(['error' => 'Invalid tab'], 400);
    }

    public function mainStoreStockData(Request $request, $tab)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        if ($tab === 'stock') {
            $query = \App\Models\StockBatch::with(['product.price', 'product.category', 'store'])
                ->whereHas('store', function($q) {
                    $q->where('store_name', 'LIKE', '%main%')
                      ->orWhere('store_type', 'warehouse')
                      ->orWhere('distribution_role', 'central');
                });
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\StockBatch::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->addColumn('product_details', function($r) {
                    $name = $r->product->product_name ?? 'Product SKU';
                    $code = $r->product->product_code ?? 'No Code';
                    $cat = $r->product->category->category_name ?? 'Category';
                    return '<div class="font-weight-bold text-dark">' . $name . '</div><small class="text-muted">Code: ' . $code . ' | Category: ' . $cat . '</small>';
                })
                ->addColumn('batch_expiry', function($r) {
                    $b = $r->batch_number ?? 'Batch';
                    $exp = $r->expiry_date ? \Carbon\Carbon::parse($r->expiry_date)->format('Y-m-d') : 'N/A';
                    return '<div class="font-weight-bold">' . $b . '</div><small class="text-muted">Exp: ' . $exp . '</small>';
                })
                ->addColumn('quantity_badge', function($r) {
                    return '<span class="badge bg-primary fs-6">' . $r->quantity . ' Base Units</span>';
                })
                ->addColumn('cost_valuation', function($r) {
                    $cost = $r->unit_cost ?? $r->cost_price ?? $r->product->cost_price ?? ($r->product->price->pr_buy_price ?? 0);
                    $val = $r->quantity * $cost;
                    return '<div class="font-weight-bold text-dark">₦' . number_format($val, 2) . '</div><small class="text-muted">Unit: ₦' . number_format($cost, 2) . '</small>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'StockBatch');
                })
                ->rawColumns(['product_details', 'batch_expiry', 'quantity_badge', 'cost_valuation', 'action'])
                ->make(true);
        }

        if ($tab === 'purchase-orders') {
            $query = \App\Models\PurchaseOrder::with(['supplier', 'creator'])
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\PurchaseOrder::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div>';
                })
                ->addColumn('po_supplier', function($r) {
                    $po = $r->po_number ?? ('PO #' . $r->id);
                    $sup = $r->supplier->company_name ?? ($r->supplier->name ?? 'Vendor Supplier');
                    return '<div class="font-weight-bold text-dark">' . $po . '</div><small class="text-muted"><i class="mdi mdi-truck"></i> ' . $sup . '</small>';
                })
                ->addColumn('amount_formatted', function($r) {
                    return '<span class="font-weight-bold text-success">₦' . number_format($r->total_amount, 2) . '</span>';
                })
                ->addColumn('status_badge', function($r) {
                    $cls = $r->status === 'fulfilled' ? 'bg-success' : ($r->status === 'pending' ? 'bg-warning text-dark' : 'bg-info');
                    return '<span class="badge ' . $cls . '">' . ucfirst($r->status ?? 'Draft') . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'PurchaseOrder');
                })
                ->rawColumns(['created_at', 'po_supplier', 'amount_formatted', 'status_badge', 'action'])
                ->make(true);
        }

        return response()->json(['error' => 'Invalid tab'], 400);
    }

    public function wardDeptStoresData(Request $request, $tab)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        if ($tab === 'stock') {
            $query = \App\Models\StockBatch::with(['product.price', 'product.category', 'store'])
                ->whereHas('store', function($q) {
                    $q->where('store_name', 'NOT LIKE', '%main%')
                      ->where('store_type', '!=', 'warehouse');
                });
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\StockBatch::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->addColumn('store_details', function($r) {
                    return '<span class="badge bg-light text-dark border"><i class="mdi mdi-store"></i> ' . ($r->store->store_name ?? 'Sub-Store') . '</span>';
                })
                ->addColumn('product_details', function($r) {
                    $name = $r->product->product_name ?? 'Sub-store Item';
                    return '<div class="font-weight-bold text-dark">' . $name . '</div>';
                })
                ->addColumn('quantity_badge', function($r) {
                    return '<span class="badge bg-info">' . $r->quantity . ' Base Units</span>';
                })
                ->addColumn('valuation', function($r) {
                    $cost = $r->unit_cost ?? $r->cost_price ?? $r->product->cost_price ?? ($r->product->price->pr_buy_price ?? 0);
                    return '<div class="font-weight-bold text-dark">₦' . number_format($r->quantity * $cost, 2) . '</div>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'StockBatch');
                })
                ->rawColumns(['store_details', 'product_details', 'quantity_badge', 'valuation', 'action'])
                ->make(true);
        }

        if ($tab === 'requisitions') {
            $query = \App\Models\StoreRequisition::with(['fromStore', 'toStore', 'requester', 'approver'])
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\StoreRequisition::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><small class="text-muted">' . $r->created_at->format('h:i A') . '</small>';
                })
                ->addColumn('stores_flow', function($r) {
                    $from = $r->fromStore->store_name ?? 'Requesting Store';
                    $to = $r->toStore->store_name ?? 'Main Warehouse';
                    return '<div class="font-weight-bold text-dark">' . $from . '</div><small class="text-muted"><i class="mdi mdi-arrow-right"></i> Supplied by: ' . $to . '</small>';
                })
                ->addColumn('status_badge', function($r) {
                    $cls = $r->status === 'fulfilled' ? 'bg-success' : ($r->status === 'pending' ? 'bg-warning text-dark' : 'bg-info');
                    return '<span class="badge ' . $cls . '">' . ucfirst($r->status) . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'StoreRequisition');
                })
                ->rawColumns(['created_at', 'stores_flow', 'status_badge', 'action'])
                ->make(true);
        }

        return response()->json(['error' => 'Invalid tab'], 400);
    }

    public function storeUtilizationRevenueData(Request $request, $tab)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        if ($tab === 'transactions') {
            $query = \App\Models\StockBatchTransaction::with(['stockBatch.product.category', 'performer'])
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\StockBatchTransaction::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($t) {
                    $date = $t->created_at->format('M d, Y');
                    $time = $t->created_at->format('h:i A');
                    $human = $t->created_at->diffForHumans();
                    return '<div class="font-weight-bold">' . $date . '</div><small class="text-muted"><i class="mdi mdi-clock-outline"></i> ' . $time . ' (' . $human . ')</small>';
                })
                ->addColumn('product', function($t) {
                    $pName = $t->stockBatch->product->product_name ?? 'Item';
                    $pCode = $t->stockBatch->product->product_code ?? 'No Code';
                    $catName = $t->stockBatch->product->category->category_name ?? 'Category';
                    return '<div class="font-weight-bold text-dark">' . $pName . '</div><div class="small mt-1"><span class="text-muted border-right pr-1 mr-1">Code: ' . $pCode . '</span><span class="text-info"><i class="mdi mdi-tag"></i> ' . $catName . '</span></div>';
                })
                ->addColumn('batch', function($t) {
                    $bNum = $t->stockBatch->batch_number ?? 'N/A';
                    $exp = $t->stockBatch->expiry_date;
                    $expHtml = $exp ? ('<div class="small mt-1"><span class="text-muted">Exp: </span><span class="text-danger">' . \Carbon\Carbon::parse($exp)->format('Y-m-d') . '</span></div>') : '';
                    return '<div class="font-weight-bold">' . $bNum . '</div>' . $expHtml;
                })
                ->editColumn('type', function($t) {
                    $cls = in_array($t->type, ['in', 'transfer_in', 'return']) ? 'bg-success' : (in_array($t->type, ['out', 'transfer_out', 'expired', 'damaged']) ? 'bg-danger' : 'bg-info');
                    return '<span class="badge ' . $cls . '">' . strtoupper(str_replace('_', ' ', $t->type)) . '</span>';
                })
                ->addColumn('qty_formatted', function($t) {
                    $sign = $t->qty < 0 ? '-' : '+';
                    $cls = $t->qty < 0 ? 'text-danger font-weight-bold' : 'text-success font-weight-bold';
                    return '<span class="' . $cls . '" style="font-size: 1.1em;">' . $sign . abs($t->qty) . '</span><div class="small text-muted">Bal: ' . ($t->balance_after ?? '-') . '</div>';
                })
                ->addColumn('reference', function($t) {
                    return '<small class="text-muted">' . \Illuminate\Support\Str::limit($t->notes ?? ($t->reference_type ?? 'Txn'), 35) . '</small>';
                })
                ->addColumn('performer', function($t) {
                    return '<div class="font-weight-bold text-dark">' . ($t->performer->fullname ?? ($t->performer->name ?? 'System Staff')) . '</div>';
                })
                ->rawColumns(['created_at', 'product', 'batch', 'type', 'qty_formatted', 'reference', 'performer'])
                ->make(true);
        }

        if ($tab === 'revenue') {
            $query = \App\Models\ProductOrServiceRequest::with(['product', 'dispensedFromStore'])
                ->whereNotNull('product_id')
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\StockBatchTransaction::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><small class="text-muted">' . $r->created_at->format('h:i A') . '</small>';
                })
                ->addColumn('product_name', function($r) {
                    return '<div class="font-weight-bold text-dark">' . ($r->product->product_name ?? 'Product Item') . '</div>';
                })
                ->addColumn('store_name', function($r) {
                    return '<span class="badge bg-light text-dark border"><i class="mdi mdi-store"></i> ' . ($r->dispensedFromStore->store_name ?? 'Store') . '</span>';
                })
                ->addColumn('revenue_amount', function($r) {
                    $amt = $r->payable_amount > 0 ? $r->payable_amount : $r->amount;
                    return '<span class="font-weight-bold text-success">₦' . number_format($amt, 2) . '</span>';
                })
                ->addColumn('coverage', function($r) {
                    $mode = strtoupper($r->coverage_mode ?? 'CASH');
                    return '<span class="badge bg-' . ($mode === 'HMO' ? 'info' : 'success') . '">' . $mode . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'StockBatchTransaction');
                })
                ->rawColumns(['created_at', 'product_name', 'store_name', 'revenue_amount', 'coverage', 'action'])
                ->make(true);
        }

        return response()->json(['error' => 'Invalid tab'], 400);
    }

    public function hmoNhisData(Request $request, $tab)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        if ($tab === 'services') {
            $query = \App\Models\ProductOrServiceRequest::with(['patient.user', 'hmo', 'product', 'service'])
                ->where('coverage_mode', 'hmo')
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\ProductOrServiceRequest::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><small class="text-muted">' . $r->created_at->format('h:i A') . '</small>';
                })
                ->addColumn('patient_hmo', function($r) {
                    return $this->renderPatientDetails($r->patient, 'Patient');
                })
                ->addColumn('service_item', function($r) {
                    $item = $r->product->product_name ?? ($r->service->service_name ?? 'HMO Item');
                    return '<div class="font-weight-bold">' . $item . '</div>';
                })
                ->addColumn('claims_amount_formatted', function($r) {
                    return '<span class="font-weight-bold text-success">₦' . number_format($r->claims_amount > 0 ? $r->claims_amount : $r->amount, 2) . '</span>';
                })
                ->addColumn('validation_status_badge', function($r) {
                    $st = $r->validation_status ?? 'pending';
                    $cls = $st === 'validated' ? 'bg-success' : ($st === 'rejected' ? 'bg-danger' : 'bg-warning text-dark');
                    return '<span class="badge ' . $cls . '">' . ucfirst($st) . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'ProductOrServiceRequest');
                })
                ->rawColumns(['created_at', 'patient_hmo', 'service_item', 'claims_amount_formatted', 'validation_status_badge', 'action'])
                ->make(true);
        }

        if ($tab === 'claims') {
            $query = \App\Models\HmoClaim::with(['hmo', 'patient.user'])
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\HmoClaim::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div>';
                })
                ->addColumn('hmo_details', function($r) {
                    return '<div class="font-weight-bold text-dark">' . ($r->hmo->name ?? 'HMO Provider') . '</div>';
                })
                ->addColumn('claim_amount_formatted', function($r) {
                    return '<span class="font-weight-bold text-success">₦' . number_format($r->claims_amount ?? 0, 2) . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'HmoClaim');
                })
                ->rawColumns(['created_at', 'hmo_details', 'claim_amount_formatted', 'action'])
                ->make(true);
        }

        if ($tab === 'remittances') {
            $query = \App\Models\HmoRemittance::with('hmo')
                ->whereBetween('payment_date', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\HmoRemittance::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('payment_date', function($r) {
                    return '<div class="font-weight-bold">' . \Carbon\Carbon::parse($r->payment_date)->format('M d, Y') . '</div>';
                })
                ->addColumn('hmo_details', function($r) {
                    return '<div class="font-weight-bold text-dark">' . ($r->hmo->name ?? 'HMO Provider') . '</div>';
                })
                ->addColumn('amount_formatted', function($r) {
                    return '<span class="font-weight-bold text-success">₦' . number_format($r->amount ?? 0, 2) . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'HmoRemittance');
                })
                ->rawColumns(['payment_date', 'hmo_details', 'amount_formatted', 'action'])
                ->make(true);
        }

        return response()->json(['error' => 'Invalid tab'], 400);
    }

    public function serviceRegistersBillingData(Request $request, $tab)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        if ($tab === 'clinical-services' || $tab === 'services') {
            $query = \App\Models\Encounter::with(['patient.user', 'doctor'])
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\Encounter::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><small class="text-muted">' . $r->created_at->format('h:i A') . '</small>';
                })
                ->addColumn('patient_details', function($r) {
                    return $this->renderPatientDetails($r->patient, 'Patient');
                })
                ->addColumn('doctor_details', function($r) {
                    return '<div class="font-weight-bold text-dark">Dr. ' . ($r->doctor->fullname ?? ($r->doctor->name ?? 'Doctor')) . '</div>';
                })
                ->addColumn('doctor_name', function($r) {
                    return '<div class="font-weight-bold text-dark">Dr. ' . ($r->doctor->fullname ?? ($r->doctor->name ?? 'Doctor')) . '</div>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'Encounter');
                })
                ->rawColumns(['created_at', 'patient_details', 'doctor_details', 'doctor_name', 'action'])
                ->make(true);
        }

        if ($tab === 'billed-services' || $tab === 'billing') {
            $query = \App\Models\ProductOrServiceRequest::with(['user', 'patient.user', 'service.category'])
                ->whereNotNull('service_id')
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\ProductOrServiceRequest::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><small class="text-muted">' . $r->created_at->format('h:i A') . '</small>';
                })
                ->addColumn('patient_details', function($r) {
                    $p = $r->patient ? $this->renderPatientDetails($r->patient, 'Patient') : '<div class="font-weight-bold text-dark"><i class="mdi mdi-account"></i> ' . ($r->user->name ?? 'Patient') . '</div>';
                    return $p;
                })
                ->addColumn('service_details', function($r) {
                    $name = $r->service->service_name ?? 'Clinical Service';
                    $cat = $r->service->category->category_name ?? 'General Category';
                    return '<div class="font-weight-bold text-dark">' . $name . '</div><small class="text-info"><i class="mdi mdi-tag"></i> ' . $cat . '</small>';
                })
                ->addColumn('amount_formatted', function($r) {
                    $amt = $r->payable_amount > 0 ? $r->payable_amount : $r->amount;
                    return '<span class="font-weight-bold text-success">₦' . number_format($amt, 2) . '</span>';
                })
                ->addColumn('total_formatted', function($r) {
                    $amt = $r->payable_amount > 0 ? $r->payable_amount : $r->amount;
                    return '<span class="font-weight-bold text-success">₦' . number_format($amt, 2) . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'ProductOrServiceRequest');
                })
                ->rawColumns(['created_at', 'patient_details', 'service_details', 'amount_formatted', 'total_formatted', 'action'])
                ->make(true);
        }

        if ($tab === 'procedures') {
            $query = \App\Models\Procedure::with(['patient.user', 'procedureDefinition.procedureCategory', 'service'])
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\Procedure::class, 'service_registers_billing');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><small class="text-muted">' . $r->created_at->format('h:i A') . '</small>';
                })
                ->addColumn('date_cat', function($r) {
                    $cat = $r->procedureDefinition->procedureCategory->name ?? 'General Procedure';
                    $isSurgical = $r->procedureDefinition->is_surgical ?? false;
                    $surgicalBadge = $isSurgical ? ' <span class="badge bg-danger">Surgical</span>' : '';
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><div class="mt-1"><small class="text-info"><i class="mdi mdi-tag"></i> ' . $cat . '</small>' . $surgicalBadge . '</div>';
                })
                ->addColumn('patient_details', function($r) {
                    return $this->renderPatientDetails($r->patient, 'Patient');
                })
                ->addColumn('proc_name', function($r) {
                    $name = $r->is_free_form ? $r->free_form_name : ($r->procedureDefinition->name ?? ($r->service->service_name ?? 'Procedure'));
                    return '<div class="font-weight-bold text-dark">' . $name . '</div>';
                })
                ->addColumn('procedure_name', function($r) {
                    $name = $r->is_free_form ? $r->free_form_name : ($r->procedureDefinition->name ?? ($r->service->service_name ?? 'Procedure'));
                    return '<div class="font-weight-bold text-dark">' . $name . '</div>';
                })
                ->addColumn('status_badge', function($r) {
                    $cls = $r->procedure_status === 'completed' ? 'bg-success' : 'bg-warning text-dark';
                    return '<span class="badge ' . $cls . '">' . ucfirst($r->procedure_status ?? ($r->status ?? 'Pending')) . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'Procedure');
                })
                ->rawColumns(['created_at', 'date_cat', 'patient_details', 'proc_name', 'procedure_name', 'status_badge', 'action'])
                ->make(true);
        }

        if ($tab === 'maternity') {
            $query = \App\Models\MaternityEnrollment::with(['patient.user'])
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\MaternityEnrollment::class, 'service_registers_billing');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><small class="text-muted">' . $r->created_at->format('h:i A') . '</small>';
                })
                ->addColumn('enrollment_date', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div>';
                })
                ->addColumn('patient_details', function($r) {
                    return $this->renderPatientDetails($r->patient, 'Patient');
                })
                ->addColumn('edd_gestation', function($r) {
                    return '<div class="font-weight-bold text-dark">' . ($r->edd ? \Carbon\Carbon::parse($r->edd)->format('M d, Y') : 'N/A') . '</div><small class="text-muted">Gestation: ' . ($r->gestation_weeks ?? 'N/A') . ' weeks</small>';
                })
                ->addColumn('status_badge', function($r) {
                    $cls = $r->status === 'active' ? 'bg-success' : 'bg-secondary';
                    return '<span class="badge ' . $cls . '">' . ucfirst($r->status ?? 'Unknown') . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'MaternityEnrollment');
                })
                ->rawColumns(['created_at', 'enrollment_date', 'patient_details', 'edd_gestation', 'status_badge', 'action'])
                ->make(true);
        }

        return response()->json(['error' => 'Invalid tab'], 400);
    }

    public function pharmacyMortuaryData(Request $request, $tab)
    {
        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        if ($tab === 'pharmacy-dispense') {
            $query = \App\Models\ProductRequest::with(['product.price', 'dispensedFromStore', 'doctor', 'patient.user'])
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\ProductRequest::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><small class="text-muted"><i class="mdi mdi-clock-outline"></i> ' . $r->created_at->format('h:i A') . ' (' . $r->created_at->diffForHumans() . ')</small>';
                })
                ->addColumn('patient_doctor', function($r) {
                    $d = $r->doctor->fullname ?? ($r->doctor->name ?? 'Prescribing Doctor');
                    return $this->renderPatientDetails($r->patient, 'Inpatient') . '<small class="text-info mt-1 d-block"><i class="mdi mdi-doctor"></i> Dr. ' . $d . '</small>';
                })
                ->addColumn('product_store', function($r) {
                    $prod = $r->product->product_name ?? 'Rx Medication';
                    $st = $r->dispensedFromStore->store_name ?? 'Central Pharmacy';
                    return '<div class="font-weight-bold text-dark">' . $prod . '</div><small class="text-muted"><i class="mdi mdi-store"></i> Dispensed: ' . $st . '</small>';
                })
                ->addColumn('classification_badge', function($r) {
                    return '<span class="badge bg-success"><i class="mdi mdi-pill"></i> Pharmacy Dispense</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'ProductRequest');
                })
                ->rawColumns(['created_at', 'patient_doctor', 'product_store', 'classification_badge', 'action'])
                ->make(true);
        }

        if ($tab === 'ward-direct-billing') {
            $query = \App\Models\ProductOrServiceRequest::with(['product.price', 'dispensedFromStore', 'user'])
                ->whereNotNull('product_id')
                ->whereHas('dispensedFromStore', function($q) {
                    $q->where('store_type', 'ward')
                      ->orWhere('distribution_role', 'ward')
                      ->orWhere('store_name', 'LIKE', '%ward%');
                })
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\ProductOrServiceRequest::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('created_at', function($r) {
                    return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><small class="text-muted"><i class="mdi mdi-clock-outline"></i> ' . $r->created_at->format('h:i A') . ' (' . $r->created_at->diffForHumans() . ')</small>';
                })
                ->addColumn('patient_details', function($r) {
                    $p = $r->user->name ?? 'Inpatient';
                    return '<div class="font-weight-bold text-dark">' . $p . '</div>';
                })
                ->addColumn('product_store', function($r) {
                    $prod = $r->product->product_name ?? 'Consumable Item';
                    $st = $r->dispensedFromStore->store_name ?? 'Ward Sub-store';
                    return '<div class="font-weight-bold text-dark">' . $prod . '</div><small class="text-muted"><i class="mdi mdi-store-24-hour"></i> ' . $st . '</small>';
                })
                ->addColumn('amount_formatted', function($r) {
                    $amt = $r->payable_amount > 0 ? $r->payable_amount : $r->amount;
                    return '<span class="font-weight-bold text-primary">₦' . number_format($amt, 2) . '</span>';
                })
                ->addColumn('classification_badge', function($r) {
                    return '<span class="badge bg-primary"><i class="mdi mdi-beaker"></i> Direct Billing</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'ProductOrServiceRequest');
                })
                ->rawColumns(['created_at', 'patient_details', 'product_store', 'amount_formatted', 'classification_badge', 'action'])
                ->make(true);
        }

        if ($tab === 'mortuary') {
            $query = \App\Models\MorgueAdmission::with(['patient.user'])
                ->whereBetween('created_at', [$startDate, $endDate]);
            $query = $this->applyMultidimensionalFilters($query, $request);

            $this->interceptBulkStamp($query, $request, \App\Models\MorgueAdmission::class, 'zone_dynamic');
            return DataTables::eloquent($query)
                ->editColumn('arrival_time', function($r) {
                    $arr = $r->arrival_time ? \Carbon\Carbon::parse($r->arrival_time) : $r->created_at;
                    return '<div class="font-weight-bold">' . $arr->format('M d, Y') . '</div><small class="text-muted">' . $arr->format('h:i A') . '</small>';
                })
                ->addColumn('deceased_details', function($r) {
                    return $this->renderPatientDetails($r->patient, 'Deceased Patient / Non-Patient');
                })
                ->addColumn('location', function($r) {
                    return '<span class="badge bg-secondary">Fridge: ' . ($r->fridge_number ?? 'N/A') . ' | Tray: ' . ($r->tray_number ?? 'N/A') . '</span>';
                })
                ->addColumn('status_badge', function($r) {
                    $cls = $r->status === 'released' ? 'bg-success' : 'bg-warning text-dark';
                    return '<span class="badge ' . $cls . '">' . ucfirst($r->status ?? 'Admitted') . '</span>';
                })
                ->addColumn('action', function($r) {
                    return $this->renderAuditAction($r, 'MorgueAdmission');
                })
                ->rawColumns(['arrival_time', 'deceased_details', 'location', 'status_badge', 'action'])
                ->make(true);
        }

        return response()->json(['error' => 'Invalid tab'], 400);
    }

    // =====================================================================
    // PAGE 5 — MAIN STORE STOCK — Story Data
    // =====================================================================
    public function mainStoreStoryData(Request $request, $story)
    {
        [$startDate, $endDate] = $this->parseAuditPeriod($request);

        switch ($story) {

            case 'batch-valuation':
                $rows = \DB::table('stock_batches as sb')
                    ->join('stores as s', 'sb.store_id', '=', 's.id')
                    ->join('products as p', 'sb.product_id', '=', 'p.id')
                    ->leftJoin('product_categories as pc', 'p.category_id', '=', 'pc.id')
                    ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
                    ->select(
                        'pc.id as category_id',
                        'pc.category_name',
                        \DB::raw('COUNT(sb.id) as batch_count'),
                        \DB::raw('SUM(sb.current_qty) as total_units'),
                        \DB::raw('SUM(sb.current_qty * COALESCE(NULLIF(sb.cost_price,0), pp.pr_buy_price, 0)) as total_value'),
                        \DB::raw('SUM(CASE WHEN sb.expiry_date < NOW() AND sb.expiry_date IS NOT NULL AND sb.current_qty > 0 THEN sb.current_qty * COALESCE(NULLIF(sb.cost_price,0), pp.pr_buy_price, 0) ELSE 0 END) as expired_value')
                    )
                    ->where(function ($q) {
                        $q->where('s.distribution_role', 'central')
                            ->orWhere('s.store_type', 'warehouse');
                    })
                    ->where('sb.current_qty', '>', 0)
                    ->groupBy('pc.id', 'pc.category_name')
                    ->orderByDesc('total_value')
                    ->get();

                $totalValue = $rows->sum('total_value');
                $expiredValue = $rows->sum('expired_value');

                $formattedRows = $rows->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="main-store" data-story="batch-valuation" data-key="' . e($r->category_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'category' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-tag text-primary"></i> ' . e($r->category_name ?? 'Uncategorised') . '</div>',
                    'batch_count' => '<span class="badge bg-light text-dark border font-weight-bold px-2 py-1">' . (int)$r->batch_count . ' Batches</span>',
                    'total_units' => '<span class="badge bg-info text-white px-2 py-1">' . number_format($r->total_units) . ' Units</span>',
                    'total_value' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->total_value, 2) . '</span>',
                    'expired_value' => '<span class="font-weight-bold text-danger">₦' . number_format($r->expired_value, 2) . '</span>',
                ]);

                $cards = [
                    ['label' => 'Total Stock Value', 'value' => '₦' . number_format($totalValue, 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Expired Stock Value', 'value' => '₦' . number_format($expiredValue, 2), 'class' => 'bg-danger text-white'],
                    ['label' => 'Categories Stocked', 'value' => $rows->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Units on Hand', 'value' => number_format($rows->sum('total_units')), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Product Category', 'Batches', 'Total Units', 'Total Value ₦', 'Expired Value ₦']]);

            case 'procurement-performance':
                $rows = \DB::table('purchase_order_items as poi')
                    ->join('purchase_orders as po', 'poi.purchase_order_id', '=', 'po.id')
                    ->leftJoin('suppliers as sup', 'po.supplier_id', '=', 'sup.id')
                    ->leftJoin('products as p', 'poi.product_id', '=', 'p.id')
                    ->select(
                        'po.id as po_id',
                        'po.po_number',
                        \DB::raw('COALESCE(sup.company_name, sup.contact_person, "Unknown Supplier") as supplier_name'),
                        \DB::raw('COUNT(poi.id) as item_lines'),
                        \DB::raw('SUM(poi.received_qty) as total_received_qty'),
                        \DB::raw('SUM(poi.received_qty * poi.actual_unit_cost) as received_value'),
                        \DB::raw('SUM(poi.received_qty * poi.unit_cost) as system_value'),
                        \DB::raw('SUM(poi.received_qty * (poi.actual_unit_cost - poi.unit_cost)) as variance')
                    )
                    ->whereBetween('po.created_at', [$startDate, $endDate])
                    ->groupBy('po.id', 'po.po_number', 'supplier_name')
                    ->orderByDesc('received_value')
                    ->get();

                $formattedRows = $rows->map(function ($r) {
                    $varClass = $r->variance > 0 ? 'text-danger' : ($r->variance < 0 ? 'text-success' : 'text-muted');
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="main-store" data-story="procurement-performance" data-key="' . e($r->po_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'po' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-file-document text-primary"></i> ' . e($r->po_number ?? 'PO#' . $r->po_id) . '</div><small class="text-muted"><i class="mdi mdi-truck"></i> ' . e($r->supplier_name) . '</small>',
                        'item_lines' => '<span class="badge bg-light text-dark border">' . (int)$r->item_lines . ' Lines</span>',
                        'received_qty' => '<span class="badge bg-info text-white">' . number_format($r->total_received_qty) . ' Units</span>',
                        'received_value' => '<span class="font-weight-bold text-success">₦' . number_format($r->received_value, 2) . '</span>',
                        'system_value' => '<span class="font-weight-bold text-dark">₦' . number_format($r->system_value, 2) . '</span>',
                        'variance' => '<span class="font-weight-bold ' . $varClass . '">₦' . number_format($r->variance, 2) . '</span>',
                    ];
                });

                $totalVariance = $rows->sum('variance');
                $cards = [
                    ['label' => 'Total Received Value', 'value' => '₦' . number_format($rows->sum('received_value'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Total Price Variance', 'value' => '₦' . number_format(abs($totalVariance), 2), 'class' => $totalVariance > 0 ? 'bg-danger text-white' : 'bg-success text-white'],
                    ['label' => 'POs Processed', 'value' => $rows->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Lines Received', 'value' => number_format($rows->sum('item_lines')), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Purchase Order', 'Line Items', 'Received Qty', 'Received Value ₦', 'System Cost ₦', 'Variance ₦']]);

            case 'supplier-analysis':
                $rows = \DB::table('purchase_orders as po')
                    ->join('suppliers as sup', 'po.supplier_id', '=', 'sup.id')
                    ->select(
                        'sup.id as supplier_id',
                        \DB::raw('COALESCE(sup.company_name, sup.contact_person, "Unknown") as supplier_name'),
                        \DB::raw('COUNT(po.id) as po_count'),
                        \DB::raw('SUM(po.total_amount) as total_ordered'),
                        \DB::raw('SUM(po.amount_paid) as total_paid'),
                        \DB::raw('SUM(po.total_amount - po.amount_paid) as outstanding'),
                        \DB::raw('SUM(CASE WHEN po.payment_status NOT IN ("paid","fully_paid") THEN 1 ELSE 0 END) as unpaid_pos')
                    )
                    ->whereBetween('po.created_at', [$startDate, $endDate])
                    ->groupBy('sup.id', 'supplier_name')
                    ->orderByDesc('outstanding')
                    ->get();

                $formattedRows = $rows->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="main-store" data-story="supplier-analysis" data-key="' . e($r->supplier_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'supplier' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-truck text-primary"></i> ' . e($r->supplier_name) . '</div>',
                    'po_count' => '<span class="badge bg-light text-dark border">' . (int)$r->po_count . ' POs</span>',
                    'total_ordered' => '<span class="font-weight-bold text-dark">₦' . number_format($r->total_ordered, 2) . '</span>',
                    'total_paid' => '<span class="font-weight-bold text-success">₦' . number_format($r->total_paid, 2) . '</span>',
                    'outstanding' => '<span class="font-weight-bold ' . ($r->outstanding > 0 ? 'text-danger' : 'text-success') . '" style="font-size:1.05rem;">₦' . number_format($r->outstanding, 2) . '</span>',
                    'unpaid_pos' => '<span class="badge ' . ($r->unpaid_pos > 0 ? 'bg-danger' : 'bg-success') . '">' . (int)$r->unpaid_pos . ' Unpaid</span>',
                ]);

                $cards = [
                    ['label' => 'Total Amount Due', 'value' => '₦' . number_format($rows->sum('total_ordered'), 2), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Paid', 'value' => '₦' . number_format($rows->sum('total_paid'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Total Outstanding', 'value' => '₦' . number_format($rows->sum('outstanding'), 2), 'class' => $rows->sum('outstanding') > 0 ? 'bg-danger text-white' : 'bg-success text-white'],
                    ['label' => 'Suppliers', 'value' => $rows->count(), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Supplier', 'POs', 'Total Ordered ₦', 'Paid ₦', 'Outstanding ₦', 'Unpaid POs']]);

            case 'damage-expiry-losses':
                $damages = \DB::table('store_damages as sd')
                    ->join('stores as s', 'sd.store_id', '=', 's.id')
                    ->join('products as p', 'sd.product_id', '=', 'p.id')
                    ->select(
                        'sd.damage_type',
                        \DB::raw('COUNT(sd.id) as incident_count'),
                        \DB::raw('SUM(sd.qty_damaged) as total_qty'),
                        \DB::raw('SUM(sd.total_value) as total_value'),
                        \DB::raw('SUM(CASE WHEN sd.status = "pending" THEN 1 ELSE 0 END) as pending_count')
                    )
                    ->where(function ($q) {
                        $q->where('s.distribution_role', 'central')
                            ->orWhere('s.store_type', 'warehouse');
                    })
                    ->whereBetween('sd.discovered_date', [$startDate, $endDate])
                    ->groupBy('sd.damage_type')
                    ->get();

                $expiredBatches = \DB::table('stock_batches as sb')
                    ->join('stores as s', 'sb.store_id', '=', 's.id')
                    ->join('products as p', 'sb.product_id', '=', 'p.id')
                    ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
                    ->select(
                        \DB::raw('"expired" as damage_type'),
                        \DB::raw('COUNT(sb.id) as incident_count'),
                        \DB::raw('SUM(sb.current_qty) as total_qty'),
                        \DB::raw('SUM(sb.current_qty * COALESCE(NULLIF(sb.cost_price,0), pp.pr_buy_price, 0)) as total_value'),
                        \DB::raw('0 as pending_count')
                    )
                    ->where(function ($q) {
                        $q->where('s.distribution_role', 'central')
                            ->orWhere('s.store_type', 'warehouse');
                    })
                    ->where('sb.current_qty', '>', 0)
                    ->whereNotNull('sb.expiry_date')
                    ->where(function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('sb.expiry_date', [$startDate, $endDate])
                            ->orWhere('sb.expiry_date', '<=', now());
                    })
                    ->groupBy('damage_type')
                    ->get();

                $rows = $damages->concat($expiredBatches);

                $formattedRows = $rows->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="main-store" data-story="damage-expiry-losses" data-key="' . e($r->damage_type) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'type' => '<span class="badge ' . ($r->damage_type === 'expired' ? 'bg-warning text-dark' : 'bg-danger') . ' px-2 py-1"><i class="mdi mdi-alert-circle"></i> ' . ucfirst($r->damage_type ?? 'Damage') . '</span>',
                    'incidents' => '<span class="badge bg-light text-dark border">' . (int)$r->incident_count . ' Incidents</span>',
                    'total_qty' => '<span class="font-weight-bold text-dark">' . number_format($r->total_qty) . ' Units</span>',
                    'total_value' => '<span class="font-weight-bold text-danger" style="font-size:1.05rem;">₦' . number_format($r->total_value, 2) . '</span>',
                    'pending' => '<span class="badge ' . ($r->pending_count > 0 ? 'bg-warning text-dark' : 'bg-success') . '">' . (int)$r->pending_count . ' Pending Approval</span>',
                ]);

                $cards = [
                    ['label' => 'Total Loss Value', 'value' => '₦' . number_format($rows->sum('total_value'), 2), 'class' => 'bg-danger text-white'],
                    ['label' => 'Damaged Items Qty', 'value' => number_format($rows->where('damage_type', '!=', 'expired')->sum('total_qty')), 'class' => 'bg-warning text-dark'],
                    ['label' => 'Expired Items Qty', 'value' => number_format($rows->where('damage_type', 'expired')->sum('total_qty')), 'class' => 'bg-secondary text-white'],
                    ['label' => 'Pending Approval', 'value' => $rows->sum('pending_count'), 'class' => 'bg-primary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Loss Type', 'Incidents', 'Total Qty', 'Total Value ₦', 'Pending Approval']]);

            case 'batch-source-breakdown':
                $rows = \DB::table('stock_batches as sb')
                    ->leftJoin('prices as pp', 'sb.product_id', '=', 'pp.product_id')
                    ->select(
                        'sb.source',
                        \DB::raw('COUNT(sb.id) as batch_count'),
                        \DB::raw('SUM(sb.initial_qty) as total_initial_qty'),
                        \DB::raw('SUM(sb.current_qty) as total_current_qty'),
                        \DB::raw('SUM(sb.initial_qty * COALESCE(NULLIF(sb.cost_price,0), pp.pr_buy_price, 0)) as total_acquisition_value'),
                        \DB::raw('SUM(sb.current_qty * COALESCE(NULLIF(sb.cost_price,0), pp.pr_buy_price, 0)) as remaining_value')
                    )
                    ->whereBetween('sb.received_date', [$startDate, $endDate])
                    ->groupBy('sb.source')
                    ->orderByDesc('batch_count')
                    ->get();

                $sourceLabels = ['purchase_order' => 'PO Reception', 'manual' => 'Manual Creation', 'transfer_in' => 'Inter-Store Transfer', 'opening_stock' => 'Opening Stock'];
                $sourceIcons = ['purchase_order' => 'mdi-truck text-primary', 'manual' => 'mdi-pencil text-info', 'transfer_in' => 'mdi-swap-horizontal text-success', 'opening_stock' => 'mdi-archive text-secondary'];

                $formattedRows = $rows->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="main-store" data-story="batch-source-breakdown" data-key="' . e($r->source) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'source' => '<div class="font-weight-bold text-dark"><i class="mdi ' . ($sourceIcons[$r->source] ?? 'mdi-package text-secondary') . '"></i> ' . e($sourceLabels[$r->source] ?? ucfirst(str_replace('_', ' ', $r->source))) . '</div>',
                    'batches' => '<span class="badge bg-light text-dark border font-weight-bold">' . number_format($r->batch_count) . ' Batches</span>',
                    'initial_qty' => '<span class="badge bg-info text-white">' . number_format($r->total_initial_qty) . ' Units</span>',
                    'current_qty' => '<span class="badge bg-primary text-white">' . number_format($r->total_current_qty) . ' Remaining</span>',
                    'acquisition_value' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->total_acquisition_value, 2) . '</span>',
                    'remaining_value' => '<span class="font-weight-bold text-primary">₦' . number_format($r->remaining_value, 2) . '</span>',
                ]);

                $cards = [
                    ['label' => 'PO-Sourced Value ₦', 'value' => '₦' . number_format($rows->firstWhere('source', 'purchase_order')?->total_acquisition_value ?? 0, 2), 'class' => 'bg-primary text-white'],
                    ['label' => 'Manually Created Value ₦', 'value' => '₦' . number_format($rows->firstWhere('source', 'manual')?->total_acquisition_value ?? 0, 2), 'class' => 'bg-info text-white'],
                    ['label' => 'Transfer-In Value ₦', 'value' => '₦' . number_format($rows->firstWhere('source', 'transfer_in')?->total_acquisition_value ?? 0, 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Total Batches Created', 'value' => number_format($rows->sum('batch_count')), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Source Type', 'Batches', 'Initial Qty', 'Current Qty', 'Acquisition Value ₦', 'Remaining Value ₦']]);

            default:
                return response()->json(['error' => 'Unknown story: ' . $story], 404);
        }
    }

    // =====================================================================
    // PAGE 6 — SUB-STORE & WARD STORES — Story Data (ALL store roles)
    // =====================================================================
    public function wardDeptStoryData(Request $request, $story)
    {
        [$startDate, $endDate] = $this->parseAuditPeriod($request);

        $allSubRoles = ['pharmacy_hub', 'pharmacy_satellite', 'department', 'ward', 'lab', 'imaging', 'other'];
        $roleLabels = \App\Models\Store::ROLE_LABELS;

        switch ($story) {

            case 'substore-valuation':
                $rows = \DB::table('stock_batches as sb')
                    ->join('stores as s', 'sb.store_id', '=', 's.id')
                    ->join('products as p', 'sb.product_id', '=', 'p.id')
                    ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
                    ->leftJoin('store_stocks as ss', function ($j) {
                        $j->on('ss.store_id', '=', 'sb.store_id')->on('ss.product_id', '=', 'sb.product_id');
                    })
                    ->select(
                        's.id as store_id',
                        's.store_name',
                        's.distribution_role',
                        \DB::raw('COUNT(sb.id) as batch_count'),
                        \DB::raw('SUM(sb.current_qty) as total_units'),
                        \DB::raw('SUM(sb.current_qty * COALESCE(NULLIF(sb.cost_price,0), pp.pr_buy_price, 0)) as total_value'),
                        \DB::raw('SUM(CASE WHEN sb.current_qty <= COALESCE(ss.reorder_level, 10) THEN 1 ELSE 0 END) as low_stock_lines')
                    )
                    ->whereIn('s.distribution_role', $allSubRoles)
                    ->where('sb.current_qty', '>', 0)
                    ->groupBy('s.id', 's.store_name', 's.distribution_role')
                    ->orderByDesc('total_value')
                    ->get();

                $formattedRows = $rows->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="ward-dept" data-story="substore-valuation" data-key="' . e($r->store_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'store' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-store text-primary"></i> ' . e($r->store_name) . '</div><span class="badge bg-light text-dark border mt-1">' . e($roleLabels[$r->distribution_role] ?? $r->distribution_role) . '</span>',
                    'batches' => '<span class="badge bg-light text-dark border">' . (int)$r->batch_count . ' Batches</span>',
                    'total_units' => '<span class="badge bg-info text-white">' . number_format($r->total_units) . ' Units</span>',
                    'total_value' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->total_value, 2) . '</span>',
                    'low_stock' => '<span class="badge ' . ($r->low_stock_lines > 0 ? 'bg-danger' : 'bg-success') . '">' . (int)$r->low_stock_lines . ' Low-Stock Lines</span>',
                ]);

                $cards = [
                    ['label' => 'Total Sub-Store Value', 'value' => '₦' . number_format($rows->sum('total_value'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Sub-Stores Tracked', 'value' => $rows->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Low-Stock Lines', 'value' => $rows->sum('low_stock_lines'), 'class' => 'bg-danger text-white'],
                    ['label' => 'Total Units Across All Sub-Stores', 'value' => number_format($rows->sum('total_units')), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Store', 'Batches', 'Total Units', 'Total Value ₦', 'Low-Stock Lines']]);

            case 'requisition-fulfillment':
                $rows = \DB::table('store_requisitions as sr')
                    ->join('stores as fs', 'sr.from_store_id', '=', 'fs.id')
                    ->join('stores as ts', 'sr.to_store_id', '=', 'ts.id')
                    ->select(
                        'fs.id as from_store_id',
                        'fs.store_name as from_store_name',
                        'fs.distribution_role as from_role',
                        'ts.store_name as to_store_name',
                        \DB::raw('COUNT(sr.id) as req_count'),
                        \DB::raw('SUM(CASE WHEN sr.status = "fulfilled" THEN 1 ELSE 0 END) as fulfilled_count'),
                        \DB::raw('SUM(CASE WHEN sr.status = "rejected" THEN 1 ELSE 0 END) as rejected_count'),
                        \DB::raw('SUM(CASE WHEN sr.status IN ("pending","approved") THEN 1 ELSE 0 END) as pending_count'),
                        \DB::raw('AVG(CASE WHEN sr.fulfilled_at IS NOT NULL THEN DATEDIFF(sr.fulfilled_at, sr.created_at) ELSE NULL END) as avg_days_to_fulfill')
                    )
                    ->whereBetween('sr.created_at', [$startDate, $endDate])
                    ->groupBy('fs.id', 'fs.store_name', 'fs.distribution_role', 'ts.store_name')
                    ->orderByDesc('req_count')
                    ->get();

                $formattedRows = $rows->map(function ($r) use ($roleLabels) {
                    $fulfillRate = $r->req_count > 0 ? round(($r->fulfilled_count / $r->req_count) * 100, 1) : 0;
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="ward-dept" data-story="requisition-fulfillment" data-key="' . e($r->from_store_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'store' => '<div class="font-weight-bold text-dark">' . e($r->from_store_name) . '</div><span class="badge bg-light text-dark border mt-1">' . e($roleLabels[$r->from_role] ?? $r->from_role) . '</span><small class="text-muted d-block"><i class="mdi mdi-arrow-right"></i> from: ' . e($r->to_store_name) . '</small>',
                        'req_count' => '<span class="badge bg-light text-dark border font-weight-bold">' . (int)$r->req_count . ' Reqs</span>',
                        'fulfilled' => '<span class="badge bg-success">' . (int)$r->fulfilled_count . ' Fulfilled</span>',
                        'rejected' => '<span class="badge bg-danger">' . (int)$r->rejected_count . ' Rejected</span>',
                        'pending' => '<span class="badge bg-warning text-dark">' . (int)$r->pending_count . ' Pending</span>',
                        'avg_days' => '<span class="font-weight-bold text-info">' . ($r->avg_days_to_fulfill !== null ? round($r->avg_days_to_fulfill, 1) . ' days avg' : 'N/A') . '</span>',
                        'fulfillment_rate' => '<div class="d-flex align-items-center"><span class="font-weight-bold me-2 ' . ($fulfillRate >= 80 ? 'text-success' : 'text-warning') . '">' . $fulfillRate . '%</span><div class="progress flex-grow-1" style="height:6px;"><div class="progress-bar ' . ($fulfillRate >= 80 ? 'bg-success' : 'bg-warning') . '" style="width:' . min($fulfillRate, 100) . '%"></div></div></div>',
                    ];
                });

                $cards = [
                    ['label' => 'Total Requisitions', 'value' => $rows->sum('req_count'), 'class' => 'bg-primary text-white'],
                    ['label' => 'Fulfilled', 'value' => $rows->sum('fulfilled_count'), 'class' => 'bg-success text-white'],
                    ['label' => 'Pending / In Progress', 'value' => $rows->sum('pending_count'), 'class' => 'bg-warning text-dark'],
                    ['label' => 'Avg Lead Time', 'value' => round($rows->avg('avg_days_to_fulfill') ?? 0, 1) . ' days', 'class' => 'bg-info text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Requesting Store', 'Requisitions', 'Fulfilled', 'Rejected', 'Pending', 'Avg Days', 'Fulfillment Rate']]);

            case 'requisition-items-audit':
                $rows = \DB::table('store_requisition_items as sri')
                    ->join('store_requisitions as sr', 'sri.store_requisition_id', '=', 'sr.id')
                    ->join('products as p', 'sri.product_id', '=', 'p.id')
                    ->leftJoin('product_categories as pc', 'p.category_id', '=', 'pc.id')
                    ->leftJoin('stock_batches as sb', 'sri.source_batch_id', '=', 'sb.id')
                    ->select(
                        'sri.product_id',
                        'p.product_name',
                        'pc.category_name',
                        \DB::raw('COUNT(sri.id) as line_count'),
                        \DB::raw('SUM(sri.requested_qty) as total_requested'),
                        \DB::raw('SUM(sri.approved_qty) as total_approved'),
                        \DB::raw('SUM(sri.fulfilled_qty) as total_fulfilled'),
                        \DB::raw('SUM(sri.requested_qty - sri.fulfilled_qty) as total_gap')
                    )
                    ->whereBetween('sr.created_at', [$startDate, $endDate])
                    ->groupBy('sri.product_id', 'p.product_name', 'pc.category_name')
                    ->orderByDesc('total_requested')
                    ->get();

                $formattedRows = $rows->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="ward-dept" data-story="requisition-items-audit" data-key="' . e($r->product_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'product' => '<div class="font-weight-bold text-dark">' . e($r->product_name) . '</div><small class="text-muted"><i class="mdi mdi-tag"></i> ' . e($r->category_name ?? 'N/A') . '</small>',
                    'requested' => '<span class="badge bg-primary text-white">' . number_format($r->total_requested) . ' Req</span>',
                    'approved' => '<span class="badge bg-info text-white">' . number_format($r->total_approved) . ' Appr</span>',
                    'fulfilled' => '<span class="badge bg-success">' . number_format($r->total_fulfilled) . ' Filled</span>',
                    'gap' => '<span class="badge ' . ($r->total_gap > 0 ? 'bg-danger' : 'bg-success') . ' font-weight-bold">' . number_format($r->total_gap) . ' Gap</span>',
                    'lines' => '<span class="badge bg-light text-dark border">' . (int)$r->line_count . ' Lines</span>',
                ]);

                $cards = [
                    ['label' => 'Total Requested Qty', 'value' => number_format($rows->sum('total_requested')), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Fulfilled Qty', 'value' => number_format($rows->sum('total_fulfilled')), 'class' => 'bg-success text-white'],
                    ['label' => 'Total Gap (Unfulfilled)', 'value' => number_format($rows->sum('total_gap')), 'class' => $rows->sum('total_gap') > 0 ? 'bg-danger text-white' : 'bg-success text-white'],
                    ['label' => 'Unique Products Requested', 'value' => $rows->count(), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Product', 'Requested', 'Approved', 'Fulfilled', 'Gap', 'Req Lines']]);

            case 'ward-stock-movement':
                $rows = \DB::table('stock_batch_transactions as sbt')
                    ->join('stock_batches as sb', 'sbt.stock_batch_id', '=', 'sb.id')
                    ->join('stores as s', 'sb.store_id', '=', 's.id')
                    ->leftJoin('users as u', 'sbt.performed_by', '=', 'u.id')
                    ->select(
                        's.id as store_id',
                        's.store_name',
                        's.distribution_role',
                        'sbt.type',
                        \DB::raw('COUNT(sbt.id) as txn_count'),
                        \DB::raw('SUM(sbt.qty) as total_qty')
                    )
                    ->whereIn('s.distribution_role', $allSubRoles)
                    ->whereBetween('sbt.created_at', [$startDate, $endDate])
                    ->groupBy('s.id', 's.store_name', 's.distribution_role', 'sbt.type')
                    ->orderBy('s.store_name')
                    ->orderBy('sbt.type')
                    ->get();

                $inboundTypes = ['in', 'transfer_in', 'return', 'req_return'];
                $outboundTypes = ['out', 'transfer_out', 'expired', 'damaged'];
                $typeColors = ['in' => 'bg-success', 'transfer_in' => 'bg-info', 'return' => 'bg-primary', 'req_return' => 'bg-primary', 'out' => 'bg-warning text-dark', 'transfer_out' => 'bg-danger', 'expired' => 'bg-danger', 'damaged' => 'bg-danger'];

                $formattedRows = $rows->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="ward-dept" data-story="ward-stock-movement" data-key="' . e($r->store_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'store' => '<div class="font-weight-bold text-dark">' . e($r->store_name) . '</div><span class="badge bg-light text-dark border mt-1">' . e($roleLabels[$r->distribution_role] ?? $r->distribution_role) . '</span>',
                    'direction' => '<span class="badge ' . (in_array($r->type, $inboundTypes) ? 'bg-success' : 'bg-danger') . '">' . (in_array($r->type, $inboundTypes) ? '▲ IN' : '▼ OUT') . '</span>',
                    'type' => '<span class="badge ' . ($typeColors[$r->type] ?? 'bg-secondary') . '">' . strtoupper(str_replace('_', ' ', $r->type)) . '</span>',
                    'txn_count' => '<span class="badge bg-light text-dark border">' . (int)$r->txn_count . ' Txns</span>',
                    'total_qty' => '<span class="font-weight-bold ' . (in_array($r->type, $inboundTypes) ? 'text-success' : 'text-danger') . '">' . (in_array($r->type, $inboundTypes) ? '+' : '-') . number_format($r->total_qty) . ' Units</span>',
                ]);

                $cards = [
                    ['label' => 'Total Inbound Qty', 'value' => number_format($rows->whereIn('type', $inboundTypes)->sum('total_qty')), 'class' => 'bg-success text-white'],
                    ['label' => 'Total Outbound Qty', 'value' => number_format($rows->whereIn('type', $outboundTypes)->sum('total_qty')), 'class' => 'bg-danger text-white'],
                    ['label' => 'Net Movement', 'value' => number_format($rows->whereIn('type', $inboundTypes)->sum('total_qty') - $rows->whereIn('type', $outboundTypes)->sum('total_qty')) . ' Units', 'class' => 'bg-info text-white'],
                    ['label' => 'Active Sub-Stores', 'value' => $rows->pluck('store_id')->unique()->count(), 'class' => 'bg-primary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Store', 'Direction', 'Movement Type', 'Transactions', 'Qty']]);

            case 'return-analysis':
                // Source A: Store Requisition Returns
                $reqReturns = \DB::table('store_requisition_returns as srr')
                    ->join('products as p', 'srr.product_id', '=', 'p.id')
                    ->leftJoin('product_categories as pc', 'p.category_id', '=', 'pc.id')
                    ->leftJoin('stores as src', 'srr.source_store_id', '=', 'src.id')
                    ->leftJoin('stock_batches as sb', 'srr.batch_id', '=', 'sb.id')
                    ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
                    ->select(
                        'srr.product_id',
                        'p.product_name',
                        'pc.category_name',
                        \DB::raw('SUM(srr.qty_returned) as qty_returned'),
                        \DB::raw('SUM(srr.qty_returned * COALESCE(NULLIF(sb.cost_price,0), pp.pr_buy_price, 0)) as cost_value'),
                        \DB::raw('"requisition_return" as return_source')
                    )
                    ->whereBetween('srr.created_at', [$startDate, $endDate])
                    ->groupBy('srr.product_id', 'p.product_name', 'pc.category_name')
                    ->get();

                // Source B: Pharmacy Workbench Returns (ProductRequest where returned_qty > 0)
                $pharmacyReturns = \DB::table('product_requests as pr')
                    ->join('products as p', 'pr.product_id', '=', 'p.id')
                    ->leftJoin('product_categories as pc', 'p.category_id', '=', 'pc.id')
                    ->leftJoin('stock_batches as sb', 'pr.dispensed_from_batch_id', '=', 'sb.id')
                    ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
                    ->select(
                        'pr.product_id',
                        'p.product_name',
                        'pc.category_name',
                        \DB::raw('SUM(pr.returned_qty) as qty_returned'),
                        \DB::raw('SUM(COALESCE(pr.refund_amount, 0)) as refund_amount'),
                        \DB::raw('SUM(pr.returned_qty * COALESCE(NULLIF(sb.cost_price,0), pp.pr_buy_price, 0)) as cost_value'),
                        \DB::raw('"pharmacy_return" as return_source')
                    )
                    ->whereNotNull('pr.returned_qty')
                    ->where('pr.returned_qty', '>', 0)
                    ->whereNull('pr.deleted_at')
                    ->whereBetween('pr.created_at', [$startDate, $endDate])
                    ->groupBy('pr.product_id', 'p.product_name', 'pc.category_name')
                    ->get();

                $allReturns = $reqReturns->merge($pharmacyReturns)->sortByDesc('cost_value');

                $formattedRows = $allReturns->map(function ($r) {
                    $isPharmacy = ($r->return_source === 'pharmacy_return');
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="ward-dept" data-story="return-analysis" data-key="' . e($r->product_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'product' => '<div class="font-weight-bold text-dark">' . e($r->product_name) . '</div><small class="text-muted"><i class="mdi mdi-tag"></i> ' . e($r->category_name ?? 'N/A') . '</small>',
                        'source' => '<span class="badge ' . ($isPharmacy ? 'bg-primary' : 'bg-info') . '"><i class="mdi ' . ($isPharmacy ? 'mdi-pill' : 'mdi-swap-horizontal') . '"></i> ' . ($isPharmacy ? 'Pharmacy Return' : 'Req Return') . '</span>',
                        'qty_returned' => '<span class="font-weight-bold text-warning">' . number_format($r->qty_returned) . ' Units</span>',
                        'refund_amount' => '<span class="font-weight-bold text-danger">₦' . number_format($r->refund_amount ?? 0, 2) . '</span>',
                        'cost_value' => '<span class="font-weight-bold text-dark">₦' . number_format($r->cost_value, 2) . '</span>',
                    ];
                });

                $cards = [
                    ['label' => 'Req Returns Qty', 'value' => number_format($reqReturns->sum('qty_returned')), 'class' => 'bg-info text-white'],
                    ['label' => 'Pharmacy Returns Qty', 'value' => number_format($pharmacyReturns->sum('qty_returned')), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Refunds Paid', 'value' => '₦' . number_format($pharmacyReturns->sum('refund_amount'), 2), 'class' => 'bg-danger text-white'],
                    ['label' => 'Total Cost of Returns', 'value' => '₦' . number_format($allReturns->sum('cost_value'), 2), 'class' => 'bg-warning text-dark'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Product', 'Return Source', 'Qty Returned', 'Refund Paid ₦', 'Cost of Return ₦']]);

            default:
                return response()->json(['error' => 'Unknown story: ' . $story], 404);
        }
    }

    // =====================================================================
    // PAGE 7 — STORE UTILIZATION vs INCOME — Story Data
    // =====================================================================
    public function storeUtilizationStoryData(Request $request, $story)
    {
        if (in_array($story, ['transactions', 'revenue', 'movements'])) {
            return $this->storeUtilizationRevenueData($request, $story);
        }

        [$startDate, $endDate] = $this->parseAuditPeriod($request);

        switch ($story) {

            case 'dispensing-revenue-attribution':
                $rows = \DB::table('product_or_service_requests as posr')
                    ->join('products as p', 'posr.product_id', '=', 'p.id')
                    ->leftJoin('product_categories as pc', 'p.category_id', '=', 'pc.id')
                    ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
                    ->select(
                        'posr.product_id',
                        'p.product_name',
                        'pc.category_name',
                        \DB::raw('COUNT(posr.id) as item_count'),
                        \DB::raw('SUM(posr.qty) as total_qty'),
                        \DB::raw('SUM(posr.payable_amount) as total_revenue'),
                        \DB::raw('SUM(CASE WHEN posr.claims_amount > 0 THEN posr.claims_amount ELSE 0 END) as total_claims'),
                        \DB::raw('AVG(COALESCE(NULLIF(pp.pr_buy_price,0), 0)) as avg_cost_price')
                    )
                    ->whereNotNull('posr.product_id')
                    ->whereBetween('posr.created_at', [$startDate, $endDate])
                    ->groupBy('posr.product_id', 'p.product_name', 'pc.category_name')
                    ->orderByDesc('total_revenue')
                    ->get();

                $formattedRows = $rows->map(function ($r) {
                    $cogs = round($r->total_qty * $r->avg_cost_price, 2);
                    $margin = $r->total_revenue - $cogs;
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="store-utilization" data-story="dispensing-revenue-attribution" data-key="' . e($r->product_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'product' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-pill text-primary"></i> ' . e($r->product_name) . '</div><small class="text-muted">' . e($r->category_name ?? 'N/A') . '</small>',
                        'qty' => '<span class="badge bg-light text-dark border font-weight-bold">' . number_format($r->total_qty) . ' Units</span>',
                        'revenue' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->total_revenue, 2) . '</span>',
                        'claims' => '<span class="font-weight-bold text-info">₦' . number_format($r->total_claims, 2) . '</span>',
                        'cogs' => '<span class="font-weight-bold text-dark">₦' . number_format($cogs, 2) . '</span>',
                        'margin' => '<span class="font-weight-bold ' . ($margin >= 0 ? 'text-success' : 'text-danger') . '">₦' . number_format($margin, 2) . '</span>',
                    ];
                });

                $totalRevenue = $rows->sum('total_revenue');
                $totalClaims = $rows->sum('total_claims');
                $totalCogs = $rows->sum(fn($r) => $r->total_qty * $r->avg_cost_price);

                $cards = [
                    ['label' => 'Total Product Revenue', 'value' => '₦' . number_format($totalRevenue, 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Total HMO Claims', 'value' => '₦' . number_format($totalClaims, 2), 'class' => 'bg-info text-white'],
                    ['label' => 'Est. Total COGS', 'value' => '₦' . number_format($totalCogs, 2), 'class' => 'bg-primary text-white'],
                    ['label' => 'Est. Gross Margin', 'value' => '₦' . number_format($totalRevenue - $totalCogs, 2), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Product', 'Qty Sold', 'Revenue ₦', 'Claims ₦', 'Est. COGS ₦', 'Est. Margin ₦']]);

            case 'store-dispensing-contribution':
                $rows = \DB::table('product_or_service_requests as posr')
                    ->join('stores as s', 'posr.dispensed_from_store_id', '=', 's.id')
                    ->select(
                        's.id as store_id',
                        's.store_name',
                        's.distribution_role',
                        \DB::raw('COUNT(posr.id) as item_count'),
                        \DB::raw('SUM(posr.qty) as total_qty'),
                        \DB::raw('SUM(posr.payable_amount) as total_revenue'),
                        \DB::raw('SUM(CASE WHEN posr.claims_amount > 0 THEN posr.claims_amount ELSE 0 END) as total_claims')
                    )
                    ->whereNotNull('posr.product_id')
                    ->whereNotNull('posr.dispensed_from_store_id')
                    ->whereBetween('posr.created_at', [$startDate, $endDate])
                    ->groupBy('s.id', 's.store_name', 's.distribution_role')
                    ->orderByDesc('total_revenue')
                    ->get();

                $roleLabels = \App\Models\Store::ROLE_LABELS;
                $formattedRows = $rows->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="store-utilization" data-story="store-dispensing-contribution" data-key="' . e($r->store_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'store' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-store text-primary"></i> ' . e($r->store_name) . '</div><span class="badge bg-light text-dark border mt-1">' . e($roleLabels[$r->distribution_role] ?? $r->distribution_role) . '</span>',
                    'items' => '<span class="badge bg-light text-dark border">' . number_format($r->item_count) . ' Lines</span>',
                    'qty' => '<span class="badge bg-info text-white">' . number_format($r->total_qty) . ' Units</span>',
                    'revenue' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->total_revenue, 2) . '</span>',
                    'claims' => '<span class="font-weight-bold text-info">₦' . number_format($r->total_claims, 2) . '</span>',
                ]);

                $cards = [
                    ['label' => 'Top Dispensing Store', 'value' => $rows->first()?->store_name ?? 'N/A', 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Dispensed Revenue', 'value' => '₦' . number_format($rows->sum('total_revenue'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Total HMO Claims', 'value' => '₦' . number_format($rows->sum('total_claims'), 2), 'class' => 'bg-info text-white'],
                    ['label' => 'Private Cash Portion', 'value' => '₦' . number_format($rows->sum('total_revenue') - $rows->sum('total_claims'), 2), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Store', 'Billing Lines', 'Total Qty', 'Revenue ₦', 'Claims ₦']]);

            case 'consumption-vs-billing-gap':
                $rows = \DB::table('stock_utilizations as su')
                    ->join('products as p', 'su.product_id', '=', 'p.id')
                    ->leftJoin('product_categories as pc', 'p.category_id', '=', 'pc.id')
                    ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
                    ->select(
                        'su.product_id',
                        'p.product_name',
                        'pc.category_name',
                        \DB::raw('SUM(su.qty) as total_consumed'),
                        \DB::raw('SUM(CASE WHEN su.is_billed = 1 THEN su.qty ELSE 0 END) as billed_qty'),
                        \DB::raw('SUM(CASE WHEN su.is_billed = 0 OR su.is_billed IS NULL THEN su.qty ELSE 0 END) as unbilled_qty'),
                        \DB::raw('COALESCE(pp.current_sale_price, 0) as sell_price')
                    )
                    ->whereBetween('su.created_at', [$startDate, $endDate])
                    ->groupBy('su.product_id', 'p.product_name', 'pc.category_name', 'pp.current_sale_price')
                    ->orderByDesc('total_consumed')
                    ->get();

                $formattedRows = $rows->map(function ($r) {
                    $gapValue = round($r->unbilled_qty * $r->sell_price, 2);
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="store-utilization" data-story="consumption-vs-billing-gap" data-key="' . e($r->product_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'product' => '<div class="font-weight-bold text-dark">' . e($r->product_name) . '</div><small class="text-muted">' . e($r->category_name ?? 'N/A') . '</small>',
                        'consumed' => '<span class="badge bg-primary text-white font-weight-bold">' . number_format($r->total_consumed) . ' Units</span>',
                        'billed' => '<span class="badge bg-success">' . number_format($r->billed_qty) . ' Billed</span>',
                        'unbilled' => '<span class="badge ' . ($r->unbilled_qty > 0 ? 'bg-danger' : 'bg-success') . ' font-weight-bold">' . number_format($r->unbilled_qty) . ' Unbilled</span>',
                        'gap_value' => '<span class="font-weight-bold ' . ($gapValue > 0 ? 'text-danger' : 'text-success') . '">₦' . number_format($gapValue, 2) . '</span>',
                    ];
                });

                $cards = [
                    ['label' => 'Total Consumed Qty', 'value' => number_format($rows->sum('total_consumed')), 'class' => 'bg-primary text-white'],
                    ['label' => 'Billed Qty', 'value' => number_format($rows->sum('billed_qty')), 'class' => 'bg-success text-white'],
                    ['label' => 'Unbilled Qty', 'value' => number_format($rows->sum('unbilled_qty')), 'class' => 'bg-danger text-white'],
                    ['label' => 'Unbilled Revenue Gap ₦', 'value' => '₦' . number_format($rows->sum(fn($r) => $r->unbilled_qty * $r->sell_price), 2), 'class' => 'bg-warning text-dark'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Product', 'Consumed', 'Billed', 'Unbilled', 'Revenue Gap ₦']]);

            case 'daily-stock-movement-trend':
                $rows = \DB::table('stock_batch_transactions as sbt')
                    ->select(
                        \DB::raw('DATE(sbt.created_at) as date'),
                        \DB::raw('SUM(CASE WHEN sbt.type IN ("in","transfer_in","return","req_return") THEN sbt.qty ELSE 0 END) as inbound'),
                        \DB::raw('SUM(CASE WHEN sbt.type IN ("out","transfer_out","expired","damaged") THEN sbt.qty ELSE 0 END) as outbound'),
                        \DB::raw('COUNT(sbt.id) as txn_count')
                    )
                    ->whereBetween('sbt.created_at', [$startDate, $endDate])
                    ->groupByRaw('DATE(sbt.created_at)')
                    ->orderBy('date')
                    ->get();

                $formattedRows = $rows->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="store-utilization" data-story="daily-stock-movement-trend" data-key="' . e($r->date) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'date' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-calendar text-primary"></i> ' . e($r->date) . '</div>',
                    'txn_count' => '<span class="badge bg-light text-dark border">' . (int)$r->txn_count . ' Txns</span>',
                    'inbound' => '<span class="font-weight-bold text-success">+' . number_format($r->inbound) . ' Units</span>',
                    'outbound' => '<span class="font-weight-bold text-danger">-' . number_format($r->outbound) . ' Units</span>',
                    'net' => '<span class="font-weight-bold ' . ($r->inbound - $r->outbound >= 0 ? 'text-primary' : 'text-warning') . '">' . ($r->inbound - $r->outbound >= 0 ? '+' : '') . number_format($r->inbound - $r->outbound) . ' Net</span>',
                ]);

                $peakInbound = $rows->sortByDesc('inbound')->first();
                $peakOutbound = $rows->sortByDesc('outbound')->first();
                $cards = [
                    ['label' => 'Total Inbound Qty', 'value' => number_format($rows->sum('inbound')), 'class' => 'bg-success text-white'],
                    ['label' => 'Total Outbound Qty', 'value' => number_format($rows->sum('outbound')), 'class' => 'bg-danger text-white'],
                    ['label' => 'Peak Inbound Day', 'value' => ($peakInbound?->date ?? 'N/A') . ' (' . number_format($peakInbound?->inbound ?? 0) . ' units)', 'class' => 'bg-primary text-white'],
                    ['label' => 'Active Days', 'value' => $rows->count(), 'class' => 'bg-info text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Date', 'Transactions', 'Inbound', 'Outbound', 'Net Movement'], 'chart' => true]);

            case 'product-turnover-rate':
                $rows = \DB::table('stock_batches as sb')
                    ->join('products as p', 'sb.product_id', '=', 'p.id')
                    ->leftJoin('product_categories as pc', 'p.category_id', '=', 'pc.id')
                    ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
                    ->select(
                        'sb.product_id',
                        'p.product_name',
                        'pc.category_name',
                        \DB::raw('SUM(sb.initial_qty) as total_initial'),
                        \DB::raw('SUM(sb.sold_qty) as total_sold'),
                        \DB::raw('SUM(sb.current_qty) as total_remaining'),
                        \DB::raw('SUM(sb.current_qty * COALESCE(NULLIF(sb.cost_price,0), pp.pr_buy_price, 0)) as value_remaining')
                    )
                    ->whereBetween('sb.received_date', [$startDate, $endDate])
                    ->groupBy('sb.product_id', 'p.product_name', 'pc.category_name')
                    ->havingRaw('total_initial > 0')
                    ->orderByRaw('(total_sold / total_initial) DESC')
                    ->get();

                $formattedRows = $rows->map(function ($r) {
                    $turnover = $r->total_initial > 0 ? round(($r->total_sold / $r->total_initial) * 100, 1) : 0;
                    $cls = $turnover >= 50 ? 'text-success' : ($turnover >= 10 ? 'text-warning' : 'text-danger');
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="store-utilization" data-story="product-turnover-rate" data-key="' . e($r->product_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'product' => '<div class="font-weight-bold text-dark">' . e($r->product_name) . '</div><small class="text-muted">' . e($r->category_name ?? 'N/A') . '</small>',
                        'initial' => '<span class="badge bg-light text-dark border">' . number_format($r->total_initial) . ' Initial</span>',
                        'sold' => '<span class="badge bg-success">' . number_format($r->total_sold) . ' Sold</span>',
                        'remaining' => '<span class="badge bg-info text-white">' . number_format($r->total_remaining) . ' Left</span>',
                        'turnover' => '<span class="font-weight-bold ' . $cls . '" style="font-size:1.05rem;">' . $turnover . '%</span>',
                        'idle_value' => '<span class="font-weight-bold text-dark">₦' . number_format($r->value_remaining, 2) . '</span>',
                    ];
                });

                $totalInitial = $rows->sum('total_initial');
                $totalSold = $rows->sum('total_sold');
                $avgTurnover = $totalInitial > 0 ? round(($totalSold / $totalInitial) * 100, 1) : 0;

                $cards = [
                    ['label' => 'Avg Turnover Rate', 'value' => $avgTurnover . '%', 'class' => $avgTurnover >= 50 ? 'bg-success text-white' : 'bg-warning text-dark'],
                    ['label' => 'Fast Movers (>50%)', 'value' => $rows->filter(fn($r) => $r->total_initial > 0 && ($r->total_sold / $r->total_initial) >= 0.5)->count(), 'class' => 'bg-success text-white'],
                    ['label' => 'Dead Stock (<5%)', 'value' => $rows->filter(fn($r) => $r->total_initial > 0 && ($r->total_sold / $r->total_initial) < 0.05)->count(), 'class' => 'bg-danger text-white'],
                    ['label' => 'Idle Stock Value ₦', 'value' => '₦' . number_format($rows->sum('value_remaining'), 2), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Product', 'Initial Qty', 'Sold', 'Remaining', 'Turnover Rate', 'Idle Value ₦']]);

            default:
                return response()->json(['error' => 'Unknown story: ' . $story], 404);
        }
    }

    // =====================================================================
    // PAGE 8 — HMO & NHIS AUDIT — Story Data
    // Scheme always displayed via: JOIN hmos JOIN hmo_schemes ON hmos.hmo_scheme_id = hmo_schemes.id
    // =====================================================================
    public function hmoNhisStoryData(Request $request, $story)
    {
        if (in_array($story, ['services', 'claims', 'remittances'])) {
            return $this->hmoNhisData($request, $story);
        }

        [$startDate, $endDate] = $this->parseAuditPeriod($request);

        switch ($story) {

            case 'hmo-claims-by-provider':
                $rows = \DB::table('product_or_service_requests as posr')
                    ->join('hmos as h', 'posr.hmo_id', '=', 'h.id')
                    ->join('hmo_schemes as hs', 'h.hmo_scheme_id', '=', 'hs.id')
                    ->select(
                        'h.id as hmo_id',
                        'h.name as hmo_name',
                        'hs.name as scheme_name',
                        'hs.code as scheme_code',
                        \DB::raw('COUNT(posr.id) as item_count'),
                        \DB::raw('SUM(CASE WHEN posr.claims_amount > 0 THEN posr.claims_amount ELSE posr.payable_amount END) as total_claims'),
                        \DB::raw('SUM(posr.payable_amount) as total_payable'),
                        \DB::raw('SUM(CASE WHEN posr.hmo_remittance_id IS NOT NULL THEN CASE WHEN posr.claims_amount > 0 THEN posr.claims_amount ELSE posr.payable_amount END ELSE 0 END) as remitted'),
                        \DB::raw('SUM(CASE WHEN posr.validation_status = "approved" THEN 1 ELSE 0 END) as approved_count'),
                        \DB::raw('SUM(CASE WHEN posr.validation_status = "pending" OR posr.validation_status IS NULL THEN 1 ELSE 0 END) as pending_count'),
                        \DB::raw('SUM(CASE WHEN posr.validation_status = "awaiting_code" THEN 1 ELSE 0 END) as awaiting_count')
                    )
                    ->whereNotNull('posr.hmo_id')
                    ->where('posr.hmo_id', '!=', 1)
                    ->whereBetween('posr.created_at', [$startDate, $endDate])
                    ->groupBy('h.id', 'h.name', 'hs.name', 'hs.code')
                    ->orderByDesc('total_claims')
                    ->get();

                $formattedRows = $rows->map(function ($r) {
                    $outstanding = $r->total_claims - $r->remitted;
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="hmo-nhis" data-story="hmo-claims-by-provider" data-key="' . e($r->hmo_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'hmo' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-shield-account text-primary"></i> ' . e($r->hmo_name) . '</div><span class="badge bg-info text-white mt-1">' . e($r->scheme_name) . ' (' . e($r->scheme_code) . ')</span>',
                        'items' => '<span class="badge bg-light text-dark border">' . (int)$r->item_count . ' Items</span>',
                        'validation' => '<span class="badge bg-success">' . (int)$r->approved_count . ' Appr</span> <span class="badge bg-warning text-dark">' . (int)$r->pending_count . ' Pend</span>' . ($r->awaiting_count > 0 ? ' <span class="badge bg-danger">' . (int)$r->awaiting_count . ' Await</span>' : ''),
                        'claims' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->total_claims, 2) . '</span>',
                        'payable' => '<span class="font-weight-bold text-primary">₦' . number_format($r->total_payable, 2) . '</span>',
                        'remitted' => '<span class="font-weight-bold text-info">₦' . number_format($r->remitted, 2) . '</span>',
                        'outstanding' => '<span class="font-weight-bold ' . ($outstanding > 0 ? 'text-danger' : 'text-success') . '" style="font-size:1.05rem;">₦' . number_format($outstanding, 2) . '</span>',
                    ];
                });

                $totalClaims = $rows->sum('total_claims');
                $totalRemitted = $rows->sum('remitted');
                $cards = [
                    ['label' => 'Total Claims Value', 'value' => '₦' . number_format($totalClaims, 2), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Remitted', 'value' => '₦' . number_format($totalRemitted, 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Outstanding (Unremitted)', 'value' => '₦' . number_format($totalClaims - $totalRemitted, 2), 'class' => 'bg-danger text-white'],
                    ['label' => 'HMO Providers', 'value' => $rows->count(), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'HMO & Scheme', 'Items', 'Validation', 'Claims ₦', 'Payable ₦', 'Remitted ₦', 'Outstanding ₦']]);

            case 'validation-status-aging':
                $rows = \DB::table('product_or_service_requests as posr')
                    ->join('hmos as h', 'posr.hmo_id', '=', 'h.id')
                    ->join('hmo_schemes as hs', 'h.hmo_scheme_id', '=', 'hs.id')
                    ->select(
                        \DB::raw('COALESCE(posr.validation_status, "pending") as validation_status'),
                        \DB::raw('COUNT(posr.id) as item_count'),
                        \DB::raw('SUM(CASE WHEN posr.claims_amount > 0 THEN posr.claims_amount ELSE posr.payable_amount END) as total_claims'),
                        \DB::raw('SUM(posr.payable_amount) as total_payable'),
                        \DB::raw('MIN(posr.created_at) as oldest_item')
                    )
                    ->whereNotNull('posr.hmo_id')
                    ->where('posr.hmo_id', '!=', 1)
                    ->whereBetween('posr.created_at', [$startDate, $endDate])
                    ->groupByRaw('COALESCE(posr.validation_status, "pending")')
                    ->get();

                $statusConfig = ['approved' => ['bg-success', 'mdi-check-circle'], 'pending' => ['bg-warning text-dark', 'mdi-clock-outline'], 'awaiting_code' => ['bg-danger', 'mdi-alert-circle']];

                $formattedRows = $rows->map(function ($r) use ($statusConfig) {
                    $cfg = $statusConfig[$r->validation_status] ?? ['bg-secondary', 'mdi-help-circle'];
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="hmo-nhis" data-story="validation-status-aging" data-key="' . e($r->validation_status) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'status' => '<span class="badge ' . $cfg[0] . ' px-2 py-1"><i class="mdi ' . $cfg[1] . '"></i> ' . ucfirst(str_replace('_', ' ', $r->validation_status)) . '</span>',
                        'item_count' => '<span class="badge bg-light text-dark border font-weight-bold">' . number_format($r->item_count) . ' Items</span>',
                        'total_claims' => '<span class="font-weight-bold text-dark" style="font-size:1.05rem;">₦' . number_format($r->total_claims, 2) . '</span>',
                        'total_payable' => '<span class="font-weight-bold text-primary">₦' . number_format($r->total_payable, 2) . '</span>',
                        'oldest_item' => '<small class="text-muted">' . \Carbon\Carbon::parse($r->oldest_item)->format('M d, Y') . '</small>',
                    ];
                });

                $cards = [
                    ['label' => 'Approved ₦', 'value' => '₦' . number_format($rows->firstWhere('validation_status', 'approved')?->total_claims ?? 0, 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Pending ₦', 'value' => '₦' . number_format($rows->firstWhere('validation_status', 'pending')?->total_claims ?? 0, 2), 'class' => 'bg-warning text-dark'],
                    ['label' => 'Awaiting Code ₦ (At Risk)', 'value' => '₦' . number_format($rows->firstWhere('validation_status', 'awaiting_code')?->total_claims ?? 0, 2), 'class' => 'bg-danger text-white'],
                    ['label' => 'Unresolved Count', 'value' => number_format($rows->whereIn('validation_status', ['pending', 'awaiting_code'])->sum('item_count')), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Validation Status', 'Items', 'Claims ₦', 'Payable ₦', 'Oldest Item']]);

            case 'scheme-breakdown':
                // Groups by ALL hmo_schemes — no LIKE filter
                $rows = \DB::table('product_or_service_requests as posr')
                    ->join('hmos as h', 'posr.hmo_id', '=', 'h.id')
                    ->join('hmo_schemes as hs', 'h.hmo_scheme_id', '=', 'hs.id')
                    ->select(
                        'hs.id as scheme_id',
                        'hs.name as scheme_name',
                        'hs.code as scheme_code',
                        \DB::raw('COUNT(DISTINCT h.id) as hmo_count'),
                        \DB::raw('COUNT(posr.id) as item_count'),
                        \DB::raw('SUM(CASE WHEN posr.claims_amount > 0 THEN posr.claims_amount ELSE posr.payable_amount END) as total_claims'),
                        \DB::raw('SUM(posr.payable_amount) as total_payable'),
                        \DB::raw('SUM(CASE WHEN posr.hmo_remittance_id IS NOT NULL THEN CASE WHEN posr.claims_amount > 0 THEN posr.claims_amount ELSE posr.payable_amount END ELSE 0 END) as remitted'),
                        \DB::raw('SUM(CASE WHEN posr.validation_status = "approved" THEN 1 ELSE 0 END) as approved_count'),
                        \DB::raw('SUM(CASE WHEN posr.validation_status != "approved" OR posr.validation_status IS NULL THEN 1 ELSE 0 END) as unresolved_count')
                    )
                    ->whereNotNull('posr.hmo_id')
                    ->where('posr.hmo_id', '!=', 1)
                    ->whereBetween('posr.created_at', [$startDate, $endDate])
                    ->groupBy('hs.id', 'hs.name', 'hs.code')
                    ->orderByDesc('total_claims')
                    ->get();

                $formattedRows = $rows->map(function ($r) {
                    $outstanding = $r->total_claims - $r->remitted;
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="hmo-nhis" data-story="scheme-breakdown" data-key="' . e($r->scheme_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'scheme' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-shield text-primary"></i> ' . e($r->scheme_name) . '</div><span class="badge bg-secondary text-white mt-1">' . e($r->scheme_code) . '</span>',
                        'hmo_count' => '<span class="badge bg-light text-dark border">' . (int)$r->hmo_count . ' HMOs</span>',
                        'item_count' => '<span class="badge bg-info text-white">' . number_format($r->item_count) . ' Items</span>',
                        'claims' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->total_claims, 2) . '</span>',
                        'payable' => '<span class="font-weight-bold text-primary">₦' . number_format($r->total_payable, 2) . '</span>',
                        'remitted' => '<span class="font-weight-bold text-info">₦' . number_format($r->remitted, 2) . '</span>',
                        'outstanding' => '<span class="font-weight-bold ' . ($outstanding > 0 ? 'text-danger' : 'text-success') . '" style="font-size:1.05rem;">₦' . number_format($outstanding, 2) . '</span>',
                        'validation' => '<span class="badge bg-success">' . (int)$r->approved_count . '</span> / <span class="badge bg-warning text-dark">' . (int)$r->unresolved_count . '</span>',
                    ];
                });

                $totalClaims = $rows->sum('total_claims');
                $cards = [
                    ['label' => 'Total Across All Schemes', 'value' => '₦' . number_format($totalClaims, 2), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Remitted', 'value' => '₦' . number_format($rows->sum('remitted'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Outstanding ₦', 'value' => '₦' . number_format($totalClaims - $rows->sum('remitted'), 2), 'class' => 'bg-danger text-white'],
                    ['label' => 'Schemes Active', 'value' => $rows->count(), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Scheme', 'HMO Providers', 'Items', 'Claims ₦', 'Payable ₦', 'Remitted ₦', 'Outstanding ₦', 'Appr / Unresolved']]);

            case 'coverage-mode-analysis':
                $rows = \DB::table('product_or_service_requests as posr')
                    ->join('hmos as h', 'posr.hmo_id', '=', 'h.id')
                    ->join('hmo_schemes as hs', 'h.hmo_scheme_id', '=', 'hs.id')
                    ->select(
                        \DB::raw('COALESCE(posr.coverage_mode, "none") as coverage_mode'),
                        \DB::raw('COUNT(posr.id) as item_count'),
                        \DB::raw('SUM(CASE WHEN posr.claims_amount > 0 THEN posr.claims_amount ELSE 0 END) as total_claims'),
                        \DB::raw('SUM(posr.payable_amount) as total_payable')
                    )
                    ->whereNotNull('posr.hmo_id')
                    ->whereBetween('posr.created_at', [$startDate, $endDate])
                    ->groupByRaw('COALESCE(posr.coverage_mode, "none")')
                    ->get();

                $modeConfig = ['primary' => 'bg-primary text-white', 'secondary' => 'bg-purple text-white', 'express' => 'bg-warning text-dark', 'none' => 'bg-secondary text-white'];

                $formattedRows = $rows->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="hmo-nhis" data-story="coverage-mode-analysis" data-key="' . e($r->coverage_mode) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'mode' => '<span class="badge ' . ($modeConfig[$r->coverage_mode] ?? 'bg-secondary text-white') . ' px-2 py-1">' . ucfirst($r->coverage_mode) . '</span>',
                    'items' => '<span class="badge bg-light text-dark border font-weight-bold">' . number_format($r->item_count) . ' Items</span>',
                    'claims' => '<span class="font-weight-bold text-success">₦' . number_format($r->total_claims, 2) . '</span>',
                    'payable' => '<span class="font-weight-bold text-primary">₦' . number_format($r->total_payable, 2) . '</span>',
                ]);

                $cards = [
                    ['label' => 'Primary Coverage ₦', 'value' => '₦' . number_format($rows->firstWhere('coverage_mode', 'primary')?->total_claims ?? 0, 2), 'class' => 'bg-primary text-white'],
                    ['label' => 'Secondary Coverage ₦', 'value' => '₦' . number_format($rows->firstWhere('coverage_mode', 'secondary')?->total_claims ?? 0, 2), 'class' => 'bg-info text-white'],
                    ['label' => 'Express Coverage ₦', 'value' => '₦' . number_format($rows->firstWhere('coverage_mode', 'express')?->total_claims ?? 0, 2), 'class' => 'bg-warning text-dark'],
                    ['label' => 'Cash / None ₦', 'value' => '₦' . number_format($rows->firstWhere('coverage_mode', 'none')?->total_payable ?? 0, 2), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Coverage Mode', 'Items', 'Claims ₦', 'Payable ₦']]);

            case 'remittance-vs-claims-matching':
                $rows = \App\Models\HmoRemittance::with(['hmo.scheme', 'bank', 'claims'])
                    ->whereBetween('payment_date', [$startDate, $endDate])
                    ->get();

                $formattedRows = $rows->map(function ($r) {
                    // Domain logic from HmoReportsController: sum claims_amount linked to hmo_remittance_id
                    $linkedClaims = $r->claims->sum(fn($c) => $c->claims_amount > 0 ? $c->claims_amount : $c->payable_amount);
                    $variance = $r->amount - $linkedClaims;
                    $hmoName = $r->hmo?->name ?? 'Unknown HMO';
                    $schemeName = $r->hmo?->scheme?->name ?? 'Standard Scheme';
                    $schemeCode = $r->hmo?->scheme?->code ? (' (' . $r->hmo->scheme->code . ')') : '';

                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="hmo-nhis" data-story="remittance-vs-claims-matching" data-key="' . e($r->id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'hmo' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-shield-account text-primary"></i> ' . e($hmoName) . '</div><span class="badge bg-info text-white mt-1">' . e($schemeName) . e($schemeCode) . '</span>',
                        'remittance' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->amount, 2) . '</span>',
                        'period' => '<small class="text-muted">' . ($r->period_from ? \Carbon\Carbon::parse($r->period_from)->format('M d') : 'N/A') . ' — ' . ($r->period_to ? \Carbon\Carbon::parse($r->period_to)->format('M d, Y') : 'N/A') . '</small>',
                        'bank' => '<span class="badge bg-light text-dark border"><i class="mdi mdi-bank"></i> ' . e($r->bank?->name ?? $r->bank_name ?? 'N/A') . '</span>',
                        'linked_claims' => '<span class="font-weight-bold ' . ($linkedClaims > 0 ? 'text-primary' : 'text-muted') . '">₦' . number_format($linkedClaims, 2) . '</span>' . ($linkedClaims == 0 ? ' <span class="badge bg-warning text-dark ms-1" style="font-size:0.75rem;">Unlinked</span>' : ''),
                        'variance' => '<span class="font-weight-bold ' . (abs($variance) < 1 ? 'text-success' : 'text-danger') . '">₦' . number_format($variance, 2) . '</span>',
                        'ref' => '<small class="text-muted">' . e($r->reference_number ?? 'N/A') . '</small>',
                    ];
                });

                $totalRemitted = $rows->sum('amount');
                $cards = [
                    ['label' => 'Total Remittances', 'value' => $rows->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Remitted ₦', 'value' => '₦' . number_format($totalRemitted, 2), 'class' => 'bg-success text-white'],
                    ['label' => 'HMOs Remitted', 'value' => $rows->pluck('hmo_id')->unique()->count(), 'class' => 'bg-info text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'HMO & Scheme', 'Remittance ₦', 'Period', 'Bank', 'Linked Claims ₦', 'Variance ₦', 'Reference']]);

            default:
                return response()->json(['error' => 'Unknown story: ' . $story], 404);
        }
    }

    // =====================================================================
    // PAGE 9 — SERVICE REGISTERS vs BILLING — Story Data
    // =====================================================================
    public function serviceRegistersStoryData(Request $request, $story)
    {
        if (in_array($story, ['clinical-services', 'billed-services', 'procedures', 'maternity'])) {
            return $this->serviceRegistersBillingData($request, $story);
        }

        [$startDate, $endDate] = $this->parseAuditPeriod($request);

        switch ($story) {

            case 'service-category-revenue':
                $rows = \DB::table('product_or_service_requests as posr')
                    ->join('services as sv', 'posr.service_id', '=', 'sv.id')
                    ->leftJoin('service_categories as sc', 'sv.category_id', '=', 'sc.id')
                    ->select(
                        'sc.id as category_id',
                        'sc.category_name',
                        \DB::raw('COUNT(posr.id) as item_count'),
                        \DB::raw('COUNT(DISTINCT posr.service_id) as unique_services'),
                        \DB::raw('SUM(posr.payable_amount) as total_revenue'),
                        \DB::raw('SUM(CASE WHEN posr.claims_amount > 0 THEN posr.claims_amount ELSE 0 END) as total_claims'),
                        \DB::raw('SUM(CASE WHEN posr.validation_status = "approved" THEN 1 ELSE 0 END) as approved_count'),
                        \DB::raw('SUM(CASE WHEN posr.validation_status = "pending" OR posr.validation_status IS NULL THEN 1 ELSE 0 END) as pending_count')
                    )
                    ->whereNotNull('posr.service_id')
                    ->whereBetween('posr.created_at', [$startDate, $endDate])
                    ->groupBy('sc.id', 'sc.category_name')
                    ->orderByDesc('total_revenue')
                    ->get();

                $formattedRows = $rows->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="service-registers" data-story="service-category-revenue" data-key="' . e($r->category_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'category' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-medical-bag text-primary"></i> ' . e($r->category_name ?? 'Uncategorised') . '</div><small class="text-muted">' . (int)$r->unique_services . ' unique services</small>',
                    'items' => '<span class="badge bg-light text-dark border font-weight-bold">' . number_format($r->item_count) . ' Billings</span>',
                    'revenue' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->total_revenue, 2) . '</span>',
                    'claims' => '<span class="font-weight-bold text-info">₦' . number_format($r->total_claims, 2) . '</span>',
                    'validation' => '<span class="badge bg-success">' . (int)$r->approved_count . ' Appr</span> <span class="badge bg-warning text-dark">' . (int)$r->pending_count . ' Pend</span>',
                ]);

                $cards = [
                    ['label' => 'Top Service Category', 'value' => $rows->first()?->category_name ?? 'N/A', 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Service Revenue', 'value' => '₦' . number_format($rows->sum('total_revenue'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Total HMO Claims', 'value' => '₦' . number_format($rows->sum('total_claims'), 2), 'class' => 'bg-info text-white'],
                    ['label' => 'Unique Categories', 'value' => $rows->count(), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Service Category', 'Billings', 'Revenue ₦', 'HMO Claims ₦', 'Validation']]);

            case 'doctor-referral-billing':
                $rows = \DB::table('product_or_service_requests as posr')
                    ->join('encounters as e', 'posr.encounter_id', '=', 'e.id')
                    ->join('users as u', 'e.doctor_id', '=', 'u.id')
                    ->select(
                        'e.doctor_id',
                        \DB::raw("CONCAT_WS(' ', u.firstname, u.surname) as doctor_name"),
                        \DB::raw('COUNT(DISTINCT posr.encounter_id) as encounter_count'),
                        \DB::raw('COUNT(posr.id) as service_count'),
                        \DB::raw('SUM(posr.payable_amount) as total_billed'),
                        \DB::raw('SUM(CASE WHEN posr.claims_amount > 0 THEN posr.claims_amount ELSE 0 END) as hmo_claims')
                    )
                    ->whereNotNull('posr.encounter_id')
                    ->whereBetween('posr.created_at', [$startDate, $endDate])
                    ->groupBy('e.doctor_id', 'u.firstname', 'u.surname')
                    ->orderByDesc('total_billed')
                    ->get();

                $formattedRows = $rows->map(function ($r) {
                    $avg = $r->encounter_count > 0 ? round($r->total_billed / $r->encounter_count, 2) : 0;
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="service-registers" data-story="doctor-referral-billing" data-key="' . e($r->doctor_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'doctor' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-doctor text-primary"></i> Dr. ' . e($r->doctor_name ?? 'Doctor') . '</div>',
                        'encounters' => '<span class="badge bg-light text-dark border">' . (int)$r->encounter_count . ' Encounters</span>',
                        'services' => '<span class="badge bg-info text-white">' . (int)$r->service_count . ' Services</span>',
                        'total_billed' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">₦' . number_format($r->total_billed, 2) . '</span>',
                        'hmo_claims' => '<span class="font-weight-bold text-info">₦' . number_format($r->hmo_claims, 2) . '</span>',
                        'avg_per_encounter' => '<span class="text-muted font-weight-bold">₦' . number_format($avg, 2) . '</span>',
                    ];
                });

                $cards = [
                    ['label' => 'Top Billing Doctor', 'value' => $rows->first()?->doctor_name ?? 'N/A', 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Revenue Linked', 'value' => '₦' . number_format($rows->sum('total_billed'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Total HMO Claims', 'value' => '₦' . number_format($rows->sum('hmo_claims'), 2), 'class' => 'bg-info text-white'],
                    ['label' => 'Avg Per Encounter', 'value' => '₦' . number_format($rows->sum('encounter_count') > 0 ? $rows->sum('total_billed') / $rows->sum('encounter_count') : 0, 2), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Doctor', 'Encounters', 'Services', 'Total Billed ₦', 'HMO Claims ₦', 'Avg / Encounter']]);

            case 'service-vs-hmo-compliance':
                $rows = \DB::table('product_or_service_requests as posr')
                    ->join('services as sv', 'posr.service_id', '=', 'sv.id')
                    ->join('hmos as h', 'posr.hmo_id', '=', 'h.id')
                    ->join('hmo_schemes as hs', 'h.hmo_scheme_id', '=', 'hs.id')
                    ->select(
                        'posr.service_id',
                        'sv.service_name',
                        \DB::raw('COALESCE(posr.validation_status, "pending") as validation_status'),
                        'hs.name as scheme_name',
                        'hs.code as scheme_code',
                        \DB::raw('COUNT(posr.id) as item_count'),
                        \DB::raw('SUM(CASE WHEN posr.claims_amount > 0 THEN posr.claims_amount ELSE posr.payable_amount END) as total_claims')
                    )
                    ->whereNotNull('posr.service_id')
                    ->whereNotNull('posr.hmo_id')
                    ->where('posr.hmo_id', '!=', 1)
                    ->whereBetween('posr.created_at', [$startDate, $endDate])
                    ->groupBy('posr.service_id', 'sv.service_name', 'validation_status', 'hs.name', 'hs.code')
                    ->orderByDesc('total_claims')
                    ->get();

                $formattedRows = $rows->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="service-registers" data-story="service-vs-hmo-compliance" data-key="' . e($r->service_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'service' => '<div class="font-weight-bold text-dark">' . e($r->service_name) . '</div><span class="badge bg-info text-white mt-1">' . e($r->scheme_name) . ' (' . e($r->scheme_code) . ')</span>',
                    'status' => '<span class="badge ' . ($r->validation_status === 'approved' ? 'bg-success' : ($r->validation_status === 'awaiting_code' ? 'bg-danger' : 'bg-warning text-dark')) . '">' . ucfirst(str_replace('_', ' ', $r->validation_status)) . '</span>',
                    'items' => '<span class="badge bg-light text-dark border">' . (int)$r->item_count . ' Items</span>',
                    'claims' => '<span class="font-weight-bold ' . ($r->validation_status === 'awaiting_code' ? 'text-danger' : 'text-success') . '">₦' . number_format($r->total_claims, 2) . '</span>',
                ]);

                $awaitingRisk = $rows->where('validation_status', 'awaiting_code')->sum('total_claims');
                $cards = [
                    ['label' => 'Approved ₦', 'value' => '₦' . number_format($rows->where('validation_status', 'approved')->sum('total_claims'), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Pending ₦', 'value' => '₦' . number_format($rows->where('validation_status', 'pending')->sum('total_claims'), 2), 'class' => 'bg-warning text-dark'],
                    ['label' => 'Awaiting Code ₦ (At Risk)', 'value' => '₦' . number_format($awaitingRisk, 2), 'class' => 'bg-danger text-white'],
                    ['label' => 'Services Checked', 'value' => $rows->pluck('service_id')->unique()->count(), 'class' => 'bg-primary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Service & Scheme', 'Validation Status', 'Items', 'Claims ₦']]);

            case 'unbilled-encounters':
                $rows = \DB::table('encounters as e')
                    ->leftJoin('product_or_service_requests as posr', 'e.id', '=', 'posr.encounter_id')
                    ->leftJoin('patients as pat', 'e.patient_id', '=', 'pat.id')
                    ->leftJoin('users as pu', 'pat.user_id', '=', 'pu.id')
                    ->leftJoin('users as du', 'e.doctor_id', '=', 'du.id')
                    ->select(
                        'e.id as encounter_id',
                        'e.created_at as encounter_date',
                        \DB::raw("CONCAT_WS(' ', pu.firstname, pu.surname) as patient_name"),
                        'pat.file_no',
                        \DB::raw("CONCAT_WS(' ', du.firstname, du.surname) as doctor_name")
                    )
                    ->whereNull('posr.id')
                    ->whereBetween('e.created_at', [$startDate, $endDate])
                    ->groupBy('e.id', 'e.created_at', 'pu.firstname', 'pu.surname', 'pat.file_no', 'du.firstname', 'du.surname')
                    ->orderBy('e.created_at', 'desc')
                    ->get();

                $totalEncounters = \DB::table('encounters')->whereBetween('created_at', [$startDate, $endDate])->count();
                $avgRevenuePerEncounter = \DB::table('product_or_service_requests')->whereBetween('created_at', [$startDate, $endDate])->whereNotNull('encounter_id')->avg('payable_amount') ?? 0;

                $formattedRows = $rows->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="service-registers" data-story="unbilled-encounters" data-key="' . e($r->encounter_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'date' => '<div class="font-weight-bold">' . \Carbon\Carbon::parse($r->encounter_date)->format('M d, Y') . '</div><small class="text-muted">' . \Carbon\Carbon::parse($r->encounter_date)->format('h:i A') . '</small>',
                    'patient' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-account"></i> ' . e($r->patient_name ?? 'Patient') . '</div><small class="text-muted">File: ' . e($r->file_no ?? 'N/A') . '</small>',
                    'doctor' => '<div class="font-weight-bold text-dark">Dr. ' . e($r->doctor_name ?? 'Doctor') . '</div>',
                    'status' => '<span class="badge bg-danger"><i class="mdi mdi-alert-circle"></i> No Billing</span>',
                ]);

                $cards = [
                    ['label' => 'Unbilled Encounters', 'value' => $rows->count(), 'class' => 'bg-danger text-white'],
                    ['label' => 'Total Encounters in Period', 'value' => $totalEncounters, 'class' => 'bg-primary text-white'],
                    ['label' => 'Unbilled %', 'value' => $totalEncounters > 0 ? round(($rows->count() / $totalEncounters) * 100, 1) . '%' : '0%', 'class' => 'bg-warning text-dark'],
                    ['label' => 'Est. Revenue Gap ₦', 'value' => '₦' . number_format($rows->count() * $avgRevenuePerEncounter, 2), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Encounter Date', 'Patient', 'Doctor', 'Status']]);

            case 'procedure-billing-audit':
                $rows = \DB::table('procedures as pr')
                    ->leftJoin('product_or_service_requests as posr', 'pr.product_or_service_request_id', '=', 'posr.id')
                    ->leftJoin('patients as pat', 'pr.patient_id', '=', 'pat.id')
                    ->leftJoin('users as pu', 'pat.user_id', '=', 'pu.id')
                    ->leftJoin('procedure_definitions as pd', 'pr.procedure_definition_id', '=', 'pd.id')
                    ->leftJoin('procedure_categories as pc', 'pd.procedure_category_id', '=', 'pc.id')
                    ->select(
                        'pc.id as category_id',
                        'pc.name as category_name',
                        \DB::raw('COUNT(pr.id) as procedure_count'),
                        \DB::raw('SUM(CASE WHEN pr.procedure_status = "completed" THEN 1 ELSE 0 END) as completed_count'),
                        \DB::raw('SUM(CASE WHEN posr.payment_id IS NULL AND pr.procedure_status = "completed" THEN 1 ELSE 0 END) as unbilled_completed'),
                        \DB::raw('SUM(CASE WHEN posr.payment_id IS NULL AND pr.procedure_status = "completed" THEN COALESCE(posr.payable_amount, 0) ELSE 0 END) as unbilled_value')
                    )
                    ->whereBetween('pr.created_at', [$startDate, $endDate])
                    ->groupBy('pc.id', 'pc.name')
                    ->orderByDesc('unbilled_completed')
                    ->get();

                $formattedRows = $rows->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="service-registers" data-story="procedure-billing-audit" data-key="' . e($r->category_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'category' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-surgery text-primary"></i> ' . e($r->category_name ?? 'Uncategorised') . '</div>',
                    'total' => '<span class="badge bg-light text-dark border">' . (int)$r->procedure_count . ' Total</span>',
                    'completed' => '<span class="badge bg-success">' . (int)$r->completed_count . ' Completed</span>',
                    'unbilled' => '<span class="badge ' . ($r->unbilled_completed > 0 ? 'bg-danger' : 'bg-success') . ' font-weight-bold">' . (int)$r->unbilled_completed . ' Unbilled</span>',
                    'gap_value' => '<span class="font-weight-bold ' . ($r->unbilled_value > 0 ? 'text-danger' : 'text-success') . '">₦' . number_format($r->unbilled_value, 2) . '</span>',
                ]);

                $cards = [
                    ['label' => 'Total Procedures', 'value' => $rows->sum('procedure_count'), 'class' => 'bg-primary text-white'],
                    ['label' => 'Completed', 'value' => $rows->sum('completed_count'), 'class' => 'bg-success text-white'],
                    ['label' => 'Completed but Unbilled', 'value' => $rows->sum('unbilled_completed'), 'class' => 'bg-danger text-white'],
                    ['label' => 'Unbilled Revenue Gap ₦', 'value' => '₦' . number_format($rows->sum('unbilled_value'), 2), 'class' => 'bg-warning text-dark'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Procedure Category', 'Total', 'Completed', 'Unbilled', 'Revenue Gap ₦']]);

            default:
                return response()->json(['error' => 'Unknown story: ' . $story], 404);
        }
    }

    // =====================================================================
    // PAGE 10 — PHARMACY & MORTUARY — Story Data
    // =====================================================================
    public function pharmacyMortuaryStoryData(Request $request, $story)
    {
        if (in_array($story, ['pharmacy-dispense', 'ward-direct-billing', 'mortuary'])) {
            return $this->pharmacyMortuaryData($request, $story);
        }

        [$startDate, $endDate] = $this->parseAuditPeriod($request);

        switch ($story) {

            case 'dispenser-performance':
                $rows = \DB::table('product_requests as pr')
                    ->join('users as u', 'pr.dispensed_by', '=', 'u.id')
                    ->select(
                        'pr.dispensed_by',
                        \DB::raw("CONCAT_WS(' ', u.firstname, u.surname) as dispenser_name"),
                        \DB::raw('COUNT(pr.id) as dispense_count'),
                        \DB::raw('SUM(pr.qty) as total_qty'),
                        \DB::raw('SUM(CASE WHEN (pr.is_adapted = 1 OR pr.adapted_from_product_id IS NOT NULL) THEN 1 ELSE 0 END) as adapted_count'),
                        \DB::raw('SUM(CASE WHEN pr.qty_adjusted_from IS NOT NULL THEN 1 ELSE 0 END) as qty_adjusted_count'),
                        \DB::raw('SUM(CASE WHEN pr.returned_qty > 0 THEN 1 ELSE 0 END) as return_count')
                    )
                    ->whereNotNull('pr.dispensed_by')
                    ->whereNull('pr.deleted_at')
                    ->whereBetween('pr.created_at', [$startDate, $endDate])
                    ->groupBy('pr.dispensed_by', 'u.firstname', 'u.surname')
                    ->orderByDesc('dispense_count')
                    ->get();

                $formattedRows = $rows->map(function ($r) {
                    $adaptRate = $r->dispense_count > 0 ? round(($r->adapted_count / $r->dispense_count) * 100, 1) : 0;
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="pharmacy-mortuary" data-story="dispenser-performance" data-key="' . e($r->dispensed_by) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'pharmacist' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-account-tie text-primary"></i> ' . e($r->dispenser_name) . '</div>',
                        'dispenses' => '<span class="badge bg-success font-weight-bold">' . number_format($r->dispense_count) . ' Dispenses</span>',
                        'qty' => '<span class="badge bg-info text-white">' . number_format($r->total_qty) . ' Units</span>',
                        'adapted' => '<span class="badge ' . ($r->adapted_count > 0 ? 'bg-warning text-dark' : 'bg-light text-dark border') . '">' . (int)$r->adapted_count . ' Adapted</span>',
                        'adjusted' => '<span class="badge ' . ($r->qty_adjusted_count > 0 ? 'bg-info text-white' : 'bg-light text-dark border') . '">' . (int)$r->qty_adjusted_count . ' Qty-Adj</span>',
                        'returns' => '<span class="badge ' . ($r->return_count > 0 ? 'bg-danger' : 'bg-light text-dark border') . '">' . (int)$r->return_count . ' Returns</span>',
                        'adapt_rate' => '<span class="font-weight-bold ' . ($adaptRate > 5 ? 'text-warning' : 'text-success') . '">' . $adaptRate . '%</span>',
                    ];
                });

                $cards = [
                    ['label' => 'Active Dispensers', 'value' => $rows->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Dispenses', 'value' => number_format($rows->sum('dispense_count')), 'class' => 'bg-success text-white'],
                    ['label' => 'Total Adaptations', 'value' => number_format($rows->sum('adapted_count')), 'class' => 'bg-warning text-dark'],
                    ['label' => 'Total Returns', 'value' => number_format($rows->sum('return_count')), 'class' => 'bg-danger text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Pharmacist', 'Dispenses', 'Total Qty', 'Adapted', 'Qty-Adjusted', 'Returns', 'Adapt Rate']]);

            case 'prescription-adaptation-audit':
                $rows = \DB::table('product_requests as pr')
                    ->join('products as orig', 'pr.adapted_from_product_id', '=', 'orig.id')
                    ->join('products as sub', 'pr.product_id', '=', 'sub.id')
                    ->leftJoin('product_categories as pc', 'sub.category_id', '=', 'pc.id')
                    ->leftJoin('users as u', 'pr.adapted_by', '=', 'u.id')
                    ->select(
                        'pr.adapted_from_product_id',
                        'orig.product_name as original_drug',
                        'pr.product_id as substituted_id',
                        'sub.product_name as substituted_drug',
                        'pc.category_name',
                        \DB::raw('COUNT(pr.id) as adaptation_count'),
                        \DB::raw("CONCAT_WS(' ', u.firstname, u.surname) as most_recent_pharmacist")
                    )
                    ->where(function($q) {
                        $q->where('pr.is_adapted', 1)
                          ->orWhereNotNull('pr.adapted_from_product_id');
                    })
                    ->whereNull('pr.deleted_at')
                    ->whereBetween('pr.created_at', [$startDate, $endDate])
                    ->groupBy('pr.adapted_from_product_id', 'orig.product_name', 'pr.product_id', 'sub.product_name', 'pc.category_name', 'u.firstname', 'u.surname')
                    ->orderByDesc('adaptation_count')
                    ->get();

                $formattedRows = $rows->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="pharmacy-mortuary" data-story="prescription-adaptation-audit" data-key="' . e($r->adapted_from_product_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'original' => '<div class="font-weight-bold text-danger"><i class="mdi mdi-close-circle text-danger"></i> ' . e($r->original_drug) . '</div>',
                    'substituted' => '<div class="font-weight-bold text-success"><i class="mdi mdi-check-circle text-success"></i> ' . e($r->substituted_drug) . '</div><small class="text-muted">' . e($r->category_name ?? 'N/A') . '</small>',
                    'count' => '<span class="badge bg-warning text-dark font-weight-bold px-2 py-1">' . (int)$r->adaptation_count . 'x Adapted</span>',
                    'pharmacist' => '<small class="text-muted">' . e($r->most_recent_pharmacist ?? 'N/A') . '</small>',
                ]);

                $cards = [
                    ['label' => 'Total Adaptations', 'value' => $rows->sum('adaptation_count'), 'class' => 'bg-warning text-dark'],
                    ['label' => 'Unique Drugs Substituted', 'value' => $rows->pluck('substituted_id')->unique()->count(), 'class' => 'bg-primary text-white'],
                    ['label' => 'Most Substituted Drug', 'value' => $rows->first()?->original_drug ?? 'N/A', 'class' => 'bg-danger text-white'],
                    ['label' => 'Original Drugs Involved', 'value' => $rows->pluck('adapted_from_product_id')->unique()->count(), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Original Drug', 'Substituted With', 'Adaptation Count', 'Pharmacist']]);

            case 'ward-consumable-billing-kit':
                $rows = \DB::table('stock_utilizations as su')
                    ->join('products as p', 'su.product_id', '=', 'p.id')
                    ->leftJoin('product_categories as pc', 'p.category_id', '=', 'pc.id')
                    ->join('stores as s', 'su.store_id', '=', 's.id')
                    ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
                    ->leftJoin('patients as pat', 'su.patient_id', '=', 'pat.id')
                    ->leftJoin('users as pu', 'pat.user_id', '=', 'pu.id')
                    ->select(
                        'su.store_id',
                        's.store_name',
                        's.distribution_role',
                        'su.product_id',
                        'p.product_name',
                        'pc.category_name',
                        \DB::raw('SUM(su.qty) as total_consumed'),
                        \DB::raw('SUM(CASE WHEN su.is_billed = 1 THEN su.qty ELSE 0 END) as billed_qty'),
                        \DB::raw('SUM(CASE WHEN su.is_billed = 0 OR su.is_billed IS NULL THEN su.qty ELSE 0 END) as unbilled_qty'),
                        \DB::raw('COALESCE(pp.current_sale_price, 0) as sell_price')
                    )
                    ->whereBetween('su.created_at', [$startDate, $endDate])
                    ->groupBy('su.store_id', 's.store_name', 's.distribution_role', 'su.product_id', 'p.product_name', 'pc.category_name', 'pp.current_sale_price')
                    ->orderByDesc('total_consumed')
                    ->get();

                $roleLabels = \App\Models\Store::ROLE_LABELS;
                $formattedRows = $rows->map(function ($r) use ($roleLabels) {
                    $gapValue = round($r->unbilled_qty * $r->sell_price, 2);
                    return [
                        'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="pharmacy-mortuary" data-story="ward-consumable-billing-kit" data-key="' . e($r->store_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                        'store_product' => '<div class="font-weight-bold text-dark">' . e($r->product_name) . '</div><small class="text-muted"><i class="mdi mdi-store"></i> ' . e($r->store_name) . ' <span class="badge bg-light text-dark border">' . e($roleLabels[$r->distribution_role] ?? $r->distribution_role) . '</span></small>',
                        'category' => '<span class="badge bg-info text-white">' . e($r->category_name ?? 'N/A') . '</span>',
                        'consumed' => '<span class="badge bg-primary text-white font-weight-bold">' . number_format($r->total_consumed) . ' Units</span>',
                        'billed' => '<span class="badge bg-success">' . number_format($r->billed_qty) . ' Billed</span>',
                        'unbilled' => '<span class="badge ' . ($r->unbilled_qty > 0 ? 'bg-danger' : 'bg-success') . ' font-weight-bold">' . number_format($r->unbilled_qty) . ' Unbilled</span>',
                        'gap_value' => '<span class="font-weight-bold ' . ($gapValue > 0 ? 'text-danger' : 'text-success') . '">₦' . number_format($gapValue, 2) . '</span>',
                    ];
                });

                $cards = [
                    ['label' => 'Total Consumed Qty', 'value' => number_format($rows->sum('total_consumed')), 'class' => 'bg-primary text-white'],
                    ['label' => 'Billed Consumables ₦', 'value' => '₦' . number_format($rows->sum(fn($r) => $r->billed_qty * $r->sell_price), 2), 'class' => 'bg-success text-white'],
                    ['label' => 'Unbilled Qty', 'value' => number_format($rows->sum('unbilled_qty')), 'class' => 'bg-danger text-white'],
                    ['label' => 'Unbilled Revenue Gap ₦', 'value' => '₦' . number_format($rows->sum(fn($r) => $r->unbilled_qty * $r->sell_price), 2), 'class' => 'bg-warning text-dark'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Store / Product', 'Category', 'Consumed', 'Billed', 'Unbilled', 'Revenue Gap ₦']]);

            case 'drug-category-dispensing':
                $rows = \DB::table('product_requests as pr')
                    ->join('products as p', 'pr.product_id', '=', 'p.id')
                    ->leftJoin('product_categories as pc', 'p.category_id', '=', 'pc.id')
                    ->leftJoin('product_packagings as pkg', 'pr.packaging_id', '=', 'pkg.id')
                    ->join('stores as s', 'pr.dispensed_from_store_id', '=', 's.id')
                    ->select(
                        'pc.id as category_id',
                        'pc.category_name',
                        \DB::raw('COUNT(pr.id) as dispense_count'),
                        \DB::raw('SUM(pr.qty) as total_base_qty'),
                        \DB::raw('COUNT(DISTINCT pr.patient_id) as unique_patients'),
                        \DB::raw('MAX(pkg.name) as packaging_name')
                    )
                    ->whereIn('s.distribution_role', ['pharmacy_hub', 'pharmacy_satellite'])
                    ->whereNull('pr.deleted_at')
                    ->whereBetween('pr.created_at', [$startDate, $endDate])
                    ->groupBy('pc.id', 'pc.category_name')
                    ->orderByDesc('total_base_qty')
                    ->get();

                $formattedRows = $rows->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="pharmacy-mortuary" data-story="drug-category-dispensing" data-key="' . e($r->category_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'category' => '<div class="font-weight-bold text-dark"><i class="mdi mdi-pill text-primary"></i> ' . e($r->category_name ?? 'Uncategorised') . '</div>',
                    'dispenses' => '<span class="badge bg-light text-dark border font-weight-bold">' . number_format($r->dispense_count) . ' Events</span>',
                    'qty' => '<span class="font-weight-bold text-success" style="font-size:1.05rem;">' . number_format($r->total_base_qty) . ' Base Units</span>',
                    'patients' => '<span class="badge bg-info text-white">' . number_format($r->unique_patients) . ' Patients</span>',
                ]);

                $cards = [
                    ['label' => 'Top Drug Category', 'value' => $rows->first()?->category_name ?? 'N/A', 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Qty Dispensed', 'value' => number_format($rows->sum('total_base_qty')) . ' Units', 'class' => 'bg-success text-white'],
                    ['label' => 'Unique Patients Served', 'value' => number_format($rows->sum('unique_patients')), 'class' => 'bg-info text-white'],
                    ['label' => 'Dispense Events', 'value' => number_format($rows->sum('dispense_count')), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Drug Category', 'Dispense Events', 'Total Qty', 'Unique Patients']]);

            case 'return-damage-write-off':
                // Source A: Pharmacy & Patient Returns (ProductRequest where returned_qty > 0 or return_reason is set)
                $pharmacyReturns = \DB::table('product_requests as pr')
                    ->join('products as p', 'pr.product_id', '=', 'p.id')
                    ->leftJoin('product_categories as pc', 'p.category_id', '=', 'pc.id')
                    ->leftJoin('stock_batches as sb', 'pr.dispensed_from_batch_id', '=', 'sb.id')
                    ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
                    ->select(
                        'pr.product_id',
                        'p.product_name',
                        'pc.category_name',
                        \DB::raw('SUM(COALESCE(pr.returned_qty, 1)) as qty_returned'),
                        \DB::raw('SUM(COALESCE(pr.refund_amount, 0)) as refund_amount'),
                        \DB::raw('SUM(COALESCE(pr.returned_qty, 1) * COALESCE(NULLIF(sb.cost_price, 0), pp.pr_buy_price, 0)) as cost_of_return'),
                        \DB::raw('"pharmacy_return" as loss_type')
                    )
                    ->where(function($q) {
                        $q->where('pr.returned_qty', '>', 0)
                          ->orWhereNotNull('pr.return_reason');
                    })
                    ->whereNull('pr.deleted_at')
                    ->whereBetween('pr.created_at', [$startDate, $endDate])
                    ->groupBy('pr.product_id', 'p.product_name', 'pc.category_name')
                    ->get();

                // Source B: Stock Batch Transactions — damaged, expired, req_return, write_off
                $writeOffs = \DB::table('stock_batch_transactions as sbt')
                    ->join('stock_batches as sb', 'sbt.stock_batch_id', '=', 'sb.id')
                    ->join('products as p', 'sb.product_id', '=', 'p.id')
                    ->leftJoin('product_categories as pc', 'p.category_id', '=', 'pc.id')
                    ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
                    ->select(
                        'sb.product_id',
                        'p.product_name',
                        'pc.category_name',
                        \DB::raw('SUM(sbt.qty) as qty_returned'),
                        \DB::raw('0 as refund_amount'),
                        \DB::raw('SUM(sbt.qty * COALESCE(NULLIF(sb.cost_price, 0), pp.pr_buy_price, 0)) as cost_of_return'),
                        \DB::raw('sbt.type as loss_type')
                    )
                    ->whereIn('sbt.type', ['damaged', 'expired', 'req_return', 'return', 'write_off'])
                    ->whereBetween('sbt.created_at', [$startDate, $endDate])
                    ->groupBy('sb.product_id', 'p.product_name', 'pc.category_name', 'sbt.type')
                    ->get();

                // Source C: Expired Stock Inventory Losses (Batches expired before now with positive stock)
                $expiredStock = \DB::table('stock_batches as sb')
                    ->join('products as p', 'sb.product_id', '=', 'p.id')
                    ->leftJoin('product_categories as pc', 'p.category_id', '=', 'pc.id')
                    ->leftJoin('prices as pp', 'p.id', '=', 'pp.product_id')
                    ->select(
                        'sb.product_id',
                        'p.product_name',
                        'pc.category_name',
                        \DB::raw('SUM(sb.current_qty) as qty_returned'),
                        \DB::raw('0 as refund_amount'),
                        \DB::raw('SUM(sb.current_qty * COALESCE(NULLIF(sb.cost_price, 0), pp.pr_buy_price, 0)) as cost_of_return'),
                        \DB::raw('"expired_batch" as loss_type')
                    )
                    ->where('sb.expiry_date', '<', now())
                    ->where('sb.current_qty', '>', 0)
                    ->groupBy('sb.product_id', 'p.product_name', 'pc.category_name')
                    ->get();

                $allLosses = $pharmacyReturns->concat($writeOffs)->concat($expiredStock)->sortByDesc('cost_of_return');

                $lossTypeConfig = [
                    'pharmacy_return' => ['bg-primary', 'mdi-arrow-u-left-top'],
                    'req_return' => ['bg-info text-white', 'mdi-tray-arrow-down'],
                    'damaged' => ['bg-warning text-dark', 'mdi-alert'],
                    'expired' => ['bg-danger', 'mdi-calendar-remove'],
                    'expired_batch' => ['bg-danger', 'mdi-calendar-alert']
                ];

                $formattedRows = $allLosses->map(fn($r) => [
                    'action' => '<button class="btn btn-xs btn-outline-primary story-detail-btn font-weight-bold py-1 px-2" data-zone="pharmacy-mortuary" data-story="return-damage-write-off" data-key="' . e($r->product_id) . '"><i class="mdi mdi-eye"></i> Details</button>',
                    'product' => '<div class="font-weight-bold text-dark">' . e($r->product_name) . '</div><small class="text-muted">' . e($r->category_name ?? 'N/A') . '</small>',
                    'type' => '<span class="badge ' . ($lossTypeConfig[$r->loss_type][0] ?? 'bg-secondary') . '"><i class="mdi ' . ($lossTypeConfig[$r->loss_type][1] ?? 'mdi-help') . '"></i> ' . ucfirst(str_replace('_', ' ', $r->loss_type)) . '</span>',
                    'qty' => '<span class="font-weight-bold text-dark">' . number_format($r->qty_returned) . ' Units</span>',
                    'refund' => '<span class="font-weight-bold text-danger">₦' . number_format($r->refund_amount, 2) . '</span>',
                    'cost_value' => '<span class="font-weight-bold text-warning">₦' . number_format($r->cost_of_return, 2) . '</span>',
                ]);

                $cards = [
                    ['label' => 'Pharmacy Returns ₦', 'value' => '₦' . number_format($pharmacyReturns->sum('cost_of_return'), 2), 'class' => 'bg-primary text-white'],
                    ['label' => 'Total Refunds Paid', 'value' => '₦' . number_format($pharmacyReturns->sum('refund_amount'), 2), 'class' => 'bg-danger text-white'],
                    ['label' => 'Write-Off & Expired ₦', 'value' => '₦' . number_format($writeOffs->sum('cost_of_return') + $expiredStock->sum('cost_of_return'), 2), 'class' => 'bg-warning text-dark'],
                    ['label' => 'Combined Loss ₦', 'value' => '₦' . number_format($allLosses->sum('cost_of_return'), 2), 'class' => 'bg-secondary text-white'],
                ];
                return response()->json(['cards' => $cards, 'rows' => $formattedRows->values(), 'headers' => ['Action', 'Product', 'Loss Type', 'Qty', 'Refund Paid ₦', 'Cost ₦']]);

            default:
                return response()->json(['error' => 'Unknown story: ' . $story], 404);
        }
    }

    public function queriesDashboardData(Request $request, $tab)
    {

        $startDate = $request->start_date ? \Carbon\Carbon::parse($request->start_date)->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate = $request->end_date ? \Carbon\Carbon::parse($request->end_date)->endOfDay() : now()->endOfDay();

        $query = \App\Models\AuditMark::with(['auditor', 'resolver'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($tab === 'active-queries') {
            $query->where('status', 'queried');
        } elseif ($tab === 'resolved-queries') {
            $query->where('status', 'resolved');
        }

        return DataTables::eloquent($query)
            ->editColumn('created_at', function($r) {
                return '<div class="font-weight-bold">' . $r->created_at->format('M d, Y') . '</div><small class="text-muted"><i class="mdi mdi-clock-outline"></i> ' . $r->created_at->format('h:i A') . '</small>';
            })
            ->addColumn('record_details', function($r) {
                $type = class_basename($r->auditable_type);
                $link = '<small class="text-primary">ID: ' . $r->auditable_id . '</small>';
                return '<div class="font-weight-bold text-dark">' . $type . '</div>' . $link . '<br><small class="text-muted">Zone: ' . ($r->zone_key ?? 'General') . '</small>';
            })
            ->addColumn('query_info', function($r) {
                $auditor = $r->auditor->name ?? 'Auditor';
                return '<div class="font-weight-bold text-danger">' . \Illuminate\Support\Str::limit($r->query_notes, 50) . '</div><small class="text-muted">By: ' . $auditor . '</small>';
            })
            ->addColumn('status_badge', function($r) {
                if ($r->status === 'resolved') {
                    return '<span class="badge bg-success">Resolved</span><br><small class="text-muted">By: ' . ($r->resolver->name ?? 'Unknown') . '</small>';
                }
                return '<span class="badge bg-warning text-dark">Active Query</span>';
            })
            ->addColumn('action', function($r) {
                return '<button class="btn btn-sm btn-outline-primary" onclick="viewQueryDetails(' . $r->id . ')"><i class="mdi mdi-eye"></i> View</button>';
            })
            ->rawColumns(['created_at', 'record_details', 'query_info', 'status_badge', 'action'])
            ->make(true);
    }
    protected function interceptBulkStamp($query, \Illuminate\Http\Request $request, $modelType, $zoneKey)
    {
        if ($request->action === 'bulk_stamp') {
            try {
                $table = (new $modelType)->getTable();
                $ids = $query->pluck($table . '.id')->toArray();
            } catch (\Exception $e) {
                $ids = $query->pluck('id')->toArray(); 
            }
            
            $bulkRequest = new \Illuminate\Http\Request();
            $bulkRequest->merge([
                'model_type' => class_basename($modelType),
                'ids' => $ids,
                'zone_key' => $zoneKey
            ]);
            $bulkRequest->setUserResolver(fn() => $request->user());
            
            $response = app(\App\Http\Controllers\AuditMarkController::class)->bulkStamp($bulkRequest);
            response()->json(json_decode($response->getContent(), true))->send();
            exit;
        }
        
        if ($request->action === 'bulk_stamp_preview') {
            try {
                $table = (new $modelType)->getTable();
                $ids = $query->pluck($table . '.id')->toArray();
            } catch (\Exception $e) {
                $ids = $query->pluck('id')->toArray(); 
            }
            
            $modelClass = '\\App\\Models\\' . class_basename($modelType);
            
            $queriedCount = \App\Models\AuditMark::where('auditable_type', $modelClass)
                ->whereIn('auditable_id', $ids)
                ->where('status', 'queried')
                ->whereNull('query_resolved_at')
                ->count();
                
            $validCount = count($ids) - $queriedCount;
            
            response()->json([
                'success' => true,
                'total' => count($ids),
                'valid' => $validCount,
                'queried' => $queriedCount
            ])->send();
            exit;
        }
        
        return $query;
    }

    protected function renderAuditAction($record, $modelType)
    {
        $html = '<div class="d-flex gap-1 flex-wrap">';
        
        $activeQuery = method_exists($record, 'activeQuery') ? $record->activeQuery : null;
        $latestAudit = method_exists($record, 'latestAudit') ? $record->latestAudit : null;
        // Check if there was ever a resolved query using auditMarks if loaded, otherwise null
        $resolvedQuery = method_exists($record, 'auditMarks') && $record->relationLoaded('auditMarks') 
                            ? $record->auditMarks->where('status', 'queried')->whereNotNull('query_resolved_at')->first() 
                            : null;

        if ($activeQuery) {
            $html .= '<button class="btn btn-sm btn-warning text-dark font-weight-bold" onclick="openResolveQueryModal(\'' . $modelType . '\', ' . $record->id . ')" title="Queried by ' . ($activeQuery->auditor->name ?? 'System') . ': ' . htmlspecialchars($activeQuery->query_notes) . '"><i class="mdi mdi-alert-circle"></i> Resolve Query</button>';
        } else {
            if ($latestAudit) {
                $html .= '<button class="btn btn-sm btn-success disabled" title="Audited by ' . ($latestAudit->auditor->name ?? 'System') . '"><i class="mdi mdi-check-decagram"></i> Audited</button>';
            } else {
                $html .= '<button class="btn btn-sm btn-outline-success audit-tick-btn" onclick="markAudited(this, \'' . $modelType . '\', ' . $record->id . ')" title="Mark as Audited"><i class="mdi mdi-check"></i> Stamp</button>';
            }
            $html .= '<button class="btn btn-sm btn-outline-warning" onclick="openRaiseQueryModal(\'' . $modelType . '\', ' . $record->id . ')" title="Raise Query"><i class="mdi mdi-flag"></i> Flag</button>';
        }
        $html .= '</div>';
        if ($latestAudit) {
            $html .= '<small class="d-block text-success mt-1"><i class="mdi mdi-check-all"></i> Stamped ' . $latestAudit->created_at->diffForHumans() . '</small>';
        }
        if ($resolvedQuery && !$activeQuery) {
             $html .= '<small class="d-block text-info mt-1"><i class="mdi mdi-information"></i> Had query (Resolved)</small>';
        }
        return $html;
    }
}
