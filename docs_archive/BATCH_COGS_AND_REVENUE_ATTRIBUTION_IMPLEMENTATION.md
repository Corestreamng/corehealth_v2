# Batch-Based COGS, Dispense Revenue Attribution, and Accounting Parity

## 1. Overview & Objective

This document details the architectural standardization of **Cost of Goods Sold (COGS)**, **Goods Used**, **Inventory Stock Valuation**, **Losses/Damages**, and **Dispense Revenue Attribution** across CoreHealth v2.

### Primary Objectives Achieved:
1. **Batch-Cost Exclusivity:** COGS, Goods Used, Stock Valuation, and Loss/Damage reporting across Pharmacy Executive Summary, Audit Workbench, OpsAudit, and Store Reports derive strictly from stock batch cost prices (`stock_batches.cost_price` with a strict `0.00` fallback)—never falling back to product catalogue purchase prices (`prices.pr_buy_price` or `products.cost_price`).
2. **Mandatory Batch Cost Price:** Batch creation validation strictly enforces `cost_price > 0`, unless the batch is explicitly marked as a donation (`is_donation = 1`), which permits setting `cost_price` to `0.00`. The legacy `skip_cost_price` checkbox has been completely eliminated.
3. **True Dispense Revenue Attribution:** Dispense Revenue accurately reflects the total amount the item was sold for (`payable_amount` [patient cash/copay] + `claims_amount` [HMO/insurance claims]), attributed directly to the specific stock batch from which the medication or item was dispensed.
4. **Accounting Ledger Parity:** Pharmacy and Store inventory write-offs (damages, shrinkage, expiries) derive `unit_cost` and `total_value` strictly from `StockBatch.cost_price`, guaranteeing exact reconciliation with General Ledger journal entries (Accounts 5030/5040/1300/1310).

---

## 2. Mathematical Contracts & Formulas

### A. Data Relationship Hierarchy
```
ProductOrServiceRequest (posr)
  ├── payable_amount (Cash / Patient Copay)
  ├── claims_amount (HMO / Insurance Claims)
  └── productRequest (pr) [via pr.product_request_id = posr.id]
        └── dispensedFromBatch (sb) [via pr.dispensed_from_batch_id = sb.id]
              └── cost_price (StockBatch.cost_price)
```

### B. Accounting Metric Formulas
| Metric | Calculation Formula | Fallback Rule |
| :--- | :--- | :--- |
| **Unit Batch Cost** | `COALESCE(sb.cost_price, 0.00)` | Strict `0.00` fallback (NO catalogue lookup) |
| **Line COGS** | `posr.qty * COALESCE(sb.cost_price, 0.00)` | Strict `0.00` fallback if unbatched |
| **Line Dispense Revenue** | `posr.payable_amount + COALESCE(posr.claims_amount, 0.00)` | Fallback to `0.00` |
| **Line Gross Margin** | `Line Dispense Revenue - Line COGS` | Positive = profit, Negative = deficit |
| **Opening Stock Equation** | `Closing Stock (at Cost) + Goods Used (at Cost) - Purchases (at Cost)` | All components strictly measured at batch cost |

---

## 3. Implementation Details by Domain

### 1. Store & Inventory Workbench
- **Controller Validation ([`app/Http/Controllers/StoreWorkbenchController.php`](app/Http/Controllers/StoreWorkbenchController.php)):**
  - Updated `createBatch()` validation rule:
    ```php
    $isDonation = $request->boolean('is_donation') || $request->reference_type === 'donation';
    $request->validate([
        ...
        'cost_price' => $isDonation ? 'nullable|numeric|min:0' : 'required|numeric|gt:0',
        ...
    ]);
    ```
