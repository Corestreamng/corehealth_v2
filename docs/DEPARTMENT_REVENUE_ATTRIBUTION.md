# CoreHealth v2 — Global Department-Level Revenue Attribution & Dynamic Workbench Filters

**Date**: 2026-10-25  
**Git Release Tag**: `v2.6.0.54`  
**Status**: Completed & Verified  

---

## 1. Executive Summary

In healthcare operations, hospital revenue is frequently analyzed along two distinct axes:
1. **Clinical Service/Product Classification** (e.g. Laboratory, Imaging, Pharmacy, Consultations, Nursing, Procedures).
2. **Departmental & Modality Cost Centers** (e.g. within Imaging: Ultrasound, Digital X-Ray, CT Scan, MRI, Mammography; within Laboratory: Haematology, Chemical Pathology, Microbiology, Parasitology, Blood Bank).

Prior to this implementation, CoreHealth v2 categorized income predominantly by `service_category_id` and `product_category_id`. While categories effectively control clinical workbench visibility and workflows, they did not allow granular sub-department revenue attribution (for instance, all imaging studies flowed into a single "Radiology" category, concealing individual modality performance).

This enhancement introduces **Global Department-Level Revenue Attribution** across CoreHealth v2:
- **Dual-Tier Effective Department Resolution**: Services and products can be explicitly attached to specific hospital departments, with automatic fallback inheritance from their parent category.
- **Auto-Prepopulation in Admin CRUD**: When creating or editing a service or product, selecting a category auto-selects that category's assigned department while still permitting granular manual overrides.
- **Observer-Driven Stamping**: `ProductOrServiceRequestObserver` and `PaymentObserver` automatically stamp `department_id` into all billing requests and corresponding debit/credit `journal_entry_lines`.
- **Dynamic Modality and Bench Filter Pills**: Imaging and Laboratory workbenches query active departments dynamically from live catalog data and departmental roles—eliminating hardcoded modality lists.
- **Omni-Channel Reporting & Auditing**: Department filters, dynamic KPI summaries, and badges are integrated into the *My Transactions* cockpit modal, *Manage Transactions* datatables, printable cashier summaries, *OpsAudit* billing workbenches, and Accounting General Ledger reports.
- **Retroactive Data Backfill Engine**: An artisan command (`php artisan corehealth:backfill-departments`) provides zero-downtime retroactive mapping for historical requests and ledger lines with progress bars and `--dry-run` safety.

---

## 2. Database Schema & Architecture

### A. Dedicated Migration
The database migration `database/migrations/2026_10_25_000001_add_department_id_to_categories_services_products.php` adds nullable, indexed `department_id` foreign keys:

```php
// service_categories table
$table->foreignId('department_id')->nullable()->after('category_name')->constrained('departments')->nullOnDelete();

// product_categories table
$table->foreignId('department_id')->nullable()->after('category_name')->constrained('departments')->nullOnDelete();

// services table
$table->foreignId('department_id')->nullable()->after('category_id')->constrained('departments')->nullOnDelete();

// products table
$table->foreignId('department_id')->nullable()->after('category_id')->constrained('departments')->nullOnDelete();

// product_or_service_requests table
$table->foreignId('department_id')->nullable()->after('service_category_id')->constrained('departments')->nullOnDelete();
```

*Note*: `journal_entry_lines.department_id` already existed in the core schema and `$fillable` array; this enhancement populates and leverages it across all automated journal entry pipelines.

### B. Dual-Tier Effective Resolution Hierarchy
Both `Service` and `Product` models implement the `getEffectiveDepartmentId()` method:

```
[Service / Product department_id (Item Override)]
                     │
                     ▼ (if null)
[ServiceCategory / ProductCategory department_id (Category Default)]
                     │
                     ▼ (if null)
             [null (General / Unassigned)]
```

This guarantees backward compatibility: existing services automatically inherit their category's department when the category is mapped, while specialized items (e.g. Ultrasound vs MRI under Category 6 "Imaging") can be overridden to individual departments.

---

## 3. Observer Pipeline & Double-Entry Accounting Stamping

### A. ProductOrServiceRequest Observer
Located at `app/Observers/Accounting/ProductOrServiceRequestObserver.php`:
- During `creating($posr)`, if `$posr->department_id` is null, the observer inspects the related `Service` or `Product` and resolves the effective department ID via `getEffectiveDepartmentId()`.
- During HMO claim revenue journal entry generation (`recordHmoRevenue()`), `$posr->department_id` is stamped onto all credit revenue lines (`department_id => $request->department_id`).

