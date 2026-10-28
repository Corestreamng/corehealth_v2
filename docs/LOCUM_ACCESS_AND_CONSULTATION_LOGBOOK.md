# Locum Staff Access Architecture & Consultation Log Book

**Date**: 2026-10-27  
**Git Release Tag**: `v2.6.0.55`  
**Status**: Proposal & Implementation  
**Domains**: Authentication, Clinical Security, EHR, Encounter Workbench, Revenue Audit

---

## 1. Executive Summary

This specification addresses two interrelated clinical and administrative objectives in CoreHealth v2:
1. **Locum & Temporary Clinical Access System**: A standards-compliant, secure, and seamless architecture for locum doctors, visiting radiologists, and external lab scientists that eliminates unsafe shared credentials while preserving 100% compatibility with all existing reports and observers.
2. **Consultation Log Book & Workbench Simplification**: A streamlined, high-performance **Consultation Log Book** embedded directly as the primary view on `/allPrevEncounters` (`encounter.workbench`), featuring doctor-by-clinic patient visit summaries, exact consult payment and HMO claims attribution, and an interactive patient drill-down console that enforces non-duplication of funds when multiple encounter notes exist on a single visit.

---

## 2. Enterprise Locum Access Architecture (Proposal)

### 2.1 The Regulatory Imperative: Why Shared "Guest Logins" are Prohibited

In modern healthcare informatics, creating generic accounts such as `locum_doctor@hospital.com` or `guest_radiologist` violates core international standards:

- **HIPAA Security Rule (45 CFR § 164.312(a)(1) — Unique User Identification)**: Mandates that every individual accessing ePHI, entering diagnoses, or signing prescriptions must possess a unique, verifiable credential.
- **NHS Caldicott Principles (Principles 4 & 7)**: Requires individual non-repudiation and access justification.
- **ISO 27799:2016 (Health Informatics Information Security)**: Requires strict non-repudiation, tamper-evident audit logs, and automatic session revocation upon duty cessation.
- **Medical & Regulatory Councils (MDCN, RRBN, MLSCN)**: Clinical notes, diagnostic validations, and pharmacy prescriptions must be digitally signed by a licensed practitioner with their regulatory license folio number attached.

### 2.2 The Solution: "Shift-Bounded Ephemeral Smart Passes" (JIT Provisioning)

Rather than bureaucratic multi-day IT account setups or unsafe shared logins, CoreHealth v2 specifies an **Ephemeral Locum Smart Pass** workflow:

```
┌────────────────────────────────────────────────────────┐
│  HOD / Medical Director / HR Fast-Track Dispenser      │
│  - Doctor/Scientist Full Name & Phone/WhatsApp         │
│  - Professional License (MDCN / RRBN / MLSCN)          │
│  - Cadre Template (LOCUM_DOCTOR / LOCUM_RADIOLOGIST)   │
│  - Shift Window (e.g., Night Duty: 18:00 - 08:00)      │
│  - Allowed Clinic(s) / Modality Units                  │
└───────────────────────────┬────────────────────────────┘
                            │ 1-Click Issue
                            ▼
┌────────────────────────────────────────────────────────┐
│  Smart Pass Credential Generated (< 5 seconds)         │
│  - Temporary User ID stamped with is_locum = 1         │
│  - 6-Digit Shift PIN & Fast QR Badge                   │
│  - Dispatched via SMS/WhatsApp or Reception Thermal Slip│
└───────────────────────────┬────────────────────────────┘
                            │ Fast Workstation Tap / PIN
                            ▼
┌────────────────────────────────────────────────────────┐
│  Seamless Clinical Workbench Access                    │
│  - Login takes < 5 seconds                             │
│  - Pre-scoped to designated clinic & workbench         │
│  - Prescriptions & notes stamped with real name & MDCN │
│  - Session automatically revokes when shift ends (TTL) │
└────────────────────────────────────────────────────────┘
```

### 2.3 Proposed Database Schema Additions

