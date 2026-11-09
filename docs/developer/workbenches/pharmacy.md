# Pharmacy & Dispensary Developer API Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | PHARMACIST</span>

## 1. Controllers & Route Contracts
- **Primary Controller**: `App\Http\Controllers\PharmacyWorkbenchController`.
- **Primary Routes (`routes/web.php`)**:
  - `GET /pharmacy-workbench` (`pharmacy.workbench`) — Prescription fulfillment queue and workbench cockpit.
  - `GET /pharmacy-workbench/prescription-queue` (`pharmacy.prescription-queue`) — Active prescription DataTable feed.
  - `POST /pharmacy-workbench/dispense` (`pharmacy.dispense`) — FIFO batch dispensing action.
  - `POST /pharmacy-workbench/dispense-free-form` (`pharmacy.dispense-free-form`) — Free-form medication dispensing.
  - `GET /pharmacy-workbench/product/{id}/stock` (`pharmacy.product-stock`) — Batch stock availability query.

## 2. Models & Observers
- `StockBatch` & `StockBatchObserver`: Enforces FIFO allocation by earliest expiry date.
- `StoreStock`: Store-level batch quantity mappings (`store_stocks` table).
- `DamageReport` & `DamageReportObserver`: Posts balancing JEs (`DR 5000 Expense, CR 1200 Inventory`).

## 3. Testing Verification
```bash
vendor/bin/phpunit tests/Feature/Pharmacy/FifoDispenseTest.php
vendor/bin/phpunit tests/Feature/Pharmacy/PharmacyReturnsTest.php
```

👉 **[Read User Story Guide for Pharmacy](/user-guides/workbenches/pharmacy)**
