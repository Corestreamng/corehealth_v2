# 🏥 CoreHealth v2 — Dashboard Enhancement Plan

## Executive Summary

Transform the current dashboard tabs from lightweight stat-card + quick-action layouts into **data-dense, workbench-centric command centers** with live charts, queue mirrors, insights, an Accounts/Audit tab, and deep-link "View in Workbench" buttons on the patient datatable.

---

## 1. Current State Audit

### 1.1 Dashboard Architecture

| Component | Location | Status |
|-----------|----------|--------|
| Main Dashboard | `resources/views/admin/home.blade.php` | Tab-based, role-aware |
| Tab Partials | `resources/views/admin/dashboards/tabs/{role}-tab.blade.php` | 8 tabs exist |
| Stats API | `HomeController` → `/dashboard/{role}-stats` | Returns 4 counts per tab |
| Chart API | `/dashboard/chart/revenue`, `/dashboard/chart/registrations`, `/api/chart/clinic/*` | Shared across tabs |
| JS Engine | Bottom of `home.blade.php` | Chart.js, AJAX stat fetchers, tab-switch loader |

### 1.2 Existing Tab → Workbench Mapping

| Dashboard Tab | Workbench | Route | Search Pane | Queue Filters | Quick Actions |
|---------------|-----------|-------|-------------|---------------|---------------|
| **Receptionist** | `reception/workbench.blade.php` | `reception.workbench` | ✅ Patient search | Waiting · Vitals Pending · In Consultation · Admitted | Ward Dashboard · New Patient · Quick Register · Today Stats · View Reports |
| **Biller** | `billing/workbench.blade.php` | `billing.workbench` | ✅ Patient search | All Unpaid · HMO Items · Credit Accounts | My Transactions · (2 disabled) |
| **Pharmacy/Store** | `pharmacy/workbench.blade.php` | `pharmacy.workbench` | ✅ Patient search | All · Unbilled · Billed · HMO | (various) |
| **Nursing** | `nursing/workbench.blade.php` | `nursing.workbench` | ✅ Patient search | Admitted · Vitals Queue · Bed Requests · Discharge Requests · Medication Due | Ward Dashboard · Quick Vitals · Medication Due · Shift Handover · Reports · Admission Summary |
| **Lab/Imaging** | `lab/workbench.blade.php` + `imaging/workbench.blade.php` | `lab.workbench` / `imaging.workbench` | ✅ Patient search | Billing · Sample · Results · Completed (Lab) / Billing · Results (Imaging) | New Request · View Reports · (disabled) |
| **Doctor** | *(no dedicated workbench SPA)* | `encounters.index` | N/A | N/A | Encounters · Patients · Add to Queue · Lab Results · Prescriptions · Imaging · Notes · Referrals |
| **HMO** | `hmo/workbench.blade.php` | `hmo.workbench` | ✅ | stat-cards + tabs (different layout) | (embedded in tabs) |
| **Admin** | *(no workbench)* | N/A | N/A | N/A | Roles · Patients · Clinics · Staff · Settings · Import/Export |

### 1.3 Patient Datatable (`/patients`)

- **View**: `resources/views/admin/patients/index.blade.php`
- **Controller**: `PatientController@index` → `patientsList` (server-side DataTable)
- **Columns**: `#`, `Fullname`, `File No`, `HMO`, `HMO No`, `Phone`, `Date`, `View`, `Edit`
- **Current "View"**: Single button linking to `patient.show` (patient profile)
- **Missing**: No per-workbench deep-link buttons

---

## 2. Enhancement Goals

1. **Data-dense dashboards** — Mirror workbench queue counts + charts directly on each tab
2. **Workbench-centric** — Each tab should feel like a "lite" version of its workbench
3. **Live queue widgets** — Show actual queue counts from workbench APIs on the dashboard
4. **Charts everywhere** — Add role-specific analytical charts (trends, distributions, KPIs)
5. **Insights panel** — Algorithmically-generated insights (anomalies, trends, alerts)
6. **Accounts/Audit tab** — New dashboard tab for financial overview + audit trail
7. **Patient datatable workbench buttons** — "Open in Billing / Nursing / Lab / Pharmacy / Reception" per patient

---

## 3. Implementation Plan

### Phase 1: Backend — New Dashboard API Endpoints

#### 3.1 Create `DashboardDataController`

**File**: `app/Http/Controllers/DashboardDataController.php`

This controller consolidates all enhanced dashboard data into rich, structured JSON endpoints.

