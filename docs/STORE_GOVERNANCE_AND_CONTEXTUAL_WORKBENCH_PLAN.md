# Store Governance and Contextual Workbench Plan

## 1) Goal

Create a clear operational separation between Central Store, Pharmacy Stores, Department Stores, and Ward Stores while preserving the current stock engine and requiring minimal code changes.

This plan is a **governance and UX layer** that sits on top of the existing, already-working stock engine. Nothing in the engine changes. Every stock write still goes through:

- `StockService::createBatch()` (Line 163) — batch creation from any source
- `StockService::dispenseStock()` / `dispenseFromBatch()` — FIFO and specific-batch deduction
- `StockService::transferStock()` (Line 219) — inter-store transfer (called by `RequisitionService::fulfill()`)
- `StockService::syncStoreStock()` — cascade to `store_stocks` + global `stocks` (triggered automatically by `StockBatchObserver`)

This plan adds:

1. **Store role metadata** — classify each store (central, pharmacy hub, satellite, department, ward) with new columns on the `stores` table.
2. **Lane policy enforcement** — a pre-call Gate check in `StoreRequisitionController::store()` (L180) and `::approve()` (L242) to validate that the source→destination route is permitted.
3. **Context resolution** — a new `StoreContextResolver` service that resolves the correct `store_id` from the user's active shift (`NursingShift.ward_id`), department, or assigned default — replacing the current reliance on manual store selection.
4. **Role-aware workbench UX** — each clinical persona (pharmacy, nurse, ward manager, central store manager) sees a contextualised workbench with pre-filled store, filtered batches, and relevant action tabs.
5. **Stage-by-stage validation with actionable UI feedback** — every stock-affecting action has a pre-flight validation pass with blocking/warning/info states surfaced in the UI before any service call is made.
6. **Admin/configuration pages** — to govern policy lanes, store ownership, packaging governance, and context resolution rules.

## 2) Core Principle: Base Unit Is King

**Non-negotiable rule:** Base units remain the only inventory truth for stock deduction, transfers, and balances. This is already enforced in the engine — this plan makes it visible to users.

**How it already works in code:**

- `PurchaseOrderService::receiveItems()` (L245) — converts `received_packaging_qty` to base units before calling `StockService::createBatch()`. The `received_packaging_id` is stored for reference only.
- `RequisitionService::create()` — stores `packaging_id` / `packaging_qty` on `store_requisition_items` for display; the actual `quantity` column saved is always base units.
- `StoreWorkbenchController::createManualBatch()` (L430) — `quantity` field is base units; no conversion logic exists because the form already collects base units directly.
- `NursingWorkbenchController::addConsumableBill()` (L1745), `administerInjection()` (L775), `administerImmunization()` (L1185) — all pass `qty` (base units) directly to `StockService::dispenseStock()` / `dispenseFromBatch()`.
- `PharmacyWorkbenchController::dispenseMedication()` (L933) — `qty` from `ProductRequest.qty` is base units; passed directly to `StockService::dispenseStock()`.

**What this plan adds (UI only — no engine change):**

1. Packaging and alternate units are UX and operational convenience layers.
2. Every quantity entry in pack/carton/etc **must show** the computed base-unit equivalent before the user can submit.
3. All stock math, alerts, availability, and locking happen in base units (already true; this plan surfaces it visually).
4. UI must always show both:
   - Entered unit (for humans): e.g., `2 cartons`
   - Computed base unit (for system truth): e.g., `= 240 tablets`

Conversion is done client-side using a read-only endpoint backed by `ProductPackaging::toBaseUnits(float $qty)` and `ProductPackaging::fromBaseUnits(float $baseQty)` — no write path involved.

### 2.1 Unit Display Convention

Use this everywhere a quantity is displayed or entered:

- **Primary label**: user-entered packaging unit (e.g., `3 boxes`)
- **Secondary helper text** (muted, computed): `= 90 tablets (base units)` — shown as soon as the quantity field loses focus
- **Submission value**: always base units — the hidden input that is actually POSTed

Example — Purchase Order Receive Form (existing `showReceiveForm()` view, L321):

| Field | Shown to user | Value sent to `receiveItems()` |
|-------|--------------|-------------------------------|
| `received_packaging_qty` | `3 boxes` | (display only) |
| `quantity` | `= 90 tablets` | `90` → `StockService::createBatch()` |

## 3) Target Store Topology

### 3.1 Store Types

These map to the existing `store_type` enum already in the DB (migration `2026_01_21_100010`). The new `distribution_role` column (Section 4) adds governance semantics on top.

1. **Central Store** (`distribution_role = central`)
   - Receives bulk procurement via PO (`PurchaseOrderService::receiveItems()`, L245).
   - Primary upstream source — fulfills inbound requisitions from all other store classes via `RequisitionService::fulfill()`.
   - No direct patient dispense.

2. **Pharmacy Hub (Central Pharmacy)** (`distribution_role = pharmacy_hub`)
   - Main dispensing pharmacy.
   - Receives from central store via requisition.
   - Dispenses to patient via `PharmacyWorkbenchController::dispenseMedication()` (L933).
   - May resupply pharmacy satellites via requisition.

3. **Pharmacy Satellite / Dispensary** (`distribution_role = pharmacy_satellite`)
   - Point-of-care pharmacy stores.
   - Receives from pharmacy hub or central via requisition.
   - Dispenses via the same `dispenseMedication()` path as hub — `store_id` in the request payload determines which store's stock is deducted.

4. **Department Store** (`distribution_role = department`)
   - Department-bound stock (Theatre, Lab, Imaging, etc.).
   - Receives from central via requisition.
   - Consumed via `NursingWorkbenchController::addConsumableBill()` (L1745) scoped to the department's store.

5. **Ward Store** (`distribution_role = ward`)
   - Ward-bound stock for nursing operations.
   - Linked to `wards` via `stores.ward_id`.
   - Context resolved from `NursingShift.ward_id` on every nursing action.
   - Stock consumed via:
     - `NursingWorkbenchController::administerInjection()` (L775) — drug injection
     - `NursingWorkbenchController::administerImmunization()` (L1185) — vaccine administration
     - `NursingWorkbenchController::addConsumableBill()` (L1745) — ward consumables billing
     - `MaternityWorkbenchController::administerFromScheduleMaternity()` (L2244) → proxies to `NursingWorkbenchController::administerFromScheduleNew()` (L3134)

### 3.2 Manager Personas

| Persona | Primary Actions | Key Controller |
|---------|----------------|----------------|
| Central Store Manager | PO receive, fulfill all requisitions | `PurchaseOrderController`, `StoreRequisitionController` |
| Pharmacy Manager (Hub) | Dispense, fill satellite reorders | `PharmacyWorkbenchController` |
| Pharmacy Satellite Manager | Dispense, reorder to hub | `PharmacyWorkbenchController` |
| Department Store Manager | Issue to department, replenish | `StoreRequisitionController`, `NursingWorkbenchController` |
| Ward Store Manager / Charge Nurse | Administer, bill consumables, handover | `NursingWorkbenchController`, `ShiftController` |
| Maternity Nurse | Immunize, inject, ANC-linked billing | `MaternityWorkbenchController` → `NursingWorkbenchController` |

## 4) Minimal Data Model Extension

Add metadata only — the stock engine (`StockService`, `StockBatch`, `StoreStock`) is **not touched**.

New columns on `stores` table (one migration):

```php
// Migration: add_distribution_role_to_stores_table
$table->string('distribution_role')->default('other');
// central | pharmacy_hub | pharmacy_satellite | department | ward | other
$table->unsignedBigInteger('department_id')->nullable()->constrained('departments');
$table->unsignedBigInteger('ward_id')->nullable()->constrained('wards');
$table->unsignedBigInteger('parent_store_id')->nullable()->constrained('stores');
$table->boolean('allows_direct_patient_dispense')->default(false);
$table->boolean('requires_shift_context')->default(false);
```

New table for lane policy (replaces hard-coded defaults):

```php
// store_lane_policies: source_role | destination_role | allowed | requires_approval_level
```

**Existing models unchanged**: `Store`, `StockBatch`, `StoreStock`, `StoreRequisition`, `PurchaseOrder`.

Keep existing `store_type` enum for backward compatibility — it continues to drive legacy logic untouched. `distribution_role` is the new governance field.

**Where these new fields are read:**

| Field | Read By | Purpose |
|-------|---------|---------|
| `distribution_role` | `StoreContextResolver` service (new) | Determine which tabs/actions to show in workbench |
| `distribution_role` | Lane policy Gate (new) — called before `StoreRequisitionController::store()` L180 | Block disallowed source→destination pairs |
| `ward_id` | `StoreContextResolver::resolveFromShift(NursingShift $shift)` | Map shift → store automatically |
| `department_id` | `StoreContextResolver::resolveFromUser(User $user)` | Map department membership → store |
| `requires_shift_context` | Gate check before `administerInjection()`, `administerImmunization()`, `addConsumableBill()` | Block ward stock actions when no active shift |
| `allows_direct_patient_dispense` | Gate check before `dispenseMedication()` L933 | Prevent non-pharmacy stores from dispensing to patients |

## 5) Policy Lanes (Who Can Supply Whom)

Default transfer/requisition lanes enforce who is allowed to request stock from whom. These are validated **before** any write reaches `RequisitionService::create()`.

