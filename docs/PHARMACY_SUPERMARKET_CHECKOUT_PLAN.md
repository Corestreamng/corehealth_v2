# Pharmacy Checkout — Supermarket Flow Plan (UI/UX only, minimal API)

**Status:** Implemented — see §8 for what shipped. This file remains the design record.
**Goal:** Make pharmacy billing + dispense feel like a supermarket checkout: one patient, one screen, a till that never leaves your sight, and a scan gun that puts items in the bag without tab-hopping.

---

## 1. Target experience (what "supermarket" means here)

**Cash/walk-in sale (fast path):**
1. Cashier opens patient → Checkout screen.
2. Scans item (or types code) into the bar at the top of the shelf → item is added *in place* and **appears in the till instantly**.
3. Till (always visible) shows line: name · qty [−][+] · price · remove × · coverage/claim badge.
4. One click on the till CTA → **Bill** → pay happens (cash/transfer/HMO) → item moves to Ready.
5. Same till CTA now says **Review & dispense** → dispense → till empties, scan box refocuses.

**Rx checkout (fast path):**
1. Open patient → Billing stage shows requested items.
2. Tick rows (or scan a barcode that matches an item already on the shelf — it bags itself).
3. Till CTA → **Bill N item(s)** → pay/validate → items land in Ready.
4. Till CTA → **Review & dispense** → done.

**The cashier never:** opens the New Request tab, opens a cart modal, scrolls to find totals, or loses the till — for the happy path.

---

## 2. What already exists (inventory — reuse, don't rebuild)

| Piece | Where |
|---|---|
| Shelf + till checkout layout (`.pharm-checkout`, `.pharm-shelf`, `.pharm-till`) | `pharmacy-dispensing.js` — `injectUnifiedPrescPartial()` HTML template; layout CSS in `public/css/pharmacy-workbench.css` (`.pharm-checkout`, `.pharm-till`, `.pharm-add-sku`, `.till-line*`) |
| Stage sub-tabs **Billing / Pending / Ready to Dispense / History** (`#presc-billing-pane`, `#presc-pending-pane`, `#presc-dispense-pane`, `#presc-history-pane`) with DataTables `#presc_billing_table`, `#presc_pending_table`, `#presc_dispense_table`, `#presc_history_table` | same template; rows rendered from `prescBillList()` / `prescDispenseList()` etc. |
| Till bag renderer — sections **To bill / On hold / Ready**, totals (`#till-item-count`, `#till-grand-total`), Print/Dismiss, CTA `#till-primary-cta` (becomes *"Bill N item(s)"* or *"Review & dispense"*) | `renderTillBag()`, `gatherSelectedItems()`, `updateStickyActionBar()`, `billPrescItems()`, `addSelectedToCartAndOpen()` |
| Selections survive reloads in-memory | `selectedItemsData` global |
| Floating-cart FAB fallback (sub-992px) `#floating-cart` → `openCartReviewModal()` | workbench.blade.php + CSS `#cartReviewModal` |
| Product search (name **and** `product_code` LIKE, HMO tariff preview, store stock, combos) | `GET /pharmacy-workbench/search-products` → `PharmacyWorkbenchController@searchProducts` |
| Create a product request inline (returns created `requests` **with ids** — great for auto-bag) | `POST /pharmacy-workbench/create-request` → `createPrescriptionRequest()` |
| Add-item affordance on the shelf… **but it jumps to the New Request tab** | `.pharm-add-sku` button → `switchWorkspaceTab('new-request')` |
| Product code column already on products (nullable, no uniqueness constraint) | `products.product_code` (migration `2023_03_07_101825_create_products_table`) |
| Dispense cart modal with qty/stock review + coverage/claim display | `openDispenseCartModal()` / `#dispenseCartModal` |
| Scan-free "free-form" add fallback | `addFreeFormProductWorkbench()` (name only, price 0, cash) |

