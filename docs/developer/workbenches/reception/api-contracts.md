# Reception Workbench API Contracts & Endpoint Specifications

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | RECEPTIONIST</span>

This technical reference provides Paystack-style API contracts for all core endpoints under the `reception/` prefix. Each specification details request headers, body parameters, validation rules, realistic JSON payloads, and HTTP status codes.

---

## 1. Quick Patient Registration

Registers a new patient, verifies phone/file uniqueness, initializes an internal wallet, and dispatches multi-facility synchronization.

### `POST /reception/patient/quick-register`

- **Route Name**: `reception.patient.quick-register`
- **Controller Action**: `ReceptionWorkbenchController@quickRegister`
- **Middleware**: `['auth']`

#### Request Headers
| Header | Value | Description |
|---|---|---|
| `Content-Type` | `application/json` | Request encoding |
| `X-CSRF-TOKEN` | `string` | Laravel CSRF token |
| `Accept` | `application/json` | JSON response format |

#### Request Parameters
| Field | Type | Required | Description |
|---|---|---|---|
| `surname` | `string` | **Yes** | Patient legal family name |
| `firstname` | `string` | **Yes** | Patient given name |
| `othername` | `string` | No | Additional name / middle name |
| `gender` | `string` | **Yes** | `male`, `female`, or `other` |
| `phone_no` | `string` | **Yes** | Contact telephone number (checks duplicate detection) |
| `dob` | `string` | No | ISO Date of Birth (`YYYY-MM-DD`) |
| `age` | `integer` | No | Age in years (if DOB is unknown, system derives DOB) |
| `file_no` | `string` | No | Manual file number (if omitted, next auto-number assigned) |
| `prefix` | `string` | No | Category prefix (`PAT-`, `ANC-`, or `PANC-`) |
| `hmo_id` | `integer` | No | Foreign key referencing `hmos.id` |
| `hmo_no` | `string` | No | Insurance policy / enrollee identifier |
| `allergies` | `array` | No | JSON array of drug or food allergies (e.g. `["Penicillin", "NSAIDs"]`) |
| `blood_group` | `string` | No | ABO blood group (e.g. `O+`, `B+`) |
| `genotype` | `string` | No | Hemoglobin genotype (e.g. `AA`, `AS`, `SS`) |
| `next_of_kin_name` | `string` | No | Emergency contact full name |
| `next_of_kin_phone` | `string` | No | Emergency contact phone |

#### Request Example (cURL)
```bash
curl -X POST https://hospital.corehealth.test/reception/patient/quick-register \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: a1b2c3d4e5..." \
  -d '{
    "surname": "Emeka",
    "firstname": "Mary",
    "othername": "Abah",
    "gender": "female",
    "phone_no": "08012345678",
    "dob": "1995-04-13",
    "prefix": "PAT-",
    "hmo_id": 1,
    "hmo_no": "HMO-98214-A",
    "allergies": ["Penicillin"]
  }'
```

#### Success Response (`200 OK`)
```json
{
  "status": true,
  "message": "Patient registered successfully",
  "patient": {
    "id": 1042,
    "user_id": 2085,
    "file_no": "PAT-21-36-49",
    "name": "Emeka Mary Abah",
    "gender": "female",
    "age": 31,
    "dob": "1995-04-13",
    "phone": "08012345678",
    "hmo_id": 1,
    "hmo_name": "Reliance HMO",
    "hmo_no": "HMO-98214-A",
    "balance": "0.00",
    "allergies": ["Penicillin"]
  }
}
```

#### Error Response (`422 Unprocessable Entity`)
```json
{
  "status": false,
  "message": "The given data was invalid.",
  "errors": {
    "phone_no": ["A patient with this phone number already exists (File: PAT-21-36-49)."],
    "surname": ["The surname field is required."]
  }
}
```

---

## 2. Consultation Booking & Queue Dispatching

Dispatches a patient into a specialty clinic doctor consulting queue or pre-schedules an outpatient appointment.

### `POST /reception/book-consultation`

- **Route Name**: `reception.book-consultation`
- **Controller Action**: `ReceptionWorkbenchController@bookConsultation`
- **Middleware**: `['auth']`

