# Ops Audit Stepped Storyboard Workflow & Visible Page Stamping Architecture

**Date**: 2026-11-01  
**Git Release Tag**: v2.6.0.58  
**Applicable Modules**: All 12 Ops Audit Modules (`store`, `billing`, `doctor`, `pharmacy`, `nursing`, `lab`, `imaging`, `reception`, `hmo`, `maternity`, `surgery`, `morgue`).

---

## 1. Executive Summary & Problem Solved

### The Operational Challenge
Auditors previously faced significant friction when navigating high-volume audit tables:
1. **Disjointed Filter Layout**: Date ranges, duty shifts, module-specific scopes, and action triggers were dispersed unevenly across page headers and flat filter bars without a logical narrative or progressive discovery.
2. **Page Reset Upon Stamping**: Whenever an auditor stamped an individual item or resolved a query on later pages (e.g., page 5 or 12), the DataTable AJAX reload defaulted to `resetPaging = true`, violently snapping the auditor back to page 1.
3. **Tedious Multi-Row Stamping**: Auditors were forced to manually click the stamp button on every single row individually, waiting for individual network confirmation modal cycles.
4. **Redundant Duplicate Filters**: Certain tab panes repeated filters already provided by the main filter bar (such as `store_id` in store batches or `cashier_id` across billing/pharmacy cashbooks).

### The Solution: Option B Progressive Stepped Storyboard
We introduced a unified, 4-step progressive ribbon across all 12 operational audit workbenches:
- **Step 1: Time Horizon (Audit Period)**: Date picker (`start_date`, `end_date`) powered by 5 instantaneous one-click horizon chips: `Today`, `Yest`, `7 Days`, `30 Days`, and `This Month`.
- **Step 2: Duty Window (Shift & Stream)**: Unified duty shift selector (`All`, `AM (till 1:00 PM)`, `PM (1:00 PM - 6:00 PM)`, `Night (6:00 PM onwards)`) combined with mutually exclusive Care Streams (`All`, `General / Walk-in`, `Inpatient / Admitted`, `Insured / Schemes`).
- **Step 3: Advanced Scoping (Module Entity Scopes)**: Dedicated slot for domain-specific entity filtering (`store_id`, `doctor_id`, `ward_id`, `cashier_id`, `hmo_id`, `hmo_scheme_id`), complemented by an active scope counter badge and quick clear action.
- **Step 4: Audit Cockpit Actions**:
  - `Apply Filters`: Synchronizes all active tables and KPI stat bars.
  - `Stamp Visible Page` (`#btnStampVisiblePage`): High-efficiency one-click action that scans visible rows, prompts for bulk confirmation, immediately mutates button UI to audited state, and executes batch stamping via `/audit-workbench/audit/mark/bulk-stamp`.
  - `Bulk Stamp`: Opens query-level bulk stamp modal.
  - `Print`: Prints active tab data with unified styling.
  - Contextual Actions: Cashier Transactions Console and Duty Shift Handover Certificate.

---

## 2. Core Architectural Components

### A. Progressive Stepped Storyboard Ribbon Partial
- **Location**: `resources/views/admin/ops_audit/partials/_stepped_story_bar.blade.php`
- Enforces responsive, high-density layout using Bootstrap 5 grid tokens and hospital branding CSS variables (`--hospital-primary`, `--hospital-primary-rgb`).
- Injects Step 3 module filters dynamically via Blade `@component` or `@include` slots while preserving form parameter binding under `#ops_audit_filter_form`.

### B. High-Density Standalone Stylesheet
- **Location**: `public/css/ops-audit.css`
- Complies strictly with the 10-line inline style constraint by extracting 250+ lines of monolithic CSS from `layout.blade.php`.
- Integrates the Dynamic Hospital Theme CSS Variable Provider Mechanism with graceful fallbacks:
  ```css
  .ops-story-ribbon {
      background: #ffffff;
      border: 1px solid rgba(var(--hospital-primary-rgb, 1, 27, 51), 0.12);
      border-radius: 8px;
      padding: 12px 14px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  }
  ```

### C. Client-Side Controller & Pagination Preservation
- **Location**: `public/js/ops-audit.js`
- **Non-Resetting Pagination Helper**:
  ```javascript
  window.opsReloadActiveTables = function(resetPaging) {
      if (typeof resetPaging === 'undefined') resetPaging = false;
      // Iterates through active tab DataTables and reloads with resetPaging flag
  };
  ```
