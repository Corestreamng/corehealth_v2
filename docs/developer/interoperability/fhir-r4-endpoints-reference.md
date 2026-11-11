# HL7 FHIR R4 REST Endpoints Detailed Reference

> **Standard**: HL7 FHIR Release 4 (v4.0.1)  
> **Base Path**: `/api/fhir/r4`  
> **Content-Type**: `application/fhir+json; charset=UTF-8`  
> **Architectural Pattern**: Controller Proxy & Inbound Mutation (Local Work Parity)  

---

## 0. Authentication, Authorization & SMART on FHIR

The FHIR R4 API implements the SMART on FHIR authorization standard backed by Laravel Sanctum:

- **Bearer Token Header**: All protected requests require:
  ```http
  Authorization: Bearer <sanctum_or_oauth_token>
  Accept: application/fhir+json
  ```
- **Public Endpoints**: Only `GET /api/fhir/r4/metadata` is public without authentication, advertising the SMART authorization server URLs.
- **SMART Scopes & Spatie Role Mapping**:
  - `patient/*.read` / `patient/*.write`: Bound to patient-compartment access.
  - `user/Patient.read`, `user/Patient.write`: Mapped to `RECEPTIONIST`, `ADMIN`, `SUPERADMIN`.
  - `user/Observation.*`: Mapped to `DOCTOR`, `NURSE`.
  - `user/MedicationRequest.*`: Mapped to `DOCTOR`, `PHARMACIST`.
  - `user/ServiceRequest.*`, `user/DiagnosticReport.*`: Mapped to `DOCTOR`, `LAB_SCIENTIST`.
  - `user/Invoice.*`, `user/PaymentReconciliation.*`: Mapped to `CASHIER`, `ACCOUNTING_OFFICER`.
- **NDPA Statutory Gatekeeper**: Requests are processed through `CheckNdpaConsent` middleware. The response includes `X-NDPA-Consent-Status: CONSENT_VERIFIED` or `DEFAULT_VITAL_INTEREST`.

---

## 1. Conformance & Metadata

### `GET /api/fhir/r4/metadata`
Retrieves the server's official `CapabilityStatement` declaring supported resource types, interactions, profiles, and search parameters.

- **Request Headers**:
  - `Accept: application/fhir+json`
- **Controller**: `FhirCapabilityController@show` (`app/Interoperability/Http/Controllers/Fhir/FhirCapabilityController.php`)
- **Response `200 OK`**:
  ```json
  {
    "resourceType": "CapabilityStatement",
    "id": "corehealth-fhir-r4",
    "status": "active",
    "fhirVersion": "4.0.1",
    "format": ["application/fhir+json", "application/json"],
    "rest": [{
      "mode": "server",
      "resource": [
        {"type": "Patient", "interaction": [{"code": "read"}, {"code": "search-type"}, {"code": "create"}]},
        {"type": "Observation", "interaction": [{"code": "read"}, {"code": "search-type"}, {"code": "create"}]},
        {"type": "Encounter", "interaction": [{"code": "read"}, {"code": "search-type"}, {"code": "create"}]},
        {"type": "MedicationRequest", "interaction": [{"code": "read"}, {"code": "search-type"}, {"code": "create"}]},
        {"type": "ServiceRequest", "interaction": [{"code": "read"}, {"code": "search-type"}, {"code": "create"}]},
        {"type": "DiagnosticReport", "interaction": [{"code": "read"}, {"code": "search-type"}, {"code": "update"}]},
        {"type": "Invoice", "interaction": [{"code": "read"}, {"code": "search-type"}]}
      ]
    }]
  }
  ```

---

## 2. Patient Administration

### `GET /api/fhir/r4/Patient`
Searches patient records using FHIR query parameters.

- **Query Parameters**:
  - `identifier` *(string, optional)*: Match hospital MRN / `file_no` or HMO member number.
  - `name` *(string, optional)*: Partial match against surname, firstname, or othername.
  - `gender` *(string, optional)*: `male` | `female` | `other`.
  - `birthdate` *(date, optional)*: Format `YYYY-MM-DD`.
  - `_count` *(int, optional)*: Page limit (default `20`, max `50`).
- **Response `200 OK`**: Standard FHIR `Bundle` with `type: searchset` and array of `Patient` entries.

### `GET /api/fhir/r4/Patient/{id}`
Reads a single patient by external UUID or numeric local patient ID.

- **Path Parameters**: `id` (UUID or numeric ID)
- **Response `200 OK`**: Complete FHIR R4 `Patient` resource including telecom, addresses, Nigerian extensions (`bloodGroup`, `genotype`), and next-of-kin contacts.
- **Error `404 Not Found`**: Returns `OperationOutcome` with diagnostic issue.

