<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class LabDashboardService
{
    /**
     * Get lab + imaging queue counts mirroring workbenches
     */
    public function getQueueCounts(): array
    {
        $today = Carbon::today();
        $labCategoryId = appsettings('investigation_category_id', 2);
        $imagingCategoryId = appsettings('imaging_category_id', 6);

        return Cache::remember('dashboard.lab.queues', 30, function () use ($today, $labCategoryId, $imagingCategoryId) {
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

            // Imaging queues
            $imgBilling = DB::table('imaging_service_requests')
                ->where('status', 0)
                ->whereDate('created_at', $today)
                ->count();

            $imgResults = DB::table('imaging_service_requests')
                ->whereIn('status', [2, 3])
                ->whereDate('created_at', $today)
                ->count();

            return [
                ['name' => 'Lab: Awaiting Billing', 'filter' => 'lab-billing', 'icon' => 'mdi-cash-clock', 'color' => 'warning',
                 'count' => $labBilling],
                ['name' => 'Lab: Sample Collection', 'filter' => 'lab-sample', 'icon' => 'mdi-test-tube', 'color' => 'info',
                 'count' => $labSample],
                ['name' => 'Lab: Result Entry', 'filter' => 'lab-results', 'icon' => 'mdi-clipboard-edit', 'color' => 'danger',
                 'count' => $labResults],
                ['name' => 'Lab: Completed', 'filter' => 'lab-completed', 'icon' => 'mdi-check-circle', 'color' => 'success',
                 'count' => $labCompleted],
                ['name' => 'Imaging: Billing', 'filter' => 'img-billing', 'icon' => 'mdi-radiobox-marked', 'color' => 'warning',
                 'count' => $imgBilling],
                ['name' => 'Imaging: Results', 'filter' => 'img-results', 'icon' => 'mdi-image-search', 'color' => 'info',
                 'count' => $imgResults],
            ];
        });
    }

    /**
     * Service category breakdown for donut chart (real data)
     */
    public function getServiceCategoryBreakdown(): array
    {
        $today = Carbon::today();

        $labCount = DB::table('lab_service_requests')
            ->whereDate('created_at', $today)
            ->count();

        $imagingCount = DB::table('imaging_service_requests')
            ->whereDate('created_at', $today)
            ->count();

        return [
            ['label' => 'Lab Tests', 'value' => $labCount, 'color' => '#0891b2'],
            ['label' => 'Imaging', 'value' => $imagingCount, 'color' => '#6366f1'],
        ];
    }

    /**
     * Lab request volume trend (real data for this month)
     */
    public function getRequestTrend(string $start = null, string $end = null): array
    {
        $start = $start ?: Carbon::now()->startOfMonth()->toDateString();
        $end = $end ?: Carbon::today()->toDateString();

        return DB::table('lab_service_requests')
            ->whereBetween(DB::raw('DATE(created_at)'), [$start, $end])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Recent completed tests
     */
    public function getRecentActivity(): array
    {
        $today = Carbon::today();

        return DB::table('lab_service_requests as lsr')
            ->join('services as s', 'lsr.service_id', '=', 's.id')
            ->join('patients as p', 'lsr.patient_id', '=', 'p.id')
            ->join('users as u', 'p.user_id', '=', 'u.id')
            ->whereDate('lsr.created_at', $today)
            ->select(
                'lsr.id',
                'lsr.patient_id',
                DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                's.service_name as test_name',
                'lsr.status',
                'lsr.created_at'
            )
            ->orderByDesc('lsr.created_at')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $statusMap = [0 => 'Billing', 1 => 'Billed', 2 => 'Sample', 3 => 'Result Entry', 4 => 'Completed'];
                $colorMap = [0 => 'warning', 1 => 'info', 2 => 'primary', 3 => 'danger', 4 => 'success'];
                $row->status_label = $statusMap[$row->status] ?? 'Unknown';
                $row->status_color = $colorMap[$row->status] ?? 'secondary';
                $row->time = Carbon::parse($row->created_at)->format('h:i A');
                return $row;
            })
            ->toArray();
    }

    /**
     * Generate insights for lab/imaging
     */
    public function getInsights(): array
    {
        $today = Carbon::today();
        $insights = [];

        // Pending results
        $pendingResults = DB::table('lab_service_requests')
            ->where('status', 3)
            ->count();

        if ($pendingResults > 0) {
            $insights[] = [
                'type' => 'alert', 'severity' => 'warning', 'icon' => 'mdi-clipboard-alert',
                'title' => 'Pending Results',
                'message' => "{$pendingResults} test(s) awaiting result entry",
            ];
        }

        // Completed today
        $completedToday = DB::table('lab_service_requests')
            ->whereDate('updated_at', $today)
            ->where('status', 4)
            ->count();

        $completedImaging = DB::table('imaging_service_requests')
            ->whereDate('updated_at', $today)
            ->where('status', 4)
            ->count();

        $insights[] = [
            'type' => 'stat', 'severity' => 'success', 'icon' => 'mdi-check-circle',
            'title' => 'Completed Today',
            'message' => ($completedToday + $completedImaging) . " test(s) completed (Lab: {$completedToday}, Imaging: {$completedImaging})",
        ];

        // Top requested test today
        $topTest = DB::table('lab_service_requests as lsr')
            ->join('services as s', 'lsr.service_id', '=', 's.id')
            ->whereDate('lsr.created_at', $today)
            ->selectRaw('s.service_name as name, COUNT(*) as cnt')
            ->groupBy('s.service_name')
            ->orderByDesc('cnt')
            ->first();

        if ($topTest) {
            $insights[] = [
                'type' => 'info', 'severity' => 'info', 'icon' => 'mdi-star',
                'title' => 'Most Requested',
                'message' => "{$topTest->name} ({$topTest->cnt} requests)",
            ];
        }

        // Total requests today
        $totalToday = DB::table('lab_service_requests')
            ->whereDate('created_at', $today)
            ->count();

        $insights[] = [
            'type' => 'stat', 'severity' => 'info', 'icon' => 'mdi-flask',
            'title' => "Today's Volume",
            'message' => "{$totalToday} lab request(s) today",
        ];

        return $insights;
    }
}
