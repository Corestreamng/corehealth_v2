# Universal Workbench Cockpit Scaling Architecture

**Date**: 2026-10-22  
**Git Release Tag**: `v2.6.0.53`  
**Status**: Completed & Production-Verified  
**Branch**: `feat/workbench-cockpit-scaling`

---

## 1. Executive Summary & Problem Context

In CoreHealth v2, clinical workbenches (Reception, Doctor, Pharmacy, Nursing, Lab, Billing, Morgue) were previously styled using standard desktop layouts with generous vertical spacing, oversized inputs (48px height), tall queue rows (~55px each), and redundant page framing. On standard laptop and hospital workstation displays (primarily 1366x768 and 1280x720 resolutions):

1. **Dead Space Overhead**:
   - The master layout rendered a 50px `<section class="content-header">` for breadcrumbs (often showing stray `/` dividers on single-tier workbench views).
   - The marketing copyright footer consumed an additional 50px at the bottom of the screen.
   - `.content-wrapper` applied `80px 30px 30px 30px` padding, robbing another 110px of vertical space.
   - Net loss: Over 210px (nearly 30% of a 768px screen height) was consumed by empty page chrome before rendering any clinical tools.
2. **Double Scrollbars**:
   - The outer browser window maintained an outer scrollbar while internal panels also scrolled, resulting in awkward and disjointed scrolling.
3. **Queue Cutoff**:
   - Only 5 to 6 doctor queue items fit vertically without scrolling the entire workbench.
4. **Disparity with Home Cockpit**:
   - The `/home` operational cockpit introduced a space-maximizing, zero-scroll viewport design (`dashboard-cockpit.css`). Clinical workbenches needed to match this operational standard while retaining high visual aesthetics, accessibility, and hospital branding color injection.

This architecture introduces the **Universal Workbench Cockpit Scaling Framework**, tested in fullness on the **Reception Workbench**, with a standardized drop-in stylesheet (`public/css/workbench-cockpit-scaling.css`) and layout integration hooks.

---

## 2. Core Architectural Pillars

```
+-----------------------------------------------------------------------------------+
| Top Navigation Bar (Fixed 60px)                                                   |
+-----------------------------------------------------------------------------------+
| .content-wrapper (Locked to Viewport: padding: 68px 10px 6px 10px; Zero-Scroll)  |
|                                                                                   |
|  +---------------------------+  +----------------------------------------------+  |
|  | .left-panel (270px fixed) |  | .main-workspace (flex: 1 1 auto; zero scroll) |  |
|  |                           |  |                                              |  |
|  | - Patient Search Widget   |  | - Workspace Navbar Ribbon (34px)             |  |
|  |   (height 30px; 36px pad) |  | - Patient Header (compact 48-64px)           |  |
|  |                           |  | - Workspace Tabs (height 34px)               |  |
|  | - Doctor Queue Widget     |  | - Tab Content / Queue Views / Tables         |  |
|  |   (min-height 28px items; |  |   (flex: 1 1 auto; internal micro-scrollbars)|  |
|  |   100% visible on 768px)  |  |                                              |  |
|  |                           |  |                                              |  |
|  | - Quick Action Buttons    |  |                                              |  |
|  |   (compact 28px pills)    |  |                                              |  |
|  +---------------------------+  +----------------------------------------------+  |
|                                                                                   |
+-----------------------------------------------------------------------------------+
| Footer & Content Header OMITTED in HTML when @section('is_workbench', true)       |
+-----------------------------------------------------------------------------------+
```

### Pillar 1: Clean Layout Breadcrumb & Footer Reclaim
In `resources/views/admin/layouts/app.blade.php`:
- The `<body>` tag accepts a dynamic `@yield('body_class')`.
- The `<section class="content-header">` (breadcrumbs and heading) and marketing `<footer>` are conditionally omitted from HTML when `@section('is_workbench', true)` is declared:
  ```blade
  @if(!View::hasSection('is_workbench'))
      <section class="content-header">...</section>
      <div class="cloud"></div>
      @yield('content')
      @include('admin.partials.footer')
  @else
      @if ($errors->any())
          <div class="workbench-alerts-strip px-3 pt-2">...</div>
      @endif
      <div class="workbench-alerts-strip">
          @include('admin.partials.notification')
      </div>
      @yield('content')
  @endif
  ```
