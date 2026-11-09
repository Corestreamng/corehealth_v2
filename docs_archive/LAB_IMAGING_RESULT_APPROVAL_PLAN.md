# Lab & Imaging Result Approval Workflow — Implementation Plan

## 1. Overview

Currently, when a lab technician or imaging technician enters a result, it is **immediately visible** to all staff (doctors, nurses, etc.) — status goes from `3 → 4` (Completed). There is no quality-control review step.

This plan introduces an **optional approval gate** controlled by two new hospital settings:
- `lab_results_require_approval` — toggles approval for Lab results
- `imaging_results_require_approval` — toggles approval for Imaging results

When enabled: results are saved to **temporary columns** (`pending_result`, `pending_result_data`, `pending_attachments`) instead of the live `result`/`result_data`/`attachments` columns, and a **new status `5` (Pending Approval)** is introduced. An **Approval Queue** quick action is added to the Lab and Imaging workbenches, accessible to **Unit Heads** and **Department Heads**.

---

## 2. Current Architecture

### 2.1 Models & Status Flow

| Model | Table | Result Columns |
|---|---|---|
| `LabServiceRequest` | `lab_service_requests` | `result` (HTML), `result_data` (JSON), `attachments` (JSON) |
| `ImagingServiceRequest` | `imaging_service_requests` | `result` (HTML), `result_data` (JSON), `attachments` (JSON) |

**Current Status Workflow (both Lab & Imaging):**
```
1 (Awaiting Billing) → 2 (Sample/Ready) → 3 (Awaiting Results) → 4 (Completed)
0 = Dismissed
```

### 2.2 Key Files

| File | Role |
|---|---|
| `app/Models/LabServiceRequest.php` | Lab result model |
| `app/Models/ImagingServiceRequest.php` | Imaging result model |
| `app/Http/Controllers/LabWorkbenchController.php` | Lab workbench — `saveResult()` at L706 |
| `app/Http/Controllers/ImagingWorkbenchController.php` | Imaging workbench — `saveResult()` at L640 |
| `app/Http/Controllers/LabServiceRequestController.php` | Legacy lab save result (redirect-based) |
| `app/Http/Controllers/EncounterController.php` | `investigationHistoryList()` — shows results to doctors |
| `resources/views/admin/lab/workbench.blade.php` | Lab workbench UI |
| `resources/views/admin/imaging/workbench.blade.php` | Imaging workbench UI |
| `resources/views/admin/patients/partials/invest.blade.php` | Patient profile investigations tab |
| `app/Models/ApplicationStatu.php` | Hospital settings model (`appsettings()` helper) |
| `app/Http/Controllers/HospitalConfigController.php` | Settings CRUD |
| `resources/views/admin/hospital-config/index.blade.php` | Settings UI |
| `resources/views/admin/partials/sidebar.blade.php` | Sidebar with unit/dept head badges at bottom |

### 2.3 Staff Hierarchy

The `Staff` model (`staff` table) has:
- `is_unit_head` (boolean) — unit-level leadership
- `is_dept_head` (boolean) — department-level leadership
- `department_id` (FK → departments)

These flags are already displayed in the sidebar bottom section near the logout button and used in the Leave Approval system (`LeaveService.php`).

---

## 3. New Status: Pending Approval

### Updated Status Map

| Status | Meaning | Badge |
|---|---|---|
| 0 | Dismissed | `badge-dark` |
| 1 | Awaiting Billing | `badge-warning` |
| 2 | Sample Collection / Ready | `badge-info` |
| 3 | Awaiting Results | `badge-danger` |
| **5** | **Pending Approval** (NEW) | **`badge-purple`** |
| 4 | Completed (Approved / No approval needed) | `badge-success` |

**Why status 5 (not 3.5)?** Integer-based — 5 sits logically after result entry. The sort order for queue display can use a custom mapping.

