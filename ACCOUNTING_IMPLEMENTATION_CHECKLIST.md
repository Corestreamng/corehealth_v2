# ACCOUNTING SYSTEM ENHANCEMENT - IMPLEMENTATION CHECKLIST

**Reference Document:** ACCOUNTING_SYSTEM_ENHANCEMENT_PLAN.md  
**Start Date:** January 31, 2026  
**Estimated Duration:** 17 weeks

---

## PHASE 0: SYSTEM INTEGRATION VALIDATION (Week 0) ⭐ CRITICAL
**Reference:** Plan Section 7 - Phase 0, Appendix C

### 0.1 Audit Existing Observers
**Reference:** Plan Section 8.3, Executive Summary

| Task | File | Status | Validated |
|------|------|--------|-----------|
| Review PaymentObserver.php | app/Observers/Accounting/PaymentObserver.php | ⬜ | ⬜ |
| - Verify creates journal entry on payment created | | | |
| - Verify metadata: patient_id, service_id, product_id, hmo_id, category | | | |
| - Test with sample payment | | | |
| Review ExpenseObserver.php | app/Observers/Accounting/ExpenseObserver.php | ⬜ | ⬜ |
| - Verify creates entry when status='approved' | | | |
| - Verify supplier_id, category metadata | | | |
| - Verify supplier sub-account creation | | | |
| Review PurchaseOrderObserver.php | app/Observers/Accounting/PurchaseOrderObserver.php | ⬜ | ⬜ |
| - Verify creates entry when status='received' | | | |
| - Verify DEBIT Inventory (1300), CREDIT AP (2100) | | | |
| - Verify supplier sub-account creation | | | |
| Review PayrollBatchObserver.php | app/Observers/Accounting/PayrollBatchObserver.php | ⬜ | ⬜ |
| - Verify payroll entries created | | | |
| Review HmoRemittanceObserver.php | app/Observers/Accounting/HmoRemittanceObserver.php | ⬜ | ⬜ |
| - Verify HMO payment entries | | | |

### 0.2 Validate Report Journal Entry Centricity
**Reference:** Plan Section 8.7 - Report Integration

| Report | Query Uses JE Only | Status |
|--------|-------------------|--------|
| Trial Balance | ⬜ | ⬜ |
| Profit & Loss | ⬜ | ⬜ |
| Balance Sheet | ⬜ | ⬜ |
| Cash Flow Statement | ⬜ | ⬜ |
| General Ledger | ⬜ | ⬜ |
| Bank Statement | ⬜ | ⬜ |
| AR Aging | ⬜ | ⬜ |
| AP Aging | ⬜ | ⬜ |

### 0.3 Balance Sheet Reconciliation
**Reference:** Plan Appendix C - Validation Queries

```sql
-- Run this query - must return 0 rows
SELECT a.code, a.name,
       (SELECT COALESCE(SUM(debit_amount - credit_amount), 0) 
        FROM journal_entry_lines WHERE account_id = a.id) as je_balance,
       COALESCE(a.current_balance, 0) as stored_balance
FROM accounts a
WHERE ABS(COALESCE((SELECT SUM(debit_amount - credit_amount) 
                    FROM journal_entry_lines WHERE account_id = a.id), 0) 
          - COALESCE(a.current_balance, 0)) > 0.01;
```

| Check | Expected | Actual | Status |
|-------|----------|--------|--------|
| Account balances = JE sums | 0 rows | | ⬜ |
| All payments have JE | 0 missing | | ⬜ |
| All expenses have JE | 0 missing | | ⬜ |
| All received POs have JE | 0 missing | | ⬜ |

### 0.4 Create Observer Template
**Reference:** Plan Section 8.3

- [ ] Create observer template file
- [ ] Document required methods
- [ ] Document metadata requirements
- [ ] Create test protocol

---

## PHASE 1: BANK MANAGEMENT & DAILY CASH (Weeks 1-2) ⭐ CRITICAL
**Reference:** Plan Sections 1, 6.1, 6.6, 6.7, 6.14

### 1.1 Enhance Banks Table
**Reference:** Plan Section 1.2 - Phase 1: Database Enhancement

