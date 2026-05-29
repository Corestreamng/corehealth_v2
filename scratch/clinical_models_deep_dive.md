# Clinical & Operations Models Deep Dive Analysis

Generated on: 2026-05-24 15:05:36
Total Models Analyzed: 58

## Overview of Models & Row Counts

| Model Name | Table Name | Row Count | Status |
| :--- | :--- | :--- | :--- |
| Encounter | `encounters` | **15** | :white_check_mark: Active |
| AdmissionRequest | `admission_requests` | **11** | :white_check_mark: Active |
| Clinic | `clinics` | **32** | :white_check_mark: Active |
| DoctorQueue | `doctor_queues` | **17** | :white_check_mark: Active |
| Hmo | `hmos` | **16** | :white_check_mark: Active |
| ImagingServiceRequest | `imaging_service_requests` | **23** | :white_check_mark: Active |
| LabServiceRequest | `lab_service_requests` | **27** | :white_check_mark: Active |
| NursingNote | `nursing_notes` | **13** | :white_check_mark: Active |
| NursingNoteType | `nursing_note_types` | **5** | :white_check_mark: Active |
| Patient | `patients` | **18** | :white_check_mark: Active |
| ProductOrServiceRequest | `product_or_service_requests` | **198** | :white_check_mark: Active |
| ProductRequest | `product_requests` | **54** | :white_check_mark: Active |
| ReasonForEncounter | `reason_for_encounters` | **12130** | :white_check_mark: Active |
| Service | `services` | **407** | :white_check_mark: Active |
| Staff | `staff` | **263** | :white_check_mark: Active |
| User | `users` | **210** | :white_check_mark: Active |
| Procedure | `procedures` | **12** | :white_check_mark: Active |
| Bed | `beds` | **2** | :white_check_mark: Active |
| Product | `products` | **735** | :white_check_mark: Active |
| ServiceCategory | `service_categories` | **9** | :white_check_mark: Active |
| ProductCategory | `product_categories` | **170** | :white_check_mark: Active |
| VitalSign | `vital_signs` | **7** | :white_check_mark: Active |
| MedicationSchedule | `medication_schedules` | **53** | :white_check_mark: Active |
| MedicationAdministration | `medication_administrations` | **12** | :white_check_mark: Active |
| IntakeOutputPeriod | `intake_output_periods` | **3** | :white_check_mark: Active |
| IntakeOutputRecord | `intake_output_records` | **4** | :white_check_mark: Active |
| InjectionAdministration | `injection_administrations` | **10** | :white_check_mark: Active |
| ImmunizationRecord | `immunization_records` | **20** | :white_check_mark: Active |
| VaccineScheduleTemplate | `vaccine_schedule_templates` | **2** | :white_check_mark: Active |
| VaccineScheduleItem | `vaccine_schedule_items` | **32** | :white_check_mark: Active |
| VaccineProductMapping | `vaccine_product_mappings` | **0** | :white_check_mark: Active |
| PatientImmunizationSchedule | `patient_immunization_schedules` | **218** | :white_check_mark: Active |
| HmoTariff | `hmo_tariffs` | **11624** | :white_check_mark: Active |
| Store | `stores` | **49** | :white_check_mark: Active |
| StoreStock | `store_stocks` | **2782** | :white_check_mark: Active |
| StockBatch | `stock_batches` | **2835** | :white_check_mark: Active |
| StoreContextRule | `store_context_rules` | **3** | :white_check_mark: Active |
| MaternityEnrollment | `maternity_enrollments` | **4** | :white_check_mark: Active |
| MaternityMedicalHistory | `maternity_medical_history` | **5** | :white_check_mark: Active |
| MaternityPreviousPregnancy | `maternity_previous_pregnancies` | **4** | :white_check_mark: Active |
| AncVisit | `anc_visits` | **3** | :white_check_mark: Active |
| AncInvestigation | `anc_investigations` | **0** | :white_check_mark: Active |
| DeliveryRecord | `delivery_records` | **4** | :white_check_mark: Active |
| DeliveryPartograph | `delivery_partograph` | **1** | :white_check_mark: Active |
| MaternityPartograph | `maternity_partograph` | **5** | :white_check_mark: Active |
| MaternityBaby | `maternity_babies` | **5** | :white_check_mark: Active |
| ChildGrowthRecord | `child_growth_records` | **8** | :white_check_mark: Active |
| PostnatalVisit | `postnatal_visits` | **3** | :white_check_mark: Active |
| WhoGrowthStandard | `who_growth_standards` | **488** | :white_check_mark: Active |
| DeathRecord | `death_records` | **3** | :white_check_mark: Active |
| TreatmentPlan | `treatment_plans` | **0** | :white_check_mark: Active |
| MorgueAdmission | `morgue_admissions` | **2** | :white_check_mark: Active |
| ProcedureItem | `procedure_items` | **13** | :white_check_mark: Active |
| ProcedureTeamMember | `procedure_team_members` | **6** | :white_check_mark: Active |
| ProcedureNote | `procedure_notes` | **3** | :white_check_mark: Active |
| ProcedureAttachment | `procedure_attachments` | **2** | :white_check_mark: Active |
| PurchaseOrder | `purchase_orders` | **2** | :white_check_mark: Active |
| StoreRequisition | `store_requisitions` | **7** | :white_check_mark: Active |

## Detailed Model Specifications

### Encounter (`encounters`)
- **Class**: `App\Models\Encounter`
- **Row Count**: 15 rows
- **Columns**:
  `id`, `doctor_id`, `service_request_id`, `service_id`, `patient_id`, `queue_id`, `admission_request_id`, `reasons_for_encounter`, `reasons_for_encounter_comment_2`, `reasons_for_encounter_comment_1`, `notes`, `created_at`, `updated_at`, `deleted_at`, `old_medical_report_id`, `old_pharmacy`, `old_patient_lab_services`, `completed`, `outcome`, `started_at`, `completed_at`, `deleted_by`, `deletion_reason`

#### Sample Record Structure:
```json
{
    "id": 1,
    "doctor_id": 1,
    "service_request_id": 8,
    "service_id": 278,
    "patient_id": 1,
    "queue_id": null,
    "admission_request_id": null,
    "reasons_for_encounter": null,
    "reasons_for_encounter_comment_2": null,
    "reasons_for_encounter_comment_1": null,
    "notes": null,
    "created_at": "2026-02-09 07:34:00",
    "updated_at": "2026-02-13 10:05:52",
    "deleted_at": null,
    "old_medical_report_id": null,
    "old_pharmacy": null,
    "old_patient_lab_services": null,
    "completed": 1,
    "outcome": "discharged",
    "started_at": null,
    "completed_at": null,
    "deleted_by": null,
    "deletion_reason": null
}
```

---

### AdmissionRequest (`admission_requests`)
- **Class**: `App\Models\AdmissionRequest`
- **Row Count**: 11 rows
- **Columns**:
  `id`, `service_request_id`, `billed_by`, `billed_date`, `service_id`, `encounter_id`, `patient_id`, `bed_id`, `preferred_ward_id`, `priority`, `esi_level`, `chief_complaint`, `bed_assign_date`, `bed_assigned_by`, `discharged`, `discharge_date`, `discharge_reason`, `discharge_note`, `followup_instructions`, `discharged_by`, `doctor_id`, `note`, `admission_reason`, `status`, `admission_status`, `created_at`, `updated_at`, `death_record_id`

#### Sample Record Structure:
```json
{
    "id": 1,
    "service_request_id": 31,
    "billed_by": 12,
    "billed_date": "2023-07-24 07:58:37",
    "service_id": 3,
    "encounter_id": 9,
    "patient_id": 1,
    "bed_id": 7,
    "preferred_ward_id": null,
    "priority": "routine",
    "esi_level": null,
    "chief_complaint": null,
    "bed_assign_date": "2023-07-24 00:20:38",
    "bed_assigned_by": 12,
    "discharged": 1,
    "discharge_date": "2023-07-24 00:44:49",
    "discharge_reason": null,
    "discharge_note": null,
    "followup_instructions": null,
    "discharged_by": 12,
    "doctor_id": 12,
    "note": "admission note 1",
    "admission_reason": null,
    "status": 1,
    "admission_status": "admitted",
    "created_at": "2023-07-07 21:46:58",
    "updated_at": "2023-07-24 07:58:37",
    "death_record_id": null
}
```

---

### Clinic (`clinics`)
- **Class**: `App\Models\Clinic`
- **Row Count**: 32 rows
- **Columns**:
  `id`, `name`, `status`, `created_at`, `updated_at`, `template`, `old_clinic_id`, `vitals_template`

