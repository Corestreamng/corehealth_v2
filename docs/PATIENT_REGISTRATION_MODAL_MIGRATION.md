# Patient Registration Modal Migration & Route Deprecation

**Date**: 2026-10-16  
**Git Release Tag**: `v2.6.0.52`

---

## 1. Executive Summary & Objective

In accordance with CoreHealth v2's modern modular architectural standards, the legacy monolithic patient creation route (`route('patient.create')` / `/patient/create`) has been fully deprecated and all references to it have been removed.

Patient creation is now standardized uniformly across the entire system through the Reception Workbench Patient Form Modal (`#patientFormModal`), defined in `resources/views/admin/partials/patient-form-modal.blade.php` and powered by `public/js/patient-form-modal.js`.

---

## 2. Identified Ingress Points & Modifications

| Location | Original Action | Modern Architecture Implementation |
| :--- | :--- | :--- |
| `resources/views/admin/patients/index.blade.php` | `<a href="{{ route('patient.create') }}">` | Replaced with `<button type="button" class="btn btn-primary btn-sm" onclick="showPatientFormModal('create')">`. Included `@include('admin.partials.patient-form-modal')` and configured `window.patientFormConfig.onSuccess` to reload the Yajra DataTable on registration. |
| `resources/views/admin/dashboard/cockpit/_col_pipeline.blade.php` | `<a href="{{ route('patient.create') }}">` | Updated to deep link to `route('reception.workbench', ['action' => 'new-patient'])` with seamless inline modal trigger `onclick="if(typeof showPatientFormModal==='function'){event.preventDefault();showPatientFormModal('create');}"`. |
| `resources/views/admin/dashboards/accounts.blade.php` | Accidental link `<a href="{{ route('patient.create') }}">` on "View Services" | Corrected to `<a href="{{ route('services.index') }}">`. |
| `resources/views/admin/dashboards/receptionist.blade.php` | `<a href="{{ route('patient.create') }}">` | Updated to `<a href="{{ route('reception.workbench', ['action' => 'new-patient']) }}">`. |
| `resources/views/admin/dashboards/tabs/child-health-tab.blade.php` | `@if(Route::has('patient.create'))` & `route('patient.create', ['type' => 'child'])` | Updated to `@if(Route::has('reception.workbench'))` & `route('reception.workbench', ['action' => 'new-patient', 'type' => 'child'])`. |
| `resources/views/admin/dashboards/tabs/receptionist-tab.blade.php` | `@if(Route::has('patient.create'))` & `route('patient.create')` | Updated to `@if(Route::has('reception.workbench'))` & `route('reception.workbench', ['action' => 'new-patient'])`. |
| `app/Http/Controllers/DashboardDataController.php` | Shortcut url `route('patient.create')` | Updated to `route('reception.workbench', ['action' => 'new-patient'])`. |
| `app/Console/Commands/ScanRoutesCommand.php` | Omnibar metadata for `patient.create` | Updated to route name `reception.workbench.new-patient` and URL `/reception/workbench?action=new-patient`. |
| `routes/web.php` | `Route::resource('patient', PatientController::class);` | Replaced with `Route::resource('patient', PatientController::class)->except(['create']);` and added a graceful GET `/patient/create` redirect route pointing to `route('reception.workbench', ['action' => 'new-patient'])`. |
| `app/Http/Controllers/PatientController.php` | `create()` rendered `admin.receptionist.new_patient` | Method deprecated and redirected directly to `route('reception.workbench', ['action' => 'new-patient'])`. |

---

---

## 3. Preservation of Data Ingestion & Observers

- `patient.store` (POST `/patient` and `/reception/patient/quick-register`): Fully preserved and operational. The patient form modal continues to post directly to the registration endpoints, triggering all downstream observers (Accounting JEs, Medical Record creation, and CoreHMS SuperAdmin event synchronization).
- Role and Permission Guards (`@can('patient-create')`): Strictly maintained across all views.
- Origin Tracking: All patient registrations automatically record `registration_workbench` and `registered_by` (staff ID).

---

## 4. Standardized Workbench File Number Prefixes

To provide distinct auditing, departmental tracking, and file identification across hospital services, CoreHealth v2 enforces an authoritative prefix matrix per originating workbench:

