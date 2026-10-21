# CoreHealth v2 — Billing, Split Payments, Free-Form Requests & Financial Governance Enhancements

**Date**: 2026-10-21  
**Git Release Tag**: `v2.5.1.0` (Target Release Tag — Pending Merge to Master)  
**Status**: Completed & Verified  

---

## 1. Executive Summary

This document details the architectural design, implementation, and verification of six major interconnected financial and billing capabilities introduced to CoreHealth v2:

1. **Split Payments**: Frictionless payment collection allowing patients to split a single payment across multiple channels (e.g. ₦4,000 Cash + ₦6,000 POS) while maintaining strict double-entry General Ledger balancing across multiple asset and liability accounts.
2. **Unlisted / Free-Form Billable Items**: Ability for clinicians, receptionists, and billers to request and bill for custom or unlisted products and services with customized pricing. Integrated into the shared clinical order workflow with an interactive SweetAlert prompt containing an "Is Billable" checkbox and price input.
3. **Walk-In Sales Parity**: Standardized Walk-In Sales tabs enabled across both **Reception Workbench** and **Billing Workbench**, governed by granular Hospital Configuration switches (`walkin_enable_reception`, `walkin_enable_billing`, `walkin_allow_freeform`).
4. **Enhanced My Transactions Cockpit**: High-density transaction modal for cashiers and billers displaying itemized line items on each transaction row, dynamic KPI cards for products and services, category filters (Product Only, Service Only, Product Category, Service Category, and Specific Item lookup), and cross-workbench accessibility from both Billing and Reception toolbars.
5. **Payment Method Adjustment & Audit Governance**: Built into the new *Manage Transactions* tab, allowing billers (own transactions) and accountants/admins (all transactions) to correct payment methods (e.g. Cash to POS or Single to Split) with automated reversing and replacement Journal Entries and immutable audit trails in `payment_adjustments`.
6. **Transaction Voiding & Comprehensive Reversal**: Complete voiding workflow that reverses GL journal entries, restores deducted patient deposit account balances, unlinks `ProductOrServiceRequest` items back to unbilled status, decrements nurse shift counters, and strikes through/excludes voided transactions across all financial reports, cashier summaries, receipts, and ops audit workbenches.

---

## 2. Database Schema & Architecture

### A. Migrations & Table Definitions

Four dedicated migrations maintain full compatibility across MariaDB and MySQL 8.0:

1. **`2026_10_21_000001_create_payment_splits_table.php`**:
   - Creates `payment_splits` table: `id`, `payment_id` (foreign key to `payments.id`), `payment_method` (e.g. `CASH`, `POS`, `TRANSFER`, `MOBILE`, `DEPOSIT`), `bank_id` (nullable foreign key to `banks.id`), `amount` (decimal 15,2), `reference_no` (nullable string), timestamps.
   - Adds `is_split_payment` (boolean, default false) to `payments` table.

2. **`2026_10_21_000002_add_free_form_and_walkin_settings.php`**:
   - Adds `is_free_form` (boolean, default false), `free_form_name` (nullable string), `free_form_description` (nullable text), `is_billable` (boolean, default true) to `product_or_service_requests` table.
   - Adds `walkin_biller_enabled`, `walkin_reception_enabled`, `walkin_lab_enabled`, `walkin_imaging_enabled`, `walkin_products_enabled`, `walkin_free_form_enabled`, `walkin_procedures_enabled` (booleans, default true) to `application_status` table.

3. **`2026_10_21_000003_create_payment_adjustments_table.php`**:
   - Creates `payment_adjustments` audit table: `payment_id`, `original_payment_method`, `original_bank_id`, `original_is_split`, `original_splits` (JSON), `new_payment_method`, `new_bank_id`, `new_is_split`, `new_splits` (JSON), `reversal_journal_entry_id`, `new_journal_entry_id`, `adjusted_by`, `reason` (text), `adjusted_at` (timestamp).

4. **`2026_10_21_000004_add_void_fields_to_payments.php`**:
   - Adds `voided_at` (nullable timestamp), `voided_by` (nullable foreign key to `users.id`), `void_reason` (nullable text), `void_reversal_je_id` (nullable foreign key to `journal_entries.id`) to `payments` table.

### B. Eloquent Models

- **`App\Models\PaymentSplit`**: Belongs to `Payment` (`payment_id`) and `Bank` (`bank_id`).
- **`App\Models\PaymentAdjustment`**: Audit trail model tracking adjustments, original/new snapshots, and reversing/new journal entries.
- **`App\Models\Payment`**:
  - Defines `public $pending_splits = null;` to safely transport split payloads to observers prior to child record persistence without Eloquent column insert errors.
  - Adds relationships: `splits()`, `adjustments()`, `voidedByUser()`, `voidReversalJournalEntry()`.
  - Scopes: `scopeNotVoided($q)` (excludes voided payments from financial reports and KPIs), `scopeOnlyVoided($q)`.
