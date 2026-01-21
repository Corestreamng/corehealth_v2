# Procedure Module & Registration Service Category - Design Plan

## Overview

This document outlines the implementation plan for two new features:
1. **Registration Service Category** - Auto-billing patients upon registration
2. **Procedure Module** - Comprehensive surgical/procedure management system

### Key Design Decision: Procedures as Services

**Approach:** Procedures are created as services that belong to the "Procedures" service category. This enables:
- Full integration with existing billing/HMO flows
- HMO tariffs can be configured on procedures like any other service
- Procedure-specific metadata (surgical flag, team roles, notes) stored in linked `procedures` table
- Same workbench/payment patterns already proven in labs, imaging, consultations

**Architecture:**
```
┌─────────────────────┐      ┌─────────────────────┐
│     services        │      │    procedures       │
├─────────────────────┤      ├─────────────────────┤
│ id                  │◄─────│ service_id (FK)     │
│ category_id (PROC)  │      │ procedure_category_id│
│ service_name        │      │ is_surgical         │
│ price_id            │      │ estimated_duration  │
│ status              │      │ ...                 │
└─────────────────────┘      └─────────────────────┘
                                      │
                                      ▼
                           ┌─────────────────────┐
                           │  patient_procedures │
                           ├─────────────────────┤
                           │ procedure_id (FK)   │
                           │ patient_id          │
                           │ encounter_id        │
                           │ service_request_id  │
                           │ team members...     │
                           │ notes...            │
                           └─────────────────────┘
```

When a service is created with the **Procedure** category, a dynamic form appears for procedure-specific fields.

---

## Part 1: Service Category Configuration

### 1.1 New Service Categories

Add two new service category IDs to `application_status` table:

| Field Name | Description | Default |
|------------|-------------|---------|
| `registration_category_id` | Service category for registration fees | TBD (seeded) |
| `procedure_category_id` | Service category for procedures | TBD (seeded) |

### 1.2 Migration

```php
// database/migrations/2026_01_19_000001_add_registration_and_procedure_category_settings.php
Schema::table('application_status', function (Blueprint $table) {
    $table->unsignedBigInteger('registration_category_id')->nullable()->after('imaging_category_id');
    $table->unsignedBigInteger('procedure_category_id')->nullable()->after('registration_category_id');
});
```

### 1.3 Seeder for Service Categories

```php
// database/seeders/ServiceCategorySeeder.php
// Create categories:
// - Registration (category_code: 'REG')
// - Procedures (category_code: 'PROC')

// After seeding, update application_status with the IDs:
$procedureCategory = ServiceCategory::where('category_code', 'PROC')->first();
$registrationCategory = ServiceCategory::where('category_code', 'REG')->first();

ApplicationStatu::first()->update([
    'procedure_category_id' => $procedureCategory->id,
    'registration_category_id' => $registrationCategory->id,
]);
```

### 1.4 Hospital Config UI Updates

Add to `resources/views/admin/hospital-config/index.blade.php` under Service Categories:
- Registration Category ID dropdown (populated from service_categories)
- Procedure Category ID dropdown (populated from service_categories)

---

## Part 2: Registration Auto-Billing

### 2.1 Patient Create Form Enhancement

**Locations (both need to be updated):**
1. `resources/views/admin/patients/create.blade.php` - Main patient creation page
2. `resources/views/admin/reception-workbench/partials/new_patient_modal.blade.php` - Reception workbench modal

Add optional field:
```html
<div class="col-md-6 mb-3">
    <label>Registration Fee (Optional)</label>
    <select name="registration_service_id" class="form-control select2">
        <option value="">-- No Registration Fee --</option>
        @foreach($registrationServices as $service)
            <option value="{{ $service->id }}">{{ $service->service_name }} - ₦{{ $service->price->sale_price }}</option>
        @endforeach
    </select>
</div>
```

### 2.2 PatientController Store Method Update

After patient creation, if `registration_service_id` is provided:

```php
if ($request->registration_service_id) {
    $service = Service::find($request->registration_service_id);
    
    // Create ProductOrServiceRequest for billing
    $reqEntry = ProductOrServiceRequest::create([
        'user_id' => $patient->user_id,
        'staff_user_id' => Auth::id(),
        'service_id' => $service->id,
        'qty' => 1,
        'payable_amount' => $service->price->sale_price,
        // Apply HMO tariff if applicable
        ...HmoHelper::applyHmoTariff($patient->id, null, $service->id) ?? []
    ]);
}
```

---

## Part 2.5: Services CRUD - Dynamic Procedure Form

### 2.5.1 Overview

When creating or editing a service, if the user selects the **Procedure** service category, additional procedure-specific fields appear dynamically.

### 2.5.2 Services Create/Edit View Enhancement

**Location:** `resources/views/admin/services/create.blade.php` and `edit.blade.php`

Add JavaScript to detect when procedure category is selected:

```html
<div class="col-md-6 mb-3">
    <label>Service Category <span class="text-danger">*</span></label>
    <select name="category_id" id="category_id" class="form-control select2" required>
        <option value="">-- Select Category --</option>
        @foreach($categories as $category)
            <option value="{{ $category->id }}" 
                data-is-procedure="{{ $category->id == $procedureCategoryId ? '1' : '0' }}">
                {{ $category->category_name }}
            </option>
        @endforeach
    </select>
</div>

<!-- Dynamic Procedure Fields (hidden by default) -->
<div id="procedure-fields" class="col-12" style="display: none;">
    <div class="card bg-light mb-3">
        <div class="card-header bg-primary text-white">
            <i class="fa fa-stethoscope mr-2"></i> Procedure Details
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label>Procedure Category <span class="text-danger">*</span></label>
                    <select name="procedure_category_id" class="form-control select2">
                        <option value="">-- Select Procedure Category --</option>
                        @foreach($procedureCategories as $procCat)
                            <option value="{{ $procCat->id }}">{{ $procCat->name }} ({{ $procCat->code }})</option>
                        @endforeach
                    </select>
                    <small class="text-muted">e.g., ENT, O&G, General Surgery, Dental</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label>Procedure Code</label>
                    <input type="text" name="procedure_code" class="form-control" placeholder="e.g., APND-001">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label>Is Surgical?</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="is_surgical" id="is_surgical" value="1">
                        <label class="form-check-label" for="is_surgical">Yes, this is a surgical procedure</label>
                    </div>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label>Estimated Duration (minutes)</label>
                    <input type="number" name="estimated_duration_minutes" class="form-control" placeholder="e.g., 60">
                </div>
                
                <div class="col-12 mb-3">
                    <label>Procedure Description</label>
                    <textarea name="procedure_description" class="form-control" rows="3" 
                        placeholder="Describe the procedure..."></textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const procedureCategoryId = {{ $procedureCategoryId ?? 'null' }};
    
    function toggleProcedureFields() {
        const selectedOption = $('#category_id option:selected');
        const isProcedure = selectedOption.data('is-procedure') == 1;
        
        if (isProcedure) {
            $('#procedure-fields').slideDown();
            $('select[name="procedure_category_id"]').prop('required', true);
        } else {
            $('#procedure-fields').slideUp();
            $('select[name="procedure_category_id"]').prop('required', false);
        }
    }
    
    $('#category_id').on('change', toggleProcedureFields);
    toggleProcedureFields(); // Initial check on page load (for edit)
});
</script>
```

### 2.5.3 ServiceController Store/Update Logic

When saving a service with procedure category, also create/update the linked procedure record:

```php
// In ServiceController::store() and update()

public function store(Request $request)
{
    $appStatus = ApplicationStatu::first();
    
    // ... existing validation and service creation ...
    
    $service = Service::create([...]);
    
    // If this is a procedure service, create linked procedure record
    if ($request->category_id == $appStatus->procedure_category_id) {
        Procedure::create([
            'service_id' => $service->id,
            'procedure_category_id' => $request->procedure_category_id,
            'name' => $service->service_name,
            'code' => $request->procedure_code,
            'description' => $request->procedure_description,
            'is_surgical' => $request->is_surgical ? true : false,
            'estimated_duration_minutes' => $request->estimated_duration_minutes,
            'status' => true,
        ]);
    }
    
    return redirect()->route('services.index')->with('success', 'Service created successfully');
}

public function update(Request $request, Service $service)
{
    $appStatus = ApplicationStatu::first();
    
    // ... existing validation and service update ...
    
    $service->update([...]);
    
    // Handle procedure record
    if ($request->category_id == $appStatus->procedure_category_id) {
        // Create or update linked procedure
        $procedure = Procedure::updateOrCreate(
            ['service_id' => $service->id],
            [
                'procedure_category_id' => $request->procedure_category_id,
                'name' => $service->service_name,
                'code' => $request->procedure_code,
                'description' => $request->procedure_description,
                'is_surgical' => $request->is_surgical ? true : false,
                'estimated_duration_minutes' => $request->estimated_duration_minutes,
                'status' => true,
            ]
        );
    } else {
        // If category changed away from procedure, soft delete the procedure record
        Procedure::where('service_id', $service->id)->delete();
    }
    
    return redirect()->route('services.index')->with('success', 'Service updated successfully');
}
```

### 2.5.4 ServiceController Create/Edit Method Updates

Pass necessary data to views:

```php
public function create()
{
    $appStatus = ApplicationStatu::first();
    
    return view('admin.services.create', [
        'categories' => ServiceCategory::active()->get(),
        'procedureCategories' => ProcedureCategory::active()->get(),
        'procedureCategoryId' => $appStatus->procedure_category_id,
        // ... existing data ...
    ]);
}

public function edit(Service $service)
{
    $appStatus = ApplicationStatu::first();
    $procedure = Procedure::where('service_id', $service->id)->first();
    
    return view('admin.services.edit', [
        'service' => $service,
        'procedure' => $procedure,
        'categories' => ServiceCategory::active()->get(),
        'procedureCategories' => ProcedureCategory::active()->get(),
        'procedureCategoryId' => $appStatus->procedure_category_id,
        // ... existing data ...
    ]);
}
```

---

## Part 3: Procedure Module

### 3.1 Database Schema

#### 3.1.1 `procedure_categories` Table
These are **procedure-specific** categories (ENT, Dental, O&G, etc.) - different from service categories.

