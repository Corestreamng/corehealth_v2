# Ops Audit Requisition & Partial Fulfillment Accountability Architecture

**Date**: 2026-11-01  
**Git Release Tag**: v2.6.0.57  
**Module**: Ops Audit — Store & Inventory (`/ops-audit/store`) & Pharmacy (`/ops-audit/pharmacy`)  

---

## 1. Executive Summary & Context

During hospital store and inventory auditing in the Store Ops Audit workbench (`/ops-audit/store`), internal audit reported that partially fulfilled requisitions appeared to be unaccounted for. 

A comprehensive forensic audit of the codebase, queries, database records, and UI views revealed that while partially fulfilled records were counted in the raw unfiltered record tally, they were **completely obscured, unfilterable, and financially distorted** across the user interface and data pipeline.

---

## 2. Root Cause Analysis

### 2.1 Complete Absence of `Partial` from Status Filter Dropdowns
In `resources/views/admin/ops_audit/store.blade.php` and `resources/views/admin/ops_audit/partials/requisitions_tab_pane.blade.php`, the status filter dropdown hardcoded only 4 statuses:
- `pending`
- `approved`
- `fulfilled`
- `rejected`

Neither `partial` (Partially Fulfilled) nor `cancelled` existed. Consequently, auditors could not filter for or review any of the 577 partially fulfilled requisitions in the hospital database. Filtering by `Fulfilled` removed all partial records, giving the false appearance that partial fulfillments were dropped from the audit ledger.

### 2.2 Unexplained "Unfulfilled Balance" Blindspot in KPI Cards
The Requisitions KPI strip displayed:
- Total Requisitions
- Req Value (Cost)
- Appr Value (Cost)
- Ful Value (Cost)
- Rej Value (Cost)

In the audited month (August 2026 for Pharmacy Hub), Approved Value totaled **₦108,797,452.76**, while Fulfilled Value totaled **₦87,428,763.46**, leaving an unexplained **₦21,368,689.30** unfulfilled gap with `₦0.00` rejected. Because the KPI strip had no indicator for Pending/Partial Balances, auditors assumed partial requisitions were missing from the valuation.

### 2.3 Zero-Cost Batch Resolution Bug (₦1.23M Valuation Suppression)
In `OpsAuditBaseController::moduleRequisitionsData`, item cost evaluation used:
```php
$getCost = fn ($i) => ($i->sourceBatch || $i->destinationBatch) 
    ? (float)($i->sourceBatch?->cost_price ?? $i->destinationBatch?->cost_price ?? 0.0) 
    : (float)($i->product->price->pr_buy_price ?? 0.0);
```
Across the database, 2,181 batches had `cost_price <= 0`. For unfulfilled items, `$i->sourceBatch` was null, correctly falling back to `$i->product->price->pr_buy_price`. Once an item was fulfilled from a batch where `cost_price = 0`, PHP's null-coalescing operator `??` treated `0.00` as non-null and failed to fall back to `pr_buy_price`. In August 2026 alone, 52 fulfilled items evaluated to `₦0.00`, artificially suppressing over **₦1,234,598.00** from the fulfilled cost value.

### 2.4 Top Filter Bar `store_id` Disconnect
The top filter bar sent `store_id`, but `moduleRequisitionsData` only inspected `from_store_id` and `to_store_id`. Selecting a store in the global filter had zero effect on the Requisitions tab until re-selected in the tab sub-dropdown.

### 2.5 Empty `Fulfilled By` on Partial Requisitions
`RequisitionService::fulfill` only populated `$requisition->fulfilled_by` and `fulfilled_at` when 100% of items were completely fulfilled. On partial requisitions, `fulfilled_by` remained `NULL`, causing the audit table to display `-` even though stock was actively dispensed by a store officer.

---

## 3. Implemented Architectural Solutions

### 3.1 Status Filter Parity
Updated `resources/views/admin/ops_audit/store.blade.php`, `resources/views/admin/ops_audit/partials/requisitions_tab_pane.blade.php`, and `resources/views/admin/ops_audit/pharmacy.blade.php`:
- Added `<option value="partial">Partially Fulfilled</option>`
- Added `<option value="cancelled">Cancelled</option>`