**Migration: enhance_banks_table.php**
```php
Schema::table('banks', function (Blueprint $table) {
    $table->decimal('current_balance', 15, 2)->default(0);
    $table->date('last_statement_date')->nullable();
    $table->decimal('last_statement_balance', 15, 2)->nullable();
    $table->integer('bank_statement_day')->default(25);
    $table->decimal('overdraft_limit', 15, 2)->default(0);
    $table->enum('bank_type', ['current', 'savings', 'fixed_deposit'])->default('current');
    $table->string('swift_code')->nullable();
    $table->string('branch')->nullable();
    $table->string('contact_person')->nullable();
    $table->string('contact_phone')->nullable();
});
```

| Column | Type | Default | Validated |
|--------|------|---------|-----------|
| current_balance | decimal(15,2) | 0 | ⬜ |
| last_statement_date | date | null | ⬜ |
| last_statement_balance | decimal(15,2) | null | ⬜ |
| bank_statement_day | integer | 25 | ⬜ |
| overdraft_limit | decimal(15,2) | 0 | ⬜ |
| bank_type | enum | 'current' | ⬜ |
| swift_code | string | null | ⬜ |
| branch | string | null | ⬜ |
| contact_person | string | null | ⬜ |
| contact_phone | string | null | ⬜ |

### 1.2 Create BankService
**Reference:** Plan Section 8.2

**File: app/Services/Accounting/BankService.php**

| Method | Parameters | Returns | Validated |
|--------|------------|---------|-----------|
| getAll() | - | Collection | ⬜ |
| getById(int $id) | $id | Bank | ⬜ |
| calculateBalance(Bank $bank) | $bank | float | ⬜ |
| getBalanceFromJournalEntries(int $accountId) | $accountId | float | ⬜ |
| getBankDashboardData() | - | array | ⬜ |
| syncBalanceFromJournalEntries(Bank $bank) | $bank | void | ⬜ |

### 1.3 Bank Dashboard
**Reference:** Plan Sections 1.2, 8.8

**Controller: app/Http/Controllers/Accounting/BankController.php**

| Method | Route | View | Validated |
|--------|-------|------|-----------|
| index() | GET /accounting/banks | accounting.banks.index | ⬜ |
| show(Bank $bank) | GET /accounting/banks/{bank} | accounting.banks.show | ⬜ |
| edit(Bank $bank) | GET /accounting/banks/{bank}/edit | accounting.banks.edit | ⬜ |
| update(Request, Bank) | PUT /accounting/banks/{bank} | redirect | ⬜ |

### 1.4 Petty Cash Tables
**Reference:** Plan Section 6.7

**Migration: create_petty_cash_tables.php**

**Table: petty_cash_funds**
| Column | Type | Nullable | FK | Validated |
|--------|------|----------|----| ---------|
| id | bigint | NO | PK | ⬜ |
| fund_name | string | NO | - | ⬜ |
| account_id | bigint | NO | accounts.id | ⬜ |
| custodian_user_id | bigint | NO | users.id | ⬜ |
| department_id | bigint | YES | departments.id | ⬜ |
| fund_limit | decimal(15,2) | NO | - | ⬜ |
| transaction_limit | decimal(15,2) | NO | - | ⬜ |
| requires_approval | boolean | NO | - | ⬜ |
| status | enum | NO | - | ⬜ |
| created_at | timestamp | YES | - | ⬜ |
| updated_at | timestamp | YES | - | ⬜ |

**Table: petty_cash_transactions**
| Column | Type | Nullable | FK | Validated |
|--------|------|----------|----| ---------|
| id | bigint | NO | PK | ⬜ |
| fund_id | bigint | NO | petty_cash_funds.id | ⬜ |
| journal_entry_id | bigint | YES | journal_entries.id | ⬜ |
| transaction_type | enum | NO | - | ⬜ |
| transaction_date | date | NO | - | ⬜ |
| voucher_number | string | NO | UNIQUE | ⬜ |
| description | text | NO | - | ⬜ |
| amount | decimal(15,2) | NO | - | ⬜ |
| category | string | NO | - | ⬜ |
| requested_by | bigint | NO | users.id | ⬜ |
| approved_by | bigint | YES | users.id | ⬜ |
| receipt_number | string | YES | - | ⬜ |
| status | enum | NO | - | ⬜ |
| created_at | timestamp | YES | - | ⬜ |
| updated_at | timestamp | YES | - | ⬜ |