### Flow When Approval IS Required:
```
3 (Awaiting Results) → 5 (Pending Approval) → 4 (Completed/Approved)
                                             → 6 (Rejected — back to tech)
```

### Flow When Approval IS NOT Required (unchanged):
```
3 (Awaiting Results) → 4 (Completed)
```

### Rejection Flow:
- Status `6` = **Rejected** — Result rejected by approver with a reason
- Tech sees rejected items in their queue, can fix and re-submit → back to status `5`
- On re-submit, the old rejection reason is cleared

---

## 4. Database Changes

### 4.1 Migration: Add Pending Columns to Both Tables

**File:** `database/migrations/2026_02_23_XXXXXX_add_approval_columns_to_lab_and_imaging_requests.php`

Add to **both** `lab_service_requests` and `imaging_service_requests`:

```php
// Temporary result storage (pending approval)
$table->longText('pending_result')->nullable()->after('result');
$table->json('pending_result_data')->nullable()->after('result_data');
$table->json('pending_attachments')->nullable()->after('attachments');

// Approval tracking
$table->unsignedBigInteger('approved_by')->nullable();
$table->timestamp('approved_at')->nullable();
$table->unsignedBigInteger('rejected_by')->nullable();
$table->timestamp('rejected_at')->nullable();
$table->text('rejection_reason')->nullable();

// Foreign keys
$table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
$table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();
```

### 4.2 Migration: Add Settings to `application_status` Table

**File:** `database/migrations/2026_02_23_XXXXXX_add_result_approval_settings.php`

```php
$table->boolean('lab_results_require_approval')->default(false);
$table->boolean('imaging_results_require_approval')->default(false);
```

---

## 5. Model Changes

### 5.1 `LabServiceRequest` & `ImagingServiceRequest`

Add to **both** models' `$fillable`:
```php
'pending_result',
'pending_result_data',
'pending_attachments',
'approved_by',
'approved_at',
'rejected_by',
'rejected_at',
'rejection_reason',
```

Add to `$casts`:
```php
'pending_result_data' => 'array',
'pending_attachments' => 'array',
'approved_at' => 'datetime',
'rejected_at' => 'datetime',
```

Add relationships:
```php
public function approver()
{
    return $this->belongsTo(User::class, 'approved_by');
}

public function rejector()
{
    return $this->belongsTo(User::class, 'rejected_by');
}
```

Add helper methods:
```php
public function isPendingApproval(): bool
{
    return $this->status == 5;
}

public function isRejected(): bool
{
    return $this->status == 6;
}

public function hasApprovedResult(): bool
{
    return $this->status == 4 && $this->approved_by !== null;
}
```

### 5.2 `ApplicationStatu` Model

Add to `$fillable`:
```php
'lab_results_require_approval',
'imaging_results_require_approval',
```

---

## 6. Controller Changes

### 6.1 `LabWorkbenchController::saveResult()` (Line ~706)

**Current:** Always saves to `result`, `result_data`, `attachments` and sets `status = 4`.

**New logic:**
```php
// After processing the result HTML and data...

$requiresApproval = appsettings('lab_results_require_approval');

if ($requiresApproval) {
    // Save to pending columns
    $labRequest->update([
        'pending_result' => $resultHtml,
        'pending_result_data' => $resultData,
        'pending_attachments' => $allAttachments,
        'status' => 5, // Pending Approval
        'result_by' => Auth::id(),
        'result_date' => now(),
        // Clear any previous rejection
        'rejected_by' => null,
        'rejected_at' => null,
        'rejection_reason' => null,
    ]);
} else {
    // Original behavior — save directly
    $labRequest->update([
        'result' => $resultHtml,
        'result_data' => $resultData,
        'attachments' => $allAttachments,
        'status' => 4,
        'result_by' => Auth::id(),
        'result_date' => now(),
    ]);
}
```

### 6.2 `ImagingWorkbenchController::saveResult()` (Line ~640)

Same pattern as above, checking `appsettings('imaging_results_require_approval')`.

