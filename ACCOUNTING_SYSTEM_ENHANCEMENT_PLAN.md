# Accounting System Enhancement Plan
**Date:** January 31, 2026  
**System:** CoreHealth v2 - Journal Entry-Centric Accounting System

---

## Executive Summary

This plan outlines critical enhancements to align CoreHealth's accounting system with international standards (IFRS/IAS) for healthcare operations. The focus is on bank management, reconciliation, revenue recognition, and comprehensive reporting while maintaining **journal entries as the ABSOLUTE core foundation**.

### Core Architecture Principles (NON-NEGOTIABLE):
1. **Journal Entries are the Single Source of Truth** - Every number, report, and analysis derives ONLY from journal_entries table
2. **Observers Create All Journal Entries** - No manual data entry that bypasses journal entries
3. **No Parallel Data Sources** - Remove any module/report that cannot derive from journal entries
4. **Existing System Integration** - All new modules must integrate with existing PO, inventory, payment, expense systems
5. **Products ARE Assets** - Inventory (code 1300) is already an asset; POs can purchase both inventory AND fixed assets

### Current System Foundation (PROVEN & WORKING):
- **PurchaseOrderObserver**: DEBIT Inventory (1300), CREDIT AP (2100) with supplier sub-account
- **PaymentObserver**: DEBIT Cash/Bank (1010), CREDIT Revenue (4xxx) or AR (1200) with patient/service/product metadata
- **ExpenseObserver**: DEBIT Expense (6xxx), CREDIT Cash/Bank/AP (2100) with supplier sub-account
- **PayrollBatchObserver**: Auto-generates payroll journal entries
- **HmoRemittanceObserver**: Tracks HMO payments

**All enhancement modules MUST follow these proven patterns.**

---

## CRITICAL INTEGRATION PRINCIPLES

### 1. Journal Entry Absolute Centricity

**Every Financial Number Derives from journal_entries Table:**
- Account balances = SUM(journal_entry_lines.debit - journal_entry_lines.credit)
- Revenue = SUM(credit) WHERE account LIKE '4%'
- Expenses = SUM(debit) WHERE account LIKE '6%'
- Assets = SUM(debit - credit) WHERE account LIKE '1%' OR '14%'
- Liabilities = SUM(credit - debit) WHERE account LIKE '2%'
- Cash = SUM(debit - credit) WHERE account LIKE '1010%'

**NO EXCEPTIONS. NO PARALLEL TRACKING.**

### 2. Existing System Integration

**Purchase Orders Already Work - Extend Them:**
```
CURRENT (PROVEN):
  PurchaseOrderObserver → DEBIT Inventory (1300), CREDIT AP (2100)
  
EXTENSION (Phase 5):
  Enhanced PurchaseOrderObserver:
    IF item_type = 'inventory': DEBIT Inventory (1300)
    IF item_type = 'fixed_asset': DEBIT Fixed Assets (14xx) + Auto-create FixedAsset record
    
  Database: Add purchase_order_items.item_type enum('inventory', 'fixed_asset')
```

**Products ARE Assets:**
- Drugs, supplies, medical consumables = Inventory (1300) = Current Assets
- Equipment, furniture, land = Fixed Assets (14xx) = Non-current Assets
- Both tracked via journal entries when received (PurchaseOrderObserver)

### 3. Observer Pattern is Mandatory

**Every Module MUST:**
1. Create Observer class in app/Observers/Accounting/
2. Observer calls AccountingService.createAndPostAutomatedEntry()
3. Store journal_entry_id in source table (for linkage)
4. Include comprehensive metadata (patient_id, supplier_id, category, etc.)
5. NO direct database inserts to journal entries outside observers

**Observer Creation Checklist:**
- [ ] Observer class created
- [ ] Registered in AppServiceProvider
- [ ] Calls AccountingService (never creates entries directly)
- [ ] Stores journal_entry_id in source record
- [ ] Includes all required metadata
- [ ] Logged for audit trail
- [ ] Tested with rollback scenario

### 4. Report Validation Protocol

**Before Implementing Any Report:**
1. Write SQL query using ONLY journal_entries and journal_entry_lines tables
2. Test query returns correct data
3. Compare with manual calculation
4. If query cannot use journal entries → Redesign or reject report
5. Document query in ReportService with comments

**Rejected Reports:** Any report that requires source table amounts (payments.total, expenses.amount, etc.)

---

## 1. BANK ACCOUNTS vs BANK LEDGER ACCOUNTS DISTINCTION

### 1.1 Current State Analysis

**Current Structure:**
- `banks` table: Physical bank information (Zenith Bank, GTBank, etc.)
- `accounts` table: General Ledger accounts (including bank accounts marked with `is_bank_account = true`)
- Relationship: `accounts.bank_id` → `banks.id`

**Problem:** Confusion between:
1. **Physical Bank Account** (actual Zenith Bank Account #0123456789)
2. **GL Bank Account** (Ledger code 1010-001 "Zenith Bank - Current Account")

### 1.2 Proposed Solution: Two-Tier Bank Management System

#### A. Physical Bank Account (Primary Focus - 70%)
**Purpose:** Track actual bank accounts - what's physically in Zenith Bank, GTBank, etc.

```sql
-- Enhanced banks table
ALTER TABLE banks ADD COLUMN:
- current_balance DECIMAL(15,2) DEFAULT 0  -- Real-time bank balance
- last_statement_date DATE                 -- Last reconciliation date
- last_statement_balance DECIMAL(15,2)     -- Last reconciled balance
- bank_statement_day INT                   -- Day of month for statement (e.g., 25)
- overdraft_limit DECIMAL(15,2) DEFAULT 0
- is_active BOOLEAN DEFAULT true
- bank_type ENUM('current', 'savings', 'fixed_deposit') DEFAULT 'current'
```

**New Features:**
1. **Bank Dashboard** showing real balances for each bank
2. **Bank Statement Import** (CSV/Excel)
3. **Multi-currency support** (if needed)

#### B. GL Bank Account (Supporting Role - 30%)
**Purpose:** Double-entry bookkeeping ledger for financial statements

**Mapping:**
```
Physical Bank (banks.id=1: Zenith Current #012345)
    ↓
GL Account (accounts.id=45: 1010-001 "Zenith Bank - Current")
    ↓
Journal Entry Lines (all debits/credits)
```

### 1.3 Implementation Plan

**Phase 1: Database Enhancement**
```php
// Migration: enhance_banks_table.php
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

**Phase 2: Bank Dashboard**
- Route: `/accounting/banks`
- Show ALL bank accounts with:
  - Current physical balance (from `banks.current_balance`)
  - GL account balance (from journal entries)
  - Variance (should be zero after reconciliation)
  - Last reconciliation date
  - Unreconciled items count

**Phase 3: Bank Statement Report Enhancement**
```
Current: Shows GL transactions
Enhanced: Shows BOTH
  ├── Physical Bank Info (Primary)
  │   ├── Bank: Zenith Bank
  │   ├── Account #: 0123456789
  │   ├── Statement Balance: ₦5,234,890.00
  │   └── As of: Jan 30, 2026
  └── GL Reconciliation (Secondary)
      ├── GL Account: 1010-001
      ├── Ledger Balance: ₦5,234,890.00
      ├── Variance: ₦0.00
      └── Status: ✓ Reconciled
```

---

## 2. BANK RECONCILIATION SYSTEM (IAS 7 - Cash Flow Statements)

### 2.1 International Standards Compliance

**IAS 7 Requirements:**
- Reconcile cash and cash equivalents
- Identify timing differences
- Identify errors
- Prove ending balance

**Standard Reconciliation Formula:**
```
GL Balance
+ Outstanding Deposits (in transit)
- Outstanding Checks (not yet cleared)
± Bank Errors
± Book Errors
= Bank Statement Balance
```

### 2.2 Database Design

**New Table: `bank_reconciliations`**
```php
Schema::create('bank_reconciliations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('bank_id')->constrained('banks');
    $table->foreignId('account_id')->constrained('accounts'); // GL account
    $table->foreignId('fiscal_period_id')->constrained('accounting_periods');
    $table->date('statement_date');
    $table->decimal('statement_balance', 15, 2);
    $table->decimal('gl_balance', 15, 2);
    $table->decimal('outstanding_deposits', 15, 2)->default(0);
    $table->decimal('outstanding_checks', 15, 2)->default(0);
    $table->decimal('bank_errors', 15, 2)->default(0);
    $table->decimal('book_errors', 15, 2)->default(0);
    $table->decimal('variance', 15, 2)->default(0);
    $table->enum('status', ['draft', 'in_progress', 'reconciled', 'discrepancy'])->default('draft');
    $table->text('notes')->nullable();
    $table->foreignId('prepared_by')->constrained('users');
    $table->foreignId('reviewed_by')->nullable()->constrained('users');
    $table->timestamp('reconciled_at')->nullable();
    $table->timestamps();
});
```

**New Table: `bank_reconciliation_items`**
```php
Schema::create('bank_reconciliation_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('reconciliation_id')->constrained('bank_reconciliations');
    $table->foreignId('journal_entry_line_id')->nullable()->constrained('journal_entry_lines');
    $table->enum('item_type', [
        'outstanding_deposit',    // Deposited in GL but not on bank statement
        'outstanding_check',      // Check issued but not cleared
        'bank_error',            // Bank made error
        'book_error',            // We made error in GL
        'bank_charge',           // Bank charges not recorded
        'interest_earned',       // Interest not recorded
        'nsf_check',            // Bounced check
        'other'
    ]);
    $table->date('transaction_date');
    $table->string('reference')->nullable();
    $table->text('description');
    $table->decimal('amount', 15, 2);
    $table->boolean('is_reconciled')->default(false);
    $table->date('cleared_date')->nullable();
    $table->timestamps();
});
```

### 2.3 Reconciliation Process (Step-by-Step)

**Step 1: Upload Bank Statement**
```php
POST /accounting/banks/{bank}/reconciliation/upload
- Parse CSV/PDF/Excel
- Match dates and amounts
- Auto-match with GL transactions (80% automation)
```

**Step 2: Match Transactions**
```php
GET /accounting/banks/{bank}/reconciliation/{id}/match
- Show unmatched GL transactions
- Show unmatched bank transactions
- Drag-and-drop matching interface
- Bulk matching by amount/date
```

**Step 3: Identify Differences**
```
Outstanding Items:
├── Deposits in Transit
│   - Deposited on Dec 31, not on statement (cleared Jan 2)
├── Outstanding Checks
│   - Check #1234 issued Dec 28, not cleared
└── Adjustments Needed
    ├── Bank Charges (create journal entry)
    ├── Interest Income (create journal entry)
    └── NSF Checks (reverse entry)
```

**Step 4: Create Adjustment Entries (Auto-Generate)**
```php
// Example: Bank charges found on statement
Journal Entry (Auto-created):
  DEBIT:  Bank Charges Expense (6500)  ₦1,500
  CREDIT: Bank Account (1010)          ₦1,500
  Description: "Bank charges - January 2026"
```

**Step 5: Finalize & Approve**
```
Reconciliation Summary:
GL Balance:                ₦5,240,000
+ Outstanding Deposits:    ₦   45,000
- Outstanding Checks:      ₦  (50,000)
- Bank Charges (new):      ₦   (1,500)
+ Interest (new):          ₦    1,390
= Bank Statement Balance:  ₦5,234,890 ✓
```

### 2.4 Reconciliation Reports

**Daily/Weekly:**
- Unreconciled transactions report
- Aging of outstanding checks

**Monthly (Required):**
- Full bank reconciliation statement
- Variance analysis
- Outstanding items detail

**Quarterly:**
- Reconciliation history
- Trend analysis (reconciliation time, discrepancies)

---

## 3. REVENUE RECOGNITION SYSTEM (IFRS 15)

### 3.1 Current State: Payment Observer

**Current Flow (PROVEN & WORKING):**
```
Patient Payment → Payment Model → PaymentObserver
  → Journal Entry (AUTOMATIC):
     DEBIT:  Cash/Bank (1010)
     CREDIT: Revenue Account (4xxx) or AR (1200)
     Metadata: patient_id, service_id, product_id, hmo_id, category
```

**Strengths (MAINTAIN THESE):**
✓ Automatic journal entry creation via observer
✓ Comprehensive metadata capture (patient, service, product, HMO, category)
✓ Sub-account tracking (HMO patients automatically get sub-accounts)
✓ ALL revenue flows through journal entries - no parallel tracking
✓ Reports pull from journal_entry_lines, not from payments table

### 3.2 Do We Need Separate Income Model?

**Answer: NO - Current system is sufficient, BUT needs enhancement**

**Why Current System Works:**
1. **Multiple Income Sources Already Handled:**
   - `PaymentObserver`: Patient payments
   - `HmoRemittanceObserver`: HMO payments
   - Product sales through ProductOrServiceRequest

2. **Journal Entry is the Single Source of Truth**
   - All revenue flows through journal entries
   - Reporting pulls from journal entries
   - Audit trail maintained

**What We Need: Income Categories & Recognition Rules**

### 3.3 Revenue Enhancement Plan

**A. Revenue Categories Configuration**
```php
// New table: revenue_categories
Schema::create('revenue_categories', function (Blueprint $table) {
    $table->id();
    $table->string('code', 20)->unique();
    $table->string('name');
    $table->foreignId('account_id')->constrained('accounts'); // Default revenue account
    $table->enum('recognition_method', [
        'cash_basis',           // Recognize when paid
        'accrual_basis',        // Recognize when service delivered
        'deferred',             // Advance payments
        'installment'           // Payment plans
    ])->default('cash_basis');
    $table->boolean('requires_approval')->default(false);
    $table->boolean('is_taxable')->default(false);
    $table->timestamps();
});

// Seed data
Revenue Categories:
├── CONSULT - Consultation Fees (4100)
├── PHARMACY - Drug Sales (4200)
├── LAB - Laboratory Services (4300)
├── IMAGING - Radiology/Imaging (4400)
├── SURGERY - Surgical Procedures (4500)
├── ACCOMMODATION - Ward/Bed Fees (4600)
├── HMO - HMO Capitation (4700)
└── OTHER - Other Hospital Income (4800)
```

**B. Deferred Revenue for Advance Payments**
```php
// When patient pays in advance
DEBIT:  Cash (1010)                    ₦50,000
CREDIT: Deferred Revenue (2300)        ₦50,000

// When service is delivered
DEBIT:  Deferred Revenue (2300)        ₦50,000
CREDIT: Consultation Revenue (4100)    ₦50,000
```

**C. Installment Revenue (Payment Plans)**
```php
// Track installment contracts
Schema::create('revenue_installments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('patient_id')->constrained('patients');
    $table->morphs('source'); // ProductOrServiceRequest, etc.
    $table->decimal('total_amount', 15, 2);
    $table->decimal('amount_paid', 15, 2)->default(0);
    $table->decimal('amount_outstanding', 15, 2);
    $table->integer('total_installments');
    $table->integer('installments_paid')->default(0);
    $table->date('start_date');
    $table->enum('frequency', ['weekly', 'biweekly', 'monthly']);
    $table->enum('status', ['active', 'completed', 'defaulted']);
    $table->timestamps();
});
```

### 3.4 Income Reports Needed

1. **Revenue by Category Report** (Daily/Weekly/Monthly)
2. **Revenue by Department Report**
3. **Revenue by HMO Report**
4. **Deferred Revenue Schedule**
5. **Outstanding Receivables Aging**

---

## 4. OTHER ACCOUNT CLASSES: Do We Need Separate Models?

### 4.1 Analysis by Account Class

**CRITICAL REMINDER: All account balances derive from journal_entry_lines table ONLY.**

#### A. LIABILITIES (Class 2)

**Current Approach: PERFECT - Observers Auto-Create Entries**

**Existing Automatic Liabilities (via Observers):**
```
✓ Accounts Payable (2100) - via PurchaseOrderObserver (when PO received), ExpenseObserver (when expense approved)
   - Each creates supplier sub-account under AP automatically
✓ Salaries Payable (2200) - via PayrollBatchObserver
✓ HMO Payables (2150) - via patient transactions
✓ ALL tracked via journal entries - no separate tracking
```

**What's Missing: Long-term Liabilities Management**
```php
// NEW: Long-term liabilities tracking
Schema::create('liability_schedules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('account_id')->constrained('accounts'); // e.g., Bank Loan (2400)
    $table->string('liability_type'); // 'loan', 'lease', 'bond'
    $table->string('creditor_name');
    $table->decimal('principal_amount', 15, 2);
    $table->decimal('current_balance', 15, 2);
    $table->decimal('interest_rate', 5, 2);
    $table->date('start_date');
    $table->date('maturity_date');
    $table->enum('payment_frequency', ['monthly', 'quarterly', 'annually']);
    $table->date('next_payment_date');
    $table->decimal('regular_payment_amount', 15, 2);
    $table->text('notes')->nullable();
    $table->timestamps();
});

// Payment schedule details
Schema::create('liability_payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('schedule_id')->constrained('liability_schedules');
    $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
    $table->date('due_date');
    $table->date('payment_date')->nullable();
    $table->decimal('principal_amount', 15, 2);
    $table->decimal('interest_amount', 15, 2);
    $table->decimal('total_payment', 15, 2);
    $table->enum('status', ['pending', 'paid', 'overdue'])->default('pending');
    $table->timestamps();
});
```

**Use Case: Bank Loan**
```
Loan Details:
- Principal: ₦10,000,000
- Rate: 15% p.a.
- Term: 5 years
- Monthly: ₦237,906

