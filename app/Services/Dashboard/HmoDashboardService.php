<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class HmoDashboardService
{
    /**
     * Get HMO pipeline queue counts
     */
    public function getQueueCounts(): array
    {
        return Cache::remember('dashboard.hmo.queues', 60, function () {
            $pending = DB::table('product_or_service_requests as posr')
                ->join('patients as p', 'posr.user_id', '=', 'p.id')
                ->whereNotNull('p.hmo_id')
                ->whereNull('posr.validation_status')
                ->count();

            $approved = DB::table('product_or_service_requests as posr')
                ->join('patients as p', 'posr.user_id', '=', 'p.id')
                ->whereNotNull('p.hmo_id')
                ->where('posr.validation_status', 'approved')
                ->whereMonth('posr.validated_at', now()->month)
                ->count();

            $rejected = DB::table('product_or_service_requests as posr')
                ->join('patients as p', 'posr.user_id', '=', 'p.id')
                ->whereNotNull('p.hmo_id')
                ->where('posr.validation_status', 'rejected')
                ->whereMonth('posr.validated_at', now()->month)
                ->count();

            return [
                ['name' => 'Pending Claims', 'filter' => 'pending', 'icon' => 'mdi-clipboard-alert', 'color' => 'warning',
                 'count' => $pending],
                ['name' => 'Approved (Month)', 'filter' => 'approved', 'icon' => 'mdi-check-circle', 'color' => 'success',
                 'count' => $approved],
                ['name' => 'Rejected (Month)', 'filter' => 'rejected', 'icon' => 'mdi-close-circle', 'color' => 'danger',
                 'count' => $rejected],
            ];
        });
    }

    /**
     * HMO provider distribution for donut chart (real data)
     */
    public function getProviderDistribution(): array
    {
        $colors = ['#3b82f6', '#8b5cf6', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#6366f1'];

        return DB::table('patients as p')
            ->join('hmos as h', 'p.hmo_id', '=', 'h.id')
            ->whereNotNull('p.hmo_id')
            ->selectRaw('h.name as label, COUNT(*) as value')
            ->groupBy('h.name')
            ->orderByDesc('value')
            ->limit(8)
            ->get()
            ->map(function ($row, $index) use ($colors) {
                $row->color = $colors[$index % count($colors)];
                return (array) $row;
            })
            ->toArray();
    }

    /**
     * Claims trend (real data for this month)
     */
    public function getClaimsTrend(string $start = null, string $end = null): array
    {
        $start = $start ?: Carbon::now()->startOfMonth()->toDateString();
        $end = $end ?: Carbon::today()->toDateString();

        return DB::table('product_or_service_requests as posr')
            ->join('patients as p', 'posr.user_id', '=', 'p.id')
            ->whereNotNull('p.hmo_id')
            ->whereBetween(DB::raw('DATE(posr.created_at)'), [$start, $end])
            ->selectRaw('DATE(posr.created_at) as date, SUM(posr.claims_amount) as total')
            ->groupByRaw('DATE(posr.created_at)')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Recent HMO activities
     */
    public function getRecentActivity(): array
    {
        return DB::table('product_or_service_requests as posr')
            ->join('patients as p', 'posr.user_id', '=', 'p.id')
            ->join('users as u', 'p.user_id', '=', 'u.id')
            ->join('hmos as h', 'p.hmo_id', '=', 'h.id')
            ->whereNotNull('p.hmo_id')
            ->select(
                'posr.id',
                'p.id as patient_id',
                DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                'h.name as hmo_name',
                'posr.claims_amount',
                'posr.validation_status',
                'posr.created_at'
            )
            ->orderByDesc('posr.created_at')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $statusMap = [null => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
                $colorMap = [null => 'warning', 'approved' => 'success', 'rejected' => 'danger'];
                $row->status_label = $statusMap[$row->validation_status] ?? 'Pending';
                $row->status_color = $colorMap[$row->validation_status] ?? 'warning';
                $row->amount_formatted = '₦' . number_format($row->claims_amount ?? 0, 2);
                $row->time = Carbon::parse($row->created_at)->format('M d, h:i A');
                return $row;
            })
            ->toArray();
    }

    /**
     * Generate insights for HMO
     */
    public function getInsights(): array
    {
        $insights = [];

        // Approval rate this month
        $totalClaims = DB::table('product_or_service_requests as posr')
            ->join('patients as p', 'posr.user_id', '=', 'p.id')
            ->whereNotNull('p.hmo_id')
            ->whereNotNull('posr.validation_status')
            ->whereMonth('posr.validated_at', now()->month)
            ->count();

        $approvedClaims = DB::table('product_or_service_requests as posr')
            ->join('patients as p', 'posr.user_id', '=', 'p.id')
            ->whereNotNull('p.hmo_id')
            ->where('posr.validation_status', 'approved')
            ->whereMonth('posr.validated_at', now()->month)
            ->count();

        if ($totalClaims > 0) {
            $rate = round(($approvedClaims / $totalClaims) * 100);
            $insights[] = [
                'type' => 'stat', 'severity' => $rate >= 70 ? 'success' : 'warning',
                'icon' => 'mdi-chart-donut',
                'title' => 'Approval Rate',
                'message' => "{$rate}% approval rate this month ({$approvedClaims}/{$totalClaims})",
            ];
        }

        // Pending settlement
        $pendingSettlement = DB::table('product_or_service_requests as posr')
            ->join('patients as p', 'posr.user_id', '=', 'p.id')
            ->whereNotNull('p.hmo_id')
            ->where('posr.validation_status', 'approved')
            ->whereNull('posr.payment_id')
            ->sum('posr.claims_amount');

        if ($pendingSettlement > 0) {
            $insights[] = [
                'type' => 'alert', 'severity' => 'warning', 'icon' => 'mdi-cash-clock',
                'title' => 'Pending Settlement',
                'message' => '₦' . number_format($pendingSettlement, 2) . ' approved but unpaid',
            ];
        }

        // New enrollees this month
        $newEnrollees = DB::table('patients')
            ->whereNotNull('hmo_id')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        if ($newEnrollees > 0) {
            $insights[] = [
                'type' => 'info', 'severity' => 'info', 'icon' => 'mdi-account-plus',
                'title' => 'New Enrollees',
                'message' => "{$newEnrollees} new HMO enrollee(s) this month",
            ];
        }

        return $insights;
    }
}