### 6.3 `LabServiceRequestController::saveResult()` (Legacy)

Same pattern — check the setting and route to pending or live columns.

### 6.4 New: Approval Methods (in both Lab & Imaging Workbench Controllers)

Add **four** new methods to each controller:

```php
/**
 * Get the approval queue (pending + rejected items).
 * Only accessible to unit heads / dept heads.
 */
public function getApprovalQueue(Request $request)
{
    $this->authorizeApprover();
    
    $query = LabServiceRequest::with(['service', 'patient', 'patient.user', 'encounter', 'resultBy'])
        ->whereIn('status', [5, 6]) // Pending Approval + Rejected
        ->orderBy('result_date', 'asc'); // FIFO
    
    // ... DataTable or paginated JSON response
}

/**
 * Get a single pending result for review.
 */
public function getApprovalDetail($id)
{
    $this->authorizeApprover();
    
    $request = LabServiceRequest::with([...])->findOrFail($id);
    
    return response()->json([
        'success' => true,
        'data' => [
            'id' => $request->id,
            'service_name' => $request->service->service_name,
            'patient_name' => $request->patient->user->firstname . ' ' . $request->patient->user->surname,
            'result_html' => $request->pending_result,
            'result_data' => $request->pending_result_data,
            'attachments' => $request->pending_attachments,
            'entered_by' => userfullname($request->result_by),
            'entered_at' => $request->result_date,
            'rejection_reason' => $request->rejection_reason, // if re-submitted after rejection
        ]
    ]);
}

/**
 * Approve a pending result — moves pending → live columns, status → 4.
 */
public function approveResult(Request $request, $id)
{
    $this->authorizeApprover();
    
    $labRequest = LabServiceRequest::findOrFail($id);
    
    if ($labRequest->status != 5) {
        return response()->json(['success' => false, 'message' => 'This result is not pending approval.'], 422);
    }
    
    $labRequest->update([
        // Move pending → live
        'result' => $labRequest->pending_result,
        'result_data' => $labRequest->pending_result_data,
        'attachments' => $labRequest->pending_attachments,
        // Clear pending
        'pending_result' => null,
        'pending_result_data' => null,
        'pending_attachments' => null,
        // Approval metadata
        'approved_by' => Auth::id(),
        'approved_at' => now(),
        'status' => 4, // Completed
    ]);
    
    return response()->json(['success' => true, 'message' => 'Result approved successfully.']);
}

/**
 * Reject a pending result — status → 6, record reason.
 */
public function rejectResult(Request $request, $id)
{
    $this->authorizeApprover();
    
    $request->validate(['rejection_reason' => 'required|string|max:500']);
    
    $labRequest = LabServiceRequest::findOrFail($id);
    
    if ($labRequest->status != 5) {
        return response()->json(['success' => false, 'message' => 'This result is not pending approval.'], 422);
    }
    
    $labRequest->update([
        'status' => 6, // Rejected
        'rejected_by' => Auth::id(),
        'rejected_at' => now(),
        'rejection_reason' => $request->rejection_reason,
    ]);
    
    return response()->json(['success' => true, 'message' => 'Result rejected. The technician will be notified.']);
}

/**
 * Authorization check: must be unit head or dept head.
 */
private function authorizeApprover()
{
    $staff = Auth::user()->staff_profile;
    
    if (!$staff || (!$staff->is_unit_head && !$staff->is_dept_head)) {
        abort(403, 'Only Unit Heads and Department Heads can approve results.');
    }
}
```

---

## 7. Route Changes

**File:** `routes/web.php`