### `POST /api/fhir/r4/Patient` (Inbound Patient Creation)
Creates a new patient record. Automatically mutates payload into internal keys and executes the hospital's production `PatientController@store` (`app/Http/Controllers/PatientController.php`).

- **Inbound Mutator**: `InboundFhirPatientMutator::mutateToStoreRequest()`
- **Proxy Controller**: `app(PatientController::class)->store($mutatedRequest)`
- **Core Models Mutated**: `User` (`app/Models/User.php`), `Patient` (`app/Models/Patient.php`), `PatientAccount` (`app/Models/PatientAccount.php`)
- **Observers Triggered**: `PatientObserver` (Central sync dispatch, audit logging)
- **Payload Schema (`Patient` JSON)**:
  ```json
  {
    "resourceType": "Patient",
    "identifier": [
      {
        "type": {"coding": [{"system": "http://terminology.hl7.org/CodeSystem/v2-0203", "code": "MR"}]},
        "value": "PAT-99120"
      }
    ],
    "name": [{"family": "Okonkwo", "given": ["Chinedu", "Emeka"]}],
    "telecom": [
      {"system": "phone", "value": "08031234567"},
      {"system": "email", "value": "chinedu@example.com"}
    ],
    "gender": "male",
    "birthDate": "1990-05-15",
    "address": [{"text": "14 Hospital Road, Victoria Island, Lagos"}],
    "contact": [{
      "name": {"text": "Amaka Okonkwo"},
      "telecom": [{"system": "phone", "value": "08099887766"}]
    }]
  }
  ```
- **Response `201 Created`**:
  - Header: `Location: /api/fhir/r4/Patient/{new-uuid}`
  - Body: Saved `Patient` resource with internal ID and persistent UUID mappings.

---

## 3. Vital Signs & Clinical Diagnostics

### `GET /api/fhir/r4/Observation`
Searches vital signs measurements.

- **Query Parameters**:
  - `patient` *(string, optional)*: Reference format `Patient/{id}` or `Patient/{uuid}`.
  - `_count` *(int, optional)*: Page limit.
- **Response `200 OK`**: FHIR `Bundle` with `type: searchset` containing Vital Signs observation sets.

### `POST /api/fhir/r4/Observation` (Inbound Vitals Creation)
Records vital signs from external monitors or mobile apps, proxying `VitalSignController@store` (`app/Http/Controllers/VitalSignController.php`).

- **Inbound Mutator**: `InboundFhirObservationMutator::mutateToStoreRequest()`
- **Proxy Controller**: `app(VitalSignController::class)->store($mutatedRequest)`
- **LOINC Code Bindings**:
  - Systolic BP: `8480-6` (`mm[Hg]`)
  - Diastolic BP: `8462-4` (`mm[Hg]`)
  - Body Temperature: `8310-5` (`Cel`)
  - Heart Rate: `8867-4` (`/min`)
  - Respiratory Rate: `9279-1` (`/min`)
  - Body Weight: `29463-7` (`kg`)
  - Body Height: `8302-2` (`cm`)
  - Pulse Oximetry (SpO2): `59408-5` (`%`)
  - Fasting Blood Sugar: `2339-0` (`mg/dL`)
- **Payload Schema (`Observation` JSON)**:
  ```json
  {
    "resourceType": "Observation",
    "status": "final",
    "category": [
      {"coding": [{"system": "http://terminology.hl7.org/CodeSystem/observation-category", "code": "vital-signs"}]}
    ],
    "subject": {"reference": "Patient/ch2-pat-105"},
    "component": [
      {"code": {"coding": [{"system": "http://loinc.org", "code": "8480-6"}]}, "valueQuantity": {"value": 120, "unit": "mmHg"}},
      {"code": {"coding": [{"system": "http://loinc.org", "code": "8462-4"}]}, "valueQuantity": {"value": 80, "unit": "mmHg"}},
      {"code": {"coding": [{"system": "http://loinc.org", "code": "8310-5"}]}, "valueQuantity": {"value": 36.5, "unit": "°C"}},
      {"code": {"coding": [{"system": "http://loinc.org", "code": "8867-4"}]}, "valueQuantity": {"value": 75, "unit": "beats/min"}}
    ]
  }
  ```
- **Response `201 Created`**: Returns created `Observation` panel.

---

## 4. Encounters & Clinical Workflow

### `GET /api/fhir/r4/Encounter` & `GET /api/fhir/r4/Encounter/{id}`
Retrieves consultation encounter details including attending physician, diagnoses, and encounter duration.