#### Sample Record Structure:
```json
{
    "id": 1,
    "name": "General",
    "status": 1,
    "created_at": null,
    "updated_at": "2026-05-03 16:20:51",
    "template": "<tr>\r\n    <td contenteditable=\"false\">Biodata(Name, Sex, Age,etc)</td>\r\n    <td contenteditable=\"false\"><span\r\n            style=\"border:1px solid black; min-width: 300px; min-height: 100px; display:inline-block;\"\r\n            contenteditable=\"true\"></span></td>\r\n</tr>\r\n<div contenteditable=\"true\" style=\"border:1px solid black;min-height:200px;min-width:100%\">\r\n\r\n</div>",
    "old_clinic_id": 1,
    "vitals_template": "[{\"name\": \"bloodPressure\", \"type\": \"text\", \"unit\": \"mmHg\", \"label\": \"Blood Pressure\", \"required\": false}, {\"name\": \"bodyTemperature\", \"type\": \"number\", \"unit\": \"\u00b0C\", \"label\": \"Temperature\", \"required\": false}, {\"name\": \"heartRate\", \"type\": \"number\", \"unit\": \"bpm\", \"label\": \"Heart Rate\", \"required\": false}, {\"name\": \"respiratoryRate\", \"type\": \"number\", \"unit\": \"bpm\", \"label\": \"Respiratory Rate\", \"required\": false}, {\"name\": \"spo2\", \"type\": \"number\", \"unit\": \"%\", \"label\": \"SpO2 (Oxygen)\", \"required\": false}, {\"name\": \"bodyWeight\", \"type\": \"number\", \"unit\": \"kg\", \"label\": \"Weight\", \"required\": false}, {\"name\": \"height\", \"type\": \"number\", \"unit\": \"cm\", \"label\": \"Height\", \"required\": false}, {\"name\": \"bloodSugar\", \"type\": \"number\", \"unit\": \"mg/dL\", \"label\": \"Blood Sugar\", \"required\": false}, {\"name\": \"painScore\", \"type\": \"select\", \"label\": \"Pain Score\", \"options\": [\"1\", \"2\", \"3\", \"4\", \"5\", \"6\", \"7\", \"8\", \"9\", \"10\"], \"required\": false}, {\"name\": \"testValue\", \"type\": \"number\", \"unit\": \"cm\", \"label\": \"Test Value\", \"required\": false}]"
}
```

---

### DoctorQueue (`doctor_queues`)
- **Class**: `App\Models\DoctorQueue`
- **Row Count**: 17 rows
- **Columns**:
  `id`, `patient_id`, `clinic_id`, `staff_id`, `receptionist_id`, `request_entry_id`, `appointment_id`, `status`, `priority`, `source`, `triage_note`, `consultation_started_at`, `consultation_ended_at`, `consultation_paused_seconds`, `last_paused_at`, `last_resumed_at`, `is_paused`, `created_at`, `updated_at`, `vitals_taken`

#### Sample Record Structure:
```json
{
    "id": 1,
    "patient_id": 1,
    "clinic_id": 1,
    "staff_id": 1,
    "receptionist_id": 1,
    "request_entry_id": 8,
    "appointment_id": null,
    "status": 5,
    "priority": "routine",
    "source": "reception",
    "triage_note": null,
    "consultation_started_at": "2026-03-10 14:40:56",
    "consultation_ended_at": null,
    "consultation_paused_seconds": 0,
    "last_paused_at": null,
    "last_resumed_at": null,
    "is_paused": 0,
    "created_at": "2026-02-09 07:31:46",
    "updated_at": "2026-04-10 16:23:12",
    "vitals_taken": 1
}
```

---

### Hmo (`hmos`)
- **Class**: `App\Models\Hmo`
- **Row Count**: 16 rows
- **Columns**:
  `id`, `hmo_scheme_id`, `name`, `desc`, `discount`, `status`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "hmo_scheme_id": 1,
    "name": "Private",
    "desc": "Private/Self-paying patients",
    "discount": 0,
    "status": 1,
    "created_at": "2026-01-02 12:25:02",
    "updated_at": "2026-01-02 12:25:02"
}
```

---

### ImagingServiceRequest (`imaging_service_requests`)
- **Class**: `App\Models\ImagingServiceRequest`
- **Row Count**: 23 rows
- **Columns**:
  `id`, `service_request_id`, `billed_by`, `billed_date`, `self_perform_intent`, `service_id`, `encounter_id`, `patient_id`, `result`, `pending_result`, `result_data`, `pending_result_data`, `attachments`, `pending_attachments`, `result_date`, `result_by`, `doctor_id`, `note`, `status`, `priority`, `created_at`, `updated_at`, `deleted_at`, `deleted_by`, `deletion_reason`, `approved_by`, `approved_at`, `rejected_by`, `rejected_at`, `rejection_reason`

#### Sample Record Structure:
```json
{
    "id": 1,
    "service_request_id": 71,
    "billed_by": 1,
    "billed_date": "2026-01-03 17:40:11",
    "self_perform_intent": null,
    "service_id": 64,
    "encounter_id": null,
    "patient_id": 24,
    "result": "<p>test imaging result<br><br>hahahaha</p>",
    "pending_result": null,
    "result_data": null,
    "pending_result_data": null,
    "attachments": "[{\"name\":\"WhatsApp Image 2025-12-21 at 10.41.39 AM.jpeg\",\"path\":\"imaging_results\\/1767464229_69595d25d8bda.jpeg\",\"size\":30103,\"type\":\"jpeg\"},{\"name\":\"i_Medical-Test-Results_full.jpg\",\"path\":\"imaging_results\\/1767465024_695960405f9b7.jpg\",\"size\":52009,\"type\":\"jpg\"}]",
    "pending_attachments": null,
    "result_date": "2026-01-03 18:17:09",
    "result_by": 1,
    "doctor_id": 1,
    "note": "test",
    "status": 4,
    "priority": "routine",
    "created_at": "2026-01-03 17:40:11",
    "updated_at": "2026-01-03 18:30:24",
    "deleted_at": null,
    "deleted_by": null,
    "deletion_reason": null,
    "approved_by": null,
    "approved_at": null,
    "rejected_by": null,
    "rejected_at": null,
    "rejection_reason": null
}
```

---

### LabServiceRequest (`lab_service_requests`)
- **Class**: `App\Models\LabServiceRequest`
- **Row Count**: 27 rows
- **Columns**:
  `id`, `service_request_id`, `billed_by`, `billed_date`, `self_perform_intent`, `service_id`, `encounter_id`, `patient_id`, `result`, `pending_result`, `result_data`, `pending_result_data`, `attachments`, `pending_attachments`, `result_date`, `result_by`, `sample_taken`, `sample_date`, `sample_taken_by`, `lab_number`, `doctor_id`, `note`, `status`, `priority`, `created_at`, `updated_at`, `old_medical_report_id`, `old_patient_lab_services`, `deleted_at`, `deleted_by`, `deletion_reason`, `dismissed_at`, `dismissed_by`, `dismiss_reason`, `approved_by`, `approved_at`, `rejected_by`, `rejected_at`, `rejection_reason`

#### Sample Record Structure:
```json
{
    "id": 1,
    "service_request_id": 1,
    "billed_by": null,
    "billed_date": null,
    "self_perform_intent": null,
    "service_id": 39,
    "encounter_id": null,
    "patient_id": 1,
    "result": null,
    "pending_result": null,
    "result_data": null,
    "pending_result_data": null,
    "attachments": null,
    "pending_attachments": null,
    "result_date": null,
    "result_by": null,
    "sample_taken": 0,
    "sample_date": null,
    "sample_taken_by": null,
    "lab_number": null,
    "doctor_id": null,
    "note": null,
    "status": 1,
    "priority": "routine",
    "created_at": "2026-02-02 17:05:40",
    "updated_at": "2026-02-02 17:05:40",
    "old_medical_report_id": null,
    "old_patient_lab_services": null,
    "deleted_at": null,
    "deleted_by": null,
    "deletion_reason": null,
    "dismissed_at": null,
    "dismissed_by": null,
    "dismiss_reason": null,
    "approved_by": null,
    "approved_at": null,
    "rejected_by": null,
    "rejected_at": null,
    "rejection_reason": null
}
```

---

### NursingNote (`nursing_notes`)
- **Class**: `App\Models\NursingNote`
- **Row Count**: 13 rows
- **Columns**:
  `id`, `patient_id`, `created_by`, `updated_by`, `nursing_note_type_id`, `note`, `completed`, `status`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "patient_id": 1,
    "created_by": 1,
    "updated_by": null,
    "nursing_note_type_id": 5,
    "note": "<p>Patient found stable vaiadns sgsfnnsbss&nbsp;</p><p>&nbsp;</p><p>ckdahda</p>",
    "completed": 1,
    "status": 1,
    "created_at": "2026-02-17 11:24:55",
    "updated_at": "2026-02-17 11:24:55"
}
```

---

