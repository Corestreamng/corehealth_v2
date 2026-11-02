# LLM Note Polishing and Clinical Patient Summary Alignment

**Date**: 2026-11-02  
**Git Release Tag**: `v2.6.0.62`  
**Domain**: Artificial Intelligence, Clinical Documentation, Doctor New Encounter & Nursing Workbench

---

## 1. Executive Summary

CoreHealth v2 incorporates an enterprise AI suite for speech dictation, clinical note polishing, and longitudinal patient summaries. However, two critical workflow defects degraded the clinician experience:
1. **Note Polishing Skipping LLM Engine**: In both Doctor New Encounter (`admin.doctors.new_encounter`) and Nurse Workbench (`admin.nursing.workbench`), clicking "Polish Note" failed to invoke the configured LLM provider (e.g., Google Gemini), falling back silently to local offline regex/retext formatting.
2. **AI Summary "No Patient Selected" Error**: In Doctor New Encounter, clicking "AI Summary" consistently produced a toastr error: *"Please select a patient first to generate a clinical summary. (No Patient Selected)"*, despite a valid patient being loaded into the workspace.

This release eliminates both blockers through asset inclusion alignment, comprehensive multi-tier global context resolution, and relaxed backend validation contracts.

---

## 2. Root Cause Analysis

### 2.1 Note Polishing Skipping LLM Engine
- **Frontend Condition Constraint**: In `public/js/speech-dictation.js`, the note polishing dispatch logic executed `if (patId && encId)`.
  - In **Nurse Workbench**, nurse notes are charted at the patient level rather than within a doctor's active encounter. As established in `public/js/nursing-core.js`, `encounterId` is explicitly `null`.
  - In **Doctor New Encounter** (`/encounters/create?patient_id={id}`), a doctor is creating a *new* encounter that has not yet been persisted to the database. Consequently, `encounter_id` is blank/null.
  - Because `encId` was falsy in both clinical workspaces, `if (patId && encId)` evaluated to `false`, causing `speech-dictation.js` to bypass `POST /llm/polish-note` entirely and execute offline NLP (`this.scribe.process(polished)`).
- **Backend Validation Strictness**: `LlmController::polishNote` declared `'patient_id' => 'required|integer'`, rejecting general notes and forcing client-side logic to couple note polishing strictly to database IDs.

### 2.2 AI Summary False "No Patient Selected" Error
- **Missing Asset Dependency**: `resources/views/admin/doctors/partials/_encounter_scripts.blade.php` included `@include('admin.partials.patient_summary_overlay')` (the HTML modal) and `@include('admin.partials.ai_quick_actions')` (the FAB button), but **omitted** `<script src="{{ asset('js/patient-summary.js') }}"></script>`.
- **Undefined Class & Instance**: Because `patient-summary.js` was never loaded, `PatientSummaryManager` was `undefined`. In `public/js/encounter-page.js`, `new PatientSummaryManager(...)` failed silently.
- **Strict Guard Trigger**: When a clinician clicked AI Summary (via the speech kit button or the floating FAB), the guard condition `if (!patId || typeof window.patientSummary === 'undefined')` evaluated to `true` solely because `window.patientSummary` was `undefined`, triggering the deceptive "No Patient Selected" toastr.

---

## 3. Architecture & Enhancements

### 3.1 Doctor Encounter Script Stack (`_encounter_scripts.blade.php`)
- Injected `<script src="{{ asset('js/patient-summary.js') }}"></script>` into `_encounter_scripts.blade.php` immediately before `speech-dictation.js` and `encounter-page.js`.
- Synchronized initial global variables in the inline header script:
  ```javascript
  window.INVEST_RES_SOURCE = 'doctor_encounter';
  window.encounterId = '{{ $encId }}';
  window.patientId = '{{ $pId }}';
  window.currentPatientId = '{{ $pId }}';
  window.currentEncounterId = '{{ $encId }}';
  ```

### 3.2 Dynamic Context Resolution (`speech-dictation.js` & `ai_quick_actions.blade.php`)
- Implemented robust multi-tier fallback chains to discover the active patient and encounter across clinical modules:
  ```javascript
  let patId = window.currentPatientId || window.patientId || (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.patientId : null) || (window.patientSummary ? window.patientSummary.patientId : null) || (typeof currentPatient !== 'undefined' ? currentPatient : null) || null;
  let encId = window.currentEncounterId || window.encounterId || (window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.encounterId : null) || (window.patientSummary ? window.patientSummary.encounterId : null) || null;
  ```
- In `manualFormat()`, the LLM dispatch is no longer gated by `patId && encId`. The client attempts `POST /llm/polish-note` with whatever context is available (`patient_id` and optional `encounter_id`). If the LLM call succeeds, it formats the note; if it fails or if LLM features are disabled, it gracefully falls back to local NLP.

### 3.3 Patient Summary Initialization (`encounter-page.js`)
- Updated `PatientSummaryManager` instantiation in `encounter-page.js` to dynamically bind `patientId` and `encounterId` from `window.WORKBENCH_CONFIG`, with `autoOpen: false` and narration defaults.

### 3.4 Relaxed Backend Contract (`LlmController::polishNote`)
- Updated validation in `app/Http/Controllers/LlmController.php`:
  ```php
  $request->validate([
      'note_content' => 'required|string',
      'patient_id' => 'nullable|integer',
      'encounter_id' => 'nullable|integer',
  ]);
  ```
- If `patient_id` is provided, patient semantic context (allergies, diagnoses, medications, history, vitals) is fetched via `PatientContextService::getPatientContext()` and injected into the prompt.
- If `patient_id` is null (e.g. general clinical drafting), the system prompt polishes the note directly without error.

---

## 4. Verification & Testing

### 4.1 PHPUnit Test Suite
- `tests/Feature/AI/LlmIntegrationTest.php`:
  - `test_llm_summary_endpoint_requires_patient_id`: Confirms summary route requires authentication/patient.
  - `test_llm_clinical_vectors_endpoint_returns_14_categories`: Confirms RAG vectors.
  - `test_polish_note_endpoint_accepts_request_with_patient_and_null_encounter`: Tests exact payload from Nurse Workbench and unsaved Doctor New Encounter.
  - `test_polish_note_endpoint_accepts_standalone_note_without_patient`: Tests general note polishing without patient context.
  - `test_patient_summary_endpoint_requires_patient_id`: Confirms HTTP 422 on missing patient ID.
- **Results**: 7 tests, 17 assertions, 100% passing (`vendor/bin/phpunit tests/Feature/AI/LlmIntegrationTest.php`).

### 4.2 Code Standards & Linter Verification
- `composer lint`: 1326 files checked, 0 violations (PSR-12 compliant).
- `node --check public/js/speech-dictation.js`: Verified clean syntax.
- `node --check public/js/encounter-page.js`: Verified clean syntax.