```sql
CREATE TABLE procedure_categories (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,          -- e.g., "ENT", "Dental", "O&G", "General Surgery"
    code VARCHAR(20) NOT NULL,
    description TEXT,
    status BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 3.1.2 `procedures` Table (Procedure Catalog - Linked to Services)

**Key Design:** Each procedure is linked to a service in the services table. The service belongs to the "Procedures" service category. This enables full billing/HMO integration.

```sql
CREATE TABLE procedures (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    service_id BIGINT NOT NULL REFERENCES services(id) ON DELETE CASCADE,  -- Links to billing service
    procedure_category_id BIGINT REFERENCES procedure_categories(id),       -- ENT, Dental, O&G, etc.
    name VARCHAR(255) NOT NULL,                     -- Synced from service_name
    code VARCHAR(50),                               -- Procedure-specific code
    description TEXT,
    is_surgical BOOLEAN DEFAULT FALSE,              -- Surgical vs Non-Surgical
    estimated_duration_minutes INT,
    status BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE KEY unique_service (service_id)          -- One procedure per service
);
```

**Relationship Diagram:**
```
┌──────────────────────┐
│   service_categories │
├──────────────────────┤
│ id: 5                │
│ name: "Procedures"   │◄──────────────────────┐
└──────────────────────┘                       │
                                               │ category_id
┌──────────────────────┐      ┌────────────────┴───────┐
│ procedure_categories │      │       services         │
├──────────────────────┤      ├────────────────────────┤
│ id: 1                │      │ id: 100                │
│ name: "ENT"          │◄─────│ service_name: "Tonsil" │
│ code: "ENT"          │  FK  │ category_id: 5 (PROC)  │
└──────────────────────┘      │ price_id: ...          │
         ▲                    └────────────────────────┘
         │                               ▲
         │ procedure_                    │ service_id
         │ category_id                   │
         │                    ┌──────────┴─────────────┐
         └────────────────────│      procedures        │
                              ├────────────────────────┤
                              │ id: 1                  │
                              │ service_id: 100        │
                              │ procedure_category_id:1│
                              │ is_surgical: TRUE      │
                              │ estimated_duration: 90 │
                              └────────────────────────┘
