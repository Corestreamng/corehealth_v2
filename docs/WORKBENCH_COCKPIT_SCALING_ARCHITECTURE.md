# Universal Workbench Cockpit Scaling Architecture

**Date**: 2026-10-16  
**Git Release Tag**: v2.5.0.9  
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

## 7. Workbench Rollout Standard (Pattern for Other Modules)

To migrate other workbenches (Doctor, Pharmacy, Nursing, Lab, Billing, Morgue) to this framework:

1. **In the workbench Blade view (`<module>/workbench.blade.php`)**:
   ```blade
   @extends('admin.layouts.app')
   @section('title', '<Module> Workbench')
   @section('is_workbench', true)
   @section('body_class', 'is-workbench has-workbench')

   @push('styles')
   <link rel="stylesheet" href="{{ versioned_asset('css/workbench-cockpit-scaling.css') }}">
   <link rel="stylesheet" href="{{ versioned_asset('css/<module>-workbench.css') }}">
   ...
   @endpush
   ```
2. **In the module-specific CSS (`public/css/<module>-workbench.css`)**:
   - Bounding master container: `height: 100% !important; max-height: 100% !important; overflow: hidden;`
   - Panels and content panes: `flex: 1 1 auto; min-height: 0; overflow-y: auto;`
   - Remove any legacy hardcoded `min-height: calc(100vh - 100px);` overrides.
   - Run `node --check` and `make lint` before committing.
