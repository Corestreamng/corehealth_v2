# Universal Clinical Workbench Framework Architecture

**Date**: 2026-10-19  
**Git Release Tag**: v2.5.0.10  
**Status**: Completed & Production-Verified  
**Branch**: `feat/workbench-cockpit-scaling`

---

## 1. Executive Summary & Problem Context

Following the implementation of cockpit viewport scaling on the **Reception Workbench**, an analysis of the remaining clinical workbenches (**Billing**, **Maternity**, **Nursing**, **Pharmacy**, **Laboratory**, and **Imaging**) revealed widespread boilerplate duplication:

1. **Repetitive Blade Markup**:
   - Each workbench maintained hundreds of identical lines of markup defining the navigation ribbon, search toggle buttons, empty state placeholders, queue drawer overlays, and active patient banners.
   - Accidental divergence, incomplete copy-pasting (such as lab reports views copied into billing), and inconsistent registration modal hooks.
2. **Duplicated Layout Styling**:
   - Each workbench CSS file (`billing-workbench.css`, `nursing-workbench.css`, etc.) duplicated flexbox containers, left panels, queue counts, and media queries.
3. **Redundant Front-End Logic**:
   - Repeated event listeners for `#btn-toggle-search`, `#btn-back-to-search`, `#btn-view-work-pane`, queue opening (`#show-all-queue-btn`), tab switching, and URL parameter auto-selection (`?patient_id=X`).

To eliminate repetition across the clinical suite, this architecture introduces the **Universal Clinical Workbench Framework**. It standardizes layout markup, front-end shell orchestration, and cockpit scaling into reusable, drop-in primitives across:
- **Billing Workbench** (First target implementation — completed and verified)
- **Maternity Workbench**
- **Nursing Workbench**
- **Pharmacy Workbench**
- **Laboratory Workbench**
- **Imaging Workbench**

---

## 2. Framework Architecture & Components

```
+---------------------------------------------------------------------------------------+
| CoreHealth v2 Universal Workbench Framework                                           |
+---------------------------------------------------------------------------------------+
|  1. Shared Blade Partials (resources/views/admin/workbench/partials/)                  |
|     - _navbar.blade.php           -> Unified toolbar: search toggle, walk-in, etc.    |
|     - _patient_header.blade.php   -> Compact active patient banner & balance badge    |
|     - _empty_state.blade.php      -> Clean queue triage placeholder                   |
|     - _queue_view.blade.php       -> Slide-in departmental datatable drawer           |
|                                                                                       |
|  2. Universal Scaler & Shell CSS (public/css/workbench-cockpit-scaling.css)           |
|     - Zero-scroll master viewport locking (height: 100vh; overflow: hidden)           |
|     - Fixed 270px left panel with collapse animation & micro-scrollbars               |
|     - Flex main workspace with fixed navbar (36px) & compact tabs (34px)              |
|                                                                                       |
|  3. Standardized Shell Controller (public/js/workbench-shell.js)                      |
|     - Mobile/desktop panel collapse & slide-in transitions                            |
|     - Active patient state store & lifecycle events (workbench:patient-selected)      |
|     - Automated queue counts background poller                                        |
|     - Unified walk-in & modal launcher hooks                                          |
+---------------------------------------------------------------------------------------+
```

### 2.1 Reusable Blade Partials (`resources/views/admin/workbench/partials/`)

1. **`_navbar.blade.php`**:
   - Provides standardized desktop and mobile navigation controls:
     - `#btn-back-to-search` (mobile search drawer back navigation)
     - `#btn-toggle-search` (desktop 270px sidebar collapse/expand)
     - New Patient trigger (`showPatientFormModal('create')`)
     - Walk-in trigger with automatic module appendage prefixing (`openWalkInRegistration('BIL-')`)
     - Configurable emergency intake trigger (checked via `appsettings()->enable_ei_<module>`)
     - Hospital Contacts & Price List modal triggers
     - Slot for domain-specific action buttons (`@yield('workbench_navbar_actions')` or `$extraActions`)
2. **`_patient_header.blade.php`**:
   - Compact banner displaying initials avatar, formatted full name (including `othername`), file number, gender/age chip, HMO badge, phone, account balance badge, and quick switch/clear controls.
3. **`_empty_state.blade.php`**:
   - Zero-patient state with customizable icon, title, description, and "View Queue" trigger button.
4. **`_queue_view.blade.php`**:
   - Slide-in datatable drawer (`#queue-view` with `#queue-datatable`) for high-density queue triage without navigating away from the workspace.

### 2.2 Reusable CSS Architecture (`public/css/workbench-cockpit-scaling.css`)

All workbench shell containers (`.workbench-container`, `.billing-workbench-container`, `.nursing-workbench-container`, etc.) inherit unified styling:
- **Viewport Bounding**: `height: 100% !important; max-height: 100% !important; overflow: hidden !important;`
- **Left Panel (Search & Queue Sidebar)**:
  - Fixed `270px` width, with smooth CSS transition collapse (`margin-left: -278px; width: 0 !important; display: none !important;` when `.collapsed` or `.hidden`).
  - Automatically collapses upon patient selection (`setPatient()`) to give full viewport width to workspace tabs, and expands upon clearing the patient or via `#btn-toggle-search`.
- **Signature Hospital Branding Patient Header**:
  - Employs dynamic CSS variable linear gradient: `linear-gradient(135deg, var(--hospital-primary, #011b33) 0%, var(--hospital-secondary, #0056b3) 100%)`.
  - Crisp high-contrast typography: white patient name (`1.15rem`, bold), initials avatar circle, and semi-transparent frosted metadata tags (`.patient-meta-item`).
  - Glassmorphic Account Balance card (`rgba(255, 255, 255, 0.15)` backdrop filter with white labels and colored amounts).
  - Clinical Alerts Octagon button, allergy alert badge strip, translucent action buttons (`.btn-expand-patient`, `.btn-edit-patient`, `#btn-switch-patient`), and expandable biodata drawer (`#patient-details-expanded` with `#patient-details-grid`).
