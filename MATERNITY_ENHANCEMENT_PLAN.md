# Maternity Workbench — Comprehensive Enhancement Plan

> **Generated:** 2025-02-27 | **Updated:** 2026-02-27  
> **Module:** `resources/views/admin/maternity/workbench.blade.php`  
> **Controller:** `app/Http/Controllers/MaternityWorkbenchController.php`  
> **Routes:** `routes/maternity_workbench.php` — 77 routes  
> **Tabs:** Overview · Enrollment · Mother's History · ANC Visits · Clinical Orders · Delivery · Baby Records · Postnatal · Immunization · Vitals · Notes · Audit Trail (12 total)

---

## Current Status Summary

| Area | Create | Read | Update | Delete | Backend Ready |
|------|--------|------|--------|--------|---------------|
| Enrollment | ✅ | ✅ | ⚠️ Partial | N/A | ✅ Full |
| Medical History | ✅ | ✅ | ✅ | ✅ | ✅ Full |
| Previous Pregnancies | ✅ | ✅ | ✅ | ✅ | ✅ Full |
| ANC Visits | ✅ | ✅ | ✅ | ❌ | ✅ Full |
| Clinical Orders | ✅ | ✅ | ✅ | ✅ | ✅ Full |
| Delivery Record | ✅ | ✅ | ✅ | N/A | ✅ Full |
| Partograph | ✅ Entry form | ✅ Table + chart | ⚠️ Edit pending | N/A | ✅ Full |
| Baby Records | ✅ | ✅ | ✅ | ❌ | ✅ Full |
| Growth Records | ✅ | ✅ table | ❌ | ❌ | ⚠️ Create only |
| Growth Charts (visual) | N/A | ❌ | N/A | N/A | ✅ WHO data ready |
| Postnatal Visits | ✅ | ✅ | ✅ | ❌ | ✅ Full |
| Immunization | ✅ Schedule-based | ✅ Mother + Baby tabs | ✅ Status/Administer | N/A | ✅ Phase 6 integrated |
| Immunization History | N/A | ✅ Timeline/Calendar/Table | N/A | N/A | ✅ Unified endpoints |
| Notes | ✅ | ✅ | ✅ | ✅ | ✅ Full |
| Charts / Graphs | N/A | ❌ None | N/A | N/A | Data available |
| Print Cards | ✅ | ✅ | N/A | N/A | ✅ Routes exist |

---

## Phase 1 — Critical Fixes & Header Polish ✅ COMPLETED

### 1.1 Header Button Visibility ✅
**Problem:** `.workspace-navbar` has `display: none` on desktop; clinical context button and toggle search sidebar button invisible.  
**Fix:** Added to `@media (min-width: 768px)`:
```css
.workspace-navbar { display: flex !important; }
.btn-back-to-search { display: none !important; }
.btn-toggle-search { display: flex !important; }
```
**Status:** Done

### 1.2 Print Buttons on Patient Header ✅
**Problem:** Print ANC Card and Road to Health Card buttons only existed in the Quick Actions sidebar, not on the patient header bar.  
**Fix:** Added two buttons (`#btn-print-anc-card`, `#btn-print-road-card`) to the patient-header-top div, shown/hidden based on enrollment existence. They open in a new tab:
- `GET /maternity-workbench/enrollment/{id}/print-anc-card`
- `GET /maternity-workbench/enrollment/{id}/print-road-health-card`

**Status:** Done

### 1.3 Prior Fixes (already applied)
- ✅ `delivery_records` schema migration — 10 columns added, 4 dropped
- ✅ `editLabResult()` / `enterLabResult()` wrapper functions added
- ✅ Episiotomy null constraint fix (`?? 'none'`)
- ✅ All 14 Bootstrap modal interactions fixed for Bootstrap 4 API
- ✅ CKEditor modal lifecycle management corrected

---

## Phase 2 — Edit/Delete UI for All Data Types ✅ COMPLETED

> **Pattern:** Each data type gets an **Edit** pencil icon button on its card/row that opens a pre-filled Bootstrap 4 modal, and a **Delete** button (where applicable) with a confirm() dialog.
> All modals use `$('#modal').modal('show'/'hide')` — **never** `bootstrap.Modal` (we are on Bootstrap 4).

### 2.1 ANC Visits — Edit ✅
- Edit pencil button on each ANC visit card header
- `editAncVisit(id)` pre-fills `#ancVisitModal` with raw field values
- Shared modal toggles between create (POST) and edit (PUT) modes via `window._ancEditMode`
- CKEditor re-initialized for clinical_notes

### 2.2 Delivery Record — Edit ✅
- "Edit Delivery" button on `renderDeliveryDetails()` header
- `editDeliveryRecord(id)` re-renders the delivery form pre-filled, changes submit to PUT
- CKEditor fields for complications and notes pre-populated

### 2.3 Baby Records — Edit ✅
- Edit pencil on each baby card header
- `editBaby(id)` pre-fills `#registerBabyModal` with cached baby data
- Shared modal toggles between register (POST) and edit (PUT) via `window._babyEditMode`
- Immunization checkboxes hidden in edit mode

### 2.4 Postnatal Visits — Edit ✅
- Edit pencil on each postnatal visit card
- `editPostnatalVisit(id)` pre-fills `#postnatalModal` with cached raw fields
- CKEditor re-initialized for clinical_notes

