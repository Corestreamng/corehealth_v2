# Nursing Workbench — Billing Tab Enhancement Plan

## 1. Problem Statement

Currently in the nursing workbench there are two separate item-insertion flows with no cross-reference:

| Tab | What it creates | Creates POSR (billing record)? |
|---|---|---|
| **Clinical Requests** → Prescriptions | `ProductRequest` | ❌ No — Pharmacy bills later |
| **Clinical Requests** → Labs | `LabServiceRequest` | ❌ No — Lab workbench bills later |
| **Clinical Requests** → Imaging | `ImagingServiceRequest` | ❌ No — Imaging workbench bills later |
| **Clinical Requests** → Procedures | `Procedure` + `ProductOrServiceRequest` | ✅ Yes — billed directly |
| **Billing** → Services | `ProductOrServiceRequest` (service) | ✅ Yes — billed directly |
| **Billing** → Consumables | `ProductOrServiceRequest` (product) + stock deduction | ✅ Yes — billed directly |

**Problems:**
1. There is **no way for nurses to directly bill** labs, imaging, or products from the Billing tab — they can only add services and consumables.
2. There are **no notes** in the Clinical Requests tab explaining that items will be billed by their respective departments (lab/pharmacy/imaging workbenches), causing confusion about where billing happens.
3. Nurses sometimes want to **quick-bill** a lab or imaging item without waiting for the respective department workflow — especially for walk-in or urgent cases.

---

## 2. Goals

1. **Clinical Requests tab** — Add informational notes in Labs, Imaging, and Prescriptions sub-tabs explaining the billing workflow.
2. **Billing tab** — Add two new sub-tabs: **Labs** and **Imaging** for direct billing (creating both the department request AND the POSR in one step).
3. **Billing toggle** — In each new billing sub-tab, provide a clear workflow that creates the lab/imaging request AND its billing record (POSR) simultaneously.

---

## 3. UI/UX Design

### 3.1 Clinical Requests — Informational Notes

Add a small alert banner at the top of each "New" sub-tab pane (not in the history pane) in the Clinical Requests section:

#### Prescriptions (inside `#cr-presc-new`, before the search input)
```html
<div class="alert alert-light border-left-info py-2 px-3 mb-3 small">
    <i class="mdi mdi-information-outline text-info"></i>
    <strong>Note:</strong> Prescriptions added here are sent to the <strong>Pharmacy</strong> for dispensing and billing.
    For quick/direct billing, use the <strong>Billing → Consumables</strong> tab instead.
</div>
```

#### Labs (inside `#cr-lab-new`, before the search input)
```html
<div class="alert alert-light border-left-info py-2 px-3 mb-3 small">
    <i class="mdi mdi-information-outline text-info"></i>
    <strong>Note:</strong> Lab requests added here are sent to the <strong>Laboratory</strong> for processing and billing.
    For quick/direct billing, use the <strong>Billing → Labs</strong> tab instead.
</div>
```

#### Imaging (inside `#cr-imaging-new`, before the search input)
```html
<div class="alert alert-light border-left-info py-2 px-3 mb-3 small">
    <i class="mdi mdi-information-outline text-info"></i>
    <strong>Note:</strong> Imaging requests added here are sent to the <strong>Imaging</strong> department for processing and billing.
    For quick/direct billing, use the <strong>Billing → Imaging</strong> tab instead.
</div>
```

**Visual style:** Left-bordered info alert (`border-left: 4px solid #17a2b8`), small text, no dismiss button — always visible as a permanent guide.

---

### 3.2 Billing Tab — Expanded Sub-tabs

Current tabs: **Services | Consumables | Pending Bills | Billing History**

New tabs: **Services | Labs | Imaging | Consumables | Pending Bills | Billing History**

```
┌──────────┬──────┬─────────┬─────────────┬───────────────┬─────────────────┐
│ Services │ Labs │ Imaging │ Consumables │ Pending Bills │ Billing History │
└──────────┴──────┴─────────┴─────────────┴───────────────┴─────────────────┘
```

New tab icons:
- Labs: `mdi mdi-flask` (green header, `bg-success`)
- Imaging: `mdi mdi-radioactive` (purple header, `bg-purple` or `bg-secondary`)

---

### 3.3 Billing → Labs Sub-tab UI

