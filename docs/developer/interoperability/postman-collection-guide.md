# CoreHealth Interoperability API — Postman Collection Guide

> Download the collection: `postman/CoreHealth-Interop-Collection.json`  
> (Also at `docs/developer/interoperability/corehealth-interop-postman-collection.json`)

---

## 1. Import the Collection

1. Open Postman → **Import** → **Upload Files**
2. Select `CoreHealth-Interop-Collection.json`
3. Click **Import** — the collection appears as **"CoreHealth v2 Interoperability API"**

---

## 2. Set Up the Environment

1. In Postman → **Environments** → **New**
2. Name it `CoreHealth Local` (or `CoreHealth PHC Node 01` for field deployment)
3. Add these variables:

| Variable | Initial Value | Notes |
|---|---|---|
| `base_url` | `http://127.0.0.1:8000` | Change to your on-prem or cloud host |
| `token` | *(empty)* | Auto-populated by request `00/POST Acquire Sanctum PAT` |
| `sdk_token` | *(empty)* | Auto-populated by request `05.1/POST Issue SDK Session Token` |
| `patient_id` | `492` | Canonical test patient (Oladipo Amina, file 14-32-97) |
| `encounter_id` | `1017` | Active test encounter |
| `ehr_id` | *(empty)* | Auto-populated by `03.1/POST EHR Create` |
| `client_id` | `partner-emr-dev` | Your SDK client ID |
| `client_secret` | *(your secret)* | Mark as **secret** in Postman |

4. Select this environment before running any requests.

---

## 3. Run Order (Step-by-Step)

Always run in this order on a fresh environment. Each section's first request populates tokens needed by later ones.

### Section 00 — Authentication

**00/POST Acquire Sanctum PAT**
```
POST {{base_url}}/sanctum/token
Content-Type: application/json

{
  "email":       "sysadmin@mail.com",
  "password":    "123456",
  "device_name": "postman-interop-test"
}
```
Expected: `200 OK` → plain text token string  
✅ The collection test script auto-sets `{{token}}` from the response.

**Next step:** All subsequent requests use `Authorization: Bearer {{token}}` automatically.

---

### Section 01 — NDPA 2023 Consent

**01.1 POST Record Consent — TREATMENT**
```
POST {{base_url}}/api/interop/ndpa/v1/consent
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "patient_id":    492,
  "purpose":       "TREATMENT",
  "consent_given": true,
  "consent_date":  "2026-11-13",
  "recorded_by":   38
}
```
Expected: `201 Created`
```json
{ "id": 12, "patient_id": 492, "purpose": "TREATMENT", "consent_given": true }
```

**01.2 POST Record Consent — CLOUD_AI** *(required before cross-border AI processing)*
```json
{ "patient_id": 492, "purpose": "CLOUD_AI", "consent_given": true, "consent_date": "2026-11-13", "recorded_by": 38 }
```

**01.3 GET List Patient Consents**
```
GET {{base_url}}/api/interop/ndpa/v1/consent/{{patient_id}}
Authorization: Bearer {{token}}
```
Expected: `200 OK` — array of consent objects

**Next step:** With TREATMENT consent in place, proceed to FHIR or openEHR requests.

---

### Section 02 — HL7 FHIR R4

**02.1 GET Capability Statement** *(no auth — public)*
```
GET {{base_url}}/api/interop/fhir/r4/metadata
Accept: application/fhir+json
```
Expected: `200 OK` — `CapabilityStatement` resource with `fhirVersion: "4.0.1"` and all supported resources listed.

**02.2 GET Patient Read**
```
GET {{base_url}}/api/interop/fhir/r4/Patient/{{patient_id}}
Authorization: Bearer {{token}}
Accept: application/fhir+json
```
Expected: `200 OK`
```json
{
  "resourceType": "Patient",
  "id": "492",
  "name": [{ "family": "Oladipo", "given": ["Amina"] }],
  "gender": "female",
  "birthDate": "1991-04-15",
  "identifier": [{ "system": "corehealth-file-no", "value": "14-32-97" }]
}
```