```
Routes to add (in routes/web.php under dashboard section):

GET /dashboard/receptionist-queues     → queueCounts from ReceptionWorkbenchController logic
GET /dashboard/biller-queues           → unpaid/hmo/credit counts from BillingWorkbenchController logic
GET /dashboard/pharmacy-queues         → all/unbilled/billed/hmo from PharmacyWorkbenchController logic
GET /dashboard/nursing-queues          → admitted/vitals/bed-requests/discharge/medication-due
GET /dashboard/lab-queues              → billing/sample/results/completed from LabWorkbenchController logic
GET /dashboard/imaging-queues          → billing/results from ImagingWorkbenchController logic
GET /dashboard/hmo-queues              → pending-claims/approved/rejected

GET /dashboard/receptionist-insights   → algorithmic insights (busiest hours, avg wait time, trends)
GET /dashboard/biller-insights         → revenue trends, outstanding amounts, payment velocity
GET /dashboard/pharmacy-insights       → stock alerts, dispensing patterns, expiry warnings
GET /dashboard/nursing-insights        → bed occupancy rate, medication compliance, admission trends
GET /dashboard/lab-insights            → turnaround times, pending vs completed ratio, popular tests
GET /dashboard/doctor-insights         → patient load, consultation patterns, referral rates
GET /dashboard/hmo-insights            → claim approval rate, top HMOs by volume, rejection reasons

GET /dashboard/accounts-overview       → financial summary (revenue, expenses, outstanding, deposits)
GET /dashboard/audit-log               → recent audit trail entries (paginated)

GET /dashboard/chart/pharmacy-dispensing   → dispensing trend data (real, not placeholder)
GET /dashboard/chart/nursing-vitals        → actual vitals recorded trend
GET /dashboard/chart/nursing-beds          → real bed occupancy data
GET /dashboard/chart/lab-requests          → lab test request trends
GET /dashboard/chart/lab-categories        → real lab vs imaging vs pathology split
GET /dashboard/chart/hmo-claims            → claims trend over time
GET /dashboard/chart/hmo-distribution      → real HMO provider distribution
GET /dashboard/chart/doctor-consultations  → actual consultation trend data
GET /dashboard/chart/accounts-revenue-expense → revenue vs expense over time
GET /dashboard/chart/accounts-payment-methods → payment method breakdown (pie)
```

**Key Implementation Notes:**
- Reuse existing workbench controller query logic (extract to Service classes where possible)
- Each queue endpoint returns: `{ queues: [ { name, filter, count, icon, color } ] }`
- Each insight endpoint returns: `{ insights: [ { type, title, message, severity, icon } ] }`

#### 3.2 Extract Shared Query Logic into Services

Create service classes to DRY-up queries shared between workbenches and dashboard:

```
app/Services/Dashboard/
├── ReceptionDashboardService.php    → queue counts, registration trends, wait times
├── BillingDashboardService.php      → payment queues, revenue analysis, outstanding
├── PharmacyDashboardService.php     → dispensing queues, stock analysis, trends
├── NursingDashboardService.php      → bed stats, vitals queues, medication schedules
├── LabDashboardService.php          → test queues, turnaround times, volume analysis
├── ImagingDashboardService.php      → imaging queues, completion rates
├── DoctorDashboardService.php       → consultation loads, patient panels, outcomes
├── HmoDashboardService.php          → claims pipeline, approval rates, provider analysis
├── AccountsDashboardService.php     → GL summaries, audit logs, financial KPIs
└── InsightsEngine.php               → Generic insight generator (anomaly detection, trend calculation)
```

---

### Phase 2: Dashboard Tab Enhancements (Frontend)

Each tab will be restructured into this **5-section layout**:

```
┌─────────────────────────────────────────────────────────┐
│ 📊 Welcome Banner (existing, keep)                      │
├─────────────────────────────────────────────────────────┤
│ 🔢 Stat Cards Row (existing 4 cards, keep + enhance)    │
├────────────────────────────┬────────────────────────────┤
│ 📋 Live Queue Widget       │ ⚡ Quick Actions            │
│ (mirrors workbench queues) │ (existing, keep + add)     │
│ Clickable → opens workbench│                            │
├────────────────────────────┴────────────────────────────┤
│ 💡 Insights Strip                                       │
│ (auto-generated alerts, anomalies, trends)              │
├────────────────────────────┬────────────────────────────┤
│ 📈 Chart 1 (Trend/Line)    │ 📊 Chart 2 (Donut/Bar)    │
├────────────────────────────┴────────────────────────────┤
│ 📋 Recent Activity Table (mini, 5-10 rows)              │
└─────────────────────────────────────────────────────────┘
```