```
┌─────────────────────────────────────────────────────────────┐
│ 🧪  Direct Lab Billing                            [bg-success header] │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─ Info Banner ──────────────────────────────────────────┐ │
│  │ ℹ This creates a lab request AND bills it directly.    │ │
│  │   The lab department will see it as already billed.    │ │
│  └────────────────────────────────────────────────────────┘ │
│                                                             │
│  🔍 Search Lab Service *              💰 Price             │
│  [___________________________]        [₦ Auto-calculated_] │
│  ┌─ dropdown ─────────────┐                                │
│  │ [Lab] CBC [CBC001]     │                                │
│  │   ₦5,000               │                                │
│  │   HMO: Pay ₦1,500      │                                │
│  │         Claim ₦3,500    │                                │
│  │         [PRIMARY]       │                                │
│  │ ─────────────────────── │                                │
│  │ [Lab] Urinalysis        │                                │
│  │   ₦3,000               │                                │
│  └─────────────────────────┘                                │
│                                                             │
│  📝 Clinical Notes                                         │
│  [___________________________________________]              │
│                                                             │
│                              [ 🧪 Add Lab Bill ]           │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Behavior:**
1. Nurse searches for a lab service (reuses existing `searchServices()` endpoint filtered to lab/investigation categories).
2. Search dropdown shows HMO tariff info (same rendering as current service dropdown).
3. On selecting a service, the price field auto-fills with HMO `payable_amount` (or regular price if non-HMO).
4. On submit:
   - **Backend** creates `LabServiceRequest` with `status = 2` (billed) + creates `ProductOrServiceRequest` with HMO tariff amounts.
   - Links POSR to lab request via `service_request_id`.
   - Returns success.
5. **Frontend** refreshes Pending Bills table + Billing History.

---

### 3.4 Billing → Imaging Sub-tab UI

Identical layout to Labs but with imaging-specific labels:

```
┌─────────────────────────────────────────────────────────────┐
│ ☢️  Direct Imaging Billing                    [bg-secondary header] │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─ Info Banner ──────────────────────────────────────────┐ │
│  │ ℹ This creates an imaging request AND bills it         │ │
│  │   directly. The imaging dept will see it as billed.    │ │
│  └────────────────────────────────────────────────────────┘ │
│                                                             │
│  🔍 Search Imaging Service *          💰 Price             │
│  [___________________________]        [₦ Auto-calculated_] │
│                                                             │
│  📝 Clinical Notes                                         │
│  [___________________________________________]              │
│                                                             │
│                              [ ☢️ Add Imaging Bill ]        │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

**Behavior:** Same as Labs but creates `ImagingServiceRequest` instead.

---

## 4. Backend Changes

### 4.1 New Controller Methods

Add to `NursingWorkbenchController`:

#### `addLabBill(Request $request)` — Direct lab billing
```
POST /nursing-workbench/billing/add-lab-bill
```

**Logic:**
1. Validate `patient_id`, `service_id`, `notes` (optional).
2. Begin transaction.
3. Create `ProductOrServiceRequest`:
   - `user_id` = patient's user_id
   - `staff_user_id` = Auth::id()
   - `service_id` = selected service
   - Apply `HmoHelper::applyHmoTariff($patientId, null, $serviceId)` → set `payable_amount`, `claims_amount`, `coverage_mode`, `validation_status`
   - Fallback to `service.price.sale_price` if no HMO tariff
4. Create `LabServiceRequest`:
   - `service_id` = selected service
   - `patient_id` = patient_id
   - `doctor_id` = Auth::id()
   - `note` = clinical notes
   - `status` = 2 (billed)
   - `billed_by` = Auth::id()
   - `billed_date` = now()
   - `service_request_id` = POSR's id  ← links them
5. Commit.
6. Return `{ success: true, message, bill: POSR, lab: LabServiceRequest }`.

#### `addImagingBill(Request $request)` — Direct imaging billing
```
POST /nursing-workbench/billing/add-imaging-bill
```

**Logic:** Same pattern as `addLabBill` but creates `ImagingServiceRequest` instead.

### 4.2 Search Endpoints

The existing `searchServices()` method already batch-loads HMO tariffs and returns `{ id, name, code, price, category, hmo }`. It can be reused directly for both lab and imaging billing search dropdowns.

