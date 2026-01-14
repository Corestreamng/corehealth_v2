# Pharmacy Workbench Implementation Plan

## Executive Summary
This document outlines the complete architecture and implementation plan for the Pharmacy Workbench, based on analysis of the current prescription workflow, billing system, and HMO integration. The pharmacy workbench will follow the same pattern as the reception workbench - a centralized interface for all pharmacy operations.

---

## Current System Analysis

### Prescription Flow (As-Is)
1. **Doctor Creates Prescription** (new_encounter.blade.php)
   - Doctor selects products from searchProducts() function
   - Adds dose/frequency for each medication
   - Saves via `/encounters/{id}/save-prescriptions` endpoint
   - Creates ProductRequest with status = 1 (Requested)

2. **Billing Processes Payment** (show1.blade.php → presc.blade.php)
   - View: `admin/patients/partials/presc.blade.php`
   - Route: `product-bill-patient`
   - Controller: `ProductRequestController::bill()`
   - Actions:
     - Displays requested prescriptions (status = 1) in DataTable
     - Allows selection of items to bill
     - Creates ProductOrServiceRequest for each item
     - **Applies HMO tariff using HmoHelper::applyHmoTariff()**
     - Sets payable_amount, claims_amount, coverage_mode, validation_status
     - Decrements stock quantity
     - Updates ProductRequest: status = 2 (Billed), billed_by, billed_date

3. **Pharmacy Dispenses Medication** (show1.blade.php → presc.blade.php)
   - View: Same as billing (presc.blade.php)
   - Route: `product-dispense-patient`
   - Controller: `ProductRequestController::dispense()`
   - Actions:
     - Displays billed prescriptions (status = 2) in DataTable
     - **Validates HMO delivery using HmoHelper::canDeliverService()**
     - Checks payment status and HMO approval requirements
     - Updates ProductRequest: status = 3 (Dispensed), dispensed_by, dispense_date

### Key Models

#### ProductRequest
```php
Fillable:
- product_request_id (FK to ProductOrServiceRequest)
- billed_by, billed_date
- dispensed_by, dispense_date
- product_id, encounter_id, patient_id, doctor_id
- dose (dosage instructions)
- status (1=Requested, 2=Billed, 3=Dispensed, 0=Dismissed)
- deleted_by, deletion_reason (soft delete tracking)

Relationships:
- productOrServiceRequest() - billing/payment record
- product() - medication details
- encounter() - consultation context
- patient() - who receives it
- doctor() - who prescribed it
- biller() - who processed payment
- dispenser() - who gave medication
```

#### ProductOrServiceRequest (Billing Bridge)
```php
Fillable:
- invoice_id, payment_id
- user_id, staff_user_id
- product_id, service_id
- qty, discount
- payable_amount (what patient pays)
- claims_amount (what HMO covers)
- coverage_mode (Full/Partial/None)
- validation_status (HMO pre-approval status)
- auth_code, validated_by, validated_at, validation_notes
- hmo_remittance_id, submitted_to_hmo_at, hmo_submission_batch

Relationships:
- payment() - payment record
- productRequest() - prescription details with dose
- remittance() - HMO claims batch
```

### HMO Integration Points
1. **At Billing**: `HmoHelper::applyHmoTariff($patient_id, $product_id, $service_id)`
   - Returns: payable_amount, claims_amount, coverage_mode, validation_status
   
2. **At Dispensing**: `HmoHelper::canDeliverService($productOrServiceRequest)`
   - Returns: can_deliver (bool), reason (string), hint (string)
   - Validates payment completion and HMO approval requirements

---

## Pharmacy Workbench Requirements

### User Stories
1. As a pharmacist, I want to see all pending prescriptions from all patients in one place
2. As a pharmacist, I want to verify billing/payment status before dispensing
3. As a pharmacist, I want to see HMO coverage details at every step
4. As a pharmacist, I want to search for patients and view their prescription history
5. As a pharmacist, I want to dispense medications with proper documentation
6. As a pharmacist, I want to generate reports on dispensing activity and stock usage
7. As a pharmacist, I want to see which prescriptions need HMO pre-authorization
8. As a pharmacist, I want to add ad-hoc products to a patient's bill

### Permissions Required
- `access-pharmacy-workbench` - Main access control
- `view-prescriptions` - See prescription queue
- `dispense-medications` - Mark medications as dispensed
- `bill-prescriptions` - Process payments (if pharmacy also bills)
- `view-hmo-coverage` - See HMO tariff details
- `add-adhoc-products` - Add non-prescribed items
- `pharmacy-reports` - Access reporting features

---

## Proposed Architecture

### Tab Structure (Following Reception Workbench Pattern)

```
┌─────────────────────────────────────────────────────────────┐
│  PHARMACY WORKBENCH                                         │
├─────────────────────────────────────────────────────────────┤
│  [Patient Search with Barcode] [Scan Badge]                │
├─────────────────────────────────────────────────────────────┤
│  TABS:                                                      │
│  1. Prescription Queue        ← Default/Primary tab         │
│  2. Patient Medications       ← Selected patient context    │
│  3. Dispensing History        ← Completed dispensing logs   │
│  4. Stock Alerts              ← Low stock warnings          │
│  5. HMO Queue                 ← Needs pre-authorization     │
│  6. Reports                   ← Daily/monthly reports       │
└─────────────────────────────────────────────────────────────┘
```

