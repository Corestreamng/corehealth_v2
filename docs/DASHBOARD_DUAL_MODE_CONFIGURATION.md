# Dual Dashboard Architecture & Hospital Configuration Standard

**Date**: 2026-11-02  
**Git Release Tag**: v2.6.0.60  
**Status**: Production Ready  
**Target Environments**: CoreHealth v2 (Hospital Instance)  

---

## 1. Executive Summary & Purpose

CoreHealth v2 now supports dual operational dashboard experiences:
1. **Operational Cockpit** (`cockpit`): High-density, zero-scroll tri-column dashboard designed for rapid clinical triage, live activity telemetry, and direct workbench launching across all 18 hospital roles.
2. **Classic Operations Dashboard** (`classic`): Restored multi-role tabbed dashboard featuring extensive departmental metrics, queues, Chart.js trends, and detailed audit feeds, now upgraded into a condensed, high-density layout.

Hospital administrators can switch the global default landing dashboard at any time via **Hospital Config** (`admin/hospital-config`). Furthermore, both layouts provide interactive preview badges in their headers, enabling users to seamlessly toggle between the Cockpit (`/home?mode=cockpit`) and Classic (`/home?mode=classic`) views on demand.

---

## 2. Commit Forensics & Restoration

- **Cockpit Redesign Commit**: `fa8fe138c761bbf8fe8b4bd6c750f531e5b703e1` (Oct 13, 2026, `v2.6.0.42`), which replaced the legacy 1,512-line monolithic `resources/views/admin/home.blade.php`.
- **Pre-Cockpit Source Commit**: `0c3f6605d843be7ff377ea14f2eb11ff8546b86d` (Oct 13, 2026, `v2.6.0.41`).
- **Restoration & Modularization**: Rather than restoring a 1,500+ line monolithic Blade view with 410 lines of inline CSS and 870 lines of inline scripts (violating Rule 1), the Classic Dashboard was re-architected into modular, high-performance assets:
  - `resources/views/admin/dashboard/classic.blade.php` (Blade view structure and modal includes)
  - `public/css/dashboard-classic.css` (Condensed styling adhering to `--hospital-primary` dynamic branding)
  - `public/js/dashboard-classic.js` (Standalone, event-delegated JS module consuming `window.DASHBOARD_CLASSIC_CONFIG`)

---

## 3. Architecture & Data Flow

```
                      +-----------------------------+
                      |   GET /home?mode={mode}     |
                      +--------------+--------------+
                                     |
                                     v
                      +-----------------------------+
                      |     HomeController@index     |
                      +--------------+--------------+
                                     |
             +-----------------------+-----------------------+
             |                                               |
             v ($mode == 'classic')                          v ($mode == 'cockpit')
+----------------------------+                 +----------------------------+
| admin.dashboard.classic    |                 | admin.home                 |
| (Classic Tabbed Dashboard) |                 | (Operational Cockpit)      |
+-------------+--------------+                 +-------------+--------------+
              |                                              |
              v                                              v
+----------------------------+                 +----------------------------+
| public/css/dashboard-      |                 | public/css/dashboard-      |
| classic.css                |                 | cockpit.css                |
| public/js/dashboard-       |                 | public/js/dashboard-       |
| classic.js                 |                 | cockpit.js                 |
+----------------------------+                 +----------------------------+
```

### 3.1 Database Schema & Settings
The `application_status` table includes the `dashboard_mode` configuration column:
- **Column**: `dashboard_mode` (`VARCHAR(32)`, default: `'cockpit'`)
- **Migration**: `database/migrations/2026_11_01_204000_add_dashboard_mode_to_application_status_table.php`
- **Model**: `app/Models/ApplicationStatu.php` (included in `$fillable`)

### 3.2 Hospital Config Management
- **Controller**: `app/Http/Controllers/HospitalConfigController.php` validates `'dashboard_mode' => 'nullable|string|in:cockpit,classic'` and flushes the settings cache via `clearAppSettingsCache()`.
- **View**: `resources/views/admin/hospital-config/index.blade.php` renders the "Default Dashboard Experience" dropdown selector under General Information.

---

## 4. Condensed Styling Standards

