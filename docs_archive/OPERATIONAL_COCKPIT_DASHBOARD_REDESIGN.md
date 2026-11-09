# CoreHealth v2 — Operational Cockpit Dashboard Redesign

**Date**: 2026-10-15  
**Git Release Tag**: `v2.6.0.48`  
**Status**: Completed & Verified  

---

## 1. Executive Summary

The default `/home` route has been completely transformed into a space-maximizing, zero-scroll operational cockpit. The cockpit functions as the command center for the entire hospital, featuring an interactive domain-driven engine where operational pipeline stages contextually drive telemetry, charts, shortcuts, and actionable live queues across Columns 2 & 3 without full page refreshes.

### Key Objectives Achieved:
1. **Zero-Scroll Viewport Lock with Restored Standard Hospital Footer & Refined Margins**: Viewport locked strictly to desktop screen bounds with zero window vertical scrollbars. Generous top margin (16px - 18px below navbar) and bottom margin (20px - 24px above footer) provide comfortable breathing room. The standard CoreHealth layout footer (`#f5f5f5` background, `1px solid #e0e0e0` top border, 13px muted typography, hospital primary branding link) is faithfully restored, matching all other pages across the application. Internal micro-scrollbars (`.dash-scrollable`) manage overflow within bounded columns.
2. **Contextual Domain-Driven Tri-Column Architecture**:
   - **Column 1 (28%)**: Operational Pipeline organized into 6 operational tiers (Executive Overview, Clinical & Consultations, Diagnostics & Investigations, Pharmacy & Supply Chain, Inpatients & Surgical Care, Revenue & Governance). Features role-scoped badges (`MY SPECIALTY`, `MY ROLE`), live counts, and interactive pipeline stage selectors.
   - **Column 2 (44%)**: Dynamic Domain Telemetry featuring 6 granular, intensive KPI cards strictly tailored to the selected domain (e.g., Lab displays specimen and test metrics with zero revenue figures; Doctor displays consultation queues and inpatients; Pharmacy displays dispensing and stock health), adaptive micro-charts, and proactive clinical insight alerts.
   - **Column 3 (28%)**: Contextual Shortcuts Dock (with rich action cards inspired by the legacy dashboard plus a toggle to the 18-role persistent dock) and a Deeply Intensive Live Queue Stream with 1-click primary action buttons (`Consult Now`, `Process Lab`, `Dispense`, `Collect`, `Fulfill`).
3. **Comprehensive 18-Role Coverage**: Every role supported in `resources/views/admin/partials/sidebar.blade.php` is fully integrated with role-tailored perspective pills, dedicated pipeline queues, and direct workbench dock launchers.
4. **Doctor Workbench Deep-Linking Contract**: Doctor queue items and shortcuts deep-link directly to `encounters/create?patient_id={id}` (`new_encounter.blade.php`) and `encounters.index` (Doctor Workbench / My Queues).
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
5. **Layout & Visual Verification**:
   - Verified in headless browser at 1920x953 viewport:
     - Top margin: Clean 18px breathing space below fixed navbar (`padding-top: 76px / 78px`).
     - Bottom margin: Clean 22px margin between cockpit cards and footer (`padding-bottom: 74px / 76px`).
     - Footer styling: Standard CoreHealth layout footer appearance restored (`#f5f5f5` background, `1px solid #e0e0e0` top border, 13px muted typography, dynamic `--hospital-primary` link color).
     - Viewport containment: Zero browser window scrollbars; micro-scrollbars handle overflow strictly within column containers.
6. **Departmental Operational Insights Normalization**:
   - Resolved `undefined` text bug in Hospital Cockpit domain insights strip.
   - Normalized payload schema in `DashboardDataController.php` and `dashboard-cockpit.js` to guarantee both `desc` / `message` and `color` / `severity` are seamlessly handled.
   - Added `.insight-success` class in `dashboard-cockpit.css` for clear visual distinction across green, yellow, blue, and red alert badges.

---

## 5. Tablet & Mobile Viewport Overhaul & Dual-Mode Queue (v2.6.0.47)

### 1. Root Cause of Previous Tablet Failures
- **Tablet No-Man's Land**: The previous mobile switcher pills were bound to `d-md-none` (< 768px). Devices in the 768px - 991.98px range (iPads, Galaxy Tabs, small netbooks, or 850x540 / 869x538 viewports) had the segmented tabs hidden while desktop columns were stacked vertically, causing a 1,500px+ vertical overflow that destroyed the zero-scroll layout.
- **Excess Container Padding**: `.content-wrapper` applied `padding: 76px 24px 74px 24px` (150px of dead vertical padding) plus fixed footer overhead on small screens, squeezing the dashboard into under 380px.
- **Table Truncation**: A rigid 4-column fixed table in Column 3 allocated only 17% width to clinic details, truncating text to "Cli..." and squishing names.

### 2. Architectural Enhancements Implemented
1. **Segmented Viewport Switcher for All Tablets & Mobile (< 992px)**:
   - Elevated `.dash-viewport-tabs` / `.dash-mobile-tabs` breakpoint to `d-lg-none` (< 992px).
   - Tablets seamlessly display high-density segmented pills: `Pipeline (with live count badge) | Telemetry | Live Queue (with live count badge)`.
   - Switching tabs switches columns with zero window scroll. Clicking a pipeline item or role perspective pill automatically activates the Telemetry tab.
