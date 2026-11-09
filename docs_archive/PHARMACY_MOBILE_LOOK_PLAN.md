# Pharmacy Checkout — Mobile Look & Feel Plan (patient header, top buttons, DataTable cards)

**Status:** Implemented — shipped with the "Mobile look & feel" commit (see the todo log in
`PHARMACY_SUPERMARKET_CHECKOUT_PLAN.md`); this file remains the reference for what was built.
**Important caveat:** this session has no vision, so I could not visually read the attached
screenshot. The plan below is grounded in the real code that renders that screen
(`resources/views/admin/pharmacy/workbench.blade.php`, the injected checkout template in
`public/js/pharmacy-dispensing.js`, and `public/css/pharmacy-workbench.css`). If one area
below doesn't match what you saw, tell me and I'll adjust.

---

## 1. What renders on that screen today (inventory)

**Top bar (`workspace-navbar`)** — `workbench.blade.php` lines ~146–165
- Left: `Back to Search` (mobile), right: `Toggle Search` / **Contacts** / **Price List** /
  **Clinical Context** buttons, each a labelled pill.
- `@media (max-width: 767px)` forces `.workspace-navbar { display:flex }` and shows
  back/search buttons; there is **no mobile rule** shrinking Contacts / Price List /
  Clinical Context, so four labelled pills + back can overflow ~360px screens or wrap.

**Patient header (`#patient-header` / `.patient-header-top`)** — blade lines ~205–230 + JS `displayPatientInfo()`
- Gradient full-width band, `padding: 1.5rem`, `.patient-name` at **1.75rem**, inline
  **Alerts** danger button, then `.patient-meta` as a single **flex row** (`File · Age ·
  Gender · HMO name · HMO No`, gap 1.5rem), the **Account Balance** card on the right and
  a **"more biodata"** expand pill — all inside one `space-between` row.
- Expanded biodata is `.patient-details-grid` with `repeat(auto-fit, minmax(250px,1fr))`
  → single column on phones.
- There are **no `@media` overrides** for the patient header below 992px.

**Checkout stage (`pharm-checkout` = shelf + till)**
- `.workspace-tab-content` `max-height: calc(100vh - 250px)` on phones; content scrolls.
- Scan/add bar, 4 sub-tabs **Billing / Pending / Ready / History** (nav-tabs), DataTable
  **cards** (`.presc-card`), and the till (now a mobile bottom pill/sheet from the last
  change).

**Cards (`renderPrescCardPharmacy()`)** — desktop-format inside a 2-column DataTable
- Col 1 = 40px checkbox; col 2 = full card: title + `[code]`, price + status badges,
  body rows (Dose/Freq, Qty), HMO/pay–claim/tariff boxes, price-override/adaptation/qty
  banners, stock-by-store list, meta (`By … Requested …`), and up to 3 action buttons
  (Adapt / Adjust Qty / Adjust Price). This is a lot of vertical real estate per row and
  it does not reflow for a 360–430px viewport.

---

## 2. Target mobile experience (what we're aiming for)

Cashier holds the phone in one hand: a **slim context header**, a **thumb-reachable scan
bar**, **dense tap-to-bag cards**, and the till **pill → bottom sheet** already in place.
Everything fits without horizontal scroll and without the patient header eating a third of
the screen.

---

## 3. Proposed mobile look (3 areas + flow)

### A. Patient header → compact "identity strip"
- **Shrink the band:** padding `1.5rem → ~0.75–0.9rem`; name `1.75rem → clamp(1.15rem,
  4.5vw, 1.4rem)` with `word-break`; keep it one line, truncate gracefully.
- **Row 1 (identity):** name + Alerts. Alerts becomes an **icon pill with a count dot**
  (title keeps the label) instead of a wide red button inline with the name.
- **Row 2 (meta as chips):** replace the single flex row with **wrapped mini-chips**
  (`File: 12345 · 34y · F/M`), each truncated; on very small screens show only
  **File + Age + Gender**, and move **HMO name / HMO No** into the expandable biodata.
- **Account balance:** becomes a **compact chip** beside the name (smaller value, `1rem`)
  rather than a wide card fighting for the row.
- **"more biodata" expand:** keep as a slim, full-width chevron strip at the bottom of the
  band (`⋮ more biodata`), chevron rotates; expanded grid becomes single-column with
  tighter cards.
- **Alerts region** (`.sticky-header-alerts`, currently scrolls inside the gradient):
  cap at ~44px, or move below the band onto white when there are alerts, so the gradient
  never grows tall.
- Selectors: `.patient-header`, `.patient-header-top`, `.patient-name`, `.btn-expand-patient`,
  `.patient-account-balance`, `.patient-meta`, `.btn-manage-alerts`. File: `pharmacy-workbench.css`
  (new `<992px` rules) — no markup change needed except optionally an id on Alerts count.

