# NDPA 2023 & NDPR Consent Management Endpoints Reference

> **Standard**: Nigeria Data Protection Act (NDPA 2023) Sections 30, 34, 41–43 & NDPR  
> **Base Path**: `/api/ndpa/v1`  
> **Supported Formats**: JSON (`application/json`)  
> **Controller**: `App\Interoperability\Http\Controllers\PatientConsentController`  
> **Service**: `App\Interoperability\Services\NdprSanitizerService`  

---

## 1. Regulatory Context & Architecture Invariants

The Nigeria Data Protection Act 2023 (NDPA) establishes strict compliance requirements for health data processing:
- **Sensitive Personal Data (Section 30)**: Health records, biometric data, genetic information, and medical histories cannot be processed or exchanged without explicit affirmative consent or a recognized statutory lawful basis (`VITAL_INTEREST`, `LEGAL_OBLIGATION`, `PUBLIC_HEALTH`).
- **Right to Withdraw Consent (Section 34)**: Patients hold the unconditional right to revoke previously granted consent at any time, terminating subsequent secondary processing.
- **Cross-Border Data Transfers (Sections 41–43)**: Transferring clinical data to external cloud services (such as AI Scribe models, Gemini, or remote diagnostics) requires data minimization, deterministic pseudonymization, and comprehensive transfer audit logs.

The CoreHealth v2 interoperability engine enforces these mandates through:
1. An auxiliary consent tracking ledger (`patient_consents`) operating without mutating existing patient records.
2. The `CheckNdpaConsent` middleware attaching dynamic verification headers (`X-NDPA-Consent-Status`).
3. The `NdprSanitizerService` de-identifying PII and mapping local IDs to persistent UUID pseudonyms.

### Authentication & Non-Repudiation Audit
- **Header**: All NDPA consent management endpoints require:
  ```http
  Authorization: Bearer <sanctum_or_oauth_token>
  Accept: application/json
  ```
- **Auditor Stamping**: The system automatically captures the authenticated user ID (`auth()->id()`) into `patient_consents.granted_by` and client IP into `patient_consents.ip_address`.
- **Authorized Roles**: Restricted via Spatie RBAC to `RECEPTIONIST`, `RECORD_OFFICER`, `DOCTOR`, `ADMIN`, and `SUPERADMIN`.

---

## 2. Consent Management REST Endpoints

### `POST /api/ndpa/v1/consent`
Records a patient's affirmative consent for specific data handling purposes.

- **Route Name**: `ndpa.consent.store`
- **Request Headers**:
  - `Content-Type: application/json`
  - `Accept: application/json`
- **Request Body Fields**:
  - `patient_id` *(integer, required)*: Local database ID of the target patient (must exist in `patients.id`).
  - `consent_type` *(string, required)*: Category of processing authorized. Allowed values:
    - `TREATMENT`: Primary clinical treatment and care coordination.
    - `RESEARCH`: De-identified clinical research and epidemiological analysis.
    - `CLOUD_AI`: Ingestion by external AI models (Clinical AI Scribe Studio, diagnostic LLMs).
    - `THIRD_PARTY_TRANSFER`: Handoff to external HMOs, referral hospitals, or government registries.
    - `ALL`: Comprehensive clinical, administrative, and technological authorization.
  - `ndpa_lawful_basis` *(string, optional)*: Statutory legal basis per NDPA Section 30. Defaults to `EXPLICIT_CONSENT`. Allowed values:
    - `EXPLICIT_CONSENT`
    - `VITAL_INTEREST`
    - `LEGAL_OBLIGATION`
    - `PUBLIC_HEALTH`
  - `notes` *(string, optional)*: Free-text remarks, specific caveats, or witness details (max 500 characters).

- **Example Request Payload**:
  ```json
  {
    "patient_id": 105,
    "consent_type": "CLOUD_AI",
    "ndpa_lawful_basis": "EXPLICIT_CONSENT",
    "notes": "Patient authorized clinical ambient transcription for doctor consultations."
  }
  ```

- **Response `201 Created` (`application/json`)**:
  ```json
  {
    "status": "success",
    "message": "Patient consent recorded successfully per NDPA 2023 standards.",
    "data": {
      "id": 14,
      "patient_id": 105,
      "consent_type": "CLOUD_AI",
      "status": "ACTIVE",
      "ndpa_lawful_basis": "EXPLICIT_CONSENT",
      "granted_by": 1,
      "ip_address": "127.0.0.1",
      "notes": "Patient authorized clinical ambient transcription for doctor consultations.",
      "granted_at": "2026-11-11T07:15:00.000000Z",
      "created_at": "2026-11-11T07:15:00.000000Z",
      "updated_at": "2026-11-11T07:15:00.000000Z"
    }
  }
  ```

---

### `GET /api/ndpa/v1/consent/{patientId}`
Retrieves the complete historical and active consent records for a specified patient.

- **Route Name**: `ndpa.consent.show`
- **URI Parameters**:
  - `patientId` *(integer, required)*: The local ID of the patient.
- **Request Headers**:
  - `Accept: application/json`