#### 3.3 Tab-by-Tab Enhancement Details

##### Receptionist Tab (`receptionist-tab.blade.php`)
- **Queue Widget**: Show Waiting / Vitals Pending / In Consultation / Admitted counts (live from `reception.queue-counts`)
- **New Charts**:
  - Hourly patient flow (bar chart) — busiest times today
  - Patient type breakdown (donut) — New vs Returning vs HMO
- **Insights**: "Peak hour was 10 AM with 15 patients" · "12 patients waiting > 30 min" · "Admissions up 20% vs last week"
- **Recent Table**: Last 10 patients registered/checked-in today

##### Biller Tab (`biller-tab.blade.php`)
- **Queue Widget**: All Unpaid / HMO Items / Credit Accounts counts (live from billing workbench API)
- **New Charts**:
  - Daily revenue trend (line, real data — replace current placeholder)
  - Payment method split (donut) — Cash vs POS vs Transfer vs HMO
- **Insights**: "₦450K outstanding across 38 patients" · "HMO claims up 15%" · "Average transaction: ₦12,500"
- **Recent Table**: Last 10 payments processed

##### Pharmacy/Store Tab (`pharmacy-tab.blade.php`)
- **Queue Widget**: All / Unbilled / Billed / HMO prescription counts (live)
- **New Charts**:
  - Dispensing trend (line, real data — replace placeholder)
  - Stock health (donut, real data) — In Stock vs Low Stock vs Out of Stock
- **Insights**: "23 items below reorder level" · "5 items expiring within 30 days" · "Top dispensed: Paracetamol (45 today)"
- **Recent Table**: Last 10 prescriptions dispensed

##### Nursing Tab (`nursing-tab.blade.php`)
- **Queue Widget**: Admitted / Vitals Queue / Bed Requests / Discharge Requests / Medication Due (live)
- **New Charts**:
  - Vitals activity trend (line, real data — replace placeholder)
  - Bed occupancy (donut, real data) — Occupied vs Available vs Reserved
- **Insights**: "Bed occupancy at 85%" · "4 medications overdue" · "3 discharge requests pending > 24h"
- **Recent Table**: Last 10 nursing activities (vitals taken, meds administered)

##### Lab/Imaging Tab (`lab-tab.blade.php`)
- **Queue Widget**: Awaiting Billing / Sample Collection / Result Entry / Completed + Imaging Billing / Results (live)
- **New Charts**:
  - Lab request volume trend (line, real data)
  - Service category split (donut, real data) — Lab Tests vs Imaging vs Pathology
- **Insights**: "Average turnaround: 4.2 hours" · "15 results pending > 8 hours" · "Top test: FBC (28 requests)"
- **Recent Table**: Last 10 completed tests/results

##### Doctor Tab (`doctor-tab.blade.php`)
- **Queue Widget**: *(Doctor has no traditional queue — show consultation states instead)*:
  My Queue / In Progress / Completed Today / Pending Results
- **New Charts**:
  - Consultation trend (line, real data — currently uses clinic timeline, keep)
  - Patient outcomes (donut, real data — currently uses clinic status, keep)
- **Insights**: "12 patients seen today vs 8 avg" · "3 lab results awaiting review" · "2 referrals pending"
- **Recent Table**: Last 10 encounters/consultations

##### HMO Tab (`hmo-tab.blade.php`)
- **Queue Widget**: Pending Claims / Under Review / Approved / Rejected
- **New Charts**:
  - Claims value trend (line, real data — replace placeholder)
  - Provider distribution (donut, real data) — by HMO company
- **Insights**: "Approval rate: 78%" · "₦2.3M pending settlement" · "Top rejection reason: Incomplete documentation"
- **Recent Table**: Last 10 claims submitted

##### Admin Tab (`admin-tab.blade.php`)
- **Queue Widget**: *(No queues — show system health instead)*:
  Active Users Online / Pending Tasks / Error Logs / System Alerts
- **New Charts**:
  - Revenue trend (keep existing)
  - Registration trend (keep existing)
  - **Add**: Department workload comparison (horizontal bar chart)
- **Insights**: "Revenue up 12% this month" · "45 new patients this week" · "Pharmacy stock alerts: 23"
- **Recent Table**: Last 10 system activities / audit entries

---

