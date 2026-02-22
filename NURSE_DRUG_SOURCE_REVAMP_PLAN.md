# Nurse Drug Source Revamp Plan

> **Status**: IN PROGRESS — Phases 1–4 backend & core UI complete, Phase 3/4 dashboard & dismiss UI remaining  
> **Created**: 2026-02-21  
> **Last audited**: 2026-02-22  
> **Scope**: MedicationChartController, NursingWorkbenchController (injections), workbench.blade.php, nurse_chart_medication_enhanced.blade.php  
> **Plan ref**: `NURSE_DRUG_SOURCE_REVAMP_PLAN.md`

---

## 1. Executive Summary

### 1.1 Current Problem
Today, when a nurse administers a medication (via the Medication Chart) or gives an injection (via the Injection panel), the system **requires selecting a store** and **deducts stock from that store** in real time. This is incorrect because:

1. **The real-world flow** is: Doctor prescribes → Patient goes to Billing/HMO → Patient pays → Patient goes to Pharmacy → Pharmacy dispenses from store → **Patient arrives at ward with their own dispensed medications** → Nurse administers from the patient's dispensed supply.
2. The nurse should **never be selecting a store or deducting stock** — the stock was already deducted by Pharmacy at dispense time.
3. There is no visibility into what the patient has actually **paid for and collected** from pharmacy.
4. Patients sometimes buy drugs **from outside** the hospital pharmacy, and there's no way to record or chart those.

### 1.2 Proposed Change
Replace the store-based stock deduction in the nursing medication chart and injection flows with a **prescription-sourced drug list** that shows what the patient has been prescribed, their payment/dispensing status, and allows charting from:

| Source | Stock Impact | When |
|--------|-------------|------|
| **Pharmacy-dispensed** | None (already deducted by pharmacy) | Normal flow |
| **Patient-purchased externally** | None (not our inventory) | Patient brings own drugs |
| **Ward stock (vaccines only)** | Deducts from ward store | Immunization / emergency |

### 1.3 Global Practice Validation

This revamp aligns with established **eMAR (Electronic Medication Administration Record)** standards and global best practices:

