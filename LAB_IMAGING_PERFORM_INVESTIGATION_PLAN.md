# Lab & Imaging — "Perform Investigation" Enhancement Plan

**Scope:** Extend the doctor/nurse result entry flow so that the requesting clinician can:
1. Bill an unbilled investigation themselves (new **Perform Investigation** button, status = 1).
2. Enter the result using the **native InvestResultEntry modal** (already works for status ≥ 2; fix the callback).
3. **Self-approve** the saved result when `lab_results_require_approval` / `imaging_results_require_approval` is enabled AND the new self-approve setting for their role is on.

The existing **Enter Result** button (status ≥ 2, already using InvestResultEntry) is mostly correct but needs the self-approve callback wired up. The new **Perform Investigation** button adds the billing-first flow.

---

## Affected Files

| File | Change |
|---|---|
| `app/Http/Controllers/HospitalConfigController.php` | Add 4 new boolean settings |
| `app/Http/Controllers/LabWorkbenchController.php` | Add `selfApproveResult()` |
| `app/Http/Controllers/ImagingWorkbenchController.php` | Add `selfApproveResult()` |
| `app/Http/Controllers/EncounterController.php` | Add "Perform Investigation" button in both history methods |
| `routes/web.php` | 2 new routes for self-approve |
| `resources/views/admin/hospital-config/index.blade.php` | 4 new toggle rows |
| `resources/views/admin/partials/perform_investigation_modal.blade.php` | **New** shared modal + JS partial |
| `resources/views/admin/doctors/new_encounter.blade.php` | Include new partial; update bindFormSubmit callback |
| `resources/views/admin/lab/workbench.blade.php` | Include new partial; update bindFormSubmit callback |
| `resources/views/admin/imaging/workbench.blade.php` | Include new partial; update bindFormSubmit callback |
| `resources/views/admin/nursing/workbench.blade.php` | Include new partial; update bindFormSubmit callback |
| `resources/views/admin/maternity/workbench.blade.php` | Include new partial; update bindFormSubmit callback |

---

## Phase 1 — New Hospital Config Settings

### 1.1 `HospitalConfigController.php`

In the `$booleanSettings` validation array (around line 110), add:

```php
'doctor_self_approve_lab_result'     => 'boolean',
'nurse_self_approve_lab_result'      => 'boolean',
'doctor_self_approve_imaging_result' => 'boolean',
'nurse_self_approve_imaging_result'  => 'boolean',
```

In the `$validated` assignments block (around line 129), add:

```php
$validated['doctor_self_approve_lab_result']     = $request->has('doctor_self_approve_lab_result');
$validated['nurse_self_approve_lab_result']      = $request->has('nurse_self_approve_lab_result');
$validated['doctor_self_approve_imaging_result'] = $request->has('doctor_self_approve_imaging_result');
$validated['nurse_self_approve_imaging_result']  = $request->has('nurse_self_approve_imaging_result');
```

### 1.2 `hospital-config/index.blade.php`

After the existing **Nurse Can Enter Imaging Results** toggle (around line 795), inside the same "Result Entry Permissions" section, add four new toggle rows following the exact same HTML pattern already used:

