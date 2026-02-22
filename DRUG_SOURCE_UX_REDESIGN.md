# Drug Source UX Redesign — Complete Technical Plan

**Date:** 2026-02-22  
**Version:** 3.0 (consolidated)  
**Scope:** Medication chart enrichment, drug-source architecture correction, injection panel fix, POSR safety, ward stock billing

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Problems](#2-problems)
3. [Design Principles](#3-design-principles)
4. [POSR Safety Analysis](#4-posr-safety-analysis)
5. [Architecture Decision — Three Paths](#5-architecture-decision--three-paths)
6. [Medication Chart Redesign](#6-medication-chart-redesign)
7. [Injection Panel Fix](#7-injection-panel-fix)
8. [Implementation Phases](#8-implementation-phases)
9. [Change Summary](#9-change-summary)
10. [Approval Checklist](#10-approval-checklist)

---

## 1. Executive Summary

The nurse drug-source feature (pharmacy dispensed, patient's own, ward stock) has three architectural problems: the drug source toggle is inside the administer modal (wrong level), the medication dropdown gives nurses zero visibility into prescription status, and the injection panel is broken for patient-owned drugs. This plan corrects all three while protecting the system's central billing model (`ProductOrServiceRequest`) from contamination.

**Key architectural decisions:**
- Drug source is a **per-medication** decision, not a per-administration slot decision
- **Patient's Own** drugs never touch the billing pipeline — no POSR, no ProductRequest
- **Ward Stock** drugs give the nurse a **"Bill Patient" checkbox** — checked creates a real POSR; unchecked absorbs cost silently
- The medication dropdown becomes a rich, status-aware Select2 showing billing/dispensing state, quantities, and prescribing doctor

---

## 2. Problems

### 2.1 Medication Chart — Bare Drug Dropdown

The `#drug-select` dropdown shows only `Product Name - Code`. Nurses cannot see:
- Whether the drug has been **billed / paid / dispensed**
- How many units were prescribed vs already administered
- Which doctor prescribed it
- Whether the drug is even chartable yet

### 2.2 Medication Chart — Drug Source Toggle in the Wrong Place

The three-source toggle (Pharmacy Dispensed / Patient's Own / Ward Stock) lives **inside the Administer modal**. The nurse picks the source at the moment of charting a single schedule slot. This is architecturally wrong:

| Why it's wrong | Correct model |
|----------------|---------------|
| Source is per-slot; it should be per-medication | Source is decided once when the medication entry is created |
| Dispensed drugs inherently *are* pharmacy_dispensed — no toggle needed | Pharmacy items auto-set their source; no nurse decision required |
| Patient's Own and Ward Stock are **alternative medication entries**, not alternative sourcing of the same prescription | Each source type has its own entry flow with its own modal |

### 2.3 Injection Panel — Patient's Own Is Broken

In `workbench.blade.php`, the `setInjectionDrugSource()` function at ~L12518:

```js
if (source === 'pharmacy_dispensed') {
    $('.inj-non-pharmacy').hide();
} else {
    $('.inj-non-pharmacy').show();  // Bug: shows Step 2 for patient_own too
}
```

**Result:** When "Patient's Own" is selected, Step 2 (Search Hospital Products) remains visible and required. The submit handler at ~L13265 demands `products.length > 0` from the selected-products table, which can only be filled via hospital product search. The nurse is completely blocked.

---

## 3. Design Principles

| # | Principle | Rule |
|---|-----------|------|
| 1 | **Source at entry level** | Drug source is chosen once when the medication entry is created, not per administration slot |
| 2 | **Dispensed = chartable** | Only `status=3` (dispensed) prescriptions can be administered from the pharmacy source |
| 3 | **All items visible** | The dropdown shows ALL prescribed items with their status; non-dispensed items are visible but disabled |
| 4 | **Ward & Patient's Own = direct entries** | These are immediate administrations — no schedule, no calendar — they bypass the prescription pipeline |
| 5 | **No phantom POSRs** | Non-billable entries must never create ProductOrServiceRequest records (see §4 for full analysis) |
| 6 | **Billing is explicit** | Ward stock creates a POSR only when the nurse explicitly checks "Bill Patient" |
| 7 | **No inventory action for patient's own** | Patient's Own skips product search, stock deduction, and billing entirely |
| 8 | **DB integrity** | `drug_source` is always set correctly; `product_request_id` is populated for pharmacy_dispensed and billed ward stock; null for all others |

---

## 4. POSR Safety Analysis

This section explains *why* we cannot create POSR records for non-billable entries. It is the foundation of the architectural decisions in §5.

### 4.1 What Is POSR?

`ProductOrServiceRequest` (POSR) is the **central billing and revenue model** across the entire system. Every financial workflow — billing queues, payment processing, outstanding balances, dashboards, HMO claims, revenue reports, aging buckets — queries this table. None of these queries have a "non-billable" filter.

### 4.2 Observers That Fire on POSR

| Trigger | Source | Effect | Risk |
|---------|--------|--------|------|
| Any create/update/delete | `Auditable` trait | Writes audit trail | **Harmless** |
| `updated` → `validation_status = 'approved'` | `ProductOrServiceRequestObserver` | Creates HMO revenue journal entry (DR: AR-HMO, CR: Revenue) | **DANGEROUS** if non-billable |
| `updated` → `validation_status = 'rejected'` | Same observer | Reverses HMO journal entry | **Unwanted** if non-billable |

### 4.3 The 18 Queries That Would Break

If we created POSR records for patient's own or unbilled ward stock, these queries would silently produce wrong numbers:

| # | Location | What It Shows | How It Breaks |
|---|----------|---------------|---------------|
| 1 | `BillingWorkbenchController L87` | Payment queue | Non-billable items appear as "unpaid bills" |
| 2 | `BillingWorkbenchController L164–180` | Queue counts (unpaid/HMO/credit) | Dashboard counts inflated |
| 3 | `BillingWorkbenchController L230` | Patient billing data | Cashier sees unbillable line items |
| 4 | `BillingWorkbenchController L597` | Outstanding total | Balance inflated |
| 5 | `BillingWorkbenchController L1803` | Admission bill total | Includes phantom charges |
| 6 | `BillingWorkbenchController L1833` | Admission bill detail | Lists non-billable items |
| 7 | `AccountsDashboardService L25` | Outstanding amount | Financial summary wrong |
| 8 | `AccountsDashboardService L104–116` | Aging buckets (0-30/30-60/60-90/90+) | All buckets inflated |
| 9 | `AccountsDashboardService L185–246` | Collection rate & KPIs | Collection rate distorted |
| 10 | `BillingDashboardService L19` | "All Unpaid" count | Billing dashboard wrong |
| 11 | `BillingDashboardService L150` | Outstanding balance insight | Wrong figure |
| 12 | `HomeController L193` | "Payment requests today" | Count inflated |
| 13 | `HomeController L208` | "Pending payments" | Massively inflated |
| 14 | `PharmacyWorkbenchController L1738+` | Revenue statistics | Non-billable drugs in revenue |
| 15 | `PharmacyWorkbenchController L1859+` | Revenue report | Revenue includes free items |
| 16 | `PharmacyWorkbenchController L1984+` | Pharmacist performance | Wrong attribution |
| 17 | `PharmacyDashboardService L25–36` | Pharmacy queue counts | Inflated counts |
| 18 | `PharmacyWorkbenchController L92, 163` | Prescription bill/dispense lists | Non-pharmacy items in pharmacy queue |

### 4.4 HMO Queries (Medium Risk)

HMO queries filter by `hmo_id`, `coverage_mode`, or `validation_status`. They would only break if a non-billable POSR accidentally had these fields set. Risk is contained but non-zero — another reason to avoid creating POSRs for non-billable entries entirely.

### 4.5 Observer Safety Summary

| Model | Observers | Safe to Write? |
|-------|-----------|----------------|
| `MedicationAdministration` | Auditable only | **Yes** — always safe |
| `InjectionAdministration` | Auditable + SoftDeletes | **Yes** — always safe |
| `ProductRequest` | Auditable + SoftDeletes | **Yes** — no dangerous observers |
| `ProductOrServiceRequest` | Auditable + `ProductOrServiceRequestObserver` (HMO JEs) | **Only for real billable charges** |
| `StockBatch` | `StockBatchObserver` (syncs `store_stocks`) | **Yes** — desired for ward stock deductions |
| `Product` | `ProductObserver` (HMO tariffs on create) | **N/A** — we never create products |

---

## 5. Architecture Decision — Three Paths

Based on the safety analysis in §4, the system supports three distinct drug-source paths. Each path has clearly defined rules about which database records it creates and which it avoids.

### 5.1 Path Overview

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        PHARMACY DISPENSED                               │
│  Doctor prescribes → ProductRequest → POSR → Billing → Payment         │
│  → Pharmacy dispenses (status=3)                                       │
│  → Nurse sees in dropdown (🟢 Dispensed) → charts on schedule          │
│                                                                         │
│  Records: ProductRequest ✓ | POSR ✓ | MedicationAdministration ✓       │
│  Billing: Full pipeline (bill → pay → dispense → administer)           │
│  Stock: Pharmacy deducts on dispense (existing flow)                   │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                         PATIENT'S OWN                                   │
│  Nurse clicks [+ Patient's Own] → fills drug name, qty, batch, expiry  │
│  → MedicationAdministration created with drug_source='patient_own'     │
│  → Appears in chart history with 🟣 "Patient's Own" badge              │
│                                                                         │
│  Records: MedicationAdministration ✓ | ProductRequest ✗ | POSR ✗       │
│  Billing: NONE — patient brought the drug, nothing to bill             │
│  Stock: NONE — not a hospital inventory item                           │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                    WARD STOCK — UNBILLED                                │
│  Nurse clicks [+ Ward Stock] → selects store + product                 │
│  → [ ] Bill Patient checkbox UNCHECKED                                 │
│  → StockService deducts stock from selected store/batch                │
│  → MedicationAdministration created with drug_source='ward_stock'      │
│  → Appears in chart history with 🔵 "Ward Stock" badge                 │
│                                                                         │
│  Records: MedicationAdministration ✓ | ProductRequest ✗ | POSR ✗       │
│  Billing: NONE — hospital absorbs cost (saline flushes, consumables)   │
│  Stock: Deducted via StockService ✓                                    │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                    WARD STOCK — BILLED                                  │
│  Nurse clicks [+ Ward Stock] → selects store + product                 │
│  → [✓] Bill Patient checkbox CHECKED                                   │
│  → StockService deducts stock from selected store/batch                │
│  → ProductRequest created (status=1, qty, product_id, patient_id)      │
│  → POSR created (product_id, payable_amount from price, user_id, qty)  │
│  → MedicationAdministration created with product_request_id            │
│  → Item enters billing queue → cashier processes normally              │
│  → Appears in chart history with 🔵 "Ward Stock (Billed)" badge       │
│                                                                         │
│  Records: MedicationAdministration ✓ | ProductRequest ✓ | POSR ✓       │
│  Billing: Full pipeline — this IS a legitimate charge                  │
│  Stock: Deducted via StockService ✓                                    │
└─────────────────────────────────────────────────────────────────────────┘
```

### 5.2 Why Billed Ward Stock Is Safe

The POSR created for billed ward stock is a **real, legitimate charge** — exactly like a doctor-prescribed drug. It has:

- A real `product_id` (hospital inventory item with a defined price)
- A real `payable_amount` (derived from product price × quantity)
- A real `user_id` and `patient_id`
- It **should** appear in billing queues, revenue reports, and HMO accruals

The danger identified in §4.3 only applies to **non-billable phantom POSRs** — which the unbilled ward stock and patient's own paths now correctly avoid.

### 5.3 Injection — Same Pattern

The injection panel follows the same three-path model:

| Path | Creates POSR? | Creates ProductRequest? | Stock Deduction? |
|------|---------------|------------------------|------------------|
| Pharmacy Dispensed | Already exists (doctor prescribed) | Already exists | Pharmacy handled |
| Patient's Own | **No** | **No** | **No** |
| Ward Stock (unbilled) | **No** | **No** | **Yes** |
| Ward Stock (billed) | **Yes** | **Yes** | **Yes** |

---

## 6. Medication Chart Redesign

### 6.1 Enriched Drug Dropdown

**Current:** Plain `<select>` showing `"Paracetamol 500mg - PARA500"`

**New:** Rich Select2 dropdown with formatted, status-aware options:

```
┌──────────────────────────────────────────────────────────────────┐
│ 🟢 Paracetamol 500mg (PARA500)                                  │
│    Qty: 10 │ Dispensed │ Administered: 3/10 │ Dr. Smith          │
├──────────────────────────────────────────────────────────────────┤
│ 🟡 Amoxicillin 250mg (AMOX250)                                  │
│    Qty: 20 │ Awaiting Pharmacy │ Paid                            │
├──────────────────────────────────────────────────────────────────┤
│ 🔴 Metformin 500mg (MET500)                                     │
│    Qty: 30 │ Awaiting Billing                                    │
├──────────────────────────────────────────────────────────────────┤
│ 🔵 [Ward Stock] Saline 0.9% — added by Nurse Jane               │
│    Administered: 2                                               │
├──────────────────────────────────────────────────────────────────┤
│ 🟣 [Patient's Own] Insulin Glargine — brought by patient         │
│    Administered: 1                                               │
└──────────────────────────────────────────────────────────────────┘
```

**Data sources (merged):**

| Source | API | What it provides |
|--------|-----|------------------|
| Pharmacy prescriptions | `GET patients/{patient}/prescribed-drugs` | All prescribed items with status (1=awaiting billing, 2=paid, 3=dispensed), qty, doctor, billing info |
| Existing chart entries | `GET patients/{patient}/nurse-chart/medication` (existing index) | Ward stock and patient's own entries already administered |

**Dropdown behaviour:**

| Status | Icon | Selectable? | Action on Select |
|--------|------|------------|-----------------|
| Dispensed (status=3) | 🟢 | **Yes** | Shows calendar, scheduling, administer modal |
| Paid, awaiting pharmacy (status=2) | 🟡 | **No** — greyed out | Tooltip: "Awaiting pharmacy dispensing" |
| Awaiting billing (status=1) | 🔴 | **No** — greyed out | Tooltip: "Awaiting billing" |
| Ward Stock entry | 🔵 | **Yes** | Shows chart history, can administer again |
| Patient's Own entry | 🟣 | **Yes** | Shows chart history, can administer again |

Each option stores metadata: `product_request_id`, `drug_source`, `product_id`, `is_dispensed`, enabling the administer modal to auto-configure itself.

### 6.2 Drug Source at Medication Entry Level

**Remove** the source toggle tabs from the Administer Modal entirely.

Add a button row below the dropdown:

```html
<div class="d-flex gap-2 mt-2">
    <button class="btn btn-sm btn-outline-primary" id="btn-add-ward-stock">
        + Administer from Ward Stock
    </button>
    <button class="btn btn-sm btn-outline-secondary" id="btn-add-patient-own">
        + Administer Patient's Own Drug
    </button>
</div>
```

Each button opens its own dedicated modal for that source type. The existing administer modal is reserved for pharmacy-dispensed drugs only.

### 6.3 Patient's Own Modal

```
┌─────────────────────────────────────────────────┐
│ 💊 Administer Patient's Own Drug                │
├─────────────────────────────────────────────────┤
│ Drug Name:     [text input — free text]         │
│ Quantity:      [number input]                   │
│ Batch No:      [text input — optional]          │
│ Expiry Date:   [date picker — optional]         │
│ Source Note:    [text — e.g. "brought by wife"]  │
│ ─────────────────────────────────────────────── │
│ Dose:          [text input]                     │
│ Route:         [select: PO/IV/IM/SC/etc]        │
│ Administered At: [datetime picker]              │
│ Comment:       [textarea — optional]            │
│                                                 │
│           [Cancel]  [✓ Administer]              │
└─────────────────────────────────────────────────┘
```

**Backend:** Creates `MedicationAdministration` only. No POSR, no ProductRequest, no stock changes.

### 6.4 Ward Stock Modal

```
┌─────────────────────────────────────────────────┐
│ 🏥 Administer from Ward Stock                   │
├─────────────────────────────────────────────────┤
│ Store:         [select — ward stores only]      │
│ Product:       [select — filtered by store]     │
│                Available: 48 units              │
│ Quantity:      [number input]                   │
│ ─────────────────────────────────────────────── │
│ Dose:          [text input]                     │
│ Route:         [select: PO/IV/IM/SC/etc]        │
│ Administered At: [datetime picker]              │
│ Comment:       [textarea — optional]            │
│                                                 │
│ ☐ Bill Patient                                  │
│   (creates a billing entry for this item)       │
│                                                 │
│           [Cancel]  [✓ Administer]              │
└─────────────────────────────────────────────────┘
```

**"Bill Patient" checkbox (default: unchecked):**

| Unchecked | Checked |
|-----------|---------|
| Stock deducted via StockService | Stock deducted via StockService |
| `MedicationAdministration` created | `MedicationAdministration` created |
| No POSR, no ProductRequest | `ProductRequest` created (status=1) |
| Hospital absorbs cost | `POSR` created (payable_amount from product price) |
| Badge: "Ward Stock" | Badge: "Ward Stock (Billed)" |
| — | Item enters billing queue for cashier |

### 6.5 Simplified Administer Modal (Pharmacy Dispensed Only)

Once source is at the entry level, the administer modal for scheduled pharmacy drugs becomes clean:

```
┌─────────────────────────────────────────────────┐
│ 💉 Administer Medication                        │
├─────────────────────────────────────────────────┤
│ Drug: Paracetamol 500mg                         │
│ Source: Pharmacy Dispensed (auto-set)            │
│ Scheduled: 2026-02-22 08:00                     │
│                                                 │
│ Administered At: [datetime picker]              │
│ Dose:            [text input]                   │
│ Route:           [select]                       │
│ Comment:         [textarea]                     │
│                                                 │
│           [Cancel]  [✓ Administer]              │
└─────────────────────────────────────────────────┘
```

No source tabs. No prescription dropdown. `drug_source` and `product_request_id` are inherited from the selected dropdown item.

---

## 7. Injection Panel Fix

### 7.1 Fix `setInjectionDrugSource()` — Hide Step 2 for Patient's Own

**File:** `workbench.blade.php` ~L12518

**Current (buggy):**
```js
if (source === 'pharmacy_dispensed') {
    $('.inj-non-pharmacy').hide();
} else {
    $('.inj-non-pharmacy').show();  // ← shows Step 2 for patient_own too
}
```

**Fixed:**
```js
if (source === 'ward_stock') {
    $('.inj-non-pharmacy').show();   // Ward stock needs hospital product search
} else {
    $('.inj-non-pharmacy').hide();   // Pharmacy & Patient's Own don't need it
}
```

### 7.2 Virtual Product Row for Patient's Own

When source is `patient_own`, after the nurse fills external drug fields (name, qty, batch, expiry) and clicks "Add", insert a **virtual row** into `#injection-selected-body`:

```
| # | Drug Name (entered)  | Qty | Batch | — | — | — | Dose | ✕ |
```

This satisfies the `products.length > 0` validation at ~L13265 without requiring hospital product search. The row data is built from the `external_*` fields rather than a selected hospital product.

### 7.3 Submit Handler Fix

**File:** `workbench.blade.php` ~L13265

Modify the validation chain to differentiate by source:

```js
if (drugSource === 'patient_own') {
    // Validate: external_drug_name required, external_qty required
    // Skip: products.length check (virtual row satisfies it)
    // Skip: product_request_id (no pharmacy prescription)
    // Skip: stock validation (no hospital inventory)
} else if (drugSource === 'ward_stock') {
    // Validate: products.length > 0 (hospital product selected)
    // Validate: store, batch, qty
    // Check: bill_patient checkbox → if true, create POSR + PR
} else {
    // pharmacy_dispensed: existing validation unchanged
}
```

---

## 8. Implementation Phases

### Phase 1: Injection Quick Fix *(immediate, low risk)*

| Task | File | Lines |
|------|------|-------|
| Fix `setInjectionDrugSource()` — show Step 2 only for `ward_stock` | `workbench.blade.php` | ~L12518–12534 |
| Add virtual row insertion when patient_own "Add" is clicked | `workbench.blade.php` | ~L13200 |
| Fix submit handler — skip `products.length` for patient_own with external fields | `workbench.blade.php` | ~L13265 |

**DB impact:** None. Injection already saves to `injection_administrations` with `drug_source`.

---

### Phase 2: Medication Chart — Enriched Dropdown *(safe, read-only)*

| Task | File |
|------|------|
| Change `loadMedicationsList()` to call `prescribed-drugs` API instead of (or merged with) the current plain product list | `nurse_chart_scripts_enhanced.blade.php` |
| Format dropdown options with status badges (🟢🟡🔴), qty, doctor name, administered count | `nurse_chart_scripts_enhanced.blade.php` |
| Disable non-dispensed options with tooltip "Awaiting dispensing — cannot chart" | `nurse_chart_scripts_enhanced.blade.php` |
| Store `product_request_id`, `drug_source`, `product_id` as data attributes per option | `nurse_chart_scripts_enhanced.blade.php` |

**DB impact:** None. API already exists (`getPatientPrescribedDrugs` in `MedicationChartController`).

---

### Phase 3: Remove Source from Administer Modal *(cleanup)*

| Task | File |
|------|------|
| Remove drug source tabs HTML from `#administerModal` | `nurse_chart_medication_enhanced.blade.php` |
| Remove drug source tab JS (switching logic, source-specific field toggles) | `nurse_chart_scripts_enhanced.blade.php` |
| Auto-set `drug_source=pharmacy_dispensed` and `product_request_id` from selected dropdown item | `nurse_chart_scripts_enhanced.blade.php` |
| Simplify administer payload to: schedule_id, time, dose, route, comment, drug_source, product_request_id | `nurse_chart_scripts_enhanced.blade.php` |

**DB impact:** None. Same columns written to `medication_administrations`.

---

### Phase 4: Ward Stock & Patient's Own Direct Administration *(core feature)*

| Task | File |
|------|------|
| Add `[+ Ward Stock]` and `[+ Patient's Own]` buttons below dropdown | `nurse_chart_medication_enhanced.blade.php` |
| Build Patient's Own modal (drug name, qty, batch, expiry, source note, dose, route, time, comment) | `nurse_chart_medication_enhanced.blade.php` |
| Build Ward Stock modal (store select, product select with live stock, qty, dose, route, time, comment, ☐ Bill Patient) | `nurse_chart_medication_enhanced.blade.php` |
| JS: Patient's Own submit → POST to `administer-direct` with `drug_source=patient_own` | `nurse_chart_scripts_enhanced.blade.php` |
| JS: Ward Stock submit → POST to `administer-direct` with `drug_source=ward_stock` + `bill_patient` boolean | `nurse_chart_scripts_enhanced.blade.php` |
| JS: Store change → fetch products via AJAX; show available stock per product | `nurse_chart_scripts_enhanced.blade.php` |
| Backend: New route `POST patients/{patient}/nurse-chart/medication/administer-direct` | `nurse_chart.php` |
| Backend: New controller method `administerDirect()` with three branches: | `MedicationChartController.php` |
| — `patient_own`: validate external fields → create `MedicationAdministration` only | |
| — `ward_stock` + `bill_patient=false`: validate store/product/qty → deduct stock via StockService → create `MedicationAdministration` | |
| — `ward_stock` + `bill_patient=true`: validate store/product/qty → deduct stock → create `ProductRequest` (status=1) + `POSR` (payable_amount from product price) → create `MedicationAdministration` with `product_request_id` | |
| Show source badge in chart history ("Ward Stock", "Ward Stock (Billed)", "Patient's Own") | `nurse_chart_scripts_enhanced.blade.php` |

**DB impact:**
- Patient's Own → `medication_administrations` only (zero billing side effects)
- Ward Stock unbilled → `medication_administrations` + stock deduction (zero billing side effects)
- Ward Stock billed → `medication_administrations` + stock deduction + `product_requests` + `product_or_service_requests` (legitimate charge — enters billing pipeline correctly)

---

### Phase 5: Backend Cleanup *(hardening)*

| Task | File |
|------|------|
| Simplify existing `administer()` — it now only handles pharmacy_dispensed (scheduled charting) | `MedicationChartController.php` |
| Remove ward_stock and patient_own branching from `administer()` validation and logic | `MedicationChartController.php` |
| Remove drug source tabs HTML + JS remnants from administer modal | `nurse_chart_medication_enhanced.blade.php` / `nurse_chart_scripts_enhanced.blade.php` |
| Clean up unused validation rules (e.g. `required_if:drug_source,ward_stock` rules in `administer()`) | `MedicationChartController.php` |

---

## 9. Change Summary

| Area | Current (broken) | After Redesign |
|------|------------------|----------------|
| **Medication dropdown** | Plain text: `"Drug - Code"` | Rich Select2: status badge, qty, doctor, dispensed indicator, administered count |
| **Non-dispensed drugs** | Selectable (leads to errors) | Visible but disabled with "awaiting dispensing" tooltip |
| **Drug source toggle** | Inside administer modal (per-slot, wrong) | At medication entry level (per-medication, correct) |
| **Ward stock entry** | Tab inside administer modal | Dedicated `[+ Ward Stock]` button → modal with store/product picker + "Bill Patient" checkbox |
| **Ward stock billing** | Always creates POSR (dangerous) | Creates POSR **only when nurse checks "Bill Patient"** — otherwise hospital absorbs cost |
| **Patient's own entry** | Tab inside administer modal (broken) | Dedicated `[+ Patient's Own]` button → simple modal (free-text drug, no billing) |
| **Patient's own billing** | Would have created phantom POSR (dangerous) | **Never** creates POSR — patient brought the drug |
| **Administer modal** | Complex: 3 source tabs, prescription dropdown, external fields, conditional visibility | Clean: time, dose, route, comment — source is pre-determined |
| **Injection patient_own** | Step 2 (product search) shown, submit blocked | Step 2 hidden, virtual row created from external fields, submit works |
| **Financial reports** | Would be contaminated by phantom POSRs | Safe — only real charges create POSRs |

---

## 10. Approval Checklist

Before implementation begins, confirm each item:

| # | Item | Status |
|---|------|--------|
| 1 | **CRITICAL:** Patient's Own must NEVER create POSR or ProductRequest records | ☐ |
| 2 | **CRITICAL:** Ward Stock creates POSR + ProductRequest **only** when nurse checks "Bill Patient" | ☐ |
| 3 | Ward Stock "Bill Patient" checkbox default: **unchecked** (hospital absorbs cost by default) | ☐ |
| 4 | Enriched dropdown design (§6.1) — status badges, qty, doctor, disabled non-dispensed | ☐ |
| 5 | Ward Stock and Patient's Own are "direct administrations" — no schedule, no calendar | ☐ |
| 6 | Administer modal simplification (§6.5) — source tabs removed, auto-set from dropdown | ☐ |
| 7 | Injection panel fix approach (§7) — hide Step 2, virtual row, submit handler fix | ☐ |
| 8 | New `administer-direct` endpoint with three-branch logic (§8 Phase 4) | ☐ |