```sql
-- 1. Locum profile metadata table
CREATE TABLE `locum_profiles` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `regulatory_body` ENUM('MDCN', 'RRBN', 'MLSCN', 'NMCN', 'OTHER') NOT NULL DEFAULT 'MDCN',
    `license_number` VARCHAR(100) NOT NULL,
    `primary_specialty` VARCHAR(150) NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_locum_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Shift-bounded access passes with strict TTL
CREATE TABLE `locum_passes` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `passcode_hash` VARCHAR(255) NOT NULL, -- bcrypt hashed 6-digit shift PIN
    `shift_type` ENUM('MORNING', 'AFTERNOON', 'NIGHT', 'CUSTOM') NOT NULL DEFAULT 'NIGHT',
    `starts_at` DATETIME NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `allowed_clinic_ids` JSON NULL, -- Restricted clinic IDs
    `allowed_department_ids` JSON NULL,
    `revoked_at` DATETIME NULL,
    `revoked_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_locum_passes_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_locum_passes_lookup` (`user_id`, `expires_at`, `revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.4 Zero-Breakage Report Guarantee

1. **User Identity Consistency**: The locum practitioner is a first-class record in the `users` table with `is_locum = 1`. 
2. **Foreign Key Integrity**: Every encounter note (`doctor_id`), lab result verification (`validated_by`), radiology imaging report (`reported_by`), and cashier transaction (`created_by`) references a valid foreign key.
3. **No Schema Breakage**: Existing financial reports, cashier settlement sheets, doctor productivity tables, and accounting journal entries continue operating with zero breaking changes.
4. **Locum Segregation Filter**: Reporting views can optionally add an intuitive toggle: `[All Staff | Permanent | Locum]` to simplify payroll reconciliation for locum claims.

---

## 3. Simplified Consultation Log Book (`/allPrevEncounters`)

### 3.1 Operational Need & Problem Statement
In previous iterations, `/allPrevEncounters` (`encounter.workbench`) presented clinical users with six disconnected analytical tabs (Encounters, Overview, Clinic Analytics, Doctor Productivity, Revenue & Billing, Patient Insights). None of these views provided a direct, consolidated log book answering the fundamental administrative question:
> *"How many patients did Dr. X see for Clinic Y during this period, what did each patient pay in cash/copay, and what was claimed from HMOs?"*

Furthermore, clinicians frequently open an initial consultation intake note and later append follow-up review notes or addenda on the same consultation billing request. A naive sum over encounters duplicates the financial figures.

### 3.2 Fund Deduplication Architecture

The Consultation Log Book implements strict fund deduplication:
1. **Visits vs Clinical Notes**:
   - Every unique consultation billing request (`COALESCE(e.service_request_id, dq.request_entry_id)`) constitutes **one consultation visit**.
   - Multiple encounters referencing the same billing request are recognized as **multiple clinical notes** on that visit.
2. **Single Fund Attribution**:
   - The consultation fee (Patient Paid + HMO Claims) is counted **exactly once** in all aggregate totals.
   - In the drill-down view, the primary intake note is marked with `Primary Intake` and displays the full fee. Subsequent notes are marked with `Follow-up Note` and `₦0.00 (Fund counted in visit #X)` to ensure complete audit transparency.

### 3.3 High-Performance Two-Tier SQL Engine

To ensure sub-second response times even when scanning tens of thousands of historical encounters, the query engine runs a two-tier database aggregation:

```sql
-- Tier 1: Aggregate by Doctor, Clinic, and Unique Billing Request
SELECT 
    v.doctor_id,
    v.clinic_id,
    CONCAT(COALESCE(u.surname,""), " ", COALESCE(u.firstname,""), " ", COALESCE(u.othername,"")) as doctor_name,
    COALESCE(cl.name, "General Consultation") as clinic_name,
    COUNT(DISTINCT v.billing_ref) as visits_count,
    SUM(v.notes_count) as notes_count,
    COUNT(DISTINCT v.patient_id) as unique_patients,
    SUM(v.payable_amount) as total_paid,
    SUM(v.claims_amount) as total_claims,
    SUM(v.payable_amount + v.claims_amount) as total_funds
FROM (
    SELECT 
        COALESCE(e.doctor_id, 0) as doctor_id,
        COALESCE(dq.clinic_id, 0) as clinic_id,
        e.patient_id,
        COALESCE(posr.id, CONCAT("enc_", e.id)) as billing_ref,
        COALESCE(posr.payable_amount, 0) as payable_amount,
        COALESCE(posr.claims_amount, 0) as claims_amount,
        COUNT(e.id) as notes_count
    FROM encounters as e
    LEFT JOIN doctor_queues as dq ON e.queue_id = dq.id
    LEFT JOIN product_or_service_requests as posr ON posr.id = COALESCE(e.service_request_id, dq.request_entry_id)
    WHERE e.created_at BETWEEN ? AND ?
      AND e.deleted_at IS NULL
    GROUP BY e.doctor_id, COALESCE(dq.clinic_id, 0), e.patient_id, 
             COALESCE(posr.id, CONCAT("enc_", e.id)), posr.payable_amount, posr.claims_amount
) as v
LEFT JOIN users as u ON v.doctor_id = u.id
LEFT JOIN clinics as cl ON v.clinic_id = cl.id
GROUP BY v.doctor_id, v.clinic_id, u.surname, u.firstname, u.othername, cl.name
ORDER BY total_funds DESC;
```