```html
<hr class="my-3">
<h6 class="text-muted mb-3" style="font-weight: 600;">
    <i class="mdi mdi-shield-check-outline mr-1"></i> Self-Approval Permissions
    <small class="text-muted d-block fw-normal" style="font-size:.8rem;">
        Only applies when Results Require Approval is ON. Allows the requesting clinician to bypass the approval queue for their own results.
    </small>
</h6>

<!-- Doctor self-approve lab -->
<div class="mb-3">
    <div class="feature-toggle-row d-flex align-items-center justify-content-between">
        <div>
            <label class="mb-0" style="font-weight:600;cursor:pointer;">
                <i class="mdi mdi-doctor text-primary mr-1"></i>Doctor Can Self-Approve Lab Results
            </label>
            <small class="text-muted d-block">Doctor can approve their own lab results without going to the approval queue</small>
        </div>
        <label class="toggle-switch">
            <input type="checkbox" name="doctor_self_approve_lab_result" value="1"
                   {{ $config->doctor_self_approve_lab_result ? 'checked' : '' }}>
            <span class="toggle-slider"></span>
        </label>
    </div>
</div>

<!-- Nurse self-approve lab -->
<div class="mb-3">
    <div class="feature-toggle-row d-flex align-items-center justify-content-between">
        <div>
            <label class="mb-0" style="font-weight:600;cursor:pointer;">
                <i class="mdi mdi-nurse text-success mr-1"></i>Nurse Can Self-Approve Lab Results
            </label>
            <small class="text-muted d-block">Nurse can approve their own lab results without going to the approval queue</small>
        </div>
        <label class="toggle-switch">
            <input type="checkbox" name="nurse_self_approve_lab_result" value="1"
                   {{ $config->nurse_self_approve_lab_result ? 'checked' : '' }}>
            <span class="toggle-slider"></span>
        </label>
    </div>
</div>

<!-- Doctor self-approve imaging -->
<div class="mb-3">
    <div class="feature-toggle-row d-flex align-items-center justify-content-between">
        <div>
            <label class="mb-0" style="font-weight:600;cursor:pointer;">
                <i class="mdi mdi-doctor text-primary mr-1"></i>Doctor Can Self-Approve Imaging Results
            </label>
            <small class="text-muted d-block">Doctor can approve their own imaging results without going to the approval queue</small>
        </div>
        <label class="toggle-switch">
            <input type="checkbox" name="doctor_self_approve_imaging_result" value="1"
                   {{ $config->doctor_self_approve_imaging_result ? 'checked' : '' }}>
            <span class="toggle-slider"></span>
        </label>
    </div>
</div>

<!-- Nurse self-approve imaging -->
<div class="mb-3">
    <div class="feature-toggle-row d-flex align-items-center justify-content-between">
        <div>
            <label class="mb-0" style="font-weight:600;cursor:pointer;">
                <i class="mdi mdi-nurse text-success mr-1"></i>Nurse Can Self-Approve Imaging Results
            </label>
            <small class="text-muted d-block">Nurse can approve their own imaging results without going to the approval queue</small>
        </div>
        <label class="toggle-switch">
            <input type="checkbox" name="nurse_self_approve_imaging_result" value="1"
                   {{ $config->nurse_self_approve_imaging_result ? 'checked' : '' }}>
            <span class="toggle-slider"></span>
        </label>
    </div>
</div>
```

---

## Phase 2 — Self-Approve Endpoints

### 2.1 `LabWorkbenchController.php`

Add a new public method after `approveResult()`:

```php
/**
 * Self-approve a pending lab result (for requesting doctors/nurses with self-approve setting).
 * Bypasses the unit/dept head restriction.
 */
public function selfApproveResult($id)
{
    try {
        $labRequest = LabServiceRequest::findOrFail($id);

        // Must be the original requester
        if (Auth::id() !== $labRequest->doctor_id) {
            return response()->json(['success' => false, 'message' => 'You can only self-approve your own requests.'], 403);
        }

        // Check role-based self-approve setting
        $user = Auth::user();
        $canSelfApprove = false;
        if ($user->hasRole('DOCTOR') && appsettings('doctor_self_approve_lab_result')) {
            $canSelfApprove = true;
        }
        if ($user->hasRole('NURSE') && appsettings('nurse_self_approve_lab_result')) {
            $canSelfApprove = true;
        }

        if (!$canSelfApprove) {
            return response()->json(['success' => false, 'message' => 'Self-approval is not enabled for your role.'], 403);
        }

        if ($labRequest->status != 5) {
            return response()->json(['success' => false, 'message' => 'Result is not pending approval.'], 422);
        }

        DB::beginTransaction();

        $labRequest->update([
            'result'           => $labRequest->pending_result,
            'result_data'      => $labRequest->pending_result_data,
            'attachments'      => $labRequest->pending_attachments,
            'pending_result'       => null,
            'pending_result_data'  => null,
            'pending_attachments'  => null,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'status' => 4,
        ]);

        $this->logAudit($id, 'result_self_approved', 'Result self-approved by ' . Auth::user()->surname . ' ' . Auth::user()->firstname);

        DB::commit();

        return response()->json(['success' => true, 'message' => 'Result approved successfully.']);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
    }
}
```