- **Blade Modals ([`resources/views/admin/inventory/store-workbench/manual-batch-form.blade.php`](resources/views/admin/inventory/store-workbench/manual-batch-form.blade.php) & [`resources/views/admin/inventory/store-workbench/tally-card.blade.php`](resources/views/admin/inventory/store-workbench/tally-card.blade.php)):**
  - Removed `skip_cost_price` checkbox entirely.
  - Donation toggle zeroes `cost_price` to `0.00` and displays donor/supplier selection with an inline "+ Add Donor" modal trigger.
- **Client Scripts ([`public/js/tally-card.js`](public/js/tally-card.js)):**
  - Removed `toggleTallyCostRequirement`. Verified via `node --check`.

### 2. Pharmacy Executive Summary
- **Controller ([`app/Http/Controllers/PharmacyWorkbenchController.php`](app/Http/Controllers/PharmacyWorkbenchController.php)):**
  - `fetchExecutiveSummaryData()` computes:
    - **Stock Valuation:** `(float)($batch->cost_price ?? 0.0)`.
    - **Purchases / Expenditure:** `(float)($reqItem->destinationBatch?->cost_price ?? $reqItem->sourceBatch?->cost_price ?? 0.0)`.
    - **Goods Used (COGS):** Decoupled from sales revenue, computed as `sum(qty * batch.cost_price)`.
    - **Dispense Revenue:** Computed as `sum(payable_amount + claims_amount)`.
    - **Gross Profit:** `total_revenue - total_goods_used`.
    - **Opening Stock:** `stock_valuation + total_goods_used - total_expenditure`.
- **Blade Printout & Reports View ([`resources/views/admin/pharmacy/executive_summary_print.blade.php`](resources/views/admin/pharmacy/executive_summary_print.blade.php) & [`resources/views/admin/pharmacy/partials/views/_pharmacy_reports_view.blade.php`](resources/views/admin/pharmacy/partials/views/_pharmacy_reports_view.blade.php)):**
  - Financial summary table clearly delineates Opening Stock, Purchases, Goods Available, Closing Stock, Goods Used (COGS), Dispense Revenue, and Gross Profit.
- **Client Scripts ([`public/js/pharmacy-reports.js`](public/js/pharmacy-reports.js)):**
  - Updated `populateDetailedExecutiveSummary()` to format and display COGS, Revenue, and Gross Profit. Verified via `node --check`.

### 3. Audit Workbench
- **Dispensing Revenue Attribution Story ([`app/Http/Controllers/AuditWorkbenchController.php`](app/Http/Controllers/AuditWorkbenchController.php)):**
  - Joined `posr` with `product_requests pr` and `stock_batches sb`.
  - Computes `total_qty`, `total_cash`, `total_claims`, `total_revenue`, `total_cogs`, and `gross_margin`.
  - Added dedicated drilldown handler returning individual dispense transactions with Date, Patient, Batch #, Unit Cost, Qty, Total Sold (Cash + Claims), Batch COGS, and Gross Margin.
- **Standardized SQL Expressions:**
  - Replaced all `COALESCE(NULLIF(sb.cost_price, 0), pp.pr_buy_price, 0)` with `COALESCE(sb.cost_price, 0)` across 7 audit stories:
    - `batch-valuation`
    - `damage-expiry-losses`
    - `batch-source-breakdown`
    - `substore-valuation`
    - `return-analysis`
    - `product-turnover-rate`
    - `return-damage-write-off`
- **Clinical Consumption & Department Reconciliations:**
  - Removed `initial_buy_price` fallback and incorporated `claims_amount` into income reconciliations.

### 4. OpsAudit, Stock Utilization & Damages Accounting
- **[`app/Http/Controllers/OpsAudit/OpsAuditBaseController.php`](app/Http/Controllers/OpsAudit/OpsAuditBaseController.php):**
  - Requisition cost calculation strictly prioritizes `sourceBatch->cost_price` and `destinationBatch->cost_price`.
- **[`app/Http/Controllers/StockUtilizationController.php`](app/Http/Controllers/StockUtilizationController.php):**
  - Item valuation derives directly from active batch costs.
