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
            'activity' => $svc->getRecentActivity(),
            'stockHealth' => $svc->getStockHealth(),
            'dispensingTrend' => $svc->getDispensingTrend(),
            'insights' => $svc->getInsights(),
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
            'activity' => $svc->getRecentActivity(),
            'bedOccupancy' => $svc->getBedOccupancy(),
            'vitalsTrend' => $svc->getVitalsTrend(),
            'insights' => $svc->getInsights(),
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
            'activity' => $svc->getRecentActivity(),
            'categoryBreakdown' => $svc->getServiceCategoryBreakdown(),
            'requestTrend' => $svc->getRequestTrend(),
            'insights' => $svc->getInsights(),
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
     *  HMO
     * ============================ */
    public function hmoData()
    {
        $svc = new HmoDashboardService();
        return response()->json([
            'queues' => $svc->getQueueCounts(),
            'activity' => $svc->getRecentActivity(),
            'providerDistribution' => $svc->getProviderDistribution(),
            'claimsTrend' => $svc->getClaimsTrend(),
            'insights' => $svc->getInsights(),
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