```

#### 3.1.3 `patient_procedures` Table (Procedure Instance for Patient)
```sql
CREATE TABLE patient_procedures (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    procedure_id BIGINT REFERENCES procedures(id),
    patient_id BIGINT REFERENCES patients(id),
    encounter_id BIGINT REFERENCES encounters(id),
    admission_request_id BIGINT REFERENCES admission_requests(id),
    
    -- Service Request Entry (for billing the procedure itself)
    service_request_id BIGINT REFERENCES product_or_service_requests(id),
    
    -- Status tracking
    status ENUM('scheduled', 'pre_op', 'in_progress', 'post_op', 'completed', 'cancelled') DEFAULT 'scheduled',
    priority ENUM('routine', 'urgent', 'emergency') DEFAULT 'routine',
    
    -- Scheduling
    scheduled_date DATE,
    scheduled_time TIME,
    actual_start_time DATETIME,
    actual_end_time DATETIME,
    
    -- Location
    operating_room VARCHAR(100),
    
    -- Outcome
    outcome ENUM('successful', 'complications', 'aborted', 'converted') NULL,
    outcome_notes TEXT,
    
    -- Cancellation (when status = 'cancelled')
    cancellation_reason TEXT,
    refund_amount DECIMAL(15,2) DEFAULT 0,
    cancelled_at DATETIME NULL,
    cancelled_by BIGINT REFERENCES users(id),
    
    -- Metadata
    requested_by BIGINT REFERENCES users(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL
);
```

#### 3.1.4 `procedure_team_members` Table (Resource Persons)
```sql
CREATE TABLE procedure_team_members (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    patient_procedure_id BIGINT REFERENCES patient_procedures(id),
    user_id BIGINT REFERENCES users(id),             -- Staff member
    role VARCHAR(100) NOT NULL,                      -- Standard surgical roles or 'Other'
    custom_role VARCHAR(100) NULL,                   -- Custom role when role='Other'
    is_lead BOOLEAN DEFAULT FALSE,                   -- Lead surgeon/anesthetist
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Standard Surgical Team Roles (per international standards):**
- Chief Surgeon / Lead Surgeon
- Assistant Surgeon
- Anesthesiologist
- Nurse Anesthetist (CRNA)
- Scrub Nurse / Scrub Tech
- Circulating Nurse
- Surgical First Assistant
- Perfusionist (for cardiac)
- Radiologist (if imaging guidance needed)
- Pathologist (for frozen section)
- **Other** (allows custom role specification)

#### 3.1.5 `procedure_notes` Table (Pre-op and Post-op)
```sql
CREATE TABLE procedure_notes (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    patient_procedure_id BIGINT REFERENCES patient_procedures(id),
    note_type ENUM('pre_op', 'intra_op', 'post_op', 'anesthesia', 'nursing') NOT NULL,
    title VARCHAR(255),
    content LONGTEXT,                                -- LONGTEXT for CKEditor WYSIWYG content
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Note:** The `content` field uses `LONGTEXT` to accommodate rich HTML content from CKEditor WYSIWYG editor. Pre-op and post-op notes can be lengthy and include formatted text, lists, and embedded images.

#### 3.1.6 `procedure_items` Table (Bundled Items)
```sql
CREATE TABLE procedure_items (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    patient_procedure_id BIGINT REFERENCES patient_procedures(id),
    
    -- Item reference (one of these will be set)
    lab_service_request_id BIGINT REFERENCES lab_service_requests(id),
    imaging_service_request_id BIGINT REFERENCES imaging_service_requests(id),
    product_request_id BIGINT REFERENCES product_requests(id),
    misc_bill_id BIGINT REFERENCES misc_bills(id),
    
    -- Billing association
    is_bundled BOOLEAN DEFAULT FALSE,                -- TRUE = part of procedure fee, FALSE = billed separately
    product_or_service_request_id BIGINT REFERENCES product_or_service_requests(id),
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### 3.2 Billing Flow Design

#### 3.2.1 Non-Bundled Items (Standard Flow)
When `is_bundled = FALSE`:
- Item creates its own `ProductOrServiceRequest` entry
- Goes through normal billing queue
- HMO validation handled individually
- Delivered via respective workbench (Lab, Imaging, Pharmacy)

```
[Doctor adds Lab in Procedure] 
    → LabServiceRequest created
    → ProductOrServiceRequest created (linked to lab)
    → HMO tariff applied
    → Appears in billing queue
    → Lab workbench shows it for processing
```

#### 3.2.2 Bundled Items (Part of Procedure Fee)
When `is_bundled = TRUE`:
- Item links to the procedure's main `ProductOrServiceRequest`
- No separate billing entry
- HMO validation tied to procedure validation
- Still goes through delivery workbench

```
[Doctor adds Lab as Bundled]
    → LabServiceRequest created
    → procedure_items entry with is_bundled=TRUE
    → Links to procedure's ProductOrServiceRequest
    → Lab workbench shows it for processing (delivery allowed based on procedure payment)
```

#### 3.2.3 HMO Validation for Procedures
```
[Procedure Request Created]
    → ProductOrServiceRequest created for procedure service
    → HMO tariff applied (payable_amount, claims_amount, coverage_mode)
    → If primary/secondary → goes to HMO Executive for validation
    → Bundled items inherit this validation status
```

#### 3.2.4 Credit Payment Method (Patient Account)

> **IMPORTANT FINDING:** The patient account system already exists! No new tables needed.

**Existing Infrastructure:**
- `patient_accounts` table with `patient_id`, `balance` (can already go negative)
- `PatientAccount` model at `app/Models/PatientAccount.php`
- `payments` table tracks transactions via `payment_type`:
  - `ACC_DEPOSIT` - Deposits
  - `ACC_WITHDRAW` - Withdrawals/payments from account (negative total)
  - `ACC_ADJUSTMENT` - Balance adjustments
- Billing Workbench already has "Pay from Account Balance" option (currently hidden when balance ≤ 0)

**Changes Needed (Minimal):**

1. **Show account payment option always** (not just when balance > 0):
   ```javascript
   // In workbench.blade.php - updateAccountBalanceDisplays()
   // CHANGE FROM:
   if (balance > 0) {
       $('#account-payment-option').show();
   } else {
       $('#account-payment-option').hide();
   }
   
   // CHANGE TO:
   // Always show if patient has an account (allow credit)
   $('#account-payment-option').show();
   ```

2. **Remove balance check in controller:**
   ```php
   // In BillingWorkbenchController::processPayment()
   // CHANGE FROM:
   if ($data['payment_type'] === 'ACCOUNT') {
       $account = PatientAccount::where('patient_id', $patient->id)->first();
       if (!$account || $account->balance < $total) {
           throw new \Exception('Insufficient account balance...');
       }
       // Deduct from account
       $account->balance -= $total;
       $account->save();
   }
   
   // CHANGE TO:
   if ($data['payment_type'] === 'ACCOUNT') {
       $account = PatientAccount::where('patient_id', $patient->id)->first();
       if (!$account) {
           throw new \Exception('Patient does not have an account. Please create one first.');
       }
       // Deduct from account (can go negative - credit facility)
       $account->balance -= $total;
       $account->save();
   }
   ```

3. **Update UI validation to warn but allow:**
   ```javascript
   // In workbench.blade.php - confirmPayment()
   // CHANGE FROM blocking to warning:
   if (paymentType === 'ACCOUNT') {
       if (totalPayable > currentAccountBalance) {
           const deficit = totalPayable - currentAccountBalance;
           if (!confirm(`This will put the account ₦${deficit.toLocaleString()} into debit. Continue?`)) {
               return;
           }
       }
   }
   ```

**UI Display (existing, just needs unhiding):**
```
Payment Method: [Cash ▼]
                 Cash
                 Bank Transfer
                 POS
                 Pay from Account Balance  ← Already exists, just hidden
                 
When selected (enhanced messaging):
┌────────────────────────────────────────┐
│ Account Balance: ₦25,000               │
│ Amount to Pay: ₦35,000                 │
│ Balance After: -₦10,000 (Debit)        │
│ ──────────────────────────────────────│
│ ⚠️ Account will go into debit balance  │
└────────────────────────────────────────┘
```

#### 3.2.5 Procedure Cancellation with Refund

When cancelling a procedure that has already been paid for:

**Flow:**
1. User clicks "Cancel Procedure" on procedure detail page
2. System checks if payment has been made
3. If paid:
   - Modal prompts for refund amount (default = full paid amount)
   - User can adjust refund amount (partial refund allowed)
   - Refund is credited to patient's account balance (uses existing `ACC_DEPOSIT` mechanism)
4. Procedure status set to 'cancelled'
5. Associated bundled items are also cancelled

**Implementation:**
```php
// In PatientProcedureController::cancel()
public function cancel(Request $request, PatientProcedure $patientProcedure)
{
    $request->validate([
        'refund_amount' => 'nullable|numeric|min:0',
        'cancellation_reason' => 'required|string',
    ]);
    
    $serviceRequest = $patientProcedure->productOrServiceRequest;
    
    DB::beginTransaction();
    try {
        // If payment exists and refund requested
        if ($serviceRequest->payment_id && $request->refund_amount > 0) {
            $patient = $patientProcedure->patient;
            
            // Get or create patient account
            $account = PatientAccount::firstOrCreate(
                ['patient_id' => $patient->id],
                ['balance' => 0]
            );
            
            // Credit refund to patient account
            $account->balance += $request->refund_amount;
            $account->save();
            
            // Record refund as payment transaction (existing pattern)
            payment::create([
                'patient_id' => $patient->id,
                'user_id' => Auth::id(),
                'total' => $request->refund_amount,
                'reference_no' => generate_invoice_no(),
                'payment_type' => 'ACC_DEPOSIT', // Refund as deposit
                'payment_method' => 'REFUND',
            ]);
        }
        
        // Update procedure status
        $patientProcedure->update([
            'status' => 'cancelled',
            'cancellation_reason' => $request->cancellation_reason,
            'cancelled_at' => now(),
            'cancelled_by' => Auth::id(),
            'refund_amount' => $request->refund_amount ?? 0,
        ]);
        
        DB::commit();
        // Cancel bundled items...
    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
}
```

**Cancellation Modal UI:**
```
┌─────────────────────────────────────────────────────────────┐
│ Cancel Procedure                                       [✕]  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│ Procedure: Appendectomy                                     │
│ Amount Paid: ₦150,000                                       │
│                                                             │
│ Refund Amount: [₦150,000_______________]                    │
│ (Enter 0 for no refund)                                     │
│                                                             │
│ Cancellation Reason: *                                      │
│ [_________________________________________________]         │
│ [_________________________________________________]         │
│                                                             │
│ ⚠️ Refund will be credited to patient's account balance     │
│                                                             │
│                         [Keep Procedure] [Cancel & Refund]  │
└─────────────────────────────────────────────────────────────┘
```

### 3.3 Models

```php
// app/Models/ProcedureCategory.php
// app/Models/Procedure.php
// app/Models/PatientProcedure.php
// app/Models/ProcedureTeamMember.php
// app/Models/ProcedureNote.php
// app/Models/ProcedureItem.php
```

### 3.4 Controllers

```php
// app/Http/Controllers/ProcedureController.php
// - index() - list procedure catalog
// - store() - create procedure in catalog
// - update() - update procedure in catalog

// app/Http/Controllers/PatientProcedureController.php
// - store() - create patient procedure
// - show() - view procedure details (opens in new tab)
// - update() - update procedure status/details
// - addTeamMember() - add resource person
// - removeTeamMember() - remove resource person
// - addNote() - add pre-op/post-op note
// - addItem() - add lab/imaging/prescription/consumable
// - updateOutcome() - set procedure outcome
```

### 3.5 UI/UX Design

#### 3.5.1 Procedure Tab in New Encounter
**Location:** `resources/views/admin/encounters/new_encounter.blade.php`

Add a "Procedures" tab alongside existing tabs (Labs, Imaging, Prescriptions):

```
┌─────────────────────────────────────────────────────────────────┐
│ [Patient Info Banner]                                           │
├─────────────────────────────────────────────────────────────────┤
│ [Notes] [Labs] [Imaging] [Prescriptions] [🔷 Procedures]        │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ Add Procedure                                             │  │
│  │ ┌────────────────────────┐ ┌─────────────┐               │  │
│  │ │ [Select Procedure ▼]   │ │ Priority ▼  │ [+ Add]       │  │
│  │ └────────────────────────┘ └─────────────┘               │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │ Pending Procedures                                        │  │
│  │ ┌────────────────────────────────────────────────────┐   │  │
│  │ │ 🔷 Appendectomy (Surgical)                         │   │  │
│  │ │    Status: Scheduled | Priority: Urgent             │   │  │
│  │ │    ₦150,000 | HMO: Primary (Pending Validation)    │   │  │
│  │ │    [Open Details ↗] [Cancel ✕]                     │   │  │
│  │ └────────────────────────────────────────────────────┘   │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

#### 3.5.2 Procedure Detail Page (Opens in New Tab)
**Route:** `/procedures/{patientProcedure}`

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Procedure: Appendectomy                                    [Complete ✓] │
│ Patient: John Doe | File: 9426 | Status: IN PROGRESS                    │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ ┌─ BILLING STATUS ────────────────────────────────────────────────────┐│
│ │  Procedure Fee: ₦150,000                                            ││
│ │  Coverage: PRIMARY | Validation: ✅ APPROVED | Auth Code: HMO-12345 ││
│ │  Patient Pays: ₦15,000 | HMO Claims: ₦135,000                       ││
│ └─────────────────────────────────────────────────────────────────────┘│
│                                                                         │
│ ┌─ TEAM MEMBERS ──────────────────────────────────────────────────────┐│
│ │  👨‍⚕️ Dr. Smith - Chief Surgeon (Lead) [✕]                           ││
│ │  👨‍⚕️ Dr. Jones - Assistant Surgeon [✕]                              ││
│ │  👩‍⚕️ Dr. Adams - Anesthesiologist [✕]                               ││
│ │  👩‍⚕️ Nurse Jane - Scrub Nurse [✕]                                   ││
│ │  [+ Add Team Member]                                                 ││
│ └─────────────────────────────────────────────────────────────────────┘│
│                                                                         │
│ ┌─ NOTES ─────────────────────────────────────────────────────────────┐│
│ │ [Pre-Op] [Intra-Op] [Post-Op] [Anesthesia] [Nursing]                ││
│ │ ┌──────────────────────────────────────────────────────────────────┐││
│ │ │ Pre-Op Assessment - Dr. Smith, Jan 19, 2026 2:30 PM              │││
│ │ │ Patient cleared for surgery. NPO since midnight...               │││
│ │ └──────────────────────────────────────────────────────────────────┘││
│ │ [+ Add Note]                                                        ││
│ └─────────────────────────────────────────────────────────────────────┘│
│                                                                         │
│ ┌─ ITEMS & ORDERS ────────────────────────────────────────────────────┐│
│ │ [Labs] [Imaging] [Medications] [Consumables/Products]               ││
│ │                                                                      ││
│ │ ┌── Labs ──────────────────────────────────────────────────────────┐││
│ │ │ • FBC - ₦5,000 [✓ Bundled] - Results Ready                       │││
│ │ │ • Blood Group - ₦3,000 [✓ Bundled] - Pending                     │││
│ │ │ [+ Add Lab Request]                                              │││
│ │ └──────────────────────────────────────────────────────────────────┘││
│ │                                                                      ││
│ │ ┌── Medications ───────────────────────────────────────────────────┐││
│ │ │ • Ceftriaxone 1g - ₦2,500 [✗ Separate Bill] - Dispensed          │││
│ │ │ • Normal Saline 1L - ₦1,200 [✓ Bundled] - Dispensed              │││
│ │ │ [+ Add Medication/Consumable]                                    │││
│ │ └──────────────────────────────────────────────────────────────────┘││
│ └─────────────────────────────────────────────────────────────────────┘│
│                                                                         │
│ ┌─ OUTCOME (Post-Op Only) ────────────────────────────────────────────┐│
│ │ Outcome: [Successful ▼]                                             ││
│ │ Notes: [___________________________________________________]        ││
│ │ [Save Outcome]                                                      ││
│ └─────────────────────────────────────────────────────────────────────┘│
│                                                                         │
│ ┌─ ACTIONS ───────────────────────────────────────────────────────────┐│
│ │ [🖨️ Print Procedure Report] [❌ Cancel Procedure]                    ││
│ └─────────────────────────────────────────────────────────────────────┘│
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

#### 3.5.3 Item Add Modal (Reuses New Encounter Widgets)

When adding items in procedure detail page:

```
┌─────────────────────────────────────────────────────────────────┐
│ Add Lab Request                                           [✕]   │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Lab Test: [Select Lab Test ▼_________________________]         │
│                                                                 │
│  Note: [_______________________________________________]        │
│                                                                 │
│  ┌────────────────────────────────────────────────────────────┐│
│  │ ☐ Bundle with Procedure Fee                                ││
│  │   (Item will be part of the procedure package price)       ││
│  │                                                            ││
│  │ ☑ Bill Separately                                          ││
│  │   (Item will go through normal billing flow)               ││
│  └────────────────────────────────────────────────────────────┘│
│                                                                 │
│  Price: ₦5,000 (HMO: Primary - Patient: ₦500, Claims: ₦4,500)  │
│                                                                 │
│                                      [Cancel] [Add to Procedure]│
└─────────────────────────────────────────────────────────────────┘
```

#### 3.5.4 Print Procedure Report

A comprehensive printable report with hospital branding (similar to lab results print). Accessible via "Print Procedure Report" button on procedure detail page at any time.

**Route:** `GET /patient-procedures/{patientProcedure}/print`

**Report Layout:**
```
┌─────────────────────────────────────────────────────────────────────────┐
│                        [HOSPITAL LOGO]                                  │
│                     HOSPITAL NAME HERE                                  │
│                    123 Hospital Street                                  │
│                   Phone: 08X-XXX-XXXX                                   │
├─────────────────────────────────────────────────────────────────────────┤
│                      PROCEDURE REPORT                                   │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│ PATIENT INFORMATION                                                     │
│ ─────────────────────────────────────────────────────────────────────── │
│ Name: John Doe                        File No: 9426                     │
│ Age/Sex: 35 Years / Male              Phone: 0812-345-6789              │
│ HMO: NHIA Primary Care                Enrollee ID: HMO-123456           │
│                                                                         │
│ PROCEDURE DETAILS                                                       │
│ ─────────────────────────────────────────────────────────────────────── │
│ Procedure: Appendectomy               Category: General Surgery         │
│ Type: Surgical                        Priority: Urgent                  │
│ Status: Completed                     Outcome: Successful               │
│                                                                         │
│ Scheduled: Jan 19, 2026 10:00 AM      Operating Room: OR-2              │
│ Started: Jan 19, 2026 10:15 AM        Ended: Jan 19, 2026 12:30 PM      │
│ Duration: 2 hours 15 minutes                                            │
│                                                                         │
│ SURGICAL TEAM                                                           │
│ ─────────────────────────────────────────────────────────────────────── │
│ Role                    │ Name                   │ Notes                │
│ ────────────────────────┼────────────────────────┼───────────────────── │
│ Chief Surgeon (Lead)    │ Dr. John Smith         │                      │
│ Assistant Surgeon       │ Dr. Mary Jones         │                      │
│ Anesthesiologist        │ Dr. Peter Adams        │                      │
│ Scrub Nurse             │ Nurse Jane Doe         │                      │
│ Circulating Nurse       │ Nurse Sarah Brown      │                      │
│                                                                         │
│ PRE-OPERATIVE NOTES                                                     │
│ ─────────────────────────────────────────────────────────────────────── │
│ Pre-Op Assessment - Dr. Smith, Jan 19, 2026 8:00 AM                     │
│ Patient is a 35-year-old male presenting with acute appendicitis.       │
│ Vitals stable. NPO since midnight. Consent obtained.                    │
│ Pre-operative labs reviewed: FBC, E/U/Cr within normal limits.          │
│ Blood type: O+, 2 units crossmatched.                                   │
│                                                                         │
│ INTRA-OPERATIVE NOTES                                                   │
│ ─────────────────────────────────────────────────────────────────────── │
│ Operative Note - Dr. Smith, Jan 19, 2026 12:30 PM                       │
│ Laparoscopic appendectomy performed under general anesthesia.           │
│ Inflamed appendix identified and resected. No complications.            │
│ Estimated blood loss: 50ml. No transfusion required.                    │
│                                                                         │
│ POST-OPERATIVE NOTES                                                    │
│ ─────────────────────────────────────────────────────────────────────── │
│ Post-Op Assessment - Dr. Jones, Jan 19, 2026 3:00 PM                    │
│ Patient stable post-operatively. Vitals within normal limits.           │
│ IV fluids continued. Pain managed with IV Paracetamol.                  │
│ Diet: Clear liquids in 6 hours if tolerating.                           │
│ Plan: Monitor overnight, discharge in 24-48 hours if stable.            │
│                                                                         │
│ ANESTHESIA NOTES                                                        │
│ ─────────────────────────────────────────────────────────────────────── │
│ Anesthesia Record - Dr. Adams, Jan 19, 2026                             │
│ ASA Class: I. General anesthesia with endotracheal intubation.          │
│ Agents: Propofol, Fentanyl, Rocuronium, Sevoflurane.                    │
│ Duration: 2 hours 15 minutes. Uneventful emergence.                     │
│                                                                         │
│ ITEMS & ORDERS                                                          │
│ ─────────────────────────────────────────────────────────────────────── │
│ Labs:                                                                   │
│   • FBC - Results Ready [Bundled]                                       │
│   • Blood Group & Crossmatch - Results Ready [Bundled]                  │
│   • E/U/Cr - Results Ready [Bundled]                                    │
│                                                                         │
│ Medications:                                                            │
│   • Ceftriaxone 1g IV - Dispensed [Separate Bill]                       │
│   • Metronidazole 500mg IV - Dispensed [Bundled]                        │
│   • Paracetamol 1g IV - Dispensed [Bundled]                             │
│                                                                         │
│ OUTCOME                                                                 │
│ ─────────────────────────────────────────────────────────────────────── │
│ Outcome: SUCCESSFUL                                                     │
│ Notes: Procedure completed without complications. Patient recovering    │
│ well. Expected discharge in 24-48 hours.                                │
│                                                                         │
│ BILLING SUMMARY                                                         │
│ ─────────────────────────────────────────────────────────────────────── │
│ Procedure Fee: ₦150,000                                                 │
│ Coverage: PRIMARY | Auth Code: HMO-12345                                │
│ Patient Pays: ₦15,000 | HMO Claims: ₦135,000                            │
│                                                                         │
├─────────────────────────────────────────────────────────────────────────┤
│ Report Generated: Jan 19, 2026 4:30 PM                                  │
│ Generated By: Dr. John Smith                                            │
├─────────────────────────────────────────────────────────────────────────┤
│                     [HOSPITAL FOOTER / STAMP]                           │
└─────────────────────────────────────────────────────────────────────────┘
```

**Implementation:**
```php
// PatientProcedureController
public function print(PatientProcedure $patientProcedure)
{
    $patientProcedure->load([
        'procedure.procedureCategory',
        'patient',
        'teamMembers.user',
        'notes.createdBy',
        'items.labServiceRequest',
        'items.productRequest',
        'productOrServiceRequest.payment',
    ]);
    
    $appStatus = ApplicationStatu::first();
    
    return view('admin.patient-procedures.print', [
        'patientProcedure' => $patientProcedure,
        'appStatus' => $appStatus,
    ]);
}
```

**View File:** `resources/views/admin/patient-procedures/print.blade.php`
- Uses same styling/branding as lab results print
- Includes `@media print` CSS for clean printing
- Shows all available information at time of printing
- Sections for missing data show "Not recorded" or similar

### 3.6 Routes

```php
// routes/web.php

// Procedure Category Management (Admin) - ENT, O&G, General Surgery, etc.
Route::resource('procedure-categories', ProcedureCategoryController::class);

// Note: Procedure services are created via the existing services CRUD
// When service category = "Procedures", the ServiceController handles
// creating/updating the linked procedure record automatically.

// Patient Procedures (Clinical Operations)
Route::prefix('patient-procedures')->group(function () {
    Route::post('/', [PatientProcedureController::class, 'store'])->name('patient-procedures.store');
    Route::get('/{patientProcedure}', [PatientProcedureController::class, 'show'])->name('patient-procedures.show');
    Route::put('/{patientProcedure}', [PatientProcedureController::class, 'update'])->name('patient-procedures.update');
    Route::delete('/{patientProcedure}', [PatientProcedureController::class, 'destroy'])->name('patient-procedures.destroy');
    
    // Cancel with Refund
    Route::post('/{patientProcedure}/cancel', [PatientProcedureController::class, 'cancel'])->name('patient-procedures.cancel');
    
    // Print Report
    Route::get('/{patientProcedure}/print', [PatientProcedureController::class, 'print'])->name('patient-procedures.print');
    
    // Team Members
    Route::post('/{patientProcedure}/team', [PatientProcedureController::class, 'addTeamMember']);
    Route::delete('/{patientProcedure}/team/{member}', [PatientProcedureController::class, 'removeTeamMember']);
    
    // Notes (CKEditor WYSIWYG content)
    Route::post('/{patientProcedure}/notes', [PatientProcedureController::class, 'addNote']);
    Route::put('/{patientProcedure}/notes/{note}', [PatientProcedureController::class, 'updateNote']);
    Route::delete('/{patientProcedure}/notes/{note}', [PatientProcedureController::class, 'deleteNote']);
    
    // Items
    Route::post('/{patientProcedure}/items/lab', [PatientProcedureController::class, 'addLabRequest']);
    Route::post('/{patientProcedure}/items/imaging', [PatientProcedureController::class, 'addImagingRequest']);
    Route::post('/{patientProcedure}/items/medication', [PatientProcedureController::class, 'addMedication']);
    Route::delete('/{patientProcedure}/items/{item}', [PatientProcedureController::class, 'removeItem']);
    
    // Outcome
    Route::put('/{patientProcedure}/outcome', [PatientProcedureController::class, 'updateOutcome']);
});

// API endpoint for fetching procedures (for encounter procedure tab)
Route::get('api/procedures', function() {
    $appStatus = \App\Models\ApplicationStatu::first();
    return \App\Models\Service::where('category_id', $appStatus->procedure_category_id)
        ->with(['price', 'procedure.procedureCategory'])
        ->active()
        ->get();
})->name('api.procedures');
```

### 3.7 Workbench Integration

#### Lab Workbench
```php
// In LabWorkbenchController
// When checking delivery status, if lab is bundled:
if ($labRequest->procedureItem && $labRequest->procedureItem->is_bundled) {
    // Check procedure's ProductOrServiceRequest status instead
    $procedureReq = $labRequest->procedureItem->patientProcedure->productOrServiceRequest;
    return HmoHelper::canDeliverService($procedureReq);
}
```

#### Similar for Imaging, Pharmacy workbenches.

### 3.8 HMO Workbench Integration

Procedures requiring HMO validation will appear in HMO Executive workbench:
- Filter by type: Consultations, Labs, Imaging, **Procedures**
- Approval/Rejection follows same pattern
- Auth code assigned to procedure's ProductOrServiceRequest

---

## Part 4: Implementation Phases

### Phase 1: Foundation (Week 1)
1. ☐ Create migrations for all new tables (procedure-related only)
2. ☐ Create seeders for:
   - Service category: "Procedures" (code: PROC)
   - Service category: "Registration" (code: REG)
   - Procedure categories: ENT, O&G, General Surgery, Dental, etc.
3. ☐ Add registration_category_id and procedure_category_id to application_status
4. ☐ Update Hospital Config UI with category dropdowns
5. ☐ Create all Eloquent models with relationships

### Phase 2: Services CRUD Enhancement (Week 1-2)
1. ☐ Update ServiceController create/edit methods to pass procedure data
2. ☐ Update services create.blade.php with dynamic procedure fields
3. ☐ Update services edit.blade.php with dynamic procedure fields (pre-populate if exists)
4. ☐ Update ServiceController store() to create linked procedure record
5. ☐ Update ServiceController update() to update/delete linked procedure record
6. ☐ Test creating services with procedure category

### Phase 3: Registration Feature (Week 2)
1. ☐ Update patient create form with registration fee selection
2. ☐ Update reception workbench new patient modal with registration fee selection
3. ☐ Update PatientController::store() to auto-bill registration
4. ☐ Test with HMO and non-HMO patients

### Phase 4: Credit Payment (Account Debit) - QUICK WIN (Week 2)
> Uses existing patient_accounts table - just minor code changes!
1. ☐ Update BillingWorkbenchController::processPayment() - remove balance check
2. ☐ Update workbench.blade.php - show account option always (not just when balance > 0)
3. ☐ Update workbench.blade.php - change validation to warning instead of block
4. ☐ Test paying from account with sufficient balance
5. ☐ Test paying from account with insufficient balance (goes negative)

### Phase 5: Procedure Catalog Admin (Week 2-3)
1. ☐ Create Procedure Category management views (CRUD)
2. ☐ Add to admin sidebar under Services menu
3. ☐ (Note: Procedure CRUD is now via Services page with procedure category)

### Phase 6: Patient Procedure UI (Week 3)
1. ☐ Add Procedures tab to new_encounter blade
2. ☐ Create procedure request flow (selecting from procedure services)
3. ☐ Create procedure detail page (new tab view)

### Phase 7: Items & Billing Integration (Week 3-4)
1. ☐ Implement item addition (labs, imaging, meds)
2. ☐ Implement bundled vs separate billing logic
3. ☐ Update workbenches for bundled item delivery checks

### Phase 8: Notes & Team (Week 4)
1. ☐ Implement team member management with "Other" custom role option
2. ☐ Implement pre-op/post-op notes with CKEditor WYSIWYG
3. ☐ Implement outcome tracking

### Phase 9: Cancellation & Refund (Week 4)
1. ☐ Create cancellation modal with refund amount input
2. ☐ Implement refund to patient account logic (uses existing ACC_DEPOSIT)
3. ☐ Handle bundled items cancellation

### Phase 10: Print Procedure Report (Week 4-5)
1. ☐ Create print.blade.php with hospital branding
2. ☐ Include all procedure sections (team, notes, items, outcome)
3. ☐ Add @media print CSS styling
4. ☐ Test printing at various procedure stages

### Phase 11: Testing & Polish (Week 5)
1. ☐ End-to-end testing of billing flows
2. ☐ HMO validation testing
3. ☐ Credit/debit payment testing
4. ☐ Cancellation/refund testing
5. ☐ UI polish and UX improvements

---

## Part 4.1: Extensive Task List (with Section References)

### 🗄️ DATABASE & MIGRATIONS

| # | Task | Section Reference | File |
|---|------|-------------------|------|
| 1 | Create migration: `add_registration_and_procedure_category_settings` - Add `registration_category_id` and `procedure_category_id` to `application_status` table | Part 1.2 | `database/migrations/2026_01_19_000001_...` |
| 2 | Create migration: `create_procedure_categories_table` - id, name, code, description, status, timestamps | Part 3.1.1 | `database/migrations/2026_01_19_000002_...` |
| 3 | Create migration: `create_procedures_table` - service_id (FK), procedure_category_id (FK), name, code, description, is_surgical, estimated_duration_minutes, status | Part 3.1.2 | `database/migrations/2026_01_19_000003_...` |
| 4 | Create migration: `create_patient_procedures_table` - procedure_id, patient_id, encounter_id, admission_request_id, service_request_id, status, priority, scheduled_date/time, actual_start/end_time, operating_room, outcome, outcome_notes, cancellation fields, requested_by, timestamps, soft deletes | Part 3.1.3 | `database/migrations/2026_01_19_000004_...` |
| 5 | Create migration: `create_procedure_team_members_table` - patient_procedure_id, user_id, role, custom_role (for "Other"), is_lead, notes, timestamps | Part 3.1.4 | `database/migrations/2026_01_19_000005_...` |
| 6 | Create migration: `create_procedure_notes_table` - patient_procedure_id, note_type (ENUM: pre_op, intra_op, post_op, anesthesia, nursing), title, content (LONGTEXT for CKEditor), created_by, timestamps | Part 3.1.5 | `database/migrations/2026_01_19_000006_...` |
| 7 | Create migration: `create_procedure_items_table` - patient_procedure_id, lab_service_request_id, imaging_service_request_id, product_request_id, misc_bill_id, is_bundled, product_or_service_request_id, timestamps | Part 3.1.6 | `database/migrations/2026_01_19_000007_...` |
| 8 | Run all migrations | Part 4 Phase 1 | Terminal |

### 🌱 SEEDERS

| # | Task | Section Reference | File |
|---|------|-------------------|------|
| 9 | Create seeder: `ProcedureServiceCategorySeeder` - Creates "Procedures" (code: PROC) and "Registration" (code: REG) service categories, updates application_status with their IDs | Part 1.3 | `database/seeders/ProcedureServiceCategorySeeder.php` |
| 10 | Create seeder: `ProcedureCategorySeeder` - Seeds: General Surgery (GS), ENT, Ophthalmology (OPH), Dental/Oral Surgery (DENT), O&G, Orthopaedic (ORTH), Urology (URO), Cardiothoracic (CTS), Neurosurgery (NEURO), Plastic Surgery (PLAS), Paediatric Surgery (PAED), Endoscopy (ENDO), Minor Procedures (MINOR) | Part 3.1.1, Appendix B | `database/seeders/ProcedureCategorySeeder.php` |
| 11 | Run seeders | Part 4 Phase 1 | Terminal |

### 📦 MODELS

| # | Task | Section Reference | File |
|---|------|-------------------|------|
| 12 | Create model: `ProcedureCategory` - fillable: name, code, description, status; relationships: procedures() | Part 3.3 | `app/Models/ProcedureCategory.php` |
| 13 | Create model: `Procedure` - fillable: service_id, procedure_category_id, name, code, description, is_surgical, estimated_duration_minutes, status; relationships: service(), procedureCategory(), patientProcedures() | Part 3.3 | `app/Models/Procedure.php` |
| 14 | Create model: `PatientProcedure` - fillable: all columns from migration; relationships: procedure(), patient(), encounter(), productOrServiceRequest(), teamMembers(), notes(), items(), cancelledBy(), requestedBy() | Part 3.3 | `app/Models/PatientProcedure.php` |
| 15 | Create model: `ProcedureTeamMember` - fillable: patient_procedure_id, user_id, role, custom_role, is_lead, notes; relationships: patientProcedure(), user() | Part 3.3 | `app/Models/ProcedureTeamMember.php` |
| 16 | Create model: `ProcedureNote` - fillable: patient_procedure_id, note_type, title, content, created_by; relationships: patientProcedure(), createdBy() | Part 3.3 | `app/Models/ProcedureNote.php` |
| 17 | Create model: `ProcedureItem` - fillable: all FK columns, is_bundled; relationships: patientProcedure(), labServiceRequest(), imagingServiceRequest(), productRequest(), miscBill(), productOrServiceRequest() | Part 3.3 | `app/Models/ProcedureItem.php` |
| 18 | Update `ApplicationStatu` model - add `registration_category_id`, `procedure_category_id` to fillable | Part 5 Modified Files | `app/Models/ApplicationStatu.php` |
| 19 | Update `Service` model - add `procedure()` relationship: `hasOne(Procedure::class)` | Part 5 Modified Files | `app/Models/service.php` |

### 🎛️ HOSPITAL CONFIG (Admin Settings)

| # | Task | Section Reference | File |
|---|------|-------------------|------|
| 20 | Update `HospitalConfigController` - Add validation rules for `registration_category_id` and `procedure_category_id` | Part 1.4 | `app/Http/Controllers/HospitalConfigController.php` |
| 21 | Update hospital config view - Add "Registration Category" dropdown (populated from service_categories) | Part 1.4 | `resources/views/admin/hospital-config/index.blade.php` |
| 22 | Update hospital config view - Add "Procedure Category" dropdown (populated from service_categories) | Part 1.4 | `resources/views/admin/hospital-config/index.blade.php` |

### 🛠️ SERVICES CRUD (Dynamic Procedure Form)

| # | Task | Section Reference | File |
|---|------|-------------------|------|
| 23 | Update `ServiceController::create()` - Pass `procedureCategories` and `procedureCategoryId` to view | Part 2.5.4 | `app/Http/Controllers/ServiceController.php` |
| 24 | Update `ServiceController::edit()` - Pass `procedure` (if exists), `procedureCategories`, `procedureCategoryId` to view | Part 2.5.4 | `app/Http/Controllers/ServiceController.php` |
| 25 | Update `ServiceController::store()` - If category_id == procedure_category_id, also create `Procedure` record with procedure_category_id, code, is_surgical, estimated_duration_minutes, description | Part 2.5.3 | `app/Http/Controllers/ServiceController.php` |
| 26 | Update `ServiceController::update()` - If category_id == procedure_category_id, create/update `Procedure` record; if category changed away from procedure, delete linked procedure | Part 2.5.3 | `app/Http/Controllers/ServiceController.php` |
| 27 | Update services create view - Add hidden `#procedure-fields` div with: Procedure Category dropdown, Procedure Code input, Is Surgical checkbox, Estimated Duration input, Procedure Description textarea | Part 2.5.2 | `resources/views/admin/services/create.blade.php` |
| 28 | Update services create view - Add JavaScript to show/hide procedure fields when procedure service category is selected | Part 2.5.2 | `resources/views/admin/services/create.blade.php` |
| 29 | Update services edit view - Same as create, but pre-populate procedure fields if `$procedure` exists | Part 2.5.2 | `resources/views/admin/services/edit.blade.php` |
| 30 | Test: Create a new service with Procedure category - verify both `services` and `procedures` records created | Part 4 Phase 2 | Manual Testing |

### 👤 REGISTRATION AUTO-BILLING

| # | Task | Section Reference | File |
|---|------|-------------------|------|
| 31 | Update patient create controller - Pass `$registrationServices` (services where category_id == registration_category_id) to view | Part 2.1, 2.2 | `app/Http/Controllers/PatientController.php` |
| 32 | Update patient create view - Add "Registration Fee (Optional)" dropdown with registration services | Part 2.1 | `resources/views/admin/patients/create.blade.php` |
| 33 | Update `PatientController::store()` - If `registration_service_id` provided, create `ProductOrServiceRequest` entry with HMO tariff applied | Part 2.2 | `app/Http/Controllers/PatientController.php` |
| 34 | Update reception workbench modal - Add "Registration Fee (Optional)" dropdown | Part 2.1 | `resources/views/admin/reception-workbench/partials/new_patient_modal.blade.php` |
| 35 | Update reception workbench AJAX patient creation - Handle `registration_service_id` | Part 2.1 | Reception workbench controller |
| 36 | Test: Create patient with registration fee (non-HMO) - verify billing entry created | Part 4 Phase 3 | Manual Testing |
| 37 | Test: Create patient with registration fee (HMO) - verify tariff applied | Part 4 Phase 3 | Manual Testing |

### 💳 CREDIT PAYMENT (Account Debit)

| # | Task | Section Reference | File |
|---|------|-------------------|------|
| 38 | Update `BillingWorkbenchController::processPayment()` - Remove balance check, allow negative balance when payment_type = ACCOUNT | Part 3.2.4 | `app/Http/Controllers/BillingWorkbenchController.php` |
| 39 | Update billing workbench view - In `updateAccountBalanceDisplays()`, always show `#account-payment-option` if patient has account (not just when balance > 0) | Part 3.2.4 | `resources/views/admin/billing/workbench.blade.php` |
| 40 | Update billing workbench view - In `confirmPayment()`, change validation from blocking to warning with confirm dialog when amount exceeds balance | Part 3.2.4 | `resources/views/admin/billing/workbench.blade.php` |
| 41 | Update billing workbench view - Show "Balance After" preview including negative/debit state | Part 3.2.4 | `resources/views/admin/billing/workbench.blade.php` |
| 42 | Test: Pay from account with sufficient balance - verify deduction works | Part 4 Phase 4 | Manual Testing |
| 43 | Test: Pay from account with insufficient balance - verify warning shown, balance goes negative | Part 4 Phase 4 | Manual Testing |

### 📁 PROCEDURE CATEGORIES ADMIN (CRUD)

| # | Task | Section Reference | File |
|---|------|-------------------|------|
| 44 | Create `ProcedureCategoryController` - index(), create(), store(), edit(), update(), destroy() methods | Part 3.4 | `app/Http/Controllers/ProcedureCategoryController.php` |
| 45 | Create procedure categories index view - DataTable listing with name, code, description, status, actions | Part 5 New Files | `resources/views/admin/procedure-categories/index.blade.php` |
| 46 | Create procedure categories create view - Form with name, code, description, status | Part 5 New Files | `resources/views/admin/procedure-categories/create.blade.php` |
| 47 | Create procedure categories edit view - Same form, pre-populated | Part 5 New Files | `resources/views/admin/procedure-categories/edit.blade.php` |
| 48 | Add routes for procedure-categories resource | Part 3.6 | `routes/web.php` |
| 49 | Update sidebar - Add "Procedure Categories" under Services menu | Part 5 Modified Files | `resources/views/admin/partials/sidebar.blade.php` |

### 📋 PATIENT PROCEDURE UI (Encounter Tab + Detail Page)

| # | Task | Section Reference | File |
|---|------|-------------------|------|
| 50 | Create `PatientProcedureController` - store(), show(), update(), destroy(), addTeamMember(), removeTeamMember(), addNote(), updateNote(), deleteNote(), addLabRequest(), addImagingRequest(), addMedication(), removeItem(), updateOutcome(), cancel(), print() | Part 3.4 | `app/Http/Controllers/PatientProcedureController.php` |
| 51 | Add patient-procedures routes - All routes from Part 3.6 including /cancel and /print | Part 3.6 | `routes/web.php` |
| 52 | Add API route for fetching procedures - `GET api/procedures` returns services with procedure category, with price and procedure.procedureCategory eager loaded | Part 3.6 | `routes/web.php` |
| 53 | Update new_encounter view - Add "Procedures" tab alongside Labs, Imaging, Prescriptions | Part 3.5.1 | `resources/views/admin/encounters/new_encounter.blade.php` |
| 54 | Update new_encounter view - Add procedure selection dropdown (from api/procedures), priority dropdown, Add button | Part 3.5.1 | `resources/views/admin/encounters/new_encounter.blade.php` |
| 55 | Update new_encounter view - Add "Pending Procedures" list with status, priority, price, HMO status, [Open Details] [Cancel] buttons | Part 3.5.1 | `resources/views/admin/encounters/new_encounter.blade.php` |
| 56 | Create patient procedure show view - Full detail page that opens in new tab with all sections | Part 3.5.2 | `resources/views/admin/patient-procedures/show.blade.php` |
| 57 | In show view - BILLING STATUS section: Procedure Fee, Coverage, Validation status, Auth Code, Patient Pays, HMO Claims | Part 3.5.2 | `resources/views/admin/patient-procedures/show.blade.php` |
| 58 | In show view - TEAM MEMBERS section: List with role, name, [✕] remove button, [+ Add Team Member] button | Part 3.5.2 | `resources/views/admin/patient-procedures/show.blade.php` |
| 59 | In show view - NOTES section: Tabs for Pre-Op, Intra-Op, Post-Op, Anesthesia, Nursing; Note list with title, author, timestamp; [+ Add Note] button | Part 3.5.2 | `resources/views/admin/patient-procedures/show.blade.php` |
| 60 | In show view - ITEMS & ORDERS section: Tabs for Labs, Imaging, Medications, Consumables; Items list with name, price, bundled status, delivery status; [+ Add] buttons | Part 3.5.2 | `resources/views/admin/patient-procedures/show.blade.php` |
| 61 | In show view - OUTCOME section: Outcome dropdown (Successful, Complications, Aborted, Converted), Notes textarea, [Save Outcome] button | Part 3.5.2 | `resources/views/admin/patient-procedures/show.blade.php` |
| 62 | In show view - ACTIONS section: [🖨️ Print Procedure Report] and [❌ Cancel Procedure] buttons | Part 3.5.2 | `resources/views/admin/patient-procedures/show.blade.php` |

### 👥 TEAM MEMBER MANAGEMENT

| # | Task | Section Reference | File |
|---|------|-------------------|------|
| 63 | Create team member modal partial - User select, Role dropdown (Chief Surgeon, Assistant Surgeon, Anesthesiologist, Nurse Anesthetist, Scrub Nurse, Circulating Nurse, Surgical First Assistant, Perfusionist, Radiologist, Pathologist, **Other**), custom_role input (shown when Other selected), is_lead checkbox, notes textarea | Part 3.1.4, Appendix A | `resources/views/admin/patient-procedures/partials/team_member_modal.blade.php` |
| 64 | Implement `PatientProcedureController::addTeamMember()` - Validate, create ProcedureTeamMember, return JSON | Part 3.4 | `app/Http/Controllers/PatientProcedureController.php` |
| 65 | Implement `PatientProcedureController::removeTeamMember()` - Delete ProcedureTeamMember, return JSON | Part 3.4 | `app/Http/Controllers/PatientProcedureController.php` |
| 66 | Add AJAX handlers in show view - Open modal, submit team member, remove team member, refresh list | Part 3.5.2 | `resources/views/admin/patient-procedures/show.blade.php` |

### 📝 NOTES MANAGEMENT (CKEditor WYSIWYG)

| # | Task | Section Reference | File |
|---|------|-------------------|------|
| 67 | Create add note modal partial - Note type select (pre_op, intra_op, post_op, anesthesia, nursing), title input, content CKEditor WYSIWYG textarea | Part 3.1.5 | `resources/views/admin/patient-procedures/partials/add_note_modal.blade.php` |
| 68 | Initialize CKEditor in modal - Full toolbar for rich text editing | Part 3.1.5 | `resources/views/admin/patient-procedures/partials/add_note_modal.blade.php` |
| 69 | Implement `PatientProcedureController::addNote()` - Validate, create ProcedureNote with LONGTEXT content, return JSON | Part 3.4 | `app/Http/Controllers/PatientProcedureController.php` |
| 70 | Implement `PatientProcedureController::updateNote()` - Find note, validate, update, return JSON | Part 3.4 | `app/Http/Controllers/PatientProcedureController.php` |
| 71 | Implement `PatientProcedureController::deleteNote()` - Delete ProcedureNote, return JSON | Part 3.4 | `app/Http/Controllers/PatientProcedureController.php` |
| 72 | Add AJAX handlers in show view - Open modal, submit note, edit note, delete note, refresh by tab | Part 3.5.2 | `resources/views/admin/patient-procedures/show.blade.php` |

### 📦 ITEMS MANAGEMENT (Labs, Imaging, Medications)

| # | Task | Section Reference | File |
|---|------|-------------------|------|
| 73 | Create add item modal partial - Item type tabs (Lab, Imaging, Medication), item select dropdown, note input, Bundle checkbox with explanation, price display with HMO breakdown | Part 3.5.3 | `resources/views/admin/patient-procedures/partials/add_item_modal.blade.php` |
| 74 | Implement `PatientProcedureController::addLabRequest()` - Create LabServiceRequest, create ProcedureItem (is_bundled flag), if not bundled create ProductOrServiceRequest with HMO tariff, return JSON | Part 3.2.1, 3.2.2 | `app/Http/Controllers/PatientProcedureController.php` |
| 75 | Implement `PatientProcedureController::addImagingRequest()` - Same pattern for imaging | Part 3.2.1, 3.2.2 | `app/Http/Controllers/PatientProcedureController.php` |
| 76 | Implement `PatientProcedureController::addMedication()` - Same pattern for medications/products | Part 3.2.1, 3.2.2 | `app/Http/Controllers/PatientProcedureController.php` |
| 77 | Implement `PatientProcedureController::removeItem()` - Delete ProcedureItem (and associated request if not delivered), return JSON | Part 3.4 | `app/Http/Controllers/PatientProcedureController.php` |
| 78 | Add AJAX handlers in show view - Open modal, load items by type, submit item, remove item | Part 3.5.2 | `resources/views/admin/patient-procedures/show.blade.php` |

### 💰 BUNDLED BILLING INTEGRATION

| # | Task | Section Reference | File |
|---|------|-------------------|------|
| 79 | Update `HmoHelper` - Add `isBundledItem($itemType, $itemId)` method to check if item is bundled with a procedure | Part 3.7 | `app/Helpers/HmoHelper.php` |
| 80 | Update `HmoHelper` - Add `canDeliverBundledItem($procedureItem)` method to check procedure's payment/validation status | Part 3.7 | `app/Helpers/HmoHelper.php` |
| 81 | Update Lab Workbench - When checking delivery, if lab has procedureItem and is_bundled, check procedure's ProductOrServiceRequest instead | Part 3.7 | `app/Http/Controllers/LabWorkbenchController.php` |
| 82 | Update Imaging Workbench - Same bundled delivery check | Part 3.7 | Imaging workbench controller |
| 83 | Update Pharmacy Workbench - Same bundled delivery check | Part 3.7 | Pharmacy workbench controller |

### 🔄 OUTCOME TRACKING

| # | Task | Section Reference | File |
|---|------|-------------------|------|
| 84 | Create outcome section partial - Outcome dropdown (Successful, Complications, Aborted, Converted), outcome_notes textarea, [Save Outcome] button | Part 3.1.3 | `resources/views/admin/patient-procedures/partials/outcome_section.blade.php` |
| 85 | Implement `PatientProcedureController::updateOutcome()` - Validate outcome enum, update patient_procedure, return JSON | Part 3.4 | `app/Http/Controllers/PatientProcedureController.php` |
| 86 | Add AJAX handler in show view - Submit outcome, show success toast | Part 3.5.2 | `resources/views/admin/patient-procedures/show.blade.php` |

### ❌ CANCELLATION WITH REFUND

| # | Task | Section Reference | File |
|---|------|-------------------|------|
| 87 | Create cancel modal view - Shows procedure name, amount paid, refund amount input (default full), cancellation reason textarea (required), warning about refund to account, [Keep Procedure] [Cancel & Refund] buttons | Part 3.2.5 | `resources/views/admin/patient-procedures/cancel_modal.blade.php` |
| 88 | Implement `PatientProcedureController::cancel()` - Validate, if payment exists and refund > 0: get/create PatientAccount, credit balance, create payment record (ACC_DEPOSIT, REFUND), update procedure status to cancelled with reason/refund_amount/cancelled_at/cancelled_by, cancel bundled items, return JSON | Part 3.2.5 | `app/Http/Controllers/PatientProcedureController.php` |
| 89 | Add cancel button click handler in show view - Open cancel modal | Part 3.5.2 | `resources/views/admin/patient-procedures/show.blade.php` |
| 90 | Add AJAX handler for cancel submission - POST to /patient-procedures/{id}/cancel, on success redirect or show message | Part 3.5.2 | `resources/views/admin/patient-procedures/show.blade.php` |

### 🖨️ PRINT PROCEDURE REPORT

| # | Task | Section Reference | File |
|---|------|-------------------|------|
| 91 | Create print view - Hospital logo/header, patient info (name, file no, age/sex, phone, HMO, enrollee ID) | Part 3.5.4 | `resources/views/admin/patient-procedures/print.blade.php` |
| 92 | In print view - Procedure details section: name, category, type (surgical/non), priority, status, outcome, scheduled/actual times, duration, operating room | Part 3.5.4 | `resources/views/admin/patient-procedures/print.blade.php` |
| 93 | In print view - Surgical team table: Role, Name, Notes columns | Part 3.5.4 | `resources/views/admin/patient-procedures/print.blade.php` |
| 94 | In print view - Pre-operative notes section: Title, author, timestamp, content (rendered HTML from CKEditor) | Part 3.5.4 | `resources/views/admin/patient-procedures/print.blade.php` |
| 95 | In print view - Intra-operative notes section | Part 3.5.4 | `resources/views/admin/patient-procedures/print.blade.php` |
| 96 | In print view - Post-operative notes section | Part 3.5.4 | `resources/views/admin/patient-procedures/print.blade.php` |
| 97 | In print view - Anesthesia notes section | Part 3.5.4 | `resources/views/admin/patient-procedures/print.blade.php` |
| 98 | In print view - Items & Orders section: Labs list, Medications list (showing bundled status) | Part 3.5.4 | `resources/views/admin/patient-procedures/print.blade.php` |
| 99 | In print view - Outcome section: Outcome status, notes | Part 3.5.4 | `resources/views/admin/patient-procedures/print.blade.php` |
| 100 | In print view - Billing summary: Procedure fee, coverage mode, auth code, patient pays, HMO claims | Part 3.5.4 | `resources/views/admin/patient-procedures/print.blade.php` |
| 101 | In print view - Footer: Report generated timestamp, generated by, hospital footer/stamp | Part 3.5.4 | `resources/views/admin/patient-procedures/print.blade.php` |
| 102 | Add @media print CSS - Clean printing, hide non-essential elements, proper page breaks | Part 3.5.4 | `resources/views/admin/patient-procedures/print.blade.php` |
| 103 | Implement `PatientProcedureController::print()` - Load procedure with all relationships, pass to print view | Part 3.5.4 | `app/Http/Controllers/PatientProcedureController.php` |
| 104 | Add print button click handler in show view - Open print view in new tab/window | Part 3.5.2 | `resources/views/admin/patient-procedures/show.blade.php` |

### 🏥 HMO WORKBENCH INTEGRATION

| # | Task | Section Reference | File |
|---|------|-------------------|------|
| 105 | Update HMO Executive workbench - Add "Procedures" filter option alongside Consultations, Labs, Imaging | Part 3.8 | HMO workbench view/controller |
| 106 | Ensure procedures with HMO validation appear in HMO queue | Part 3.8 | HMO workbench controller |
| 107 | Ensure auth code assignment works for procedures | Part 3.8 | HMO workbench controller |

### 🧪 TESTING

| # | Task | Section Reference | File |
|---|------|-------------------|------|
| 108 | Test: Create procedure service via Services CRUD - verify both services and procedures tables updated | Part 4 Phase 2 | Manual |
| 109 | Test: Edit procedure service - change is_surgical, verify procedures table updated | Part 4 Phase 2 | Manual |
| 110 | Test: Change service category away from procedure - verify procedures record deleted | Part 4 Phase 2 | Manual |
| 111 | Test: Patient registration with fee (non-HMO) - verify ProductOrServiceRequest created | Part 4 Phase 3 | Manual |
| 112 | Test: Patient registration with fee (HMO) - verify tariff applied correctly | Part 4 Phase 3 | Manual |
| 113 | Test: Credit payment with sufficient balance | Part 4 Phase 4 | Manual |
| 114 | Test: Credit payment with insufficient balance - verify warning, negative balance | Part 4 Phase 4 | Manual |
| 115 | Test: Request procedure in encounter - verify ProductOrServiceRequest and HMO flow | Part 4 Phase 6 | Manual |
| 116 | Test: Add bundled lab to procedure - verify no separate billing entry | Part 4 Phase 7 | Manual |
| 117 | Test: Add non-bundled lab to procedure - verify separate billing entry | Part 4 Phase 7 | Manual |
| 118 | Test: Deliver bundled item - verify checks procedure's payment status | Part 4 Phase 7 | Manual |
| 119 | Test: Add team member with standard role | Part 4 Phase 8 | Manual |
| 120 | Test: Add team member with "Other" custom role | Part 4 Phase 8 | Manual |
| 121 | Test: Add pre-op note with CKEditor (rich text) | Part 4 Phase 8 | Manual |
| 122 | Test: Set procedure outcome | Part 4 Phase 8 | Manual |
| 123 | Test: Cancel procedure without refund | Part 4 Phase 9 | Manual |
| 124 | Test: Cancel procedure with full refund - verify account credited | Part 4 Phase 9 | Manual |
| 125 | Test: Cancel procedure with partial refund | Part 4 Phase 9 | Manual |
| 126 | Test: Print procedure report at various stages | Part 4 Phase 10 | Manual |
| 127 | Test: Full end-to-end flow: Create procedure service → Request for patient → Add team → Add notes → Add items → Complete → Print | Part 4 Phase 11 | Manual |

---

## Part 5: Files to Create/Modify

### New Files
```
database/migrations/
├── 2026_01_19_000001_add_registration_and_procedure_category_settings.php
├── 2026_01_19_000002_create_procedure_categories_table.php
├── 2026_01_19_000003_create_procedures_table.php
├── 2026_01_19_000004_create_patient_procedures_table.php
├── 2026_01_19_000005_create_procedure_team_members_table.php
├── 2026_01_19_000006_create_procedure_notes_table.php
└── 2026_01_19_000007_create_procedure_items_table.php

database/seeders/
├── ProcedureCategorySeeder.php          (ENT, O&G, General Surgery, etc.)
├── ProcedureServiceCategorySeeder.php   (Creates "Procedures" and "Registration" service categories)
└── DefaultProceduresSeeder.php          (Optional: seed common procedures as services)

app/Models/
├── ProcedureCategory.php
├── Procedure.php
├── PatientProcedure.php
├── ProcedureTeamMember.php
├── ProcedureNote.php
└── ProcedureItem.php

app/Http/Controllers/
├── ProcedureCategoryController.php      (CRUD for ENT, O&G, etc.)
└── PatientProcedureController.php       (Patient procedure management)

resources/views/admin/procedure-categories/
├── index.blade.php
├── create.blade.php
└── edit.blade.php

resources/views/admin/patient-procedures/
├── show.blade.php           (patient procedure detail - new tab)
├── print.blade.php          (printable procedure report with hospital branding)
├── cancel_modal.blade.php   (cancellation with refund modal)
└── partials/
    ├── team_member_modal.blade.php
    ├── add_note_modal.blade.php     (includes CKEditor WYSIWYG)
    ├── add_item_modal.blade.php
    └── outcome_section.blade.php
```

### Modified Files
```
app/Models/ApplicationStatu.php                    (add new category IDs to fillable)
app/Models/service.php                             (add procedure() relationship)
app/Http/Controllers/HospitalConfigController.php  (add validation rules)
app/Http/Controllers/ServiceController.php         (add procedure fields handling)
app/Http/Controllers/PatientController.php         (add registration billing)
app/Http/Controllers/BillingWorkbenchController.php (allow credit/debit payment from account)
app/Helpers/HmoHelper.php                          (add bundled item check method)

resources/views/admin/hospital-config/index.blade.php  (add category dropdowns)
resources/views/admin/services/create.blade.php        (add dynamic procedure form)
resources/views/admin/services/edit.blade.php          (add dynamic procedure form)
resources/views/admin/patients/create.blade.php        (add registration fee select)
resources/views/admin/reception-workbench/partials/new_patient_modal.blade.php (add registration fee select)
resources/views/admin/billing/workbench.blade.php      (show account payment option always, warn on debit)
resources/views/admin/encounters/new_encounter.blade.php (add procedures tab)
resources/views/admin/partials/sidebar.blade.php       (add procedure categories menu)

routes/web.php                                         (add procedure routes)
```

### Patient Account (EXISTING - No New Tables Needed!)
```
✅ ALREADY EXISTS:
- patient_accounts table (patient_id, balance - can go negative)
- PatientAccount model
- payments table with payment_type: ACC_DEPOSIT, ACC_WITHDRAW, ACC_ADJUSTMENT
- Billing workbench already has "Pay from Account Balance" option

CHANGES NEEDED (small modifications only):
- BillingWorkbenchController.php: Remove balance check, allow negative balance
- workbench.blade.php: Show account payment option always (not just when balance > 0)
- workbench.blade.php: Change validation to warning instead of block
```

---

## Part 6: Key Technical Decisions

### 6.1 Why Procedures Link to Services?
**Decision:** `procedures.service_id → services.id` with service category = "Procedures"

**Benefits:**
- **HMO Integration:** Tariffs configured on services work seamlessly
- **Billing Reuse:** Uses existing `ProductOrServiceRequest` → Payment flow
- **Price Management:** Prices managed via existing price records
- **Familiar UI:** Staff use Services CRUD they already know
- **Validation Flow:** `canDeliverService()` works without modification

**Alternative Rejected:** Standalone procedures table with duplicate billing logic

### 6.2 Why Dynamic Form in Services CRUD?
Instead of separate Procedure CRUD pages, we show additional fields when the Procedure service category is selected:

- **Single Source of Truth:** Service + procedure metadata in one form
- **Atomic Creation:** Service and procedure record created together
- **Familiar Workflow:** Users create "services" not "procedures"
- **Consistency:** Same pattern as other service types

### 6.3 Why Separate `procedure_categories` Table?
Procedure categories (ENT, O&G, General Surgery) are different from service categories:

- Service categories define billing/routing behavior
- Procedure categories classify medical specialty
- A service category "Procedures" contains many procedures from different procedure categories

### 6.4 Why Separate `procedure_items` Table?
- Allows tracking which items are bundled vs separate
- Maintains referential integrity
- Enables audit trail of what was added during procedure

### 6.5 Why Link to Existing Request Models?
- Reuses proven billing flow
- Items still flow through workbenches normally
- HMO validation logic remains consistent

### 6.6 Bundled Billing Strategy
- Bundled items don't create separate `ProductOrServiceRequest`
- They link to procedure's main billing entry
- Delivery check looks at procedure's payment/validation status
- This keeps billing simple: one line item for the procedure package

---

## Part 4.2: Gap Implementation Checklist (Remaining Tasks)

> **Status as of January 19, 2026:** ✅ **ALL CORE FEATURES IMPLEMENTED**
> 
> After comprehensive code review, all major features from the specification are complete.
> The items below have been verified as implemented.

### ✅ COMPLETE: Encounter Procedure Tab Integration

| # | Task | Spec Reference | File | Status |
|---|------|----------------|------|--------|
| G1 | **API route for procedures** - Uses existing `live-search-services` route with `category_id` filter | Part 3.6, Task #52 | `routes/web.php` (line 487) | ✅ |
| G2 | **Procedures tab in new_encounter** - Tab button exists alongside Labs, Imaging, Medications | Part 3.5.1, Task #53 | `resources/views/admin/doctors/new_encounter.blade.php` (line 147-152) | ✅ |
| G3 | **Procedure selection dropdown** - Search input with live results, priority dropdown, Add button | Part 3.5.1, Task #54 | `resources/views/admin/doctors/partials/procedures.blade.php` | ✅ |
| G4 | **Pending Procedures list** - Table with status, priority, price, HMO status, actions | Part 3.5.1, Task #55 | `resources/views/admin/doctors/partials/procedures.blade.php` | ✅ |
| G5 | **AJAX handler for loading procedures** - Uses `live-search-services` with `procedureCategoryId` | Part 3.5.1 | `procedures.blade.php` (line 415-419) | ✅ |
| G6 | **AJAX handler for adding procedure** - `saveProcedures()` function implemented | Part 3.5.1 | `procedures.blade.php` | ✅ |
| G7 | **Pending procedures list** - `procedureHistoryList` DataTable implemented | Part 3.5.1 | `procedures.blade.php` | ✅ |
| G8 | **Cancelling procedure** - Delete functionality in history table | Part 3.5.1 | `EncounterController::deleteProcedure()` | ✅ |
| G9 | **Store method in EncounterController** - `saveProcedures()` method exists | Part 3.4, 3.6 | `routes/web.php` (line 243) | ✅ |

### ✅ COMPLETE: Navigation & Admin UI

| # | Task | Spec Reference | File | Status |
|---|------|----------------|------|--------|
| G10 | **Procedure Categories in sidebar** - Link exists under Services menu | Part 5, Task #49 | `sidebar.blade.php` (line 554) | ✅ |
| G11 | **Procedures Catalog link** - Filtered services list link exists | Part 5 | `sidebar.blade.php` (line 550-552) | ✅ |

### ✅ COMPLETE: HMO Executive Workbench Integration

| # | Task | Spec Reference | File | Status |
|---|------|----------------|------|--------|
| G12 | **Procedures type filter** - `case 'procedure'` filter implemented | Part 3.8, Task #105 | `HmoWorkbenchController.php` (line 142-144) | ✅ |
| G13 | **Procedures in HMO queue** - `->whereHas('procedure')` query implemented | Part 3.8, Task #106 | `HmoWorkbenchController.php` | ✅ |
| G14 | **Auth code for procedures** - Uses same ProductOrServiceRequest approval flow | Part 3.8, Task #107 | `HmoWorkbenchController.php` | ✅ |

### ✅ COMPLETE: Credit Payment (Account Debit)

| # | Task | Spec Reference | File | Status |
|---|------|----------------|------|--------|
| G15 | **Show account option always** - Comment: "Credit facility: allow payments even with zero or negative balance" | Part 3.2.4, Task #39 | `workbench.blade.php` (line 4619-4621) | ✅ |
| G16 | **Warning instead of blocking** - Confirmation dialog with balance warning | Part 3.2.4, Task #40 | `workbench.blade.php` (line 5346) | ✅ |
| G17 | **Balance preview** - Shows current and new balance including negative state | Part 3.2.4, Task #41 | `workbench.blade.php` | ✅ |
| G18 | **Controller allows debit** - `$account->balance -= $total` (can go negative) | Part 3.2.4, Task #38 | `BillingWorkbenchController.php` (line 567) | ✅ |

### 🔵 REMAINING: Testing & Verification Tasks

| # | Task | Spec Reference | Status |
|---|------|----------------|--------|
| G19 | Test: Create procedure service via Services CRUD | Task #108 | ☐ |
| G20 | Test: Request procedure in encounter | Task #115 | ☐ |
| G21 | Test: Add bundled lab to procedure | Task #116 | ☐ |
| G22 | Test: Add non-bundled lab to procedure | Task #117 | ☐ |
| G23 | Test: Deliver bundled item | Task #118 | ☐ |
| G24 | Test: Cancel procedure with refund | Task #124 | ☐ |
| G25 | Test: Print procedure report | Task #126 | ☐ |
| G26 | Test: Full end-to-end flow | Task #127 | ☐ |

---

### 📊 Implementation Summary

| Category | Implemented | Remaining | Coverage |
|----------|-------------|-----------|----------|
| Encounter Integration (G1-G9) | 9/9 | 0 | **100%** ✅ |
| Navigation (G10-G11) | 2/2 | 0 | **100%** ✅ |
| HMO Integration (G12-G14) | 3/3 | 0 | **100%** ✅ |
| Credit Payment (G15-G18) | 4/4 | 0 | **100%** ✅ |
| Testing (G19-G26) | 0/8 | 8 | 0% (Manual) |
| **TOTAL CODE** | **18/18** | **0** | **100%** ✅ |

---

### 📁 Key Implementation Files

**Encounter Procedures:**
- `resources/views/admin/doctors/new_encounter.blade.php` - Main encounter page with Procedures tab
- `resources/views/admin/doctors/partials/procedures.blade.php` - Full procedure UI (1250 lines)
- `app/Http/Controllers/EncounterController.php` - saveProcedures, deleteProcedure, etc.

**Procedure Detail Page:**
- `resources/views/admin/patient-procedures/show.blade.php` - Detail view (1525 lines)
- `resources/views/admin/patient-procedures/print.blade.php` - Print report (505 lines)
- `app/Http/Controllers/PatientProcedureController.php` - Items, team, notes management (982 lines)

**Admin CRUD:**
- `resources/views/admin/procedure-categories/` - CRUD views for procedure categories
- `resources/views/admin/service/create.blade.php` & `edit.blade.php` - Dynamic procedure fields

**Billing Integration:**
- `app/Http/Controllers/BillingWorkbenchController.php` - Credit payment with negative balance
- `app/Http/Controllers/HmoWorkbenchController.php` - Procedure type filter and validation

**Bundled Billing:**
- `app/Helpers/HmoHelper.php` - `isBundledItem()` and `canDeliverBundledItem()` methods
- `app/Http/Controllers/LabWorkbenchController.php` - Bundled item delivery checks
- `app/Http/Controllers/ImagingWorkbenchController.php` - Bundled item delivery checks
- `app/Http/Controllers/PharmacyWorkbenchController.php` - Bundled item delivery checks

---

## Appendix A: Standard Surgical Team Roles

| Role | Description |
|------|-------------|
| Chief Surgeon / Lead Surgeon | Primary operating surgeon |
| First Assistant | Assists primary surgeon |
| Second Assistant | Additional surgical assistant |
| Anesthesiologist | Physician managing anesthesia |
| Nurse Anesthetist (CRNA) | Nurse providing anesthesia care |
| Scrub Nurse / Scrub Tech | Maintains sterile field, passes instruments |
| Circulating Nurse | Non-sterile support, documentation |
| Surgical First Assistant | Licensed assistant to surgeon |
| Perfusionist | Operates heart-lung machine (cardiac) |
| Radiologist | Imaging guidance specialist |
| Pathologist | Intra-operative specimen analysis |
| **Other** | Custom role - user specifies in `custom_role` field |

## Appendix B: Procedure Categories to Seed

| Category | Code |
|----------|------|
| General Surgery | GS |
| ENT (Ear, Nose, Throat) | ENT |
| Ophthalmology | OPH |
| Dental / Oral Surgery | DENT |
| Obstetrics & Gynaecology | OG |
| Orthopaedic Surgery | ORTH |
| Urology | URO |
| Cardiothoracic Surgery | CTS |
| Neurosurgery | NEURO |
| Plastic Surgery | PLAS |
| Paediatric Surgery | PAED |
| Endoscopy | ENDO |
| Minor Procedures | MINOR |