### Tab 1: Prescription Queue (Primary Workspace)

**Purpose**: Global view of all pending prescriptions across all patients

**Data Source**: ProductRequest with status IN (1, 2) - Requested or Billed

**Columns**:
- Patient Info (Name, File No, Photo)
- Medication Details (Product Code, Name, Dose/Frequency)
- Prescription Date/Time
- Doctor Name
- Billing Status
  - Not Billed (status=1): Yellow badge
  - Billed (status=2): Green badge with payment details
- HMO Coverage
  - Coverage Mode (Full/Partial/None)
  - Patient Pays: ₦X
  - HMO Claims: ₦Y
  - Validation Status (if HMO requires pre-auth)
- Actions
  - [View Patient] - Opens Patient Medications tab
  - [Dispense] - Available only if billed + HMO approved
  - [Bill & Dispense] - Quick action for cash patients

**Filters**:
- Status: All / Not Billed / Ready to Dispense / Needs HMO Auth
- Date Range: Today / This Week / Custom
- Doctor: Dropdown of prescribing doctors
- HMO: Filter by insurance provider
- Product: Search by medication name

**Real-time Features**:
- Auto-refresh every 30 seconds
- Toast notification for new prescriptions
- Highlight urgent/STAT orders

### Tab 2: Patient Medications (Context-Aware)

**Purpose**: Complete medication management for a selected patient

**Activated By**: 
- Clicking patient name from any tab
- Scanning patient barcode
- Searching patient in header

**Sub-tabs**:
1. **Pending Prescriptions** (status=1,2)
   - Table of unbilled/billed prescriptions
   - Quick Bill button (if unbilled)
   - Dispense button (if billed & approved)
   - Bulk operations (select multiple to dispense)

2. **Prescription History** (status=3)
   - All dispensed medications
   - Dispensed by, date/time
   - Dose given, quantity
   - Print receipt option