### 5.1 Default Lane Matrix

| Source Role | Can Supply To | Notes |
|------------|--------------|-------|
| `central` | `pharmacy_hub`, `pharmacy_satellite`, `department`, `ward` | Primary upstream for everything |
| `pharmacy_hub` | `pharmacy_satellite` | Satellite replenishment only |
| `pharmacy_hub` | `ward` | Optional — admin toggle in lane policy |
| `department` | (none by default) | Departments don't supply downstream |
| `ward` | (none by default) | Wards don't supply downstream |
| Any | `central` | **Blocked** — reverse direction requires explicit admin override |

### 5.2 Where Lane Policy Is Enforced in Code

**Step 1 — Requisition creation** (`StoreRequisitionController::store()`, L180):

```php
// New Gate check inserted before RequisitionService::create()
Gate::authorize('requisition-lane-allowed', [
    $sourceStore->distribution_role,
    $destinationStore->distribution_role,
]);
// If denied → 403 with human-readable reason, no service call is made
```

The `RequisitionService::create()` method itself is **unchanged** — it trusts that the controller has already validated the lane.

**Step 2 — Approval** (`StoreRequisitionController::approve()`, L242):

```php
// New Gate check: approver must be the designated manager of the SOURCE store
Gate::authorize('can-approve-requisition-for-store', $sourceStore);
```

`RequisitionService::approve()` is **unchanged**.

**Step 3 — Fulfillment** (`StoreRequisitionController::fulfill()`, L321):

No lane check at fulfill time (lane was validated at create). The multi-batch payload `[item_id => ['batches' => [batch_id => qty]]]` is submitted unchanged to `RequisitionService::fulfill()`, which calls `StockService::transferStock()`. If a non-FIFO batch order is detected (batch creation date newer than available older batch), a client-side warning toast is shown — **no server-side block**, override is logged.

### 5.3 Lane Policy Storage

Lanes are stored in the new `store_lane_policies` table (Section 4) and cached. The admin config page (Section 9.1) provides the UI to manage them. A "Test Lane" panel lets admins simulate a source→destination pair before saving.

## 6) Contextual Workbench by Role

## 6.1 Shared Workbench Shell

The existing `StoreWorkbenchController` remains the shell. Context-dependent rendering is added without replacing it. `distribution_role` on the resolved store drives which tab groups and action buttons are rendered.

**Shared header block (all roles):**

1. **Active Store Context** — resolved by `StoreContextResolver` (Section 10); locked or selectable depending on `store-context.change-manual` permission.
2. **Role badge** — derived from `$store->distribution_role`; shows "Central Store", "Pharmacy Hub", "Ward Store", etc.
3. **Policy hints** — lane summary text: "This store can supply: Pharmacy Hub, Ward Stores"
4. **Today metrics cards** — sourced from `StoreStock` + `StockBatch` for the resolved store only; no global stock queries.
5. **Quick actions** — role-specific buttons (see each persona below).

**Context resolution on page load:**

```php
// StoreWorkbenchController::index() — augmented (additive)
$store = app(StoreContextResolver::class)->resolve(auth()->user());
// If unresolved → redirect to "Resolve Store Context" CTA
```

## 6.2 Central Store Manager Workbench

**Context auto-resolution:** User has `distribution_role = central` store as default assignment. No shift required.

**Primary tabs:**

1. **Bulk Receiving (PO Receive)**
   - Loads approved POs via `PurchaseOrderService::getReadyToReceive()` (L366).
   - Receive form via `PurchaseOrderController::showReceiveForm()` (L321) — view augmented with dual-unit labels per line item.
   - On submit: `PurchaseOrderController::receive()` (L336) → `PurchaseOrderService::receiveItems()` (L245) → `StockService::createBatch()` with `SOURCE_PURCHASE_ORDER`. **Unchanged.**
   - UI addition: `received_packaging_qty × packaging_name = X base units` shown before submit; blocking error if `ProductPackaging` conversion factor is missing.

2. **Outbound Requisitions Queue**
   - Loads `RequisitionService::getPendingFulfillment(storeId)` (L369).
   - Fulfill form → `StoreRequisitionController::fulfill()` (L321) → `RequisitionService::fulfill()` → `StockService::transferStock()`. **Unchanged.**
   - UI addition: batch table with FIFO-suggested pick order, expiry dates, packaging-unit display. Non-FIFO selection triggers amber override warning; reason captured and logged.
   - Lane compliance badge per requisition: `Allowed`, `Blocked by Policy`.

3. **Allocation Planner** — read-only `StoreStock` view per downstream store; "Create Requisition" shortcut pre-fills source as this central store with suggested qty.

4. **Expiry and Slow-Moving** — `StockBatch` where `expiry_date < now() + 90 days` and `current_qty > 0` for this store; transfer-out links to requisition creation with the expiring batch pre-selected.

5. **Inter-store Fill Rate Reports** — `store_requisition_items` + `stock_batch_transactions` filtered by `store_id`; fill rate, lead time, and batch source breakdown.

## 6.3 Pharmacy Hub Manager Workbench

**Context auto-resolution:** User's assigned `pharmacy_hub` store. `allows_direct_patient_dispense = true` gate enforced.

**Primary tabs:**

1. **Dispensing Queue**
   - `PharmacyWorkbenchController::getPrescriptionQueue()` (L439) + `getQueueCounts()` (L522) — filtered to this pharmacy's `store_id`.
   - Patient panel via `getPatientPrescriptionData($patientId)` (L587).
   - Pre-dispense stock check via `validateCartStock()` (L809) — `StoreStock` strict check, no global fallback.
   - **Dispense action** — `dispenseMedication()` (L933):
     - Phase 1: all items validated (status=2 Billed, HMO delivery check via `HmoHelper::canDeliverService()`, strict `StoreStock` check)
     - Phase 2 (all pass): `StockService::dispenseStock()` FIFO, or legacy direct `store_stock` decrement if no batches exist
     - Sets `ProductRequest.status = 3`, `dispensed_by`, `dispense_date`, `dispensed_from_store_id`
   - **Readiness chip per item**: `Ready` (green) / `Billing Pending` (amber) / `HMO Blocked` (red) / `Stock Short` (red)
   - **UI additions**: store badge locked; batch expiry banner if any active batch expires within 30 days; dual-unit qty on dispense receipt.
   - **Gate added (additive)**: `Gate::authorize('dispense-from-store', $store)` — passes only if `allows_direct_patient_dispense = true` and `distribution_role` in (`pharmacy_hub`, `pharmacy_satellite`).

2. **Inbound from Central** — `RequisitionService::getMyRequisitions(storeId)` (L398); status timeline: Pending → Approved → Fulfilled → Received.

3. **Satellite Replenishment** — lists linked satellites (`stores.parent_store_id = this store`); quick requisition creation pre-validated by lane policy (hub → satellite allowed by default).

4. **Controlled Drug Watchlist** — `StockBatch` for `controlled_substance = true` products; each dispense event shows `dispensed_by` + `dispensed_from_store_id` for audit.

5. **Daily Throughput** — `getMyTransactions()` (L1124) scoped to this store.

## 6.4 Pharmacy Satellite / Dispensary Manager Workbench

**Context auto-resolution:** User's assigned `pharmacy_satellite` store. Same `dispenseMedication()` path as hub — `store_id` in the payload determines which store's stock is deducted. No code change to the controller or service.

**Primary tabs:**

1. **Local Dispensing Queue** — same as 6.3 Tab 1 with this satellite's `store_id`. Store context locked unless `store-context.change-manual` permission. Stockout hard-stop: "Insufficient stock — request replenishment from hub" CTA with one-click reorder.

2. **Reorder to Hub / Central** — source = `stores.parent_store_id`, destination = this satellite; `StoreRequisitionController::store()` (L180); lane check (hub → satellite) passes; packaging-unit preview on each line item.

3. **Near-Expiry Transfer Suggestions** — `StockBatch` expiring within 60 days; "Transfer back to hub" creates a reverse requisition; requires `store-policy.override-lane` permission.

4. **Shift Dispense Summary** — `getMyTransactions()` scoped to today and this store.

## 6.5 Department Store Manager Workbench

**Context auto-resolution:** `stores.department_id` matched to authenticated user's `department_id`.

**Primary tabs:**

1. **Department Requests**
   - Issues consumables via `NursingWorkbenchController::addConsumableBill()` (L1745) with `store_id` = this department store.
   - Stock deducted via `StockService::dispenseFromBatch()` (if `batch_id` provided) or FIFO `dispenseStock()`. **Unchanged.**
   - `ProductOrServiceRequest` created with HMO tariff via `HmoHelper::applyHmoTariff()`. **Unchanged.**
   - **Gate added (additive)**: `Gate::authorize('bill-consumable-from-store', $store)` — passes if `distribution_role = 'department'` and user belongs to this department.

2. **Procedure/Service Consumption** — bulk issue: a set of `addConsumableBill()` calls for all consumables in a procedure template; each deducts from this department store via the existing `StockService` path.

3. **Replenishment Queue** — `RequisitionService::getMyRequisitions(storeId)` for this department store; quick replenish to central pre-fills source = central, destination = this store.

4. **Cost and Variance** — `stock_batch_transactions` for this `store_id`; daily/weekly consumption trend.