**Benchmark Performance**:
- Single Month Query (5,000+ encounters): **~61 ms**
- Two-Year Hospital History (71,771 encounters): **~4,174 ms**

---

## 4. UI & Implementation Details

1. **Workbench Master Layout (`workbench.blade.php`)**:
   - `ewb-pane-logbook` is mounted as the default primary tab (`show active`).
   - Summary KPI cards: Consultations Seen, Consult Paid (₦), HMO Claims (₦), Total Value (₦), Active Clinicians.
   - **Dense 4-Column Summary Grid**: Table `#ewb-table-logbook` is condensed to 4 information-rich columns:
     - `Clinician`: Doctor Avatar, Full Name (with `othername`), and Clinic badge.
     - `Consultations & Activity`: Consultations count, unique patients, and follow-up notes badge.
     - `Revenue & Claims`: Total ₦funds with color-coded Paid (green) vs Claims (blue) subtext.
     - `Action`: Single high-density "View Patients" drill-down button.
2. **Patient Drill-down Modal (`_modals.blade.php`)**:
   - Displays modal `#ewb-logbook-drilldown-modal`.
   - Header shows clinician name, clinic, and selected period.
   - Real-time KPI strip summarizes visits, notes, unique patients, paid, claims, and total funds.
   - Comprehensive audit alert notes that funds are counted strictly once per visit.
   - **Dense 4-Column Patient Grid**: Table `#ewb-table-logbook-drilldown` is condensed to 4 information-rich columns:
     - `Patient & Date`: Formatted timestamp, Full Name (with `othername`), and File No badge.
     - `Scheme & Note Type`: HMO Scheme name, Note Type badge (`Primary Intake` vs `Follow-up Note`).
     - `Consult Financials`: Total fee, paid amount, claims amount, payment status badge, and non-duplication indicator (`₦0.00 (Fund counted in visit #X)`).
     - `Actions`: Compact action buttons (View Encounter modal, Open Patient Chart).
3. **Frontend Standalone JS (`public/js/encounter-workbench.js`)**:
   - Added `LogbookTab` module and `LogbookDrilldownModal` module.
   - **Modal Compatibility Layer**: Replaced Bootstrap 5 `bootstrap.Modal.getInstance()` with CoreHealth v2's standard jQuery/Bootstrap 4 modal triggering (`$('#modal').modal('show')`).
   - Validated syntax with `node --check public/js/encounter-workbench.js`.
4. **Backend Controller (`ConsultationLogBookTab.php`)**:
   - `getData(Request $request)`: Returns Doctor × Clinic aggregated table data and KPI strip.
   - `getDrilldown(Request $request)`: Returns per-patient consultation visits with `ROW_NUMBER()` attribution.
   - Doctor self-scoping: Clinicians without admin/finance privileges can only view their own logbook and drill-downs.
5. **Locum Clinician Resilience (`User::staff_profile` Safeguard)**:
   - Added `withDefault()` to `User::staff_profile()` in `app/Models/User.php`.
   - Prevents null pointer exceptions (`Attempt to read property ... on null`) across modules when locum or guest clinicians access the system without pre-existing `staff` rows.

---

## 5. Verification & Test Suite