- **Visible Page Stamping Engine (`stampCurrentPage`)**:
  - Scans active table for visible unstamped buttons (`.audit-tick-btn:visible`).
  - Extracts model type and record IDs from `data-model-type` and `data-model-id` attributes stamped by `OpsAuditBaseController::renderAuditCell()`.
  - Dispatches non-blocking batch POST to `/audit-workbench/audit/mark/bulk-stamp`.
  - Calls `window.opsReloadActiveTables(false)` upon completion, guaranteeing the auditor remains on the exact same page.

### D. Duplicate Page-Level Filter Elimination
- Main ribbon provides global horizon, shift, care stream, and primary module scopes.
- Page-level (tab-level) filters are strictly limited to tab-specific dimensions (e.g. `status`, `urgency`, `payment_method`, `bank_id`, `clinic_id`, `entity`).
- Redundant dropdowns (e.g. secondary `store_id` in batches pane, duplicate `cashier_id` in payment filters) have been completely purged or conditionally suppressed via `@if(empty($hideCashier) && !isset($users) && !isset($cashiers))`.

### E. Shift Time Range Indicators & Default Custom Time Window Controls
- **Preset Boundaries**:
  - `All`: 24 Hours (`00:00` - `23:59`)
  - `AM`: AM Duty till 1:00 PM (`00:00` - `12:59`)
  - `PM`: Afternoon / Late PM Duty (`13:00` - `17:59`)
  - `Night`: Night Duty (`18:00` - `07:59` cross-midnight)
- **Dynamic Badge & Custom Time Input**:
  - Displays the active time range badge (`#opsShiftTimeDisplay` e.g., `00:00 - 23:59`) and status subtext (`#opsShiftRangeBadge`).
  - Directly exposes `shift_start` (`Time From`) and `shift_end` (`Time To`) `<input type="time">` controls visible by default in Step 2.
  - Automatically identifies whether selected times match standard shift presets or dynamically highlights the `Custom` indicator button (`#btnShiftCustom`).
  - Automatically persists selections to `sessionStorage` and reloads active tables with zero layout jump or pagination disturbance.
  - Backend `OpsAuditBaseController::applyShiftFilter` dynamically pads 5-character `HH:MM` inputs to `:00` and `:59` second precision and handles cross-midnight windows seamlessly.

### F. Live Audit Scope Context Banner & Daily Operations Story KPI Architecture
- **Live Audit Scope Context Banner**:
  - Located at the base of `.ops-story-ribbon`, this persistent banner (`#opsScopeNarrative` and `#opsScopeExplainerText`) provides an instantaneous, human-readable summary of the exact query parameters and operational rationale.
  - Dynamically distinguishes between single-shift handover sign-off (e.g. today's PM duty till balancing) and multi-day shift pattern auditing (e.g. isolating afternoon peak hours across the month).
  - Accompanied by inline context hints in Step 1 (`#opsHorizonContext`) and Step 2 (`#opsShiftContext`).
- **Daily Operations Story KPI Architecture**:
  - All KPI cards across all 12 modules are rendered via `window.renderOpsKpis()` with an overarching `DAILY OPERATIONS TELEMETRY & ACCOUNTABILITY STORY` header strip illustrating the chronological flow:
    $$\text{Demand} \;\longrightarrow\; \text{Execution} \;\longrightarrow\; \text{Settlement} \;\longrightarrow\; \text{Exceptions \& Audit}$$
  - Each individual KPI card is enhanced with:
    - **Story Stage Tag**: Chronological stage indicator (e.g. `1. Demand`, `2. Authorization`, `3. Execution`, `4. Variance`, `5. Declined`, `1. Inflow`, `2. Till Cash`).
    - **Context Icon**: Visual iconography reflecting the operational action.
    - **Contextual Subtext**: Human-readable operational explanation beneath the metric label (e.g. *"Requisitions initiated from units"*, *"Physical stock issued & consumed"*, *"Balance awaiting replenishment"*).

---

## 3. Verification & Test Suite

### PHPUnit Test Coverage
- **Feature Test**: `tests/Feature/OpsAudit/OpsAuditSteppedStoryboardTest.php`
  - Validates that all 12 Ops Audit module views render with `#ops_audit_filter_form`, `.ops-story-ribbon`, `#btnStampVisiblePage`, and standalone assets (`css/ops-audit.css`, `js/ops-audit.js`).
  - Asserts that no duplicate `store_id` or `cashier_id` dropdowns exist between the ribbon and tab panes.
  - Verifies `/audit-workbench/audit/mark/bulk-stamp` JSON endpoint stability.
- **Full Ops Audit Domain Suite**:
  - `vendor/bin/phpunit tests/Feature/OpsAudit/`: 194 tests, 319 assertions passing with 0 failures.

### Code Style & Asset Verification
- `node --check public/js/ops-audit.js`: Passed with 0 syntax errors.
- `composer lint`: Passed with 0 errors across 1,322 repository files.
