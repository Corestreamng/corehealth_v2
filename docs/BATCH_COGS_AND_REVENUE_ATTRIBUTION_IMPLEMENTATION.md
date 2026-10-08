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
- **Full Pharmacy Feature Suite ([`tests/Feature/Pharmacy/`](tests/Feature/Pharmacy/)):**
  - 43 / 43 tests passed (100%).

### B. Linting & Static Analysis
- **PHP CS Fixer:** `composer lint` passed with 0 violations across 1,245 files.
- **JavaScript Syntax:** `node --check` verified cleanly for `public/js/pharmacy-reports.js` and `public/js/tally-card.js`.

---

## 5. Release Information
- **Git Commit:** `905b4728`
- **Git Tag:** `v2.6.0.36`
- **Branch:** `master`