### 2.5 Previous Pregnancies — Edit & Delete ✅
- Actions column with edit/delete icons on each table row
- `editPreviousPregnancy(id)` pre-fills `#addPregnancyModal`, toggles to PUT mode
- `deletePreviousPregnancy(id)` with confirm() then AJAX DELETE
- Backend routes: `PUT /prev-pregnancy/{id}`, `DELETE /prev-pregnancy/{id}`

### 2.6 Medical History — Edit & Delete ✅
- Actions column with edit/delete icons on each table row
- `editMedicalHistory(id)` pre-fills `#addHistoryModal`, toggles to PUT mode
- `deleteMedicalHistory(id)` with confirm() then AJAX DELETE
- Backend routes: `PUT /medical-history/{id}`, `DELETE /medical-history/{id}`

### 2.7 Notes — Edit & Delete ✅
- Edit/delete icons on note cards (only when `can_edit` is true — ownership + time window)
- `editNote(id)` re-opens CKEditor modal with note content, toggles to PUT mode
- `deleteNote(id)` with confirm() then AJAX DELETE
- Backend routes: `PUT /note/{id}`, `DELETE /note/{id}`

---

## Phase 3 — Partograph (Frontend Implementation) 🔄 IN PROGRESS

> **Priority: CRITICAL** — Backend is 100% ready (migration, model, 2 controller methods, 2 routes), but frontend is 0%.

### Phase 3 Implementation Delta (Started)
- ✅ Added initial Partograph UI in Delivery tab (Add Entry modal + entries table)
- ✅ Added explanatory clinical form sections and validation for core fields
- ✅ Normalized controller save/read contract to tolerate legacy/new column variants
- ✅ Added normalized partograph response payload for stable frontend rendering
- ⚠️ Pending: advanced chart visualization (alert/action line and multi-axis trend)

### 3.1 Partograph Data Entry Form
**Uses:** `POST /delivery/{id}/partograph` → `savePartographEntry()`

**Modal form fields** (from `delivery_partograph` migration):
| Section | Fields |
|---------|--------|
| **Timing** | `recorded_at` (datetime) |
| **Cervical** | `cervical_dilation` (0–10 cm), `descent_of_head` (0–5) |
| **Contractions** | `contractions_per_10min` (count), `contraction_duration` (seconds enum) |
| **Fetal** | `fetal_heart_rate` (bpm), `amniotic_fluid` (C/I/M/B/A), `moulding` (0/+/++/+++) |
| **Maternal Vitals** | `maternal_pulse`, `maternal_bp_systolic`, `maternal_bp_diastolic`, `maternal_temp` |
| **Other** | `oxytocin_drops`, `urine_volume`, `urine_protein`, `urine_acetone`, `notes` |

**UI:** Bootstrap 4 modal with grouped sections, opened from a "Add Entry" button in the partograph section.

### 3.2 Partograph Chart (WHO Standard)
**Uses:** `GET /delivery/{id}/partograph` → `getPartographEntries()`

**Visual Component** — Chart.js multi-axis chart:
1. **Cervical Dilation Plot** (primary) — Y-axis: 0–10 cm, X-axis: hours since admission
   - Alert line (starts at 4 cm, rises 1 cm/hour)
   - Action line (4 hours right of alert line)
   - Patient's actual dilation plotted as connected points
2. **Descent of Head** — plotted on same time axis
3. **FHR** — strip chart above main chart
4. **Contractions** — visual blocks below main chart
5. **Maternal Vitals Table** — below the chart

**Implementation approach:**
- Load Chart.js (already available in project, just not loaded in maternity)
- Use `chart.js` with `chartjs-plugin-annotation` for alert/action lines
- Responsive, printable layout
- Render inside the Delivery tab, below delivery details, visible when delivery exists

### 3.3 Partograph Entry Table
**Fallback/companion** to chart:
- DataTable of all partograph entries sorted by `recorded_at`
- Columns: Time, Dilation, Descent, FHR, Contractions, Liquor, Moulding, BP, Pulse, Temp
- Row click to edit entry

---

## Phase 4 — ANC Trend Charts

> **Priority: HIGH** — Adds clinical decision support by visualizing trends.
> **Needs:** Chart.js loaded in workbench.

### 4.1 Load Chart.js
Add to the blade `@section` or `@push('scripts')`:
```html
<script src="{{ asset('assets/libs/chart.js/chart.min.js') }}"></script>
```

### 4.2 Blood Pressure Trend
- **Data Source:** ANC visits → `bp_systolic`, `bp_diastolic` per visit date
- **Chart Type:** Line chart with dual lines (systolic red, diastolic blue)
- **Reference Lines:** 140/90 mmHg (pre-eclampsia threshold)
- **Location:** ANC Visits tab, above visit cards (collapsible panel)

### 4.3 Weight Gain Trend
- **Data Source:** ANC visits → `weight` per visit date + enrollment `pre_pregnancy_weight`
- **Chart Type:** Line chart showing weight trajectory
- **Reference Band:** Expected gain range (0.5–2 kg/month in 2nd/3rd trimester)
- **Location:** ANC Visits tab, collapsible panel

### 4.4 Fundal Height vs Gestational Age
- **Data Source:** ANC visits → `fundal_height` per `gestational_age`
- **Chart Type:** Scatter/line with McDonald's rule reference (GA ± 2 cm)
- **Clinical Value:** Detects IUGR or macrosomia
- **Location:** ANC Visits tab, collapsible panel