- **[`app/Http/Controllers/PharmacyDamagesController.php`](app/Http/Controllers/PharmacyDamagesController.php) & [`app/Http/Controllers/StoreDamagesController.php`](app/Http/Controllers/StoreDamagesController.php):**
  - Inventory damage write-offs derive `unit_cost` and `total_value` directly from `StockBatch.cost_price`, maintaining 100% accounting ledger parity in journal entries (Accounts 5030/5040/1300/1310).

### 5. Dispense & Requisition Summary Shared Modal & Service Parity
- **Reusable Modal Extraction ([`resources/views/admin/inventory/components/summary-report-modal.blade.php`](resources/views/admin/inventory/components/summary-report-modal.blade.php)):**
  - Extracted the inline `#summaryReportsModal` into an isolated, reusable Blade component.
  - Supports Bootstrap 4 and Bootstrap 5 attributes (`data-toggle`/`data-bs-toggle`, `data-dismiss`/`data-bs-dismiss`, `btn-close`).
  - Supports dynamic tabs with scoped prefixing (`sw-sr` vs `pharm-modal-sr`) preventing DOM ID collisions.
  - Houses both Outbound (`given`) and Inbound (`received`) tabs powered by [`summary-report-ui.blade.php`](resources/views/admin/inventory/components/summary-report-ui.blade.php).
- **Store Workbench Integration ([`resources/views/admin/inventory/store-workbench/index.blade.php`](resources/views/admin/inventory/store-workbench/index.blade.php)):**
  - Includes shared modal `@include('admin.inventory.components.summary-report-modal', ['modalId' => 'summaryReportsModal', 'storeIds' => $store->id, 'storeName' => $store->store_name ?? 'All Stores', 'modalTitle' => 'Dispense & Requisition Summary', 'tabPrefix' => 'sw-sr'])`.
  - Renamed quick action tile to "Dispense & Requisition Summary" with icon `mdi-chart-donut`.
- **Pharmacy Workbench Integration ([`resources/views/admin/pharmacy/partials/_modals.blade.php`](resources/views/admin/pharmacy/partials/_modals.blade.php) & [`resources/views/admin/pharmacy/workbench.blade.php`](resources/views/admin/pharmacy/workbench.blade.php)):**
  - Includes shared modal for pharmacy stores with tab prefix `pharm-modal-sr`.
  - Added quick action button `#btn-pharmacy-summary-report` in top action bar.
  - Aligned report tab label to "Dispense & Requisition Summary" in `_pharmacy_reports_view.blade.php`.
- **Printout Alignment ([`resources/views/admin/inventory/print/summary-report-print.blade.php`](resources/views/admin/inventory/print/summary-report-print.blade.php)):**
  - Standardized print heading and document title to "DISPENSE & REQUISITION SUMMARY".