- **`App\Models\ProductOrServiceRequest`**:
  - Fillable attributes: `is_free_form`, `free_form_name`, `free_form_description`, `is_billable`.
- **`App\Models\ApplicationStatu`**:
  - Casts: `walkin_enable_reception` => `boolean`, `walkin_enable_billing` => `boolean`, `walkin_allow_freeform` => `boolean`.

---

## 3. General Ledger Balancing & Accounting Engine

### A. Split Payment Multi-Leg Journal Entries

When a split payment is recorded, `App\Observers\Accounting\PaymentObserver` dynamically splits the debit legs across the respective asset and liability accounts:

```
[Payment: ₦10,000 (Split: ₦4,000 Cash + ₦6,000 POS / Zenith Bank)]
-------------------------------------------------------------------------
DEBIT:  Cash on Hand (Account 1010)                        ₦4,000.00
DEBIT:  Bank Account - Zenith Bank (Account 1020 / Sub)    ₦6,000.00
CREDIT: Revenue / Accounts Receivable                     ₦10,000.00
-------------------------------------------------------------------------
TOTAL DEBITS: ₦10,000.00   |   TOTAL CREDITS: ₦10,000.00 (BALANCED)
```

Supported split channels:
- `CASH`: Debits Cash on Hand (1010).
- `POS`, `TRANSFER`, `BANK_TRANSFER`, `MOBILE`: Debits Bank Account (1020) linked to the specific selected bank.
- `DEPOSIT`, `ACC_DEPOSIT`: Debits Patient Deposits Liability (2200), drawing down the patient's advance balance.
- `STAFF`: Debits Staff Receivables (1050).
- `ORGANIZATION`: Debits Corporate Client Receivables (1060).

### B. Payment Adjustment Workflow (`PaymentReversalService::adjustPayment`)

1. Validates user authorization (billers can only adjust their own transactions within policy; accountants and admins have full hospital-wide authority).
2. Takes an immutable JSON snapshot of original payment method, bank, and split allocations.
3. Reverses the active Journal Entry via `AccountingService::reverseEntry()`, creating an offsetting posted reversal journal entry (`entry_type = 'reversal'`) and marking the original entry as `reversed`.
4. Updates payment method, bank, and `is_split_payment` on the `Payment` record.
5. Re-synchronizes `payment_splits` table rows.
6. Generates the replacement active Journal Entry via `PaymentObserver::createPaymentJournalEntry()`.
7. Records the complete audit record in `payment_adjustments`.

### C. Payment Voiding Workflow (`PaymentReversalService::voidPayment`)

1. Reverses the posted Journal Entry in the General Ledger.
2. Unlinks all associated `ProductOrServiceRequest` rows (`payment_id = null`, `removed_by = $userId`, `removed_at = now()`), safely returning unpaid items to the patient's unbilled cart.
3. If paid from patient deposit (`ACC_WITHDRAW`), automatically credits back the patient's `PatientAccount` balance and reactivates any applied `PatientDeposit`.
4. Decrements active nursing shift counters and collections (`NursingShift`).
5. Cancels associated `StaffBill` or `OrganizationBill` records.
6. Marks `payments.voided_at = now()`, `voided_by = $userId`, `void_reason = $reason`, and stores `void_reversal_je_id`.

---

## 4. Frontend & User Experience

### A. Universal Helpers (`helpers.php` & `workbench-helper.js`)

- **Name Rendering Helper (`getItemDisplayName($item)`)**:
  - Free-form items: returns `$item->free_form_name`.
  - Service items: returns `$item->service->service_name` (or fallback `Service #ID`).
  - Product items: returns `$item->product->product_name` (or fallback `Product #ID`).
  - Frontend equivalent: `window.CoreHealth.getItemDisplayName(item)`.
- **Payment Method Formatter (`formatPaymentMethod($payment)`)**:
  - Maps methods directly matching the system payment modal (`CASH` -> "Cash", `POS` -> "POS", `TRANSFER` / `BANK_TRANSFER` -> "Bank Transfer", `MOBILE` -> "Mobile Money", `BILL_TO_STAFF` -> "Bill to Staff", `BILL_TO_ORGANIZATION` -> "Bill to Organization", `ACCOUNT` -> "Patient Account", `DEPOSIT` / `ACC_DEPOSIT` -> "Account Deposit", `SPLIT` -> "Split Payment").
  - Automatically incorporates bank names for non-cash methods (e.g. `POS - Zenith Bank`, `Bank Transfer - Access Bank`).
  - Splits: returns `Split: Cash ₦4,000.00 + POS (Zenith Bank) ₦6,000.00`.
  - Frontend equivalent: `window.CoreHealth.formatPaymentMethod(tx)`.

### B. Modular JavaScript Libraries

- **`public/js/billing-split-payments.js`**:
  - Dynamic split leg builder for payment modal.
  - Validates split sum against invoice balance in real time with visual feedback.
  - Handles bank dropdown population per electronic leg.