3. **Add Ad-hoc Products**
   - Product search field (like doctor's interface)
   - Add to cart with dosage
   - Bill immediately
   - Use case: Patient requests OTC items while picking up prescription

4. **Patient Summary Card** (Sidebar)
   - Patient photo, name, file number
   - Current allergies (WARNING if drug interaction)
   - HMO details: Provider, Coverage %, Auth Required?
   - Outstanding balance

**HMO Display**:
- Each prescription shows:
  ```
  ┌──────────────────────────────────────────┐
  │ Paracetamol 500mg Tabs                   │
  │ Dose: 2 tabs TDS x 5 days                │
  ├──────────────────────────────────────────┤
  │ Price: ₦1,500                            │
  │ HMO Coverage: 80% (₦1,200)               │
  │ Patient Pays: ₦300                       │
  │ Status: ✓ Approved (No auth required)    │
  └──────────────────────────────────────────┘
  ```

### Tab 3: Dispensing History

**Purpose**: Audit trail of all dispensed medications

**Data Source**: ProductRequest with status = 3, ordered by dispense_date DESC

**Columns**:
- Date/Time Dispensed
- Patient Name & File No
- Medication (Code + Name)
- Dose Given
- Prescribed By (Doctor)
- Dispensed By (Pharmacist)
- Payment Method (Cash/HMO/Mixed)
- Total Cost / Patient Paid / HMO Claimed

**Filters**:
- Date Range
- Dispensed By (filter by pharmacist)
- Payment Method
- HMO Provider

**Export Options**:
- Excel/CSV: For accounting reconciliation
- PDF: Daily dispensing report with totals

### Tab 4: Stock Alerts

**Purpose**: Proactive stock management

**Data Source**: Product model with stock relationships

**Display**:
- Products below reorder level
- Products with pending prescriptions but out of stock
- Frequently dispensed items running low

**Actions**:
- [Request Restock] - Creates procurement request
- [View Usage History] - Shows dispensing trends

### Tab 5: HMO Queue

**Purpose**: Manage prescriptions requiring HMO pre-authorization

**Data Source**: ProductOrServiceRequest where validation_status = 'pending_validation' OR coverage_mode requiring approval

**Columns**:
- Patient Name & HMO Details
- Medication Requiring Auth
- Total Claim Amount
- Auth Code (input field)
- Validated By / Validated At
- Actions:
  - [Enter Auth Code] - Updates validation_status
  - [Contact HMO] - Opens contact modal with HMO phone/email

**Workflow**:
1. Pharmacist sees prescription in HMO Queue
2. Contacts HMO via phone/portal
3. Receives authorization code
4. Enters code in system
5. Updates validation_status to 'validated'
6. Prescription moves to ready-to-dispense in main queue

### Tab 6: Reports

**Purpose**: Analytics and compliance reporting

**Report Types**:

1. **Daily Dispensing Summary**
   - Total prescriptions dispensed
   - Total revenue (Cash + HMO claims)
   - Breakdown by payment method
   - Top 10 dispensed medications
   - Pharmacist productivity

2. **HMO Claims Report**
   - Claims by HMO provider
   - Total claim amounts pending submission
   - Approved vs Pending claims
   - Rejected claims (if tracked)

3. **Stock Usage Report**
   - Medications dispensed vs stock levels
   - Expiry date warnings
   - Fast-moving vs slow-moving items

4. **Prescription Turnaround Time**
   - Average time from prescription to dispensing
   - Bottlenecks in workflow (billing delays, HMO auth delays)

**Export**: All reports exportable to Excel, PDF

---

## Technical Implementation Plan

### Phase 1: Backend Setup (Days 1-3)

#### 1.1 Create Controller
**File**: `app/Http/Controllers/PharmacyWorkbenchController.php`

**Methods**:
```php
class PharmacyWorkbenchController extends Controller
{
    // Main view
    public function index()
    
    // DataTable endpoints
    public function getPrescriptionQueue(Request $request) // Status 1,2
    public function getPatientPrescriptions($patientId, Request $request)
    public function getDispensingHistory(Request $request) // Status 3
    public function getHmoQueue(Request $request)
    
    // Actions
    public function dispenseMedication(Request $request)
    public function bulkDispense(Request $request)
    public function billAndDispense(Request $request) // Quick action
    
    // HMO Management
    public function updateHmoAuth(Request $request)
    
    // Reports
    public function dailySummaryReport(Request $request)
    public function hmoClaimsReport(Request $request)
    public function stockUsageReport(Request $request)
}
```

#### 1.2 Create Routes
**File**: `routes/pharmacy_workbench.php` (new file)

```php
Route::middleware(['auth'])->prefix('pharmacy-workbench')->name('pharmacy-workbench.')->group(function () {
    // Main view
    Route::get('/', [PharmacyWorkbenchController::class, 'index'])
        ->name('index')
        ->middleware('can:access-pharmacy-workbench');
    
    // DataTable endpoints
    Route::get('/prescription-queue', [PharmacyWorkbenchController::class, 'getPrescriptionQueue'])
        ->name('prescription-queue');
    Route::get('/patient/{patientId}/prescriptions', [PharmacyWorkbenchController::class, 'getPatientPrescriptions'])
        ->name('patient-prescriptions');
    Route::get('/dispensing-history', [PharmacyWorkbenchController::class, 'getDispensingHistory'])
        ->name('dispensing-history');
    Route::get('/hmo-queue', [PharmacyWorkbenchController::class, 'getHmoQueue'])
        ->name('hmo-queue');
    
    // Actions
    Route::post('/dispense', [PharmacyWorkbenchController::class, 'dispenseMedication'])
        ->name('dispense')
        ->middleware('can:dispense-medications');
    Route::post('/bulk-dispense', [PharmacyWorkbenchController::class, 'bulkDispense'])
        ->name('bulk-dispense')
        ->middleware('can:dispense-medications');
    Route::post('/bill-and-dispense', [PharmacyWorkbenchController::class, 'billAndDispense'])
        ->name('bill-and-dispense');
    
    // HMO Management
    Route::post('/hmo-auth/update', [PharmacyWorkbenchController::class, 'updateHmoAuth'])
        ->name('hmo-auth.update');
    
    // Reports
    Route::get('/reports/daily-summary', [PharmacyWorkbenchController::class, 'dailySummaryReport'])
        ->name('reports.daily-summary');
    Route::get('/reports/hmo-claims', [PharmacyWorkbenchController::class, 'hmoClaimsReport'])
        ->name('reports.hmo-claims');
    Route::get('/reports/stock-usage', [PharmacyWorkbenchController::class, 'stockUsageReport'])
        ->name('reports.stock-usage');
});
```

Register in `bootstrap/app.php` or `app/Providers/RouteServiceProvider.php`

#### 1.3 Update Models (If Needed)
- ProductRequest: Already has all needed fields
- ProductOrServiceRequest: Already has HMO fields
- Verify soft deletes are working properly

#### 1.4 Add Permissions/Roles
**File**: Database seeder or migration

```php
$permissions = [
    'access-pharmacy-workbench',
    'view-prescriptions',
    'dispense-medications',
    'bill-prescriptions',
    'view-hmo-coverage',
    'add-adhoc-products',
    'pharmacy-reports',
];

foreach ($permissions as $permission) {
    Permission::firstOrCreate(['name' => $permission]);
}

// Assign to Pharmacist role
$pharmacistRole = Role::firstOrCreate(['name' => 'Pharmacist']);
$pharmacistRole->syncPermissions($permissions);
```

### Phase 2: Frontend Development (Days 4-8)

#### 2.1 Create Main Blade View
**File**: `resources/views/admin/pharmacy/workbench.blade.php`

**Structure**:
```blade
@extends('admin.layouts.layout')

@section('content')
<div class="pharmacy-workbench-container">
    <!-- Header with Patient Search -->
    <div class="workbench-header">
        <h2>Pharmacy Workbench</h2>
        <div class="patient-search-bar">
            <input type="text" id="patient_search_global" placeholder="Search patient by name, file no, or scan barcode">
            <button id="scan_barcode_btn"><i class="fa fa-barcode"></i> Scan Badge</button>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs workbench-tabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#prescription-queue-tab">
                <i class="fa fa-list"></i> Prescription Queue
                <span class="badge bg-danger" id="queue_count">0</span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#patient-medications-tab" disabled id="patient_meds_tab_btn">
                <i class="fa fa-user"></i> Patient Medications
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#dispensing-history-tab">
                <i class="fa fa-history"></i> Dispensing History
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#hmo-queue-tab">
                <i class="fa fa-shield-alt"></i> HMO Queue
                <span class="badge bg-warning" id="hmo_queue_count">0</span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#reports-tab">
                <i class="fa fa-chart-bar"></i> Reports
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content workbench-content">
        <!-- Tab 1: Prescription Queue -->
        <div class="tab-pane fade show active" id="prescription-queue-tab">
            @include('admin.pharmacy.partials.prescription_queue')
        </div>

        <!-- Tab 2: Patient Medications -->
        <div class="tab-pane fade" id="patient-medications-tab">
            @include('admin.pharmacy.partials.patient_medications')
        </div>

        <!-- Tab 3: Dispensing History -->
        <div class="tab-pane fade" id="dispensing-history-tab">
            @include('admin.pharmacy.partials.dispensing_history')
        </div>

        <!-- Tab 4: HMO Queue -->
        <div class="tab-pane fade" id="hmo-queue-tab">
            @include('admin.pharmacy.partials.hmo_queue')
        </div>

        <!-- Tab 5: Reports -->
        <div class="tab-pane fade" id="reports-tab">
            @include('admin.pharmacy.partials.reports')
        </div>
    </div>
</div>

@include('admin.pharmacy.partials.modals')
@endsection

@section('scripts')
    @include('admin.pharmacy.partials.scripts')
@endsection
```

#### 2.2 Create Partial Views
**Files**: `resources/views/admin/pharmacy/partials/`

1. **prescription_queue.blade.php** - Main queue DataTable
2. **patient_medications.blade.php** - Patient-specific view with sub-tabs
3. **dispensing_history.blade.php** - Historical DataTable
4. **hmo_queue.blade.php** - HMO authorization queue
5. **reports.blade.php** - Report generation interface
6. **modals.blade.php** - Dispense modal, HMO auth modal, etc.
7. **scripts.blade.php** - JavaScript for DataTables, AJAX, etc.

#### 2.3 Key JavaScript Functions

```javascript
// Global patient context
let currentPatientId = null;

// Initialize all DataTables
function initPrescriptionQueueTable() {
    $('#prescription_queue_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '/pharmacy-workbench/prescription-queue',
            data: function(d) {
                d.status_filter = $('#status_filter').val();
                d.date_range = $('#date_range').val();
                d.hmo_filter = $('#hmo_filter').val();
            }
        },
        columns: [
            { data: 'patient_info', name: 'patient_info', orderable: false },
            { data: 'medication_details', name: 'medication_details' },
            { data: 'prescription_date', name: 'created_at' },
            { data: 'doctor_name', name: 'doctor_name' },
            { data: 'billing_status', name: 'status' },
            { data: 'hmo_coverage', name: 'hmo_coverage', orderable: false },
            { data: 'actions', name: 'actions', orderable: false }
        ],
        order: [[2, 'desc']] // Latest first
    });
}

// Dispense medication
function dispenseMedication(productRequestId) {
    // Show confirmation modal with HMO coverage details
    $('#dispense_modal').modal('show');
    // Pre-fill modal with product request details
    loadDispenseDetails(productRequestId);
}

function confirmDispense() {
    $.ajax({
        url: '/pharmacy-workbench/dispense',
        method: 'POST',
        data: {
            product_request_id: $('#dispense_pr_id').val(),
            quantity_dispensed: $('#quantity_dispensed').val(),
            notes: $('#dispense_notes').val(),
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            toastr.success(response.message);
            $('#dispense_modal').modal('hide');
            refreshPrescriptionQueue();
            refreshDispensingHistory();
        },
        error: function(xhr) {
            toastr.error(xhr.responseJSON.message || 'Error dispensing medication');
        }
    });
}

// Load patient context
function loadPatientMedications(patientId) {
    currentPatientId = patientId;
    $('#patient_meds_tab_btn').prop('disabled', false).click();
    
    // Reload patient-specific DataTables
    $('#patient_pending_table').DataTable().ajax.url(
        `/pharmacy-workbench/patient/${patientId}/prescriptions?status=pending`
    ).load();
    
    // Load patient summary card
    loadPatientSummaryCard(patientId);
}

// HMO Authorization
function updateHmoAuth(productOrServiceRequestId) {
    $.ajax({
        url: '/pharmacy-workbench/hmo-auth/update',
        method: 'POST',
        data: {
            product_or_service_request_id: productOrServiceRequestId,
            auth_code: $('#auth_code_input').val(),
            validation_notes: $('#validation_notes').val(),
            _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function(response) {
            toastr.success('HMO authorization updated');
            refreshHmoQueue();
            refreshPrescriptionQueue();
        },
        error: function(xhr) {
            toastr.error('Error updating HMO authorization');
        }
    });
}

// Auto-refresh (every 30 seconds)
setInterval(function() {
    refreshPrescriptionQueue();
    updateQueueCounts();
}, 30000);
```

### Phase 3: Controller Implementation (Days 9-11)

#### 3.1 Prescription Queue DataTable
```php
public function getPrescriptionQueue(Request $request)
{
    $query = ProductRequest::with([
        'product',
        'patient.user',
        'patient.hmo',
        'doctor',
        'biller',
        'productOrServiceRequest.payment'
    ])
    ->whereIn('status', [1, 2]) // Requested or Billed
    ->orderBy('created_at', 'DESC');

    // Apply filters
    if ($request->filled('status_filter')) {
        $query->where('status', $request->status_filter);
    }

    if ($request->filled('date_range')) {
        $dates = explode(' - ', $request->date_range);
        $query->whereBetween('created_at', [
            Carbon::parse($dates[0])->startOfDay(),
            Carbon::parse($dates[1])->endOfDay()
        ]);
    }

    if ($request->filled('hmo_filter')) {
        $query->whereHas('patient', function($q) use ($request) {
            $q->where('hmo_id', $request->hmo_filter);
        });
    }

    return DataTables::of($query)
        ->addColumn('patient_info', function($pr) {
            $patient = $pr->patient;
            $user = $patient->user;
            $photoUrl = $user && $user->filename 
                ? asset('storage/image/user/' . $user->filename)
                : asset('assets/img/default-avatar.png');
            
            return view('admin.pharmacy.partials.columns.patient_info', compact('patient', 'user', 'photoUrl'))->render();
        })
        ->addColumn('medication_details', function($pr) {
            return view('admin.pharmacy.partials.columns.medication_details', compact('pr'))->render();
        })
        ->addColumn('billing_status', function($pr) {
            if ($pr->status == 1) {
                return '<span class="badge bg-warning">Not Billed</span>';
            } elseif ($pr->status == 2) {
                $payment = $pr->productOrServiceRequest->payment ?? null;
                if ($payment) {
                    return '<span class="badge bg-success">Billed</span><br><small>Paid: ₦' . number_format($payment->total, 2) . '</small>';
                }
                return '<span class="badge bg-success">Billed</span>';
            }
        })
        ->addColumn('hmo_coverage', function($pr) {
            $posr = $pr->productOrServiceRequest;
            if (!$posr) return 'N/A';
            
            return view('admin.pharmacy.partials.columns.hmo_coverage', compact('posr'))->render();
        })
        ->addColumn('actions', function($pr) {
            return view('admin.pharmacy.partials.columns.actions', compact('pr'))->render();
        })
        ->rawColumns(['patient_info', 'medication_details', 'billing_status', 'hmo_coverage', 'actions'])
        ->make(true);
}
```

#### 3.2 Dispense Medication
```php
public function dispenseMedication(Request $request)
{
    $request->validate([
        'product_request_id' => 'required|exists:product_requests,id',
        'quantity_dispensed' => 'required|numeric|min:1',
        'notes' => 'nullable|string'
    ]);

    try {
        DB::beginTransaction();

        $productRequest = ProductRequest::with('productOrServiceRequest')->findOrFail($request->product_request_id);

        // Validate status
        if ($productRequest->status != 2) {
            return response()->json(['message' => 'Prescription must be billed before dispensing'], 400);
        }

        // Check HMO delivery requirements
        if ($productRequest->productOrServiceRequest) {
            $deliveryCheck = HmoHelper::canDeliverService($productRequest->productOrServiceRequest);
            if (!$deliveryCheck['can_deliver']) {
                return response()->json([
                    'message' => $deliveryCheck['reason'],
                    'hint' => $deliveryCheck['hint']
                ], 400);
            }
        }

        // Update ProductRequest
        $productRequest->update([
            'status' => 3, // Dispensed
            'dispensed_by' => Auth::id(),
            'dispense_date' => now()
        ]);

        // Audit/Activity log
        activity()
            ->performedOn($productRequest)
            ->causedBy(Auth::user())
            ->log('Dispensed medication: ' . $productRequest->product->product_name);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Medication dispensed successfully'
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['message' => 'Error dispensing medication: ' . $e->getMessage()], 500);
    }
}
```

#### 3.3 Bulk Dispense
```php
public function bulkDispense(Request $request)
{
    $request->validate([
        'product_request_ids' => 'required|array',
        'product_request_ids.*' => 'exists:product_requests,id'
    ]);

    try {
        DB::beginTransaction();

        $dispensedCount = 0;
        $errors = [];

        foreach ($request->product_request_ids as $prId) {
            $productRequest = ProductRequest::with('productOrServiceRequest')->find($prId);

            // Validate each request
            if ($productRequest->status != 2) {
                $errors[] = "PR#{$prId}: Not billed";
                continue;
            }

            if ($productRequest->productOrServiceRequest) {
                $deliveryCheck = HmoHelper::canDeliverService($productRequest->productOrServiceRequest);
                if (!$deliveryCheck['can_deliver']) {
                    $errors[] = "PR#{$prId}: {$deliveryCheck['reason']}";
                    continue;
                }
            }

            // Dispense
            $productRequest->update([
                'status' => 3,
                'dispensed_by' => Auth::id(),
                'dispense_date' => now()
            ]);

            $dispensedCount++;
        }

        DB::commit();

        $message = "Successfully dispensed {$dispensedCount} medication(s)";
        if (count($errors) > 0) {
            $message .= ". Errors: " . implode('; ', $errors);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'dispensed_count' => $dispensedCount,
            'errors' => $errors
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['message' => 'Bulk dispense error: ' . $e->getMessage()], 500);
    }
}
```

#### 3.4 HMO Authorization Update
```php
public function updateHmoAuth(Request $request)
{
    $request->validate([
        'product_or_service_request_id' => 'required|exists:product_or_service_requests,id',
        'auth_code' => 'required|string',
        'validation_notes' => 'nullable|string'
    ]);

    try {
        $posr = ProductOrServiceRequest::findOrFail($request->product_or_service_request_id);

        $posr->update([
            'auth_code' => $request->auth_code,
            'validation_status' => 'validated',
            'validated_by' => Auth::id(),
            'validated_at' => now(),
            'validation_notes' => $request->validation_notes
        ]);

        return response()->json([
            'success' => true,
            'message' => 'HMO authorization updated successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json(['message' => 'Error updating HMO auth: ' . $e->getMessage()], 500);
    }
}
```

#### 3.5 Daily Summary Report
```php
public function dailySummaryReport(Request $request)
{
    $startDate = $request->input('start_date', now()->startOfDay());
    $endDate = $request->input('end_date', now()->endOfDay());

    $dispensedPrescriptions = ProductRequest::with(['productOrServiceRequest.payment', 'product'])
        ->where('status', 3)
        ->whereBetween('dispense_date', [$startDate, $endDate])
        ->get();

    $totalDispensed = $dispensedPrescriptions->count();
    $totalRevenue = $dispensedPrescriptions->sum(function($pr) {
        return $pr->productOrServiceRequest->payment->total ?? 0;
    });
    $cashRevenue = $dispensedPrescriptions->filter(function($pr) {
        return optional($pr->productOrServiceRequest)->coverage_mode == 'none';
    })->sum(function($pr) {
        return $pr->productOrServiceRequest->payment->total ?? 0;
    });
    $hmoClaimsTotal = $dispensedPrescriptions->sum(function($pr) {
        return $pr->productOrServiceRequest->claims_amount ?? 0;
    });

    // Top 10 medications
    $topMedications = $dispensedPrescriptions->groupBy('product_id')
        ->map(function($group) {
            return [
                'product' => $group->first()->product,
                'count' => $group->count()
            ];
        })
        ->sortByDesc('count')
        ->take(10);

    // Pharmacist productivity
    $dispensedByPharmacist = $dispensedPrescriptions->groupBy('dispensed_by')
        ->map(function($group) {
            return [
                'pharmacist' => userfullname($group->first()->dispensed_by),
                'count' => $group->count()
            ];
        });

    return view('admin.pharmacy.reports.daily_summary', compact(
        'totalDispensed',
        'totalRevenue',
        'cashRevenue',
        'hmoClaimsTotal',
        'topMedications',
        'dispensedByPharmacist',
        'startDate',
        'endDate'
    ));
}
```

### Phase 4: HMO Integration Deep Dive (Days 12-13)

#### 4.1 HMO Coverage Display Component
Create reusable Blade component: `resources/views/components/hmo-coverage-card.blade.php`

```blade
@props(['productOrServiceRequest'])

@php
    $posr = $productOrServiceRequest;
    $coverageIcon = match($posr->coverage_mode ?? 'none') {
        'full' => 'fa-check-circle text-success',
        'partial' => 'fa-exclamation-circle text-warning',
        default => 'fa-times-circle text-secondary'
    };
@endphp

<div class="hmo-coverage-card border rounded p-2">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <strong><i class="fa {{ $coverageIcon }}"></i> HMO Coverage</strong>
        <span class="badge bg-{{ $posr->coverage_mode == 'full' ? 'success' : ($posr->coverage_mode == 'partial' ? 'warning' : 'secondary') }}">
            {{ ucfirst($posr->coverage_mode ?? 'None') }}
        </span>
    </div>
    
    @if($posr && $posr->coverage_mode != 'none')
        <div class="row g-1 small">
            <div class="col-6">
                <strong>Total:</strong> ₦{{ number_format($posr->payable_amount + $posr->claims_amount, 2) }}
            </div>
            <div class="col-6">
                <strong>Patient Pays:</strong> <span class="text-success">₦{{ number_format($posr->payable_amount, 2) }}</span>
            </div>
            <div class="col-6">
                <strong>HMO Claims:</strong> <span class="text-primary">₦{{ number_format($posr->claims_amount, 2) }}</span>
            </div>
            <div class="col-6">
                @if($posr->validation_status == 'validated')
                    <span class="badge bg-success"><i class="fa fa-check"></i> Approved</span>
                @elseif($posr->validation_status == 'pending_validation')
                    <span class="badge bg-warning"><i class="fa fa-clock"></i> Needs Auth</span>
                @else
                    <span class="badge bg-secondary">No Auth Required</span>
                @endif
            </div>
        </div>
        
        @if($posr->auth_code)
            <div class="mt-1 small">
                <strong>Auth Code:</strong> <code>{{ $posr->auth_code }}</code>
            </div>
        @endif
    @else
        <small class="text-muted">Cash Payment - No HMO Coverage</small>
    @endif
</div>
```

Usage: `<x-hmo-coverage-card :productOrServiceRequest="$pr->productOrServiceRequest" />`

#### 4.2 HMO Validation Guards
Before dispensing, always check:

```php
// In controller before dispensing
if ($productRequest->productOrServiceRequest) {
    $posr = $productRequest->productOrServiceRequest;
    
    // Check if HMO requires validation
    if ($posr->coverage_mode != 'none' && $posr->validation_status == 'pending_validation') {
        return response()->json([
            'message' => 'HMO pre-authorization required before dispensing',
            'hint' => 'Please obtain authorization code from HMO and update in HMO Queue tab'
        ], 400);
    }
    
    // Use HmoHelper to validate delivery
    $deliveryCheck = HmoHelper::canDeliverService($posr);
    if (!$deliveryCheck['can_deliver']) {
        return response()->json([
            'message' => $deliveryCheck['reason'],
            'hint' => $deliveryCheck['hint']
        ], 400);
    }
}
```

### Phase 5: UI/UX Enhancements (Days 14-15)

#### 5.1 Real-time Updates with Livewire (Optional) or Polling

**Option A: JavaScript Polling (Simpler)**
```javascript
// Auto-refresh every 30 seconds
let autoRefreshInterval = setInterval(function() {
    if ($('#prescription-queue-tab').hasClass('active')) {
        $('#prescription_queue_table').DataTable().ajax.reload(null, false);
        updateQueueCounts();
    }
}, 30000);

function updateQueueCounts() {
    $.get('/pharmacy-workbench/queue-counts', function(data) {
        $('#queue_count').text(data.prescription_queue_count);
        $('#hmo_queue_count').text(data.hmo_queue_count);
    });
}
```

**Option B: Pusher/WebSockets (Advanced)**
- Broadcast new prescriptions in real-time
- Show toast notification when doctor creates prescription

#### 5.2 Barcode Scanner Integration
```javascript
let barcodeBuffer = '';
let barcodeTimeout;

$(document).on('keypress', function(e) {
    // Check if focused on search input
    if ($('#patient_search_global').is(':focus') || $('#consult_presc_search').is(':focus')) {
        clearTimeout(barcodeTimeout);
        barcodeBuffer += String.fromCharCode(e.which);
        
        barcodeTimeout = setTimeout(function() {
            if (barcodeBuffer.length > 5) { // Assume barcode is longer than 5 chars
                searchPatientByBarcode(barcodeBuffer);
            }
            barcodeBuffer = '';
        }, 100);
    }
});

function searchPatientByBarcode(barcode) {
    $.get('/pharmacy-workbench/search-patient', { barcode: barcode }, function(response) {
        if (response.patient) {
            loadPatientMedications(response.patient.id);
            toastr.success('Patient loaded: ' + response.patient.name);
        } else {
            toastr.error('Patient not found');
        }
    });
}
```

#### 5.3 Keyboard Shortcuts
```javascript
// Ctrl + Q: Focus prescription queue
// Ctrl + P: Focus patient search
// Ctrl + D: Quick dispense selected
// Esc: Clear selection

$(document).on('keydown', function(e) {
    if (e.ctrlKey && e.key === 'q') {
        e.preventDefault();
        $('#prescription-queue-tab').click();
    }
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        $('#patient_search_global').focus();
    }
    if (e.key === 'Escape') {
        clearPatientContext();
    }
});
```

### Phase 6: Testing & Deployment (Days 16-18)

#### 6.1 Test Scenarios
1. **Doctor creates prescription** → Verify appears in Pharmacy Queue (status=1)
2. **Billing processes payment** → Verify status changes to 2, HMO coverage calculated
3. **Pharmacist dispenses** → Verify status changes to 3, dispensed_by recorded
4. **HMO requiring auth** → Verify blocked until auth code entered
5. **Bulk dispense** → Verify multiple prescriptions processed correctly
6. **Stock decrement** → Verify stock reduces after billing (already implemented)
7. **Reports accuracy** → Verify daily summary totals match database
8. **Permissions** → Verify non-pharmacy users cannot access

#### 6.2 Deployment Checklist
- [ ] Run migrations (if any added)
- [ ] Seed permissions
- [ ] Clear cache: `php artisan cache:clear`, `php artisan config:clear`
- [ ] Compile assets: `npm run production`
- [ ] Test on staging environment
- [ ] Train pharmacy staff
- [ ] Monitor for first 24 hours

---

## Database Migrations (If Needed)

### Check Existing Schema
Current ProductRequest schema seems sufficient, but verify:

```sql
-- Ensure these columns exist
ALTER TABLE product_requests ADD COLUMN IF NOT EXISTS billed_by INT UNSIGNED NULL;
ALTER TABLE product_requests ADD COLUMN IF NOT EXISTS billed_date DATETIME NULL;
ALTER TABLE product_requests ADD COLUMN IF NOT EXISTS dispensed_by INT UNSIGNED NULL;
ALTER TABLE product_requests ADD COLUMN IF NOT EXISTS dispense_date DATETIME NULL;
ALTER TABLE product_requests ADD COLUMN IF NOT EXISTS deleted_by INT UNSIGNED NULL;
ALTER TABLE product_requests ADD COLUMN IF NOT EXISTS deletion_reason TEXT NULL;

-- Ensure ProductOrServiceRequest has HMO fields
ALTER TABLE product_or_service_requests ADD COLUMN IF NOT EXISTS validation_status VARCHAR(50) NULL;
ALTER TABLE product_or_service_requests ADD COLUMN IF NOT EXISTS auth_code VARCHAR(100) NULL;
ALTER TABLE product_or_service_requests ADD COLUMN IF NOT EXISTS validated_by INT UNSIGNED NULL;
ALTER TABLE product_or_service_requests ADD COLUMN IF NOT EXISTS validated_at DATETIME NULL;
ALTER TABLE product_or_service_requests ADD COLUMN IF NOT EXISTS validation_notes TEXT NULL;
```

No new tables needed - existing schema supports all features.

---

## Navigation Integration

### Add to Sidebar
**File**: `resources/views/admin/partials/sidebar.blade.php`

```blade
@can('access-pharmacy-workbench')
<li class="nav-item">
    <a href="{{ route('pharmacy-workbench.index') }}" class="nav-link {{ request()->routeIs('pharmacy-workbench.*') ? 'active' : '' }}">
        <i class="fa fa-pills"></i>
        <span>Pharmacy Workbench</span>
    </a>
</li>
@endcan
```

### Dashboard Widget (Optional)
Add quick stats to receptionist or pharmacy dashboard:
- Pending Prescriptions: X
- Awaiting HMO Auth: Y
- Dispensed Today: Z

---

## Success Metrics

### KPIs to Track
1. **Efficiency**:
   - Average time from prescription to dispensing
   - Prescriptions dispensed per pharmacist per day
   
2. **Accuracy**:
   - Error rate (wrong medication dispensed)
   - HMO claim rejection rate
   
3. **Financial**:
   - Daily dispensing revenue
   - HMO claims vs cash ratio
   - Outstanding HMO claims amount
   
4. **Patient Experience**:
   - Patient wait time (prescription created → dispensed)
   - Percentage of prescriptions dispensed same day

---

## Future Enhancements (Post-MVP)

1. **E-Prescribing Integration**
   - QR code on prescription printout
   - Patient mobile app to track prescription status
   
2. **Automated Stock Replenishment**
   - Auto-generate purchase orders when stock hits reorder level
   - Integration with suppliers
   
3. **Drug Interaction Checking**
   - Alert pharmacist if patient has allergies
   - Check for contraindications with current medications
   
4. **SMS Notifications**
   - Notify patient when prescription ready for pickup
   - Remind patient to take medications
   
5. **Advanced HMO Portal**
   - Real-time eligibility verification
   - Automated claims submission
   - Track claim approval/rejection status
   
6. **Pharmacy Analytics Dashboard**
   - Visualize dispensing trends
   - Identify fast-moving vs slow-moving drugs
   - Forecast demand

---

## Summary & Next Steps

### What We've Designed
✅ **Complete pharmacy workbench** mirroring reception workbench pattern  
✅ **6 functional tabs** covering entire medication lifecycle  
✅ **HMO integration** visible at every step  
✅ **Full audit trail** with dispensed_by, timestamps  
✅ **Reports** for compliance and analytics  
✅ **Bulk operations** for efficiency  
✅ **Real-time updates** for collaboration  

### Implementation Timeline
- **Phase 1-2** (Days 1-8): Backend + Frontend scaffolding = **MVP**
- **Phase 3-4** (Days 9-13): Core functionality + HMO integration = **Functional**
- **Phase 5-6** (Days 14-18): Polish + Testing = **Production-ready**

### Immediate Next Step
**Start with Phase 1.1**: Create `PharmacyWorkbenchController.php` with basic index() method and route, verify permissions, then build from there.

---

## Questions to Clarify Before Coding

1. **Billing vs Pharmacy Separation**:
   - Should pharmacy staff be able to bill prescriptions directly, or is billing always done by billing department?
   - Answer determines if we need "Bill & Dispense" quick action
   
2. **Stock Management**:
   - Is stock decrement happening at billing or dispensing?
   - Current code decrements at billing - is this correct?
   
3. **HMO Pre-Authorization**:
   - Which specific HMOs require pre-authorization?
   - Is this tracked in `hmo` table with a flag?
   
4. **Quantity Tracking**:
   - ProductRequest doesn't have `quantity` field - assumed 1 per prescription?
   - Do we need to track quantity dispensed separately from quantity prescribed?
   
5. **Return/Refund Workflow**:
   - What happens if patient returns unused medication?
   - Do we need a "reverse dispense" feature?

---

**End of Plan**