The only difference is the **category filtering**:
- **Labs** → filter by `investigation_category_id` (from `appsettings('investigation_category_id')`)
- **Imaging** → filter by imaging category ID (from `appsettings('imaging_category_id')` — check if this exists, or use a known category)

The existing `searchServices()` already supports `category_id` as a query parameter, so the frontend just needs to pass the appropriate category ID.

### 4.3 New Routes

```php
// Billing tab — direct lab/imaging billing
Route::post('/nursing-workbench/billing/add-lab-bill', [NursingWorkbenchController::class, 'addLabBill'])
    ->name('nursing-workbench.billing.add-lab-bill');
Route::post('/nursing-workbench/billing/add-imaging-bill', [NursingWorkbenchController::class, 'addImagingBill'])
    ->name('nursing-workbench.billing.add-imaging-bill');
```

---

## 5. Frontend Changes

### 5.1 Billing Tab HTML (workbench.blade.php)

Insert two new `<li>` items in `#billing-sub-tabs` after Services:

```html
<li class="nav-item">
    <a class="nav-link" id="billing-labs-tab" data-toggle="tab" href="#billing-labs" role="tab">
        <i class="mdi mdi-flask"></i> Labs
    </a>
</li>
<li class="nav-item">
    <a class="nav-link" id="billing-imaging-tab" data-toggle="tab" href="#billing-imaging" role="tab">
        <i class="mdi mdi-radioactive"></i> Imaging
    </a>
</li>
```

Insert two new tab panes in `#billing-sub-content` after the Services pane:

#### Labs Pane
```html
<div class="tab-pane fade" id="billing-labs" role="tabpanel">
    <div class="card-modern">
        <div class="card-header bg-success text-white py-2">
            <h6 class="mb-0"><i class="mdi mdi-flask"></i> Direct Lab Billing</h6>
        </div>
        <div class="card-body">
            <div class="alert alert-light border py-2 px-3 mb-3 small" style="border-left: 4px solid #28a745 !important;">
                <i class="mdi mdi-information-outline text-success"></i>
                This creates a lab request <strong>and bills it directly</strong>. The lab department will see it as already billed.
            </div>
            <form id="lab-billing-form">
                <div class="form-row">
                    <div class="form-group col-md-8" style="position: relative;">
                        <label for="lab-billing-search"><i class="mdi mdi-magnify"></i> Search Lab Service *</label>
                        <input type="text" class="form-control" id="lab-billing-search" placeholder="Type to search lab services..." autocomplete="off">
                        <input type="hidden" id="lab-billing-id">
                        <ul class="list-group" id="lab-billing-search-results" style="display: none; position: absolute; z-index: 1050; max-height: 280px; overflow-y: auto; width: 100%; left: 0;"></ul>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="lab-billing-price"><i class="mdi mdi-currency-ngn"></i> Price</label>
                        <input type="text" class="form-control" id="lab-billing-price" readonly placeholder="Auto-calculated">
                    </div>
                </div>
                <div class="form-group">
                    <label for="lab-billing-notes"><i class="mdi mdi-note-text"></i> Clinical Notes</label>
                    <textarea class="form-control" id="lab-billing-notes" rows="2" placeholder="Any clinical notes..."></textarea>
                </div>
                <div class="form-actions text-right">
                    <button type="submit" class="btn btn-success">
                        <i class="mdi mdi-flask-plus"></i> Add Lab Bill
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
```