## 6.6 Ward Store Manager / Charge Nurse Workbench

**Context auto-resolution:** `NursingShift.ward_id` → `stores.ward_id`. Shift must be active if `stores.requires_shift_context = true`. Resolved by `StoreContextResolver::resolveFromShift(NursingShift $shift)`.

**Primary tabs:**

1. **Shift Consumption — Drug Injection**

   `NursingWorkbenchController::administerInjection()` (L775). All drug-source paths **unchanged**:

   | Path | `drug_source` | Stock Deducted | Billing |
   |------|--------------|----------------|---------|
   | 1 | `pharmacy_dispensed` | No | No (already billed at pharmacy) |
   | 2 | `patient_own` | No | No (external drug documented only) |
   | 3A | `ward_stock`, `bill_patient=false` | `dispenseFromBatch()` or FIFO `dispenseStock()` | No |
   | 3B | `ward_stock`, `bill_patient=true` | Same as 3A | `ProductRequest` (status=2) + POSR via `applyTariffToRequest()` |

   - **UI additions**: `store_id` pre-filled from active shift's ward store; batch selector shows expiry; non-FIFO batch order triggers amber warning; "Bill patient?" toggle with billing breakdown preview; `ShiftAction` log entry after each administration.
   - **Gate added (additive)**: `Gate::authorize('administer-from-store', $store)` — passes if `distribution_role` in (`ward`, `other`) and shift active when `requires_shift_context = true`.

2. **Immunization Administration**

   Primary path: `NursingWorkbenchController::administerFromScheduleNew()` (L3134).
   Direct path: `administerImmunization()` (L1185).

   Flow **unchanged**: `StockService::getAvailableStock()` → `ProductOrServiceRequest` (billing + HMO tariff) → `dispenseFromBatch()` or FIFO `dispenseStock()` → `ImmunizationRecord`.

   - **UI additions**: `store_id` pre-filled from ward store; vaccine batch list sorted FEFO (nearest-expiry first, UI sort only — `dispenseFromBatch()` uses user-confirmed `batch_id`); cold-chain red badge for batches expiring within 7 days; schedule status auto-updated post-administration.

3. **Patient-linked Consumable Billing**

   `NursingWorkbenchController::addConsumableBill()` (L1745). Flow **unchanged**: `getAvailableStock()` check → POSR with HMO tariff via `HmoHelper::applyHmoTariff()` → `dispenseFromBatch()` or FIFO → optional `ProductRequest` if `is_medication = true`.

   - **UI additions**: `store_id` pre-filled; product search filtered to ward-allowed categories; packaging-unit helper text on qty input; `ShiftAction` log entry post-bill for handover visibility.

4. **Ward Replenishment** — `StoreRequisitionController::store()` (L180); lane check (central → ward, pharmacy_hub → ward) passes by default; packaging-unit display on all line items.

5. **Handover Stock Snapshot**

   `NursingWorkbenchController::getShiftSummary()` (L2613) + `generateHandoverReport()` (L2764) — extended to include:
   - Opening balance (snapshot at shift start from `StoreStock`)
   - Consumed during shift (injections + immunizations + consumable bills from `ShiftAction` log entries)
   - Closing balance (current `StoreStock`)
   - Variance with mandatory reason if negative

## 6.7 Maternity Nurse Workbench

**Context auto-resolution:** Maternity ward's linked store from `NursingShift.ward_id` — same as Section 6.6.

The maternity workbench **does not call `StockService` directly**. All stock-affecting actions proxy to `NursingWorkbenchController` via `$this->nursingProxy()`:

| Maternity Method | Line | Proxies To | Stock Engine Call |
|-----------------|------|------------|-------------------|
| `administerFromScheduleMaternity()` | L2244 | `administerFromScheduleNew()` L3134 | `dispenseFromBatch()` / `dispenseStock()` |
| `administerFromSchedule($request, $babyId)` | L2380 | Same as above | Same |
| `getImmunizationHistoryByPatient()` | L2239 | `getImmunizationHistory()` | Read-only |

**UI additions** (display-only, no proxy changes):

- Maternity sends resolved ward `store_id` to proxy endpoints — already accepted by `administerFromScheduleNew()`.
- After immunization: maternity ANC timeline badge links `ImmunizationRecord` to current ANC visit.
- Mother vs baby clearly labelled in confirmation modal.
- FEFO batch sort for vaccines (same as Section 6.6, Tab 2).


## 7) Stage-by-Stage Validation Rules

Every stage has two layers: (1) a **server-side Gate/Policy check** that wraps the controller method before the service is called, and (2) a **client-side preflight** that mirrors the same rules and surfaces errors in the UI before the form is submitted. The services themselves are not modified.

## 7.1 Requisition Creation

**Code touch point**: `StoreRequisitionController::store()` (L180)
**Service called after passing validation**: `RequisitionService::create()` (unchanged)

**Server-side validation (pre-call Gate):**

1. Source store ≠ destination store (hard block).
2. Lane policy allows source_role → destination_role (check `store_lane_policies` table; 403 with reason if denied).
3. Authenticated user has permission for the destination store's class (`store-governance.manage` or role-specific Gate).
4. Each item: `quantity > 0` in base units.
5. If `packaging_id` is set: `ProductPackaging::toBaseUnits($packaging_qty)` must return a whole-number or within decimal allowance for this product class; blocking if conversion fails.

**UI feedback:**

- Inline error per line item with exact rule that failed.
- Lane-policy banner above the form with "Route blocked: Central Store → Ward Store (direct) is not allowed. Request from Pharmacy Hub instead." + direct link to create a hub-sourced requisition.
- Base-unit preview appears as soon as packaging qty is entered (client-side calculation using `ProductPackaging` conversion endpoint).
- Submit button disabled until all items pass preflight.

## 7.2 Requisition Approval

**Code touch point**: `StoreRequisitionController::approve()` (L242)
**Service called after passing validation**: `RequisitionService::approve()` (unchanged)

**Server-side validation (pre-call Gate):**

1. Authenticated user is the designated manager of the **source** store (`store-governance.manage` permission scoped to `$sourceStore->id`).
2. Approved qty per item ≤ requested qty (hard block; over-approval requires `store-policy.over-receive` permission).
3. Source store has sufficient stock to cover the approved qty (check `StoreStock.current_quantity`; if not, amber warning — approval is not blocked but fulfillment will fail if not replenished in time).

**UI feedback:**

- Approval matrix card: "Approver must be manager of [Source Store Name]". Currently logged-in user shown with pass/fail indicator.
- Per-item: approved qty input with max = requested qty; hard cap enforced client-side.
- Stock adequacy indicator per item: green if `current_quantity >= approved_qty`, amber if `< approved_qty` with "Stock may be insufficient at fulfillment time".
- Warning banner if approving below the source store's configured reorder threshold.

## 7.3 Fulfillment / Transfer

**Code touch point**: `StoreRequisitionController::fulfill()` (L321)
**Batch availability data**: `StoreRequisitionController::getAvailableBatches()` (L390) → `StockService::getAvailableBatches()`
**Service called after passing validation**: `RequisitionService::fulfill()` → `StockService::transferStock()` (both unchanged)

**Server-side validation (pre-call Gate):**

1. Each selected batch belongs to the source store (`stock_batches.store_id = source_store_id`; hard block if mismatch).
2. Sum of batch quantities for each item ≥ approved quantity (hard block on negative projected balance; `StockService::transferStock()` will also catch this, but the UI blocks first).
3. If non-FIFO order detected (a batch with a later `created_at` selected before an older one with remaining qty): override reason must be captured in the payload; logged as a `ShiftAction` / audit entry.
4. Packaging conversion integrity: `quantity_to_transfer` (base units) must match sum of `batches[batch_id] × 1` (batches are always in base units in `StockBatch.current_qty`).

**UI feedback:**

- Batch table per item with columns: `Batch No.`, `Expiry`, `Available`, `To Pick`, `Remaining After`.
- Hard block displayed inline if any item's "Remaining After" would go negative.
- FIFO suggestion highlighted (oldest batch first by `created_at`). Deviation shows amber warning; reason field required before the submit button re-enables.
- Expiry warning: if a batch expiring within 30 days is being bypassed for a newer batch, red "FEFO violation" badge.
- Packaging-unit column shows `qty to transfer` in both base units and the product's primary packaging.

## 7.4 PO Receiving at Central Store

**Code touch point**: `PurchaseOrderController::receive()` (L336)
**Form loaded by**: `PurchaseOrderController::showReceiveForm()` (L321) — already eager-loads `items.product.packagings` and `items.packaging`
**Service called after passing validation**: `PurchaseOrderService::receiveItems()` (L245) → `StockService::createBatch()` with `SOURCE_PURCHASE_ORDER` (unchanged)

**Server-side validation (pre-call):**

1. PO status is `approved` (already enforced by `showReceiveForm()`; hard block otherwise).
2. Each item: `received_qty > 0` and `received_qty ≤ ordered_qty × (1 + over-receive-tolerance%)`; requires `store-policy.over-receive` permission to exceed.
3. If `received_packaging_id` is set: `ProductPackaging` record for this product must exist and have a non-zero `conversion_factor`; blocking error if missing.
4. `batch_number` uniqueness: not already used in an active batch for this `product_id` + `store_id` (already enforced in existing code; surface the error clearly).
5. `expiry_date` ≥ today; warn if < 90 days from now.

