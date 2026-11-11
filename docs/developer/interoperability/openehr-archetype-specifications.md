# openEHR Canonical Archetype Specifications & AQL Node Registry

> **Standard**: openEHR Specifications (Release 1.1.0+), openEHR CKM (Clinical Knowledge Manager)  
> **Source Grounding**: [openEHR CKM Knowledge Repository](https://ckm.openehr.org)  
> **Target Interoperability Facade**: CoreHealth v2 Translation & Compatibility Layer  
> **Verification Scope**: Comprehensive Clinical, Inpatient, Surgical, Obstetric & Pediatric Archetypes  

---

## 1. Overview & Two-Level Clinical Modeling

openEHR decouples information technology implementation from clinical knowledge definitions:
1. **Level 1 (Reference Model - RM)**: Stable information primitives (`COMPOSITION`, `SECTION`, `ENTRY`, `OBSERVATION`, `EVALUATION`, `INSTRUCTION`, `ACTION`, `ADMIN_ENTRY`).
2. **Level 2 (Archetypes & Templates)**: Clinically governed concepts with immutable node paths (`at0001`, `at0004`, etc.).

In CoreHealth v2, the **openEHR Compatibility Adapter** dynamically serializes native consultations, triage vitals, surgeries, deliveries, and child growth records into valid openEHR JSON representations without restructuring MySQL tables.

---

## 2. Comprehensive Archetype Mapping Registry

### 2.1 Blood Pressure (`openEHR-EHR-OBSERVATION.blood_pressure.v2`)
- **Maps from**: `App\Models\VitalSign` (`app/Models/VitalSign.php`) (`blood_pressure` column).

| Node ID | Archetype Element Name | Data Type | AQL Canonical Node Path | CoreHealth v2 Grounding |
|---|---|---|---|---|
| `at0001` | History | `HISTORY` | `/data[at0001]` | Root measurement timeline |
| `at0006` | Any event | `POINT_EVENT` | `/data[at0001]/events[at0006]` | Measurement event (`vital_signs.time_taken`) |
| `at0003` | Tree | `ITEM_TREE` | `/data[at0001]/events[at0006]/data[at0003]` | Data container |
| `at0004` | **Systolic** | `DV_QUANTITY` | `/data[at0001]/events[at0006]/data[at0003]/items[at0004]/value` | Parsed systolic (e.g. `120` `mmHg`) |
| `at0005` | **Diastolic** | `DV_QUANTITY` | `/data[at0001]/events[at0006]/data[at0003]/items[at0005]/value` | Parsed diastolic (e.g. `80` `mmHg`) |
| `at1006` | Mean Arterial Pressure | `DV_QUANTITY` | `/data[at0001]/events[at0006]/data[at0003]/items[at1006]/value` | Calculated: $\text{Diastolic} + \frac{1}{3}(\text{Systolic} - \text{Diastolic})$ |
| `at1007` | Pulse Pressure | `DV_QUANTITY` | `/data[at0001]/events[at0006]/data[at0003]/items[at1007]/value` | Calculated: $\text{Systolic} - \text{Diastolic}$ |
| `at0008` | State / Position | `DV_CODED_TEXT`| `/data[at0001]/events[at0006]/state[at0007]/items[at0008]/value` | Sitting (`at1001`), Lying (`at1003`), Standing (`at1002`) |

---

### 2.2 Pulse / Heart Rate (`openEHR-EHR-OBSERVATION.pulse.v2`)
- **Maps from**: `App\Models\VitalSign` (`app/Models/VitalSign.php`) (`heart_rate` column).

| Node ID | Element Name | Data Type | AQL Node Path | CoreHealth Source |
|---|---|---|---|---|
| `at0004` | Rate | `DV_QUANTITY` | `/data[at0002]/events[at0003]/data[at0001]/items[at0004]/value` | `vital_signs.heart_rate` (`/min`) |
| `at0005` | Regularity | `DV_CODED_TEXT`| `/data[at0002]/events[at0003]/data[at0001]/items[at0005]/value` | Regular (`at0006`), Irregular (`at0007`) |

---

### 2.3 Body Temperature (`openEHR-EHR-OBSERVATION.body_temperature.v2`)
- **Maps from**: `App\Models\VitalSign` (`app/Models/VitalSign.php`) (`temp` column).

| Node ID | Element Name | Data Type | AQL Node Path | CoreHealth Source |
|---|---|---|---|---|
| `at0004` | Temperature | `DV_QUANTITY` | `/data[at0002]/events[at0003]/data[at0001]/items[at0004]/value` | `vital_signs.temp` (`Cel`) |
| `at0021` | Site of measurement | `DV_CODED_TEXT`| `/protocol[at0020]/items[at0021]/value` | Axillary, Oral, Tympanic |

---

### 2.4 Clinical Encounter Composition (`openEHR-EHR-COMPOSITION.encounter.v1`)
- **Maps from**: `App\Models\Encounter` (`app/Models/Encounter.php`) + AI Scribe Structured Sections.

```json
{
  "_type": "COMPOSITION",
  "archetype_node_id": "openEHR-EHR-COMPOSITION.encounter.v1",
  "name": { "_type": "DV_TEXT", "value": "Clinical Consultation Encounter" },
  "archetype_details": {
    "archetype_id": { "value": "openEHR-EHR-COMPOSITION.encounter.v1" },
    "rm_version": "1.0.4"
  },
  "language": { "_type": "CODE_PHRASE", "terminology_id": { "value": "ISO_639-1" }, "code_string": "en" },
  "territory": { "_type": "CODE_PHRASE", "terminology_id": { "value": "ISO_3166-1" }, "code_string": "NG" },
  "category": { "_type": "DV_CODED_TEXT", "value": "event", "defining_code": { "terminology_id": { "value": "openehr" }, "code_string": "433" } },
  "context": {
    "_type": "EVENT_CONTEXT",
    "start_time": { "_type": "DV_DATE_TIME", "value": "2026-11-10T08:30:00+01:00" },
    "setting": { "_type": "DV_CODED_TEXT", "value": "other care", "defining_code": { "terminology_id": { "value": "openehr" }, "code_string": "238" } }
  },
  "content": [
    {
      "_type": "SECTION",
      "archetype_node_id": "openEHR-EHR-SECTION.history_rcp.v1",
      "name": { "_type": "DV_TEXT", "value": "Presenting Complaint & History" }
    },
    {
      "_type": "SECTION",
      "archetype_node_id": "openEHR-EHR-SECTION.examination_findings_rcp.v1",
      "name": { "_type": "DV_TEXT", "value": "Physical Examination" }
    },
    {
      "_type": "EVALUATION",
      "archetype_node_id": "openEHR-EHR-EVALUATION.problem_diagnosis.v1",
      "name": { "_type": "DV_TEXT", "value": "Provisional Diagnosis" }
    }
  ]
}
```

---

### 2.5 Medication Order (`openEHR-EHR-INSTRUCTION.medication_order.v3`)
- **Maps from**: `App\Models\ProductRequest` (`app/Models/ProductRequest.php`).

| Node ID | Element Name | Data Type | AQL Node Path | CoreHealth Source |
|---|---|---|---|---|
| `at0070` | Medication item | `DV_TEXT` / `DV_CODED_TEXT` | `/activities[at0001]/description[at0002]/items[at0070]/value` | `product.product_name` |
| `at0009` | Overall directions | `DV_TEXT` | `/activities[at0001]/description[at0002]/items[at0009]/value` | `product_request.dose` |
| `at0173` | Dose amount | `DV_QUANTITY` | `/activities[at0001]/description[at0002]/items[at0173]/value` | Parsed dose (e.g. `500` `mg`) |
| `at0010` | Route | `DV_CODED_TEXT`| `/activities[at0001]/description[at0002]/items[at0010]/value` | Parsed route (e.g. `Oral`) |
| `at0018` | Course duration | `DV_DURATION`| `/activities[at0001]/description[at0002]/items[at0018]/value` | Parsed duration (e.g. `P5D`) |

---

### 2.6 Laboratory Test Result (`openEHR-EHR-OBSERVATION.laboratory_test_result.v1`)
- **Maps from**: `App\Models\LabServiceRequest` (`app/Models/LabServiceRequest.php`).

| Node ID | Element Name | Data Type | AQL Node Path | CoreHealth Source |
|---|---|---|---|---|
| `at0005` | Test name | `DV_TEXT` / `DV_CODED_TEXT` | `/data[at0001]/events[at0002]/data[at0003]/items[at0005]/value` | `service.service_name` |
| `at0057` | Overall interpretation | `DV_TEXT` | `/data[at0001]/events[at0002]/data[at0003]/items[at0057]/value` | `lab_service_request.result` |
| `at0073` | Test status | `DV_CODED_TEXT`| `/data[at0001]/events[at0002]/data[at0003]/items[at0073]/value` | Preliminary, Final, Signed-off |
| `at0065` | Specimen detail | `CLUSTER` | `/protocol[at0004]/items[at0065]` | `lab_service_request.sample_date` |

---

### 2.7 Surgical Procedure (`openEHR-EHR-ACTION.procedure.v1`)
- **Maps from**: `App\Models\Procedure` (`app/Models/Procedure.php`).

| Node ID | Element Name | Data Type | AQL Node Path | CoreHealth Source |
|---|---|---|---|---|
| `at0002` | Procedure name | `DV_TEXT` / `DV_CODED_TEXT` | `/description[at0001]/items[at0002]/value` | `procedure.free_form_name` / `service.service_name` |
| `at0014` | Reason/Indication | `DV_TEXT` | `/description[at0001]/items[at0014]/value` | `procedure.pre_notes` |
| `at0004` | Outcome | `DV_TEXT` | `/description[at0001]/items[at0004]/value` | `procedure.outcome` / `outcome_notes` |
| `at0005` | Complications | `DV_TEXT` | `/description[at0001]/items[at0005]/value` | Post-operative complications note |
| `at0006` | Procedure steps | `DV_MULTIMEDIA` / `DV_TEXT`| `/description[at0001]/items[at0006]/value` | `procedure.post_notes` |

---

### 2.8 Antenatal Examination (`openEHR-EHR-OBSERVATION.antenatal_examination.v0`)
- **Maps from**: `App\Models\AncVisit` (`app/Models/AncVisit.php`).

| Node ID | Element Name | Data Type | AQL Node Path | CoreHealth Source |
|---|---|---|---|---|
| `at0004` | Gestational age | `DV_DURATION` | `/data[at0001]/events[at0002]/data[at0003]/items[at0004]/value` | `anc_visit.gestational_age_weeks` + `days` |
| `at0005` | Uterine fundal height | `DV_QUANTITY` | `/data[at0001]/events[at0002]/data[at0003]/items[at0005]/value` | `anc_visit.fundal_height_cm` (`cm`) |
| `at0006` | Fetal presentation | `DV_CODED_TEXT`| `/data[at0001]/events[at0002]/data[at0003]/items[at0006]/value` | `anc_visit.presentation` (Cephalic, Breech) |
| `at0007` | Fetal heart rate | `DV_QUANTITY` | `/data[at0001]/events[at0002]/data[at0003]/items[at0007]/value` | `anc_visit.fetal_heart_rate` (`/min`) |
| `at0008` | Fetal movement | `DV_BOOLEAN` | `/data[at0001]/events[at0002]/data[at0003]/items[at0008]/value` | `anc_visit.foetal_movement` |

---

### 2.9 Child Growth & Milestones (`openEHR-EHR-OBSERVATION.growth_anthro.v1`)
- **Maps from**: `App\Models\ChildGrowthRecord` (`app/Models/ChildGrowthRecord.php`).

| Node ID | Element Name | Data Type | AQL Node Path | CoreHealth Source |
|---|---|---|---|---|
| `at0004` | Weight-for-age z-score | `DV_QUANTITY` | `/data[at0001]/events[at0002]/data[at0003]/items[at0004]/value` | `child_growth_record.weight_for_age_z` |
| `at0005` | Height-for-age z-score | `DV_QUANTITY` | `/data[at0001]/events[at0002]/data[at0003]/items[at0005]/value` | `child_growth_record.length_for_age_z` |
| `at0006` | Weight-for-height z-score| `DV_QUANTITY` | `/data[at0001]/events[at0002]/data[at0003]/items[at0006]/value` | `child_growth_record.weight_for_length_z` |
| `at0007` | Mid-Upper Arm (MUAC) | `DV_QUANTITY` | `/data[at0001]/events[at0002]/data[at0003]/items[at0007]/value` | `child_growth_record.muac_cm` (`cm`) |
| `at0008` | Head circumference | `DV_QUANTITY` | `/data[at0001]/events[at0002]/data[at0003]/items[at0008]/value` | `child_growth_record.head_circumference_cm` (`cm`) |