### 2.2 `ImagingWorkbenchController.php`

Add an identical method `selfApproveResult($id)` mirroring the lab version but using `ImagingServiceRequest`, `imaging_results_require_approval`, `doctor_self_approve_imaging_result`, `nurse_self_approve_imaging_result`, and the imaging `logAudit()` call.

### 2.3 `routes/web.php`

Add immediately after the existing `lab.approveResult` route (around line 588):

```php
Route::post('/lab-workbench/self-approve/{id}', [\App\Http\Controllers\LabWorkbenchController::class, 'selfApproveResult'])->name('lab.selfApproveResult');
```

Add immediately after the existing `imaging.approveResult` route (around line 622):

```php
Route::post('/imaging-workbench/self-approve/{id}', [\App\Http\Controllers\ImagingWorkbenchController::class, 'selfApproveResult'])->name('imaging.selfApproveResult');
```

---

## Phase 3 — "Perform Investigation" Button in Card DataTable

### 3.1 `EncounterController.php` — `investigationHistoryList()`

**Location:** Inside the per-row card HTML generation in `investigationHistoryList()`, after the existing `$canEnterResult` block (around line 590) and before the View/Print/Delete buttons.

**Add this block** (immediately after the `if ($canEnterResult) { $str .= ... }` block):

```php
// "Perform Investigation" button — for status == 1 (not yet billed), requester only
$canPerformInvestigation = false;
if (empty($his->result) && $his->status == 1 && Auth::id() == $his->doctor_id) {
    $user = Auth::user();
    if (($user->hasRole('DOCTOR') && appsettings('doctor_can_enter_lab_result'))
        || ($user->hasRole('NURSE') && appsettings('nurse_can_enter_lab_result'))
    ) {
        $canPerformInvestigation = true;
    }
}

if ($canPerformInvestigation) {
    // Pre-compute HMO pricing estimate for the billing confirmation modal
    $piServiceName = htmlspecialchars(optional($his->service)->service_name ?? 'N/A', ENT_QUOTES);
    $piPrice       = optional(optional($his->service)->price)->sale_price ?? 0;
    $piCovMode     = '';
    $piPayable     = $piPrice;
    $piClaims      = 0;
    try {
        $hmoEst = \App\Helpers\HmoHelper::applyHmoTariff($his->patient_id, null, $his->service_id);
        if ($hmoEst) {
            $piCovMode = $hmoEst['coverage_mode'] ?? '';
            $piPayable = $hmoEst['payable_amount'] ?? $piPrice;
            $piClaims  = $hmoEst['claims_amount'] ?? 0;
        }
    } catch (\Exception $e) {
        // Silently fall back to full price
    }

    $str .= "
        <button type='button' class='btn btn-warning btn-sm perform-investigation-btn'
            data-type='lab'
            data-request-id='{$his->id}'
            data-patient-id='{$his->patient_id}'
            data-service-name='{$piServiceName}'
            data-price='{$piPrice}'
            data-coverage-mode='{$piCovMode}'
            data-payable='{$piPayable}'
            data-claims='{$piClaims}'
            onclick='performInvestigation(this)'>
            <i class='mdi mdi-flask-outline'></i> Perform Investigation
        </button>";
}
```

### 3.2 `EncounterController.php` — `imagingHistoryList()`