### NursingNoteType (`nursing_note_types`)
- **Class**: `App\Models\NursingNoteType`
- **Row Count**: 5 rows
- **Columns**:
  `id`, `name`, `template`, `status`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "name": "Observation Chart",
    "template": "<table class=\"table\">\n    <thead>\n        <tr>\n            <b>Observation Chart</b>\n        </tr>\n        <tr>\n            <th>Date</th>\n            <th>Time</th>\n            <th>Temp.</th>\n            <th>Pulse</th>\n            <th>Resp.</th>\n            <th>B.P</th>\n            <th>Sign</th>\n        </tr>\n    </thead>\n    <tbody>\n        <tr>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n        </tr>\n        <tr>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n        </tr>\n        <tr>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n        </tr>\n        <tr>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n        </tr>\n        <tr>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n        </tr>\n        <tr>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n        </tr>\n        <tr>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n        </tr>\n        <tr>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n        </tr>\n        <tr>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n        </tr>\n        <tr>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n        </tr>\n        <tr>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n        </tr>\n        <tr>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n        </tr>\n        <tr>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n        </tr>\n        <tr>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n        </tr>\n        <tr>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n            <td contenteditable=\"false\">\n                <span style=\"border:1px solid black; min-width: 90px; display:inline-block;\"\n                    contenteditable=\"true\"></span>\n            </td>\n        </tr>\n    </tbody>\n</table>",
    "status": 1,
    "created_at": "2022-08-29 08:51:21",
    "updated_at": null
}
```

---

### Patient (`patients`)
- **Class**: `App\Models\Patient`
- **Row Count**: 18 rows
- **Columns**:
  `id`, `user_id`, `file_no`, `insurance_scheme`, `hmo_id`, `is_deceased`, `date_of_death`, `hmo_no`, `gender`, `dob`, `blood_group`, `genotype`, `disability`, `address`, `phone_no`, `nationality`, `ethnicity`, `misc`, `allergies`, `medical_history`, `next_of_kin_name`, `created_at`, `updated_at`, `next_of_kin_phone`, `next_of_kin_address`, `old_patient_id`, `old_user_id`, `dhis_consult_enrollment_id`, `dhis_consult_tracker_id`

#### Sample Record Structure:
```json
{
    "id": 1,
    "user_id": 64684,
    "file_no": "0001",
    "insurance_scheme": null,
    "hmo_id": 8,
    "is_deceased": 0,
    "date_of_death": null,
    "hmo_no": "777767676",
    "gender": "Male",
    "dob": "1999-10-15",
    "blood_group": "B+",
    "genotype": "AA",
    "disability": 0,
    "address": "Elwazir Street,bosso\r\nVcm 105 Elwazir Estate",
    "phone_no": "07050737404",
    "nationality": "Nigerian",
    "ethnicity": "Igbo",
    "misc": null,
    "allergies": "[\"beans\",\"Nuts\",\"oil\",\"yuwyeuqw\"]",
    "medical_history": "Known champion",
    "next_of_kin_name": "Chin Timothy",
    "created_at": "2026-02-02 14:18:01",
    "updated_at": "2026-05-18 18:09:33",
    "next_of_kin_phone": "0987655353",
    "next_of_kin_address": "Jos North, NG",
    "old_patient_id": null,
    "old_user_id": null,
    "dhis_consult_enrollment_id": null,
    "dhis_consult_tracker_id": null
}
```

---

### ProductOrServiceRequest (`product_or_service_requests`)
- **Class**: `App\Models\ProductOrServiceRequest`
- **Row Count**: 198 rows
- **Columns**:
  `id`, `type`, `invoice_id`, `payment_id`, `hmo_remittance_id`, `user_id`, `patient_id`, `encounter_id`, `admission_request_id`, `parent_id`, `is_bundle_item`, `staff_user_id`, `created_by`, `removed_by`, `removed_at`, `order_date`, `dispensed_from_store_id`, `product_id`, `service_id`, `qty`, `packaging_id`, `packaging_qty`, `amount`, `discount`, `payable_amount`, `claims_amount`, `coverage_mode`, `hmo_id`, `validation_status`, `auth_code`, `validated_by`, `validated_at`, `validation_notes`, `reception_validated`, `reception_validated_by`, `reception_validated_at`, `reception_validation_notes`, `submitted_to_hmo_at`, `hmo_submission_batch`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "type": null,
    "invoice_id": null,
    "payment_id": 3,
    "hmo_remittance_id": null,
    "user_id": 64684,
    "patient_id": null,
    "encounter_id": null,
    "admission_request_id": null,
    "parent_id": null,
    "is_bundle_item": 0,
    "staff_user_id": 1,
    "created_by": null,
    "removed_by": null,
    "removed_at": null,
    "order_date": null,
    "dispensed_from_store_id": null,
    "product_id": null,
    "service_id": 39,
    "qty": 1,
    "packaging_id": null,
    "packaging_qty": null,
    "amount": "0.00",
    "discount": "0.00",
    "payable_amount": "7000.00",
    "claims_amount": "0.00",
    "coverage_mode": null,
    "hmo_id": null,
    "validation_status": null,
    "auth_code": null,
    "validated_by": null,
    "validated_at": null,
    "validation_notes": null,
    "reception_validated": 0,
    "reception_validated_by": null,
    "reception_validated_at": null,
    "reception_validation_notes": null,
    "submitted_to_hmo_at": null,
    "hmo_submission_batch": null,
    "created_at": "2026-02-02 17:05:40",
    "updated_at": "2026-02-02 17:10:26"
}
```

---

### ProductRequest (`product_requests`)
- **Class**: `App\Models\ProductRequest`
- **Row Count**: 54 rows
- **Columns**:
  `id`, `product_request_id`, `billed_by`, `dispensed_by`, `returned_by`, `dispense_date`, `returned_date`, `dispensed_from_store_id`, `billed_date`, `product_id`, `encounter_id`, `patient_id`, `doctor_id`, `dose`, `qty`, `packaging_id`, `packaging_qty`, `returned_qty`, `status`, `created_at`, `updated_at`, `old_medical_report_id`, `deleted_at`, `deleted_by`, `deletion_reason`, `dispensed_from_batch_id`, `original_product_id`, `adapted_from_product_id`, `original_qty`, `adaptation_note`, `is_adapted`, `adapted_by`, `adapted_at`, `qty_adjusted_from`, `qty_adjustment_reason`, `qty_adjusted_at`, `qty_adjusted_by`, `price_override`, `price_original`, `price_override_reason`, `price_override_by`, `price_override_at`, `refund_amount`, `return_reason`, `return_condition`, `damaged_by`, `damaged_date`, `damaged_qty`, `damage_reason`, `damage_type`, `approved_by`, `approved_at`, `approval_notes`

#### Sample Record Structure:
```json
{
    "id": 1,
    "product_request_id": 13,
    "billed_by": 1,
    "dispensed_by": 1,
    "returned_by": null,
    "dispense_date": "2026-02-09 10:27:12",
    "returned_date": null,
    "dispensed_from_store_id": 2,
    "billed_date": "2026-02-09 10:19:22",
    "product_id": 380,
    "encounter_id": null,
    "patient_id": 1,
    "doctor_id": null,
    "dose": null,
    "qty": 3,
    "packaging_id": null,
    "packaging_qty": null,
    "returned_qty": null,
    "status": 4,
    "created_at": "2026-02-02 17:05:40",
    "updated_at": "2026-02-10 14:36:30",
    "old_medical_report_id": null,
    "deleted_at": null,
    "deleted_by": null,
    "deletion_reason": null,
    "dispensed_from_batch_id": 18,
    "original_product_id": null,
    "adapted_from_product_id": 649,
    "original_qty": null,
    "adaptation_note": "Out of stock",
    "is_adapted": 0,
    "adapted_by": 1,
    "adapted_at": "2026-02-09 10:18:26",
    "qty_adjusted_from": 1,
    "qty_adjustment_reason": "bh",
    "qty_adjusted_at": "2026-02-09 10:19:05",
    "qty_adjusted_by": 1,
    "price_override": null,
    "price_original": null,
    "price_override_reason": null,
    "price_override_by": null,
    "price_override_at": null,
    "refund_amount": null,
    "return_reason": null,
    "return_condition": null,
    "damaged_by": null,
    "damaged_date": null,
    "damaged_qty": null,
    "damage_reason": null,
    "damage_type": null,
    "approved_by": null,
    "approved_at": null,
    "approval_notes": null
}
```

---

