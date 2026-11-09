# HMO Workbench Enhancement Plan

## Overview

Two enhancements to the HMO validation workbench:

1. **Awaiting Auth Code Queue** — Allow secondary coverage items to be approved without an auth code, parking them in a dedicated "Awaiting Code" queue until the code arrives from the HMO.
2. **Patient HMO Correction** — Allow HMO officers to correct a patient's HMO provider and HMO number during validation, directly from the workbench.

---

## Feature 1: Awaiting Auth Code Queue

### Problem

Currently, secondary coverage items **cannot be approved** without an auth code:
- `approveRequest()` (single) → returns 422 if `coverage_mode === 'secondary'` and `auth_code` is empty
- `batchApprove()` → skips secondary items entirely with an error message
- `groupApprove()` → skips secondary items without auth code

This blocks the validation workflow when the HMO hasn't yet issued the auth code but the officer has already confirmed the service is valid/covered.

### Solution: New `awaiting_code` Validation Status

Introduce a fourth enum value `awaiting_code` to the `validation_status` column. This creates a clean state machine:

```
pending ──► approved          (primary/express, or secondary WITH auth code)
pending ──► awaiting_code     (secondary WITHOUT auth code)
pending ──► rejected

awaiting_code ──► approved    (auth code entered later)
awaiting_code ──► rejected    (can still reject if needed)
```

#### Why a new enum value (not a flag)?
- Clean DB queries: `WHERE validation_status = 'awaiting_code'` is simpler than `WHERE validation_status = 'approved' AND coverage_mode = 'secondary' AND auth_code IS NULL`
- Explicit state machine — no ambiguity about what "approved" means
- The queue tab maps 1:1 to a status value, consistent with existing tabs

---

### 1.1 Database Migration

**New migration**: `add_awaiting_code_to_validation_status_enum`

```php
// Alter the enum column to add 'awaiting_code'
Schema::table('product_or_service_requests', function (Blueprint $table) {
    DB::statement("ALTER TABLE product_or_service_requests MODIFY COLUMN validation_status ENUM('pending', 'approved', 'rejected', 'awaiting_code') NULL");
});
```

No new columns needed.

---

### 1.2 Backend Changes — `HmoWorkbenchController.php`

#### A. `approveRequest()` (single approve) — Lines 760-814

Current behavior: Returns 422 for secondary without auth code.

**Change**: If secondary and no auth code provided, set `validation_status = 'awaiting_code'` instead of rejecting.

```php
// For secondary coverage without auth code → awaiting_code
if ($hmoRequest->coverage_mode === 'secondary' && empty($request->auth_code)) {
    $hmoRequest->update([
        'validation_status' => 'awaiting_code',
        'validated_by' => Auth::id(),
        'validated_at' => now(),
        'validation_notes' => $request->validation_notes,
    ]);

    DB::commit();

    $this->sendHmoNotification(
        "Request #{$id} Awaiting Auth Code",
        "Secondary request for " . HmoHelper::getDisplayName($hmoRequest) . " approved, awaiting auth code"
    );

    return response()->json([
        'success' => true,
        'message' => 'Request approved — awaiting authorization code',
        'awaiting_code' => true,
    ]);
}

// Existing flow for secondary WITH auth code, or primary/express
$hmoRequest->update([
    'validation_status' => 'approved',
    ...
]);
```

The existing `required_if:coverage_mode,secondary` validation on `auth_code` must be **removed** (auth code becomes optional for secondary).

#### B. `batchApprove()` — Lines 1008-1084

Current behavior: Skips secondary items and adds an error.

**Change**: Instead of skipping secondary items, approve them as `awaiting_code`.

```php
if ($hmoRequest->coverage_mode === 'secondary') {
    $hmoRequest->update([
        'validation_status' => 'awaiting_code',
        'validated_by' => Auth::id(),
        'validated_at' => now(),
        'validation_notes' => $request->validation_notes ?? 'Batch approved — awaiting auth code',
    ]);
    $awaitingCode++;
    continue;
}
```