**02.4 POST Observation (Vital Signs)**
```
POST {{base_url}}/api/interop/fhir/r4/Observation
Authorization: Bearer {{token}}
Content-Type: application/fhir+json

{
  "resourceType": "Observation",
  "status": "final",
  "code": {
    "coding": [{ "system": "http://loinc.org", "code": "55284-4", "display": "Blood pressure systolic and diastolic" }]
  },
  "subject": { "reference": "Patient/{{patient_id}}" },
  "effectiveDateTime": "2026-11-13T09:15:00+01:00",
  "component": [
    { "code": { "coding": [{ "system": "http://loinc.org", "code": "8480-6", "display": "Systolic" }] },
      "valueQuantity": { "value": 128, "unit": "mm[Hg]", "system": "http://unitsofmeasure.org" } },
    { "code": { "coding": [{ "system": "http://loinc.org", "code": "8462-4", "display": "Diastolic" }] },
      "valueQuantity": { "value": 82,  "unit": "mm[Hg]", "system": "http://unitsofmeasure.org" } }
  ]
}
```
Expected: `201 Created` — echoes back the Observation with a CoreHealth `id`.  
⚙️ Internally proxies `VitalSignController@store` — all observers fire.

**02.6 POST MedicationRequest**
```
POST {{base_url}}/api/interop/fhir/r4/MedicationRequest
Authorization: Bearer {{token}}
Content-Type: application/fhir+json

{
  "resourceType": "MedicationRequest",
  "status": "active",
  "intent": "order",
  "medicationCodeableConcept": {
    "coding": [{ "system": "http://www.whocc.no/atc", "code": "A10BA02", "display": "Metformin" }]
  },
  "subject": { "reference": "Patient/{{patient_id}}" },
  "encounter": { "reference": "Encounter/{{encounter_id}}" },
  "dosageInstruction": [{ "text": "500mg twice daily with meals" }]
}
```
Expected: `201 Created`  
⚙️ Proxies `EncounterController@addSinglePrescriptionRequest` — pharmacy queue updated.

**Next step:** After creating Observations and MedicationRequests, check the openEHR section to view the same data as a Composition.

---

### Section 03 — openEHR ITS-REST

**03.1 POST EHR Create**
```
POST {{base_url}}/api/interop/openehr/v1/ehr
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "subject": {
    "externalRef": {
      "id": "PATIENT-492",
      "namespace": "corehealth",
      "type": "PERSON"
    }
  }
}
```
Expected: `201 Created`
```json
{ "ehr_id": "7f3a1d2e-...", "system_id": "corehealth-ng" }
```
✅ Test script auto-sets `{{ehr_id}}`.

**03.2 GET EHR Read**
```
GET {{base_url}}/api/interop/openehr/v1/ehr/{{ehr_id}}
Authorization: Bearer {{token}}
```
Expected: `200 OK` — EHR status object with subject reference.

**03.5 POST Composition Create — Blood Pressure**
```
POST {{base_url}}/api/interop/openehr/v1/ehr/{{ehr_id}}/composition
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "archetypeId": "openEHR-EHR-OBSERVATION.blood_pressure.v2",
  "templateId": "corehealth_encounter_summary",
  "language": { "code": "en", "terminology": "ISO_639-1" },
  "territory": { "code": "NG", "terminology": "ISO_3166-1" },
  "content": [{
    "archetypeId": "openEHR-EHR-OBSERVATION.blood_pressure.v2",
    "data": {
      "systolic":  { "magnitude": 128, "units": "mm[Hg]" },
      "diastolic": { "magnitude": 82,  "units": "mm[Hg]" }
    }
  }]
}
```
Expected: `201 Created` — Composition UUID returned in `Location` header.

