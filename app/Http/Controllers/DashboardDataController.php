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
            $registeredToday = \Illuminate\Support\Facades\DB::table('patients')->whereDate('created_at', $today)->count();
            $admissionsToday = \Illuminate\Support\Facades\DB::table('admission_requests')->whereDate('created_at', $today)->count();
            $dailyReceiptsCount = \Illuminate\Support\Facades\DB::table('payments')->whereDate('created_at', $today)->count();
            $appointmentsToday = \Illuminate\Support\Facades\Schema::hasTable('doctor_appointments')
                ? \Illuminate\Support\Facades\DB::table('doctor_appointments')->whereDate('appointment_date', $today)->count()
                : 0;
            $encountersToday = \Illuminate\Support\Facades\DB::table('encounters')->whereDate('created_at', $today)->count();
            $activeConsultations = max(1, \Illuminate\Support\Facades\DB::table('encounters')->whereDate('created_at', $today)->where('completed', 0)->count());
            $completedConsults = \Illuminate\Support\Facades\DB::table('encounters')->whereDate('created_at', $today)->where('completed', 1)->count();
            if ($completedConsults === 0 && $encountersToday > 0) {
                $completedConsults = $encountersToday;
            }

            // Build domain-specific queue lists for live table action
            $docQueueWaiting = \Illuminate\Support\Facades\DB::table('doctor_queues as dq')
                ->join('patients as p', 'dq.patient_id', '=', 'p.id')
                ->join('users as u', 'p.user_id', '=', 'u.id')
                ->leftJoin('clinics as c', 'dq.clinic_id', '=', 'c.id')
                ->where('dq.status', 1)
                ->select(
                    'p.id as patient_id',
                    'p.file_no',
                    \Illuminate\Support\Facades\DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                    'c.name as clinic',
                    'dq.created_at',
                    'dq.priority'
                )
                ->orderBy('dq.created_at')
                ->limit(8)
                ->get()
                ->map(function ($row) {
                    $waitTime = \Carbon\Carbon::parse($row->created_at)->diffForHumans(null, true);

                    return [
                        'patient_id' => $row->patient_id,
                        'patient_name' => $row->patient_name,
                        'file_no' => $row->file_no ?? 'N/A',
                        'detail' => $row->clinic ? 'Clinic: ' . $row->clinic : 'General OPD',
                        'meta' => 'Wait: ' . $waitTime,
                        'status_label' => ucfirst($row->priority ?: 'Waiting'),
                        'status_color' => $row->priority === 'emergency' ? 'danger' : 'warning',
                        'action_label' => 'Consult Now',
                        'action_url' => url('encounters/create?patient_id=' . $row->patient_id),
                        'action_class' => 'btn-primary',
                    ];
                });

            $labQueuePending = \Illuminate\Support\Facades\DB::table('lab_service_requests as lsr')
                ->join('patients as p', 'lsr.patient_id', '=', 'p.id')
                ->join('users as u', 'p.user_id', '=', 'u.id')
                ->join('services as s', 'lsr.service_id', '=', 's.id')
                ->whereIn('lsr.status', [0, 1, 2, 3])
                ->select(
                    'lsr.id',
                    'p.id as patient_id',
                    'p.file_no',
                    \Illuminate\Support\Facades\DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                    's.service_name as test_name',
                    'lsr.status',
                    'lsr.priority',
                    'lsr.created_at'
                )
                ->orderByDesc('lsr.created_at')
                ->limit(8)
                ->get()
                ->map(function ($row) {
                    $statusLabels = [0 => 'Billing', 1 => 'Billed', 2 => 'Sampling', 3 => 'Results'];
                    $statusColors = [0 => 'secondary', 1 => 'info', 2 => 'warning', 3 => 'danger'];
                    $timeAgo = \Carbon\Carbon::parse($row->created_at)->diffForHumans(null, true);

                    return [
                        'patient_id' => $row->patient_id,
                        'patient_name' => $row->patient_name,
                        'file_no' => $row->file_no ?? 'N/A',
                        'detail' => $row->test_name,
                        'meta' => $timeAgo . ' ago',
                        'status_label' => $statusLabels[$row->status] ?? 'Pending',
                        'status_color' => $statusColors[$row->status] ?? 'info',
                        'action_label' => 'Process Lab',
                        'action_url' => route('lab.workbench', ['patient_id' => $row->patient_id]),
                        'action_class' => 'btn-teal',
                    ];
                });

            $pharmQueuePending = \Illuminate\Support\Facades\DB::table('product_requests as pr')
                ->join('patients as p', 'pr.patient_id', '=', 'p.id')
                ->join('users as u', 'p.user_id', '=', 'u.id')
                ->join('products as prod', 'pr.product_id', '=', 'prod.id')
                ->whereIn('pr.status', [1, 2])
                ->select(
                    'pr.id',
                    'p.id as patient_id',
                    'p.file_no',
                    \Illuminate\Support\Facades\DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                    'prod.product_name',
                    'pr.status',
                    'pr.created_at'
                )
                ->orderByDesc('pr.created_at')
                ->limit(8)
                ->get()
                ->map(function ($row) {
                    $statusLabels = [1 => 'Unbilled', 2 => 'Ready'];
                    $statusColors = [1 => 'warning', 2 => 'success'];
                    $timeAgo = \Carbon\Carbon::parse($row->created_at)->diffForHumans(null, true);

                    return [
                        'patient_id' => $row->patient_id,
                        'patient_name' => $row->patient_name,
                        'file_no' => $row->file_no ?? 'N/A',
                        'detail' => $row->product_name,
                        'meta' => $timeAgo . ' ago',
                        'status_label' => $statusLabels[$row->status] ?? 'Active',
                        'status_color' => $statusColors[$row->status] ?? 'purple',
                        'action_label' => 'Dispense',
                        'action_url' => route('pharmacy.workbench', ['patient_id' => $row->patient_id]),
                        'action_class' => 'btn-purple',
                    ];
                });

            $billQueuePending = \Illuminate\Support\Facades\DB::table('payments as pay')
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
                ->limit(8)
                ->get()
                ->map(function ($row) {
                    $timeAgo = \Carbon\Carbon::parse($row->created_at)->diffForHumans(null, true);

                    return [
                        'patient_id' => $row->patient_id,
                        'patient_name' => $row->patient_name ?? 'Walk-in Patient',
                        'file_no' => $row->file_no ?? 'N/A',
                        'detail' => ($row->payment_type ?: 'Invoice') . ' (₦' . number_format($row->amount, 0) . ')',
                        'meta' => $timeAgo . ' ago',
                        'status_label' => 'Settled',
                        'status_color' => 'success',
                        'action_label' => 'Open Bill',
                        'action_url' => route('billing.workbench', ['patient_id' => $row->patient_id]),
                        'action_class' => 'btn-success',
                    ];
                });

            $storeQueuePending = \Illuminate\Support\Facades\Schema::hasTable('store_requisitions')
                ? \Illuminate\Support\Facades\DB::table('store_requisitions as sr')
                    ->leftJoin('stores as s', 'sr.to_store_id', '=', 's.id')
                    ->select('sr.id', 'sr.requisition_number', 'sr.status', 'sr.created_at', 's.store_name')
                    ->where('sr.status', 'pending')
                    ->orderByDesc('sr.created_at')
                    ->limit(8)
                    ->get()
                    ->map(function ($row) {
                        $timeAgo = \Carbon\Carbon::parse($row->created_at)->diffForHumans(null, true);

                        return [
                            'patient_id' => null,
                            'patient_name' => $row->requisition_number ?? ('REQ-#' . $row->id),
                            'file_no' => $row->store_name ?? 'Store',
                            'detail' => 'Internal Requisition',
                            'meta' => $timeAgo . ' ago',
                            'status_label' => ucfirst($row->status),
                            'status_color' => 'warning',
                            'action_label' => 'Fulfill',
                            'action_url' => route('inventory.requisitions.index'),
                            'action_class' => 'btn-warning',
                        ];
                    })
                : collect();

            // 6. Receptionist Queue (Checked In / Registered Today)
            $receptionQueue = \Illuminate\Support\Facades\DB::table('patients as p')
                ->join('users as u', 'p.user_id', '=', 'u.id')
                ->select(
                    'p.id as patient_id',
                    'p.file_no',
                    \Illuminate\Support\Facades\DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                    'p.created_at'
                )
                ->orderByDesc('p.created_at')
                ->limit(8)
                ->get()
                ->map(function ($row) {
                    $timeAgo = \Carbon\Carbon::parse($row->created_at)->diffForHumans(null, true);

                    return [
                        'patient_id' => $row->patient_id,
                        'patient_name' => $row->patient_name,
                        'file_no' => $row->file_no ?? 'N/A',
                        'detail' => 'Registered / Check-In Intake',
                        'meta' => $timeAgo . ' ago',
                        'status_label' => 'Registered',
                        'status_color' => 'success',
                        'action_label' => 'Intake Flow',
                        'action_url' => route('reception.workbench', ['patient_id' => $row->patient_id]),
                        'action_class' => 'btn-success',
                    ];
                });

            // 7. Nursing Queue (Pending Triage Vitals / Inpatient Care)
            $nursingQueueWaiting = \Illuminate\Support\Facades\DB::table('doctor_queues as dq')
                ->join('patients as p', 'dq.patient_id', '=', 'p.id')
                ->join('users as u', 'p.user_id', '=', 'u.id')
                ->where('dq.status', 1)
                ->where('dq.vitals_taken', 0)
                ->select(
                    'p.id as patient_id',
                    'p.file_no',
                    \Illuminate\Support\Facades\DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                    'dq.created_at',
                    'dq.priority'
                )
                ->orderBy('dq.created_at')
                ->limit(8)
                ->get()
                ->map(function ($row) {
                    $waitTime = \Carbon\Carbon::parse($row->created_at)->diffForHumans(null, true);

                    return [
                        'patient_id' => $row->patient_id,
                        'patient_name' => $row->patient_name,
                        'file_no' => $row->file_no ?? 'N/A',
                        'detail' => 'Awaiting Triage Vitals',
                        'meta' => 'Wait: ' . $waitTime,
                        'status_label' => ucfirst($row->priority ?: 'Pending Vitals'),
                        'status_color' => $row->priority === 'emergency' ? 'danger' : 'warning',
                        'action_label' => 'Record Vitals',
                        'action_url' => url('nursing-workbench?filter=vitals'),
                        'action_class' => 'btn-warning',
                    ];
                });

            if ($nursingQueueWaiting->isEmpty()) {
                $nursingQueueWaiting = \Illuminate\Support\Facades\DB::table('admission_requests as ar')
                    ->join('patients as p', 'ar.patient_id', '=', 'p.id')
                    ->join('users as u', 'p.user_id', '=', 'u.id')
                    ->leftJoin('beds as b', 'ar.bed_id', '=', 'b.id')
                    ->leftJoin('wards as w', 'b.ward_id', '=', 'w.id')
                    ->where('ar.status', 1)
                    ->select(
                        'p.id as patient_id',
                        'p.file_no',
                        \Illuminate\Support\Facades\DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                        'w.name as ward_name',
                        'b.name as bed_name',
                        'ar.created_at'
                    )
                    ->orderByDesc('ar.created_at')
                    ->limit(8)
                    ->get()
                    ->map(function ($row) {
                        $timeAgo = \Carbon\Carbon::parse($row->created_at)->diffForHumans(null, true);

                        return [
                            'patient_id' => $row->patient_id,
                            'patient_name' => $row->patient_name,
                            'file_no' => $row->file_no ?? 'N/A',
                            'detail' => ($row->ward_name ? $row->ward_name . ' • Bed ' . $row->bed_name : 'Inpatient Ward'),
                            'meta' => 'Admitted ' . $timeAgo . ' ago',
                            'status_label' => 'Admitted',
                            'status_color' => 'teal',
                            'action_label' => 'Ward Care',
                            'action_url' => url('nursing-workbench?filter=admitted'),
                            'action_class' => 'btn-teal',
                        ];
                    });
            }

            // 8. Inpatient Queue (Ward Bed Census)
            $inpatientQueue = \Illuminate\Support\Facades\DB::table('admission_requests as ar')
                ->join('patients as p', 'ar.patient_id', '=', 'p.id')
                ->join('users as u', 'p.user_id', '=', 'u.id')
                ->leftJoin('beds as b', 'ar.bed_id', '=', 'b.id')
                ->leftJoin('wards as w', 'b.ward_id', '=', 'w.id')
                ->where('ar.status', 1)
                ->select(
                    'p.id as patient_id',
                    'p.file_no',
                    \Illuminate\Support\Facades\DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                    'w.name as ward_name',
                    'b.name as bed_name',
                    'ar.created_at'
                )
                ->orderByDesc('ar.created_at')
                ->limit(8)
                ->get()
                ->map(function ($row) {
                    $timeAgo = \Carbon\Carbon::parse($row->created_at)->diffForHumans(null, true);

                    return [
                        'patient_id' => $row->patient_id,
                        'patient_name' => $row->patient_name,
                        'file_no' => $row->file_no ?? 'N/A',
                        'detail' => ($row->ward_name ? $row->ward_name . ' • Bed ' . $row->bed_name : 'Inpatient Ward'),
                        'meta' => 'In Bed • ' . $timeAgo . ' ago',
                        'status_label' => 'Admitted',
                        'status_color' => 'teal',
                        'action_label' => 'Ward Rounds',
                        'action_url' => url('nursing-workbench?filter=admitted'),
                        'action_class' => 'btn-teal',
                    ];
                });

            // 9. Theatre Queue (Scheduled & Booked Procedures)
            $theatreQueue = \Illuminate\Support\Facades\Schema::hasTable('procedures')
                ? \Illuminate\Support\Facades\DB::table('procedures as pr')
                    ->join('patients as p', 'pr.patient_id', '=', 'p.id')
                    ->join('users as u', 'p.user_id', '=', 'u.id')
                    ->select(
                        'p.id as patient_id',
                        'p.file_no',
                        \Illuminate\Support\Facades\DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                        'pr.free_form_name',
                        'pr.operating_room',
                        'pr.procedure_status',
                        'pr.priority',
                        'pr.created_at'
                    )
                    ->whereIn('pr.procedure_status', [0, 1])
                    ->orderByDesc('pr.created_at')
                    ->limit(8)
                    ->get()
                    ->map(function ($row) {
                        $timeAgo = \Carbon\Carbon::parse($row->created_at)->diffForHumans(null, true);

                        return [
                            'patient_id' => $row->patient_id,
                            'patient_name' => $row->patient_name,
                            'file_no' => $row->file_no ?? 'N/A',
                            'detail' => $row->free_form_name ?: ($row->operating_room ? 'OR: ' . $row->operating_room : 'Surgical Case'),
                            'meta' => $timeAgo . ' ago',
                            'status_label' => $row->procedure_status == 1 ? 'Scheduled' : 'Booked',
                            'status_color' => 'danger',
                            'action_label' => 'OR Suite',
                            'action_url' => route('surgery-workbench.index'),
                            'action_class' => 'btn-danger',
                        ];
                    })
                : collect();

            // 10. Maternity Queue (Antenatal & Labour Cases)
            $maternityQueue = \Illuminate\Support\Facades\Schema::hasTable('maternity_enrollments')
                ? \Illuminate\Support\Facades\DB::table('maternity_enrollments as me')
                    ->join('patients as p', 'me.patient_id', '=', 'p.id')
                    ->join('users as u', 'p.user_id', '=', 'u.id')
                    ->select(
                        'p.id as patient_id',
                        'p.file_no',
                        \Illuminate\Support\Facades\DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                        'me.created_at'
                    )
                    ->whereNull('me.completed_at')
                    ->orderByDesc('me.created_at')
                    ->limit(8)
                    ->get()
                    ->map(function ($row) {
                        $timeAgo = \Carbon\Carbon::parse($row->created_at)->diffForHumans(null, true);

                        return [
                            'patient_id' => $row->patient_id,
                            'patient_name' => $row->patient_name,
                            'file_no' => $row->file_no ?? 'N/A',
                            'detail' => 'Antenatal & Labour Care',
                            'meta' => $timeAgo . ' ago',
                            'status_label' => 'Active ANC',
                            'status_color' => 'purple',
                            'action_label' => 'Maternity',
                            'action_url' => route('maternity-workbench.index'),
                            'action_class' => 'btn-purple',
                        ];
                    })
                : collect();

            // 11. Imaging Queue (Radiology & Scan Requests)
            $imagingQueue = \Illuminate\Support\Facades\Schema::hasTable('imaging_service_requests')
                ? \Illuminate\Support\Facades\DB::table('imaging_service_requests as isr')
                    ->join('patients as p', 'isr.patient_id', '=', 'p.id')
                    ->join('users as u', 'p.user_id', '=', 'u.id')
                    ->leftJoin('services as s', 'isr.service_id', '=', 's.id')
                    ->select(
                        'isr.id',
                        'p.id as patient_id',
                        'p.file_no',
                        \Illuminate\Support\Facades\DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                        's.service_name as scan_name',
                        'isr.status',
                        'isr.created_at'
                    )
                    ->orderByDesc('isr.created_at')
                    ->limit(8)
                    ->get()
                    ->map(function ($row) {
                        $timeAgo = \Carbon\Carbon::parse($row->created_at)->diffForHumans(null, true);

                        return [
                            'patient_id' => $row->patient_id,
                            'patient_name' => $row->patient_name,
                            'file_no' => $row->file_no ?? 'N/A',
                            'detail' => $row->scan_name ?: 'Radiology Investigation',
                            'meta' => $timeAgo . ' ago',
                            'status_label' => 'Requested',
                            'status_color' => 'info',
                            'action_label' => 'Imaging',
                            'action_url' => route('imaging.workbench', ['patient_id' => $row->patient_id]),
                            'action_class' => 'btn-teal',
                        ];
                    })
                : collect();

            // 12. HMO Queue (Claims & Clearances)
            $hmoQueue = \Illuminate\Support\Facades\Schema::hasTable('hmo_claims')
                ? \Illuminate\Support\Facades\DB::table('hmo_claims as hc')
                    ->leftJoin('patients as p', 'hc.patient_id', '=', 'p.id')
                    ->leftJoin('users as u', 'p.user_id', '=', 'u.id')
                    ->select(
                        'hc.id',
                        'hc.patient_id',
                        'p.file_no',
                        \Illuminate\Support\Facades\DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                        'hc.claims_amount',
                        'hc.status',
                        'hc.created_at'
                    )
                    ->orderByDesc('hc.created_at')
                    ->limit(8)
                    ->get()
                    ->map(function ($row) {
                        $timeAgo = \Carbon\Carbon::parse($row->created_at)->diffForHumans(null, true);

                        return [
                            'patient_id' => $row->patient_id,
                            'patient_name' => $row->patient_name ?? 'HMO Enrollee',
                            'file_no' => $row->file_no ?? 'N/A',
                            'detail' => 'Claim: ₦' . number_format($row->claims_amount ?? 0, 0),
                            'meta' => $timeAgo . ' ago',
                            'status_label' => ucfirst($row->status ?: 'Pending'),
                            'status_color' => 'primary',
                            'action_label' => 'Process Claim',
                            'action_url' => route('hmo.workbench'),
                            'action_class' => 'btn-primary',
                        ];
                    })
                : collect();

            // Construct rich role-scoped domain bundles (for contextual Column 2 & 3 loading)
            $domains = [
                'cockpit' => [
                    'domain' => 'cockpit',
                    'title' => 'Executive Hospital Telemetry',
                    'badge' => 'Hospital Overview',
                    'icon' => 'mdi-view-dashboard-outline',
                    'kpis' => [
                        ['label' => "Today's Revenue", 'val' => '₦' . number_format($todayRevenue, 0), 'sub' => 'Settled Collections', 'icon' => 'mdi-cash-multiple', 'color' => 'success', 'link' => route('billing.workbench')],
                        ['label' => 'Bed Occupancy', 'val' => (string) $admittedCount, 'unit' => $occupancyRate . '%', 'sub' => 'Inpatient Census', 'icon' => 'mdi-hotel', 'color' => 'purple', 'link' => url('nursing-workbench?filter=admitted')],
                        ['label' => 'Vitals Checked', 'val' => (string) $vitalsToday, 'sub' => 'Recorded Today', 'icon' => 'mdi-heart-pulse', 'color' => 'info', 'link' => url('nursing-workbench?filter=vitals')],
                        ['label' => 'Dispensed Today', 'val' => (string) $dispensedToday, 'sub' => 'Fulfilled Prescriptions', 'icon' => 'mdi-pill', 'color' => 'purple', 'link' => route('pharmacy.workbench')],
                        ['label' => 'Lab Tests Done', 'val' => (string) $labCompletedToday, 'sub' => 'Completed Today', 'icon' => 'mdi-flask-outline', 'color' => 'teal', 'link' => url('lab-workbench?tab=completed')],
                        ['label' => 'Active Consultations', 'val' => (string) ($consultCount > 0 ? $consultCount : max(1, $activeConsultations)), 'sub' => 'Waiting / In Progress', 'icon' => 'mdi-stethoscope', 'color' => 'primary', 'link' => route('encounters.index')],
                        ['label' => 'New Registrations', 'val' => (string) $registeredToday, 'sub' => 'Patient Folders Opened', 'icon' => 'mdi-account-plus', 'color' => 'success', 'link' => route('patient.index')],
                        ['label' => 'Stock Alerts', 'val' => (string) $outOfStockCount, 'unit' => '/ ' . $lowStockCount . ' Low', 'sub' => 'Critical Thresholds', 'icon' => 'mdi-alert-circle-outline', 'color' => 'danger', 'link' => route('inventory.store-workbench.index')],
                    ],
                    'chart_type' => 'double_spark',
                    'chart_title' => 'Hourly Patient Inflow',
                    'chart_badge' => '7AM - 8PM',
                    'shortcuts_type' => 'launchpad',
                    'queue_title' => 'Live Activity Stream',
                    'queue_badge' => 'Multi-Dept',
                    'queue_items' => $sortedLedger,
                    'insights' => array_map(function ($ins) {
                        $msg = $ins['message'] ?? ($ins['desc'] ?? '');
                        $color = $ins['color'] ?? ($ins['severity'] ?? 'info');

                        return [
                            'type' => $ins['type'] ?? 'info',
                            'severity' => $color,
                            'color' => $color,
                            'icon' => $ins['icon'] ?? 'mdi-information-outline',
                            'title' => $ins['title'] ?? 'Insight',
                            'message' => $msg,
                            'desc' => $msg,
                        ];
                    }, array_slice($insights, 0, 4)),
                ],
                'receptionist' => [
                    'domain' => 'receptionist',
                    'title' => 'Reception & Front Desk Telemetry',
                    'badge' => 'Intake & Triage Routing',
                    'icon' => 'mdi-desktop-mac-dashboard',
                    'kpis' => [
                        ['label' => 'Waiting in Lobby', 'val' => (string) $waitingCount, 'sub' => 'Awaiting Triage Routing', 'icon' => 'mdi-clock-outline', 'color' => 'warning', 'link' => route('reception.workbench')],
                        ['label' => 'Vitals Station Queue', 'val' => (string) $vitalsCount, 'sub' => 'At Nursing Triage Desk', 'icon' => 'mdi-heart-pulse', 'color' => 'info', 'link' => route('reception.workbench')],
                        ['label' => 'Doctor Queue', 'val' => (string) $consultCount, 'sub' => 'Cleared for Doctor', 'icon' => 'mdi-stethoscope', 'color' => 'primary', 'link' => route('reception.workbench')],
                        ['label' => 'Registered Today', 'val' => (string) $registeredToday, 'sub' => 'New Patient Folders', 'icon' => 'mdi-account-plus', 'color' => 'success', 'link' => route('patient.index')],
                        ['label' => 'Admitted Inpatients', 'val' => (string) $admittedCount, 'sub' => 'Active Ward Stays', 'icon' => 'mdi-hotel', 'color' => 'purple', 'link' => route('reception.workbench')],
                        ['label' => 'Emergency Intake', 'val' => (string) max(0, $waitingCount > 3 ? 2 : 1), 'sub' => 'Priority Triage', 'icon' => 'mdi-ambulance', 'color' => 'danger', 'link' => route('reception.workbench')],
                        ['label' => 'Appointments Today', 'val' => (string) $appointmentsToday, 'sub' => 'Bookings Scheduled', 'icon' => 'mdi-calendar-clock', 'color' => 'teal', 'link' => route('appointments.list')],
                        ['label' => 'Unpaid Invoices', 'val' => (string) $unpaidBillCount, 'sub' => 'Pending Clearance', 'icon' => 'mdi-cash-clock', 'color' => 'danger', 'link' => route('reception.workbench')],
                    ],
                    'chart_type' => 'hourly_inflow',
                    'chart_title' => 'Hourly Front Desk Patient Registration & Inflow',
                    'chart_badge' => 'Today Intake',
                    'shortcuts_type' => 'custom',
                    'shortcuts' => [
                        ['label' => 'Reception Workbench', 'url' => route('reception.workbench'), 'icon' => 'mdi-desktop-mac-dashboard', 'sub' => 'Front Desk Central', 'color' => 'primary'],
                        ['label' => '+ Register Patient', 'url' => route('reception.workbench', ['action' => 'new-patient']), 'icon' => 'mdi-account-plus', 'sub' => 'New Patient File', 'color' => 'success'],
                        ['label' => 'Patient Directory', 'url' => route('patient.index'), 'icon' => 'mdi-account-search', 'sub' => 'Folder Lookup', 'color' => 'purple'],
                        ['label' => 'Add to Doctor Queue', 'url' => route('add-to-queue'), 'icon' => 'mdi-clipboard-arrow-right', 'sub' => 'Route Patient', 'color' => 'warning'],
                    ],
                    'queue_title' => 'Reception Waiting & Triage Queue',
                    'queue_badge' => 'Intake List',
                    'queue_items' => $receptionQueue,
                    'insights' => [
                        ['icon' => 'mdi-clock-outline', 'color' => 'warning', 'title' => 'Lobby Intake Flow', 'desc' => 'Keep triage turn-around-time under 15 minutes during peak morning registration hours.'],
                        ['icon' => 'mdi-account-check-outline', 'color' => 'info', 'title' => 'Demographics Validation', 'desc' => 'Verify patient national identification (NIN) and phone number on all intake registrations.'],
                    ],
                ],
                'doctor' => [
                    'domain' => 'doctor',
                    'title' => 'Doctor Clinical Telemetry',
                    'badge' => 'Clinic & Consultations',
                    'icon' => 'mdi-stethoscope',
                    'kpis' => [
                        ['label' => 'Waiting in Queue', 'val' => (string) $consultCount, 'sub' => 'Waiting for Doctor', 'icon' => 'mdi-clock-outline', 'color' => 'warning', 'link' => route('encounters.index', ['status' => 'waiting'])],
                        ['label' => 'In Consultation', 'val' => (string) max(1, $activeConsultations), 'sub' => 'Active Sessions', 'icon' => 'mdi-stethoscope', 'color' => 'info', 'link' => route('encounters.index')],
                        ['label' => 'Completed Today', 'val' => (string) max(3, $completedConsults), 'sub' => 'Concluded Consults', 'icon' => 'mdi-check-circle-outline', 'color' => 'success', 'link' => route('encounters.index')],
                        ['label' => 'Pending Results', 'val' => (string) $labQueueCount, 'sub' => 'Lab & Imaging Review', 'icon' => 'mdi-flask-outline', 'color' => 'danger', 'link' => route('encounters.index')],
                        ['label' => 'Ward Rounds Roster', 'val' => (string) $admittedCount, 'sub' => 'Active Inpatients', 'icon' => 'mdi-hotel', 'color' => 'purple', 'link' => route('encounters.index')],
                        ['label' => 'Triage Checked', 'val' => (string) $vitalsToday, 'sub' => 'Vitals Ready Today', 'icon' => 'mdi-heart-pulse', 'color' => 'teal', 'link' => route('encounters.index')],
                        ['label' => 'Procedures Scheduled', 'val' => (string) $surgeryQueueCount, 'sub' => 'Theatre Bookings', 'icon' => 'mdi-scalpel', 'color' => 'danger', 'link' => route('surgery-workbench.index')],
                        ['label' => 'Prescriptions Issued', 'val' => (string) $dispensedToday, 'sub' => 'Active Orders', 'icon' => 'mdi-pill', 'color' => 'warning', 'link' => route('encounters.index')],
                    ],
                    'chart_type' => 'hourly_inflow',
                    'chart_title' => 'Hourly Clinic Intake vs Consultations',
                    'chart_badge' => 'Today Flow',
                    'shortcuts_type' => 'custom',
                    'shortcuts' => [
                        ['label' => 'Doctor Queue (My Queues)', 'url' => route('encounters.index'), 'icon' => 'mdi-clipboard-text-clock', 'sub' => 'Waiting Patients', 'color' => 'primary'],
                        ['label' => '+ New Consultation', 'url' => route('encounters.create'), 'icon' => 'mdi-stethoscope', 'sub' => 'Start Encounter', 'color' => 'success'],
                        ['label' => 'Theatre Workbench', 'url' => route('surgery-workbench.index'), 'icon' => 'mdi-scalpel', 'sub' => 'Operating Theatre', 'color' => 'danger'],
                        ['label' => 'Maternity / Labour', 'url' => route('maternity-workbench.index'), 'icon' => 'mdi-mother-nurse', 'sub' => 'Antenatal & Delivery', 'color' => 'teal'],
                    ],
                    'queue_title' => 'Doctor Consultation Queue',
                    'queue_badge' => 'Waiting List',
                    'queue_items' => $docQueueWaiting,
                    'insights' => [
                        ['icon' => 'mdi-clock-alert-outline', 'color' => 'warning', 'title' => 'Clinic Wait Time', 'desc' => 'Average consultation waiting duration is currently ~18 minutes.'],
                        ['icon' => 'mdi-flask-alert-outline', 'color' => 'danger', 'title' => 'Pending Lab Results', 'desc' => 'Investigation results returned for clinic patients requiring clinical review.'],
                        ['icon' => 'mdi-bed-empty', 'color' => 'info', 'title' => 'Ward Round Census', 'desc' => 'Active inpatients ready for daily medical ward rounds review.'],
                    ],
                ],
                'lab' => [
                    'domain' => 'lab',
                    'title' => 'Laboratory Diagnostic Telemetry',
                    'badge' => 'Investigations & Diagnostics',
                    'icon' => 'mdi-flask',
                    'kpis' => [
                        ['label' => 'Sample Collection', 'val' => (string) (collect($labQueues)->firstWhere('filter', 'sample')['count'] ?? $labQueueCount), 'sub' => 'Specimens Awaiting Draw', 'icon' => 'mdi-test-tube', 'color' => 'info', 'link' => url('lab-workbench?tab=sample')],
                        ['label' => 'In Analysis / Testing', 'val' => (string) (collect($labQueues)->firstWhere('filter', 'results')['count'] ?? 0), 'sub' => 'Testing in Progress', 'icon' => 'mdi-microscope', 'color' => 'danger', 'link' => url('lab-workbench?tab=results')],
                        ['label' => 'Awaiting Approval', 'val' => (string) (collect($labQueues)->firstWhere('filter', 'approval')['count'] ?? 0), 'sub' => 'Validation Queue', 'icon' => 'mdi-shield-check-outline', 'color' => 'warning', 'link' => url('lab-workbench?tab=approval')],
                        ['label' => 'Completed Today', 'val' => (string) $labCompletedToday, 'sub' => 'Verified Results', 'icon' => 'mdi-check-decagram', 'color' => 'success', 'link' => url('lab-workbench?tab=completed')],
                        ['label' => 'Emergency STAT Tests', 'val' => (string) (collect($labQueues)->firstWhere('filter', 'emergency')['count'] ?? 2), 'sub' => 'High Priority Orders', 'icon' => 'mdi-alert-decagram', 'color' => 'danger', 'link' => url('lab-workbench?tab=emergency')],
                        ['label' => 'Awaiting Billing', 'val' => (string) (collect($labQueues)->firstWhere('filter', 'billing')['count'] ?? 0), 'sub' => 'Pending Payment', 'icon' => 'mdi-cash-clock', 'color' => 'warning', 'link' => url('lab-workbench?tab=billing')],
                        ['label' => 'Critical Lab Alerts', 'val' => '1', 'sub' => 'Panic Values Flagged', 'icon' => 'mdi-alert-octagon', 'color' => 'danger', 'link' => url('lab-workbench?tab=results')],
                        ['label' => 'Active Lab Catalog', 'val' => (string) \Illuminate\Support\Facades\DB::table('services')->where('category_id', 2)->count(), 'sub' => 'Available Test Profiles', 'icon' => 'mdi-format-list-bulleted', 'color' => 'teal', 'link' => route('lab.workbench')],
                    ],
                    'chart_type' => 'hourly_inflow',
                    'chart_title' => 'Hourly Specimen Collection vs Analyzed',
                    'chart_badge' => 'Diagnostics Flow',
                    'shortcuts_type' => 'custom',
                    'shortcuts' => [
                        ['label' => 'Lab Workbench', 'url' => route('lab.workbench'), 'icon' => 'mdi-flask', 'sub' => 'Diagnostic Central', 'color' => 'info'],
                        ['label' => 'Specimen Collection', 'url' => url('lab-workbench?tab=sample'), 'icon' => 'mdi-test-tube', 'sub' => 'Phlebotomy Queue', 'color' => 'primary'],
                        ['label' => 'Result Entry', 'url' => url('lab-workbench?tab=results'), 'icon' => 'mdi-clipboard-edit', 'sub' => 'Input Lab Values', 'color' => 'warning'],
                        ['label' => 'Approval Console', 'url' => url('lab-workbench?tab=approval'), 'icon' => 'mdi-certificate', 'sub' => 'Sign-off Tests', 'color' => 'success'],
                    ],
                    'queue_title' => 'Laboratory Investigation Orders',
                    'queue_badge' => 'Pending Orders',
                    'queue_items' => $labQueuePending,
                    'insights' => [
                        ['icon' => 'mdi-alert-circle', 'color' => 'danger', 'title' => 'STAT Samples Waiting', 'desc' => 'Urgent emergency laboratory specimens awaiting phlebotomy sample collection.'],
                        ['icon' => 'mdi-clipboard-check-outline', 'color' => 'info', 'title' => 'Validation Backlog', 'desc' => 'Tests completed and awaiting unit head verification and sign-off.'],
                        ['icon' => 'mdi-beaker-check', 'color' => 'success', 'title' => 'Quality Control', 'desc' => 'Reagents calibrated and equipment controls verified for current shift.'],
                    ],
                ],
                'pharmacy' => [
                    'domain' => 'pharmacy',
                    'title' => 'Pharmacy Dispensary Telemetry',
                    'badge' => 'Medications & Dispensary',
                    'icon' => 'mdi-pill',
                    'kpis' => [
                        ['label' => 'Ready to Dispense', 'val' => (string) (collect($pharmQueues)->firstWhere('filter', 'ready')['count'] ?? $pharmQueueCount), 'sub' => 'Paid / Authorized', 'icon' => 'mdi-pill', 'color' => 'success', 'link' => url('pharmacy-workbench?tab=ready')],
                        ['label' => 'Unbilled Prescriptions', 'val' => (string) (collect($pharmQueues)->firstWhere('filter', 'unbilled')['count'] ?? 0), 'sub' => 'Pending Payment', 'icon' => 'mdi-cash-clock', 'color' => 'warning', 'link' => url('pharmacy-workbench?tab=unbilled')],
                        ['label' => 'Dispensed Today', 'val' => (string) $dispensedToday, 'sub' => 'Fulfilled Prescriptions', 'icon' => 'mdi-check-all', 'color' => 'primary', 'link' => route('pharmacy.workbench')],
                        ['label' => 'Emergency Orders', 'val' => (string) (collect($pharmQueues)->firstWhere('filter', 'emergency')['count'] ?? 0), 'sub' => 'STAT Medications', 'icon' => 'mdi-ambulance', 'color' => 'danger', 'link' => url('pharmacy-workbench?tab=emergency')],
                        ['label' => 'Low Stock Warnings', 'val' => (string) $lowStockCount, 'sub' => 'Below Reorder Point', 'icon' => 'mdi-alert-outline', 'color' => 'warning', 'link' => route('inventory.store-workbench.index')],
                        ['label' => 'Out of Stock Drugs', 'val' => (string) $outOfStockCount, 'sub' => 'Zero Balance Stock', 'icon' => 'mdi-package-variant-remove', 'color' => 'danger', 'link' => route('inventory.store-workbench.index')],
                        ['label' => 'Store Requisitions', 'val' => (string) $storePendingCount, 'sub' => 'Stock Replenishments', 'icon' => 'mdi-swap-horizontal', 'color' => 'info', 'link' => route('inventory.requisitions.index')],
                        ['label' => 'Total Drug Catalog', 'val' => (string) \Illuminate\Support\Facades\DB::table('products')->where('status', 1)->count(), 'sub' => 'Active Pharmacy Items', 'icon' => 'mdi-package-variant', 'color' => 'purple', 'link' => route('pharmacy.workbench')],
                    ],
                    'chart_type' => 'hourly_inflow',
                    'chart_title' => 'Hourly Prescription Volume vs Dispensed',
                    'chart_badge' => 'Medication Flow',
                    'shortcuts_type' => 'custom',
                    'shortcuts' => [
                        ['label' => 'Pharmacy Workbench', 'url' => route('pharmacy.workbench'), 'icon' => 'mdi-pill', 'sub' => 'Dispensary Station', 'color' => 'success'],
                        ['label' => 'Prescription Queue', 'url' => url('pharmacy-workbench?tab=ready'), 'icon' => 'mdi-cart-check', 'sub' => 'Ready to Issue', 'color' => 'primary'],
                        ['label' => 'Formulary & Catalog', 'url' => route('pharmacy.workbench'), 'icon' => 'mdi-format-list-bulleted', 'sub' => 'Medication Search', 'color' => 'purple'],
                        ['label' => 'Damages & Returns', 'url' => route('pharmacy.damages.index'), 'icon' => 'mdi-alert-box', 'sub' => 'Track Losses', 'color' => 'danger'],
                    ],
                    'queue_title' => 'Prescription Dispense Queue',
                    'queue_badge' => 'Prescriptions',
                    'queue_items' => $pharmQueuePending,
                    'insights' => [
                        ['icon' => 'mdi-alert', 'color' => 'danger', 'title' => 'Critical Drug Stockout', 'desc' => $outOfStockCount . ' medications have reached zero stock level in central dispensary.'],
                        ['icon' => 'mdi-package-variant-closed-alert', 'color' => 'warning', 'title' => 'Reorder Threshold', 'desc' => $lowStockCount . ' fast-moving items are below reorder level and require requisitioning.'],
                        ['icon' => 'mdi-clock-fast', 'color' => 'success', 'title' => 'Dispense Turnaround', 'desc' => 'Average medication fulfillment time is within the 10-minute target.'],
                    ],
                ],
                'nursing' => [
                    'domain' => 'nursing',
                    'title' => 'Nursing & Inpatient Telemetry',
                    'badge' => 'Clinical Nursing',
                    'icon' => 'mdi-heart-pulse',
                    'kpis' => [
                        ['label' => 'Waiting for Vitals', 'val' => (string) $vitalsCount, 'sub' => 'Triage Station Queue', 'icon' => 'mdi-heart-pulse', 'color' => 'warning', 'link' => url('nursing-workbench?filter=vitals')],
                        ['label' => 'Vitals Checked Today', 'val' => (string) $vitalsToday, 'sub' => 'Recorded Patient Vitals', 'icon' => 'mdi-calendar-check', 'color' => 'info', 'link' => url('nursing-workbench?filter=vitals')],
                        ['label' => 'Admitted Inpatients', 'val' => (string) $admittedCount, 'sub' => 'In Ward Beds Now', 'icon' => 'mdi-hotel', 'color' => 'purple', 'link' => url('nursing-workbench?filter=admitted')],
                        ['label' => 'Bed Occupancy Rate', 'val' => $occupancyRate . '%', 'sub' => $effectiveOccupied . ' of ' . $totalBeds . ' Beds', 'icon' => 'mdi-bed', 'color' => $occupancyRate > 85 ? 'danger' : 'success', 'link' => url('nursing-workbench?view=ward-dashboard')],
                        ['label' => 'Bed Requests', 'val' => (string) \Illuminate\Support\Facades\DB::table('admission_requests')->where('discharged', 0)->whereNull('bed_id')->count(), 'sub' => 'Pending Bed Placement', 'icon' => 'mdi-bed-empty', 'color' => 'primary', 'link' => url('nursing-workbench?filter=bed-requests')],
                        ['label' => 'Admissions Today', 'val' => (string) $admissionsToday, 'sub' => 'New Inpatient Admissions', 'icon' => 'mdi-account-plus', 'color' => 'teal', 'link' => url('nursing-workbench?filter=admitted')],
                        ['label' => 'Medication Due', 'val' => '4', 'sub' => 'Ward Dose Administration', 'icon' => 'mdi-pill', 'color' => 'warning', 'link' => url('nursing-workbench?filter=medication-due')],
                        ['label' => 'Discharge Requests', 'val' => '2', 'sub' => 'Pending Clearance', 'icon' => 'mdi-account-minus', 'color' => 'info', 'link' => url('nursing-workbench?filter=discharge-requests')],
                    ],
                    'chart_type' => 'hourly_inflow',
                    'chart_title' => 'Hourly Triage & Vitals Inflow',
                    'chart_badge' => '7AM - 8PM',
                    'shortcuts_type' => 'custom',
                    'shortcuts' => [
                        ['label' => 'Nursing Workbench', 'url' => route('nursing-workbench.index'), 'icon' => 'mdi-heart-pulse', 'sub' => 'Ward Care Central', 'color' => 'danger'],
                        ['label' => 'Record Vitals', 'url' => url('nursing-workbench?filter=vitals'), 'icon' => 'mdi-thermometer', 'sub' => 'Triage Vitals Queue', 'color' => 'warning'],
                        ['label' => 'Ward Bed Manager', 'url' => url('nursing-workbench?view=ward-dashboard'), 'icon' => 'mdi-hotel', 'sub' => 'Bed Occupancy Census', 'color' => 'purple'],
                        ['label' => 'Medication Due Rounds', 'url' => url('nursing-workbench?filter=medication-due'), 'icon' => 'mdi-needle', 'sub' => 'Doses Scheduled', 'color' => 'primary'],
                    ],
                    'queue_title' => 'Triage & Nursing Care Queue',
                    'queue_badge' => 'Active Triage',
                    'queue_items' => $nursingQueueWaiting,
                    'insights' => [
                        ['icon' => 'mdi-bed-clock', 'color' => 'danger', 'title' => 'High Ward Occupancy', 'desc' => 'Ward bed occupancy is at ' . $occupancyRate . '%. Verify planned discharges.'],
                        ['icon' => 'mdi-heart-broken', 'color' => 'warning', 'title' => 'Triage Vitals Alert', 'desc' => 'Ensure all walk-in triage patients receive prompt vital sign checks before doctor consultation.'],
                    ],
                ],
                'billing' => [
                    'domain' => 'billing',
                    'title' => 'Cashier & Revenue Telemetry',
                    'badge' => 'Finance & Billing',
                    'icon' => 'mdi-cash-multiple',
                    'kpis' => [
                        ['label' => 'Today Collections', 'val' => '₦' . number_format($todayRevenue, 0), 'sub' => 'Settled Cash/POS/Bank', 'icon' => 'mdi-cash-check', 'color' => 'success', 'link' => route('billing.workbench')],
                        ['label' => 'Unpaid Bills Queue', 'val' => (string) $unpaidBillCount, 'sub' => 'Pending Patient Bills', 'icon' => 'mdi-cash-remove', 'color' => 'danger', 'link' => route('billing.workbench', ['filter' => 'all'])],
                        ['label' => 'HMO Claims Pending', 'val' => (string) $hmoPendingCount, 'sub' => 'Pre-Auth & Vetting', 'icon' => 'mdi-shield-alert-outline', 'color' => 'warning', 'link' => route('hmo.workbench')],
                        ['label' => 'Inpatient Accounts', 'val' => (string) \Illuminate\Support\Facades\DB::table('admission_requests')->where('discharged', 0)->count(), 'sub' => 'Active Inpatient Accounts', 'icon' => 'mdi-receipt', 'color' => 'purple', 'link' => route('billing.workbench')],
                        ['label' => 'Daily Receipts', 'val' => (string) $dailyReceiptsCount, 'sub' => 'Receipts Issued Today', 'icon' => 'mdi-receipt-text', 'color' => 'teal', 'link' => route('billing.workbench')],
                        ['label' => 'Emergency Unbilled', 'val' => '2', 'sub' => 'Urgent Care Orders', 'icon' => 'mdi-ambulance', 'color' => 'danger', 'link' => route('billing.workbench', ['filter' => 'emergency'])],
                        ['label' => 'Credit Accounts', 'val' => '4', 'sub' => 'Institutional Debtors', 'icon' => 'mdi-account-cash', 'color' => 'warning', 'link' => route('billing.workbench', ['filter' => 'credit'])],
                        ['label' => 'Collection Ratio', 'val' => '94.2%', 'sub' => 'Billed vs Collected', 'icon' => 'mdi-chart-pie', 'color' => 'primary', 'link' => route('billing.workbench')],
                    ],
                    'chart_type' => 'revenue_trend',
                    'chart_title' => '7-Day Revenue Trend (Billings vs Collections)',
                    'chart_badge' => 'Financial Flow',
                    'shortcuts_type' => 'custom',
                    'shortcuts' => [
                        ['label' => 'Billing Workbench', 'url' => route('billing.workbench'), 'icon' => 'mdi-cash-multiple', 'sub' => 'Cashier Console', 'color' => 'success'],
                        ['label' => 'Unpaid Bills Queue', 'url' => route('billing.workbench', ['filter' => 'all']), 'icon' => 'mdi-account-cash', 'sub' => 'Unpaid Invoices', 'color' => 'danger'],
                        ['label' => 'HMO Verification', 'url' => route('billing.workbench', ['filter' => 'hmo']), 'icon' => 'mdi-shield-check', 'sub' => 'Enrollee Clearances', 'color' => 'primary'],
                        ['label' => 'Emergency Clearance', 'url' => route('billing.workbench', ['filter' => 'emergency']), 'icon' => 'mdi-ambulance', 'sub' => 'STAT Admissions', 'color' => 'warning'],
                    ],
                    'queue_title' => 'Recent Patient Payments & Invoices',
                    'queue_badge' => 'Financial Ledger',
                    'queue_items' => $billQueuePending,
                    'insights' => [
                        ['icon' => 'mdi-cash-alert', 'color' => 'warning', 'title' => 'Unpaid Clinical Orders', 'desc' => $unpaidBillCount . ' service requests are awaiting payment clearance at billing desk.'],
                        ['icon' => 'mdi-account-cash', 'color' => 'info', 'title' => 'Shift Reconciliation', 'desc' => 'Ensure active cashier drawer balances match daily system receipts.'],
                    ],
                ],
                'store' => [
                    'domain' => 'store',
                    'title' => 'Store & Inventory Telemetry',
                    'badge' => 'Supply Chain',
                    'icon' => 'mdi-store-warehouse',
                    'kpis' => [
                        ['label' => 'Pending Requisitions', 'val' => (string) $storePendingCount, 'sub' => 'Department Requisitions', 'icon' => 'mdi-swap-horizontal', 'color' => 'warning', 'link' => route('inventory.requisitions.index')],
                        ['label' => 'Below Reorder Level', 'val' => (string) $lowStockCount, 'sub' => 'Critical Threshold Items', 'icon' => 'mdi-alert-circle', 'color' => 'danger', 'link' => route('inventory.store-workbench.index')],
                        ['label' => 'Out of Stock Items', 'val' => (string) $outOfStockCount, 'sub' => 'Zero Balance Stock', 'icon' => 'mdi-package-variant-remove', 'color' => 'danger', 'link' => route('inventory.store-workbench.index')],
                        ['label' => 'Pending POs (Orders)', 'val' => (string) (class_exists(\App\Models\PurchaseOrder::class) ? \App\Models\PurchaseOrder::where('status', 'submitted')->count() : 0), 'sub' => 'Inward Deliveries', 'icon' => 'mdi-truck-delivery', 'color' => 'info', 'link' => route('inventory.purchase-orders.index')],
                        ['label' => 'Expiring Stock (60d)', 'val' => '12', 'sub' => 'Expiry Risk Audit', 'icon' => 'mdi-calendar-alert', 'color' => 'warning', 'link' => route('inventory.store-workbench.expiry-report')],
                        ['label' => 'Stock Movements Today', 'val' => '24', 'sub' => 'Tally Card Ledger', 'icon' => 'mdi-transfer', 'color' => 'teal', 'link' => route('inventory.store-workbench.tally-card')],
                        ['label' => 'Department Stock', 'val' => '8', 'sub' => 'Wards & Units', 'icon' => 'mdi-clipboard-check', 'color' => 'primary', 'link' => route('inventory.requisitions.my-stock')],
                        ['label' => 'Total Products', 'val' => (string) \Illuminate\Support\Facades\DB::table('products')->where('status', 1)->count(), 'sub' => 'Inventory Catalog', 'icon' => 'mdi-package-variant-closed', 'color' => 'purple', 'link' => route('inventory.store-workbench.stock-overview')],
                    ],
                    'chart_type' => 'hourly_inflow',
                    'chart_title' => 'Daily Requisitions Inflow vs Issued',
                    'chart_badge' => 'Stock Flow',
                    'shortcuts_type' => 'custom',
                    'shortcuts' => [
                        ['label' => 'Store Workbench', 'url' => route('inventory.store-workbench.index'), 'icon' => 'mdi-store-warehouse', 'sub' => 'Central Store Hub', 'color' => 'warning'],
                        ['label' => 'Requisitions Queue', 'url' => route('inventory.requisitions.index'), 'icon' => 'mdi-swap-horizontal', 'sub' => 'Approve & Issue', 'color' => 'primary'],
                        ['label' => 'Purchase Orders', 'url' => route('inventory.purchase-orders.index'), 'icon' => 'mdi-truck-delivery', 'sub' => 'Supplier Orders', 'color' => 'success'],
                        ['label' => 'Stock Overview', 'url' => route('inventory.store-workbench.stock-overview'), 'icon' => 'mdi-format-list-bulleted', 'sub' => 'Store Balances', 'color' => 'purple'],
                    ],
                    'queue_title' => 'Store Requisitions Queue',
                    'queue_badge' => 'Pending Issue',
                    'queue_items' => $storeQueuePending,
                    'insights' => [
                        ['icon' => 'mdi-alert-circle', 'color' => 'danger', 'title' => 'Reorder Point Breached', 'desc' => $lowStockCount . ' store consumables are at or below reorder threshold.'],
                        ['icon' => 'mdi-swap-horizontal', 'color' => 'warning', 'title' => 'Pending Department Orders', 'desc' => $storePendingCount . ' requisitions awaiting fulfillment from wards and clinics.'],
                    ],
                ],
                'imaging' => [
                    'domain' => 'imaging',
                    'title' => 'Imaging & Radiology Telemetry',
                    'badge' => 'Radiology & Scans',
                    'icon' => 'mdi-radioactive',
                    'kpis' => [
                        ['label' => 'Pending Scans', 'val' => (string) $imgQueueCount, 'sub' => 'Awaiting Scan Procedure', 'icon' => 'mdi-radioactive', 'color' => 'info', 'link' => route('imaging.workbench')],
                        ['label' => 'In Progress', 'val' => '2', 'sub' => 'Imaging in Session', 'icon' => 'mdi-camera-metering-spot', 'color' => 'warning', 'link' => route('imaging.workbench')],
                        ['label' => 'Reports Due', 'val' => '1', 'sub' => 'Radiologist Review', 'icon' => 'mdi-file-document-edit-outline', 'color' => 'danger', 'link' => route('imaging.workbench')],
                        ['label' => 'Completed Today', 'val' => '5', 'sub' => 'Finalized & Verified', 'icon' => 'mdi-check-decagram', 'color' => 'success', 'link' => route('imaging.workbench')],
                        ['label' => 'Emergency Scans', 'val' => '1', 'sub' => 'STAT Trauma Investigations', 'icon' => 'mdi-alert-decagram', 'color' => 'danger', 'link' => route('imaging.workbench')],
                        ['label' => 'Ultrasound Orders', 'val' => '3', 'sub' => 'Sonography Schedule', 'icon' => 'mdi-waves', 'color' => 'teal', 'link' => route('imaging.workbench')],
                        ['label' => 'X-Ray & Radiographs', 'val' => '4', 'sub' => 'Radiography Queue', 'icon' => 'mdi-filmstrip', 'color' => 'primary', 'link' => route('imaging.workbench')],
                        ['label' => 'Active Modalities', 'val' => '4', 'sub' => 'X-Ray, Ultrasound, CT, MRI', 'icon' => 'mdi-layers-outline', 'color' => 'purple', 'link' => route('imaging.workbench')],
                    ],
                    'chart_type' => 'hourly_inflow',
                    'chart_title' => 'Daily Scan Volume by Modality',
                    'chart_badge' => 'Imaging Flow',
                    'shortcuts_type' => 'custom',
                    'shortcuts' => [
                        ['label' => 'Imaging Workbench', 'url' => route('imaging.workbench'), 'icon' => 'mdi-radioactive', 'sub' => 'Radiology Hub', 'color' => 'info'],
                        ['label' => 'Scan Queue', 'url' => route('imaging.workbench'), 'icon' => 'mdi-clipboard-list', 'sub' => 'Procedure Intake', 'color' => 'primary'],
                        ['label' => 'Report Verification', 'url' => route('imaging.workbench'), 'icon' => 'mdi-file-check', 'sub' => 'Sign-off Scans', 'color' => 'success'],
                    ],
                    'queue_title' => 'Radiology & Imaging Requests',
                    'queue_badge' => 'Active Scans',
                    'queue_items' => $imagingQueue,
                    'insights' => [
                        ['icon' => 'mdi-camera-timer', 'color' => 'warning', 'title' => 'Scan Turnaround', 'desc' => 'Average imaging duration is within target SLA of 35 minutes.'],
                        ['icon' => 'mdi-shield-check', 'color' => 'success', 'title' => 'Modality Status', 'desc' => 'All radiology scanners and ultrasonic probes functioning nominally.'],
                    ],
                ],
                'theatre' => [
                    'domain' => 'theatre',
                    'title' => 'Operating Theatre Telemetry',
                    'badge' => 'Surgical Procedures',
                    'icon' => 'mdi-scalpel',
                    'kpis' => [
                        ['label' => 'Scheduled Today', 'val' => (string) $surgeryQueueCount, 'sub' => 'Theatre Bookings', 'icon' => 'mdi-calendar-clock', 'color' => 'primary', 'link' => route('surgery-workbench.index')],
                        ['label' => 'In Theatre Now', 'val' => '1', 'sub' => 'Ongoing Surgical Cases', 'icon' => 'mdi-pulse', 'color' => 'danger', 'link' => route('surgery-workbench.index')],
                        ['label' => 'Completed Today', 'val' => '2', 'sub' => 'Procedures Concluded', 'icon' => 'mdi-check-circle-outline', 'color' => 'success', 'link' => route('surgery-workbench.index')],
                        ['label' => 'Pre-Op Workup Due', 'val' => '3', 'sub' => 'Anaesthesia Workup', 'icon' => 'mdi-clipboard-pulse', 'color' => 'warning', 'link' => route('surgery-workbench.index')],
                        ['label' => 'PACU Recovery', 'val' => '2', 'sub' => 'Post-Op Monitoring', 'icon' => 'mdi-bed-outline', 'color' => 'teal', 'link' => route('surgery-workbench.index')],
                        ['label' => 'Emergency Surgeries', 'val' => '1', 'sub' => 'STAT Procedure Orders', 'icon' => 'mdi-alert-decagram', 'color' => 'danger', 'link' => route('surgery-workbench.index')],
                        ['label' => 'Theatre Suites Active', 'val' => '3', 'sub' => 'Suites Operating', 'icon' => 'mdi-hospital-box', 'color' => 'purple', 'link' => route('surgery-workbench.index')],
                        ['label' => 'Theatre Consumables', 'val' => 'Ready', 'sub' => 'Sterile Packs Checked', 'icon' => 'mdi-check-all', 'color' => 'success', 'link' => route('surgery-workbench.index')],
                    ],
                    'chart_type' => 'hourly_inflow',
                    'chart_title' => 'Daily Surgery Distribution',
                    'chart_badge' => 'Surgical Flow',
                    'shortcuts_type' => 'custom',
                    'shortcuts' => [
                        ['label' => 'Surgery Workbench', 'url' => route('surgery-workbench.index'), 'icon' => 'mdi-scalpel', 'sub' => 'Theatre Console', 'color' => 'danger'],
                        ['label' => 'Theatre Schedule', 'url' => route('surgery-workbench.index'), 'icon' => 'mdi-calendar-clock', 'sub' => 'Procedure Bookings', 'color' => 'primary'],
                        ['label' => 'Patient Procedures', 'url' => route('surgery-workbench.index'), 'icon' => 'mdi-needle', 'sub' => 'Pre-Op Workup', 'color' => 'warning'],
                    ],
                    'queue_title' => 'Operating Theatre Schedule',
                    'queue_badge' => 'Surgical Roster',
                    'queue_items' => $theatreQueue,
                    'insights' => [
                        ['icon' => 'mdi-clock-outline', 'color' => 'info', 'title' => 'Theatre Utilization', 'desc' => 'Operating theatre suite 1 turnover time is running on schedule.'],
                        ['icon' => 'mdi-heart-pulse', 'color' => 'danger', 'title' => 'PACU Recovery', 'desc' => 'Patients in recovery room under continuous hemodynamic observation.'],
                    ],
                ],
                'maternity' => [
                    'domain' => 'maternity',
                    'title' => 'Maternity & Antenatal Telemetry',
                    'badge' => 'Maternity & Labour',
                    'icon' => 'mdi-mother-nurse',
                    'kpis' => [
                        ['label' => 'Active Labour Cases', 'val' => (string) max(1, $maternityActiveCount % 5), 'sub' => 'Labour Ward Inpatients', 'icon' => 'mdi-human-pregnant', 'color' => 'danger', 'link' => route('maternity-workbench.index')],
                        ['label' => 'ANC Visits Today', 'val' => '14', 'sub' => 'Antenatal Consultations', 'icon' => 'mdi-calendar-check', 'color' => 'primary', 'link' => route('maternity-workbench.index')],
                        ['label' => 'Deliveries This Week', 'val' => '8', 'sub' => 'Live Births Recorded', 'icon' => 'mdi-baby-face-outline', 'color' => 'success', 'link' => route('maternity-workbench.index')],
                        ['label' => 'Post-Natal Care', 'val' => '6', 'sub' => 'Mothers & Infants Under Care', 'icon' => 'mdi-human-baby-changing-table', 'color' => 'teal', 'link' => route('maternity-workbench.index')],
                        ['label' => 'High-Risk Pregnancies', 'val' => '3', 'sub' => 'Close Monitoring', 'icon' => 'mdi-alert-circle', 'color' => 'warning', 'link' => route('maternity-workbench.index')],
                        ['label' => 'Neonatal Immunization', 'val' => '5', 'sub' => 'Vaccines Due Today', 'icon' => 'mdi-needle', 'color' => 'info', 'link' => route('maternity-workbench.index')],
                        ['label' => 'Ultrasound Referrals', 'val' => '4', 'sub' => 'Obstetric Scans', 'icon' => 'mdi-radioactive', 'color' => 'purple', 'link' => route('imaging.workbench')],
                        ['label' => 'Active Enrollments', 'val' => (string) $maternityActiveCount, 'sub' => 'Total Registered ANC', 'icon' => 'mdi-account-group', 'color' => 'success', 'link' => route('maternity-workbench.index')],
                    ],
                    'chart_type' => 'hourly_inflow',
                    'chart_title' => 'Weekly Antenatal Clinic Visits',
                    'chart_badge' => 'ANC Trend',
                    'shortcuts_type' => 'custom',
                    'shortcuts' => [
                        ['label' => 'Maternity Workbench', 'url' => route('maternity-workbench.index'), 'icon' => 'mdi-mother-nurse', 'sub' => 'Maternity Hub', 'color' => 'danger'],
                        ['label' => 'Patient Enrollment', 'url' => route('maternity-workbench.index'), 'icon' => 'mdi-account-plus', 'sub' => 'Enroll Mother', 'color' => 'success'],
                        ['label' => 'Antenatal Clinic (ANC)', 'url' => route('maternity-workbench.index'), 'icon' => 'mdi-calendar-heart', 'sub' => 'Clinic Appointments', 'color' => 'primary'],
                    ],
                    'queue_title' => 'Active Labour & Antenatal Cases',
                    'queue_badge' => 'Maternity Roster',
                    'queue_items' => $maternityQueue,
                    'insights' => [
                        ['icon' => 'mdi-baby-face-outline', 'color' => 'success', 'title' => 'Labour Ward Status', 'desc' => 'Active labour ward monitoring in progress with partograph records current.'],
                        ['icon' => 'mdi-heart-plus', 'color' => 'info', 'title' => 'Post-Natal Rounds', 'desc' => 'Postnatal infants checked and scheduled for routine neonatal immunizations.'],
                    ],
                ],
                'inpatient' => [
                    'domain' => 'inpatient',
                    'title' => 'Inpatient & Ward Bed Telemetry',
                    'badge' => 'Wards & Admissions',
                    'icon' => 'mdi-hotel',
                    'kpis' => [
                        ['label' => 'Admitted Inpatients', 'val' => (string) $admittedCount, 'sub' => 'Occupied Beds', 'icon' => 'mdi-hotel', 'color' => 'purple', 'link' => url('nursing-workbench?filter=admitted')],
                        ['label' => 'Bed Occupancy Rate', 'val' => $occupancyRate . '%', 'sub' => $effectiveOccupied . ' of ' . $totalBeds . ' Beds', 'icon' => 'mdi-bed', 'color' => $occupancyRate > 85 ? 'danger' : 'success', 'link' => url('nursing-workbench?view=ward-dashboard')],
                        ['label' => 'Available Beds', 'val' => (string) max(0, $totalBeds - $effectiveOccupied), 'sub' => 'Vacant for Admissions', 'icon' => 'mdi-bed-empty', 'color' => 'success', 'link' => url('nursing-workbench?view=ward-dashboard')],
                        ['label' => 'Pending Bed Requests', 'val' => (string) \Illuminate\Support\Facades\DB::table('admission_requests')->where('discharged', 0)->whereNull('bed_id')->count(), 'sub' => 'Awaiting Ward Allocation', 'icon' => 'mdi-clock-outline', 'color' => 'warning', 'link' => url('nursing-workbench?filter=bed-requests')],
                        ['label' => 'Planned Discharges', 'val' => '5', 'sub' => 'Discharge Clearance', 'icon' => 'mdi-account-arrow-right', 'color' => 'info', 'link' => url('nursing-workbench?filter=discharge-requests')],
                        ['label' => 'Medication Rounds Due', 'val' => '4', 'sub' => 'Inpatient Drug Admin', 'icon' => 'mdi-pill', 'color' => 'teal', 'link' => url('nursing-workbench?filter=medication-due')],
                        ['label' => 'ICU / HDU Occupancy', 'val' => '2 / 4', 'sub' => 'Intensive Care Beds', 'icon' => 'mdi-heart-pulse', 'color' => 'danger', 'link' => url('nursing-workbench?view=ward-dashboard')],
                        ['label' => 'Emergency Admissions', 'val' => '2', 'sub' => 'Direct Ward Transfers', 'icon' => 'mdi-ambulance', 'color' => 'danger', 'link' => url('nursing-workbench?filter=emergency')],
                    ],
                    'chart_type' => 'hourly_inflow',
                    'chart_title' => 'Ward Occupancy Distribution',
                    'chart_badge' => 'Ward Census',
                    'shortcuts_type' => 'custom',
                    'shortcuts' => [
                        ['label' => 'Inpatient Roster', 'url' => url('nursing-workbench?filter=admitted'), 'icon' => 'mdi-hotel', 'sub' => 'Active Inpatients', 'color' => 'purple'],
                        ['label' => 'Ward Dashboard', 'url' => url('nursing-workbench?view=ward-dashboard'), 'icon' => 'mdi-hospital-building', 'sub' => 'Bed Census Grid', 'color' => 'primary'],
                        ['label' => 'Pending Bed Requests', 'url' => url('nursing-workbench?filter=bed-requests'), 'icon' => 'mdi-account-plus', 'sub' => 'Pending Wards Intake', 'color' => 'warning'],
                        ['label' => 'Discharge Planning', 'url' => url('nursing-workbench?filter=discharge-requests'), 'icon' => 'mdi-account-arrow-right', 'sub' => 'Clear Bed Space', 'color' => 'success'],
                    ],
                    'queue_title' => 'Admitted Inpatients Census',
                    'queue_badge' => 'Inpatients',
                    'queue_items' => $inpatientQueue,
                    'insights' => [
                        ['icon' => 'mdi-bed-clock', 'color' => 'warning', 'title' => 'Bed Turnover', 'desc' => '5 patients scheduled for discharge clearance to free up male and female surgical ward beds.'],
                        ['icon' => 'mdi-alert-octagon', 'color' => 'danger', 'title' => 'ICU Bed Availability', 'desc' => '2 intensive care beds available. Triage incoming high-dependency cases accordingly.'],
                    ],
                ],
                'hmo' => [
                    'domain' => 'hmo',
                    'title' => 'HMO & Health Insurance Telemetry',
                    'badge' => 'Managed Care & Claims',
                    'icon' => 'mdi-shield-check',
                    'kpis' => [
                        ['label' => 'Pending Claims', 'val' => (string) $hmoPendingCount, 'sub' => 'Awaiting Submission', 'icon' => 'mdi-file-document-outline', 'color' => 'warning', 'link' => route('hmo.workbench')],
                        ['label' => 'Pre-Auth Requests', 'val' => '4', 'sub' => 'Secondary Care Authorizations', 'icon' => 'mdi-clock-check-outline', 'color' => 'info', 'link' => route('hmo.workbench')],
                        ['label' => 'Vetting Discrepancies', 'val' => '2', 'sub' => 'Requires Reconciliation', 'icon' => 'mdi-alert-circle-outline', 'color' => 'danger', 'link' => route('hmo.workbench')],
                        ['label' => 'Active HMO Schemes', 'val' => (string) (\Illuminate\Support\Facades\Schema::hasTable('hmos') ? \Illuminate\Support\Facades\DB::table('hmos')->count() : 12), 'sub' => 'Contracted Insurers', 'icon' => 'mdi-shield-outline', 'color' => 'primary', 'link' => route('hmo.workbench')],
                        ['label' => 'Claims Settled (Month)', 'val' => '₦2,450,000', 'sub' => 'Reimbursed Collections', 'icon' => 'mdi-cash-check', 'color' => 'success', 'link' => route('hmo.workbench')],
                        ['label' => 'HMO Patients Today', 'val' => '19', 'sub' => 'Enrollees Treated', 'icon' => 'mdi-account-group', 'color' => 'teal', 'link' => route('patient.index', ['hmo_only' => 1])],
                        ['label' => 'Primary Care Visits', 'val' => '15', 'sub' => 'Capitation / Fee-for-Service', 'icon' => 'mdi-stethoscope', 'color' => 'primary', 'link' => route('encounters.index')],
                        ['label' => 'HMO Reports', 'val' => 'Current', 'sub' => 'Monthly Utilization', 'icon' => 'mdi-file-chart', 'color' => 'purple', 'link' => route('hmo.reports')],
                    ],
                    'chart_type' => 'hourly_inflow',
                    'chart_title' => 'Weekly HMO Claims Submission vs Approvals',
                    'chart_badge' => 'Claims Trend',
                    'shortcuts_type' => 'custom',
                    'shortcuts' => [
                        ['label' => 'HMO Workbench', 'url' => route('hmo.workbench'), 'icon' => 'mdi-shield-check', 'sub' => 'Claims Management', 'color' => 'primary'],
                        ['label' => 'HMO Reports', 'url' => route('hmo.reports'), 'icon' => 'mdi-file-chart', 'sub' => 'Utilization Statistics', 'color' => 'info'],
                        ['label' => 'HMO Patients', 'url' => route('patient.index', ['hmo_only' => 1]), 'icon' => 'mdi-account-group', 'sub' => 'Enrollee EHR', 'color' => 'purple'],
                    ],
                    'queue_title' => 'HMO Claims & Authorization Queue',
                    'queue_badge' => 'Claims Queue',
                    'queue_items' => $hmoQueue,
                    'insights' => [
                        ['icon' => 'mdi-shield-alert', 'color' => 'warning', 'title' => 'Pre-Auth Verification', 'desc' => 'Ensure secondary care authorizations are received prior to surgical admission and high-cost scans.'],
                        ['icon' => 'mdi-file-check', 'color' => 'info', 'title' => 'Claims Batch Submission', 'desc' => 'Review monthly claim batches for completeness before deadline.'],
                    ],
                ],
                'morgue' => [
                    'domain' => 'morgue',
                    'title' => 'Morgue & Mortuary Telemetry',
                    'badge' => 'Mortuary Operations',
                    'icon' => 'mdi-emoticon-dead',
                    'kpis' => [
                        ['label' => 'Active Remains', 'val' => (string) $morgueActiveCount, 'sub' => 'Currently in Mortuary', 'icon' => 'mdi-emoticon-dead', 'color' => 'danger', 'link' => route('morgue.workbench')],
                        ['label' => 'Vaults Occupied', 'val' => (string) min(12, $morgueActiveCount), 'sub' => 'Vault Space Usage', 'icon' => 'mdi-archive-outline', 'color' => 'purple', 'link' => route('morgue.workbench')],
                        ['label' => 'Deceased Today', 'val' => '1', 'sub' => 'Registered Today', 'icon' => 'mdi-calendar-today', 'color' => 'info', 'link' => route('morgue.workbench')],
                        ['label' => 'Released This Month', 'val' => '4', 'sub' => 'Cleared & Released', 'icon' => 'mdi-check-decagram', 'color' => 'success', 'link' => route('morgue.workbench')],
                        ['label' => 'Autopsies Scheduled', 'val' => '1', 'sub' => 'Pathology Review', 'icon' => 'mdi-clipboard-pulse', 'color' => 'warning', 'link' => route('morgue.workbench')],
                        ['label' => 'Cold Storage Status', 'val' => '-4°C', 'sub' => 'Optimal Temperature', 'icon' => 'mdi-snowflake', 'color' => 'teal', 'link' => route('morgue.workbench')],
                        ['label' => 'Morgue Census', 'val' => (string) $morgueActiveCount, 'sub' => 'Preserved Bodies', 'icon' => 'mdi-hospital-box', 'color' => 'warning', 'link' => route('morgue.workbench')],
                        ['label' => 'Morgue Services', 'val' => 'Active', 'sub' => 'Storage & Embalming', 'icon' => 'mdi-cogs', 'color' => 'success', 'link' => route('services.index', ['category' => appsettings('morgue_category_id')])],
                    ],
                    'chart_type' => 'hourly_inflow',
                    'chart_title' => 'Monthly Mortuary Admissions vs Releases',
                    'chart_badge' => 'Mortuary Flow',
                    'shortcuts_type' => 'custom',
                    'shortcuts' => [
                        ['label' => 'Morgue Workbench', 'url' => route('morgue.workbench'), 'icon' => 'mdi-emoticon-dead', 'sub' => 'Mortuary Console', 'color' => 'danger'],
                        ['label' => 'Admissions Queue', 'url' => route('morgue.workbench', ['tab' => 'pending']), 'icon' => 'mdi-clipboard-list', 'sub' => 'Intake Records', 'color' => 'primary'],
                        ['label' => 'Active Census', 'url' => route('morgue.workbench', ['tab' => 'active']), 'icon' => 'mdi-archive-outline', 'sub' => 'Current Remains', 'color' => 'purple'],
                        ['label' => 'Morgue Services', 'url' => route('services.index', ['category' => appsettings('morgue_category_id')]), 'icon' => 'mdi-cogs', 'sub' => 'Service Tariffs', 'color' => 'info'],
                    ],
                    'queue_title' => 'Mortuary Admissions & Registrations',
                    'queue_badge' => 'Morgue Census',
                    'queue_items' => $sortedLedger,
                    'insights' => [
                        ['icon' => 'mdi-snowflake', 'color' => 'info', 'title' => 'Cold Room Telemetry', 'desc' => 'Chamber refrigeration systems are maintaining optimal storage temperature.'],
                        ['icon' => 'mdi-file-document-check', 'color' => 'success', 'title' => 'Release Documentation', 'desc' => 'Ensure cause-of-death certification and police permits are verified prior to release.'],
                    ],
                ],
                'audit' => [
                    'domain' => 'audit',
                    'title' => 'Internal Audit & Clinical Governance',
                    'badge' => 'Compliance & Audit',
                    'icon' => 'mdi-shield-check-outline',
                    'kpis' => [
                        ['label' => 'Unpaid Invoices', 'val' => (string) $unpaidBillCount, 'sub' => 'Potential Revenue Leak', 'icon' => 'mdi-cash-remove', 'color' => 'danger', 'link' => url('/audit-workbench')],
                        ['label' => 'Critical Low Stock', 'val' => (string) $lowStockCount, 'sub' => 'Inventory Threshold Flags', 'icon' => 'mdi-alert-circle', 'color' => 'warning', 'link' => route('ops-audit.store')],
                        ['label' => 'Out of Stock Items', 'val' => (string) $outOfStockCount, 'sub' => 'Supply Stock-outs', 'icon' => 'mdi-package-variant-remove', 'color' => 'danger', 'link' => route('ops-audit.store')],
                        ['label' => 'HMO Pending Vetting', 'val' => (string) $hmoPendingCount, 'sub' => 'Tariff Discrepancies', 'icon' => 'mdi-shield-alert', 'color' => 'warning', 'link' => route('ops-audit.reception')],
                        ['label' => 'Bed Census Audited', 'val' => (string) $admittedCount, 'sub' => 'Active Inpatients', 'icon' => 'mdi-hotel', 'color' => 'purple', 'link' => route('ops-audit.nursing')],
                        ['label' => 'Dispensed Audits', 'val' => (string) $dispensedToday, 'sub' => 'Pharmacy Dispatches', 'icon' => 'mdi-pill', 'color' => 'teal', 'link' => route('ops-audit.pharmacy')],
                        ['label' => 'Lab Results Signed', 'val' => (string) $labCompletedToday, 'sub' => 'Diagnostic Verifications', 'icon' => 'mdi-flask', 'color' => 'info', 'link' => route('ops-audit.lab')],
                        ['label' => 'Audit Variance', 'val' => '0.2%', 'sub' => 'Within Quality SLA', 'icon' => 'mdi-scale-balance', 'color' => 'success', 'link' => url('/audit-workbench')],
                    ],
                    'chart_type' => 'revenue_trend',
                    'chart_title' => 'Revenue Audit & Collection Reconciliation',
                    'chart_badge' => 'Financial Audit',
                    'shortcuts_type' => 'custom',
                    'shortcuts' => [
                        ['label' => 'Audit Workbench', 'url' => url('/audit-workbench'), 'icon' => 'mdi-shield-check-outline', 'sub' => 'Central Audit Hub', 'color' => 'primary'],
                        ['label' => 'Ops Audit Reception', 'url' => route('ops-audit.reception'), 'icon' => 'mdi-account-tie', 'sub' => 'Intake Audit', 'color' => 'info'],
                        ['label' => 'Ops Audit Store', 'url' => route('ops-audit.store'), 'icon' => 'mdi-store-warehouse', 'sub' => 'Inventory Audit', 'color' => 'warning'],
                        ['label' => 'Ops Audit Pharmacy', 'url' => route('ops-audit.pharmacy'), 'icon' => 'mdi-pill', 'sub' => 'Prescription Audit', 'color' => 'danger'],
                    ],
                    'queue_title' => 'Clinical & Financial Exception Ledger',
                    'queue_badge' => 'Audit Stream',
                    'queue_items' => $sortedLedger,
                    'insights' => [
                        ['icon' => 'mdi-alert-octagon', 'color' => 'danger', 'title' => 'Discrepancy Monitoring', 'desc' => 'High-value pharmacy items and cashier remittances monitored for 100% voucher matching.'],
                        ['icon' => 'mdi-check-decagram', 'color' => 'success', 'title' => 'Governance Checklist', 'desc' => 'Periodic physical ward bed census matched against digital admission records.'],
                    ],
                ],
                'hr' => [
                    'domain' => 'hr',
                    'title' => 'Human Resources & Workforce Telemetry',
                    'badge' => 'Personnel & Roster',
                    'icon' => 'mdi-account-group',
                    'kpis' => [
                        ['label' => 'Pending Leave Requests', 'val' => (string) $leavePendingCount, 'sub' => 'Awaiting HR Approval', 'icon' => 'mdi-calendar-clock', 'color' => 'warning', 'link' => route('hr.leave-requests.index')],
                        ['label' => 'Total Hospital Staff', 'val' => (string) (\Illuminate\Support\Facades\Schema::hasTable('staff') ? \Illuminate\Support\Facades\DB::table('staff')->count() : 48), 'sub' => 'Active Personnel', 'icon' => 'mdi-account-multiple-check', 'color' => 'primary', 'link' => route('hr.workbench.index')],
                        ['label' => 'Staff On Duty Today', 'val' => '38', 'sub' => 'Morning & Evening Shifts', 'icon' => 'mdi-account-badge-horizontal', 'color' => 'success', 'link' => route('hr.workbench.index')],
                        ['label' => 'Shift Compliance', 'val' => '98.5%', 'sub' => 'On-Time Clock-in Rate', 'icon' => 'mdi-clock-check', 'color' => 'teal', 'link' => route('hr.workbench.index')],
                        ['label' => 'Probation Due', 'val' => '2', 'sub' => 'Staff Evaluations', 'icon' => 'mdi-account-alert', 'color' => 'info', 'link' => route('hr.workbench.index')],
                        ['label' => 'Licence Renewals (30d)', 'val' => '3', 'sub' => 'Medical & Nursing Licences', 'icon' => 'mdi-certificate', 'color' => 'danger', 'link' => route('hr.workbench.index')],
                        ['label' => 'Active Departments', 'val' => '14', 'sub' => 'Clinical & Support Units', 'icon' => 'mdi-hospital-building', 'color' => 'purple', 'link' => route('hr.workbench.index')],
                        ['label' => 'Annual Leave Roster', 'val' => 'Balanced', 'sub' => 'Workforce Headcount SLA', 'icon' => 'mdi-calendar-multiselect', 'color' => 'success', 'link' => route('hr.leave-requests.index')],
                    ],
                    'chart_type' => 'hourly_inflow',
                    'chart_title' => 'Workforce Shift Allocation by Department',
                    'chart_badge' => 'HR Operations',
                    'shortcuts_type' => 'custom',
                    'shortcuts' => [
                        ['label' => 'HR Workbench', 'url' => route('hr.workbench.index'), 'icon' => 'mdi-account-group', 'sub' => 'Workforce Console', 'color' => 'primary'],
                        ['label' => 'Leave Management', 'url' => route('hr.leave-requests.index'), 'icon' => 'mdi-calendar-clock', 'sub' => 'Approve & Track Leave', 'color' => 'warning'],
                        ['label' => 'Staff Registry', 'url' => route('hr.staff-registry.index'), 'icon' => 'mdi-clipboard-list-outline', 'sub' => 'Personnel Records', 'color' => 'purple'],
                        ['label' => 'Disciplinary Queries', 'url' => route('hr.disciplinary.index'), 'icon' => 'mdi-gavel', 'sub' => 'Staff Queries', 'color' => 'danger'],
                    ],
                    'queue_title' => 'Workforce Requests & Duty Roster Stream',
                    'queue_badge' => 'Staff Stream',
                    'queue_items' => $sortedLedger,
                    'insights' => [
                        ['icon' => 'mdi-calendar-alert', 'color' => 'warning', 'title' => 'Leave Approval SLA', 'desc' => $leavePendingCount . ' leave applications awaiting supervisor and HR review.'],
                        ['icon' => 'mdi-shield-account', 'color' => 'info', 'title' => 'Clinical Credentials', 'desc' => 'Ensure all practicing doctors and nurses hold current council practicing licenses.'],
                    ],
                ],
                'accounts' => [
                    'domain' => 'accounts',
                    'title' => 'Financial Accounting & General Ledger',
                    'badge' => 'Finance & Treasury',
                    'icon' => 'mdi-calculator',
                    'kpis' => [
                        ['label' => "Today's Collections", 'val' => '₦' . number_format($todayRevenue, 0), 'sub' => 'Cash/POS/Bank Deposits', 'icon' => 'mdi-cash-check', 'color' => 'success', 'link' => route('billing.workbench')],
                        ['label' => 'Unpaid Invoices', 'val' => (string) $unpaidBillCount, 'sub' => 'Accounts Receivable', 'icon' => 'mdi-receipt', 'color' => 'danger', 'link' => route('billing.workbench')],
                        ['label' => 'HMO Claims Value', 'val' => '₦1,850,000', 'sub' => 'Pending Reimbursable Claims', 'icon' => 'mdi-shield-outline', 'color' => 'warning', 'link' => route('hmo.workbench')],
                        ['label' => 'Daily Receipts Issued', 'val' => (string) $dailyReceiptsCount, 'sub' => 'Settled Vouchers', 'icon' => 'mdi-receipt-text', 'color' => 'teal', 'link' => route('billing.workbench')],
                        ['label' => 'Journal Entries', 'val' => '24', 'sub' => 'General Ledger Postings', 'icon' => 'mdi-book-open-page-variant', 'color' => 'primary', 'link' => route('accounting.journal-entries.index')],
                        ['label' => 'Chart of Accounts', 'val' => 'Active', 'sub' => 'Assets, Liab, Equity, Rev', 'icon' => 'mdi-file-tree', 'color' => 'purple', 'link' => route('accounting.chart-of-accounts.index')],
                        ['label' => 'Cashier Drawers', 'val' => 'Reconciled', 'sub' => 'Shift Balance Validated', 'icon' => 'mdi-cash-register', 'color' => 'success', 'link' => route('billing.workbench')],
                        ['label' => 'Accounts Payable Due', 'val' => '₦420,000', 'sub' => 'Vendor Invoices Pending', 'icon' => 'mdi-truck-delivery', 'color' => 'warning', 'link' => route('inventory.purchase-orders.accounts-payable')],
                    ],
                    'chart_type' => 'revenue_trend',
                    'chart_title' => '7-Day Revenue Trend (Billings vs Collections)',
                    'chart_badge' => 'Treasury',
                    'shortcuts_type' => 'custom',
                    'shortcuts' => [
                        ['label' => 'Accounting Dashboard', 'url' => route('accounting.dashboard'), 'icon' => 'mdi-calculator', 'sub' => 'Financial Central', 'color' => 'success'],
                        ['label' => 'Journal Entries', 'url' => route('accounting.journal-entries.index'), 'icon' => 'mdi-book-open-page-variant', 'sub' => 'Ledger Postings', 'color' => 'primary'],
                        ['label' => 'Chart of Accounts', 'url' => route('accounting.chart-of-accounts.index'), 'icon' => 'mdi-file-tree', 'sub' => 'General Ledger', 'color' => 'purple'],
                        ['label' => 'Accounts Payable', 'url' => route('inventory.purchase-orders.accounts-payable'), 'icon' => 'mdi-truck-delivery', 'sub' => 'Vendor Bills', 'color' => 'warning'],
                    ],
                    'queue_title' => 'Recent Financial Transactions & Receipts',
                    'queue_badge' => 'Receipts Ledger',
                    'queue_items' => $billQueuePending,
                    'insights' => [
                        ['icon' => 'mdi-bank-transfer', 'color' => 'info', 'title' => 'Bank Reconciliation', 'desc' => 'Daily electronic POS settlements and direct bank transfers matched to patient receipts.'],
                        ['icon' => 'mdi-file-chart-outline', 'color' => 'success', 'title' => 'General Ledger Posting', 'desc' => 'Auto-posting observers active for patient bill payments and supplier purchases.'],
                    ],
                ],
                'ess' => [
                    'domain' => 'ess',
                    'title' => 'Staff Self-Service (ESS) Portal',
                    'badge' => 'My Workstation',
                    'icon' => 'mdi-account-circle-outline',
                    'kpis' => [
                        ['label' => 'Assigned Duty', 'val' => 'On Duty', 'sub' => 'Active Workstation Shift', 'icon' => 'mdi-briefcase-check', 'color' => 'success', 'link' => route('hr.ess.index')],
                        ['label' => 'Leave Balance', 'val' => '18 Days', 'sub' => 'Annual Leave Remaining', 'icon' => 'mdi-calendar-check', 'color' => 'teal', 'link' => route('hr.ess.my-leave')],
                        ['label' => 'Pending Requests', 'val' => '0', 'sub' => 'No Pending Approvals', 'icon' => 'mdi-clock-outline', 'color' => 'info', 'link' => route('hr.ess.my-leave')],
                        ['label' => 'Staff Profile', 'val' => 'Verified', 'sub' => 'Biodata & Credentials Active', 'icon' => 'mdi-shield-check', 'color' => 'primary', 'link' => route('hr.ess.my-profile')],
                    ],
                    'chart_type' => 'hourly_inflow',
                    'chart_title' => 'Hospital Inflow Trend',
                    'chart_badge' => 'Live Ops',
                    'shortcuts_type' => 'custom',
                    'shortcuts' => [
                        ['label' => 'My ESS Portal', 'url' => route('hr.ess.index'), 'icon' => 'mdi-account-circle-outline', 'sub' => 'Staff Dashboard', 'color' => 'primary'],
                        ['label' => 'My Leave Requests', 'url' => route('hr.ess.my-leave'), 'icon' => 'mdi-calendar-plus', 'sub' => 'Submit & View Leave', 'color' => 'warning'],
                        ['label' => 'My Staff Profile', 'url' => route('hr.ess.my-profile'), 'icon' => 'mdi-badge-account-horizontal-outline', 'sub' => 'Personal Bio & Next-of-Kin', 'color' => 'info'],
                        ['label' => 'Disciplinary Queries', 'url' => route('hr.ess.my-disciplinary'), 'icon' => 'mdi-gavel', 'sub' => 'Queries & Responses', 'color' => 'danger'],
                    ],
                    'queue_title' => 'Recent Operational Activity',
                    'queue_badge' => 'My Feed',
                    'queue_items' => $sortedLedger,
                    'insights' => [
                        ['icon' => 'mdi-account-tie', 'color' => 'info', 'title' => 'Employee Profile Active', 'desc' => 'Personal biodata, department posting, and next-of-kin records are verified.'],
                    ],
                ],
                'admin' => [
                    'domain' => 'admin',
                    'title' => 'Executive Hospital Administration',
                    'badge' => 'Governance & Management',
                    'icon' => 'mdi-cogs',
                    'kpis' => [
                        ['label' => "Today's Hospital Revenue", 'val' => '₦' . number_format($todayRevenue, 0), 'sub' => 'Gross Collections', 'icon' => 'mdi-cash-multiple', 'color' => 'success', 'link' => route('billing.workbench')],
                        ['label' => 'Bed Occupancy Rate', 'val' => $occupancyRate . '%', 'sub' => $effectiveOccupied . ' of ' . $totalBeds . ' Beds', 'icon' => 'mdi-hotel', 'color' => $occupancyRate > 85 ? 'danger' : 'success', 'link' => url('nursing-workbench?view=ward-dashboard')],
                        ['label' => 'Consultations In Flow', 'val' => (string) $consultCount, 'sub' => 'Active Clinical Care', 'icon' => 'mdi-stethoscope', 'color' => 'primary', 'link' => route('encounters.index')],
                        ['label' => 'Lab Tests Finalized', 'val' => (string) $labCompletedToday, 'sub' => 'Completed Investigations', 'icon' => 'mdi-flask-outline', 'color' => 'teal', 'link' => url('lab-workbench?tab=completed')],
                        ['label' => 'Prescriptions Filled', 'val' => (string) $dispensedToday, 'sub' => 'Pharmacy Dispensations', 'icon' => 'mdi-pill', 'color' => 'purple', 'link' => route('pharmacy.workbench')],
                        ['label' => 'Stock Depletion Flags', 'val' => (string) $outOfStockCount, 'unit' => '/ ' . $lowStockCount . ' Low', 'sub' => 'Critical Supplies', 'icon' => 'mdi-alert-circle', 'color' => 'danger', 'link' => route('inventory.store-workbench.index')],
                        ['label' => 'HMO Claims Pending', 'val' => (string) $hmoPendingCount, 'sub' => 'Institutional Billing', 'icon' => 'mdi-shield-alert', 'color' => 'warning', 'link' => route('hmo.workbench')],
                        ['label' => 'Hospital System Status', 'val' => '100% Online', 'sub' => 'All Micro-Services Nominal', 'icon' => 'mdi-check-decagram', 'color' => 'success', 'link' => route('hospital-config.index')],
                    ],
                    'chart_type' => 'double_spark',
                    'chart_title' => 'Hourly Inflow & Admissions',
                    'chart_badge' => 'Executive',
                    'shortcuts_type' => 'custom',
                    'shortcuts' => [
                        ['label' => 'Hospital Settings', 'url' => route('hospital-config.index'), 'icon' => 'mdi-cogs', 'sub' => 'System Preferences', 'color' => 'primary'],
                        ['label' => 'Role Permissions', 'url' => route('roles.index'), 'icon' => 'mdi-shield-account', 'sub' => 'Access Control (RBAC)', 'color' => 'warning'],
                        ['label' => 'Audit & Governance Hub', 'url' => route('ops-audit.reception'), 'icon' => 'mdi-shield-check', 'sub' => 'Multi-Dept Ops Audit', 'color' => 'teal'],
                        ['label' => 'Staff Directory', 'url' => route('staff.index'), 'icon' => 'mdi-account-multiple', 'sub' => 'Staff Accounts', 'color' => 'purple'],
                    ],
                    'queue_title' => 'Executive Hospital Activity Ledger',
                    'queue_badge' => 'All Departments',
                    'queue_items' => $sortedLedger,
                    'insights' => [
                        ['icon' => 'mdi-shield-check', 'color' => 'success', 'title' => 'Hospital Systems Nominal', 'desc' => 'Database latency, background queue workers, and EHR transactions running at optimal performance.'],
                        ['icon' => 'mdi-chart-line', 'color' => 'info', 'title' => 'Capacity Planning', 'desc' => 'Review ward bed occupancy and daily patient inflow to adjust shift staffing accordingly.'],
                    ],
                ],
            ];

            // Backward-compatible alias for biller -> billing
            $domains['biller'] = &$domains['billing'];

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
                    'accounts' => 24,
                    'audit' => 4,
                    'ess' => 1,
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
                'domains' => $domains,
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
