# Tariff Preview for Unbilled Items — Implementation Plan

## Problem

In the Pharmacy, Lab, and Imaging workbenches, the **Billing tab** shows unbilled items with **no HMO pricing information**. Staff see only the base price and an "Unbilled" badge. They have no idea what the patient will pay vs. what the HMO covers until **after** billing.

Meanwhile, the **Pending/Sample/Results** tabs (post-billing) show full HMO breakdown: coverage mode badge, patient-pays amount, and HMO claim amount.

This forces staff to bill blindly and creates confusion, especially when items are NC (not covered) or PE (partial exclusion).

## Goal

Show a **tariff preview** on unbilled item cards so staff can see the expected HMO split **before** billing. This is a read-only estimate based on the current tariff — no billing records are created.

---

## Design: UI/UX

### Visual Treatment

**Key principle**: The preview should be visually distinct from the confirmed HMO info shown on billed items, to clearly communicate "this is an estimate, not yet billed."

```
┌─────────────────────────────────────────────────────┐
│  💊 AMOXYCILLIN 500MG CAPS                    Qty: 2│
│  ₦290.00                              ⚬ Unbilled   │
│                                                     │
│  ┌─ HMO Estimate ─────────────────────────────────┐ │
│  │ 🏷 PRIMARY   Patient: ₦0.00   HMO: ₦290.00    │ │
│  └─────────────────────────────────────────────────┘ │
│                                                     │
│  Dose: 500mg BD × 5 days                           │
│  Requested by: Dr. Smith · Mar 16, 2026            │
└─────────────────────────────────────────────────────┘
```

**For non-HMO (Private/Cash) patients**: No preview is shown — they see the normal base price only.

**For HMO patients with a tariff**: A subtle info-toned bar appears below the price:

```html
<div class="tariff-preview small mt-1 p-2 rounded border border-info bg-info bg-opacity-10">
    <i class="mdi mdi-information-outline text-info me-1"></i>
    <span class="text-muted fw-semibold">HMO Estimate:</span>
    <span class="badge bg-info ms-1">{COVERAGE_MODE}</span>
    <span class="text-danger ms-2">Patient: ₦{payable}</span>
    <span class="text-success ms-2">HMO: ₦{claims}</span>
</div>
```

**For HMO patients without a tariff** (tariff not configured):

```html
<div class="tariff-preview small mt-1 p-2 rounded border border-warning bg-warning bg-opacity-10">
    <i class="mdi mdi-alert-outline text-warning me-1"></i>
    <span class="text-muted">No HMO tariff configured — patient may pay full price</span>
</div>
```

### Design Rationale

| Choice | Reason |
|--------|--------|
| Light blue tinted border + background | Differentiates from the solid `bg-light` block used for confirmed HMO info on billed items |
| "HMO Estimate" label | Clearly communicates this is a preview, not final billing |
| `mdi-information-outline` icon | Softer than the confirmed HMO block (no icon) — signals informational |
| Same data layout (badge + Pay + HMO) | Consistent with the post-billing HMO info block — easy to scan |
| Warning state for missing tariff | Alerts staff proactively that billing will fail or fall back to full price |

---

## Architecture

### Backend: New `HmoHelper` Method

Add a lightweight tariff-preview method that doesn't create any records:

```php
// app/Helpers/HmoHelper.php

/**
 * Preview what the HMO tariff split would be for a product/service,
 * without creating any billing records.
 *
 * Returns null for non-HMO patients.
 * Returns ['no_tariff' => true] if HMO patient but no tariff configured.
 */
public static function previewTariff($patientId, $productId = null, $serviceId = null, $qty = 1)
{
    $patient = Patient::find($patientId);
    if (!$patient || !$patient->hmo_id) {
        return null;
    }

    $tariff = HmoTariff::where('hmo_id', $patient->hmo_id)
        ->where(function($q) use ($productId, $serviceId) {
            if ($productId) {
                $q->where('product_id', $productId)->whereNull('service_id');
            } else {
                $q->where('service_id', $serviceId)->whereNull('product_id');
            }
        })
        ->first();

    if (!$tariff) {
        return ['no_tariff' => true];
    }

    return [
        'payable_amount' => round($tariff->payable_amount * $qty, 2),
        'claims_amount'  => round($tariff->claims_amount * $qty, 2),
        'coverage_mode'  => $tariff->coverage_mode,
        'per_unit_payable' => $tariff->payable_amount,
        'per_unit_claims'  => $tariff->claims_amount,
    ];
}
```