### 3.2 High-Fidelity Cost Resolution Engine
In `app/Http/Controllers/OpsAudit/OpsAuditBaseController.php`:
```php
$getCost = function ($i) {
    $batchCost = (float)($i->sourceBatch?->cost_price ?? $i->destinationBatch?->cost_price ?? 0.0);
    if ($batchCost > 0) {
        return $batchCost;
    }

    return (float)($i->product?->price?->pr_buy_price ?? 0.0);
};
```
- Eager-loaded `items.destinationBatch.creator`.
- Strictly validates `$batchCost > 0` before accepting batch cost, properly falling back to catalog buy price (`pr_buy_price`).

### 3.3 Dynamic 6-Card Balanced Requisitions KPI Strip
Added `Pending/Partial Bal` KPI (`Appr Value - Ful Value`):
```php
$pendingBalCost = max(0, $totalApprCost - $totalFulCost);

return [
    ['label' => 'Total Requisitions', 'value' => number_format($all->count()), 'color' => '#0d6efd'],
    ['label' => 'Req Value (Cost)', 'value' => '₦' . number_format($totalReqCost, 2), 'color' => '#17a2b8'],
    ['label' => 'Appr Value (Cost)', 'value' => '₦' . number_format($totalApprCost, 2), 'color' => '#ffc107'],
    ['label' => 'Ful Value (Cost)', 'value' => '₦' . number_format($totalFulCost, 2), 'color' => '#28a745'],
    ['label' => 'Pending/Partial Bal', 'value' => '₦' . number_format($pendingBalCost, 2), 'color' => '#fd7e14'],
    ['label' => 'Rej Value (Cost)', 'value' => '₦' . number_format($totalRejCost, 2), 'color' => '#dc3545'],
];
```
- Auto-scales into high-density 6-column layout (`col-md-2` per card via `layout.blade.php`).
- Ensures mathematical balance: `Fulfilled Value + Pending/Partial Balance = Approved Value`.

### 3.4 Bidirectional Global Store Filter Resolution
```php
if ($request->filled('store_id') && !$request->filled('from_store_id') && !$request->filled('to_store_id')) {
    $query->where(function ($q) use ($request) {
        $q->where('from_store_id', $request->store_id)
            ->orWhere('to_store_id', $request->store_id);
    });
}
```
Selecting a store in the top filter bar automatically scopes requisitions where the store is either the sender or recipient.

### 3.5 Interim Fulfiller Attribution
- In `app/Http/Controllers/OpsAudit/OpsAuditBaseController.php`, if `$row->fulfilled_by` is null on partial records, the system resolves the fulfiller from `items.destinationBatch.creator` and badges them with `<small class="text-muted d-block">(Interim)</small>`.
- In `app/Services/RequisitionService.php`, partial fulfillments now preserve the authenticated fulfiller ID (`$requisition->fulfilled_by = auth()->id()`) if previously unset.
- In `resources/views/admin/ops_audit/details/requisition.blade.php`, interim fulfillers are clearly distinguished from final closing fulfillers.

---

## 4. Verification & Testing

Automated PHPUnit test suite added in `tests/Feature/OpsAudit/RequisitionFiltersLayoutTest.php`:
- `test_store_ops_audit_index_renders_requisitions_tab_with_partial_and_cancelled_filters`: Verifies DOM structure of status dropdowns.
- `test_store_ops_audit_requisitions_data_endpoint_supports_partial_filter_and_kpis`: Verifies AJAX endpoint filtering by `status=partial`, JSON response structure, and presence of `Pending/Partial Bal` KPI.
- `test_store_ops_audit_requisitions_supports_top_level_store_filter`: Tests global `store_id` filter routing.
- `test_store_ops_audit_requisitions_details_endpoint_renders_properly`: Verifies modal requisition details view.
- `test_pharmacy_ops_audit_stock_tab_supports_partial_status`: Tests pharmacy stock requisition item partial status resolution.

**Execution Command**:
```bash
vendor/bin/phpunit tests/Feature/OpsAudit/RequisitionFiltersLayoutTest.php --testdox
```
**Result**: 5 tests, 22 assertions, 100% passing.