- **`public/js/walkin-sales-shared.js`**:
  - Shared search, item addition, and cart management across Reception and Billing.
  - Interactive SweetAlert prompt for free-form requests with `Is Billable` toggle and price input.
- **`public/js/billing-my-transactions.js`**:
  - My Transactions modal with itemized line item summaries.
  - Filters: Product Only, Service Only, Product Category, Service Category, and Item search.
  - True Tender Accounting: Split is recognized as an operational mechanism, not an independent payment method. Split tender legs are accurately distributed and accounted into their respective tender methods (Cash, POS, Bank Transfer, Mobile/Deposit).
  - Terminal & Bank Breakdown: Displays live bank-specific breakdowns directly inside the POS, Transfer, and Deposit KPI cards (e.g. POS Zenith, POS UBA, Transfer Access) and on printouts.
  - Dynamic Category KPIs: Interactive pills for each Clinical/Service category and Pharmacy/Product category showing item counts and ₦ totals, clickable to immediately filter the transaction ledger.
  - Manage Transactions DataTables grid with 1-click Payment Adjustment and Void modal flows.

### C. UI & Master Layout Integration

- Added **Walk-In Sales** tab to both `resources/views/admin/reception/workbench.blade.php` and `resources/views/admin/billing/workbench.blade.php`.
- Added **My Transactions** launcher button to the Reception Workbench header toolbar with Spatie role/permission authorization check (`@if(auth()->user()->hasAnyRole(['Biller', 'Cashier', 'Admin', 'Super-Admin']) || auth()->user()->can('process payment'))`).
- Extracted shared modal to `resources/views/admin/shared/_my_transactions_modal.blade.php` and included in both workbenches.
- Added VOID banners and watermarks to `receipt_a4.blade.php` and `receipt_thermal.blade.php`.

---

## 5. Automated Test Suite & Quality Assurance

### A. Execution Commands

```bash
# Run all new Billing enhancement test suites
vendor/bin/phpunit --filter "PaymentSplitTest|FreeFormBillingTest|MyTransactionsEnhancedTest|PaymentAdjustmentAndVoidTest" tests/Feature/Billing/

# Run Billing workbench core test suite
vendor/bin/phpunit tests/Feature/BillingWorkbenchTest.php

# Run Ops Audit test suite
vendor/bin/phpunit tests/Feature/OpsAudit/OpsAuditEndpointsTest.php tests/Feature/OpsAudit/AuditWorkbenchTest.php

# Verify code style and PSR-12 compliance
make lint

# Verify standalone JavaScript syntax
node --check public/js/billing-split-payments.js
node --check public/js/walkin-sales-shared.js
node --check public/js/billing-my-transactions.js
node --check public/js/billing-core.js
node --check public/js/workbench-helper.js
```

### B. Test Verification Results

| Test Suite | File | Tests | Assertions | Status |
| :--- | :--- | :--- | :--- | :--- |
| **Split Payments** | `tests/Feature/Billing/PaymentSplitTest.php` | 2 | 18 | **PASSED** |
| **Free-Form Billing** | `tests/Feature/Billing/FreeFormBillingTest.php` | 2 | 15 | **PASSED** |
| **My Transactions & Bank KPIs** | `tests/Feature/Billing/MyTransactionsEnhancedTest.php` | 5 | 35 | **PASSED** |
| **Reception My Transactions** | `tests/Feature/Billing/ReceptionMyTransactionsTest.php` | 2 | 11 | **PASSED** |
| **Adjustment & Voiding** | `tests/Feature/Billing/PaymentAdjustmentAndVoidTest.php` | 3 | 25 | **PASSED** |
| **Billing Workbench Core**| `tests/Feature/BillingWorkbenchTest.php` | 8 | 23 | **PASSED** |
| **Full Billing Domain Suite**| `tests/Feature/Billing/` | 59 | 225 | **PASSED** |
| **Ops Audit Endpoints** | `tests/Feature/OpsAudit/OpsAuditEndpointsTest.php` | 156 | 156 | **PASSED** |
| **PSR-12 Code Style** | `make lint` | 1307 files | 0 errors | **PASSED** |
| **JavaScript Syntax** | `node --check` | 6 modules | 0 errors | **PASSED** |

---

## 6. Deployment & Configuration Guide

1. **Run Database Migrations**:
   ```bash
   php artisan migrate
   ```
2. **Hospital Settings Configuration**:
   Navigate to **Hospital Settings** -> **General / Operations** to configure:
   - `walkin_enable_reception`: Allow walk-in order booking from Reception.
   - `walkin_enable_billing`: Allow walk-in direct billing from Billing Workbench.
   - `walkin_allow_freeform`: Allow staff to enter custom unlisted products/services.
3. **Optimized Autoload Classmap**:
   ```bash
   composer dump-autoload -o
   ```