### ReasonForEncounter (`reason_for_encounters`)
- **Class**: `App\Models\ReasonForEncounter`
- **Row Count**: 12130 rows
- **Columns**:
  `id`, `code`, `name`, `category`, `sub_category`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "code": " A00",
    "name": "Cholera (ICD10: A00)",
    "category": "Unknown",
    "sub_category": "Unknown",
    "created_at": null,
    "updated_at": null
}
```

---

### Service (`services`)
- **Class**: `App\Models\Service`
- **Row Count**: 407 rows
- **Columns**:
  `id`, `user_id`, `category_id`, `service_name`, `service_code`, `status`, `is_combo`, `price_assign`, `created_at`, `updated_at`, `template`, `result_template_v2`, `old_lab_services_id`

#### Sample Record Structure:
```json
{
    "id": 1,
    "user_id": 1,
    "category_id": 3,
    "service_name": "Full Blood Count",
    "service_code": "LAB-FBC-001",
    "status": 1,
    "is_combo": 0,
    "price_assign": 0,
    "created_at": "2026-01-28 10:37:28",
    "updated_at": "2026-05-12 07:53:17",
    "template": null,
    "result_template_v2": null,
    "old_lab_services_id": null
}
```

---

### Staff (`staff`)
- **Class**: `App\Models\Staff`
- **Row Count**: 263 rows
- **Columns**:
  `id`, `employee_id`, `user_id`, `specialization_id`, `clinic_id`, `can_see_clinic_queues`, `gender`, `date_of_birth`, `home_address`, `phone_number`, `consultation_fee`, `is_unit_head`, `is_dept_head`, `status`, `date_hired`, `date_confirmed`, `confirmation_due_date`, `employment_type`, `employment_status`, `job_title`, `department_id`, `unit_id`, `cadre_id`, `grade_level_id`, `entry_grade_level_id`, `license_number`, `license_expiry_date`, `national_id_number`, `job_location`, `responsibility`, `marital_status`, `number_of_children`, `permanent_home_address`, `other_talents`, `retirement_date`, `max_service_date`, `last_promotion_date`, `next_promotion_due_date`, `last_medical_exam_date`, `next_medical_exam_due`, `salary_increment_date`, `bank_name`, `bank_account_number`, `bank_account_name`, `emergency_contact_name`, `emergency_contact_phone`, `emergency_contact_relationship`, `tax_id`, `pension_id`, `hr_notes`, `suspended_at`, `suspended_by`, `suspension_reason`, `suspension_end_date`, `created_at`, `updated_at`, `old_user_id`

#### Sample Record Structure:
```json
{
    "id": 1,
    "employee_id": "9900",
    "user_id": 1,
    "specialization_id": 1,
    "clinic_id": 1,
    "can_see_clinic_queues": "[5,6,8,24]",
    "gender": "Male",
    "date_of_birth": "2025-06-01",
    "home_address": "jos",
    "phone_number": "0810000008",
    "consultation_fee": 0,
    "is_unit_head": 1,
    "is_dept_head": 1,
    "status": 1,
    "date_hired": "2025-06-01",
    "date_confirmed": null,
    "confirmation_due_date": null,
    "employment_type": "full_time",
    "employment_status": "suspended",
    "job_title": "Boss Admin",
    "department_id": 14,
    "unit_id": 12,
    "cadre_id": 1,
    "grade_level_id": 2,
    "entry_grade_level_id": 1,
    "license_number": null,
    "license_expiry_date": null,
    "national_id_number": null,
    "job_location": null,
    "responsibility": null,
    "marital_status": null,
    "number_of_children": 0,
    "permanent_home_address": null,
    "other_talents": null,
    "retirement_date": null,
    "max_service_date": null,
    "last_promotion_date": null,
    "next_promotion_due_date": null,
    "last_medical_exam_date": null,
    "next_medical_exam_due": null,
    "salary_increment_date": null,
    "bank_name": "zenith bank",
    "bank_account_number": "220445688",
    "bank_account_name": "App Tech",
    "emergency_contact_name": "Chink",
    "emergency_contact_phone": "+2348188223228",
    "emergency_contact_relationship": "spouse",
    "tax_id": "8837338",
    "pension_id": "872183",
    "hr_notes": null,
    "suspended_at": "2026-01-25 22:55:28",
    "suspended_by": 1,
    "suspension_reason": "sdhasd: ajdgjad\nadada",
    "suspension_end_date": "2026-01-28",
    "created_at": null,
    "updated_at": "2026-04-19 12:50:21",
    "old_user_id": 58
}
```

---

### User (`users`)
- **Class**: `App\Models\User`
- **Row Count**: 210 rows
- **Columns**:
  `id`, `is_admin`, `email`, `filename`, `old_records`, `surname`, `firstname`, `othername`, `assignRole`, `assignPermission`, `email_verified_at`, `password`, `status`, `remember_token`, `created_at`, `updated_at`, `old_user_id`, `old_dependant_id`, `next_of_kin_name`, `next_of_kin_phone`, `next_of_kin_address`, `next_of_kin`

#### Sample Record Structure:
```json
{
    "id": 1,
    "is_admin": 21,
    "email": "sysadmin@mail.com",
    "filename": "1767369464-whatsapp image 2025-12-21 at 3.00.46 pm.jpeg",
    "old_records": "1698826162-corehealth-qr.pdf",
    "surname": "Admin",
    "firstname": "System",
    "othername": "ing",
    "assignRole": "1",
    "assignPermission": "0",
    "email_verified_at": null,
    "password": "$2y$10$RhJtrHJ1u6P5QWbPiwWEyuKzn4tp3c2Vb2FAIRxwA419uYhBSNP6G",
    "status": 1,
    "remember_token": null,
    "created_at": null,
    "updated_at": "2026-04-26 10:42:32",
    "old_user_id": 58,
    "old_dependant_id": null,
    "next_of_kin_name": null,
    "next_of_kin_phone": null,
    "next_of_kin_address": null,
    "next_of_kin": null
}
```

---

### Procedure (`procedures`)
- **Class**: `App\Models\Procedure`
- **Row Count**: 12 rows
- **Columns**:
  `id`, `service_id`, `procedure_definition_id`, `requested_by`, `patient_id`, `encounter_id`, `admission_request_id`, `product_or_service_request_id`, `requested_on`, `billed_by`, `billed_on`, `pre_notes`, `pre_notes_by`, `post_notes`, `post_notes_by`, `cancellation_reason`, `refund_amount`, `cancelled_at`, `cancelled_by`, `status`, `consent_status`, `consent_marked_by`, `consent_marked_at`, `consent_notes`, `procedure_status`, `priority`, `scheduled_date`, `scheduled_time`, `actual_start_time`, `actual_end_time`, `operating_room`, `outcome`, `outcome_notes`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "service_id": 67,
    "procedure_definition_id": 1,
    "requested_by": 1,
    "patient_id": 24,
    "encounter_id": 222,
    "admission_request_id": null,
    "product_or_service_request_id": null,
    "requested_on": "2026-01-20 16:28:37",
    "billed_by": null,
    "billed_on": null,
    "pre_notes": null,
    "pre_notes_by": null,
    "post_notes": null,
    "post_notes_by": null,
    "cancellation_reason": null,
    "refund_amount": null,
    "cancelled_at": null,
    "cancelled_by": null,
    "status": 1,
    "consent_status": null,
    "consent_marked_by": null,
    "consent_marked_at": null,
    "consent_notes": null,
    "procedure_status": "requested",
    "priority": "routine",
    "scheduled_date": null,
    "scheduled_time": null,
    "actual_start_time": null,
    "actual_end_time": null,
    "operating_room": null,
    "outcome": null,
    "outcome_notes": null,
    "created_at": "2026-01-20 16:28:37",
    "updated_at": "2026-01-20 16:28:37",
    "deleted_at": null
}
```

---

### Bed (`beds`)
- **Class**: `App\Models\Bed`
- **Row Count**: 2 rows
- **Columns**:
  `id`, `ward_id`, `service_id`, `name`, `ward`, `unit`, `price`, `status`, `bed_status`, `occupant_id`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 7,
    "ward_id": 1,
    "service_id": 286,
    "name": "bed specal 1",
    "ward": "special ward",
    "unit": "amenity",
    "price": 9500,
    "status": 1,
    "bed_status": "occupied",
    "occupant_id": 1,
    "created_at": "2023-07-24 06:50:52",
    "updated_at": "2026-02-09 07:56:16"
}
```

---

### Product (`products`)
- **Class**: `App\Models\Product`
- **Row Count**: 735 rows
- **Columns**:
  `id`, `user_id`, `category_id`, `product_type`, `base_unit_name`, `allow_decimal_qty`, `product_name`, `product_code`, `reorder_alert`, `has_have`, `has_piece`, `howmany_to`, `current_quantity`, `status`, `stock_assign`, `price_assign`, `promotion`, `created_at`, `updated_at`, `old_product_id`, `old_stock_id`

#### Sample Record Structure:
```json
{
    "id": 161,
    "user_id": 1,
    "category_id": 1,
    "product_type": "drug",
    "base_unit_name": "tablet",
    "allow_decimal_qty": 0,
    "product_name": "Paracetamol 500mg",
    "product_code": "PARA-500",
    "reorder_alert": "100",
    "has_have": null,
    "has_piece": null,
    "howmany_to": null,
    "current_quantity": "500",
    "status": 1,
    "stock_assign": 1,
    "price_assign": 0,
    "promotion": 0,
    "created_at": "2026-01-28 09:00:40",
    "updated_at": "2026-05-04 14:14:40",
    "old_product_id": null,
    "old_stock_id": null
}
```

---

### ServiceCategory (`service_categories`)
- **Class**: `App\Models\ServiceCategory`
- **Row Count**: 9 rows
- **Columns**:
  `id`, `category_name`, `category_code`, `category_description`, `status`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "category_name": "Consultation",
    "category_code": "CONS",
    "category_description": "Consultations",
    "status": 1,
    "created_at": "2023-03-11 12:27:03",
    "updated_at": "2023-03-11 12:32:52"
}
```

---

### ProductCategory (`product_categories`)
- **Class**: `App\Models\ProductCategory`
- **Row Count**: 170 rows
- **Columns**:
  `id`, `category_name`, `category_code`, `category_description`, `status`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "category_name": "Suspension",
    "category_code": "SUP",
    "category_description": "sus",
    "status": 1,
    "created_at": "2023-03-08 11:17:32",
    "updated_at": "2023-09-30 19:48:24"
}
```

---

### VitalSign (`vital_signs`)
- **Class**: `App\Models\VitalSign`
- **Row Count**: 7 rows
- **Columns**:
  `id`, `requested_by`, `taken_by`, `patient_id`, `blood_pressure`, `temp`, `heart_rate`, `resp_rate`, `weight`, `height`, `spo2`, `blood_sugar`, `bmi`, `pain_score`, `other_notes`, `form_data`, `time_taken`, `status`, `source`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "requested_by": null,
    "taken_by": 1,
    "patient_id": 1,
    "blood_pressure": "120/95",
    "temp": "36.7",
    "heart_rate": "70",
    "resp_rate": "19",
    "weight": "70",
    "height": "170.00",
    "spo2": "97.00",
    "blood_sugar": "70.00",
    "bmi": "24.20",
    "pain_score": 10,
    "other_notes": "dhagfhgahghda adad bna d",
    "form_data": null,
    "time_taken": "2026-02-17 10:37:00",
    "status": 1,
    "source": null,
    "created_at": "2026-02-17 10:45:37",
    "updated_at": "2026-02-17 10:45:59"
}
```

