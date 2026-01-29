```markdown
# CoreHealth V2 - Double-Entry Accounting Ledger System Plan

**Created:** January 29, 2026  
**Status:** Planning Complete  
**Version:** 1.0

---

## Executive Summary

Implement a comprehensive double-entry accounting module where **the Journal Entry is the single source of truth** for all financial data. Every report traces back to journal entries with drill-down breadcrumb navigation, bulk actions, edit approval workflows with **in-app group chat notifications via an "Accounts Staff" group**.

---

## Table of Contents

1. [Core Architectural Principles](#1-core-architectural-principles)
2. [Current System Analysis](#2-current-system-analysis)
3. [Database Schema Design](#3-database-schema-design)
4. [Models & Services](#4-models--services)
5. [Notification System](#5-notification-system)
6. [Observers for Automated Journaling](#6-observers-for-automated-journaling)
7. [Controllers & Routes](#7-controllers--routes)
8. [Views & UI Components](#8-views--ui-components)
9. [Reports with Drill-Down](#9-reports-with-drill-down)
10. [JavaScript Modules](#10-javascript-modules)
11. [Seeders & Permissions](#11-seeders--permissions)
12. [Implementation Phases](#12-implementation-phases)

---

## 1. Core Architectural Principles

### 1.1 Journal Entry as Foundation

All financial reports are aggregations of journal entry lines. Every number in every report is clickable, opening the Journal List pre-filtered with breadcrumb navigation showing the drill-down path.

**Key Rules:**
- All balances are calculated from `journal_entry_lines` for posted entries only
- No balance is stored separately — always computed from journal entries
- Every transaction creates a journal entry with `reference_type`/`reference_id` linking to source
- Source records store `journal_entry_id` for bidirectional traceability

### 1.2 Approval Workflow

- **Auto-generated entries** (from payments, POs, payroll) are automatically posted
- **Manual entries** require approval workflow: Draft → Pending Approval → Approved → Posted
- **Edits to posted entries** create edit requests requiring approval
- **Reversals** create contra entries, never delete original entries

### 1.3 Chart of Accounts Hierarchy

```
Account Class (ASSET, LIABILITY, EQUITY, INCOME, EXPENSE)
    └── Account Group (Current Assets, Operating Revenue, etc.)
        └── Account (Cash on Hand, Zenith Bank, Sales Revenue, etc.)
            └── Sub-Account (Patient: John Doe, Service: Consultation, etc.)
```

---

## 2. Current System Analysis

### 2.1 Existing Financial Models

| Model | Location | Purpose | Key Fields |
|-------|----------|---------|------------|
| Payment | `app/Models/payment.php` | Patient payments | `total`, `payment_type`, `payment_method`, `bank_id` |
| PatientAccount | `app/Models/PatientAccount.php` | Patient deposits/credit | `balance` |
| ProductOrServiceRequest | `app/Models/ProductOrServiceRequest.php` | Billing line items | `amount`, `payable_amount`, `payment_id` |
| Expense | `app/Models/Expense.php` | Expense tracking | `amount`, `category`, `status`, `reference_type/id` |
| PurchaseOrder | `app/Models/PurchaseOrder.php` | Procurement | `total_amount`, `amount_paid`, `payment_status` |
| PurchaseOrderPayment | `app/Models/PurchaseOrderPayment.php` | PO payments | `amount`, `payment_method`, `expense_id` |
| PayrollBatch | `app/Models/PayrollBatch.php` | Payroll processing | `total_net`, `status`, `expense_id` |
| Sale | `app/Models/Sale.php` | Sales/dispensing | `total_amount`, `gain`, `loss` |

### 2.2 Current Money Flow

#### Revenue (Money In)
1. **Patient Payments** → `payments` table → links to `product_or_service_requests`
2. **Patient Deposits** → `payments` (type=`ACC_DEPOSIT`) → updates `patient_accounts.balance`
3. **HMO Claims** → `hmo_claims` → settled via `hmo_remittances`

#### Expenses (Money Out)
1. **Purchase Orders** → `purchase_orders` → `purchase_order_payments` → `expenses`
2. **Payroll** → `payroll_batches` → `expenses` (category=`salaries`)
3. **General Expenses** → `expenses` directly

### 2.3 Existing Approval Patterns

Reference implementations in codebase:

| Pattern | Model | Status Flow | Key Methods |
|---------|-------|-------------|-------------|
| PurchaseOrder | `app/Models/PurchaseOrder.php` | draft→submitted→approved→received | `canApprove()`, `canReceive()` |
| Expense | `app/Models/Expense.php` | pending→approved→rejected→void | `approve()`, `reject()` |
| PayrollBatch | `app/Models/PayrollBatch.php` | draft→submitted→approved→paid | Status constants |
| StoreRequisition | `app/Models/StoreRequisition.php` | pending→approved→fulfilled | `approve()`, `reject()` |

### 2.4 Existing Messaging System

**Package:** `cmgmyr/messenger v2.29` with custom chat layer

**Models:**
- `ChatConversation` — `app/Models/ChatConversation.php`
- `ChatMessage` — `app/Models/ChatMessage.php`
- `ChatParticipant` — `app/Models/ChatParticipant.php`

**Service:** `app/Services/DepartmentNotificationService.php`

**Existing Groups:**
```php
const GROUP_NURSING = 'Nursing Staff';
const GROUP_LAB = 'Laboratory Staff';
const GROUP_IMAGING = 'Imaging Staff';
const GROUP_HMO = 'HMO Executives';
```

### 2.5 Confirmed Role Names

From `resources/views/admin/partials/sidebar.blade.php` line 224:

```php
@hasanyrole('SUPERADMIN|ADMIN|ACCOUNTS|BILLER')
```

**Accounts Staff Group will include:**
- `ACCOUNTS` — Primary accounts role
- `BILLER` — Billing/cashier role
- `SUPERADMIN` — Oversight
- `ADMIN` — Oversight

---

## 3. Database Schema Design

### 3.1 Fiscal Period Tables

#### `fiscal_years`
```sql
CREATE TABLE fiscal_years (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    year_name VARCHAR(50) NOT NULL,              -- "FY 2026"
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('open', 'closing', 'closed') DEFAULT 'open',
    closed_by BIGINT UNSIGNED NULL,
    closed_at TIMESTAMP NULL,
    retained_earnings_entry_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (closed_by) REFERENCES users(id),
    FOREIGN KEY (retained_earnings_entry_id) REFERENCES journal_entries(id)
);
```

#### `accounting_periods`
```sql
CREATE TABLE accounting_periods (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    fiscal_year_id BIGINT UNSIGNED NOT NULL,
    period_number TINYINT NOT NULL,              -- 1-12 for monthly
    period_name VARCHAR(50) NOT NULL,            -- "January 2026"
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('open', 'closing', 'closed') DEFAULT 'open',
    is_adjustment_period BOOLEAN DEFAULT FALSE,  -- For year-end adjustments
    closed_by BIGINT UNSIGNED NULL,
    closed_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (fiscal_year_id) REFERENCES fiscal_years(id),
    FOREIGN KEY (closed_by) REFERENCES users(id),
    UNIQUE KEY (fiscal_year_id, period_number)
);
```

### 3.2 Chart of Accounts Tables

#### `account_classes`
```sql
CREATE TABLE account_classes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(10) NOT NULL UNIQUE,            -- "1", "2", "3", "4", "5"
    name VARCHAR(50) NOT NULL,                   -- "ASSET", "LIABILITY", etc.
    normal_balance ENUM('debit', 'credit') NOT NULL,
    display_order TINYINT NOT NULL,
    is_temporary BOOLEAN DEFAULT FALSE,          -- TRUE for INCOME, EXPENSE
    cash_flow_category ENUM('operating', 'investing', 'financing') NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### `account_groups`