- **Response `200 OK` (`application/json`)**:
  ```json
  {
    "status": "success",
    "patient_id": 105,
    "consents": [
      {
        "id": 14,
        "patient_id": 105,
        "consent_type": "CLOUD_AI",
        "status": "ACTIVE",
        "ndpa_lawful_basis": "EXPLICIT_CONSENT",
        "granted_by": 1,
        "ip_address": "127.0.0.1",
        "notes": "Patient authorized clinical ambient transcription for doctor consultations.",
        "granted_at": "2026-11-11T07:15:00.000000Z",
        "revoked_at": null,
        "created_at": "2026-11-11T07:15:00.000000Z",
        "updated_at": "2026-11-11T07:15:00.000000Z"
      },
      {
        "id": 8,
        "patient_id": 105,
        "consent_type": "THIRD_PARTY_TRANSFER",
        "status": "REVOKED",
        "ndpa_lawful_basis": "EXPLICIT_CONSENT",
        "granted_by": 1,
        "ip_address": "127.0.0.1",
        "notes": "Previous insurer exchange consent.",
        "granted_at": "2026-08-10T11:20:00.000000Z",
        "revoked_at": "2026-10-01T14:30:00.000000Z",
        "created_at": "2026-08-10T11:20:00.000000Z",
        "updated_at": "2026-10-01T14:30:00.000000Z"
      }
    ]
  }
  ```

- **Error Responses**:
  - `404 Not Found`: Returned when the `patientId` does not exist in `patients`.

---

### `DELETE /api/ndpa/v1/consent/{id}`
Revokes an existing consent entry per NDPA Section 34 (Right to Withdraw Consent).

- **Route Name**: `ndpa.consent.revoke`
- **URI Parameters**:
  - `id` *(integer, required)*: The primary key of the consent record in `patient_consents`.
- **Request Headers**:
  - `Accept: application/json`

- **Response `200 OK` (`application/json`)**:
  ```json
  {
    "status": "success",
    "message": "Consent revoked successfully.",
    "data": {
      "id": 14,
      "patient_id": 105,
      "consent_type": "CLOUD_AI",
      "status": "REVOKED",
      "ndpa_lawful_basis": "EXPLICIT_CONSENT",
      "granted_by": 1,
      "ip_address": "127.0.0.1",
      "notes": "Patient authorized clinical ambient transcription for doctor consultations.",
      "granted_at": "2026-11-11T07:15:00.000000Z",
      "revoked_at": "2026-11-11T07:30:00.000000Z",
      "created_at": "2026-11-11T07:15:00.000000Z",
      "updated_at": "2026-11-11T07:30:00.000000Z"
    }
  }
  ```

---

## 3. Middleware: `CheckNdpaConsent`

The `App\Interoperability\Http\Middleware\CheckNdpaConsent` class intercepts requests involving patient-specific clinical queries.

### Enforcement Rules:
1. **Parameter Detection**: Automatically inspects route parameters (`patientId`, `patient`) and query/request body inputs (`patient_id`).
2. **Consent Resolution**: Invokes `PatientConsent::hasActiveConsent($patientId, $consentType)`.
3. **Response Headers**:
   - `X-NDPA-Consent-Status: CONSENT_VERIFIED` — An affirmative `ACTIVE` consent record was matched for this patient and category.
   - `X-NDPA-Consent-Status: DEFAULT_VITAL_INTEREST` — No explicit consent was found; processing proceeded under the emergency/vital interest presumption permitted for emergency care delivery.

---

## 4. Real-time PII Sanitization: `NdprSanitizerService`

The `NdprSanitizerService` ensures clinical texts and AI prompts comply with NDPA Sections 41–43 before transmission to cloud or multi-tenant services.

### Clinical Text Sanitization (`sanitizeClinicalText`)
Replaces direct patient personal identifiers with strict redaction placeholders:

| Target Identifier Pattern | Source | Replacement Token |
| :--- | :--- | :--- |
| First, Surname, Othername | `users.firstname`, `users.surname` | `[REDACTED_NAME]` |
| Phone Numbers (Local / Int'l) | `+234...`, `080...`, `070...`, `090...` | `[REDACTED_PHONE]` |
| Email Addresses | RFC 5322 Email regex | `[REDACTED_EMAIL]` |
| National Identity Number (NIN) | 11 consecutive numeric digits | `[REDACTED_NIN]` |
| Medical Record Numbers | `PAT-...`, `MRN-...`, `FILE-...` | `[REDACTED_FILE_NO]` |

### Deterministic Pseudonymization
External AI models (e.g., Gemini Cloud for Clinical AI Scribe Studio) must not receive raw database keys. The service provides:
- **`getPatientPseudonym(int $patientId): string`**: Fetches or mints a stable UUID stored in `interop_external_identifiers` under system `NDPA` and entity `PatientPseudonym`.
- **`resolvePseudonym(string $pseudonym): ?int`**: Safely translates incoming AI responses back to the local database ID without exposing patient identity to external networks.

### Cross-Border Audit Logging
`auditCrossBorderTransfer($patientId, $targetService, $dataCategory, $purpose)` records immutable audit entries in the daily log:
```json
{
  "patient_id": 105,
  "pseudonym": "61c33c37-9755-465d-b0ad-e906e5da560e",
  "target_service": "Google Gemini Cloud AI Scribe",
  "data_category": "Clinical Ambient Audio Transcription",
  "purpose": "Clinical consultation note summarization",
  "timestamp": "2026-11-11T07:20:00+01:00",
  "jurisdiction_safeguard": "Standard Contractual Clauses & Anonymization per NDPA Sec 42"
}
```