### B. Payment Observer
Located at `app/Observers/Accounting/PaymentObserver.php`:
- `extractPaymentMetadata(Payment $payment)` resolves the effective `department_id` by checking paid `ProductOrServiceRequest` line items.
- All generated double-entry journal lines (debiting Cash/Bank/Wallet and crediting Revenue accounts) are stamped with `department_id`.

---

## 4. Clinical Workbenches: Dynamic Modality & Bench Filters

To avoid inflexible hardcoded filters, workbench filters query live database state:

### A. Imaging Workbench (`ImagingWorkbenchController` & `imaging-results.js`)
- `ImagingWorkbenchController::getImagingDepartments()` dynamically discovers:
  1. Departments explicitly assigned to services within the Imaging category (`investigation_imaging_category_id` or category 6).
  2. The default department linked to the Imaging category.
  3. Active departments whose names or short codes match `%radiolog%`, `%imaging%`, `%scan%`, `%ultrasound%`, `%x-ray%`, `%mri%`, `%ct%`, or `RAD`.
- Passed to `admin.imaging.workbench` as `$imagingDepartments`.
- Rendered as interactive pill buttons (`#imaging-modality-filter-bar`). Clicking a modality updates the DataTables parameter `department_id` and refreshes the queue without reloading the page.

### B. Laboratory Workbench (`LabWorkbenchController` & `lab-results.js`)
- `LabWorkbenchController::getLabDepartments()` dynamically discovers:
  1. Departments assigned to laboratory investigation services (`investigation_category_id` or category 2).
  2. The default department linked to the Lab category.
  3. Active departments whose names match `%laborat%`, `%haematolog%`, `%patholog%`, `%microbiolog%`, `%parasitolog%`, `%blood bank%`, `%chemistry%`, or `LAB`.
- Passed to `admin.lab.workbench` as `$labDepartments`.
- Rendered as interactive bench pills (`#lab-bench-filter-bar`) that trigger DataTables re-filtering.

---

## 5. Billing, My Transactions & OpsAudit Enhancements

### A. My Transactions Cockpit (`_my_transactions_modal.blade.php`, `BillingWorkbenchController.php`)
- **Department Dropdown Filter**: Added to both the *My Transactions* tab and *Manage Transactions* tab.
- **Department Revenue KPI Strip**: Computes aggregated departmental revenue (`$departmentKpis`) in real time, rendering clickable KPI cards (e.g. `[ Ultrasound: ₦45,000 ]`, `[ Digital X-Ray: ₦30,000 ]`).
- **Interactive Pill Toggling**: Clicking a department KPI card immediately filters the transaction list and items table to that specific department.
- **Department Badge**: Itemized table rows display an informative pill badge indicating the attributed department.

### B. Printable Cashier Summary (`my_transactions_print.blade.php`)
- The cashier printout includes a dedicated **Department Revenue Attribution Summary** table displaying the breakdown of collections attributed to each hospital department alongside tender methods and service categories.

### C. OpsAudit Billing Workbench (`OpsAuditBaseController.php`, `ops_audit/billing.blade.php`)
- `OpsAuditBaseController::applyItemFilters()` accepts `department_id`, filtering line items via `posr.department_id` or through service/product department relationships.
- Added Department selection dropdown in the payments audit toolbar.

### D. Accounting General Ledger (`ReportService.php`, `ReportController.php`, `general-ledger.blade.php`)
- General Ledger reports accept optional `?department_id=` parameter.
- Transaction line items display `department_name` alongside account code and descriptions.

---

## 6. Admin CRUD Configuration Pages

Dedicated admin CRUD pages for:
1. **Service Categories**: `resources/views/admin/serviceCategory/create.blade.php` and `edit.blade.php`.
2. **Product Categories**: `resources/views/admin/productCategory/create.blade.php` and `edit.blade.php`.
3. **Services**: `resources/views/admin/service/create.blade.php` and `edit.blade.php`.
4. **Products**: `resources/views/admin/product/create.blade.php` and `edit.blade.php`.

### Auto-Prepopulation Mechanism
When creating or editing a Service or Product:
- Each category `<option>` carries a data attribute: `data-department-id="{{ $cat->department_id }}"`.
- When the user selects a Category, JavaScript automatically selects the corresponding Department in the Department dropdown if no manual override was previously set, while still providing complete freedom to change it.

---

## 7. Zero-Downtime Data Backfill Engine

An Artisan command is provided for historical data migration:
```bash
# Preview changes without modifying database
php artisan corehealth:backfill-departments --dry-run

# Run full migration in chunks of 500
php artisan corehealth:backfill-departments --chunk=500
```
The command:
1. Pre-resolves effective department IDs for all active services and products.
2. Iterates across unassigned `product_or_service_requests` where `department_id IS NULL`, setting them to the effective item department.
3. Iterates across unassigned `journal_entry_lines` linked to payments, populating `department_id`.