---

### MedicationSchedule (`medication_schedules`)
- **Class**: `App\Models\MedicationSchedule`
- **Row Count**: 53 rows
- **Columns**:
  `id`, `patient_id`, `product_or_service_request_id`, `product_id`, `drug_source`, `external_drug_name`, `scheduled_time`, `dose`, `route`, `created_by`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "patient_id": 1,
    "product_or_service_request_id": 19,
    "product_id": null,
    "drug_source": "pharmacy_dispensed",
    "external_drug_name": null,
    "scheduled_time": "2026-02-17 11:50:00",
    "dose": "500mg",
    "route": "Oral",
    "created_by": 1,
    "created_at": "2026-02-17 10:51:46",
    "updated_at": "2026-02-17 10:51:46",
    "deleted_at": null
}
```

---

### MedicationAdministration (`medication_administrations`)
- **Class**: `App\Models\MedicationAdministration`
- **Row Count**: 12 rows
- **Columns**:
  `id`, `patient_id`, `product_id`, `product_or_service_request_id`, `schedule_id`, `administered_at`, `dose`, `qty`, `route`, `comment`, `administered_by`, `drug_source`, `product_request_id`, `external_drug_name`, `external_qty`, `external_batch_number`, `external_expiry_date`, `external_source_note`, `store_id`, `dispensed_from_batch_id`, `edited_by`, `edited_at`, `edit_reason`, `previous_data`, `deleted_at`, `deleted_by`, `delete_reason`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "patient_id": 1,
    "product_id": null,
    "product_or_service_request_id": 19,
    "schedule_id": 1,
    "administered_at": "2026-02-17 10:52:00",
    "dose": "500mg",
    "qty": "1.00",
    "route": "Oral",
    "comment": null,
    "administered_by": 1,
    "drug_source": "pharmacy_dispensed",
    "product_request_id": null,
    "external_drug_name": null,
    "external_qty": null,
    "external_batch_number": null,
    "external_expiry_date": null,
    "external_source_note": null,
    "store_id": 2,
    "dispensed_from_batch_id": 201,
    "edited_by": null,
    "edited_at": null,
    "edit_reason": null,
    "previous_data": null,
    "deleted_at": null,
    "deleted_by": null,
    "delete_reason": null,
    "created_at": "2026-02-17 10:53:46",
    "updated_at": "2026-02-17 10:53:46"
}
```

---

### IntakeOutputPeriod (`intake_output_periods`)
- **Class**: `App\Models\IntakeOutputPeriod`
- **Row Count**: 3 rows
- **Columns**:
  `id`, `patient_id`, `type`, `started_at`, `ended_at`, `ended_by`, `nurse_id`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "patient_id": 1,
    "type": "fluid",
    "started_at": "2026-02-17 11:03:23",
    "ended_at": "2026-02-17 11:05:04",
    "ended_by": null,
    "nurse_id": 1,
    "created_at": "2026-02-17 11:03:23",
    "updated_at": "2026-02-17 11:05:04"
}
```

---

### IntakeOutputRecord (`intake_output_records`)
- **Class**: `App\Models\IntakeOutputRecord`
- **Row Count**: 4 rows
- **Columns**:
  `id`, `period_id`, `type`, `amount`, `description`, `recorded_at`, `edited_at`, `edited_by`, `edit_reason`, `deleted_at`, `deleted_by`, `delete_reason`, `nurse_id`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 2,
    "period_id": 1,
    "type": "intake",
    "amount": "500.00",
    "description": "Juice",
    "recorded_at": "2026-02-17 15:04:00",
    "edited_at": null,
    "edited_by": null,
    "edit_reason": null,
    "deleted_at": null,
    "deleted_by": null,
    "delete_reason": null,
    "nurse_id": 1,
    "created_at": "2026-02-17 11:04:46",
    "updated_at": "2026-02-17 11:04:46"
}
```

---

### InjectionAdministration (`injection_administrations`)
- **Class**: `App\Models\InjectionAdministration`
- **Row Count**: 10 rows
- **Columns**:
  `id`, `patient_id`, `product_id`, `product_or_service_request_id`, `dose`, `route`, `site`, `administered_at`, `administered_by`, `drug_source`, `product_request_id`, `external_drug_name`, `external_qty`, `external_batch_number`, `external_expiry_date`, `external_source_note`, `dispensed_from_store_id`, `notes`, `batch_number`, `expiry_date`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "patient_id": 24,
    "product_id": 58,
    "product_or_service_request_id": 91,
    "dose": "600mg",
    "route": "IM",
    "site": "Left Arm",
    "administered_at": "2026-01-09 18:47:00",
    "administered_by": 1,
    "drug_source": "pharmacy_dispensed",
    "product_request_id": null,
    "external_drug_name": null,
    "external_qty": null,
    "external_batch_number": null,
    "external_expiry_date": null,
    "external_source_note": null,
    "dispensed_from_store_id": null,
    "notes": null,
    "batch_number": null,
    "expiry_date": null,
    "created_at": "2026-01-09 18:47:58",
    "updated_at": "2026-01-09 18:47:58",
    "deleted_at": null
}
```

---

### ImmunizationRecord (`immunization_records`)
- **Class**: `App\Models\ImmunizationRecord`
- **Row Count**: 20 rows
- **Columns**:
  `id`, `patient_id`, `product_id`, `product_or_service_request_id`, `vaccine_name`, `dose_number`, `dose`, `route`, `site`, `administered_at`, `administered_by`, `dispensed_from_store_id`, `batch_number`, `manufacturer`, `expiry_date`, `next_due_date`, `adverse_reaction`, `notes`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "patient_id": 24,
    "product_id": 58,
    "product_or_service_request_id": 92,
    "vaccine_name": "BCG",
    "dose_number": 1,
    "dose": "BCG",
    "route": "Oral",
    "site": "Left Deltoid",
    "administered_at": "2026-01-09 21:02:00",
    "administered_by": 1,
    "dispensed_from_store_id": null,
    "batch_number": null,
    "manufacturer": null,
    "expiry_date": null,
    "next_due_date": null,
    "adverse_reaction": null,
    "notes": null,
    "created_at": "2026-01-09 20:09:27",
    "updated_at": "2026-01-09 20:09:27",
    "deleted_at": null
}
```

---

### VaccineScheduleTemplate (`vaccine_schedule_templates`)
- **Class**: `App\Models\VaccineScheduleTemplate`
- **Row Count**: 2 rows
- **Columns**:
  `id`, `name`, `description`, `is_default`, `is_active`, `country`, `created_by`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "name": "Nigeria NPI Schedule",
    "description": "Standard Nigeria National Programme on Immunization (NPI) vaccination schedule for children 0-9 months",
    "is_default": 1,
    "is_active": 1,
    "country": "Nigeria",
    "created_by": null,
    "created_at": "2026-01-09 19:29:59",
    "updated_at": "2026-01-09 19:29:59",
    "deleted_at": null
}
```

---

### VaccineScheduleItem (`vaccine_schedule_items`)
- **Class**: `App\Models\VaccineScheduleItem`
- **Row Count**: 32 rows
- **Columns**:
  `id`, `template_id`, `vaccine_name`, `vaccine_code`, `dose_number`, `dose_label`, `age_days`, `age_display`, `route`, `site`, `notes`, `sort_order`, `is_required`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "template_id": 1,
    "vaccine_name": "BCG",
    "vaccine_code": "BCG",
    "dose_number": 1,
    "dose_label": "BCG",
    "age_days": 0,
    "age_display": "At Birth",
    "route": "ID",
    "site": "Right Upper Arm",
    "notes": "Bacillus Calmette\u2013Gu\u00e9rin vaccine for tuberculosis protection",
    "sort_order": 1,
    "is_required": 1,
    "created_at": "2026-01-09 19:29:59",
    "updated_at": "2026-01-09 19:29:59"
}
```

---

### VaccineProductMapping (`vaccine_product_mappings`)
- **Class**: `App\Models\VaccineProductMapping`
- **Row Count**: 0 rows
- **Columns**:
  `id`, `vaccine_name`, `product_id`, `is_primary`, `is_active`, `created_at`, `updated_at`

#### Sample Record: *[No rows present in table]*

---

### PatientImmunizationSchedule (`patient_immunization_schedules`)
- **Class**: `App\Models\PatientImmunizationSchedule`
- **Row Count**: 218 rows
- **Columns**:
  `id`, `patient_id`, `schedule_item_id`, `due_date`, `administered_date`, `status`, `immunization_record_id`, `skip_reason`, `notes`, `updated_by`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "patient_id": 24,
    "schedule_item_id": 1,
    "due_date": "2020-08-09",
    "administered_date": "2026-01-09",
    "status": "administered",
    "immunization_record_id": 1,
    "skip_reason": null,
    "notes": null,
    "updated_by": 1,
    "created_at": "2026-01-09 19:37:38",
    "updated_at": "2026-01-09 20:09:27"
}
```

---

### HmoTariff (`hmo_tariffs`)
- **Class**: `App\Models\HmoTariff`
- **Row Count**: 11624 rows
- **Columns**:
  `id`, `hmo_id`, `product_id`, `service_id`, `claims_amount`, `payable_amount`, `coverage_mode`, `display_name`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "hmo_id": 1,
    "product_id": null,
    "service_id": 144,
    "claims_amount": "0.00",
    "payable_amount": "200.00",
    "coverage_mode": "primary",
    "display_name": null,
    "created_at": "2026-02-09 07:29:55",
    "updated_at": "2026-02-09 07:29:55"
}
```