- **Zero Notification Loss**: System flash alerts and error messages are rendered in a compact `.workbench-alerts-strip` directly at the top of the workbench without consuming layout space when absent.

### Pillar 2: Universal Viewport Locking & Zero-Scroll Bounding
In `public/css/workbench-cockpit-scaling.css`:
- `html, body.is-workbench, body.has-workbench`:
  `height: 100vh !important; max-height: 100vh !important; overflow: hidden !important;`
- `.page-body-wrapper`:
  Locked to `margin-left: 260px !important; width: calc(100% - 260px) !important;` (aligning with `.ch-sidebar` width 260px). When sidebar is minimized (`.sidebar-icon-only`), transitions to `margin-left: 72px !important; width: calc(100% - 72px) !important;`.
- `.main-panel`:
  Full width (`100% !important;`), locked to `100vh`, flexbox column.
- `.content-wrapper`:
  Locked to `padding: 68px 10px 6px 10px !important; flex: 1 1 auto; overflow: hidden;` providing exact clearance for the fixed 60px top navbar and maximum usable screen space.

### Pillar 3: High-Density Universal Scaling Tokens
All controls across workbenches conform to unified cockpit tokens:
- **Inputs & Selects**:
  `height: 30px !important; min-height: 30px !important; padding: 2px 8px !important; font-size: 0.8125rem !important; border-radius: 5px !important;`
- **Buttons**:
  `padding: 3px 10px !important; font-size: 0.78rem !important; border-radius: 5px !important;`
  `.btn-sm`: `height: 24-26px !important; font-size: 0.72rem !important;`
  `.btn-action`, `.quick-action-btn`: `height: 28-30px !important; font-size: 0.76-0.78rem !important;`
- **DataTables**:
  Headers: `padding: 5px 8px !important; font-size: 0.74rem !important; text-transform: uppercase !important;`
  Rows: `padding: 4px 8px !important; font-size: 0.79rem !important;`
  Search/Filter input: `height: 26px !important; font-size: 0.76rem !important;`
  Pagination pills: `height: 24px !important; font-size: 0.74rem !important;`
- **Modals**:
  Headers: `padding: 8px 14px !important; min-height: 38px !important;`
  Body: `padding: 12px 14px !important; max-height: calc(85vh - 90px) !important; overflow-y: auto !important;`
- **Micro-Scrollbars**:
  4px width (`rgba(148, 163, 184, 0.45)` thumb with transparent track), applied universally via `-webkit-scrollbar` and Firefox `scrollbar-width: thin`.

### Pillar 4: Low-Resolution Responsive Scaler (1366x768 & Lower)
A dedicated media query (`@media (max-width: 1366.98px)`) tightens typography to `0.78rem`, input heights to `28px`, table cell paddings to `3px 6px`, and ensures 100% of departmental queue items and triage controls fit on low-res screens without window scrollbars.

---

## 3. Reception Workbench Implementation Details

### View Declarations (`resources/views/admin/reception/workbench.blade.php`)
```blade
@extends('admin.layouts.app')

@section('title', 'Reception Workbench')
@section('is_workbench', true)
@section('body_class', 'is-workbench has-workbench')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('css/workbench-cockpit-scaling.css') }}">
<link rel="stylesheet" href="{{ versioned_asset('css/reception-workbench.css') }}">
...
```

### Component Tuning (`public/css/reception-workbench.css`)
1. **Container**:
   `.reception-workbench-container`: `height: 100% !important; max-height: 100% !important; overflow: hidden; gap: 6px; border-radius: 8px; border: 1px solid #e2e8f0; width: 100% !important;`
2. **Left Panel**:
   - Width: `270px; flex: 0 0 270px;`
   - Patient Search Widget: `padding: 6px 8px; height: 30px; padding-left: 36px;` with magnifying glass aligned at `left: 10px;`.
   - Doctor Queue Widget: Items sized at `min-height: 28px; padding: 3px 8px; font-size: 0.76rem;`. Count badges sized at `height: 19px; min-width: 22px; font-size: 0.75rem;`. All 8 queue filters (Emergency, Waiting, Vitals, Consultation, Admitted, Appointments, Referrals, HMO Pending) fit comfortably without scrolling the panel.
   - Quick Actions: Compact 28px buttons with 16px icons.