### 4.5 Haemoglobin Trend
- **Data Source:** ANC visits → `haemoglobin` per visit date
- **Chart Type:** Line chart
- **Reference Lines:** 11 g/dL (normal threshold), 7 g/dL (severe anaemia)
- **Location:** ANC Visits tab, collapsible panel

### 4.6 Combined ANC Dashboard
- All 4 charts in a responsive 2×2 grid at the top of the ANC tab
- Toggle: "Show/Hide Trends" button
- Charts auto-update when new ANC visit is saved

---

## Phase 5 — Baby Growth Charts (WHO Standards)

> **Priority: HIGH** — Physical Road to Health card shows growth curves; digital version should match.
> **Backend Ready:** `WhoGrowthStandard` model + seeder, `getGrowthChartData()` controller method, `GET /baby/{id}/growth-chart` route.

### 5.1 Weight-for-Age Chart
- **WHO Standard:** Weight (kg) vs Age (months), separate curves for boys/girls
- **Lines:** –3SD, –2SD, Median, +2SD, +3SD (colour-coded zones)
- **Patient Data:** Overlay baby's actual weight measurements
- **Range:** Birth to 60 months (matching physical Road to Health card)
- **Chart Type:** Chart.js line chart with filled zones

### 5.2 Length/Height-for-Age Chart
- Same format as 5.1 but for length/height
- Switch from "length" to "height" at 24 months

### 5.3 Head Circumference-for-Age Chart
- Same format for head circumference

### 5.4 Growth Chart Location
- Replace current HTML table in baby's growth section with tabbed charts
- Tabs: Weight | Length/Height | Head Circumference
- Keep "Add Growth Record" modal as-is
- Chart auto-refreshes after new growth record saved

### 5.5 Z-Score Display
- Calculate and display z-score for each measurement
- Colour-coded: green (normal), yellow (moderate), red (severe)
- Show on growth record table AND on baby card

---

## Phase 6 — Unified Immunization System (Maternity ↔ Nursing Parity)

> **Priority: HIGH** — Current maternity immunization is a basic hardcoded schedule with no billing/stock.
> **Goal:** Merge maternity immunization with the nursing workbench's `PatientImmunizationSchedule` system, reuse the same UI components with zero code duplication, add mother immunization, and add proper subtabs.

### Architecture Overview

```
Immunization Tab
├── Mother Sub-tab
│   ├── Schedule & Administer (sub-sub-tab)
│   │   └── Uses PatientImmunizationSchedule + "Nigeria ANC Maternal Schedule" template
│   └── History & Timeline (sub-sub-tab)
│       └── Timeline / Calendar / Table views (reused from nursing)
├── Baby 1 Sub-tab (Baby Name)
│   ├── Schedule & Administer
│   │   └── Uses PatientImmunizationSchedule + "Nigeria NPI Schedule" template
│   └── History & Timeline
│       └── Timeline / Calendar / Table views (reused from nursing)
├── Baby 2 Sub-tab (if twins/multiples)
│   └── ... same structure ...
└── (one sub-tab per registered baby)
```

### 6.1 Seed Mother ANC Immunization Schedule Template ⭐ HIGH
**File:** `database/seeders/MaternalVaccineScheduleSeeder.php` (new)

Create `VaccineScheduleTemplate` named "Nigeria ANC Maternal Schedule" with items:

| Vaccine | Code | Dose # | GA/Timing | Route | Site |
|---------|------|--------|-----------|-------|------|
| Tetanus Diphtheria (Td) | TD-1 | 1 | First ANC visit (or ≥20 weeks) | IM | Left Deltoid |
| Tetanus Diphtheria (Td) | TD-2 | 2 | 4 weeks after TD-1 | IM | Left Deltoid |
| Tetanus Diphtheria (Td) | TD-3 | 3 | 6 months after TD-2 (if needed) | IM | Left Deltoid |
| IPTp-SP (Malaria) | IPTP-1 | 1 | 13 weeks GA (2nd trimester start) | Oral | Mouth |
| IPTp-SP (Malaria) | IPTP-2 | 2 | 20 weeks GA | Oral | Mouth |
| IPTp-SP (Malaria) | IPTP-3 | 3 | 28 weeks GA | Oral | Mouth |
| IPTp-SP (Malaria) | IPTP-4 | 4 | 32 weeks GA (optional) | Oral | Mouth |
| Iron/Folic Acid | IFA-1 | 1 | First ANC visit | Oral | Mouth |
| Deworming (Albendazole) | DEWORM | 1 | 2nd trimester | Oral | Mouth |

**Notes:**
- The `age_days` field will be calculated from enrollment LMP (not DOB as with babies)
- Template `is_default` = false, `is_active` = true, `country` = 'Nigeria'
- Uses same `VaccineScheduleTemplate` / `VaccineScheduleItem` models as nursing

### 6.2 Shared Immunization JS Module ⭐ HIGH
**File:** `public/js/immunization-module.js` (new)

Extract from nursing workbench's JS into a reusable module:

```js
window.ImmunizationModule = {
    // Core schedule rendering — renders age-grouped timeline
    renderScheduleTimeline(response, containerId, options),

    // Schedule loading — AJAX to get schedule
    loadSchedule(patientId, containerId, baseUrl, options),

    // Schedule generation
    generateSchedule(patientId, baseUrl, templateId, onSuccess),

    // Open administer modal (reuses #administerVaccineModal)
    openAdministerModal(scheduleId, patientId, baseUrl),

    // Skip/Contraindicate actions
    skipVaccine(scheduleId, baseUrl, onSuccess),
    contraindicateVaccine(scheduleId, baseUrl, onSuccess),

    // History views — timeline, calendar, table
    loadTimeline(patientId, containerId, baseUrl),
    loadCalendar(patientId, containerId, baseUrl),
    loadHistoryTable(patientId, containerId, tableId, baseUrl),

    // Template loader
    loadTemplates(selectId, baseUrl),
};
```

**Key design:**
- All functions accept a `baseUrl` parameter (nursing = `/nursing-workbench`, maternity = `/maternity-workbench`)
- All functions accept a `patientId` (for mother, it's the mother's patient_id; for babies, it's the baby's patient_id)
- The module is loaded once via `<script>` in both workbenches
- The administer modal HTML is included once per page (already exists in nursing, add to maternity)
- CSRF token obtained from `$('meta[name="csrf-token"]')` or global `CSRF_TOKEN`

### 6.3 Maternity Controller — Upgrade to Schedule System ⭐ HIGH
**Replace** the hardcoded immunization methods in `MaternityWorkbenchController` with proxies to the existing `PatientImmunizationSchedule` model:

| Old Method | New Behavior |
|-----------|-------------|
| `getImmunizationSchedule($babyId)` | Proxy to `getPatientSchedule($baby->patient_id)` using `PatientImmunizationSchedule` model |
| `administerImmunization($babyId)` | Remove — use schedule-based administration instead |
| `administerFromSchedule($babyId)` | Proxy to full `administerFromScheduleNew()` with billing + stock |

**New methods to add:**

| Method | Route | Purpose |
|--------|-------|---------|
| `getMotherSchedule($enrollmentId)` | `GET /enrollment/{id}/mother-schedule` | Get mother's immunization schedule |
| `generateMotherSchedule($enrollmentId)` | `POST /enrollment/{id}/generate-mother-schedule` | Generate mother's schedule from maternal template |
| `getMotherImmunizationHistory($enrollmentId)` | `GET /enrollment/{id}/mother-immunization-history` | Get mother's immunization records |
| `getBabySchedule($babyId)` | Refactored `getImmunizationSchedule()` — uses `PatientImmunizationSchedule` model |
| `generateBabySchedule($babyId)` | `POST /baby/{id}/generate-schedule` | Auto-generate NPI schedule for baby |
| `getBabyImmunizationHistory($babyId)` | Already exists — keep as-is |
| `administerFromScheduleMaternity()` | `POST /administer-from-schedule` | Unified administer endpoint (same as nursing) |
| `getScheduleTemplatesMaternity()` | `GET /schedule-templates` | Proxy to nursing's template list |
| `updateScheduleStatusMaternity($scheduleId)` | `PUT /schedule/{id}/status` | Proxy to skip/contraindicate |
| `getVaccineProductsMaternity($vaccineName)` | `GET /vaccine-products/{name}` | Proxy to nursing's product mapper |

### 6.4 Maternity Routes — Immunization Block ⭐ HIGH
**Replace** existing immunization routes with:

```php
// ── Immunization (unified with nursing schedule system) ──
// Mother schedule
Route::get('/enrollment/{id}/mother-schedule', 'getMotherSchedule');
Route::post('/enrollment/{id}/generate-mother-schedule', 'generateMotherSchedule');
Route::get('/enrollment/{id}/mother-immunization-history', 'getMotherImmunizationHistory');

// Baby schedule (per baby)
Route::get('/baby/{id}/schedule', 'getBabySchedule');
Route::post('/baby/{id}/generate-schedule', 'generateBabySchedule');
Route::get('/baby/{id}/immunization-history', 'getBabyImmunizationHistory');

// Shared administration endpoints
Route::post('/administer-from-schedule', 'administerFromScheduleMaternity');
Route::put('/schedule/{id}/status', 'updateScheduleStatusMaternity');
Route::get('/schedule-templates', 'getScheduleTemplatesMaternity');
Route::get('/vaccine-products/{name}', 'getVaccineProductsMaternity');

// Keep existing for backwards compat (but deprecated)
Route::get('/baby/{id}/immunization-schedule', 'getImmunizationSchedule');
```

### 6.5 Maternity Blade — Immunization Tab Redesign ⭐ HIGH
**Replace** the current simple `#immunization-content` div with:

```html
<div class="workspace-tab-content" id="immunization-tab">
    <div class="p-3">
        <!-- Dynamic sub-tabs: Mother + one per baby -->
        <ul class="nav nav-tabs mb-3" id="imm-person-tabs"></ul>
        <div class="tab-content" id="imm-person-content"></div>
    </div>
</div>
```

**JS `loadImmunizationTab()`** becomes:
1. Fetch enrollment data to get mother patient_id + list of babies
2. Dynamically build sub-tabs: "Mother (Maternal Name)" + "Baby 1 (Baby Name)" + "Baby 2..." etc.
3. Under each person sub-tab, render sub-sub-tabs: "Schedule & Administer" + "History & Timeline"
4. Each uses `ImmunizationModule.loadSchedule(patientId, containerId, baseUrl)` and `ImmunizationModule.loadTimeline(patientId, containerId, baseUrl)`
5. Auto-generate schedule if none exists (using default template — NPI for babies, Maternal for mother)