Apply the same pattern in `imagingHistoryList()` after the existing `if ($canEnterImagingResult)` block (around line 845):

```php
$canPerformImagingInvestigation = false;
if (empty($his->result) && $his->status == 1 && Auth::id() == $his->doctor_id) {
    $user = Auth::user();
    if (($user->hasRole('DOCTOR') && appsettings('doctor_can_enter_imaging_result'))
        || ($user->hasRole('NURSE') && appsettings('nurse_can_enter_imaging_result'))
    ) {
        $canPerformImagingInvestigation = true;
    }
}

if ($canPerformImagingInvestigation) {
    $piServiceName = htmlspecialchars(optional($his->service)->service_name ?? 'N/A', ENT_QUOTES);
    $piPrice       = optional(optional($his->service)->price)->sale_price ?? 0;
    $piCovMode     = '';
    $piPayable     = $piPrice;
    $piClaims      = 0;
    try {
        $hmoEst = \App\Helpers\HmoHelper::applyHmoTariff($his->patient_id, null, $his->service_id);
        if ($hmoEst) {
            $piCovMode = $hmoEst['coverage_mode'] ?? '';
            $piPayable = $hmoEst['payable_amount'] ?? $piPrice;
            $piClaims  = $hmoEst['claims_amount'] ?? 0;
        }
    } catch (\Exception $e) { }

    $str .= "
        <button type='button' class='btn btn-warning btn-sm perform-investigation-btn'
            data-type='imaging'
            data-request-id='{$his->id}'
            data-patient-id='{$his->patient_id}'
            data-service-name='{$piServiceName}'
            data-price='{$piPrice}'
            data-coverage-mode='{$piCovMode}'
            data-payable='{$piPayable}'
            data-claims='{$piClaims}'
            onclick='performInvestigation(this)'>
            <i class='mdi mdi-radiology-box-outline'></i> Perform Investigation
        </button>";
}
```

---

## Phase 4 — New Shared Modal Partial

**Create:** `resources/views/admin/partials/perform_investigation_modal.blade.php`

This file contains both the modal HTML and the complete JavaScript for the "Perform Investigation" flow. By putting the JS in this partial, all five consumer views get it with a single `@include`.