**Table: petty_cash_reconciliations**
| Column | Type | Nullable | FK | Validated |
|--------|------|----------|----| ---------|
| id | bigint | NO | PK | ⬜ |
| fund_id | bigint | NO | petty_cash_funds.id | ⬜ |
| reconciliation_date | date | NO | - | ⬜ |
| expected_balance | decimal(15,2) | NO | - | ⬜ |
| actual_cash_count | decimal(15,2) | NO | - | ⬜ |
| variance | decimal(15,2) | NO | - | ⬜ |
| notes | text | YES | - | ⬜ |
| reconciled_by | bigint | NO | users.id | ⬜ |
| created_at | timestamp | YES | - | ⬜ |
| updated_at | timestamp | YES | - | ⬜ |

### 1.5 PettyCashObserver
**Reference:** Plan Section 6.7 - Observer Implementation

**File: app/Observers/Accounting/PettyCashObserver.php**

| Method | Trigger | Journal Entry | Validated |
|--------|---------|---------------|-----------|
| updated() | status='approved' | Create entry | ⬜ |
| - disbursement | | DEBIT Expense, CREDIT Petty Cash | ⬜ |
| - reimbursement | | DEBIT Petty Cash, CREDIT Bank | ⬜ |
| Store journal_entry_id | After entry | Update transaction | ⬜ |

### 1.6 Inter-Account Transfers
**Reference:** Plan Section 6.14

**Migration: create_inter_account_transfers_table.php**

| Column | Type | Nullable | FK | Validated |
|--------|------|----------|----| ---------|
| id | bigint | NO | PK | ⬜ |
| transfer_number | string | NO | UNIQUE | ⬜ |
| from_bank_id | bigint | NO | banks.id | ⬜ |
| to_bank_id | bigint | NO | banks.id | ⬜ |
| from_account_id | bigint | NO | accounts.id | ⬜ |
| to_account_id | bigint | NO | accounts.id | ⬜ |
| journal_entry_id | bigint | YES | journal_entries.id | ⬜ |
| transfer_date | date | NO | - | ⬜ |
| amount | decimal(15,2) | NO | - | ⬜ |
| reference | string | YES | - | ⬜ |
| description | text | NO | - | ⬜ |
| expected_clearance_date | date | YES | - | ⬜ |
| actual_clearance_date | date | YES | - | ⬜ |
| status | enum | NO | - | ⬜ |
| initiated_by | bigint | NO | users.id | ⬜ |
| approved_by | bigint | YES | users.id | ⬜ |
| created_at | timestamp | YES | - | ⬜ |
| updated_at | timestamp | YES | - | ⬜ |

**Observer: app/Observers/Accounting/TransferObserver.php**

| Method | Trigger | Journal Entry | Validated |
|--------|---------|---------------|-----------|
| updated() | status='cleared' | Create entry | ⬜ |
| | | DEBIT to_account, CREDIT from_account | ⬜ |

---

## PHASE 2: BANK RECONCILIATION (Weeks 3-4) ⭐ CRITICAL
**Reference:** Plan Section 2

### 2.1 Reconciliation Tables
**Reference:** Plan Section 2.2

