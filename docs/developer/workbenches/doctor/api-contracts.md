# Doctor Workbench Paystack-Style API Contracts

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | DOCTOR</span>

This developer reference details the request headers, payload parameters, validation rules, and JSON response contracts for all core Doctor Consultation endpoints, emphasizing **when each endpoint is triggered and how the frontend responds during actual clinic use**.

---

## 1. Unified Queue Telemetry Endpoint

### `GET /appointments/doctor/unified-list`

- **Clinical Trigger & UI Context**: Called automatically by the DataTables engine whenever the doctor opens `/encounters?status=waiting`, clicks any status pill (`Waiting`, `Ready`, `In Consultation`, `Scheduled`), or changes date/clinic filters.
- **Frontend Response Handling**: Renders responsive HTML patient cards into `#unified-queue-table`. If `can_deliver` is `true`, injects the green **Start Encounter** button; if `false`, renders the disabled **Blocked** button with the exact HMO/payment barrier tooltip. Initializes live microsecond timers on active consultation cards.

#### Headers
```http
Accept: application/json
X-Requested-With: XMLHttpRequest
X-CSRF-TOKEN: {{ csrf_token() }}
```

#### Query Parameters
| Parameter | Type | Required | Description |
|:---|:---|:---|:---|
| `start_date` | string (YYYY-MM-DD) | No | Start date for appointment and queue filtering. Defaults to today. |
| `end_date` | string (YYYY-MM-DD) | No | End date for filtering. Defaults to 30 days ahead. |
| `status_filter` | string | No | Filter by QueueStatus integer (`1`=Waiting, `2`=Vitals, `3`=Ready, `4`=In Consultation, `6`=Scheduled, `5`=Completed, or `all`). |
| `source_filter` | string | No | `all`, `walk_in`, `scheduled`, `follow_up`, `referral`, `emergency`. |
| `priority_filter` | string | No | `all`, `routine`, `urgent`, `emergency`. |
| `clinic_filter` | integer/string | No | Filter by specific clinic ID or `all`. |
| `sort_filter` | string | No | `newest`, `oldest`, `patient_az`, `patient_za`, `priority`. |

#### Response `200 OK`
```json
{
  "draw": 1,
  "recordsTotal": 56057,
  "recordsFiltered": 2,
  "data": [
    {
      "DT_RowIndex": 1,
      "card_html": "<div class=\"queue-card\" data-id=\"56084\"><div class=\"queue-card-avatar\">AR</div><div class=\"queue-card-body\"><div class=\"queue-card-name\">Auwal Rukky Baby <span class=\"queue-card-demo\">(F, 26)</span></div><div class=\"queue-card-mrn\">MRN: 0009925 · Private</div></div><div class=\"queue-card-actions\"><a href=\"/encounters/create?patient_id=90116&req_entry_id=102&queue_id=56084\" class=\"btn btn-success btn-sm queue-card-action-btn\"><i class=\"fa fa-street-view\"></i> Start Encounter</a></div></div>",
      "patient_name": "Auwal Rukky Baby",
      "status": 1,
      "priority": "routine",
      "can_deliver": true
    }
  ]
}
```

---

## 2. Incremental Auto-Save Endpoint

### `POST /encounters/incremental-save`

- **Clinical Trigger & UI Context**: Debounced and executed silently every 15 seconds in the background as the doctor types notes, dictates history, or selects ICPC-2 complaints.
- **Frontend Response Handling**: Displays a subtle green auto-save indicator in `#labs-auto-save-status` without interrupting the doctor's keystrokes. Protects against browser crashes or power outages.

#### Headers
```http
Content-Type: application/json
Accept: application/json
X-CSRF-TOKEN: {{ csrf_token() }}
```

#### Request Payload
```json
{
  "encounter_id": 14205,
  "patient_id": 90116,
  "reason_for_encounter": "Routine follow-up on hypertension management and recurring morning headache.",
  "icpc_code": "K86",
  "history_of_present_illness": "Patient reports adherence to Amlodipine 10mg daily. Complains of intermittent occipital headaches over the past 3 days.",
  "physical_examination": "Afebril, not pale, anicteric. Chest clear, vesicular breath sounds. S1S2 present.",
  "diagnosis_id": 4821,
  "consultation_seconds": 345
}
```

#### Response `200 OK`
```json
{
  "success": true,
  "message": "Encounter notes auto-saved successfully.",
  "data": {
    "encounter_id": 14205,
    "last_saved_at": "2026-11-09T14:45:00.000000Z",
    "consultation_seconds": 345
  }
}
```

---

## 3. Encounter Finalization & Conclusion Endpoint

### `POST /encounters`

- **Clinical Trigger & UI Context**: Triggered when the doctor clicks **Complete Encounter** in `#concludeEncounterModal`.
- **Frontend Response Handling**: Disables the submit button to prevent double-submission. On `200 OK`, shows a success toastr notification, stops the consultation stopwatch, marks the queue card as `COMPLETED`, and redirects the doctor back to `/encounters?status=waiting`. On `422`, aborts and pops up an impossible-to-miss red allergy conflict warning.