---

### Store (`stores`)
- **Class**: `App\Models\Store`
- **Row Count**: 49 rows
- **Columns**:
  `id`, `store_name`, `location`, `status`, `created_at`, `updated_at`, `code`, `description`, `store_type`, `distribution_role`, `department_id`, `ward_id`, `parent_store_id`, `allows_direct_patient_dispense`, `requires_shift_context`, `is_default`, `is_immutable`, `manager_id`

#### Sample Record Structure:
```json
{
    "id": 1,
    "store_name": "Centeral",
    "location": "central",
    "status": 0,
    "created_at": "2023-09-30 19:49:15",
    "updated_at": "2026-04-22 11:27:57",
    "code": null,
    "description": "Deactivated \u2014 superseded by Central Store (id=3).",
    "store_type": "pharmacy",
    "distribution_role": "other",
    "department_id": null,
    "ward_id": null,
    "parent_store_id": null,
    "allows_direct_patient_dispense": 1,
    "requires_shift_context": 0,
    "is_default": 0,
    "is_immutable": 0,
    "manager_id": null
}
```

---

### StoreStock (`store_stocks`)
- **Class**: `App\Models\StoreStock`
- **Row Count**: 2782 rows
- **Columns**:
  `id`, `store_id`, `product_id`, `initial_quantity`, `quantity_sale`, `order_quantity`, `current_quantity`, `created_at`, `updated_at`, `reserved_qty`, `reorder_level`, `max_stock_level`, `is_active`, `last_restocked_at`, `last_sold_at`

#### Sample Record Structure:
```json
{
    "id": 16,
    "store_id": 1,
    "product_id": 161,
    "initial_quantity": 500,
    "quantity_sale": 0,
    "order_quantity": 0,
    "current_quantity": 500,
    "created_at": "2026-01-28 09:00:40",
    "updated_at": "2026-02-10 17:47:22",
    "reserved_qty": 0,
    "reorder_level": 100,
    "max_stock_level": null,
    "is_active": 1,
    "last_restocked_at": "2026-02-10 17:47:22",
    "last_sold_at": null
}
```

---

### StockBatch (`stock_batches`)
- **Class**: `App\Models\StockBatch`
- **Row Count**: 2835 rows
- **Columns**:
  `id`, `product_id`, `store_id`, `supplier_id`, `batch_name`, `batch_number`, `initial_qty`, `current_qty`, `sold_qty`, `cost_price`, `expiry_date`, `received_date`, `source`, `purchase_order_item_id`, `source_requisition_id`, `created_by`, `is_active`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "product_id": 2,
    "store_id": 1,
    "supplier_id": null,
    "batch_name": "Legacy Stock - Centeral",
    "batch_number": "LEGACY-STR-2",
    "initial_qty": 158,
    "current_qty": 137,
    "sold_qty": 21,
    "cost_price": "0.00",
    "expiry_date": null,
    "received_date": "2026-01-21",
    "source": "manual",
    "purchase_order_item_id": null,
    "source_requisition_id": null,
    "created_by": 1,
    "is_active": 1,
    "created_at": "2026-01-21 22:55:13",
    "updated_at": "2026-01-26 10:52:00",
    "deleted_at": null
}
```

---

### StoreContextRule (`store_context_rules`)
- **Class**: `App\Models\StoreContextRule`
- **Row Count**: 3 rows
- **Columns**:
  `id`, `rule_type`, `user_role`, `department_id`, `store_id`, `type_filter`, `fallback_action`, `notes`, `updated_by`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "rule_type": "fallback_behavior",
    "user_role": null,
    "department_id": null,
    "store_id": null,
    "type_filter": null,
    "fallback_action": "block",
    "notes": "Default: block all stock actions when store context cannot be resolved.",
    "updated_by": null,
    "created_at": "2026-04-22 10:56:56",
    "updated_at": "2026-04-22 10:56:56"
}
```

---

### MaternityEnrollment (`maternity_enrollments`)
- **Class**: `App\Models\MaternityEnrollment`
- **Row Count**: 4 rows
- **Columns**:
  `id`, `patient_id`, `enrolled_by`, `service_request_id`, `enrollment_date`, `booking_date`, `entry_point`, `lmp`, `edd`, `gestational_age_at_booking`, `gravida`, `parity`, `alive`, `abortion_miscarriage`, `blood_group`, `genotype`, `height_cm`, `booking_weight_kg`, `booking_bmi`, `booking_bp`, `pelvis_assessment`, `nipple_assessment`, `general_condition`, `risk_level`, `risk_factors`, `birth_plan_notes`, `preferred_delivery_place`, `ante_natal_records`, `status`, `completed_at`, `outcome_summary`, `notes`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record Structure:
```json
{
    "id": 3,
    "patient_id": 1,
    "enrolled_by": 1,
    "service_request_id": null,
    "enrollment_date": "2026-02-25",
    "booking_date": "2026-02-25",
    "entry_point": "anc",
    "lmp": "2026-02-09",
    "edd": "2026-11-16",
    "gestational_age_at_booking": 2,
    "gravida": 4,
    "parity": 0,
    "alive": 0,
    "abortion_miscarriage": 2,
    "blood_group": "B+",
    "genotype": "AA",
    "height_cm": null,
    "booking_weight_kg": null,
    "booking_bmi": null,
    "booking_bp": null,
    "pelvis_assessment": null,
    "nipple_assessment": null,
    "general_condition": null,
    "risk_level": "low",
    "risk_factors": null,
    "birth_plan_notes": null,
    "preferred_delivery_place": null,
    "ante_natal_records": "booked",
    "status": "postnatal",
    "completed_at": null,
    "outcome_summary": null,
    "notes": null,
    "created_at": "2026-02-25 17:22:06",
    "updated_at": "2026-03-10 05:26:38",
    "deleted_at": null
}
```

---

### MaternityMedicalHistory (`maternity_medical_history`)
- **Class**: `App\Models\MaternityMedicalHistory`
- **Row Count**: 5 rows
- **Columns**:
  `id`, `enrollment_id`, `category`, `description`, `year`, `notes`, `created_by`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "enrollment_id": 3,
    "category": "medical",
    "description": "shahgdahgd",
    "year": "2024",
    "notes": "khkhhwrwr",
    "created_by": null,
    "created_at": "2026-02-25 19:01:52",
    "updated_at": "2026-02-25 19:01:52",
    "deleted_at": null
}
```

---

### MaternityPreviousPregnancy (`maternity_previous_pregnancies`)
- **Class**: `App\Models\MaternityPreviousPregnancy`
- **Row Count**: 4 rows
- **Columns**:
  `id`, `enrollment_id`, `year`, `place_of_delivery`, `duration_weeks`, `complications`, `type_of_labour`, `baby_alive`, `baby_dead`, `baby_stillbirth`, `baby_sex`, `duration_of_pregnancy`, `ante_natal_complications`, `labour_notes`, `baby_alive_or_dead`, `sex`, `birth_weight_kg`, `present_health`, `notes`, `age_at_death`, `sort_order`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record Structure:
```json
{
    "id": 3,
    "enrollment_id": 3,
    "year": "2025",
    "place_of_delivery": "dklk;ad",
    "duration_weeks": 7,
    "complications": "222",
    "type_of_labour": null,
    "baby_alive": 1,
    "baby_dead": 0,
    "baby_stillbirth": 0,
    "baby_sex": "male",
    "duration_of_pregnancy": null,
    "ante_natal_complications": null,
    "labour_notes": null,
    "baby_alive_or_dead": null,
    "sex": null,
    "birth_weight_kg": "22.00",
    "present_health": null,
    "notes": "2222",
    "age_at_death": null,
    "sort_order": 0,
    "created_at": "2026-02-26 02:47:27",
    "updated_at": "2026-02-26 02:47:27",
    "deleted_at": null
}
```

---

### AncVisit (`anc_visits`)
- **Class**: `App\Models\AncVisit`
- **Row Count**: 3 rows
- **Columns**:
  `id`, `enrollment_id`, `patient_id`, `encounter_id`, `visit_number`, `visit_type`, `visit_date`, `gestational_age_weeks`, `gestational_age_days`, `weight_kg`, `blood_pressure_systolic`, `blood_pressure_diastolic`, `fundal_height_cm`, `presentation`, `fetal_heart_rate`, `blood_pressure`, `height_of_fundus`, `presentation_and_position`, `foetal_heart_rate`, `foetal_movement`, `oedema`, `urine_protein`, `urine_glucose`, `haemoglobin`, `clinical_notes`, `complaints`, `examination_notes`, `diagnosis`, `treatment`, `plan`, `next_appointment`, `seen_by`, `notes`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record Structure:
```json
{
    "id": 2,
    "enrollment_id": 3,
    "patient_id": 1,
    "encounter_id": null,
    "visit_number": 1,
    "visit_type": "booking",
    "visit_date": "2026-02-26",
    "gestational_age_weeks": 2,
    "gestational_age_days": 0,
    "weight_kg": "77.00",
    "blood_pressure_systolic": 120,
    "blood_pressure_diastolic": 89,
    "fundal_height_cm": "7.0",
    "presentation": "Breech",
    "fetal_heart_rate": "60",
    "blood_pressure": null,
    "height_of_fundus": null,
    "presentation_and_position": null,
    "foetal_heart_rate": null,
    "foetal_movement": null,
    "oedema": "mild",
    "urine_protein": null,
    "urine_glucose": null,
    "haemoglobin": null,
    "clinical_notes": "<p>ygtgjhgh</p>",
    "complaints": null,
    "examination_notes": null,
    "diagnosis": null,
    "treatment": null,
    "plan": null,
    "next_appointment": "2026-02-28",
    "seen_by": 1,
    "notes": null,
    "created_at": "2026-02-26 02:53:22",
    "updated_at": "2026-02-27 19:33:47",
    "deleted_at": null
}
```

---

### AncInvestigation (`anc_investigations`)
- **Class**: `App\Models\AncInvestigation`
- **Row Count**: 0 rows
- **Columns**:
  `id`, `enrollment_id`, `anc_visit_id`, `investigation_type`, `lab_service_request_id`, `imaging_service_request_id`, `investigation_name`, `result_summary`, `gestational_age_weeks`, `is_routine`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record: *[No rows present in table]*

---

### DeliveryRecord (`delivery_records`)
- **Class**: `App\Models\DeliveryRecord`
- **Row Count**: 4 rows
- **Columns**:
  `id`, `enrollment_id`, `patient_id`, `encounter_id`, `delivery_date`, `delivery_time`, `duration_of_labour_hours`, `place_of_delivery`, `type_of_delivery`, `episiotomy`, `induction`, `induction_method`, `augmentation`, `complications`, `blood_loss_ml`, `placenta_complete`, `placenta_notes`, `perineal_tear_degree`, `oxytocin_given`, `number_of_babies`, `delivered_by`, `anaesthesia_type`, `notes`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "enrollment_id": 3,
    "patient_id": 1,
    "encounter_id": null,
    "delivery_date": "2026-02-26 06:58:27",
    "delivery_time": "07:57:00",
    "duration_of_labour_hours": "7.0",
    "place_of_delivery": null,
    "type_of_delivery": "svd",
    "episiotomy": "none",
    "induction": 0,
    "induction_method": null,
    "augmentation": 0,
    "complications": "<p>hgghg</p>",
    "blood_loss_ml": 200,
    "placenta_complete": 1,
    "placenta_notes": null,
    "perineal_tear_degree": null,
    "oxytocin_given": 1,
    "number_of_babies": 2,
    "delivered_by": 1,
    "anaesthesia_type": null,
    "notes": "<p>ggkgkg</p>",
    "created_at": "2026-02-26 06:58:27",
    "updated_at": "2026-02-26 06:58:27",
    "deleted_at": null
}
```

