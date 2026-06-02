<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AuditReportService
{
    /**
     * Apply common payment filters to a query builder instance
     */
    protected function applyPaymentFilters($query, array $filters)
    {
        if (!empty($filters['payment_method'])) {
            $query->where('payments.payment_method', $filters['payment_method']);
        }
        if (!empty($filters['cashier_id'])) {
            $query->where('payments.user_id', $filters['cashier_id']);
        }
        if (isset($filters['min_amount']) && $filters['min_amount'] !== '') {
            $query->where('payments.total', '>=', $filters['min_amount']);
        }
        if (isset($filters['max_amount']) && $filters['max_amount'] !== '') {
            $query->where('payments.total', '<=', $filters['max_amount']);
        }
        return $query;
    }

    /**
     * Apply filters to PatientDeposit query
     */
    protected function applyDepositFilters($query, array $filters)
    {
        if (!empty($filters['payment_method'])) {
            $query->where('patient_deposits.payment_method', $filters['payment_method']);
        }
        if (!empty($filters['cashier_id'])) {
            $query->where('patient_deposits.received_by', $filters['cashier_id']);
        }
        if (isset($filters['min_amount']) && $filters['min_amount'] !== '') {
            $query->where('patient_deposits.amount', '>=', $filters['min_amount']);
        }
        if (isset($filters['max_amount']) && $filters['max_amount'] !== '') {
            $query->where('patient_deposits.amount', '<=', $filters['max_amount']);
        }
        return $query;
    }

    /**
     * Apply filters to ProductOrServiceRequest query
     */
    protected function applyRequestFilters($query, array $filters)
    {
        if (!empty($filters['payment_method'])) {
            $query->where('p.payment_method', $filters['payment_method']);
        }
        if (!empty($filters['cashier_id'])) {
            $query->where('p.user_id', $filters['cashier_id']);
        }
        if (isset($filters['min_amount']) && $filters['min_amount'] !== '') {
            $query->whereRaw('COALESCE(posr.payable_amount, posr.amount) >= ?', [$filters['min_amount']]);
        }
        if (isset($filters['max_amount']) && $filters['max_amount'] !== '') {
            $query->whereRaw('COALESCE(posr.payable_amount, posr.amount) <= ?', [$filters['max_amount']]);
        }

        // Apply item type filter
        if (!empty($filters['item_type'])) {
            if ($filters['item_type'] === 'product') {
                $query->whereNotNull('posr.product_id');
            } elseif ($filters['item_type'] === 'service') {
                $query->whereNotNull('posr.service_id');
            }
        }

        // Apply category filter
        if (!empty($filters['item_category_id'])) {
            $cat = $filters['item_category_id'];
            if (str_starts_with($cat, 'prod_')) {
                $catId = substr($cat, 5);
                $query->where('pr.category_id', $catId);
            } elseif (str_starts_with($cat, 'serv_')) {
                $catId = substr($cat, 5);
                $query->where('sv.category_id', $catId);
            }
        }

        // Apply specific item filter
        if (!empty($filters['item_id'])) {
            $itm = $filters['item_id'];
            if (str_starts_with($itm, 'prod_')) {
                $itmId = substr($itm, 5);
                $query->where('posr.product_id', $itmId);
            } elseif (str_starts_with($itm, 'serv_')) {
                $itmId = substr($itm, 5);
                $query->where('posr.service_id', $itmId);
            }
        }

        return $query;
    }

    /**
     * Get Query for ProductOrServiceRequests matching filters
     */
    public function getUnifiedReceiptsQuery(Carbon $startDate, Carbon $endDate, array $filters)
    {
        // If filters dictate we only want deposits or settlements, requests query will return empty
        if (!empty($filters['item_type']) && !in_array($filters['item_type'], ['product', 'service'])) {
            return null;
        }
        if (!empty($filters['item_category_id'])) {
            $cat = $filters['item_category_id'];
            if (!str_starts_with($cat, 'prod_') && !str_starts_with($cat, 'serv_')) {
                return null;
            }
        }
        if (!empty($filters['item_id'])) {
            $itm = $filters['item_id'];
            if (!str_starts_with($itm, 'prod_') && !str_starts_with($itm, 'serv_')) {
                return null;
            }
        }

        $query = DB::table('product_or_service_requests as posr')
            ->join('payments as p', 'posr.payment_id', '=', 'p.id')
            ->leftJoin('patients as pt', 'posr.user_id', '=', 'pt.user_id') // Join patient to get folder/file info
            ->leftJoin('users as patient_user', 'pt.user_id', '=', 'patient_user.id')
            ->leftJoin('users as cashier_user', 'p.user_id', '=', 'cashier_user.id')
            ->leftJoin('products as pr', 'posr.product_id', '=', 'pr.id')
            ->leftJoin('product_categories as pc', 'pr.category_id', '=', 'pc.id')
            ->leftJoin('services as sv', 'posr.service_id', '=', 'sv.id')
            ->leftJoin('service_categories as sc', 'sv.category_id', '=', 'sc.id')
            ->whereBetween('p.created_at', [$startDate, $endDate]);

        return $this->applyRequestFilters($query, $filters);
    }

    /**
     * Get Query for PatientDeposits matching filters
     */
    public function getWalletDepositsQuery(Carbon $startDate, Carbon $endDate, array $filters)
    {
        // If type filter is set and it's not wallet/all, return null
        if (!empty($filters['item_type']) && $filters['item_type'] !== 'wallet') {
            return null;
        }
        if (!empty($filters['item_category_id']) && $filters['item_category_id'] !== 'wallet') {
            return null;
        }
        if (!empty($filters['item_id'])) {
            return null; // A wallet deposit has no specific inventory product/service item
        }

        $query = DB::table('patient_deposits')
            ->leftJoin('patients', 'patient_deposits.patient_id', '=', 'patients.id')
            ->leftJoin('users as patient_user', 'patients.user_id', '=', 'patient_user.id')
            ->leftJoin('users as receiver_user', 'patient_deposits.received_by', '=', 'receiver_user.id')
            ->whereBetween('patient_deposits.deposit_date', [$startDate, $endDate]);

        return $this->applyDepositFilters($query, $filters);
    }

    /**
     * Get Query for Staff Settlement payments matching filters
     */
    public function getSettlementsQuery(Carbon $startDate, Carbon $endDate, array $filters)
    {
        // If type filter is set and it's not settlement/all, return null
        if (!empty($filters['item_type']) && $filters['item_type'] !== 'settlement') {
            return null;
        }
        if (!empty($filters['item_category_id']) && $filters['item_category_id'] !== 'settlement') {
            return null;
        }
        if (!empty($filters['item_id'])) {
            return null; // A staff settlement is an accounting entry and has no specific inventory item
        }

        $query = DB::table('payments')
            ->leftJoin('users as cashier_user', 'payments.user_id', '=', 'cashier_user.id')
            ->leftJoin('users as patient_user', 'payments.patient_id', '=', 'patient_user.id') // staff bills point to user
            ->where('payments.payment_type', 'STAFF_BILL_SETTLEMENT')
            ->whereBetween('payments.created_at', [$startDate, $endDate]);

        return $this->applyPaymentFilters($query, $filters);
    }

    /**
     * Aggregate performance details by Type
     */
    public function getPerformanceByType(Carbon $startDate, Carbon $endDate, array $filters)
    {
        $stats = [];

        // 1. Products & Services Performance
        $requestsQuery = $this->getUnifiedReceiptsQuery($startDate, $endDate, $filters);
        if ($requestsQuery) {
            $reqStats = $requestsQuery->select([
                    DB::raw("CASE WHEN posr.product_id IS NOT NULL THEN 'Product' ELSE 'Service' END as item_type"),
                    DB::raw("COUNT(*) as txn_count"),
                    DB::raw("SUM(COALESCE(posr.payable_amount, posr.amount)) as total_revenue")
                ])
                ->groupBy('item_type')
                ->get();

            foreach ($reqStats as $r) {
                $stats[$r->item_type] = [
                    'type' => $r->item_type,
                    'count' => $r->txn_count,
                    'revenue' => (float)$r->total_revenue
                ];
            }
        }

        // 2. Wallet Deposits Performance
        $depositsQuery = $this->getWalletDepositsQuery($startDate, $endDate, $filters);
        if ($depositsQuery) {
            $depStats = $depositsQuery->select([
                    DB::raw("COUNT(*) as txn_count"),
                    DB::raw("SUM(patient_deposits.amount) as total_revenue")
                ])
                ->first();

            if ($depStats && $depStats->txn_count > 0) {
                $stats['Wallet Deposit'] = [
                    'type' => 'Wallet Deposit',
                    'count' => $depStats->txn_count,
                    'revenue' => (float)$depStats->total_revenue
                ];
            }
        }

        // 3. Staff Settlements Performance
        $settlementsQuery = $this->getSettlementsQuery($startDate, $endDate, $filters);
        if ($settlementsQuery) {
            $settleStats = $settlementsQuery->select([
                    DB::raw("COUNT(*) as txn_count"),
                    DB::raw("SUM(payments.total) as total_revenue")
                ])
                ->first();

            if ($settleStats && $settleStats->txn_count > 0) {
                $stats['Staff Settlement'] = [
                    'type' => 'Staff Settlement',
                    'count' => $settleStats->txn_count,
                    'revenue' => (float)$settleStats->total_revenue
                ];
            }
        }

        return array_values($stats);
    }

    /**
     * Aggregate performance details by Category
     */
    public function getPerformanceByCategory(Carbon $startDate, Carbon $endDate, array $filters)
    {
        $categories = [];

        // 1. Products Categories
        $reqsQuery = $this->getUnifiedReceiptsQuery($startDate, $endDate, $filters);
        if ($reqsQuery) {
            // Clone the query builder for separate runs
            $prodQuery = (clone $reqsQuery)->whereNotNull('posr.product_id');
            $prodStats = $prodQuery->select([
                    'pc.category_name',
                    DB::raw("COUNT(*) as txn_count"),
                    DB::raw("SUM(COALESCE(posr.payable_amount, posr.amount)) as total_revenue")
                ])
                ->groupBy('pc.id', 'pc.category_name')
                ->get();

            foreach ($prodStats as $p) {
                $name = $p->category_name ?: 'Uncategorized Products';
                $categories[] = [
                    'type' => 'Product',
                    'category' => $name,
                    'count' => $p->txn_count,
                    'revenue' => (float)$p->total_revenue
                ];
            }

            // 2. Services Categories
            $servQuery = (clone $reqsQuery)->whereNotNull('posr.service_id');
            $servStats = $servQuery->select([
                    'sc.category_name',
                    DB::raw("COUNT(*) as txn_count"),
                    DB::raw("SUM(COALESCE(posr.payable_amount, posr.amount)) as total_revenue")
                ])
                ->groupBy('sc.id', 'sc.category_name')
                ->get();

            foreach ($servStats as $s) {
                $name = $s->category_name ?: 'Uncategorized Services';
                $categories[] = [
                    'type' => 'Service',
                    'category' => $name,
                    'count' => $s->txn_count,
                    'revenue' => (float)$s->total_revenue
                ];
            }
        }

        // 3. Wallet Deposits
        $depositsQuery = $this->getWalletDepositsQuery($startDate, $endDate, $filters);
        if ($depositsQuery) {
            $depStats = $depositsQuery->select([
                    DB::raw("COUNT(*) as txn_count"),
                    DB::raw("SUM(patient_deposits.amount) as total_revenue")
                ])
                ->first();

            if ($depStats && $depStats->txn_count > 0) {
                $categories[] = [
                    'type' => 'Wallet Deposit',
                    'category' => 'Wallet Top-up',
                    'count' => $depStats->txn_count,
                    'revenue' => (float)$depStats->total_revenue
                ];
            }
        }

        // 4. Staff Settlements
        $settlementsQuery = $this->getSettlementsQuery($startDate, $endDate, $filters);
        if ($settlementsQuery) {
            $settleStats = $settlementsQuery->select([
                    DB::raw("COUNT(*) as txn_count"),
                    DB::raw("SUM(payments.total) as total_revenue")
                ])
                ->first();

            if ($settleStats && $settleStats->txn_count > 0) {
                $categories[] = [
                    'type' => 'Staff Settlement',
                    'category' => 'Staff Bill Settlement',
                    'count' => $settleStats->txn_count,
                    'revenue' => (float)$settleStats->total_revenue
                ];
            }
        }

        // Sort descending by revenue
        usort($categories, function ($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });

        return $categories;
    }

    /**
     * Aggregate performance details by Item
     */
    public function getPerformanceByItem(Carbon $startDate, Carbon $endDate, array $filters)
    {
        $items = [];

        // 1. Products Items
        $reqsQuery = $this->getUnifiedReceiptsQuery($startDate, $endDate, $filters);
        if ($reqsQuery) {
            $prodQuery = (clone $reqsQuery)->whereNotNull('posr.product_id');
            $prodStats = $prodQuery->select([
                    'pr.product_name',
                    DB::raw("COUNT(*) as txn_count"),
                    DB::raw("SUM(COALESCE(posr.payable_amount, posr.amount)) as total_revenue")
                ])
                ->groupBy('pr.id', 'pr.product_name')
                ->orderBy(DB::raw("SUM(COALESCE(posr.payable_amount, posr.amount))"), 'desc')
                ->limit(100)
                ->get();

            foreach ($prodStats as $p) {
                $items[] = [
                    'type' => 'Product',
                    'name' => $p->product_name ?: 'Unknown Product',
                    'count' => $p->txn_count,
                    'revenue' => (float)$p->total_revenue
                ];
            }

            // 2. Services Items
            $servQuery = (clone $reqsQuery)->whereNotNull('posr.service_id');
            $servStats = $servQuery->select([
                    'sv.service_name',
                    DB::raw("COUNT(*) as txn_count"),
                    DB::raw("SUM(COALESCE(posr.payable_amount, posr.amount)) as total_revenue")
                ])
                ->groupBy('sv.id', 'sv.service_name')
                ->orderBy(DB::raw("SUM(COALESCE(posr.payable_amount, posr.amount))"), 'desc')
                ->limit(100)
                ->get();

            foreach ($servStats as $s) {
                $items[] = [
                    'type' => 'Service',
                    'name' => $s->service_name ?: 'Unknown Service',
                    'count' => $s->txn_count,
                    'revenue' => (float)$s->total_revenue
                ];
            }
        }

        // Sort descending by revenue
        usort($items, function ($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });

        // Slice to top 100 overall
        return array_slice($items, 0, 100);
    }
}
