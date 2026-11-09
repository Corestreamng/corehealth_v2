# Maternity API Contracts & Route Specifications

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | super-admin | MATERNITY</span>

This reference documents the JSON endpoints, input validation rules, and response payloads for `routes/maternity_workbench.php`.

---

## 1. Antenatal Enrollment & Booking

### Enroll Mother in Maternity Care
- **Route**: `POST /maternity-workbench/enroll`
- **Controller**: `App\Http\Controllers\MaternityWorkbenchController@enrollPatient`

#### Request Payload
```json
{
  "patient_id": 42,
  "lmp": "2026-02-01",
  "booking_weight": 68.5,
  "booking_height": 164,
  "gravida": 2,
  "parity": 1,
  "living_children": 1,
  "abortions": 0,
  "blood_group": "O+",
  "genotype": "AA",
  "special_instructions": "Previous spontaneous vaginal delivery without complications."
}
```

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "message": "Patient enrolled in maternity care successfully.",
  "enrollment": {
    "id": 84,
    "patient_id": 42,
    "anc_number": "ANC-2026-0042",
    "edd": "2026-11-08",
    "gestational_age_weeks": 12,
    "status": "active"
  }
}
```

---

## 2. Antenatal Trimester Visits

### Record ANC Visit
- **Route**: `POST /maternity-workbench/enrollment/{id}/anc-visit`
- **Controller**: `App\Http\Controllers\MaternityWorkbenchController@saveAncVisit`

#### Request Payload
```json
{
  "visit_date": "2026-05-24",
  "gestational_age_weeks": 16,
  "weight_kg": 70.2,
  "blood_pressure": "115/75",
  "fundal_height_cm": 16,
  "fetal_heart_rate": 144,
  "fetal_presentation": "cephalic",
  "fetal_lie": "longitudinal",
  "urine_protein": "negative",
  "urine_glucose": "negative",
  "notes": "First trimester ultrasound confirms single live fetus. Prescribed routine Iron/Folic acid and Tetanus Toxoid TT1."
}
```

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "message": "ANC visit recorded successfully.",
  "visit_id": 312
}
```

---

## 3. Real-Time Digital Partograph

### Save Partograph Entry
- **Route**: `POST /maternity-workbench/enrollment/{id}/maternity-partograph`
- **Controller**: `App\Http\Controllers\MaternityWorkbenchController@saveMaternityPartographEntry`

#### Request Payload
```json
{
  "recorded_at": "2026-11-09 14:00:00",
  "cervical_dilation_cm": 6.0,
  "fetal_head_descent": "3/5",
  "fetal_heart_rate": 140,
  "liquor_status": "clear",
  "molding": "0",
  "contractions_per_10min": 3,
  "contraction_duration_seconds": 35,
  "maternal_pulse": 82,
  "maternal_bp": "118/76",
  "temperature": 36.7,
  "oxytocin_drops_min": 0
}
```

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "message": "Partograph entry saved and chart refreshed.",
  "entry": {
    "id": 145,
    "alert_line_dilation": 5.0,
    "action_line_dilation": 1.0,
    "is_prolonged": false
  }
}
```

---

## 4. Delivery & Baby Registration

### Record Delivery Outcome
- **Route**: `POST /maternity-workbench/enrollment/{id}/delivery`
- **Controller**: `App\Http\Controllers\MaternityWorkbenchController@saveDeliveryRecord`

#### Request Payload
```json
{
  "delivery_date": "2026-11-09",
  "delivery_time": "18:24",
  "type_of_delivery": "svd",
  "number_of_babies": 1,
  "duration_of_labour_hours": 8.5,
  "blood_loss_ml": 250,
  "oxytocin_given": 1,
  "placenta_complete": 1,
  "perineal_tear_degree": "None",
  "episiotomy": "none",
  "notes": "Normal Spontaneous Vaginal Delivery of a live male infant. AMTSL executed seamlessly."
}
```

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "message": "Delivery record saved successfully. Proceed to register baby.",
  "delivery_id": 78
}
```

---

### Register Newborn Baby
- **Route**: `POST /maternity-workbench/enrollment/{id}/baby`
- **Controller**: `App\Http\Controllers\MaternityWorkbenchController@registerBaby`

#### Request Payload
```json
{
  "baby_surname": "Bello",
  "baby_firstname": "Baby Boy",
  "sex": "male",
  "is_still_birth": 0,
  "birth_weight_kg": 3.40,
  "length_cm": 50.0,
  "head_circumference_cm": 35.0,
  "apgar_1_min": 8,
  "apgar_5_min": 10,
  "apgar_10_min": 10,
  "feeding_method": "exclusive_breastfeeding",
  "bcg_given": 1,
  "opv0_given": 1,
  "hbv0_given": 1,
  "vitamin_k_given": 1,
  "eye_prophylaxis": 1
}
```

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "message": "Baby registered and immunization schedule generated.",
  "baby": {
    "id": 92,
    "patient_id": 43,
    "panc_number": "PANC-2026-0042",
    "name": "Bello Baby Boy",
    "apgar_summary": "8/10/10",
    "status": "alive"
  }
}
```

---

## 5. Postnatal Care & Discharge

### Record Postnatal Visit
- **Route**: `POST /maternity-workbench/enrollment/{id}/postnatal`
- **Controller**: `App\Http\Controllers\MaternityWorkbenchController@savePostnatalVisit`

#### Request Payload
```json
{
  "visit_date": "2026-11-09",
  "visit_type": "inpatient_day_1",
  "general_condition": "Good",
  "blood_pressure": "118/76",
  "temperature_c": 36.7,
  "lochia": "Rubra",
  "uterus_assessment": "Well-contracted at umbilicus",
  "wound_assessment": "Intact",
  "breast_assessment": "Colostrum flowing freely",
  "baby_weight_kg": 3.38,
  "baby_feeding": "Exclusive Breastfeeding",
  "cord_status": "Clean and dry",
  "jaundice": 0,
  "family_planning_counselled": 1,
  "family_planning_method": "Lactational Amenorrhea Method (LAM)"
}
```

#### Success Response (`200 OK`)
```json
{
  "success": true,
  "message": "Postnatal visit recorded successfully.",
  "visit_id": 114
}
```

---

## Developer Reference
👉 **[Read Maternity Developer Architecture](/developer/workbenches/maternity)**  
👉 **[Read Maternity Database Schema & Models Reference](/developer/workbenches/maternity/database-and-models)**