2. **Dual-Mode Clinical Queue Engine (Column 3)**:
   - **Mode A (Default)**: High-Density Queue Stream Cards (`.dash-queue-stream`) with fluid layout, priority indicator stripes, clinic/department avatars, unclipped patient names and file numbers, full appointment/order details, and touch-ready action buttons.
   - **Mode B (Data Table)**: Modern Responsive Table (`.dash-ledger-table`) with proportional column widths (`col-patient 36%`, `col-dept 32%`, `col-status 16%`, `col-action 16%`) and generous touch padding.
   - View mode toggle button (`#btn-toggle-queue-view`) in card header with `localStorage` persistence.
3. **Optimized Screen Real Estate on Small Viewports**:
   - Eliminated dead padding: `.content-wrapper` on `<= 991.98px` now uses `padding: 60px 8px 6px 8px`, and layout footer is cleanly suppressed on `/home`.
   - Compact 2-line ribbon: Line 1 groups Shift & Fast Intake buttons; Line 2 renders the horizontally scrollable perspective bar (~58px total height).
   - High-density shortcuts dock in Column 3: 3-column micro-grid (`repeat(3, 1fr)`) taking only ~56px instead of ~150px, maximizing vertical space for the active queue.
4. **Domain-Specific Queues & Complete 18-Role Telemetry**:
   - Discontinued `$docQueueWaiting` reuse; wired real live queues for `receptionist`, `doctor`, `nursing`, `lab`, `pharmacy`, `billing`, `store`, `imaging`, `theatre`, `maternity`, `inpatient`, and `hmo`.
   - Added complete telemetry and shortcuts for `morgue`, `audit`, `hr`, `accounts`, `ess`, and `admin`.
   - Resolved SQL column alias bug on `wards.name as ward_name` and `beds.name as bed_name`.
   - Corrected route reference from non-existent `users.index` to `staff.index`.

---

## 6. Authoritative Sidebar Guard Auditing & Complete 18-Role Scoping (v2.6.0.48)

### 1. Architectural Guard Strategy: Sidebar as Authoritative Source of Truth
To eliminate authorization bypasses and 403 Forbidden leaks, the operational cockpit mirrors the exact Spatie role and permission constraints enforced in `resources/views/admin/partials/sidebar.blade.php`:

1. **Elimination of Forbidden Route Leaks**:
   - **Doctor & Receptionist**: Non-nursing users previously encountered 403 errors when clicking `nursing-workbench` links. Doctor KPIs now route strictly to `encounters.index`, `surgery-workbench.index`, and `maternity-workbench.index`. Receptionist KPIs route to `reception.workbench` and `patient.index`.
   - **Morgue Domain**: Replaced non-view JSON endpoints (`morgue.queue`, `morgue.reports`) and 403 targets (`ops-audit.morgue`, `billing.workbench`) with the authoritative `route('morgue.workbench')` view and parameter tabs.
   - **HR Domain**: Substituted restricted administrative routes (`staff.create`, `staff.index`) with HR-accessible `hr.staff-registry.index` and `hr.disciplinary.index`.
   - **Employee Self-Service (ESS)**: Replaced non-existent `my-profile` with valid authenticated staff routes (`hr.ess.index`, `hr.ess.my-leave`, `hr.ess.my-roster`).
   - **HMO Role Normalization**: Aligned role checks to encompass both `'HMO'` and `'HMO Executive'`.

2. **Column 1 Pipeline Multi-Tier Scoping**:
   - Expanded Column 1 into 13 high-density operational tiers (`Executive Overview`, `Reception & Intake`, `Nursing & Inpatients`, `Clinical Consultations`, `Diagnostics & Investigations`, `Pharmacy & Supply Chain`, `Operating Theatre & Maternity`, `Revenue & Billing`, `Finance & Treasury`, `Human Resources`, `Internal Audit`, `Morgue & Mortuary`, `Employee Self-Service`).
   - Every tier and queue item is guarded character-for-character by `@if($isAdmin || $isX)`. Non-admin users see strictly their department and clinical responsibilities.
   - Replaced static action links with a dynamic role-scoped quick action footer dock that adapts seamlessly to Nurse, Doctor, Pharmacist, Storekeeper, Accountant, HR Manager, Auditor, Morgue Attendant, HMO Officer, Receptionist, Biller, and ESS users, preventing any non-admin role from falling into administrative configuration shortcuts.

3. **Column 2 & Column 3 Guard Protection**:
   - In `_col_telemetry.blade.php`, pre-rendered KPI links evaluate server-side role checks (`$revLink`, `$bedLink`, `$vitalsLink`, `$pharmLink`, `$labLink`, `$docLink`, `$recepLink`, `$storeLink`) and safely fall back to `javascript:void(0);` if the active user lacks access.
   - In `_col_ledger.blade.php`, the persistent launchpad dock integrates all 18 roles with Spatie role checks, preventing unauthorized workbench links.

4. **Testing & Quality Assurance**:
   - Expanded PHPUnit test suite (`tests/Feature/Dashboard/DashboardCockpitTest.php`) with 10 feature tests and 396 assertions covering Doctor, Nurse, Receptionist, Biller, and SuperAdmin view scoping and pipeline key validation.
   - Clean PSR-12 code style verified via PHP-CS-Fixer.
   - Zero JavaScript syntax errors verified across all files via `node --check`.