### Backend: Enrich Unbilled Items in Controllers

Each controller's data-loading method gets updated to include tariff preview data for status=1 items.

#### Pharmacy — `prescBillList()`

Add new columns to the DataTables response:

```php
->addColumn('tariff_preview', function($item) {
    if ($item->status !== 1) return null;
    return HmoHelper::previewTariff(
        $item->patient_id,
        $item->product_id,
        null,
        $item->qty ?? 1
    );
})
```

#### Lab — `getPatientRequests()`

In the `$requests->map()` callback, for status=1 items:

```php
if ($req->status == 1) {
    $req->tariff_preview = HmoHelper::previewTariff(
        $req->patient_id,
        null,
        $req->service_id,
        1
    );
}
```

#### Imaging — `getPatientRequests()`

Same as Lab (service-based):

```php
if ($req->status == 1) {
    $req->tariff_preview = HmoHelper::previewTariff(
        $req->patient_id,
        null,
        $req->service_id,
        1
    );
}
```

### Frontend: Render Preview on Cards

#### Lab & Imaging — `createRequestCard(request, section)`

Inside the `section === 'billing'` branch, after the existing "Unbilled" badge, add:

```js
// Tariff preview for unbilled items
let tariffPreviewHtml = '';
if (section === 'billing' && request.tariff_preview) {
    const tp = request.tariff_preview;
    if (tp.no_tariff) {
        tariffPreviewHtml = `
            <div class="tariff-preview small mt-1 p-2 rounded border border-warning bg-warning bg-opacity-10">
                <i class="mdi mdi-alert-outline text-warning me-1"></i>
                <span class="text-muted">No HMO tariff configured — patient may pay full price</span>
            </div>`;
    } else {
        tariffPreviewHtml = `
            <div class="tariff-preview small mt-1 p-2 rounded border border-info bg-info bg-opacity-10">
                <i class="mdi mdi-information-outline text-info me-1"></i>
                <span class="text-muted fw-semibold">HMO Estimate:</span>
                <span class="badge bg-info ms-1">${tp.coverage_mode.toUpperCase()}</span>
                <span class="text-danger ms-2">Patient: ₦${Number(tp.payable_amount).toLocaleString()}</span>
                <span class="text-success ms-2">HMO: ₦${Number(tp.claims_amount).toLocaleString()}</span>
            </div>`;
    }
}
```

Then insert `${tariffPreviewHtml}` in the card template (after the price/status line, before notes).

#### Pharmacy — `renderPrescCardPharmacy(row, tabType)`

Same pattern, but reads from `row.tariff_preview` (DataTables column):

```js
let tariffPreviewHtml = '';
if (tabType === 'billing' && row.tariff_preview) {
    const tp = row.tariff_preview;
    if (tp.no_tariff) {
        tariffPreviewHtml = `
            <div class="tariff-preview small mt-1 p-2 rounded border border-warning bg-warning bg-opacity-10">
                <i class="mdi mdi-alert-outline text-warning me-1"></i>
                <span class="text-muted">No HMO tariff configured — patient may pay full price</span>
            </div>`;
    } else {
        tariffPreviewHtml = `
            <div class="tariff-preview small mt-1 p-2 rounded border border-info bg-info bg-opacity-10">
                <i class="mdi mdi-information-outline text-info me-1"></i>
                <span class="text-muted fw-semibold">HMO Estimate:</span>
                <span class="badge bg-info ms-1">${(tp.coverage_mode || '').toUpperCase()}</span>
                <span class="text-danger ms-2">Patient: ₦${formatMoneyPharmacy(tp.payable_amount)}</span>
                <span class="text-success ms-2">HMO: ₦${formatMoneyPharmacy(tp.claims_amount)}</span>
            </div>`;
    }
}
```