- **Patient Intake / Registration Modal Scaling (`#patientFormModal`)**:
  - Flexbox column architecture: `form#patient-form` and `.modal-body` are flex columns with `flex: 1 1 auto; min-height: 0; overflow: hidden;`.
  - Fixed header and stepper (`flex-shrink: 0;`), fluid internal form steps container (`.form-steps-container { flex: 1 1 auto; overflow-y: auto; max-height: calc(100vh - 290px); }`), and persistent pinned footer (`.modal-footer { flex-shrink: 0; min-height: 62px; padding: 12px 24px; }`).
  - Prevents vertical clipping on all viewports (including low-res 1366x768 and 1920x953 displays), guarantees full 36px button heights with proper padding, and ensures strict visibility gating (`#pf-btn-prev`, `#pf-btn-submit` remain hidden on step 1).
- **Cockpit Density Tokens**: Inputs (30px), buttons (28-30px), compact datatables (4px row padding), and 4px micro-scrollbars.

### 2.3 JavaScript Framework (`public/js/workbench-shell.js`)

`window.WorkbenchShell` provides modular state and event handling:
- **Panel Coordination**: Handles `#btn-toggle-search`, `#btn-back-to-search`, `#btn-view-work-pane`, and keyboard shortcuts (`Alt+S` or `/` to focus search).
- **Patient Lifecycle**: Manages `setPatient()`, `clearPatient()`, `collapseLeftPanel()`, `expandLeftPanel()`, `updatePatientHeader()`, and dispatches jQuery events:
  - `workbench:patient-selected` (auto-closes search sidebar and renders rich metadata)
  - `workbench:patient-cleared` (auto-reopens search sidebar)
  - `workbench:tab-switched`
  - `workbench:queue-filter-changed`
- **Queue Polling**: Automatically refreshes queue count badges via `queueCountsUrl` at configurable intervals without full-page reloads.

---

## 3. Billing Workbench Implementation

The Billing Workbench was refactored as the primary implementation:

1. **View Refactoring (`resources/views/admin/billing/workbench.blade.php`)**:
   - Extends standard cockpit directives: `@section('is_workbench', true)` and `@section('body_class', 'is-workbench has-workbench')`.
   - Incorporates `_navbar`, `_empty_state`, `_queue_view`, and `_patient_header`.
   - Retains all billing tabs (`#billing-tab`, `#receipts-tab`, `#admissions-tab`, `#account-tab`), billing shift lock overlay, and payment modals.
2. **Scripts & Framework Hookup (`resources/views/admin/billing/partials/_scripts.blade.php` & `public/js/billing-core.js`)**:
   - Loads `versioned_asset('js/workbench-shell.js')`.
   - Initializes `WorkbenchShell.init({ module: 'billing', walkinPrefix: 'BIL-', ... })`.
   - Formats patient names with `othername` support in `displayPatientInfo()`.

---

## 4. Verification & Testing

- **Linting**: PSR-12 code style check passed via `composer lint` (0 files need fixing across 1,295 files).
- **JS Syntax Verification**: Passed `node --check` across `workbench-shell.js`, `billing-core.js`, `billing-payments.js`, and `billing-hmo.js`.
- **PHPUnit Feature Tests**:
  - `tests/Feature/BillingWorkbenchTest.php`: **8/8 tests passed (23 assertions)**.
  - `tests/Feature/PatientRegistrationTest.php`: **12/12 tests passed (69 assertions)**.
  - `tests/Feature/Reception/`: **4/4 tests passed (21 assertions)**.
- **Browser Automation Verification**:
  - Verified `http://localhost:8000/billing-workbench` with Chrome subagent.
  - Confirmed zero-scroll viewport locking (`scrollY = 0`), no outer scrollbars, clean console without runtime errors.
  - Confirmed interactive sidebar toggle collapse and expansion.
  - Confirmed New Patient registration modal opening and clean dismissal.

---

## 5. Rollout Blueprint for Remaining Workbenches

To apply this framework to the remaining workbenches (**Maternity**, **Nursing**, **Pharmacy**, **Laboratory**, and **Imaging**):

```blade
@extends('admin.layouts.app')
@section('title', '<Module> Workbench')
@section('is_workbench', true)
@section('body_class', 'is-workbench has-workbench')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('css/workbench-cockpit-scaling.css') }}">
<link rel="stylesheet" href="{{ versioned_asset('css/<module>-workbench.css') }}">
<link rel="stylesheet" href="{{ asset('plugins/dataT/datatables.min.css') }}">
@endpush

@section('content')
<div class="<module>-workbench-container workbench-container">
    <div class="left-panel" id="left-panel">
        <div class="panel-header">...</div>
        @include('admin.partials.patient_search_html')
        <div class="queue-widget">...</div>
        <div class="quick-actions">...</div>
    </div>
    <div class="main-workspace" id="main-workspace">
        @include('admin.workbench.partials._navbar', ['module' => '<module>', 'walkinPrefix' => '<PREFIX>-'])
        @include('admin.workbench.partials._empty_state', ['icon' => '<module-icon>', 'title' => 'No Patient Selected'])
        @include('admin.workbench.partials._queue_view', ['title' => 'Queue'])
        @include('admin.workbench.partials._patient_header')
        <div class="workspace-content" id="workspace-content">
            <!-- Module Specific Tabs and Panes -->
        </div>
    </div>
</div>
@endsection
```