#### Imaging Pane
```html
<div class="tab-pane fade" id="billing-imaging" role="tabpanel">
    <div class="card-modern">
        <div class="card-header py-2" style="background: #6f42c1; color: white;">
            <h6 class="mb-0"><i class="mdi mdi-radioactive"></i> Direct Imaging Billing</h6>
        </div>
        <div class="card-body">
            <div class="alert alert-light border py-2 px-3 mb-3 small" style="border-left: 4px solid #6f42c1 !important;">
                <i class="mdi mdi-information-outline" style="color: #6f42c1;"></i>
                This creates an imaging request <strong>and bills it directly</strong>. The imaging department will see it as already billed.
            </div>
            <form id="imaging-billing-form">
                <div class="form-row">
                    <div class="form-group col-md-8" style="position: relative;">
                        <label for="imaging-billing-search"><i class="mdi mdi-magnify"></i> Search Imaging Service *</label>
                        <input type="text" class="form-control" id="imaging-billing-search" placeholder="Type to search imaging services..." autocomplete="off">
                        <input type="hidden" id="imaging-billing-id">
                        <ul class="list-group" id="imaging-billing-search-results" style="display: none; position: absolute; z-index: 1050; max-height: 280px; overflow-y: auto; width: 100%; left: 0;"></ul>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="imaging-billing-price"><i class="mdi mdi-currency-ngn"></i> Price</label>
                        <input type="text" class="form-control" id="imaging-billing-price" readonly placeholder="Auto-calculated">
                    </div>
                </div>
                <div class="form-group">
                    <label for="imaging-billing-notes"><i class="mdi mdi-note-text"></i> Clinical Notes</label>
                    <textarea class="form-control" id="imaging-billing-notes" rows="2" placeholder="Any clinical notes..."></textarea>
                </div>
                <div class="form-actions text-right">
                    <button type="submit" class="btn" style="background: #6f42c1; color: white;">
                        <i class="mdi mdi-radioactive"></i> Add Imaging Bill
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
```

### 5.2 Billing Tab JS

Add search handlers and form submit handlers for both lab and imaging billing sub-tabs. Follows the same pattern as existing `#service-billing-form` handler.

#### Lab Billing Search
```js
// Lab Billing Search — reuses searchServices with investigation category filter
let labBillingTimer;
$('#lab-billing-search').on('input', function() {
    clearTimeout(labBillingTimer);
    const term = $(this).val().trim();
    if (term.length < 2) { $('#lab-billing-search-results').hide(); return; }

    labBillingTimer = setTimeout(() => {
        $.get('{{ route("nursing-workbench.search-services") }}', {
            term: term,
            patient_id: currentPatient,
            category_id: '{{ appsettings("investigation_category_id", "") }}'
        }, function(results) {
            // Render dropdown — same as existing service search rendering
            // with HMO tariff display
            renderBillingSearchResults(results, '#lab-billing-search-results', 'selectLabBilling');
        });
    }, 300);
});

function selectLabBilling(id, name, price) {
    $('#lab-billing-id').val(id);
    $('#lab-billing-search').val(name);
    $('#lab-billing-price').val('₦' + parseFloat(price).toLocaleString());
    $('#lab-billing-search-results').hide();
}
```

#### Lab Billing Form Submit
```js
$('#lab-billing-form').on('submit', function(e) {
    e.preventDefault();
    const data = {
        patient_id: currentPatient,
        service_id: $('#lab-billing-id').val(),
        notes: $('#lab-billing-notes').val()
    };
    if (!data.service_id) {
        showNotification('error', 'Please select a lab service');
        return;
    }
    $.ajax({
        url: '{{ route("nursing-workbench.billing.add-lab-bill") }}',
        method: 'POST',
        data: data,
        headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
        success: function(response) {
            showNotification('success', response.message || 'Lab billed successfully');
            if (billingHistoryLoaded) reloadBillingHistory();
            $('#lab-billing-form')[0].reset();
            $('#lab-billing-id').val('');
            loadPendingBills(currentPatient);
        },
        error: function(xhr) {
            showNotification('error', xhr.responseJSON?.message || 'Failed to bill lab');
        }
    });
});
```

#### Imaging Billing — Same pattern
Same as lab but with `imaging-billing-*` IDs, `imaging_category_id` appsetting, and `add-imaging-bill` route.

