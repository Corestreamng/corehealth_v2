<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class LabDashboardService
{
    /**
     * Get lab stats for dashboard cards
     */
    public function getStats(): array
    {
        $today = Carbon::today();
        $labCategoryId = appsettings('investigation_category_id', 2);

        return [
            'queue' => DB::table('lab_service_requests')->whereIn('status', [0, 2, 3])->whereDate('created_at', $today)->count(),
            'completed' => DB::table('lab_service_requests')->where('status', 4)->whereDate('updated_at', $today)->count(),
            'tests_this_month' => DB::table('lab_service_requests')
                ->where('status', 4)
                ->whereMonth('updated_at', now()->month)
                ->whereYear('updated_at', now()->year)
                ->count(),
            'services' => DB::table('services')->where('category_id', $labCategoryId)->count(),
        ];
    }

    /**
     * Get lab queue counts mirroring workbench
     */
    public function getQueueCounts(): array
    {
        $today = Carbon::today();
        $labCategoryId = appsettings('investigation_category_id', 2);

        return Cache::remember('dashboard.lab.queues', 30, function () use ($today, $labCategoryId) {
            // Lab queues
            $labBilling = DB::table('lab_service_requests as lsr')
                ->join('services as s', 'lsr.service_id', '=', 's.id')
                ->where('s.category_id', $labCategoryId)
                ->where('lsr.status', 0) // awaiting billing
                ->whereDate('lsr.created_at', $today)
                ->count();

            $labSample = DB::table('lab_service_requests as lsr')
                ->join('services as s', 'lsr.service_id', '=', 's.id')
                ->where('s.category_id', $labCategoryId)
                ->where('lsr.status', 2) // sample collection
                ->whereDate('lsr.created_at', $today)
                ->count();

            $labResults = DB::table('lab_service_requests as lsr')
                ->join('services as s', 'lsr.service_id', '=', 's.id')
                ->where('s.category_id', $labCategoryId)
                ->where('lsr.status', 3) // result entry
                ->whereDate('lsr.created_at', $today)
                ->count();

            $labCompleted = DB::table('lab_service_requests as lsr')
                ->join('services as s', 'lsr.service_id', '=', 's.id')
                ->where('s.category_id', $labCategoryId)
                ->where('lsr.status', 4) // completed
                ->whereDate('lsr.updated_at', $today)
                ->count();

            return [
                ['name' => 'Awaiting Billing', 'filter' => 'billing', 'icon' => 'mdi-cash-clock', 'color' => 'warning',
                 'count' => $labBilling],
                ['name' => 'Sample Collection', 'filter' => 'sample', 'icon' => 'mdi-test-tube', 'color' => 'info',
                 'count' => $labSample],
                ['name' => 'Result Entry', 'filter' => 'results', 'icon' => 'mdi-clipboard-edit', 'color' => 'danger',
                 'count' => $labResults],
                ['name' => 'Completed Today', 'filter' => 'completed', 'icon' => 'mdi-check-circle', 'color' => 'success',
                 'count' => $labCompleted],
            ];
        });
    }

    /**
     * Service category breakdown for donut chart (real data)
     */
    public function getServiceCategoryBreakdown(): array
    {
        $labCategoryId = appsettings('investigation_category_id', 2);

        return DB::table('lab_service_requests as lsr')
            ->join('services as s', 'lsr.service_id', '=', 's.id')
            ->where('s.category_id', $labCategoryId)
            ->selectRaw('s.service_name as label, count(*) as value')
            ->groupBy('s.service_name')
            ->orderByDesc('value')
            ->limit(5)
            ->get()
            ->map(function($row, $index) {
                $colors = ['#0891b2', '#0e7490', '#155e75', '#164e63', '#06b6d4'];
                $row->color = $colors[$index % count($colors)];
                return (array)$row;
            })
            ->toArray();
    }

    /**
     * Lab request volume trend
     */
    public function getRequestTrend(): array
    {
        $start = Carbon::now()->startOfMonth()->toDateString();
        $end = Carbon::today()->toDateString();
        $labCategoryId = appsettings('investigation_category_id', 2);

        return DB::table('lab_service_requests as lsr')
            ->join('services as s', 'lsr.service_id', '=', 's.id')
            ->where('s.category_id', $labCategoryId)
            ->whereBetween(DB::raw('DATE(lsr.created_at)'), [$start, $end])
            ->selectRaw('DATE(lsr.created_at) as date, count(*) as total')
            ->groupByRaw('DATE(lsr.created_at)')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Recent personal activity
     */
    public function getRecentActivity(): array
    {
        $labCategoryId = appsettings('investigation_category_id', 2);

        return DB::table('lab_service_requests as lsr')
            ->join('services as s', 'lsr.service_id', '=', 's.id')
            ->join('patients as p', 'lsr.patient_id', '=', 'p.id')
            ->join('users as u', 'p.user_id', '=', 'u.id')
            ->where('s.category_id', $labCategoryId)
            ->select(
                'lsr.id',
                DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                's.service_name as test_name',
                'lsr.status',
                'lsr.created_at'
            )
            ->orderByDesc('lsr.created_at')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $statusMap = [0 => 'Billing', 1 => 'Billed', 2 => 'Sampling', 3 => 'Results', 4 => 'Completed'];
                $colorMap = [0 => 'warning', 1 => 'info', 2 => 'primary', 3 => 'danger', 4 => 'success'];
                $row->status_label = $statusMap[$row->status] ?? 'Unknown';
                $row->status_color = $colorMap[$row->status] ?? 'secondary';
                $row->time = Carbon::parse($row->created_at)->format('h:i A');
                return $row;
            })
            ->toArray();
    }

    /**
     * Insights for lab
     */
    public function getInsights(): array
    {
        $today = Carbon::today();
        $labCategoryId = appsettings('investigation_category_id', 2);
        $insights = [];

        $pending = DB::table('lab_service_requests as lsr')
            ->join('services as s', 'lsr.service_id', '=', 's.id')
            ->where('s.category_id', $labCategoryId)
            ->where('lsr.status', 3)
            ->count();

        if ($pending > 0) {
            $insights[] = [
                'type' => 'alert', 'severity' => 'warning', 'icon' => 'mdi-clipboard-alert',
                'title' => 'Pending Results',
                'message' => "{$pending} lab test(s) awaiting result entry",
            ];
        }

        $completedToday = DB::table('lab_service_requests as lsr')
            ->join('services as s', 'lsr.service_id', '=', 's.id')
            ->where('s.category_id', $labCategoryId)
            ->where('lsr.status', 4)
            ->whereDate('lsr.updated_at', $today)
            ->count();

        $insights[] = [
            'type' => 'stat', 'severity' => 'success', 'icon' => 'mdi-check-circle',
            'title' => 'Tests Completed',
            'message' => "{$completedToday} lab test(s) finalized today",
        ];

        return $insights;
    }
}
