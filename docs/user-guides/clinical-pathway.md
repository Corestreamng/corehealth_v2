# The Longitudinal Patient Clinical Pathway

In CoreHealth v2, clinical care is designed as a continuous, unified patient narrative rather than disconnected departmental silos. Every interaction—from outpatient registration at Reception to pharmacy dispensing or ward admission—shares a single longitudinal Electronic Health Record (EHR).

---

## 1. Visual Overview: Longitudinal Care Flow

![Longitudinal Clinical Telemetry Overview](/images/workbenches/dashboard-overview.jpg)

---

## 2. The Hospital Patient Journey Flowchart

```mermaid
flowchart TD
    A["Patient Arrival at Hospital"] --> B["Reception & Health Records<br/>(reception.workbench)"]
    B --> C{"Intake Classification"}
    
    C -->|Emergency / Code Red| D["Emergency Triage & Bed Hold<br/>(Immediate Nursing Care)"]
    C -->|Scheduled / Outpatient| E["Triage Queue & Vital Signs<br/>(nursing-workbench.index)"]
    C -->|Maternity Intake| F["Maternity Clinic<br/>(maternity-workbench.index)"]
    
    D --> G["Doctor Consultation Cockpit<br/>(encounters.index / encounter.workbench)"]
    E --> G
    F --> G
    
    G --> H{"Clinical Orders & Investigations"}
    H -->|Lab Orders| I["Medical Laboratory (LIS)<br/>(lab.workbench)"]
    H -->|Imaging Orders| J["Radiology Workbench<br/>(imaging.workbench)"]
    H -->|Surgical Booking| K["Theatre / Surgery<br/>(surgery-workbench.index)"]
    H -->|Medication Prescription| L["Pharmacy & Dispensary<br/>(pharmacy.workbench)"]
    H -->|Inpatient Care Needed| M["Ward Admission<br/>(beds.index)"]
    
    I --> N["Cashier & Billing Workbench<br/>(billing.workbench)"]
    J --> N
    K --> N
    L --> N
    M --> N
    
    N --> O["Discharge & Follow-Up<br/>(Outpatient Exit or Post-Op Review)"]
    M -->|Terminal Event| P["Mortuary & Last Office<br/>(morgue.workbench)"]
```

---

## 3. Departmental Handoff Sequences

The clinical lifecycle enforces strict cross-module data integrity:

```mermaid
sequenceDiagram
    autonumber
    actor Patient
    participant Rec as Receptionist (reception.workbench)
    participant Nurse as Nurse (nursing-workbench)
    participant Doc as Doctor (encounters)
    participant Lab as Lab Scientist (lab.workbench)
    participant Cashier as Cashier (billing.workbench)
    participant Pharm as Pharmacist (pharmacy.workbench)

    Patient->>Rec: Arrives at front desk
    Rec->>Rec: Searches file or registers via Rapid Modal (#patientFormModal)
    Rec->>Nurse: Dispatches to Triage Queue (status = 1 / waiting)
    Nurse->>Nurse: Captures Vitals (BP, Pulse, Temp, SpO2)
    Nurse->>Doc: Pushes to Doctor Queue
    Doc->>Doc: Evaluates patient, enters ICD-10 Diagnosis
    Doc->>Lab: Requests Full Blood Count (FBC)
    Doc->>Pharm: Prescribes Oral Antibiotics
    Doc->>Cashier: Generates pending bills (Orders Queue)
    Patient->>Cashier: Pays via Split Payment (Cash + POS)
    Cashier-->>Lab: Payment cleared for sample collection
    Lab->>Lab: Analyzes sample, enters results, Consultant signs off
    Doc->>Doc: Reviews completed lab results in Encounter timeline
    Patient->>Pharm: Approaches Pharmacy window
    Pharm->>Pharm: Dispenses FIFO batches against verified prescription
    Pharm-->>Patient: Medication handed over with usage counsel
```

---

## 4. Departmental Navigation Matrix (Fact-Checked)