3. **Main Workspace**:
   - Navbar Ribbon: `height: 34px;` with 26px compact utility buttons.
   - Patient Header: Scaled from 160px down to `padding: 6px 12px;` with `1.15rem` patient name, single-row metadata chips, and compact account balance badge.
   - Workspace Tabs: Fixed `height: 34px;` with `0.78rem` active indicator bar.
   - Tab Content: `flex: 1 1 auto; overflow-y: auto;` with micro-scrollbars.
   - Queue Datatable View: Full width table rendering all 10 columns (`#`, `Patient`, `File No`, `HMO`, `Clinic`, `Doctor`, `Service`, `Status`, `Time`, `Action`) with zero horizontal overflow.

---

## 4. Browser Verification & Measurements

Tests conducted on Google Chrome via browser subagent across two target display profiles:

| Metric / Checkpoint | Low-Res Laptop (1366 x 768) | Desktop Display (1920 x 953) | Status |
|---|---|---|---|
| Window `scrollY` | `0px` | `0px` | **PASSED** |
| `scrollHeight <= innerHeight` | `768px <= 768px` | `953px <= 953px` | **PASSED** |
| Outer Window Scrollbars | None (Hidden) | None (Hidden) | **PASSED** |
| Breadcrumb (`.content-header`) | Omitted from DOM | Omitted from DOM | **PASSED** |
| Marketing Footer (`.footer`) | Omitted from DOM | Omitted from DOM | **PASSED** |
| Left Panel Queue Visibility | All 8 queue items visible | All 8 queue items visible | **PASSED** |
| Patient Search Alignment | Icon left: 10px, text pad: 36px | Icon left: 10px, text pad: 36px | **PASSED** |
| Patient Header (Active State) | Compact banner (~56px) | Compact banner (~56px) | **PASSED** |
| Profile Cards (3 cards) | Fits 100% on screen | Fits 100% on screen | **PASSED** |
| Queue Datatable (10 cols) | Zero horizontal overflow | Zero horizontal overflow | **PASSED** |
| Right Edge Margin | Exact 10px padding | Exact 10px padding | **PASSED** |

### Verified Artifact Screenshots
- `reception_workbench_1366_1792168742354.png` (Empty state at 1366x768)
- `patient_workspace_1366_1792168764775.png` (Loaded patient at 1366x768)
- `queue_modal_1366_1792168881973.png` (Doctor queue datatable at 1366x768)
- `reception_workbench_1920_1792168895318.png` (Full desktop view at 1920x953)

---

## 5. Automated Test Suite Results

- `make lint` / `composer lint`: **0 errors** across 1,295 files.
- `vendor/bin/phpunit tests/Feature/Referral/SpecialistReferralTest.php`: **11 tests, 36 assertions — 100% passed**.
- `vendor/bin/phpunit tests/Feature/Reception/ReceptionQueueTest.php`: **6 tests, 9 assertions — 100% passed**.
- `vendor/bin/phpunit tests/Feature/PatientRegistrationTest.php`: **12 tests, 69 assertions — 100% passed**.

---

## 6. Reception Workbench Referral Actions & Isolated Printing Architecture

During testing of the Reception Workbench within the zero-scroll cockpit shell, referral lifecycle actions and printing were restored and verified:

1. **Restored Referral & Appointment Modals (`partials/_modals.blade.php`)**:
   - `#bookReferralModal`: Internal referral booking modal into target clinic/doctor queues.
   - `#referOutModal`: External referral status handover modal with documented action notes.
   - `#declineReferralModal`: Referral decline modal with mandatory clinical rationale.
   - `#referralDetailModal`: Complete referral history and clinical note inspector.
   - `#rescheduleAppointmentModal`, `#reassignDoctorModal`, `#appointmentChainModal`.