Response message: `"{$approved} approved, {$awaitingCode} awaiting auth code"`.

#### C. `groupApprove()` — Lines 1700-1784

Current behavior: Skips secondary items without auth code.

**Change**: Same pattern — approve as `awaiting_code` when secondary has no auth code.

```php
if (empty($authCode)) {
    // Approve without code → awaiting_code
    $hmoRequest->update([
        'validation_status' => 'awaiting_code',
        'validated_by' => Auth::id(),
        'validated_at' => now(),
        'validation_notes' => $request->validation_notes ?? 'Group approved — awaiting auth code',
    ]);
    $awaitingCode++;
    continue;
}
```

**Additionally**: Add a new option in the auth code section UI — "Approve without code (enter later)". When selected:
- The shared/individual auth code inputs are hidden
- Items are explicitly marked as awaiting

#### D. New Method: `submitAuthCode()`

New endpoint to enter an auth code for a request in `awaiting_code` status:

```php
public function submitAuthCode(Request $request, $id)
{
    $validator = Validator::make($request->all(), [
        'auth_code' => 'required|string|max:100',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    $hmoRequest = ProductOrServiceRequest::findOrFail($id);

    if ($hmoRequest->validation_status !== 'awaiting_code') {
        return response()->json([
            'success' => false,
            'message' => 'Request is not awaiting an auth code'
        ], 422);
    }

    $hmoRequest->update([
        'validation_status' => 'approved',
        'auth_code' => $request->auth_code,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Auth code submitted — request is now fully approved',
    ]);
}
```

#### E. New Method: `batchSubmitAuthCode()`

Bulk auth code submission for multiple awaiting requests (same patient, shared code):

```php
public function batchSubmitAuthCode(Request $request)
{
    $validator = Validator::make($request->all(), [
        'request_ids' => 'required|array|min:1',
        'request_ids.*' => 'exists:product_or_service_requests,id',
        'auth_mode' => 'required|in:shared,individual',
        'shared_auth_code' => 'required_if:auth_mode,shared|nullable|string|max:100',
        'individual_auth_codes' => 'required_if:auth_mode,individual|nullable|array',
        'individual_auth_codes.*' => 'nullable|string|max:100',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    DB::beginTransaction();
    try {
        $updated = 0;
        $errors = [];

        foreach ($request->request_ids as $id) {
            $hmoRequest = ProductOrServiceRequest::find($id);
            if (!$hmoRequest || $hmoRequest->validation_status !== 'awaiting_code') continue;

            $authCode = $request->auth_mode === 'shared'
                ? $request->shared_auth_code
                : ($request->individual_auth_codes[$id] ?? null);

            if (empty($authCode)) {
                $errors[] = "Request #{$id}: No auth code provided";
                continue;
            }

            $hmoRequest->update([
                'validation_status' => 'approved',
                'auth_code' => $authCode,
            ]);
            $updated++;
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => "{$updated} request(s) updated to approved",
            'updated' => $updated,
            'errors' => $errors,
        ]);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ], 500);
    }
}
```

#### F. `getQueueCounts()` — Lines 1163-1228

**Add** `awaiting_code` count:

```php
'awaiting_code' => $baseQuery()
    ->where('validation_status', 'awaiting_code')
    ->where('claims_amount', '>', 0)
    ->count(),
```

#### G. `getRequests()` — Lines 62-115

**Add** new tab filter case:

```php
case 'awaiting_code':
    $query->where('validation_status', 'awaiting_code')
          ->where('claims_amount', '>', 0);
    break;
```

---

### 1.3 Routes — `web.php`

Add to the HMO workbench route group (lines 755-776):