**Gaps that create the current friction**
1. `.pharm-till` is `position: sticky; top: 0` **inside its flex column** — it stays put only while the checkout container itself is in the scroll viewport; on tall pages / outer-page scrolling the till scrolls away. On <992px it disappears entirely behind a cart FAB.
2. Adding an off-Rx item means: leave Checkout → New Request tab → search/select → submit → return to Checkout → find the row → tick it → *then* it's in the till. ~6 steps and two context switches.
3. No barcode/scan input anywhere. `searchProducts` matches `product_code` only as a fuzzy LIKE, never exact-first, so a full code scan can rank below name matches.
4. Bagging = ticking small checkboxes; no tap-row-to-bag, no bag-all, no qty stepper on the till line itself.
5. After Bill/Dispense the cashier has to re-orient (toast only); no "stay in the flow" behaviour and no scan refocus.

---

## 3. The improvements

### I. Pin the till to the viewport — it never leaves the screen

**UX:** The till becomes a real register, not a sidebar that scrolls with the page.

- **Desktop (≥992px):** make the checkout column the *only* scrolling region and give the till a fixed rail on the right of the viewport:
  - `.pharm-checkout` becomes `height: calc(100vh − <admin header + patient header>); overflow: hidden`.
  - `.pharm-shelf` scrolls internally (`overflow-y: auto`).
  - `.pharm-till` becomes `position: fixed; right: 0; top: <header offset>; bottom: 0; width: ~340px` with its own internal scroll for `.pharm-till-body`, while the header (`Till`, count), totals (`Items`, `Bag total`) and the action row (`#till-primary-cta`, Print, Dismiss, Clear) are always on screen.
- **Tablet/mobile (<992px):** replace the "find the FAB, open a modal" hop with a **bottom-sheet till**: a slim always-visible bar (item count + bag total + CTA) docked to the bottom of the viewport that expands upward to the full bag; sheet is draggable/dismissable, same `renderTillBag()` DOM so one renderer serves both layouts.
- Keep the `#floating-cart`/`#cartReviewModal` code as an accessibility fallback only (e.g., very narrow screens, print).
- Bonus polish: subtle shadow + the shelf never overlaps the till rail.

**API impact:** none. **Files:** `public/css/pharmacy-workbench.css`, `workbench.blade.php` (outer scroll wrapper if needed), `pharmacy-dispensing.js` (only if a class toggles the drawer).

---

### II. Inline "scan / add item" bar right on the billing shelf (add in place, see it in the till)

**UX:** Replace the `.pharm-add-sku` button that *jumps to New Request* with an always-on input bar at the top of the shelf:

- Input is always visible on the Checkout screen (both Billing and Ready stages), with placeholder *"Scan barcode or type to add item…"*.
- On submit/scan the client calls the **existing** `search-products` (or exact-code path from IV) and, on a unique hit, calls the **existing** `create-request` with `patient_id`, the product, qty (default 1), urgency default, `send_to_billing=1` — i.e., the exact payload the New Request form already sends.
- Response already returns `requests[].id` → client refreshes the Billing DataTable, then **auto-ticks the returned ids**, which flows straight through `gatherSelectedItems()` → `renderTillBag()`: the item is now on the Billing shelf **and bagged in the till** — zero tab switches.
- The newly created line is highlighted (green flash) and scrolled into view.
- Ambiguous result → small dropdown in the bar itself (never a full tab). No hit → audible beep + offer free-form name add (existing flow) from the bar.
- **Scan of an item that already exists on the shelf** (e.g. already requested): the bar's lookup detects the same product id in the currently loaded shelf rows and *ticks it* instead of creating a duplicate request.

**API impact:** none required (endpoints + response shape already support it; see §4 for optional tiny tweak). **Files:** `pharmacy-dispensing.js` (replace `.pharm-add-sku` block; new `quickAddProduct()`/`resolveScan()` helpers), CSS for the bar, New Request form can reuse the same helpers.

---

### III. Barcode scanning when adding requests — no `sku` column, ever

**Strategy:** `products.product_code` *is* the machine code. No new column, no new table, no migration.

