# Pharmacy Quick Actions Feature — Implementation Plan (Updated)

> **Last Updated:** June 2025  
> **Architecture:** Workbench-integrated blade partials (not standalone pages)  
> **Status:** Phase 6 — QA in progress, gap analysis complete  

---

## Overview

Post-transaction actions (Returns, Damages, Stock Reports) are embedded directly into the pharmacy workbench via blade partials that follow the existing queue-view panel pattern. Three buttons in the sidebar trigger slide-in panels, identical in feel to the Reports & Analytics feature.

---

## Architecture Decision: Workbench-Embedded Partials

### Chosen Approach
- **Three blade partials** included in `workbench.blade.php` via `@include`
- **queue-view CSS pattern**: `display: none` by default, `display: flex` when `.active` class added
- **JS show/hide functions** toggle panels via `hideAllPanelViews()` → add `.active`
- **Lazy initialization**: DataTables, stats, and dropdowns load on first panel open
- **Inline modals** for approve/reject workflows

### Why Not Standalone Pages
- Pharmacists stay in context — no page navigation away from the workbench
- Consistent UX with existing Reports & Analytics panel
- Shared CSRF tokens, auth context, and workbench utilities (toastr, formatMoney, etc.)

### File Structure
```
resources/views/admin/pharmacy/
├── workbench.blade.php              ← Main workbench (includes partials)
├── partials/
│   ├── _returns.blade.php           ← Returns list + create form + modals
│   ├── _damages.blade.php           ← Damages list + create form + modals
│   └── _stock_reports.blade.php     ← Stock overview + expiry + by-store + by-category
├── returns/index.blade.php          ← Standalone fallback (unused)
├── damages/index.blade.php          ← Standalone fallback (unused)
└── reports/index.blade.php          ← Standalone fallback (unused)
```

---

## 1. DATABASE LAYER

### 1.1 Migration: `add_return_and_damage_fields_to_product_requests` ✅
Adds return/damage tracking columns to `product_requests` table.

| Column | Type | Purpose |
|---|---|---|
| `returned_by` | FK → users | Who processed the return |
| `returned_date` | timestamp | When returned |
| `returned_qty` | decimal(10,2) | Qty returned |
| `refund_amount` | decimal(10,2) | Refund value |
| `return_reason` | text | Why returned |
| `return_condition` | string(50) | good / damaged / expired |
| `damaged_by` | FK → users | Who recorded damage |
| `damaged_date` | timestamp | When damaged |
| `damaged_qty` | decimal(10,2) | Qty damaged |
| `damage_reason` | text | Why damaged |
| `damage_type` | string(50) | expired / broken / contaminated / other |
| `approved_by` | FK → users | Who approved |
| `approved_at` | timestamp | When approved |
| `approval_notes` | text | Approval/rejection notes |

**Status:** ✅ Migrated

### 1.2 Migration: `create_pharmacy_returns_table` ✅
Dedicated returns table with full audit trail.

**Key columns:** `product_request_id`, `product_or_service_request_id`, `patient_id`, `product_id`, `store_id`, `batch_id`, `qty_returned`, `original_qty`, `refund_amount`, `original_amount`, `return_condition`, `return_reason`, `restock`, `refund_to_patient`, `refund_to_hmo`, `status`, `created_by`, `approved_by`, `journal_entry_id`

**Indexes:** `status`, `created_at`, composite `(patient_id, created_at)`

**Status:** ✅ Migrated

### 1.3 Migration: `create_pharmacy_damages_table` ✅
Dedicated damages table with stock deduction tracking.

**Key columns:** `product_id`, `store_id`, `batch_id`, `qty_damaged`, `unit_cost`, `total_value`, `damage_type`, `damage_reason`, `discovered_date`, `status`, `created_by`, `approved_by`, `journal_entry_id`, `stock_deducted`, `stock_deducted_at`

**Indexes:** `status`, `damage_type`, `discovered_date`, composite `(store_id, discovered_date)`

**Status:** ✅ Migrated

---

## 2. MODELS

### 2.1 PharmacyReturn ✅
- **Traits:** HasFactory, SoftDeletes, Auditable
- **Relationships (9):** productRequest, billRequest, patient, product, store, batch, creator, approver, journalEntry
- **Casts:** restock (bool), approved_at (datetime), all monetary fields (decimal:2)