### B. Top buttons (`workspace-navbar`) → icon row on phones
- `<576px`: **Back** becomes icon+short text ("Back"); Contacts, Price List, Clinical
  Context collapse to **icon-only round buttons** (tooltips/title kept; mdi icons already
  present). Widths shrink to ≤40px each; the row never wraps to two lines.
- 576–767px: labels can remain but smaller; allow `flex-wrap` with balanced two-per-row.
- Unify the odd 767/768/991/992 breakpoints for this screen: single **<992px mobile mode**
  for the checkout, with a `<576px` phone tier inside it.

### C. DataTable cards → card-first, details-on-demand
The tables are server-side DataTables with only two columns (checkbox + card), so we can
restyle aggressively with CSS **without touching the ajax/render pipeline**:
- **Checkbox:** first column shrinks `40px → 28px`; on phones the checkbox can sit flush
  top-left of the card (the whole card is already tap-to-bag from the last change).
- **Card header:** title (0.9rem max, one line, ellipsis) + `[code]` on its own small line;
  **price on its own right-aligned line** with status badges underneath (never side-by-side
  with a long drug name).
- **Body:** Dose/Freq + Qty become two compact **chips** on one row instead of two stacked
  lines. HMO/pay-claim info stays as one compact colored line.
- **Long blocks collapse:** the verbose areas (billing-estimate/tariff box, stock-by-store,
  adaptation/qty/price-override banners) go behind a **"Details ▾" toggle** that is
  **collapsed by default on phones** and expanded on tap — one card then reads in ~3 lines
  instead of a full screen.
- **Actions:** the three text buttons become a **single row of icon buttons** at the card
  bottom (Adapt ⇄ · Qty # · Price ₦) with `title` tooltips — no wrapping, ~30px targets.
- **Meta:** collapse `By/Requested/Dispensed` to one truncated line on phones (tap
  Details for the rest).
- Keep `node`-safe: all of this is `.pharm-shelf` CSS under `@media (max-width: 991.98px)`
  and one small toggle in `renderPrescCardPharmacy()` (add a `details` block wrapper) —
  no DataTable config changes.

### D. Overall flow on a phone
1. `workspace-navbar` slim icon row → patient identity strip (≈3 lines max) → scan/add bar.
2. Stage sub-tabs (Billing/Pending/Ready/History) become **sticky, horizontally
   scrollable pills** (full-width swipe) so the cashier never loses the stage while
   scrolling cards.
3. Cards list scrolls with proper bottom padding so the till pill/sheet never covers the
   last card: add `padding-bottom: calc(96px + env(safe-area-inset-bottom))` to
   `.workspace-tab-content` on mobile.
4. Till = existing **bottom pill** (count + total) → tap expands the sheet (header count +
   scrollable bag + totals + Bill/Print/Dismiss/Clear). The expanded sheet gets a light
   scrim/dim behind it so the cards underneath read as "background".

---

## 4. Open questions to confirm against the screenshot

1. Which of these is "the button above the header" you meant — the top-bar **Back / Toggle
   search / Clinical Context** row, or the **Alerts / more biodata** controls inside the
   patient band?
2. Is the pain mainly the **header taking too much vertical space**, or the **cards being
   too tall / crowded**, or **horizontal overflow** somewhere?
3. Preferred phone breakpoint — treat <992px as mobile (matches the till change) or keep
   phone-tier at <576–768px?

---

## 5. Build order (each slice independently shippable)

| # | Slice | Work | Touches |
|---|---|---|---|
| 1 | Compact identity-strip header | CSS only (≤992px rules + Alerts chip + balance chip) | CSS |
| 2 | Icon-row top buttons + unified breakpoints | CSS only | CSS |
| 3 | Card-first CSS compaction (chips, price/status stack, icon actions) | CSS only | CSS |
| 4 | "Details ▾" collapse wrapper + per-card toggle | 1 JS edit in renderPrescCardPharmacy + CSS | JS + CSS |
| 5 | Sticky scrollable stage pills + bottom clear-padding + sheet scrim | CSS + small layout JS | CSS/JS |

---

## 6. Acceptance checks (mobile, ≤430px width)

- Patient name + essential meta fit one screen-width without wrap/overflow; header height
  roughly halves.
- Contacts / Price List / Clinical Context are reachable (icon buttons) with no horizontal
  scroll and no two-row navbar.
- A billing card shows title, [code], price, status, dose/qty chips and tap-to-bag in
  ≈3–4 lines; verbose blocks hidden behind Details.
- Cards remain tappable/baggable; select-all header checkbox still works; paging + the
  till bag persistence from the earlier fixes still function.
- No horizontal page scroll at 320px; last card is never hidden behind the till pill.
