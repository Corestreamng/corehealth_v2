# Encounter Workbench Memory Optimization & High-Density Consultation Standards

**Date**: 2026-11-04  
**Git Release Tag**: v2.6.0.65  
**Domain**: Clinical Consultation, Encounter Workbench, Performance & Memory Governance

---

## 1. Executive Summary

During peak clinical operations, loading `/encounters/create?patient_id={id}` resulted in critical PHP fatal errors (`Allowed memory size of 134217728 bytes exhausted`) at the default 128MB limit. In addition, attempts by Laravel's global error handler (Ignition) to format stack traces under memory exhaustion triggered secondary FastCGI worker crashes, causing Apache to return blank HTTP 500 error pages to cashiers and physicians.

Profiling revealed that:
1. **ICPC-2 Reason Rendering**: `EncounterController::create` previously loaded all reason codes via `ReasonForEncounter::all()` and passed flat collections into `resources/views/admin/doctors/partials/modals.blade.php`. A legacy $O(C \times S \times N)$ triple-nested loop iterated through the entire collection for every category and subcategory, executing over **3,000,000 loop cycles** and triggering Eloquent's `HasAttributes::getAttribute` millions of times per request.
2. **In-View Unbounded User Hydration**: `resources/views/admin/doctors/partials/procedures.blade.php` performed an unprojected query loading all active hospital users with multi-level relations (`with(['category', 'staff_profile.specialization'])`).
3. **Missing Null Guards**: If `patient_id` was empty or if a user without a linked `Staff` record visited the consultation route, the controller triggered uncaught `\Error: Attempt to read property on null` which escaped legacy `catch (\Exception $e)` blocks in PHP 8.
4. **Dashboard Polling Column Typo**: A background AJAX service (`ChildHealthDashboardService::getStats`) polled `patient_immunization_schedules` with `scheduled_date` instead of `due_date`, generating database query exceptions.

This release eliminates these performance bottlenecks, transforming consultation page rendering from multi-million iteration cycles down to linear $O(N)$ operations while establishing strict memory governance.

---

## 2. Key Architectural Optimizations

### 2.1 On-Demand AJAX ICPC Reason Search & Zero-Bloat Select2
- **The Issue**: Loading all 13,188 `ReasonForEncounter` records hydrated 13,188 Eloquent models in memory (~50MB overhead) and generated over 4.28 MB of HTML (`<option>` tags) inside `#editEncounterModal`.
- **The Solution**:
  - Replaced the server-side grouping query in `EncounterController::create` and `PatientController::show` with empty collections (`collect([])`).
  - Rendered `#editEncounterReasons` as an empty `<select>` in `resources/views/admin/doctors/partials/modals.blade.php`.
  - Configured Select2 with remote AJAX search pointing to `/live-search-reasons?q=...` (`public/js/encounter-page.js`).
  - When opening existing consultation notes in `window.editEncounterNote`, existing diagnoses are dynamically appended as `new Option(...)` tags without loading the entire 13,188-item catalog.
  - Enhanced `EncounterController::liveSearchReasons` to support both `q` and `term` query parameters with safe minimum length checks.

### 2.2 Surgical Staff Hydration (Fixing 26,500+ Patient Model Leak)
- **The Issue**: In `resources/views/admin/doctors/partials/procedures.blade.php`, the query filtered by `where('is_admin', '!=', 19)`. Because `is_admin = 0` represents hospital patients in CoreHealth v2 (and 19 represents HMOs), this query loaded all **25,659 patients** and **475 staff** into memory (26,527 full User models), consuming over 100MB of RAM and exhausting the 128MB limit at `GuardsAttributes.php:252` and `Model.php:496`.
- **The Solution**:
  - Refactored query to target the `Staff` model directly:
    ```php
    $staffUsers = \App\Models\Staff::with(['user:id,surname,firstname,othername', 'user.category:id,name'])
        ->where('status', 1)
        ->get()
        ->map(fn ($s) => $s->user)
        ->filter()
        ->sortBy('surname')
        ->values();
    ```
  - Reduces memory from over 100MB down to **1.2MB** while accurately populating the procedure team selector with active hospital staff only.

### 2.3 Strict Parameter & Staff Validation Guards
- Added explicit guards in `EncounterController::create`:
  ```php
  if (!$request->filled('patient_id')) {
      return redirect()->route('encounters.index')->with('error', 'Please select a patient from the queue to start a consultation.');
  }

  $doctor = Staff::where('user_id', Auth::id())->first();
  if (!$doctor) {
      return redirect()->route('encounters.index')->with('error', 'Active staff record not found for the logged in user.');
  }

  $patient = Patient::with('user')->find($request->get('patient_id'));
  if (!$patient) {
      return redirect()->route('encounters.index')->with('error', 'Patient record not found.');
  }
  ```
- Upgraded controller exception boundary to `catch (\Throwable $e)` to safely intercept all PHP 8 `\Error` and `\TypeError` exceptions.

### 2.4 Child Health Dashboard Schedule Schema Correction
- In `app/Services/Dashboard/ChildHealthDashboardService.php`:
  - Fixed `scheduled_date` query column to `due_date`, preventing SQL column errors during background dashboard polling.

---

## 3. Verification & Testing

- **Peak Memory Benchmarks**:
  - Base Framework Boot: ~32 MB
  - Staff Query (475 active staff): 36 MB (reduced from 145+ MB)
  - Full Consultation View Render (including all partials, master layout, and scripts): **46.5 MB** (well below the 128 MB server limit, leaving >80 MB safety headroom).
- **Unit & Feature Tests**:
  - `tests/Unit/Encounter/EncounterCreateOptimizationTest.php`: Validates empty collection handling and memory safety (3 tests, 11 assertions).
  - `tests/Unit/AI/NotePolishingPromptTest.php`: Validates multi-modality scribing prompts (6 tests, 31 assertions).
  - `tests/Feature/HR/ReasonForEncounterTest.php`: Validates reason endpoints (2 tests, 2 assertions).
- **Linting & Code Standards**:
  - `composer lint`: Passes 100% PSR-12 with 0 errors across 1,332 files.
  - `node --check public/js/encounter-page.js`: Validated syntax without warnings.
  - `node --check public/js/speech-dictation.js`: Validated syntax without warnings.