Monthly Entry (Auto-generated):
DEBIT:  Interest Expense (6100)        ₦125,000
DEBIT:  Bank Loan (2400)               ₦112,906
CREDIT: Cash/Bank (1010)               ₦237,906
```

**Decision: YES - Need liability management module for long-term debts**

#### B. ASSETS (Class 1)

**Current Approach: WORKING - But Needs Extension**

**Handled Well (via Journal Entries):**
```
✓ Cash/Bank (1010) - via PaymentObserver, ExpenseObserver, all cash movements
✓ Accounts Receivable (1200) - via PaymentObserver
✓ Inventory (1300) - via PurchaseOrderObserver
   → When PO received: DEBIT Inventory (1300), CREDIT AP (2100)
   → Products/drugs ARE assets, tracked at cost in inventory account
   → ALL inventory movements via journal entries (no parallel tracking)
```

**What's Missing: Fixed Assets (Long-lived Assets > 1 year)**

**CRITICAL INTEGRATION: POs Already Can Purchase Fixed Assets**

The existing PurchaseOrderObserver currently debits ALL purchases to Inventory (1300). We need to:
1. **Extend PO System** to distinguish:
   - Inventory items (drugs, supplies) → 1300 Inventory
   - Capital items (equipment, furniture, land) → 14xx Fixed Assets
2. **Update PurchaseOrderObserver Logic**:
   ```php
   // ENHANCED PurchaseOrderObserver (to be updated)
   if ($poItem->asset_type === 'fixed_asset') {
       DEBIT: Fixed Assets (14xx - by category)
       CREDIT: AP (2100)
   } else {
       DEBIT: Inventory (1300)
       CREDIT: AP (2100)
   }
   ```
```php
// NEW: Fixed assets register (INTEGRATED with PO system)
Schema::create('fixed_assets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('account_id')->constrained('accounts'); // Fixed Asset account (14xx)
    $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries'); // Link to acquisition entry
    
    // PO INTEGRATION - Link to source purchase
    $table->morphs('source'); // purchase_order_items, expense (for purchases outside PO)
    
    $table->string('asset_number')->unique();
    $table->string('name');
    $table->enum('asset_category', [
        'land', 'buildings', 'medical_equipment', 'furniture',
        'computers', 'vehicles', 'other'
    ]);
    $table->date('purchase_date');
    $table->decimal('cost', 15, 2); // From journal entry debit amount
    $table->decimal('accumulated_depreciation', 15, 2)->default(0); // From depreciation journal entries
    $table->decimal('book_value', 15, 2); // Calculated: cost - accumulated_depreciation
    $table->enum('depreciation_method', ['straight_line', 'reducing_balance', 'none']);
    $table->integer('useful_life_years')->nullable();
    $table->decimal('salvage_value', 15, 2)->default(0);
    $table->string('location')->nullable();
    $table->string('serial_number')->nullable();
    $table->foreignId('department_id')->nullable()->constrained('departments');
    $table->foreignId('custodian_user_id')->nullable()->constrained('users');
    $table->enum('status', ['active', 'disposed', 'damaged', 'under_repair']);
    $table->date('last_depreciation_date')->nullable();
    $table->timestamps();
    $table->softDeletes();
});
```

**Monthly Depreciation (Auto-generated via Observer)**
```php
// NEW: DepreciationObserver (Phase 5)
// Triggered by monthly cron job or fiscal period close
foreach (FixedAsset::needsDepreciation() as $asset) {
    // Observer automatically creates journal entry:
    DEBIT:  Depreciation Expense (6200)           ₦X
    CREDIT: Accumulated Depreciation (1410)       ₦X
    
    // Update asset record
    $asset->accumulated_depreciation += $depreciationAmount;
    $asset->book_value = $asset->cost - $asset->accumulated_depreciation;
    $asset->last_depreciation_date = now();
    $asset->save();
}
```

**CRITICAL: Asset balances calculated from journal entries:**
- Fixed Assets (14xx) balance = SUM of all DEBIT entries to 14xx accounts
- Accumulated Depreciation (1410) = SUM of all CREDIT entries to 1410
- Book Value = Fixed Assets - Accumulated Depreciation
- Asset register is METADATA only; GL is source of truth

**Decision: YES - Need fixed assets module + Extend PO system**

#### C. EQUITY (Class 3)

**Current Approach: PERFECT - Opening Balances Sufficient**

**Handled:**
```
✓ Capital/Share Capital (3000) - Opening balance
✓ Retained Earnings (3100) - Auto-calculated by FiscalYear
✓ Current Year Earnings - Auto from P&L
```

**No Additional Model Needed**

---

## 5. INTERNATIONAL STANDARD REPORTS FOR HOSPITAL OPERATIONS

### 5.1 Financial Statements (IFRS Required - Monthly)

**Already Implemented ✓:**
1. Trial Balance
2. Profit & Loss Statement (Income Statement)
3. Balance Sheet (Statement of Financial Position)
4. Cash Flow Statement

**Enhancement Needed:**
5. **Statement of Changes in Equity** (IFRS Required)

```php
// Add to ReportService
public function statementOfChanges(Carbon $startDate, Carbon $endDate)
{
    /*
    Opening Balance        ₦50,000,000
    + Net Profit           ₦12,000,000
    - Dividends            ₦ (2,000,000)
    - Withdrawals          ₦   (500,000)
    Closing Balance        ₦59,500,000
    */
}
```

### 5.2 Management Reports (Daily/Weekly)

**NEW - Critical for Hospital Operations:**

#### A. Cash Position Report (Daily)
```
Date: Jan 31, 2026

CASH SUMMARY:
├── Opening Cash          ₦500,000
├── Today's Receipts      ₦850,000
│   ├── Cash Payments     ₦450,000
│   ├── Bank Transfers    ₦400,000
│   └── Card Payments     ₦  0
├── Today's Payments      ₦(230,000)
│   ├── Expenses          ₦(180,000)
│   └── Refunds          ₦ (50,000)
└── Closing Cash          ₦1,120,000

BANK SUMMARY (All Banks):
├── Zenith Current        ₦5,234,890
├── GTBank Current        ₦2,890,450
├── Access Savings        ₦10,000,000
└── Total Bank           ₦18,125,340

TOTAL LIQUID ASSETS      ₦19,245,340
```

#### B. Revenue by Department (Daily)
```
Department Performance - Jan 31, 2026

Consultation:            ₦145,000  (15 patients)
Pharmacy:                ₦325,000  (45 sales)
Laboratory:              ₦89,000   (23 tests)
Imaging:                 ₦180,000  (8 scans)
Ward/Admission:          ₦200,000  (5 admissions)
---------------------------------------------
TOTAL:                   ₦939,000  (96 transactions)
```

#### C. Outstanding Receivables Summary (Weekly)
```
Accounts Receivable Aging - Week Ending Jan 31, 2026

HMO Receivables:
├── Current (0-30 days)      ₦2,500,000
├── 31-60 days              ₦1,200,000
├── 61-90 days              ₦  800,000
└── Over 90 days            ₦  350,000
Total HMO:                   ₦4,850,000

Patient Receivables:
├── Current (0-30 days)      ₦  450,000
├── 31-60 days              ₦  120,000
└── Over 60 days            ₦   80,000
Total Patient:               ₦  650,000

TOTAL RECEIVABLES:           ₦5,500,000
```

#### D. Payables Summary (Weekly)
```
Accounts Payable - Week Ending Jan 31, 2026

Suppliers:
├── Pharmaceuticals         ₦1,500,000
├── Medical Supplies        ₦  800,000
├── Utilities              ₦  150,000
└── Other                  ₦  200,000
Total Suppliers:            ₦2,650,000

Employee Salaries Due:      ₦3,200,000  (Due: Feb 5)
Statutory Deductions Due:   ₦  450,000  (Due: Feb 7)

TOTAL PAYABLES:             ₦6,300,000
```

### 5.3 Regulatory Reports (Monthly/Quarterly)

**Required for Nigerian Healthcare:**

#### A. Revenue Analysis by Payer Type
```
Monthly Revenue - January 2026

Cash Patients:              ₦8,500,000  (42%)
HMO/Insurance:              ₦10,200,000 (50%)
Corporate:                  ₦1,200,000  (6%)
Government:                 ₦  400,000  (2%)
---------------------------------------------
TOTAL:                      ₦20,300,000 (100%)
```

#### B. Expense Analysis by Category
```
Monthly Expenses - January 2026

Personnel Costs:            ₦9,500,000  (48%)
  ├── Salaries              ₦7,200,000
  ├── Allowances            ₦1,800,000
  └── Statutory             ₦  500,000

Medical Supplies:           ₦4,200,000  (21%)
  ├── Pharmacy              ₦2,800,000
  └── Other Supplies        ₦1,400,000

Utilities:                  ₦1,800,000  (9%)
Maintenance:                ₦1,200,000  (6%)
Administrative:             ₦  800,000  (4%)
Other:                      ₦2,500,000  (12%)
---------------------------------------------
TOTAL:                      ₦20,000,000 (100%)
```

#### C. Inventory Valuation Report
```
Inventory Position - Jan 31, 2026

Pharmaceuticals:
├── Opening Stock           ₦5,000,000
├── Purchases              ₦3,500,000
├── Sales (Cost)           ₦(2,800,000)
└── Closing Stock          ₦5,700,000

Medical Supplies:
├── Opening Stock           ₦1,200,000
├── Purchases              ₦  800,000
├── Consumption            ₦  (600,000)
└── Closing Stock          ₦1,400,000

TOTAL INVENTORY:            ₦7,100,000
```

### 5.4 Audit & Compliance Reports

#### A. Journal Entry Audit Trail
```
Suspicious Transactions Report

Criteria:
- Large amounts (>₦500,000)
- After-hours posting
- Adjusted/Reversed entries
- Unusual account combinations
```

#### B. Internal Control Reports
```
1. Segregation of Duties Compliance
2. Authorization Limits Compliance
3. Missing Approvals Report
4. Duplicate Entry Detection
```

### 5.5 Budget vs Actual Reports (Monthly)

```php
// NEW: Budget tracking
Schema::create('budgets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fiscal_year_id')->constrained('fiscal_years');
    $table->foreignId('account_id')->constrained('accounts');
    $table->foreignId('department_id')->nullable()->constrained('departments');
    $table->enum('period', ['monthly', 'quarterly', 'annual']);
    $table->integer('period_number'); // Month 1-12, Quarter 1-4
    $table->decimal('budgeted_amount', 15, 2);
    $table->decimal('actual_amount', 15, 2)->default(0);
    $table->decimal('variance', 15, 2)->default(0);
    $table->decimal('variance_percent', 5, 2)->default(0);
    $table->timestamps();
});
```

```
Budget vs Actual - January 2026

Revenue:
Account                    Budget        Actual      Variance    %
---------------------------------------------------------------
Consultation             ₦1,500,000   ₦1,680,000   ₦180,000   12%
Pharmacy                 ₦3,000,000   ₦2,850,000  (₦150,000)  -5%
Laboratory               ₦  800,000   ₦  920,000   ₦120,000   15%
...

Expenses:
Personnel                ₦9,000,000   ₦9,500,000  (₦500,000)  -6%
Medical Supplies         ₦4,500,000   ₦4,200,000   ₦300,000    7%
...
```

---

## 6. ADDITIONAL MODULES NEEDED

### 6.1 Bank Management Module ⭐ PRIORITY 1

**Components:**
1. Bank Dashboard (`/accounting/banks`)
2. Bank Reconciliation (`/accounting/banks/{id}/reconciliation`)
3. Bank Statement Upload
4. Outstanding Items Management
5. Reconciliation Reports

### 6.2 Fixed Assets Management ⭐ PRIORITY 2

**Components:**
1. Assets Register (`/accounting/fixed-assets`)
2. Asset Categories & Depreciation Setup
3. Monthly Depreciation Posting (Automated)
4. Asset Disposal/Transfer
5. Asset Valuation Report

### 6.3 Liabilities Management ⭐ PRIORITY 3

**Components:**
1. Loan/Liability Register (`/accounting/liabilities`)
2. Payment Schedule Generation
3. Automatic Payment Reminders
4. Interest Calculation & Posting
5. Amortization Schedule Report

### 6.4 Budget Management ⭐ PRIORITY 4

**Components:**
1. Budget Creation (`/accounting/budgets`)
2. Budget Templates (by department/year)
3. Budget Approval Workflow
4. Budget vs Actual Analysis
5. Variance Reports & Alerts

### 6.5 Revenue Recognition Engine ⭐ PRIORITY 5

**Components:**
1. Revenue Categories Configuration
2. Deferred Revenue Management
3. Installment Contracts Tracking
4. Revenue Recognition Rules
5. Revenue Analysis Reports

### 6.6 Purchase Order Extension for Fixed Assets ⭐ PRIORITY 6 (INTEGRATED with Phase 5)

**Purpose:** Extend existing PO system to distinguish inventory vs fixed asset purchases

**Current State (WORKING):**
- PurchaseOrderObserver: DEBIT Inventory (1300), CREDIT AP (2100)
- ALL purchases currently treated as inventory
- System proven and working for drugs/supplies

**Enhancement Needed:**
```
Current:  All POs → Inventory (1300)
Enhanced: 
  - Inventory POs → Inventory (1300)
  - Capital POs → Fixed Assets (14xx) + Auto-create FixedAsset record
```

**Database Changes:**
```php
// 1. Add to purchase_order_items table
Schema::table('purchase_order_items', function (Blueprint $table) {
    $table->enum('item_type', ['inventory', 'fixed_asset'])->default('inventory')->after('product_id');
    $table->foreignId('fixed_asset_category_id')->nullable()->constrained('accounts')->after('item_type');
    // For fixed assets: which 14xx account to use
});

// 2. Add fixed_asset_categories reference table
Schema::create('fixed_asset_categories', function (Blueprint $table) {
    $table->id();
    $table->foreignId('account_id')->constrained('accounts'); // Links to 14xx accounts
    $table->string('name'); // 'Medical Equipment', 'Furniture', 'Computers', 'Vehicles'
    $table->integer('default_useful_life_years');
    $table->enum('default_depreciation_method', ['straight_line', 'reducing_balance']);
    $table->decimal('default_salvage_percentage', 5, 2)->default(10); // 10%
    $table->timestamps();
});
```

**Enhanced PurchaseOrderObserver:**
```php
// UPDATED: app/Observers/Accounting/PurchaseOrderObserver.php

protected function createPOReceivedJournalEntry(PurchaseOrder $po): void
{
    $accountingService = App::make(AccountingService::class);
    
    // Group items by type (inventory vs fixed_asset)
    $inventoryAmount = 0;
    $assetAmounts = []; // Grouped by asset category account
    
    foreach ($po->items as $item) {
        if ($item->item_type === 'fixed_asset') {
            $accountId = $item->fixed_asset_category_id;
            $assetAmounts[$accountId] = ($assetAmounts[$accountId] ?? 0) + $item->line_total;
        } else {
            $inventoryAmount += $item->line_total;
        }
    }
    
    $lines = [];
    
    // Inventory items
    if ($inventoryAmount > 0) {
        $inventoryAccount = Account::where('code', '1300')->first();
        $lines[] = [
            'account_id' => $inventoryAccount->id,
            'debit_amount' => $inventoryAmount,
            'credit_amount' => 0,
            'description' => "Inventory received: PO {$po->po_number}",
            'supplier_id' => $po->supplier_id,
            'category' => 'purchase_order_inventory',
        ];
    }
    
    // Fixed asset items (separate line per asset category)
    foreach ($assetAmounts as $accountId => $amount) {
        $lines[] = [
            'account_id' => $accountId,
            'debit_amount' => $amount,
            'credit_amount' => 0,
            'description' => "Fixed asset acquired: PO {$po->po_number}",
            'supplier_id' => $po->supplier_id,
            'category' => 'purchase_order_fixed_asset',
        ];
    }
    
    // Credit AP (single line for whole PO)
    $apAccount = Account::where('code', '2100')->first();
    $supplierSubAccount = $this->subAccountService->getOrCreateSupplierSubAccount($po->supplier);
    
    $lines[] = [
        'account_id' => $apAccount->id,
        'sub_account_id' => $supplierSubAccount?->id,
        'debit_amount' => 0,
        'credit_amount' => $po->total_amount,
        'description' => "AP - {$po->supplier->company_name} (PO: {$po->po_number})",
        'supplier_id' => $po->supplier_id,
        'category' => 'purchase_order',
    ];
    
    $entry = $accountingService->createAndPostAutomatedEntry(
        PurchaseOrder::class,
        $po->id,
        "Purchase Order Received: {$po->po_number}",
        $lines
    );
    
    // Auto-create FixedAsset records for fixed asset items
    foreach ($po->items as $item) {
        if ($item->item_type === 'fixed_asset') {
            $this->createFixedAssetFromPOItem($item, $entry);
        }
    }
}

protected function createFixedAssetFromPOItem(PurchaseOrderItem $item, JournalEntry $entry): void
{
    $category = FixedAssetCategory::find($item->fixed_asset_category_id);
    
    FixedAsset::create([
        'account_id' => $category->account_id,
        'journal_entry_id' => $entry->id,
        'source_type' => PurchaseOrderItem::class,
        'source_id' => $item->id,
        'asset_number' => $this->generateAssetNumber(),
        'name' => $item->product->product_name ?? 'Asset from PO',
        'asset_category' => $this->mapCategoryToEnum($category->name),
        'purchase_date' => $item->received_at ?? now(),
        'cost' => $item->actual_line_total,
        'book_value' => $item->actual_line_total,
        'depreciation_method' => $category->default_depreciation_method,
        'useful_life_years' => $category->default_useful_life_years,
        'salvage_value' => $item->actual_line_total * ($category->default_salvage_percentage / 100),
        'status' => 'active',
    ]);
}
```

**UI Changes:**
```
PO Creation Form:
  For each line item:
    [ ] Item Type: (*) Inventory  ( ) Fixed Asset
    
    If Fixed Asset:
      - Asset Category: [Dropdown: Medical Equipment, Furniture, etc.]
      - Useful Life: [Auto-filled from category] years
      - Serial Number: [Optional text]
