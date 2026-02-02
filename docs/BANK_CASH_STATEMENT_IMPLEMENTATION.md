# Bank & Cash Statement Implementation Plan

## Executive Summary

This document is the **SINGLE SOURCE OF TRUTH** for all accounting journal entries in CoreHealth. All income, expense, AR (Accounts Receivable), and AP (Accounts Payable) reports derive their values from **journal entries** only. Observers are the key mechanism that create these entries.

### Core Principles
1. **Journal Entries = Single Source of Truth** - All financial reports query journal entries, not transaction tables directly
2. **Accrual Accounting** - Revenue/expense recognized when earned/incurred, not when cash moves
3. **Two-Stage Entries** - Most transactions need TWO journal entries: one at recognition, one at settlement
4. **Observers = Entry Points** - Every financial event flows through observers that create journal entries
5. **Granular Tracking** - Journal entries must capture product/service/category details for drill-down reporting

---

## Table of Contents

1. [Part 1A: Granular Tracking & Drill-Down Strategy](#part-1a-granular-tracking--drill-down-strategy--new) ⭐ NEW
2. [Part 1B: Cash Flow Classification Strategy](#part-1b-cash-flow-classification-strategy--new) ⭐ NEW
3. [Part 2: Two-Stage Accrual Accounting](#part-2-two-stage-accrual-accounting) 
4. [Part 3: Current Gaps Analysis](#part-3-current-gaps-analysis)
5. [Part 4: Observer Documentation](#part-4-observer-documentation)
6. [Part 5: Observer Summary Matrix](#part-5-observer-summary-matrix)
7. [Part 5A: Observer Line Metadata Requirements](#part-5a-observer-line-metadata-requirements--new) ⭐ NEW
8. [Part 6: Financial Reports from Journals](#part-6-financial-reports-from-journals)
9. [Part 6A: Granular Drill-Down Reports](#part-6a-granular-drill-down-reports--new) ⭐ NEW
10. [Part 7: Implementation Plan](#part-7-implementation-plan)
    - Phase 1: Database Schema Updates (incl. metadata migration)
    - Phase 2: Create New Observers
    - Phase 3: Update Existing Observers
    - Phase 4: Register Observers
    - Phase 5: SubAccountService Helper ⭐ NEW
    - Phase 6: CashFlowClassifier Service ⭐ NEW
11. [Part 8: Testing & Debugging Guide](#part-8-testing--debugging-guide)
12. [Part 9: Bank Statement Report Implementation](#part-9-bank-statement-report-implementation)

---

## Part 1A: Granular Tracking & Drill-Down Strategy ⭐ NEW

### 1A.1 The Problem
To generate useful financial reports, we need to answer questions like:
- "What is total revenue from Lab services?"
- "What are all journal entries for Product ID 45?"
- "Show me all AR-HMO entries for NHIS"
- "What is revenue breakdown by service category?"

### 1A.2 Two-Pronged Solution

We use **TWO complementary approaches** for granular tracking:

| Approach | Purpose | When to Use |
|----------|---------|-------------|
| **Sub-Accounts** | Formal subsidiary ledger tracking | For entity-specific balances (HMO AR, Supplier AP) |
| **Line Metadata** | Quick filtering and drill-down | For product/service/category queries |

### 1A.3 Sub-Accounts (Already Exists in Schema)

The `account_sub_accounts` table provides formal subsidiary ledger tracking:

```
account_sub_accounts
├── account_id (FK to parent GL account)
├── code (e.g., "1110.HMO.NHIS")
├── name (e.g., "NHIS - National Health Insurance")
├── product_id (FK - for product-level tracking)
├── service_id (FK - for service-level tracking)
├── product_category_id (FK - for category tracking)
├── service_category_id (FK - for category tracking)
├── supplier_id (FK - for supplier tracking)
├── patient_id (FK - for patient tracking)
└── is_active
```

**Use Cases:**
- AR-HMO by HMO company (e.g., 1110.NHIS, 1110.HYGEIA)
- AP by Supplier (e.g., 2100.MEDSUPPLY, 2100.PHARMAPLUS)
- Revenue by Service Category (e.g., 4030.LAB, 4040.IMAGING)

### 1A.4 Line Metadata (NEW - Must Add)

Add direct metadata columns to `journal_entry_lines` for fast filtering:

```php
// Migration to add metadata columns
Schema::table('journal_entry_lines', function (Blueprint $table) {
    // Direct entity references for quick filtering
    $table->foreignId('product_id')->nullable()->after('sub_account_id')
          ->constrained('products')->nullOnDelete();
    $table->foreignId('service_id')->nullable()->after('product_id')
          ->constrained('services')->nullOnDelete();
    $table->foreignId('product_category_id')->nullable()->after('service_id')
          ->constrained('product_categories')->nullOnDelete();
    $table->foreignId('service_category_id')->nullable()->after('product_category_id')
          ->constrained('service_categories')->nullOnDelete();
    $table->foreignId('hmo_id')->nullable()->after('service_category_id')
          ->constrained('hmos')->nullOnDelete();
    $table->foreignId('supplier_id')->nullable()->after('hmo_id')
          ->constrained('suppliers')->nullOnDelete();
    $table->foreignId('patient_id')->nullable()->after('supplier_id')
          ->constrained('patients')->nullOnDelete();
    $table->foreignId('department_id')->nullable()->after('patient_id')
          ->constrained('departments')->nullOnDelete();
    $table->string('category', 50)->nullable()->after('department_id'); // 'lab', 'pharmacy', 'imaging', etc.
    
    // Indexes for fast queries
    $table->index('product_id');
    $table->index('service_id');
    $table->index('product_category_id');
    $table->index('service_category_id');
    $table->index('hmo_id');
    $table->index('supplier_id');
    $table->index('patient_id');
    $table->index('category');
});
```

### 1A.5 When to Use Which

| Scenario | Use Sub-Account | Use Line Metadata |
|----------|-----------------|-------------------|
| "Balance for HMO NHIS" | ✅ Query by sub_account | ✅ Also filter by hmo_id |
| "All entries for Product #45" | ❌ | ✅ Filter by product_id |
| "Revenue by service category" | ✅ Sub-account per category | ✅ Group by service_category_id |
| "Lab revenue this month" | ✅ Sub-account 4030.LAB | ✅ Filter by category='lab' |
| "Supplier ABC balance" | ✅ Sub-account 2100.ABC | ✅ Filter by supplier_id |

### 1A.6 Sample Queries Enabled

**Query 1: All Lab Revenue Entries**
```sql
SELECT je.*, jel.*
FROM journal_entry_lines jel
JOIN journal_entries je ON jel.journal_entry_id = je.id
WHERE jel.category = 'lab'
  AND jel.credit > 0  -- Revenue is credit
  AND je.status = 'posted';
```

**Query 2: Revenue by Service Category**
```sql
SELECT 
    sc.name as category,
    SUM(jel.credit) - SUM(jel.debit) as revenue
FROM journal_entry_lines jel
JOIN journal_entries je ON jel.journal_entry_id = je.id
JOIN service_categories sc ON jel.service_category_id = sc.id
WHERE je.status = 'posted'
  AND je.entry_date BETWEEN :from AND :to
GROUP BY sc.id, sc.name;
```

**Query 3: AR by HMO Company**
```sql
SELECT 
    h.name as hmo,
    SUM(jel.debit) - SUM(jel.credit) as receivable
FROM journal_entry_lines jel
JOIN journal_entries je ON jel.journal_entry_id = je.id
JOIN hmos h ON jel.hmo_id = h.id
JOIN accounts a ON jel.account_id = a.id
WHERE a.code = '1110'  -- AR-HMO account
  AND je.status = 'posted'
GROUP BY h.id, h.name;
```

**Query 4: Entries for Specific Product**
```sql
SELECT je.entry_date, je.entry_number, je.description,
       jel.debit, jel.credit, jel.narration
FROM journal_entry_lines jel
JOIN journal_entries je ON jel.journal_entry_id = je.id
WHERE jel.product_id = 45
  AND je.status = 'posted'
ORDER BY je.entry_date DESC;
```

### 1A.7 Sub-Account Auto-Creation Strategy

Create sub-accounts automatically when needed:

```php
// Helper Service: SubAccountService
class SubAccountService
{
    /**
     * Get or create sub-account for HMO under AR-HMO (1110)
     */
    public function getHmoSubAccount(Hmo $hmo): AccountSubAccount
    {
        $arHmo = Account::where('code', '1110')->first();
        
        return AccountSubAccount::firstOrCreate(
            ['account_id' => $arHmo->id, 'hmo_id' => $hmo->id],
            [
                'code' => "1110.HMO.{$hmo->id}",
                'name' => $hmo->name,
                'is_active' => true,
            ]
        );
    }

    /**
     * Get or create sub-account for Supplier under AP (2100)
     */
    public function getSupplierSubAccount(Supplier $supplier): AccountSubAccount
    {
        $ap = Account::where('code', '2100')->first();
        
        return AccountSubAccount::firstOrCreate(
            ['account_id' => $ap->id, 'supplier_id' => $supplier->id],
            [
                'code' => "2100.SUP.{$supplier->id}",
                'name' => $supplier->name,
                'is_active' => true,
            ]
        );
    }

    /**
     * Get or create sub-account for Service Category under Revenue
     */
    public function getServiceCategorySubAccount(string $revenueCode, ServiceCategory $category): AccountSubAccount
    {
        $revenueAccount = Account::where('code', $revenueCode)->first();
        
        return AccountSubAccount::firstOrCreate(
            ['account_id' => $revenueAccount->id, 'service_category_id' => $category->id],
            [
                'code' => "{$revenueCode}.CAT.{$category->id}",
                'name' => $category->name,
                'is_active' => true,
            ]
        );
    }
}
```

---

## Part 1: Accounting Flow Overview (GAAP/IFRS Compliant)

### 1.1 The Two-Stage Journal Entry Pattern

For proper accrual accounting, most transactions require **two** journal entries:

| Stage | When | Effect | Example |
|-------|------|--------|---------|
| **Recognition** | Event occurs (service rendered, goods received, salary earned) | Creates AR/AP | DR AR-HMO, CR Revenue |
| **Settlement** | Cash moves (payment received, payment made) | Clears AR/AP | DR Bank, CR AR-HMO |

### 1.2 Transaction Types and Required Entries

| Transaction | Recognition Entry | Settlement Entry |
|-------------|-------------------|------------------|
| **HMO Service** | When validated → DR AR-HMO, CR Revenue | When remitted → DR Bank, CR AR-HMO |
| **Cash Patient** | When paid → DR Cash/Bank, CR Revenue | N/A (single entry) |
| **PO Receipt** | When received → DR Inventory, CR AP | When paid → DR AP, CR Bank |
| **Salary** | When approved → DR Expense, CR Salary Payable | When paid → DR Salary Payable, CR Bank |
| **Expense (credit)** | When approved → DR Expense, CR AP | When paid → DR AP, CR Bank |
| **Expense (immediate)** | When approved → DR Expense, CR Cash/Bank | N/A (single entry) |

---

## Part 1B: Cash Flow Classification Strategy ⭐ NEW

### 1B.1 The Three Cash Flow Activities (IAS 7 / IFRS)

Cash flow statements classify all cash movements into three activities:

| Activity | Definition | Hospital Examples |
|----------|------------|-------------------|
| **Operating** | Day-to-day revenue and expense operations | Patient payments, supplier payments, salaries, utilities |
| **Investing** | Long-term asset purchases/sales | Equipment purchase, building renovations, asset sales |
| **Financing** | Capital structure changes | Loans, equity injections, dividend payments, loan repayments |

### 1B.2 Current System Structure

The system already has infrastructure for cash flow classification:

```
account_classes.cash_flow_category        → Default for whole class
accounts.cash_flow_category_override      → Override for specific account
journal_entry_lines.cash_flow_category    → Line-level classification (NOT CURRENTLY SET)
```

**Problem:** The `cash_flow_category` on journal entry lines is never populated by observers!

### 1B.3 Auto-Classification Strategy

Each journal entry line should auto-determine its cash flow category using this priority:

```
Priority 1: Explicit override in observer (highest priority)
Priority 2: Account's cash_flow_category_override
Priority 3: Account Class's cash_flow_category  
Priority 4: Smart inference from transaction context (category field)
```

### 1B.4 Classification Rules by Observer

| Observer | Default Classification | Logic |
|----------|----------------------|-------|
| `PaymentObserver` | **Operating** | Patient revenue is operating activity |
| `ExpenseObserver` | **Operating** | Most expenses are operating |
| `PayrollBatchObserver` | **Operating** | Salaries are operating expense |
| `ProductOrServiceRequestObserver` | **Operating** | Healthcare revenue is operating |
| `HmoRemittanceObserver` | **Operating** | AR collection is operating |
| `PurchaseOrderObserver` | **Operating** | Inventory purchase is operating |
| `PurchaseOrderPaymentObserver` | **Operating** | Supplier payment is operating |
| Asset Purchase Observer (future) | **Investing** | Equipment purchase is investing |
| Loan Receipt Observer (future) | **Financing** | Loan proceeds are financing |

### 1B.5 Category-Based Classification Map

Use the `category` metadata field to infer cash flow classification:

```php
const CASH_FLOW_MAP = [
    // Operating Activities (day-to-day operations)
    'consultation' => 'operating',
    'pharmacy' => 'operating',
    'lab' => 'operating',
    'imaging' => 'operating',
    'procedure' => 'operating',
    'admission' => 'operating',
    'hmo_remittance' => 'operating',
    'po_payment' => 'operating',
    'payroll_expense' => 'operating',
    'payroll_payment' => 'operating',
    'general_expense' => 'operating',
    'utilities' => 'operating',
    'maintenance' => 'operating',
    'inventory_receipt' => 'operating',
    
    // Investing Activities (long-term assets)
    'equipment_purchase' => 'investing',
    'asset_sale' => 'investing',
    'capital_expenditure' => 'investing',
    
    // Financing Activities (capital structure)
    'loan_receipt' => 'financing',
    'loan_repayment' => 'financing',
    'dividend_payment' => 'financing',
    'equity_injection' => 'financing',
];
```

### 1B.6 CashFlowClassifier Service

Create a service that determines cash flow category for any journal entry line:

```php
class CashFlowClassifier
{
    /**
     * Determine cash flow category for a journal entry line
     * 
     * @param array $lineData Line data with account_id and category
     * @param string|null $explicitOverride Override from observer
     * @return string|null 'operating', 'investing', 'financing', or null
     */
    public function classify(array $lineData, ?string $explicitOverride = null): ?string
    {
        // Priority 1: Explicit override
        if ($explicitOverride) {
            return $explicitOverride;
        }
        
        // Priority 2: Account's override
        $account = Account::find($lineData['account_id']);
        if ($account?->cash_flow_category_override) {
            return $account->cash_flow_category_override;
        }
        
        // Priority 3: Account class default
        if ($account?->accountGroup?->accountClass?->cash_flow_category) {
            return $account->accountGroup->accountClass->cash_flow_category;
        }
        
        // Priority 4: Infer from category field
        if (!empty($lineData['category'])) {
            return self::CASH_FLOW_MAP[$lineData['category']] ?? 'operating';
        }
        
        // Default: Operating (most common in hospital context)
        return 'operating';
    }
}
```

### 1B.7 Updated Cash Flow Report Query

With line-level classification, the cash flow report becomes more accurate:

```sql
-- Operating Activities from journal entry lines
SELECT 
    jel.category,
    a.name as account_name,
    SUM(CASE WHEN a.code LIKE '1%' THEN jel.debit - jel.credit ELSE 0 END) as cash_change
FROM journal_entry_lines jel
JOIN accounts a ON jel.account_id = a.id
JOIN journal_entries je ON jel.journal_entry_id = je.id
WHERE je.status = 'posted'
  AND je.entry_date BETWEEN :from AND :to
  AND jel.cash_flow_category = 'operating'
  AND a.is_bank_account = true  -- Only cash/bank accounts
GROUP BY jel.category, a.id, a.name
ORDER BY cash_change DESC;
```

---

## Part 2: Current System Analysis

### 2.1 Income Sources (Money Coming In)

| Source | Model | Has Observer | Accrual Correct | Status |
|--------|-------|--------------|-----------------|--------|
| **Patient Payments (Cash)** | `Payment` | ✅ `PaymentObserver` | ⚠️ No AR at billing | Cash-basis |
| **Account Deposits** | `Payment` | ✅ `PaymentObserver` | ⚠️ No AR at billing | Cash-basis |
| **HMO Claims** | `ProductOrServiceRequest` | ❌ None | ❌ No entry at validation | **MISSING** |
| **HMO Remittances** | `HmoRemittance` | ❌ None | ❌ No bank entry | **MISSING** |

### 2.2 Expense Sources (Money Going Out)

| Source | Model | Has Observer | Accrual Correct | Status |
|--------|-------|--------------|-----------------|--------|
| **General Expenses** | `Expense` | ✅ `ExpenseObserver` | ⚠️ Mixed - some AP, some direct | Partially correct |
| **PO Receipt** | `PurchaseOrder` | ✅ `PurchaseOrderObserver` | ✅ Creates AP | Correct |
| **PO Payments** | `PurchaseOrderPayment` | ❌ None | ❌ AP never cleared | **MISSING** |
| **Payroll Approval** | `PayrollBatch` | ⚠️ Only at payment | ❌ No liability at approval | **NEEDS FIX** |
| **Payroll Payment** | `PayrollBatch` | ✅ At payment | ❌ Creates expense, not liability offset | **NEEDS FIX** |

### 2.3 Current Account Structure

```
Chart of Accounts (Key Accounts):
├── ASSETS (1xxx)
│   ├── 1010 - Cash in Hand
│   ├── 1020 - Bank Account (generic - needs per-bank accounts)
│   ├── 1025 - Cheques Receivable
│   ├── 1030 - Petty Cash
│   ├── 1110 - Accounts Receivable - HMO
│   ├── 1200 - Accounts Receivable (General)
│   └── 1300 - Inventory
├── LIABILITIES (2xxx)
│   ├── 2050 - Salaries Payable (NEEDS CREATION)
│   └── 2100 - Accounts Payable
├── REVENUE (4xxx)
│   ├── 4000 - General Revenue
│   ├── 4010 - Consultation Revenue
│   ├── 4020 - Pharmacy Revenue
│   ├── 4030 - Laboratory Revenue
│   ├── 4040 - Imaging Revenue
│   ├── 4050 - Procedure Revenue
│   └── 4060 - Admission Revenue
└── EXPENSES (5xxx-6xxx)
    ├── 5010 - Cost of Goods Sold
    ├── 6010 - Store Operating Expenses
    ├── 6020 - Maintenance & Repairs
    ├── 6030 - Utilities Expense
    ├── 6040 - Salaries & Wages
    └── 6090 - Miscellaneous Expenses
```

---

## Part 3: Identified Gaps

### Gap 1: No HMO Revenue Recognition at Validation ⚠️ CRITICAL
**Problem:** When HMO services are validated, no journal entry is created. Revenue is never recognized for HMO patients.

**Impact:** 
- AR-HMO report shows zero (nothing owed by HMOs)
- Revenue is understated
- Cannot track what HMOs owe us

**Solution:** Create `ProductOrServiceRequestObserver` that creates journal entry when `validation_status` changes to `approved`.

### Gap 2: HMO Remittance Missing Observer ⚠️ CRITICAL
**Problem:** When HMO pays a remittance, no journal entry is created to offset AR-HMO.

**Impact:**
- Bank statement incomplete
- AR-HMO never decreases (shows inflated receivables)

**Solution:** Create `HmoRemittanceObserver` to debit Bank and credit AR-HMO.

### Gap 3: PurchaseOrderPayment Missing Observer ⚠️ HIGH
**Problem:** When supplier is paid, no journal entry is created to offset AP.

**Impact:**
- AP balance never decreases (shows we still owe suppliers we've paid)
- Bank statement incomplete

**Solution:** Create `PurchaseOrderPaymentObserver` to debit AP and credit Bank/Cash.

### Gap 4: Payroll Uses Cash-Basis ⚠️ HIGH
**Problem:** `PayrollBatchObserver` only creates entry at payment, not at approval. Entry is `DR Expense, CR Bank` instead of proper two-stage.

**Impact:**
- No salary liability shown until payday
- Distorts profit in periods before payment

**Solution:** Modify `PayrollBatchObserver` for two-stage entries:
- Stage 1 (approved): DR Salaries Expense, CR Salaries Payable
- Stage 2 (paid): DR Salaries Payable, CR Bank

### Gap 5: Single Bank Account in GL ⚠️ MEDIUM
**Problem:** All bank transactions go to account 1020, regardless of which actual bank.

**Impact:** Cannot generate per-bank statements for reconciliation.

**Solution:** Create individual GL accounts per bank via `BankObserver`.

### Gap 6: Missing account_id Columns ⚠️ MEDIUM
**Problem:** Most transaction tables have `bank_id` but not `account_id` (direct link to GL account).

**Impact:** Cannot easily determine which GL account to use for entries.

**Solution:** Add `account_id` column to relevant tables.

---

## Part 4: Observer Documentation (Single Source of Debug)

This section documents EVERY accounting observer, what entries they create, and when. **If journal entries are incorrect, debug starts here.**

---

### 4.1 PaymentObserver ✅ EXISTS
**File:** `app/Observers/Accounting/PaymentObserver.php`  
**Model:** `App\Models\Payment`  
**Trigger:** `created` event

#### Entry Created (Cash Payment - Single Stage)
| Condition | Debit Account | Credit Account |
|-----------|---------------|----------------|
| `payment_method = 'cash'` | 1010 (Cash in Hand) | 4xxx (Revenue by type) or 1200 (AR if invoice) |
| `payment_method = 'bank_transfer'` | 1020 (Bank) | 4xxx (Revenue) or 1200 (AR) |
| `payment_method = 'card'/'pos'` | 1020 (Bank) | 4xxx (Revenue) or 1200 (AR) |
| `payment_method = 'cheque'` | 1025 (Cheques Receivable) | 4xxx (Revenue) or 1200 (AR) |

#### Revenue Account Mapping
```php
'consultation' => '4010'  // Consultation Revenue
'pharmacy'     => '4020'  // Pharmacy Revenue
'lab'          => '4030'  // Laboratory Revenue
'imaging'      => '4040'  // Imaging Revenue
'procedure'    => '4050'  // Procedure Revenue
'admission'    => '4060'  // Admission Revenue
default        => '4000'  // General Revenue
```

#### Example Entry
```
Payment #PAY-001 | Cash | ₦50,000 | Consultation
────────────────────────────────────────────────
DR: 1010 Cash in Hand           ₦50,000
    CR: 4010 Consultation Revenue        ₦50,000
```

#### ⚠️ Issue: Cash-Basis Only
This observer creates revenue at payment time. For cash patients this is correct. But for credit/invoice patients, AR should be created at billing time, then cleared at payment.

---

### 4.2 ExpenseObserver ✅ EXISTS
**File:** `app/Observers/Accounting/ExpenseObserver.php`  
**Model:** `App\Models\Expense`  
**Trigger:** `updated` event when `status` changes to `'approved'`

#### Entry Created
| Condition | Debit Account | Credit Account |
|-----------|---------------|----------------|
| Supplier + no payment_method | 6xxx (Expense by category) | 2100 (Accounts Payable) |
| `payment_method = 'cash'` | 6xxx (Expense) | 1010 (Cash in Hand) |
| `payment_method = 'bank_transfer'` | 6xxx (Expense) | 1020 (Bank) |

#### Expense Account Mapping
```php
'purchase_order' => '5010'  // Cost of Goods Sold
'store_expense'  => '6010'  // Store Operating Expenses
'maintenance'    => '6020'  // Maintenance & Repairs
'utilities'      => '6030'  // Utilities Expense
'salaries'       => '6040'  // Salaries & Wages
'other'          => '6090'  // Miscellaneous Expenses
```

#### Example Entry (Immediate Payment)
```
Expense #EXP-001 | Utilities | ₦30,000 | Bank Transfer
────────────────────────────────────────────────────────
DR: 6030 Utilities Expense      ₦30,000
    CR: 1020 Bank Account                ₦30,000
```

#### Example Entry (Credit - AP)
```
Expense #EXP-002 | Maintenance | ₦100,000 | Supplier: ABC Ltd | No immediate payment
──────────────────────────────────────────────────────────────────────────────────────
DR: 6020 Maintenance & Repairs  ₦100,000
    CR: 2100 Accounts Payable            ₦100,000
```

#### ⚠️ Issue: No Expense Payment Observer
When AP expenses are later paid, there's no observer to clear the AP. Need `ExpensePaymentObserver` or add to `ExpenseObserver.updated()` when `payment_method` is set after AP was created.

---

### 4.3 PurchaseOrderObserver ✅ EXISTS
**File:** `app/Observers/Accounting/PurchaseOrderObserver.php`  
**Model:** `App\Models\PurchaseOrder`  
**Trigger:** `updated` event when `status` changes to `'received'`

#### Entry Created (PO Received)
| Debit Account | Credit Account |
|---------------|----------------|
| 1300 (Inventory) | 2100 (Accounts Payable) |

#### Example Entry
```
PO #PO-2026-001 | Received | ₦500,000 | Supplier: MedSupply Ltd
────────────────────────────────────────────────────────────────
DR: 1300 Inventory              ₦500,000
    CR: 2100 Accounts Payable            ₦500,000
```

#### ✅ This is Correct
This follows proper accrual accounting - AP is created when goods are received.

#### ❌ Missing: PO Payment Entry
When supplier is paid, AP should be debited. See Gap 3 - `PurchaseOrderPaymentObserver`.

---

### 4.4 PayrollBatchObserver ⚠️ NEEDS FIX
**File:** `app/Observers/Accounting/PayrollBatchObserver.php`  
**Model:** `App\Models\HR\PayrollBatch`  
**Trigger:** `updated` event when `status` changes to `'paid'`

#### Current Entry (INCORRECT - Cash Basis)
| Debit Account | Credit Account |
|---------------|----------------|
| 6040 (Salaries & Wages) | 1020 (Bank - hardcoded) |

#### Current Example Entry
```
Payroll Batch #PAY-2026-01 | Status: Paid | ₦2,000,000
────────────────────────────────────────────────────────
DR: 6040 Salaries & Wages       ₦2,000,000
    CR: 1020 Bank Account                ₦2,000,000
```

#### ❌ Problems
1. **Cash-basis:** Expense only recognized at payment, not when salaries are earned
2. **Hardcoded bank:** Always uses 1020, no bank selection
3. **No liability stage:** Should create Salaries Payable at approval

#### ✅ Required Fix (Two-Stage Accrual)

**Stage 1: When status = 'approved'**
```
DR: 6040 Salaries & Wages       ₦2,000,000
    CR: 2050 Salaries Payable            ₦2,000,000
```

**Stage 2: When status = 'paid'**
```
DR: 2050 Salaries Payable       ₦2,000,000
    CR: 1020 Bank Account (or selected)   ₦2,000,000
```

---

### 4.5 ProductOrServiceRequestObserver ❌ DOES NOT EXIST - MUST CREATE
**File:** `app/Observers/Accounting/ProductOrServiceRequestObserver.php` (TO CREATE)  
**Model:** `App\Models\ProductOrServiceRequest`  
**Trigger:** `updated` event when `validation_status` changes to `'approved'`

#### Purpose
Recognizes revenue for HMO patients when their claims are validated (approved). This creates the AR-HMO that will later be offset when HMO remits payment.

#### Entry to Create (HMO Claim Validated)
| Debit Account | Credit Account |
|---------------|----------------|
| 1110 (Accounts Receivable - HMO) | 4xxx (Revenue by service type) |

#### Example Entry
```
HMO Claim Validated | Patient: John Doe | HMO: NHIS | Lab Test | ₦15,000
────────────────────────────────────────────────────────────────────────
DR: 1110 AR - HMO               ₦15,000
    CR: 4030 Laboratory Revenue          ₦15,000
```

#### Revenue Account Mapping (Based on service type)
```php
'pharmacy'     => '4020'  // Product (pharmacy items)
'lab'          => '4030'  // Laboratory services
'imaging'      => '4040'  // Radiology/Imaging services
'consultation' => '4010'  // Consultation (if service type)
'procedure'    => '4050'  // Procedure services
default        => '4000'  // General Revenue
```

#### Implementation Notes
- Only create entry for HMO patients (`hmo_id` is not null)
- Use `claims_amount` for HMO portion, not `payable_amount`
- If `claims_amount` is 0, no entry needed (fully self-pay)

---

### 4.6 HmoRemittanceObserver ❌ DOES NOT EXIST - MUST CREATE
**File:** `app/Observers/Accounting/HmoRemittanceObserver.php` (TO CREATE)  
**Model:** `App\Models\HmoRemittance`  
**Trigger:** `created` event (when remittance is recorded)

#### Purpose
Records cash received from HMO and offsets the AR-HMO that was created when claims were validated.

#### Entry to Create (HMO Remittance Received)
| Debit Account | Credit Account |
|---------------|----------------|
| 1020 (Bank - or selected bank) | 1110 (Accounts Receivable - HMO) |

#### Example Entry
```
HMO Remittance | NHIS | Period: Jan 2026 | ₦500,000 | Bank: Zenith
────────────────────────────────────────────────────────────────────
DR: 1020 Bank Account           ₦500,000
    CR: 1110 AR - HMO                    ₦500,000
```

#### Implementation Notes
- Need `bank_id` and `account_id` columns on `hmo_remittances` table
- Must update linked `ProductOrServiceRequest` records with `hmo_remittance_id`
- Amount should match sum of `claims_amount` for linked items

---

### 4.7 PurchaseOrderPaymentObserver ❌ DOES NOT EXIST - MUST CREATE
**File:** `app/Observers/Accounting/PurchaseOrderPaymentObserver.php` (TO CREATE)  
**Model:** `App\Models\PurchaseOrderPayment`  
**Trigger:** `created` event

#### Purpose
Records payment to supplier and offsets the AP that was created when PO was received.

#### Entry to Create (Supplier Payment)
| Debit Account | Credit Account |
|---------------|----------------|
| 2100 (Accounts Payable) | 1010/1020 (Cash or Bank based on payment_method) |

#### Example Entry
```
PO Payment | PO#: PO-2026-001 | Supplier: MedSupply Ltd | ₦250,000 | Bank
─────────────────────────────────────────────────────────────────────────
DR: 2100 Accounts Payable       ₦250,000
    CR: 1020 Bank Account                ₦250,000
```

#### Credit Account Mapping
```php
'cash'          => '1010'  // Cash in Hand
'bank_transfer' => '1020'  // Bank (or specific bank account)
'cheque'        => '1020'  // Bank (cheque drawn on bank)
default         => '1010'  // Default to cash
```

---

### 4.8 BankObserver ❌ DOES NOT EXIST - RECOMMENDED
**File:** `app/Observers/BankObserver.php` (TO CREATE)  
**Model:** `App\Models\Bank`  
**Trigger:** `created` event

#### Purpose
Automatically creates a GL account for each bank to enable per-bank statements.

#### Action (No Journal Entry - Just Creates Account)
When a bank is created, create corresponding GL account:
```php
Account::create([
    'code' => '102' . str_pad($bank->id, 2, '0', STR_PAD_LEFT), // 10201, 10202...
    'name' => "{$bank->name} - {$bank->account_number}",
    'account_group_id' => $currentAssetsGroup->id,
    'is_bank_account' => true,
    'bank_id' => $bank->id,
]);
```
---

## Part 5: Observer Summary Matrix

Quick reference for debugging - shows what entry each observer creates:

| Observer | Model | Trigger | Debit | Credit | Status |
|----------|-------|---------|-------|--------|--------|
| `PaymentObserver` | Payment | created | Cash/Bank (1010/1020) | Revenue (4xxx) or AR (1200) | ✅ Exists |
| `ExpenseObserver` | Expense | status→approved | Expense (6xxx) | Cash/Bank or AP (2100) | ✅ Exists |
| `PurchaseOrderObserver` | PurchaseOrder | status→received | Inventory (1300) | AP (2100) | ✅ Exists |
| `PayrollBatchObserver` | PayrollBatch | status→paid | Expense (6040) | Bank (1020) | ⚠️ Needs Fix |
| `ProductOrServiceRequestObserver` | ProductOrServiceRequest | validation→approved | AR-HMO (1110) | Revenue (4xxx) | ❌ Create |
| `HmoRemittanceObserver` | HmoRemittance | created | Bank (1020) | AR-HMO (1110) | ❌ Create |
| `PurchaseOrderPaymentObserver` | PurchaseOrderPayment | created | AP (2100) | Cash/Bank | ❌ Create |
| `BankObserver` | Bank | created | N/A - Creates GL Account | N/A | ❌ Create |

---

## Part 5A: Observer Line Metadata Requirements ⭐ NEW

Each observer MUST populate these metadata fields on `journal_entry_lines` for granular filtering:

### 5A.1 Metadata Fields by Observer

| Observer | product_id | service_id | product_category_id | service_category_id | hmo_id | supplier_id | patient_id | department_id | category | cash_flow |
|----------|------------|------------|---------------------|---------------------|--------|-------------|------------|---------------|----------|-----------|
| `PaymentObserver` | ✅ If product | ✅ If service | ✅ | ✅ | ✅ If HMO | ❌ | ✅ | ❌ | ✅ payment_type | operating |
| `ExpenseObserver` | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ✅ expense_category | operating |
| `PurchaseOrderObserver` | ✅ Per item | ❌ | ✅ | ❌ | ❌ | ✅ | ❌ | ❌ | 'inventory_receipt' | operating |
| `PayrollBatchObserver` | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | 'payroll_*' | operating |
| `ProductOrServiceRequestObserver` | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ | ✅ service_type | operating |
| `HmoRemittanceObserver` | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | ❌ | 'hmo_remittance' | operating |
| `PurchaseOrderPaymentObserver` | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ | ❌ | ❌ | 'po_payment' | operating |

### 5A.2 Cash Flow Classification by Observer ⭐ NEW

| Observer | Cash Flow Activity | Rationale |
|----------|-------------------|-----------|
| `PaymentObserver` | **Operating** | Patient revenue from healthcare services |
| `ExpenseObserver` | **Operating** (default) | Day-to-day operational expenses |
| `PurchaseOrderObserver` | **Operating** | Inventory is operating for hospitals |
| `PayrollBatchObserver` | **Operating** | Salaries are operating expenses |
| `ProductOrServiceRequestObserver` | **Operating** | Healthcare revenue recognition |
| `HmoRemittanceObserver` | **Operating** | Collection of AR is operating |
| `PurchaseOrderPaymentObserver` | **Operating** | Supplier payments for inventory |
| Future: Asset Purchase | **Investing** | Long-term equipment purchases |
| Future: Loan Observer | **Financing** | Capital structure changes |

### 5A.3 Sub-Account Usage by Observer

| Observer | Uses Sub-Account | Sub-Account Type | Example Code |
|----------|------------------|------------------|--------------|
| `PaymentObserver` | ⚠️ Should use | Patient AR sub-account | 1200.PAT.123 |
| `ExpenseObserver` | ✅ Should use | Supplier AP sub-account | 2100.SUP.45 |
| `PurchaseOrderObserver` | ✅ Should use | Supplier AP sub-account | 2100.SUP.45 |
| `PayrollBatchObserver` | ❌ | N/A | N/A |
| `ProductOrServiceRequestObserver` | ✅ Must use | HMO AR sub-account | 1110.HMO.NHIS |
| `HmoRemittanceObserver` | ✅ Must use | HMO AR sub-account | 1110.HMO.NHIS |
| `PurchaseOrderPaymentObserver` | ✅ Must use | Supplier AP sub-account | 2100.SUP.45 |

### 5A.4 Journal Entry Line Structure with Metadata

```php
// Example: ProductOrServiceRequestObserver creating HMO revenue entry
$lines = [
    [
        'account_id' => $arHmo->id,           // 1110 AR-HMO
        'sub_account_id' => $hmoSubAccount->id, // 1110.HMO.NHIS
        'debit_amount' => $request->claims_amount,
        'credit_amount' => 0,
        'description' => "HMO Claim: {$request->hmo->name}",
        // METADATA for drill-down
        'product_id' => $request->product_id,
        'service_id' => $request->service_id,
        'product_category_id' => $request->product?->category_id,
        'service_category_id' => $request->service?->category_id,
        'hmo_id' => $request->hmo_id,
        'patient_id' => $request->patient_id,
        'category' => $this->getCategory($request), // 'lab', 'pharmacy', etc.
    ],
    [
        'account_id' => $revenueAccount->id,  // 4030 Lab Revenue
        'sub_account_id' => $serviceCategorySubAccount->id, // 4030.CAT.12
        'debit_amount' => 0,
        'credit_amount' => $request->claims_amount,
        'description' => $this->getServiceDescription($request),
        // SAME METADATA
        'product_id' => $request->product_id,
        'service_id' => $request->service_id,
        'product_category_id' => $request->product?->category_id,
        'service_category_id' => $request->service?->category_id,
        'hmo_id' => $request->hmo_id,
        'patient_id' => $request->patient_id,
        'category' => $this->getCategory($request),
    ]
];
```

---

## Part 6: Report Derivation from Journal Entries

All reports MUST derive values from journal entries, not from transaction tables directly.

### 6.1 Accounts Receivable Report (AR)
```sql
-- AR balance = Sum of DEBITS - Sum of CREDITS for AR accounts
SELECT 
    a.code,
    a.name,
    SUM(jel.debit_amount) - SUM(jel.credit_amount) as balance
FROM journal_entry_lines jel
JOIN accounts a ON jel.account_id = a.id
JOIN journal_entries je ON jel.journal_entry_id = je.id
WHERE a.code LIKE '11%'  -- AR accounts (1110, 1120, 1200, etc.)
  AND je.status = 'posted'
  AND je.entry_date <= :as_of_date
GROUP BY a.id, a.code, a.name
HAVING balance != 0;
```

### 6.2 Accounts Payable Report (AP)
```sql
-- AP balance = Sum of CREDITS - Sum of DEBITS for AP accounts (liability is credit balance)
SELECT 
    a.code,
    a.name,
    SUM(jel.credit_amount) - SUM(jel.debit_amount) as balance
FROM journal_entry_lines jel
JOIN accounts a ON jel.account_id = a.id
JOIN journal_entries je ON jel.journal_entry_id = je.id
WHERE a.code LIKE '21%'  -- AP accounts (2100, 2050, etc.)
  AND je.status = 'posted'
  AND je.entry_date <= :as_of_date
GROUP BY a.id, a.code, a.name
HAVING balance != 0;
```

### 6.3 Revenue Report (Income Statement)
```sql
-- Revenue = Sum of CREDITS for revenue accounts
SELECT 
    a.code,
    a.name,
    SUM(jel.credit_amount) - SUM(jel.debit_amount) as revenue
FROM journal_entry_lines jel
JOIN accounts a ON jel.account_id = a.id
JOIN journal_entries je ON jel.journal_entry_id = je.id
WHERE a.code LIKE '4%'  -- Revenue accounts
  AND je.status = 'posted'
  AND je.entry_date BETWEEN :from_date AND :to_date
GROUP BY a.id, a.code, a.name;
```

### 6.4 Expense Report (Income Statement)
```sql
-- Expenses = Sum of DEBITS for expense accounts
SELECT 
    a.code,
    a.name,
    SUM(jel.debit_amount) - SUM(jel.credit_amount) as expense
FROM journal_entry_lines jel
JOIN accounts a ON jel.account_id = a.id
JOIN journal_entries je ON jel.journal_entry_id = je.id
WHERE a.code LIKE '5%' OR a.code LIKE '6%'  -- Expense accounts
  AND je.status = 'posted'
  AND je.entry_date BETWEEN :from_date AND :to_date
GROUP BY a.id, a.code, a.name;
```

### 6.5 Bank Statement
```sql
-- Bank statement from journal entries for specific bank account
SELECT 
    je.entry_date,
    je.entry_number,
    je.description,
    je.reference_type,
    je.reference_id,
    jel.debit_amount as deposit,
    jel.credit_amount as withdrawal,
    jel.narration
FROM journal_entry_lines jel
JOIN journal_entries je ON jel.journal_entry_id = je.id
WHERE jel.account_id = :bank_account_id
  AND je.status = 'posted'
  AND je.entry_date BETWEEN :from_date AND :to_date
ORDER BY je.entry_date, je.id;
```

### 6.6 Cash Position Report
```sql
-- Cash position from journal entries
SELECT 
    a.code,
    a.name,
    SUM(jel.debit_amount) - SUM(jel.credit_amount) as balance
FROM journal_entry_lines jel
JOIN accounts a ON jel.account_id = a.id
JOIN journal_entries je ON jel.journal_entry_id = je.id
WHERE a.code IN ('1010', '1020', '1030')  -- Cash, Bank, Petty Cash
  AND je.status = 'posted'
  AND je.entry_date <= :as_of_date
GROUP BY a.id, a.code, a.name;
```

---

## Part 6A: Granular Drill-Down Reports ⭐ NEW

Using the line metadata, these additional reports become possible:

### 6A.1 Revenue by Service Category
```sql
SELECT 
    sc.name as category,
    SUM(jel.credit) - SUM(jel.debit) as revenue
FROM journal_entry_lines jel
JOIN journal_entries je ON jel.journal_entry_id = je.id
LEFT JOIN service_categories sc ON jel.service_category_id = sc.id
JOIN accounts a ON jel.account_id = a.id
WHERE a.code LIKE '4%'
  AND je.status = 'posted'
  AND je.entry_date BETWEEN :from AND :to
GROUP BY sc.id, sc.name
ORDER BY revenue DESC;
```

### 6A.2 Revenue by Product Category
```sql
SELECT 
    pc.name as category,
    SUM(jel.credit) - SUM(jel.debit) as revenue
FROM journal_entry_lines jel
JOIN journal_entries je ON jel.journal_entry_id = je.id
LEFT JOIN product_categories pc ON jel.product_category_id = pc.id
JOIN accounts a ON jel.account_id = a.id
WHERE a.code LIKE '4%'
  AND je.status = 'posted'
  AND je.entry_date BETWEEN :from AND :to
GROUP BY pc.id, pc.name
ORDER BY revenue DESC;
```

### 6A.3 AR-HMO by HMO Company
```sql
SELECT 
    h.name as hmo,
    h.id as hmo_id,
    SUM(jel.debit) - SUM(jel.credit) as receivable
FROM journal_entry_lines jel
JOIN journal_entries je ON jel.journal_entry_id = je.id
JOIN hmos h ON jel.hmo_id = h.id
JOIN accounts a ON jel.account_id = a.id
WHERE a.code = '1110'
  AND je.status = 'posted'
  AND je.entry_date <= :as_of_date
GROUP BY h.id, h.name
HAVING receivable > 0
ORDER BY receivable DESC;
```

### 6A.4 AP by Supplier
```sql
SELECT 
    s.name as supplier,
    s.id as supplier_id,
    SUM(jel.credit) - SUM(jel.debit) as payable
FROM journal_entry_lines jel
JOIN journal_entries je ON jel.journal_entry_id = je.id
JOIN suppliers s ON jel.supplier_id = s.id
JOIN accounts a ON jel.account_id = a.id
WHERE a.code = '2100'
  AND je.status = 'posted'
  AND je.entry_date <= :as_of_date
GROUP BY s.id, s.name
HAVING payable > 0
ORDER BY payable DESC;
```

### 6A.5 All Entries for a Specific Service
```sql
SELECT 
    je.entry_date,
    je.entry_number,
    je.description,
    a.code as account_code,
    a.name as account_name,
    jel.debit,
    jel.credit,
    jel.narration
FROM journal_entry_lines jel
JOIN journal_entries je ON jel.journal_entry_id = je.id
JOIN accounts a ON jel.account_id = a.id
WHERE jel.service_id = :service_id
  AND je.status = 'posted'
ORDER BY je.entry_date DESC;
```

### 6A.6 All Entries for a Specific Product
```sql
SELECT 
    je.entry_date,
    je.entry_number,
    je.description,
    a.code as account_code,
    a.name as account_name,
    jel.debit,
    jel.credit,
    jel.narration
FROM journal_entry_lines jel
JOIN journal_entries je ON jel.journal_entry_id = je.id
JOIN accounts a ON jel.account_id = a.id
WHERE jel.product_id = :product_id
  AND je.status = 'posted'
ORDER BY je.entry_date DESC;
```

### 6A.7 Revenue by Category (Using category field)
```sql
SELECT 
    jel.category,
    SUM(jel.credit) - SUM(jel.debit) as revenue
FROM journal_entry_lines jel
JOIN journal_entries je ON jel.journal_entry_id = je.id
JOIN accounts a ON jel.account_id = a.id
WHERE a.code LIKE '4%'
  AND je.status = 'posted'
  AND je.entry_date BETWEEN :from AND :to
  AND jel.category IS NOT NULL
GROUP BY jel.category
ORDER BY revenue DESC;
```

### 6A.8 Patient Transaction History
```sql
SELECT 
    je.entry_date,
    je.entry_number,
    je.description,
    a.code as account_code,
    a.name as account_name,
    jel.debit,
    jel.credit,
    jel.category
FROM journal_entry_lines jel
JOIN journal_entries je ON jel.journal_entry_id = je.id
JOIN accounts a ON jel.account_id = a.id
WHERE jel.patient_id = :patient_id
  AND je.status = 'posted'
ORDER BY je.entry_date DESC;
```

### 6A.9 Sub-Account Balance Report
```sql
-- Show all sub-accounts with their balances
SELECT 
    a.code as parent_code,
    a.name as parent_name,
    sa.code as sub_code,
    sa.name as sub_name,
    SUM(jel.debit) - SUM(jel.credit) as balance
FROM journal_entry_lines jel
JOIN journal_entries je ON jel.journal_entry_id = je.id
JOIN account_sub_accounts sa ON jel.sub_account_id = sa.id
JOIN accounts a ON sa.account_id = a.id
WHERE je.status = 'posted'
  AND je.entry_date <= :as_of_date
GROUP BY a.id, a.code, a.name, sa.id, sa.code, sa.name
HAVING balance != 0
ORDER BY a.code, sa.code;
```

---

## Part 7: Implementation Plan

### Phase 1: Database Schema Updates

#### 7.1.1 Migration: Create Salaries Payable Account
```php
// Run artisan tinker or seeder
Account::create([
    'code' => '2050',
    'name' => 'Salaries Payable',
    'account_group_id' => $liabilitiesGroup->id,
    'is_active' => true,
]);
```

#### 7.1.2 Migration: Add metadata columns to journal_entry_lines ⭐ NEW
```php
// database/migrations/xxxx_add_metadata_to_journal_entry_lines.php
Schema::table('journal_entry_lines', function (Blueprint $table) {
    // Direct entity references for granular filtering and drill-down
    $table->foreignId('product_id')->nullable()->after('sub_account_id')
          ->constrained('products')->nullOnDelete();
    $table->foreignId('service_id')->nullable()->after('product_id')
          ->constrained('services')->nullOnDelete();
    $table->foreignId('product_category_id')->nullable()->after('service_id')
          ->constrained('product_categories')->nullOnDelete();
    $table->foreignId('service_category_id')->nullable()->after('product_category_id')
          ->constrained('service_categories')->nullOnDelete();
    $table->foreignId('hmo_id')->nullable()->after('service_category_id')
          ->constrained('hmos')->nullOnDelete();
    $table->foreignId('supplier_id')->nullable()->after('hmo_id')
          ->constrained('suppliers')->nullOnDelete();
    $table->foreignId('patient_id')->nullable()->after('supplier_id')
          ->constrained('patients')->nullOnDelete();
    $table->foreignId('department_id')->nullable()->after('patient_id')
          ->constrained('departments')->nullOnDelete();
    $table->string('category', 50)->nullable()->after('department_id')
          ->comment('lab, pharmacy, imaging, consultation, procedure, admission, payroll, expense, po_payment, hmo_remittance');
    
    // Indexes for fast queries
    $table->index('product_id', 'jel_product_idx');
    $table->index('service_id', 'jel_service_idx');
    $table->index('product_category_id', 'jel_prod_cat_idx');
    $table->index('service_category_id', 'jel_svc_cat_idx');
    $table->index('hmo_id', 'jel_hmo_idx');
    $table->index('supplier_id', 'jel_supplier_idx');
    $table->index('patient_id', 'jel_patient_idx');
    $table->index('category', 'jel_category_idx');
});
```

#### 7.1.3 Migration: Add account_id columns to transaction tables
```php
Schema::table('hmo_remittances', function (Blueprint $table) {
    $table->foreignId('bank_id')->nullable()->after('payment_method')
          ->constrained('banks')->nullOnDelete();
    $table->foreignId('account_id')->nullable()->after('bank_id')
          ->constrained('accounts')->nullOnDelete();
});

Schema::table('payroll_batches', function (Blueprint $table) {
    $table->string('payment_method')->default('bank_transfer')->after('total_net');
    $table->foreignId('bank_id')->nullable()->after('payment_method')
          ->constrained('banks')->nullOnDelete();
    $table->foreignId('account_id')->nullable()->after('bank_id')
          ->constrained('accounts')->nullOnDelete();
});

Schema::table('payments', function (Blueprint $table) {
    $table->foreignId('account_id')->nullable()->after('bank_id')
          ->constrained('accounts')->nullOnDelete();
});

Schema::table('expenses', function (Blueprint $table) {
    $table->foreignId('account_id')->nullable()->after('bank_id')
          ->constrained('accounts')->nullOnDelete();
});

Schema::table('purchase_order_payments', function (Blueprint $table) {
    $table->foreignId('account_id')->nullable()->after('bank_id')
          ->constrained('accounts')->nullOnDelete();
});

Schema::table('banks', function (Blueprint $table) {
    $table->foreignId('account_id')->nullable()->after('is_active')
          ->constrained('accounts')->nullOnDelete();
});
```

### Phase 2: Create Missing Observers

#### 7.2.1 ProductOrServiceRequestObserver (NEW) ⭐ WITH GRANULAR METADATA
```php
<?php

namespace App\Observers\Accounting;

use App\Models\ProductOrServiceRequest;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubAccount;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * ProductOrServiceRequest Observer
 *
 * ACCRUAL ACCOUNTING: Creates journal entry when HMO claims are validated.
 * This recognizes revenue and creates AR-HMO that will be offset when 
 * HMO remits payment.
 *
 * On Validation Approved (HMO patient only):
 * DEBIT:  Accounts Receivable - HMO (1110) + HMO Sub-Account
 * CREDIT: Revenue by service type (4xxx)
 *
 * METADATA CAPTURED:
 * - product_id, service_id (the item/service rendered)
 * - product_category_id, service_category_id (for category filtering)
 * - hmo_id (for HMO-specific reports)
 * - patient_id (for patient transaction history)
 * - category (lab, pharmacy, imaging, etc.)
 */
class ProductOrServiceRequestObserver
{
    public function updated(ProductOrServiceRequest $request): void
    {
        // Only process HMO patients
        if (!$request->hmo_id) {
            return;
        }

        // Only when validation_status changes to approved
        if ($request->isDirty('validation_status') && $request->validation_status === 'approved') {
            try {
                $this->createHmoRevenueEntry($request);
            } catch (\Exception $e) {
                Log::error('Failed to create HMO revenue journal entry', [
                    'request_id' => $request->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    protected function createHmoRevenueEntry(ProductOrServiceRequest $request): void
    {
        // Only create entry if there's a claims_amount
        if (!$request->claims_amount || $request->claims_amount <= 0) {
            return;
        }

        $accountingService = App::make(AccountingService::class);

        $arHmo = Account::where('code', '1110')->first(); // AR - HMO
        $revenueAccount = Account::where('code', $this->getRevenueCode($request))->first();

        if (!$arHmo || !$revenueAccount) {
            Log::warning('HMO revenue entry skipped - accounts not configured', [
                'request_id' => $request->id
            ]);
            return;
        }

        // Get or create HMO sub-account for AR tracking
        $hmoSubAccount = $this->getOrCreateHmoSubAccount($arHmo, $request->hmo);
        
        // Get category for filtering
        $category = $this->getCategory($request);

        $description = $this->buildDescription($request);

        // Build lines WITH METADATA for granular filtering
        $lines = [
            [
                'account_id' => $arHmo->id,
                'sub_account_id' => $hmoSubAccount?->id,  // HMO sub-account
                'debit_amount' => $request->claims_amount,
                'credit_amount' => 0,
                'description' => "HMO Claim: {$request->hmo->name} - Patient: {$request->patient?->fullname}",
                // METADATA for drill-down
                'product_id' => $request->product_id,
                'service_id' => $request->service_id,
                'product_category_id' => $request->product?->category_id,
                'service_category_id' => $request->service?->category_id,
                'hmo_id' => $request->hmo_id,
                'patient_id' => $request->patient_id,
                'category' => $category,
            ],
            [
                'account_id' => $revenueAccount->id,
                'sub_account_id' => null,  // Could add service category sub-account
                'debit_amount' => 0,
                'credit_amount' => $request->claims_amount,
                'description' => $this->getServiceDescription($request),
                // SAME METADATA on revenue line
                'product_id' => $request->product_id,
                'service_id' => $request->service_id,
                'product_category_id' => $request->product?->category_id,
                'service_category_id' => $request->service?->category_id,
                'hmo_id' => $request->hmo_id,
                'patient_id' => $request->patient_id,
                'category' => $category,
            ]
        ];

        $accountingService->createAndPostAutomatedEntry(
            ProductOrServiceRequest::class,
            $request->id,
            $description,
            $lines
        );
    }

    /**
     * Get or create sub-account for HMO under AR-HMO (1110)
     */
    protected function getOrCreateHmoSubAccount(Account $arHmo, $hmo): ?AccountSubAccount
    {
        if (!$hmo) return null;
        
        return AccountSubAccount::firstOrCreate(
            ['account_id' => $arHmo->id, 'hmo_id' => $hmo->id],
            [
                'code' => "1110.HMO.{$hmo->id}",
                'name' => $hmo->name,
                'is_active' => true,
            ]
        );
    }

    /**
     * Get category for filtering (lab, pharmacy, imaging, etc.)
     */
    protected function getCategory(ProductOrServiceRequest $request): string
    {
        if ($request->product_id) {
            return 'pharmacy';
        }

        return match ($request->type ?? $request->service?->category ?? 'general') {
            'pharmacy', 'drug' => 'pharmacy',
            'lab', 'laboratory' => 'lab',
            'imaging', 'radiology' => 'imaging',
            'consultation' => 'consultation',
            'procedure' => 'procedure',
            'admission' => 'admission',
            default => 'general'
        };
    }

    protected function getRevenueCode(ProductOrServiceRequest $request): string
    {
        if ($request->product_id) {
            return '4020'; // Pharmacy Revenue
        }

        // Map service type to revenue account
        return match ($request->type ?? $request->service?->category ?? 'general') {
            'pharmacy', 'drug' => '4020',
            'lab', 'laboratory' => '4030',
            'imaging', 'radiology' => '4040',
            'consultation' => '4010',
            'procedure' => '4050',
            'admission' => '4060',
            default => '4000' // General Revenue
        };
    }

    protected function buildDescription(ProductOrServiceRequest $request): string
    {
        $parts = [
            "HMO Claim Validated",
            "HMO: " . ($request->hmo?->name ?? 'Unknown'),
            "Patient: " . ($request->patient?->fullname ?? 'Unknown'),
            "Amount: " . number_format($request->claims_amount, 2),
        ];

        if ($request->auth_code) {
            $parts[] = "Auth Code: {$request->auth_code}";
        }

        return implode(' | ', $parts);
    }

    protected function getServiceDescription(ProductOrServiceRequest $request): string
    {
        if ($request->product) {
            return "Pharmacy: {$request->product->name} x {$request->qty}";
        }
        if ($request->service) {
            return "Service: {$request->service->service_name}";
        }
        return "Service rendered";
    }
}
```

#### 7.2.2 HmoRemittanceObserver (NEW) ⭐ WITH GRANULAR METADATA
```php
<?php

namespace App\Observers\Accounting;

use App\Models\HmoRemittance;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubAccount;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * HMO Remittance Observer
 *
 * ACCRUAL ACCOUNTING: Creates journal entry when HMO remits payment.
 * This records cash received and offsets the AR-HMO that was created
 * when claims were validated.
 *
 * On Remittance Created:
 * DEBIT:  Bank Account (1020 or specific bank)
 * CREDIT: Accounts Receivable - HMO (1110) + HMO Sub-Account
 *
 * METADATA CAPTURED:
 * - hmo_id (for HMO-specific reports)
 * - category ('hmo_remittance')
 */
class HmoRemittanceObserver
{
    public function created(HmoRemittance $remittance): void
    {
        try {
            $this->createRemittanceJournalEntry($remittance);
        } catch (\Exception $e) {
            Log::error('Failed to create HMO remittance journal entry', [
                'remittance_id' => $remittance->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function createRemittanceJournalEntry(HmoRemittance $remittance): void
    {
        $accountingService = App::make(AccountingService::class);

        // Get bank account - prefer specific, fallback to generic
        $bankAccount = $this->getBankAccount($remittance);
        $arHmo = Account::where('code', '1110')->first(); // AR - HMO

        if (!$bankAccount || !$arHmo) {
            Log::warning('HMO remittance entry skipped - accounts not configured', [
                'remittance_id' => $remittance->id
            ]);
            return;
        }

        // Get or create HMO sub-account for AR tracking
        $hmoSubAccount = $this->getOrCreateHmoSubAccount($arHmo, $remittance->hmo);

        $description = $this->buildDescription($remittance);

        // Build lines WITH METADATA
        $lines = [
            [
                'account_id' => $bankAccount->id,
                'sub_account_id' => null,
                'debit_amount' => $remittance->amount,
                'credit_amount' => 0,
                'description' => "HMO Remittance received: {$remittance->hmo?->name}",
                // METADATA
                'hmo_id' => $remittance->hmo_id,
                'category' => 'hmo_remittance',
            ],
            [
                'account_id' => $arHmo->id,
                'sub_account_id' => $hmoSubAccount?->id,  // HMO sub-account
                'debit_amount' => 0,
                'credit_amount' => $remittance->amount,
                'description' => "Claims settled - Period: {$remittance->period_from} to {$remittance->period_to}",
                // METADATA
                'hmo_id' => $remittance->hmo_id,
                'category' => 'hmo_remittance',
            ]
        ];

        $entry = $accountingService->createAndPostAutomatedEntry(
            HmoRemittance::class,
            $remittance->id,
            $description,
            $lines
        );

        // Link journal entry back
        $remittance->journal_entry_id = $entry->id;
        $remittance->saveQuietly();
    }

    protected function getBankAccount(HmoRemittance $remittance): ?Account
    {
        // If specific account_id set, use it
        if ($remittance->account_id) {
            return Account::find($remittance->account_id);
        }

        // If bank_id set, find its linked account
        if ($remittance->bank_id && $remittance->bank?->account_id) {
            return Account::find($remittance->bank->account_id);
        }

        // Fallback to generic bank
        return Account::where('code', '1020')->first();
    }

    protected function buildDescription(HmoRemittance $remittance): string
    {
        $parts = [
            "HMO Remittance Received",
            "HMO: " . ($remittance->hmo?->name ?? 'Unknown'),
            "Amount: " . number_format($remittance->amount, 2),
            "Period: {$remittance->period_from} to {$remittance->period_to}",
        ];

        if ($remittance->reference_number) {
            $parts[] = "Ref: {$remittance->reference_number}";
        }

        return implode(' | ', $parts);
    }

    /**
     * Get or create sub-account for HMO under AR-HMO (1110)
     */
    protected function getOrCreateHmoSubAccount(Account $arHmo, $hmo): ?AccountSubAccount
    {
        if (!$hmo) return null;
        
        return AccountSubAccount::firstOrCreate(
            ['account_id' => $arHmo->id, 'hmo_id' => $hmo->id],
            [
                'code' => "1110.HMO.{$hmo->id}",
                'name' => $hmo->name,
                'is_active' => true,
            ]
        );
    }
}
```

#### 7.2.3 PurchaseOrderPaymentObserver (NEW) ⭐ WITH GRANULAR METADATA
```php
<?php

namespace App\Observers\Accounting;

use App\Models\PurchaseOrderPayment;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubAccount;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Purchase Order Payment Observer
 *
 * ACCRUAL ACCOUNTING: Creates journal entry when supplier is paid.
 * This offsets the AP that was created when PO was received.
 *
 * On Payment Created:
 * DEBIT:  Accounts Payable (2100) + Supplier Sub-Account
 * CREDIT: Cash/Bank (1010/1020)
 *
 * METADATA CAPTURED:
 * - supplier_id (for supplier-specific reports)
 * - category ('po_payment')
 */
class PurchaseOrderPaymentObserver
{
    public function created(PurchaseOrderPayment $payment): void
    {
        try {
            $this->createPaymentJournalEntry($payment);
        } catch (\Exception $e) {
            Log::error('Failed to create PO payment journal entry', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    protected function createPaymentJournalEntry(PurchaseOrderPayment $payment): void
    {
        $accountingService = App::make(AccountingService::class);

        $apAccount = Account::where('code', '2100')->first(); // Accounts Payable
        $cashBankAccount = $this->getCashBankAccount($payment);

        if (!$apAccount || !$cashBankAccount) {
            Log::warning('PO payment entry skipped - accounts not configured', [
                'payment_id' => $payment->id
            ]);
            return;
        }

        $po = $payment->purchaseOrder;
        
        // Get or create Supplier sub-account for AP tracking
        $supplierSubAccount = $this->getOrCreateSupplierSubAccount($apAccount, $po->supplier);
        
        $description = $this->buildDescription($payment, $po);

        // Build lines WITH METADATA
        $lines = [
            [
                'account_id' => $apAccount->id,
                'sub_account_id' => $supplierSubAccount?->id,  // Supplier sub-account
                'debit_amount' => $payment->amount,
                'credit_amount' => 0,
                'description' => "AP cleared: PO {$po->po_number} - {$po->supplier?->name}",
                // METADATA
                'supplier_id' => $po->supplier_id,
                'category' => 'po_payment',
            ],
            [
                'account_id' => $cashBankAccount->id,
                'sub_account_id' => null,
                'debit_amount' => 0,
                'credit_amount' => $payment->amount,
                'description' => "Supplier payment via {$payment->payment_method}",
                // METADATA
                'supplier_id' => $po->supplier_id,
                'category' => 'po_payment',
            ]
        ];

        $entry = $accountingService->createAndPostAutomatedEntry(
            PurchaseOrderPayment::class,
            $payment->id,
            $description,
            $lines
        );

        // Link journal entry back
        $payment->journal_entry_id = $entry->id;
        $payment->saveQuietly();
    }

    /**
     * Get or create sub-account for Supplier under AP (2100)
     */
    protected function getOrCreateSupplierSubAccount(Account $apAccount, $supplier): ?AccountSubAccount
    {
        if (!$supplier) return null;
        
        return AccountSubAccount::firstOrCreate(
            ['account_id' => $apAccount->id, 'supplier_id' => $supplier->id],
            [
                'code' => "2100.SUP.{$supplier->id}",
                'name' => $supplier->name,
                'is_active' => true,
            ]
        );
    }

    protected function getCashBankAccount(PurchaseOrderPayment $payment): ?Account
    {
        // If specific account_id set, use it
        if ($payment->account_id) {
            return Account::find($payment->account_id);
        }

        // If bank_id set, find its linked account
        if ($payment->bank_id && $payment->bank?->account_id) {
            return Account::find($payment->bank->account_id);
        }

        // Map by payment method
        $code = match ($payment->payment_method ?? 'cash') {
            'cash' => '1010',
            'bank_transfer', 'bank', 'transfer' => '1020',
            'cheque', 'check' => '1020',
            default => '1010'
        };

        return Account::where('code', $code)->first();
    }

    protected function buildDescription(PurchaseOrderPayment $payment, $po): string
    {
        $parts = [
            "Supplier Payment",
            "PO: {$po->po_number}",
            "Supplier: " . ($po->supplier?->name ?? 'Unknown'),
            "Amount: " . number_format($payment->amount, 2),
            "Method: {$payment->payment_method}",
        ];

        if ($payment->reference_number) {
            $parts[] = "Ref: {$payment->reference_number}";
        }

        return implode(' | ', $parts);
    }
}
```

### Phase 3: Update Existing Observers

#### 7.3.1 PayrollBatchObserver (FIX - Two Stage)
```php
<?php

namespace App\Observers\Accounting;

use App\Models\HR\PayrollBatch;
use App\Models\Accounting\Account;
use App\Services\Accounting\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * Payroll Batch Observer (UPDATED for Accrual Accounting + Metadata)
 *
 * TWO-STAGE ACCRUAL ENTRIES:
 * 
 * Stage 1 - When APPROVED:
 * DEBIT:  Salaries & Wages Expense (6040)
 * CREDIT: Salaries Payable (2050)
 *
 * Stage 2 - When PAID:
 * DEBIT:  Salaries Payable (2050)
 * CREDIT: Bank Account (1020 or selected)
 *
 * METADATA CAPTURED:
 * - department_id: Associated department (if departmental payroll)
 * - category: 'payroll_expense' or 'payroll_payment'
 */
class PayrollBatchObserver
{
    public function updated(PayrollBatch $batch): void
    {
        if (!$batch->isDirty('status')) {
            return;
        }

        try {
            // Stage 1: Approved - Recognize expense and liability
            if ($batch->status === PayrollBatch::STATUS_APPROVED) {
                $this->createExpenseRecognitionEntry($batch);
            }
            
            // Stage 2: Paid - Clear liability and reduce bank
            if ($batch->status === PayrollBatch::STATUS_PAID) {
                $this->createPaymentEntry($batch);
            }
        } catch (\Exception $e) {
            Log::error('Failed to create payroll journal entry', [
                'batch_id' => $batch->id,
                'status' => $batch->status,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Stage 1: Recognize salary expense and create liability
     */
    protected function createExpenseRecognitionEntry(PayrollBatch $batch): void
    {
        $accountingService = App::make(AccountingService::class);

        $salaryExpense = Account::where('code', '6040')->first(); // Salaries & Wages
        $salaryPayable = Account::where('code', '2050')->first(); // Salaries Payable

        if (!$salaryExpense || !$salaryPayable) {
            Log::warning('Payroll expense entry skipped - accounts not configured', [
                'batch_id' => $batch->id
            ]);
            return;
        }

        $lines = [
            [
                'account_id' => $salaryExpense->id,
                'debit_amount' => $batch->total_gross,
                'credit_amount' => 0,
                'description' => "Salary expense: {$batch->name} ({$batch->total_staff} staff)",
                // METADATA
                'department_id' => $batch->department_id,
                'category' => 'payroll_expense',
            ],
            [
                'account_id' => $salaryPayable->id,
                'debit_amount' => 0,
                'credit_amount' => $batch->total_gross,
                'description' => "Salary liability: {$batch->pay_period_start->format('M d')} - {$batch->pay_period_end->format('M d, Y')}",
                // METADATA
                'department_id' => $batch->department_id,
                'category' => 'payroll_expense',
            ]
        ];

        $accountingService->createAndPostAutomatedEntry(
            PayrollBatch::class,
            $batch->id,
            "Payroll Expense Recognition: {$batch->name} | Period: {$batch->pay_period_start->format('M d')} - {$batch->pay_period_end->format('M d, Y')} | Staff: {$batch->total_staff} | Gross: " . number_format($batch->total_gross, 2),
            $lines
        );
    }

    /**
     * Stage 2: Clear liability and pay from bank
     */
    protected function createPaymentEntry(PayrollBatch $batch): void
    {
        $accountingService = App::make(AccountingService::class);

        $salaryPayable = Account::where('code', '2050')->first(); // Salaries Payable
        $bankAccount = $this->getBankAccount($batch);

        if (!$salaryPayable || !$bankAccount) {
            Log::warning('Payroll payment entry skipped - accounts not configured', [
                'batch_id' => $batch->id
            ]);
            return;
        }

        $lines = [
            [
                'account_id' => $salaryPayable->id,
                'debit_amount' => $batch->total_net,
                'credit_amount' => 0,
                'description' => "Salary liability cleared: {$batch->name}",
                // METADATA
                'department_id' => $batch->department_id,
                'category' => 'payroll_payment',
            ],
            [
                'account_id' => $bankAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $batch->total_net,
                'description' => "Net salary payment to {$batch->total_staff} staff via bank",
                // METADATA
                'department_id' => $batch->department_id,
                'category' => 'payroll_payment',
            ]
        ];

        $entry = $accountingService->createAndPostAutomatedEntry(
            PayrollBatch::class,
            $batch->id,
            "Payroll Payment: {$batch->name} | Net: " . number_format($batch->total_net, 2) . " | Staff: {$batch->total_staff}",
            $lines
        );

        // Link journal entry
        $batch->journal_entry_id = $entry->id;
        $batch->saveQuietly();
    }

    protected function getBankAccount(PayrollBatch $batch): ?Account
    {
        // If specific account_id set, use it
        if ($batch->account_id) {
            return Account::find($batch->account_id);
        }

        // If bank_id set, find its linked account
        if ($batch->bank_id && $batch->bank?->account_id) {
            return Account::find($batch->bank->account_id);
        }

        // Map by payment method
        $code = match ($batch->payment_method ?? 'bank_transfer') {
            'cash' => '1010',
            'bank_transfer' => '1020',
            default => '1020'
        };

        return Account::where('code', $code)->first();
    }
}
```

#### 7.3.2 PaymentObserver (UPDATE - Add Metadata)

The existing `PaymentObserver` needs to be updated to include metadata fields for granular filtering:

```php
<?php
// app/Observers/Accounting/PaymentObserver.php
// UPDATED: Add metadata to journal entry lines

/**
 * Payment Observer (UPDATED with Metadata)
 *
 * METADATA CAPTURED:
 * - product_id: If payment linked to product (pharmacy)
 * - service_id: If payment linked to service (consultation, lab, etc.)
 * - product_category_id: Product's category
 * - service_category_id: Service's category  
 * - hmo_id: If HMO patient payment
 * - patient_id: Always populated
 * - category: Payment type (consultation, pharmacy, lab, etc.)
 */
class PaymentObserver
{
    // ... existing code ...

    protected function createPaymentJournalEntry(Payment $payment): void
    {
        $accountingService = App::make(AccountingService::class);

        $cashBankAccount = $this->getCashBankAccount($payment);
        $revenueAccount = $this->getRevenueAccount($payment);

        if (!$cashBankAccount || !$revenueAccount) {
            return;
        }

        // Extract metadata from payment context
        $metadata = $this->extractPaymentMetadata($payment);

        $lines = [
            [
                'account_id' => $cashBankAccount->id,
                'debit_amount' => $payment->amount,
                'credit_amount' => 0,
                'description' => "Cash receipt: {$payment->receipt_number}",
                // METADATA
                'product_id' => $metadata['product_id'],
                'service_id' => $metadata['service_id'],
                'product_category_id' => $metadata['product_category_id'],
                'service_category_id' => $metadata['service_category_id'],
                'hmo_id' => $metadata['hmo_id'],
                'patient_id' => $payment->patient_id,
                'category' => $payment->payment_type ?? 'general',
            ],
            [
                'account_id' => $revenueAccount->id,
                'sub_account_id' => $metadata['sub_account_id'],
                'debit_amount' => 0,
                'credit_amount' => $payment->amount,
                'description' => "Revenue: {$payment->payment_type}",
                // METADATA
                'product_id' => $metadata['product_id'],
                'service_id' => $metadata['service_id'],
                'product_category_id' => $metadata['product_category_id'],
                'service_category_id' => $metadata['service_category_id'],
                'hmo_id' => $metadata['hmo_id'],
                'patient_id' => $payment->patient_id,
                'category' => $payment->payment_type ?? 'general',
            ]
        ];

        // ... rest of method ...
    }

    /**
     * Extract metadata from payment context
     */
    protected function extractPaymentMetadata(Payment $payment): array
    {
        $metadata = [
            'product_id' => null,
            'service_id' => null,
            'product_category_id' => null,
            'service_category_id' => null,
            'hmo_id' => null,
            'sub_account_id' => null,
        ];

        // If linked to encounter, get patient's HMO
        if ($payment->encounter_id && $payment->encounter?->patient?->hmo_id) {
            $metadata['hmo_id'] = $payment->encounter->patient->hmo_id;
        }

        // If linked to product_or_service_requests (many payments are)
        if ($payment->product_or_service_request_id) {
            $psr = ProductOrServiceRequest::find($payment->product_or_service_request_id);
            if ($psr) {
                $metadata['product_id'] = $psr->product_id;
                $metadata['service_id'] = $psr->service_id;
                $metadata['product_category_id'] = $psr->product?->product_category_id;
                $metadata['service_category_id'] = $psr->service?->service_category_id;
            }
        }

        // If pharmacy payment, try to link to product
        if ($payment->payment_type === 'pharmacy' && $payment->payable_type === Prescription::class) {
            $prescription = $payment->payable;
            if ($prescription && $prescription->items->first()) {
                $firstItem = $prescription->items->first();
                $metadata['product_id'] = $firstItem->product_id;
                $metadata['product_category_id'] = $firstItem->product?->product_category_id;
            }
        }

        return $metadata;
    }
}
```

#### 7.3.3 ExpenseObserver (UPDATE - Add Metadata + Sub-Account)

The existing `ExpenseObserver` needs to be updated to include metadata and supplier sub-accounts:

```php
<?php
// app/Observers/Accounting/ExpenseObserver.php
// UPDATED: Add metadata and supplier sub-account

/**
 * Expense Observer (UPDATED with Metadata)
 *
 * METADATA CAPTURED:
 * - supplier_id: Always if expense has supplier
 * - category: Expense category (utilities, maintenance, etc.)
 *
 * SUB-ACCOUNT: Creates/uses supplier sub-account under AP (2100)
 */
class ExpenseObserver
{
    // ... existing code ...

    protected function createExpenseJournalEntry(Expense $expense): void
    {
        $accountingService = App::make(AccountingService::class);

        $expenseAccount = $this->getExpenseAccount($expense);
        $creditAccount = $this->getCreditAccount($expense);

        if (!$expenseAccount || !$creditAccount) {
            return;
        }

        // Get or create supplier sub-account if AP
        $supplierSubAccount = null;
        if ($expense->supplier_id && $creditAccount->code === '2100') {
            $supplierSubAccount = $this->getOrCreateSupplierSubAccount($creditAccount, $expense->supplier);
        }

        $lines = [
            [
                'account_id' => $expenseAccount->id,
                'debit_amount' => $expense->amount,
                'credit_amount' => 0,
                'description' => "Expense: {$expense->description}",
                // METADATA
                'supplier_id' => $expense->supplier_id,
                'category' => $expense->expense_category ?? 'general_expense',
            ],
            [
                'account_id' => $creditAccount->id,
                'sub_account_id' => $supplierSubAccount?->id,  // Supplier sub-account if AP
                'debit_amount' => 0,
                'credit_amount' => $expense->amount,
                'description' => $expense->supplier_id 
                    ? "AP: {$expense->supplier?->name}" 
                    : "Payment for expense",
                // METADATA
                'supplier_id' => $expense->supplier_id,
                'category' => $expense->expense_category ?? 'general_expense',
            ]
        ];

        // ... rest of method ...
    }

    /**
     * Get or create sub-account for Supplier under AP (2100)
     */
    protected function getOrCreateSupplierSubAccount(Account $apAccount, $supplier): ?AccountSubAccount
    {
        if (!$supplier) return null;
        
        return AccountSubAccount::firstOrCreate(
            ['account_id' => $apAccount->id, 'supplier_id' => $supplier->id],
            [
                'code' => "2100.SUP.{$supplier->id}",
                'name' => $supplier->name,
                'is_active' => true,
            ]
        );
    }
}
```

#### 7.3.4 PurchaseOrderObserver (UPDATE - Add Metadata + Sub-Account)

The existing `PurchaseOrderObserver` needs to be updated for metadata on inventory receipt:

```php
<?php
// app/Observers/Accounting/PurchaseOrderObserver.php
// UPDATED: Add metadata and supplier sub-account

/**
 * Purchase Order Observer (UPDATED with Metadata)
 *
 * METADATA CAPTURED:
 * - product_id: Per line item
 * - product_category_id: Product's category
 * - supplier_id: Always populated
 * - category: 'inventory_receipt'
 *
 * SUB-ACCOUNT: Creates/uses supplier sub-account under AP (2100)
 */
class PurchaseOrderObserver
{
    protected function createReceiptJournalEntry(PurchaseOrder $po): void
    {
        $accountingService = App::make(AccountingService::class);

        $inventoryAccount = Account::where('code', '1300')->first();
        $apAccount = Account::where('code', '2100')->first();

        if (!$inventoryAccount || !$apAccount) {
            return;
        }

        // Get or create supplier sub-account
        $supplierSubAccount = $this->getOrCreateSupplierSubAccount($apAccount, $po->supplier);

        $lines = [];

        // Option A: Single line for whole PO
        $lines = [
            [
                'account_id' => $inventoryAccount->id,
                'debit_amount' => $po->total_amount,
                'credit_amount' => 0,
                'description' => "Inventory received: PO {$po->po_number}",
                // METADATA
                'supplier_id' => $po->supplier_id,
                'category' => 'inventory_receipt',
            ],
            [
                'account_id' => $apAccount->id,
                'sub_account_id' => $supplierSubAccount?->id,
                'debit_amount' => 0,
                'credit_amount' => $po->total_amount,
                'description' => "AP created: {$po->supplier?->name}",
                // METADATA
                'supplier_id' => $po->supplier_id,
                'category' => 'inventory_receipt',
            ]
        ];

        // Option B: Separate line per product (more granular)
        // Uncomment if you want product-level tracking on inventory entries
        /*
        foreach ($po->items as $item) {
            $lines[] = [
                'account_id' => $inventoryAccount->id,
                'debit_amount' => $item->total_price,
                'credit_amount' => 0,
                'description' => "Inventory: {$item->product?->name}",
                // METADATA
                'product_id' => $item->product_id,
                'product_category_id' => $item->product?->product_category_id,
                'supplier_id' => $po->supplier_id,
                'category' => 'inventory_receipt',
            ];
        }
        // Single AP line for total
        $lines[] = [
            'account_id' => $apAccount->id,
            'sub_account_id' => $supplierSubAccount?->id,
            'debit_amount' => 0,
            'credit_amount' => $po->total_amount,
            'description' => "AP: {$po->supplier?->name}",
            'supplier_id' => $po->supplier_id,
            'category' => 'inventory_receipt',
        ];
        */

        $accountingService->createAndPostAutomatedEntry(
            PurchaseOrder::class,
            $po->id,
            "Inventory Receipt: PO {$po->po_number} | Supplier: {$po->supplier?->name}",
            $lines
        );
    }

    /**
     * Get or create sub-account for Supplier under AP (2100)
     */
    protected function getOrCreateSupplierSubAccount(Account $apAccount, $supplier): ?AccountSubAccount
    {
        if (!$supplier) return null;
        
        return AccountSubAccount::firstOrCreate(
            ['account_id' => $apAccount->id, 'supplier_id' => $supplier->id],
            [
                'code' => "2100.SUP.{$supplier->id}",
                'name' => $supplier->name,
                'is_active' => true,
            ]
        );
    }
}
```

### Phase 4: Register Observers

#### 7.4.1 Update AppServiceProvider
```php
// app/Providers/AppServiceProvider.php

use App\Models\ProductOrServiceRequest;
use App\Models\HmoRemittance;
use App\Models\PurchaseOrderPayment;
use App\Models\Bank;
use App\Observers\Accounting\ProductOrServiceRequestObserver;
use App\Observers\Accounting\HmoRemittanceObserver;
use App\Observers\Accounting\PurchaseOrderPaymentObserver;
use App\Observers\BankObserver;

public function boot()
{
    // ... existing observers ...

    // NEW: HMO Revenue Recognition (Accrual)
    ProductOrServiceRequest::observe(ProductOrServiceRequestObserver::class);
    
    // NEW: HMO Remittance (AR Offset)
    HmoRemittance::observe(HmoRemittanceObserver::class);
    
    // NEW: PO Payment (AP Offset)
    PurchaseOrderPayment::observe(PurchaseOrderPaymentObserver::class);
    
    // NEW: Bank GL Account Creation
    Bank::observe(BankObserver::class);
}
```

### Phase 5: SubAccountService Helper ⭐ NEW

Create a reusable service for consistent sub-account management across all observers:

#### 7.5.1 SubAccountService
```php
<?php
// app/Services/Accounting/SubAccountService.php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubAccount;

/**
 * SubAccountService
 * 
 * Centralized helper for creating and retrieving sub-accounts.
 * Used by observers to ensure consistent sub-account management.
 */
class SubAccountService
{
    /**
     * Get or create HMO sub-account under AR-HMO (1110)
     */
    public function getOrCreateHmoSubAccount($hmo): ?AccountSubAccount
    {
        if (!$hmo) return null;
        
        $arHmo = Account::where('code', '1110')->first();
        if (!$arHmo) return null;
        
        return AccountSubAccount::firstOrCreate(
            ['account_id' => $arHmo->id, 'hmo_id' => $hmo->id],
            [
                'code' => "1110.HMO.{$hmo->id}",
                'name' => $hmo->name ?? $hmo->company_name,
                'is_active' => true,
            ]
        );
    }

    /**
     * Get or create Supplier sub-account under AP (2100)
     */
    public function getOrCreateSupplierSubAccount($supplier): ?AccountSubAccount
    {
        if (!$supplier) return null;
        
        $ap = Account::where('code', '2100')->first();
        if (!$ap) return null;
        
        return AccountSubAccount::firstOrCreate(
            ['account_id' => $ap->id, 'supplier_id' => $supplier->id],
            [
                'code' => "2100.SUP.{$supplier->id}",
                'name' => $supplier->name ?? $supplier->company_name,
                'is_active' => true,
            ]
        );
    }

    /**
     * Get or create Patient sub-account under AR (1200)
     */
    public function getOrCreatePatientSubAccount($patient): ?AccountSubAccount
    {
        if (!$patient) return null;
        
        $ar = Account::where('code', '1200')->first();
        if (!$ar) return null;
        
        return AccountSubAccount::firstOrCreate(
            ['account_id' => $ar->id, 'patient_id' => $patient->id],
            [
                'code' => "1200.PAT.{$patient->id}",
                'name' => $patient->full_name ?? "{$patient->first_name} {$patient->last_name}",
                'is_active' => true,
            ]
        );
    }

    /**
     * Get or create Product sub-account under Inventory (1300)
     */
    public function getOrCreateProductSubAccount(Account $account, $product): ?AccountSubAccount
    {
        if (!$product) return null;
        
        return AccountSubAccount::firstOrCreate(
            ['account_id' => $account->id, 'product_id' => $product->id],
            [
                'code' => "{$account->code}.PRD.{$product->id}",
                'name' => $product->name,
                'is_active' => true,
            ]
        );
    }

    /**
     * Get or create Service sub-account under Revenue (4xxx)
     */
    public function getOrCreateServiceSubAccount(Account $account, $service): ?AccountSubAccount
    {
        if (!$service) return null;
        
        return AccountSubAccount::firstOrCreate(
            ['account_id' => $account->id, 'service_id' => $service->id],
            [
                'code' => "{$account->code}.SVC.{$service->id}",
                'name' => $service->name,
                'is_active' => true,
            ]
        );
    }
}
```

#### 7.5.2 Using SubAccountService in Observers
```php
// In any observer
use App\Services\Accounting\SubAccountService;

class SomeObserver
{
    protected SubAccountService $subAccountService;
    
    public function __construct(SubAccountService $subAccountService)
    {
        $this->subAccountService = $subAccountService;
    }

    protected function createEntry($model): void
    {
        // Get sub-account
        $hmoSubAccount = $this->subAccountService->getOrCreateHmoSubAccount($model->hmo);
        
        $lines = [
            [
                'account_id' => $arHmo->id,
                'sub_account_id' => $hmoSubAccount?->id,  // Use the sub-account
                // ... other fields
            ]
        ];
    }
}
```

---

## Part 8: Testing & Debugging Guide

### 8.1 Debugging Journal Entry Issues

**Step 1: Check if Observer is Registered**
```php
// In tinker
$dispatcher = App\Models\Payment::getEventDispatcher();
dump($dispatcher->getListeners('eloquent.created: App\Models\Payment'));
```

**Step 2: Check if Entry was Created**
```sql
-- Find journal entries for a specific transaction
SELECT * FROM journal_entries 
WHERE reference_type = 'App\\Models\\Payment' 
  AND reference_id = 123;
```

**Step 3: Check Entry Lines**
```sql
-- Check the journal entry lines
SELECT jel.*, a.code, a.name 
FROM journal_entry_lines jel
JOIN accounts a ON jel.account_id = a.id
WHERE jel.journal_entry_id = 456;
```

**Step 4: Verify Balance**
```sql
-- Verify debits = credits for an entry
SELECT 
    SUM(debit_amount) as total_debits,
    SUM(credit_amount) as total_credits
FROM journal_entry_lines
WHERE journal_entry_id = 456;
```

### 8.2 Common Issues & Solutions

| Issue | Likely Cause | Solution |
|-------|--------------|----------|
| No journal entry created | Observer not registered | Check AppServiceProvider |
| Entry created but wrong account | Account code mapping wrong | Check observer's account mapping logic |
| Entry not showing in reports | Entry status not 'posted' | Check AccountingService posts immediately |
| AR/AP balance incorrect | Missing offset entry | Check if settlement observer exists |
| Duplicate entries | Observer firing multiple times | Add `saveQuietly()` after linking journal_entry_id |

### 8.3 Test Scenarios

#### Test 1: HMO Claim Validation
1. Create ProductOrServiceRequest for HMO patient
2. Update validation_status to 'approved'
3. Verify: Journal entry created with DR 1110, CR 4xxx
4. Verify: AR-HMO report shows the receivable

#### Test 2: HMO Remittance
1. Create HmoRemittance record
2. Verify: Journal entry created with DR 1020, CR 1110
3. Verify: AR-HMO reduced, Bank increased

#### Test 3: PO Receipt + Payment
1. Mark PO as received → Check DR 1300, CR 2100 (AP created)
2. Create PurchaseOrderPayment → Check DR 2100, CR 1020 (AP cleared)
3. Verify: AP report shows zero balance for this PO

#### Test 4: Payroll Two-Stage
1. Create PayrollBatch → No entry yet
2. Approve batch → Check DR 6040, CR 2050 (Liability created)
3. Mark as paid → Check DR 2050, CR 1020 (Liability cleared)
4. Verify: AP shows Salaries Payable = 0

---

## Part 9: Bank Statement Report Implementation

### 9.1 Controller Method
```php
// app/Http/Controllers/Accounting/ReportsController.php

public function bankStatement(Request $request)
{
    $request->validate([
        'account_id' => 'required|exists:accounts,id',
        'from_date' => 'required|date',
        'to_date' => 'required|date|after_or_equal:from_date',
    ]);

    $account = Account::with('bank')->findOrFail($request->account_id);
    $fromDate = Carbon::parse($request->from_date);
    $toDate = Carbon::parse($request->to_date);

    // Opening balance (balance before from_date)
    $openingBalance = JournalEntryLine::whereHas('journalEntry', function($q) use ($fromDate) {
            $q->where('status', 'posted')
              ->where('entry_date', '<', $fromDate);
        })
        ->where('account_id', $account->id)
        ->selectRaw('SUM(debit_amount) - SUM(credit_amount) as balance')
        ->value('balance') ?? 0;

    // Transactions in period
    $transactions = JournalEntryLine::with('journalEntry')
        ->whereHas('journalEntry', function($q) use ($fromDate, $toDate) {
            $q->where('status', 'posted')
              ->whereBetween('entry_date', [$fromDate, $toDate]);
        })
        ->where('account_id', $account->id)
        ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
        ->orderBy('journal_entries.entry_date')
        ->orderBy('journal_entries.id')
        ->select('journal_entry_lines.*')
        ->get();

    // Calculate running balance
    $runningBalance = $openingBalance;
    $transactionData = $transactions->map(function($line) use (&$runningBalance) {
        $runningBalance += ($line->debit_amount - $line->credit_amount);
        return [
            'date' => $line->journalEntry->entry_date,
            'reference' => $line->journalEntry->entry_number,
            'description' => $line->narration ?? $line->journalEntry->description,
            'source_type' => class_basename($line->journalEntry->reference_type ?? 'Manual'),
            'source_id' => $line->journalEntry->reference_id,
            'debit' => $line->debit_amount,
            'credit' => $line->credit_amount,
            'balance' => $runningBalance,
        ];
    });

    $closingBalance = $runningBalance;

    return view('accounting.reports.bank-statement', compact(
        'account', 'transactionData', 'openingBalance', 'closingBalance', 
        'fromDate', 'toDate'
    ));
}
```

### 9.2 Routes
```php
// routes/web.php (within accounting routes group)

Route::get('/reports/bank-statement', [ReportsController::class, 'bankStatement'])
     ->name('accounting.reports.bank-statement');
Route::get('/reports/cash-position', [ReportsController::class, 'cashPosition'])
     ->name('accounting.reports.cash-position');
Route::get('/reports/ar-aging', [ReportsController::class, 'arAging'])
     ->name('accounting.reports.ar-aging');
Route::get('/reports/ap-aging', [ReportsController::class, 'apAging'])
     ->name('accounting.reports.ap-aging');
```

---

## Part 10: Priority Implementation Order

| Priority | Task | Effort | Impact | Dependencies |
|----------|------|--------|--------|--------------|
| **1** | Create Account 2050 (Salaries Payable) | 5 min | High | None |
| **2** | Create `ProductOrServiceRequestObserver` | 2 hrs | Critical | None |
| **3** | Create `HmoRemittanceObserver` | 1 hr | Critical | Task 2 |
| **4** | Create `PurchaseOrderPaymentObserver` | 1 hr | High | None |
| **5** | Update `PayrollBatchObserver` (two-stage) | 1 hr | High | Task 1 |
| **6** | Register all observers in AppServiceProvider | 15 min | Critical | Tasks 2-5 |
| **7** | Migration: Add account_id columns | 30 min | Medium | None |
| **8** | Create `BankObserver` | 1 hr | Medium | Task 7 |
| **9** | Update forms for bank/account selection | 2 hrs | Medium | Task 8 |
| **10** | Create Bank Statement Report | 2 hrs | High | All above |
| **11** | Create AR/AP Reports from journal | 2 hrs | High | All above |

---

## Part 11: Summary

### Files to Create
1. `app/Observers/Accounting/ProductOrServiceRequestObserver.php` - HMO revenue recognition
2. `app/Observers/Accounting/HmoRemittanceObserver.php` - HMO payment receipt
3. `app/Observers/Accounting/PurchaseOrderPaymentObserver.php` - Supplier payment
4. `app/Observers/BankObserver.php` - Auto-create bank GL accounts
5. `database/migrations/xxxx_add_accounting_columns.php` - Schema updates
6. `resources/views/accounting/reports/bank-statement.blade.php` - Report view

### Files to Modify
1. `app/Observers/Accounting/PayrollBatchObserver.php` - Two-stage entries
2. `app/Providers/AppServiceProvider.php` - Register new observers
3. `app/Http/Controllers/Accounting/ReportsController.php` - Add report methods
4. Various models to add `account_id` to fillable

### Key Accounting Entries Reference

| Transaction | Stage 1 (Recognition) | Stage 2 (Settlement) |
|------------|----------------------|---------------------|
| **HMO Service** | DR AR-HMO(1110) CR Revenue(4xxx) | DR Bank(1020) CR AR-HMO(1110) |
| **PO Receipt** | DR Inventory(1300) CR AP(2100) | DR AP(2100) CR Bank(1020) |
| **Payroll** | DR Expense(6040) CR Payable(2050) | DR Payable(2050) CR Bank(1020) |
| **Expense (credit)** | DR Expense(6xxx) CR AP(2100) | DR AP(2100) CR Bank(1020) |
| **Cash Payment** | DR Cash/Bank(1010/1020) CR Revenue(4xxx) | N/A (single stage) |

---

## Approval Required

Please confirm:
1. ✅ Create missing observers as documented
2. ✅ Update PayrollBatchObserver to two-stage accrual
3. ✅ All reports derive from journal entries only
4. ✅ Implementation priority order