**Table: bank_reconciliations**
| Column | Type | Nullable | FK | Validated |
|--------|------|----------|----| ---------|
| id | bigint | NO | PK | ⬜ |
| bank_id | bigint | NO | banks.id | ⬜ |
| account_id | bigint | NO | accounts.id | ⬜ |
| fiscal_period_id | bigint | NO | accounting_periods.id | ⬜ |
| statement_date | date | NO | - | ⬜ |
| statement_balance | decimal(15,2) | NO | - | ⬜ |
| gl_balance | decimal(15,2) | NO | - | ⬜ |
| outstanding_deposits | decimal(15,2) | NO | - | ⬜ |
| outstanding_checks | decimal(15,2) | NO | - | ⬜ |
| bank_errors | decimal(15,2) | NO | - | ⬜ |
| book_errors | decimal(15,2) | NO | - | ⬜ |
| variance | decimal(15,2) | NO | - | ⬜ |
| status | enum | NO | - | ⬜ |
| notes | text | YES | - | ⬜ |
| prepared_by | bigint | NO | users.id | ⬜ |
| reviewed_by | bigint | YES | users.id | ⬜ |
| reconciled_at | timestamp | YES | - | ⬜ |

**Table: bank_reconciliation_items**
| Column | Type | Nullable | FK | Validated |
|--------|------|----------|----| ---------|
| id | bigint | NO | PK | ⬜ |
| reconciliation_id | bigint | NO | bank_reconciliations.id | ⬜ |
| journal_entry_line_id | bigint | YES | journal_entry_lines.id | ⬜ |
| item_type | enum | NO | - | ⬜ |
| transaction_date | date | NO | - | ⬜ |
| reference | string | YES | - | ⬜ |
| description | text | NO | - | ⬜ |
| amount | decimal(15,2) | NO | - | ⬜ |
| is_reconciled | boolean | NO | - | ⬜ |
| cleared_date | date | YES | - | ⬜ |

### 2.2 ReconciliationService
**Reference:** Plan Section 8.2

| Method | Parameters | Returns | Validated |
|--------|------------|---------|-----------|
| startReconciliation(Bank, date) | $bank, $statementDate | BankReconciliation | ⬜ |
| getUnreconciledTransactions(Account, date, date) | $account, $from, $to | Collection | ⬜ |
| matchTransaction(item, JournalEntryLine) | $item, $jeLine | bool | ⬜ |
| autoMatch(BankReconciliation) | $reconciliation | int (count) | ⬜ |
| createAdjustmentEntry(item) | $item | JournalEntry | ⬜ |
| finalizeReconciliation(BankReconciliation) | $reconciliation | bool | ⬜ |

### 2.3 Statement Parser
**Reference:** Plan Section 8.2

| Method | Parameters | Returns | Validated |
|--------|------------|---------|-----------|
| parse(UploadedFile, string $bankFormat) | $file, $format | array | ⬜ |
| parseZenithFormat(content) | $content | array | ⬜ |
| parseGTBankFormat(content) | $content | array | ⬜ |
| parseAccessFormat(content) | $content | array | ⬜ |

---

## PHASE 3: CASH FLOW & PATIENT DEPOSITS (Week 5)
**Reference:** Plan Sections 6.8, 6.9

### 3.1 Cash Flow Forecast Tables
**Reference:** Plan Section 6.8

**Table: cash_flow_forecasts**
| Column | Type | Nullable | FK | Validated |
|--------|------|----------|----| ---------|
| id | bigint | NO | PK | ⬜ |
| fiscal_year_id | bigint | NO | fiscal_years.id | ⬜ |
| forecast_name | string | NO | - | ⬜ |
| forecast_type | enum | NO | - | ⬜ |
| start_date | date | NO | - | ⬜ |
| end_date | date | NO | - | ⬜ |
| scenario | enum | NO | - | ⬜ |
| status | enum | NO | - | ⬜ |
| created_by | bigint | NO | users.id | ⬜ |

### 3.2 Patient Deposits Tables
**Reference:** Plan Section 6.9

**Table: patient_deposits**
| Column | Type | Nullable | FK | Validated |
|--------|------|----------|----| ---------|
| id | bigint | NO | PK | ⬜ |
| patient_id | bigint | NO | patients.id | ⬜ |
| admission_id | bigint | YES | admissions.id | ⬜ |
| deposit_number | string | NO | UNIQUE | ⬜ |
| deposit_date | date | NO | - | ⬜ |
| amount | decimal(15,2) | NO | - | ⬜ |
| utilized_amount | decimal(15,2) | NO | - | ⬜ |
| balance | decimal(15,2) | NO | - | ⬜ |
| journal_entry_id | bigint | NO | journal_entries.id | ⬜ |
| deposit_type | enum | NO | - | ⬜ |
| received_by | bigint | NO | users.id | ⬜ |
| status | enum | NO | - | ⬜ |
| notes | text | YES | - | ⬜ |