```php
// Lab Approval Routes
Route::middleware(['auth'])->prefix('lab-workbench')->group(function () {
    Route::get('/approval-queue', [LabWorkbenchController::class, 'getApprovalQueue'])->name('lab.approvalQueue');
    Route::get('/approval/{id}', [LabWorkbenchController::class, 'getApprovalDetail'])->name('lab.approvalDetail');
    Route::post('/approve/{id}', [LabWorkbenchController::class, 'approveResult'])->name('lab.approveResult');
    Route::post('/reject/{id}', [LabWorkbenchController::class, 'rejectResult'])->name('lab.rejectResult');
});

// Imaging Approval Routes
Route::middleware(['auth'])->prefix('imaging-workbench')->group(function () {
    Route::get('/approval-queue', [ImagingWorkbenchController::class, 'getApprovalQueue'])->name('imaging.approvalQueue');
    Route::get('/approval/{id}', [ImagingWorkbenchController::class, 'getApprovalDetail'])->name('imaging.approvalDetail');
    Route::post('/approve/{id}', [ImagingWorkbenchController::class, 'approveResult'])->name('imaging.approveResult');
    Route::post('/reject/{id}', [ImagingWorkbenchController::class, 'rejectResult'])->name('imaging.rejectResult');
});
```

---

## 8. UI Changes

### 8.1 Hospital Config — Feature Flags Section

**File:** `resources/views/admin/hospital-config/index.blade.php`

Add two new toggles in the "Feature Flags" card (after the existing `enable_twakto` toggle):

```html
<!-- Lab Result Approval -->
<div class="d-flex justify-content-between align-items-center py-3 border-bottom">
    <div>
        <label for="lab_results_require_approval" class="mb-0" style="font-weight: 600;">
            <i class="mdi mdi-flask-outline text-info mr-2"></i>Lab Results Require Approval
        </label>
        <p class="text-muted small mb-0">
            When enabled, lab results must be approved by a Unit/Dept Head before becoming visible
        </p>
    </div>
    <input type="checkbox" name="lab_results_require_approval" value="1"
           {{ $config->lab_results_require_approval ? 'checked' : '' }}>
</div>

<!-- Imaging Result Approval -->
<div class="d-flex justify-content-between align-items-center py-3 border-bottom">
    <div>
        <label for="imaging_results_require_approval" class="mb-0" style="font-weight: 600;">
            <i class="mdi mdi-radiology-box-outline text-warning mr-2"></i>Imaging Results Require Approval
        </label>
        <p class="text-muted small mb-0">
            When enabled, imaging results must be approved by a Unit/Dept Head before becoming visible
        </p>
    </div>
    <input type="checkbox" name="imaging_results_require_approval" value="1"
           {{ $config->imaging_results_require_approval ? 'checked' : '' }}>
</div>
```

Also update `HospitalConfigController::update()` validation and checkbox handling.

### 8.2 Lab Workbench — Approval Queue Quick Action

**File:** `resources/views/admin/lab/workbench.blade.php`

Add an **"Approval Queue"** button in the workbench header/quick-actions area, **only visible to unit/dept heads when the setting is enabled**:

```html
@php
    $staffProfile = Auth::user()->staff_profile;
    $isApprover = $staffProfile && ($staffProfile->is_unit_head || $staffProfile->is_dept_head);
    $labApprovalEnabled = appsettings('lab_results_require_approval');
@endphp

@if($labApprovalEnabled && $isApprover)
<button class="btn btn-outline-purple btn-sm" onclick="toggleApprovalQueue()" id="approvalQueueBtn">
    <i class="mdi mdi-clipboard-check-outline"></i> Approval Queue
    <span class="badge badge-pill badge-purple" id="approvalQueueCount">0</span>
</button>
@endif
```

**Approval Queue Panel** — a slide-out or tab panel showing:
- List of pending results (status 5) with patient name, test name, entered by, date
- Click to expand → shows the full result preview (read-only) with the HTML/structured data
- Two buttons: **Approve** (green) and **Reject** (red, opens reason textarea)
- Rejected items (status 6) shown in a separate "Rejected" sub-tab for tracking