2. **Isolated Print Architecture (`public/js/reception-registration.js`)**:
   - **Problem**: In zero-scroll workbenches, invoking `window.print()` directly on the main window causes `@media print` styles and container viewport locking (`height: 100vh; overflow: hidden`) to clip or hide referral letters, and freezes or hides open modal backdrops.
   - **Solution**: Implemented `executeReferralPrint(htmlContent, title)`. It dynamically constructs an isolated, off-screen `<iframe>` with standalone A4 letterhead CSS (`@page { size: A4 portrait; margin: 15mm; }`), writes the referral document, and triggers `iframe.contentWindow.print()` with a popup window fallback.
   - **Internal vs External Letter Parity**:
     - **External Referrals**: Renders "Specialist Referral Letter", external facility & doctor details, diagnosis, clinical summary, and signature line.
     - **Internal Referrals**: Renders "Specialist Referral Slip / Order", target clinic & doctor details within the facility, consultation purpose, and referring doctor signature.
   - **Table & Modal Print Triggers**: Both internal and external referrals now display dedicated print triggers in the queue datatable (`.btn-print-referral`) and detail modal (`#btn-print-referral-letter`).

---

## 9. Phased Integration Blueprint: Scaling Pharmacy, Laboratory, and Imaging Workbenches

### 1. Architectural Objectives & Non-Breaking Principles
Following the verified deployment of Universal Workbench Cockpit Scaling on the **Reception** and **Billing** workbenches, this blueprint details the exact strategy to scale the remaining primary clinical service workbenches:
1. **Pharmacy Workbench** (`admin/pharmacy/workbench`)
2. **Laboratory Workbench** (`admin/lab/workbench`)
3. **Imaging / Radiology Workbench** (`admin/imaging/workbench`)

The foundational mandates for this rollout:
- **Zero-Scroll Viewport Locking**: Ensure the entire workbench fits within `height: 100vh; overflow: hidden;` on low-resolution 1366x768 workstation baseline displays, eliminating outer window scrollbars (`scrollY == 0`).
- **Internal Overflow Management**: Panel scrolling is restricted strictly to internal scroll panes using high-density 4px micro-scrollbars (`.dash-scrollable`).
- **100% Feature Parity & JS Invariance**: ZERO domain-specific JavaScript code or DOM event handlers may be broken or lost. All DOM element IDs, classes, data attributes, DataTables definitions, modal triggers, and AJAX route URLs must be preserved with exact character-for-character fidelity.
- **Architectural Cleanup**: Remediate legacy file structure anomalies (e.g., Lab workbench HTML mistakenly split across `_modals.blade.php`).

---

### 2. Module 1: Pharmacy Workbench Cockpit Scaling

#### A. Layout Geometry & Viewport Locking
1. **Blade Declarations (`resources/views/admin/pharmacy/workbench.blade.php`)**:
   - Add `@section('is_workbench', true)` and `@section('body_class', 'is-workbench has-workbench')`.
   - Include universal scaling stylesheet in `@push('styles')`:
     ```blade
     <link rel="stylesheet" href="{{ versioned_asset('css/workbench-cockpit-scaling.css') }}">
     <link rel="stylesheet" href="{{ versioned_asset('css/pharmacy-workbench.css') }}">
     ```
   - Omit `.content-header` and `.footer` automatically through layout condition in `admin.layouts.app`.
2. **Store Context Banner Integration**:
   - **Challenge**: The store context banner (`#store-context-badge` / `#store-context-banner`) is conditionally rendered above the flex container. If `.pharmacy-workbench-container` is styled as `height: 100%`, the banner would push the container down by ~34px, creating an outer window scrollbar.
   - **Solution**: Set `.pharmacy-workbench-container` to `flex: 1 1 auto; min-height: 0; height: auto; max-height: 100%; display: flex; overflow: hidden;`. This allows the banner to act as a `flex-shrink: 0` element and the container to automatically fill the remaining viewport height.