#### Request Parameters
| Field | Type | Required | Description |
|---|---|---|---|
| `patient_id` | `integer` | **Yes** | Target patient ID |
| `clinic_id` | `integer` | **Yes** | Department / specialty clinic (`clinics.id`) |
| `doctor_id` | `integer` | No | Assigned physician (`staff.id`), or null for any doctor |
| `service_id` | `integer` | **Yes** | Consultation tariff ID (`services.id`) |
| `booking_type` | `string` | **Yes** | `walkin` (Now) or `schedule` (Future) |
| `appointment_date`| `date` | Conditional | Required if `booking_type === 'schedule'` (`YYYY-MM-DD`) |
| `appointment_time`| `string` | Conditional | Required if scheduled (`HH:MM:SS`) |
| `priority` | `string` | No | `routine`, `urgent`, or `emergency` (default: `routine`) |
| `force_rebill` | `boolean` | No | `true` overrides hospital consultation cycle duration limits |
| `notes` | `string` | No | Directives or referral reason |

#### Request Example (JavaScript Fetch)
```javascript
fetch('/reception/book-consultation', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': window.WORKBENCH_CONFIG.csrf
  },
  body: JSON.stringify({
    patient_id: 1042,
    clinic_id: 2,
    service_id: 15,
    booking_type: 'walkin',
    force_rebill: false
  })
})
.then(res => res.json())
.then(data => console.log(data));
```

#### Success Response (`200 OK`)
```json
{
  "status": true,
  "message": "Patient successfully enqueued for consultation",
  "queue_entry": {
    "id": 5892,
    "patient_id": 1042,
    "clinic_id": 2,
    "clinic_name": "General Outpatient (GOPD)",
    "status": 1,
    "status_label": "Waiting",
    "enqueued_at": "2026-11-09 14:15:00",
    "routing_slip_url": "/reception/queue/5892/routing-slip"
  }
}
```

---

## 3. Direct Walk-in Sales Checkout

Processes point-of-sale orders across Pharmacy, Lab, Imaging, Procedures, and Custom free-form items with instant financial settlement.

### `POST /reception/book-walkin`

- **Route Name**: `reception.book-walkin`
- **Controller Action**: `ReceptionWorkbenchController@bookWalkinServices`
- **Middleware**: `['auth']`

#### Request Parameters
| Field | Type | Required | Description |
|---|---|---|---|
| `patient_id` | `integer` | **Yes** | Patient identifier |
| `items` | `array` | **Yes** | Array of cart item objects |
| `items[].type` | `string` | **Yes** | `lab`, `imaging`, `product`, `procedure`, or `free_form` |
| `items[].id` | `integer` | Conditional | Item ID in respective catalog |
| `items[].name` | `string` | **Yes** | Item description |
| `items[].price` | `numeric` | **Yes** | Tariff unit price |
| `items[].quantity` | `integer` | **Yes** | Quantity ordered |
| `items[].dept_id` | `integer` | **Yes** | Attribution department ID |
| `payment_method` | `string` | Conditional | `CASH`, `POS`, `TRANSFER`, `WALLET`, or `SPLIT` |
| `splits` | `array` | Conditional | Array of tender legs if `SPLIT` payment used |

#### Success Response (`200 OK`)
```json
{
  "status": true,
  "message": "Walk-in services ordered and paid successfully",
  "order": {
    "order_id": "ORD-20261109-089",
    "total_amount": 14500.00,
    "hmo_covered": 10000.00,
    "patient_paid": 4500.00,
    "receipt_id": 14201,
    "thermal_receipt_url": "/billing-workbench/receipt/14201/thermal",
    "a4_receipt_url": "/billing-workbench/receipt/14201/a4"
  }
}
```

---

## 4. HMO Claims Validation (Single & Batch)

Approves secondary health insurance coverage requests and unlocks clinical order fulfillment.

### Single Validation: `POST /reception/hmo-validate/{id}`

#### Request Parameters
| Field | Type | Required | Description |
|---|---|---|---|
| `id` | `integer` | **Yes** | ID in `product_or_service_requests` |
| `notes` | `string` | No | Authorization remarks |

### Batch Validation: `POST /reception/hmo-batch-validate`

#### Request Parameters
| Field | Type | Required | Description |
|---|---|---|---|
| `request_ids` | `array` | **Yes** | Array of integer request IDs (e.g. `[812, 813, 814]`) |
| `notes` | `string` | No | Batch verification remarks |

#### Success Response (`200 OK`)
```json
{
  "status": true,
  "message": "3 HMO requests validated successfully",
  "validated_count": 3,
  "timestamp": "2026-11-09T14:22:10Z"
}
```

---

👉 **[Return to Reception Developer Reference](/developer/workbenches/reception)** | **[Next: Database Schemas & Models](/developer/workbenches/reception/database-and-models)**