---

## 8. Verification & Test Suite

The test suite is located in `tests/Feature/Billing/DepartmentRevenueAttributionTest.php`:

| Test Name | Coverage | Status |
|:---|:---|:---:|
| `test_categories_and_items_can_be_assigned_to_departments_with_effective_fallback` | Verifies direct department assignment on categories and items, ensuring `getEffectiveDepartmentId()` falls back correctly. | **PASS** |
| `test_product_or_service_request_stamps_department_id_via_observer` | Validates that creating a `ProductOrServiceRequest` automatically stamps `department_id` via `ProductOrServiceRequestObserver`. | **PASS** |
| `test_payment_observer_stamps_department_id_on_journal_entry_lines` | Confirms double-entry journal lines generated by `PaymentObserver` inherit `department_id`. | **PASS** |
| `test_my_transactions_filters_by_department_and_returns_department_kpis` | Verifies `getMyTransactions()` aggregates `$departmentKpis`, returns `all_departments`, and filters correctly. | **PASS** |
| `test_manage_transactions_filters_by_department` | Tests Yajra DataTable query filtering by `department_id` in Manage Transactions. | **PASS** |
| `test_imaging_and_lab_dynamic_department_helpers` | Asserts `ImagingWorkbenchController::getImagingDepartments()` and `LabWorkbenchController::getLabDepartments()` return active departments without hardcoding. | **PASS** |

### Code Quality Verification
- **PHPUnit**: 69 tests, 280 assertions, 0 errors (`vendor/bin/phpunit tests/Feature/Billing/`).
- **Performance & DataTables Suite**: 4 tests, 34 assertions (`vendor/bin/phpunit tests/Feature/Billing/MyTransactionsPerformanceTest.php`).
- **PSR-12 Compliance**: `composer lint` passed with 0 errors across 1,311 files.
- **JavaScript Syntax**: Verified with `node --check` across `public/js/billing-my-transactions.js`, `imaging-results.js`, and `lab-results.js`.

---

## 9. Transactions DataTable Row Details Breakdown & Performance

The Transactions Console (`#my-transactions-table`) utilizes server-side Yajra DataTables with optimized memoization and SQL aggregation:
- **Server-Side Pagination & Search**: Fast response time on queries across multi-month ranges with 100,000+ records.
- **Row Details Accordion (`.btn-toggle-items-breakdown`)**:
  - Each itemized row includes an accordion container (`.items-summary-container`) with `.dt-items-box` and toggle button.
  - Event handler stops propagation (`e.stopPropagation()`) to avoid DataTables row-select or responsive conflicts.
  - Hierarchical resolution prioritizes `.closest('.items-summary-container, td, .dtr-data')`, ensuring child breakdown boxes expand reliably in standard table views as well as DataTables Responsive child rows (`.dtr-details`).
  - Chevron icon flips smoothly between `mdi-chevron-down` and `mdi-chevron-up`.

---

## 10. Multi-Modal Stacking & Layering Governance

When cashiers or billers click "Print Receipt" (`.btn-print-tx-receipt`) from within the Transactions Console modal (`#myTransactionsModal`):
- **Z-Index Hierarchy**:
  - Primary Level (`z-index: 1040`): Backdrop for `#myTransactionsModal`.
  - Second Level (`z-index: 1050`): Transactions Console modal (`#myTransactionsModal`).
  - Third Level (`z-index: 1065`): Second backdrop for `#receiptPreviewModal` (`.receipt-modal-backdrop` / `body.has-receipt-modal-open .modal-backdrop:last-of-type`), dimming `#myTransactionsModal`.
  - Fourth Level (`z-index: 1070` modal, `z-index: 1071` `.modal-dialog`): Receipt Preview Modal (`#receiptPreviewModal`), appearing crisp and prominent in the foreground.
- **Scroll & Focus Retention**:
  - Upon closing `#receiptPreviewModal`, `hidden.bs.modal` checks for active background modals and re-applies `.modal-open` to `<body>`, restoring smooth scrolling and complete interactivity to `#myTransactionsModal`.

---

## 11. Walk-in Sales Benefiting Department, Cart Telemetry & Receipt Preview Governance

