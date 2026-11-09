# Billing Workbench API Contracts & Route Specifications

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | ACCOUNTS | BILLER</span>

All endpoints return JSON responses adhering to standard envelope contracts: `{ "success": boolean, "message": string, "data": array|object }`.

---

## 1. Shift Management Endpoints

### `POST /billing-workbench/start-shift`
Initiates an audited cashier till session.

**Request Payload:**
```json
{
  "shift_type": "morning" // "morning" | "afternoon" | "night"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Billing shift started successfully",
  "shift": {
    "id": 142,
    "user_id": 1,
    "shift_type": "morning",
    "started_at": "2026-11-09 08:00:00",
    "status": "active"
  }
}
```

---

### `POST /billing-workbench/end-shift`
Closes the active cashier session, computes till totals, and seals the audit record.

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Billing shift ended successfully",
  "summary": {
    "shift_id": 142,
    "duration_minutes": 360,
    "total_collected": 345000.00,
    "cash_collected": 120000.00,
    "pos_collected": 175000.00,
    "transfers_collected": 50000.00,
    "refunds_paid": 5000.00
  }
}
```

---

## 2. Patient Data & Checkout Endpoints

### `GET /billing-workbench/patient/{id}/billing-data`
Retrieves unpaid clinical orders, historical receipts, and wallet balance for the selected patient.

**Response (200 OK):**
```json
{
  "success": true,
  "patient": {
    "id": 1,
    "name": "Oladipo Amina",
    "file_no": "14-32-97",
    "balance": 25000.00,
    "hmo_id": 12,
    "hmo_name": "PLASCHEMA"
  },
  "billing_items": [
    {
      "id": 8921,
      "type": "lab",
      "service_name": "Full Blood Count (FBC)",
      "category": "Laboratory",
      "price": 5000.00,
      "qty": 1,
      "hmo_share": 4000.00,
      "patient_share": 1000.00,
      "created_at": "2026-11-09 09:15:00"
    }
  ]
}
```

---

### `POST /billing-workbench/process-payment`
Processes single-tender or multi-leg split payment checkout.

**Single-Tender Payload:**
```json
{
  "patient_id": 1,
  "items": [
    { "id": 8921, "type": "lab", "amount": 1000.00, "discount_percent": 0 }
  ],
  "payment_method": "CASH",
  "amount_tendered": 1000.00,
  "bank_id": null
}
```

**Split-Payment Payload:**
```json
{
  "patient_id": 1,
  "items": [
    { "id": 8921, "type": "lab", "amount": 50000.00, "discount_percent": 0 }
  ],
  "is_split": true,
  "splits": [
    { "method": "CASH", "amount": 10000.00, "bank_id": null },
    { "method": "POS", "amount": 20000.00, "bank_id": 3, "reference": "POS-AUTH-88219" },
    { "method": "ACCOUNT", "amount": 20000.00, "bank_id": null }
  ]
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Payment processed successfully",
  "receipt_number": "REC-202611-0492",
  "receipt_thermal": "<div class=\"receipt-thermal\">...</div>",
  "journal_entry_id": 10842
}
```

---

## 3. Patient Wallet & Deposit Endpoints

### `POST /billing-workbench/make-deposit`
Credits funds to a patient's pre-funded wallet.

**Request Payload:**
```json
{
  "patient_id": 1,
  "amount": 50000.00,
  "payment_method": "POS",
  "bank_id": 2,
  "description": "Advance deposit for surgery"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Deposit recorded successfully",
  "deposit_id": 412,
  "new_balance": 75000.00,
  "receipt_html": "..."
}
```

---

### `POST /billing-workbench/adjust-payment`
Reclassifies payment instruments without altering bill items or deleting records.

**Request Payload:**
```json
{
  "payment_id": 5042,
  "new_method": "POS",
  "bank_id": 3,
  "reason": "Cashier mistakenly recorded card payment as cash"
}
```

---

### `POST /billing-workbench/void-transaction`
Supervised voiding of an erroneous receipt with mandatory reversing entries.

**Request Payload:**
```json
{
  "payment_id": 5042,
  "refund_mode": "WALLET", // "CASH" | "WALLET" | "REVERSAL"
  "reason": "Order cancelled by physician prior to sample collection",
  "supervisor_pin": "992144"
}
```

---

## Developer Reference
👉 **[Read Billing Database & Observers Reference](/developer/workbenches/billing/database-and-models)**  
👉 **[Read Billing User Guide](/user-guides/workbenches/billing)**