**UI feedback:**

- Each line item in the receive form shows: `Ordered (pkg)` | `Ordered (base)` | `Receiving (pkg input)` | `= X base units` (computed live).
- Variance badge if received qty ≠ ordered qty (amber = under, red = over without permission).
- "Reconcile card" at bottom: total ordered base units vs. total receiving base units; variance highlighted.
- Batch number field: real-time duplicate check via AJAX; red border + "Batch already exists in this store" if collision.
- Expiry field: amber banner if within 90 days; hard block if in the past.

## 7.5 Dispensing / Consumption (All Clinical Paths)

This stage covers four distinct entry points. Each has its own pre-flight validation sequence. **All service calls are unchanged** — the validation runs before them.

### 7.5.1 Pharmacy Dispense — `dispenseMedication()` (L933)

**Pre-flight (Phase 1, already in code — UI enhancements added):**

1. Each `ProductRequest`: status must be `2` (Billed) — hard block with "Item is [status]: must be Billed before dispensing".
2. HMO delivery check via `HmoHelper::canDeliverService()` or `canDeliverBundledItem()` — hard block with reason ("HMO validation required", "Procedure not yet paid").
3. Strict `StoreStock.current_quantity ≥ qty` check — hard block with "Insufficient stock in [Store Name]: need X, available Y. Click to request replenishment."
4. Store context Gate: `allows_direct_patient_dispense = true` and `distribution_role` in (`pharmacy_hub`, `pharmacy_satellite`).

**UI readiness chip per item:**

| State | Chip | Actionable Fix |
|-------|------|----------------|
| status=2, stock OK, HMO OK | `Ready` (green) | — |
| status ≠ 2 | `Billing Pending` (amber) | Link to billing workbench |
| HMO blocked | `HMO Blocked` (red) | Link to HMO validation |
| Stock insufficient | `Stock Short` (red) | Link to replenishment requisition |

**Dispense is disabled for the entire batch if ANY item fails.** All items must be `Ready` to enable the dispense button.

### 7.5.2 Injection Administration — `administerInjection()` (L775)

**Pre-flight by drug source:**

| Check | `pharmacy_dispensed` | `patient_own` | `ward_stock` |
|-------|---------------------|--------------|-------------|
| ProductRequest status = 3 (dispensed) | Hard block | N/A | N/A |
| ProductRequest matches patient | Hard block | N/A | N/A |
| `store_id` provided | N/A | N/A | Required |
| `StoreStock ≥ qty` in ward store | N/A | N/A | Hard block |
| Shift active (if `requires_shift_context`) | N/A | N/A | Hard block |

**UI additions:**

- For `ward_stock`: batch selector with expiry visible; "Bill patient?" toggle with computed billing breakdown before confirm.
- Blocking error if ward store not resolved: "No ward store found for your current shift. Contact your supervisor."

### 7.5.3 Immunization Administration — `administerImmunization()` (L1185) / `administerFromScheduleNew()` (L3134)

**Pre-flight:**

1. `store_id` required and must be a ward/pharmacy store with `StoreStock ≥ 1` for each vaccine product.
2. Schedule item status must be `pending` or `due` (for schedule-based path); hard block if already administered.
3. If `batch_id` provided: must belong to `store_id`, be active, `current_qty > 0`.
4. `administered_at` ≤ now (no future administration dates).

**UI additions:**

- Batch list sorted FEFO (nearest expiry first) for vaccine products.
- Red badge on batches expiring within 7 days.
- For babies: dose number validated against `PatientImmunizationSchedule` (schedule-based path already does this).

### 7.5.4 Consumable Billing — `addConsumableBill()` (L1745)

**Pre-flight:**

1. `store_id` required; must belong to the user's active shift ward or department.
2. `StoreStock.current_quantity ≥ qty` for the product in the selected store (hard block with exact shortage amount).
3. If `batch_id` provided: must belong to `store_id`, be active, `current_qty ≥ qty`.
4. `qty ≥ 1` (integer, hard block on zero or negative).
5. Patient must have an active encounter (or admission) to attach the billing record.

**UI additions:**

- Qty input shows packaging-unit helper text computed from `ProductPackaging::fromBaseUnits()`.
- Stock availability badge next to product: `X units available` (green if ≥ qty, red if <).
- If `is_medication = true`: dose field shown as required; confirmation modal shows "This will also create a prescription record".


## 8) UI and UX Improvements (Cross-Cutting)

All UX improvements below are additive — no Blade view is replaced, only augmented with new components or conditional blocks based on `$store->distribution_role`.

## 8.1 Clarity and Explainability

1. **"Why blocked?" tooltip** on every disabled critical button (Dispense, Approve, Fulfill, Receive) — tooltip text comes from the same Gate check message, so the UI reason is always in sync with the server-side block.
2. **Base-unit truth label** displayed below every stock figure: `Tracked in base units (tablets / ml / pieces)` — drives from `Product.base_unit_name`.
3. **Standard helper text under quantity fields** (all forms):
   - `Entered in [packaging_name] for convenience; saved in base units.`
   - Live conversion: as the user types, the helper text updates to show `= X [base_unit_name]` using the `ProductPackaging::toBaseUnits()` read endpoint.
4. **Dispense confirmation modal** (`dispenseMedication()` path) — shows the store name, batch number(s) that will be deducted, and the base-unit quantity; user must click "Confirm Dispense" to proceed.

## 8.2 Safe Defaults

1. **Store from context** — `StoreContextResolver` runs on every workbench load; store is pre-filled in all `store_id` hidden fields. User never sees a blank store selector unless context is unresolved.
2. **FIFO batch default** — batch tables in `fulfill()` form and `administerInjection()` / `addConsumableBill()` batch pickers pre-select the oldest active batch by `created_at`. User can override; override is logged.
3. **FEFO for vaccines** — batch picker in `administerImmunization()` and maternity immunization proxy pre-selects nearest-expiry batch. User can override with `store-policy.override-fifo` permission.
4. **Source lane default** — requisition creation form pre-fills source store as the policy-default upstream for the current user's store role (e.g., ward user → source defaults to central or pharmacy hub per lane policy).

## 8.3 Progressive Disclosure

- **Basic mode** (daily users): simple forms with pre-filled defaults, no batch picker (FIFO auto-selected), no lane override option.
- **Advanced mode** (supervisors with `store-governance.manage` or `store-policy.override-*` permissions):
  - Manual batch override in fulfill and dispense (batch picker becomes multi-selectable)
  - Lane override with mandatory reason field
  - Over-receive input with tolerance field
  - Context store change dropdown

## 8.4 Visual Language

Consistent status chip/badge colors across **all** workbenches (pharmacy, nursing, maternity, store):

| Color | Meaning | Examples |
|-------|---------|---------|
| Green | Valid / ready | `Ready` chip on `ProductRequest`; stock sufficient |
| Amber | Caution / action needed | Non-FIFO warning; near-expiry batch; stock below reorder |
| Red | Blocked / error | `HMO Blocked`; `Stock Short`; lane denied; batch expired |
| Blue | Informational / system-derived | Base-unit conversion helper; resolved store context badge |
| Grey | N/A / neutral | `patient_own` path — no stock impact |

## 8.5 Auditability in UI

Every critical action confirmation modal must display **before** the user clicks confirm:

1. **Actor** — logged-in user full name + role
2. **Store context** — resolved store name + `distribution_role` badge
3. **Unit conversion preview** — e.g., "2 boxes = 240 tablets will be deducted"
4. **Policy/lane check result** — green "Lane allowed" or amber "Override active: [reason]"
5. **Timestamp** — "Recording as [datetime]"

This applies to: `dispenseMedication()`, `administerInjection()`, `administerImmunization()`, `addConsumableBill()`, `fulfill()`, `receive()`.

## 9) Administration and Configuration Pages

Styled to match the existing Hospital Config (`hospital-config/index.blade.php`) and Banks (`banks/index.blade.php`) pages: card sections with helper text, server-side validation, sticky action bar, clear success/error alerts.

## 9.1 Admin Module: Store Governance

**Route**: `Admin → Configuration → Store Governance`

### Section A: Store Role Catalog

A table listing all stores (loaded from `stores` with `distribution_role`, `allows_direct_patient_dispense`, `requires_shift_context`).

| Column | Editable | Notes |
|--------|---------|-------|
| Store Name | No | Read from `stores.name` |
| Distribution Role | Yes | Dropdown: central / pharmacy_hub / pharmacy_satellite / department / ward / other |
| Direct Patient Dispense | Yes | Toggle — if enabled, store appears in `dispenseMedication()` store selector |
| Requires Shift Context | Yes | Toggle — if enabled, ward stock actions block when no active shift |
| Parent Store | Yes | Dropdown of `pharmacy_hub` stores (for satellite only) |
| Ward | Yes | Dropdown of `wards` (for ward stores) |
| Department | Yes | Dropdown of `departments` (for department stores) |

**Save guard**: if toggling `allows_direct_patient_dispense = false` on a store that has pending `ProductRequest` records with `dispensed_from_store_id = this_store`, show "X pending dispenses linked to this store — changing this will block those dispenses. Confirm?" before saving.

### Section B: Lane Policy Matrix