| Workbench / Clinical Domain | Active Fixed Prefix | Legacy / Aliased Prefixes | Clinical Scope & Behavior |
| :--- | :--- | :--- | :--- |
| **Medical Laboratory** | `LAB-` | `LAB%` | Direct walk-in lab investigations and external sample intake. |
| **Pharmacy** | `PHARM-` | `PHM%`, `PHARM%` | Pharmacy walk-in counter sales, OTC medications, and direct dispensing. |
| **Maternity** | `ANC-` | `PANC%`, `ANC%` | Antenatal Care (`ANC-`) and Postnatal Care (`PANC-`) maternity enrollments. |
| **Imaging / Radiology** | `RAD-` | `IMG%`, `RAD%` | Walk-in diagnostic scans, X-rays, Ultrasounds, and CT imaging. |
| **Nursing** | `NUR-` | `WRD%`, `NUR%` | Ward bedside registrations, immunizations, and direct injections. |
| **Emergency Intake** | `EMG-` | `EX-%`, `EX%`, `EMG%` | Triage, acute resuscitation, and unidentified emergency patient admissions. |
| **Billing / Cashier** | `BIL-` | `BIL%` | Walk-in direct payments, cashier deposits, and billing counter receipts. |
| **HMO / Insurance** | `HMO-` | `HMO%` | HMO desk registrations and insurance policy holder intakes. |
| **Surgery / Theatre** | `SURG-` | `TH-%`, `TH%`, `SURG%` | Operating theatre bookings and emergency surgical intakes. |
| **Morgue / Mortuary** | `MOR-` | `MORGUE%`, `MOR%` | Direct mortuary body intake and deceased registrations. |
| **Doctor Consultation** | `DOC-` | `DOC%` | Walk-in clinic intake and specialist room consultations. |
| **Reception / Cockpit** | *None (Hospital Default)* | Numeric / Sequential | Standard sequenced hospital numbers (e.g. `001`, `1042`, etc.). |

---

## 5. Input Appendage Prefix Architecture & Manual Mode Enforcement

Departmental prefixes are integrated directly into the file number input group as a native **Input Appendage** (`#pf-fileno-prefix-addon` inside `.pf-fileno-input-group`):

1. **Native Input Appendage (`.pf-fileno-prefix-addon`)**:
   - The prefix (e.g. `LAB-`, `PHARM-`, `ANC-`) is rendered as a stylized prepended input addon seamlessly attached to the left of `#pf-file-no`.
   - In **Auto Mode**, the addon and input share a green border and background (`#e8f5e9` / `#28a745`), with the input displaying only the sequence (e.g. `014`).
   - In **Manual Mode**, the addon and input seamlessly switch to an orange theme (`#fff3e6` / `#fd7e14`), with the input ready for the user to type the numeric sequence.
   - For workbenches without prefixes (e.g. Reception, Cockpit), the appendage is automatically hidden (`display: none`), and `#pf-file-no` renders as a standard rounded input.

2. **Automatic Prefix Sanitization & Suffix Isolation**:
   - The user only interacts with the suffix/sequence inside `#pf-file-no`.
   - If the user pastes or types a prefixed string (e.g. `LAB-9876`), the input handler automatically strips the redundant prefix from the input box since the addon already represents it.
   - Recent file number chips automatically strip the prefix when clicked to copy into `#pf-file-no`.

3. **Unified Serialization & Backend Contract Parity**:
   - Frontend helper `getFullPatientFileNumber()` combines the prefix from the appendage with the user-entered suffix to construct the complete canonical file number (e.g. `LAB-9876`).
   - `submitPatientForm()` and emergency intake use `getFullPatientFileNumber()` to append `file_no` to `formData`.
   - Real-time duplicate checks evaluate `getFullPatientFileNumber()` against existing database records.
   - `PatientController::store()` and `Patient::getPrefixForWorkbench($workbench)` enforce prefix consistency server-side.

---

## 6. Smart Retroactive Migration Backfill

Migration `database/migrations/2026_10_16_101455_add_registration_workbench_to_patients_table.php` executes a multi-tiered backfill that respects existing non-empty records:

1. **Non-Empty Protection**:
   - Every backfill step filters with `WHERE (registration_workbench IS NULL OR registration_workbench = '')`. Any record with an existing non-empty workbench is preserved and untouched.
2. **Prefix-Based Pattern Resolution**:
   - Evaluates existing `file_no` values against active and historical prefixes (e.g., `ANC%` & `PANC%` $\rightarrow$ `maternity`, `PHARM%` & `PHM%` $\rightarrow$ `pharmacy`, `LAB%` $\rightarrow$ `lab`, `RAD%` & `IMG%` $\rightarrow$ `imaging`, `EMG%` & `EX%` $\rightarrow$ `emergency`).
3. **Clinical Enrollment Tables Join**:
   - Resolves un-prefixed records enrolled in `maternity_enrollments` $\rightarrow$ `'maternity'`.
   - Resolves un-prefixed records admitted in `morgue_admissions` $\rightarrow$ `'morgue'`.
4. **Creator Role Auditing**:
   - Inspects `audits` table (`event = 'created'`) joined to `model_has_roles` and `roles` to assign workbenches based on the registering staff's departmental role.
5. **Default Reception Fallback**:
   - Sets any remaining unassigned records to `'reception'`.

---

## 7. Verification & Testing

