# OpenEMR Ecosystem & Cross-EHR Compatibility Specifications

> **Standard**: OpenEMR FHIR R4 API (v7.0+), US Core & SMART on FHIR Interoperability  
> **Source Grounding**: [OpenEMR API Documentation](https://www.open-emr.org/wiki/index.php/OpenEMR_API), OpenEMR FHIR Swagger Registry  
> **Target Interoperability Facade**: CoreHealth v2 Translation & Compatibility Layer  

---

## 1. Overview & Architectural Compatibility

OpenEMR is the world's most widely deployed open-source Electronic Medical Records (EMR) software. Achieving OpenEMR compatibility allows CoreHealth v2 to:
1. Interoperate with regional clinics running OpenEMR.
2. Provide standardized CCDA (Consolidated Clinical Document Architecture) and FHIR export/import pipelines for patient referrals.
3. Align terminology and field classifications with OpenEMR's ONC-certified clinical definitions.

---

## 2. OpenEMR REST / FHIR Endpoints Registry

The CoreHealth Translation Layer exposes an OpenEMR-compatible FHIR endpoint structure:
`https://{corehealth-host}/apis/default/fhir/`

| OpenEMR FHIR URI Path | HTTP Methods | CoreHealth Facade Transformer | Authoritative Scopes |
|---|---|---|---|
| `/apis/default/fhir/Patient` | `GET` (Search) | `PatientCollectionTransformer` | `patient/Patient.rs`, `user/Patient.rs` |
| `/apis/default/fhir/Patient/{id}` | `GET` (Read) | `PatientResourceTransformer` | `patient/Patient.read`, `user/Patient.read` |
| `/apis/default/fhir/Encounter` | `GET` (Search) | `EncounterCollectionTransformer` | `patient/Encounter.rs`, `user/Encounter.rs` |
| `/apis/default/fhir/Encounter/{id}` | `GET` (Read) | `EncounterResourceTransformer` | `patient/Encounter.read`, `user/Encounter.read` |
| `/apis/default/fhir/Observation` | `GET` (Search) | `ObservationCollectionTransformer` | `patient/Observation.rs`, `user/Observation.rs` |
| `/apis/default/fhir/Observation/{id}` | `GET` (Read) | `ObservationResourceTransformer` | `patient/Observation.read`, `user/Observation.read` |
| `/apis/default/fhir/Procedure` | `GET` (Search) | `ProcedureCollectionTransformer` | `patient/Procedure.rs`, `user/Procedure.rs` |
| `/apis/default/fhir/MedicationRequest` | `GET` (Search) | `MedicationRequestTransformer` | `patient/MedicationRequest.rs` |

---

## 3. Schema Alignment Matrix: CoreHealth v2 $\leftrightarrow$ OpenEMR Tables

| CoreHealth v2 Model | CoreHealth Table | OpenEMR Equivalent Table | Key Column Alignments |
|---|---|---|---|
| `Patient` + `User` | `patients`, `users` | `patient_data` | `user.surname` $\leftrightarrow$ `lname`<br/>`user.firstname` $\leftrightarrow$ `fname`<br/>`patient.dob` $\leftrightarrow$ `DOB`<br/>`patient.gender` $\leftrightarrow$ `sex`<br/>`patient.file_no` $\leftrightarrow$ `pubpid`<br/>`patient.phone_no` $\leftrightarrow$ `phone_cell` |
| `Encounter` | `encounters` | `form_encounter` | `encounter.id` $\leftrightarrow$ `encounter`<br/>`encounter.patient_id` $\leftrightarrow$ `pid`<br/>`encounter.started_at` $\leftrightarrow$ `date`<br/>`encounter.notes` $\leftrightarrow$ `brief_description` |
| `VitalSign` | `vital_signs` | `form_vitals` | `vital_signs.blood_pressure` $\leftrightarrow$ `bps` / `bpd`<br/>`vital_signs.weight` $\leftrightarrow$ `weight` (kg)<br/>`vital_signs.height` $\leftrightarrow$ `height` (cm)<br/>`vital_signs.temp` $\leftrightarrow$ `temperature` (C)<br/>`vital_signs.heart_rate` $\leftrightarrow$ `pulse`<br/>`vital_signs.resp_rate` $\leftrightarrow$ `respiration`<br/>`vital_signs.spo2` $\leftrightarrow$ `oxygen_saturation` |
| `ProductRequest` | `product_requests` | `prescriptions` | `product_request.product_id` $\leftrightarrow$ `drug_id`<br/>`product_request.dose` $\leftrightarrow$ `dosage`, `unit`, `route`, `interval`<br/>`product_request.qty` $\leftrightarrow$ `quantity` |
| `LabServiceRequest` | `lab_service_requests` | `procedure_order` / `procedure_report` | `lab_service_request.service_id` $\leftrightarrow$ `procedure_type_id`<br/>`lab_service_request.result` $\leftrightarrow$ `report_notes`<br/>`lab_service_request.status` $\leftrightarrow$ `order_status` |

---

## 4. OpenEMR CCDA Interoperability Pipeline

For document-level data exchange, CoreHealth v2 provides a Consolidated Clinical Document Architecture (C-CDA) XML generation pipeline from completed Encounters:
- **Header**: Patient demographics, Document author (Doctor), Legal Authenticator, Custodian Hospital.
- **Sections**:
  - `Results` (Lab & Imaging findings)
  - `Vital Signs` (Observation elements with LOINC codes)
  - `Medications` (Prescription list with active dose schedules)
  - `Problem List` (Active ICD-10 diagnoses from `TreatmentPlan`)
  - `Plan of Treatment` (Follow-up review intervals and referrals)

