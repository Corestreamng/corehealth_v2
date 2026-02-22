# Clinical Orders Enhancement Plan

> Auto-Save · Duplicate Filtering · Re-Prescribe · Treatment Plans · Per-Drug Calculator  
> Applies uniformly to **Doctor Encounter** + **Nursing Workbench**

---

## Table of Contents

1. [Current State Audit](#1-current-state-audit)
2. [Phase 0 — Shared JS Module + UI Components](#2-phase-0--shared-js-module--ui-components)
3. [Phase 1 — Backend: Single-Item Endpoints + Trait](#3-phase-1--backend-single-item-endpoints--trait)
4. [Phase 2 — Auto-Save on Add + Duplicate Filtering](#4-phase-2--auto-save-on-add--duplicate-filtering)
5. [Phase 3 — Re-Prescribe from Previous Encounters](#5-phase-3--re-prescribe-from-previous-encounters)
6. [Phase 4 — Treatment Plans](#6-phase-4--treatment-plans)
7. [Implementation Order & Effort](#7-implementation-order--effort)
8. [Safety & Compatibility](#8-safety--compatibility)

---

## 1. Current State Audit

### 1.1 Architecture Comparison

| Aspect | Doctor Encounter | Nursing Workbench |
|---|---|---|
| **View file** | `new_encounter.blade.php` (4,143 lines) + tab partials in `doctors/partials/` | `nursing/workbench.blade.php` (18,214 lines) |
| **JS pattern** | Loose global functions across multiple `<script>` blocks + `@push('scripts')` partials | `ClinicalRequests` IIFE module (lines 7842–8310) |
| **Save style** | Bulk array POST → delete-and-reinsert (labs/imaging/meds), append-only (procedures) | Bulk array POST → append-only (all 4 types) |
| **Scope key** | `encounterId` (encounter-bound) | `patientId` (patient-bound, no encounter) |
| **Save endpoints** | `/encounters/{encounter}/save-labs\|save-imaging\|save-prescriptions\|save-procedures` | `/nursing-workbench/clinical-requests/labs\|imaging\|prescriptions\|procedures` |
| **Search APIs** | Same: `/live-search-products`, `/live-search-services` | Same |
| **Structured dose** | `buildStructuredDoseHtml()`, `updateStructuredDoseValue()` | `buildCrStructuredDoseHtml()`, `updateDoseVal()` — **identical logic, different names** |
| **Freq/dur maps** | `freqMultiplierMap`, `durUnitMultiplierMap` | `crFreqMultiplierMap`, `crDurUnitMultiplierMap` — **identical values** |
| **Dose calculator** | Global panel `#dose_calculator_panel`, `calculateDose()`, `applyCalculatorToSelected()` | Global panel `#cr_dose_calculator_panel`, `calculate()`, `applyToSelected()` |
| **Dose toggle** | Checkbox `#dose_mode_toggle`, unchecked (free-text) by default | Checkbox `#cr_dose_mode_toggle`, unchecked (free-text) by default |
| **Duplicate filter** | Procedures only (`selectedProcedures.some()` + "Already Added" badge) | Procedures only (same pattern) |
| **Patient weight** | ✅ `window.patientWeight` from last vital (just added) | ✅ `window.patientWeight` from `data.last_weight` (just added) |

### 1.2 Key Files

| File | Role |
|---|---|
| `app/Http/Controllers/EncounterController.php` | Doctor save endpoints: `saveLabs()`, `saveImaging()`, `savePrescriptions()`, `saveProcedures()` |
| `app/Http/Controllers/NursingWorkbenchController.php` | Nurse save endpoints: `saveNurseLabs()`, `saveNurseImaging()`, `saveNursePrescriptions()`, `saveNurseProcedures()` |
| `app/Models/LabServiceRequest.php` | Lab orders — fields: `service_id`, `note`, `encounter_id`, `patient_id`, `doctor_id` |
| `app/Models/ImagingServiceRequest.php` | Imaging orders — same shape as lab |
| `app/Models/ProductRequest.php` | Prescriptions — fields: `product_id`, `dose`, `encounter_id`, `patient_id`, `doctor_id` |
| `app/Models/Procedure.php` | Procedure orders — includes `priority`, `scheduled_date`, `pre_notes`, billing via `ProductOrServiceRequest` |
| `resources/views/admin/doctors/partials/medications.blade.php` | Doctor medications tab HTML (168 lines) |
| `resources/views/admin/doctors/partials/laboratory_services.blade.php` | Doctor labs tab HTML |
| `resources/views/admin/doctors/partials/imaging_services.blade.php` | Doctor imaging tab HTML |
| `resources/views/admin/doctors/partials/procedures.blade.php` | Doctor procedures tab HTML + JS (1,312 lines) |
| `routes/web.php` | Doctor encounter routes |
| `routes/nursing_workbench.php` | Nurse workbench routes (clinical-requests group at line 188) |

### 1.3 Dose Value Format

Structured dose produces a pipe-delimited string stored in the `dose` column:

```
500mg | PO | TDS | 5 days | Qty: 15
```

Components: `amount+unit | route | frequency | duration+duration_unit | Qty: calculated`

---

## 2. Phase 0 — Shared JS Module + UI Components

### 2.1 Shared JavaScript: `public/js/clinical-orders-shared.js`

Extract all duplicated logic into a single `ClinicalOrdersKit` namespace.

#### Functions to extract:

| Shared Function | Replaces (Doctor) | Replaces (Nurse) |
|---|---|---|
| `ClinicalOrdersKit.buildStructuredDoseHtml(prefix, rowId)` | `buildStructuredDoseHtml()` | `buildCrStructuredDoseHtml()` |
| `ClinicalOrdersKit.updateDoseValue(el, prefix)` | `updateStructuredDoseValue()` | `updateDoseVal()` |
| `ClinicalOrdersKit.autoCalculateQty($row, prefix)` | `autoCalculateQty()` | `autoCalculateCrQty()` |
| `ClinicalOrdersKit.toggleDoseMode(isStructured, config)` | `toggleDoseMode()` | IIFE `toggleDoseMode()` |
| `ClinicalOrdersKit.renderCoverageBadge(mode, payable, claims)` | Duplicated 8+ times inline | Same |
| `ClinicalOrdersKit.showInlineMessage(containerId, msg, type, autoClose)` | `showMessage()` | IIFE `showMessage()` |
| `ClinicalOrdersKit.debounce(fn, ms)` | (new, for auto-save) | Same |
| `ClinicalOrdersKit.FREQ_MULTIPLIER_MAP` | `freqMultiplierMap` | `crFreqMultiplierMap` |
| `ClinicalOrdersKit.DUR_UNIT_MULTIPLIER_MAP` | `durUnitMultiplierMap` | `crDurUnitMultiplierMap` |
| `ClinicalOrdersKit.parseStrengthFromName(name)` | (new) | (new) |
| `ClinicalOrdersKit.buildInlineCalculatorHtml(...)` | (new, replaces global calc panel) | (new) |
| `ClinicalOrdersKit.liveCalc(rowId, prefix)` | `calculateDose()` | `calculate()` |
| `ClinicalOrdersKit.applyCalcToRow(rowId, prefix)` | `applyCalculatorToSelected()` | `applyToSelected()` |
| `ClinicalOrdersKit.toggleRowCalc(btn, drugName, rowId, prefix)` | (new) | (new) |
| `ClinicalOrdersKit.closeCalc(rowId, prefix)` | (new) | (new) |
| `ClinicalOrdersKit.initDoseModeToggle(config)` | (new) | (new) |
| `ClinicalOrdersKit.addItem(config)` | (new, for auto-save) | (new) |
| `ClinicalOrdersKit.removeItem(config)` | (new, for auto-save) | (new) |

#### Selector convention:

- **Doctor** uses unprefixed: `#selected-products`, `.dose-amount`, `consult_presc_dose[]`
- **Nurse** uses `cr-` prefix: `#cr-selected-products`, `.cr-dose-amount`, `cr_presc_dose[]`
- Shared module accepts a **config object**: `{ tableSelector, dosePrefix, inputNamePrefix, endpointBase, csrfToken }`

#### How each view imports:

```html
<!-- Both views, before their own <script> blocks -->
<script src="{{ asset('js/clinical-orders-shared.js') }}"></script>
```

Doctor functions become thin wrappers; Nurse `ClinicalRequests` IIFE delegates to `ClinicalOrdersKit.*`.

### 2.2 Dose Mode Toggle — Segmented Button Group (Default: Structured)

**Replace** the bare checkbox switch in both views with a segmented radio button group.

**New shared partial:** `resources/views/admin/partials/dose-mode-toggle.blade.php`

```html
{{-- @param $prefix: '' for doctor, 'cr_' for nurse --}}
<div class="dose-mode-toggle-group mb-3">
    <div class="btn-group btn-group-sm" role="group" aria-label="Dose entry mode">
        <input type="radio" class="btn-check" name="{{$prefix}}dose_mode" 
               id="{{$prefix}}dose_mode_simple" value="simple" autocomplete="off">
        <label class="btn btn-outline-secondary" for="{{$prefix}}dose_mode_simple">
            <i class="fa fa-pen"></i> Simple Note
        </label>
        <input type="radio" class="btn-check" name="{{$prefix}}dose_mode" 
               id="{{$prefix}}dose_mode_structured" value="structured" autocomplete="off" checked>
        <label class="btn btn-outline-primary" for="{{$prefix}}dose_mode_structured">
            <i class="fa fa-th-list"></i> Structured Dose
        </label>
    </div>
    <div class="form-text text-muted mt-1" style="font-size: 0.78em; max-width: 520px;">
        <strong>Structured</strong>: amount, unit, route, frequency, duration &amp; qty in 
        separate fields for precision.
        <strong>Simple</strong>: free text (e.g. "500mg BD × 5 days").
    </div>
</div>
```

**Inclusion:**
- Doctor: `@include('admin.partials.dose-mode-toggle', ['prefix' => ''])`  
  (replaces the checkbox in `medications.blade.php` line 63–69)
- Nurse: `@include('admin.partials.dose-mode-toggle', ['prefix' => 'cr_'])`  
  (replaces the checkbox in `workbench.blade.php` lines 4950–4955)

**Key changes from current:**
- Structured is **checked by default** (currently unchecked → free-text default)
- Two-button segmented design makes the current mode obvious at a glance
- Explanation text tells users what each mode does

**JS wiring** (in `ClinicalOrdersKit.initDoseModeToggle`):

```js
ClinicalOrdersKit.initDoseModeToggle = function(config) {
    // config: { prefix, tableSelector, onToggle }
    const simpleRadio = document.getElementById(config.prefix + 'dose_mode_simple');
    const structuredRadio = document.getElementById(config.prefix + 'dose_mode_structured');
    
    // Initial state: structured ON (matches the checked attribute)
    config.isStructured = true;
    
    simpleRadio.addEventListener('change', () => {
        config.isStructured = false;
        ClinicalOrdersKit.convertDoseRows(config, false);
        if (config.onToggle) config.onToggle(false);
    });
    structuredRadio.addEventListener('change', () => {
        config.isStructured = true;
        ClinicalOrdersKit.convertDoseRows(config, true);
        if (config.onToggle) config.onToggle(true);
    });
};
```

### 2.3 Per-Drug Inline Dose Calculator (Replaces Global Panel)

#### Problem with current global calculator:
- One panel for all drugs — unclear which drug the calculation targets
- "Apply to Selected" applies to the last row, but user doesn't know which
- No auto-detection of drug strength from the product name
- On mobile the hidden/toggled panel is awkward

#### New design: Inline per-drug calculator

Each drug row in the selection table gets a small **calculator button**. Clicking it expands an inline calculator `<tr>` directly below that drug row.

##### Drug-name strength scraping:

```js
ClinicalOrdersKit.parseStrengthFromName = function(name) {
    // Matches: "500mg", "500 mg", "40ML", "4 ml", "250MG", "100mcg", "50IU"
    const match = name.match(/(\d+(?:\.\d+)?)\s*(mg|g|ml|mcg|iu|units?)\b/i);
    if (match) {
        return {
            amount: parseFloat(match[1]),
            unit: match[2].toLowerCase().replace(/^unit$/, 'units')
        };
    }
    return null;
};
```

**Examples:**

| Product Name | Parsed | Pre-fills |
|---|---|---|
| `Amoxicillin 500mg Capsules` | `{ amount: 500, unit: 'mg' }` | Strength: 500, unit: mg, badge: "auto-detected" |
| `Ibuprofen 400 MG Tab` | `{ amount: 400, unit: 'mg' }` | Strength: 400 |
| `Normal Saline 500ml` | `{ amount: 500, unit: 'ml' }` | Strength: 500, unit: ml |
| `Paracetamol Syrup` | `null` | Strength: blank (user fills in) |

##### Calculator button per drug row:

Added after the Qty field in `buildStructuredDoseHtml()`:

```html
<button type="button" class="btn btn-sm btn-outline-info mt-1 w-100 calc-toggle-btn"
        onclick="ClinicalOrdersKit.toggleRowCalc(this, '{drugName}', '{rowId}', '{prefix}')">
    <i class="fa fa-calculator"></i> Calculator
</button>
```

##### Inline calculator row (colspan `<tr>`):

```
┌──────────────────────────────────────────────────────────────────────┐
│ 🧮 Calculator for Amoxicillin 500mg Cap                              │
│ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌─────────────┐ │
│ │Weight: [25]kg│ │Dose/kg:[25]  │ │Freq: [TDS ▾] │ │Dur: [5][d▾] │ │
│ └──────────────┘ └──────────────┘ └──────────────┘ └─────────────┘ │
│ ┌───────────────────────┐                                           │
│ │Strength: [500] mg     │ (auto-detected)                          │
│ └───────────────────────┘                                           │
│ ┌──────────────────────────────────────────────────────────────────┐│
│ │ Single: 625mg │ Daily: 1875mg │ Course: 9375mg │ Qty: 19 × 500mg││
│ └──────────────────────────────────────────────────────────────────┘│
│                               [ ✅ Apply to this drug ] [ ✕ Close ] │
└──────────────────────────────────────────────────────────────────────┘
```

**Mobile layout** — fields use `col-6 col-md-3` so they stack as 2×2 grid on small screens.

##### Calculator logic (shared):

```js
ClinicalOrdersKit.liveCalc = function(rowId, prefix) {
    // Read weight, dose/kg, frequency, duration, duration-unit, tablet strength
    // Calculate: singleDose, dailyDose, totalCourse, totalUnits
    // Display results live in the results container
};

ClinicalOrdersKit.applyCalcToRow = function(rowId, prefix) {
    // If structured mode: fill .dose-amount, .dose-unit, .dose-frequency, 
    //   .dose-duration, .dose-duration-unit, .dose-qty → call updateDoseValue()
    // If simple mode: compose "625mg | PO | TDS | 5 days | Qty: 19" → fill text input
    // Close calculator row with brief green flash (visual confirmation)
    // toastr: "Calculator applied to Amoxicillin 500mg"
};
```

##### Weight persistence across drugs:

- Calculator pre-fills weight from `window.patientWeight` (already wired for both views)
- If user changes weight in one calculator, it updates `window.patientWeight` so next calculator opened has the same value
- Critical for pediatric encounters (same weight → 5–10 medications)

##### Removal of global calculator panel:

- **Delete** `#dose_calculator_panel` from `medications.blade.php` (lines 76–127)
- **Delete** `#cr_dose_calculator_panel` from `workbench.blade.php` (lines 4963–5009)
- **Delete** global functions: `toggleDoseCalculator()`, `calculateDose()`, `applyCalculatorToSelected()` (doctor) and `ClinicalRequests.toggleCalculator()`, `.calculate()`, `.applyToSelected()` (nurse)
- **Delete** the "Dose Calculator" button (currently next to the toggle)
- All replaced by the per-drug inline calculator

### 2.4 Shared CSS

Either in `public/css/clinical-orders-shared.css` or inline `<style>` in the shared partial:

```css
/* Dose mode toggle */
.dose-mode-toggle-group .btn-group .btn { min-width: 120px; }
.dose-mode-toggle-group .btn-check:checked + .btn-outline-primary {
    background: #0d6efd; color: #fff; font-weight: 600;
}
.dose-mode-toggle-group .btn-check:checked + .btn-outline-secondary {
    background: #6c757d; color: #fff; font-weight: 600;
}

/* Inline calculator */
.dose-calc-inline { max-width: 100%; }
.dose-calc-inline .form-label { font-weight: 600; color: #495057; }

/* Calculator row slide animation */
tr.calc-enter { animation: calcSlideDown 0.2s ease-out; }
@keyframes calcSlideDown {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Mobile stack */
@media (max-width: 576px) {
    .dose-calc-inline .row > [class*="col-md-"] { margin-bottom: 0.25rem; }
    .dose-mode-toggle-group .btn-group { width: 100%; }
    .dose-mode-toggle-group .btn-group .btn { flex: 1; font-size: 0.85em; }
}
```

---

## 3. Phase 1 — Backend: Single-Item Endpoints + Trait

### 3.1 Shared Trait: `App\Http\Traits\ClinicalOrdersTrait`

Eliminates duplicated save logic between `EncounterController` and `NursingWorkbenchController`.

```php
namespace App\Http\Traits;

trait ClinicalOrdersTrait
{
    // --- Labs ---
    protected function addSingleLab(int $serviceId, ?string $note, int $patientId, ?int $encounterId): LabServiceRequest
    {
        $lab = new LabServiceRequest();
        $lab->service_id = $serviceId;
        $lab->note = $note;
        $lab->patient_id = $patientId;
        $lab->encounter_id = $encounterId;  // null for nurse
        $lab->doctor_id = Auth::id();
        $lab->save();
        return $lab;
    }

    protected function removeSingleLab(int $id): void
    {
        LabServiceRequest::where('id', $id)->where('doctor_id', Auth::id())->delete();
    }

    // --- Imaging ---
    protected function addSingleImaging(int $serviceId, ?string $note, int $patientId, ?int $encounterId): ImagingServiceRequest { /* same pattern */ }
    protected function removeSingleImaging(int $id): void { /* same pattern */ }

    // --- Prescriptions ---
    protected function addSinglePrescription(int $productId, string $dose, int $patientId, ?int $encounterId): ProductRequest { /* same pattern */ }
    protected function updatePrescriptionDose(int $id, string $dose): ProductRequest
    {
        $presc = ProductRequest::findOrFail($id);
        $presc->dose = $dose;
        $presc->save();
        return $presc;
    }
    protected function removeSinglePrescription(int $id): void { /* same pattern */ }

    // --- Procedures ---
    protected function addSingleProcedure(array $data, int $patientId, ?int $encounterId): Procedure
    {
        // Create Procedure + ProductOrServiceRequest (billing) — 
        // extracted from existing saveProcedures() / saveNurseProcedures()
    }
    protected function removeSingleProcedure(int $id): void { /* same pattern */ }
}
```

### 3.2 New Routes — Doctor (EncounterController)

Add to the existing encounter route group:

```php
// Single-item clinical order endpoints (for auto-save)
Route::post('/encounters/{encounter}/add-lab',                'addLabItem');
Route::delete('/encounters/{encounter}/remove-lab/{id}',      'removeLabItem');
Route::post('/encounters/{encounter}/add-imaging',            'addImagingItem');
Route::delete('/encounters/{encounter}/remove-imaging/{id}',  'removeImagingItem');
Route::post('/encounters/{encounter}/add-prescription',       'addPrescriptionItem');
Route::put('/encounters/{encounter}/update-prescription/{id}','updatePrescriptionDose');
Route::delete('/encounters/{encounter}/remove-prescription/{id}', 'removePrescriptionItem');
Route::post('/encounters/{encounter}/add-procedure',          'addProcedureItem');
Route::delete('/encounters/{encounter}/remove-procedure/{id}','removeProcedureItem');
```

### 3.3 New Routes — Nurse (NursingWorkbenchController)

Add to the `clinical-requests` group in `routes/nursing_workbench.php`:

```php
Route::post('/add-lab',                'addNurseLabItem');
Route::delete('/remove-lab/{id}',      'removeNurseLabItem');
Route::post('/add-imaging',            'addNurseImagingItem');
Route::delete('/remove-imaging/{id}',  'removeNurseImagingItem');
Route::post('/add-prescription',       'addNursePrescriptionItem');
Route::put('/update-prescription/{id}','updateNursePrescriptionDose');
Route::delete('/remove-prescription/{id}', 'removeNursePrescriptionItem');
Route::post('/add-procedure',          'addNurseProcedureItem');
Route::delete('/remove-procedure/{id}','removeNurseProcedureItem');
```

### 3.4 Response Shape (all add endpoints)

```json
{
    "success": true,
    "item": { "id": 42, "service_id": 7, "note": "...", "created_at": "2026-02-21T..." },
    "message": "Lab request added"
}
```

The returned `item.id` is stored on the `<tr>` as `data-record-id` so the remove button can call `DELETE /remove-lab/42`.

### 3.5 Backward Compatibility

**Old bulk save endpoints stay alive.** `saveLabs()`, `saveImaging()`, `savePrescriptions()`, `saveProcedures()` and their nurse equivalents remain untouched. They continue to work as fallback and for any code paths we haven't found.

---

## 4. Phase 2 — Auto-Save on Add + Duplicate Filtering

### 4.1 Auto-Save Behavior (all 4 types, both views)

| Type | On search-result click | Save timing | UI feedback |
|---|---|---|---|
| **Labs** | Instant POST → row appears with spinner → ✓ | Immediate | Row fades in, checkmark |
| **Imaging** | Instant POST → row appears with spinner → ✓ | Immediate | Row fades in, checkmark |
| **Medications** | POST with empty dose → row appears with structured dose builder | Add: immediate. Dose: debounced 800ms PUT on field change | Tiny "Saving…" → "Saved ✓" beside Qty field |
| **Procedures** | User fills priority/date/notes, clicks result → instant POST | Immediate | Row fades in, checkmark |

**Remove:** Each row's ✕ button calls `DELETE /remove-{type}/{id}`, then fades out the row.

### 4.2 Shared JS Flow

```js
ClinicalOrdersKit.addItem = function(config) {
    // config: { url, payload, tableSelector, buildRowHtml, csrfToken, onSuccess }
    // 1. Show temp row with spinner
    // 2. POST to url with payload
    // 3. On success: replace spinner row with real row (data-record-id from response)
    // 4. Call onSuccess callback (e.g. reload history DataTable)
    // 5. On error: remove temp row, show toastr error
};

ClinicalOrdersKit.removeItem = function(config) {
    // config: { url, rowSelector, csrfToken, onSuccess }
    // 1. Confirm if needed
    // 2. Fade out row
    // 3. DELETE to url
    // 4. On success: remove row from DOM, call onSuccess
    // 5. On error: fade row back in, show toastr error
};
```

Both views call the same functions with different URLs:

```js
// Doctor: adding a lab
ClinicalOrdersKit.addItem({
    url: `/encounters/${encounterId}/add-lab`,
    payload: { service_id: 7, note: '' },
    tableSelector: '#selected-services',
    buildRowHtml: (item) => `<tr data-record-id="${item.id}">...</tr>`,
    csrfToken: csrfToken,
    onSuccess: () => { $('#investigation_history_list').DataTable().ajax.reload(); }
});

// Nurse: adding a lab
ClinicalOrdersKit.addItem({
    url: '/nursing-workbench/clinical-requests/add-lab',
    payload: { patient_id: patientId, service_id: 7, note: '' },
    tableSelector: '#cr-selected-labs',
    buildRowHtml: (item) => `<tr data-record-id="${item.id}">...</tr>`,
    csrfToken: CSRF_TOKEN,
    onSuccess: () => { initLabHistory(); }
});
```

### 4.3 Medication Two-Phase Save

Medications are special because of the structured dose complexity:

1. **Phase 1 (instant):** Click search result → `POST /add-prescription` with `{ product_id, dose: '' }` → row appears with structured dose builder, ready for input
2. **Phase 2 (debounced):** Any change to dose fields fires `PUT /update-prescription/{id}` after 800ms debounce → tiny "Saved ✓" indicator

If user navigates away with empty dose, the backend already handles that (logs a warning, saves empty dose — matches existing behavior on both controllers).

### 4.4 Duplicate Filtering

Track added IDs per type in a `Set`:

```js
ClinicalOrdersKit.addedIds = {
    labs: new Set(),
    imaging: new Set(),
    meds: new Set(),
    procedures: new Set()
};
```

- On `addItem` success → `addedIds[type].add(referenceId)`
- On `removeItem` success → `addedIds[type].delete(referenceId)`
- Search result renderers check `addedIds[type].has(id)` and show "Already Added" badge + disable click

This extends the existing procedure-only pattern to **all 4 types**.

### 4.5 UI Changes

- **Remove** "Save" and "Save & Next" buttons from all 4 tabs (both views)
- **Replace** with a status line: `"3 items added (auto-saved)"` at bottom of selection table
- **Keep navigation:** Add a "Next →" button that just switches tabs (no save needed)
- **History DataTable** auto-reloads after each add/remove

---

## 5. Phase 3 — Re-Prescribe from Previous Encounters

### 5.1 Backend

**New endpoints (shared via trait):**

```php
// Doctor
Route::post('/encounters/{encounter}/re-prescribe', 'rePrescribe');

// Nurse
Route::post('/nursing-workbench/clinical-requests/re-prescribe', 'rePrescribeNurse');
```

**Request:**

```json
{
    "source_type": "prescriptions",        // "labs" | "imaging" | "prescriptions"
    "source_ids": [12, 15, 18],            // IDs of original records to copy
    "adjust_doses": { "12": "500mg | PO | BD | 7 days | Qty: 14" }  // optional overrides
}
```

**Trait method:**

```php
protected function rePrescribeItems(
    string $type, 
    array $sourceIds, 
    int $patientId, 
    ?int $encounterId, 
    array $doseOverrides = []
): Collection
{
    // Load original records by ID
    // Create new copies linked to current encounter/patient
    // Apply dose overrides if provided
    // Return collection of new records
}
```

### 5.2 Frontend — History Tab Enhancement

Each history row gets a small action button:

- **Labs/Imaging:** `Re-order` button → calls re-prescribe endpoint → item auto-saves into current tab
- **Medications:** `Re-prescribe` button → item appears with **original dose pre-filled** in structured dose builder (editable)

Button appearance:
```html
<button class="btn btn-sm btn-outline-primary">
    <i class="fa fa-redo"></i> Re-order
</button>
```

### 5.3 Re-Prescribe All from Encounter

At the top of each history tab, add a dropdown:

1. Shows last 5 encounters for this patient
2. Selecting one loads all items from that encounter
3. Labs/imaging auto-save immediately; meds appear with dose pre-filled

**Shared JS:**

```js
ClinicalOrdersKit.rePrescribe = function(config) {
    // config: { url, sourceType, sourceIds, tableSelector, buildRowHtml, csrfToken }
    // POST to re-prescribe endpoint
    // For each returned item, render row in selection table
    // If auto-save: items are already persisted on the backend
};
```

---

## 6. Phase 4 — Treatment Plans

### 6.1 Database Schema

```sql
CREATE TABLE treatment_plans (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,         -- e.g. "Malaria Protocol (Adult)"
    description     TEXT NULL,
    specialty       VARCHAR(100) NULL,             -- optional specialty filter
    created_by      INT UNSIGNED NOT NULL,
    is_global       BOOLEAN DEFAULT FALSE,         -- visible to all or just creator
    status          ENUM('active','archived') DEFAULT 'active',
    created_at      TIMESTAMP NULL,
    updated_at      TIMESTAMP NULL,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE treatment_plan_items (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    treatment_plan_id   BIGINT UNSIGNED NOT NULL,
    item_type           ENUM('lab','imaging','medication','procedure') NOT NULL,
    reference_id        INT UNSIGNED NOT NULL,     -- services.id or products.id
    dose                VARCHAR(500) NULL,         -- medication only (pipe-delimited)
    note                TEXT NULL,                 -- labs/imaging clinical note, or procedure pre_notes
    priority            VARCHAR(20) NULL,          -- procedure only
    sort_order          INT DEFAULT 0,
    created_at          TIMESTAMP NULL,
    updated_at          TIMESTAMP NULL,
    FOREIGN KEY (treatment_plan_id) REFERENCES treatment_plans(id) ON DELETE CASCADE
);
```

### 6.2 Models

```
App\Models\TreatmentPlan       — hasMany(TreatmentPlanItem::class)
App\Models\TreatmentPlanItem   — belongsTo(TreatmentPlan::class)
```

`TreatmentPlanItem` morphs to `service` or `product` via `item_type` + `reference_id`.

### 6.3 Endpoints

```php
// CRUD (both doctor and nurse can use)
GET    /treatment-plans                    // list (filterable by specialty, creator, global)
POST   /treatment-plans                    // create { name, description, specialty, is_global, items[] }
GET    /treatment-plans/{id}               // show with items + resolved names/prices
PUT    /treatment-plans/{id}               // update
DELETE /treatment-plans/{id}               // archive (soft-delete)

// Apply to encounter/patient
POST   /encounters/{encounter}/apply-treatment-plan
POST   /nursing-workbench/clinical-requests/apply-treatment-plan

// Quick-save current selection as template
POST   /treatment-plans/from-current
```

**Apply logic** (shared trait): Load plan items → call `addSingle*` for each → return all created records.

### 6.4 Frontend — Treatment Plan Modal

**New shared partial:** `resources/views/admin/partials/treatment-plan-modal.blade.php`

**Access buttons** (added to the top of all 4 tab areas, both views):

```html
<div class="btn-group mb-2">
    <button class="btn btn-sm btn-outline-secondary" 
            data-bs-toggle="modal" data-bs-target="#treatmentPlanModal">
        <i class="fa fa-clipboard-list"></i> Treatment Plans
    </button>
    <button class="btn btn-sm btn-outline-success" 
            onclick="ClinicalOrdersKit.treatmentPlans.saveFromCurrent()">
        <i class="fa fa-save"></i> Save as Template
    </button>
</div>
```

**Modal layout:**

- **Browse tab:** Searchable list — "My Plans" / "Global Plans" tabs, specialty filter
- **Preview panel:** Expanding a plan shows items grouped by type with checkboxes
- **Apply button:** `Apply Selected Items` → calls apply endpoint → items auto-save → modal closes → history reloads

**"Save current as template":**

- Gathers all currently-added items across the 4 tabs
- Opens small modal: plan name, description, `is_global` toggle
- POSTs to `/treatment-plans/from-current`

**Shared JS:**

```js
ClinicalOrdersKit.treatmentPlans = {
    browse: (modal, filters) => { /* load list via GET /treatment-plans */ },
    preview: (planId, container) => { /* load details via GET /treatment-plans/{id} */ },
    apply: (planId, context) => { /* POST to apply endpoint */ },
    saveFromCurrent: () => { /* gather items, open save modal, POST */ }
};
```

---

## 7. Implementation Order & Effort

| Step | Scope | Files Touched | Risk | Effort |
|---|---|---|---|---|
| **0a.** `clinical-orders-shared.js` + CSS | New file | `public/js/`, both view `<script src>` | Low (additive) | ~4 hrs |
| **0b.** Dose mode toggle partial | New partial + replace in 2 views | `partials/dose-mode-toggle.blade.php`, `medications.blade.php`, `workbench.blade.php` | Low | ~2 hrs |
| **0c.** Per-drug inline calculator | Replace global panel in both views + shared JS | `medications.blade.php`, `workbench.blade.php`, shared JS, `new_encounter.blade.php` | Medium | ~4 hrs |
| **0d.** Remove old global calculator | Delete from 2 views + 2 JS blocks | `medications.blade.php`, `workbench.blade.php`, `new_encounter.blade.php` | Low | ~1 hr |
| **0e.** Weight pre-fill wiring | Connect `window.patientWeight` to calc | Shared JS | Low | ~30 min |
| | | | | |
| **1a.** `ClinicalOrdersTrait` | New trait | `app/Http/Traits/ClinicalOrdersTrait.php` | Low | ~3 hrs |
| **1b.** Single-item routes + controller methods | 18 new routes | `EncounterController`, `NursingWorkbenchController`, 2 route files | Low (new, no existing changes) | ~4 hrs |
| | | | | |
| **2a.** Auto-save JS wiring (labs/imaging/procedures) | Both views | `new_encounter.blade.php`, `workbench.blade.php`, shared JS | Medium | ~6 hrs |
| **2b.** Two-phase medication auto-save | Both views | Same as above + debounce PUT logic | Medium | ~4 hrs |
| **2c.** Duplicate filtering in search results | Both views | Search result renderers in both views | Low | ~2 hrs |
| **2d.** Remove Save buttons + add status line | Both views | 4 tab partials (doctor) + workbench HTML | Low | ~2 hrs |
| | | | | |
| **3a.** Re-prescribe backend (trait method + routes) | Backend | Trait, 2 controllers, 2 route files | Low | ~3 hrs |
| **3b.** Re-prescribe buttons in history DataTables | Both views | DataTable renderers (server-side) | Medium | ~4 hrs |
| **3c.** "Re-prescribe from encounter" dropdown | Both views | New UI component + JS | Medium | ~4 hrs |
| | | | | |
| **4a.** Treatment plans migration + models | Backend | `database/migrations/`, `app/Models/` | Low | ~2 hrs |
| **4b.** Treatment plans CRUD controller + routes | Backend | New controller + routes | Low | ~4 hrs |
| **4c.** Apply treatment plan endpoints | Backend | 2 controllers + trait | Medium | ~3 hrs |
| **4d.** Treatment plan modal (shared partial + JS) | Both views | New partial, shared JS, include in both views | Medium | ~6 hrs |
| | | | | |
| **5.** QA + edge cases + mobile testing | All | All | — | ~8 hrs |

### Summary

| Phase | Description | Effort |
|---|---|---|
| **Phase 0** | Shared JS module + toggle + per-drug calculator | ~1.5 days |
| **Phase 1** | Backend trait + single-item endpoints | ~1 day |
| **Phase 2** | Auto-save + duplicate filtering | ~1.75 days |
| **Phase 3** | Re-prescribe from history | ~1.5 days |
| **Phase 4** | Treatment plans (full feature) | ~2 days |
| **Phase 5** | QA + polish | ~1 day |
| **Total** | | **~8.75 days** |

---

## 8. Safety & Compatibility

### 8.1 Non-Breaking Guarantees

1. **Old bulk save endpoints stay alive** — `saveLabs()`, `saveImaging()`, `savePrescriptions()`, `saveProcedures()` and nurse equivalents remain untouched as fallback
2. **New single-item endpoints always append** — they do NOT use the doctor's delete-and-reinsert pattern
3. **Selector IDs preserved** — all existing `#selected-products`, `#consult_presc_search`, `#cr-selected-labs`, etc. keep their IDs
4. **Form field names preserved** — `consult_presc_id[]`, `consult_presc_dose[]`, `cr_presc_id[]`, etc. unchanged to avoid breaking existing form submissions

### 8.2 Medication Dose Safety

- **Two-phase save** — add saves immediately with empty dose. Dose auto-saves via debounced PUT after user completes fields
- **Empty dose warning** — backend already handles and warns about empty doses (both controllers)
- **Structured default** — standardized pipe-delimited dose is the default, reducing dosing errors
- **Per-drug calculator** — tie calculator to specific drug row, eliminating "which drug?" confusion

### 8.3 Authorization

- New endpoints inherit the same middleware groups as existing ones (`auth`, workbench guards)
- Trait methods check `Auth::id()` for ownership on delete operations
- Treatment plans: only creator or global plans visible; `is_global` requires appropriate permission

### 8.4 CSRF

All new endpoints use the existing CSRF token middleware. JS passes `_token` in every AJAX request (already established pattern in both views).

### 8.5 Already Completed ✅

- `window.patientWeight` wired in doctor encounter (`new_encounter.blade.php` line 3127)
- `window.patientWeight` wired in nurse workbench (`workbench.blade.php` line 7763)
- `last_weight` added to nurse patient details API (`NursingWorkbenchController::getPatientDetails()`)
- `weight` and `spo2` added to `last_vitals` response object