---

### DeliveryPartograph (`delivery_partograph`)
- **Class**: `App\Models\DeliveryPartograph`
- **Row Count**: 1 rows
- **Columns**:
  `id`, `delivery_record_id`, `recorded_at`, `cervical_dilation_cm`, `descent_of_head`, `contractions_per_10_min`, `contraction_duration_sec`, `foetal_heart_rate`, `amniotic_fluid`, `moulding`, `maternal_bp`, `maternal_pulse`, `maternal_temp`, `urine_output_ml`, `urine_protein`, `oxytocin_dose`, `iv_fluids`, `medications`, `recorded_by`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "delivery_record_id": 1,
    "recorded_at": "2026-02-27 20:34:00",
    "cervical_dilation_cm": "6.0",
    "descent_of_head": "3",
    "contractions_per_10_min": 4,
    "contraction_duration_sec": 10,
    "foetal_heart_rate": "130",
    "amniotic_fluid": "intact",
    "moulding": "+",
    "maternal_bp": "120/89",
    "maternal_pulse": 60,
    "maternal_temp": "36.7",
    "urine_output_ml": 33,
    "urine_protein": "+",
    "oxytocin_dose": "10",
    "iv_fluids": "2w12w1",
    "medications": "ajkdasdasdaa\r\n\r\nadhadhad",
    "recorded_by": 1,
    "created_at": "2026-02-27 19:36:11",
    "updated_at": "2026-02-27 19:36:11"
}
```

---

### MaternityPartograph (`maternity_partograph`)
- **Class**: `App\Models\MaternityPartograph`
- **Row Count**: 5 rows
- **Columns**:
  `id`, `enrollment_id`, `delivery_record_id`, `phase`, `recorded_at`, `cervical_dilation_cm`, `descent_of_head`, `contractions_per_10_min`, `contraction_duration_sec`, `foetal_heart_rate`, `amniotic_fluid`, `moulding`, `maternal_bp`, `maternal_pulse`, `maternal_temp`, `urine_output_ml`, `urine_protein`, `oxytocin_dose`, `iv_fluids`, `medications`, `recorded_by`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "enrollment_id": 5,
    "delivery_record_id": null,
    "phase": "pre_delivery",
    "recorded_at": "2026-05-08 11:38:00",
    "cervical_dilation_cm": "4.0",
    "descent_of_head": "2/5",
    "contractions_per_10_min": 3,
    "contraction_duration_sec": 18,
    "foetal_heart_rate": "140",
    "amniotic_fluid": "intact",
    "moulding": "none",
    "maternal_bp": "89/12",
    "maternal_pulse": 120,
    "maternal_temp": "37.0",
    "urine_output_ml": null,
    "urine_protein": null,
    "oxytocin_dose": null,
    "iv_fluids": null,
    "medications": null,
    "recorded_by": 1,
    "created_at": "2026-05-08 10:39:28",
    "updated_at": "2026-05-08 10:39:28"
}
```

---

### MaternityBaby (`maternity_babies`)
- **Class**: `App\Models\MaternityBaby`
- **Row Count**: 5 rows
- **Columns**:
  `id`, `enrollment_id`, `patient_id`, `birth_order`, `sex`, `is_still_birth`, `birth_weight_kg`, `length_cm`, `head_circumference_cm`, `chest_circumference_cm`, `apgar_1_min`, `apgar_5_min`, `apgar_10_min`, `resuscitation`, `resuscitation_details`, `birth_defects`, `feeding_method`, `bcg_given`, `opv0_given`, `hbv0_given`, `vitamin_k_given`, `eye_prophylaxis`, `date_first_seen`, `reasons_for_special_care`, `status`, `deceased_at`, `cause_of_death`, `notes`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "enrollment_id": 3,
    "patient_id": 4,
    "birth_order": 1,
    "sex": "male",
    "is_still_birth": 0,
    "birth_weight_kg": "3.100",
    "length_cm": "52.0",
    "head_circumference_cm": "35.0",
    "chest_circumference_cm": null,
    "apgar_1_min": 4,
    "apgar_5_min": 6,
    "apgar_10_min": 7,
    "resuscitation": 0,
    "resuscitation_details": null,
    "birth_defects": null,
    "feeding_method": "exclusive_breastfeeding",
    "bcg_given": 1,
    "opv0_given": 1,
    "hbv0_given": 0,
    "vitamin_k_given": 0,
    "eye_prophylaxis": 0,
    "date_first_seen": "2026-02-26",
    "reasons_for_special_care": null,
    "status": "alive",
    "deceased_at": null,
    "cause_of_death": null,
    "notes": null,
    "created_at": "2026-02-26 07:00:22",
    "updated_at": "2026-02-26 07:00:22",
    "deleted_at": null
}
```

---

### ChildGrowthRecord (`child_growth_records`)
- **Class**: `App\Models\ChildGrowthRecord`
- **Row Count**: 8 rows
- **Columns**:
  `id`, `baby_id`, `patient_id`, `record_date`, `age_months`, `weight_kg`, `length_height_cm`, `head_circumference_cm`, `muac_cm`, `weight_for_age_z`, `length_for_age_z`, `weight_for_length_z`, `bmi_for_age_z`, `nutritional_status`, `milestones`, `feeding_method`, `dietary_notes`, `notes`, `recorded_by`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "baby_id": 1,
    "patient_id": 4,
    "record_date": "2026-02-26",
    "age_months": "0.0",
    "weight_kg": "3.10",
    "length_height_cm": "52.0",
    "head_circumference_cm": "35.0",
    "muac_cm": null,
    "weight_for_age_z": "-0.52",
    "length_for_age_z": "1.12",
    "weight_for_length_z": null,
    "bmi_for_age_z": null,
    "nutritional_status": "normal",
    "milestones": null,
    "feeding_method": null,
    "dietary_notes": null,
    "notes": null,
    "recorded_by": 1,
    "created_at": "2026-02-26 07:00:22",
    "updated_at": "2026-02-26 07:00:22",
    "deleted_at": null
}
```

---

