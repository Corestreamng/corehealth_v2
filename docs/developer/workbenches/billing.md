# Billing & Revenue Cycle Developer Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | ACCOUNTS | BILLER</span>

The **Billing & Revenue Cycle** subsystem governs financial point-of-sale processing, multi-tender transactions, patient deposits/wallets, and automated General Ledger journal entries across CoreHealth v2.

---

## 1. Architectural Foundations

### Modular Workbench Standard
- **Blade Master View**: `resources/views/admin/billing/workbench.blade.php` extends `admin.layouts.app`.
- **Modals Partial**: `resources/views/admin/billing/partials/_modals.blade.php`.
- **Scripts Partial**: `resources/views/admin/billing/partials/_scripts.blade.php` exports `window.WORKBENCH_CONFIG` with server-side routes and session IDs.
- **Standalone JS Engine**: Built across modular files under `public/js/`:
  - `billing-core.js`: Patient loading, item selection, UI state management.
  - `billing-payments.js`: Single-tender checkout, receipt formatting.
  - `billing-split-payments.js`: Multi-leg split tender validation and calculation.
  - `billing-my-transactions.js`: Cashier shift reconciliation, method adjustments, transaction voiding.
  - `billing-hmo.js`: Queue data tables, filtering, reports.

---

## 2. Double-Entry General Ledger Invariant

Whenever a payment is processed through `BillingWorkbenchController@processPayment`, the system relies on the **`PaymentObserver`** to post balanced double-entry journal entries:

```
[DEBIT LEGS]                                  [CREDIT LEGS]
DR 1010 Cash in Hand (if Cash tendered)        CR 4010 Laboratory Revenue
DR 1031 POS Terminal (if POS card swiped)      CR 4020 Pharmacy Sales
DR 1020 Bank Account (if Bank Transfer)        CR 4030 Consultation Fees
DR 2050 Patient Deposits (if Wallet deducted)  CR 4040 Radiology Revenue
DR 1120 HMO Receivables (if Claim accrued)     CR 4050 Surgery & Theatre Fees
DR 1130 Corporate Receivables (if Org billed)  CR 4060 Inpatient Ward Revenue
---------------------------------------------------------------------------------
TOTAL DEBITS                                == TOTAL CREDITS
```

> [!CAUTION]
> **Observer Integrity**: NEVER bypass Eloquent models using raw DB queries (`DB::table('payments')->insert(...)`). Doing so bypasses `PaymentObserver`, breaking General Ledger balancing and causing Trial Balance errors.

---

## 3. Dedicated Developer Subpages

- 👉 **[Billing API Contracts & Route Specifications](/developer/workbenches/billing/api-contracts)**: Exhaustive JSON request payloads, query parameters, response structures, and HTTP error codes.
- 👉 **[Billing Database Schema & Observers Reference](/developer/workbenches/billing/database-and-models)**: Entity-Relationship diagrams, table column definitions, Eloquent models, and observer hooks.

---

## 4. Automated Testing Contracts

Run the dedicated Billing Workbench test suites:
```bash
# Sandboxed test execution
vendor/bin/phpunit tests/Feature/Billing/BillingWorkbenchTest.php
vendor/bin/phpunit tests/Feature/Billing/SplitPaymentTest.php
```

👉 **[Read Billing User Guide](/user-guides/workbenches/billing)**