---

## Performance Considerations

### Problem: N+1 Queries

Each unbilled item would trigger a separate `HmoTariff::where(...)` query.

### Solution: Batch-load tariffs

For Lab/Imaging (JSON response), load all tariffs for the patient's HMO in one query:

```php
// In getPatientRequests(), before the map:
$tariffMap = [];
if ($patient->hmo_id) {
    $serviceIds = $requests->where('status', 1)->pluck('service_id')->unique()->filter();
    $tariffs = HmoTariff::where('hmo_id', $patient->hmo_id)
        ->whereIn('service_id', $serviceIds)
        ->get()
        ->keyBy('service_id');
    $tariffMap = $tariffs;
}

// Then inside map(), for status=1:
$req->tariff_preview = isset($tariffMap[$req->service_id])
    ? [
        'payable_amount' => round($tariffMap[$req->service_id]->payable_amount, 2),
        'claims_amount'  => round($tariffMap[$req->service_id]->claims_amount, 2),
        'coverage_mode'  => $tariffMap[$req->service_id]->coverage_mode,
    ]
    : ($patient->hmo_id ? ['no_tariff' => true] : null);
```

For Pharmacy (DataTables), batch-load product tariffs similarly:

```php
// Before DataTables::of()
$tariffMap = [];
$patient = Patient::find($patientId);
if ($patient && $patient->hmo_id) {
    $productIds = $items->where('status', 1)->pluck('product_id')->unique()->filter();
    $tariffs = HmoTariff::where('hmo_id', $patient->hmo_id)
        ->whereIn('product_id', $productIds)
        ->get()
        ->keyBy('product_id');
    $tariffMap = $tariffs;
}
```

This results in **1 extra query per workbench load** instead of N queries.

---

## Files to Modify

| File | Change |
|------|--------|
| `app/Helpers/HmoHelper.php` | Add `previewTariff()` static method |
| `app/Http/Controllers/PharmacyWorkbenchController.php` | Batch-load tariffs in `prescBillList()`, add `tariff_preview` column |
| `app/Http/Controllers/LabWorkbenchController.php` | Batch-load tariffs in `getPatientRequests()`, add `tariff_preview` to status=1 items |
| `app/Http/Controllers/ImagingWorkbenchController.php` | Same as Lab |
| `resources/views/admin/pharmacy/workbench.blade.php` | Add tariff preview rendering in `renderPrescCardPharmacy()` for `billing` tab |
| `resources/views/admin/lab/workbench.blade.php` | Add tariff preview rendering in `createRequestCard()` for `billing` section |
| `resources/views/admin/imaging/workbench.blade.php` | Add tariff preview rendering in `createRequestCard()` for `billing` section |

---

## Edge Cases

| Case | Handling |
|------|----------|
| Non-HMO (cash/private) patient | `previewTariff` returns null → no preview shown |
| HMO patient, tariff exists | Show blue estimate bar with amounts |
| HMO patient, no tariff configured | Show yellow warning bar |
| Qty changes (pharmacy "Adjust Qty") | Tariff preview uses the current qty; if qty is adjusted, the preview won't auto-update until next reload (acceptable for estimate) |
| Bundled procedure items | Show preview for the parent procedure's tariff, not individual items (matches billing behavior) |
| Item already billed (status > 1) | No preview shown — uses actual POSR data as today |

---

## Summary

- **3 controllers** get a batch tariff lookup added to their billing data methods
- **3 blade views** get a small rendering block in their card functions (billing section only)
- **1 helper method** added for reusability
- **0 new routes** — data piggybacks on existing endpoints
- **1 extra DB query** per patient load — negligible performance impact
- Clear visual distinction between "estimate" (light blue border) and "confirmed" (solid bg-light block)
