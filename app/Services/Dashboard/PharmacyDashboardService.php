<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class PharmacyDashboardService
{
    /**
     * Get pharmacy stats for dashboard cards
     */
    public function getStats(): array
    {
        $today = Carbon::today();
        return [
            'queue' => DB::table('product_requests')
                ->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                ->where('status', 1) // Requested
                ->count(),
            'dispensed' => DB::table('product_requests')
                ->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                ->where('status', 3) // Dispensed
                ->count(),
            'products' => DB::table('products')->where('status', 1)->count(),
            'low_stock' => DB::table('store_stocks')
                ->where('current_quantity', '<=', DB::raw('reorder_level'))
                ->count(),
        ];
    }

    /**
     * Get pharmacy queue counts mirroring pharmacy workbench
     */
    public function getQueueCounts(): array
    {
        $today = Carbon::today();

        return Cache::remember('dashboard.pharmacy.queues', 30, function () use ($today) {
            $allPending = DB::table('product_requests')
                ->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                ->whereIn('status', [1, 2])
                ->count();

            $unbilled = DB::table('product_requests')
                ->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                ->where('status', 1)
                ->count();

            // Ready to dispense: Billed (status 2) AND (Paid OR HMO Validated)
            $ready = DB::table('product_requests as pr')
                ->leftJoin('product_or_service_requests as posr', 'pr.product_request_id', '=', 'posr.id')
                ->whereBetween('pr.created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                ->where('pr.status', 2)
                ->where(function($q) {
                    $q->whereNotNull('posr.payment_id')
                      ->orWhereIn('posr.validation_status', ['validated', 'approved', 'awaiting_code']);
                })
                ->count();

            $hmo = DB::table('product_requests as pr')
                ->join('patients as p', 'pr.patient_id', '=', 'p.id')
                ->whereBetween('pr.created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
                ->whereNotNull('p.hmo_id')
                ->whereIn('pr.status', [1, 2])
                ->count();

            return [
                ['name' => 'All Pending', 'filter' => 'all', 'icon' => 'mdi-cart', 'color' => 'warning',
                 'count' => $allPending],
                ['name' => 'Unbilled', 'filter' => 'unbilled', 'icon' => 'mdi-cash-remove', 'color' => 'danger',
                 'count' => $unbilled],
                ['name' => 'Ready to Dispense', 'filter' => 'ready', 'icon' => 'mdi-cash-check', 'color' => 'success',
                 'count' => $ready],
                ['name' => 'HMO Patients', 'filter' => 'hmo', 'icon' => 'mdi-shield-check', 'color' => 'info',
                 'count' => $hmo],
            ];
        });
    }

    /**
     * Stock health breakdown for donut chart (real data)
     */
    public function getStockHealth(): array
    {
        $inStock = DB::table('products as p')
            ->join('stocks as s', 'p.id', '=', 's.product_id')
            ->where('p.reorder_alert', '>', 0)
            ->whereRaw('s.current_quantity > CAST(p.reorder_alert AS SIGNED)')
            ->count();

        $lowStock = DB::table('products as p')
            ->join('stocks as s', 'p.id', '=', 's.product_id')
            ->where('p.reorder_alert', '>', 0)
            ->whereRaw('s.current_quantity <= CAST(p.reorder_alert AS SIGNED)')
            ->whereRaw('s.current_quantity > 0')
            ->count();

        $outOfStock = DB::table('products as p')
            ->join('stocks as s', 'p.id', '=', 's.product_id')
            ->where('s.current_quantity', '<=', 0)
            ->count();

        return [
            ['label' => 'In Stock', 'value' => $inStock, 'color' => '#10b981'],
            ['label' => 'Low Stock', 'value' => $lowStock, 'color' => '#f59e0b'],
            ['label' => 'Out of Stock', 'value' => $outOfStock, 'color' => '#ef4444'],
        ];
    }

    /**
     * Dispensing trend (real data for this month)
     */
    public function getDispensingTrend(string $start = null, string $end = null): array
    {
        $start = $start ?: Carbon::now()->startOfMonth()->toDateString();
        $end = $end ?: Carbon::today()->toDateString();

        return DB::table('product_requests')
            ->where('status', 3)
            ->whereBetween(DB::raw('DATE(created_at)'), [$start, $end])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Recent dispensing activity
     */
    public function getRecentActivity(): array
    {
        $today = Carbon::today();

        return DB::table('product_requests as pr')
            ->join('patients as p', 'pr.patient_id', '=', 'p.id')
            ->join('users as u', 'p.user_id', '=', 'u.id')
            ->join('products as prod', 'pr.product_id', '=', 'prod.id')
            ->whereBetween('pr.created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->select(
                'pr.id',
                'pr.patient_id',
                DB::raw("CONCAT(u.surname, ' ', u.firstname) as patient_name"),
                'prod.product_name as product',
                'pr.qty',
                'pr.status',
                'pr.created_at'
            )
            ->orderByDesc('pr.created_at')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $statusMap = [1 => 'Pending', 2 => 'Billed', 3 => 'Dispensed'];
                $colorMap = [1 => 'warning', 2 => 'info', 3 => 'success'];
                $row->status_label = $statusMap[$row->status] ?? 'Unknown';
                $row->status_color = $colorMap[$row->status] ?? 'secondary';
                $row->time = Carbon::parse($row->created_at)->format('h:i A');
                return $row;
            })
            ->toArray();
    }

    /**
     * Generate insights for pharmacy
     */
    public function getInsights(): array
    {
        $today = Carbon::today();
        $insights = [];

        // Low stock alert
        $lowStock = DB::table('products as p')
            ->join('stocks as s', 'p.id', '=', 's.product_id')
            ->where('p.reorder_alert', '>', 0)
            ->whereRaw('s.current_quantity <= CAST(p.reorder_alert AS SIGNED)')
            ->count();

        if ($lowStock > 0) {
            $insights[] = [
                'type' => 'alert', 'severity' => 'danger', 'icon' => 'mdi-alert',
                'title' => 'Low Stock Alert',
                'message' => "{$lowStock} item(s) below reorder level",
            ];
        }

        // Out of stock
        $outOfStock = DB::table('stocks')->where('current_quantity', '<=', 0)->count();
        if ($outOfStock > 0) {
            $insights[] = [
                'type' => 'alert', 'severity' => 'danger', 'icon' => 'mdi-package-variant-remove',
                'title' => 'Out of Stock',
                'message' => "{$outOfStock} product(s) completely out of stock",
            ];
        }

        // Dispensed today
        $dispensedToday = DB::table('product_requests')
            ->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->where('status', 3)
            ->count();

        $insights[] = [
            'type' => 'stat', 'severity' => 'success', 'icon' => 'mdi-pill',
            'title' => 'Dispensed Today',
            'message' => "{$dispensedToday} prescription(s) dispensed so far",
        ];

        // Top dispensed product today
        $topProduct = DB::table('product_requests as pr')
            ->join('products as prod', 'pr.product_id', '=', 'prod.id')
            ->whereBetween('pr.created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->selectRaw('prod.product_name as name, COUNT(*) as cnt')
            ->groupBy('prod.product_name')
            ->orderByDesc('cnt')
            ->first();

        if ($topProduct) {
            $insights[] = [
                'type' => 'info', 'severity' => 'info', 'icon' => 'mdi-star',
                'title' => 'Top Product',
                'message' => "{$topProduct->name} ({$topProduct->cnt} requests today)",
            ];
        }

        return $insights;
    }
}