### 2.2 PharmacyDamage ✅
- **Traits:** HasFactory, SoftDeletes, Auditable
- **Relationships (6):** product, store, batch, creator, approver, journalEntry
- **Casts:** discovered_date (date), approved_at (datetime), stock_deducted (bool), monetary fields (decimal:2)

---

## 3. CONTROLLERS

### 3.1 PharmacyReturnsController ✅ (452 lines)

| Method | Purpose | Status |
|---|---|---|
| `index()` | List view or AJAX stats (stats_only param) | ✅ |
| `create()` | Create view | ✅ |
| `searchDispensedItems()` | AJAX search dispensed items by product/patient | ✅ Fixed |
| `store()` | Create return, set ProductRequest.status=4 | ✅ |
| `show($id)` | View return details | ✅ |
| `approve($id)` | Approve pending return → triggers observer JE | ✅ |
| `reject($id)` | Reject pending return → revert ProductRequest.status to 3 | ✅ |
| `processRefund($id)` | Complete approved return → restock batch if applicable | ✅ |
| `datatables()` | Server-side DataTable with filters | ✅ |

### 3.2 PharmacyDamagesController ✅ (422 lines)

| Method | Purpose | Status |
|---|---|---|
| `index()` | List view or AJAX stats | ✅ |
| `create()` | Create view with stores | ✅ |
| `store()` | Create damage report with stock validation | ✅ |
| `show($id)` | View damage details | ✅ |
| `approve($id)` | Approve → observer creates JE + deducts stock | ✅ |
| `reject($id)` | Reject damage report | ✅ |
| `datatables()` | Server-side DataTable with filters | ✅ |
| `searchProducts()` | AJAX product search by store | ✅ |
| `getBatches()` | AJAX batch lookup by product+store | ✅ |

### 3.3 PharmacyReportsController ✅ (372 lines)

| Method | Purpose | Status |
|---|---|---|
| `index()` | Dashboard or AJAX stats | ✅ |
| `stockReport()` | DataTable stock overview with filters | ✅ |
| `stockByStore()` | JSON summary grouped by store | ✅ |
| `stockByCategory()` | JSON summary grouped by category | ✅ |
| `valuationReport()` | Stock valuation data | ✅ |
| `exportStock()` | CSV download | ✅ |
| `expiringStock()` | DataTable of batches expiring within N days | ✅ |
| `movementAnalysis()` | ⚠️ **PLACEHOLDER** — not yet implemented | ❌ Stub |

---

## 4. OBSERVERS (Accounting Integration)

### 4.1 PharmacyReturnObserver ✅
**Trigger:** `updated` event when `status` → `approved`

**GL Accounts:**
| Code | Name | Role |
|---|---|---|
| 1010 | Cash | CR — patient refund |
| 1020 | Bank | CR — bank refund (alt) |
| 1300 | Inventory - Pharmacy | DR — restockable returns |
| 1110 | AR - HMO | CR — HMO portion reversal |
| 5060 | Loss on Returns | DR — non-restockable returns |

**JE Patterns:**
- **Restockable (good condition):** DR Inventory 1300 / CR Cash 1010
- **Non-restockable (damaged/expired):** DR Loss on Returns 5060 / CR Cash 1010
- **HMO split:** DR Inventory or Loss / CR Cash (patient) + CR AR-HMO 1110 (HMO)

### 4.2 PharmacyDamageObserver ✅
**Trigger:** `updated` event when `status` → `approved`

**GL Accounts:**
| Code | Name | When Used |
|---|---|---|
| 1300 | Inventory - Pharmacy | Always CR |
| 5030 | Damaged Goods Write-off | DR — broken/contaminated/spoiled/other |
| 5040 | Expired Stock Write-off | DR — expired items |
| 5050 | Theft/Shrinkage | DR — theft |

**Stock Deduction:**
1. Deducts from `Product->stock->current_quantity` (global Stock model)
2. If batch_id set, also deducts from `StockBatch.current_qty`
3. Sets `stock_deducted = true` to prevent duplicates

**Both observers registered in AppServiceProvider** ✅

---

## 5. ROUTES (35 total) ✅