- **Data semantics:** label the field "Product code / barcode" in search + add UIs. Where `product_code` is blank, scanning falls back to fuzzy name search (identical to typing).
- **Input handling (client):**
  - HID scan guns behave like keyboards: strip the trailing `\n`/`\r` suffix, trim, debounce.
  - A global `keydown` listener while a patient is open catches scan-suffix input so the cashier can scan *anywhere* on the checkout screen (not only when the input is focused), unless the focus is in another text field.
  - Quantity shortcuts on the scan bar: `2*` or `2x` before the code → qty 2; otherwise qty = 1 and packaging = default dispense packaging.
- **Matching order (client asks, server returns candidates):** (1) exact `product_code` match → auto-add; (2) single fuzzy match (code or name) → auto-add; (3) several → picker in the bar; (4) none → beep + free-form option. Always visual + audible feedback (green flash/short beep on add; red + low beep on miss).
- **Also surface the same scan box on the New Request tab** (where multi-item "requests" are composed) so a scanned item joins `selectedProducts` exactly like clicking a search result today — one shared `lookupProduct()` helper used by both screens.

**API impact:** none (search already covers `product_code`). Optional server tweak in IV makes exact scans deterministic. **Files:** `pharmacy-dispensing.js`, `pharmacy-core.js` (shared helper), CSS; `_scripts.blade.php` if wiring new input ids.

---

### IV. Exact-code-first product lookup (the only *optional* server touch)

**Why:** today `searchProducts` does `product_name LIKE %term% OR product_code LIKE %term%` with no ordering — a 12-digit code scan can be drowned out by name matches (and `LIKE '%code%'` is technically fine for a full code but ordering is wrong). For a scanner we want: if *any* row's `product_code` equals the term, it must win outright.

**Proposed change (backwards compatible):**
- Client sends `mode=barcode` (or `exact=1`) when the input came from a scan.
- Server, when `exact=1`: run an **exact** `product_code = term` query first and return its row alone (no name LIKE, no combos); only if that finds nothing, fall back to today's behaviour.
- ~10 lines in `PharmacyWorkbenchController@searchProducts`; default behaviour for the existing search box is unchanged.

**API impact:** one additive, optional query param on an existing endpoint. Files: `PharmacyWorkbenchController.php` + the shared client helper.

---

### V. Supermarket bagging: tap-to-bag, bag-all, and qty on the till line

**UX (all client-side, no API):**
- **Tap the row/card toggles the bag** (the checkbox becomes a visual state, not a tiny target). Power users keep the checkbox.
- **"Bag all" one-tap** per stage (Bill all / bag all on this screen), plus existing per-row tick.
- **Qty stepper directly on the till line**: `[−] 2 [+]` + editable number, updating the bag total live. (Today qty editing lives in the dispense-cart modal — move it onto the till for the bill stage too; qty is carried in the existing bill payload.)
- Keyboard for speed: ↑/↓ move selection, `Space` bags/unbags, `Esc` clears selections (reuse `clearAllSelections()`), `Enter` fires the till CTA.
- Coverage/claims shown inline per till line where the server already computed them (`payable_amount`, `claims_amount`, `coverage_mode` are already returned by `search-products` and the list endpoints): e.g. `Pay ₦800 · HMO ₦1,200` chip; tapping the chip opens the existing coverage/claim detail (modal) — cashier never leaves the flow to know what the patient owes.

**API impact:** none. **Files:** `pharmacy-dispensing.js` (`renderTillBag()` line template + click handlers), CSS `.till-line` / `.till-qty`, row rendering for tap zones.

---

### VI. Stay-in-flow primary action + feedback (no context loss after Bill/Dispense)

**UX:** Make `#till-primary-cta` the single register button and never drop the cashier after an action:
- After **Bill N item(s)** succeeds: the bag clears, items move to Pending (pay) / Ready (validated) automatically on refresh; the Checkout screen stays put, till still pinned, and the scan bar is **refocused with a soft beep** so the next scan can begin immediately. (If the till was left on the Billing stage, auto-switch to Pending/Ready stage so the cashier sees where the items went — no manual tab hunting.)
- After **Review & dispense** succeeds: same — bag empties, focus returns to the scan bar, a success chime plays, and a print-slip option is offered if the store prints receipts (existing print paths).
- All failure modes surface in the till itself (inline error line under the CTA: "2 items out of stock — see rows") rather than a redirecting toast + scroll.
- Feedback primitives: 80–150 ms green flash on the added till line, short success beep, red flash + low beep on a missed scan; disable toasts for routine scan events (toastr stays for real errors).