#### Shared Search Renderer
```js
function renderBillingSearchResults(results, containerSelector, selectFnName) {
    const $container = $(containerSelector);
    if (!results.length) {
        $container.html('<li class="list-group-item text-muted">No results</li>').show();
        return;
    }
    let html = '';
    results.forEach(service => {
        const price = parseFloat(service.price || 0);
        const hmo = service.hmo;
        const payable = hmo ? parseFloat(hmo.payable) : price;
        html += `<li class="list-group-item billing-search-item"
            onclick="${selectFnName}(${service.id}, '${service.name.replace(/'/g,"\\'")}', ${payable})"
            style="cursor:pointer;">
            <div class="billing-search-item-name">${service.name}</div>
            <div class="billing-search-item-meta">
                <span class="billing-search-item-price">
                    ${hmo ? '<s style="color:#999;font-weight:400">₦' + price.toLocaleString() + '</s>'
                          : '₦' + price.toLocaleString()}
                </span>
                ${service.category ? `<span class="billing-search-item-badge">${service.category}</span>` : ''}
                ${service.code ? `<span class="billing-search-item-badge">${service.code}</span>` : ''}
            </div>
            ${hmo ? `<div class="billing-search-hmo-row">
                <span class="billing-search-hmo-label">HMO:</span>
                <span class="billing-search-hmo-payable">Pay ₦${parseFloat(hmo.payable).toLocaleString()}</span>
                <span class="billing-search-hmo-claims">Claim ₦${parseFloat(hmo.claims).toLocaleString()}</span>
                <span class="billing-search-hmo-mode mode-${hmo.mode}">${hmo.mode}</span>
            </div>` : ''}
        </li>`;
    });
    $container.html(html).show();
}
```

---

## 6. Data Flow Diagrams

### 6.1 Current Flow (Clinical Requests → Department bills later)
```
Nurse picks lab service
    → POST /nursing-workbench/clinical-requests/add-lab
        → Creates LabServiceRequest (status=1, no POSR)
            → Lab workbench sees it as "Awaiting Billing"
                → Lab tech clicks "Bill" → Creates POSR with HMO tariff
                    → Cashier sees POSR in billing queue
```

### 6.2 New Flow (Billing tab → Direct billing)
```
Nurse picks lab service in Billing → Labs tab
    → POST /nursing-workbench/billing/add-lab-bill
        → Creates POSR with HMO tariff (payable, claims, coverage_mode, validation_status)
        → Creates LabServiceRequest (status=2, billed, linked to POSR via service_request_id)
            → Lab workbench sees it as "Billed" (proceeds to sample collection)
            → Cashier sees POSR in billing queue immediately
```

### 6.3 Prescriptions — No change to direct billing
Consumables (Billing → Consumables) already handles direct product billing with stock deduction. The Clinical Requests → Prescriptions path sends to pharmacy as before. **No new sub-tab needed for prescriptions** — the existing Consumables tab already covers direct product billing.

---

## 7. Implementation Phases

### Phase 1: Clinical Requests Notes (Low effort)
- Add 3 info banners to CR sub-tabs (Prescriptions, Labs, Imaging)
- Pure HTML changes in `workbench.blade.php`
- Add CSS for `.border-left-info` style

### Phase 2: Backend — Direct Billing Endpoints (Medium effort)
- Add `addLabBill()` and `addImagingBill()` methods to `NursingWorkbenchController`
- Add 2 routes to `routes/web.php`
- Follows exact same POSR creation pattern as existing `addServiceBill()` + additional LabServiceRequest/ImagingServiceRequest creation

### Phase 3: Frontend — Billing Tab Expansion (Medium effort)
- Add Labs and Imaging tab headers
- Add Labs and Imaging tab panes with forms
- Add search handlers (reuse existing `searchServices()` endpoint with category filter)
- Add form submit handlers
- Refactor existing service search dropdown into shared `renderBillingSearchResults()` function

---

## 8. Edge Cases & Validation

| Scenario | Handling |
|---|---|
| Non-HMO patient | Fall back to `service.price.sale_price` (same as existing `addServiceBill`) |
| HMO patient, no tariff defined | Show toastr error: "No HMO tariff found for this service. Contact admin." |
| Duplicate lab/imaging request | Not blocked — nurses may legitimately order the same test twice |
| Patient not selected | Button disabled / toastr error if `currentPatient` is null |
| Lab request already exists (via CR tab) | These are independent — direct billing creates a NEW lab request (already billed). The pending one from CR tab still exists for the lab dept. |

---

## 9. Files to Modify

| File | Changes |
|---|---|
| `resources/views/admin/nursing/workbench.blade.php` | Add 3 info banners, 2 tab headers, 2 tab panes, search JS, form submit JS |
| `app/Http/Controllers/NursingWorkbenchController.php` | Add `addLabBill()`, `addImagingBill()` methods |
| `routes/web.php` | Add 2 POST routes |

**Total: 3 files modified. No new files needed.**