```php
Route::post('hmo/requests/{id}/submit-auth-code', [HmoWorkbenchController::class, 'submitAuthCode'])->name('hmo.submit-auth-code');
Route::post('hmo/requests/batch-submit-auth-code', [HmoWorkbenchController::class, 'batchSubmitAuthCode'])->name('hmo.batch-submit-auth-code');
```

---

### 1.4 Frontend Changes — `workbench.blade.php`

#### A. New Queue Tab (after Approved tab, line ~628)

```html
<li class="nav-item">
    <a class="nav-link" id="awaiting-code-tab" data-toggle="tab" href="#awaiting_code" role="tab">
        <i class="mdi mdi-key-alert mr-1"></i>Awaiting Code
        <span class="badge badge-purple ml-1" id="awaiting_code_badge" style="border-radius: 6px;">0</span>
    </a>
</li>
```

Badge color: Use a distinct purple/violet (`#7c4dff` or Bootstrap `badge-purple` custom class) to differentiate from approved (info/blue) and pending (warning/yellow).

#### B. New Tab Pane with DataTable

Add a corresponding tab pane with:
- Same DataTable structure as the pending tab
- Columns: Select, Patient, HMO, Item, Coverage, Claims Amount, Approved By, Approved Date, Actions
- **Actions column**: "Enter Auth Code" button (primary), "Reject" button (outline-danger)
- **Batch action bar**: "Enter Auth Code for Selected" button

#### C. "Enter Auth Code" Inline Action

For individual requests in the Awaiting Code tab, clicking "Enter Auth Code" opens a small inline form or popover:
- Single text input for auth code
- Submit button
- Calls `POST /hmo/requests/{id}/submit-auth-code`
- On success: remove row from awaiting_code table, reload counts

#### D. Batch Auth Code Modal

For bulk operations — allows entering shared or individual auth codes for selected awaiting items:
- Reuses the same shared/individual auth code radio pattern from the group approve modal
- Calls `POST /hmo/requests/batch-submit-auth-code`

#### E. Single Approve Modal Changes (lines 954-961)

Current: `auth_code_div` shown for secondary with `required` label.

**Change**: Make auth code **optional** for secondary. Update the label:

```html
<label class="font-weight-bold">Authorization Code <span class="text-muted">(optional — enter later if not available)</span></label>
<small class="form-text text-muted">If left blank, request moves to "Awaiting Code" queue</small>
```

Remove the red asterisk `*` for secondary auth code.

#### F. Group Validation Modal Changes (lines 1469-1488)

Current: Auth code section requires code for secondary. JS blocks approval if secondary has no code.

**Change**: Add a third option to the auth mode radio group:

```html
<div class="custom-control custom-radio">
    <input type="radio" class="custom-control-input" id="vg_auth_skip" name="vg_auth_mode" value="skip">
    <label class="custom-control-label small" for="vg_auth_skip">
        Approve without code — enter later <span class="badge badge-purple badge-sm">Awaiting Code</span>
    </label>
</div>
```

When `skip` is selected:
- Hide shared/individual auth code inputs
- JS approval handler sends `auth_mode: 'skip'` → backend sets `awaiting_code` status for secondary items

#### G. Badge Count Update (lines 1829-1848)

Add to `loadQueueCounts()`:

```javascript
$('#awaiting_code_badge').text(data.awaiting_code || 0);
```

#### H. DataTable Row Styling

Awaiting code items appearing in "All" tab should have a distinct badge:

```html
<span class="badge badge-purple">Awaiting Code</span>
```

---

### 1.5 UX Flow Summary

**Single Approve (Secondary)**:
1. Officer opens approve modal for a secondary request
2. Auth code field is visible but **optional** (label says "enter later if not available")
3. If auth code provided → status = `approved`
4. If auth code left blank → status = `awaiting_code`, toast says "Approved — awaiting auth code"