### Returns (9 routes)
| Method | URL | Name |
|---|---|---|
| GET | `/pharmacy/returns` | `pharmacy.returns.index` |
| GET | `/pharmacy/returns/datatables` | `pharmacy.returns.datatables` |
| GET | `/pharmacy/returns/search-dispensed-items` | `pharmacy.returns.search-dispensed` |
| GET | `/pharmacy/returns/create` | `pharmacy.returns.create` |
| POST | `/pharmacy/returns` | `pharmacy.returns.store` |
| GET | `/pharmacy/returns/{id}` | `pharmacy.returns.show` |
| POST | `/pharmacy/returns/{id}/approve` | `pharmacy.returns.approve` |
| POST | `/pharmacy/returns/{id}/reject` | `pharmacy.returns.reject` |
| POST | `/pharmacy/returns/{id}/process-refund` | `pharmacy.returns.process-refund` |

### Damages (9 routes)
| Method | URL | Name |
|---|---|---|
| GET | `/pharmacy/damages` | `pharmacy.damages.index` |
| GET | `/pharmacy/damages/datatables` | `pharmacy.damages.datatables` |
| GET | `/pharmacy/damages/search-products` | `pharmacy.damages.search-products` |
| GET | `/pharmacy/damages/get-batches` | `pharmacy.damages.get-batches` |
| GET | `/pharmacy/damages/create` | `pharmacy.damages.create` |
| POST | `/pharmacy/damages` | `pharmacy.damages.store` |
| GET | `/pharmacy/damages/{id}` | `pharmacy.damages.show` |
| POST | `/pharmacy/damages/{id}/approve` | `pharmacy.damages.approve` |
| POST | `/pharmacy/damages/{id}/reject` | `pharmacy.damages.reject` |

### Reports (8 routes)
| Method | URL | Name |
|---|---|---|
| GET | `/pharmacy/reports` | `pharmacy.reports.index` |
| GET | `/pharmacy/reports/stock-overview` | `pharmacy.reports.stock-overview` |
| GET | `/pharmacy/reports/stock-by-store` | `pharmacy.reports.by-store` |
| GET | `/pharmacy/reports/stock-by-category` | `pharmacy.reports.by-category` |
| GET | `/pharmacy/reports/valuation` | `pharmacy.reports.valuation` |
| GET | `/pharmacy/reports/export-stock` | `pharmacy.reports.export-stock` |
| GET | `/pharmacy/reports/expiring-stock` | `pharmacy.reports.expiring` |
| GET | `/pharmacy/reports/movement-analysis` | `pharmacy.reports.movement-analysis` |

### Workbench-shared (9 existing PharmacyWorkbenchController routes)
Stores, statistics, and report endpoints reused by stock filter dropdowns.

---

## 6. PERMISSIONS ✅

### 13 Pharmacy Permissions (Seeded)
| Permission | Description |
|---|---|
| `pharmacy.returns.view` | View pharmacy returns |
| `pharmacy.returns.create` | Create pharmacy returns |
| `pharmacy.returns.approve` | Approve pharmacy returns |
| `pharmacy.returns.reject` | Reject pharmacy returns |
| `pharmacy.returns.process` | Process refunds for returns |
| `pharmacy.damages.view` | View pharmacy damage reports |
| `pharmacy.damages.create` | Record pharmacy damage reports |
| `pharmacy.damages.approve` | Approve pharmacy damage reports |
| `pharmacy.damages.reject` | Reject pharmacy damage reports |
| `pharmacy.reports.stock` | View pharmacy stock reports |
| `pharmacy.reports.valuation` | View pharmacy stock valuation |
| `pharmacy.reports.expiring` | View expiring stock reports |
| `pharmacy.reports.export` | Export pharmacy reports |

### Role Assignments
| Role | Access |
|---|---|
| **admin** | All `pharmacy.*` permissions |
| **pharmacist** | View + create (no approve/reject) |
| **store-manager** | All 13 permissions explicitly |

---

## 7. WORKBENCH UI INTEGRATION ✅

### Sidebar Buttons
Three `quick-action-btn` buttons in the Post-Transaction Quick Actions section:
- **Process Returns** (`#btn-pharmacy-returns`) — `mdi-undo-variant`
- **Report Damages** (`#btn-pharmacy-damages`) — `mdi-alert-octagon`
- **Stock Reports** (`#btn-pharmacy-stock-reports`) — `mdi-file-chart`

### Panel Pattern
Each panel follows the queue-view pattern:
```
div.queue-view#pharmacy-{feature}-view → hidden by default
  → header with gradient background + close button
  → stat-card-mini row (4 summary cards)
  → date-presets-bar (filters)
  → DataTable or tab content
```