**Observer: PatientDepositObserver**

| Method | Trigger | Journal Entry | Validated |
|--------|---------|---------------|-----------|
| created() | new deposit | DEBIT Cash, CREDIT Deposits Liability (2350) | ⬜ |

---

## PHASE 5: FIXED ASSETS & PO EXTENSION (Weeks 7-8)
**Reference:** Plan Sections 4.1B, 6.6

### 5.1 Extend Purchase Order Items
**Reference:** Plan Section 6.6 - PO Extension

**Migration: add_asset_fields_to_purchase_order_items.php**
| Column | Type | Nullable | FK | Validated |
|--------|------|----------|----| ---------|
| item_type | enum('inventory','fixed_asset') | NO | - | ⬜ |
| fixed_asset_category_id | bigint | YES | accounts.id | ⬜ |

### 5.2 Fixed Asset Categories Table
**Reference:** Plan Section 6.6

**Table: fixed_asset_categories**
| Column | Type | Nullable | FK | Validated |
|--------|------|----------|----| ---------|
| id | bigint | NO | PK | ⬜ |
| account_id | bigint | NO | accounts.id | ⬜ |
| name | string | NO | - | ⬜ |
| default_useful_life_years | integer | NO | - | ⬜ |
| default_depreciation_method | enum | NO | - | ⬜ |
| default_salvage_percentage | decimal(5,2) | NO | - | ⬜ |

### 5.3 Fixed Assets Table
**Reference:** Plan Section 4.1B

**Table: fixed_assets**
| Column | Type | Nullable | FK | Validated |
|--------|------|----------|----| ---------|
| id | bigint | NO | PK | ⬜ |
| account_id | bigint | NO | accounts.id | ⬜ |
| journal_entry_id | bigint | YES | journal_entries.id | ⬜ |
| source_type | string | YES | - | ⬜ |
| source_id | bigint | YES | - | ⬜ |
| asset_number | string | NO | UNIQUE | ⬜ |
| name | string | NO | - | ⬜ |
| asset_category | enum | NO | - | ⬜ |
| purchase_date | date | NO | - | ⬜ |
| cost | decimal(15,2) | NO | - | ⬜ |
| accumulated_depreciation | decimal(15,2) | NO | - | ⬜ |
| book_value | decimal(15,2) | NO | - | ⬜ |
| depreciation_method | enum | NO | - | ⬜ |
| useful_life_years | integer | YES | - | ⬜ |
| salvage_value | decimal(15,2) | NO | - | ⬜ |
| location | string | YES | - | ⬜ |
| serial_number | string | YES | - | ⬜ |
| department_id | bigint | YES | departments.id | ⬜ |
| custodian_user_id | bigint | YES | users.id | ⬜ |
| status | enum | NO | - | ⬜ |
| last_depreciation_date | date | YES | - | ⬜ |

### 5.4 Update PurchaseOrderObserver
**Reference:** Plan Section 6.6 - Enhanced Observer

| Change | Description | Validated |
|--------|-------------|-----------|
| Check item_type | Loop through items | ⬜ |
| Route inventory items | DEBIT 1300, CREDIT 2100 | ⬜ |
| Route fixed_asset items | DEBIT 14xx, CREDIT 2100 | ⬜ |
| Auto-create FixedAsset | For fixed_asset items | ⬜ |
| Link to journal_entry | Store journal_entry_id | ⬜ |

### 5.5 DepreciationObserver
**Reference:** Plan Section 8.3

| Method | Trigger | Journal Entry | Validated |
|--------|---------|---------------|-----------|
| calculateMonthlyDepreciation() | Cron/manual | DEBIT Depreciation Exp (6200), CREDIT Accum Depr (1410) | ⬜ |
| Update FixedAsset | After entry | accumulated_depreciation, book_value | ⬜ |

