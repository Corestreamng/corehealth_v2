# Store & Inventory API Contracts & Route Specifications

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | super-admin | STORE | PHARMACY | ACCOUNTS</span>

All endpoints return JSON responses adhering to the standardized envelope: `{ "success": boolean, "message": string, "data": array|object }`.

---

## 1. Purchase Orders & Goods Receiving (GRN) Endpoints

### `POST /inventory/purchase-orders`
Creates a new purchase order with multi-line items.

**Request Payload:**
```json
{
  "supplier_id": 8, // Apollos Technologies
  "target_store_id": 1, // Central Medical Store
  "expected_delivery_date": "2026-11-12",
  "payment_terms": "net_30",
  "notes": "Restock surgical consumables ahead of elective appendectomy roster",
  "items": [
    { "product_id": 142, "quantity": 10, "unit_cost": 8500.00 }, // Laparoscopic Trocar 10mm
    { "product_id": 204, "quantity": 15, "unit_cost": 4200.00 }, // Vicryl 2-0 Sutures
    { "product_id": 311, "quantity": 20, "unit_cost": 2107.30 }  // Disposable Surgical Gowns
  ]
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Purchase order created successfully",
  "data": {
    "id": 142,
    "po_number": "PO2026010001",
    "total_amount": 190146.00,
    "status": "draft"
  }
}
```

---

### `POST /inventory/purchase-orders/{id}/receive`
Processes a Goods Receive Note (GRN), creates batch lots, increments warehouse stock, and logs Accounts Payable liability.

**Request Payload:**
```json
{
  "delivery_note_number": "DN-APOLLOS-8821",
  "received_date": "2026-11-09",
  "items": [
    {
      "po_item_id": 1,
      "received_qty": 10,
      "batch_number": "LOT-TRC-901",
      "manufacturing_date": "2026-05-01",
      "expiry_date": "2029-05-31"
    },
    {
      "po_item_id": 2,
      "received_qty": 15,
      "batch_number": "LOT-VIC-442",
      "manufacturing_date": "2026-04-15",
      "expiry_date": "2028-10-31"
    },
    {
      "po_item_id": 3,
      "received_qty": 20,
      "batch_number": "LOT-GWN-108",
      "manufacturing_date": "2026-06-01",
      "expiry_date": "2031-12-31"
    }
  ]
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Goods received successfully. Stock incremented and AP voucher generated.",
  "data": {
    "po_id": 142,
    "status": "received",
    "accounts_payable_id": 42,
    "total_received_amount": 190146.00
  }
}
```

---

## 2. Store Requisitions & Fulfillment Endpoints

### `POST /inventory/requisitions`
Initiates an inter-store stock replenishment request.

**Request Payload:**
```json
{
  "source_store_id": 1, // Central Medical Store
  "destination_store_id": 3, // Theatre Store
  "urgency": "urgent",
  "target_date": "2026-11-10",
  "notes": "Surgical consumables for patient Oladipo Amina appendectomy",
  "items": [
    { "product_id": 142, "requested_qty": 2 },
    { "product_id": 204, "requested_qty": 4 }
  ]
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Requisition REQ2026090551 submitted for approval",
  "requisition_id": 2393
}
```

---

### `POST /inventory/requisitions/{id}/fulfill`
Allocates specific physical batches using FEFO logic and dispatches items to the requesting store.

**Request Payload:**
```json
{
  "dispatched_items": [
    { "req_item_id": 482, "batch_id": 801, "dispatched_qty": 2 }, // LOT-TRC-901
    { "req_item_id": 483, "batch_id": 802, "dispatched_qty": 4 }  // LOT-VIC-442
  ]
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Requisition fulfilled and dispatch waybill generated",
  "status": "fulfilled"
}
```

---

## 3. Stock Utilization & Patient Billing Endpoints

### `POST /inventory/requisitions/my-stock/utilize`
Records clinical stock consumption, either billing a patient or charging departmental overhead.

**Request Payload (Patient-Billed Mode):**
```json
{
  "store_id": 3, // Theatre Store
  "utilization_type": "patient_billed",
  "patient_id": 492, // Oladipo Amina (File No: 14-32-97)
  "performer_id": 14, // Dr. Danladi Timothy
  "items": [
    { "product_id": 142, "batch_id": 801, "quantity": 1, "unit_tariff": 12500.00 },
    { "product_id": 204, "batch_id": 802, "quantity": 2, "unit_tariff": 6500.00 }
  ]
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Stock utilized and billed to patient account successfully",
  "billing_items_created": [
    { "id": 10842, "item_name": "Laparoscopic Trocar 10mm", "amount": 12500.00 },
    { "id": 10843, "item_name": "Vicryl 2-0 Sutures Box", "amount": 13000.00 }
  ],
  "stock_updated": true
}
```

---

## 4. Tally Card & Stock Ledger Endpoints

### `GET /inventory/store-workbench/tally-card/data`
Returns paginated, immutable stock ledger movements.

**Query Parameters:**
- `store_id` (integer, optional)
- `product_id` (integer, optional)
- `from_date` (YYYY-MM-DD)
- `to_date` (YYYY-MM-DD)

**Response (200 OK):**
```json
{
  "draw": 1,
  "recordsTotal": 1420,
  "recordsFiltered": 1420,
  "summary": {
    "total_in": 10,
    "total_out": 3,
    "net_movement": 7,
    "current_balance": 7
  },
  "data": [
    {
      "datetime": "2026-11-09 14:15:22",
      "action": "PATIENT_UTILIZATION",
      "batch_number": "LOT-TRC-901",
      "balance_bf": 2,
      "change": -1,
      "balance_af": 1,
      "user_name": "Nurse Nanle Sunday Dachollom",
      "notes": "Consumed during appendectomy for Oladipo Amina (14-32-97)"
    }
  ]
}
```

---

## 5. Damages, Returns & Governance Endpoints

### `POST /inventory/store-damages`
Logs compromised items for disposal.

**Request Payload:**
```json
{
  "store_id": 3,
  "product_id": 204,
  "batch_id": 802,
  "quantity": 1,
  "damage_type": "physical_breakage",
  "notes": "Suture box dropped and crushed by trolley"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Damage record created and awaiting audit verification",
  "damage_id": 94
}
```

---

## Developer Reference
👉 **[Read Store Database Schema & Observer Reference](/developer/workbenches/inventory/database-and-models)**