**03.6 POST AQL Query — Blood Pressure Panel**
```
POST {{base_url}}/api/interop/openehr/v1/query/aql
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "q": "SELECT e/ehr_id/value, o/data[at0001]/events[at0006]/data[at0003]/items[at0004]/value/magnitude AS systolic, o/data[at0001]/events[at0006]/data[at0003]/items[at0005]/value/magnitude AS diastolic FROM EHR e CONTAINS OBSERVATION o[openEHR-EHR-OBSERVATION.blood_pressure.v2] WHERE e/ehr_id/value='{{ehr_id}}'",
  "offset": 0,
  "fetch": 20
}
```
Expected: `200 OK` — `rows` array with ehr_id, systolic, diastolic values.

**Next step:** Use AQL to aggregate epidemiological data across all PHCs — no SQL access required.

---

### Section 04 — OpenEMR REST Compatibility

**04.1 GET Patient List**
```
GET {{base_url}}/apis/default/api/patient
Authorization: Bearer {{token}}
```
Expected: `200 OK` — `{ "data": [ { "pid": "492", "fname": "Amina", ... } ] }`

**04.4 GET C-CDA XML Export**
```
GET {{base_url}}/apis/default/api/ccda/{{patient_id}}
Authorization: Bearer {{token}}
Accept: application/xml
```
Expected: `200 OK` — CCD XML document with patient summary.

---

### Section 05 — AI Scribe SDK

**05.1 POST Issue SDK Session Token**
```
POST {{base_url}}/api/sdk/auth/token
Content-Type: application/json

{
  "client_id":     "{{client_id}}",
  "client_secret": "{{client_secret}}",
  "patient_id":    492,
  "encounter_id":  1017,
  "clinician_id":  38,
  "scope":         "scribe:process scribe:sign interop:compositions"
}
```
Expected: `200 OK`
```json
{
  "sdk_token":  "SKT_eyJ...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "scope":      "scribe:process scribe:sign interop:compositions"
}
```
✅ Test script auto-sets `{{sdk_token}}`.

**05.2 POST Create Scribe Session**
```
POST {{base_url}}/api/sdk/scribe/session
Authorization: Bearer {{sdk_token}}
Content-Type: application/json

{
  "patient_id":   492,
  "encounter_id": 1017,
  "clinician_id": 38,
  "locale":       "en-NG"
}
```
Expected: `201 Created` — `{ "session_id": "ses_...", "status": "IDLE" }`

**05.3 GET Session Status**
```
GET {{base_url}}/api/sdk/scribe/session/{{sdk_token}}
Authorization: Bearer {{sdk_token}}
```
Expected: `200 OK` — `{ "status": "SIGNED_OFF", "signed_at": "2026-11-13T15:42:00+01:00" }`

**05.4 POST Get openEHR Composition + FHIR Bundle** *(after sign-off)*
```
POST {{base_url}}/api/sdk/scribe/session/{{sdk_token}}/compositions
Authorization: Bearer {{sdk_token}}
Content-Type: application/json

{ "cross_border": false }
```
Expected: `200 OK` — full payload with `openehr_composition` + `fhir_bundle` + `transcript_summary` + `signed_at`

> See `sdk-embeddable-integration-guide.md` §5 for full response sample.

---

## 4. Shared Test Script (Collection Level)

```js
// Runs after every request
pm.test("No server error", () => {
    pm.expect(pm.response.code).to.not.be.oneOf([500, 502, 503]);
});

if (pm.response.code === 401) {
    console.warn("Token may be expired — re-run 00/POST Acquire Sanctum PAT");
}
```

---

## 5. Sync With Submodule

The Postman collection lives in two places — always keep them identical:

```bash
# After updating docs/developer/interoperability/corehealth-interop-postman-collection.json
cp docs/developer/interoperability/corehealth-interop-postman-collection.json \
   vendor/corehealth-sdk/postman/CoreHealth-Interop-Collection.json
```

