<?php

namespace App\Services\Dashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\StoreRequisition;
use App\Models\PurchaseOrder;

class StoreDashboardService
{
    /**
     * Get store stats for dashboard cards
     */
    public function getStats(): array
    {
        return [
            'pending_reqs' => StoreRequisition::where('status', StoreRequisition::STATUS_PENDING)->count(),
            'pending_pos' => PurchaseOrder::where('status', PurchaseOrder::STATUS_SUBMITTED)->count(),
            'approved_pos' => PurchaseOrder::where('status', PurchaseOrder::STATUS_APPROVED)->count(),
            'fulfill' => StoreRequisition::whereIn('status', [StoreRequisition::STATUS_APPROVED, StoreRequisition::STATUS_PARTIAL])->count(),
        ];
    }

    /**
     * Get store operation queue counts mirroring store workbench
     */
    public function getQueueCounts(): array
    {
        $today = Carbon::today();

        return Cache::remember('dashboard.store.queues', 30, function () use ($today) {
            $pendingReqs = StoreRequisition::whereIn('status', [StoreRequisition::STATUS_PENDING])
                ->count();

            $pendingPOs = PurchaseOrder::whereIn('status', [PurchaseOrder::STATUS_SUBMITTED])
                ->count();
            
            $approvedPOs = PurchaseOrder::whereIn('status', [PurchaseOrder::STATUS_APPROVED])
                ->count();

            $partialReqs = StoreRequisition::whereIn('status', [StoreRequisition::STATUS_APPROVED, StoreRequisition::STATUS_PARTIAL])
                ->count();

            return [
                ['name' => 'Pending Requisitions', 'filter' => 'requisitions', 'icon' => 'mdi-swap-horizontal', 'color' => 'warning',
                 'count' => $pendingReqs],
                ['name' => 'Pending POs', 'filter' => 'pos', 'icon' => 'mdi-cart-arrow-down', 'color' => 'danger',
                 'count' => $pendingPOs],
                ['name' => 'Approved POs', 'filter' => 'approved-pos', 'icon' => 'mdi-check-circle-outline', 'color' => 'info',
                 'count' => $approvedPOs],
                ['name' => 'To Fulfill', 'filter' => 'fulfillment', 'icon' => 'mdi-truck-delivery', 'color' => 'success',
                 'count' => $partialReqs],
            ];
        });
    }

    /**
     * Stock health breakdown for donut chart
     */
    public function getStockHealth(): array
    {
        // Low stock alerts (using store_stocks table which has current aggregated values)
        $lowStock = DB::table('store_stocks')
            ->where('is_active', true)
            ->whereRaw('current_quantity <= reorder_level')
            ->where('current_quantity', '>', 0)
            ->count();

        $outOfStock = DB::table('store_stocks')
            ->where('is_active', true)
            ->where('current_quantity', '<=', 0)
            ->count();

        $healthyStock = DB::table('store_stocks')
            ->where('is_active', true)
            ->whereRaw('current_quantity > reorder_level')
            ->count();

        return [
            ['label' => 'Healthy', 'value' => $healthyStock, 'color' => '#10b981'],
            ['label' => 'Low Stock', 'value' => $lowStock, 'color' => '#f59e0b'],
            ['label' => 'Out of Stock', 'value' => $outOfStock, 'color' => '#ef4444'],
        ];
    }

    /**
     * Requisition trend
     */
    public function getRequisitionTrend(string $start = null, string $end = null): array
    {
        $start = $start ?: Carbon::now()->startOfMonth()->toDateString();
        $end = $end ?: Carbon::today()->toDateString();

        return StoreRequisition::whereBetween(DB::raw('DATE(created_at)'), [$start, $end])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Recent inventory activity
     */
    public function getRecentActivity(): array
    {
        $today = Carbon::today();

        // Combine recent requisitions and POs for an activity feed
        $requisitions = StoreRequisition::with(['fromStore', 'toStore', 'requester'])
            ->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
        
        $pos = PurchaseOrder::with(['supplier'])
            ->whereBetween('created_at', [$today->copy()->startOfDay(), $today->copy()->endOfDay()])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $activity = [];

        foreach ($requisitions as $r) {
            $activity[] = [
                'id' => $r->id,
                'type' => 'Requisition',
                'ref' => $r->requisition_number,
                'detail' => $r->fromStore?->store_name . ' → ' . $r->toStore?->store_name,
                'status_label' => ucfirst($r->status),
                'status_color' => $r->getStatusBadgeClass(),
                'time' => $r->created_at->format('h:i A'),
                'created_at' => $r->created_at,
            ];
        }

        foreach ($pos as $po) {
            $activity[] = [
                'id' => $po->id,
                'type' => 'Purchase Order',
                'ref' => $po->po_number,
                'detail' => $po->supplier?->supplier_name ?? 'Local Vendor',
                'status_label' => ucfirst($po->status),
                'status_color' => $po->getStatusBadgeClass(),
                'time' => $po->created_at->format('h:i A'),
                'created_at' => $po->created_at,
            ];
        }

        usort($activity, function($a, $b) {
            return $b['created_at'] <=> $a['created_at'];
        });

        return array_slice($activity, 0, 10);
    }

    /**
     * Generate insights for store operations
     */
    public function getInsights(): array
    {
        $insights = [];

        // Pending approvals
        $pendingApprovals = StoreRequisition::where('status', StoreRequisition::STATUS_PENDING)->count();
        if ($pendingApprovals > 0) {
            $insights[] = [
                'type' => 'alert', 'severity' => 'warning', 'icon' => 'mdi-clock-outline',
                'title' => 'Pending Approvals',
                'message' => "{$pendingApprovals} requisition(s) awaiting manager approval",
            ];
        }

        // Expiring soon (stock batches)
        $expiringCount = DB::table('stock_batches')
            ->where('is_active', true)
            ->where('current_qty', '>', 0)
            ->whereBetween('expiry_date', [now(), now()->addDays(30)])
            ->count();
        
        if ($expiringCount > 0) {
            $insights[] = [
                'type' => 'alert', 'severity' => 'danger', 'icon' => 'mdi-calendar-alert',
                'title' => 'Expiring Stock',
                'message' => "{$expiringCount} batch(es) expiring within 30 days",
            ];
        }

        // Fulfillment lag
        $delayedFulfillment = StoreRequisition::whereIn('status', [StoreRequisition::STATUS_APPROVED])
            ->where('created_at', '<', now()->subDays(2))
            ->count();
        
        if ($delayedFulfillment > 0) {
            $insights[] = [
                'type' => 'info', 'severity' => 'info', 'icon' => 'mdi-truck-delivery',
                'title' => 'Fulfillment Lag',
                'message' => "{$delayedFulfillment} approved requisition(s) older than 48 hours",
            ];
        }

        return $insights;
    }
}