---

## PHASE 6: LIABILITIES & LEASES (Weeks 9-10)
**Reference:** Plan Sections 4.1A, 6.13

### 6.1 Liability Schedules Tables
**Reference:** Plan Section 4.1A

**Table: liability_schedules**
| Column | Type | Nullable | FK | Validated |
|--------|------|----------|----| ---------|
| id | bigint | NO | PK | ⬜ |
| account_id | bigint | NO | accounts.id | ⬜ |
| liability_type | string | NO | - | ⬜ |
| creditor_name | string | NO | - | ⬜ |
| principal_amount | decimal(15,2) | NO | - | ⬜ |
| current_balance | decimal(15,2) | NO | - | ⬜ |
| interest_rate | decimal(5,2) | NO | - | ⬜ |
| start_date | date | NO | - | ⬜ |
| maturity_date | date | NO | - | ⬜ |
| payment_frequency | enum | NO | - | ⬜ |
| next_payment_date | date | NO | - | ⬜ |
| regular_payment_amount | decimal(15,2) | NO | - | ⬜ |
| notes | text | YES | - | ⬜ |

### 6.2 Lease Management (IFRS 16)
**Reference:** Plan Section 6.13

**Table: leases**
| Column | Type | Validated |
|--------|------|-----------|
| id | bigint | ⬜ |
| lease_number | string UNIQUE | ⬜ |
| lease_type | enum | ⬜ |
| leased_item | string | ⬜ |
| lessor_id | FK suppliers | ⬜ |
| rou_asset_account_id | FK accounts | ⬜ |
| lease_liability_account_id | FK accounts | ⬜ |
| commencement_date | date | ⬜ |
| end_date | date | ⬜ |
| lease_term_months | integer | ⬜ |
| monthly_payment | decimal | ⬜ |
| total_lease_payments | decimal | ⬜ |
| initial_rou_asset_value | decimal | ⬜ |
| initial_lease_liability | decimal | ⬜ |
| current_rou_asset_value | decimal | ⬜ |
| current_lease_liability | decimal | ⬜ |
| incremental_borrowing_rate | decimal | ⬜ |
| has_purchase_option | boolean | ⬜ |
| purchase_option_amount | decimal | ⬜ |
| status | enum | ⬜ |
| notes | text | ⬜ |

**Observer: LeasePaymentObserver**

| Method | Trigger | Journal Entry | Validated |
|--------|---------|---------------|-----------|
| processPayment() | Payment due | DEBIT Lease Liability + Interest Exp, CREDIT Cash | ⬜ |
| processDepreciation() | Monthly | DEBIT Depr Exp, CREDIT Accum Depr (ROU) | ⬜ |

---

## PHASE 7: COST CENTER ACCOUNTING (Week 11)
**Reference:** Plan Section 6.11

### 7.1 Cost Center Tables

**Table: cost_centers**
| Column | Type | Validated |
|--------|------|-----------|
| id | bigint | ⬜ |
| code | string(20) UNIQUE | ⬜ |
| name | string | ⬜ |
| department_id | FK departments | ⬜ |
| manager_user_id | FK users | ⬜ |
| center_type | enum | ⬜ |
| is_active | boolean | ⬜ |
| description | text | ⬜ |

**Table: cost_center_allocations**
| Column | Type | Validated |
|--------|------|-----------|
| id | bigint | ⬜ |
| source_cost_center_id | FK cost_centers | ⬜ |
| target_cost_center_id | FK cost_centers | ⬜ |
| account_id | FK accounts | ⬜ |
| allocation_method | enum | ⬜ |
| allocation_percentage | decimal | ⬜ |
| is_active | boolean | ⬜ |

**Observer: AllocationObserver**

| Method | Trigger | Journal Entry | Validated |
|--------|---------|---------------|-----------|
| processMonthlyAllocations() | Month-end | DEBIT Target, CREDIT Source | ⬜ |

---

## PHASE 8-10: CAPEX, BUDGET, KPI (Weeks 12-16)
**Reference:** Plan Sections 6.10, Budget, 6.15

