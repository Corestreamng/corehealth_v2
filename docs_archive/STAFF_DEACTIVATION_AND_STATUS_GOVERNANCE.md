# Staff Deactivation, Employment Status Governance & Locum Integration

**Date**: 2026-11-02  
**Git Release Tag**: `v2.6.0.61`  
**Domain**: Human Resources, Authentication, Clinical Queue Routing & Locum Governance

---

## 1. Executive Summary

In enterprise hospital management, staff employment status transitions directly dictate system access, patient queue visibility, and medico-legal liability. Prior to this enhancement:
1. `Staff.employment_status` and `User.status` were decoupled, permitting suspended or terminated practitioners to retain active sessions or authenticate into the longitudinal EHR.
2. The staff directory (`/staff`) filtered out all practitioners with `where('status', '>', 0)`, rendering deactivated or suspended staff invisible and preventing HR or administrators from reviewing or updating their records.
3. Locum practitioners with revoked or expired shift passes retained active sessions until arbitrary PHP session timeout.
4. When access was blocked, staff received generic validation messages with zero context regarding their suspension reason, duration, or contact procedures.

This release establishes an end-to-end governance architecture ensuring immediate session revocation, rich contextual feedback for blocked staff, dynamic locum shift pass TTL enforcement, and instant status transitions via a modal quick-action in the staff directory.

---

## 2. Employment Status Behavioral Matrix

The `staff.employment_status` column was expanded via migration `2026_11_02_070000_expand_staff_employment_status_enum.php` to support seven standardized states:

| Employment Status | Staff `status` | User `status` | System Login | Active Sessions | Clinical Rosters / Doctor Queues | Locum Shift Pass Impact |
|:---|:---:|:---:|:---:|:---:|:---:|:---|
| **`active`** | `1` | `1` | Permitted | Allowed | Included | Valid within pass validity window |
| **`probation`** | `1` | `1` | Permitted | Allowed | Included | Valid within pass validity window |
| **`on_leave`** | `1` | `1` | Permitted | Allowed | **Excluded** | Revoked / Ineligible |
| **`suspended`** | `0` | `0` | **Blocked** | **Immediately Revoked** | **Excluded** | **All active passes revoked** |
| **`resigned`** | `0` | `0` | **Blocked** | **Immediately Revoked** | **Excluded** | **All active passes revoked** |
| **`terminated`** | `0` | `0` | **Blocked** | **Immediately Revoked** | **Excluded** | **All active passes revoked** |
| **`retired`** | `0` | `0` | **Blocked** | **Immediately Revoked** | **Excluded** | **All active passes revoked** |

---

## 3. Bidirectional User and Staff Lifecycle Synchronization

In `app/Models/Staff.php` and `app/Models/User.php`, Eloquent lifecycle hooks (`booted()`) guarantee status consistency:
- When a `Staff` record's `employment_status` is updated to a non-working state (`suspended`, `terminated`, `resigned`, `retired`), `staff.status` is set to `0` and `user.status` is set to `0`.
- Conversely, transitioning a staff member back to `active` or `probation` automatically restores `staff.status = 1` and `user.status = 1`.
- A dedicated synchronization helper `syncUserAndStaffStatus()` prevents recursion while ensuring both records persist identical access states.

---

## 4. Authentication Blocking & Rich Context Delivery

### 4.1 Login Guard (`LoginController::authenticated`)
Upon successful credential entry, the application executes post-authentication governance:
- If `user.status == 0` or `!$staff->canLogin()`, authentication is rejected, the session is invalidated, and a context-rich error is dispatched to the login view.
- For suspended staff, the message automatically compiles the recorded suspension reason and scheduled reinstatement date:
  > *"Your staff account has been suspended (Reason: Disciplinary review pending inquiry | Expected Reinstatement: Nov 16, 2026). System access is currently disabled. Please contact Human Resources or Hospital Administration for guidance."*
- For terminated, resigned, or retired staff, the specific exit status is clearly communicated alongside HR clearance instructions.

### 4.2 In-Flight Session Termination Middleware (`CheckSuspension`)
Registered in `app/Http/Kernel.php` on the `web` middleware group:
- Evaluates every authenticated HTTP request.
- Detects if an active user's status was changed to `0` or if their staff profile was suspended, terminated, or retired during their session.
- Immediately flushes session data, issues `Auth::logout()`, and redirects with a tailored security notice.

### 4.3 Visual Feedback on Login Screen (`resources/views/auth/login.blade.php`)
- Integrated a prominent alert banner positioned directly above the login tabs.
- Utilizes Material Design Icons (`mdi-account-off`, `mdi-account-remove`, `mdi-account-lock`) with distinct styling to ensure staff immediately understand why their login was restricted.

---

## 5. Locum Practitioner Integration & Governance