#### B. Component & Panel Density Scaling
1. **Left Panel (`.left-panel`)**:
   - Width locked to `270px; flex: 0 0 270px;` with smooth CSS transition on `#btn-toggle-search` collapse.
   - Patient Search Widget conforms to shared `height: 30px; font-size: 0.8125rem;` standards.
   - Prescription Queue Widget (`.queue-widget`): Scale all 6 queue filters (`emergency`, `all`, `unbilled`, `billed`, `hmo`, `freeform`) to `min-height: 28px; padding: 4px 8px; font-size: 0.78rem;` and badge to `height: 19px; min-width: 22px; font-size: 0.75rem;`.
   - Quick Actions (`.quick-actions`): Scale buttons to `min-height: 28px; font-size: 0.78rem; padding: 3px 8px;` so all 7 actions (Walk-in, My Transactions, Reports, Returns, Damages, Stock Reports, Summary) fit on screen without vertical overflow.
2. **Main Workspace (`.main-workspace`)**:
   - Navbar Ribbon (`.workspace-navbar`): Locked to `height: 36px; padding: 0 10px;`. Action buttons (`#btn-pharmacy-new-patient`, walk-in, emergency intake, contacts, price list, clinical context) scaled to `height: 30px; font-size: 0.8rem;`.
   - Patient Header Banner (`#patient-header`): Retain compact signature gradient (~56px). Support expandable biodata drawer (`.patient-details-expanded`) with `:not(.show)` collapsed state and smooth chevron flip.
   - Workspace Tabs (`.workspace-tabs`): Locked to `height: 34px;` for Checkout, New Request, History, Procedures.
3. **Prescription Checkout & Till Integration**:
   - The unified prescription container (`#pharmacy-presc-container`) renders subtabs (Awaiting Billing, Pending Payment/HMO, Dispense Ready).
   - DataTables (`#presc_billing_table`, `#presc_pending_table`, `#presc_dispense_table`) scale to compact rows (`padding: 4px 8px; font-size: 0.79rem;`).
   - Floating Till Cart (`#floating-cart`) and Dispense Summary Card (`#dispense-summary-card`): Scale z-index to float above tab content without occluding bottom action buttons.
4. **Overlay Panels (Full Screen Views)**:
   - `#queue-view`, `#pharmacy-reports-view`, `#pharmacy-returns-panel`, `#pharmacy-damages-panel`, `#pharmacy-stock-reports-panel` positioned absolute inside `.main-workspace` with `top: 0; left: 0; right: 0; bottom: 0; z-index: 50; display: flex; flex-direction: column;`. Header stays fixed; body content scrolls via 4px micro-scrollbars.

#### C. Non-Breaking JS Preservation Catalog
- All 11 event handlers in `public/js/pharmacy-core.js` for returns and damages must maintain exact IDs: `#qtyAdjustmentModal`, `#priceAdjustmentModal`, `#overrideStoreContextModal`, `#select-all-pending-returns`, `#btn-bulk-approve-returns`, etc.
- In `public/js/pharmacy-dispensing.js`: `loadPatient(patientId)`, `pharmBagClearAll()`, `pharmApplyTillState()`, `openCartReviewModal()`, `billPrescItems()`, `dispensePrescItems()` rely on exact DOM nodes `#cart-item-count`, `#cart-total-display`, `#dispense-count`, `#btn-manage-alerts`, `#patient-header-balance`.
- In `public/js/pharmacy-returns.js`: `loadPatientReceipts()`, `initializeHistoryDataTable(patientId)` must bind to `#receipts-table` and `#receipts-tbody` without interruption.

---

### 3. Module 2: Laboratory Workbench Cockpit Scaling

#### A. Structural Remediation & File Modularization
- **Defect Discovery**: In `resources/views/admin/lab/workbench.blade.php`, the file was prematurely cut at line 71 inside `<div class="quick-actions">`, while lines 1 to 538 of `resources/views/admin/lab/partials/_modals.blade.php` contained the remainder of the left panel, the entire main workspace, patient header, workspace tabs, pending subtabs, new request form, history, and procedures content.
- **Architectural Fix**:
  - Relocate lines 1 to 538 from `_modals.blade.php` back into `workbench.blade.php` so that `workbench.blade.php` owns the complete HTML DOM structure.
  - Keep `_modals.blade.php` dedicated solely to modal dialogs (`#sampleCollectionModal`, `#deleteReasonModal`, `#dismissReasonModal`, `#approvalReviewModal`, etc.).
  - Add `@section('is_workbench', true)` and `@section('body_class', 'is-workbench has-workbench')`.
  - Include `workbench-cockpit-scaling.css` in `@push('styles')`.