**Group Approve (Validate Patient)**:
1. Officer opens Validate Patient modal for a patient with secondary items
2. Auth code section shows three options: Shared / Individual / **Approve without code**
3. If "Approve without code" selected → secondary items get `awaiting_code` status
4. Primary items get `approved` regardless of auth mode selection

**Batch Approve (Toolbar)**:
1. Officer selects multiple items and clicks Batch Approve
2. Secondary items automatically get `awaiting_code` (no auth code input in batch modal)
3. Primary items get `approved`
4. Toast: "X approved, Y awaiting auth code"

**Entering Auth Code Later**:
1. Officer switches to "Awaiting Code" tab → sees all awaiting items
2. Can enter code individually (inline popover) or in bulk (batch modal with shared/individual options)
3. On submission → status changes from `awaiting_code` to `approved`

---

## Feature 2: Patient HMO Correction

### Problem

When a patient's HMO or HMO number is incorrect/outdated, the officer currently has no way to fix it from the workbench. They must go to the reception/patient profile, make the change, and return — breaking their validation workflow.

### Solution: Inline HMO Edit in View Details Modal

The View Details modal already displays the HMO card (lines 893-906) with HMO name, HMO#, and scheme. We add an edit capability **directly in that card** via an independent AJAX call, keeping the officer's workflow uninterrupted.

---

### 2.1 No Migration Needed

Patient HMO columns already exist:
- `patients.hmo_id` (FK to `hmos` table)
- `patients.hmo_no` (varchar)

No new columns required.

---

### 2.2 Backend Changes — `HmoWorkbenchController.php`

#### New Method: `updatePatientHmo()`

```php
public function updatePatientHmo(Request $request, $patientId)
{
    $validator = Validator::make($request->all(), [
        'hmo_id' => 'required|exists:hmos,id',
        'hmo_no' => 'required|string|max:100',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    $patient = Patient::findOrFail($patientId);
    $oldHmoId = $patient->hmo_id;
    $oldHmoNo = $patient->hmo_no;

    $patient->update([
        'hmo_id' => $request->hmo_id,
        'hmo_no' => $request->hmo_no,
    ]);

    // Recalculate tariffs for pending/awaiting_code requests if HMO changed
    $recalculated = 0;
    if ($oldHmoId != $request->hmo_id) {
        $recalculated = $this->recalculatePendingTariffs($patient);
    }

    return response()->json([
        'success' => true,
        'message' => 'Patient HMO updated successfully'
            . ($recalculated > 0 ? ". {$recalculated} pending request tariff(s) recalculated." : ''),
        'new_hmo_name' => Hmo::find($request->hmo_id)->name,
        'new_hmo_no' => $request->hmo_no,
        'recalculated' => $recalculated,
    ]);
}
```

#### Helper Method: `recalculatePendingTariffs()`

When the HMO changes, claims_amount and payable_amount may need recalculation for un-validated items:

```php
private function recalculatePendingTariffs(Patient $patient)
{
    $pendingRequests = ProductOrServiceRequest::where('user_id', $patient->user_id)
        ->whereIn('validation_status', ['pending', 'awaiting_code'])
        ->get();

    $count = 0;
    foreach ($pendingRequests as $posr) {
        $tariff = HmoHelper::getTariff($patient->hmo_id, $posr);
        if ($tariff) {
            $posr->update([
                'claims_amount' => $tariff['claims_amount'] * $posr->qty,
                'payable_amount' => $tariff['payable_amount'] * $posr->qty,
                'hmo_id' => $patient->hmo_id,
            ]);
            $count++;
        }
    }

    return $count;
}
```

