# HL7 FHIR R4 Core Resource Specifications & Parameter Registry

> **Standard**: HL7 FHIR Release 4 (v4.0.1)  
> **Source Grounding**: [HL7 FHIR R4 Specification](https://hl7.org/fhir/R4/)  
> **Target Interoperability Facade**: CoreHealth v2 Translation & Compatibility Layer  
> **Verification Scope**: Comprehensive Field-by-Field Coverage of 21 Core Clinical & Operational Models  

---

## 1. Overview & Architectural Role

HL7 FHIR R4 provides RESTful JSON data exchange for CoreHealth v2. In CoreHealth v2, the **FHIR Translation & Compatibility Layer** (`app/Interoperability/`) dynamically transforms internal Eloquent models into standard FHIR resources on-the-fly without altering existing MySQL database tables.

---

## 2. Comprehensive Model-to-FHIR Field-by-Field Registry

### 2.1 Demographics & Identity: `Patient` & `User` $\to$ FHIR `Patient`
- **Models**: `App\Models\Patient` (`app/Models/Patient.php`) + `App\Models\User` (`app/Models/User.php`)

| CoreHealth Model Field | Data Type | Target FHIR R4 Element Path | Transformation Logic & Terminology Binding |
|---|---|---|---|
| `patient.id` | `bigint` | `Patient.id` | Internal ID or derived UUID (`ch2-pat-{id}`) |
| `patient.file_no` | `string` | `Patient.identifier[type=MR].value` | System: `http://corehealth.hospital.internal/mrn` |
| `patient.misc['nin']` | `string` | `Patient.identifier[type=NNNGA].value`| System: `http://nimc.gov.ng/nin` (National Identity Number) |
| `patient.dhis_consult_tracker_id` | `string` | `Patient.identifier[type=DHIS2].value`| National DHIS2 Tracker patient instance ID |
| `patient.dhis_consult_enrollment_id`| `string` | `Patient.identifier[type=DHIS2_ENR].value`| National DHIS2 program enrollment ID |
| `user.status` + `patient.is_deceased` | `int`, `bool` | `Patient.active` | `!is_deceased && user.status == 1` |
| `user.surname` | `string` | `Patient.name[use=official].family` | Family name / surname |
| `user.firstname`, `othername` | `string` | `Patient.name[use=official].given` | Array: `[firstname, othername]` |
| `patient.phone_no` | `string` | `Patient.telecom[system=phone].value` | `use: mobile` |
| `user.email` | `string` | `Patient.telecom[system=email].value` | `use: home` |
| `patient.gender` | `string` | `Patient.gender` | Lowercase: `male` \| `female` \| `other` \| `unknown` |
| `patient.dob` | `date` | `Patient.birthDate` | ISO 8601 (`YYYY-MM-DD`) |
| `patient.is_deceased` | `boolean` | `Patient.deceasedBoolean` | Standard boolean |
| `patient.date_of_death` | `date` | `Patient.deceasedDateTime` | ISO 8601 DateTime |
| `patient.blood_group` + `genotype` | `string` | `Patient.extension[blood-genotype]` | Custom extension or separate child `Observation` |
| `patient.disability` | `string` | `Patient.extension[disability]` | Disability classification |
| `patient.address` | `string` | `Patient.address[0].line` | Array containing street address |
| `patient.nationality` | `string` | `Patient.address[0].country` | ISO 3166 country name / code |
| `patient.next_of_kin_name` | `string` | `Patient.contact[0].name.text` | Contact person text |
| `patient.next_of_kin_phone` | `string` | `Patient.contact[0].telecom[system=phone].value` | Next-of-kin phone number |
| `patient.next_of_kin_address` | `string` | `Patient.contact[0].address.line` | Next-of-kin address |
| `patient.principal_id` | `bigint` | `Patient.link[type=seealso]` | Reference to family principal `Patient/ch2-pat-{principal_id}` |
| `patient.hmo_id`, `hmo_no` | `int`, `str` | `Coverage.subscriberId`, `Coverage.payor`| Links to linked FHIR `Coverage` resource |

---

### 2.2 Clinical Practitioner: `Staff` & `User` $\to$ FHIR `Practitioner` & `PractitionerRole`
- **Models**: `App\Models\Staff` (`app/Models/Staff.php`) + `App\Models\User` (`app/Models/User.php`)

| CoreHealth Model Field | Target FHIR Element Path | Transformation Logic & Terminology |
|---|---|---|
| `staff.id`, `staff.user_id` | `Practitioner.id` | `ch2-staff-{id}` |
| `staff.employee_id` | `Practitioner.identifier[type=EI]` | Hospital employee badge ID |
| `staff.license_number` | `Practitioner.qualification.identifier`| Medical Council license (MDCN / NMCN / PCN) |
| `staff.license_expiry_date` | `Practitioner.qualification.period.end` | License expiration date |
| `staff.specialization_id` | `PractitionerRole.specialty` | Mapped via `Specialization` model to SNOMED CT |
| `staff.clinic_id`, `department_id` | `PractitionerRole.organization` | Department / Clinic location reference |
| `staff.consultation_fee` | `PractitionerRole.extension[fee]` | Tariff consultation rate |

---

### 2.3 Vital Signs & Biometrics: `VitalSign` $\to$ FHIR `Observation` (Vitals Profile)
- **Model**: `App\Models\VitalSign` (`app/Models/VitalSign.php`)
- **FHIR Profile**: `http://hl7.org/fhir/StructureDefinition/vitalsigns`

| Vital Sign Parameter | CoreHealth Column | Grounded LOINC Code | LOINC Display Name | UCUM Unit Code | UCUM Unit Display |
|---|---|---|---|---|---|
| **Blood Pressure Panel** | `blood_pressure` | `85354-9` | Blood pressure panel with all children optional | *N/A (Panel)* | *N/A* |
| **Systolic Blood Pressure** | Parsed from `blood_pressure` | `8480-6` | Systolic blood pressure | `mm[Hg]` | mmHg |
| **Diastolic Blood Pressure** | Parsed from `blood_pressure` | `8462-4` | Diastolic blood pressure | `mm[Hg]` | mmHg |
| **Heart Rate / Pulse** | `heart_rate` | `8867-4` | Heart rate | `/min` | beats/minute |
| **Body Temperature** | `temp` | `8310-5` | Body temperature | `Cel` | °C |
| **Respiratory Rate** | `resp_rate` | `9279-1` | Respiratory rate | `/min` | breaths/minute |
| **Oxygen Saturation ($SpO_2$)**| `spo2` | `59408-5` | Oxygen saturation in Arterial blood by Pulse oximetry | `%` | % |
| **Body Weight** | `weight` | `29463-7` | Body weight | `kg` | kg |
| **Body Height** | `height` | `8302-2` | Body height | `cm` | cm |
| **Body Mass Index (BMI)** | `bmi` (or calculated) | `39156-5` | Body mass index (BMI) [Ratio] | `kg/m2` | kg/m² |
| **Blood Glucose** | `blood_sugar` | `2339-0` | Glucose [Mass/volume] in Blood | `mg/dL` | mg/dL |
| **Pain Severity** | `pain_score` | `72514-3` | Pain severity - 0-10 verbal numeric rating score | `{score}` | 0-10 scale |
| **Extended Form Data** | `form_data` (JSON) | Component items | Maps custom ward vitals (e.g. neurological pupil size, GCS) |

---

### 2.4 Consultations: `Encounter` & `DoctorQueue` $\to$ FHIR `Encounter`
- **Models**: `App\Models\Encounter` (`app/Models/Encounter.php`), `App\Models\DoctorQueue` (`app/Models/DoctorQueue.php`)

| CoreHealth Model Field | Target FHIR Element Path | Transformation Logic |
|---|---|---|
| `encounter.id` | `Encounter.id` | `ch2-enc-{id}` |
| `encounter.completed`, `outcome` | `Encounter.status` | `completed ? "finished" : (started_at ? "in-progress" : "planned")` |
| `admission_request_id` | `Encounter.class` | Present: `IMP` (inpatient), Absent: `AMB` (ambulatory outpatient) |
| `encounter.patient_id` | `Encounter.subject` | `Patient/ch2-pat-{patient_id}` |
| `encounter.doctor_id` | `Encounter.participant[0].individual` | `Practitioner/ch2-staff-{doctor_id}` |
| `encounter.started_at`, `completed_at` | `Encounter.period` | `start`, `end` timestamps in ISO 8601 |
| `encounter.reasons_for_encounter` | `Encounter.reasonCode` | Mapped via linked `ReasonForEncounter` codes (ICD-10) |
| `encounter.reasons_for_encounter_comment_1`| `Encounter.reasonCode.text` | Clinician narrative explanation |
| `encounter.notes` | `DocumentReference` / narrative | Clinical note text |
| `doctor_queue.priority` | `Encounter.priority` | Normal, Urgent, Emergency |
| `doctor_queue.triage_note` | `Encounter.extension[triage-note]` | Nurse intake note |

---

### 2.5 Diagnoses & Problems: `TreatmentPlan` $\to$ FHIR `Condition` & `CarePlan`
- **Model**: `App\Models\TreatmentPlan` (`app/Models/TreatmentPlan.php`)

| CoreHealth Field | Target FHIR Element Path | Transformation Logic |
|---|---|---|
| `treatment_plan.id` | `Condition.id` / `CarePlan.id` | `ch2-cond-{id}` |
| `treatment_plan.icd_code` | `Condition.code.coding[0].code` | System: `http://hl7.org/fhir/sid/icd-10` |
| `treatment_plan.problem_text` | `Condition.code.text` | Problem narrative description |
| `treatment_plan.diagnosis_data` (JSON) | `Condition.code.coding` | Array of mapped ICD-10 & SNOMED CT codes |
| `treatment_plan.diagnosis_status` | `Condition.verificationStatus` | `provisional` $\to$ `provisional`, `confirmed` $\to$ `confirmed` |
| `treatment_plan.diagnosis_course` | `Condition.severity` | `acute` $\to$ `acute`, `chronic` $\to$ `chronic` |
| `treatment_plan.goal` | `Goal.description.text` | Clinical goal text |
| `treatment_plan.progress_percent` | `CarePlan.activity.progress` | Percentage integer (0-100) |
| `treatment_plan.patient_id` | `Condition.subject` | `Patient/ch2-pat-{patient_id}` |
| `treatment_plan.encounter_id` | `Condition.encounter` | `Encounter/ch2-enc-{encounter_id}` |

---

### 2.6 Diagnostic Investigations: `LabServiceRequest` $\to$ FHIR `ServiceRequest` & `DiagnosticReport`
- **Model**: `App\Models\LabServiceRequest` (`app/Models/LabServiceRequest.php`)

| CoreHealth Field | Target FHIR Element Path | Transformation Logic |
|---|---|---|
| `lab_service_request.id` | `DiagnosticReport.id` | `ch2-lab-{id}` |
| `lab_service_request.lab_number` | `DiagnosticReport.identifier` | Accession number |
| `lab_service_request.status` | `DiagnosticReport.status` | `approved_at ? "final" : (result ? "preliminary" : "registered")` |
| `lab_service_request.service_id` | `DiagnosticReport.code` | Service LOINC code (via `interop_terminology_maps`) |
| `lab_service_request.sample_date` | `Specimen.collection.collectedDateTime` | Timestamp of sample collection |
| `lab_service_request.sample_taken_by` | `Specimen.collection.collector` | Phlebotomist / Lab Scientist Practitioner reference |
| `lab_service_request.result` | `DiagnosticReport.conclusion` | Overall report text |
| `lab_service_request.result_data` (JSON) | `DiagnosticReport.result` | References to child `Observation` resources per analyte |
| `lab_service_request.attachments` (JSON)| `DiagnosticReport.presentedForm` | Attachment array (PDF / scan) |
| `lab_service_request.approved_by`, `approved_at` | `DiagnosticReport.resultsInterpreter` | Consultant pathologist sign-off |
| `lab_service_request.nhmis_outcome` | `DiagnosticReport.extension[nhmis]` | Nigeria National Health MIS classification |

---

### 2.7 Imaging / Radiology: `ImagingServiceRequest` $\to$ FHIR `DiagnosticReport` & `ImagingStudy`
- **Model**: `App\Models\ImagingServiceRequest` (`app/Models/ImagingServiceRequest.php`)

| CoreHealth Field | Target FHIR Element Path | Transformation Logic |
|---|---|---|
| `imaging_service_request.id` | `DiagnosticReport.id` (category: `RAD`) | `ch2-rad-{id}` |
| `imaging_service_request.service_id` | `DiagnosticReport.code` | Radiology procedure code (RadLex / LOINC) |
| `imaging_service_request.result` | `DiagnosticReport.conclusion` | Radiologist narrative impression |
| `imaging_service_request.attachments` | `ImagingStudy.series.instance` | DICOM instance links / uploaded scan images |
| `imaging_service_request.approved_by` | `DiagnosticReport.resultsInterpreter` | Radiologist sign-off |

---

### 2.8 Medication Management: `ProductRequest` $\to$ FHIR `MedicationRequest` & `MedicationDispense`
- **Model**: `App\Models\ProductRequest` (`app/Models/ProductRequest.php`)

| CoreHealth Field | Target FHIR Element Path | Transformation Logic |
|---|---|---|
| `product_request.id` | `MedicationRequest.id` | `ch2-rx-{id}` |
| `product_request.product_id` | `MedicationRequest.medicationCodeableConcept` | WHO ATC / RxNorm code + `product.product_name` |
| `product_request.dose` | `MedicationRequest.dosageInstruction[0]` | Parsed from pipe string `<dose> \| <freq> \| <dur> \| <route>` |
| `product_request.qty` | `MedicationRequest.dispenseRequest.quantity` | Quantity integer |
| `product_request.status` | `MedicationRequest.status` | `dispensed ? "completed" : (deleted_by ? "cancelled" : "active")` |
| `product_request.dispensed_from_batch_id`| `MedicationDispense.dosageInstruction` | Batch lot number from linked `StockBatch` |
| `product_request.dispensed_by`, `dispense_date` | `MedicationDispense.performer`, `whenHandedOver` | Pharmacist reference and handover timestamp |

---

### 2.9 Surgical Operations & Procedures: `Procedure` $\to$ FHIR `Procedure`
- **Model**: `App\Models\Procedure` (`app/Models/Procedure.php`)

| CoreHealth Field | Target FHIR Element Path | Transformation Logic |
|---|---|---|
| `procedure.id` | `Procedure.id` | `ch2-proc-{id}` |
| `procedure.service_id`, `procedure_definition_id` | `Procedure.code` | ICD-10-PCS / CPT-4 surgical code |
| `procedure.procedure_status` | `Procedure.status` | `completed`, `in-progress`, `stopped` |
| `procedure.actual_start_time`, `actual_end_time` | `Procedure.performedPeriod` | Surgical duration start and end |
| `procedure.operating_room` | `Procedure.location` | Theatre / Operating Room location reference |
| `procedure.outcome`, `outcome_notes` | `Procedure.outcome` | Clinical outcome assessment |
| `procedure.pre_notes`, `post_notes` | `Procedure.note` | Pre-op and post-op surgical notes |
| `procedure.consent_status`, `consent_marked_by` | `Procedure.extension[consent]` | Informed surgical consent audit |

---

### 2.10 Inpatient Admissions: `AdmissionRequest` $\to$ FHIR `Encounter` (class=`IMP`) & `Location`
- **Model**: `App\Models\AdmissionRequest` (`app/Models/AdmissionRequest.php`), `App\Models\Bed` (`app/Models/Bed.php`), `App\Models\Ward` (`app/Models/Ward.php`)

| CoreHealth Field | Target FHIR Element Path | Transformation Logic |
|---|---|---|
| `admission_request.id` | `Encounter.id` | `ch2-adm-{id}` |
| `admission_request.admission_status` | `Encounter.status` | `admitted ? "in-progress" : (discharged ? "finished" : "planned")` |
| `admission_request.bed_id` | `Encounter.location[0].location` | Reference to `Location/ch2-bed-{bed_id}` |
| `admission_request.esi_level` | `Encounter.priority` | Emergency Severity Index (Level 1–5) |
| `admission_request.admission_reason` | `Encounter.reasonCode` | Narrative / ICD-10 admission indication |
| `admission_request.discharge_reason`, `discharge_note` | `Encounter.hospitalization.dischargeDisposition` | Discharge notes and disposition |
| `admission_request.followup_instructions` | `Communication.payload` | Patient discharge review instructions |

---

### 2.11 Immunization: `ImmunizationRecord` $\to$ FHIR `Immunization`
- **Model**: `App\Models\ImmunizationRecord` (`app/Models/ImmunizationRecord.php`)

| CoreHealth Field | Target FHIR Element Path | Transformation Logic |
|---|---|---|
| `immunization_record.id` | `Immunization.id` | `ch2-imm-{id}` |
| `immunization_record.vaccine_name` | `Immunization.vaccineCode.text` | Vaccine antigen name |
| `immunization_record.batch_number` | `Immunization.lotNumber` | Vaccine manufacturer batch lot |
| `immunization_record.manufacturer` | `Immunization.manufacturer.display` | Manufacturer name |
| `immunization_record.expiry_date` | `Immunization.expirationDate` | Vaccine vial expiration date |
| `immunization_record.dose_number` | `Immunization.protocolApplied[0].doseNumberPositiveInt` | Dose series number (e.g. 1, 2, 3) |
| `immunization_record.route` | `Immunization.route` | Route (Oral, Intramuscular, Subcutaneous) |
| `immunization_record.site` | `Immunization.site` | Anatomical site (Left deltoid, Right anterolateral thigh) |
| `immunization_record.administered_at` | `Immunization.occurrenceDateTime` | Administration timestamp |
| `immunization_record.administered_by` | `Immunization.performer[0].actor` | Vaccinator Practitioner reference |
| `immunization_record.adverse_reaction` | `Immunization.reaction[0].detail` | Adverse event following immunization (AEFI) |
| `immunization_record.vvm_stage` | `Immunization.extension[vvm-stage]` | WHO Vaccine Vial Monitor Stage (1–4) |

---

### 2.12 Maternal & Antenatal Health: `AncVisit` $\to$ FHIR `Observation`s & `Encounter`
- **Model**: `App\Models\AncVisit` (`app/Models/AncVisit.php`)

| CoreHealth Field | Target FHIR Observation Code | Grounded LOINC Code & Display |
|---|---|---|
| `anc_visit.gestational_age_weeks` + `days` | `Observation.code` | `49052-4` (Gestational age in weeks and days) |
| `anc_visit.fundal_height_cm` | `Observation.code` | `11881-0` (Uterine fundal height, cm) |
| `anc_visit.fetal_heart_rate` | `Observation.code` | `55283-6` (Fetal Heart Rate, /min) |
| `anc_visit.presentation` | `Observation.code` | `11877-8` (Fetal presentation - Cephalic, Breech, Transverse) |
| `anc_visit.foetal_movement` | `Observation.code` | `11878-6` (Fetal movement) |
| `anc_visit.urine_protein` | `Observation.code` | `2888-6` (Protein [Presence] in Urine) |
| `anc_visit.urine_glucose` | `Observation.code` | `2345-7` (Glucose [Presence] in Urine) |
| `anc_visit.haemoglobin` | `Observation.code` | `718-7` (Hemoglobin [Mass/volume] in Blood, g/dL) |

---

### 2.13 Child Growth & Anthropometry: `ChildGrowthRecord` $\to$ FHIR `Observation`s
- **Model**: `App\Models\ChildGrowthRecord` (`app/Models/ChildGrowthRecord.php`)

| CoreHealth Field | Target FHIR Observation Code | Grounded LOINC Code & Display |
|---|---|---|
| `child_growth_record.head_circumference_cm` | `Observation.code` | `8287-5` (Head Occipital-frontal circumference, cm) |
| `child_growth_record.muac_cm` | `Observation.code` | `56072-2` (Circumference Mid upper arm, cm) |
| `child_growth_record.weight_for_age_z` | `Observation.code` | `77353-1` (Weight-for-age z-score [WHO]) |
| `child_growth_record.length_for_age_z` | `Observation.code` | `77354-9` (Length-for-age z-score [WHO]) |
| `child_growth_record.weight_for_length_z` | `Observation.code` | `77355-6` (Weight-for-length z-score [WHO]) |
| `child_growth_record.bmi_for_age_z` | `Observation.code` | `77356-4` (BMI-for-age z-score [WHO]) |
| `child_growth_record.milestones` (JSON) | `Observation.code` | `89574-8` (Child developmental milestones panel) |

---

### 2.14 Billing, Ledger & Payments: `Payment` $\to$ FHIR `Invoice` & `PaymentReconciliation`
- **Model**: `App\Models\Payment` (`app/Models/Payment.php`), `App\Models\PaymentSplit` (`app/Models/PaymentSplit.php`)

| CoreHealth Field | Target FHIR Element Path | Transformation Logic |
|---|---|---|
| `payment.reference_no` | `PaymentReconciliation.identifier` | Receipt reference number |
| `payment.total` | `PaymentReconciliation.paymentAmount` | Total amount in NGN (`currency: "NGN"`) |
| `payment.payment_method` | `PaymentReconciliation.paymentType` | Cash, POS, Transfer, HMO, Deposit |
| `payment_split.amount`, `payment_method`| `PaymentReconciliation.allocation` | Multi-tender split payment lines |
| `payment.journal_entry_id` | `PaymentReconciliation.extension[gl-je]`| Reference to General Ledger Journal Entry header |
| `payment.is_split_payment` | `PaymentReconciliation.extension[is-split]`| Boolean indicator |
| `payment.voided_at`, `void_reason` | `PaymentReconciliation.status` | `voided_at ? "cancelled" : "active"` |

---

### 2.15 AI Scribe Governance: `AiScribeAudit` $\to$ FHIR `AuditEvent` & `Provenance`
- **Model**: `App\Models\AiScribeAudit` (`app/Models/AiScribeAudit.php`)

| CoreHealth Field | Target FHIR Element Path | Transformation Logic |
|---|---|---|
| `ai_scribe_audit.id` | `AuditEvent.id` | `ch2-audit-{id}` |
| `ai_scribe_audit.signed_at` | `AuditEvent.recorded` | ISO 8601 timestamp of electronic signing |
| `ai_scribe_audit.user_id` | `AuditEvent.agent[type=practitioner]` | Clinician who reviewed and signed the note |
| `ai_scribe_audit.model_used`, `provider` | `AuditEvent.agent[type=software]` | AI model identifier (e.g. `gemini-2.5-flash`) |
| `ai_scribe_audit.original_transcript` | `AuditEvent.entity[what=transcript]` | Verbatim spoken text hashed for integrity |
| `ai_scribe_audit.structured_note_json` | `AuditEvent.entity[what=note]` | Structured note array hash |
| `ai_scribe_audit.orders_dispatched_json`| `AuditEvent.entity[what=orders]` | Dispatched orders manifest |