### A. Custom / Free-Form Benefiting Department Selection
- **Intuitive UI Dropdown**: In `resources/views/admin/shared/_walkin_sales_tab.blade.php`, the Custom / Free-Form tab incorporates a prominent Benefiting Department selector (`#walkin-freeform-dept`) populated from active departments (`Department::where('is_active', true)->orderBy('name')->get()`).
- **Validation & Clinical Guidance**: Requires selection prior to cart addition with helper note (*"Revenue and clinical fulfillment will be attributed to this department"*).
- **Explicit Persistence**: Stamped on creation into `ProductOrServiceRequest.department_id`, preserving explicit cashier intent over default category fallbacks.

### B. Benefiting Department Visibility in Cart & Recent (24h)
- **Walk-in Cart**: `#walkin-cart-table` header updated to `Item & Department`; items render with department pill badges (`<span class="badge bg-light text-secondary border"><i class="mdi mdi-office-building"></i> {Dept}</span>`).
- **Recent 24h List**: `recent-requests-container` renders benefiting department pill badge alongside clinical modality badges and displays `Dept: <strong>...</strong>` in the item metadata strip.
- **Automatic Cart Tab Switching**: Whenever a new catalog item or custom free-form item is added (`WalkinSales.addItem`, `WalkinSales.handleFreeFormSubmit`), the UI automatically switches focus back from the Recent (24h) tab to `#walkin-cart-pane` (`WalkinSales.switchToCartTab`), ensuring seamless real-time visual confirmation across Billing and Reception workbenches.

### C. Canonical Billing Receipt Reprint Modal Governance
- **Single Source of Truth**: The canonical Billing Receipt Reprint Modal (`#receiptPreviewModal` with `#modal-receipt-a4`, `#modal-receipt-thermal`, format tabs, and print triggers) is maintained without shadow duplication.
- **Resolution of Infinite Loading Spinner**: Removed redundant dummy `#receiptPreviewModal` from `resources/views/admin/shared/_my_transactions_modal.blade.php`, ensuring payment confirmations and receipt reprints in Billing and Reception (walk-in sales & consultations) immediately render live A4 and Thermal receipt previews without spinning.

---

## 12. Standalone Facility Department Streamlining & Catalog Backfill Script

- **Target Facility**: Standalone script located at `scripts/streamline_skane_departments.php` configured for target facility databases (e.g. `_corehealth_db_v2_skane`).
- **Department Streamlining**: Consolidates active departments down to 11 active cost centers: Ultrasound (code `USS`), X-Ray (code `XRY`), Consultation / Clinic (code `CONS`), Health Records (code `HRD`), Laboratory (code `LAB`), Nursing (code `NURS`), Pharmacy (code `PHAR`), Administration (code `ADMIN`), Finance & Accounts (code `FIN`), Billing (code `BILL`), and Store & Inventory (code `STOR`). Deactivates 28 non-applicable departments (`is_active = 0`).
- **Complete Catalog Mapping**:
  - Maps 100% of imported service catalog items (Ultrasound: 16 items, X-ray: 16 items, Laboratory: 89 items, Consultation: 1 item, Health Records: 1 item).
  - Maps 100% of imported pharmacy items (99 products across 18 product categories).
  - Maps internal store locations (Pharmacy Store to Pharmacy, Central Store to Store & Inventory).
- **Zero-Downtime Idempotency**: Safe to execute repeatedly with full transaction rollback on error and `--dry-run` inspection support.

---

## 13. Universal Workbench Shell Topbar Toggle Search & Event Bubbling Governance

- **Root Cause**: On the Billing Workbench topbar ribbon, `#btn-toggle-search` was registered via a delegated document click listener in `workbench-shell.js` while concurrently bound with a direct click listener in `billing-core.js`. Unstopped event bubbling caused double execution (`toggleLeftPanel` collapse immediately followed by expand in under 1ms), causing the button to appear completely dead/unresponsive.
- **Architectural Solution**:
  1. **Debounce / Throttle Guard in WorkbenchShell**: Added 300ms execution lock (`Date.now() - this.state._lastToggle < 300`) inside `WorkbenchShell.toggleLeftPanel()`, ensuring only a single toggle occurs regardless of event bubbling or duplicate click triggers.
  2. **Idempotent Shell Event Binding**: `WorkbenchShell.bindEvents()` now enforces `this.state.eventsBound` guard and unbinds prior handlers (`$(document).off('click', ...).on('click', ...)`).
  3. **Event Stop-Propagation**: `billing-core.js` calls `e.stopPropagation()` and `.off('click')` on `#btn-toggle-search`, `#btn-back-to-search`, and `#btn-view-work-pane`.
  4. **Scoped Fallbacks**: Defined `hideAllViews` and `clearPatient` in `billing-core.js` and exposed on `window` to prevent `ReferenceError` during patient clear/reset workflows.