Grid with source roles as rows and destination roles as columns. Each cell:

- **Toggle**: `Allow` / `Deny`
- **Approval level**: `None` / `Manager` / `Admin`

Changes to this matrix are validated before save: if a currently active requisition uses a lane that would be newly denied, the impact list is shown before confirming.

### Section C: Store Ownership Mapping

Inline editable DataTable: `stores.ward_id`, `stores.department_id`, `stores.parent_store_id`.

### Section D: Manager Assignment

For each store: primary manager (from `users` with `store-governance.manage` permission) and backup manager.

### Section E: Override Policies

Per-store toggles:
- Lane override allowed (requires `store-policy.override-lane`)
- FIFO override allowed (requires `store-policy.override-fifo`)
- Over-receive allowed (requires `store-policy.over-receive`) + tolerance % field

## 9.2 Admin Module: Unit and Packaging Governance

**Route**: `Admin → Configuration → Units and Packaging`

### Section A: Base Unit Rules

Informational card (no toggle — always enforced): "All stock arithmetic uses base units. Packaging units are for display only."

### Section B: Packaging Definitions

Inline editable DataTable backed by `product_packagings`:

| Column | Validation |
|--------|-----------|
| Product | Read-only |
| Packaging Name | Required, unique per product |
| Conversion Factor | Required, numeric, > 0 |
| Is Default Display | Toggle — only one per product |

**Test Conversion panel**: enter a product + packaging qty → shows `= X base units` using `ProductPackaging::toBaseUnits()`. Allows admins to verify conversion factors before saving.

### Section C: Rounding Rules

Per product class: decimal allowance (0, 1, 2 decimal places). Used when `toBaseUnits()` produces a fractional result.

### Section D: Display Rules

Toggle per store class: "Show dual unit display" (base + packaging) — defaults to ON for all classes. If disabled for a class, only base unit is shown.

## 9.3 Admin Module: Context Resolution Rules

**Route**: `Admin → Configuration → Store Context Rules`

### Section A: Role → Default Store Resolution

DataTable: `user_role` → `store_id` (default store when no shift/department resolves).

### Section B: Department → Store Mapping

DataTable: `department_id` → `store_id` (for department workbench auto-resolution).

### Section C: Ward → Store Mapping

DataTable: `ward_id` → `store_id` (for nursing/maternity shift auto-resolution; maps `stores.ward_id`).

### Section D: Shift → Store Requirement

Toggle per ward: "Require active shift for ward stock actions". Drives `stores.requires_shift_context`.

### Section E: Fallback Behavior

Dropdown: when context unresolved → `Block all stock actions` (default) / `Allow with manual store selection` / `Use role default store`.

### Section F: Test Resolution Panel

Input: pick a user + ward + shift status → output: "Resolved store: [Store Name] (ward store, FIFO, shift required)". Calls a read-only endpoint backed by `StoreContextResolver::resolve()`.

## 9.4 Validation UX for All Config Pages

1. **Inline field validation** with Bootstrap validation classes — each field validates on blur.
2. **Section-level summary** card: lists all unresolved fields before the sticky save bar allows publish.
3. **Save guards**: policy changes that affect active requisitions, pending POs, or pending `ProductRequest` records show an impact modal with a list of affected records before confirming.
4. **Audit trail**: every config page save is audited via `owenIt\Auditing` on the affected models.


## 10) Workbench Context Resolution Logic

The `StoreContextResolver` is a new service class that encapsulates the resolution chain. It is called **once** on each workbench page load and passes the resolved `Store` model to the view. All subsequent `store_id` inputs in that page session are pre-filled with the resolved value.

### Resolution Chain

```
StoreContextResolver::resolve(User $user): ?Store
```

Steps executed in order (first non-null result wins):

1. **Explicit user override** — if the user has `store-context.change-manual` permission and has a session-stored `selected_store_id`, use that store (subject to: `Gate::authorize('access-store', $store)`).

2. **Active shift ward store** — if the user has an active `NursingShift` (`status = active`, `ended_at = null`):
   ```php
   $shift = NursingShift::where('user_id', $user->id)->where('status', 'active')->first();
   if ($shift) {
       return Store::where('ward_id', $shift->ward_id)->where('distribution_role', 'ward')->first();
   }
   ```
   This is how `administerInjection()`, `administerImmunization()`, and `addConsumableBill()` get their `store_id` auto-filled.

3. **User department store** — if the user belongs to a department and no shift is active:
   ```php
   return Store::where('department_id', $user->department_id)->first();
   ```

4. **User assigned default store** — a `user_store_assignments` pivot (new) or a `users.default_store_id` nullable column:
   ```php
   return Store::find($user->default_store_id);
   ```

5. **Role default store** — from the `store_context_rules` config table (Section 9.3, Section A):
   ```php
   return Store::find(StoreContextRule::where('user_role', $user->primary_role)->value('store_id'));
   ```

**If all five steps return null:**

- Stock-affecting buttons are disabled.
- A persistent amber banner is shown: "Store context not resolved. You cannot perform stock actions until a store is selected."
- A "Resolve Store Context" CTA opens a modal: shows which resolution steps failed and what to do (e.g., "Start a shift to auto-resolve your ward store" / "Contact your admin to assign a default store").

### How Existing Controllers Use the Resolved Store

| Controller Method | How `store_id` Is Currently Supplied | With This Plan |
|------------------|-------------------------------------|----------------|
| `dispenseMedication()` L933 | User selects from a dropdown in the pharmacy workbench | Pre-filled from resolved pharmacy store; dropdown still shown for `store-context.change-manual` users |
| `administerInjection()` L775 | `store_id` field in the AJAX payload (for `ward_stock` path) | Pre-filled from shift ward store; hidden field (no selector for basic users) |
| `administerImmunization()` L1185 | `store_id` field in AJAX payload | Same as above |
| `administerFromScheduleNew()` L3134 | `store_id` field in AJAX payload | Same as above (maternity also sends from resolved store) |
| `addConsumableBill()` L1745 | `store_id` field in AJAX payload | Pre-filled from shift ward store |
| `StoreRequisitionController::store()` L180 | User selects source store | Pre-filled as per resolved store's role default upstream |
| `PurchaseOrderController::receive()` L336 | PO already linked to a store | Not changed — store comes from the PO record |
| `StoreWorkbenchController::createManualBatch()` L430 | `store_id` field in form | Pre-filled from resolved store |


## 11) Permissions Model (Additive)

All permissions below are **additive** — no existing permission is removed or renamed. They are checked via `Gate::authorize()` in the controllers listed.

### New Permissions Added by This Plan

| Permission Slug | Checked In | Purpose |
|----------------|-----------|---------|
| `store-governance.view` | Store Governance admin pages | View store roles, lane matrix |
| `store-governance.manage` | Store Governance admin pages, `StoreRequisitionController::approve()` (L242) | Edit store roles, lane matrix, ownership mappings |
| `store-policy.override-lane` | `StoreRequisitionController::store()` (L180) | Create requisitions on a denied lane (reason required) |
| `store-policy.override-fifo` | `fulfill()` (L321), `dispenseMedication()` (L933), `administerInjection()` (L775), `administerImmunization()` (L1185), `addConsumableBill()` (L1745) | Select a non-oldest batch; deviation logged as `ShiftAction` |
| `store-policy.over-receive` | `PurchaseOrderController::receive()` (L336) | Receive more than the ordered quantity (within tolerance) |
| `store-context.change-manual` | All workbench pages (client-side + `StoreContextResolver`) | Override resolved store with a manual selection |
| `store-context.use-cross-department` | `StoreContextResolver` step 3 | Resolve stores from a department other than the user's own |
| `dispense-from-store` | `PharmacyWorkbenchController::dispenseMedication()` (L933) | Allows dispense from a specific store (scoped to store ID) |
| `administer-from-store` | `NursingWorkbenchController::administerInjection()` (L775), `administerImmunization()` (L1185), `administerFromScheduleNew()` (L3134) | Allows ward-stock administration from a specific store |
| `bill-consumable-from-store` | `NursingWorkbenchController::addConsumableBill()` (L1745) | Allows consumable billing from a specific store |
| `requisition-lane-allowed` | `StoreRequisitionController::store()` (L180) Gate check | Auto-approved when lane policy allows; denied otherwise |
| `can-approve-requisition-for-store` | `StoreRequisitionController::approve()` (L242) | Scoped to store ID; must be manager of the source store |

### Permission Assignment Matrix (Initial)

| Role | Permissions |
|------|------------|
| Admin | All |
| Pharmacy Manager | `store-governance.view`, `dispense-from-store`, `store-policy.override-fifo`, `store-context.change-manual`, `can-approve-requisition-for-store` (pharmacy stores) |
| Pharmacist | `dispense-from-store` (assigned pharmacy store only) |
| Head Nurse | `administer-from-store`, `bill-consumable-from-store`, `store-policy.override-fifo`, `can-approve-requisition-for-store` (ward stores) |
| Nurse | `administer-from-store` (shift ward store only), `bill-consumable-from-store` |
| Maternity Nurse | Same as Nurse + access to maternity proxy routes |
| Store Keeper | `store-governance.view`, `can-approve-requisition-for-store`, `store-policy.over-receive` |
| Doctor | None (read-only stock data) |

## 12) Rollout Plan (Minimal Change / Max Impact)