### `POST /api/fhir/r4/Encounter`
Creates an active consultation encounter for a patient.
- **Parameters**: `subject.reference` (required), `reasonCode[0].text` (chief complaint).
- **Core Model Mutated**: `Encounter` (`app/Models/Encounter.php`).

---

## 5. Pharmacy & Medication Management

### `GET /api/fhir/r4/MedicationRequest` & `GET /api/fhir/r4/MedicationRequest/{id}`
Searches prescriptions by patient or reads an existing order.

### `POST /api/fhir/r4/MedicationRequest` (Inbound Prescription)
Prescribes a medication into the hospital's dispensary queue, proxying `EncounterController@addSinglePrescriptionRequest` (`app/Http/Controllers/EncounterController.php`).

- **Inbound Mutator**: `InboundFhirPrescriptionMutator::mutate()`
- **Proxy Controller**: `app(EncounterController::class)->addSinglePrescriptionRequest($mutated['request'], $mutated['encounter'])`
- **Core Model Mutated**: `ProductRequest` (`app/Models/ProductRequest.php`)
- **Observers Triggered**: Dispensary queue notification observers
- **Payload Schema (`MedicationRequest` JSON)**:
  ```json
  {
    "resourceType": "MedicationRequest",
    "status": "active",
    "intent": "order",
    "subject": {"reference": "Patient/ch2-pat-105"},
    "medicationCodeableConcept": {
      "coding": [{"system": "http://www.whocc.no/atc", "code": "N02BE01", "display": "paracetamol"}],
      "text": "Paracetamol 500mg Tablets"
    },
    "dosageInstruction": [{"text": "1g TDS for 3 days"}]
  }
  ```
- **Response `201 Created`**: Location header and created resource.

---

## 6. Diagnostic Orders & Laboratory Reports

### `POST /api/fhir/r4/ServiceRequest` (Inbound Laboratory Order)
Orders a clinical laboratory test, proxying `EncounterController@addSingleLabRequest` (`app/Http/Controllers/EncounterController.php`).

- **Inbound Mutator**: `InboundFhirServiceRequestMutator::mutate()`
- **Proxy Controller**: `app(EncounterController::class)->addSingleLabRequest($mutated['request'], $mutated['encounter'])`
- **Core Model Mutated**: `LabServiceRequest` (`app/Models/LabServiceRequest.php`)
- **Observers Triggered**: `ProductOrServiceRequestObserver` (Department stamping & HMO tariff resolution)
- **Payload Schema (`ServiceRequest` JSON)**:
  ```json
  {
    "resourceType": "ServiceRequest",
    "status": "active",
    "intent": "order",
    "subject": {"reference": "Patient/ch2-pat-105"},
    "code": {
      "coding": [{"system": "http://loinc.org", "code": "58410-2", "display": "Complete blood count panel"}],
      "text": "Full Blood Count"
    },
    "note": [{"text": "Pre-operative assessment"}]
  }
  ```
- **Response `201 Created`**: Returns created `ServiceRequest`.

### `PUT /api/fhir/r4/DiagnosticReport/{id}` (Sign-off Lab Result)
Updates diagnostic results and signs off the report.
- **Parameters**: `conclusion` *(string, required)*.
- **Effects**: Updates `lab_service_requests.status` to `2` (Approved/Completed), sets `approved_at = NOW()`, and records `approved_by`.

---

## 7. Financial & Billing

### `GET /api/fhir/r4/Invoice` & `GET /api/fhir/r4/Invoice/{id}`
Returns billing lines and hospital invoices with line items, currency (`NGN`), and payment status.

### `POST /api/fhir/r4/PaymentReconciliation` (Inbound Payment Processing)
Processes patient payments, proxying `BillingWorkbenchController@processPayment` (`app/Http/Controllers/BillingWorkbenchController.php`).

- **Core Models Mutated**: `Payment` (`app/Models/Payment.php`), `PaymentSplit` (`app/Models/PaymentSplit.php`)
- **Observer Guarantee**: Fires `PaymentObserver` (`app/Observers/PaymentObserver.php`) to post **Double-Entry General Ledger journal entries** (DR Cash / CR Revenue).
- **Payload Schema**:
  ```json
  {
    "resourceType": "PaymentReconciliation",
    "status": "active",
    "paymentIssuer": {"reference": "Patient/ch2-pat-105"},
    "detail": [
      {"request": {"reference": "Invoice/ch2-inv-881"}, "amount": {"value": 7500, "currency": "NGN"}}
    ]
  }
  ```
- **Response `200 OK`**: Returns reconciled status and transaction confirmation.