#### B. Component & Panel Density Scaling
1. **Left Panel (`.left-panel`)**:
   - Standard 270px width.
   - Pending Queue Widget (`.queue-widget`): Scale all 7 queue filters (`emergency`, `billing`, `sample`, `results`, `completed`, `freeform`, `approval`) to high-density items (`min-height: 28px; padding: 4px 8px; font-size: 0.78rem;`) with count badges (`height: 19px; min-width: 22px;`).
   - Quick Actions: Scale `#btn-new-request`, `#btn-view-reports`, `#btn-my-transactions` to 28px height.
2. **Main Workspace & Subtabs (`.main-workspace`)**:
   - Workspace Navbar: 36px ribbon with 30px buttons (`#btn-lab-new-patient`, walk-in, emergency intake, contacts, price list, clinical context).
   - Empty State: `#empty-state` centered; suppressed automatically when patient is loaded.
   - Patient Header Banner: Signature gradient, patient file details, expandable biodata drawer.
   - Pending Tab Subtabs Ribbon (`.pending-subtabs`):
     - Sized to `min-height: 32px; height: 32px;` with compact pills for `All Pending`, `Awaiting Billing`, `Awaiting Sample`, `Awaiting Results`, `Free-Form`, `Awaiting Approval`.
     - Count badges: `font-size: 0.68rem; padding: 1px 5px; border-radius: 8px;`.
3. **Sample Collection & Results Entry Workflow Modals**:
   - Sample Collection Modal (`#sampleCollectionModal`): Sized to compact modal dialog with high-density inputs (`#sample_lab_number`, `#btnAutoLabNumber`, `#btnManualLabNumber`, `#sampleItemsList` scrollable container max-height 160px).
   - Single & Bulk Results Entry Modals (`invest_res_modal`, `bulk_result_entry_modal`): Conforms to modal cockpit tokens (`max-height: calc(85vh - 90px); padding: 12px 14px;`).
4. **Lab Reports View (`#reports-view`)**:
   - Fixed 36px header with close button `#btn-close-reports`.
   - Subtabs: Overview, Detailed Report, Analytics.
   - `#reports-datatable` sized with compact headers and cells.

#### C. Non-Breaking JS Preservation Catalog
- In `public/js/lab-core.js`:
  - `loadPatient(patientId)`: Preserves AJAX calls to `/lab-workbench/patient/${patientId}/requests`, initializes `ClinicalAlerts.init(patientId, 'lab_imaging')`, and calls `displayPendingRequests()`.
  - Event listeners on `#btn-view-work-pane`, `#btn-toggle-search`, `#btn-back-to-search`, `#show-all-queue-btn`, `.queue-item[data-filter]`.
  - Sample lab number check timeout: `window._labNumCheckTimeout` querying `lab.checkLabNumber`.
- In `public/js/lab-results.js`:
  - `recordBilling(requestIds)`, `collectSample(requestIds)`, `autoGenerateLabNumber()`, `confirmCollectSample()`, `saveResult()`.
  - Selection preservation: `saveCheckedItemsState()`, `restoreCheckedItemsState()`, `checkedItemsState.billing`, `checkedItemsState.sample`.
- In `public/js/lab-reports.js`:
  - Filters form `#reports-filter-form`, sub-tabs `#reports-tabs`, Chart.js canvas `#status-chart` and `#trends-chart`.

---

### 4. Module 3: Imaging / Radiology Workbench Cockpit Scaling

#### A. Layout Geometry & Viewport Locking
1. **Blade Declarations (`resources/views/admin/imaging/workbench.blade.php`)**:
   - Add `@section('is_workbench', true)` and `@section('body_class', 'is-workbench has-workbench')`.
   - Include universal scaling stylesheet in `@push('styles')`:
     ```blade
     <link rel="stylesheet" href="{{ versioned_asset('css/workbench-cockpit-scaling.css') }}">
     <link rel="stylesheet" href="{{ versioned_asset('css/imaging-workbench.css') }}">
     ```
   - Omit `.content-header` and `.footer` automatically.