```

**Reports Integration:**
- Fixed Assets Register shows: "Source: PO-2026-001" with link
- PO detail shows: "Item type: Fixed Asset" badge
- Fixed asset detail shows: "Acquired via: PO-2026-001"

### 6.7 Petty Cash Management ⭐ PRIORITY 7

**Purpose:** Day-to-day small cash transactions (< ₦50,000)

**Components:**
1. Petty Cash Register (`/accounting/petty-cash`)
2. Cash Custodian Assignment
3. Expense Categories & Limits
4. Reimbursement Workflow
5. Daily Cash Count & Reconciliation

**Database Design:**
```php
Schema::create('petty_cash_funds', function (Blueprint $table) {
    $table->id();
    $table->string('fund_name'); // 'Reception', 'Administration', 'Pharmacy'
    $table->foreignId('account_id')->constrained('accounts'); // Cash account (1010)
    $table->foreignId('custodian_user_id')->constrained('users');
    $table->foreignId('department_id')->nullable()->constrained('departments');
    $table->decimal('fund_limit', 15, 2); // Maximum fund size
    // CRITICAL: Balance calculated from journal entries, NOT stored
    // current_balance = SUM(journal_entry_lines.debit - journal_entry_lines.credit WHERE account_id = this.account_id)
    $table->decimal('transaction_limit', 15, 2); // Per transaction limit
    $table->boolean('requires_approval')->default(false);
    $table->enum('status', ['active', 'suspended', 'closed'])->default('active');
    $table->timestamps();
});

Schema::create('petty_cash_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fund_id')->constrained('petty_cash_funds');
    $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries'); // Link to GL
    $table->enum('transaction_type', ['disbursement', 'reimbursement', 'adjustment']);
    $table->date('transaction_date');
    $table->string('voucher_number')->unique();
    $table->text('description');
    $table->decimal('amount', 15, 2);
    $table->string('category'); // 'Transport', 'Stationery', 'Refreshment', etc.
    $table->foreignId('requested_by')->constrained('users');
    $table->foreignId('approved_by')->nullable()->constrained('users');
    $table->string('receipt_number')->nullable();
    $table->enum('status', ['pending', 'approved', 'paid', 'rejected'])->default('pending');
    $table->timestamps();
});

Schema::create('petty_cash_reconciliations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fund_id')->constrained('petty_cash_funds');
    $table->date('reconciliation_date');
    $table->decimal('expected_balance', 15, 2);
    $table->decimal('actual_cash_count', 15, 2);
    $table->decimal('variance', 15, 2);
    $table->text('notes')->nullable();
    $table->foreignId('reconciled_by')->constrained('users');
    $table->timestamps();
});
```

**Workflow (via Observer):**
```php
// NEW: PettyCashObserver

// 1. Fund Establishment (initial)
DEBIT:  Petty Cash (1010-PC-001)     ₦50,000
CREDIT: Cash/Bank (1010)             ₦50,000

// 2. Disbursement (when approved)
DEBIT:  Expense Account (6xxx)       ₦2,000
CREDIT: Petty Cash (1010-PC-001)     ₦2,000

// 3. Reimbursement (replenish fund)
DEBIT:  Petty Cash (1010-PC-001)     ₦10,000
CREDIT: Cash/Bank (1010)             ₦10,000

// Balance ALWAYS calculated from journal entries:
// fund.current_balance = SUM(je_lines.debit - je_lines.credit WHERE account_id = fund.account_id)
```

**Observer Implementation:**
```php
// app/Observers/Accounting/PettyCashObserver.php

class PettyCashObserver
{
    public function updated(PettyCashTransaction $transaction)
    {
        if ($transaction->isDirty('status') && $transaction->status === 'approved') {
            $this->createJournalEntry($transaction);
        }
    }
    
    protected function createJournalEntry($transaction)
    {
        $accountingService = App::make(AccountingService::class);
        
        if ($transaction->transaction_type === 'disbursement') {
            // Expense from petty cash
            $expenseAccount = $this->getExpenseAccount($transaction->category);
            $lines = [
                [
                    'account_id' => $expenseAccount->id,
                    'debit_amount' => $transaction->amount,
                    'credit_amount' => 0,
                    'description' => $transaction->description,
                    'category' => $transaction->category,
                ],
                [
                    'account_id' => $transaction->fund->account_id,
                    'debit_amount' => 0,
                    'credit_amount' => $transaction->amount,
                    'description' => "Petty cash disbursement: {$transaction->voucher_number}",
                ]
            ];
        } elseif ($transaction->transaction_type === 'reimbursement') {
            // Replenish petty cash
            $bankAccount = Account::where('code', '1010')->first();
            $lines = [
                [
                    'account_id' => $transaction->fund->account_id,
                    'debit_amount' => $transaction->amount,
                    'credit_amount' => 0,
                    'description' => "Petty cash reimbursement: {$transaction->voucher_number}",
                ],
                [
                    'account_id' => $bankAccount->id,
                    'debit_amount' => 0,
                    'credit_amount' => $transaction->amount,
                    'description' => "Bank payment for petty cash reimbursement",
                ]
            ];
        }
        
        $entry = $accountingService->createAndPostAutomatedEntry(
            PettyCashTransaction::class,
            $transaction->id,
            "Petty Cash: {$transaction->voucher_number}",
            $lines
        );
        
        $transaction->journal_entry_id = $entry->id;
        $transaction->saveQuietly();
    }
}
```

**Reports:**
- Daily Petty Cash Summary
- Expense Analysis by Category
- Custodian Performance Report
- Variance Report

**Dashboard Integration:**
- Petty Cash Balance widget
- Pending Reimbursements alert
- Daily cash count status

### 6.8 Cash Flow Forecasting ⭐ PRIORITY 8

**Purpose:** Predict short-term (weekly/monthly) and long-term cash positions

**Components:**
1. Cash Flow Projections (`/accounting/cash-flow-forecast`)
2. Scenario Modeling (Best/Worst/Expected)
3. Automatic Forecast from Historical Data
4. Manual Adjustments for Known Events
5. Cash Shortage Alerts

**Database Design:**
```php
Schema::create('cash_flow_forecasts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fiscal_year_id')->constrained('fiscal_years');
    $table->string('forecast_name');
    $table->enum('forecast_type', ['weekly', 'monthly', 'quarterly', 'annual']);
    $table->date('start_date');
    $table->date('end_date');
    $table->enum('scenario', ['best_case', 'expected', 'worst_case'])->default('expected');
    $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
    $table->foreignId('created_by')->constrained('users');
    $table->timestamps();
});

Schema::create('cash_flow_forecast_periods', function (Blueprint $table) {
    $table->id();
    $table->foreignId('forecast_id')->constrained('cash_flow_forecasts');
    $table->date('period_start');
    $table->date('period_end');
    $table->integer('period_number');
    $table->decimal('opening_balance', 15, 2);
    $table->decimal('projected_receipts', 15, 2);
    $table->decimal('projected_payments', 15, 2);
    $table->decimal('net_cash_flow', 15, 2);
    $table->decimal('closing_balance', 15, 2);
    $table->decimal('actual_receipts', 15, 2)->nullable();
    $table->decimal('actual_payments', 15, 2)->nullable();
    $table->decimal('actual_closing_balance', 15, 2)->nullable();
    $table->decimal('variance', 15, 2)->nullable();
    $table->timestamps();
});

Schema::create('cash_flow_forecast_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('period_id')->constrained('cash_flow_forecast_periods');
    $table->foreignId('account_id')->constrained('accounts');
    $table->enum('item_type', ['receipt', 'payment']);
    $table->string('description');
    $table->decimal('amount', 15, 2);
    $table->enum('probability', ['certain', 'probable', 'possible'])->default('probable');
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

**Features:**
1. **Auto-generation from Historical Patterns:**
   - Last 3 months average
   - Seasonal adjustments
   - Growth trends

2. **Known Events Integration:**
   - Payroll dates (auto-populated)
   - Loan payments (from liability schedules)
   - Large purchases (from purchase orders)
   - HMO payments (from receivables)

3. **Alerts:**
   - Cash balance below minimum (₦1M)
   - Upcoming shortfalls
   - Over-collection warnings

**Reports:**
- 13-Week Cash Flow Forecast (rolling)
- Monthly Cash Flow Projection
- Forecast vs Actual Analysis
- Cash Burn Rate Analysis

**Dashboard Widget:**
```
Cash Flow Forecast (Next 4 Weeks)
Week 1: ₦5.2M (Safe)
Week 2: ₦3.8M (Safe)
Week 3: ₦1.2M (⚠️ Warning)
Week 4: ₦-0.5M (🚨 Deficit)

Action Required: Accelerate collections or arrange overdraft
```

### 6.9 Patient Deposits/Advance Payments ⭐ PRIORITY 9

**Purpose:** Track patient advance payments and apply to bills

**Components:**
1. Deposit Register (`/accounting/patient-deposits`)
2. Deposit Application to Bills
3. Refund Processing
4. Unutilized Deposits Report

**Database Design:**
```php
Schema::create('patient_deposits', function (Blueprint $table) {
    $table->id();
    $table->foreignId('patient_id')->constrained('patients');
    $table->foreignId('admission_id')->nullable()->constrained('admissions');
    $table->string('deposit_number')->unique();
    $table->date('deposit_date');
    $table->decimal('amount', 15, 2);
    $table->decimal('utilized_amount', 15, 2)->default(0);
    $table->decimal('balance', 15, 2);
    $table->foreignId('journal_entry_id')->constrained('journal_entries');
    $table->enum('deposit_type', ['admission', 'surgery', 'general'])->default('general');
    $table->foreignId('received_by')->constrained('users');
    $table->enum('status', ['active', 'fully_utilized', 'refunded'])->default('active');
    $table->text('notes')->nullable();
    $table->timestamps();
});

Schema::create('patient_deposit_applications', function (Blueprint $table) {
    $table->id();
    $table->foreignId('deposit_id')->constrained('patient_deposits');
    $table->morphs('applicable'); // Payment, ProductOrServiceRequest
    $table->decimal('amount_applied', 15, 2);
    $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
    $table->foreignId('applied_by')->constrained('users');
    $table->timestamps();
});
```

**Database Design:**
```php
Schema::create('patient_deposits', function (Blueprint $table) {
    $table->id();
    $table->foreignId('patient_id')->constrained('patients');
    $table->foreignId('admission_id')->nullable()->constrained('admissions');
    $table->string('deposit_number')->unique();
    $table->date('deposit_date');
    $table->decimal('amount', 15, 2);
    // CRITICAL: utilized_amount and balance calculated from journal entries
    // utilized_amount = SUM(deposit_applications.amount_applied)
    // balance = amount - utilized_amount
    $table->decimal('utilized_amount', 15, 2)->default(0);
    $table->decimal('balance', 15, 2);
    $table->foreignId('journal_entry_id')->constrained('journal_entries'); // Receipt entry
    $table->enum('deposit_type', ['admission', 'surgery', 'general'])->default('general');
    $table->foreignId('received_by')->constrained('users');
    $table->enum('status', ['active', 'fully_utilized', 'refunded'])->default('active');
    $table->text('notes')->nullable();
    $table->timestamps();
});

Schema::create('patient_deposit_applications', function (Blueprint $table) {
    $table->id();
    $table->foreignId('deposit_id')->constrained('patient_deposits');
    $table->morphs('applicable'); // Payment, ProductOrServiceRequest
    $table->decimal('amount_applied', 15, 2);
    $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries'); // Application entry
    $table->foreignId('applied_by')->constrained('users');
    $table->timestamps();
});
```

**Journal Entries (via PatientDepositObserver):**
```php
// NEW: PatientDepositObserver

// When deposit received
public function created(PatientDeposit $deposit)
{
    DEBIT:  Cash (1010)                         ₦100,000
    CREDIT: Patient Deposits Liability (2350)   ₦100,000
    
    $deposit->journal_entry_id = $entry->id;
}

// When applied to bill (via PatientDepositApplication observer)
public function created(PatientDepositApplication $application)
{
    DEBIT:  Patient Deposits Liability (2350)   ₦30,000
    CREDIT: Revenue Account (4xxx)              ₦30,000
    
    $application->journal_entry_id = $entry->id;
    
    // Update deposit balances (calculated from journal entries)
    $deposit->utilized_amount = $deposit->applications->sum('amount_applied');
    $deposit->balance = $deposit->amount - $deposit->utilized_amount;
}

// When refunded
public function refunded(PatientDeposit $deposit, $refundAmount)
{
    DEBIT:  Patient Deposits Liability (2350)   ₦70,000
    CREDIT: Cash (1010)                         ₦70,000
}
```

**Reports:**
- Outstanding Deposits Report
- Deposit Aging (unutilized > 90 days)
- Deposit Utilization Summary

**Dashboard Widget:**
- Total Patient Deposits: ₦2.5M
- Aging > 90 days: ₦450K (requires follow-up)

### 6.10 Capital Budgeting (CAPEX) ⭐ PRIORITY 10

**Purpose:** Long-term capital expenditure planning (3-5 years)

**Components:**
1. CAPEX Planning (`/accounting/capex`)
2. Project Evaluation (NPV, IRR, Payback)
3. Multi-year Budget Allocation
4. Project Approval Workflow
5. Project Tracking & Variance

**Database Design:**
```php
Schema::create('capex_projects', function (Blueprint $table) {
    $table->id();
    $table->string('project_code')->unique();
    $table->string('project_name');
    $table->text('description');
    $table->enum('project_type', [
        'building_construction',
        'equipment_purchase',
        'system_implementation',
        'facility_upgrade',
        'expansion'
    ]);
    $table->foreignId('department_id')->nullable()->constrained('departments');
    $table->decimal('estimated_cost', 15, 2);
    $table->decimal('approved_budget', 15, 2)->nullable();
    $table->decimal('actual_cost', 15, 2)->default(0);
    $table->date('planned_start_date');
    $table->date('planned_end_date');
    $table->date('actual_start_date')->nullable();
    $table->date('actual_end_date')->nullable();
    $table->enum('funding_source', ['internal', 'loan', 'grant', 'mixed']);
    $table->decimal('expected_roi', 5, 2)->nullable();
    $table->integer('payback_period_months')->nullable();
    $table->enum('status', [
        'proposed', 'under_review', 'approved', 'rejected',
        'in_progress', 'completed', 'cancelled'
    ])->default('proposed');
    $table->foreignId('proposed_by')->constrained('users');
    $table->foreignId('approved_by')->nullable()->constrained('users');
    $table->text('justification');
    $table->timestamps();
});

Schema::create('capex_project_cashflows', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained('capex_projects');
    $table->integer('year');
    $table->decimal('capital_outflow', 15, 2);
    $table->decimal('expected_revenue', 15, 2)->default(0);
    $table->decimal('expected_cost_savings', 15, 2)->default(0);
    $table->decimal('net_cashflow', 15, 2);
    $table->timestamps();
});

Schema::create('capex_project_expenditures', function (Blueprint $table) {
    $table->id();
    $table->foreignId('project_id')->constrained('capex_projects');
    $table->foreignId('journal_entry_id')->constrained('journal_entries');
    $table->date('expenditure_date');
    $table->string('description');
    $table->decimal('amount', 15, 2);
    $table->string('vendor')->nullable();
    $table->timestamps();
});
```

**Financial Analysis Tools:**
```php
// Calculate NPV
public function calculateNPV($cashflows, $discountRate)
{
    // Net Present Value calculation
}

// Calculate IRR
public function calculateIRR($cashflows)
{
    // Internal Rate of Return
}

// Payback Period
public function calculatePaybackPeriod($cashflows)
{
    // Years to recover investment
}
```

**Reports:**
- CAPEX Budget vs Actual
- Project ROI Analysis
- Multi-year CAPEX Plan
- Funding Source Analysis
- Project Status Dashboard

**Dashboard Widget:**
```
Active CAPEX Projects: 5
Total Approved Budget: ₦250M
Spent to Date: ₦125M (50%)
Expected Completion: Q3 2026
```

### 6.11 Cost Center Accounting ⭐ PRIORITY 11

**Purpose:** Track costs and revenues by department/service line

**Components:**
1. Cost Center Definition (`/accounting/cost-centers`)
2. Allocation Rules Configuration
3. Inter-department Transfers
4. Cost Center Performance Reports

**Database Design:**
```php
Schema::create('cost_centers', function (Blueprint $table) {
    $table->id();
    $table->string('code', 20)->unique();
    $table->string('name');
    $table->foreignId('department_id')->nullable()->constrained('departments');
    $table->foreignId('manager_user_id')->nullable()->constrained('users');
    $table->enum('center_type', [
        'revenue_center',      // Generates revenue (Consultation, Pharmacy)
        'cost_center',         // Only costs (Administration, Maintenance)
        'profit_center',       // Both revenue and costs (Laboratory)
        'service_center'       // Provides services to other centers (IT, HR)
    ]);
    $table->boolean('is_active')->default(true);
    $table->text('description')->nullable();
    $table->timestamps();
});

Schema::create('cost_center_allocations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('source_cost_center_id')->constrained('cost_centers');
    $table->foreignId('target_cost_center_id')->constrained('cost_centers');
    $table->foreignId('account_id')->constrained('accounts'); // Allocation account
    $table->enum('allocation_method', [
        'percentage',           // Fixed percentage
        'headcount',           // Based on staff numbers
        'square_footage',      // Based on space occupied
        'revenue',             // Based on revenue generated
        'patient_count',       // Based on patient volume
        'manual'               // Manual allocation
    ]);
    $table->decimal('allocation_percentage', 5, 2)->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::create('cost_center_transactions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cost_center_id')->constrained('cost_centers');
    $table->foreignId('journal_entry_line_id')->constrained('journal_entry_lines');
    $table->enum('transaction_type', ['direct', 'allocated']);
    $table->decimal('amount', 15, 2);
    $table->timestamps();
});
```

**Allocation Example:**
```
Service Center: IT Support (Cost: ₦500,000/month)
Allocate to:
├── Consultation (30% / ₦150,000) - Based on staff count
├── Pharmacy (25% / ₦125,000)
├── Laboratory (25% / ₦125,000)
└── Administration (20% / ₦100,000)

Journal Entry (Monthly allocation):
DEBIT:  IT Expense - Consultation (6150)    ₦150,000
DEBIT:  IT Expense - Pharmacy (6151)        ₦125,000
DEBIT:  IT Expense - Laboratory (6152)      ₦125,000
DEBIT:  IT Expense - Administration (6153)  ₦100,000
CREDIT: IT Cost Center Clearing (6100)      ₦500,000
```

**Reports:**
- Cost Center Performance (P&L by center)
- Allocation Summary Report
- Department Profitability Analysis
- Service Center Cost Recovery

**Dashboard Widget:**
```
Most Profitable Centers:
1. Pharmacy: ₦1.2M (45% margin)
2. Laboratory: ₦850K (38% margin)
3. Imaging: ₦620K (35% margin)

Highest Cost Centers:
1. Administration: ₦2.1M
2. Maintenance: ₦890K
```

### 6.12 Medical Equipment Maintenance Tracking ⭐ PRIORITY 12

**Purpose:** Track equipment maintenance schedules, costs, and warranties

**Components:**
1. Equipment Register (extends Fixed Assets)
2. Maintenance Schedule
3. Service Provider Management
4. Maintenance Cost Tracking

**Database Design:**
```php
Schema::create('equipment_maintenance_schedules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fixed_asset_id')->constrained('fixed_assets');
    $table->enum('maintenance_type', ['preventive', 'calibration', 'certification', 'inspection']);
    $table->enum('frequency', ['weekly', 'monthly', 'quarterly', 'semi_annual', 'annual']);
    $table->integer('frequency_days'); // Days between services
    $table->date('last_service_date')->nullable();
    $table->date('next_due_date');
    $table->foreignId('service_provider_id')->nullable()->constrained('suppliers');
    $table->decimal('estimated_cost', 15, 2)->nullable();
    $table->boolean('is_mandatory')->default(false); // Regulatory requirement
    $table->text('notes')->nullable();
    $table->timestamps();
});

Schema::create('equipment_maintenance_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('schedule_id')->constrained('equipment_maintenance_schedules');
    $table->foreignId('fixed_asset_id')->constrained('fixed_assets');
    $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
    $table->date('service_date');
    $table->foreignId('service_provider_id')->nullable()->constrained('suppliers');
    $table->decimal('cost', 15, 2);
    $table->text('work_performed');
    $table->text('findings')->nullable();
    $table->text('recommendations')->nullable();
    $table->string('technician_name')->nullable();
    $table->string('certificate_number')->nullable();
    $table->date('next_service_date')->nullable();
    $table->enum('status', ['completed', 'failed', 'partial'])->default('completed');
    $table->foreignId('logged_by')->constrained('users');
    $table->timestamps();
});

Schema::create('equipment_warranties', function (Blueprint $table) {
    $table->id();
    $table->foreignId('fixed_asset_id')->constrained('fixed_assets');
    $table->string('warranty_number')->nullable();
    $table->foreignId('supplier_id')->constrained('suppliers');
    $table->date('start_date');
    $table->date('end_date');
    $table->integer('warranty_period_months');
    $table->enum('warranty_type', ['manufacturer', 'extended', 'service_contract']);
    $table->text('coverage_details')->nullable();
    $table->decimal('warranty_cost', 15, 2)->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**Features:**
1. **Automatic Reminders:**
   - 30 days before due: Email to department head
   - 7 days before due: Email to maintenance team
   - Overdue: Daily escalation emails

2. **Compliance Tracking:**
   - Regulatory requirements (e.g., annual calibration for lab equipment)
   - Certification expiry alerts

3. **Cost Analysis:**
   - Maintenance cost per equipment
   - Total maintenance spend by department
   - Warranty utilization tracking

**Reports:**
- Upcoming Maintenance Schedule
- Overdue Maintenance Report
- Maintenance Cost Analysis
- Equipment Downtime Report
- Warranty Expiry Report

**Dashboard Widget:**
```
Equipment Maintenance Status:
✓ Up to Date: 85 items
⚠️ Due Soon (30 days): 12 items
🚨 Overdue: 3 items

This Month Maintenance Cost: ₦450,000
```

### 6.13 Lease/Rental Management (IFRS 16) ⭐ PRIORITY 13

**Purpose:** Comply with IFRS 16 lease accounting standards

**Components:**
1. Lease Register (`/accounting/leases`)
2. Right-of-Use Asset Calculation
3. Lease Liability Schedule
4. Monthly Lease Expense Recognition

**Database Design:**
```php
Schema::create('leases', function (Blueprint $table) {
    $table->id();
    $table->string('lease_number')->unique();
    $table->enum('lease_type', [
        'property',          // Building/Office lease
        'equipment',         // Equipment rental
        'vehicle',           // Vehicle lease
        'other'
    ]);
    $table->string('leased_item');
    $table->foreignId('lessor_id')->constrained('suppliers'); // Landlord/Lessor
    $table->foreignId('rou_asset_account_id')->constrained('accounts'); // Right-of-Use Asset
    $table->foreignId('lease_liability_account_id')->constrained('accounts');
    $table->date('commencement_date');
    $table->date('end_date');
    $table->integer('lease_term_months');
    $table->decimal('monthly_payment', 15, 2);
    $table->decimal('total_lease_payments', 15, 2);
    $table->decimal('initial_rou_asset_value', 15, 2);
    $table->decimal('initial_lease_liability', 15, 2);
    $table->decimal('current_rou_asset_value', 15, 2);
    $table->decimal('current_lease_liability', 15, 2);
    $table->decimal('incremental_borrowing_rate', 5, 2); // Discount rate
    $table->boolean('has_purchase_option')->default(false);
    $table->decimal('purchase_option_amount', 15, 2)->nullable();
    $table->enum('status', ['active', 'expired', 'terminated'])->default('active');
    $table->text('notes')->nullable();
    $table->timestamps();
});

Schema::create('lease_payment_schedules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('lease_id')->constrained('leases');
    $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
    $table->integer('payment_number');
    $table->date('payment_date');
    $table->decimal('payment_amount', 15, 2);
    $table->decimal('interest_expense', 15, 2);
    $table->decimal('principal_reduction', 15, 2);
    $table->decimal('depreciation_expense', 15, 2);
    $table->decimal('remaining_liability', 15, 2);
    $table->enum('payment_status', ['pending', 'paid', 'overdue'])->default('pending');
    $table->date('actual_payment_date')->nullable();
    $table->timestamps();
});
```

**IFRS 16 Journal Entries:**
```php
// Initial Recognition (Lease commencement)
DEBIT:  Right-of-Use Asset (1420)          ₦10,000,000
CREDIT: Lease Liability (2450)             ₦10,000,000