- **Backend Service Calculation Parity ([`app/Services/InventoryReportService.php`](app/Services/InventoryReportService.php)):**
  - Outbound dispenses compute batch-cost COGS strictly from `stock_batches.cost_price` (fallback `0.00`).
  - Outbound dispense revenue derives strictly from `payable_amount + claims_amount` (clamped with `max(0.0, ...)`).
  - Profit is strictly `Total Revenue - Batch COGS`.
  - Zero-cost donation batches (`is_donation = 1`, `cost_price = 0.00`) are strictly preserved without catalog price replacement.
  - **Sale Price Per Unit:** Explicitly calculates `sale_price_per_unit` / `unit_sale_price` (`(payable_amount + claims_amount) / total_qty`) across both aggregate summary groupings and detailed batch drilldowns, rendering dedicated columns across the main data table, printouts, and drill-down views.
  - **POSR Sale Amounts & Revenue vs Deficit Attribution:**
    - Dispense sale amounts derive strictly from the `product_or_service_requests` table (`payable_amount` [cash/copay] and `claims_amount` [HMO claims], with fallback handling for `amount` based on `hmo_id` and catalog pricing only when no POSR record exists).
    - **Revenue vs Deficit Distinction:**
      - A POSR that is paid (`payment_id` present or payment status paid) is recognized as **Cash Revenue** (`cash_revenue` / `paid_revenue`).
      - An HMO claim that is validated/approved (`validation_status` in `['validated', 'approved']`) is recognized as **Claims Revenue** (`claims_revenue` / `validated_claims_revenue`).
      - Unpaid cash/copay and unvalidated/pending HMO claims on already dispensed inventory are strictly computed and reported as **Deficit** (`deficit`, `deficit_payable`, `deficit_claim`).
      - Mathematical consistency holds: `Realized Revenue + Deficit == Total Sale Amount`.
  - **Condensed Column Layout:**
    - Main aggregate table utilizes a condensed two-tier header: `Category`, `Qty`, `Cost (₦)`, `Unit Price (₦)`, `Sale Amount (₦)` (`Payable`, `Claim`, `Total`), `Profit / Loss (₦)`, `Action`.
    - Interactive drilldown table is condensed from 13 columns to 11 columns by merging Product Name, Batch #, Expiry, and Packaging into a structured compound cell.
    - KPI cards display inline breakdowns: Total Sale Amount (`Pay` / `Clm`) and Total Profit/Loss with an `Unpaid Deficit` indicator.

---

## 4. Verification & Testing

### A. Automated PHPUnit Test Suites
- **[`tests/Feature/Audit/AuditWorkbenchBatchCostTest.php`](tests/Feature/Audit/AuditWorkbenchBatchCostTest.php):**
  - `test_dispensing_revenue_attribution_computes_batch_cogs_and_gross_margin`: **PASS**
  - `test_dispensing_revenue_attribution_drilldown_returns_batch_cost_and_margin`: **PASS**
  - `test_batch_valuation_story_respects_zero_cost_donation_batches`: **PASS**
  - `test_batch_creation_requires_cost_price_unless_marked_as_donation`: **PASS**
  - `test_batch_creation_allows_zero_cost_when_marked_as_donation`: **PASS**
- **[`tests/Feature/Pharmacy/ExecutiveSummaryTest.php`](tests/Feature/Pharmacy/ExecutiveSummaryTest.php):**
  - 7 / 7 tests passed (100%), verifying `total_goods_used`, `total_revenue`, `opening_stock`, and preservation of zero-cost donation batches.
- **[`tests/Feature/Inventory/InventoryReportSummaryTest.php`](tests/Feature/Inventory/InventoryReportSummaryTest.php):**
  - 12 / 12 tests passed (100%), including:
    - `test_inventory_report_dispense_computes_strict_batch_cogs_cash_and_claims_revenue`: **PASS**
    - `test_dispense_sale_amounts_computed_from_posr_revenue_and_deficit_logic`: **PASS** (verifies POSR amounts, paid cash, validated claims, unpaid cash deficit, and pending claim deficit)
    - `test_shared_summary_reports_modal_is_included_in_store_and_pharmacy_workbenches`: **PASS**
- **Full Inventory Domain Suite ([`tests/Feature/Inventory/`](tests/Feature/Inventory/)):**
  - 47 / 47 tests passed (100%).
- **Full Pharmacy Feature Suite ([`tests/Feature/Pharmacy/`](tests/Feature/Pharmacy/)):**
  - 43 / 43 tests passed (100%).

### B. Linting & Static Analysis
- **PHP CS Fixer:** `composer lint` passed with 0 violations across 1,245 files.
- **JavaScript Syntax:** `node --check` verified cleanly for `public/js/inventory-summary-report.js`, `public/js/pharmacy-reports.js`, and `public/js/tally-card.js`.

---

## 5. Release Information
- **Git Commit:** Head of `master` (amended)
- **Branch:** `master`