2. **Container Sizing**:
   - `.imaging-workbench-container`: Set to `flex: 1 1 auto; min-height: 0; height: 100%; display: flex; overflow: hidden; gap: 8px;`.

#### B. Component & Panel Density Scaling
1. **Left Panel (`.left-panel`)**:
   - Standard 270px width.
   - Modality Queue Widget (`.queue-widget`): Scale all 5 queue filters (`emergency`, `billing`, `results`, `freeform`, `approval`) to 28px items with count badges.
   - Quick Actions: Scale `#btn-new-request`, `#btn-view-reports`, and `#trashPanel` triggers.
2. **Workspace Subtabs & Radiologist Tools**:
   - Pending Tab Subtabs: `All Pending`, `Awaiting Billing`, `Awaiting Results`, `Free-Form`, `Pending Approval`. Note: Imaging skips sample collection and proceeds directly to results/scan.
   - Request Cards (`.request-card`): Compact 2-row layout with service name, urgency pill, scan notes, and action triggers (`.btn-enter-results`, `.btn-view-results`, `.btn-upload-scan`).
3. **Diagnostic Viewer & Dedicated Modals**:
   - Result Entry Modal: `@include('admin.partials.invest_res_modal', ['save_route' => 'imaging.saveResult'])`.
   - Dedicated Imaging View Modal (`invest_res_view_imaging_modal`): Renders DICOM / scan image carousel with full contrast controls in compact dialog.
   - Approval Modals: `#approvalReviewModal`, `#rejectionReasonModal`, `#approveConfirmModal`, `#reverseApprovalModal`.
   - Trash & Dismissed Aside Drawer (`#trashPanel`): Positioned absolute on the right or slide-over with tabs for Dismissed and Deleted requests, driven by `#dismissed-table` and `#deleted-table`.

#### C. Non-Breaking JS Preservation Catalog
- In `public/js/imaging-core.js`:
  - `loadPatient(patientId)` querying `/imaging-workbench/patient/${patientId}/requests`.
  - Queue filters, search widget binding, patient header population.
  - History DataTable `#investigation_history_list` and procedures `#procedures_history_list`.
- In `public/js/imaging-results.js`:
  - `loadTrashData()`, `createTrashCard()`, `#dismissReasonModal`, `#deleteReasonModal`.
  - Approval queue handlers: `#btn-approve-result`, `#btn-reject-result`, `#btn-confirm-reverse`.

---

### 5. Implementation Sequence & Quality Gates

The integration will follow this strict 3-phase execution order:

```
+-------------------------------------------------------------------------------+
| PHASE 1: Laboratory Workbench Modularization & Scaling                        |
| - Move main workspace markup from _modals.blade.php to workbench.blade.php    |
| - Add is_workbench & body_class declarations + link scaler CSS               |
| - Tune lab-workbench.css for flex layout and 1366x768 low-res baseline        |
| - Verify zero JS errors (lab-core.js, lab-results.js, lab-reports.js)        |
+-------------------------------------------------------------------------------+
                                        |
                                        v
+-------------------------------------------------------------------------------+
| PHASE 2: Imaging Workbench Scaling                                            |
| - Add is_workbench & body_class declarations + link scaler CSS               |
| - Tune imaging-workbench.css for flex layout and diagnostic image viewer     |
| - Verify zero JS errors (imaging-core.js, imaging-results.js)                 |
+-------------------------------------------------------------------------------+
                                        |
                                        v
+-------------------------------------------------------------------------------+
| PHASE 3: Pharmacy Workbench Scaling                                           |
| - Add is_workbench & body_class declarations + link scaler CSS               |
| - Harmonize Store Context Banner with flex: 1 1 auto container                |
| - Tune till cart, dispensing datatables, returns & damages panels            |
| - Verify zero JS errors (pharmacy-dispensing.js, pharmacy-core.js)            |
+-------------------------------------------------------------------------------+
                                        |
                                        v
+-------------------------------------------------------------------------------+
| VERIFICATION & REGRESSION SUITE                                               |
| - Node JS syntax verification (node --check public/js/*.js)                  |
| - PHP CS Fixer compliance (composer lint)                                    |
| - Full PHPUnit domain test run (Pharmacy, Lab, Imaging: 43+ tests passing)   |
| - Browser subagent automated zero-scroll viewport measurement (1366 x 768)   |
+-------------------------------------------------------------------------------+
```