**API impact:** none. **Files:** `pharmacy-dispensing.js` (`billPrescItems()` / dispense success handlers, focus management), small CSS keyframes.

---

### Bonus micro-improvements (pick as capacity allows)

- **Compact shelf rows** (POS density): name + qty/price + badge on one line, secondary actions under `⋯`.
- **Bag count in the shelf subtab badges** (e.g., Billing badge shows `3 in till / 12`).
- **Persist till across accidental reloads** within the session (client `sessionStorage` of `selectedItemsData` keyed by patient id), so F5 doesn't empty the bag.
- **Auto-advance to the same patient's next screen** after dispense if the queue tool is open (power feature, off by default).

---

## 4. API-change register (deliberately tiny)

| # | Change | Endpoint | Why | Needed? |
|---|---|---|---|---|
| 1 | `exact=1` mode: exact `product_code` match wins for scans | `GET /pharmacy-workbench/search-products` | deterministic scan resolution | Optional (nice) — client can still filter exact matches from the 20 results |
| 2 | (none) inline add → create request | `POST /pharmacy-workbench/create-request` | already returns `requests[].id` | Not needed |
| 3 | (verify) `product_code` present in shelf list payloads | `prescBillList` / `prescDispenseList` | lets scan-matches-shelf work purely client-side | Verify only; add to mapper if absent (~2 lines) |

Everything else in §3 is CSS + JS in `pharmacy-workbench.css`, `pharmacy-dispensing.js`, `pharmacy-core.js`, and the blade templates.

---

## 5. Build order (each slice shippable on its own)

| Phase | Slice | Work | API | Est. |
|---|---|---|---|---|
| **A** | Till always visible (desktop rail + mobile bottom sheet) | CSS + minor layout JS | — | 1–2 d |
| **B** | Inline add bar on the shelf → create-request → auto-tick → bag | JS (reuse endpoints) | — | 1 d |
| **C** | Scan input + global scan capture + feedback + New Request scan box; shared `lookupProduct()` | JS/CSS | — | 1–2 d |
| **D** | Exact-code lookup mode | controller | `exact=1` | 0.5 d |
| **E** | Tap-to-bag, bag-all, till qty stepper, coverage chip, keyboard | JS/CSS | — | 1–2 d |
| **F** | Stay-in-flow post-action behaviour + sound/flash polish | JS/CSS | — | 0.5–1 d |
| *(opt)* | Session-persisted bag, compact rows, stage-badge bag counts | JS/CSS | — | 1 d |

Order note: A and B unblock the whole "supermarket" feel; C–F layer on top. D is independent and can be done any time.

---

## 6. Acceptance checks (definition of done per slice)

1. Open any long patient list → scroll shelf → the till, its totals, and its CTA never leave the screen (desktop); on mobile the till bar is always docked.
2. Scan a product code on the Checkout screen → within ~1.5 s it is a new line on the Billing shelf **and** visible in the till under "To bill", with no tab switch and no page jump.
3. Scanning a code that's already an open shelf item ticks that item instead of duplicating.
4. On the New Request tab, scanning adds the item to the request list exactly like a search click.
5. Unknown code → audible miss + free-form fallback offered in place.
6. Qty `2*CODE` adds qty 2; till qty `[−][+]` updates the bag total live.
7. Bill N item(s) from the till → success → bag clears, focus returns to the scan bar, cashier has not left the Checkout screen.
8. No `sku`/barcode column or migration anywhere in the diff; full regression of existing search box (no `exact=1` → identical results).
9. Rx + cash flows above (Target experience) each ≤3 clicks/keys after the scan/tick.

---

## 7. Non-goals / guardrails for this pass