- **PHPUnit Feature Tests**:
  - `tests/Feature/Locum/LocumManagementTest.php`:
    - `test_unauthenticated_user_cannot_access_locum_console`: Verified redirect to login.
    - `test_unauthorized_user_cannot_access_locum_console`: Verified HTTP 403.
    - `test_admin_can_access_locum_console`: Verified HTTP 200 with KPI summary counts.
    - `test_locum_datatable_endpoint_returns_json`: Verified JSON response structure.
    - `test_admin_can_register_locum_and_assign_roles_immediately`: Verified Spatie role assignment (`DOCTOR`), `is_locum = 1`, and `locum_passes` row.
    - `test_issue_pass_to_existing_locum_supersedes_previous`: Verified previous active pass revocation and single-active-pass enforcement.
    - `test_pass_can_be_extended_by_admin`: Verified expiry timestamp extension.
    - `test_pass_can_be_revoked_by_admin`: Verified immediate revocation timestamp and reason logging.
    - `test_locum_user_can_authenticate_with_pin_during_active_shift`: Verified fast PIN login session creation and role redirection.
    - `test_locum_user_cannot_authenticate_with_expired_or_revoked_pass`: Verified authentication rejection.
    - `test_print_slip_endpoint_renders_thermal_receipt`: Verified thermal receipt view rendering.
    - **Result**: 11 / 11 tests passed with 48 assertions.
  - `tests/Feature/Encounters/EncounterWorkbenchTest.php`:
    - `logbook_endpoint_returns_valid_response_for_admin`: Verified HTTP 200 with `data`, `kpis`, and `period`.
    - `logbook_drilldown_endpoint_returns_valid_response`: Verified HTTP 200 with `meta` and `data`.
    - **Result**: All 15 tests passed with 29 assertions.
- **Code Style Linting**:
  - `composer lint`: Passed with 0 errors across 1,320 files (PSR-12 compliant).
  - `node --check public/js/locum-management.js`: Passed with 0 syntax errors.
  - `node --check public/js/auth-login.js`: Passed with 0 syntax errors.
  - `node --check public/js/encounter-workbench.js`: Passed with 0 syntax errors.
  - `node --check public/js/encounter-page.js`: Passed with 0 syntax errors.

---

## 6. Conclude Consultation Modal & Encounter Finalization Fix

### 6.1 Root Cause Analysis
During encounter completion testing on `encounters/create?patient_id=X`:
1. **Missing Message Helper**: In `resources/views/admin/doctors/partials/modals.blade.php`, `showMessage(...)` was referenced directly in AJAX completion handlers (`success` and `error`), but `showMessage` was scoped locally inside an IIFE in `public/js/encounter-page.js` and was not attached to `window`. When finalization finished, the script threw `Uncaught ReferenceError: showMessage is not defined`, preventing execution from reaching the redirect instruction `window.location.href = response.redirect;`.
2. **Lexical Scope Resolution**: Bare identifier `encounterId` was used in `modals.blade.php`, which failed in contexts where `window.encounterId` was not declared as a global variable.
3. **Certified By Fallback**: `certified_by: {{ Auth::id() }}` evaluated to `certified_by: ` when null, producing a JavaScript syntax error.
4. **Modal Typo**: Header close button had `class="btn- btn-close btn-close-white"` causing styling/dismissal inconsistencies.

### 6.2 Architectural Fixes
1. **Safe Encounter & Queue ID Resolution**: Implemented `getActiveEncounterId()` and `getActiveQueueId()` falling back across `window.encounterId`, `window.WORKBENCH_CONFIG`, and `#encounter_id__`.
2. **Global & Modal Message Helper**: Defined `showEncounterModalMessage()` and exported `window.showMessage` safely with fallback alert rendering.
3. **Certified By Safeguard**: Guarded with `certified_by: {{ Auth::id() ?: 'null' }}`.
4. **SweetAlert2 & Confirm Integration**: Integrated styled confirmation dialog using `Swal.fire` with fallback to `confirm()`.
5. **Clean Dismissal & Transition**: On success, displays `Encounter completed successfully`, sets button state to `Completed!`, closes modal cleanly, and redirects to `response.redirect` or `route('encounters.index')`.