### Phase A — Foundation (Schema + Config UI)

**Goal**: Ship the data model and admin tools. Zero code changes to clinical controllers.

1. **Migration**: Add columns to `stores` table (`distribution_role`, `ward_id`, `department_id`, `parent_store_id`, `allows_direct_patient_dispense`, `requires_shift_context`); create `store_lane_policies` table; create `store_context_rules` table.
2. **Seed**: Set `distribution_role` for all existing stores based on naming convention.
3. **Admin UI**: Build Store Governance page (Section 9.1), Unit and Packaging Governance page (Section 9.2), Context Rules page (Section 9.3).
4. **Permissions**: Register all new permission slugs in the permissions table; assign to roles per Section 11 matrix.
5. **Gate check on `StoreRequisitionController::store()` (L180)**: add lane policy check before `RequisitionService::create()`. Return 403 with reason if denied.
6. **Gate check on `StoreRequisitionController::approve()` (L242)**: add `can-approve-requisition-for-store` check.

**No clinical controller is touched in Phase A.** All existing workflows continue unchanged.

### Phase B — Context Resolution + Workbench Pre-fill

**Goal**: Auto-fill `store_id` in all workbench forms; surface store badges in UI.

1. **Build `StoreContextResolver` service** (five-step chain from Section 10).
2. **Integrate into workbench controllers**:
   - `PharmacyWorkbenchController` — pass resolved store to `dispenseMedication()` pharmacy page.
   - `NursingWorkbenchController` — pass resolved store to shift summary page; pre-fill `store_id` in injection/immunization/consumable AJAX payload defaults.
   - `MaternityWorkbenchController` — pass resolved store through the `nursingProxy()` layer.
3. **Dual-unit display**: add `ProductPackaging::fromBaseUnits()` conversion helper to all stock quantity displays in pharmacy, nursing, maternity, and store workbench views.
4. **Readiness chips**: add server-side `readiness` field to `validateCartStock()` response (Section 7.5.1 chip states).
5. **Gate checks on `dispenseMedication()` (L933)**: add `dispense-from-store`, `administer-from-store`, `bill-consumable-from-store` checks before `StockService::dispenseStock()`.
6. **FEFO sort for vaccine batch pickers** in `administerImmunization()` and maternity schedule paths.

### Phase C — Handover, KPIs, and Hardening

**Goal**: Complete visibility and governance loop.

1. **Handover stock snapshot**: extend `generateHandoverReport()` (L2764) to include resolved store's `StoreStock` snapshot at handover time.
2. **`ShiftAction` logging**: log FIFO override, lane override, and over-receive events as `ShiftAction` records for audit.
3. **KPI dashboard endpoints**: build read-only endpoints for Section 13 metrics.
4. **"Test Resolution" + "Test Conversion" panels** in admin config pages (Section 9.3 Section F, Section 9.2 Section B).
5. **Save guards** for config page policy changes (Section 9.4).
6. **Load testing**: verify `StoreContextResolver` adds ≤ 50 ms to workbench page load (cached for session).

## 13) KPI Dashboard by Manager Type

### Pharmacy Manager

**Data sources**: `product_requests` (status, `dispensed_from_store_id`), `store_stocks` (`current_quantity`), `store_stock_batches` (`expiry_date`, `current_qty`)

| KPI | Query |
|-----|-------|
| Dispenses today | `ProductRequest::where('status', 3)->whereDate('dispensed_at', today())->count()` |
| HMO blocked queue | `ProductRequest::where('status', 2)->whereHas('hmoBlocks')` |
| Stock-out products | `StoreStock::where('store_id', $pharmacyStore)->where('current_quantity', 0)` |
| Near-expiry batches (≤ 30 days) | `StoreStockBatch::where('expiry_date', '<=', now()->addDays(30))` |
| Avg dispense time | `dispensed_at - billed_at` per `ProductRequest` |

### Head Nurse / Ward Manager

**Data sources**: `shift_actions`, `product_or_service_requests`, `store_stocks`, `nursing_shifts`

| KPI | Query |
|-----|-------|
| Consumables billed this shift | `ProductOrServiceRequest` created during active shift, product type = consumable |
| Injections administered | `InjectionRecord::where('shift_id', $activeShift->id)->count()` |
| Ward stock below reorder | `StoreStock::where('store_id', $wardStore)->where('current_quantity', '<', 'reorder_level')` |
| Pending requisitions to this ward | `StoreRequisition::where('destination_store_id', $wardStore)->where('status', 'approved')` |
| FIFO overrides this shift | `ShiftAction::where('type', 'fifo_override')->where('shift_id', $shift->id)` |

### Store / Supply Manager

**Data sources**: `store_requisitions`, `purchase_orders`, `store_lane_policies`

| KPI | Query |
|-----|-------|
| Pending fulfillments | `StoreRequisition::where('source_store_id', $myStore)->where('status', 'approved')` — from `RequisitionService::getPendingFulfillment()` (L369) |
| POs awaiting receive | `PurchaseOrder::where('store_id', $myStore)->where('status', 'approved')` |
| Lane policy violations (overrides) | `ShiftAction::where('type', 'lane_override')` |
| Over-receive events | `ShiftAction::where('type', 'over_receive')` |
| Batch turnover by product | `StoreStockBatch` created vs. depleted per 30-day window |

## 14) Non-Functional Requirements

### Performance

1. `StoreContextResolver::resolve()` must complete in ≤ 50 ms per request (Eloquent with index on `stores.ward_id`, `stores.department_id`). Cache resolved store in `Session` for the duration of the workbench session.
2. `validateCartStock()` endpoint (L809) must complete in ≤ 200 ms for carts of up to 20 items (bulk `StoreStock::whereIn` query).
3. Lane policy check in `StoreRequisitionController::store()` is a single `store_lane_policies` table lookup — must not add > 10 ms.
4. Batch picker in `getAvailableBatches()` (L390) must paginate; default page size 50.

### Security

1. All new Gate checks (`dispense-from-store`, `administer-from-store`, `bill-consumable-from-store`, `can-approve-requisition-for-store`) are **scoped to store ID** — a user with pharmacy store X permissions cannot dispense from pharmacy store Y.
2. `StoreContextResolver` result is **not** user-controlled unless the user has `store-context.change-manual`. The resolved store ID is never taken directly from the request payload without this permission check.
3. `store_lane_policies` can only be modified by users with `store-governance.manage`. Changes are audited.
4. Override reason fields (FIFO override, lane override) are **required** strings logged as `ShiftAction` with `user_id`, `store_id`, `timestamp`, and `reason` — never truncated or optional.

### Data Integrity

1. All stock arithmetic continues to use `StockService` methods unchanged — no direct `StoreStock::decrement()` calls outside `StockService`.
2. `ProductPackaging::toBaseUnits()` is the only conversion function — no local arithmetic in controllers.
3. All new foreign keys (`stores.ward_id`, `stores.department_id`, `stores.parent_store_id`) are nullable with `SET NULL` on delete — existing stores are not broken by ward/department deletion.
4. `store_lane_policies` changes are transactional; partial updates are rolled back.

### Backward Compatibility

1. Stores without a `distribution_role` set (null) behave as `other` — no lane restrictions apply, all existing workflows continue.
2. `ProductRequest` records without `dispensed_from_store_id` (legacy) are not broken — `dispenseMedication()` only sets this on new dispenses.
3. Existing `StockBatch.source_tag` values (`SOURCE_MANUAL`, `SOURCE_PURCHASE_ORDER`, `SOURCE_TRANSFER_IN`) are preserved exactly — no remapping.

## 15) Final Governance Rulebook (Short Form)

Each rule is listed with its **enforcement point** — where in the codebase it is checked.

| # | Rule | Enforcement Point |
|---|------|------------------|
| R01 | Base unit is king — all stock arithmetic in base units | `StockService` (unchanged); `ProductPackaging::toBaseUnits()` before every service call |
| R02 | Packaging units are for display only | UI helper text + `ProductPackaging::fromBaseUnits()` in view layer only |
| R03 | FIFO is the default batch selection order | `StockService::dispenseStock()` (unchanged); `getAvailableBatches()` returns batches ordered by `created_at` |
| R04 | FEFO for vaccines | Batch picker in `administerImmunization()` sorts by `expiry_date` before `created_at` |
| R05 | Store lane policy governs requisitions | `StoreRequisitionController::store()` L180 Gate check against `store_lane_policies` |
| R06 | Only the source store's manager can approve a requisition | `StoreRequisitionController::approve()` L242 Gate: `can-approve-requisition-for-store` scoped to source store |
| R07 | Dispense only from stores with `allows_direct_patient_dispense = true` | `dispenseMedication()` L933 Gate: `dispense-from-store` scoped to store ID |
| R08 | Ward stock actions require an active shift if `requires_shift_context = true` | `administerInjection()` L775, `administerImmunization()` L1185, `addConsumableBill()` L1745 — shift check before service call |
| R09 | All dispense/injection/consumable stock deductions go through `StockService` | No direct `StoreStock::decrement()` in clinical controllers — existing code already follows this |
| R10 | HMO tariff applied before any billing-path stock action | `HmoHelper::applyHmoTariff()` called in `applyTariffToRequest()` before `dispenseStock()` in all billed paths |
| R11 | FIFO override requires a reason and is logged | `ShiftAction` record with `type=fifo_override`, `reason`, `user_id`, `store_id`, `timestamp` |
| R12 | Lane override requires a reason and is logged | `ShiftAction` record with `type=lane_override`; requires `store-policy.override-lane` |
| R13 | Over-receive capped by tolerance; requires `store-policy.over-receive` | `PurchaseOrderController::receive()` L336 Gate check |
| R14 | No future-dated administration records | `administered_at` ≤ `now()` enforced in `administerInjection()`, `administerImmunization()`, `addConsumableBill()` |
| R15 | Store context is auto-resolved from shift/department/role | `StoreContextResolver::resolve()` called on workbench load; result pre-fills all `store_id` fields |
| R16 | Store context cannot be manually changed without `store-context.change-manual` | `StoreContextResolver` ignores session-stored `selected_store_id` unless permission check passes |
| R17 | Every config change to lane policies is audited | `owenIt\Auditing` on `StoreLanePolicy` model |
| R18 | Every config change to store roles is audited | `owenIt\Auditing` on `Store` model (audit only the governance columns) |
| R19 | Maternity stock/immunization always delegates to nursing proxy | `MaternityWorkbenchController` never calls `StockService` directly — always via `$this->nursingProxy()` |
| R20 | `SOURCE_MANUAL`, `SOURCE_PURCHASE_ORDER`, `SOURCE_TRANSFER_IN` tags preserved | `StoreWorkbenchController::createManualBatch()`, `PurchaseOrderService::receiveItems()`, `RequisitionService::fulfill()` — tags hardcoded in each service, never remapped |