> **Note**: Only `pending` and `awaiting_code` items are recalculated. Already `approved` or `rejected` items keep their original tariff values (they've been validated at those amounts).

---

### 2.3 Routes — `web.php`

```php
Route::post('hmo/patient/{patient}/update-hmo', [HmoWorkbenchController::class, 'updatePatientHmo'])->name('hmo.update-patient-hmo');
```

---

### 2.4 Frontend Changes — `workbench.blade.php`

#### A. HMO Card Edit Button (View Details Modal, lines 893-906)

Add a small edit icon button to the HMO card header:

```html
<div class="card-header py-1 px-2 d-flex align-items-center justify-content-between">
    <strong class="text-primary"><i class="mdi mdi-hospital-building mr-1"></i>HMO / Scheme</strong>
    <button type="button" class="btn btn-link btn-sm p-0 text-muted" id="detail_edit_hmo_btn"
            title="Correct patient HMO" style="font-size: 0.85rem;">
        <i class="mdi mdi-pencil"></i>
    </button>
</div>
```

#### B. Inline Edit Form (Hidden by Default)

Below the existing static HMO display, add a collapsible edit form:

```html
<div id="detail_hmo_edit_section" style="display:none;" class="mt-2 p-2 bg-light rounded">
    <div class="form-group mb-2">
        <label class="small font-weight-bold mb-1">HMO Provider</label>
        <select class="form-control form-control-sm" id="detail_edit_hmo_id" style="border-radius:6px;">
            @foreach($hmos as $hmo)
                <option value="{{ $hmo->id }}">{{ $hmo->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="form-group mb-2">
        <label class="small font-weight-bold mb-1">HMO Number</label>
        <input type="text" class="form-control form-control-sm" id="detail_edit_hmo_no"
               placeholder="Enter HMO number" style="border-radius:6px;">
    </div>
    <div class="d-flex justify-content-end" style="gap:6px;">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="detail_cancel_hmo_edit">Cancel</button>
        <button type="button" class="btn btn-sm btn-primary" id="detail_save_hmo_edit">
            <i class="mdi mdi-content-save mr-1"></i>Save
        </button>
    </div>
    <small class="text-muted d-block mt-1">
        <i class="mdi mdi-information-outline"></i> Changing HMO will recalculate tariffs for pending requests.
    </small>
</div>
```

#### C. JavaScript Handler

```javascript
// Toggle edit form
$('#detail_edit_hmo_btn').on('click', function() {
    let $section = $('#detail_hmo_edit_section');
    if ($section.is(':visible')) {
        $section.slideUp(200);
    } else {
        // Pre-fill with current values
        $('#detail_edit_hmo_id').val(currentDetailData.patient.hmo_id);
        $('#detail_edit_hmo_no').val(currentDetailData.patient.hmo_no);
        $section.slideDown(200);
    }
});

$('#detail_cancel_hmo_edit').on('click', function() {
    $('#detail_hmo_edit_section').slideUp(200);
});

// Save HMO correction
$('#detail_save_hmo_edit').on('click', function() {
    let patientId = currentDetailData.patient.id;
    let $btn = $(this).prop('disabled', true);

    $.ajax({
        url: '/hmo/patient/' + patientId + '/update-hmo',
        type: 'POST',
        data: {
            _token: '{{ csrf_token() }}',
            hmo_id: $('#detail_edit_hmo_id').val(),
            hmo_no: $('#detail_edit_hmo_no').val()
        },
        success: function(resp) {
            $btn.prop('disabled', false);
            $('#detail_hmo_edit_section').slideUp(200);

            // Update displayed values in the modal
            $('#detail_hmo_name').text(resp.new_hmo_name);
            $('#detail_hmo_no').text(resp.new_hmo_no);

            // Reload DataTable to reflect tariff changes
            table.ajax.reload(null, false);
            loadQueueCounts();
            loadFinancialSummary();

            toastr.success(resp.message);
        },
        error: function(xhr) {
            $btn.prop('disabled', false);
            if (xhr.status === 422) {
                let errors = xhr.responseJSON.errors;
                let msg = Object.values(errors).flat().join(', ');
                toastr.error(msg);
            } else {
                toastr.error(xhr.responseJSON?.message || 'Failed to update HMO');
            }
        }
    });
});
```

#### D. Variable: `currentDetailData`

The `show()` AJAX response already returns patient and HMO info. Store it when the view details modal is populated:

```javascript
let currentDetailData = null;

// In the existing showDetails() function, after populating modal fields:
currentDetailData = response;
```

The `show()` method response must include `patient.id`, `patient.hmo_id`, `patient.hmo_no` — check if these are already returned, add if not.

---

### 2.5 UX Flow

1. Officer clicks "View Details" on any request → details modal opens
2. HMO card shows current HMO name, HMO#, scheme
3. Officer clicks the pencil icon on the HMO card header
4. Edit form slides down with HMO dropdown (pre-selected) and HMO# input (pre-filled)
5. Officer selects new HMO and/or corrects the number → clicks "Save"
6. Backend updates the patient record, recalculates tariffs on pending items
7. Modal updates inline (HMO name/number change visually), DataTable reloads
8. Toast: "Patient HMO updated successfully. X pending request tariff(s) recalculated."
9. Edit form slides up, officer continues validation

---

## Implementation Sequence

### Phase 1: Awaiting Auth Code Queue
1. **Migration** — Add `awaiting_code` to `validation_status` enum
2. **Controller** — Modify `approveRequest()`, `batchApprove()`, `groupApprove()`
3. **Controller** — Add `submitAuthCode()`, `batchSubmitAuthCode()`
4. **Controller** — Update `getQueueCounts()`, `getRequests()` tab filter
5. **Routes** — Add 2 new routes
6. **Blade** — Add "Awaiting Code" queue tab + tab pane
7. **Blade** — Add auth code entry UI (inline popover + batch modal)
8. **Blade** — Modify single approve modal (make auth code optional)
9. **Blade** — Modify group validation modal (add "skip auth code" option)
10. **Blade** — Update `loadQueueCounts()` JS for new badge

### Phase 2: Patient HMO Correction
11. **Controller** — Add `updatePatientHmo()` + `recalculatePendingTariffs()`
12. **Controller** — Ensure `show()` returns `patient.id`, `patient.hmo_id`, `patient.hmo_no`
13. **Routes** — Add 1 new route
14. **Blade** — Add edit button to HMO card in details modal
15. **Blade** — Add inline edit form (dropdown + input)
16. **Blade** — Add JS handler for save/cancel

---

## Files Modified

| File | Changes |
|------|---------|
| `database/migrations/xxxx_add_awaiting_code_status.php` | **New** — Adds `awaiting_code` to enum |
| `app/Http/Controllers/Admin/HmoWorkbenchController.php` | Modify 3 methods + add 3 new methods |
| `resources/views/admin/hmo/workbench.blade.php` | New tab, modal modifications, new JS handlers |
| `routes/web.php` | 3 new routes |

---

## Edge Cases & Considerations

1. **Existing queries checking `validation_status`**: Search codebase for any `where('validation_status', '!=', 'pending')` or similar that might mishandle the new status. Key places: billing, claims submission, financial reports.

2. **Claims submission**: Items in `awaiting_code` should **NOT** be submittable to HMO for claims until auth code is entered. The claims tab already filters by `payment_id`, but any claims batch process needs to exclude `awaiting_code` items.

3. **Tariff recalculation safety**: When HMO changes, only `pending` and `awaiting_code` items are recalculated. Approved/rejected items retain their validated amounts to maintain audit integrity.

4. **Audit trail**: Both features should be captured by the existing `OwenIt\Auditing` integration on the `ProductOrServiceRequest` model — no extra work needed for audit logging.

5. **HMO dropdown filtering**: The `$hmos` variable passed to the blade only includes `status = 1` (active) HMOs, so the correction dropdown automatically excludes deactivated HMOs.

6. **Concurrent validation**: If two officers are both validating the same patient's requests, the HMO correction should be safe since it operates on the patient record, not individual requests. Tariff recalculation uses the latest DB state.