```blade
{{--
    Perform Investigation Modal + JavaScript
    =========================================
    Include once per view that uses investigationHistoryList / imagingHistoryList.
    Requires:
      - invest_res_modal + invest_res_js partials already included in the same view
      - enterLabResult(id) and enterImagingResult(id) JS functions defined in the view
      - Bootstrap 5 modal
--}}

<div class="modal fade" id="performInvestModal" tabindex="-1" aria-labelledby="performInvestModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning bg-opacity-10">
                <h5 class="modal-title" id="performInvestModalLabel">
                    <i class="mdi mdi-flask-outline me-2 text-warning"></i>
                    Perform Investigation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    You are about to bill and perform this investigation yourself.
                    A billing record will be created and you will then be prompted to enter the result.
                </p>

                <div class="card border-0 bg-light mb-3">
                    <div class="card-body py-2">
                        <strong id="pi_service_name" class="d-block mb-2 text-dark"></strong>
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <th class="text-muted fw-normal" style="width:40%">Full Price</th>
                                    <td id="pi_full_price" class="fw-semibold"></td>
                                </tr>
                                <tr id="pi_hmo_row" style="display:none;">
                                    <th class="text-muted fw-normal">HMO Coverage</th>
                                    <td>
                                        <span id="pi_coverage_badge" class="badge bg-info me-1"></span>
                                        <span id="pi_claims_amount" class="text-success small"></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="text-muted fw-normal">Payable Amount</th>
                                    <td id="pi_payable_amount" class="fw-bold text-danger"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="alert alert-info py-2 mb-0" style="font-size:.85rem;">
                    <i class="mdi mdi-information-outline me-1"></i>
                    Confirming will record the billing and immediately open the result entry form.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning btn-sm" id="confirmPerformInvestBtn">
                    <i class="mdi mdi-check me-1"></i> Confirm &amp; Enter Result
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // ── State ─────────────────────────────────────────────────────────────
    var _piContext = {};   // { type, requestId, patientId }

    // ── Open billing confirmation modal ───────────────────────────────────
    window.performInvestigation = function(btn) {
        var $btn = $(btn);
        _piContext = {
            type:      $btn.data('type'),         // 'lab' | 'imaging'
            requestId: $btn.data('request-id'),
            patientId: $btn.data('patient-id')
        };

        var price    = parseFloat($btn.data('price')) || 0;
        var payable  = parseFloat($btn.data('payable')) || price;
        var claims   = parseFloat($btn.data('claims')) || 0;
        var covMode  = ($btn.data('coverage-mode') || '').toString().trim();

        $('#pi_service_name').text($btn.data('service-name'));
        $('#pi_full_price').text(price.toLocaleString(undefined, {minimumFractionDigits: 2}));
        $('#pi_payable_amount').text(payable.toLocaleString(undefined, {minimumFractionDigits: 2}));

        if (covMode) {
            $('#pi_coverage_badge').text(covMode.toUpperCase());
            $('#pi_claims_amount').text('Claim: ' + claims.toLocaleString(undefined, {minimumFractionDigits: 2}));
            $('#pi_hmo_row').show();
        } else {
            $('#pi_hmo_row').hide();
        }

        var modal = new bootstrap.Modal(document.getElementById('performInvestModal'));
        modal.show();
    };

    // ── Confirm: bill then open result entry modal ────────────────────────
    $(document).on('click', '#confirmPerformInvestBtn', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="mdi mdi-loading mdi-spin me-1"></i> Billing...');

        var billingUrl = _piContext.type === 'lab'
            ? '{{ route("lab.recordBilling") }}'
            : '{{ route("imaging.recordBilling") }}';

        $.ajax({
            url: billingUrl,
            method: 'POST',
            data: {
                _token:      $('meta[name="csrf-token"]').attr('content'),
                request_ids: [_piContext.requestId],
                patient_id:  _piContext.patientId
            },
            success: function(response) {
                if (!response.success) {
                    toastr.error(response.message || 'Billing failed.');
                    $btn.prop('disabled', false).html('<i class="mdi mdi-check me-1"></i> Confirm & Enter Result');
                    return;
                }

                // Close billing modal, then open result entry modal
                bootstrap.Modal.getInstance(document.getElementById('performInvestModal')).hide();

                // Track context so the post-save callback knows what type this was
                window._investResultContext = {
                    type: _piContext.type,
                    id:   _piContext.requestId
                };

                if (_piContext.type === 'lab') {
                    // enterLabResult must be defined in the consuming view
                    if (typeof enterLabResult === 'function') {
                        enterLabResult(_piContext.requestId);
                    }
                } else {
                    if (typeof enterImagingResult === 'function') {
                        enterImagingResult(_piContext.requestId);
                    }
                }
            },
            error: function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Billing error.';
                toastr.error(msg);
                $btn.prop('disabled', false).html('<i class="mdi mdi-check me-1"></i> Confirm & Enter Result');
            }
        });
    });

    // ── Reset button state when modal is hidden ───────────────────────────
    document.getElementById('performInvestModal').addEventListener('hidden.bs.modal', function() {
        $('#confirmPerformInvestBtn')
            .prop('disabled', false)
            .html('<i class="mdi mdi-check me-1"></i> Confirm & Enter Result');
    });
})();
</script>
```

---

## Phase 5 — Self-Approve Callback Helper (Shared JS Snippet)

This JS snippet is **not** a separate partial — it is added directly inside each view's `@section('scripts')` where `InvestResultEntry.bindFormSubmit()` is called. Add it once per view immediately before the `bindFormSubmit` call.