```sql
CREATE TABLE account_groups (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    account_class_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,            -- "1.1", "1.2"
    name VARCHAR(100) NOT NULL,                  -- "Current Assets"
    description TEXT NULL,
    display_order TINYINT NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (account_class_id) REFERENCES account_classes(id)
);
```

#### `accounts`
```sql
CREATE TABLE accounts (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    account_group_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,            -- "1000", "1001"
    name VARCHAR(100) NOT NULL,                  -- "Cash on Hand", "Zenith Bank"
    description TEXT NULL,
    bank_id BIGINT UNSIGNED NULL,                -- Links to banks table for bank accounts
    is_system BOOLEAN DEFAULT FALSE,             -- Protected from deletion
    is_active BOOLEAN DEFAULT TRUE,
    is_bank_account BOOLEAN DEFAULT FALSE,
    cash_flow_category_override ENUM('operating', 'investing', 'financing') NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (account_group_id) REFERENCES account_groups(id),
    FOREIGN KEY (bank_id) REFERENCES banks(id)
);
```

#### `account_sub_accounts`
```sql
CREATE TABLE account_sub_accounts (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(30) NOT NULL,                   -- "1100.PAT.001"
    name VARCHAR(150) NOT NULL,                  -- "John Doe" or "Consultation"
    
    -- Polymorphic linking (only one should be set)
    product_id BIGINT UNSIGNED NULL,
    service_id BIGINT UNSIGNED NULL,
    product_category_id BIGINT UNSIGNED NULL,
    service_category_id BIGINT UNSIGNED NULL,
    supplier_id BIGINT UNSIGNED NULL,
    patient_id BIGINT UNSIGNED NULL,
    
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (account_id) REFERENCES accounts(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (product_category_id) REFERENCES product_categories(id),
    FOREIGN KEY (service_category_id) REFERENCES service_categories(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    
    UNIQUE KEY (account_id, code)
);
```

### 3.3 Journal Entry Tables

#### `journal_entries`
```sql
CREATE TABLE journal_entries (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    entry_number VARCHAR(20) NOT NULL UNIQUE,    -- "JE202601-0001"
    accounting_period_id BIGINT UNSIGNED NOT NULL,
    entry_date DATE NOT NULL,
    description TEXT NOT NULL,
    
    -- Polymorphic reference to source transaction
    reference_type VARCHAR(100) NULL,            -- "App\Models\Payment"
    reference_id BIGINT UNSIGNED NULL,
    
    entry_type ENUM('auto', 'manual', 'opening', 'closing', 'reversal') NOT NULL,
    status ENUM('draft', 'pending_approval', 'approved', 'posted', 'reversed') NOT NULL DEFAULT 'draft',
    
    -- Reversal tracking
    reversal_of_id BIGINT UNSIGNED NULL,         -- If this is a reversal, points to original
    reversed_by_id BIGINT UNSIGNED NULL,         -- If reversed, points to reversal entry
    
    -- Workflow tracking
    created_by BIGINT UNSIGNED NOT NULL,
    submitted_by BIGINT UNSIGNED NULL,
    submitted_at TIMESTAMP NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    rejected_by BIGINT UNSIGNED NULL,
    rejected_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    posted_by BIGINT UNSIGNED NULL,
    posted_at TIMESTAMP NULL,
    
    edit_requires_approval BOOLEAN DEFAULT TRUE,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (accounting_period_id) REFERENCES accounting_periods(id),
    FOREIGN KEY (reversal_of_id) REFERENCES journal_entries(id),
    FOREIGN KEY (reversed_by_id) REFERENCES journal_entries(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (submitted_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (rejected_by) REFERENCES users(id),
    FOREIGN KEY (posted_by) REFERENCES users(id),
    
    INDEX (entry_date),
    INDEX (status),
    INDEX (reference_type, reference_id)
);
```

#### `journal_entry_lines`
```sql
CREATE TABLE journal_entry_lines (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    journal_entry_id BIGINT UNSIGNED NOT NULL,
    line_number SMALLINT NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    sub_account_id BIGINT UNSIGNED NULL,
    debit DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    credit DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    narration VARCHAR(255) NULL,
    cash_flow_category ENUM('operating', 'investing', 'financing') NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id),
    FOREIGN KEY (sub_account_id) REFERENCES account_sub_accounts(id),
    
    INDEX (account_id),
    INDEX (sub_account_id),
    
    CHECK (debit >= 0),
    CHECK (credit >= 0),
    CHECK (NOT (debit > 0 AND credit > 0))  -- Either debit OR credit, not both
);
```

### 3.4 Edit Request Table

#### `journal_entry_edits`
```sql
CREATE TABLE journal_entry_edits (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    journal_entry_id BIGINT UNSIGNED NOT NULL,
    original_data JSON NOT NULL,                 -- Snapshot before edit
    edited_data JSON NOT NULL,                   -- Proposed changes
    edit_reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    
    requested_by BIGINT UNSIGNED NOT NULL,
    requested_at TIMESTAMP NOT NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    rejected_by BIGINT UNSIGNED NULL,
    rejected_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (rejected_by) REFERENCES users(id)
);
```

### 3.5 Credit Note Tables