1. **PHPUnit Feature Tests**:
   - `tests/Feature/PatientRegistrationTest.php`:
     - `test_receptionist_can_view_patient_registration_form`: Asserts reception workbench loads properly.
     - `test_patient_can_be_registered_successfully`: Asserts patient model creation.
     - `test_patient_search_by_file_number_returns_results`: Asserts API search responsiveness.
     - `test_patient_create_route_is_deprecated_and_redirects_to_reception_workbench`: Asserts route deprecation.
     - `test_patient_index_page_loads_with_modal_and_no_patient_create_route`: Asserts `/patient` loads `#patientFormModal`.
     - `test_patient_registration_workbench_and_registered_by_attributes_and_accessors`: Asserts model attributes, accessors, and relationships.
     - `test_patient_edit_gate_and_role_permissions`: Asserts Spatie role and gate authorization rules.
     - `test_workbenches_provide_patient_registration_metadata`: Asserts metadata across reception, billing, imaging, and lab endpoints.
     - `test_standardized_workbench_prefixes_and_helpers`: Asserts `WORKBENCH_PREFIXES` and `resolveWorkbenchFromFileNo()`.
     - `test_get_next_file_number_with_workbench_prefix`: Asserts prefix scoping in `/reception/patient/next-file-number`.
     - `test_patient_store_enforces_workbench_prefix`: Asserts backend prefix enforcement when manual input omits prefix.
     - `test_smart_backfill_resolves_prefixes`: Asserts prefix backfilling and preservation of existing non-empty workbenches.
     - `test_isolated_auto_increments_for_normal_register_and_prefixed_walkins`: Asserts isolated auto-increments between normal patients and prefixed walk-ins (`EX-`, `LAB-`).
     - `test_quick_register_with_age_instead_of_dob`: Asserts quick walk-in registration using age and age unit.
     - `test_quick_register_with_age_group_instead_of_dob`: Asserts quick walk-in registration using clinical age group.
     - `test_patient_registration_with_referred_from`: Asserts storage and persistence of `referred_from`.
   - Result: **16/16 tests passing (90 assertions)**.
2. **Code Style & Linting**:
   - `composer lint` passed with **0 violations across 1,295 files** (strict PSR-12 standard).
3. **Frontend Verification**:
   - Verified via `node --check` across `public/js/patient-form-modal.js`, `public/js/reception-queue.js`, `public/js/imaging-results.js`, `public/js/reception-core.js`, `public/js/billing-core.js`, `public/js/pharmacy-dispensing.js`, `public/js/lab-core.js`, `public/js/imaging-core.js`, `public/js/nursing-core.js`, `public/js/maternity-core.js`, and `public/js/hmo-workbench.js`.

---

## 8. Isolated Auto-Increment Sequences for Walk-in Prefixes

To prevent walk-in registrations from consuming or polluting normal hospital file numbers:
- **Reception Walk-in Rebrand**: Reception "Quick Register" rebranded to "Register Walk-in Patient" using `EX-` prefix (`EX-0001`, `EX-0002`).
- **Domain Prefixes**: Lab walk-in uses `LAB-`, Pharmacy uses `PHARM-`, Nursing uses `NUR-`, etc.
- **Prefix Isolation Engine**:
  - `Patient::getAllRecognizedPrefixes()` provides the authoritative list of recognized prefix patterns.
  - Normal patient sequence queries explicitly exclude all recognized prefixes (`NOT LIKE 'LAB-%'`, `NOT LIKE 'EX-%'`, etc.), maintaining a pure contiguous sequence.
  - Prefixed queries (`where('file_no', 'like', $prefix . '%')`) increment only within their own prefix namespace.

---

## 9. Age and Age Group Toggle Enhancements

1. **Reception Walk-in Modal (`#quickRegisterModal`)**:
   - Equipped with a 3-pill toggle (`Age`, `Group`, `DOB`).
   - Defaults to `Age` mode for fast walk-in intake.
   - When entering Age or selecting an Age Group (e.g. Neonate, Infant, Toddler, Child, Adolescent, Young Adult, Adult, Older Adult, Elderly), the system automatically calculates a representative DOB and stores it in `#quick-register-dob` with a visual approximate year badge.
2. **Main Patient Modal (`#patientFormModal`)**:
   - Defaults to `DOB` mode with `.mode-dob` and `.active` on the `DOB` pill.
   - Includes a third pill for `Age Group` alongside `DOB` and `Age`.
   - Full bidirectional synchronization between DOB, Age numeric/unit, and Age Group dropdown.

---

## 10. Intuitive "Referred From" Referral Source Tracking

1. **Database Schema**:
   - Migration `database/migrations/2026_10_19_120000_add_referred_from_to_patients_table.php` adds `referred_from VARCHAR(255) NULL` to `patients`.
   - Added `referred_from` to `Patient::$fillable`.
2. **Interactive UI**:
   - Integrated into `#quickRegisterModal` and `#patientFormModal` (Step 1 and Step 4 Summary) as well as the full edit page (`admin.patients.edit`).
   - Autocomplete suggestions powered by `<datalist>`: `Self / Walk-in`, `Family / Friend`, `Doctor / Physician`, `Hospital / Clinic`, `Primary Health Centre (PHC)`, `Community Outreach`, `Company / Corporate Partner`, `HMO Partner`, `Church / Religious Org`, `School / Institution`.
   - Quick one-click chips (`Self`, `Family/Friend`, `Doctor`, `Hospital/Clinic`, `Outreach`, `Corporate`) allow rapid single-tap selection with immediate focus for additional custom physician or facility names.