| Standard / Practice | Alignment |
|---|---|
| **Closed-Loop Medication Management (CLMM)** | ✅ Prescribe → Verify → Dispense → Administer — the nurse administers from dispensed supply, not raw store stock |
| **ISMP (Institute for Safe Medication Practices)** | ✅ Recommends nurses verify the medication was dispensed before charting — prevents administering un-verified drugs |
| **Joint Commission (USA) / NABH (India) / NHIA (Ghana)** | ✅ All require medication reconciliation — tracking the source of every drug administered (hospital pharmacy vs. patient's own) |
| **NHS (UK) eMAR systems** | ✅ "Patient's Own Drugs (PODs)" is a formal category — saves cost, reduces waste. UK hospitals actively encourage administering patient's own supply |
| **WHO High 5s — Medication Accuracy at Transitions** | ✅ Reconciliation of what was prescribed, dispensed, and administered reduces errors at care transitions |
| **JCI Standard MMU.6** | ✅ The hospital identifies patients' medications brought into the hospital and documents the decision about their use |

**Key insight**: The user's request is not just valid — it represents the **correct** clinical workflow that major accreditation bodies mandate. The current implementation (nurse picks from store) is actually an anti-pattern that:
- Creates **phantom stock movements** (stock deducted twice — once by pharmacy, once by nurse)
- Provides **no visibility** into what the patient actually has
- Makes **medication reconciliation impossible** (which drug did the patient actually receive?)

---

## 2. Current System Analysis

### 2.1 Current Flow (As-Is)

```
Doctor prescribes (ProductRequest status=1)
    ↓
Pharmacist bills (ProductRequest status=2, creates ProductOrServiceRequest)
    ↓
Patient pays (ProductOrServiceRequest.payment_id set)
    ↓
Pharmacist dispenses from store (ProductRequest status=3, stock deducted from pharmacy store)
    ↓
Nurse creates schedule (MedicationSchedule → links to ProductOrServiceRequest)
    ↓
Nurse administers on schedule:
    ├── Selects a store (any active store)         ← PROBLEM
    ├── Selects a batch (FIFO or manual)           ← PROBLEM
    ├── Stock deducted AGAIN (qty=1 hardcoded)     ← PROBLEM (double stock hit)
    └── Creates MedicationAdministration
```

### 2.2 Key Tables Involved

| Table | Key Columns | Current Role |
|-------|-------------|-------------|
| `product_requests` | `product_id`, `qty`, `dose`, `status` (0=dismissed, 1=requested, 2=billed, 3=dispensed), `product_request_id` (FK→POSR.id), `dispensed_from_store_id`, `dispensed_from_batch_id`, `original_product_id`, `adapted_from_product_id`, `is_adapted`, `qty_adjusted_from`, `refund_amount`, `return_reason` | Prescription record |
| `product_or_service_requests` | `product_id`, `qty`, `payment_id`, `payable_amount`, `claims_amount`, `coverage_mode`, `validation_status`, `dispensed_from_store_id`, `hmo_id`, `auth_code`, `hmo_remittance_id` | Billing / financial record |
| `medication_schedules` | `patient_id`, `product_or_service_request_id`, `scheduled_time`, `dose`, `route`, `created_by` | Nurse scheduling |
| `medication_administrations` | `patient_id`, `product_or_service_request_id`, `schedule_id`, `dose`, `route`, `store_id`, `dispensed_from_batch_id`, **`drug_source`**, **`product_request_id`**, **`external_drug_name`**, **`external_qty`**, **`external_batch_number`**, **`external_expiry_date`**, **`external_source_note`**, `edited_by`, `edited_at`, `edit_reason`, `previous_data` | Administration record |
| `injection_administrations` | `patient_id`, `product_id`, `product_or_service_request_id`, `dose`, `route`, `site`, `dispensed_from_store_id`, **`drug_source`**, **`product_request_id`**, **`external_drug_name`**, **`external_qty`**, **`external_batch_number`**, **`external_expiry_date`**, **`external_source_note`**, `notes`, `batch_number`, `expiry_date` | Injection record |

> **Note**: Bold columns are the new drug-source columns added by this revamp. They already exist in the DB as of 2026-02-22.

### 2.3 Controllers Involved

| Controller | Methods | Actual Line | Current Behavior |
|---|---|---|---|
| `MedicationChartController` | `administer()` | **L497** | ✅ REVAMPED — accepts `drug_source`, only deducts stock for `ward_stock`. `store_id` required only for `ward_stock`. |
| `MedicationChartController` | `getPatientPrescribedDrugs()` | **L25** | ✅ IMPLEMENTED — returns patient prescriptions grouped by status with payment/dispense info |
| `MedicationChartController` | `nurseDismissPrescription()` | **L108** | ✅ IMPLEMENTED — dismisses undispensed prescriptions |
| `NursingWorkbenchController` | `administerInjection()` | **L728** | ✅ REVAMPED — accepts `drug_source`, only deducts stock for `ward_stock`. Creates POSR+billing only for `ward_stock`. |
| `NursingWorkbenchController` | `administerFromScheduleNew()` | **L2393** | Vaccine/immunization — `store_id` nullable, stock deducted only if store provided. Creates `ImmunizationRecord` (not `InjectionAdministration`). |
| `PharmacyWorkbenchController` | `dispenseMedication()` | **L898** | Unchanged — deducts stock, sets `ProductRequest.status=3`. Two-phase validate→execute with batch-aware FIFO. |
| `ProductRequestController` | `dismissAjax()` | **L682** | Unchanged — sets `ProductRequest.status=0` for array of IDs. |

### 2.4 Identified Bugs in Current System — STATUS

1. ~~**Double stock deduction**~~ — ✅ **FIXED**. `administer()` and `administerInjection()` now only deduct stock for `drug_source='ward_stock'`. Pharmacy-dispensed and patient-own paths have zero stock impact.
2. ~~**Missing store_id in JS**~~ — ✅ **FIXED**. The JS in `nurse_chart_scripts_enhanced.blade.php` (line ~1693) now sends `drug_source` and conditionally includes `store_id` only for `ward_stock`, `product_request_id` for `pharmacy_dispensed`, and `external_*` fields for `patient_own`.
3. **Hardcoded qty=1** — ⚠️ **STILL PRESENT** in `ward_stock` path only. When `drug_source='ward_stock'`, stock deduction is still `qty=1` regardless of actual dosage. Low priority since ward stock is vaccines/emergency only.
4. ~~**No patient medication visibility**~~ — ✅ **FIXED** (backend). `getPatientPrescribedDrugs()` endpoint exists at `GET patients/{patient}/prescribed-drugs`. However, the **Prescription Status Dashboard UI widget** that consumes this API is **NOT YET BUILT** (see §11).

---

## 3. Proposed Flow (To-Be)

### 3.1 New Medication Administration Flow

```
Doctor prescribes (ProductRequest status=1)
    ↓
Pharmacist bills + Patient pays + Pharmacist dispenses (status=3, stock deducted ONCE at pharmacy)
    ↓
Nurse opens Medication Chart for patient:
    ├── System shows list of PRESCRIBED drugs grouped by status:
    │   ├─ 🟢 Dispensed (status=3) — ready to chart, qty X dispensed
    │   ├─ 🟡 Billed, awaiting payment (status=2, payment_id=null) — show "Awaiting Payment"
    │   ├─ 🟠 Billed, paid, awaiting pharmacy (status=2, payment_id≠null) — show "Awaiting Pharmacy"
    │   ├─ 🔴 Requested, not yet billed (status=1) — show "Awaiting Billing"
    │   └─ ⚪ Dismissed (status=0/deleted) — hidden or shown as cancelled
    ↓
Nurse creates schedule from DISPENSED prescriptions:
    ├── Drug source = "Pharmacy Dispensed" (default)
    ├── No store selection needed
    ├── No stock deduction at administration
    ├── Qty tracking: dispensed_qty vs administered_count
    └── System tracks remaining doses
    ↓
OR: Nurse records "Patient-Purchased Externally":
    ├── Drug source = "Patient's Own"
    ├── Nurse enters drug name, qty brought, batch/expiry if available
    ├── Optionally: dismiss matching prescription (like pharmacy dismiss)
    ├── No stock deduction
    └── Audit trail of external medication
    ↓
EXCEPTION: Vaccines / Emergency:
    ├── Drug source = "Ward Stock"
    ├── Store selection required (ward store only)
    ├── Stock deduction from ward store (existing logic preserved)
    └── Billing created automatically
```

### 3.2 New Injection Flow

```
For prescribed injectables (status=3, dispensed):
    ├── Nurse selects from dispensed list → no store, no stock deduction
    └── Creates InjectionAdministration with source="pharmacy_dispensed"

For patient-purchased injectables:
    ├── Nurse records external source → no store, no stock deduction
    └── Creates InjectionAdministration with source="patient_own"

For vaccines / ward-stock injectables (ONLY exception):
    ├── Nurse selects ward store → stock deducted
    └── Creates InjectionAdministration + POSR (billing) as today
```

---

## 4. Detailed Technical Design

### 4.1 New `drug_source` Enum

Add a `drug_source` column to both `medication_administrations` and `injection_administrations`:

```php
// Values:
'pharmacy_dispensed'  // Drug came from hospital pharmacy (dispensed via ProductRequest)
'patient_own'         // Patient purchased externally and brought their own
'ward_stock'          // Taken from ward/unit store (vaccines, emergencies)
```

### 4.2 Schema Changes

#### Migration: `add_drug_source_columns`

```php
// medication_administrations
Schema::table('medication_administrations', function (Blueprint $table) {
    $table->enum('drug_source', ['pharmacy_dispensed', 'patient_own', 'ward_stock'])
          ->default('pharmacy_dispensed')
          ->after('administered_by');
    $table->unsignedBigInteger('product_request_id')->nullable()->after('drug_source');
    $table->string('external_drug_name')->nullable()->after('product_request_id');
    $table->decimal('external_qty', 8, 2)->nullable()->after('external_drug_name');
    $table->string('external_batch_number', 50)->nullable()->after('external_qty');
    $table->date('external_expiry_date')->nullable()->after('external_batch_number');
    $table->text('external_source_note')->nullable()->after('external_expiry_date');
    
    // Make store_id nullable (was required — only needed for ward_stock)
    $table->unsignedBigInteger('store_id')->nullable()->change();
    // Make dispensed_from_batch_id nullable (already is, but confirm)

    // FK
    $table->foreign('product_request_id')->references('id')->on('product_requests')->onDelete('set null');
});

// injection_administrations
Schema::table('injection_administrations', function (Blueprint $table) {
    $table->enum('drug_source', ['pharmacy_dispensed', 'patient_own', 'ward_stock'])
          ->default('pharmacy_dispensed')
          ->after('administered_by');
    $table->unsignedBigInteger('product_request_id')->nullable()->after('drug_source');
    $table->string('external_drug_name')->nullable()->after('product_request_id');
    $table->decimal('external_qty', 8, 2)->nullable()->after('external_drug_name');
    $table->string('external_batch_number', 50)->nullable()->after('external_qty');
    $table->date('external_expiry_date')->nullable()->after('external_batch_number');
    $table->text('external_source_note')->nullable()->after('external_expiry_date');

    // Make dispensed_from_store_id nullable (keep for ward_stock)
    // It's already nullable in the original migration

    // FK
    $table->foreign('product_request_id')->references('id')->on('product_requests')->onDelete('set null');
});
```

### 4.3 Backend Changes

#### 4.3.1 New Endpoint: Get Patient's Prescription Drug List

**Route**: `GET /patients/{patient}/prescribed-drugs`  
**Controller**: `MedicationChartController::getPatientPrescribedDrugs($patientId)`

Returns ALL `ProductRequest` records for the patient, grouped by status, with payment and dispense info:

```php
public function getPatientPrescribedDrugs($patientId)
{
    $prescriptions = ProductRequest::with([
        'product:id,product_name,product_code',
        'productOrServiceRequest:id,payment_id,payable_amount,claims_amount,coverage_mode,validation_status',
        'productOrServiceRequest.payment:id',
        'doctor:id,name',
    ])
    ->where('patient_id', $patientId)
    ->whereIn('status', [1, 2, 3]) // exclude dismissed
    ->orderByDesc('created_at')
    ->get()
    ->map(function ($rx) {
        // Count how many times this prescription's POSR has been administered
        $adminCount = MedicationAdministration::where('product_or_service_request_id', $rx->product_request_id)
            ->whereNull('deleted_at')
            ->count();

        return [
            'id' => $rx->id,
            'product_id' => $rx->product_id,
            'product_name' => $rx->product->product_name ?? 'Unknown',
            'product_code' => $rx->product->product_code ?? '',
            'qty_prescribed' => $rx->qty,
            'dose' => $rx->dose,
            'doctor_name' => $rx->doctor->name ?? '',
            'prescribed_at' => $rx->created_at,

            // Status pipeline
            'status' => $rx->status,   // 1=requested, 2=billed, 3=dispensed
            'status_label' => match($rx->status) {
                1 => 'Awaiting Billing',
                2 => optional($rx->productOrServiceRequest)->payment_id
                    ? 'Awaiting Pharmacy'
                    : (optional($rx->productOrServiceRequest)->validation_status === 'validated'
                        ? 'Awaiting Pharmacy (HMO Validated)'
                        : 'Awaiting Payment'),
                3 => 'Dispensed',
                default => 'Unknown',
            },
            'status_color' => match($rx->status) {
                1 => 'danger',         // red - not yet billed
                2 => optional($rx->productOrServiceRequest)->payment_id ? 'warning' : 'secondary',
                3 => 'success',        // green - dispensed
                default => 'light',
            },

            // Financial
            'is_paid' => optional($rx->productOrServiceRequest)->payment_id !== null,
            'is_hmo_validated' => optional($rx->productOrServiceRequest)->validation_status === 'validated',
            'payable_amount' => optional($rx->productOrServiceRequest)->payable_amount,
            'claims_amount' => optional($rx->productOrServiceRequest)->claims_amount,
            'coverage_mode' => optional($rx->productOrServiceRequest)->coverage_mode,

            // Dispensing
            'is_dispensed' => $rx->status === 3,
            'dispensed_from_store' => $rx->dispensedFromStore->store_name ?? null,
            'dispense_date' => $rx->dispense_date,

            // Administration tracking
            'times_administered' => $adminCount,
            'remaining_doses' => max(0, $rx->qty - $adminCount),
            'is_fully_administered' => $adminCount >= $rx->qty,

            // Can chart from this?
            'can_chart' => $rx->status === 3, // Only dispensed drugs can be charted
        ];
    });

    return response()->json([
        'success' => true,
        'prescriptions' => $prescriptions,
        'summary' => [
            'total' => $prescriptions->count(),
            'dispensed' => $prescriptions->where('is_dispensed', true)->count(),
            'awaiting_payment' => $prescriptions->where('status', 2)->where('is_paid', false)->count(),
            'awaiting_pharmacy' => $prescriptions->where('status', 2)->where('is_paid', true)->count(),
            'awaiting_billing' => $prescriptions->where('status', 1)->count(),
        ],
    ]);
}
```

#### 4.3.2 Modified `MedicationChartController::administer()`

**Key changes:**
- Accept `drug_source` parameter (`pharmacy_dispensed` | `patient_own` | `ward_stock`)
- Accept `product_request_id` for `pharmacy_dispensed` source
- Accept `external_*` fields for `patient_own` source
- Only deduct stock if `drug_source === 'ward_stock'`
- Make `store_id` required only for `ward_stock`

```php
// New validation rules (replacing current):
$rules = [
    'schedule_id'       => 'required|exists:medication_schedules,id',
    'administered_at'   => 'required|date',
    'administered_dose' => 'required|string|max:100',
    'route'             => 'required|string|max:50',
    'comment'           => 'nullable|string|max:500',
    'drug_source'       => 'required|in:pharmacy_dispensed,patient_own,ward_stock',
    'product_request_id'=> 'required_if:drug_source,pharmacy_dispensed|nullable|exists:product_requests,id',
    
    // Ward stock only
    'store_id'          => 'required_if:drug_source,ward_stock|nullable|exists:stores,id',
    'batch_id'          => 'nullable|exists:stock_batches,id',
    'product_id'        => 'nullable|exists:products,id',

    // Patient's own
    'external_drug_name'     => 'required_if:drug_source,patient_own|nullable|string|max:255',
    'external_qty'           => 'required_if:drug_source,patient_own|nullable|numeric|min:0.01',
    'external_batch_number'  => 'nullable|string|max:50',
    'external_expiry_date'   => 'nullable|date',
    'external_source_note'   => 'nullable|string|max:500',
];

// Stock deduction logic:
if ($data['drug_source'] === 'ward_stock') {
    // EXISTING LOGIC: deduct from selected store (vaccines, emergencies)
    $stockService->dispenseStock(...);
} elseif ($data['drug_source'] === 'pharmacy_dispensed') {
    // NO stock deduction — pharmacy already deducted
    // Validate that the ProductRequest is actually dispensed (status=3)
    $productRequest = ProductRequest::findOrFail($data['product_request_id']);
    if ($productRequest->status !== 3) {
        return response()->json(['error' => 'This prescription has not been dispensed yet'], 422);
    }
} elseif ($data['drug_source'] === 'patient_own') {
    // NO stock deduction — patient brought their own
}
```

#### 4.3.3 Modified `NursingWorkbenchController::administerInjection()`

Same three-source pattern. Key change: `store_id` only required for `ward_stock`.

#### 4.3.4 New Endpoint: Nurse Dismiss Prescription

**Route**: `POST /patients/{patient}/dismiss-prescription`  
**Controller**: `MedicationChartController::nurseDismissPrescription()`

Allows nurses to dismiss a prescription (same as pharmacy dismiss — sets `ProductRequest.status = 0`), for cases where:
- Patient bought drug outside and doesn't need the hospital prescription
- Doctor cancelled but prescription wasn't formally dismissed

```php
public function nurseDismissPrescription(Request $request)
{
    $validated = $request->validate([
        'product_request_id' => 'required|exists:product_requests,id',
        'reason' => 'required|string|max:500',
    ]);

    $rx = ProductRequest::findOrFail($validated['product_request_id']);
    
    // Can only dismiss if NOT dispensed
    if ($rx->status === 3) {
        return response()->json(['error' => 'Cannot dismiss an already dispensed prescription'], 422);
    }

    $rx->update([
        'status' => 0,
        'deleted_by' => Auth::id(),
        'deletion_reason' => 'Nurse dismissed: ' . $validated['reason'],
    ]);

    return response()->json(['success' => true, 'message' => 'Prescription dismissed']);
}
```

### 4.4 Frontend Changes

#### 4.4.1 Medication Chart — Drug Source Selector

Replace the current store dropdown in the administer modal with a **three-tab drug source selector**:

```html
<!-- Drug Source Tabs (replaces store dropdown) -->
<ul class="nav nav-pills nav-fill mb-3" id="drug-source-tabs">
    <li class="nav-item">
        <button class="nav-link active" data-source="pharmacy_dispensed">
            <i class="fa fa-prescription-bottle"></i> Pharmacy Dispensed
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-source="patient_own">
            <i class="fa fa-user-md"></i> Patient's Own
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-source="ward_stock">
            <i class="fa fa-warehouse"></i> Ward Stock
            <small class="d-block text-muted">(Vaccines only)</small>
        </button>
    </li>
</ul>
```

**Tab 1 — Pharmacy Dispensed (default):**
- Shows the dispensed drug auto-linked from the schedule's ProductOrServiceRequest → ProductRequest
- Shows: drug name, qty dispensed, qty administered so far, remaining doses
- No store dropdown needed
- Simple "Administer" button

**Tab 2 — Patient's Own:**
- Fields: drug name (pre-filled from prescription, editable), qty patient brought, batch number (optional), expiry (optional), source note
- Option to "Record & Dismiss Prescription" (dismisses matching ProductRequest)

**Tab 3 — Ward Stock (Vaccines only):**
- Existing store + batch selection flow preserved
- Intended for vaccines, emergency meds from ward stock

#### 4.4.2 Injection Panel — Drug Source Selector

Replace the store dropdown at the top of the injection panel with the same three-tab pattern. The drug search changes:

**Current**: Nurse searches all products → selects store → checks stock → administers  
**New**: Nurse sees a list of **dispensed injectable prescriptions** + option to add patient's own + option to use ward stock

#### 4.4.3 Prescription Status Dashboard (New Widget)

Add a new section to the Medication Chart page showing the patient's prescription pipeline:

```
╔══════════════════════════════════════════════════════════════╗
║  Patient's Prescriptions                                     ║
╠══════════════════════════════════════════════════════════════╣
║  🟢 Dispensed (Ready to Chart)                               ║
║  ┌─────────────────┬────────┬──────────┬─────────────────┐   ║
║  │ Drug            │ Qty    │ Used/Rem │ Action           │   ║
║  ├─────────────────┼────────┼──────────┼─────────────────┤   ║
║  │ Amoxicillin 500 │ 15     │ 3 / 12   │ [Schedule]      │   ║
║  │ Metformin 500   │ 60     │ 10 / 50  │ [Schedule]      │   ║
║  └─────────────────┴────────┴──────────┴─────────────────┘   ║
║                                                               ║
║  🟡 Awaiting Payment                                         ║
║  ┌─────────────────┬────────┬───────────────────────────┐    ║
║  │ Ibuprofen 400   │ 20     │ ₦4,800 unpaid             │    ║
║  └─────────────────┴────────┴───────────────────────────┘    ║
║                                                               ║
║  🟠 Awaiting Pharmacy (Paid)                                 ║
║  ┌─────────────────┬────────┬───────────────────────────┐    ║
║  │ Omeprazole 20   │ 30     │ Paid ✓ — awaiting pickup  │    ║
║  └─────────────────┴────────┴───────────────────────────┘    ║
║                                                               ║
║  + [Record Patient's Own Drug]   [Dismiss Prescription ▾]    ║
╚══════════════════════════════════════════════════════════════╝
```

---

## 5. Implementation Phases

### Phase 1: Schema & Backend Foundation ✅ COMPLETE
1. ✅ Migration created: `drug_source`, `product_request_id`, `external_*` columns on both tables — columns verified in DB
2. ✅ `MedicationAdministration` model: 20 fillable columns including `drug_source`, `product_request_id`, all `external_*` fields, `productRequest()` relationship
3. ✅ `InjectionAdministration` model: 17 fillable columns including `drug_source`, `product_request_id`, all `external_*` fields, `productRequest()` relationship
4. ✅ `getPatientPrescribedDrugs()` at MedicationChartController L25 — route: `GET patients/{patient}/prescribed-drugs`
5. ✅ `nurseDismissPrescription()` at MedicationChartController L108 — route: `POST patients/{patient}/dismiss-prescription`
6. ✅ Routes registered in `routes/nurse_chart.php`

### Phase 2: Modify Administration Logic ✅ COMPLETE
1. ✅ `MedicationChartController::administer()` L497 — three-source branching with conditional validation
2. ✅ `NursingWorkbenchController::administerInjection()` L728 — three-source branching with per-product external fields
3. ✅ `administerFromScheduleNew()` L2393 — unchanged, vaccine-only, `store_id` nullable
4. ✅ JS bug fixed — `adminData` now sends `drug_source` + conditional fields (nurse_chart_scripts_enhanced.blade.php L1693)
5. ✅ Administration qty tracking via `times_administered` count in `getPatientPrescribedDrugs()`

### Phase 3: Medication Chart UI Revamp — PARTIALLY COMPLETE
1. ❌ **NOT BUILT**: Prescription Status Dashboard widget (§4.4.3) — backend API exists but no UI consumes it
2. ✅ Drug source tabs in administer modal (nurse_chart_medication_enhanced.blade.php L736)
3. ✅ "Pharmacy Dispensed" tab with dispensed Rx dropdown
4. ✅ "Patient's Own" tab with `external_*` fields
5. ✅ "Ward Stock" tab with store/batch selectors
6. ❌ **NOT BUILT**: Dismiss Prescription button/modal in UI — backend endpoint exists but no button or JS handler

### Phase 4: Injection Panel UI Revamp ✅ COMPLETE
1. ✅ Dispensed prescriptions dropdown in injection panel (workbench.blade.php L4447)
2. ✅ "Patient's Own" option with external fields (workbench.blade.php L4469)
3. ✅ "Ward Stock" tab for vaccines (workbench.blade.php L4490)
4. ✅ Store dropdown moved inside ward_stock section only

### Phase 5: Testing & Edge Cases — NOT STARTED
1. ⬜ Test backward compatibility — old administrations (without drug_source) default to 'pharmacy_dispensed'
2. ⬜ Test HMO patients — validate payment check works correctly
3. ⬜ Test partial administration (10 dispensed, 3 used, 7 remaining)
4. ⬜ Test dismiss flow — ensure matching pharmacy dismiss behavior
5. ⬜ Test external drug + dismiss combo

---

## 6. Additional Suggestions

### 6.1 Dose Tracking & Alerts
- When `remaining_doses` reaches 0, show **"Prescription Exhausted"** alert
- When `remaining_doses` ≤ 3, show **"Running Low — Contact Pharmacy"** warning
- Prevent over-administration: if `administered_count >= qty_dispensed`, require nurse to confirm override

### 6.2 Refill Workflow
- When a prescription's doses are exhausted but the course isn't complete, add a **"Request Refill"** button
- This creates a new `ProductRequest` (status=1) with the same drug/dose, linking back to the original
- Follows the normal billing → pharmacy → dispense flow

### 6.3 Medication Reconciliation Report
- Add a report showing: Prescribed vs Dispensed vs Administered vs Remaining
- Flags discrepancies (e.g., prescribed 30, dispensed 30, only 25 administered — 5 unaccounted)
- Required by JCI Standard MMU.7 and NABH

### 6.4 Bar Code / QR Verification (Future)
- Pharmacy prints barcode on dispensed pack
- Nurse scans before administration — system auto-matches to ProductRequest
- Full closed-loop verification per ISMP recommendations

### 6.5 Standing Orders / PRN Handling
- Some medications are PRN (as needed) — no fixed schedule
- Allow nurse to administer from dispensed supply without a schedule
- Record PRN reason and clinical indication

### 6.6 Multi-Dose Vial Tracking
- Some injectables (insulin, vaccines) come in multi-dose vials
- Track vial opening date and discard-after date
- When `drug_source='ward_stock'`, track the vial across multiple patients

### 6.7 Audit Trail Enhancement
- Every source change (pharmacy → patient_own → ward_stock) should be logged
- Include who recorded external drugs and any dismiss actions
- Visible in patient's medication history

---

## 7. Database Impact Summary

### New Columns
| Table | Column | Type | Default | Notes |
|-------|--------|------|---------|-------|
| `medication_administrations` | `drug_source` | enum | `'pharmacy_dispensed'` | Source of the drug |
| `medication_administrations` | `product_request_id` | FK nullable | null | Links to the specific prescription |
| `medication_administrations` | `external_drug_name` | varchar(255) nullable | null | For patient_own source |
| `medication_administrations` | `external_qty` | decimal(8,2) nullable | null | Qty patient brought |
| `medication_administrations` | `external_batch_number` | varchar(50) nullable | null | Optional batch tracking |
| `medication_administrations` | `external_expiry_date` | date nullable | null | Optional expiry tracking |
| `medication_administrations` | `external_source_note` | text nullable | null | Where patient bought it |
| `injection_administrations` | `drug_source` | enum | `'pharmacy_dispensed'` | Source of the drug |
| `injection_administrations` | `product_request_id` | FK nullable | null | Links to the specific prescription |
| `injection_administrations` | `external_drug_name` | varchar(255) nullable | null | For patient_own source |
| `injection_administrations` | `external_qty` | decimal(8,2) nullable | null | Qty patient brought |
| `injection_administrations` | `external_batch_number` | varchar(50) nullable | null | Optional batch tracking |
| `injection_administrations` | `external_expiry_date` | date nullable | null | Optional expiry tracking |
| `injection_administrations` | `external_source_note` | text nullable | null | Where patient bought it |

### Modified Columns
| Table | Column | Change |
|-------|--------|--------|
| `medication_administrations` | `store_id` | Remains nullable (already is after migration) — only populated for `ward_stock` |
| `medication_administrations` | `dispensed_from_batch_id` | Remains nullable — only populated for `ward_stock` |

### No Columns Removed
Backward compatible — all existing data preserved with `drug_source` defaulting to `'pharmacy_dispensed'` (retroactively most accurate assumption, since the nurse *intended* to administer from the patient's supply even though stock was incorrectly deducted).

---

## 8. Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|-----------|
| Existing administrations have `store_id` + batch deductions | Historical data shows phantom stock movements | Default `drug_source='pharmacy_dispensed'` for existing rows; do NOT attempt to reverse old stock deductions (too risky) |
| Nurses accustomed to store selection | Training needed | Phase 3 UI makes "Pharmacy Dispensed" the default/easiest path; Ward Stock is relegated to a third tab |
| Vaccine workflow disruption | Ward stock is critical for vaccines | `ward_stock` source preserves 100% of existing vaccine logic |
| Over-administration with no stock guard | Without store stock as a safety check, nurse could administer more than dispensed | New `remaining_doses` tracking prevents this — soft block with override option |
| Edge case: drug dispensed by pharmacy but patient lost it | Need to re-dispense or buy externally | Nurse can either: (a) pharmacy re-dispenses (new ProductRequest), or (b) record as "patient's own" if bought outside |

---

## 9. Files Modified — Status

| File | Changes | Status |
|------|---------|--------|
| `database/migrations/*_add_drug_source_columns.php` | New columns on both tables | ✅ Migrated |
| `app/Models/MedicationAdministration.php` | 20 fillable cols, `productRequest()`, `store()`, `dispensedFromBatch()`, audit traits | ✅ Done |
| `app/Models/InjectionAdministration.php` | 17 fillable cols, `productRequest()`, `dispensedFromStore()`, SoftDeletes, audit | ✅ Done |
| `app/Http/Controllers/MedicationChartController.php` | `administer()` L497 (3-source), `getPatientPrescribedDrugs()` L25, `nurseDismissPrescription()` L108 | ✅ Done |
| `app/Http/Controllers/NursingWorkbenchController.php` | `administerInjection()` L728 (3-source) | ✅ Done |
| `routes/nurse_chart.php` | `prescribed-drugs`, `dismiss-prescription` routes | ✅ Done |
| `resources/views/admin/patients/partials/nurse_chart_medication_enhanced.blade.php` | Drug source tabs in administer modal | ✅ Done |
| `resources/views/admin/patients/partials/nurse_chart_scripts_enhanced.blade.php` | JS: `drug_source` conditional payload, store_id bug fixed | ✅ Done |
| `resources/views/admin/nursing/workbench.blade.php` | Injection panel: drug source tabs, dispensed Rx picker | ✅ Done |
| `resources/views/admin/patients/partials/nurse_chart_medication_enhanced.blade.php` | Prescription Status Dashboard widget | ❌ Not built |
| `resources/views/admin/patients/partials/nurse_chart_scripts_enhanced.blade.php` | Dismiss Prescription button + modal + JS | ❌ Not built |

---

## 10. Success Criteria

- [x] Nurse can administer a medication charted from a **pharmacy-dispensed** prescription with zero stock deduction
- [ ] Nurse can see all prescriptions with **payment/dispensing status** badges ← **Needs Prescription Status Dashboard UI (§11 gap #1)**
- [x] Nurse can record administration of a **patient-purchased external** drug
- [ ] Nurse can **dismiss** an undispensed prescription with a reason ← **Backend done, needs UI (§11 gap #2)**
- [x] Vaccine/ward stock flow remains **unchanged** and fully functional
- [x] Remaining doses are tracked: `dispensed_qty - administered_count` (in `getPatientPrescribedDrugs()` API)
- [x] No double stock deduction occurs in any pathway
- [x] Historical data is preserved with sensible defaults (`drug_source` defaults to `'pharmacy_dispensed'`)

---

## 11. Remaining Gaps (Identified 2026-02-22 Audit)

### Gap 1: Prescription Status Dashboard Widget — NOT BUILT
**Plan reference**: §4.4.3  
**Backend**: `getPatientPrescribedDrugs()` API at `GET patients/{patient}/prescribed-drugs` — ✅ exists, returns prescriptions grouped by status with `status_label`, `status_color`, `remaining_doses`, `can_chart`  
**Frontend**: No UI widget exists. No Blade partial, no JS that calls the API to render a dashboard.  
**Impact**: Nurses cannot see the patient's prescription pipeline (dispensed/awaiting payment/awaiting pharmacy) at a glance.  
**Fix needed**: Build a collapsible card/widget in `nurse_chart_medication_enhanced.blade.php` that calls `GET patients/{patient}/prescribed-drugs` on load and renders the status-grouped table described in §4.4.3. Include action buttons: "Schedule" for dispensed items, "Dismiss" for undispensed items.

### Gap 2: Dismiss Prescription UI Button — NOT BUILT
**Plan reference**: §4.3.4  
**Backend**: `nurseDismissPrescription()` at `POST patients/{patient}/dismiss-prescription` — ✅ exists, validates `product_request_id` + `reason`, blocks if status=3  
**Frontend**: No button, no modal, no JS handler anywhere in the Blade views or scripts.  
**Impact**: The dismiss feature is unreachable by nurses.  
**Fix needed**: Add a "Dismiss" dropdown/button in the Prescription Status Dashboard (Gap 1) for items with `status < 3`. Show a confirmation modal with a required reason textarea. JS handler to POST to dismiss endpoint and refresh the dashboard.

### Gap 3: Standalone "Record Patient's Own Drug" Flow — PARTIAL
**Plan reference**: §3.1, §4.4.1 Tab 2  
**Current state**: "Patient's Own" tab exists **only inside the administer modal** (tied to a specific medication schedule). There is no standalone entry point to record that a patient brought an external drug **without** having a pre-existing schedule for it.  
**Impact**: If a patient arrives with a drug that was never prescribed in the system, the nurse has no way to record it outside the administration flow.  
**Workaround**: Nurse can create a schedule first, then administer with `patient_own` source. Not ideal but functional.  
**Fix needed** (low priority): Add a "Record External Drug" button in the Prescription Status Dashboard that creates a `MedicationAdministration` record with `drug_source='patient_own'` without requiring a schedule.

### Gap 4: Plan Line Numbers Stale
**Issue**: Several line numbers cited in §2.3 were incorrect:  
- `administer()` was listed as L390, actual is **L497**  
- `administerFromScheduleNew()` was listed as L2338, actual is **L2393**  
**Status**: ✅ **FIXED** in this audit — §2.3 table now shows correct line numbers.

### Gap 5: `administerFromScheduleNew()` Creates ImmunizationRecord, Not InjectionAdministration
**Plan says** (§2.3): Creates injection administration  
**Actual**: Creates an `ImmunizationRecord` and optionally a `ProductOrServiceRequest` for billing. This is a **separate model** from `InjectionAdministration`.  
**Impact**: Plan §5 Phase 2.3 says "keep mostly unchanged" which is correct — but the plan should clarify that this method targets the immunization subsystem, not the general injection flow.  
**Status**: ✅ **Clarified** in §2.3 table.

### Gap 6: `product_request_id` FK Naming Confusion
**Issue**: On the `product_requests` table, the column `product_request_id` is actually a **foreign key pointing TO `product_or_service_requests.id`** — not a self-reference. The name is misleading.  
**Relationship**: `ProductRequest::productOrServiceRequest()` → `belongsTo(POSR, 'product_request_id', 'id')`  
**Inverse**: `ProductOrServiceRequest::productRequest()` → `hasOne(ProductRequest, 'product_request_id', 'id')`  
**Impact**: No code bug, but developers reading the schema may misinterpret the FK direction. The plan's §2.2 table is correct — the FK is on `product_requests` side.