### 8.1 CAPEX Projects Table
**Reference:** Plan Section 6.10

| Column | Type | Validated |
|--------|------|-----------|
| project_code | string UNIQUE | ⬜ |
| project_name | string | ⬜ |
| project_type | enum | ⬜ |
| estimated_cost | decimal | ⬜ |
| approved_budget | decimal | ⬜ |
| actual_cost | decimal | ⬜ |
| status | enum | ⬜ |

### 8.2 Budget Tables
**Reference:** Plan Section 9

| Column | Type | Validated |
|--------|------|-----------|
| fiscal_year_id | FK fiscal_years | ⬜ |
| account_id | FK accounts | ⬜ |
| department_id | FK departments | ⬜ |
| period | enum | ⬜ |
| period_number | integer | ⬜ |
| budgeted_amount | decimal | ⬜ |
| actual_amount | decimal | ⬜ |
| variance | decimal | ⬜ |

### 8.3 KPI Configuration
**Reference:** Plan Section 6.15

**Table: financial_kpis**
| Column | Type | Validated |
|--------|------|-----------|
| kpi_code | string UNIQUE | ⬜ |
| kpi_name | string | ⬜ |
| category | string | ⬜ |
| calculation_formula | text | ⬜ |
| unit | string | ⬜ |
| frequency | enum | ⬜ |
| target_value | decimal | ⬜ |
| warning_threshold | decimal | ⬜ |
| critical_threshold | decimal | ⬜ |

---

## VALIDATION CHECKLIST (Each Phase)

| Check | Validated |
|-------|-----------|
| All migrations run without errors | ⬜ |
| Foreign key constraints valid | ⬜ |
| Enums have correct values | ⬜ |
| Indexes created for query performance | ⬜ |
| Models have correct $fillable | ⬜ |
| Models have correct relationships | ⬜ |
| Observers registered in AppServiceProvider | ⬜ |
| Observers create journal entries | ⬜ |
| Journal entries have correct metadata | ⬜ |
| Services use correct method signatures | ⬜ |
| Controllers use correct service methods | ⬜ |
| Views display data correctly | ⬜ |
| Routes registered correctly | ⬜ |
| Permissions created and assigned | ⬜ |
| Reports query journal_entries ONLY | ⬜ |

---

## DATA CONSISTENCY VALIDATION QUERIES

### 1. Account Balance = Journal Entry Sum
```sql
SELECT a.code, a.name,
       (SELECT COALESCE(SUM(debit_amount - credit_amount), 0) 
        FROM journal_entry_lines WHERE account_id = a.id) as je_balance
FROM accounts a;
```

### 2. All Transactions Have Journal Entries
```sql
-- Payments without JE
SELECT COUNT(*) FROM payments p
WHERE NOT EXISTS (SELECT 1 FROM journal_entries je 
                  WHERE je.source_type = 'App\\Models\\Payment' 
                  AND je.source_id = p.id);

-- Expenses without JE (approved only)
SELECT COUNT(*) FROM expenses e
WHERE e.status = 'approved'
AND NOT EXISTS (SELECT 1 FROM journal_entries je 
                WHERE je.source_type = 'App\\Models\\Expense' 
                AND je.source_id = e.id);

-- POs without JE (received only)
SELECT COUNT(*) FROM purchase_orders po
WHERE po.status = 'received'
AND NOT EXISTS (SELECT 1 FROM journal_entries je 
                WHERE je.source_type = 'App\\Models\\PurchaseOrder' 
                AND je.source_id = po.id);
```

### 3. Fixed Assets Book Value = Cost - Accumulated Depreciation
```sql
SELECT fa.asset_number, fa.name,
       fa.cost, fa.accumulated_depreciation, fa.book_value,
       (fa.cost - fa.accumulated_depreciation) as calculated_book_value,
       fa.book_value - (fa.cost - fa.accumulated_depreciation) as variance
FROM fixed_assets fa
WHERE ABS(fa.book_value - (fa.cost - fa.accumulated_depreciation)) > 0.01;
```

---

**PROCEED ONLY WHEN ALL PHASE VALIDATIONS PASS.**