### 6.6 Administer Vaccine Modal — Shared ⭐ HIGH
**Copy** the `#administerVaccineModal` HTML from nursing workbench into maternity workbench blade.
- Same 3-step wizard: Store Selection → Product Search → Administration Details
- Same batch selection with FIFO
- Same stock validation
- JS handlers already in shared module (`ImmunizationModule.openAdministerModal`)

### 6.7 Auto-Schedule Generation
When a baby is registered or a mother is enrolled:
- **Baby:** Auto-generate NPI schedule using `PatientImmunizationSchedule::generateForPatient()`
- **Mother:** Auto-generate Maternal schedule on enrollment (or first immunization tab load)
- If schedule already exists, show it; don't regenerate

### 6.8 Migration Considerations
- No schema changes needed — `PatientImmunizationSchedule` table already has `patient_id` FK
- Mother's `patient_id` comes from the enrollment's patient record
- Baby's `patient_id` comes from `MaternityBaby.patient_id`
- The `VaccineScheduleItem.age_days` for maternal vaccines will be calculated from LMP (enrollment) instead of DOB
  - This requires a small tweak to `calculateDueDate()` or the controller logic

---

## Phase 7 — Overview Tab Enhancement

### 7.1 Stat Cards (already exist, enhance)
- Current: 4 stat cards (Overview numbers)
- Add: Risk score indicator, days to EDD countdown, BMI trend indicator

### 7.2 Pregnancy Progress Bar
- Visual progress bar: LMP → Current GA → EDD
- Show trimester divisions (0–12, 13–28, 29–40 weeks)
- Colour changes: green (normal) → yellow (near term) → red (post-dates)

### 7.3 Quick Summary Cards
- **Latest Vitals** mini-card (BP, weight, temperature from last visit)
- **Next Appointment** prominent card with countdown
- **Alerts** card: overdue vaccines, missed appointments, abnormal lab results, high-risk flags
- **Risk Factors** sidebar based on medical history + ANC data

### 7.4 Timeline Enhancement
- Current timeline exists but could add icons per event type
- Add clickable events → navigate to the relevant tab/record
- Add colour-coding by event category

---

## Phase 8 — Reports & Analytics Enhancement

### 8.1 Visual Charts for Reports
Currently all reports render as HTML tables. Add Chart.js visualizations:

| Report | Chart Type | Data |
|--------|-----------|------|
| Delivery Stats | Donut/Pie | SVD vs C/S vs Assisted vs Breech |
| Monthly Deliveries | Bar chart | Deliveries per month (12-month trend) |
| Immunization Coverage | Stacked Bar | % coverage per vaccine |
| ANC Defaulters | Horizontal Bar | Days since last visit per patient |
| High-Risk Register | Table + Gauge | Risk factors distribution |

### 8.2 Export Options
- Add "Export to CSV" button on each report table
- Add "Print Report" button for each section

---

## Phase 9 — Print Cards Enhancement

### 9.1 ANC Card Print Layout
Match the physical Nigerian ANC card format from reference photos:
- **Page 1:** Patient demographics, blood group, genotype, EDD, risk factors
- **Page 2:** ANC visit tracking table with columns: Date, Height of Fundus, Presentation & Position, Foetal Heart, Oedema, Urine (Album/Sugar), Weight, H/B, B.P., Treatment
- **Page 3:** Summary of delivery, record of baby, previous pregnancies grid

### 9.2 Road to Health Card Print Layout
Match the physical Road to Health card:
- **Cover:** Baby details (name, DOB, sex, birth weight, hospital number)
- **Inside Left:** WHO growth chart (weight-for-age, birth to 5 years) with plotted points
- **Inside Right:** Immunization schedule table with dates administered
- **Back:** Developmental milestones checklist

### 9.3 Print Infrastructure
- Both print routes exist and redirect to dedicated Blade views
- Ensure `@media print` CSS is comprehensive
- Add print button to patient header ✅ (already done)

---

## Phase 10 — Enrollment Tab Polish

### 10.1 Enrollment Form Enhancement
- Current form captures: LMP, EDD, blood_group, genotype, gravida, para, alive, gestational_age, risk_factors, pre_pregnancy_weight, height, hiv_status
- Add visual pregnancy wheel calculator (LMP → auto-calculate EDD and GA)
- Add BMI auto-calculation from pre_pregnancy_weight and height
- Better risk factors display with severity badges
- Status badge bar: Active → Delivered → Postnatal → Discharged

### 10.2 Enrollment Status Transitions
- Add "Close Enrollment" action with reason (delivered, transferred, LAMA, deceased)
- Add "Reopen" for corrections
- Status change triggers audit trail entry

---

## Master Implementation TODO (Granular, End-to-End)

> Use this as the execution checklist for all phases.  
> **Rule:** No phase is marked complete until **schema columns, controller payloads, and UI bindings** are all verified against latest migrations/models.

### Global Gates (apply to every phase)

- [ ] **G0.1 Column Baseline Snapshot**
    - [ ] For each touched table, capture latest columns from migration + model `$fillable` + `$casts`
    - [ ] Create/refresh per-phase "Column Contract" table in implementation notes
    - [ ] Flag deprecated/legacy aliases (e.g., old naming variants) and decide canonical field per feature
- [ ] **G0.2 Controller/UI Contract Check**
    - [ ] Verify request validation keys exactly match canonical column names
    - [ ] Verify controller `create/update` arrays map 1:1 to canonical columns
    - [ ] Verify frontend form `name` attributes match backend request keys
    - [ ] Verify response payload keys used in JS renderers exist and are correctly cased