## Appendix A: Suggested New Routes (Conceptual)

1. `GET /admin/store-governance`
2. `PUT /admin/store-governance/policies`
3. `PUT /admin/store-governance/mappings`
4. `GET /admin/unit-packaging-governance`
5. `PUT /admin/unit-packaging-governance`
6. `GET /admin/store-context-rules`
7. `PUT /admin/store-context-rules`
8. `POST /admin/store-context-rules/test-resolution`
9. `POST /admin/unit-packaging-governance/test-conversion`

## Appendix B: Suggested Config Page Components

1. Card-based sections with helper text and examples.
2. DataTables for mapping/policy rows.
3. Sticky action bar: `Save Draft`, `Validate`, `Publish`.
4. Change impact modal before publish.
5. Export/import of policy matrix for governance portability.

---

## Appendix C: Backward Compatibility Trace

> **Rule**: Every method listed below is **unchanged**. The governance layer is strictly additive — new policy checks run *before* service calls; service internals are not modified.

---

### C.1 Manual Batch (Add-Batch Flow)

| Method | File | Line | Status | Governance Hook |
|--------|------|------|--------|-----------------|
| `StoreWorkbenchController::manualBatchForm()` | StoreWorkbenchController.php | 413 | **Unchanged** | New plan renders store-role badge + "allowed product types for this store" hint in the form view only |
| `StoreWorkbenchController::createManualBatch()` | StoreWorkbenchController.php | 430 | **Unchanged** | New plan inserts a store-role policy check (Gate/Policy) *before* the `$stockService->createBatch()` call; if the product category is disallowed for the store type, request is rejected before the service is ever reached |
| `StockService::createBatch()` | StockService.php | 163 | **Unchanged completely** | No modification; existing `source` field (`SOURCE_MANUAL`) is already stored and used for reporting/filtering |

**Base-unit rule**: `createManualBatch` already accepts `quantity` in raw base units. The new plan adds a dual-unit preview widget in the form — conversion is display-only, `quantity` submitted to the service remains in base units.

---

### C.2 Purchase Order Flow (Draft → Submit → Approve → Receive)

| Method | File | Line | Status | Governance Hook |
|--------|------|------|--------|-----------------|
| `PurchaseOrderController::store()` | PurchaseOrderController.php | 120 | **Unchanged** | New plan shows target-store role badge in the create form; no request payload changes |
| `PurchaseOrderService::create()` | PurchaseOrderService.php | 47 | **Unchanged** | `packaging_id` / `packaging_qty` already saved per item; no new fields required |
| `PurchaseOrderService::submit()` | PurchaseOrderService.php | 178 | **Unchanged** | No hook needed at submit stage |
| `PurchaseOrderService::approve()` | PurchaseOrderService.php | 198 | **Unchanged** | Approver-authority check is a Gate/Policy around the controller action, not inside the service |
| `PurchaseOrderController::showReceiveForm()` | PurchaseOrderController.php | 321 | **Unchanged** | New plan adds dual-unit display columns (packaging qty ↔ base qty) to the existing receive form view; `items.product.packagings` relation already eager-loaded |
| `PurchaseOrderController::receive()` | PurchaseOrderController.php | 336 | **Unchanged** | `received_packaging_id` / `received_packaging_qty` already accepted and validated; new plan adds a conversion preview tooltip in the UI only |
| `PurchaseOrderService::receiveItems()` | PurchaseOrderService.php | 245 | **Unchanged** | Already calls `StockService::createBatch()` with `SOURCE_PURCHASE_ORDER` and stores `received_packaging_id` / `received_packaging_qty` on the item; governance layer only adds a post-receive notification to the store manager |
| `PurchaseOrderService::getReadyToReceive()` | PurchaseOrderService.php | 366 | **Unchanged** | Used only for queue display |

**Base-unit rule**: `receiveItems()` already converts packaging qty to base units before calling `createBatch()`. The new plan's dual-unit label on the receive form calls `ProductPackaging::fromBaseUnits()` for display only — no change to the value stored or passed to `createBatch()`.

---

### C.3 Requisition Flow (Create → Approve → Fulfill → Receive at Destination)

| Method | File | Line | Status | Governance Hook |
|--------|------|------|--------|-----------------|
| `StoreRequisitionController::store()` | StoreRequisitionController.php | 180 | **Unchanged** | New plan inserts a lane-policy Gate check (requester store type → supplier store type allowed?) *before* `RequisitionService::create()`; `packaging_id` / `packaging_qty` already validated and saved |
| `StoreRequisitionController::approve()` | StoreRequisitionController.php | 242 | **Unchanged** | New plan adds approver-authority Gate check (must be the designated store manager of the source store) as a controller-level middleware/policy; service call unchanged |
| `StoreRequisitionController::fulfill()` | StoreRequisitionController.php | 321 | **Unchanged** | New plan adds a FIFO-override warning toast in the UI when the nurse/manager selects a non-FIFO batch order; the multi-batch payload `[item_id => ['batches' => [batch_id => qty]]]` and the `RequisitionService::fulfill()` call are untouched |
| `RequisitionService::fulfill()` | RequisitionService.php | ~190 | **Unchanged** | `StockService::transferStock()` call chain preserved exactly; batch source tag `SOURCE_TRANSFER_IN` unchanged |
| `StoreRequisitionController::getAvailableBatches()` | StoreRequisitionController.php | 390 | **Unchanged** | Already calls `StockService::getAvailableBatches()`; new plan augments the JSON response with packaging-unit labels for UI display only |
| `RequisitionService::getAvailableStockForRequisition()` | RequisitionService.php | 332 | **Unchanged** | Used for UI display in the fulfill form; no change |
| `RequisitionService::getPendingFulfillment()` | RequisitionService.php | 369 | **Unchanged** | Used for workbench queue |
| `RequisitionService::getMyRequisitions()` | RequisitionService.php | 398 | **Unchanged** | Used for requester-side queue |

---

### C.4 Batch Source Tags — Preserved

| Source Constant | Set By | Unchanged |
|----------------|--------|-----------|
| `StockBatch::SOURCE_MANUAL` | `StoreWorkbenchController::createManualBatch()` | ✓ |
| `StockBatch::SOURCE_PURCHASE_ORDER` | `PurchaseOrderService::receiveItems()` | ✓ |
| `StockBatch::SOURCE_TRANSFER_IN` | `RequisitionService::fulfill()` via `StockService::transferStock()` | ✓ |

---

### C.5 syncStoreStock Cascade — Unchanged

`StockService::syncStoreStock(int $productId, int $storeId)` is called automatically inside `createBatch()` and via `StockBatchObserver` after every `dispenseStock()` / `dispenseFromBatch()` / `transferStock()`. The governance layer runs **before** these writes. No cascade logic is modified.

---

## Appendix D: Dispense, Injection, Immunization & Consumable Billing — Governance Considerations

> These flows are **read-only from a stock-governance perspective** — the stock deductions already use `StockService` correctly. The governance plan's contribution is: (a) ensuring the correct store is pre-selected by context, (b) surfacing batch/expiry warnings in the UI, and (c) enforcing store-type access rules at the Gate layer.

---

### D.1 Pharmacy Workbench — Drug Dispense

**Controller**: `PharmacyWorkbenchController::dispenseMedication()` — Line 933

**Current flow (unchanged)**:
1. Validates `product_request_ids[]` (status must be `2` = Billed) + `store_id`
2. HMO bundled/delivery check per item — entire batch rejected on any failure
3. Strict store-stock check via `StoreStock` — no fallback to global
4. If batches exist → `StockService::dispenseStock()` (FIFO, triggers `StockBatchObserver` → `syncStoreStock`)
5. If no batches → legacy direct `store_stock` decrement + global stock decrement
6. Marks `ProductRequest` status = 3 (Dispensed), records `dispensed_by`, `dispense_date`, `dispensed_from_store_id`