| Workbench | Real Route Name & URI | Authoritative Role Guard (`sidebar.blade.php`) | Core Purpose | Direct Story Link |
|---|---|---|---|---|
| **Reception & Records** | `reception.workbench`<br/>(`/reception/workbench`) | `@hasanyrole('SUPERADMIN\|ADMIN\|RECEPTIONIST')` | Patient file management, triage queue dispatch, rapid registration | [Read Story &rarr;](/user-guides/workbenches/reception) |
| **Doctor Consultation** | `encounters.index`<br/>(`/encounters?status=waiting`) | `@hasanyrole('SUPERADMIN\|ADMIN\|DOCTOR')` | Encounter management, ICD-10 diagnosis, clinical order sets | [Read Story &rarr;](/user-guides/workbenches/doctor) |
| **Nursing & MAR** | `nursing-workbench.index`<br/>(`/nursing-workbench`) | `@hasanyrole('SUPERADMIN\|ADMIN\|NURSE')` | Vitals triage, medication administration (MAR), fluid balance, consumables | [Read Story &rarr;](/user-guides/workbenches/nursing) |
| **Pharmacy & Dispensary** | `pharmacy.workbench`<br/>(`/pharmacy-workbench`) | `@hasanyrole('SUPERADMIN\|ADMIN\|PHARMACIST')` | Prescription fulfillment, FIFO batch tracking, returns & damages | [Read Story &rarr;](/user-guides/workbenches/pharmacy) |
| **Medical Laboratory (LIS)**| `lab.workbench`<br/>(`/lab-workbench`) | `@hasanyrole('SUPERADMIN\|ADMIN\|LAB SCIENTIST')` | Specimen accessioning, result entry, dual supervisor approval | [Read Story &rarr;](/user-guides/workbenches/laboratory) |
| **Imaging / Radiology** | `imaging.workbench`<br/>(`/imaging-workbench`) | `@hasanyrole('SUPERADMIN\|ADMIN\|RADIOLOGIST')` | Modality worklists, report authoring, DICOM attachments | [Read Story &rarr;](/user-guides/workbenches/imaging) |
| **Theatre & Surgery** | `surgery-workbench.index`<br/>(`/surgery-workbench`) | `@hasanyrole('SUPERADMIN\|ADMIN\|SURGERY\|DOCTOR\|NURSE')` | Surgical schedules, WHO checklist, intra-operative team logs | [Read Story &rarr;](/user-guides/workbenches/theatre) |
| **Maternity (ANC/PANC)** | `maternity-workbench.index`<br/>(`/maternity-workbench`) | `@hasanyrole('SUPERADMIN\|ADMIN\|MATERNITY')` | Antenatal visits, partographs, delivery records, child wellness | [Read Story &rarr;](/user-guides/workbenches/maternity) |
| **Ward Admissions / Beds** | `beds.index`<br/>(`/beds`) | `@hasanyrole('SUPERADMIN\|ADMIN\|NURSE')` | Ward occupancy, bed allocation, nursing rounds, transfer logs | [Read Story &rarr;](/user-guides/workbenches/inpatient) |
| **Billing & Cashier** | `billing.workbench`<br/>(`/billing-workbench`) | `@hasanyrole('SUPERADMIN\|ADMIN\|ACCOUNTS\|BILLER')` | Multi-tender checkout, split payments, patient deposits | [Read Story &rarr;](/user-guides/workbenches/billing) |
| **HMO & Insurance** | `hmo.workbench`<br/>(`/hmo/workbench`) | `@hasanyrole('SUPERADMIN\|ADMIN\|HMO Executive')` | Tariff management, secondary pre-auth, remittance reconciliation | [Read Story &rarr;](/user-guides/workbenches/hmo) |
| **Store & Inventory** | `inventory.store-workbench.index`<br/>(`/inventory/store-workbench`) | `@hasanyrole('SUPERADMIN\|ADMIN\|STORE')` | Multi-store transfers, lane policy approvals, purchase orders | [Read Story &rarr;](/user-guides/workbenches/inventory) |
| **Internal Audit & Ops** | `audit.workbench`<br/>(`/audit-workbench`) | `hasAnyRole(['SUPERADMIN', 'ADMIN', 'AUDITOR', 'AUDIT'])` | Duty shift certificates, cashier till balancing, page stamping | [Read Story &rarr;](/user-guides/workbenches/ops-audit) |
| **Mortuary & Last Office**| `morgue.workbench`<br/>(`/morgue-workbench`) | `@hasanyrole('SUPERADMIN\|ADMIN\|MORGUE')` | Deceased intake, embalming records, vault storage, release logs | [Read Story &rarr;](/user-guides/workbenches/mortuary) |
| **Human Resources & ESS** | `hr.workbench.index`<br/>(`/hr/workbench`) | `@hasanyrole('SUPERADMIN\|ADMIN\|HR MANAGER')` (`@can('hr-workbench.access')`) | Staff records, duty rosters, locum pass issuing, status governance | [Read Story &rarr;](/user-guides/workbenches/hr) |
| **Operational Cockpit** | `home`<br/>(`/home`) | Authenticated Staff (Perspective Switcher) | Zero-scroll triage, real-time KPI matrix, role launchpad dock | [Read Story &rarr;](/user-guides/workbenches/cockpit) |