- [ ] **G0.3 UX Quality Gate**
    - [ ] Every form section has explanatory labels + helper text for clinical meaning
    - [ ] Required fields are visibly marked and validated before submit
    - [ ] Empty/loading/error states are user-friendly and actionable
    - [ ] Success/error toast messaging is specific (not generic "failed")
    - [ ] Edit mode clearly distinguished from create mode
- [ ] **G0.4 Verification Gate**
    - [ ] PHP syntax/lint for changed controllers/routes/seeders
    - [ ] Browser verification for happy path + no-data path + validation errors
    - [ ] Re-open records and confirm persisted values render correctly
    - [ ] Confirm no JS console errors in touched tabs

---

### Phase 1 TODO — Header Fixes (Hardening Pass)

- [ ] Re-verify desktop/mobile navbar behavior and clinical context buttons
- [ ] Confirm print buttons only appear when enrollment context exists
- [ ] Validate print URLs are generated with correct enrollment ID and role access
- [ ] UX polish: loading indicator before opening print view in new tab

### Phase 2 TODO — Edit/Delete Completeness (Regression Pass)

- [ ] **ANC Edit**
    - [ ] Confirm modal prefill uses canonical ANC columns (`visit_date`, `blood_pressure_systolic`, `blood_pressure_diastolic`, `fundal_height_cm`, `fetal_heart_rate`, `haemoglobin`, `clinical_notes`)
    - [ ] Verify update endpoint writes same fields and returns refreshed record
- [ ] **Delivery Edit**
    - [ ] Verify form fields map to canonical delivery columns (`delivery_date`, `delivery_time`, `duration_of_labour_hours`, `blood_loss_ml`, `placenta_complete`, `perineal_tear_degree`, `episiotomy`, `complications`, `notes`)
- [ ] **Baby Edit**
    - [ ] Verify baby update payload keys align with model columns (`birth_weight_kg`, `length_cm`, `head_circumference_cm`, etc.)
- [ ] **Postnatal Edit**
    - [ ] Verify postnatal payload keys match model/migration columns (`general_condition`, `blood_pressure`, `temperature_c`, `family_planning_method`, etc.)
- [ ] **Medical History / Previous Pregnancy / Notes Delete**
    - [ ] Verify soft-delete behavior and list refresh states after delete
    - [ ] Verify deleted rows are excluded from normal read endpoints

### Phase 3 TODO — Partograph Frontend (New Implementation)

- [ ] **P3.1 Column Contract (must verify first)**
    - [ ] Confirm exact table columns for `delivery_partograph` in latest migration
    - [ ] Align with model fields and controller request keys before UI build
- [ ] **P3.2 Data Entry UI**
    - [ ] Build modal/form sections: timing, cervical progress, contractions, fetal status, maternal vitals, urine/medications
    - [ ] Add field-level helper tips for clinical interpretation
    - [ ] Add required validation and range constraints (e.g., dilation 0–10)
- [ ] **P3.3 Persistence Contract**
    - [ ] POST payload keys must exactly match controller + DB columns
    - [ ] Verify response re-renders latest entries without page reload
- [ ] **P3.4 Visualization**
    - [ ] Render partograph trend visualization (dilation/descent/fetal/maternal)
    - [ ] Include alert/action guidance lines where applicable
    - [ ] Add fallback DataTable view for audit/readability
- [ ] **P3.5 QA**
    - [ ] Create 3 sample entries with varying times; verify order and display
    - [ ] Edit/reload tab; ensure values persist and chart updates

### Phase 4 TODO — ANC Trend Charts

- [ ] **P4.1 Data Contract Verification**
    - [ ] Verify ANC source columns exist and are populated (`visit_date`, BP split columns, `weight_kg`, `fundal_height_cm`, `haemoglobin`)
- [ ] **P4.2 Chart Data Preparation**
    - [ ] Normalize null/missing values
    - [ ] Sort all series by visit date
    - [ ] Add safe handling when fewer than 2 points exist
- [ ] **P4.3 Chart UI/UX**
    - [ ] Add collapsible "Trends" panel with explanatory legends
    - [ ] Ensure mobile readability and responsive layout
    - [ ] Include threshold/reference lines where clinically relevant
- [ ] **P4.4 Validation**
    - [ ] Cross-check chart points against raw visit records
    - [ ] Verify chart refresh after new ANC visit save/update

### Phase 5 TODO — Baby Growth Charts

- [ ] **P5.1 Column/Model Verification**
    - [ ] Verify `child_growth_records` columns used in UI (`age_months`, `weight_kg`, `length_height_cm`, `head_circumference_cm`, z-scores, `nutritional_status`)
    - [ ] Verify sex and DOB sources used for WHO lookup are correct
- [ ] **P5.2 Chart Rendering**
    - [ ] Weight-for-age, length/height-for-age, head circumference charts
    - [ ] Overlay patient trajectory on WHO reference bands
- [ ] **P5.3 Table + Chart Consistency**
    - [ ] Ensure each table row corresponds to plotted point
    - [ ] Display z-score and nutritional interpretation badges consistently
- [ ] **P5.4 UX**
    - [ ] Add explanatory text for color zones and z-score meaning
    - [ ] Handle empty-state with CTA to add first growth record

### Phase 6 TODO — Unified Immunization (Finalize + Hardening)