### Phase 3: New Accounts/Audit Dashboard Tab

#### 3.4 Create New Tab

**Files to create/modify:**

1. **`resources/views/admin/dashboards/tabs/accounts-tab.blade.php`** (new)
2. **`resources/views/admin/home.blade.php`** — Add accounts tab to `$roleTabs` array

**Tab Structure:**
```
Accessible by: SUPERADMIN, ADMIN, ACCOUNTS, BILLER

Section 1: Financial Summary Cards
├── Total Revenue (Today / Month / Year)
├── Total Expenses (Today / Month / Year)
├── Outstanding Balance (all patients)
├── Deposits Held
├── Net Profit Margin
└── HMO Receivables

Section 2: Financial Charts (2 columns)
├── Revenue vs Expenses Trend (dual-line chart)
├── Payment Method Breakdown (donut)
├── Department Revenue Split (horizontal bar)
└── Daily Cash Flow (bar chart, +/- waterfall)

Section 3: Audit Trail
├── Filter bar: Date range, User, Action type, Module
├── Recent audit entries table:
│   Timestamp | User | Action | Module | Record | Details
└── Paginated, 20 per page

Section 4: Financial KPIs
├── Average Revenue Per Patient
├── Collection Rate (billed vs collected)
├── HMO Claim Turnaround
└── Outstanding Aging (0-30, 30-60, 60-90, 90+ days)
```

**Backend Routes:**
```
GET /dashboard/accounts-stats          → summary financial cards
GET /dashboard/accounts-chart-data     → all chart datasets
GET /dashboard/audit-log               → paginated audit entries with filters
GET /dashboard/financial-kpis          → calculated KPI values
```

**Integration with Existing Accounting Module:**
- Leverage existing `routes/accounting.php` and GL/Journal infrastructure
- Pull from `patient_accounts`, `payment_transactions`, `product_or_service_requests` tables
- Audit trail from `audits` table (if using Laravel Auditing package) or `activity_log`

---

### Phase 4: Patient Datatable — Workbench View Buttons

#### 3.5 Modify Patient Index

**Files to modify:**

1. **`resources/views/admin/patients/index.blade.php`** — Add workbench action column
2. **`app/Http/Controllers/PatientController.php`** — Add workbench links to DataTable response

**Current columns**: `#, Fullname, File No, HMO, HMO No, Phone, Date, View, Edit`

**New column**: Replace single `View` with a **dropdown button group** containing:

```html
<div class="btn-group btn-group-sm">
    <a href="/patient/{id}" class="btn btn-outline-primary btn-sm" title="Profile">
        <i class="mdi mdi-account"></i>
    </a>
    <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle dropdown-toggle-split"
            data-bs-toggle="dropdown">
    </button>
    <div class="dropdown-menu dropdown-menu-end">
        <h6 class="dropdown-header">Open in Workbench</h6>
        <a class="dropdown-item" href="/reception/workbench?patient_id={id}">
            <i class="mdi mdi-desktop-mac-dashboard text-primary me-2"></i> Reception
        </a>
        <a class="dropdown-item" href="/billing-workbench?patient_id={id}">
            <i class="mdi mdi-cash-multiple text-success me-2"></i> Billing
        </a>
        <a class="dropdown-item" href="/nursing/workbench?patient_id={id}">
            <i class="mdi mdi-heart-pulse text-danger me-2"></i> Nursing
        </a>
        <a class="dropdown-item" href="/pharmacy-workbench?patient_id={id}">
            <i class="mdi mdi-pill text-warning me-2"></i> Pharmacy
        </a>
        <a class="dropdown-item" href="/lab-workbench?patient_id={id}">
            <i class="mdi mdi-flask text-info me-2"></i> Lab
        </a>
        <a class="dropdown-item" href="/imaging-workbench?patient_id={id}">
            <i class="mdi mdi-radiobox-marked text-secondary me-2"></i> Imaging
        </a>
        <a class="dropdown-item" href="/hmo/workbench?patient_id={id}">
            <i class="mdi mdi-shield-check text-purple me-2"></i> HMO
        </a>
    </div>
</div>
```

**Workbench Auto-Select Logic:**

Each workbench already has a patient search function. We need to modify each workbench controller's `index()` method to accept an optional `patient_id` query parameter and pass it to the view:

```php
// In each workbench controller:
public function index(Request $request)
{
    $preSelectedPatientId = $request->query('patient_id');
    return view('admin.{module}.workbench', compact('preSelectedPatientId'));
}
```