### JS Module (~700 lines)
- `hideAllPanelViews()` — master hide function
- Show/hide pairs for each panel
- Lazy-init DataTables with `window.pharmacy{Feature}Initialized` flags
- AJAX stats loaders
- Form submit handlers
- Approve/reject modal handlers via event delegation
- Cascading select handlers (damage: store→product→batch)
- Debounced search (returns: dispensed item search)

---

## 8. GAP ANALYSIS — BUGS & ISSUES

### 🔴 Critical Bugs (Will cause errors or broken functionality)

| # | Location | Issue | Fix Required |
|---|---|---|---|
| **B1** | `PharmacyReturnsController::datatables()` → actions column | Uses `onclick="approveReturn(id)"` / `onclick="rejectReturn(id)"` / `onclick="viewReturn(id)"` / `onclick="processRefund(id)"` — **none of these JS functions exist** in workbench JS. Workbench JS listens on `.btn-approve-return` / `.btn-reject-return` CSS classes with `data-id` attributes instead. | Rewrite returns actions column to use `data-id` + CSS classes matching the workbench event delegation, same pattern the damages controller already uses |
| **B2** | `PharmacyReturnsController::datatables()` | No `->addIndexColumn()` call — the first column `{ data: 'DT_RowIndex' }` will be empty/error | Add `->addIndexColumn()` before `->addColumn()` chain |
| **B3** | `PharmacyDamagesController::datatables()` | Same missing `->addIndexColumn()` issue | Add `->addIndexColumn()` |
| **B4** | `PharmacyReturnsController::reject()` | Validates `approval_notes` field, but workbench JS sends `rejection_reason` as the parameter name | Change controller to validate `rejection_reason` (match damages controller pattern) |
| **B5** | `_returns.blade.php` | `return_condition` select has `wrong_item` option, but controller validation only accepts `good,damaged,expired` — form submission will fail with 422 | Either remove `wrong_item` from partial or add it to controller validation |
| **B6** | Workbench JS `loadDamagesStats()` | References `$('#damages-stat-total')` — this element doesn't exist in the partial. Partial has `damages-stat-pending`, `damages-stat-approved`, `damages-stat-value`, `damages-stat-deducted` | Fix JS to update the actual stat card IDs |
| **B7** | Workbench JS `loadReturnsStats()` | Never updates `#returns-stat-rejected` card. Controller stats don't return a `rejected` count. | Add `rejected` to controller stats, update JS to populate the card |

### 🟡 Logic & Data Issues (May cause incorrect behavior)

| # | Location | Issue | Fix Required |
|---|---|---|---|
| **L1** | `PharmacyDamageObserver::deductStock()` | Deducts from `Product->stock` (global `stocks` table) but NOT from `StoreStock` (per-store `store_stocks` table). Stock appears deducted globally but specific store quantity unchanged. | Add `StoreStock::where('product_id', ...)->where('store_id', ...)->decrement('current_quantity', qty)` |
| **L2** | `PharmacyReportsController` | Uses `StoreStock` model joined with `prices.pr_buy_price` for cost calculations. But `prices` table has one `pr_buy_price` per product — doesn't account for batch-level cost variations (FIFO/weighted avg). | Acceptable for MVP — document as known limitation |
| **L3** | `PharmacyReturnObserver` | Creates JE on approval, but `processRefund()` does the actual restocking separately. If observer fails silently, stock may not match JE. | Consider moving restock into observer to keep atomic |

### 🟢 Minor / Cosmetic Issues

| # | Location | Issue |
|---|---|---|
| **M1** | Standalone views (returns/index, damages/index, reports/index) | Still exist but not actively used. Column counts differ from workbench partials. |
| **M2** | `PharmacyReportsController::movementAnalysis()` | Placeholder returning "not implemented" message |
| **M3** | `PharmacyReturn->patient()` | Uses lowercase `patient::class` — works on Windows but may break on case-sensitive Linux deployments |

### ⚠️ Security Gap

| # | Issue | Fix Required |
|---|---|---|
| **S1** | **No permission middleware** enforced on any controller method. Permissions are seeded but never checked. Any authenticated user can access all endpoints. | Add `$this->middleware('permission:pharmacy.returns.view')` etc. in controller constructors, or add `@can` directives in blade partials |

---

## 9. UI/UX IMPROVEMENT RECOMMENDATIONS

### 9.1 Speed Improvements

