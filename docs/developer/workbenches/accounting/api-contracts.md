# Accounting API Contracts & Route Specifications

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | super-admin | ACCOUNTS | AUDIT</span>

This document defines the request/response contracts for all accounting subsystem endpoints. All POST, PUT, and AJAX endpoints return standardized JSON envelopes: `{ "success": boolean, "message": string, "data": array|object }`.

---

## 1. Chart of Accounts & General Ledger Endpoints

### `POST /accounting/chart-of-accounts`
Creates a new financial account within the 5-class ledger hierarchy.

**Request Payload:**
```json
{
  "code": "1033",
  "name": "Emergency POS - MoniePoint 3",
  "account_class_id": 1,
  "account_group_id": 4,
  "normal_balance": "debit",
  "is_bank_or_cash": true,
  "description": "Terminal stationed at Emergency Ward triage"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Account created successfully",
  "account": {
    "id": 89,
    "code": "1033",
    "name": "Emergency POS - MoniePoint 3",
    "balance": 0.00,
    "is_active": true
  }
}
```

---

### `POST /accounting/journal-entries`
Posts an audited compound double-entry journal entry. Validates that $\sum \text{Debits} == \sum \text{Credits}$.

**Request Payload:**
```json
{
  "entry_date": "2026-11-09",
  "reference": "ADJ-202611-AUDIT",
  "description": "Accrual of statutory audit fee for November",
  "items": [
    {
      "account_id": 45, // 5090 - Audit & Legal Fees
      "debit": 50000.00,
      "credit": 0.00,
      "narration": "November audit fee provision"
    },
    {
      "account_id": 18, // 2030 - Accrued Expenses
      "debit": 0.00,
      "credit": 50000.00,
      "narration": "Accrued audit payable"
    }
  ]
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Journal entry JE-202611-0089 posted successfully",
  "entry": {
    "id": 1402,
    "entry_number": "JE-202611-0089",
    "total_debit": 50000.00,
    "total_credit": 50000.00,
    "status": "posted"
  }
}
```

---

## 2. Banking, Transfers & Reconciliation Endpoints

### `POST /accounting/transfers`
Initiates a treasury sweep or inter-bank liquidity transfer.

**Request Payload:**
```json
{
  "source_account_id": 5, // 1031 MoniePoint POS 1
  "destination_account_id": 2, // 1020 Zenith Bank Operating
  "amount": 20000.00,
  "transfer_date": "2026-11-09",
  "reference": "SWEEP-20261109-POS1",
  "payment_method": "electronic_transfer",
  "narration": "Daily MoniePoint collection sweep to primary operating account"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Transfer initiated and awaiting approval",
  "transfer_id": 84
}
```

---

### `POST /accounting/bank-reconciliation/{reconciliation}/auto-match`
Executes algorithmic matching between General Ledger postings and imported bank statement records.

**Request Payload:**
```json
{
  "date_tolerance_days": 3,
  "match_mode": "exact_amount_and_reference"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "matched_count": 142,
  "unmatched_gl_count": 4,
  "unmatched_statement_count": 2,
  "gl_closing_balance": 4680447.50,
  "statement_closing_balance": 4680447.50,
  "difference": 0.00
}
```

---

## 3. Petty Cash Float Endpoints

### `POST /accounting/petty-cash/funds/{fund}/disbursement`
Issues an emergency petty cash disbursement voucher.

**Request Payload:**
```json
{
  "payee": "Ibrahim Yakubu - Medical Gas Vendor",
  "amount": 12500.00,
  "expense_account_id": 34, // 5020 Medical Consumables & Gases
  "cost_center_id": 3, // Theatre & Surgical Suite
  "description": "Emergency refill of 1 x 40L Medical Oxygen cylinder for Theatre 2"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "voucher_number": "PC-2026-0042",
  "fund_remaining_cash": 37500.00,
  "status": "pending_approval"
}
```

---

## 4. Store POs, Accounts Payable & Operating Expenses

### `POST /inventory/purchase-orders/{purchaseOrder}/receive`
Processes a Goods Receive Note (GRN), updating store inventory and automatically posting Accounts Payable liability.

**Request Payload:**
```json
{
  "delivery_note_number": "DN-APOLLOS-8821",
  "received_date": "2026-11-09",
  "items": [
    { "po_item_id": 1, "received_qty": 10, "batch_number": "LOT-TRC-901", "expiry_date": "2029-05-31" },
    { "po_item_id": 2, "received_qty": 15, "batch_number": "LOT-VIC-442", "expiry_date": "2028-10-31" },
    { "po_item_id": 3, "received_qty": 20, "batch_number": "LOT-GWN-108", "expiry_date": "2031-12-31" }
  ]
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Stock received and Accounts Payable voucher AP-202611-0042 created",
  "stock_updated": true,
  "accounts_payable_id": 42,
  "total_liability": 190146.00
}
```

---

### `POST /inventory/purchase-orders/{purchaseOrder}/payment`
Disburses payment to supplier against outstanding Accounts Payable balance.

**Request Payload:**
```json
{
  "bank_id": 2, // 1020 Zenith Bank
  "amount": 36127.00,
  "payment_date": "2026-11-09",
  "reference": "ZEN-TX-99824102",
  "payment_method": "transfer",
  "narration": "Partial payment for PO2026010001 to Apollos Technologies"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Payment recorded successfully",
  "amount_paid": 36127.00,
  "remaining_balance": 154019.00
}
```

---

## 5. Fixed Assets & Depreciation Engine Endpoints

### `POST /accounting/fixed-assets/depreciation/run`
Triggers straight-line monthly depreciation calculation across all active assets.

**Request Payload:**
```json
{
  "period_month": 11,
  "period_year": 2026
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "assets_processed": 58,
  "total_depreciation_posted": 1845200.00,
  "journal_entry_id": 1405,
  "journal_reference": "DEP-202611-AUTO"
}
```

---

## 6. Financial Reports & Query Parameters

### `GET /accounting/reports/trial-balance`
- **Query Parameters**:
  - `start_date` (YYYY-MM-DD)
  - `end_date` (YYYY-MM-DD)
  - `hide_zero_balances` (boolean: `1` or `0`)
  - `format` (`json`, `pdf`, `excel`)
- **JSON Response Format**:
  ```json
  {
    "as_of_date": "2026-11-09",
    "accounts": [
      { "code": "1010", "name": "Cash in Hand", "class": "Assets", "debit": 45000.00, "credit": 0.00 },
      { "code": "1020", "name": "Zenith Bank Operating", "class": "Assets", "debit": 4680447.50, "credit": 0.00 },
      { "code": "1120", "name": "HMO Receivables", "class": "Assets", "debit": 4820150.00, "credit": 0.00 },
      { "code": "2010", "name": "Accounts Payable", "class": "Liabilities", "debit": 0.00, "credit": 154019.00 },
      { "code": "4050", "name": "Surgery Revenue", "class": "Revenue", "debit": 0.00, "credit": 120000.00 }
    ],
    "total_debit": 28450190.50,
    "total_credit": 28450190.50,
    "is_balanced": true
  }
  ```

---

## Developer Reference
👉 **[Read Accounting Database Schema & Observers Reference](/developer/workbenches/accounting/database-and-models)**

