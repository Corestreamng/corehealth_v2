# WeCare247 & CoreHealth v2 Longitudinal EHR Clinical Story Synchronization

**Date**: October 12, 2026  
**Git Release Tag**: `v2.6.0.38`  
**Status**: Production Ready  

---

## 1. Overview & Purpose

This document details the bidirectional synchronization architecture between **CoreHealth v2** (hospital EHR/HMIS) and **WeCare247** (telehealth & remote care platform) for patient longitudinal medical records.

The clinical story subsystem aggregates a patient's complete hospital history—spanning doctor consultation encounters, ICD-10 diagnoses, vital sign observations, nurse monitoring notes, laboratory investigations, radiology scans, pharmacy prescriptions, surgical procedures, ward admissions, referrals, and medication administrations—into a unified, chronologically ordered EHR timeline.

Furthermore, this clinical story is presented seamlessly across every clinical touchpoint in WeCare247, allowing consulting telemedicine physicians to review, quote, and incorporate physical hospital records directly into digital consultation notes.

---

## 2. Zero-Environment-Variable Facility Credential Architecture

To ensure multi-tenant security and isolation across facilities, **facility credentials strictly reside in MongoDB documents within the `facilities` collection, with ZERO reliance on environment variables**.

### A. Integrated Facility Database Configuration
A healthcare facility configured for CoreHealth EHR integration is stored in MongoDB with:
- **Facility ID**: `684852a46c77f6aa140b6eb3` (matching `application_status.wecare_facility_id` in CoreHealth)
- **Facility Name**: `Healthcare Facility (Integrated Instance)`
- **Owner / Administrator ID**: `68813a515922dc2b25e92f47` (`facility_admin`)
- **Embedded Integration Document (`facilities.corehealth_integration`)**:
  ```json
  {
    "corehealth_url": "http://127.0.0.1:8000",
    "client_id": "123",
    "client_secret": "123",
    "facility_code": 1,
    "sync_enabled": true,
    "sync_demographic": true,
    "sync_encounters": true,
    "sync_prescriptions": true,
    "sync_lab_results": true,
    "updated_at": "2026-10-11T20:42:48.722Z"
  }
  ```

### B. Dynamic Client Construction in WeCare247
The client factory function `clientFromFacility(facility)` in `wecare247/src/lib/corehealth-client.ts` enforces this invariant:
```typescript
export function clientFromFacility(facility: any): CoreHealthClient {
  const integration = facility?.corehealth_integration;
  if (!integration) {
    throw new Error(
      `Facility "${facility?.name || facility?._id || "Unknown"}" does not have CoreHealth integration configured in its database record.`
    );
  }
  if (!integration.corehealth_url || !integration.client_id || !integration.client_secret) {
    throw new Error(
      `Facility "${facility?.name || facility?._id || "Unknown"}" is missing CoreHealth API credentials in its configuration.`
    );
  }
  return new CoreHealthClient({
    baseUrl: integration.corehealth_url,
    clientId: integration.client_id,
    clientSecret: integration.client_secret,
    facilityCode: integration.facility_code || 1,
  });
}
```
All environment variable fallbacks (`COREHEALTH_URL`, `COREHEALTH_CLIENT_ID`, etc.) were completely purged from `wecare247/.env.local` and runtime libraries.

---

## 3. Strict Contract Parity & Typed Enums

Both platforms enforce 1-to-1 parity via typed enums and contract interfaces:

### Enums

| Domain | PHP 8.3 Enum (`app/Enums/WeCare/`) | TypeScript Enum (`wecare247/src/contracts/corehealth-clinical.ts`) | Values |
|---|---|---|---|
| **Clinical Category** | `ClinicalCategory: string` | `ClinicalCategory` (enum) | `all`, `encounters`, `vitals`, `nursing_notes`, `labs`, `imaging`, `prescriptions`, `procedures`, `admissions`, `referrals`, `med_admin` |
| **Timeline Event Type** | `TimelineType: string` | `TimelineType` (enum) | `encounter`, `vital`, `nursing_note`, `lab`, `imaging`, `prescription`, `procedure`, `admission`, `referral`, `med_admin` |
| **Lab Status** | `LabStatus: int` | `LabStatus` (numeric enum) | `0` (Dismissed), `1` (Unbilled), `2` (Billed Pending), `3` (Sample Taken), `4` (Completed), `5` (Pending Approval), `6` (Rejected) |
| **Imaging Status** | `ImagingStatus: int` | `ImagingStatus` (numeric enum) | `0` (Dismissed), `1` (Unbilled), `2` (Billed Pending), `3` (In Progress), `4` (Completed), `5` (Pending Approval), `6` (Rejected) |
| **Prescription Status**| `PrescriptionStatus: int` | `PrescriptionStatus` (numeric enum) | `0` (Dismissed), `1` (Unbilled), `2` (Billed), `3` (Dispensed) |
| **Clinical Priority** | `ClinicalPriority: string` | `ClinicalPriority` (enum) | `routine`, `urgent`, `emergency` |

### Core Interfaces & DTOs
- **CoreHealth Backend**:
  - `app/Contracts/WeCare/ClinicalStoryInterface.php`
  - `app/Contracts/WeCare/TimelineItemInterface.php`
  - `app/Support/WeCare/ClinicalStoryDto.php`
- **WeCare247 Frontend**:
  - `wecare247/src/contracts/corehealth-clinical.ts` (`ClinicalStoryResponse`, `ClinicalEncounter`, `ClinicalVital`, `ClinicalNurseNote`, `ClinicalLab`, `ClinicalImaging`, `ClinicalPrescription`, `ClinicalProcedure`, `ClinicalAdmission`, `ClinicalTimelineItem`)