| # | Improvement | Rationale | Priority |
|---|---|---|---|
| **SPD-1** | **Prefetch stats on workbench load** | Currently stats only load when panel opens. Prefetch in background after workbench renders so panels feel instant. | High |
| **SPD-2** | **Cache store/category dropdowns** | Store and category lists are fetched every time a panel opens. Cache in JS variables after first fetch — they rarely change mid-session. | High |
| **SPD-3** | **Debounce damage product search** | Add debounced search input for damage product select instead of loading ALL products for a store. For stores with 500+ products the dropdown is slow. Replace `<select>` with a searchable input like the returns dispensed item search. | Medium |
| **SPD-4** | **Skeleton loaders for stat cards** | Show animated skeleton placeholders while stats load instead of static "0" values that flash-update. | Low |
| **SPD-5** | **DataTable state persistence** | Remember filter selections (status, date range) when toggling between panels so users don't re-enter filters. Use `stateSave: true` in DataTable config. | Medium |

### 9.2 Clarity Improvements

| # | Improvement | Rationale | Priority |
|---|---|---|---|
| **CLR-1** | **Add confirmation dialog before approve** | Currently clicking approve immediately fires the AJAX. Add a brief confirmation: "Approve this return? A journal entry will be created and ₦X refunded." | High |
| **CLR-2** | **Show JE reference after approval** | After approve/reject, display the created JE reference number in a toastr or inline. Currently just says "approved" with no accounting reference. | Medium |
| **CLR-3** | **Add "View Details" slide-out for returns** | The `viewReturn(id)` function doesn't exist. Add a slide-out detail view showing full return info + JE details + timeline, triggered from the eye icon in DataTable. | High |
| **CLR-4** | **Progress indicator for create forms** | Add a simple 2-step indicator for returns creation: Step 1 "Search & Select Item" → Step 2 "Enter Return Details". Currently the form is a wall of fields. | Medium |
| **CLR-5** | **Empty state illustrations** | When no returns/damages exist, show a friendly empty state ("No returns recorded yet") instead of an empty DataTable. | Low |
| **CLR-6** | **Badge counts on sidebar buttons** | Show pending counts as small badges on the Process Returns and Report Damages buttons: e.g., "Process Returns (3)". This alerts supervisors that items need approval. | High |
| **CLR-7** | **Color-code expiring stock rows** | In the Expiring Stock DataTable, use row background colors: red for ≤30 days, yellow for ≤60 days, green for ≤90 days. Currently only the status badge is colored. | Medium |
| **CLR-8** | **Add total row to stock reports** | Show totals at the bottom of the stock overview DataTable: total qty, total value. Use DataTable footerCallback. | Medium |

### 9.3 Workflow Improvements

| # | Improvement | Rationale | Priority |
|---|---|---|---|
| **WF-1** | **Batch approve/reject** | Allow selecting multiple pending returns/damages and approving/rejecting all at once. Supervisors processing 20+ items per day need this. | Medium |
| **WF-2** | **Auto-restock on approve for good-condition returns** | Currently approve and restock are separate steps (approve → then processRefund). Merge into one step for "good" condition: approve = approve + restock + refund. | High |
| **WF-3** | **Print damage report** | Add a print button that generates a formatted PDF/printable page for the damage report — useful for physical audits and sign-offs. | Medium |
| **WF-4** | **Return deadline indicator** | Show "X days remaining" on each dispensed item in the search results. Items past the 30-day return window should be grayed out or hidden. | Medium |
| **WF-5** | **Damage photo upload** | Allow attaching a photo of the damaged item. Useful for insurance claims and audit evidence. | Low |
| **WF-6** | **Notifications for pending approvals** | Send in-app notification (and optionally email) to users with `pharmacy.*.approve` permission when a new return or damage report is submitted. | Medium |

### 9.4 Data Quality Improvements

| # | Improvement | Rationale | Priority |
|---|---|---|---|
| **DQ-1** | **Prevent duplicate returns** | Check if a return already exists for the same `product_request_id`. Currently nothing prevents submitting multiple returns for the same dispensed item. | High |
| **DQ-2** | **Enforce return qty ≤ remaining qty** | If a partial return was already made, the max qty should be `original_qty - sum(previous_returns)`, not just `original_qty`. | High |
| **DQ-3** | **Validate damage qty ≤ actual store stock** | Controller checks stock but the check is at create time — by approval time, stock may have changed. Re-check at approval and show current available qty in the confirmation dialog. | Medium |

---

## 10. IMPLEMENTATION PHASES — STATUS