**Approval Review Modal:**
```html
<div class="modal" id="approvalReviewModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-purple text-white">
                <h5><i class="mdi mdi-clipboard-check"></i> Review Result</h5>
            </div>
            <div class="modal-body">
                <!-- Patient & Test Info -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Patient:</strong> <span id="approval-patient-name"></span><br>
                        <strong>Test:</strong> <span id="approval-test-name"></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Entered by:</strong> <span id="approval-entered-by"></span><br>
                        <strong>Date:</strong> <span id="approval-entered-at"></span>
                    </div>
                </div>
                
                <!-- Result Preview (read-only) -->
                <div class="card">
                    <div class="card-header">Result</div>
                    <div class="card-body" id="approval-result-content"></div>
                </div>
                
                <!-- Attachments -->
                <div id="approval-attachments-section" class="mt-3" style="display:none;"></div>
                
                <!-- Rejection Reason (toggle) -->
                <div id="rejection-reason-section" class="mt-3" style="display:none;">
                    <label class="font-weight-bold text-danger">Rejection Reason *</label>
                    <textarea class="form-control" id="rejection-reason" rows="3" 
                              placeholder="Explain why this result is being rejected..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-success" onclick="approveResult()">
                    <i class="mdi mdi-check-circle"></i> Approve
                </button>
                <button class="btn btn-danger" onclick="showRejectionForm()">
                    <i class="mdi mdi-close-circle"></i> Reject
                </button>
                <button class="btn btn-danger" id="confirmRejectBtn" style="display:none;" onclick="rejectResult()">
                    <i class="mdi mdi-send"></i> Submit Rejection
                </button>
                <button class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
```

### 8.3 Imaging Workbench — Same Pattern

Mirror the lab workbench approval queue UI in the imaging workbench.

### 8.4 Status Badge Updates

Update status badge rendering in **all views** that display lab/imaging status:
- Lab workbench queue
- Imaging workbench queue
- Patient profile investigations tab (`EncounterController::investigationHistoryList`)
- Nursing workbench queue cards

```php
case 5:
    $badge = '<span class="badge" style="background: #7c3aed; color: white;">Pending Approval</span>';
    break;
case 6:
    $badge = '<span class="badge badge-danger">Rejected</span>';
    break;
```

### 8.5 Result Visibility in Patient Profile

**File:** `app/Http/Controllers/EncounterController.php` → `investigationHistoryList()`

Currently shows `$his->result` directly. When approval is required:

```php
// Results section — only show approved results
if ($his->result) {
    $str .= '<div class="alert alert-light mb-2"><small><b>Result:</b><br>' . $his->result . '</small></div>';
} elseif ($his->status == 5) {
    $str .= '<div class="alert alert-info mb-2"><small><i class="mdi mdi-clock-outline"></i> Result entered — pending approval</small></div>';
} elseif ($his->status == 6) {
    $str .= '<div class="alert alert-warning mb-2"><small><i class="mdi mdi-alert-outline"></i> Result under review</small></div>';
}
```

**Key point:** Since results in `pending_result` are NEVER copied to `result` until approved, doctors **cannot** see unapproved results. The `result` column remains `null` until approval — this is the core safety mechanism.

### 8.6 Tech View — Rejected Items

In the lab/imaging workbench, the technician who entered the result should see rejected items (status 6) with the rejection reason, so they can correct and re-submit:

```html
<!-- In the tech's queue, for rejected items -->
<div class="alert alert-danger">
    <i class="mdi mdi-alert-circle"></i> <strong>Rejected by:</strong> {{ rejector_name }}
    <br><strong>Reason:</strong> {{ rejection_reason }}
    <br><button class="btn btn-sm btn-warning mt-2" onclick="editRejectedResult(id)">
        <i class="mdi mdi-pencil"></i> Correct & Re-submit
    </button>
</div>
```

---

## 9. Implementation Order

### Phase 1: Database & Settings (1 file each)
1. **Migration** — Add pending columns + approval tracking to both tables + settings columns
2. **Model updates** — `LabServiceRequest`, `ImagingServiceRequest`, `ApplicationStatu` fillable/casts
3. **Hospital Config** — Add toggles to settings UI + controller validation