The snippet bakes the hospital config settings as PHP-evaluated JS constants, then provides a reusable `_autoApproveIfEnabled(requestId, type)` function:

```blade
<script>
// ── Perform Investigation — self-approve config ───────────────────────────
(function() {
    var LAB_REQUIRES_APPROVAL      = {{ appsettings('lab_results_require_approval') ? 'true' : 'false' }};
    var IMAGING_REQUIRES_APPROVAL  = {{ appsettings('imaging_results_require_approval') ? 'true' : 'false' }};
    var DR_SELF_APPROVE_LAB        = {{ appsettings('doctor_self_approve_lab_result') ? 'true' : 'false' }};
    var NR_SELF_APPROVE_LAB        = {{ appsettings('nurse_self_approve_lab_result') ? 'true' : 'false' }};
    var DR_SELF_APPROVE_IMAGING    = {{ appsettings('doctor_self_approve_imaging_result') ? 'true' : 'false' }};
    var NR_SELF_APPROVE_IMAGING    = {{ appsettings('nurse_self_approve_imaging_result') ? 'true' : 'false' }};

    // Called by each view's bindFormSubmit success callback
    window._autoApproveIfEnabled = function(requestId, type) {
        // Don't auto-approve edits
        if ($('#invest_res_is_edit').val() == '1') return;

        var requiresApproval = (type === 'lab') ? LAB_REQUIRES_APPROVAL : IMAGING_REQUIRES_APPROVAL;
        if (!requiresApproval) return;

        var canSelf = (type === 'lab')
            ? (DR_SELF_APPROVE_LAB || NR_SELF_APPROVE_LAB)
            : (DR_SELF_APPROVE_IMAGING || NR_SELF_APPROVE_IMAGING);

        if (!canSelf) return;

        var approveUrl = (type === 'lab')
            ? '/lab-workbench/self-approve/' + requestId
            : '/imaging-workbench/self-approve/' + requestId;

        $.post(approveUrl, { _token: $('meta[name="csrf-token"]').attr('content') })
            .done(function(res) {
                if (res && res.success) {
                    toastr.success('Result approved automatically.');
                } else {
                    toastr.warning('Result saved. Auto-approval failed: ' + (res.message || ''));
                }
            })
            .fail(function() {
                toastr.warning('Result saved but auto-approval could not be completed.');
            });
    };
})();
</script>
```

---

## Phase 6 — Update Each View

### 6.1 `new_encounter.blade.php`

**A) Add include** for the new modal partial near the bottom of the file, alongside the existing invest_res includes:

```blade
@include('admin.partials.perform_investigation_modal')
```

Place it after `@include('admin.partials.invest_res_view_imaging_js')`.

**B) Track context in `enterLabResult()` and `enterImagingResult()`** (around line 2100):

```javascript
function enterLabResult(requestId) {
    window._investResultContext = { type: 'lab', id: requestId };  // ADD THIS LINE
    InvestResultEntry.enterResult(
        requestId,
        `/lab-workbench/lab-service-requests/${requestId}`,
        `/lab-workbench/lab-service-requests/${requestId}/attachments`,
        '{{ route("lab.saveResult") }}'
    );
}

function enterImagingResult(requestId) {
    window._investResultContext = { type: 'imaging', id: requestId };  // ADD THIS LINE
    InvestResultEntry.enterResult(
        requestId,
        `/imaging-workbench/imaging-service-requests/${requestId}`,
        `/imaging-workbench/imaging-service-requests/${requestId}/attachments`,
        '{{ route("imaging.saveResult") }}'
    );
}
```

**C) Add the self-approve config snippet** (from Phase 5) immediately before the existing `InvestResultEntry.bindFormSubmit(...)` call.

**D) Update the `bindFormSubmit` callback** to call `_autoApproveIfEnabled`:

```javascript
InvestResultEntry.bindFormSubmit(function() {
    if ($.fn.DataTable.isDataTable('#investigation_history_list')) {
        $('#investigation_history_list').DataTable().ajax.reload(null, false);
    }
    if ($.fn.DataTable.isDataTable('#imaging_history_list')) {
        $('#imaging_history_list').DataTable().ajax.reload(null, false);
    }
    // Auto-approve if configured
    var ctx = window._investResultContext;
    if (ctx) {
        _autoApproveIfEnabled(ctx.id, ctx.type);
        window._investResultContext = null;
    }
});
```

### 6.2 `resources/views/admin/lab/workbench.blade.php`

**A)** Add `@include('admin.partials.perform_investigation_modal')` near the end of the view alongside the other invest_res includes.

**B)** The lab workbench already has `enterResult(requestId)` which calls `InvestResultEntry.enterResult(...)`. Wrap it to also track context:

```javascript
function enterResult(requestId) {
    window._investResultContext = { type: 'lab', id: requestId };
    InvestResultEntry.enterResult(requestId,
        '/lab-workbench/lab-service-requests/' + requestId,
        '/lab-workbench/lab-service-requests/' + requestId + '/attachments');
}
```

**C)** Add the self-approve config snippet immediately before the existing `InvestResultEntry.bindFormSubmit(...)` call (around line 3963).

**D)** Update the `bindFormSubmit` callback to call `_autoApproveIfEnabled`:

```javascript
InvestResultEntry.bindFormSubmit(function() {
    // existing reload logic ...
    var ctx = window._investResultContext;
    if (ctx) {
        _autoApproveIfEnabled(ctx.id, ctx.type);
        window._investResultContext = null;
    }
});
```

### 6.3 `resources/views/admin/imaging/workbench.blade.php`

Same pattern as lab workbench but:
- Track context as `type: 'imaging'` in `enterResult(requestId)`
- `_autoApproveIfEnabled` uses `type: 'imaging'`

### 6.4 `resources/views/admin/nursing/workbench.blade.php`

The nursing workbench has both `enterResult(requestId)` for lab (line 9500) and a similar function for imaging. Apply the same two changes:
- Track context in both enter functions
- Update the `bindFormSubmit` callback to call `_autoApproveIfEnabled`
- Include `perform_investigation_modal` partial

### 6.5 `resources/views/admin/maternity/workbench.blade.php`

Same pattern. Maternity workbench has `InvestResultEntry.enterResult(...)` calls at lines 7898 and 7919. Wrap them in named functions that track context, then update the `bindFormSubmit` callback.

---

## Phase 7 — Flow Summary (End-to-End)

### Flow A: Unbilled Request (status = 1) — New "Perform Investigation" path

```
[EncounterController renders card with "Perform Investigation" button (status=1)]
    ↓ Doctor clicks button
[performInvestigation(btn) reads data attributes → populates #performInvestModal]
    ↓ Doctor sees service name, price, HMO coverage, payable amount
[Doctor clicks "Confirm & Enter Result"]
    ↓
[AJAX POST → lab.recordBilling / imaging.recordBilling]
    ↓ LabWorkbenchController::recordBilling() creates/links ProductOrServiceRequest,
      applies HMO tariff, sets status = 2, billed_by = Auth::id(), billed_date = now()
    ↓ Success response
[Bootstrap modal hides → enterLabResult(id) or enterImagingResult(id) called]
    ↓ _investResultContext set { type, id }
[InvestResultEntry.enterResult() → fetches /lab-workbench/lab-service-requests/{id}]
    ↓ investResModal shows with V1/V2 template
[Doctor enters result, clicks "Save Result"]
    ↓
[AJAX POST → lab.saveResult / imaging.saveResult]
    ↓ If lab_results_require_approval = true: status = 5 (pending), saves to pending_* columns
    ↓ If lab_results_require_approval = false: status = 4 (completed), saves to live columns
[investResModal hides → bindFormSubmit onSuccess callback fires]
    ↓ DataTable reloads
    ↓ _autoApproveIfEnabled(id, 'lab') called
        ↓ If requires_approval AND (dr_self_approve OR nr_self_approve):
            [AJAX POST → lab.selfApproveResult / imaging.selfApproveResult]
            ↓ LabWorkbenchController::selfApproveResult() validates requester,
              moves pending → live columns, status = 4
            ↓ toastr.success('Result approved automatically.')
        ↓ Else: nothing (goes to normal approval queue)
```