- [ ] **P6.1 Schema/Template Verification**
    - [ ] Confirm maternal template exists and seeded items are complete
    - [ ] Verify baby schedule defaults to NPI template where expected
- [ ] **P6.2 Controller Contract Validation**
    - [ ] Mother schedule generation uses enrollment `lmp` for due-date math
    - [ ] Baby schedule generation uses patient DOB flow via shared nursing engine
    - [ ] Status update/administer endpoints return payload fields expected by shared JS
- [ ] **P6.3 Route/JS Consistency**
    - [ ] Verify all maternity immunization URLs used in JS exist in routes
    - [ ] Verify shared module baseUrl + product batch URL are correctly set per context
- [ ] **P6.4 UI Subtab Verification**
    - [ ] Confirm Mother + each Baby tabs build dynamically for any baby count
    - [ ] Confirm per-person nested tabs: Schedule/Administer + History
    - [ ] Confirm history view switches (timeline/calendar/table) work independently per person
- [ ] **P6.5 Clinical Workflow QA**
    - [ ] Generate mother schedule, administer one dose, check stock/batch flow
    - [ ] Generate baby schedule (if missing), administer one due dose
    - [ ] Verify status transitions: pending → due/overdue → administered/skipped/contraindicated

### Phase 7 TODO — Overview Tab Enhancement

- [ ] Verify source fields for risk, EDD countdown, latest vitals are canonical and non-duplicated
- [ ] Implement progress bar with trimester segmentation and legend
- [ ] Add quick-summary cards with clear iconography and plain-language labels
- [ ] Add alerts panel for overdue/missed/high-risk events with links to target tabs
- [ ] UX check: no clutter, readable hierarchy, consistent card spacing and typography

### Phase 8 TODO — Reports & Analytics

- [ ] Verify aggregation queries reference correct canonical fields and statuses
- [ ] Implement report charts with explicit titles, units, and legends
- [ ] Add CSV/print actions per report block with empty-data handling
- [ ] Reconcile totals between chart output and table output
- [ ] QA with date filters and edge cases (zero records, mixed statuses)

### Phase 9 TODO — Print Cards

- [ ] **P9.1 ANC Card**
    - [ ] Map each displayed field to canonical columns (mother demographics, ANC records, delivery summary, previous pregnancies)
    - [ ] Ensure print CSS fidelity and page-break behavior
- [ ] **P9.2 Road to Health Card**
    - [ ] Verify baby identity, growth records, immunization timeline mapping
    - [ ] Ensure growth chart/structured growth section matches available data columns
- [ ] **P9.3 Immunization Card**
    - [ ] Add route/view/controller method and render schedule + administered history clearly
    - [ ] Validate print output for single baby and multiple baby scenarios

### Phase 10 TODO — Enrollment Polish

- [ ] Verify enrollment form fields map to canonical columns (`lmp`, `edd`, `gravida`, `parity`, `alive`, `abortion_miscarriage`, `risk_level`, `risk_factors`, etc.)
- [ ] Implement EDD/GA helper logic with clear overwrite rules for manual edits
- [ ] Implement BMI auto-calc from booking height/weight with validation guards
- [ ] Add status transition controls with audit entries and role checks
- [ ] UX refinement: sectioning, helper text, and warning states for inconsistent dates

---

## Per-Phase Verification Checklist Template (Copy for each execution PR)

- [ ] **Columns verified**
    - [ ] Migration columns reviewed
    - [ ] Model `$fillable`/`$casts` reviewed
    - [ ] Controller request/response keys aligned
    - [ ] UI form names/render keys aligned
- [ ] **Functional checks passed**
    - [ ] Create
    - [ ] Read
    - [ ] Update
    - [ ] Delete (where applicable)
- [ ] **UX checks passed**
    - [ ] Empty/loading/error states
    - [ ] Form guidance and validation clarity
    - [ ] Mobile/responsive readability
- [ ] **Technical checks passed**
    - [ ] PHP lint/no syntax errors
    - [ ] No new JS console errors
    - [ ] No route mismatch/404 in network calls

---

## Implementation Priority Order

| Priority | Phase | Effort | Impact | Status |
|----------|-------|--------|--------|--------|
| 🔴 **P0** | Phase 1 — Header Fixes | ✅ Done | High | ✅ COMPLETED |
| 🔴 **P1** | Phase 2 — Edit/Delete UI for all data types | ✅ Done | Very High | ✅ COMPLETED |
| 🔴 **P1** | Phase 6 — Unified Immunization System | Large | Critical | ✅ COMPLETED |
| 🔴 **P1** | Phase 3 — Partograph UI | Large | Critical | ✅ COMPLETED |
| 🟠 **P2** | Phase 4 — ANC Trend Charts | Medium | High | ✅ COMPLETED |
| 🟠 **P2** | Phase 5 — Baby Growth Charts | Medium | High | ✅ COMPLETED |
| 🟢 **P4** | Phase 7 — Overview Enhancement | Small | Medium | ✅ COMPLETED |
| 🟢 **P4** | Phase 8 — Reports Charts | Medium | Medium | ✅ COMPLETED |
| 🟢 **P4** | Phase 9 — Print Card Layouts | Medium | Medium | 🔲 Not started |
| 🟢 **P5** | Phase 10 — Enrollment Polish | Small | Low | ✅ COMPLETED |

---

## Backend Routes Added / To Add

