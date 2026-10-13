# CoreHealth v2 — Operational Cockpit Dashboard Redesign

**Date**: 2026-10-13  
**Git Release Tag**: `v2.6.0.42`  
**Status**: Completed & Verified  

---

## 1. Executive Summary

The default `/home` route has been completely transformed from a legacy tabbed Blade view into a modern, space-maximizing, zero-scroll operational cockpit. The new design acts as the central command center for the entire hospital, synthesizing clinical, diagnostic, inventory, inpatient, and financial activities into a high-density, multi-column dashboard with instant deep-linking to all departmental workbenches.

### Key Objectives Achieved:
1. **Zero-Scroll Viewport Lock (Desktop)**: Viewport locked strictly to `100vh - 62px` with zero browser scrollbar on desktop workstations. Internal micro-scrollbars (`.dash-scrollable`, 3px slim track) manage overflow within bounded columns.
2. **Tri-Column High-Density Geometry**:
   - **Column 1 (28%)**: Operational Pipeline & Triage Queues with live counter badges, pulse indicators, and 1-click deep links.
   - **Column 2 (44%)**: Clinical Telemetry & Analytics featuring a 2x3 KPI matrix, dual micro-charts (Hourly Patient Flow & 7-Day Revenue Trend), and proactive operational insight alerts.
   - **Column 3 (28%)**: Multi-Department Real-Time Activity Ledger with direct patient-to-encounter links, plus a persistent 18-role Quick Workbench Launch Dock.
3. **Comprehensive 18-Role Coverage**: Every role supported in `resources/views/admin/partials/sidebar.blade.php` is fully integrated with role-tailored perspective pills, dedicated pipeline queues, and direct workbench dock launchers:
   - Receptionist (`reception.workbench`)
   - Biller / Cashier (`billing.workbench`)
   - Doctor / Consultant (`encounters.index`, `encounters/create?patient_id={id}`)
   - Nurse / Ward Care (`nursing-workbench.index`, `beds.index`)
   - Pharmacist (`pharmacy.workbench`, `products.index`)
   - Store Keeper (`inventory.store-workbench.index`, `inventory.requisitions.index`)
   - Lab Scientist (`lab.workbench`)
   - Radiologist / Scanning Room (`imaging.workbench`)
   - Surgeon / Theatre (`surgery-workbench.index`)
   - Maternity / Antenatal (`maternity-workbench.index`)
   - Child Health / Pediatrics (`nursing-workbench.index`)
   - Morgue / Mortuary (`morgue.workbench`)
   - HMO Executive (`hmo.workbench`, `hmo.reports`)
   - Accountant (`accounting.dashboard`, `accounting.journal-entries.index`)
   - Internal Auditor (`/audit-workbench`, `ops-audit.reception`)
   - HR Manager / Payroll Approver (`hr.workbench.index`, `hr.leave-calendar.index`)
   - Employee Self-Service (ESS / My Portal) (`hr.ess.index`, `hr.ess.my-leave`, `hr.ess.my-payslips`)
   - Hospital Administrator / SuperAdmin (`hospital-config.index`, `roles.index`)
4. **Doctor Workbench Deep-Linking Contract**:
   - Clicking a patient in the Activity Ledger or Doctor Queue navigates directly to `encounters/create?patient_id={id}` (`new_encounter.blade.php`).
   - Deep-linking parameters (`?queue_filter=waiting`, `?status=waiting`, `?tab=history`) automatically activate the matching status pill or history tab in `public/js/doctor-queue.js`.
5. **Full Responsive Parity**:
   - **Tablet Landscape (1024px - 1199px)**: Columns dynamically adapt (30% / 40% / 30%) with condensed font scales.
   - **Tablet Portrait (768px - 991px)**: Shifts to a 2-column top grid with full-width activity ledger at bottom.
   - **Mobile Phones (< 768px)**: Segmented tab switcher (`Pipeline`, `Telemetry`, `Activity`) ensures 100% of data is accessible with zero loss of functionality.
6. **Strict Architectural Adherence**:
   - Zero inline `<style>` or `<script>` exceeding 10 lines in any Blade view.
   - Standalone CSS (`public/css/dashboard-cockpit.css`) utilizing dynamic hospital theming variables (`var(--hospital-primary)`, `var(--hospital-primary-rgb)`).
   - Standalone JS module (`public/js/dashboard-cockpit.js`) validated with `node --check`.

---

## 2. Architecture & File Inventory