---

## 4. API Endpoints Specification

### A. CoreHealth v2: `GET /api/corehealth/patient/clinical-story`
- **Security**: SHA256 Basic Auth (`auth.basic.sha256`) using facility client credentials.
- **Toggles**: Checks master toggle `wecare_enabled` in `application_status`. Rejects with HTTP 403 `WECARE_INTEGRATION_DISABLED` when disabled.
- **Query Parameters**:
  - `file_no`: Hospital patient MRN / folder number.
  - `patient_id`: CoreHealth internal patient ID.
  - `category`: Optional filter (`ClinicalCategory`).
- **Response Structure**:
  ```json
  {
    "status": true,
    "patient": {
      "id": 1,
      "file_no": "14-32-97",
      "fullname": "John Doe",
      "gender": "male",
      "blood_group": "O+",
      "corehealth_sync_uuid": "...",
      "already_linked": true,
      "wecare_patient_id": "68487b88f0a3b7520784ce3e"
    },
    "summary": {
      "encounters_count": 14,
      "vitals_count": 12,
      "nursing_notes_count": 6,
      "labs_count": 5,
      "imaging_count": 2,
      "prescriptions_count": 8,
      "procedures_count": 1,
      "admissions_count": 1,
      "total_records": 49
    },
    "encounters": [ ... ],
    "vitals": [ ... ],
    "nursing_notes": [ ... ],
    "labs": [ ... ],
    "imaging": [ ... ],
    "prescriptions": [ ... ],
    "procedures": [ ... ],
    "admissions": [ ... ],
    "timeline": [ ... ]
  }
  ```

### B. WeCare247 Universal Route: `GET /api/clinical-story`
- **Security**: User JWT Bearer Token (`Cookies.get("token")` / `Authorization: Bearer <jwt>`).
- **Resolution Strategy**:
  1. Resolves patient identity from query `patient_id`, appointment context `appointment_id`, or session user.
  2. Resolves facility from appointment `facility_id`, `patient_affiliations`, patient profile `facility_id`, or fallback to active integrated facility.
  3. Loads facility document from MongoDB and queries CoreHealth using `clientFromFacility(facility)`.
  4. Automatically caches snapshot rollups into `patient_clinical_records`.

---

## 5. Universal Presentation Across Clinical Workspaces

The longitudinal EHR story and past doctor/nurse notes are embedded across all five key clinical interfaces in WeCare247:

1. **Doctor Documentation Workspace** (`src/app/doctor/documentation/[appointmentId]/page.tsx`):
   - Real-time patient MRN badge and longitudinal stats ribbon.
   - Dedicated "Hospital EHR Story" tab presenting chronological timeline events.
   - Sub-segmented Notes Tab with "Quote into Note" actions for past doctor encounters, nurse notes, and vitals into the TinyMCE rich editor.
2. **WebRTC Video Consultation Room** (`src/app/meeting/[roomId]/page.tsx`):
   - Integrated clinical drawer with tabs for Call Notes, Hospital Encounters, Nurse Observation Notes, and Vitals.
   - 1-click quote insertion buttons appending styled blockquotes directly into documentation.
   - Full-screen `ClinicalStoryModal` overlay.
3. **Patient Documentation & Notes** (`src/app/dashboard/documentation/[appointmentId]/`):
   - Interactive viewer for completed consultation notes and synchronized hospital records.
4. **Patient Medical Records Page** (`src/app/dashboard/medical-records/page.tsx`):
   - Dynamic 9-domain dashboard covering Encounters, Vitals, Nurse Notes, Labs, Imaging, Prescriptions, Procedures, Admissions, and Timeline.
   - Latest vitals telemetry card and trigger for `ClinicalStoryModal`.
5. **Appointment & Encounter History** (`src/app/dashboard/history/page.tsx`):
   - Unified encounter history combining teleconsultations with physical CoreHealth hospital visits.
   - Filter pills, expandable physician notes, and ICD-10 diagnostic badges.
6. **Facility Admin Patient Management** (`src/app/facility_admin/patients/page.tsx`):
   - Slide-over EHR drawer with full 11-tabbed `ClinicalStoryModal` view for any linked patient.

---

## 6. Testing, Linting & Verification

### A. CoreHealth PHPUnit Feature Test Suite
- Test Class: `tests/Feature/WeCare/WeCareClinicalStoryTest.php`
- Command:
  ```bash
  vendor/bin/phpunit tests/Feature/WeCare/
  ```
- Coverage:
  - `test_clinical_story_requires_authentication`: Confirms 401 on unauthorized requests.
  - `test_clinical_story_blocked_when_wecare_disabled`: Confirms 403 `WECARE_INTEGRATION_DISABLED`.
  - `test_clinical_story_requires_file_no_or_patient_id`: Confirms 422 on missing parameters.
  - `test_clinical_story_returns_404_for_unknown_patient`: Confirms 404 handling.
  - `test_clinical_story_returns_complete_payload_structure`: Asserts full 11-key payload schema.
  - `test_wecare_clinical_story_enums_and_constants`: Validates enum values and integrity.
- Status: **12 tests, 75 assertions, 100% Passing**.

### B. Code Style & Linting
- Executed via isolated CS Fixer:
  ```bash
  tools/vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php app/Http/Controllers/API/WeCareIntegrationController.php app/Enums/WeCare/ app/Contracts/WeCare/ app/Support/WeCare/ tests/Feature/WeCare/
  ```
- Status: **PSR-12 Compliant, 0 linting errors**.