#### Request Payload
```json
{
  "patient_id": 90116,
  "queue_id": 56084,
  "clinical_outcome": "Consultation Finalized / Continuing Care",
  "notes": "Patient advised on dietary sodium reduction and continued on current antihypertensive regimen.",
  "diagnoses": [
    {
      "icd10_code": "I10",
      "diagnosis_name": "Essential (primary) hypertension",
      "type": "confirmed"
    }
  ],
  "lab_requests": [
    {
      "service_id": 124,
      "priority": "routine",
      "clinical_notes": "Routine renal function check"
    }
  ],
  "prescriptions": [
    {
      "product_id": 542,
      "dosage": "10mg",
      "route": "Oral",
      "frequency": "OD",
      "duration": 30,
      "quantity": 30,
      "price": 1500.00
    }
  ],
  "consultation_duration_seconds": 720
}
```

#### Response `200 OK`
```json
{
  "success": true,
  "message": "Clinical encounter concluded and finalized successfully.",
  "data": {
    "encounter_id": 14205,
    "patient_id": 90116,
    "queue_status": "COMPLETED",
    "redirect_url": "/encounters?status=waiting"
  }
}
```

#### Response `422 Unprocessable Entity` (Allergy Conflict)
```json
{
  "success": false,
  "message": "Clinical Validation Error: Patient has a documented allergy to Penicillins. Order set blocked.",
  "errors": {
    "prescriptions.0.product_id": [
      "Amoxicillin 500mg conflicts with documented patient allergy 'Penicillin'."
    ]
  }
}
```

---

## 4. Specialist Referral Endpoint

### `POST /specialist-referrals`

- **Clinical Trigger & UI Context**: Triggered when the doctor clicks **Submit Referral** in `#referrals`.
- **Frontend Response Handling**: Appends the new referral card to the historical referrals list, clears the form, shows a success toastr, and (if `urgency === 'emergency'`) triggers an audible priority chime on the reception desk.

#### Request Payload
```json
{
  "encounter_id": 14205,
  "referral_type": "internal",
  "urgency": "urgent",
  "target_clinic_id": 4,
  "target_staff_id": 12,
  "provisional_diagnosis": "Secondary Hypertension with Retinopathy",
  "clinical_summary": "Patient presented with poorly controlled BP (170/105 mmHg) despite dual therapy. Visual acuity changes noted.",
  "reason": "Specialist Cardiology and Ophthalmology evaluation required."
}
```

#### Response `201 Created`
```json
{
  "success": true,
  "message": "Specialist referral logged and dispatched successfully.",
  "data": {
    "referral_id": 842,
    "tracking_code": "REF-2026-0842",
    "urgency": "urgent",
    "target_clinic": "Cardiology",
    "created_at": "2026-11-09T14:46:12.000000Z"
  }
}
```

---

## 5. Inpatient Ward Admission Request Endpoint

### `POST /admission-requests`

- **Clinical Trigger & UI Context**: Triggered when the doctor clicks **Admit** on the sticky header (`openAdmitModal()`) and submits the target ward.
- **Frontend Response Handling**: Closes the admit modal, updates the sticky consultation header badge to `ADMISSION REQUESTED`, and immediately places an incoming bed hold on the ward manager's screen.

#### Request Payload
```json
{
  "patient_id": 90116,
  "encounter_id": 14205,
  "ward_id": 2,
  "admission_type": "emergency",
  "admitting_diagnosis": "Hypertensive Urgency with acute target organ strain",
  "doctor_notes": "Admit to Male Medical Ward for IV Labetalol titration and serial BP monitoring. Strict intake/output charting.",
  "dietary_orders": "Low salt, diabetic diet"
}
```

#### Response `201 Created`
```json
{
  "success": true,
  "message": "Inpatient admission requisition submitted successfully.",
  "data": {
    "admission_request_id": 319,
    "ward": "Male Medical Ward",
    "status": "admission_requested",
    "created_at": "2026-11-09T14:47:00.000000Z"
  }
}
```

---

## 6. AI Note Polishing Endpoint

### `POST /llm/polish-note`

- **Clinical Trigger & UI Context**: Triggered when the doctor clicks **Polish with AI** (<i class="fa fa-magic"></i>) next to the speech dictation controls.
- **Frontend Response Handling**: Replaces messy conversational speech with formatted medical SOAP paragraphs, capitalizes medical acronyms, and formats dosage notation.

#### Request Payload
```json
{
  "raw_text": "patient 54 year old male with history of high bp complains of bad headache since yesterday denies chest pain bp 150 over 95 heart rate 78 start amlodipine 10mg once daily follow up next week",
  "context": {
    "age": 54,
    "gender": "Male",
    "specialty": "General Outpatient"
  }
}
```

#### Response `200 OK`
```json
{
  "success": true,
  "polished_content": "**SUBJECTIVE:**\n54-year-old male with a known history of hypertension presents with severe headache persisting since yesterday. Patient explicitly denies chest pain, palpitations, or shortness of breath.\n\n**OBJECTIVE:**\n- BP: 150/95 mmHg\n- Heart Rate: 78 bpm\n- General: Alert, oriented, in mild distress due to cephalalgia.\n\n**ASSESSMENT:**\n- Essential Hypertension (ICD-10: I10), sub-optimally controlled.\n- Acute Cephalalgia, secondary to elevated systemic pressure.\n\n**PLAN:**\n- Rx: Amlodipine 10mg PO OD.\n- Advised lifestyle modifications: dietary sodium restriction.\n- Follow-up in 1 week for serial blood pressure evaluation."
}
```