### Flow B: Already Billed (status ≥ 2) — Existing "Enter Result" path (augmented)

```
[EncounterController renders card with "Enter Result" button (status ≥ 2, empty result)]
    ↓ Doctor clicks button → enterLabResult(id) / enterImagingResult(id)
[_investResultContext set, InvestResultEntry.enterResult() called]
    ↓ [Same result entry flow as Flow A from InvestResultEntry step onwards]
```

---

## Phase 8 — Edge Cases & Guards

| Scenario | Guard |
|---|---|
| Doctor clicks "Perform Investigation" but request was just billed by cashier | `recordBilling()` already checks `$his->service_request_id`; if already billed, reuses existing `ProductOrServiceRequest`. Status will be 2 already. The billing endpoint is idempotent for this path. |
| Non-requester can't see the button | Button only rendered when `Auth::id() == $his->doctor_id` in PHP |
| Self-approve with approval disabled | `_autoApproveIfEnabled` checks `requiresApproval` first; returns early if false |
| Self-approve called on edit (not new result) | `$('#invest_res_is_edit').val() == '1'` guard in `_autoApproveIfEnabled` |
| Self-approve endpoint called by non-requester | `Auth::id() !== $labRequest->doctor_id` check returns 403 |
| Self-approve endpoint called by user without right setting | Role + setting check returns 403 |
| Result status is not 5 (pending) when self-approve fires | Returns 422; toastr warning shown to user |
| HmoHelper::applyHmoTariff throws (no HMO/config error) | Wrapped in try/catch, silently falls back to full service price in the button data attributes |

---

## Phase 9 — Database (No Migrations Needed)

All new config settings (`doctor_self_approve_lab_result`, etc.) are stored in the `hospital_configs` table as key/value pairs via the existing `appsettings()` system. No schema changes required.

The `selfApproveResult()` methods use existing columns: `pending_result`, `pending_result_data`, `pending_attachments`, `result`, `result_data`, `attachments`, `approved_by`, `approved_at`, `status`. All of these already exist from the approval workflow implementation.

---

## Phase 10 — Verification Checklist

After implementation, verify:

- [ ] "Perform Investigation" button visible only to requesting doctor/nurse on unbilled (status=1) rows
- [ ] Button not visible to other staff or when result already exists
- [ ] Billing confirmation modal shows correct price and HMO info
- [ ] Confirming billing correctly creates the `ProductOrServiceRequest` record and sets `status=2`
- [ ] InvestResultEntry modal opens after billing with correct service template
- [ ] Result saves correctly to lab/imaging workbench (visible in lab/imaging workbench DataTable)
- [ ] With `lab_results_require_approval=true` and `doctor_self_approve_lab_result=true`: result auto-approves, goes to status=4
- [ ] With `lab_results_require_approval=true` and `doctor_self_approve_lab_result=false`: result goes to status=5, visible in approval queue
- [ ] With `lab_results_require_approval=false`: result goes directly to status=4 (no self-approve needed)
- [ ] Edit flow (`editLabResult`) does NOT trigger self-approve (`invest_res_is_edit=1` guard)
- [ ] Hospital config page shows all 4 new self-approve toggles and saves correctly
- [ ] All 5 views (new_encounter, lab wb, imaging wb, nursing wb, maternity wb) have the new modal and correct callbacks