Then in each workbench's JavaScript, check for the pre-selected patient and auto-load:

```javascript
// At the end of $(document).ready()
var preSelectedPatientId = '{{ $preSelectedPatientId ?? "" }}';
if (preSelectedPatientId) {
    loadPatient(preSelectedPatientId);
}
```

---

### Phase 5: Shared UI Components

#### 3.6 Create Reusable Blade Components

**File**: `resources/views/components/dashboard/`

```
components/dashboard/
├── queue-widget.blade.php        → Reusable queue count widget
│   Props: $queues (array), $workbenchRoute, $title
│
├── insights-strip.blade.php      → Horizontal scrollable insight cards
│   Props: $insightsEndpoint (AJAX URL)
│
├── mini-table.blade.php          → Compact recent-activity table
│   Props: $dataEndpoint, $columns, $title
│
├── stat-card.blade.php           → Single stat card (already inline, extract)
│   Props: $label, $value, $hint, $icon, $gradient, $elementId
│
└── chart-card.blade.php          → Chart container card
    Props: $title, $subtitle, $canvasId, $icon, $iconColor
```

#### 3.7 CSS Additions (in `home.blade.php` style section)

```css
/* Queue Widget */
.dash-queue-widget { ... }
.dash-queue-item { cursor: pointer; display: flex; justify-content: space-between; ... }
.dash-queue-badge { font-weight: 700; min-width: 36px; text-align: center; ... }

/* Insights Strip */
.dash-insights { display: flex; gap: 16px; overflow-x: auto; padding: 4px 0; }
.dash-insight-card { min-width: 280px; flex-shrink: 0; border-radius: 12px; ... }
.dash-insight-card.warning { border-left: 4px solid #f59e0b; }
.dash-insight-card.danger  { border-left: 4px solid #ef4444; }
.dash-insight-card.success { border-left: 4px solid #10b981; }
.dash-insight-card.info    { border-left: 4px solid #3b82f6; }

/* Mini Table */
.dash-mini-table { font-size: 0.85rem; }
.dash-mini-table th { font-weight: 600; color: #6b7280; text-transform: uppercase; font-size: 0.7rem; }
```

---

## 4. Implementation Order & Effort Estimates

| # | Task | Files | Effort | Dependencies |
|---|------|-------|--------|--------------|
| 1 | Create `DashboardDataController` with queue endpoints | 1 controller, routes/web.php | 3-4 hrs | None |
| 2 | Create Dashboard Service classes | 8-9 service files | 6-8 hrs | Task 1 |
| 3 | Create Blade components (queue-widget, insights-strip, etc.) | 5 component files | 2-3 hrs | None |
| 4 | Add Accounts/Audit tab (new) | 1 blade + home.blade.php + controller methods | 4-5 hrs | Tasks 1,2 |
| 5 | Enhance Receptionist tab | 1 blade file | 2-3 hrs | Tasks 1-3 |
| 6 | Enhance Biller tab | 1 blade file | 2-3 hrs | Tasks 1-3 |
| 7 | Enhance Pharmacy tab | 1 blade file | 2-3 hrs | Tasks 1-3 |
| 8 | Enhance Nursing tab | 1 blade file | 2-3 hrs | Tasks 1-3 |
| 9 | Enhance Lab/Imaging tab | 1 blade file | 2-3 hrs | Tasks 1-3 |
| 10 | Enhance Doctor tab | 1 blade file | 2-3 hrs | Tasks 1-3 |
| 11 | Enhance HMO tab | 1 blade file | 2-3 hrs | Tasks 1-3 |
| 12 | Enhance Admin tab | 1 blade file | 2-3 hrs | Tasks 1-3 |
| 13 | Update JS engine in `home.blade.php` | 1 file | 3-4 hrs | Tasks 4-12 |
| 14 | Patient datatable workbench buttons | 2 files (view + controller) | 2-3 hrs | None |
| 15 | Workbench auto-select patient | 7 controller + 7 blade files | 3-4 hrs | Task 14 |
| 16 | Replace placeholder chart data with real queries | Controller methods | 3-4 hrs | Tasks 1-2 |

**Total estimated effort: ~40-55 hours**

---

## 5. File Change Summary