### Phase 2: Save Logic (3 controller files)
4. **`LabWorkbenchController::saveResult()`** — Branch on setting: pending vs live
5. **`ImagingWorkbenchController::saveResult()`** — Same
6. **`LabServiceRequestController::saveResult()`** — Same (legacy)

### Phase 3: Approval API (2 controller files + routes)
7. **New approval methods** in `LabWorkbenchController` — queue, detail, approve, reject
8. **New approval methods** in `ImagingWorkbenchController` — same
9. **Routes** — Register all 8 new endpoints

### Phase 4: UI (3-4 blade files)
10. **Lab workbench** — Approval Queue quick action button + modal + JS
11. **Imaging workbench** — Same
12. **Patient profile** — Update result display for status 5/6
13. **Status badges** — Update everywhere (lab queue, imaging queue, nursing workbench)

### Phase 5: Polish
14. **Notification** — Optional: Notify approvers when new results are pending (database notification or sound alert)
15. **Approval Queue Count** — Badge count in workbench header auto-refreshes via polling
16. **Audit trail** — Already handled via Auditable trait on both models

---

## 10. Edge Cases & Safety

| Case | Handling |
|---|---|
| Setting toggled OFF after results are pending | Auto-approve all status=5 items via a console command or migration |
| Setting toggled ON mid-day | Only new saves affected; existing completed results stay as-is |
| Tech edits a pending result | Overwrites `pending_*` columns, stays at status 5 |
| Tech edits a rejected result | Clears rejection fields, status goes back to 5 |
| Approver is also the tech | Allow (self-approval) — or optionally block with a setting |
| Multiple approvers | First approver wins — standard optimistic locking via status check |
| Result edit window + approval | Edit window timer (`result_edit_duration`) starts from `result_date` (when tech entered), applies to tech edits only. Approvers have no time limit. |
| Attachments | Stored in `pending_attachments`, moved to `attachments` on approval. File uploads remain in storage regardless (not deleted on rejection). |

---

## 11. Files Changed Summary

| # | File | Change |
|---|---|---|
| 1 | `database/migrations/2026_02_23_*_add_approval_columns...php` | NEW — migration |
| 2 | `database/migrations/2026_02_23_*_add_result_approval_settings.php` | NEW — migration |
| 3 | `app/Models/LabServiceRequest.php` | EDIT — fillable, casts, relationships, helpers |
| 4 | `app/Models/ImagingServiceRequest.php` | EDIT — fillable, casts, relationships, helpers |
| 5 | `app/Models/ApplicationStatu.php` | EDIT — add 2 fields to fillable |
| 6 | `app/Http/Controllers/HospitalConfigController.php` | EDIT — validation + checkbox handling |
| 7 | `app/Http/Controllers/LabWorkbenchController.php` | EDIT — saveResult() + 4 new approval methods |
| 8 | `app/Http/Controllers/ImagingWorkbenchController.php` | EDIT — saveResult() + 4 new approval methods |
| 9 | `app/Http/Controllers/LabServiceRequestController.php` | EDIT — saveResult() branch |
| 10 | `app/Http/Controllers/EncounterController.php` | EDIT — investigationHistoryList() visibility |
| 11 | `routes/web.php` | EDIT — 8 new approval routes |
| 12 | `resources/views/admin/hospital-config/index.blade.php` | EDIT — 2 new toggles |
| 13 | `resources/views/admin/lab/workbench.blade.php` | EDIT — approval queue UI + modal + JS |
| 14 | `resources/views/admin/imaging/workbench.blade.php` | EDIT — approval queue UI + modal + JS |
| 15 | `resources/views/admin/patients/partials/invest.blade.php` | EDIT — status 5/6 display |

**Estimated: ~15 files, 2 new migrations, 8 new routes, 8 new controller methods.**
