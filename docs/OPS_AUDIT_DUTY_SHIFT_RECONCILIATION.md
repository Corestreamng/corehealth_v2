# CoreHealth v2 — Ops Audit Duty Shift Service Handover & Care Stream Reconciliation

**Date**: 2026-10-25  
**Git Release Tag**: `v2.6.0.54`  
**Status**: Completed & Verified  

---

## 1. Executive Summary & HOD Audit Mandate

In response to the HOD Internal Audit operational mandate:
> *"How do we get the summary print out for each service e.g. print out for drugs (general, inpatient, NHIS) for AM, late and night duties separately?"*

CoreHealth v2 Ops Audit has been extended with an **Executive Duty Shift Service Handover & Reconciliation Subsystem**. This feature introduces a 1-page A4 printable shift handover certificate, dynamic care-stream segmentation, calibrated shift boundary filtering, and seamless access to the Cashier Transactions Console directly within Ops Audit.

---

## 2. Duty Shift Operational Boundaries

Hospital billing shifts follow operational shift handovers calibrated to:
1. **AM Duty (Morning)**: Beginning of day until **1:00 PM** (`00:00:00` to `12:59:59`).
2. **Late / PM Duty (Afternoon)**: **1:00 PM** until **6:00 PM** (`13:00:00` to `17:59:59`).
3. **Night Duty (Overnight)**: **6:00 PM** onwards (`18:00:00` to `23:59:59` and `00:00:00` to `07:59:59` cross-midnight).
4. **All Shifts**: Full-day 24-hour handover view.

These boundaries are standardized across `OpsAuditBaseController::applyShiftFilter()`.

---

## 3. Care Stream Segmentation Architecture

Clinical revenue and volume are segregated into 3 mutually exclusive care streams:

### A. General (Outpatient Walk-in / Self-Pay)
- Patients not on admission (`admission_request_id IS NULL` and no active admission stay).
- Non-HMO patients (`hmo_id IS NULL` and `claims_amount <= 0`).
- Represents direct out-of-pocket revenue collected for ambulatory services.

### B. Inpatient (Wards / Admissions)
- Admitted patients (`admission_request_id IS NOT NULL` or active admission window `created_at >= admission_date AND created_at <= discharge_date`).
- Non-HMO private admissions.
- Captures inpatient pharmacy, ward round investigations, and daily bed fees.

### C. Insured / Schemes Pool (Comprehensive Insured Patients Pool)
- Replaces narrow "NHIS" terminology with an inclusive designation representing all managed care populations:
  - **National Health Insurance Authority (NHIA / Formal Sector Schemes)**.
  - **State Social Health Insurance Agencies (e.g. PLASCHEMA, KADCHMA, LASHMA, etc.)**.
  - **Private Health Maintenance Organizations (HMOs)**.
  - **Corporate Retainerships & Institutional Sponsors**.
- Filter accepts aliases: `'nhis'`, `'hmo'`, `'insured'`, and `'schemes'`.
- Provides dual revenue tracking:
  - **Insured Claims AR**: The insurance receivable portion billed to the scheme/HMO.
  - **Insured Co-pay**: The cash or out-of-pocket co-payment paid by the enrollee at cashier intake.

### D. Revenue Reconciliation Identity
Every billable item is attributed to exactly one stream:
$$\text{Total Gross Billed} = \text{General} + \text{Inpatient} + \text{Insured Claims AR} + \text{Insured Co-pay}$$
$$\text{Total Volume} = \text{General Count} + \text{Inpatient Count} + \text{Insured Enrollee Count}$$

---

## 4. Cashier Tender Handover & Deficit Accounting

The Tender Reconciliation section details the physical cash drawer and electronic payments:
- **Cash Inflows**: Gross positive payments collected through bill settlements and patient deposits.
- **Refunds & Withdrawals (Disbursed)**: Cash disbursed out of the drawer for deposit refunds/withdrawals (transaction type `ACC_WITHDRAW` or negative total payments).
- **Physical Cash in Till**: Net currency present in the cash drawer at handover.
  $$\text{Net Cash in Till} = \text{Gross Cash Inflows} - \text{Refunds \& Withdrawals Disbursed}$$
- **Electronic Tenders**: Broken down by POS terminal acquiring bank (e.g. Stanbic POS, Zenith POS) and Bank Transfer ledgers.
- **Net Handover**: Reconciled net physical and electronic tenders.
- **Secondary Settlement Summary**: Reflects deposit disbursements, staff credits, corporate retainership credits, and accrued insured claims AR.

---

## 5. Tri-Party Accountability Handover Certification

The A4 printable report incorporates 3 accountability signature blocks:
1. **Outgoing Duty Officer**: Attests to exact reconciliation of billed services and drawer contents.
2. **Incoming Duty Officer**: Acknowledges physical inspection of cash-in-till and POS merchant slips upon takeover.
3. **Internal Audit / HOD Certification**: Certifies ledger concordance against the CoreHealth transactional database.

---

## 6. Ops Audit Workbench & Transactions Console Enhancements

1. **Transactions Console Integration**: Embedded `#myTransactionsModal` and `billing-my-transactions.js` into Ops Audit layout (`layout.blade.php`), enabling auditors to inspect cashier transactions without leaving the audit workspace.
2. **Date-Time Precision**: Modal inputs upgraded to `datetime-local` (`#my-trans-from-date`, `#my-trans-to-date`), allowing exact operational shift cutoffs (e.g. `2026-07-01T00:00` to `2026-07-01T13:00`).
3. **Audit Cashier Scoping**: When accessed in an audit/accounting role, the modal renders a cashier filter (`#my-trans-cashier-filter`) populated with active billers, allowing auditors to inspect facility-wide transactions ("All Cashiers") or isolate an individual cashier.
4. **Refund & Withdrawal Visibility**: DataTables and printable reports highlight refund vouchers with `<span class="badge bg-danger">REFUND / WITHDRAWAL</span>` and red negative amounts (`-₦...`). Summary headers feature a dedicated **Refunds & Disbursed Payouts** KPI card alongside **Gross Cash Inflow**.
5. **Care Stream Filter Pills**: Added interactive pills (`All`, `General`, `Inpatient`, `Insured / Schemes`) across Billing and Pharmacy audit tabs.
6. **Shift Handover Button**: Directly launches the Duty Shift Handover printout formatted for standard A4 printing (`/ops-audit/shift-service-summary`).

---

## 7. Testing & Quality Assurance

- **PHPUnit Feature Tests**:
  - `tests/Feature/Billing/OpsAuditShiftServiceSummaryTest.php`: Verifies route accessibility, JSON service matrix structure, shift boundary calibration, insured care stream aliases (`insured`, `schemes`), refunds in tender reconciliation, and print view compilation.
  - `tests/Feature/Billing/MyTransactionsPerformanceTest.php`: Verifies wide-range SQL summarization, datetime filtering, audit cashier scoping, refund KPI computation, and server-side DataTables pagination.
- **Code Style**: Strictly PSR-12 compliant via `tools/vendor/bin/php-cs-fixer`.
- **JS Syntax**: Standalone modules verified via `node --check`.
- **Database Target**: MySQL 8.0 / MariaDB compatibility.