#### `credit_notes`
```sql
CREATE TABLE credit_notes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    credit_note_number VARCHAR(20) NOT NULL UNIQUE, -- "CN202601-0001"
    patient_id BIGINT UNSIGNED NOT NULL,
    original_payment_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    reason TEXT NOT NULL,
    refund_method ENUM('cash', 'bank', 'account_credit') NOT NULL,
    bank_id BIGINT UNSIGNED NULL,
    
    status ENUM('draft', 'pending_approval', 'approved', 'processed', 'void') DEFAULT 'draft',
    journal_entry_id BIGINT UNSIGNED NULL,       -- Links to posted journal entry
    
    created_by BIGINT UNSIGNED NOT NULL,
    submitted_by BIGINT UNSIGNED NULL,
    submitted_at TIMESTAMP NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at TIMESTAMP NULL,
    processed_by BIGINT UNSIGNED NULL,
    processed_at TIMESTAMP NULL,
    voided_by BIGINT UNSIGNED NULL,
    voided_at TIMESTAMP NULL,
    void_reason TEXT NULL,
    
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (original_payment_id) REFERENCES payments(id),
    FOREIGN KEY (bank_id) REFERENCES banks(id),
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

#### `credit_note_items`
```sql
CREATE TABLE credit_note_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    credit_note_id BIGINT UNSIGNED NOT NULL,
    product_or_service_request_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (credit_note_id) REFERENCES credit_notes(id) ON DELETE CASCADE,
    FOREIGN KEY (product_or_service_request_id) REFERENCES product_or_service_requests(id)
);
```

### 3.6 Saved Report Filters Table

#### `saved_report_filters`
```sql
CREATE TABLE saved_report_filters (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    report_type ENUM(
        'trial_balance', 'profit_loss', 'balance_sheet', 
        'general_ledger', 'accounts_payable', 'accounts_receivable',
        'cash_flow', 'daily_audit'
    ) NOT NULL,
    filters JSON NOT NULL,                       -- All filter parameters
    is_default BOOLEAN DEFAULT FALSE,
    is_shared BOOLEAN DEFAULT FALSE,             -- Visible to all users
    description TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### 3.7 Modifications to Existing Tables

Add `journal_entry_id` FK to source tables:

```sql
-- payments table
ALTER TABLE payments ADD COLUMN journal_entry_id BIGINT UNSIGNED NULL;
ALTER TABLE payments ADD FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id);

-- purchase_orders table
ALTER TABLE purchase_orders ADD COLUMN journal_entry_id BIGINT UNSIGNED NULL;
ALTER TABLE purchase_orders ADD FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id);

-- purchase_order_payments table
ALTER TABLE purchase_order_payments ADD COLUMN journal_entry_id BIGINT UNSIGNED NULL;
ALTER TABLE purchase_order_payments ADD FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id);

-- payroll_batches table
ALTER TABLE payroll_batches ADD COLUMN journal_entry_id BIGINT UNSIGNED NULL;
ALTER TABLE payroll_batches ADD FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id);

-- expenses table
ALTER TABLE expenses ADD COLUMN journal_entry_id BIGINT UNSIGNED NULL;
ALTER TABLE expenses ADD FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id);
```

---

## 4. Models & Services

### 4.1 Eloquent Models

#### Location: `app/Models/Accounting/`

| Model | File | Key Methods |
|-------|------|-------------|
| `FiscalYear` | `FiscalYear.php` | `canClose()`, `periods()` |
| `AccountingPeriod` | `AccountingPeriod.php` | `canClose()`, `isOpen()`, `fiscalYear()` |
| `AccountClass` | `AccountClass.php` | `groups()`, `isTemporary()` |
| `AccountGroup` | `AccountGroup.php` | `accountClass()`, `accounts()` |
| `Account` | `Account.php` | `getBalance($from, $to)`, `group()`, `subAccounts()`, `bank()` |
| `AccountSubAccount` | `AccountSubAccount.php` | `account()`, `product()`, `service()`, `supplier()`, `patient()` |
| `JournalEntry` | `JournalEntry.php` | `lines()`, `isBalanced()`, `canSubmit()`, `canApprove()`, `canPost()`, `canReverse()`, `reference()`, `reversalOf()`, `reversedBy()` |
| `JournalEntryLine` | `JournalEntryLine.php` | `journalEntry()`, `account()`, `subAccount()` |
| `JournalEntryEdit` | `JournalEntryEdit.php` | `journalEntry()`, `getDiff()` |
| `CreditNote` | `CreditNote.php` | `items()`, `patient()`, `originalPayment()`, `journalEntry()` |
| `CreditNoteItem` | `CreditNoteItem.php` | `creditNote()`, `productOrServiceRequest()` |
| `SavedReportFilter` | `SavedReportFilter.php` | `scopeForReport()`, `scopeAccessibleBy()` |

#### Key Model Implementation: Account Balance Calculation

```php
// app/Models/Accounting/Account.php
public function getBalance(?Carbon $fromDate = null, ?Carbon $toDate = null): float
{
    $query = JournalEntryLine::query()
        ->where('account_id', $this->id)
        ->whereHas('journalEntry', function ($q) use ($fromDate, $toDate) {
            $q->where('status', 'posted');
            if ($fromDate) $q->where('entry_date', '>=', $fromDate);
            if ($toDate) $q->where('entry_date', '<=', $toDate);
        });
    
    $debits = (clone $query)->sum('debit');
    $credits = (clone $query)->sum('credit');
    
    // Return based on normal balance
    return $this->group->accountClass->normal_balance === 'debit'
        ? $debits - $credits
        : $credits - $debits;
}
```

### 4.2 Services

#### Location: `app/Services/Accounting/`

| Service | File | Purpose |
|---------|------|---------|
| `AccountingService` | `AccountingService.php` | Core journal operations |
| `ReportQueryService` | `ReportQueryService.php` | Report data & drill-down URLs |
| `PeriodClosingService` | `PeriodClosingService.php` | Period/year-end procedures |
| `RefundService` | `RefundService.php` | Credit note processing |
| `SavedReportFilterService` | `SavedReportFilterService.php` | Filter management |
| `AccountingNotificationService` | `AccountingNotificationService.php` | Group chat notifications |

#### AccountingService Methods

```php
// app/Services/Accounting/AccountingService.php

public function createJournalEntry(
    array $lines,
    string $description,
    ?Model $reference = null,
    string $type = 'manual',
    bool $autoPost = false
): JournalEntry;

public function submitForApproval(int $entryId): JournalEntry;
public function approveEntry(int $entryId): JournalEntry;
public function rejectEntry(int $entryId, string $reason): JournalEntry;
public function postEntry(int $entryId): JournalEntry;
public function reverseEntry(int $entryId, string $reason): JournalEntry;

public function requestEdit(int $entryId, array $newData, string $reason): JournalEntryEdit;
public function approveEdit(int $editId): JournalEntry;
public function rejectEdit(int $editId, string $reason): JournalEntryEdit;

public function bulkApprove(array $entryIds): int;
public function bulkPost(array $entryIds): int;
public function bulkExport(array $entryIds, string $format): string;
```

#### ReportQueryService Methods

```php
// app/Services/Accounting/ReportQueryService.php

public function getTrialBalanceFilters(Carbon $asOfDate, ?int $accountId = null): array;
public function getProfitLossFilters(Carbon $fromDate, Carbon $toDate, ?int $accountId = null): array;
public function getBalanceSheetFilters(Carbon $asOfDate, ?int $accountId = null): array;
public function getARFilters(Carbon $asOfDate, ?int $patientId = null): array;
public function getAPFilters(Carbon $asOfDate, ?int $supplierId = null): array;
public function getCashFlowFilters(Carbon $fromDate, Carbon $toDate, ?string $category = null): array;

// Returns array with drill-down URL components:
// ['date_from' => ..., 'date_to' => ..., 'account_ids' => [...], 'status' => 'posted',
//  'source_report' => 'trial_balance', 'source_label' => 'Trial Balance', 'breadcrumb' => [...]]
```

---

## 5. Notification System

### 5.1 Accounts Staff Group Configuration

**Update:** DepartmentNotificationService.php

```php
// Add new constant
const GROUP_ACCOUNTS = 'Accounts Staff';

// In syncGroup() method, add:
case self::GROUP_ACCOUNTS:
    $roles = ['ACCOUNTS', 'BILLER'];
    break;
```

**Group Members:**
- Users with role `ACCOUNTS`
- Users with role `BILLER`
- Users with role `SUPERADMIN` (oversight)
- Users with role `ADMIN` (oversight)

### 5.2 AccountingNotificationService

**Location:** `app/Services/Accounting/AccountingNotificationService.php`

```php
class AccountingNotificationService
{
    protected DepartmentNotificationService $deptNotification;
    
    public function notifyJournalPendingApproval(JournalEntry $entry, User $submittedBy): void
    {
        $message = "📋 **Journal Entry Pending Approval**\n\n" .
            "Entry: **{$entry->entry_number}**\n" .
            "Amount: ₦" . number_format($entry->lines->sum('debit'), 2) . "\n" .
            "Submitted by: {$submittedBy->name}\n" .
            "Description: {$entry->description}\n\n" .
            "[Review Entry](/accounting/journal-entries/{$entry->id})";
        
        $this->deptNotification->sendToGroup(
            DepartmentNotificationService::GROUP_ACCOUNTS,
            $message
        );
    }
    
    // Additional methods...
}
```

### 5.3 Notification Messages

| Event | Icon | Message |
|-------|------|---------|
| Journal pending approval | 📋 | **Journal Entry Pending Approval** with entry details and link |
| Journal approved | ✅ | **Journal Entry Approved** with entry number and approver |
| Journal rejected | ❌ | **Journal Entry Rejected** with reason |
| Edit request pending | ✏️ | **Edit Request Pending** with entry and reason |
| Edit approved | ✅ | **Edit Approved** |
| Edit rejected | ❌ | **Edit Rejected** with reason |
| Credit note pending | 💰 | **Credit Note Pending Approval** with details |
| Credit note approved | ✅ | **Credit Note Approved** |
| Refund processed | 💸 | **Refund Processed** with amount and method |
| Period closing | 📅 | **Accounting Period Closing** |
| Period closed | 🔒 | **Accounting Period Closed** |
| Fiscal year closed | 📆 | **Fiscal Year Closed** |
| Bulk operation | 📦 | **Bulk Operation Completed** (summary for bulk actions) |

---

## 6. Observers for Automated Journaling

### 6.1 Observer Registration

**Location:** EventServiceProvider.php

```php
protected $observers = [
    // Existing observers...
    \App\Models\payment::class => \App\Observers\PaymentObserver::class,
    \App\Models\PurchaseOrder::class => \App\Observers\PurchaseOrderObserver::class,
    \App\Models\PurchaseOrderPayment::class => \App\Observers\PurchaseOrderPaymentObserver::class,
    \App\Models\PayrollBatch::class => \App\Observers\PayrollBatchObserver::class,
    \App\Models\Expense::class => \App\Observers\ExpenseObserver::class,
    \App\Models\PatientAccount::class => \App\Observers\PatientAccountObserver::class,
];
```

### 6.2 PaymentObserver

**Location:** `app/Observers/PaymentObserver.php`

**Trigger:** On `Payment` created

**Journal Logic:**
- **Regular Payment:** DR Bank/Cash (by `payment_method`/`bank_id`), CR Income sub-accounts per `ProductOrServiceRequest` item
- **ACC_DEPOSIT:** DR Bank/Cash, CR Patient Deposits Liability (with patient sub-account)
- **ACC_WITHDRAW:** DR Patient Deposits Liability, CR Income sub-accounts

### 6.3 PurchaseOrderObserver

**Location:** `app/Observers/PurchaseOrderObserver.php`

**Trigger:** On PO status → `received`

**Journal Logic:** DR Inventory Asset (or Expense), CR Accounts Payable (with supplier sub-account)

### 6.4 PurchaseOrderPaymentObserver

**Location:** `app/Observers/PurchaseOrderPaymentObserver.php`

**Trigger:** On `PurchaseOrderPayment` created

**Journal Logic:** DR Accounts Payable (supplier sub-account), CR Bank/Cash

### 6.5 PayrollBatchObserver

**Location:** `app/Observers/PayrollBatchObserver.php`

**Trigger:** On batch status → `paid`

**Journal Logic:** DR Salary Expense, CR Bank/Cash

### 6.6 ExpenseObserver

**Location:** `app/Observers/ExpenseObserver.php`

**Trigger:** On `Expense` status → `approved` (non-PO, non-payroll categories)

**Journal Logic:** DR Expense account (by category mapping), CR Bank/Cash

### 6.7 PatientAccountObserver

**Location:** `app/Observers/PatientAccountObserver.php`

**Trigger:** On `balance` change going negative

**Journal Logic:** DR Accounts Receivable (patient sub-account), CR Patient Deposits Liability

### 6.8 Extended Product/Service Observers

**Update:** ProductObserver.php and ServiceObserver.php

**Additional Logic:** Auto-create income sub-account under category's revenue account on product/service creation

---

## 7. Controllers & Routes

### 7.1 Route File

**Location:** `routes/accounting.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Accounting\*;

Route::middleware(['auth'])->prefix('accounting')->name('accounting.')->group(function () {
    
    // Fiscal Periods
    Route::prefix('fiscal-years')->name('fiscal-years.')->group(function () {
        Route::get('/', [FiscalPeriodController::class, 'index'])->name('index');
        Route::post('/', [FiscalPeriodController::class, 'storeYear'])->name('store');
        Route::post('/{fiscalYear}/periods', [FiscalPeriodController::class, 'storePeriod'])->name('periods.store');
        Route::post('/{fiscalYear}/close', [FiscalPeriodController::class, 'closeYear'])->name('close');
    });
    
    Route::prefix('periods')->name('periods.')->group(function () {
        Route::post('/{period}/close', [FiscalPeriodController::class, 'closePeriod'])->name('close');
        Route::post('/{period}/reopen', [FiscalPeriodController::class, 'reopenPeriod'])->name('reopen');
    });
    
    // Chart of Accounts
    Route::prefix('chart-of-accounts')->name('coa.')->group(function () {
        Route::get('/', [ChartOfAccountsController::class, 'index'])->name('index');
        Route::get('/tree', [ChartOfAccountsController::class, 'tree'])->name('tree');
        Route::apiResource('classes', AccountClassController::class);
        Route::apiResource('groups', AccountGroupController::class);
        Route::apiResource('accounts', AccountController::class);
        Route::apiResource('sub-accounts', AccountSubAccountController::class);
    });
    
    // Journal Entries
    Route::prefix('journal-entries')->name('journal-entries.')->group(function () {
        Route::get('/', [JournalEntryController::class, 'index'])->name('index');
        Route::get('/create', [JournalEntryController::class, 'create'])->name('create');
        Route::post('/', [JournalEntryController::class, 'store'])->name('store');
        Route::get('/{entry}', [JournalEntryController::class, 'show'])->name('show');
        Route::get('/{entry}/edit', [JournalEntryController::class, 'edit'])->name('edit');
        Route::put('/{entry}', [JournalEntryController::class, 'update'])->name('update');
        
        // Workflow
        Route::post('/{entry}/submit', [JournalEntryController::class, 'submitForApproval'])->name('submit');
        Route::post('/{entry}/approve', [JournalEntryController::class, 'approve'])->name('approve');
        Route::post('/{entry}/reject', [JournalEntryController::class, 'reject'])->name('reject');
        Route::post('/{entry}/post', [JournalEntryController::class, 'post'])->name('post');
        Route::post('/{entry}/reverse', [JournalEntryController::class, 'reverse'])->name('reverse');
        
        // Bulk Actions
        Route::post('/bulk/approve', [JournalEntryController::class, 'bulkApprove'])->name('bulk.approve');
        Route::post('/bulk/post', [JournalEntryController::class, 'bulkPost'])->name('bulk.post');
        Route::post('/bulk/export', [JournalEntryController::class, 'bulkExport'])->name('bulk.export');
        
        // Edit Requests
        Route::get('/edits', [JournalEntryEditController::class, 'index'])->name('edits.index');
        Route::get('/edits/{edit}', [JournalEntryEditController::class, 'show'])->name('edits.show');
        Route::post('/edits/{edit}/approve', [JournalEntryEditController::class, 'approve'])->name('edits.approve');
        Route::post('/edits/{edit}/reject', [JournalEntryEditController::class, 'reject'])->name('edits.reject');
    });
    
    // Credit Notes
    Route::prefix('credit-notes')->name('credit-notes.')->group(function () {
        Route::get('/', [CreditNoteController::class, 'index'])->name('index');
        Route::get('/create', [CreditNoteController::class, 'create'])->name('create');
        Route::post('/', [CreditNoteController::class, 'store'])->name('store');
        Route::get('/{creditNote}', [CreditNoteController::class, 'show'])->name('show');
        Route::post('/{creditNote}/submit', [CreditNoteController::class, 'submit'])->name('submit');
        Route::post('/{creditNote}/approve', [CreditNoteController::class, 'approve'])->name('approve');
        Route::post('/{creditNote}/process', [CreditNoteController::class, 'process'])->name('process');
        Route::post('/{creditNote}/void', [CreditNoteController::class, 'void'])->name('void');
        
        // API
        Route::get('/refundable-items/{payment}', [CreditNoteController::class, 'getRefundableItems'])->name('refundable-items');
    });
    
    // Opening Balances
    Route::prefix('opening-balances')->name('opening-balances.')->group(function () {
        Route::get('/', [OpeningBalanceController::class, 'index'])->name('index');
        Route::get('/create', [OpeningBalanceController::class, 'create'])->name('create');
        Route::post('/', [OpeningBalanceController::class, 'store'])->name('store');
    });
    
    // Reports
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('/profit-loss', [ReportController::class, 'profitLoss'])->name('profit-loss');
        Route::get('/balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/general-ledger', [ReportController::class, 'generalLedger'])->name('general-ledger');
        Route::get('/accounts-payable', [ReportController::class, 'accountsPayable'])->name('accounts-payable');
        Route::get('/accounts-receivable', [ReportController::class, 'accountsReceivable'])->name('accounts-receivable');
        Route::get('/cash-flow', [ReportController::class, 'cashFlow'])->name('cash-flow');
        Route::get('/daily-audit', [ReportController::class, 'dailyAudit'])->name('daily-audit');
        
        // Export endpoints
        Route::get('/trial-balance/export', [ReportController::class, 'exportTrialBalance'])->name('trial-balance.export');
        Route::get('/profit-loss/export', [ReportController::class, 'exportProfitLoss'])->name('profit-loss.export');
        // ... other exports
    });
    
    // Saved Filters
    Route::prefix('saved-filters')->name('saved-filters.')->group(function () {
        Route::get('/{reportType}', [SavedReportFilterController::class, 'index'])->name('index');
        Route::post('/', [SavedReportFilterController::class, 'store'])->name('store');
        Route::put('/{filter}', [SavedReportFilterController::class, 'update'])->name('update');
        Route::delete('/{filter}', [SavedReportFilterController::class, 'destroy'])->name('destroy');
        Route::post('/{filter}/set-default', [SavedReportFilterController::class, 'setDefault'])->name('set-default');
    });
});
```

### 7.2 Controller List

**Location:** `app/Http/Controllers/Accounting/`

| Controller | Purpose |
|------------|---------|
| `FiscalPeriodController` | Fiscal year and period management |
| `ChartOfAccountsController` | COA tree view |
| `AccountClassController` | Account class CRUD |
| `AccountGroupController` | Account group CRUD |
| `AccountController` | Account CRUD |
| `AccountSubAccountController` | Sub-account CRUD |
| `JournalEntryController` | Journal entry CRUD + workflow + bulk |
| `JournalEntryEditController` | Edit request approval |
| `CreditNoteController` | Refund/credit note management |
| `OpeningBalanceController` | Opening balance entry |
| `ReportController` | All reports with drill-down |
| `SavedReportFilterController` | Saved filter management |

---

## 8. Views & UI Components

### 8.1 View Structure

```
resources/views/admin/accounting/
├── layouts/
│   └── accounting-layout.blade.php
├── partials/
│   ├── breadcrumb-nav.blade.php
│   ├── bulk-actions-bar.blade.php
│   └── filter-manager.blade.php
├── fiscal-periods/
│   └── index.blade.php
├── chart-of-accounts/
│   ├── index.blade.php
│   └── tree.blade.php
├── journals/
│   ├── index.blade.php
│   ├── form.blade.php
│   ├── show.blade.php
│   └── edit-requests.blade.php
├── credit-notes/
│   ├── index.blade.php
│   ├── form.blade.php
│   └── show.blade.php
├── opening-balances/
│   └── form.blade.php
└── reports/
    ├── trial-balance.blade.php
    ├── profit-loss.blade.php
    ├── balance-sheet.blade.php
    ├── general-ledger.blade.php
    ├── accounts-payable.blade.php
    ├── accounts-receivable.blade.php
    ├── cash-flow.blade.php
    └── daily-audit.blade.php
```

### 8.2 Journal Entry List Features

**File:** `resources/views/admin/accounting/journals/index.blade.php`

**Features:**
- **Breadcrumb navigation bar** — Reads URL params, displays clickable drill-down trail
- **Context banner** — "Viewing X entries from: Trial Balance - Cash Account"
- **Checkbox column** — Select All on Page / Select All Matching Filter
- **Bulk Actions floating bar** — Approve Selected, Post Selected, Export Selected
- **Filter section** — Date range, period, entry_type, status, account, sub-account, reference type
- **Summary cards** — Total Entries, Total Debits, Total Credits, Pending Approval
- **DataTable** — Checkbox, Entry #, Date, Description, Reference, Type, Debits, Credits, Status, Actions

### 8.3 Journal Entry Form Features

**File:** `resources/views/admin/accounting/journals/form.blade.php`

**Features:**
- Header fields (date, period auto-selected, description)
- Dynamic line items with Add/Remove
- Account select (Select2 with hierarchy)
- Dependent Sub-Account select
- Debit/Credit inputs (mutually exclusive)
- Narration per line
- Real-time balance validation footer
- Balanced/Unbalanced indicator
- For posted entry edits: edit reason field, diff preview

### 8.4 Credit Note Form Features

**File:** `resources/views/admin/accounting/credit-notes/form.blade.php`

**Features:**
- Payment search and selection
- Original payment details display
- Refundable items with checkboxes
- Amount per item (validated ≤ max)
- Refund method dropdown
- Bank select (if bank transfer)
- **Live Accounting Impact Preview** — Shows DR/CR lines that will be journaled

---

## 9. Reports with Drill-Down

### 9.1 Drill-Down URL Structure

All report drill-downs link to `/accounting/journal-entries` with filter params:

```
/accounting/journal-entries?
    date_from=2026-01-01&
    date_to=2026-01-31&
    account_ids[]=15&
    status[]=posted&
    source_report=trial_balance&
    source_label=Trial%20Balance&
    breadcrumb[0][label]=Trial%20Balance&
    breadcrumb[0][url]=/accounting/reports/trial-balance&
    breadcrumb[1][label]=1000%20-%20Cash%20on%20Hand
```

### 9.2 Report List

| Report | Filters | Drill-Down Target |
|--------|---------|-------------------|
| **Trial Balance** | As-of Date, Period, Include Zero | Account balances → entries for that account |
| **Profit & Loss** | Date Range, Period, Compare Previous | Revenue/Expense amounts → entries |
| **Balance Sheet** | As-of Date, Period | Asset/Liability/Equity amounts → entries |
| **General Ledger** | Date Range, Class/Group/Account | Hierarchy levels → entries; transactions → detail view |
| **Accounts Payable** | As-of Date, Supplier, Aging | Supplier balances → A/P entries |
| **Accounts Receivable** | As-of Date, Patient, Aging | Patient balances → A/R entries |
| **Cash Flow** | Date Range, Period | Operating/Investing/Financing → tagged entries |
| **Daily Audit** | Date | Entry rows → detail view |

### 9.3 Cash Flow Statement Categories

Per IAS 7, journal entries are tagged with cash flow category:

- **Operating:** Revenue receipts, expense payments, working capital changes
- **Investing:** Asset purchases, asset sales
- **Financing:** Equity changes, loan transactions

Tags can be:
1. Auto-assigned based on account class/group
2. Overridden at journal entry line level
3. Derived from account's `cash_flow_category` or `cash_flow_category_override`

---

## 10. JavaScript Modules

### 10.1 Breadcrumb Module

**File:** `public/js/accounting/breadcrumb.js`

**Functions:**
- `initBreadcrumb()` — Reads URL params on page load
- `renderBreadcrumb()` — Displays clickable trail
- `addBreadcrumb(label, url)` — Adds new level
- `navigateBack(level)` — Returns to previous level
- SessionStorage persistence for back-navigation

### 10.2 Bulk Actions Module

**File:** `public/js/accounting/bulk-actions.js`

**Functions:**
- `initBulkActions()` — Sets up checkbox handlers
- `selectAllOnPage()` — Selects visible rows
- `selectAllMatching()` — AJAX to get all IDs matching filter
- `showActionBar()` / `hideActionBar()` — Floating bar visibility
- `submitBulkAction(action)` — AJAX submission with loading
- `refreshTable()` — Reloads DataTable after action

### 10.3 Saved Filters Module

**File:** `public/js/accounting/saved-filters.js`

**Functions:**
- `initSavedFilters(reportType)` — Loads filters dropdown
- `loadSavedFilter(filterId)` — Applies filter to form
- `saveCurrentFilter(name, isShared)` — AJAX save
- `deleteFilter(filterId)` — AJAX delete
- `setDefaultFilter(filterId)` — AJAX set default

### 10.4 Journal Form Module

**File:** `public/js/accounting/journal-form.js`

**Functions:**
- `initJournalForm()` — Sets up dynamic lines
- `addLine()` — Adds new line from template
- `removeLine(index)` — Removes line
- `calculateTotals()` — Updates debit/credit totals
- `validateBalance()` — Shows balanced/unbalanced indicator
- `loadSubAccounts(accountId, selectElement)` — Dependent dropdown

---

## 11. Seeders & Permissions

### 11.1 Chart of Accounts Seeder

**File:** `database/seeders/AccountingSeeder.php`

**Account Classes:**
| Code | Name | Normal Balance | Is Temporary | Cash Flow |
|------|------|----------------|--------------|-----------|
| 1 | ASSET | Debit | No | - |
| 2 | LIABILITY | Credit | No | - |
| 3 | EQUITY | Credit | No | Financing |
| 4 | INCOME | Credit | Yes | Operating |
| 5 | EXPENSE | Debit | Yes | Operating |

**Account Groups (sample):**
| Class | Code | Name |
|-------|------|------|
| ASSET | 1.1 | Current Assets |
| ASSET | 1.2 | Fixed Assets |
| LIABILITY | 2.1 | Current Liabilities |
| LIABILITY | 2.2 | Long-term Liabilities |
| EQUITY | 3.1 | Owner's Equity |
| INCOME | 4.1 | Operating Revenue |
| INCOME | 4.2 | Other Income |
| EXPENSE | 5.1 | Cost of Sales |
| EXPENSE | 5.2 | Operating Expenses |

**System Accounts:**
| Code | Name | Group | System | Notes |
|------|------|-------|--------|-------|
| 1000 | Cash on Hand | Current Assets | Yes | |
| 1001 | Petty Cash | Current Assets | Yes | |
| 1010+ | {Bank Name} | Current Assets | Yes | One per existing Bank record |
| 1100 | Accounts Receivable | Current Assets | Yes | |
| 1200 | Inventory | Current Assets | Yes | |
| 2000 | Accounts Payable | Current Liabilities | Yes | |
| 2100 | Patient Deposits | Current Liabilities | Yes | |
| 2200 | Accrued Salaries | Current Liabilities | Yes | |
| 3000 | Owner's Capital | Owner's Equity | Yes | |
| 3100 | Retained Earnings | Owner's Equity | Yes | |
| 3200 | Income Summary | Owner's Equity | Yes | For closing entries |
| 3900 | Opening Balance Equity | Owner's Equity | Yes | For opening balances |
| 5100 | Cost of Goods Sold | Cost of Sales | Yes | |
| 5200 | Salary Expense | Operating Expenses | Yes | |
| 5201 | Utilities Expense | Operating Expenses | Yes | |
| 5202 | Maintenance Expense | Operating Expenses | Yes | |

**Dynamic Accounts (auto-created):**
- One revenue account per `ServiceCategory` under Operating Revenue
- One revenue account per `ProductCategory` under Operating Revenue
- Sub-accounts for individual products/services under their category account

### 11.2 Permissions Seeder

**File:** `database/seeders/AccountingPermissionSeeder.php`

**Permissions:**
```php
$permissions = [
    // Journal Entries
    'view journal entries',
    'create journal entries',
    'edit journal entries',
    'approve journal entries',
    'post journal entries',
    'reverse journal entries',
    
    // Reports
    'view accounting reports',
    
    // Chart of Accounts
    'view chart of accounts',
    'manage chart of accounts',
    
    // Fiscal Periods
    'view fiscal periods',
    'manage fiscal periods',
    'close fiscal periods',
    'reopen fiscal periods',  // SUPERADMIN only
    
    // Credit Notes
    'view credit notes',
    'create credit notes',
    'approve credit notes',
    'process credit notes',
    'void credit notes',
    
    // Opening Balances
    'manage opening balances',
];
```

**Role Assignments:**
| Permission | SUPERADMIN | ADMIN | ACCOUNTS | BILLER |
|------------|------------|-------|----------|--------|
| view journal entries | ✓ | ✓ | ✓ | ✓ |
| create journal entries | ✓ | ✓ | ✓ | - |
| edit journal entries | ✓ | ✓ | ✓ | - |
| approve journal entries | ✓ | ✓ | ✓ | - |
| post journal entries | ✓ | ✓ | ✓ | - |
| reverse journal entries | ✓ | ✓ | - | - |
| view accounting reports | ✓ | ✓ | ✓ | ✓ |
| view chart of accounts | ✓ | ✓ | ✓ | ✓ |
| manage chart of accounts | ✓ | ✓ | - | - |
| view fiscal periods | ✓ | ✓ | ✓ | ✓ |
| manage fiscal periods | ✓ | ✓ | - | - |
| close fiscal periods | ✓ | ✓ | - | - |
| reopen fiscal periods | ✓ | - | - | - |
| view credit notes | ✓ | ✓ | ✓ | ✓ |
| create credit notes | ✓ | ✓ | ✓ | ✓ |
| approve credit notes | ✓ | ✓ | ✓ | - |
| process credit notes | ✓ | ✓ | ✓ | - |
| void credit notes | ✓ | ✓ | - | - |
| manage opening balances | ✓ | ✓ | - | - |

---

## 12. Implementation Phases

### Phase 1: Foundation (Week 1-2)

1. Create all migrations
2. Create all Eloquent models
3. Run AccountingSeeder for Chart of Accounts
4. Run AccountingPermissionSeeder
5. Update DepartmentNotificationService with GROUP_ACCOUNTS
6. Create AccountingNotificationService

### Phase 2: Core Services (Week 2-3)

1. Implement AccountingService
2. Implement ReportQueryService
3. Implement PeriodClosingService
4. Implement RefundService
5. Implement SavedReportFilterService

### Phase 3: Automated Journaling (Week 3-4)

1. Create PaymentObserver
2. Create PurchaseOrderObserver
3. Create PurchaseOrderPaymentObserver
4. Create PayrollBatchObserver
5. Create ExpenseObserver
6. Create PatientAccountObserver
7. Extend Product/Service observers

### Phase 4: Controllers & Routes (Week 4-5)

1. Create all controllers
2. Create routes/accounting.php
3. Include in web.php
4. Test all endpoints

### Phase 5: Views - Journal Entries (Week 5-6)

1. Create journal list with filters and bulk actions
2. Create journal form with dynamic lines
3. Create journal detail view
4. Create edit request interface
5. Implement breadcrumb navigation

### Phase 6: Views - Credit Notes & Periods (Week 6-7)

1. Create credit note form with accounting preview
2. Create credit note list and detail
3. Create fiscal period management view
4. Create opening balance entry form

### Phase 7: Reports (Week 7-9)

1. Implement Trial Balance with drill-down
2. Implement Profit & Loss with drill-down
3. Implement Balance Sheet with drill-down
4. Implement General Ledger with hierarchy
5. Implement Accounts Payable with aging
6. Implement Accounts Receivable with aging
7. Implement Cash Flow Statement
8. Implement Daily Audit report

### Phase 8: JavaScript & Polish (Week 9-10)

1. Create breadcrumb.js module
2. Create bulk-actions.js module
3. Create saved-filters.js module
4. Create journal-form.js module
5. Testing and bug fixes
6. Documentation

---

## Appendix A: UI Mockup References

### A.1 Existing Patterns to Follow

| Pattern | Reference File | Use For |
|---------|----------------|---------|
| DataTables with filters | `resources/views/admin/billing/hmo-validation.blade.php` | Journal list, report tables |
| Dynamic form rows | `resources/views/admin/inventory/purchase-orders/form.blade.php` | Journal entry lines |
| Summary stat cards | `resources/views/admin/expenses/index.blade.php` | Report summaries |
| Approval workflow UI | show.blade.php | Journal detail |
| Collapsible accordion | Bootstrap 5 accordion | General Ledger hierarchy |

### A.2 Icon Reference (Material Design Icons)

| Feature | Icon |
|---------|------|
| Journal Entry | `mdi-book-open-page-variant` |
| Chart of Accounts | `mdi-file-tree` |
| Reports | `mdi-chart-box` |
| Trial Balance | `mdi-scale-balance` |
| Profit/Loss | `mdi-chart-line` |
| Balance Sheet | `mdi-file-document` |
| Cash Flow | `mdi-cash-multiple` |
| Credit Note | `mdi-cash-refund` |
| Fiscal Period | `mdi-calendar-clock` |
| Approve | `mdi-check-circle` |
| Reject | `mdi-close-circle` |
| Post | `mdi-send` |
| Reverse | `mdi-undo` |
| Edit | `mdi-pencil` |
| Delete | `mdi-delete` |

---

## Appendix B: Data Flow Diagrams

### B.1 Payment → Journal Entry Flow

```
Patient Payment
    │
    ▼
PaymentObserver::created()
    │
    ├─► Get payment_method & bank_id
    │       │
    │       ▼
    │   Determine DR account (Bank/Cash)
    │
    ├─► Get ProductOrServiceRequests
    │       │
    │       ▼
    │   For each item:
    │       - Find service/product category
    │       - Get income sub-account
    │       - Calculate CR amount
    │
    ▼
AccountingService::createJournalEntry()
    │
    ├─► Validate debits = credits
    ├─► Get current open period
    ├─► Generate entry_number
    ├─► Create JournalEntry (type=auto, status=posted)
    ├─► Create JournalEntryLines
    ▼
Update Payment.journal_entry_id
```

### B.2 Manual Entry Approval Flow

```
User creates manual entry
    │
    ▼
JournalEntry (status=draft)
    │
    ▼
User clicks "Submit for Approval"
    │
    ▼
AccountingService::submitForApproval()
    │
    ├─► Update status to pending_approval
    ├─► Set submitted_by, submitted_at
    ▼
AccountingNotificationService::notifyJournalPendingApproval()
    │
    ▼
Message sent to Accounts Staff group chat
    │
    ▼
Approver reviews entry
    │
    ├─► Approve
    │       │
    │       ▼
    │   AccountingService::approveEntry()
    │       │
    │       ├─► Update status to approved
    │       ├─► Set approved_by, approved_at
    │       ▼
    │   AccountingNotificationService::notifyJournalApproved()
    │       │
    │       ▼
    │   Approver posts entry
    │       │
    │       ▼
    │   AccountingService::postEntry()
    │       │
    │       ├─► Validate period is open
    │       ├─► Update status to posted
    │       └─► Set posted_by, posted_at
    │
    └─► Reject
            │
            ▼
        AccountingService::rejectEntry()
            │
            ├─► Update status to draft (returns to creator)
            ├─► Set rejected_by, rejected_at, rejection_reason
            ▼
        AccountingNotificationService::notifyJournalRejected()
```

---

## Appendix C: SQL Query Examples

### C.1 Trial Balance Query

```sql
SELECT 
    ac.name as class_name,
    ag.name as group_name,
    a.code as account_code,
    a.name as account_name,
    CASE 
        WHEN ac.normal_balance = 'debit' THEN 
            SUM(jel.debit) - SUM(jel.credit)
        ELSE 
            SUM(jel.credit) - SUM(jel.debit)
    END as balance,
    CASE 
        WHEN (ac.normal_balance = 'debit' AND SUM(jel.debit) - SUM(jel.credit) >= 0)
            OR (ac.normal_balance = 'credit' AND SUM(jel.credit) - SUM(jel.debit) < 0)
        THEN ABS(SUM(jel.debit) - SUM(jel.credit))
        ELSE 0
    END as debit_balance,
    CASE 
        WHEN (ac.normal_balance = 'credit' AND SUM(jel.credit) - SUM(jel.debit) >= 0)
            OR (ac.normal_balance = 'debit' AND SUM(jel.debit) - SUM(jel.credit) < 0)
        THEN ABS(SUM(jel.credit) - SUM(jel.debit))
        ELSE 0
    END as credit_balance
FROM accounts a
JOIN account_groups ag ON a.account_group_id = ag.id
JOIN account_classes ac ON ag.account_class_id = ac.id
LEFT JOIN journal_entry_lines jel ON jel.account_id = a.id
LEFT JOIN journal_entries je ON jel.journal_entry_id = je.id
    AND je.status = 'posted'
    AND je.entry_date <= :as_of_date
WHERE a.is_active = 1
GROUP BY a.id, ac.name, ag.name, a.code, a.name, ac.normal_balance
HAVING balance != 0 OR :include_zero = 1
ORDER BY ac.display_order, ag.display_order, a.code;
```

### C.2 Account Balance Query

```sql
SELECT 
    COALESCE(SUM(jel.debit), 0) as total_debits,
    COALESCE(SUM(jel.credit), 0) as total_credits,
    COALESCE(SUM(jel.debit), 0) - COALESCE(SUM(jel.credit), 0) as net_debit
FROM journal_entry_lines jel
JOIN journal_entries je ON jel.journal_entry_id = je.id
WHERE jel.account_id = :account_id
    AND je.status = 'posted'
    AND je.entry_date >= :from_date
    AND je.entry_date <= :to_date;
```

---

*End of Plan Document*
```

You can copy this content and save it to `ACCOUNTING_SYSTEM_PLAN.md` in your project root.You can copy this content and save it to `ACCOUNTING_SYSTEM_PLAN.md` in your project root.