| Phase | Description | Status |
|---|---|---|
| **Phase 1** | Database & Models (migrations, PharmacyReturn, PharmacyDamage) | ✅ Complete |
| **Phase 2** | Controllers (Returns, Damages, Reports — all methods) | ✅ Complete |
| **Phase 3** | Observers (PharmacyReturnObserver, PharmacyDamageObserver + registration) | ✅ Complete |
| **Phase 4** | Routes (35 routes) + Permissions (13 permissions seeded) | ✅ Complete |
| **Phase 5** | UI — Workbench partials + CSS + JS integration | ✅ Complete |
| **Phase 6** | QA — Bug fixes (field names, model refs, column alignment) | 🔄 In Progress |
| **Phase 7** | Bug Fixes — Critical bugs from gap analysis (B1–B7, S1) | ❌ Not Started |
| **Phase 8** | UI/UX Improvements (priority-ordered from Section 9) | ❌ Not Started |
| **Phase 9** | Testing & Documentation | ❌ Not Started |

---

## 11. PRIORITY FIX ORDER (Phase 7)

Fix order based on impact — highest first:

1. **B1** — Returns DataTable actions (onclick → class+data-id) — *buttons completely broken*
2. **B2+B3** — Add `addIndexColumn()` to both DataTables — *# column empty*
3. **B4** — Returns reject parameter name mismatch — *reject always fails*
4. **B5** — Remove or add `wrong_item` in return_condition — *form 422 error*
5. **B6+B7** — Fix damages/returns stat card ID mismatches — *stats don't display*
6. **S1** — Add permission middleware to controllers — *security gap*
7. **L1** — Observer store-level stock deduction — *stock data inconsistency*

---

## 12. COLUMN NAME REFERENCE (Critical for Queries)

These are the actual database column names — using wrong names causes SQL errors.

| Table | Column | ❌ Wrong Name Often Used |
|---|---|---|
| `products` | `product_name` | ~~name~~ |
| `stores` | `store_name` | ~~name~~ |
| `patients` | `file_no` | ~~file_number~~ |
| `patients` | `user_id` → `users.firstname` | ~~patients.firstname~~ |
| `patients` | `user_id` → `users.surname` | ~~patients.lastname~~ |
| `users` | `getNameAttribute()` = `surname firstname othername` | ~~patients.fullname~~ |
| `store_stocks` | `current_quantity` | ~~quantity_available~~ |
| `stock_batches` | `current_qty` | ~~quantity_available~~ |
| `stock_batches` | `cost_price` | ~~unit_cost~~ |
| `prices` | `pr_buy_price` | ~~buy_price~~ / ~~unit_cost~~ |
| `hmos` | `name` | *(this one is correct)* |

**Patient name access pattern:** `$patient->user->name` (via User model's `getNameAttribute` accessor)

---

## 13. BUSINESS RULES

### Returns Policy
1. Only dispensed items (status = 3) can be returned
2. Returns should be within 30 days of dispense (not yet enforced)
3. Partial returns allowed (qty_returned ≤ original_qty)
4. Refund amount is proportional to qty returned
5. Items in "good" condition can be restocked
6. Damaged/expired items cannot be restocked
7. HMO returns split refund between patient and HMO

### Damage Reporting
1. Any pharmacist or store manager can report damage
2. Supervisor approval required
3. Stock automatically deducted upon approval (via observer)
4. Journal entry created upon approval (via observer)
5. Different GL accounts for different damage types (5030/5040/5050)

### Stock Reports
1. Real-time data from `store_stocks` + `stock_batches` + `prices`
2. Valuation uses product buy price from `prices.pr_buy_price`
3. Low stock threshold from `store_stocks.reorder_level`
4. Export to CSV with same filters as on-screen report

---

## 14. GL ACCOUNT MAPPING

| Code | Account Name | Used By | Debit/Credit |
|---|---|---|---|
| 1010 | Cash | Return refunds | CR |
| 1020 | Bank | Return refunds (alt) | CR |
| 1110 | AR - HMO | HMO return reversals | CR |
| 1300 | Inventory - Pharmacy | Returns (restock), Damages (write-off) | DR / CR |
| 5030 | Damaged Goods Write-off | Damage: broken/contaminated/spoiled/other | DR |
| 5040 | Expired Stock Write-off | Damage: expired | DR |
| 5050 | Theft/Shrinkage | Damage: theft | DR |
| 5060 | Loss on Returns | Returns: non-restockable (damaged/expired condition) | DR |
