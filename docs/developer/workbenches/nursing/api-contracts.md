# Nursing API Contracts & Route Specifications

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | super-admin | NURSE</span>

This reference specifies the JSON payloads, request parameters, and response structures for the core endpoints exposed under `routes/nursing_workbench.php`.

---

## 1. Shift Lifecycle Endpoints

### Start Shift
- **Route**: `POST /nursing-workbench/shift/start`
- **Controller**: `App\Http\Controllers\ShiftController@startShift`

#### Request Payload
```json
{
  "ward_id": 4,
  "shift_type": "morning",
  "notes": "Commencing Morning Shift coverage for Female Surgical Ward."
}
```

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "message": "Shift started successfully.",
  "shift": {
    "id": 182,
    "user_id": 14,
    "ward_id": 4,
    "shift_type": "morning",
    "started_at": "2026-11-09 07:00:00",
    "status": "active"
  }
}
```

---

### End Shift & Handover
- **Route**: `POST /nursing-workbench/shift/end`
- **Controller**: `App\Http\Controllers\ShiftController@endShift`

#### Request Payload
```json
{
  "shift_id": 182,
  "handover_notes": "All 18 patients stable. Bed 4 Oladipo Amina received IV Ceftriaxone 1g at 14:00. Drain output 45ml clear serosanguinous.",
  "census_summary": {
    "total_admitted": 18,
    "transfers": 1,
    "discharges": 2,
    "critical_count": 0
  }
}
```

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "message": "Shift ended and handover report drafted successfully.",
  "handover_id": 94
}
```

---

## 2. Bedside Vitals & MEWS Score Recording

### Save Vital Signs
- **Route**: `POST /nursing-workbench/vitals`
- **Controller**: `App\Http\Controllers\NursingWorkbenchController@saveVitals`

#### Request Payload
```json
{
  "patient_id": 42,
  "admission_id": 88,
  "systolic_bp": 120,
  "diastolic_bp": 80,
  "pulse_rate": 78,
  "respiratory_rate": 16,
  "temperature": 36.8,
  "oxygen_saturation": 98,
  "avpu_scale": "Alert",
  "pain_score": 2,
  "blood_glucose": 5.4,
  "notes": "Patient resting comfortably. Post-op Day 2."
}
```

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "message": "Vitals recorded successfully.",
  "data": {
    "id": 512,
    "patient_id": 42,
    "mews_score": 0,
    "mews_status": "Low Risk",
    "recorded_at": "2026-11-09 14:15:00",
    "vital_summary": "BP: 120/80 mmHg | HR: 78 bpm | Temp: 36.8 °C | SpO2: 98% | MEWS: 0"
  }
}
```

---

## 3. Electronic Medication Administration (eMAR)

### Administer Dose from Schedule
- **Route**: `POST /nursing-workbench/schedule/{scheduleId}/administer`
- **Controller**: `App\Http\Controllers\NursingWorkbenchController@administerFromSchedule`

#### Request Payload
```json
{
  "batch_id": 204,
  "store_id": 2,
  "dose_given": "1g",
  "route": "Intravenous",
  "injection_site": "Left Forearm IV Cannula",
  "administered_at": "2026-11-09 14:00:00",
  "administration_notes": "Infused in 100ml Normal Saline over 30 mins. No adverse reaction observed."
}
```

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "message": "Medication administered and ward stock decremented.",
  "record": {
    "id": 1024,
    "schedule_id": 412,
    "status": "administered",
    "batch_code": "LOT-CEF-2026-09",
    "stock_balance": 48
  }
}
```

---

## 4. Nurse-Initiated Clinical Orders (Auto-Save Endpoints)

### Add Single Consumable / Product
- **Route**: `POST /nursing-workbench/clinical-requests/add-prescription`
- **Controller**: `App\Http\Controllers\NursingWorkbenchController@nurseAddSinglePrescription`

#### Request Payload
```json
{
  "patient_id": 42,
  "admission_id": 88,
  "product_id": 158,
  "quantity": 1,
  "dosage": "1 cannula stat",
  "duration": 1,
  "store_id": 2
}
```

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "item_id": 789,
  "item_name": "IV Cannula 20G Pink",
  "unit_price": 250.00,
  "total_price": 250.00,
  "message": "Item added to clinical request."
}
```

---

## 5. Bed Management & Ward Census

### Complete Ward Discharge
- **Route**: `POST /nursing-workbench/admission/{admissionId}/complete-discharge`
- **Controller**: `App\Http\Controllers\NursingWorkbenchController@completeDischarge`

#### Request Payload
```json
{
  "discharge_type": "normal",
  "discharge_summary": "Surgical wound healed with primary intention. Ambulatory, tolerating oral diet.",
  "take_home_medications_issued": true,
  "follow_up_date": "2026-11-23"
}
```

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "message": "Discharge completed. Bed released to CLEANING status.",
  "bed_id": 14,
  "bed_status": "cleaning"
}
```

---

## Developer Reference
👉 **[Read Nursing Developer Architecture](/developer/workbenches/nursing)**  
👉 **[Read Nursing Database Schema & Models Reference](/developer/workbenches/nursing/database-and-models)**