| Method | Path | Controller Method | Purpose | Status |
|--------|------|-------------------|---------|--------|
| PUT | `/medical-history/{id}` | `updateMedicalHistory` | Edit medical history entry | ✅ Done |
| DELETE | `/medical-history/{id}` | `deleteMedicalHistory` | Delete medical history entry | ✅ Done |
| PUT | `/prev-pregnancy/{id}` | `updatePreviousPregnancy` | Edit previous pregnancy | ✅ Done |
| PUT | `/note/{id}` | `updateNote` | Edit clinical note | ✅ Done |
| DELETE | `/note/{id}` | `deleteNote` | Delete clinical note | ✅ Done |
| GET | `/enrollment/{id}/mother-schedule` | `getMotherSchedule` | Mother immunization schedule | ✅ Done |
| POST | `/enrollment/{id}/generate-mother-schedule` | `generateMotherSchedule` | Generate maternal schedule | ✅ Done |
| GET | `/enrollment/{id}/mother-immunization-history` | `getMotherImmunizationHistory` | Mother immunization records | ✅ Done |
| GET | `/baby/{id}/schedule` | `getBabySchedule` | Baby schedule (via PatientImmunizationSchedule) | ✅ Done |
| POST | `/baby/{id}/generate-schedule` | `generateBabySchedule` | Auto-generate NPI for baby | ✅ Done |
| POST | `/administer-from-schedule` | `administerFromScheduleMaternity` | Unified administer endpoint | ✅ Done |
| PUT | `/schedule/{id}/status` | `updateScheduleStatusMaternity` | Skip/contraindicate entry | ✅ Done |
| GET | `/schedule-templates` | `getScheduleTemplatesMaternity` | List available templates | ✅ Done |
| GET | `/vaccine-products/{name}` | `getVaccineProductsMaternity` | Get product mappings | ✅ Done |
| GET | `/baby/{id}/print-immunization-card` | `printImmunizationCard` | Print baby's vaccination card | 🔲 Phase 9 |
| DELETE | `/anc-visit/{id}` | `deleteAncVisit` | Delete ANC visit (admin only) | 🔲 Future |
| DELETE | `/postnatal/{id}` | `deletePostnatalVisit` | Delete postnatal visit (admin only) | 🔲 Future |

---

## Technical Notes

- **Bootstrap Version: 4** — All modals use `$('#id').modal('show'/'hide')`, never `bootstrap.Modal`
- **Chart.js:** Available in project at `assets/libs/chart.js/chart.min.js` — needs `<script>` tag in workbench
- **CKEditor 5:** Already integrated for ANC notes, Postnatal notes, and general Notes via `MaternityEditors` helper
- **WHO Growth Standards:** Model `WhoGrowthStandard` + seeder exist with z-score data for weight, height, head circumference, BMI by sex and age
- **Partograph Schema:** Full migration exists with 15+ data columns — ready for frontend
- **Clinical Orders:** Already at full parity with nursing workbench — no changes needed
- **Vitals:** Shared partial with nursing — no changes needed

---

## Files to Modify

| File | Changes | Status |
|------|---------|--------|
| `resources/views/admin/maternity/workbench.blade.php` | Edit/delete buttons, immunization subtabs, partograph UI, Chart.js charts, administer modal | ✅ Phase 2 done, ⚠️ Phase 6 integrated |
| `app/Http/Controllers/MaternityWorkbenchController.php` | 5 done + 10 new methods for Phase 6 immunization | ✅ Phase 2 done, ✅ Phase 6 integrated |
| `routes/maternity_workbench.php` | 5 done + 10 new routes for Phase 6 | ✅ Phase 2 done, ✅ Phase 6 integrated |
| `public/js/immunization-module.js` | **NEW** — shared immunization JS module (extracted from nursing) | ✅ Phase 6 integrated |
| `database/seeders/MaternalVaccineScheduleSeeder.php` | **NEW** — Nigeria ANC Maternal immunization template | ✅ Added |
| `resources/views/admin/nursing/workbench.blade.php` | Refactor to use shared immunization-module.js | 🔲 Phase 6 |
| `resources/views/admin/maternity/print-anc-card.blade.php` | Enhanced layout matching physical card | 🔲 Phase 9 |
| `resources/views/admin/maternity/print-road-health-card.blade.php` | Enhanced layout with WHO growth chart | 🔲 Phase 9 |
| `resources/views/admin/maternity/print-immunization-card.blade.php` | New file | 🔲 Phase 9 |

---

## Reference: Physical Card Photos

The user provided 4 reference photos of Nigerian ANC & Road to Health cards:
1. **ANC Tracking Sheet** — Columns: Date, Height of Fundus, Presentation & Position, Foetal Heart, Oedema, Urine (Album/Sugar), Weight, H/B, B.P., Treatment
2. **ANC Card Summary** — Sections: Summary of Delivery (date, type, complications), Record of Baby (sex, weight, APGAR), Previous Pregnancies grid (year, duration, place, type, outcome, sex, weight, alive)
3. **Road to Health Growth Chart** — WHO-style weight-for-age curves from Birth to 5 years with -3SD to +3SD bands, separate for boys (blue) and girls (pink)
4. **Vaccination Card** — Nigerian NPI schedule: BCG, OPV-0, HBV-0, Penta 1-3, PCV 1-3, OPV 1-3, Rota 1-3, IPV 1-2, VITA 1-2, Measles 1-2, Yellow Fever, Meningitis
