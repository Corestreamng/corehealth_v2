# Mobile Encounters & Consultation — Implementation Plan

> **Goal:** Bring the full Doctor Queue + Consultation system to both mobile apps, reusing existing Laravel controller/service logic. The Doctor App gets full CRUD; the Patient App gets read-only access.

---

## Table of Contents

1. [Architecture Strategy](#1-architecture-strategy)
2. [Laravel Backend — New Mobile API Endpoints](#2-laravel-backend--new-mobile-api-endpoints)
3. [Laravel Backend — Reuse Existing Endpoints](#3-laravel-backend--reuse-existing-endpoints)
4. [Doctor App — Screen-by-Screen Plan](#4-doctor-app--screen-by-screen-plan)
5. [Patient App — Screen-by-Screen Plan](#5-patient-app--screen-by-screen-plan)
6. [Implementation Phases](#6-implementation-phases)

---

## 1. Architecture Strategy

### 1.1 Controller Reuse Pattern

Instead of duplicating logic, we make existing `EncounterController` methods **dual-purpose** by adding `wantsJson()` checks. Where methods already return JSON (15+ endpoints), we simply expose them under `/api/mobile/` routes with `auth:sanctum` middleware.

```
┌─────────────┐     ┌──────────────────────┐     ┌─────────────────────────────┐
│  Mobile App  │────▶│  api/mobile/* routes  │────▶│  Existing Controllers       │
│  (Sanctum)   │     │  (auth:sanctum)       │     │  + MobileEncounterController │
└─────────────┘     └──────────────────────┘     └─────────────────────────────┘
```

### 1.2 Three-Layer Approach

| Layer | What | How |
|-------|------|-----|
| **Already JSON** | saveDiagnosis, saveLabs, saveImaging, savePrescriptions, saveProcedures, finalize, summary, all deletes, updateNotes, liveSearch | Wrap in `api/mobile/` routes — zero code changes |
| **Needs JSON variant** | Queue listings (NewEncounterList, ContEncounterList, PrevEncounterList), history lists (investigationHistoryList, imagingHistoryList, prescHistoryList), encounter create | Add `if ($request->wantsJson())` branches in existing methods, OR create thin `MobileEncounterController` that calls the same service logic |
| **New endpoints** | Patient-facing read-only (my encounters, my results, my prescriptions, my vitals) | New `MobilePatientController` |

### 1.3 Why NOT a Separate MobileEncounterController for Everything

Most AJAX endpoints (save-diagnosis, save-labs, etc.) already return `response()->json(...)`. Creating a wrapper controller would be pure duplication. Instead:

- **Route aliasing**: Point mobile routes to the same controller methods
- **Guard swap**: Use `auth:sanctum` instead of `auth` (web session)
- **Only new controller** for: queue listing JSON, encounter init JSON, patient-facing reads

---

## 2. Laravel Backend — New Mobile API Endpoints

### 2.1 New Controller: `MobileEncounterController`

**File:** `app/Http/Controllers/API/MobileEncounterController.php`

| Method | Route | Purpose |
|--------|-------|---------|
| `queues()` | `GET /api/mobile/doctor/queues?status={1,2,3}&date=` | JSON queue listing for current doctor |
| `startEncounter()` | `POST /api/mobile/doctor/encounters/start` | Find-or-create encounter (replaces `create()` view logic) |
| `encounterDetail()` | `GET /api/mobile/doctor/encounters/{id}` | Full encounter state (patient, vitals, diagnosis, labs, imaging, rx, procedures) |
| `labHistory()` | `GET /api/mobile/patient/{id}/lab-history` | Lab history as JSON (not HTML cards) |
| `imagingHistory()` | `GET /api/mobile/patient/{id}/imaging-history` | Imaging history as JSON |
| `prescriptionHistory()` | `GET /api/mobile/patient/{id}/prescription-history` | Prescription history as JSON |
| `procedureHistory()` | `GET /api/mobile/patient/{id}/procedure-history` | Procedure history as JSON |
| `encounterHistory()` | `GET /api/mobile/patient/{id}/encounter-history` | Past encounters as JSON |

#### `queues()` — Response Format
```json
{
  "success": true,
  "data": [
    {
      "queue_id": 1,
      "patient_id": 5,
      "patient_name": "John Doe",
      "file_no": "FN-0001",
      "hmo_name": "NHIS",
      "hmo_no": "HMO123",
      "clinic_name": "General OPD",
      "doctor_name": "Dr. Smith",
      "status": 1,
      "status_label": "New",
      "vitals_taken": true,
      "created_at": "2025-01-15T09:30:00Z",
      "request_entry_id": 8
    }
  ],
  "meta": { "total": 15, "page": 1, "per_page": 20 }
}
```

#### `startEncounter()` — Request & Response
```
POST /api/mobile/doctor/encounters/start
Body: { "patient_id": 5, "req_entry_id": 8, "queue_id": 1 }

Response:
{
  "success": true,
  "encounter": {
    "id": 42,
    "patient_id": 5,
    "doctor_id": 3,
    "service_request_id": 8,
    "notes": "",
    "completed": false,
    "reasons_for_encounter": null,
    "created_at": "2025-01-15T09:35:00Z"
  },
  "patient": { /* patient object with demographics, HMO, next of kin */ },
  "clinic": { /* clinic object */ },
  "is_admitted": false,
  "admission": null,
  "existing_diagnosis": [],
  "settings": {
    "require_diagnosis": true,
    "note_edit_duration": 30
  }
}
```

#### `encounterDetail()` — Response Format
```json
{
  "success": true,
  "encounter": { /* encounter with relationships */ },
  "patient": { /* full patient demographics */ },
  "vitals": [ /* last 10 vitals */ ],
  "diagnosis": {
    "reasons": ["A01-Headache", "B02-Fever"],
    "comment_1": "CONFIRMED",
    "comment_2": "ACUTE",
    "notes": "Patient presents with..."
  },
  "labs": [
    { "id": 1, "service_name": "FBC", "status": "requested", "note": "Urgent" }
  ],
  "imaging": [
    { "id": 2, "service_name": "Chest X-Ray", "status": "requested", "note": "" }
  ],
  "prescriptions": [
    { "id": 3, "product_name": "Paracetamol 500mg", "dose": "1x3 daily", "status": "requested" }
  ],
  "procedures": [
    { "id": 1, "service_name": "Appendectomy", "priority": "urgent", "status": "requested" }
  ],
  "admission": null
}
```

### 2.2 New Controller: `MobilePatientController`

**File:** `app/Http/Controllers/API/MobilePatientController.php`

| Method | Route | Purpose |
|--------|-------|---------|
| `myProfile()` | `GET /api/mobile/patient/profile` | Patient demographics, HMO |
| `myEncounters()` | `GET /api/mobile/patient/encounters` | Paginated encounter history |
| `encounterDetail()` | `GET /api/mobile/patient/encounters/{id}` | Single encounter detail (read-only) |
| `myVitals()` | `GET /api/mobile/patient/vitals` | Paginated vitals history |
| `myLabResults()` | `GET /api/mobile/patient/lab-results` | Lab results with statuses |
| `myImagingResults()` | `GET /api/mobile/patient/imaging-results` | Imaging results |
| `myPrescriptions()` | `GET /api/mobile/patient/prescriptions` | Prescription history |
| `myProcedures()` | `GET /api/mobile/patient/procedures` | Procedure history |
| `myAdmissions()` | `GET /api/mobile/patient/admissions` | Admission history |

### 2.3 Updated `routes/api.php`

```php
// ═══════════════════════════════════════════════════
// MOBILE — DOCTOR ROUTES (auth:sanctum + role:doctor)
// ═══════════════════════════════════════════════════
Route::prefix('mobile/doctor')->middleware(['auth:sanctum'])->group(function () {

    // ---- Queue Management ----
    Route::get('queues',                    [MobileEncounterController::class, 'queues']);

    // ---- Encounter Lifecycle ----
    Route::post('encounters/start',         [MobileEncounterController::class, 'startEncounter']);
    Route::get('encounters/{encounter}',    [MobileEncounterController::class, 'encounterDetail']);

    // ---- Reused from EncounterController (already return JSON) ----
    Route::post('encounters/{encounter}/save-diagnosis',      [EncounterController::class, 'saveDiagnosis']);
    Route::post('encounters/{encounter}/save-labs',           [EncounterController::class, 'saveLabs']);
    Route::post('encounters/{encounter}/save-imaging',        [EncounterController::class, 'saveImaging']);
    Route::post('encounters/{encounter}/save-prescriptions',  [EncounterController::class, 'savePrescriptions']);
    Route::post('encounters/{encounter}/save-procedures',     [EncounterController::class, 'saveProcedures']);
    Route::post('encounters/{encounter}/finalize',            [EncounterController::class, 'finalizeEncounter']);
    Route::get('encounters/{encounter}/summary',              [EncounterController::class, 'getEncounterSummary']);
    Route::put('encounters/{encounter}/notes',                [EncounterController::class, 'updateEncounterNotes']);
    Route::delete('encounters/{encounter}',                   [EncounterController::class, 'destroy']);

    // ---- Reused Delete Endpoints ----
    Route::delete('encounters/{encounter}/labs/{lab}',                   [EncounterController::class, 'deleteLab']);
    Route::delete('encounters/{encounter}/imaging/{imaging}',            [EncounterController::class, 'deleteImaging']);
    Route::delete('encounters/{encounter}/prescriptions/{prescription}', [EncounterController::class, 'deletePrescription']);
    Route::delete('encounters/{encounter}/procedures/{procedure}',       [EncounterController::class, 'deleteProcedure']);

    // ---- Reused Procedure Sub-Endpoints ----
    Route::get('procedures/{procedure}',              [ProcedureController::class, 'show']);
    Route::get('procedures/{procedure}/team',         [ProcedureController::class, 'getTeam']);
    Route::post('procedures/{procedure}/team',        [ProcedureController::class, 'addTeamMember']);
    Route::delete('procedures/{procedure}/team/{member}', [ProcedureController::class, 'removeTeamMember']);
    Route::get('procedures/{procedure}/notes',        [ProcedureController::class, 'getNotes']);
    Route::post('procedures/{procedure}/notes',       [ProcedureController::class, 'addNote']);
    Route::delete('procedures/{procedure}/notes/{note}', [ProcedureController::class, 'deleteNote']);
    Route::post('procedures/{procedure}/cancel',      [ProcedureController::class, 'cancel']);

    // ---- History Lists (new JSON versions) ----
    Route::get('patient/{patient}/lab-history',          [MobileEncounterController::class, 'labHistory']);
    Route::get('patient/{patient}/imaging-history',      [MobileEncounterController::class, 'imagingHistory']);
    Route::get('patient/{patient}/prescription-history', [MobileEncounterController::class, 'prescriptionHistory']);
    Route::get('patient/{patient}/procedure-history',    [MobileEncounterController::class, 'procedureHistory']);
    Route::get('patient/{patient}/encounter-history',    [MobileEncounterController::class, 'encounterHistory']);

    // ---- Search / Autocomplete ----
    Route::get('search/diagnosis',   [EncounterController::class, 'liveSearchReasons']);
    Route::get('search/services',    [ServiceController::class, 'liveSearchServices']);
    Route::get('search/products',    [ProductController::class, 'liveSearchProducts']);

    // ---- Vitals ----
    Route::post('vitals',                               [VitalSignController::class, 'store']);
    Route::get('patient/{patient}/vitals',              [NursingWorkbenchController::class, 'getPatientVitals']);
});

// ═══════════════════════════════════════════════
// MOBILE — PATIENT ROUTES (auth:sanctum)
// ═══════════════════════════════════════════════
Route::prefix('mobile/patient')->middleware(['auth:sanctum'])->group(function () {
    Route::get('profile',               [MobilePatientController::class, 'myProfile']);
    Route::get('encounters',            [MobilePatientController::class, 'myEncounters']);
    Route::get('encounters/{encounter}',[MobilePatientController::class, 'encounterDetail']);
    Route::get('vitals',                [MobilePatientController::class, 'myVitals']);
    Route::get('lab-results',           [MobilePatientController::class, 'myLabResults']);
    Route::get('imaging-results',       [MobilePatientController::class, 'myImagingResults']);
    Route::get('prescriptions',         [MobilePatientController::class, 'myPrescriptions']);
    Route::get('procedures',            [MobilePatientController::class, 'myProcedures']);
    Route::get('admissions',            [MobilePatientController::class, 'myAdmissions']);
});
```

### 2.4 Minimal Changes to Existing Controllers

Only **3 methods** in `EncounterController` need a `wantsJson()` branch — and even those are optional if we use `MobileEncounterController` instead:

| Method | Current Return | Mobile Fix |
|--------|---------------|------------|
| `liveSearchReasons()` | Already returns JSON ✅ | None needed |
| `saveDiagnosis()` | Already returns JSON ✅ | None needed |
| `saveLabs()` | Already returns JSON ✅ | None needed |
| `saveImaging()` | Already returns JSON ✅ | None needed |
| `savePrescriptions()` | Already returns JSON ✅ | None needed |
| `saveProcedures()` | Already returns JSON ✅ | None needed |
| `finalizeEncounter()` | Already returns JSON ✅ | None needed |
| `getEncounterSummary()` | Already returns JSON ✅ | None needed |
| `updateEncounterNotes()` | Already returns JSON ✅ | None needed |
| All delete methods | Already return JSON ✅ | None needed |
| `store()` | Returns redirect ❌ | **Not needed** — mobile uses step-by-step AJAX saves |
| `create()` | Returns Blade view ❌ | **Replaced** by `MobileEncounterController@startEncounter` |
| `index()` | Returns Blade view ❌ | **Replaced** by `MobileEncounterController@queues` |
| Queue DataTable methods | Return DataTables HTML ❌ | **Replaced** by `MobileEncounterController@queues` |
| History list methods | Return DataTables HTML cards ❌ | **Replaced** by `MobileEncounterController@*History` |

**Auth guard adjustment** — The existing methods use `auth()->user()` which works with both `auth` (web session) and `auth:sanctum` (token). The Sanctum middleware sets the same `Auth` guard, so `auth()->user()` resolves correctly for mobile requests too. ✅

### 2.5 VitalSignController@store — Already Mobile-Ready

The existing `store()` method already has:
```php
if ($request->wantsJson()) {
    return response()->json(['success' => true, 'message' => '...']);
}
```
So it works as-is when called from mobile with `Accept: application/json`. ✅

---

## 3. Laravel Backend — Reuse Existing Endpoints

### 3.1 Endpoint Reuse Summary

| # | Web Endpoint | Mobile Route | Code Changes | Returns |
|---|-------------|-------------|--------------|---------|
| 1 | `POST encounters/{id}/save-diagnosis` | `POST api/mobile/doctor/encounters/{id}/save-diagnosis` | **None** — route alias | `{success, message}` |
| 2 | `POST encounters/{id}/save-labs` | `POST api/mobile/doctor/encounters/{id}/save-labs` | **None** — route alias | `{success, message, count}` |
| 3 | `POST encounters/{id}/save-imaging` | `POST api/mobile/doctor/encounters/{id}/save-imaging` | **None** — route alias | `{success, message, count}` |
| 4 | `POST encounters/{id}/save-prescriptions` | `POST api/mobile/doctor/encounters/{id}/save-prescriptions` | **None** — route alias | `{success, message, count}` |
| 5 | `POST encounters/{id}/save-procedures` | `POST api/mobile/doctor/encounters/{id}/save-procedures` | **None** — route alias | `{success, message}` |
| 6 | `POST encounters/{id}/finalize` | `POST api/mobile/doctor/encounters/{id}/finalize` | **None** — route alias | `{success, message}` |
| 7 | `GET encounters/{id}/summary` | `GET api/mobile/doctor/encounters/{id}/summary` | **None** — route alias | `{success, data}` |
| 8 | `DELETE encounters/{id}/labs/{lab}` | `DELETE api/mobile/doctor/encounters/{id}/labs/{lab}` | **None** — route alias | `{success, message}` |
| 9 | `DELETE encounters/{id}/imaging/{img}` | `DELETE ...` | **None** | `{success, message}` |
| 10 | `DELETE encounters/{id}/prescriptions/{rx}` | `DELETE ...` | **None** | `{success, message}` |
| 11 | `DELETE encounters/{id}/procedures/{proc}` | `DELETE ...` | **None** | `{success, message}` |
| 12 | `PUT encounters/{id}/notes` | `PUT api/mobile/doctor/encounters/{id}/notes` | **None** — route alias | `{success, message}` |
| 13 | `GET live-search-reasons` | `GET api/mobile/doctor/search/diagnosis` | **None** — route alias | `[{code, name, category}]` |
| 14 | `GET live-search-services` | `GET api/mobile/doctor/search/services` | **None** — route alias | `[{id, service_name, price, ...}]` |
| 15 | `GET live-search-products` | `GET api/mobile/doctor/search/products` | **None** — route alias | `[{id, product_name, stock, ...}]` |
| 16 | `POST vitals` | `POST api/mobile/doctor/vitals` | **None** — already JSON-aware | `{success, message}` |
| 17 | `GET nursing-workbench/patient/{id}/vitals` | `GET api/mobile/doctor/patient/{id}/vitals` | **None** — route alias | `[{bp, temp, hr, ...}]` |

**Total: 17 endpoints reused with ZERO code changes — only route aliasing under Sanctum auth.**

### 3.2 New Endpoints Needed

| # | Endpoint | New Code | Reason |
|---|----------|----------|--------|
| 1 | `GET api/mobile/doctor/queues` | ~40 lines | Web uses DataTables with HTML; mobile needs clean JSON |
| 2 | `POST api/mobile/doctor/encounters/start` | ~60 lines | Web `create()` returns Blade view; mobile needs JSON |
| 3 | `GET api/mobile/doctor/encounters/{id}` | ~50 lines | Aggregated encounter detail (replaces loading 10 blade tabs) |
| 4 | `GET api/mobile/doctor/patient/{id}/lab-history` | ~30 lines | Web uses DataTables HTML cards; mobile needs JSON |
| 5 | `GET api/mobile/doctor/patient/{id}/imaging-history` | ~30 lines | Same |
| 6 | `GET api/mobile/doctor/patient/{id}/prescription-history` | ~30 lines | Same |
| 7 | `GET api/mobile/doctor/patient/{id}/procedure-history` | ~30 lines | Same |
| 8 | `GET api/mobile/doctor/patient/{id}/encounter-history` | ~30 lines | Same |
| 9 | `GET api/mobile/patient/profile` | ~20 lines | Patient self-view |
| 10 | `GET api/mobile/patient/encounters` | ~30 lines | Patient encounter history |
| 11 | `GET api/mobile/patient/encounters/{id}` | ~40 lines | Patient encounter detail |
| 12 | `GET api/mobile/patient/vitals` | ~25 lines | Patient vitals history |
| 13 | `GET api/mobile/patient/lab-results` | ~30 lines | Patient lab results |
| 14 | `GET api/mobile/patient/imaging-results` | ~25 lines | Patient imaging results |
| 15 | `GET api/mobile/patient/prescriptions` | ~25 lines | Patient prescription history |
| 16 | `GET api/mobile/patient/procedures` | ~25 lines | Patient procedure history |
| 17 | `GET api/mobile/patient/admissions` | ~25 lines | Patient admission history |

**Total: ~17 new endpoint methods, ~550 lines of new code.**

---

## 4. Doctor App — Screen-by-Screen Plan

### 4.1 Screen Map

```
┌─────────────────────────────────────────────────────────┐
│                    DOCTOR APP                            │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  🏠 Home (Dashboard)                                     │
│   ├── Queue Summary Cards (New / Continuing / Completed) │
│   └── Quick Actions                                      │
│                                                          │
│  📋 Queue Screen (5 Tabs)                                │
│   ├── Tab: New Patients          (status=1)              │
│   ├── Tab: Continuing            (status=2)              │
│   ├── Tab: Previous              (status=3)              │
│   ├── Tab: My Admissions                                 │
│   └── Tab: Other Admissions                              │
│   └── Each row → "Start Encounter" button                │
│                                                          │
│  🩺 Consultation Screen (10 Tabs)                        │
│   ├── Tab 1: Patient Data        (read-only demographics)│
│   ├── Tab 2: Vitals & Allergies  (view + record vitals)  │
│   ├── Tab 3: Nurse Charts        (read-only view)        │
│   ├── Tab 4: Inj/Imm History     (read-only view)        │
│   ├── Tab 5: Clinical Notes      (ICPC-2 + rich text)    │
│   ├── Tab 6: Laboratory          (search + request)      │
│   ├── Tab 7: Imaging             (search + request)      │
│   ├── Tab 8: Medications         (search + prescribe)    │
│   ├── Tab 9: Procedures          (search + request)      │
│   └── Tab 10: Admission History  (view + request)        │
│   └── FAB: "Conclude Encounter"                          │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

### 4.2 Queue Screen

**API:** `GET /api/mobile/doctor/queues?status=1` (New), `?status=2` (Continuing), `?status=3` (Previous)

**UI Elements per Tab:**
- Date range picker (optional filter)
- Pull-to-refresh
- Scrollable list of patient cards:
  - Patient name + file number
  - HMO/Insurance badge
  - Clinic name
  - Time queued (relative: "15 min ago")
  - Vitals status indicator (✅ taken / ⚠️ pending)
  - "Start Encounter" button

**Actions:**
- Tap patient card → opens Consultation Screen
- "Start Encounter" calls `POST /api/mobile/doctor/encounters/start` with `patient_id`, `req_entry_id`, `queue_id`

### 4.3 Consultation Screen — Tab by Tab

#### Tab 1: Patient Data (Read-Only)

**API:** Data comes from `startEncounter()` response → `patient` object

**UI:**
- Patient photo (circular avatar)
- Full name (large text)
- File number
- Demographics table: Gender, DOB (+ age calc), Blood Group, Genotype
- Contact: Phone, Address
- HMO info: Insurance name, HMO number, coverage details
- Next of Kin: Name, Phone, Address
- If admitted: Admission card with bed/ward/status

#### Tab 2: Vitals & Allergies

**API:** `GET /api/mobile/doctor/patient/{id}/vitals` → JSON array of vitals

**UI — 2 sub-sections:**

1. **Latest Vitals Card** (most recent reading):
   - BP with color indicator (green/yellow/red)
   - Temperature with color
   - Heart Rate with color
   - Respiratory Rate with color
   - SpO2 with color
   - Pain Score (0-10 visual bar)
   - Weight, Height, BMI
   - Blood Sugar with color
   - Time taken

2. **Record New Vitals** (expandable form):
   - BP input with pattern validation (###/##)
   - Temperature (number, 34-42, step 0.1)
   - Heart Rate (number, 30-250)
   - Respiratory Rate (number, 5-60)
   - SpO2 (number, 50-100, step 0.1)
   - Pain Score (0-10 button row)
   - Weight (number, 0.5-500)
   - Height (number, 30-300)
   - BMI (auto-calculated)
   - Blood Sugar (number, 20-600)
   - Date/Time (defaults to now)
   - Notes (text)
   - **Save** → `POST /api/mobile/doctor/vitals`

3. **Vitals History** (scrollable list of past readings)

**Color Coding Rules** (same as web):

| Vital | Normal (Green) | Warning (Yellow) | Critical (Red) |
|-------|---------------|-------------------|-----------------|
| BP systolic | 90-140 | <90 or >140 | <80 or >180 |
| BP diastolic | 60-90 | <60 or >90 | <50 or >110 |
| Temp °C | 36.1-37.2 | 36.1-38 | <34 or >39 |
| Heart Rate | 60-100 | <60 or >100 | <50 or >150 |
| Resp Rate | 12-20 | <12 or >20 | <8 or >30 |
| SpO2 % | ≥95 | 90-95 | <90 |
| Blood Sugar | 80-140 | 70-80 or 140-200 | <70 or >200 |
| Pain Score | 0-3 | 4-6 | 7-10 |

#### Tab 3: Nurse Charts (Read-Only)

**Scope:** View medication charts, fluid/solid I/O charts, and nursing notes recorded by nurses. Read-only for doctors.

**API:** New endpoint `GET /api/mobile/doctor/patient/{id}/nurse-charts` (can be added in a later phase).

**UI:** Timeline/list view of nursing entries. Lower priority — Phase 3.

#### Tab 4: Injection/Immunization History (Read-Only)

**Scope:** View injection and immunization records.

**API:** New endpoint `GET /api/mobile/doctor/patient/{id}/injections` (later phase).

**UI:** Chronological list. Lower priority — Phase 3.

#### Tab 5: Clinical Notes / Diagnosis ⭐ (Core Feature)

**APIs:**
- `GET /api/mobile/doctor/search/diagnosis?q=headache` → ICPC-2 codes
- `POST /api/mobile/doctor/encounters/{id}/save-diagnosis` → save
- `GET /api/mobile/doctor/patient/{id}/encounter-history` → past notes
- `PUT /api/mobile/doctor/encounters/{id}/notes` → edit notes

**UI — 2 sub-sections:**

1. **Past Notes** (scrollable cards):
   - Each card: Date, Doctor name, Diagnosis codes (chips), Notes preview
   - Tap to expand full notes
   - Edit button (if within `note_edit_duration` window)

2. **New Clinical Entry** (form):
   - **Diagnosis Search**: Text input with autocomplete
     - Type ≥2 chars → debounce 300ms → search ICPC-2 codes
     - Results: `[A01] Headache - Neurological` format
     - Tap to add as chip/badge
     - Allow custom/free-text entries
     - Selected shown as colored chips with ✕ remove
   - **Diagnosis Applicable** toggle (conditional on `requirediagnosis` setting)
   - **Comment 1**: Dropdown — NA, QUERY, DIFFERENTIAL, CONFIRMED
   - **Comment 2**: Dropdown — NA, ACUTE, CHRONIC, RECURRENT
   - **Clinical Notes**: Multi-line text input (rich text not needed on mobile — use Markdown or plain text)
   - **Save** button → `POST .../save-diagnosis`
   - **Auto-save**: Timer every 30s → `POST .../autosave` (lightweight, notes only)

#### Tab 6: Laboratory Services ⭐ (Core Feature)

**APIs:**
- `GET /api/mobile/doctor/search/services?term=fbc&patient_id=5` → search
- `POST /api/mobile/doctor/encounters/{id}/save-labs` → save
- `DELETE /api/mobile/doctor/encounters/{id}/labs/{lab}` → delete
- `GET /api/mobile/doctor/patient/{id}/lab-history` → history

**UI — 2 sub-sections:**

1. **Lab History** (scrollable cards):
   - Each card: Test name, Date, Status badge (Requested/In Progress/Completed/Billed), Results preview
   - Color-coded status: 🟡 Requested, 🔵 In Progress, 🟢 Completed

2. **New Lab Request** (form):
   - **Search Input**: Autocomplete with debounce
     - Results show: `[Category] Service Name [Code] — ₦Price`
     - HMO badge if applicable (coverage_mode indicator)
   - **Selected Services List**: Card per selected service
     - Service name + price
     - Note input field
     - Remove (✕) button
   - **Total** display
   - **Submit** → `POST .../save-labs` with `{consult_invest_id: [...], consult_invest_note: [...]}`

#### Tab 7: Imaging Services ⭐ (Core Feature)

**Identical structure to Lab Services**, but:
- Search filtered to `category_id=6` (Imaging)
- Different field names: `consult_imaging_id[]`, `consult_imaging_note[]`
- API: `POST .../save-imaging`, `DELETE .../imaging/{id}`
- History: `GET .../imaging-history`

#### Tab 8: Medications ⭐ (Core Feature)

**APIs:**
- `GET /api/mobile/doctor/search/products?term=para&patient_id=5` → search
- `POST /api/mobile/doctor/encounters/{id}/save-prescriptions` → save
- `DELETE /api/mobile/doctor/encounters/{id}/prescriptions/{rx}` → delete
- `GET /api/mobile/doctor/patient/{id}/prescription-history` → history

**UI — 2 sub-sections:**

1. **Drug History** (scrollable cards):
   - Each card: Drug name, Dose/Freq, Date, Status badge, Dispensing status

2. **New Prescription** (form):
   - **Search Input**: Product autocomplete
     - Results: `[Category] Product [Code] (Qty: 45) — ₦Price`
     - Stock quantity shown (out of stock warning if 0)
     - HMO badge if applicable
   - **Selected Products List**: Card per product
     - Product name + price
     - **Dose/Frequency** text input (e.g., "1x3 daily for 5 days")
     - Remove (✕) button
   - **Submit** → `POST .../save-prescriptions` with `{consult_presc_id: [...], consult_presc_dose: [...]}`

#### Tab 9: Procedures ⭐ (Core Feature)

**APIs:**
- `GET /api/mobile/doctor/search/services?term=appen&category_id={procCatId}&patient_id=5`
- `POST /api/mobile/doctor/encounters/{id}/save-procedures`
- `DELETE /api/mobile/doctor/encounters/{id}/procedures/{proc}`
- `GET /api/mobile/doctor/patient/{id}/procedure-history`
- `GET/POST/DELETE /api/mobile/doctor/procedures/{id}/team`
- `GET/POST/DELETE /api/mobile/doctor/procedures/{id}/notes`
- `POST /api/mobile/doctor/procedures/{id}/cancel`

**UI — 2 sub-sections:**

1. **Procedure History** (list):
   - Columns: Procedure name, Priority badge, Status badge, Date
   - Tap → Detail bottom sheet:
     - Full procedure info
     - Team members list
     - Procedure notes list
     - Cancel action (if `requested` or `scheduled`)

2. **Request Procedure** (form):
   - **Search Input**: Procedure autocomplete
   - **Priority**: Segmented control — Routine / Urgent / Emergency
   - **Scheduled Date**: Date picker (optional)
   - **Pre-Procedure Notes**: Multi-line text
   - **Selected Procedures Table**: List with priority, remove button
   - **Submit** → `POST .../save-procedures`

3. **Procedure Detail Screen** (separate screen from history tap):
   - Header: Procedure name, status, priority
   - **Team Tab**: List of team members with roles
     - Add member: Staff picker, Role picker (11 roles), Is Lead toggle, Notes
   - **Notes Tab**: List of notes with types
     - Add note: Type picker (pre_op/intra_op/post_op/anesthesia/nursing), Title, Content

#### Tab 10: Admission History

**API:** `GET /api/mobile/doctor/patient/{id}/encounter-history` (filtered) or dedicated admission endpoint

**UI:**
- List of admission records:
  - Status badge (pending → admitted → discharge_requested → discharged)
  - Admission reason
  - Bed/Ward info
  - Duration (days admitted)
  - Doctor name
- **Request Admission** button (from "Conclude Encounter" flow)

### 4.4 Conclude Encounter (Floating Action Button)

**Trigger:** FAB on Consultation Screen

**UI — Bottom Sheet / Modal:**
- **Action Selection:**
  - ○ End Consultation (patient goes home)
  - ○ Continue Consultation (patient returns later)
  - ○ Request Admission
- **If "Request Admission" selected:**
  - Admission Reason (text)
  - Priority: Routine / Urgent / Emergency
  - Note (text)
  - Bed selection (if bed management enabled)
- **Encounter Summary** (auto-loaded from `GET .../summary`):
  - Diagnosis codes listed
  - Lab requests count
  - Imaging requests count
  - Prescriptions count
  - Procedures count
- **Confirm** button → `POST /api/mobile/doctor/encounters/{id}/finalize`
  - `end_consultation=1` → queue status=3 (completed)
  - `end_consultation=0` → queue status=2 (continuing)
  - `consult_admit=1` → creates `AdmissionRequest`

### 4.5 Delete Confirmation Dialog

**Shared component** used for deleting labs, imaging, prescriptions, procedures:
- Reason dropdown (required)
- "Other" free-text input (shown when "Other" selected)
- Additional notes (optional)
- Confirm/Cancel buttons
- API: `DELETE .../encounters/{id}/{type}/{itemId}` with `{reason}`

---

## 5. Patient App — Screen-by-Screen Plan

### 5.1 Screen Map

```
┌──────────────────────────────────────────────────────┐
│                    PATIENT APP                        │
├──────────────────────────────────────────────────────┤
│                                                       │
│  🏠 Home (Dashboard)                                  │
│   ├── Next Appointment Card                           │
│   ├── Latest Vitals Summary                           │
│   └── Recent Activity (last 5 events)                 │
│                                                       │
│  👤 My Profile                                        │
│   └── Demographics, HMO info, Next of Kin             │
│                                                       │
│  📋 My Encounters                                     │
│   ├── List of past consultations                      │
│   └── Tap → Encounter Detail (read-only)              │
│       ├── Diagnosis & Notes                           │
│       ├── Lab Results                                 │
│       ├── Imaging Results                             │
│       ├── Prescriptions                               │
│       └── Procedures                                  │
│                                                       │
│  💉 My Vitals                                         │
│   ├── Latest readings with color indicators           │
│   └── Historical chart (trends)                       │
│                                                       │
│  🔬 My Lab Results                                    │
│   └── List: Test name, date, status, results          │
│                                                       │
│  📸 My Imaging Results                                │
│   └── List: Scan name, date, status, findings         │
│                                                       │
│  💊 My Prescriptions                                  │
│   └── List: Drug, dose, date, dispensing status       │
│                                                       │
│  🏥 My Admissions                                     │
│   └── List: Admission date, ward, status, duration    │
│                                                       │
└──────────────────────────────────────────────────────┘
```

### 5.2 Key Differences from Doctor App

| Feature | Doctor App | Patient App |
|---------|-----------|-------------|
| Queue management | Full access | ❌ None |
| Start encounter | Yes | ❌ No |
| Record vitals | Yes | ❌ No (view only) |
| Write clinical notes | Yes | ❌ No |
| Request labs/imaging | Yes | ❌ No |
| Write prescriptions | Yes | ❌ No |
| Request procedures | Yes | ❌ No |
| View own encounters | Yes (all patients) | Yes (own only) |
| View lab results | Yes (all patients) | Yes (own only, no pending) |
| View prescriptions | Yes (all patients) | Yes (own only) |
| View vitals | Yes (all patients) | Yes (own only) |
| Delete anything | Yes | ❌ No |
| Conclude encounter | Yes | ❌ No |

### 5.3 Patient Encounter Detail Screen

When patient taps an encounter from "My Encounters":

**Header:** Date, Doctor name, Clinic name, Status badge

**Sections (scrollable, not tabs — simpler UX):**

1. **Diagnosis**: ICPC-2 codes as chips + notes text
2. **Lab Results**: List of tests with status (Pending → Results Ready)
3. **Imaging Results**: List of scans with status
4. **Prescriptions**: List of medications with dose, dispensing status
5. **Procedures**: List with status

Each section is a collapsible card — empty sections show "No [type] for this visit."

---

## 6. Implementation Phases

### Phase 1 — Backend API + Queue Screen (Week 1-2)

**Backend:**
- [ ] Create `MobileEncounterController` with `queues()` and `startEncounter()`
- [ ] Create `MobilePatientController` with `myProfile()`, `myEncounters()`
- [ ] Add all new routes to `routes/api.php` (route aliases for existing JSON endpoints + new endpoints)
- [ ] Test all endpoints with Postman/curl

**Doctor App:**
- [ ] Create `EncounterApiService` in `lib/features/encounters/data/`
- [ ] Create Queue screen with 5 tabs (New, Continuing, Previous, My Admissions, Other Admissions)
- [ ] Create Queue list item widget with patient card design
- [ ] Add pull-to-refresh and pagination
- [ ] Wire "Start Encounter" to API

**Patient App:**
- [ ] Create `PatientApiService` in `lib/features/health/data/`
- [ ] Create "My Encounters" list screen
- [ ] Create Profile screen

### Phase 2 — Core Consultation Tabs (Week 2-3)

**Backend:**
- [ ] Create `encounterDetail()` in `MobileEncounterController`
- [ ] Create `labHistory()`, `imagingHistory()`, `prescriptionHistory()`, `encounterHistory()` in `MobileEncounterController`

**Doctor App:**
- [ ] Create Consultation screen shell with 10 tabs (TabBarView)
- [ ] **Tab 1: Patient Data** — display demographics
- [ ] **Tab 2: Vitals** — display latest + record form + history list
- [ ] **Tab 5: Clinical Notes** — diagnosis search + notes editor + history
- [ ] **Tab 6: Lab Services** — service search + request list + save + history
- [ ] **Tab 7: Imaging Services** — same pattern as labs
- [ ] **Tab 8: Medications** — product search + prescription list + save + history
- [ ] **Tab 9: Procedures** — procedure search + request + team + notes
- [ ] **Conclude Encounter** — summary + finalize modal
- [ ] **Delete Dialog** — shared component for all deletions

**Patient App:**
- [ ] Create Encounter Detail screen (read-only, scrollable sections)
- [ ] Create My Vitals screen with history + color indicators
- [ ] Create My Lab Results screen
- [ ] Create My Prescriptions screen

### Phase 3 — Secondary Tabs + Polish (Week 3-4)

**Backend:**
- [ ] Add nurse charts endpoint (if needed)
- [ ] Add injection/immunization history endpoint
- [ ] Create `myImagingResults()`, `myProcedures()`, `myAdmissions()` in `MobilePatientController`
- [ ] Add procedure `procedureHistory()` endpoint

**Doctor App:**
- [ ] **Tab 3: Nurse Charts** — read-only nursing notes/med charts
- [ ] **Tab 4: Inj/Imm History** — read-only view
- [ ] **Tab 10: Admission History** — view + request admission flow
- [ ] Procedure detail screen with team + notes management
- [ ] Notes auto-save timer (every 30 seconds)
- [ ] Offline draft support (save form state to local storage)

**Patient App:**
- [ ] Create My Imaging Results screen
- [ ] Create My Procedures screen
- [ ] Create My Admissions screen
- [ ] Home dashboard with summary cards
- [ ] Pull-to-refresh everywhere

### Phase 4 — Integration Testing + Refinement (Week 4)

- [ ] End-to-end testing: Doctor creates encounter on mobile → data appears on web
- [ ] End-to-end testing: Doctor requests labs → lab dept sees request on web
- [ ] Patient app: login → see all own data
- [ ] Error handling: network offline, token expiry, 422 validation errors
- [ ] Loading states, empty states, error states for all screens
- [ ] Search debouncing optimization
- [ ] Performance: lazy loading for history lists

---

## Appendix A: Flutter File Structure (Doctor App)

```
lib/features/encounters/
├── data/
│   ├── encounter_api_service.dart      # All API calls
│   └── models/
│       ├── queue_item.dart             # Queue list model
│       ├── encounter.dart              # Encounter model
│       ├── vital_sign.dart             # Vital sign model
│       ├── diagnosis_code.dart         # ICPC-2 code model
│       ├── lab_request.dart            # Lab request model
│       ├── imaging_request.dart        # Imaging request model
│       ├── prescription.dart           # Prescription model
│       ├── procedure.dart              # Procedure model
│       └── encounter_summary.dart      # Summary for finalize
├── presentation/
│   ├── screens/
│   │   ├── queue_screen.dart           # 5-tab queue listing
│   │   ├── consultation_screen.dart    # 10-tab consultation
│   │   └── procedure_detail_screen.dart
│   └── widgets/
│       ├── queue_list_item.dart        # Patient card in queue
│       ├── vital_sign_card.dart        # Color-coded vital display
│       ├── vital_form.dart             # Record new vitals
│       ├── diagnosis_search.dart       # ICPC-2 autocomplete + chips
│       ├── service_search.dart         # Lab/imaging/procedure search
│       ├── product_search.dart         # Medication search
│       ├── selected_items_list.dart    # Cart-like list for selected items
│       ├── encounter_summary_card.dart # Summary in finalize modal
│       ├── delete_confirmation.dart    # Shared delete dialog
│       ├── history_card.dart           # Generic history item card
│       └── status_badge.dart           # Colored status indicator
```

## Appendix B: Flutter File Structure (Patient App)

```
lib/features/health/
├── data/
│   ├── patient_api_service.dart        # All API calls
│   └── models/
│       ├── encounter_summary.dart
│       ├── vital_sign.dart
│       ├── lab_result.dart
│       ├── imaging_result.dart
│       ├── prescription.dart
│       ├── procedure.dart
│       └── admission.dart
├── presentation/
│   ├── screens/
│   │   ├── encounters_list_screen.dart
│   │   ├── encounter_detail_screen.dart
│   │   ├── vitals_screen.dart
│   │   ├── lab_results_screen.dart
│   │   ├── imaging_results_screen.dart
│   │   ├── prescriptions_screen.dart
│   │   ├── procedures_screen.dart
│   │   └── admissions_screen.dart
│   └── widgets/
│       ├── vital_sign_card.dart
│       ├── result_card.dart
│       ├── status_badge.dart
│       └── section_card.dart
```

## Appendix C: Data Flow Diagrams

### Doctor Encounter Workflow
```
Queue Screen                    Consultation Screen
─────────                       ───────────────────
[Select Patient] ──POST──▶ /encounters/start
                               │
                    ◀── encounter_id + patient data
                               │
              ┌─── Tab 5: Save Diagnosis ──POST──▶ /save-diagnosis
              │
              ├─── Tab 6: Save Labs ──POST──▶ /save-labs
              │
              ├─── Tab 7: Save Imaging ──POST──▶ /save-imaging
              │
              ├─── Tab 8: Save Prescriptions ──POST──▶ /save-prescriptions
              │
              ├─── Tab 9: Save Procedures ──POST──▶ /save-procedures
              │
              └─── FAB: Finalize ──GET──▶ /summary
                                  ──POST──▶ /finalize
                                       │
                            ◀── Queue status updated
                            ◀── Back to Queue Screen
```

### Patient Read-Only Flow
```
Encounters List                 Encounter Detail
───────────────                 ─────────────────
[My Encounters] ──GET──▶ /patient/encounters
                               │
                    ◀── paginated list
                               │
[Tap Encounter] ──GET──▶ /patient/encounters/{id}
                               │
                    ◀── full detail (diagnosis, labs, imaging, rx, procedures)
```

---

## Appendix D: Endpoint Count Summary

| Category | Reused (Zero Changes) | New Code | Total |
|----------|----------------------|----------|-------|
| Doctor — Queue | 0 | 1 | 1 |
| Doctor — Encounter lifecycle | 0 | 2 | 2 |
| Doctor — Diagnosis | 3 | 0 | 3 |
| Doctor — Labs | 2 | 1 | 3 |
| Doctor — Imaging | 2 | 1 | 3 |
| Doctor — Prescriptions | 2 | 1 | 3 |
| Doctor — Procedures | 8 | 1 | 9 |
| Doctor — Vitals | 2 | 0 | 2 |
| Doctor — Search | 3 | 0 | 3 |
| Doctor — Encounter mgmt | 3 | 1 | 4 |
| Patient — All | 0 | 9 | 9 |
| **TOTAL** | **25** | **17** | **42** |

**25 out of 42 endpoints require ZERO backend code changes — only route aliasing.**