| Component | Project Path | Description |
| :--- | :--- | :--- |
| **Master Blade View** | `resources/views/admin/home.blade.php` | Lightweight entry view (< 70 lines) extending `admin.layouts.app` and assembling modular partials. |
| **Header Ribbon** | `resources/views/admin/dashboard/cockpit/_header_ribbon.blade.php` | Live WAT clock, LIVE OPS pulse, dynamic 18-role perspective switcher pills, and Fast Intake buttons. |
| **Pipeline Column** | `resources/views/admin/dashboard/cockpit/_col_pipeline.blade.php` | High-density operational pipeline queues across all 14 clinical and operational departments. |
| **Telemetry Column** | `resources/views/admin/dashboard/cockpit/_col_telemetry.blade.php` | 2x3 KPI matrix, dual Chart.js sparkline micro-charts, and operational insight ticker. |
| **Ledger & Dock** | `resources/views/admin/dashboard/cockpit/_col_ledger.blade.php` | Multi-department activity stream with patient links and 18-role quick workbench dock. |
| **Modals Partial** | `resources/views/admin/dashboard/cockpit/_modals.blade.php` | Ingestion wrapper for Emergency Intake and Patient Registration modals. |
| **Backend API** | `app/Http/Controllers/DashboardDataController.php` | `cockpitSummary()` aggregating cached multi-department queues, KPIs, sparklines, and sorted event ledger. |
| **Web Route** | `routes/web.php` | `GET /dashboard/data/cockpit-summary` (`dashboard.data.cockpit-summary`). |
| **Client Controller** | `public/js/dashboard-cockpit.js` | Standalone client controller managing clock, AJAX fetching, Chart.js 2.9.4 rendering, and role perspective filters. |
| **Doctor Deep-Linker** | `public/js/doctor-queue.js` | Updated to consume URL query params (`patient_id`, `queue_filter`, `status`, `tab`) for frictionless intake. |
| **Cockpit CSS** | `public/css/dashboard-cockpit.css` | 830-line modular design system tokens, zero-scroll geometry, custom micro-scrollbars, and responsive breakpoints. |
| **Feature Test** | `tests/Feature/Dashboard/DashboardCockpitTest.php` | PHPUnit test suite validating view rendering, JSON contract parity, and activity deep-links. |

---

## 3. Deep-Linking Matrix

| Source Element | Department | Deep-Link Destination | Behavior / Target |
| :--- | :--- | :--- | :--- |
| **Waiting for Triage** | Reception | `/reception/workbench` | Opens Reception Workbench triage queue. |
| **Vitals Pending** | Nursing | `/nursing-workbench` | Opens Nursing Workbench vitals queue. |
| **Doctor Consultation** | Doctor | `/encounters?status=waiting` | Auto-selects "Waiting" status pill in Doctor workbench. |
| **Activity Row (Doctor)** | Doctor | `/encounters/create?patient_id={id}` | Opens consultation intake form for selected patient. |
| **Pharmacy Dispense** | Pharmacy | `/pharmacy-workbench?tab=pending` | Opens Pharmacy Workbench pending prescriptions tab. |
| **Activity Row (Pharmacy)**| Pharmacy | `/pharmacy-workbench?patient_id={id}`| Pre-loads patient prescription in Pharmacy workbench. |
| **Laboratory Orders** | Lab | `/lab.workbench` | Opens Medical Laboratory workbench. |
| **Activity Row (Lab)** | Lab | `/lab-workbench?patient_id={id}` | Pre-loads patient tests in Lab workbench. |
| **Radiology & Scans** | Radiology | `/imaging.workbench` | Opens Imaging / Radiology workbench. |
| **Operating Theatre** | Surgery | `/surgery-workbench` | Opens Surgery / Theatre workbench. |
| **Maternity & ANC** | Maternity | `/maternity-workbench` | Opens Maternity & Antenatal care workbench. |
| **Admitted Inpatients** | Nursing | `/beds` | Opens Ward Bed Management and admitted patient roster. |
| **Store Requisitions** | Store | `/inventory/requisitions` | Opens Store Requisitions management queue. |
| **Unpaid Invoices** | Billing | `/billing-workbench` | Opens Cashier / Billing workbench. |
| **Activity Row (Billing)** | Billing | `/billing-workbench?patient_id={id}`| Opens Billing workbench filtered to patient's invoice. |
| **HMO Pre-Auth & Claims** | HMO | `/hmo/workbench` | Opens HMO Executive workbench. |
| **Staff Leave Requests** | HR | `/hr/leave-requests` | Opens HR Leave Requests approval queue. |
| **Mortuary Admissions** | Morgue | `/morgue.workbench` | Opens Morgue Workbench occupancy list. |

---

## 4. Verification & Testing

1. **PHPUnit Feature Tests**:
   - `tests/Feature/Dashboard/DashboardCockpitTest.php` executed against MySQL test database:
     - 3 feature tests, 83 assertions passing with 100% success rate (0 regressions).
   - `tests/Feature/Dashboard/DashboardKpiTest.php`:
     - 4 tests passing with 100% success rate.
2. **JavaScript Syntax & Strict Mode**:
   - `node --check public/js/dashboard-cockpit.js` (Passed, exit code 0).
   - `node --check public/js/doctor-queue.js` (Passed, exit code 0).
3. **PSR-12 Code Formatting**:
   - Verified via `tools/vendor/bin/php-cs-fixer fix --dry-run` on modified controllers and test suites (Clean).
4. **View Render Test**:
   - Verified via tinker: `view('admin.home')->render()` renders 495KB of semantic markup with all partials cleanly included.