// Monthly Payment
DEBIT:  Lease Liability (2450)             ₦150,000
DEBIT:  Interest Expense (6110)            ₦50,000
CREDIT: Cash (1010)                        ₦200,000

// Monthly Depreciation
DEBIT:  Depreciation Expense (6210)        ₦166,667
CREDIT: Accumulated Depreciation (1421)    ₦166,667
```

**Reports:**
- Lease Portfolio Summary
- Lease Payment Schedule
- Lease Liability Schedule
- IFRS 16 Disclosure Notes
- Lease vs Buy Analysis

**Dashboard Widget:**
```
Active Leases: 8
Total Monthly Obligation: ₦850,000
Total Lease Liability: ₦48.5M
Expiring in 6 months: 2 leases
```

### 6.14 Inter-Account Transfer Module ⭐ PRIORITY 14

**Purpose:** Track fund movements between bank accounts

**Components:**
1. Transfer Register (`/accounting/transfers`)
2. In-transit Tracking
3. Transfer Reconciliation

**Database Design:**
```php
Schema::create('inter_account_transfers', function (Blueprint $table) {
    $table->id();
    $table->string('transfer_number')->unique();
    $table->foreignId('from_bank_id')->constrained('banks');
    $table->foreignId('to_bank_id')->constrained('banks');
    $table->foreignId('from_account_id')->constrained('accounts'); // GL account
    $table->foreignId('to_account_id')->constrained('accounts');
    $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries');
    $table->date('transfer_date');
    $table->decimal('amount', 15, 2);
    $table->string('reference')->nullable();
    $table->text('description');
    $table->date('expected_clearance_date')->nullable();
    $table->date('actual_clearance_date')->nullable();
    $table->enum('status', ['initiated', 'in_transit', 'cleared', 'failed'])->default('initiated');
    $table->foreignId('initiated_by')->constrained('users');
    $table->foreignId('approved_by')->nullable()->constrained('users');
    $table->timestamps();
});
```

**Database Design:**
```php
Schema::create('inter_account_transfers', function (Blueprint $table) {
    $table->id();
    $table->string('transfer_number')->unique();
    $table->foreignId('from_bank_id')->constrained('banks');
    $table->foreignId('to_bank_id')->constrained('banks');
    $table->foreignId('from_account_id')->constrained('accounts'); // GL account
    $table->foreignId('to_account_id')->constrained('accounts');
    $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries'); // Link to GL
    $table->date('transfer_date');
    $table->decimal('amount', 15, 2);
    $table->string('reference')->nullable();
    $table->text('description');
    $table->date('expected_clearance_date')->nullable();
    $table->date('actual_clearance_date')->nullable();
    $table->enum('status', ['initiated', 'in_transit', 'cleared', 'failed'])->default('initiated');
    $table->foreignId('initiated_by')->constrained('users');
    $table->foreignId('approved_by')->nullable()->constrained('users');
    $table->timestamps();
});
```

**Journal Entry (via TransferObserver):**
```php
// NEW: TransferObserver

public function updated(InterAccountTransfer $transfer)
{
    // When transfer approved
    if ($transfer->isDirty('status') && $transfer->status === 'cleared') {
        // Single journal entry moves money
        DEBIT:  Bank B (1010-002)                  ₦500,000
        CREDIT: Bank A (1010-001)                  ₦500,000
        
        $transfer->journal_entry_id = $entry->id;
    }
}

// Bank balances always from journal entries:
// bank_balance = SUM(je_lines.debit - je_lines.credit WHERE je_lines.account_id = bank.account_id)
```

**Reports:**
- Transfers in Transit
- Transfer History
- Failed Transfers Report

### 6.15 Key Performance Indicators (KPI) Dashboard ⭐ PRIORITY 15

**Purpose:** Real-time financial health monitoring

**Components:**
1. KPI Configuration (`/accounting/kpis`)
2. Target Setting
3. Automated Calculation
4. Alert System

**KPIs to Track:**
```php
Schema::create('financial_kpis', function (Blueprint $table) {
    $table->id();
    $table->string('kpi_code')->unique();
    $table->string('kpi_name');
    $table->string('category'); // 'liquidity', 'profitability', 'efficiency', 'solvency'
    $table->text('description');
    $table->text('calculation_formula');
    $table->string('unit'); // 'ratio', 'days', 'percent', 'amount'
    $table->enum('frequency', ['daily', 'weekly', 'monthly', 'quarterly']);
    $table->decimal('target_value', 15, 2)->nullable();
    $table->decimal('warning_threshold', 15, 2)->nullable();
    $table->decimal('critical_threshold', 15, 2)->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});