### PostnatalVisit (`postnatal_visits`)
- **Class**: `App\Models\PostnatalVisit`
- **Row Count**: 3 rows
- **Columns**:
  `id`, `enrollment_id`, `patient_id`, `encounter_id`, `visit_type`, `visit_date`, `days_postpartum`, `general_condition`, `blood_pressure`, `temperature_c`, `uterus_assessment`, `lochia`, `wound_assessment`, `breast_assessment`, `breastfeeding_support`, `emotional_wellbeing`, `emotional_notes`, `baby_weight_kg`, `baby_feeding`, `cord_status`, `jaundice`, `baby_general_condition`, `baby_notes`, `family_planning_counselled`, `family_planning_method`, `clinical_notes`, `next_appointment`, `seen_by`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "enrollment_id": 3,
    "patient_id": 1,
    "encounter_id": null,
    "visit_type": "within_24h",
    "visit_date": "2026-03-09",
    "days_postpartum": 11,
    "general_condition": "Good",
    "blood_pressure": "120/88",
    "temperature_c": null,
    "uterus_assessment": null,
    "lochia": "normal",
    "wound_assessment": null,
    "breast_assessment": null,
    "breastfeeding_support": null,
    "emotional_wellbeing": null,
    "emotional_notes": null,
    "baby_weight_kg": null,
    "baby_feeding": null,
    "cord_status": null,
    "jaundice": 0,
    "baby_general_condition": null,
    "baby_notes": null,
    "family_planning_counselled": 1,
    "family_planning_method": null,
    "clinical_notes": "<p>sdhsadgasduasyd</p>",
    "next_appointment": null,
    "seen_by": 1,
    "created_at": "2026-03-09 21:34:20",
    "updated_at": "2026-03-09 21:34:35",
    "deleted_at": null
}
```

---

### WhoGrowthStandard (`who_growth_standards`)
- **Class**: `App\Models\WhoGrowthStandard`
- **Row Count**: 488 rows
- **Columns**:
  `id`, `indicator`, `sex`, `age_months`, `l_value`, `m_value`, `s_value`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "indicator": "wfa",
    "sex": "M",
    "age_months": "0.0",
    "l_value": "0.3487",
    "m_value": "3.3464",
    "s_value": "0.14602",
    "created_at": "2026-02-25 16:43:17",
    "updated_at": "2026-02-25 16:43:17"
}
```

---

### DeathRecord (`death_records`)
- **Class**: `App\Models\DeathRecord`
- **Row Count**: 3 rows
- **Columns**:
  `id`, `patient_id`, `encounter_id`, `admission_request_id`, `death_type`, `date_of_death`, `time_of_death`, `cause_of_death_primary`, `cause_of_death_description`, `certified_by_doctor_id`, `last_office_done`, `last_office_by_nurse_id`, `last_office_at`, `disposition`, `disposition_note`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "patient_id": 11,
    "encounter_id": null,
    "admission_request_id": null,
    "death_type": "Still Birth",
    "date_of_death": "2026-04-26",
    "time_of_death": "20:16:52",
    "cause_of_death_primary": "Still Birth",
    "cause_of_death_description": "Recorded during baby registration",
    "certified_by_doctor_id": 1,
    "last_office_done": 0,
    "last_office_by_nurse_id": null,
    "last_office_at": null,
    "disposition": "pending",
    "disposition_note": null,
    "created_at": "2026-04-26 20:16:52",
    "updated_at": "2026-04-26 20:16:52",
    "deleted_at": null
}
```

---

### TreatmentPlan (`treatment_plans`)
- **Class**: `App\Models\TreatmentPlan`
- **Row Count**: 0 rows
- **Columns**:
  `id`, `name`, `description`, `specialty`, `created_by`, `is_global`, `status`, `created_at`, `updated_at`

#### Sample Record: *[No rows present in table]*

---

### MorgueAdmission (`morgue_admissions`)
- **Class**: `App\Models\MorgueAdmission`
- **Row Count**: 2 rows
- **Columns**:
  `id`, `death_record_id`, `patient_id`, `body_code`, `fridge_number`, `tray_number`, `daily_service_id`, `current_service_request_id`, `admitted_by_staff_id`, `arrival_time`, `release_time`, `released_by_staff_id`, `released_to_name`, `released_to_id_type`, `released_to_id_no`, `status`, `notes`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "death_record_id": 2,
    "patient_id": 9,
    "body_code": "MORG-2026-0001",
    "fridge_number": "f002",
    "tray_number": "6006",
    "daily_service_id": 12,
    "current_service_request_id": 116,
    "admitted_by_staff_id": 1,
    "arrival_time": "2026-04-26 20:34:58",
    "release_time": null,
    "released_by_staff_id": null,
    "released_to_name": null,
    "released_to_id_type": null,
    "released_to_id_no": null,
    "status": "stored",
    "notes": "mmm",
    "created_at": "2026-04-26 20:34:58",
    "updated_at": "2026-04-26 20:34:58",
    "deleted_at": null
}
```

---

### ProcedureItem (`procedure_items`)
- **Class**: `App\Models\ProcedureItem`
- **Row Count**: 13 rows
- **Columns**:
  `id`, `procedure_id`, `lab_service_request_id`, `imaging_service_request_id`, `product_request_id`, `misc_bill_id`, `product_or_service_request_id`, `is_bundled`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "procedure_id": 5,
    "lab_service_request_id": null,
    "imaging_service_request_id": null,
    "product_request_id": 109,
    "misc_bill_id": null,
    "product_or_service_request_id": null,
    "is_bundled": 1,
    "created_at": "2026-01-21 09:59:59",
    "updated_at": "2026-01-21 09:59:59"
}
```

---

### ProcedureTeamMember (`procedure_team_members`)
- **Class**: `App\Models\ProcedureTeamMember`
- **Row Count**: 6 rows
- **Columns**:
  `id`, `procedure_id`, `user_id`, `role`, `custom_role`, `is_lead`, `notes`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "procedure_id": 8,
    "user_id": 2,
    "role": "scrub_nurse",
    "custom_role": null,
    "is_lead": 0,
    "notes": null,
    "created_at": "2026-02-09 16:56:13",
    "updated_at": "2026-02-09 16:56:13"
}
```

---

### ProcedureNote (`procedure_notes`)
- **Class**: `App\Models\ProcedureNote`
- **Row Count**: 3 rows
- **Columns**:
  `id`, `procedure_id`, `note_type`, `title`, `content`, `created_by`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "procedure_id": 8,
    "note_type": "pre_op",
    "title": "suadagd",
    "content": "<p>agdhadasd</p><p>dsadasdasa</p><p>&nbsp;</p><p>djasdaskj<strong>dhsghsdas</strong></p><p><i><strong>dhdhshc</strong></i></p>",
    "created_by": 1,
    "created_at": "2026-02-09 16:57:29",
    "updated_at": "2026-02-09 16:57:29"
}
```

---

### ProcedureAttachment (`procedure_attachments`)
- **Class**: `App\Models\ProcedureAttachment`
- **Row Count**: 2 rows
- **Columns**:
  `id`, `procedure_id`, `uploaded_by`, `file_path`, `original_name`, `file_size`, `mime_type`, `label`, `created_at`, `updated_at`

#### Sample Record Structure:
```json
{
    "id": 2,
    "procedure_id": 11,
    "uploaded_by": 1,
    "file_path": "procedure-attachments/11/b70a354c-871e-4374-91eb-07de63d50f6e.jpeg",
    "original_name": "WhatsApp Image 2026-04-25 at 10.26.34 PM.jpeg",
    "file_size": 50300,
    "mime_type": "image/jpeg",
    "label": "anaother test",
    "created_at": "2026-05-12 12:01:27",
    "updated_at": "2026-05-12 12:01:27"
}
```

---

### PurchaseOrder (`purchase_orders`)
- **Class**: `App\Models\PurchaseOrder`
- **Row Count**: 2 rows
- **Columns**:
  `id`, `po_number`, `supplier_id`, `target_store_id`, `created_by`, `approved_by`, `status`, `payment_status`, `expected_date`, `total_amount`, `amount_paid`, `notes`, `submitted_at`, `approved_at`, `journal_entry_id`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "po_number": "PO2026040001",
    "supplier_id": 1,
    "target_store_id": 3,
    "created_by": 1,
    "approved_by": 1,
    "status": "partial",
    "payment_status": "partial",
    "expected_date": "2026-05-01",
    "total_amount": "2635000.00",
    "amount_paid": "263500.00",
    "notes": "Receiving Notes: dadggDGGDH",
    "submitted_at": "2026-04-28 10:02:39",
    "approved_at": "2026-04-28 10:06:26",
    "journal_entry_id": 102,
    "created_at": "2026-04-28 10:02:39",
    "updated_at": "2026-04-28 10:12:45",
    "deleted_at": null
}
```

---

### StoreRequisition (`store_requisitions`)
- **Class**: `App\Models\StoreRequisition`
- **Row Count**: 7 rows
- **Columns**:
  `id`, `requisition_number`, `from_store_id`, `to_store_id`, `requested_by`, `approved_by`, `rejected_by`, `fulfilled_by`, `status`, `request_notes`, `approval_notes`, `rejection_reason`, `approved_at`, `rejected_at`, `fulfilled_at`, `created_at`, `updated_at`, `deleted_at`

#### Sample Record Structure:
```json
{
    "id": 1,
    "requisition_number": "REQ2026010001",
    "from_store_id": 1,
    "to_store_id": 4,
    "requested_by": 1,
    "approved_by": 1,
    "rejected_by": null,
    "fulfilled_by": null,
    "status": "partial",
    "request_notes": null,
    "approval_notes": "test",
    "rejection_reason": null,
    "approved_at": "2026-01-22 11:58:23",
    "rejected_at": null,
    "fulfilled_at": null,
    "created_at": "2026-01-22 11:45:19",
    "updated_at": "2026-01-22 12:13:55",
    "deleted_at": null
}
```

---

