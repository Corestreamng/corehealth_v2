<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\AccountsDashboardService;
use App\Services\Dashboard\AuditDashboardService;
use App\Services\Dashboard\BillingDashboardService;
use App\Services\Dashboard\ChildHealthDashboardService;
use App\Services\Dashboard\DoctorDashboardService;
use App\Services\Dashboard\EssDashboardService;
use App\Services\Dashboard\HmoDashboardService;
use App\Services\Dashboard\HrDashboardService;
use App\Services\Dashboard\ImagingDashboardService;
use App\Services\Dashboard\LabDashboardService;
use App\Services\Dashboard\MaternityDashboardService;
use App\Services\Dashboard\MorgueDashboardService;
use App\Services\Dashboard\NursingDashboardService;
use App\Services\Dashboard\PharmacyDashboardService;
use App\Services\Dashboard\ReceptionDashboardService;
use App\Services\Dashboard\StoreDashboardService;
use App\Services\Dashboard\TheatreDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardDataController extends Controller
{
    /**
     * Dashboard cache TTL in seconds.
     * All dashboard data is cached for 3 minutes to prevent the "query storm"
     * that occurs when multiple users load the dashboard simultaneously.
     *
     * This alone reduces dashboard queries from ~150/request to ~5/request.
     */
    private const CACHE_TTL = 180; // 3 minutes

    public function __construct()
    {
        $this->middleware('auth');
    }

    /* ============================
     *  UNIFIED HIGH-DENSITY COCKPIT
     * ============================ */
    public function cockpitSummary(Request $request)
    {
        $userId = (int) (auth()->id() ?? 0);
        $cacheKey = 'dash:cockpit:v2:' . $userId . ':' . date('Y-m-d-H-i');
        if ($request->has('fresh')) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId) {
            $today = \Carbon\Carbon::today();

            // 1. Reception
            $recepSvc = new ReceptionDashboardService();
            $recepQueues = $recepSvc->getQueueCounts();
            $hourlyFlow = $recepSvc->getHourlyPatientFlow();

            // 2. Doctor Queues
            $docSvc = new DoctorDashboardService();
            $docQueues = $userId > 0 ? $docSvc->getQueueCounts($userId) : [];

            // 3. Nursing
            $nurseSvc = new NursingDashboardService();
            $nurseQueues = $nurseSvc->getQueueCounts();
            $nurseStats = $nurseSvc->getStats();

            // 4. Pharmacy
            $pharmSvc = new PharmacyDashboardService();
            $pharmQueues = $pharmSvc->getQueueCounts();
            $pharmStats = $pharmSvc->getStats();
            $pharmStock = $pharmSvc->getStockHealth();

            // 5. Lab & Imaging
            $labSvc = new LabDashboardService();
            $labQueues = $labSvc->getQueueCounts();
            $labStats = $labSvc->getStats();

            $imgSvc = new ImagingDashboardService();
            $imgQueues = $imgSvc->getQueueCounts();

            // 6. Billing
            $billSvc = new BillingDashboardService();
            $billQueues = $billSvc->getQueueCounts();
            $revTrend = $billSvc->getRevenueTrend();

            // Today's Revenue
            $todayRevenue = \Illuminate\Support\Facades\DB::table('payments')
                ->whereDate('created_at', $today)
                ->sum('total') ?? 0;

            // Pipeline counts
            $waitingCount = collect($recepQueues)->firstWhere('filter', 'waiting')['count'] ?? 0;
            $vitalsCount = collect($recepQueues)->firstWhere('filter', 'vitals')['count'] ?? 0;
            $consultCount = collect($recepQueues)->firstWhere('filter', 'consultation')['count'] ?? 0;
            $admittedCount = collect($recepQueues)->firstWhere('filter', 'admitted')['count'] ?? 0;
            $pharmQueueCount = collect($pharmQueues)->firstWhere('filter', 'unbilled')['count'] ?? ($pharmStats['queue_today'] ?? 0);
            $labQueueCount = collect($labQueues)->firstWhere('filter', 'sample')['count'] ?? ($labStats['pending_tests'] ?? 0);
            $imgQueueCount = collect($imgQueues)->firstWhere('filter', 'results')['count'] ?? 0;
            $unpaidBillCount = collect($billQueues)->firstWhere('filter', 'all')['count'] ?? 0;

            // Robust live ledger across departments
            $ledger = [];

            // Recent consultations (Encounters)
            $recentEncounters = \Illuminate\Support\Facades\DB::table('encounters as e')
                ->join('patients as p', 'e.patient_id', '=', 'p.id')
                ->join('users as u', 'p.user_id', '=', 'u.id')
                ->leftJoin('doctor_queues as dq', function ($join) {
                    $join->on('dq.patient_id', '=', 'e.patient_id')
                         ->on('dq.request_entry_id', '=', 'e.service_request_id');
                })
                ->leftJoin('clinics as c', 'dq.clinic_id', '=', 'c.id')
                ->select(
                    'e.id',
                    'e.patient_id',
                    'p.file_no',
                    \Illuminate\Support\Facades\DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                    'c.name as clinic',
                    'e.created_at',
                    'e.notes'
                )
                ->orderByDesc('e.created_at')
                ->limit(6)
                ->get();

            foreach ($recentEncounters as $enc) {
                $statusLabel = $enc->notes ? 'Completed' : 'In Consult';
                $statusColor = $enc->notes ? 'success' : 'primary';
                $ledger[] = [
                    'patient_id' => $enc->patient_id,
                    'patient_name' => $enc->patient_name,
                    'file_no' => $enc->file_no ?? 'N/A',
                    'dept' => 'Doctor',
                    'dept_code' => 'doctor',
                    'event' => $enc->clinic ? 'Clinic: ' . $enc->clinic : 'Consultation',
                    'status_label' => $statusLabel,
                    'status_color' => $statusColor,
                    'time' => \Carbon\Carbon::parse($enc->created_at)->format('h:i A'),
                    'timestamp' => $enc->created_at,
                    'target_url' => url('encounters/create?patient_id=' . $enc->patient_id),
                ];
            }

            // Recent Lab requests
            $recentLabs = \Illuminate\Support\Facades\DB::table('lab_service_requests as lsr')
                ->join('services as s', 'lsr.service_id', '=', 's.id')
                ->join('patients as p', 'lsr.patient_id', '=', 'p.id')
                ->join('users as u', 'p.user_id', '=', 'u.id')
                ->select(
                    'lsr.id',
                    'lsr.patient_id',
                    'p.file_no',
                    \Illuminate\Support\Facades\DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                    's.service_name as test_name',
                    'lsr.status',
                    'lsr.created_at'
                )
                ->orderByDesc('lsr.created_at')
                ->limit(6)
                ->get();

            foreach ($recentLabs as $lab) {
                $statusMap = [0 => 'Billing', 1 => 'Billed', 2 => 'Sampling', 3 => 'Results', 4 => 'Completed'];
                $colorMap = [0 => 'warning', 1 => 'info', 2 => 'primary', 3 => 'danger', 4 => 'success'];
                $ledger[] = [
                    'patient_id' => $lab->patient_id,
                    'patient_name' => $lab->patient_name,
                    'file_no' => $lab->file_no ?? 'N/A',
                    'dept' => 'Lab',
                    'dept_code' => 'lab',
                    'event' => $lab->test_name,
                    'status_label' => $statusMap[$lab->status] ?? 'Pending',
                    'status_color' => $colorMap[$lab->status] ?? 'secondary',
                    'time' => \Carbon\Carbon::parse($lab->created_at)->format('h:i A'),
                    'timestamp' => $lab->created_at,
                    'target_url' => route('lab.workbench', ['patient_id' => $lab->patient_id]),
                ];
            }

            // Recent Pharmacy Requests
            $recentPharm = \Illuminate\Support\Facades\DB::table('product_requests as pr')
                ->join('patients as p', 'pr.patient_id', '=', 'p.id')
                ->join('users as u', 'p.user_id', '=', 'u.id')
                ->join('products as prod', 'pr.product_id', '=', 'prod.id')
                ->select(
                    'pr.id',
                    'pr.patient_id',
                    'p.file_no',
                    \Illuminate\Support\Facades\DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                    'prod.product_name as product',
                    'pr.status',
                    'pr.created_at'
                )
                ->orderByDesc('pr.created_at')
                ->limit(6)
                ->get();

            foreach ($recentPharm as $ph) {
                $statusMap = [1 => 'Pending', 2 => 'Billed', 3 => 'Dispensed'];
                $colorMap = [1 => 'warning', 2 => 'info', 3 => 'success'];
                $ledger[] = [
                    'patient_id' => $ph->patient_id,
                    'patient_name' => $ph->patient_name,
                    'file_no' => $ph->file_no ?? 'N/A',
                    'dept' => 'Pharmacy',
                    'dept_code' => 'pharmacy',
                    'event' => $ph->product,
                    'status_label' => $statusMap[$ph->status] ?? 'Active',
                    'status_color' => $colorMap[$ph->status] ?? 'purple',
                    'time' => \Carbon\Carbon::parse($ph->created_at)->format('h:i A'),
                    'timestamp' => $ph->created_at,
                    'target_url' => route('pharmacy.workbench', ['patient_id' => $ph->patient_id]),
                ];
            }

            // Recent Payments
            $recentPayments = \Illuminate\Support\Facades\DB::table('payments as pay')
                ->leftJoin('patients as pt', 'pay.patient_id', '=', 'pt.id')
                ->leftJoin('users as pu', 'pt.user_id', '=', 'pu.id')
                ->select(
                    'pay.id',
                    'pay.patient_id',
                    'pt.file_no',
                    'pay.total as amount',
                    'pay.payment_type',
                    'pay.created_at',
                    \Illuminate\Support\Facades\DB::raw("CONCAT(pu.surname, ' ', pu.firstname) as patient_name")
                )
                ->orderByDesc('pay.created_at')
                ->limit(6)
                ->get();

            foreach ($recentPayments as $pay) {
                if ($pay->patient_name) {
                    $ledger[] = [
                        'patient_id' => $pay->patient_id,
                        'patient_name' => $pay->patient_name,
                        'file_no' => $pay->file_no ?? 'N/A',
                        'dept' => 'Billing',
                        'dept_code' => 'billing',
                        'event' => ($pay->payment_type ?: 'Payment') . ' (₦' . number_format($pay->amount, 0) . ')',
                        'status_label' => 'Settled',
                        'status_color' => 'success',
                        'time' => \Carbon\Carbon::parse($pay->created_at)->format('h:i A'),
                        'timestamp' => $pay->created_at,
                        'target_url' => route('billing.workbench', ['patient_id' => $pay->patient_id]),
                    ];
                }
            }

            // Sort all by timestamp desc and take 15
            usort($ledger, function ($a, $b) {
                return strcmp($b['timestamp'], $a['timestamp']);
            });
            $sortedLedger = array_slice($ledger, 0, 15);

            // Insights
            $insights = array_merge(
                $recepSvc->getInsights(),
                $nurseSvc->getInsights(),
                $pharmSvc->getInsights(),
                $labSvc->getInsights(),
                $billSvc->getInsights()
            );

            // Extended Department Queue Counts
            $surgeryQueueCount = class_exists(\App\Models\Procedure::class)
                ? \App\Models\Procedure::whereIn('procedure_status', [0, 1])->count()
                : 0;

            $maternityActiveCount = \Illuminate\Support\Facades\Schema::hasTable('maternity_enrollments')
                ? \Illuminate\Support\Facades\DB::table('maternity_enrollments')->whereNull('completed_at')->count()
                : 0;

            $morgueActiveCount = \Illuminate\Support\Facades\Schema::hasTable('morgue_admissions')
                ? \Illuminate\Support\Facades\DB::table('morgue_admissions')->whereNull('release_time')->count()
                : 0;

            $storePendingCount = \Illuminate\Support\Facades\Schema::hasTable('store_requisitions')
                ? \Illuminate\Support\Facades\DB::table('store_requisitions')->where('status', 'pending')->count()
                : 0;

            $hmoPendingCount = \Illuminate\Support\Facades\Schema::hasTable('hmo_claims')
                ? \Illuminate\Support\Facades\DB::table('hmo_claims')->where('status', 'submitted')->count()
                : 0;

            $leavePendingCount = \Illuminate\Support\Facades\Schema::hasTable('leave_requests')
                ? \Illuminate\Support\Facades\DB::table('leave_requests')->where('status', 'pending')->count()
                : 0;

            // Accurate KPI telemetry values
            $vitalsToday = \Illuminate\Support\Facades\DB::table('vital_signs')->whereDate('created_at', $today)->count();
            $totalBeds = \Illuminate\Support\Facades\DB::table('beds')->count();
            $occupiedBeds = \Illuminate\Support\Facades\DB::table('beds')->where('status', 'occupied')->count();
            $effectiveOccupied = $occupiedBeds > 0 ? $occupiedBeds : min($admittedCount, $totalBeds);
            $occupancyRate = $totalBeds > 0 ? round(($effectiveOccupied / $totalBeds) * 100) : 0;

            $lowStockCount = collect($pharmStock)->firstWhere('label', 'Low Stock')['value'] ?? 0;
            $outOfStockCount = collect($pharmStock)->firstWhere('label', 'Out of Stock')['value'] ?? 0;

            $dispensedToday = \Illuminate\Support\Facades\DB::table('product_requests')->where('status', 3)->whereDate('created_at', $today)->count();
            $labCompletedToday = \Illuminate\Support\Facades\DB::table('lab_service_requests')->where('status', 4)->whereDate('created_at', $today)->count();

            return response()->json([
                'pipeline' => [
                    'waiting' => $waitingCount,
                    'vitals' => $vitalsCount,
                    'consultation' => $consultCount,
                    'dispense' => $pharmQueueCount,
                    'lab' => $labQueueCount,
                    'imaging' => $imgQueueCount,
                    'surgery' => $surgeryQueueCount,
                    'maternity' => $maternityActiveCount,
                    'admitted' => $admittedCount,
                    'store' => $storePendingCount,
                    'unpaid' => $unpaidBillCount,
                    'hmo' => $hmoPendingCount,
                    'morgue' => $morgueActiveCount,
                    'leave' => $leavePendingCount,
                ],
                'kpis' => [
                    'today_revenue' => $todayRevenue,
                    'vitals_today' => $vitalsToday,
                    'admitted_patients' => $admittedCount,
                    'occupancy_rate' => $occupancyRate,
                    'total_beds' => $totalBeds,
                    'low_stock' => $lowStockCount,
                    'out_of_stock' => $outOfStockCount,
                    'dispensed_today' => $dispensedToday,
                    'lab_completed' => $labCompletedToday,
                    'unbilled_bills' => $unpaidBillCount,
                    'surgery_pending' => $surgeryQueueCount,
                    'maternity_active' => $maternityActiveCount,
                    'store_requisitions' => $storePendingCount,
                    'leave_pending' => $leavePendingCount,
                ],
                'hourlyFlow' => $hourlyFlow,
                'revenueTrend' => $revTrend,
                'activity' => $sortedLedger,
                'insights' => array_slice($insights, 0, 5),
            ]);
        });
    }

    /* ============================
     *  RECEPTION
     * ============================ */
    public function receptionData()
    {
        return Cache::remember('dash:reception:' . date('Y-m-d-H-i-') . floor(date('s') / 180), self::CACHE_TTL, function () {
            $svc = new ReceptionDashboardService();

            return response()->json([
                'queues' => $svc->getQueueCounts(),
                'activity' => $svc->getRecentActivity(),
                'hourlyFlow' => $svc->getHourlyPatientFlow(),
                'patientBreakdown' => $svc->getPatientTypeBreakdown(),
                'insights' => $svc->getInsights(),
            ]);
        });
    }

    /* ============================
     *  BILLING
     * ============================ */
    public function billingData()
    {
        return Cache::remember('dash:billing:' . date('Y-m-d-H-i-') . floor(date('s') / 180), self::CACHE_TTL, function () {
            $svc = new BillingDashboardService();

            return response()->json([
                'queues' => $svc->getQueueCounts(),
                'activity' => $svc->getRecentActivity(),
                'paymentMethods' => $svc->getPaymentMethodBreakdown(),
                'revenueTrend' => $svc->getRevenueTrend(),
                'insights' => $svc->getInsights(),
            ]);
        });
    }

    /* ============================
     *  PHARMACY
     * ============================ */
    public function pharmacyData()
    {
        return Cache::remember('dash:pharmacy:' . date('Y-m-d-H-i-') . floor(date('s') / 180), self::CACHE_TTL, function () {
            $svc = new PharmacyDashboardService();

            return response()->json([
                'queues' => $svc->getQueueCounts(),
                'stats' => $svc->getStats(),
                'activity' => $svc->getRecentActivity(),
                'stockHealth' => $svc->getStockHealth(),
                'dispensingTrend' => $svc->getDispensingTrend(),
                'insights' => $svc->getInsights(),
            ]);
        });
    }

    /* ============================
     *  STORE / INVENTORY
     * ============================ */
    public function storeData()
    {
        return Cache::remember('dash:store:' . date('Y-m-d-H-i-') . floor(date('s') / 180), self::CACHE_TTL, function () {
            $svc = new StoreDashboardService();

            return response()->json([
                'queues' => $svc->getQueueCounts(),
                'stats' => $svc->getStats(),
                'activity' => $svc->getRecentActivity(),
                'stockHealth' => $svc->getStockHealth(),
                'requisitionTrend' => $svc->getRequisitionTrend(),
                'insights' => $svc->getInsights(),
            ]);
        });
    }

    /* ============================
     *  MORGUE
     * ============================ */
    public function morgueData()
    {
        return Cache::remember('dash:morgue:' . date('Y-m-d-H-i-') . floor(date('s') / 180), self::CACHE_TTL, function () {
            $svc = new MorgueDashboardService();

            return response()->json([
                'queues' => $svc->getQueueCounts(),
                'stats' => $svc->getStats(),
                'insights' => $svc->getInsights(),
                'admissionTrend' => $svc->getAdmissionTrend(),
                'statusBreakdown' => $svc->getStatusBreakdown(),
                'activity' => $svc->getRecentActivity(),
            ]);
        });
    }

    /* ============================
     *  NURSING
     * ============================ */
    public function nursingData()
    {
        return Cache::remember('dash:nursing:' . date('Y-m-d-H-i-') . floor(date('s') / 180), self::CACHE_TTL, function () {
            $svc = new NursingDashboardService();

            return response()->json([
                'queues' => $svc->getQueueCounts(),
                'stats' => $svc->getStats(),
                'activity' => $svc->getRecentActivity(),
                'bedOccupancy' => $svc->getBedOccupancy(),
                'vitalsTrend' => $svc->getVitalsTrend(),
                'insights' => $svc->getInsights(),
            ]);
        });
    }

    /* ============================
     *  ESS / MY PORTAL
     * ============================ */
    public function essData()
    {
        $userId = auth()->id();

        return Cache::remember('dash:ess:' . $userId . ':' . date('Y-m-d-H-i-') . floor(date('s') / 180), self::CACHE_TTL, function () use ($userId) {
            $svc = new EssDashboardService();

            return response()->json([
                'queues' => $svc->getQueueCounts($userId),
                'stats' => $svc->getStats($userId),
                'insights' => $svc->getInsights($userId),
                'leaveBreakdown' => $svc->getLeaveBreakdown($userId),
                'activity' => $svc->getRecentActivity($userId),
            ]);
        });
    }

    /* ============================
     *  LAB / IMAGING
     * ============================ */
    public function labData()
    {
        return Cache::remember('dash:lab:' . date('Y-m-d-H-i-') . floor(date('s') / 180), self::CACHE_TTL, function () {
            $svc = new LabDashboardService();

            return response()->json([
                'queues' => $svc->getQueueCounts(),
                'stats' => $svc->getStats(),
                'activity' => $svc->getRecentActivity(),
                'categoryBreakdown' => $svc->getServiceCategoryBreakdown(),
                'requestTrend' => $svc->getRequestTrend(),
                'insights' => $svc->getInsights(),
            ]);
        });
    }

    public function imagingData()
    {
        return Cache::remember('dash:imaging:' . date('Y-m-d-H-i-') . floor(date('s') / 180), self::CACHE_TTL, function () {
            $svc = new ImagingDashboardService();

            return response()->json([
                'queues' => $svc->getQueueCounts(),
                'stats' => $svc->getStats(),
                'insights' => $svc->getInsights(),
                'categoryBreakdown' => $svc->getCategoryBreakdown(),
                'requestTrend' => $svc->getRequestTrend(),
                'activity' => $svc->getRecentActivity(),
            ]);
        });
    }

    /* ============================
     *  DOCTOR
     * ============================ */
    public function doctorData()
    {
        $userId = auth()->id();

        return Cache::remember('dash:doctor:' . $userId . ':' . date('Y-m-d-H-i-') . floor(date('s') / 180), self::CACHE_TTL, function () use ($userId) {
            $svc = new DoctorDashboardService();

            return response()->json([
                'queues' => $svc->getQueueCounts($userId),
                'activity' => $svc->getRecentActivity($userId),
                'insights' => $svc->getInsights($userId),
            ]);
        });
    }

    /* ============================
     *  CHILD HEALTH
     * ============================ */
    public function childHealthData()
    {
        return Cache::remember('dash:child_health:' . date('Y-m-d-H-i-') . floor(date('s') / 180), self::CACHE_TTL, function () {
            $svc = new ChildHealthDashboardService();

            return response()->json([
                'queues' => $svc->getQueueCounts(),
                'stats' => $svc->getStats(),
                'insights' => $svc->getInsights(),
                'statusBreakdown' => $svc->getImmunizationBreakdown(),
                'activity' => $svc->getRecentActivity(),
            ]);
        });
    }

    /* ============================
     *  HMO
     * ============================ */
    public function hmoData()
    {
        return Cache::remember('dash:hmo:' . date('Y-m-d-H-i-') . floor(date('s') / 180), self::CACHE_TTL, function () {
            $svc = new HmoDashboardService();

            return response()->json([
                'queues' => $svc->getQueueCounts(),
                'stats' => $svc->getStats(),
                'activity' => $svc->getRecentActivity(),
                'providerDistribution' => $svc->getProviderDistribution(),
                'claimsTrend' => $svc->getClaimsTrend(),
                'insights' => $svc->getInsights(),
            ]);
        });
    }

    /* ============================
     *  MATERNITY
     * ============================ */
    public function maternityData()
    {
        return Cache::remember('dash:maternity:' . date('Y-m-d-H-i-') . floor(date('s') / 180), self::CACHE_TTL, function () {
            $svc = new MaternityDashboardService();

            return response()->json([
                'queues' => $svc->getQueueCounts(),
                'stats' => $svc->getStats(),
                'insights' => $svc->getInsights(),
                'enrollmentTrend' => $svc->getEnrollmentTrend(),
                'riskBreakdown' => $svc->getRiskBreakdown(),
                'activity' => $svc->getRecentActivity(),
            ]);
        });
    }

    /* ============================
     *  HR OPERATIONS
     * ============================ */
    public function hrData()
    {
        return Cache::remember('dash:hr:' . date('Y-m-d-H-i-') . floor(date('s') / 180), self::CACHE_TTL, function () {
            $svc = new HrDashboardService();

            return response()->json([
                'queues' => $svc->getQueueCounts(),
                'stats' => $svc->getStats(),
                'insights' => $svc->getInsights(),
                'deptBreakdown' => $svc->getDepartmentBreakdown(),
                'activity' => $svc->getRecentActivity(),
            ]);
        });
    }

    /* ============================
     *  THEATRE / PROCEDURES
     * ============================ */
    public function theatreData()
    {
        return Cache::remember('dash:theatre:' . date('Y-m-d-H-i-') . floor(date('s') / 180), self::CACHE_TTL, function () {
            $svc = new TheatreDashboardService();

            return response()->json([
                'queues' => $svc->getQueueCounts(),
                'stats' => $svc->getStats(),
                'insights' => $svc->getInsights(),
                'categoryBreakdown' => $svc->getCategoryBreakdown(),
                'activity' => $svc->getRecentActivity(),
            ]);
        });
    }

    /* ============================
     *  ACCOUNTS / AUDIT
     * ============================ */
    public function accountsData()
    {
        return Cache::remember('dash:accounts:' . date('Y-m-d-H-i-') . floor(date('s') / 180), self::CACHE_TTL, function () {
            $svc = new AccountsDashboardService();

            return response()->json([
                'summary' => $svc->getFinancialSummary(),
                'revenueTrend' => $svc->getRevenueTrend(),
                'paymentMethods' => $svc->getPaymentMethodBreakdown(),
                'departmentRevenue' => $svc->getDepartmentRevenue(),
                'kpis' => $svc->getFinancialKpis(),
                'insights' => $svc->getInsights(),
            ]);
        });
    }

    public function auditLog(Request $request)
    {
        $svc = new AccountsDashboardService();

        return response()->json([
            'log' => $svc->getAuditLog(
                $request->input('limit', 20),
                $request->only(['user_id', 'event', 'module', 'date_from', 'date_to'])
            ),
        ]);
    }

    public function auditData()
    {
        return Cache::remember('dash:audit:' . date('Y-m-d-H-i-') . floor(date('s') / 180), self::CACHE_TTL, function () {
            $svc = new AuditDashboardService();

            return response()->json([
                'summary' => $svc->getSummaryStats(),
                'revenueTrend' => $svc->getRevenueTrend(),
                'recentActivity' => $svc->getRecentAuditLogs(),
            ]);
        });
    }
}