---

### 6. Verification Checklist & Success Criteria

| Checkpoint | Requirement | Verification Method |
|---|---|---|
| **Zero-Scroll Outer Window** | `window.scrollY == 0`, `scrollHeight <= innerHeight` on 1366x768 | Browser subagent viewport measurement |
| **Header & Footer Reclaim** | `.content-header` and `.footer` omitted from HTML DOM | Blade `@if(!View::hasSection('is_workbench'))` |
| **All Queue Items Visible** | All 5-7 queue items visible simultaneously without panel scroll | Visual inspection at 1366x768 |
| **Patient Header Compact** | Banner height ~56px with collapsible biodata drawer | DOM inspection and `.show` state toggle |
| **Patient Search Widget** | 30px height, 36px left padding, 10px icon offset | CSS computed styles check |
| **Store Context Banner** | Spans full width without causing vertical page overflow | Visual inspection on Pharmacy workbench |
| **Lab Workbench Architecture** | Main workspace in `workbench.blade.php`, only modals in `_modals.blade.php` | Code review and PSR-12 linting |
| **JS Intactness** | No console errors, DataTables load, modals fire, AJAX saves work | `node --check` and browser subagent interaction |
| **Automated Tests** | 100% pass on `tests/Feature/Pharmacy/`, `tests/Feature/Lab/`, `tests/Feature/Imaging/` | `vendor/bin/phpunit` |

---

### 7. Verification Results & Browser Measurements

#### A. Zero-Scroll Viewport Measurements (1366 x 768 Workstation Baseline)

| Workbench | Viewport Dimensions | `window.scrollY` | `scrollHeight` vs `innerHeight` | Outer Window Scrollbar | Console Errors | Verification Status |
|---|---|---|---|---|---|---|
| **Laboratory Workbench** | `1366 x 768` | `0px` | `637px <= 637px` | **None (Hidden)** | `0 errors` | **VERIFIED & PASSED** |
| **Imaging Workbench** | `1366 x 768` | `0px` | `637px <= 637px` | **None (Hidden)** | `0 errors` | **VERIFIED & PASSED** |
| **Pharmacy Workbench** | `1366 x 768` | `0px` | `637px <= 637px` | **None (Hidden)** | `0 errors` | **VERIFIED & PASSED** |

#### B. Verified Visual Artifact Screenshots
- Laboratory Workbench:
  - `lab_workbench_1366x768_1792660998187.png` (Empty state, queue counts, search box)
  - `lab_queue_modal_1792661019672.png` (Queue datatable drawer open)
  - `lab_patient_selected_1792661040854.png` (Patient header banner, biodata drawer, tabs)
- Imaging Workbench:
  - `imaging_workbench_loaded_1792661165165.png` (Modality queue, empty state)
  - `imaging_queue_modal_1792661186144.png` (Imaging queue datatable)
  - `imaging_patient_selected_1792661206440.png` (Patient selected, request card, tabs)
- Pharmacy Workbench:
  - `pharmacy_workbench_1792661369156.png` (Store context banner, prescription queue, quick actions)
  - `pharmacy_queue_modal_1792661396904.png` (Prescription queue modal)
  - `pharmacy_patient_selected_1792661441896.png` (Checkout tab, till register, patient header)

#### C. Automated Test Results
- `tests/Feature/Lab/`: 12 tests, 15 assertions — **100% passed**
- `tests/Feature/Imaging/`: 7 tests, 7 assertions — **100% passed**
- `tests/Feature/Pharmacy/`: 24 tests, 136 assertions — **100% passed**
- Total Domain Test Suite: **43 tests, 158 assertions — 100% passed, 0 failures**
- PSR-12 Linting (`composer lint`): **0 errors** across 1,307 files.

