<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\ReceptionDashboardService;
use App\Services\Dashboard\BillingDashboardService;
use App\Services\Dashboard\PharmacyDashboardService;
use App\Services\Dashboard\NursingDashboardService;
use App\Services\Dashboard\LabDashboardService;
use App\Services\Dashboard\DoctorDashboardService;
use App\Services\Dashboard\HmoDashboardService;
use App\Services\Dashboard\AccountsDashboardService;
use App\Services\Dashboard\StoreDashboardService;
use App\Services\Dashboard\MaternityDashboardService;
use App\Services\Dashboard\MorgueDashboardService;
use App\Services\Dashboard\EssDashboardService;
use App\Services\Dashboard\ChildHealthDashboardService;
use App\Services\Dashboard\HrDashboardService;
use App\Services\Dashboard\TheatreDashboardService;
use App\Services\Dashboard\ImagingDashboardService;
use Illuminate\Http\Request;

class DashboardDataController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /* ============================
     *  RECEPTION
     * ============================ */
    public function receptionData()
    {
        $svc = new ReceptionDashboardService();
        return response()->json([
            'queues' => $svc->getQueueCounts(),
            'activity' => $svc->getRecentActivity(),
            'hourlyFlow' => $svc->getHourlyPatientFlow(),
            'patientBreakdown' => $svc->getPatientTypeBreakdown(),
            'insights' => $svc->getInsights(),
        ]);
    }

    /* ============================
     *  BILLING
     * ============================ */
    public function billingData()
    {
        $svc = new BillingDashboardService();
        return response()->json([
            'queues' => $svc->getQueueCounts(),
            'activity' => $svc->getRecentActivity(),
            'paymentMethods' => $svc->getPaymentMethodBreakdown(),
            'revenueTrend' => $svc->getRevenueTrend(),
            'insights' => $svc->getInsights(),
        ]);
    }

    /* ============================
     *  PHARMACY
     * ============================ */
    public function pharmacyData()
    {
        $svc = new PharmacyDashboardService();
        return response()->json([
            'queues' => $svc->getQueueCounts(),
            'stats' => $svc->getStats(),
            'activity' => $svc->getRecentActivity(),
            'stockHealth' => $svc->getStockHealth(),
            'dispensingTrend' => $svc->getDispensingTrend(),
            'insights' => $svc->getInsights(),
        ]);
    }

    /* ============================
     *  STORE / INVENTORY
     * ============================ */
    public function storeData()
    {
        $svc = new StoreDashboardService();
        return response()->json([
            'queues' => $svc->getQueueCounts(),
            'stats' => $svc->getStats(),
            'activity' => $svc->getRecentActivity(),
            'stockHealth' => $svc->getStockHealth(),
            'requisitionTrend' => $svc->getRequisitionTrend(),
            'insights' => $svc->getInsights(),
        ]);
    }

    /* ============================
     *  MORGUE
     * ============================ */
    public function morgueData()
    {
        $svc = new MorgueDashboardService();
        return response()->json([
            'queues' => $svc->getQueueCounts(),
            'stats' => $svc->getStats(),
            'insights' => $svc->getInsights(),
            'admissionTrend' => $svc->getAdmissionTrend(),
            'statusBreakdown' => $svc->getStatusBreakdown(),
            'activity' => $svc->getRecentActivity(),
        ]);
    }

    /* ============================
     *  NURSING
     * ============================ */
    public function nursingData()
    {
        $svc = new NursingDashboardService();
        return response()->json([
            'queues' => $svc->getQueueCounts(),
            'stats' => $svc->getStats(),
            'activity' => $svc->getRecentActivity(),
            'bedOccupancy' => $svc->getBedOccupancy(),
            'vitalsTrend' => $svc->getVitalsTrend(),
            'insights' => $svc->getInsights(),
        ]);
    }

    /* ============================
     *  ESS / MY PORTAL
     * ============================ */
    public function essData()
    {
        $svc = new EssDashboardService();
        $userId = auth()->id();
        return response()->json([
            'queues' => $svc->getQueueCounts($userId),
            'stats' => $svc->getStats($userId),
            'insights' => $svc->getInsights($userId),
            'leaveBreakdown' => $svc->getLeaveBreakdown($userId),
            'activity' => $svc->getRecentActivity($userId),
        ]);
    }

    /* ============================
     *  LAB / IMAGING
     * ============================ */
    public function labData()
    {
        $svc = new LabDashboardService();
        return response()->json([
            'queues' => $svc->getQueueCounts(),
            'stats' => $svc->getStats(),
            'activity' => $svc->getRecentActivity(),
            'categoryBreakdown' => $svc->getServiceCategoryBreakdown(),
            'requestTrend' => $svc->getRequestTrend(),
            'insights' => $svc->getInsights(),
        ]);
    }

    public function imagingData()
    {
        $svc = new ImagingDashboardService();
        return response()->json([
            'queues' => $svc->getQueueCounts(),
            'stats' => $svc->getStats(),
            'insights' => $svc->getInsights(),
            'categoryBreakdown' => $svc->getCategoryBreakdown(),
            'requestTrend' => $svc->getRequestTrend(),
            'activity' => $svc->getRecentActivity(),
        ]);
    }

    /* ============================
     *  DOCTOR
     * ============================ */
    public function doctorData()
    {
        $svc = new DoctorDashboardService();
        $userId = auth()->id();
        return response()->json([
            'queues' => $svc->getQueueCounts($userId),
            'activity' => $svc->getRecentActivity($userId),
            'insights' => $svc->getInsights($userId),
        ]);
    }

    /* ============================
     *  CHILD HEALTH
     * ============================ */
    public function childHealthData()
    {
        $svc = new ChildHealthDashboardService();
        return response()->json([
            'queues' => $svc->getQueueCounts(),
            'stats' => $svc->getStats(),
            'insights' => $svc->getInsights(),
            'statusBreakdown' => $svc->getImmunizationBreakdown(),
            'activity' => $svc->getRecentActivity(),
        ]);
    }

    /* ============================
     *  HMO
     * ============================ */
    public function hmoData()
    {
        $svc = new HmoDashboardService();
        return response()->json([
            'queues' => $svc->getQueueCounts(),
            'stats' => $svc->getStats(),
            'activity' => $svc->getRecentActivity(),
            'providerDistribution' => $svc->getProviderDistribution(),
            'claimsTrend' => $svc->getClaimsTrend(),
            'insights' => $svc->getInsights(),
        ]);
    }

    /* ============================
     *  MATERNITY
     * ============================ */
    public function maternityData()
    {
        $svc = new MaternityDashboardService();
        return response()->json([
            'queues' => $svc->getQueueCounts(),
            'stats' => $svc->getStats(),
            'insights' => $svc->getInsights(),
            'enrollmentTrend' => $svc->getEnrollmentTrend(),
            'riskBreakdown' => $svc->getRiskBreakdown(),
            'activity' => $svc->getRecentActivity(),
        ]);
    }

    /* ============================
     *  HR OPERATIONS
     * ============================ */
    public function hrData()
    {
        $svc = new HrDashboardService();
        return response()->json([
            'queues' => $svc->getQueueCounts(),
            'stats' => $svc->getStats(),
            'insights' => $svc->getInsights(),
            'deptBreakdown' => $svc->getDepartmentBreakdown(),
            'activity' => $svc->getRecentActivity(),
        ]);
    }

    /* ============================
     *  THEATRE / PROCEDURES
     * ============================ */
    public function theatreData()
    {
        $svc = new TheatreDashboardService();
        return response()->json([
            'queues' => $svc->getQueueCounts(),
            'stats' => $svc->getStats(),
            'insights' => $svc->getInsights(),
            'categoryBreakdown' => $svc->getCategoryBreakdown(),
            'activity' => $svc->getRecentActivity(),
        ]);
    }

    /* ============================
     *  ACCOUNTS / AUDIT
     * ============================ */
    public function accountsData()
    {
        $svc = new AccountsDashboardService();
        return response()->json([
            'summary' => $svc->getFinancialSummary(),
            'revenueTrend' => $svc->getRevenueTrend(),
            'paymentMethods' => $svc->getPaymentMethodBreakdown(),
            'departmentRevenue' => $svc->getDepartmentRevenue(),
            'kpis' => $svc->getFinancialKpis(),
            'insights' => $svc->getInsights(),
        ]);
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
}