Locum personnel operate under shift-bounded credentials managed by `LocumManagementService`:
1. **Pass Verification**: In `CheckSuspension` and `LoginController`, locums (`$user->isLocum()`) are required to hold an active, non-expired, and non-revoked `LocumPass`.
2. **Shift Expiry Eviction**: When a locum shift pass expires, the middleware terminates their session and directs them to request a shift extension from the HOD.
3. **Automatic Pass Revocation**: When a locum is suspended, terminated, or deactivated via `StaffController::updateEmploymentStatus()`, all active `LocumPass` records are instantly stamped as revoked (`revocation_reason: Staff deactivated/suspended by administrator`).
4. **PIN Rejection**: In `LocumManagementService::authenticatePass()`, PIN authentication checks user and staff active status before evaluating PIN validity.
5. **Clinical Intake Guard**: `ReceptionWorkbenchController::getDoctorsByClinic` and `DoctorAppointmentController::getDoctorsForClinic` require locum doctors to hold an active pass valid for the targeted clinic.

---

## 6. Staff Directory Quick Action & Modular Architecture

### 6.1 DataTable Expansion
- `StaffController::listStaff()`: Removed hardcoded `where('status', '>', 0)` restriction. Deactivated and suspended staff are now fully visible and filterable.
- Added multi-criteria filter controls: Department, Category, Employment Type (Full Time, Part Time, Contract, Intern, Locum), and Employment Status (Active, Probation, On Leave, Suspended, Resigned, Terminated, Retired).
- Status column renders distinct badges for active, probation, on leave, suspended, resigned, terminated, and retired practitioners, including locum shift pass validity tags.

### 6.2 Quick Status Modal & Endpoint
- Quick Status button (`.btn-quick-status` with `mdi-account-settings` icon) opens `#quickStatusModal`.
- Endpoint: `POST /staff/{id}/update-status` (`staff.updateStatus`).
- Wrapped in database transactions; automatically records audit trails in `staff_suspensions` or `staff_terminations` and revokes locum passes when applicable.

### 6.3 Modular Frontend Assets
Adhering to CoreHealth Development Standards (Rule 1: no inline scripts or styles exceeding 10 lines in Blade):
- Stylesheet: `public/css/staff-directory.css`
- JavaScript Module: `public/js/staff-directory.js` (verified with `node --check`)
- Modal Partial: `resources/views/admin/staff/partials/_quick_status_modal.blade.php` (utilizing dual `data-bs-*` and standard Bootstrap markup)

### 6.4 Staff Edit Screen Integration (`resources/views/admin/staff/edit.blade.php`)
- **Prominent Left Column Status Card**: Relocated the employment status control from an obscure row inside Tab 2 to a primary card directly below the profile image in the left column (`col-lg-3`), visible across all tabs.
- **Full Status Coverage**: Includes all 7 statuses (`active`, `probation`, `on_leave`, `suspended`, `resigned`, `terminated`, `retired`) with dynamic badge coloration.
- **Conditional Suspension Details**: Smoothly exposes suspension end date and suspension reason inputs when suspended status is selected.
- **Locum Practitioner Support**: Added `locum` ("Locum Practitioner") to `employment_type` dropdown.
- **Controller Persistence**: `StaffController::update()` persists suspension details and revokes locum passes if a locum staff is suspended or deactivated.

---

## 7. Verification & Testing

### 7.1 PHPUnit Test Suites
- `tests/Feature/Staff/StaffDeactivationTest.php`:
  1. `test_updating_employment_status_to_suspended_deactivates_user_and_revokes_login`
  2. `test_check_suspension_middleware_terminates_active_session_for_suspended_staff`
  3. `test_terminated_resigned_or_retired_staff_cannot_login`
  4. `test_reinstated_staff_can_login`
  5. `test_deactivating_locum_practitioner_revokes_active_shift_passes_and_blocks_pass_login`
  6. `test_check_suspension_middleware_terminates_session_when_locum_pass_expires`
  7. `test_staff_directory_list_allows_filtering_by_deactivated_and_locum_status`
  8. `test_reception_get_doctors_by_clinic_excludes_inactive_or_suspended_doctors`
  9. `test_hr_disciplinary_suspension_and_lift_flow_synchronizes_status`
  10. `test_hr_disciplinary_termination_flow_deactivates_account`
- **Staff Domain Suite**: 10 tests, 74 assertions, 100% pass (`vendor/bin/phpunit tests/Feature/Staff/StaffDeactivationTest.php`).
- **Locum & Staff Suites**: 16 tests, 80 assertions, 100% pass (`vendor/bin/phpunit tests/Feature/Staff/ tests/Feature/Locum/`).

### 7.2 End-to-End Browser Subagent Verification
- Performed live browser testing using `browser_subagent` against `http://127.0.0.1:8001/login`:
  - Selected staff account `bola.adekunle@hospital.com`, reset password, and applied suspension.
  - Attempted login; confirmed user remained on `/login` without accessing dashboard.
  - Verified prominent **"Account Suspended"** alert banner displayed full context including reason (*"Disciplinary review pending inquiry"*), reinstatement date (*Nov 16, 2026*), and HR contact instructions.
  - Recording saved to `staff_suspension_test_1793601733637.webp`.
  - Reinstated test account to active status upon completion.