- **No new DB columns or tables** (no `sku`, no barcode table, no migration).
- No change to billing/dispense business rules, stock decrement, HMO validation, or free-form pricing rules.
- No new payment rails; payment/coverage screens are reused as-is.
- No rewrite of the DataTables/card rendering pipeline — improvements restyle and wrap, don't replace.
- `product_code` data hygiene (blank/duplicate codes) is a **data** backfill exercise in the existing column, separate from this UI work — flag inventory items with missing codes in a report so scanning coverage is visible.

---

## 8. Implementation status (this checkout pass)

Implemented client-side in the pharmacy workbench checkout. No DB migration, no new
endpoint, no `sku` column.

**Files changed**

- `public/js/pharmacy-dispensing.js` — scan/add bar in the checkout template; server-driven
  card attributes extended (`data-coverage-mode`, `data-product-code`, `data-unit-price`,
  `data-is-paid`, `data-is-validated`, `data-status`); `gatherSelectedItems()` now carries
  payable/claims/coverage/code per bagged item (all copied from the card's server-rendered
  attributes — nothing re-derived); till lines show qty + CASH/HMO chip + *Patient pays /
  HMO* split; till footer shows Items / Patient pays / HMO claim / Bag total; tap-a-card
  bags; `Esc` clears the bag; bill qty `−/+` persists via the existing
  `prescription/{id}/adjust-quantity` endpoint (reason auto-fills "Quantity changed at
  checkout till"); scan-gun capture (HID keyboard + Enter, `2*CODE` qty prefix, exact
  `product_code` preferred over name matches); scan of an item already on the shelf bags it
  instead of duplicating; unknown/multi-match handled inline with beep/flash; register
  pinning (`layoutPharmRegister()`, wide screens ≥1200px) keeps the till fixed over any
  page scroll; post-bill/dispense hook keeps the cashier in flow.
- `public/js/pharmacy-stock.js` — dispense cart rows now carry and display the card's
  server payable/claims/coverage; bill + dispense success call
  `pharmOnTillActionSuccess()` so the till clears and the scan bar refocuses.
- `public/css/pharmacy-workbench.css` — pinned till rail, compact dense cards, till line
  chips/splits, scan bar + result picker, flash feedback, mobile keeps the floating-cart
  fallback (till hidden <992px).

**Deviations from the plan (deliberate)**

- No `exact=1` server parameter was added; exact-code preference is done client-side over
  the existing 20-result payload. (Option D remains available later.)
- Mobile (<992px) uses the existing floating cart + review modal rather than a new
  bottom-sheet till, to keep this pass low-risk.
- Free-form "not listed" items stay on New Request (they are excluded from the billing
  shelf by design); the scan bar points cashiers there for free-form.
- Till qty steppers are enabled for **To bill** rows only, because qty changes must be
  persisted server-side before billing (no client-side price guesswork). Pending/Ready
  qty is read-only here (stock/batch logic lives in the dispense modal).

**Validation:** `node --check` passes on every first-party script under `public/js`
(63/63 files), including `pharmacy-dispensing.js` and `pharmacy-stock.js`.

### Follow-up bugfixes (PR #14, second commit)

- **Print 404 fixed** — `printPrescription()` fell back to `/pharmacy/print-prescription-slip`
  (route is `/pharmacy-workbench/print-prescription-slip`) because only two routes are
  registered in `WORKBENCH_CONFIG`. Corrected fallbacks for the prescription slip and the
  returns record-billing call.
- **Till bag decoupled from the DataTable DOM** — server-side DataTables only render the
  current page, so a redraw/paging/auto-refresh could "unpark" bagged items out of the till
  while the select-all header stayed checked. New `pharmBag` maps (keyed by stage) hold a
  snapshot of each bagged item copied from the card's server-rendered data attributes and
  are the single source of truth for `gatherSelectedItems()`; bill / dismiss / dispense /
  print now read the bag (with DOM fallbacks for older call sites). Row re-ticking after
  redraw refreshes snapshots from the cards; the select-all header now shows a tri-state
  honest to the rows on the current page.
- **drawCallback crash fixed** — `this.api().page.info()` could throw on a stale/destroyed
  DataTable instance during re-init (`recordsTotal` of undefined). All four drawCallbacks now
  go through a guarded `pharmDtDrawInfo()` and restore the till from the bag even when the
  instance is mid-teardown.