The restored Classic Dashboard adheres strictly to condensed, space-scaled UI standards:
- **Tab Bar**: Compact pills (`padding: 8px 14px; font-size: 0.8125rem; font-weight: 600; border-radius: 10px;`) with horizontal overflow scrolling.
- **Welcome Banner**: Reduced padding (`14px 20px;`) with 42px avatar and 1.15rem title.
- **KPI Stat Cards**: Scaled from 28px padding to 12px padding with 1.5rem value typography and 38px iconography.
- **Queue Widgets**: Compact items (`10px 8px; border-radius: 10px;`) with 1.35rem count badges.
- **Charts & Mini Tables**: Standardized 220px canvas containers with compact 6px padded table rows.
- **Branding**: Full integration with dynamic `:root` variables (`--hospital-primary`, `--hospital-primary-rgb`, `--hospital-secondary`).

---

## 5. KPI Metric Fallbacks & Zero-Dash Eradication

All 18 role tabs across the classic dashboard enforce clean initial metrics:
- Replaced placeholder dashes (`—`) with true numeric zero (`0`), zero percentages (`0%`), or formatted currency (`₦0`) across Doctor, HMO, Accounts, Audit, and ESS tab stat cards and summary cards.
- Live telemetry AJAX handlers update cards immediately upon receipt of server data while maintaining pristine visual density without unrendered dashes.
- All KPI cards deep-link to corresponding clinical workbenches or filtered listings.

---

## 6. Modern Workbench Routing & Legacy Deprecation

In alignment with modern workbench architecture:
1. **Zero Direct Links to `/add-to-queue`**:
   - All links pointing to `/add-to-queue` on the Cockpit pipeline, ribbon, classic Doctor tab, Receptionist dashboard, send queue forms, and request tables have been removed and replaced with direct routes to `reception.workbench` (e.g. `route('reception.workbench', ['queue_filter' => 'waiting'])`).
   - Legacy endpoint `GET /add-to-queue` automatically returns an HTTP 302 redirect to `reception.workbench`.
2. **Unified Patient Form Modal Integration in Header Ribbons**:
   - Patient Form Modal (`#patientFormModal`) remains fully preserved across all modern workbenches, dashboards, and header ribbons (`@include('admin.partials.patient-form-modal')`).
   - The Cockpit Header Ribbon, pipeline quick actions, and Patients index trigger the registration modal directly via `showPatientFormModal('create')`.
   - All legacy full-page redirects to `patient/create` remain deprecated in favor of the rapid registration modal and modern Reception Workbench intake.
3. **Sidebar Modern Workbench Integration**:
   - `_nav-reception.blade.php`: Added direct sidebar navigation to `New Patient` (`reception.workbench?action=new-patient`) and `Triage & Intake Queue` (`reception.workbench?queue_filter=waiting`).
   - `_nav-admin.blade.php`: Added `New Patient (Workbench)` under Admin Patients menu.
   - `_nav-doctor.blade.php`: Added `Waiting Queue` under Doctor Consultations menu.

---

## 7. Verification & Testing

### 7.1 Automated PHPUnit Tests
Comprehensive feature test suite in `tests/Feature/Dashboard/DashboardModeSwitchTest.php`:
- `test_home_route_renders_cockpit_view_by_default()`
- `test_home_route_renders_classic_view_when_query_mode_is_classic()`
- `test_home_route_renders_cockpit_view_when_query_mode_is_cockpit()`
- `test_home_route_renders_classic_view_when_application_status_dashboard_mode_is_classic()`
- `test_hospital_config_updates_dashboard_mode_setting_successfully()`
- `test_add_to_queue_route_redirects_to_reception_workbench()`
- `test_patient_create_route_redirects_to_reception_workbench()`
- `test_dashboard_routes_exclude_add_to_queue_and_include_patient_form_modal()`
- `test_sidebar_contains_modern_reception_workbench_links()`

Full suite execution:
```bash
vendor/bin/phpunit tests/Feature/Dashboard/
# Result: 23 tests, 430 assertions — OK (100% pass)
```

### 7.2 Linting & Asset Validation
- PSR-12 code style verified via `composer lint` (0 violations across 1,324 files).
- Standalone JavaScript verified via `node --check public/js/dashboard-classic.js` and `node --check public/js/dashboard-cockpit.js` (0 syntax errors).