**Governance additions (additive only)**:

| Addition | Where | Logic Change? |
|----------|-------|---------------|
| **Store pre-selection from context** | UI only — pharmacy workbench tab initialises `store_id` from the user's assigned pharmacy store | No |
| **Batch expiry warning** | UI only — if selected store has batches expiring within 30 days, yellow banner shown before dispense | No |
| **Store-type Gate** | Controller-level `Gate::authorize('dispense-from-store', $store)` — passes only if `$store->store_type === 'pharmacy'` | No service change |
| **Dual-unit label on dispense receipt** | Print slip shows qty in both base units and prescribed packaging unit | No |
| **Legacy-path alert** | If no batches exist and legacy fallback is triggered, a `Log::warning` entry + admin notification is added to flag the store as needing batch import | No service change |

**Base-unit rule**: `qty` on `ProductRequest` is always in base units. Dispense confirmation receipt may display packaging equivalent (e.g., "1 bottle of 100 tabs dispensed") — display only.

---

### D.2 Nursing Workbench — Drug Injection Administration

**Controller**: `NursingWorkbenchController::administerInjection()` — Line 775

**Current flow (unchanged)**:
Three drug-source paths already implemented:

| Path | `drug_source` | Stock Deducted? | Billing Created? |
|------|--------------|-----------------|-----------------|
| 1 | `pharmacy_dispensed` | No (already dispensed by pharmacy) | No (POSR already exists) |
| 2 | `patient_own` | No | No |
| 3A | `ward_stock` + `bill_patient = false` | Yes — `StockService::dispenseFromBatch()` or `dispenseStock()` FIFO | No |
| 3B | `ward_stock` + `bill_patient = true` | Yes — same as 3A | Yes — `ProductRequest` (status=2) + `ProductOrServiceRequest` via `applyTariffToRequest()` |

**Governance additions (additive only)**:

| Addition | Where | Logic Change? |
|----------|-------|---------------|
| **Ward store pre-selection** | UI only — `store_id` defaults to the ward's linked store based on active shift's `ward_id` | No |
| **Store-type Gate** | `Gate::authorize('administer-from-store', $store)` — `ward_stock` path only; passes if `$store->store_type` is `ward` or `other` | No service change |
| **Packaging-unit display** | UI only — drug search results show dose in both base units and the product's primary packaging (e.g., "2 ml" = "0.4 × 5 ml ampoule") | No |
| **Batch expiry warning** | UI only — if selected `batch_id` expires within 7 days, red warning shown before confirm | No |
| **Unbilled ward deduction log** | Path 3A already deducts stock; new plan adds an informational shift-action log entry referencing the `InjectionAdministration` record for shift handover visibility | No service change |

**Base-unit rule**: `qty` for injection is always in base units (e.g., ml, tablets). Packaging unit shown in search is display-only.

---

### D.3 Nursing Workbench — Immunization Administration

**Controller**: `NursingWorkbenchController::administerImmunization()` — Line 1185
**Schedule-based (primary path)**: `NursingWorkbenchController::administerFromScheduleNew()` — Line 3134

**Current flow (unchanged)**:
1. Validates `store_id`, `products[].product_id`, `products[].dose_number`, `route`, `administered_at`
2. Checks stock via `StockService::getAvailableStock()`
3. Always creates a `ProductOrServiceRequest` (billing record) with HMO tariff or fallback price
4. Deducts stock via `StockService::dispenseFromBatch()` (if batch_id given) or `dispenseStock()` FIFO
5. Creates `ImmunizationRecord`

**Governance additions (additive only)**:

| Addition | Where | Logic Change? |
|----------|-------|---------------|
| **Store pre-selection** | UI only — `store_id` defaults to ward's linked store | No |
| **Store-type Gate** | `Gate::authorize('administer-immunization-from-store', $store)` — passes if store type is `ward`, `pharmacy`, or `other` | No service change |
| **Cold-chain batch priority** | UI only — vaccine product batch list sorted by nearest-expiry first (FEFO) with expiry date prominently displayed | No service change; `dispenseFromBatch()` call already exists |
| **Packaging-unit badge** | UI only — vaccine quantity shown as "1 dose" / "0.5 ml" alongside base unit | No |
| **Schedule context** | `administerFromScheduleNew()` already carries `schedule_id`; governance plan adds schedule status update audit trail via `ShiftAction` for shift handover | No service change |

---

### D.4 Maternity Workbench — Injection & Immunization (Proxy Pattern)

**Controller**: `MaternityWorkbenchController` proxies all stock-touching immunization/injection calls to `NursingWorkbenchController` via `$this->nursingProxy()`.

| Maternity Method | Line | Proxies To | Stock Logic |
|-----------------|------|------------|-------------|
| `administerFromScheduleMaternity()` | 2244 | `NursingWorkbenchController::administerFromScheduleNew()` | Fully delegated |
| `administerFromSchedule($request, $babyId)` | 2380 | `administerFromScheduleMaternity()` → `administerFromScheduleNew()` | Fully delegated |
| `getImmunizationHistoryByPatient($patientId)` | 2239 | `NursingWorkbenchController::getImmunizationHistory()` | Read-only |
| `getImmunizationSchedule($babyId)` | 2362 | `NursingWorkbenchController::getPatientSchedule()` | Read-only |

**All proxy methods are unchanged.** The maternity workbench does not directly touch `StockService`.

**Governance additions (additive only)**:

| Addition | Where | Logic Change? |
|----------|-------|---------------|
| **Maternity-store pre-selection** | UI only — maternity ward has its own linked store; `store_id` is pre-filled when the proxy AJAX call is made from the maternity workbench | No |
| **Maternal vs baby patient labelling** | UI only — dispense/immunization confirmation distinguishes mother vs baby patient clearly; underlying `patient_id` logic unchanged | No |
| **Cold-chain FEFO** | Same as D.3 — applied via UI batch sort on `administerFromScheduleNew()` | No service change |
| **ANC-visit linkage display** | After immunization, the maternity timeline shows a badge linking the `ImmunizationRecord` to the current ANC visit; this is a read query only | No |

---

### D.5 Nursing Workbench — Consumable Billing

**Controller**: `NursingWorkbenchController::addConsumableBill()` — Line 1745

**Current flow (unchanged)**:
1. Validates `patient_id`, `store_id`, `product_id`, `qty`, optional `batch_id`
2. Checks stock via `StockService::getAvailableStock()`
3. Creates `ProductOrServiceRequest` with HMO tariff via `HmoHelper::applyHmoTariff()` or fallback price
4. Deducts stock via `StockService::dispenseFromBatch()` (if `batch_id` given) or `dispenseStock()` FIFO
5. If `is_medication = true`, also creates a `ProductRequest` (status=2 Billed) linked to the POSR

**Governance additions (additive only)**:

| Addition | Where | Logic Change? |
|----------|-------|---------------|
| **Ward store pre-selection** | UI only — `store_id` defaults to active shift's ward linked store | No |
| **Store-type Gate** | `Gate::authorize('bill-consumable-from-store', $store)` — passes if `$store->store_type` is `ward` or `other` | No service change |
| **Product category filter** | UI only — consumable search filters to products in categories flagged as `consumable` for the store's type; no payload change | No |
| **Packaging-unit display** | UI only — qty input labelled with base unit name (e.g., "pieces", "ml"); packaging equivalent shown as helper text | No |
| **Batch expiry warning** | UI only — if FIFO selects a batch expiring within 14 days, amber warning shown after bill is created | No |
| **Shift billing log** | After successful `addConsumableBill()`, a `ShiftAction` log entry is appended for inclusion in shift handover report | No service change |

**Base-unit rule**: `qty` field is always in base units. The packaging-unit display is computed client-side from `ProductPackaging::fromBaseUnits()` endpoint — read-only, no write impact.

---

### D.6 Cross-Cutting Governance Rules for All Dispense/Administer Paths

These rules apply to all four paths (D.1–D.5) uniformly:

1. **Store-context resolution order** (additive check before every dispense/administer action):
   - User's active shift `ward_id` → linked `Store` → resolved `store_id`
   - If no shift active: user's default assigned store
   - If neither: user must select manually (no silent fallback to global stock)

2. **Base unit is king** — all `qty` values transmitted to `StockService` methods are in base units. Packaging-unit values shown in UI are computed on the fly and never stored as the dispense quantity.

3. **No fallback to global stock for batchable stores** — `dispenseMedication()` already enforces this with "STRICT store stock check". The governance plan extends this strict rule to `addConsumableBill()` and `administerInjection()` (ward_stock path) via the same `StoreStock` query pattern.

4. **FIFO is default; FEFO for vaccines** — all dispense paths default to FIFO. Vaccine products (category flagged `vaccine`) trigger FEFO (nearest expiry first) batch sorting in the UI batch-selection list. The underlying `dispenseFromBatch()` call is unchanged — FEFO is enforced by pre-selecting the correct `batch_id` in the UI.

5. **HMO tariff pipeline unchanged** — `HmoHelper::applyHmoTariff()` and `NursingWorkbenchController::applyTariffToRequest()` are not modified. Governance only ensures the correct store's `dispensed_from_store_id` is set on the POSR.