### New Files
```
app/Http/Controllers/DashboardDataController.php
app/Services/Dashboard/ReceptionDashboardService.php
app/Services/Dashboard/BillingDashboardService.php
app/Services/Dashboard/PharmacyDashboardService.php
app/Services/Dashboard/NursingDashboardService.php
app/Services/Dashboard/LabDashboardService.php
app/Services/Dashboard/ImagingDashboardService.php
app/Services/Dashboard/DoctorDashboardService.php
app/Services/Dashboard/HmoDashboardService.php
app/Services/Dashboard/AccountsDashboardService.php
app/Services/Dashboard/InsightsEngine.php
resources/views/admin/dashboards/tabs/accounts-tab.blade.php
resources/views/components/dashboard/queue-widget.blade.php
resources/views/components/dashboard/insights-strip.blade.php
resources/views/components/dashboard/mini-table.blade.php
resources/views/components/dashboard/stat-card.blade.php
resources/views/components/dashboard/chart-card.blade.php
```

### Modified Files
```
routes/web.php                                          → new dashboard API routes
resources/views/admin/home.blade.php                    → add accounts tab, new CSS, enhanced JS
resources/views/admin/dashboards/tabs/receptionist-tab.blade.php  → queue + insights + charts
resources/views/admin/dashboards/tabs/biller-tab.blade.php        → queue + insights + charts
resources/views/admin/dashboards/tabs/pharmacy-tab.blade.php      → queue + insights + charts
resources/views/admin/dashboards/tabs/nursing-tab.blade.php       → queue + insights + charts
resources/views/admin/dashboards/tabs/lab-tab.blade.php           → queue + insights + charts
resources/views/admin/dashboards/tabs/doctor-tab.blade.php        → queue + insights + charts
resources/views/admin/dashboards/tabs/hmo-tab.blade.php           → queue + insights + charts
resources/views/admin/dashboards/tabs/admin-tab.blade.php         → queue + insights + charts
resources/views/admin/patients/index.blade.php                    → workbench dropdown buttons
app/Http/Controllers/PatientController.php                        → workbench link in DataTable
app/Http/Controllers/HomeController.php                           → accounts/audit methods
app/Http/Controllers/ReceptionWorkbenchController.php             → accept patient_id param
app/Http/Controllers/BillingWorkbenchController.php               → accept patient_id param
app/Http/Controllers/PharmacyWorkbenchController.php              → accept patient_id param
app/Http/Controllers/NursingWorkbenchController.php               → accept patient_id param
app/Http/Controllers/LabWorkbenchController.php                   → accept patient_id param
app/Http/Controllers/ImagingWorkbenchController.php               → accept patient_id param
app/Http/Controllers/HmoWorkbenchController.php                   → accept patient_id param
```

---

## 6. Technical Notes

### Chart.js Usage
- Already loaded via `plugins/chartjs/Chart.js`
- Use existing `renderLineChart()` and `renderDoughnutChart()` helpers
- Add new helpers: `renderBarChart()`, `renderHorizontalBarChart()`, `renderWaterfallChart()`

### Data Freshness
- Stats: Auto-refresh every 60 seconds (already implemented)
- Queues: Should refresh every 30 seconds for real-time feel
- Insights: Refresh on tab switch only (heavier queries)
- Charts: Refresh on tab switch only

### Performance Considerations
- Queue count queries should use `COUNT(*)` with proper indexes
- Cache insight computations for 5 minutes (`Cache::remember()`)
- Use DB raw queries for aggregate chart data (not Eloquent collections)
- Lazy-load chart data only when tab becomes visible (already implemented via `loadTabData()`)

### Workbench Deep-Links
- All workbenches use SPA-style JavaScript
- Pass `patient_id` as query param: `?patient_id=123`
- Each workbench's JS already has a `loadPatient(id)` or `selectPatient(id)` function
- Just call it on `$(document).ready()` if param exists

---

## 7. Suggested Implementation Sequence

```
Week 1: Foundation
├── Day 1-2: DashboardDataController + routes + 3 service classes
├── Day 3: Blade components (queue-widget, insights, chart-card, stat-card)
├── Day 4-5: Accounts/Audit tab (new)

Week 2: Dashboard Tabs
├── Day 1: Receptionist + Biller tabs
├── Day 2: Nursing + Pharmacy tabs
├── Day 3: Lab + Doctor tabs
├── Day 4: HMO + Admin tabs
├── Day 5: JS engine updates, integration testing

Week 3: Patient Deep-Links + Polish
├── Day 1: Patient datatable workbench buttons
├── Day 2: Workbench auto-select patient (all 7)
├── Day 3: Replace all placeholder chart data
├── Day 4-5: Testing, performance tuning, responsive fixes
```