Schema::create('financial_kpi_values', function (Blueprint $table) {
    $table->id();
    $table->foreignId('kpi_id')->constrained('financial_kpis');
    $table->foreignId('fiscal_period_id')->nullable()->constrained('accounting_periods');
    $table->date('value_date');
    $table->decimal('actual_value', 15, 2);
    $table->decimal('target_value', 15, 2)->nullable();
    $table->decimal('variance', 15, 2)->nullable();
    $table->enum('status', ['good', 'warning', 'critical'])->default('good');
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

**Critical Hospital KPIs:**
```
LIQUIDITY RATIOS:
1. Current Ratio = Current Assets / Current Liabilities (Target: > 2.0)
2. Quick Ratio = (Current Assets - Inventory) / Current Liabilities (Target: > 1.0)
3. Cash Ratio = Cash / Current Liabilities (Target: > 0.5)
4. Days Cash on Hand = Cash / (Operating Expenses / 365) (Target: > 60 days)

PROFITABILITY RATIOS:
5. Operating Margin = Operating Income / Revenue (Target: > 15%)
6. Net Profit Margin = Net Income / Revenue (Target: > 10%)
7. Return on Assets (ROA) = Net Income / Total Assets (Target: > 8%)
8. Revenue per Patient Visit (Target: ₦25,000+)

EFFICIENCY RATIOS:
9. Accounts Receivable Days = (Receivables / Revenue) * 365 (Target: < 45 days)
10. Accounts Payable Days = (Payables / COGS) * 365 (Target: 30-45 days)
11. Inventory Turnover = COGS / Average Inventory (Target: > 8x/year)
12. Asset Turnover = Revenue / Total Assets (Target: > 1.0)

SOLVENCY RATIOS:
13. Debt-to-Equity = Total Liabilities / Total Equity (Target: < 1.5)
14. Debt Service Coverage = Operating Income / Debt Obligations (Target: > 1.25)
15. Interest Coverage = EBIT / Interest Expense (Target: > 3.0)

OPERATIONAL METRICS:
16. Bed Occupancy Rate = Occupied Bed Days / Available Bed Days (Target: > 70%)
17. Revenue per Bed = Total Revenue / Number of Beds
18. Average Length of Stay (ALOS) (Target: < 5 days)
19. Staff Cost as % of Revenue (Target: < 45%)
20. Drug Cost as % of Revenue (Target: < 25%)
```

**Dashboard Widget:**
```
Financial Health Score: 85/100 (Good)

🟢 Current Ratio: 2.3 (Target: 2.0)
🟢 Operating Margin: 18% (Target: 15%)
🟡 AR Days: 52 (Target: 45) ⚠️
🔴 Debt-to-Equity: 1.8 (Target: 1.5) 🚨

Top Concerns:
1. High receivables - Collection needed
2. Debt levels increasing - Review borrowing
```

---

## 7. IMPLEMENTATION PRIORITY & TIMELINE (UPDATED)

**PREREQUISITES FOR ALL PHASES:**
1. ✓ Validate journal entry centricity (all reports query journal_entries only)
2. ✓ Test observer automation (all domain events create journal entries)
3. ✓ Verify no parallel data sources (remove any non-journal queries)
4. ✓ Ensure metadata completeness (patient_id, supplier_id, category in all entries)

### Phase 0: System Integration Validation (Week 0 - CRITICAL) ⭐⭐⭐

**Purpose:** Validate existing system before adding new modules

**Tasks:**
1. **Audit All Existing Reports:**
   - Review every report query
   - Ensure 100% derive from journal_entries
   - Identify any non-compliant reports
   - Fix or remove non-compliant reports

2. **Test All Existing Observers:**
   - Payment Observer: Verify journal entries created
   - Expense Observer: Verify journal entries created
   - PO Observer: Verify journal entries created
   - Payroll Observer: Verify journal entries created
   - HMO Observer: Verify journal entries created

3. **Validate Balance Sheet Reconciliation:**
   ```sql
   -- ALL account balances must equal journal entry sums
   SELECT a.code, a.name,
          (SELECT SUM(debit_amount - credit_amount) 
           FROM journal_entry_lines 
           WHERE account_id = a.id) as je_balance,
          a.current_balance as stored_balance,
          ABS((SELECT SUM(debit_amount - credit_amount) FROM journal_entry_lines WHERE account_id = a.id) - a.current_balance) as variance
   FROM accounts a
   HAVING variance > 0.01;
   ```
   - If variance > 0, FIX before proceeding

4. **Document Observer Patterns:**
   - Create observer template for all new observers
   - Document metadata requirements
   - Establish testing protocol

**Deliverables:**
- ✓ System validation report
- ✓ All variances resolved
- ✓ Observer template documented
- ✓ Proceed/No-proceed decision

### Phase 1: Bank Management & Daily Cash Operations (Weeks 1-2) ⭐ CRITICAL
**Why First:** Most urgent - needed for daily operations and reconciliation

**Bank Management (Module 6.1):**
1. Enhance `banks` table structure
2. Create Bank Dashboard showing all banks with real balances
3. Update Bank Statement Report to show physical + GL
4. Add bank filtering to all relevant reports

**Petty Cash Management (Module 6.6):**
1. Create petty cash tables
2. Build petty cash register interface
3. Implement reimbursement workflow
4. Daily cash count & reconciliation

**Inter-Account Transfers (Module 6.13):**
1. Create transfer tables
2. Build transfer interface with approval
3. Track in-transit transfers
4. Transfer reconciliation

**Reports Added:**
- Bank Dashboard
- Petty Cash Summary
- Transfer Register

### Phase 2: Bank Reconciliation (Weeks 3-4) ⭐ CRITICAL
**Why Second:** International audit requirement (IAS 7)

**Bank Reconciliation (Module 6.2 from original plan):**
1. Create reconciliation tables
2. Build reconciliation interface
3. Implement statement upload/parsing
4. Create matching algorithm (target 80% automation)
5. Auto-generate adjustment entries
6. Reconciliation workflow & approval

**Reports Added:**
- Bank Reconciliation Statement
- Outstanding Items Report
- Reconciliation History

### Phase 3: Short-term Cash Management (Week 5) ⭐ HIGH PRIORITY
**Why Third:** Prevent cash shortages and improve liquidity management

**Cash Flow Forecasting (Module 6.7):**
1. Create forecast tables
2. Build 13-week rolling forecast interface
3. Implement auto-generation from historical data
4. Known events integration (payroll, loans, large purchases)
5. Scenario modeling (best/expected/worst)
6. Cash shortage alerts

**Patient Deposits (Module 6.8):**
1. Create deposit tables
2. Build deposit register interface
3. Implement deposit application to bills
4. Refund processing workflow
5. Unutilized deposits tracking

**Reports Added:**
- 13-Week Cash Flow Forecast
- Monthly Cash Flow Projection
- Forecast vs Actual Analysis
- Patient Deposits Report
- Deposit Aging Report

### Phase 4: Daily Management Reports (Week 6)
**Why Fourth:** Real-time visibility for management decisions

**Dashboard & Reports (from original plan Section 5.2):**
1. Cash Position Report (Daily)
2. Revenue by Department (Daily)
3. Outstanding Receivables Summary (Weekly)
4. Payables Summary (Weekly)
5. Expense Analysis by Category
6. Inventory Valuation Report

**KPI Dashboard (Module 6.14):**
1. Create KPI configuration tables
2. Implement 20 key financial KPIs
3. Automated calculation engine
4. Alert system for thresholds
5. Real-time KPI dashboard

**Reports Added:**
- Daily Cash Position
- Department Revenue Performance
- AR/AP Aging
- Financial Health Dashboard

### Phase 5: Fixed Assets Management (Weeks 7-8) ⭐ MEDIUM PRIORITY
**Why Fifth:** Compliance with IAS 16 and asset protection

**Fixed Assets Register (from original plan):**
1. Create fixed assets tables
2. Build asset register interface
3. Asset categorization and tagging
4. Depreciation setup (straight-line, reducing balance)
5. Monthly depreciation automation
6. Asset disposal/transfer workflow

**Equipment Maintenance (Module 6.11):**
1. Create maintenance tables
2. Build maintenance schedule interface
3. Service provider management
4. Automatic maintenance reminders
5. Warranty tracking
6. Cost tracking per equipment

**Reports Added:**
- Fixed Assets Register
- Depreciation Schedule
- Asset Valuation Report
- Upcoming Maintenance Schedule
- Maintenance Cost Analysis
- Warranty Expiry Report

### Phase 6: Long-term Liabilities & Leases (Weeks 9-10) ⭐ MEDIUM PRIORITY
**Why Sixth:** IFRS 16 compliance and debt management

**Liabilities Management (from original plan):**
1. Create liability schedules tables
2. Build loan/liability register
3. Payment schedule generation
4. Interest calculation automation
5. Automatic payment reminders
6. Amortization tracking

**Lease Management (Module 6.12 - IFRS 16):**
1. Create lease tables
2. Build lease register interface
3. Right-of-Use asset calculation
4. Lease liability schedule
5. Monthly lease expense recognition
6. IFRS 16 disclosure reports

**Reports Added:**
- Liability Schedules
- Amortization Schedule
- Debt Service Coverage Report
- Lease Portfolio Summary
- IFRS 16 Disclosure Notes

### Phase 7: Cost Center Accounting (Week 11) ⭐ MEDIUM PRIORITY
**Why Seventh:** Department profitability and cost allocation

**Cost Center Module (Module 6.10):**
1. Create cost center tables
2. Define cost centers by department
3. Configure allocation rules
4. Implement allocation methods (percentage, headcount, revenue, etc.)
5. Monthly allocation automation
6. Inter-department transfer tracking

**Reports Added:**
- Cost Center Performance (P&L by center)
- Allocation Summary Report
- Department Profitability Analysis
- Service Center Cost Recovery

### Phase 8: Capital Budgeting & Long-term Planning (Week 12) ⭐ LOW PRIORITY
**Why Eighth:** Strategic planning for growth

**CAPEX Module (Module 6.9):**
1. Create CAPEX project tables
2. Build CAPEX planning interface
3. Project evaluation tools (NPV, IRR, Payback)
4. Multi-year budget allocation
5. Project approval workflow
6. Project tracking & variance

**Reports Added:**
- CAPEX Budget vs Actual
- Project ROI Analysis
- Multi-year CAPEX Plan
- Funding Source Analysis
- Project Status Dashboard

### Phase 9: Budget Management (Weeks 13-14) ⭐ LOW PRIORITY
**Why Ninth:** Enhanced budget control

**Budget Module (from original plan):**
1. Create budget tables
2. Build budget creation interface
3. Budget templates (by department/year)
4. Budget approval workflow
5. Budget vs actual analysis
6. Variance reports & alerts

**Reports Added:**
- Budget vs Actual Report
- Variance Analysis
- Budget Performance by Department
- Rolling Forecast vs Budget

### Phase 10: Revenue Enhancements (Weeks 15-16) ⭐ LOW PRIORITY
**Why Last:** System already handles revenue well via observers

**Revenue Recognition Engine (from original plan):**
1. Revenue categories configuration
2. Deferred revenue management
3. Installment contracts tracking
4. Revenue recognition rules
5. Advanced revenue analysis reports

**Reports Added:**
- Revenue by Category
- Deferred Revenue Schedule
- Installment Contracts Report
- Revenue Recognition Analysis

---

### IMPLEMENTATION SUMMARY BY PRIORITY

**CRITICAL (Weeks 1-4):** ⭐⭐⭐
- Bank Management
- Petty Cash
- Inter-Account Transfers
- Bank Reconciliation

**HIGH (Weeks 5-6):** ⭐⭐
- Cash Flow Forecasting
- Patient Deposits
- Daily Management Reports
- KPI Dashboard

**MEDIUM (Weeks 7-11):** ⭐
- Fixed Assets & Maintenance
- Long-term Liabilities
- Lease Management (IFRS 16)
- Cost Center Accounting

**LOW (Weeks 12-16):**
- CAPEX Planning
- Budget Management
- Revenue Enhancements

**TOTAL TIMELINE: 16 WEEKS (4 MONTHS)**

---

## 8. TECHNICAL ARCHITECTURE (UPDATED)

### 8.1 Core Principle: Journal Entry Remains Center

```
ALL TRANSACTIONS FLOW THROUGH JOURNAL ENTRIES

External Events:
├── Payments (Cash/Bank) ──────────┐
├── Expenses ──────────────────────┤
├── Purchases ─────────────────────┤
├── Payroll ───────────────────────┤
├── HMO Remittances ───────────────┤
├── Asset Depreciation ────────────┤
├── Bank Reconciliation ───────────┼──→ Observers ──→ JOURNAL ENTRIES ──→ GL Accounts
├── Loan Interest ─────────────────┤                        ↓
├── Lease Payments (IFRS 16) ──────┤                   REPORTS
├── Cost Center Allocations ───────┤                (All pull from
├── Equipment Maintenance ─────────┤                 journal entries)
├── Patient Deposits ──────────────┤
├── Inter-Account Transfers ───────┤
└── Adjustments ───────────────────┘
```

### 8.2 Service Layer Organization (UPDATED)

```php
app/Services/Accounting/
├── Core Services (Existing) ✓
│   ├── AccountingService.php         (Core - journal entry creation)
│   ├── ReportService.php             (All reports)
│   └── SubAccountService.php         (Sub-accounts)
│
├── Day-to-Day Operations (NEW - Phase 1-3)
│   ├── BankService.php               (Bank management)
│   ├── ReconciliationService.php     (Bank reconciliation)
│   ├── PettyCashService.php          (Petty cash tracking)
│   ├── TransferService.php           (Inter-account transfers)
│   ├── CashFlowService.php           (Cash flow forecasting)
│   └── PatientDepositService.php     (Deposit management)
│
├── Asset & Liability Management (NEW - Phase 5-6)
│   ├── FixedAssetService.php         (Asset management)
│   ├── MaintenanceService.php        (Equipment maintenance)
│   ├── LiabilityService.php          (Long-term debt management)
│   └── LeaseService.php              (IFRS 16 lease accounting)
│
├── Planning & Analysis (NEW - Phase 7-10)
│   ├── CostCenterService.php         (Cost center accounting)
│   ├── CapexService.php              (Capital budgeting)
│   ├── BudgetService.php             (Budget management)
│   ├── KpiService.php                (KPI calculations)
│   └── RevenueService.php            (Revenue recognition)
│
└── Integration Layer
    ├── StatementParserService.php    (Bank statement parsing)
    ├── AllocationService.php         (Cost allocations)
    └── ForecastingService.php        (Predictive analytics)
```

### 8.3 Observer Pattern (EXPANDED)

```php
app/Observers/Accounting/
├── Existing Observers ✓
│   ├── PaymentObserver.php
│   ├── ExpenseObserver.php
│   ├── PurchaseOrderObserver.php
│   ├── PayrollBatchObserver.php
│   └── HmoRemittanceObserver.php
│
└── New Observers (to be added per phase)
    ├── DepreciationObserver.php      (Phase 5 - Monthly auto-depreciation)
    ├── LoanPaymentObserver.php       (Phase 6 - Loan interest & principal)
    ├── LeasePaymentObserver.php      (Phase 6 - IFRS 16 entries)
    ├── MaintenanceObserver.php       (Phase 5 - Maintenance costs)
    ├── PatientDepositObserver.php    (Phase 3 - Deposit entries)
    └── AllocationObserver.php        (Phase 7 - Monthly allocations)
```

### 8.4 Controller Organization (UPDATED)

```php
app/Http/Controllers/Accounting/
├── Existing ✓
│   ├── AccountController.php
│   ├── JournalEntryController.php
│   ├── ReportController.php
│   └── FiscalPeriodController.php
│
├── Day-to-Day Operations (NEW)
│   ├── BankController.php
│   ├── BankReconciliationController.php
│   ├── PettyCashController.php
│   ├── TransferController.php
│   ├── CashFlowController.php
│   └── PatientDepositController.php
│
├── Asset & Liability Management (NEW)
│   ├── FixedAssetController.php
│   ├── MaintenanceController.php
│   ├── LiabilityController.php
│   └── LeaseController.php
│
└── Planning & Analysis (NEW)
    ├── CostCenterController.php
    ├── CapexController.php
    ├── BudgetController.php
    ├── KpiController.php
    └── DashboardController.php (enhanced)
```

### 8.5 Route Structure (UPDATED)

```php
// routes/accounting.php (NEW - to be created)

// Day-to-Day Operations
Route::prefix('accounting')->group(function () {
    
    // Banks & Cash
    Route::resource('banks', BankController::class);
    Route::get('banks/{bank}/reconciliation', [BankReconciliationController::class, 'index']);
    Route::post('banks/{bank}/reconciliation/upload', [BankReconciliationController::class, 'uploadStatement']);
    Route::resource('petty-cash', PettyCashController::class);
    Route::post('petty-cash/{fund}/reimbursement', [PettyCashController::class, 'reimbursement']);
    Route::resource('transfers', TransferController::class);
    Route::get('cash-flow/forecast', [CashFlowController::class, 'forecast']);
    Route::resource('patient-deposits', PatientDepositController::class);
    
    // Assets & Liabilities
    Route::resource('fixed-assets', FixedAssetController::class);
    Route::post('fixed-assets/{asset}/depreciate', [FixedAssetController::class, 'depreciate']);
    Route::resource('maintenance', MaintenanceController::class);
    Route::get('maintenance/upcoming', [MaintenanceController::class, 'upcoming']);
    Route::resource('liabilities', LiabilityController::class);
    Route::get('liabilities/{liability}/schedule', [LiabilityController::class, 'paymentSchedule']);
    Route::resource('leases', LeaseController::class);
    Route::get('leases/{lease}/schedule', [LeaseController::class, 'paymentSchedule']);
    
    // Planning & Analysis
    Route::resource('cost-centers', CostCenterController::class);
    Route::post('cost-centers/allocate', [CostCenterController::class, 'allocate']);
    Route::resource('capex', CapexController::class);
    Route::get('capex/{project}/analysis', [CapexController::class, 'financialAnalysis']);
    Route::resource('budgets', BudgetController::class);
    Route::get('budgets/variance', [BudgetController::class, 'varianceAnalysis']);
    Route::get('kpis', [KpiController::class, 'index']);
    Route::get('kpis/{kpi}/trend', [KpiController::class, 'trend']);
    
    // Enhanced Dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);
    Route::get('dashboard/cash-position', [DashboardController::class, 'cashPosition']);
    Route::get('dashboard/department-revenue', [DashboardController::class, 'departmentRevenue']);
});
```

### 8.6 Database Schema Summary (NEW TABLES)

**Total New Tables: 29**

**Day-to-Day Operations (8 tables):**
1. `petty_cash_funds`
2. `petty_cash_transactions`
3. `petty_cash_reconciliations`
4. `cash_flow_forecasts`
5. `cash_flow_forecast_periods`
6. `cash_flow_forecast_items`
7. `patient_deposits`
8. `patient_deposit_applications`
9. `inter_account_transfers`

**Asset Management (6 tables):**
10. `equipment_maintenance_schedules`
11. `equipment_maintenance_logs`
12. `equipment_warranties`

**Liability Management (5 tables):**
13. `liability_schedules`
14. `liability_payments`
15. `leases`
16. `lease_payment_schedules`

**Planning & Analysis (10 tables):**
17. `cost_centers`
18. `cost_center_allocations`
19. `cost_center_transactions`
20. `capex_projects`
21. `capex_project_cashflows`
22. `capex_project_expenditures`
23. `budgets` (note: was in original plan)
24. `financial_kpis`
25. `financial_kpis_values`
26. `revenue_categories` (from original plan)
27. `revenue_installments` (from original plan)
28. `bank_reconciliations` (from original plan)
29. `bank_reconciliation_items` (from original plan)

**Enhanced Existing Tables:**
- `banks` (add 8 columns: current_balance, last_statement_date, bank_type, etc.)
- `fixed_assets` (from original plan - new table)

### 8.7 Report Integration (CENTRAL REPORTING MODULE)

**ABSOLUTE REQUIREMENT: ALL reports derive from journal_entries table ONLY.**

**Validation Checklist (MUST PASS):**
- ✓ Report queries join through journal_entries or journal_entry_lines
- ✓ No queries directly to source tables (payments, expenses, POs) for amounts
- ✓ Balances calculated as: SUM(debit_amount - credit_amount)
- ✓ Filters use journal_entry_lines.category, patient_id, supplier_id, etc. (metadata)
- ✗ REJECT any report that cannot derive from journal entries

**All new reports integrate into existing ReportService.php and ReportController.php:**

```php
// app/Services/Accounting/ReportService.php (ENHANCED)

class ReportService
{
    // Existing Reports ✓
    public function generateGeneralLedger($accountId, $fromDate, $toDate) { }
    public function generateTrialBalance($fiscalPeriodId) { }
    public function generateBalanceSheet($fiscalPeriodId) { }
    public function generateProfitAndLoss($fiscalPeriodId) { }
    public function generateCashFlow($fiscalPeriodId) { }
    
    // Day-to-Day Reports (NEW - ALL from journal entries)
    public function generateCashPositionReport($date) 
    { 
        // Query:
        // SELECT SUM(debit_amount - credit_amount) as balance
        // FROM journal_entry_lines
        // WHERE account_id IN (SELECT id FROM accounts WHERE code LIKE '1010%')
        // AND date <= $date
    }
    
    public function generateDepartmentRevenueReport($fromDate, $toDate) 
    {
        // Query:
        // SELECT department_id, SUM(credit_amount) as revenue
        // FROM journal_entry_lines
        // JOIN journal_entries ON ...
        // WHERE account_id IN (SELECT id FROM accounts WHERE code LIKE '4%')
        // AND date BETWEEN $fromDate AND $toDate
        // GROUP BY department_id
    }
    
    public function generatePettyCashReport($fundId, $fromDate, $toDate) 
    {
        // Query:
        // SELECT je.*, jel.debit_amount, jel.credit_amount
        // FROM journal_entry_lines jel
        // JOIN petty_cash_funds pcf ON jel.account_id = pcf.account_id
        // WHERE pcf.id = $fundId
        // AND je.date BETWEEN $fromDate AND $toDate
    }
    
    public function generateTransferRegister($fromDate, $toDate) 
    {
        // Query:
        // SELECT iat.*, je.*
        // FROM inter_account_transfers iat
        // JOIN journal_entries je ON iat.journal_entry_id = je.id
        // WHERE je.date BETWEEN $fromDate AND $toDate
    }
    
    public function generateCashFlowForecast($weeks = 13) 
    {
        // Historical data from journal entries:
        // SELECT DATE(je.date) as date, 
        //        SUM(CASE WHEN a.code LIKE '1010%' AND jel.debit_amount > 0 THEN jel.debit_amount ELSE 0 END) as receipts,
        //        SUM(CASE WHEN a.code LIKE '1010%' AND jel.credit_amount > 0 THEN jel.credit_amount ELSE 0 END) as payments
        // FROM journal_entry_lines jel
        // JOIN journal_entries je ON jel.journal_entry_id = je.id
        // JOIN accounts a ON jel.account_id = a.id
        // WHERE je.date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        // GROUP BY DATE(je.date)
    }
    
    public function generatePatientDepositsReport($status = 'all') 
    {
        // Query:
        // SELECT pd.*, je.entry_number, jel.debit_amount
        // FROM patient_deposits pd
        // JOIN journal_entries je ON pd.journal_entry_id = je.id
        // JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id
        // WHERE jel.account_id = (SELECT id FROM accounts WHERE code = '2350')
    }
    
    // Asset Reports (NEW - ALL from journal entries)
    public function generateFixedAssetsRegister($asOf = null) 
    {
        // Query:
        // SELECT fa.*, 
        //        (SELECT SUM(jel.debit_amount) FROM journal_entry_lines jel 
        //         WHERE jel.account_id = fa.account_id AND jel.journal_entry_id = fa.journal_entry_id) as cost,
        //        (SELECT SUM(jel.credit_amount) FROM journal_entry_lines jel
        //         JOIN accounts a ON jel.account_id = a.id
        //         WHERE a.code = '1410' AND jel.description LIKE CONCAT('%', fa.asset_number, '%')) as accumulated_depreciation
        // FROM fixed_assets fa
    }
    
    public function generateDepreciationSchedule($fiscalYearId) 
    {
        // Query:
        // SELECT je.date, je.entry_number, fa.name, jel.credit_amount as depreciation
        // FROM journal_entries je
        // JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id
        // JOIN accounts a ON jel.account_id = a.id
        // WHERE a.code = '1410' -- Accumulated Depreciation
        // AND je.fiscal_year_id = $fiscalYearId
    }
    public function generateMaintenanceSchedule($daysAhead = 90) { }
    public function generateMaintenanceCostAnalysis($fromDate, $toDate) { }
    public function generateWarrantyExpiryReport($daysAhead = 90) { }
    
    // Liability Reports (NEW)
    public function generateLiabilitySchedules($asOf = null) { }
    public function generateAmortizationSchedule($liabilityId) { }
    public function generateDebtServiceReport($fiscalYearId) { }
    public function generateLeasePortfolio($asOf = null) { }
    public function generateIfrs16DisclosureNotes($fiscalYearId) { }
    
    // Planning Reports (NEW)
    public function generateCostCenterPerformance($periodId, $costCenterId = null) { }
    public function generateAllocationSummary($periodId) { }
    public function generateCapexBudgetVsActual($fiscalYearId) { }
    public function generateProjectRoiAnalysis($projectId) { }
    public function generateBudgetVarianceAnalysis($periodId, $departmentId = null) { }
    public function generateKpiDashboard($date = null) { }
    public function generateKpiTrend($kpiId, $periods = 12) { }
}
```

**REMOVED/REJECTED Reports (Cannot derive from journal entries):**

NONE - All planned reports CAN and MUST derive from journal entries.

**If any report cannot be derived from journal_entries:**
1. Redesign the report to use journal entries
2. Add necessary metadata to journal_entry_lines
3. Create appropriate observer to capture the data
4. If truly impossible, DO NOT implement the report

**Examples of CORRECT journal entry queries:**
```
Reports Menu:
├── Financial Statements
│   ├── Trial Balance ✓
│   ├── Profit & Loss ✓
│   ├── Balance Sheet ✓
│   ├── Cash Flow Statement ✓
│   └── Statement of Changes in Equity (NEW)
│
├── Day-to-Day Operations (NEW)
│   ├── Cash Position Report (Daily)
│   ├── Department Revenue (Daily)
│   ├── Petty Cash Summary
│   ├── Transfer Register
│   ├── Cash Flow Forecast
│   └── Patient Deposits Report
│
├── General Ledger ✓
│   ├── General Ledger Report ✓
│   ├── Bank Statement ✓
│   └── Account Analysis ✓
│
├── Receivables & Payables
│   ├── AR Aging Report ✓
│   ├── AP Aging Report ✓
│   ├── Receivables Summary ✓
│   └── Payables Summary ✓
│
├── Asset Management (NEW)
│   ├── Fixed Assets Register
│   ├── Depreciation Schedule
│   ├── Asset Valuation Report
│   ├── Maintenance Schedule
│   ├── Maintenance Cost Analysis
│   └── Warranty Expiry Report
│
├── Liability Management (NEW)
│   ├── Liability Schedules
│   ├── Amortization Schedule
│   ├── Debt Service Coverage
│   ├── Lease Portfolio
│   └── IFRS 16 Disclosures
│
├── Bank Reconciliation (NEW)
│   ├── Reconciliation Statement
│   ├── Outstanding Items
│   └── Reconciliation History
│
├── Budget & Planning (NEW)
│   ├── Budget vs Actual
│   ├── Variance Analysis
│   ├── CAPEX Budget Report
│   ├── Project ROI Analysis
│   └── Multi-year CAPEX Plan
│
├── Cost Center Reports (NEW)
│   ├── Cost Center Performance
│   ├── Department Profitability
│   ├── Allocation Summary
│   └── Service Center Recovery
│
├── Management Reports (NEW)
│   ├── KPI Dashboard
│   ├── Financial Health Score
│   ├── Revenue Analysis
│   └── Expense Analysis
│
└── Audit & Compliance ✓
    ├── Journal Entry Audit Trail ✓
    ├── Approval Status Report ✓
    └── User Activity Log ✓
```

### 8.8 Dashboard Integration (ENHANCED)

**CRITICAL: All dashboard numbers derive from journal entries ONLY**

**Dashboard Data Sources (VALIDATION):**
```sql
-- Cash Position (from journal entries)
SELECT SUM(debit_amount - credit_amount) 
FROM journal_entry_lines 
WHERE account_id IN (SELECT id FROM accounts WHERE code LIKE '1010%');

-- Revenue Today (from journal entries)
SELECT SUM(credit_amount) 
FROM journal_entry_lines jel
JOIN journal_entries je ON jel.journal_entry_id = je.id
WHERE jel.account_id IN (SELECT id FROM accounts WHERE code LIKE '4%')
AND DATE(je.date) = CURDATE();

-- Receivables (from journal entries)
SELECT SUM(debit_amount - credit_amount) 
FROM journal_entry_lines 
WHERE account_id IN (SELECT id FROM accounts WHERE code LIKE '1200%');

-- Expenses (from journal entries)
SELECT SUM(debit_amount) 
FROM journal_entry_lines jel
JOIN journal_entries je ON jel.journal_entry_id = je.id
WHERE jel.account_id IN (SELECT id FROM accounts WHERE code LIKE '6%')
AND DATE(je.date) = CURDATE();
```

**Main Accounting Dashboard (`/accounting/dashboard`):**

```
┌─────────────────────────────────────────────────────────────────┐
│ FINANCIAL HEALTH SCORE: 85/100 (Good)                   ↑ +5    │
└─────────────────────────────────────────────────────────────────┘

┌──────────────── TODAY'S SNAPSHOT ────────────────┐
│ Cash Position:     ₦19.2M    │ Revenue Today: ₦850K │
│ Total Receivables: ₦5.5M     │ Expenses:     ₦230K │
│ Total Payables:    ₦6.3M     │ Net Cash Flow: ₦620K │
└──────────────────────────────────────────────────┘

┌──────────────── KEY RATIOS ──────────────────────┐
│ 🟢 Current Ratio:     2.3  (Target: 2.0)         │
│ 🟢 Operating Margin:  18%  (Target: 15%)         │
│ 🟡 AR Days:           52   (Target: 45) ⚠️        │
│ 🔴 Debt-to-Equity:    1.8  (Target: 1.5) 🚨       │
└──────────────────────────────────────────────────┘

┌──────────────── ALERTS & ACTIONS ────────────────┐
│ 🚨 3 Equipment Maintenances Overdue              │
│ ⚠️ Cash shortage in Week 3 - Review forecast     │
│ ⚠️ 2 Leases expiring in 90 days                  │
│ ✓ All banks reconciled this month                │
└──────────────────────────────────────────────────┘

┌──────────────── QUICK ACTIONS ───────────────────┐
│ [New Journal Entry] [Bank Reconciliation]        │
│ [View Cash Forecast] [Department Performance]    │
│ [Petty Cash] [Approve Entries] [Reports]         │
└──────────────────────────────────────────────────┘

┌──────────────── BANK ACCOUNTS ───────────────────┐
│ Zenith Current:     ₦5.2M  [Reconcile] [View]    │
│ GTBank Current:     ₦2.9M  [Reconcile] [View]    │
│ Access Savings:     ₦10.0M [View]                │
│ Petty Cash (3 funds): ₦120K [Manage]             │
└──────────────────────────────────────────────────┘

┌──────────────── THIS MONTH ──────────────────────┐
│ Revenue:           ₦20.3M  (Budget: ₦18M) ✓      │
│ Expenses:          ₦17.8M  (Budget: ₦16M) ⚠️     │
│ Net Profit:        ₦2.5M   (Margin: 12.3%)       │
│ Outstanding AR:    ₦5.5M   (Collection: 72%)     │
└──────────────────────────────────────────────────┘

┌──────────────── RECENT ACTIVITY ─────────────────┐
│ • 15 journal entries posted today                │
│ • 5 pending approvals                            │
│ • Last reconciliation: Jan 28 (Zenith)           │
│ • Next payroll: Feb 5 (₦3.2M due)                │
└──────────────────────────────────────────────────┘
```

**Department Dashboards:**

Each department gets custom financial view:
- Pharmacy: Revenue, COGS, Margin, Inventory turns
- Laboratory: Revenue per test, Supply costs, Profitability
- Consultation: Revenue per visit, Doctor productivity
- Administration: Cost center performance, Budget variance

---

## 9. COMPLIANCE CHECKLIST (UPDATED)

### 9.1 International Standards (IFRS/IAS)

**Financial Reporting:**
- [x] IAS 1: Presentation of Financial Statements (Trial Balance, P&L, Balance Sheet) ✓
- [x] IAS 7: Cash Flow Statements ✓
- [ ] IAS 8: Accounting Policies (Document in system)
- [ ] IAS 10: Events After Reporting Period
- [ ] IAS 16: Property, Plant & Equipment (Phase 5 - Fixed Assets Module)
- [ ] IAS 18/IFRS 15: Revenue Recognition (Phase 10 - Revenue Enhancement)
- [ ] IAS 32: Financial Instruments (Phase 2 - Bank Reconciliation)
- [ ] IAS 36: Impairment of Assets
- [ ] IAS 37: Provisions, Contingent Liabilities
- [ ] IFRS 16: Leases (Phase 6 - Lease Management)

**Disclosure Requirements:**
- [ ] Notes to Financial Statements (automated generation)
- [ ] Related Party Transactions tracking
- [ ] Segment Reporting (by department) - Phase 7
- [ ] Significant Accounting Policies documentation

### 9.2 Nigerian Regulatory Requirements

**Statutory Compliance:**
- [x] Chart of Accounts aligned with NSA (Nigerian Standards on Auditing) ✓
- [x] Audit trail (all journal entries logged with user, timestamp) ✓
- [ ] Monthly tax computation (WHT, VAT)
- [ ] Statutory deduction tracking (PAYE, Pension, NHF)
- [ ] Annual financial statements filing
- [ ] Corporate Affairs Commission (CAC) returns
- [ ] FIRS tax returns integration

**Healthcare Specific (Nigeria):**
- [x] Revenue by payer type (HMO, Cash, Corporate, Government) ✓
- [x] Department cost allocation ✓
- [ ] NHIS claims reconciliation
- [ ] State HMO reporting requirements
- [ ] Medical equipment certification tracking (Phase 5)

### 9.3 Healthcare International Best Practices

**Operational Standards:**
- [x] Revenue cycle management (patient payments tracked) ✓
- [ ] Bed occupancy revenue correlation (Phase 7)
- [ ] Capitation tracking for HMO contracts ✓
- [ ] Insurance claims reconciliation
- [ ] Patient deposit management (Phase 3)
- [ ] Cost per patient analysis (Phase 7)

**Asset Management:**
- [ ] Medical equipment maintenance schedules (Phase 5)
- [ ] Equipment downtime tracking
- [ ] Calibration certificates management
- [ ] Asset warranty tracking (Phase 5)
- [ ] Equipment utilization analysis

**Financial Controls:**
- [x] Segregation of duties (journal entry vs approval) ✓
- [x] Authorization limits by user role ✓
- [ ] Petty cash controls (Phase 1)
- [ ] Bank reconciliation (monthly mandatory) - Phase 2
- [ ] Fixed asset physical verification (annual) - Phase 5
- [ ] Inventory count reconciliation (monthly)

### 9.4 Internal Control Framework

**Preventive Controls:**
- [x] User authentication & role-based access ✓
- [x] Transaction approval workflows ✓
- [ ] Transaction amount limits by user level
- [ ] Dual authorization for large transactions (>₦500K)
- [ ] Automated validation rules on data entry
- [ ] Petty cash custodian assignments (Phase 1)

**Detective Controls:**
- [x] Audit trail for all transactions ✓
- [x] Journal entry reversal tracking ✓
- [ ] Duplicate payment detection
- [ ] Unusual transaction reports (after-hours, large amounts)
- [ ] Budget variance alerts (Phase 9)
- [ ] KPI threshold alerts (Phase 4)

**Corrective Controls:**
- [x] Journal entry reversal capability ✓
- [x] Error correction workflow ✓
- [ ] Reconciliation discrepancy resolution (Phase 2)
- [ ] Adjustment entry approval process
- [ ] Year-end closing procedures

### 9.5 Data Security & Privacy

**Access Control:**
- [x] Role-based permissions ✓
- [ ] Multi-factor authentication for sensitive transactions
- [ ] IP restriction for remote access
- [ ] Session timeout after inactivity

**Data Protection:**
- [ ] Automated daily backups
- [ ] Backup restoration testing (quarterly)
- [ ] Encryption for sensitive financial data
- [ ] Patient financial data privacy (NDPR compliance)

**Audit & Monitoring:**
- [x] User activity logging ✓
- [ ] Failed login attempt tracking
- [ ] Permission change logging
- [ ] Data export/download logging

### 9.6 Compliance Reporting Requirements

**Monthly Reports:**
- [ ] Bank Reconciliation Statements (all banks) - Phase 2
- [ ] Cash Position Report - Phase 3
- [ ] Department Revenue & Expense Reports - Phase 4
- [ ] Budget vs Actual Analysis - Phase 9
- [ ] Accounts Receivable Aging
- [ ] Accounts Payable Aging

**Quarterly Reports:**
- [ ] Financial Statements Package (P&L, Balance Sheet, Cash Flow)
- [ ] Fixed Assets Register - Phase 5
- [ ] Depreciation Schedule - Phase 5
- [ ] KPI Dashboard - Phase 4
- [ ] Cost Center Performance - Phase 7

**Annual Reports:**
- [ ] Audited Financial Statements
- [ ] Tax Returns (Companies Income Tax)
- [ ] Fixed Assets Physical Verification - Phase 5
- [ ] Equipment Maintenance Log - Phase 5
- [ ] Lease Portfolio Review - Phase 6
- [ ] Debt Service Coverage Analysis - Phase 6

### 9.7 Module-Specific Compliance

**Petty Cash (Phase 1):**
- [ ] Daily cash count reconciliation
- [ ] Weekly custodian reporting
- [ ] Receipt verification (all transactions > ₦500)
- [ ] Monthly surprise audits

**Bank Management (Phase 1-2):**
- [ ] Monthly reconciliation (mandatory within 5 days)
- [ ] Outstanding items follow-up (items > 60 days)
- [ ] Bank charge verification
- [ ] Interest income recognition

**Fixed Assets (Phase 5):**
- [ ] Asset tagging and physical identification
- [ ] Annual physical verification (100% coverage)
- [ ] Depreciation policy documentation
- [ ] Disposal approval workflow
- [ ] Asset insurance tracking

**Equipment Maintenance (Phase 5):**
- [ ] Preventive maintenance schedules (regulatory)
- [ ] Calibration certificates (medical equipment)
- [ ] Service provider qualification tracking
- [ ] Warranty expiry monitoring

**Leases (Phase 6):**
- [ ] IFRS 16 compliance (lease vs service contract)
- [ ] Right-of-use asset valuation
- [ ] Lease liability calculation
- [ ] Lease payment schedule accuracy
- [ ] Lease modification tracking

**Cost Centers (Phase 7):**
- [ ] Allocation methodology documentation
- [ ] Monthly allocation review
- [ ] Inter-department transfer approvals
- [ ] Cost center manager accountability

**CAPEX (Phase 8):**
- [ ] Project approval workflow (Board level > ₦10M)
- [ ] Financial analysis (NPV, IRR, Payback)
- [ ] Multi-year budget alignment
- [ ] Project variance tracking
- [ ] Post-implementation review

**Budget (Phase 9):**
- [ ] Annual budget approval (Board)
- [ ] Quarterly budget review
- [ ] Variance explanation (>10%)
- [ ] Budget revision approval process

---

## 10. SUCCESS METRICS (UPDATED)

### 10.1 Day-to-Day Operations Metrics

**Bank Management (Phase 1):**
- ✓ All banks reconciled monthly within 3 days of statement receipt
- ✓ 100% of bank accounts tracked in system
- ✓ Real-time bank balance visibility (< 24 hours lag)
- ✓ Bank variance < 0.1% of total balance

**Petty Cash (Phase 1):**
- ✓ 100% of petty cash transactions have receipts
- ✓ Daily cash count completion rate > 95%
- ✓ Reimbursement turnaround < 48 hours
- ✓ Petty cash variance < ₦500 per fund per month

**Inter-Account Transfers (Phase 1):**
- ✓ Transfer clearance time < 24 hours (same bank)
- ✓ 100% of transfers have approval before execution
- ✓ Zero failed transfers due to insufficient funds

**Bank Reconciliation (Phase 2):**
- ✓ 80%+ automation of transaction matching
- ✓ 100% of banks reconciled within 5 days of month-end
- ✓ Zero unreconciled items older than 60 days
- ✓ Average reconciliation time < 2 hours per bank

**Cash Flow Management (Phase 3):**
- ✓ Forecast accuracy: Actual vs Projected variance < 15%
- ✓ Zero cash shortages (maintained minimum ₦1M balance)
- ✓ 13-week rolling forecast updated weekly
- ✓ 100% of known large transactions in forecast

**Patient Deposits (Phase 3):**
- ✓ 100% of deposits applied within discharge timeframe
- ✓ Refund processing < 7 days
- ✓ Unutilized deposits > 90 days < 5% of total
- ✓ Zero deposit loss/misappropriation

### 10.2 Asset Management Metrics (Phase 5)

**Fixed Assets:**
- ✓ 100% of assets > ₦50K tagged and registered
- ✓ Annual physical verification: 100% coverage
- ✓ Asset register accuracy > 99%
- ✓ Depreciation posting: 100% automated, on-time

**Equipment Maintenance:**
- ✓ Preventive maintenance on-time rate > 90%
- ✓ Equipment downtime due to missed maintenance < 2%
- ✓ Overdue maintenance items: 0 critical equipment
- ✓ Maintenance cost tracking: 100% of expenses captured

**Warranties:**
- ✓ Warranty utilization rate > 75%
- ✓ Zero warranty expirations without notification (30-day alert)
- ✓ 100% of new assets have warranty details recorded

### 10.3 Liability Management Metrics (Phase 6)

**Long-term Liabilities:**
- ✓ 100% of loan payments made on time (no penalties)
- ✓ Interest calculation accuracy: 100%
- ✓ Payment reminder delivery: 7 days before due
- ✓ Amortization schedule accuracy: variance < 0.5%

**Lease Management (IFRS 16):**
- ✓ 100% of leases > 12 months in register
- ✓ Right-of-use asset calculation: 100% accurate
- ✓ Lease liability reconciliation: monthly variance < 1%
- ✓ IFRS 16 disclosure notes: auto-generated within 5 days

### 10.4 Financial Reporting Metrics

**Report Generation:**
- ✓ Financial statements generated within 5 working days of month-end
- ✓ Trial balance balances: zero variance (always balanced)
- ✓ Report generation time: < 30 seconds for standard reports
- ✓ Report accuracy: 100% (zero material misstatements)

**Management Reports (Phase 4):**
- ✓ Daily reports available by 9 AM next day
- ✓ Weekly reports available Monday 9 AM
- ✓ Monthly reports available by 5th working day
- ✓ Report accessibility: 100% uptime

**KPI Dashboard (Phase 4):**
- ✓ Real-time KPI updates (< 1 hour lag)
- ✓ 20 key financial KPIs tracked continuously
- ✓ Alert response time: < 24 hours for critical thresholds
- ✓ KPI accuracy: variance from manual calculation < 2%

### 10.5 Planning & Analysis Metrics

**Cost Center Accounting (Phase 7):**
- ✓ 100% of transactions allocated to cost centers
- ✓ Monthly allocation completion: within 3 days of month-end
- ✓ Cost center profitability visibility: all centers tracked
- ✓ Allocation accuracy: variance < 5%

**CAPEX Planning (Phase 8):**
- ✓ Project ROI accuracy: forecast vs actual variance < 20%
- ✓ 100% of projects > ₦5M have financial analysis
- ✓ Project approval turnaround: < 10 days (Board level)
- ✓ Budget variance: < 10% of approved budget

**Budget Management (Phase 9):**
- ✓ Budget vs actual reports: available real-time
- ✓ Variance explanations: 100% for variances > 10%
- ✓ Budget revision approval: < 5 days
- ✓ Annual budget completion: 30 days before fiscal year

### 10.6 Operational Efficiency Metrics

**Automation:**
- ✓ 90%+ of journal entries auto-generated (via observers)
- ✓ Manual journal entry rate: < 10 per day
- ✓ Data entry time reduction: 80% vs manual system
- ✓ Report generation: 100% automated (zero manual compilation)

**Month-End Close:**
- ✓ Close completion time: < 5 days (international best practice)
- ✓ Adjustment entries: < 10 per month
- ✓ Closing checklist completion: 100%
- ✓ Audit-ready status: 100% by day 5

**User Productivity:**
- ✓ Average time per journal entry: < 3 minutes
- ✓ Average time per bank reconciliation: < 2 hours
- ✓ Average time per report generation: < 30 seconds
- ✓ User error rate: < 2% of transactions

### 10.7 Audit & Compliance Metrics

**Audit Readiness:**
- ✓ Audit preparation time: < 2 weeks
- ✓ Audit queries resolution: < 48 hours per query
- ✓ Clean audit opinion: achieved annually
- ✓ Management letter issues: < 5 per year

**Internal Controls:**
- ✓ Segregation of duties compliance: 100%
- ✓ Authorization limit violations: 0 per month
- ✓ Unapproved transaction rate: 0%
- ✓ Control override rate: < 1% with documentation

**Compliance:**
- ✓ Statutory filing: 100% on-time
- ✓ Tax returns submission: 100% on-time
- ✓ Regulatory reporting: 100% compliance
- ✓ Policy violations: 0 material violations

### 10.8 Data Quality Metrics

**Accuracy:**
- ✓ Journal entry error rate: < 1%
- ✓ Trial balance balancing: 100% (always balanced)
- ✓ Bank reconciliation variance: < 0.1%
- ✓ Data validation failure rate: < 2%

**Completeness:**
- ✓ Transaction capture rate: 100%
- ✓ Missing approvals: 0%
- ✓ Incomplete records: < 1%
- ✓ Missing supporting documents: < 5%

**Timeliness:**
- ✓ Same-day transaction posting: > 95%
- ✓ Approval turnaround: < 24 hours
- ✓ Report delivery: 100% on-time
- ✓ Period close on schedule: 100%

### 10.9 Financial Health Indicators

**Liquidity (Target Ranges):**
- Current Ratio: > 2.0 (Maintain ✓)
- Quick Ratio: > 1.0 (Maintain ✓)
- Days Cash on Hand: > 60 days (Target ✓)

**Profitability (Target Ranges):**
- Operating Margin: > 15% (Target ✓)
- Net Profit Margin: > 10% (Target ✓)
- Return on Assets: > 8% (Monitor)

**Efficiency (Target Ranges):**
- AR Days: < 45 days (Improve - currently 52)
- AP Days: 30-45 days (Maintain ✓)
- Inventory Turnover: > 8x/year (Monitor)

**Solvency (Target Ranges):**
- Debt-to-Equity: < 1.5 (Improve - currently 1.8)
- Interest Coverage: > 3.0 (Monitor)
- Debt Service Coverage: > 1.25 (Monitor)

### 10.10 User Satisfaction Metrics

**System Usability:**
- User satisfaction score: > 80%
- Feature utilization rate: > 70%
- Support ticket volume: < 5 per user per month
- Average ticket resolution time: < 24 hours

**Training & Adoption:**
- User training completion: 100% within 30 days
- System adoption rate: > 90% (active users)
- Manual workaround rate: < 10%
- Feature request implementation: > 50% within 90 days

---

## 11. RISKS & MITIGATIONS (UPDATED)

### 11.1 Technical Implementation Risks

**Risk 1: Database Performance with Large Transaction Volume**
- **Impact:** HIGH - Reports slow down, user experience degraded
- **Probability:** MEDIUM
- **Mitigation:**
  - Index optimization on journal_entry_lines (account_id, date)
  - Partition tables by fiscal year after 1M+ records
  - Implement report caching for frequently accessed data
  - Query optimization and materialized views for KPIs

**Risk 2: Bank Statement Format Variations**
- **Impact:** MEDIUM - Reconciliation automation reduced
- **Probability:** HIGH
- **Mitigation:**
  - Build flexible parser supporting major Nigerian banks (Zenith, GTBank, Access, UBA, First Bank)
  - Template-based import with field mapping
  - Manual override capability for unsupported formats
  - Continuous parser improvement based on user feedback

**Risk 3: Complex Cost Allocation Logic**
- **Impact:** MEDIUM - Inaccurate cost center performance
- **Probability:** MEDIUM
- **Mitigation:**
  - Document allocation methodology clearly
  - Implement allocation validation rules
  - Quarterly allocation review by finance team
  - Rollback capability for allocation adjustments

**Risk 4: IFRS 16 Lease Calculation Complexity**
- **Impact:** MEDIUM - Incorrect financial statements
- **Probability:** MEDIUM
- **Mitigation:**
  - Use proven discount rate calculation formulas
  - Third-party validation tool integration (optional)
  - External auditor consultation during Phase 6
  - Comprehensive testing with sample leases

**Risk 5: Data Migration from Legacy Systems**
- **Impact:** HIGH - Historical data accuracy
- **Probability:** LOW (system is new)
- **Mitigation:**
  - Full backup before any migration
  - Staged migration (accounts → balances → transactions)
  - Validation reconciliation after each stage
  - Parallel run capability for 1 month

### 11.2 Operational Risks

**Risk 6: Retroactive Reconciliation Workload**
- **Impact:** MEDIUM - Resource intensive
- **Probability:** HIGH (if historical data exists)
- **Mitigation:**
  - Start reconciliation from current month forward (priority)
  - Gradually backfill historical months (1-2 per week)
  - Outsource reconciliation for > 12 months old
  - Accept older discrepancies if immaterial (<1%)

**Risk 7: User Resistance to Complex Features**
- **Impact:** HIGH - Low adoption, system underutilization
- **Probability:** MEDIUM
- **Mitigation:**
  - Phased rollout with pilot users (2-3 key staff)
  - Comprehensive training program (video + live sessions)
  - In-app help documentation and tooltips
  - Dedicated support channel (WhatsApp group)
  - "Quick Start" guides for each module

**Risk 8: Month-End Close Delays**
- **Impact:** HIGH - Late reporting, management decisions delayed
- **Probability:** MEDIUM
- **Mitigation:**
  - Enforce transaction cutoff dates (no backdating)
  - Automated month-end checklist with progress tracking
  - Penalty alerts for late submissions
  - Close process rehearsal (dry run last week of month)

**Risk 9: Incorrect Depreciation Calculations**
- **Impact:** MEDIUM - Asset values overstated/understated
- **Probability:** LOW
- **Mitigation:**
  - Validate formulas against accounting standards
  - Monthly depreciation review report
  - Annual physical asset verification
  - External auditor review of depreciation policies

**Risk 10: Cash Shortage Not Detected in Time**
- **Impact:** HIGH - Operational disruption, vendor payments delayed
- **Probability:** LOW (with forecasting)
- **Mitigation:**
  - Automated weekly cash forecast
  - Minimum cash balance alerts (₦1M threshold)
  - Emergency credit line arrangement with bank
  - Accelerated collection procedures for aged AR

### 11.3 Compliance & Audit Risks

**Risk 11: IFRS/IAS Non-Compliance**
- **Impact:** HIGH - Audit qualification, regulatory penalties
- **Probability:** LOW (with proper implementation)
- **Mitigation:**
  - External consultant review during implementation
  - Built-in compliance checklists
  - Annual external audit engagement
  - Continuous professional development for finance team

**Risk 12: Inadequate Audit Trail**
- **Impact:** HIGH - Cannot trace transactions, audit failure
- **Probability:** LOW (system has logging)
- **Mitigation:**
  - Comprehensive logging at database level
  - User activity tracking (who, what, when)
  - Immutable journal entry history (soft deletes only)
  - Regular audit trail testing

**Risk 13: Unauthorized Access to Financial Data**
- **Impact:** HIGH - Data breach, fraud
- **Probability:** LOW
- **Mitigation:**
  - Role-based access control (RBAC)
  - Multi-factor authentication for sensitive operations
  - IP whitelisting for remote access
  - Regular access review (quarterly)
  - Automatic lockout after failed login attempts

**Risk 14: Period Close Without Reconciliation**
- **Impact:** MEDIUM - Inaccurate financial statements
- **Probability:** LOW (with controls)
- **Mitigation:**
  - System validation: Block period close if reconciliation incomplete
  - Mandatory checklist completion before close
  - Dual authorization for period close action
  - Close reversal capability (with approvals)

### 11.4 Resource & Timeline Risks

**Risk 15: Insufficient Finance Team Capacity**
- **Impact:** HIGH - Implementation delays, user burnout
- **Probability:** MEDIUM
- **Mitigation:**
  - Phased implementation (16 weeks spread over 6 months)
  - Prioritize critical modules (bank, cash, reconciliation first)
  - Temporary staff augmentation for implementation
  - Offshore development support for technical tasks

**Risk 16: Scope Creep**
- **Impact:** MEDIUM - Timeline delays, budget overrun
- **Probability:** HIGH
- **Mitigation:**
  - Strict phase boundaries with sign-offs
  - Change request process (must be approved by steering committee)
  - Document "nice-to-have" features for Phase 2 (post-launch)
  - Weekly progress reviews against plan

**Risk 17: Parallel System Dependency**
- **Impact:** LOW - Continued reliance on old methods
- **Probability:** MEDIUM
- **Mitigation:**
  - Hard cutover dates per module
  - Disable old system features after new module launch
  - Delete manual Excel templates (force system use)
  - Monthly system utilization reporting

**Risk 18: Key Person Dependency**
- **Impact:** HIGH - Knowledge loss if key person leaves
- **Probability:** MEDIUM
- **Mitigation:**
  - Document all processes and configurations
  - Cross-train 2-3 users per module
  - Video record training sessions
  - External system documentation (user manuals)

### 11.5 Financial Risks

**Risk 19: Budget Overrun for Implementation**
- **Impact:** MEDIUM - Project halted or reduced scope
- **Probability:** LOW
- **Mitigation:**
  - Detailed cost breakdown per phase
  - 20% contingency budget
  - Monthly budget tracking against actuals
  - Prioritize phases by ROI (critical first)

**Risk 20: Benefit Realization Delay**
- **Impact:** MEDIUM - ROI not achieved in expected timeframe
- **Probability:** MEDIUM
- **Mitigation:**
  - Track efficiency metrics from Day 1
  - Quick wins highlighted (e.g., bank reconciliation time saved)
  - Monthly benefits realization report to management
  - Adjust implementation if metrics not improving

### 11.6 Data Quality Risks

**Risk 21: Duplicate Transaction Entry**
- **Impact:** MEDIUM - Overstated revenues/expenses
- **Probability:** LOW (with validation)
- **Mitigation:**
  - Automatic duplicate detection (same amount, date, description)
  - Warning prompts for similar transactions within 24 hours
  - Monthly duplicate transaction report
  - User training on proper transaction entry

**Risk 22: Incorrect Account Coding**
- **Impact:** MEDIUM - Misstated financial statements
- **Probability:** MEDIUM
- **Mitigation:**
  - Clear chart of accounts documentation with examples
  - Account selection dropdowns with search/filter
  - Default account mappings per transaction type
  - Monthly account usage review (detect unusual patterns)

**Risk 23: Incomplete Fixed Asset Register**
- **Impact:** MEDIUM - Assets untracked, depreciation incorrect
- **Probability:** HIGH (during Phase 5 rollout)
- **Mitigation:**
  - Physical asset verification and tagging project
  - Department heads accountable for asset listing
  - Cutoff date for asset registration (mandatory)
  - Annual asset verification process

**Risk 24: Bank Statement Upload Errors**
- **Impact:** LOW - Reconciliation delays
- **Probability:** MEDIUM
- **Mitigation:**
  - File format validation before processing
  - Error reporting with clear messages
  - Sample templates provided for each bank
  - Manual entry option as fallback

### 11.7 Integration Risks

**Risk 25: Observer Failures Creating Incomplete Journal Entries**
- **Impact:** HIGH - Missing transactions in GL
- **Probability:** LOW (observers are tested)
- **Mitigation:**
  - Comprehensive observer testing in staging
  - Transaction logging (before and after observer)
  - Daily reconciliation: source transactions vs journal entries
  - Failed observer alert system (email + dashboard)

**Risk 26: KPI Calculation Errors**
- **Impact:** MEDIUM - Misleading management information
- **Probability:** LOW
- **Mitigation:**
  - Manual validation of KPI formulas (first 3 months)
  - Quarterly KPI audit by external consultant
  - Display calculation methodology in dashboard
  - User-reported KPI discrepancy channel

### 11.8 Change Management Risks

**Risk 27: Management Not Using New Reports**
- **Impact:** HIGH - System value not realized
- **Probability:** MEDIUM
- **Mitigation:**
  - Executive dashboard tailored to management needs
  - Weekly management report email (automated)
  - Mobile-friendly dashboard for on-the-go access
  - Monthly management review sessions

**Risk 28: Customization Requests Overwhelming Team**
- **Impact:** MEDIUM - Core implementation delayed
- **Probability:** HIGH
- **Mitigation:**
  - Standard reports must meet 80% of needs
  - Custom report request process (justification required)
  - Quarterly customization review cycle
  - Self-service report builder (Phase 2)

### Risk Matrix Summary:

**CRITICAL (Immediate Action):**
- Risk 1: Database performance
- Risk 7: User resistance
- Risk 10: Cash shortage detection
- Risk 25: Observer failures

**HIGH (Monitor Closely):**
- Risk 3, 6, 8, 11, 13, 15, 18

**MEDIUM (Standard Management):**
- Risk 2, 4, 5, 9, 14, 16, 17, 19, 20, 21, 22, 23, 26, 27, 28

**LOW (Accept & Monitor):**
- Risk 12, 24

---

## 12. CONCLUSION & NEXT STEPS

### 12.1 Summary of Enhancements

This comprehensive plan adds **14 new modules** to the existing journal entry-centric accounting system:

**Day-to-Day Operations (6 modules - Weeks 1-6):**
1. Bank Management Enhancement
2. Bank Reconciliation (IAS 7)
3. Petty Cash Management
4. Inter-Account Transfers
5. Cash Flow Forecasting
6. Patient Deposits/Advance Payments

**Asset & Liability Management (4 modules - Weeks 7-10):**
7. Fixed Assets Register (IAS 16)
8. Equipment Maintenance Tracking
9. Long-term Liabilities Management
10. Lease Management (IFRS 16)

**Planning & Analysis (4 modules - Weeks 11-16):**
11. Cost Center Accounting
12. Capital Budgeting (CAPEX)
13. Budget Management
14. KPI Dashboard (20 financial ratios)

**Total Implementation:** 16 weeks (4 months)
**Total New Database Tables:** 29 tables
**Total New Reports:** 40+ reports added to central reporting module
**Compliance Achievement:** IFRS/IAS alignment, Nigerian regulatory compliance

### 12.2 Key Principles Maintained

✓ **Journal Entry Remains Central:** All transactions flow through journal entries
✓ **Observer Pattern:** Automatic journal entry creation from domain events
✓ **Single Source of Truth:** All reports pull from journal entries
✓ **Clean Module Architecture:** Each module follows existing patterns
✓ **Central Reporting:** All reports integrate into ReportService.php
✓ **Dashboard Integration:** All modules have dashboard widgets

### 12.3 Expected Benefits

**Operational Efficiency:**
- 90% reduction in manual journal entries (observer automation)
- 80% automation of bank reconciliation (matching algorithm)
- Month-end close time: < 5 days (down from 10-15 days)
- Real-time financial visibility (no waiting for reports)

**Compliance & Audit:**
- IFRS/IAS compliance (IAS 7, IAS 16, IFRS 15, IFRS 16)
- Nigerian regulatory compliance (NSA, CAC, FIRS)
- Audit preparation time: < 2 weeks (down from 4-6 weeks)
- Clean audit opinion achievable

**Management Decision Support:**
- Real-time KPI dashboard (20 financial ratios)
- Daily cash position visibility
- Department profitability analysis
- 13-week rolling cash forecast
- Budget vs actual real-time tracking

**Risk Reduction:**
- Zero cash shortages (forecasting + alerts)
- 100% bank reconciliation (no unreconciled items > 60 days)
- Asset protection (maintenance tracking + warranties)
- Liability management (no missed payments, penalties avoided)

### 12.4 Resource Requirements

**Finance Team:**
- Finance Manager: 30% time (oversight, approvals, reviews)
- Senior Accountant: 70% time (implementation, testing, training)
- Accountant: 50% time (data entry, validation)
- IT Support: 20% time (technical assistance)

**External Resources:**
- IFRS Consultant: 10 days (Phase 5-6 - Fixed Assets, Leases)
- System Developer: 100% time (16 weeks - coding, testing)
- Trainer: 5 days (user training across all phases)

**Budget Estimate:**
- Development: ₦8M - ₦12M (₦500K - ₦750K per module)
- Consulting: ₦1M - ₦2M
- Training: ₦500K
- Infrastructure: ₦500K (servers, backups)
- **Total: ₦10M - ₦15M**

### 12.5 Critical Success Factors

1. **Executive Sponsorship:** CFO/Finance Director active involvement
2. **User Buy-In:** Early engagement of department heads and key users
3. **Phased Approach:** Complete critical phases (1-3) before moving to optional phases
4. **Training Investment:** Comprehensive training, not just "system demo"
5. **Change Management:** Clear communication, address resistance proactively
6. **Data Quality:** Invest time in cleanup before going live
7. **Testing Rigor:** Full UAT before each phase rollout
8. **Documentation:** Maintain process documentation, not just technical docs

### 12.6 Immediate Next Steps (Week 1)

**Step 1: Approval & Planning (Days 1-2)**
- [ ] Review this plan with Finance Director/CFO
- [ ] Approve budget and timeline
- [ ] Form steering committee (Finance Manager, IT Head, Department Heads)
- [ ] Assign implementation lead

**Step 2: Phase 1 Kickoff (Days 3-5)**
- [ ] Create development branch: `feature/day-to-day-operations`
- [ ] Database design review for Phase 1 tables
- [ ] UI/UX mockups for Bank Dashboard
- [ ] Define acceptance criteria for Phase 1

**Step 3: Bank Management Implementation (Week 1-2)**
- [ ] Migration: Enhance `banks` table (8 new columns)
- [ ] Migration: Create `inter_account_transfers` table
- [ ] Controller: BankController.php (index, show, update)
- [ ] View: Bank Dashboard (`/accounting/banks`)
- [ ] Service: BankService.php (balance calculations)
- [ ] Update existing bank statement report to show physical bank info
- [ ] Testing: Bank management CRUD operations

**Step 4: Petty Cash Implementation (Week 1-2)**
- [ ] Migration: Create 3 petty cash tables
- [ ] Controller: PettyCashController.php
- [ ] Views: Petty cash register, reimbursement form, reconciliation
- [ ] Service: PettyCashService.php
- [ ] Observer: PettyCashObserver.php (auto journal entries)
- [ ] Testing: Full petty cash workflow

**Step 5: Phase 1 Completion (End of Week 2)**
- [ ] User Acceptance Testing (UAT) with 2-3 pilot users
- [ ] Training: Bank management & petty cash (2-hour session)
- [ ] Merge to master: `feature/day-to-day-operations`
- [ ] Go-live: Banks and petty cash modules
- [ ] Monitor for issues (daily check-ins)

### 12.7 Long-term Vision (12 Months)

**Month 4 (End of Phase 10):**
- All 14 modules live and operational
- Finance team fully trained
- Management using KPI dashboard daily
- Month-end close consistently < 5 days

**Month 6:**
- First external audit using new system
- All compliance requirements met
- User satisfaction > 80%
- Efficiency gains realized

**Month 12:**
- System fully embedded in operations
- Advanced features being utilized (forecasting, cost center analysis)
- Consider additional enhancements:
  - Advanced analytics (predictive modeling)
  - Mobile app for approvals
  - API integrations (banks, tax software)
  - Self-service reporting for department heads

### 12.8 Governance Structure

**Steering Committee (Monthly Meetings):**
- Finance Director/CFO (Chair)
- Finance Manager
- IT Head
- Implementation Lead
- External Consultant (Phases 5-6)

**Responsibilities:**
- Review progress against timeline
- Approve phase completions and go-live dates
- Resolve escalated issues
- Approve budget and resource changes
- Make go/no-go decisions for each phase

**Implementation Team (Weekly Standups):**
- Implementation Lead
- Senior Accountant
- System Developer
- IT Support
- Trainer

**Responsibilities:**
- Daily implementation tasks
- Issue tracking and resolution
- Testing coordination
- User training delivery
- Documentation maintenance

### 12.9 Communication Plan

**Weekly Updates:**
- Email to all finance staff (progress, upcoming changes)
- Dashboard: Implementation progress widget

**Monthly Reports:**
- Steering committee presentation (status, risks, decisions needed)
- Management summary (benefits realized, upcoming phases)

**Phase Completions:**
- All-staff announcement
- Training session invitations
- User guide distribution
- Go-live checklist review

### 12.10 Final Recommendations

**CRITICAL - Must Do:**
1. **Prioritize Phases 1-3** (Bank, Cash, Reconciliation) - These are foundational
2. **Invest in Training** - Budget 2-3 hours per module per user
3. **External Audit Consultation** - Engage auditor during Phase 5-6 (IFRS compliance)
4. **Parallel Run** - Run old and new systems parallel for 1 month (critical modules)

**HIGHLY RECOMMENDED:**
1. Start with pilot users (2-3 experienced staff) before full rollout
2. Document all processes (not just system, but accounting policies)
3. Monthly steering committee reviews (don't skip)
4. Celebrate quick wins (e.g., "Bank reconciliation now takes 2 hours vs 2 days!")

**OPTIONAL (Consider for Phase 2):**
1. Mobile app for approvals (executives can approve on phone)
2. Advanced analytics (AI-powered forecasting)
3. Integration with Nigerian bank APIs (auto-fetch statements)
4. Self-service report builder for power users

---

## APPROVAL SIGNATURES

**Prepared By:**
_________________________  
Finance Manager  
Date: January 31, 2026

**Reviewed By:**
_________________________  
External Consultant (IFRS/IAS)  
Date: _________________

**Approved By:**
_________________________  
Chief Financial Officer / Finance Director  
Date: _________________

---

## APPENDIX A: GLOSSARY OF TERMS

**CAPEX:** Capital Expenditure - Long-term investments in assets  
**IFRS:** International Financial Reporting Standards  
**IAS:** International Accounting Standards  
**NPV:** Net Present Value - Financial analysis metric  
**IRR:** Internal Rate of Return - Investment return metric  
**KPI:** Key Performance Indicator - Measurable performance metric  
**NSA:** Nigerian Standards on Auditing  
**FIRS:** Federal Inland Revenue Service (Nigeria)  
**CAC:** Corporate Affairs Commission (Nigeria)  
**NHIS:** National Health Insurance Scheme  
**UAT:** User Acceptance Testing  
**RBAC:** Role-Based Access Control  
**P&L:** Profit and Loss Statement  
**AR:** Accounts Receivable  
**AP:** Accounts Payable  
**GL:** General Ledger  
**COGS:** Cost of Goods Sold

---

## APPENDIX B: CONTACT & SUPPORT

**Implementation Support:**
- Email: accounting-support@corehealth.ng
- Phone: +234-XXX-XXX-XXXX
- WhatsApp Group: CoreHealth Accounting Users

**Technical Support:**
- Email: tech-support@corehealth.ng
- Ticketing System: http://support.corehealth.ng

**External Consultant:**
- IFRS/IAS Specialist: [Name], [Email], [Phone]

---

**END OF DOCUMENT**

*This document is a living plan and will be updated as implementation progresses.*

**Last Updated:** January 31, 2026  
**Version:** 3.0 (Enhanced with System Integration & Journal Entry Centricity Validation)  
**Next Review:** February 15, 2026

---

## APPENDIX C: SYSTEM INTEGRATION SUMMARY

### Existing System Components (DO NOT REPLACE - EXTEND ONLY)

**Working Observers:**
1. PaymentObserver.php - Patient payments → journal entries (PROVEN)
2. ExpenseObserver.php - Approved expenses → journal entries (PROVEN)
3. PurchaseOrderObserver.php - PO receipts → journal entries (PROVEN - will extend for fixed assets)
4. PayrollBatchObserver.php - Payroll → journal entries (PROVEN)
5. HmoRemittanceObserver.php - HMO payments → journal entries (PROVEN)

**Working Models:**
1. PurchaseOrder + PurchaseOrderItem - Inventory procurement (EXTEND for fixed assets)
2. Product - Both inventory AND fixed asset items
3. StockBatch - Inventory tracking with PO linkage
4. Payment - Revenue recognition
5. Expense - Expense tracking with supplier sub-accounts
6. Supplier - Used by PO and Expense observers

**Critical Tables:**
1. journal_entries - ALL transactions (SINGLE SOURCE OF TRUTH)
2. journal_entry_lines - ALL debits/credits with metadata
3. accounts - Chart of accounts with account_sub_accounts
4. purchase_orders + purchase_order_items - To be extended
5. banks - Physical bank tracking

### Integration Points by Phase

**Phase 0: Validation (Week 0)**
- Audit all existing reports for journal entry centricity
- Test all 5 existing observers
- Validate balance sheet = journal entry sums
- Document observer template

**Phase 1-2: Bank & Cash (Weeks 1-4)**
- NEW: PettyCashObserver, TransferObserver, BankReconciliationObserver
- EXTEND: banks table (add current_balance calculation from JE)
- INTEGRATE: All cash reports query journal_entries only

**Phase 3: Deposits (Week 5)**
- NEW: PatientDepositObserver, DepositApplicationObserver
- INTEGRATE: Deposits link to journal entries

**Phase 5: Assets (Weeks 7-8)**
- EXTEND: PurchaseOrderObserver (distinguish inventory vs fixed_asset)
- EXTEND: purchase_order_items table (add item_type, fixed_asset_category_id)
- NEW: DepreciationObserver, AssetDisposalObserver, MaintenanceObserver
- NEW: fixed_assets table (with journal_entry_id, source morphs to PO items)
- INTEGRATE: Asset register shows "Source: PO-2026-001"

**Phase 6: Liabilities (Weeks 9-10)**
- NEW: LoanPaymentObserver, LeasePaymentObserver
- INTEGRATE: All liability payments via journal entries

**Phase 7-10: Planning (Weeks 11-16)**
- NEW: AllocationObserver (cost center monthly allocations)
- INTEGRATE: All budgets, KPIs calculated from journal entries

### Key Enhancements to Existing System

**1. PurchaseOrder System Extension:**
```php
// CURRENT:
All POs → Inventory (1300)

// ENHANCED:
PO Line Item Type:
  - 'inventory' → Inventory (1300)  // Drugs, supplies
  - 'fixed_asset' → Fixed Assets (14xx)  // Equipment, furniture, vehicles

PurchaseOrderObserver UPDATED to:
  1. Check each line item type
  2. Route to correct GL account
  3. Auto-create FixedAsset record for fixed_asset items
  4. Link FixedAsset to journal_entry_id
```

**2. Metadata Expansion:**
```php
// journal_entry_lines table already has:
- patient_id, service_id, product_id, hmo_id, supplier_id, category

// Will add for new modules:
- cost_center_id (Phase 7)
- project_id (Phase 8 - CAPEX)
- budget_id (Phase 9)
```

**3. Balance Calculation Standardization:**
```php
// BEFORE: Some tables had balance columns
// AFTER: ALL balances calculated from journal entries

// Example:
$cashBalance = JournalEntryLine::where('account_id', $cashAccount->id)
    ->sum(DB::raw('debit_amount - credit_amount'));

// NO MORE: $account->current_balance (stored column)
// ALWAYS: Calculate from journal entries on demand or cache
```

### Validation Queries (Run Before Each Phase)

**1. Account Balance Reconciliation:**
```sql
-- Must return 0 rows
SELECT a.code, a.name,
       (SELECT SUM(debit_amount - credit_amount) FROM journal_entry_lines WHERE account_id = a.id) as je_balance,
       a.current_balance as stored_balance
FROM accounts a
WHERE ABS((SELECT SUM(debit_amount - credit_amount) FROM journal_entry_lines WHERE account_id = a.id) - COALESCE(a.current_balance, 0)) > 0.01;
```

**2. Observer Coverage:**
```sql
-- All payments should have journal entries
SELECT COUNT(*) as payments_without_je
FROM payments p
LEFT JOIN journal_entries je ON je.source_type = 'App\\Models\\Payment' AND je.source_id = p.id
WHERE je.id IS NULL AND p.created_at > '2026-01-01';

-- Should return 0
```

**3. Metadata Completeness:**
```sql
-- All revenue entries should have patient_id
SELECT COUNT(*) as revenue_without_patient
FROM journal_entry_lines jel
JOIN accounts a ON jel.account_id = a.id
WHERE a.code LIKE '4%'  -- Revenue accounts
AND jel.patient_id IS NULL
AND jel.created_at > '2026-01-01';

-- Should return 0 or very few
```

### Migration Strategy for Existing Data

**If Historical Data Exists:**

1. **Inventory to Fixed Assets Reclassification:**
   ```sql
   -- Find equipment purchases in inventory
   SELECT po.*, poi.* 
   FROM purchase_orders po
   JOIN purchase_order_items poi ON po.id = poi.purchase_order_id
   JOIN products p ON poi.product_id = p.id
   WHERE p.product_name LIKE '%Equipment%' OR p.product_name LIKE '%Furniture%';
   
   -- Create reversing entries:
   DEBIT: Fixed Assets (14xx)
   CREDIT: Inventory (1300)
   
   -- Create FixedAsset records linked to new journal entries
   ```

2. **Existing Cash Balances:**
   ```sql
   -- Validate bank balances match journal entries
   SELECT b.name,
          b.current_balance as bank_table_balance,
          (SELECT SUM(debit_amount - credit_amount) 
           FROM journal_entry_lines jel
           JOIN accounts a ON jel.account_id = a.id
           WHERE a.bank_id = b.id) as journal_entries_balance,
          b.current_balance - (SELECT SUM(debit_amount - credit_amount) 
           FROM journal_entry_lines jel
           JOIN accounts a ON jel.account_id = a.id
           WHERE a.bank_id = b.id) as variance
   FROM banks b;
   
   -- If variance ≠ 0, create adjustment entries
   ```

3. **Phased Data Migration:**
   - Week 0: Current month data (January 2026)
   - Week 1-2: Previous 3 months (backfill)
   - Week 3-4: Previous 6 months (if needed)
   - Older data: Accept as opening balances

---

**FINAL CHECKLIST BEFORE IMPLEMENTATION:**

- [ ] All existing observers tested and working
- [ ] All existing reports query journal_entries only
- [ ] Balance sheet reconciles to journal entries (variance < 0.01%)
- [ ] Team trained on observer pattern
- [ ] Observer template documented
- [ ] Phase 0 validation complete
- [ ] Steering committee approval obtained
- [ ] Budget approved (₦10M-₦15M)
- [ ] Timeline agreed (17 weeks including Phase 0)
- [ ] Backup strategy in place

**PROCEED ONLY IF ALL CHECKBOXES CHECKED.**
