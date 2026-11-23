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

## Carts and the console (reference)
- `pharmResetCarts()` (`pharmacy-dispensing.js`) is the one place that empties the till bag, ticked rows, totals, sticky bars, floating cart, cart-review modal and the separate `dispenseCart` (`pharmacy-stock.js`, `pharmResetDispenseCart()`). It runs from `loadPatient()`, from `hideAllViews()` (leaving the patient screen), after a till action succeeds, on `clearAllSelections()` and when the dispense modal closes; `addSelectedToCartAndOpen()` rebuilds the cart from the bag each time. `pharmPruneBag(items)` (called from `loadPrescriptionItems`) drops bagged items that no longer exist or changed stage.
- My Transactions is the shared `admin.shared._my_transactions_modal` (`activityWorkbench = pharmacy`). The "What I did" pane is `public/js/my-activity.js` over `GET /billing-workbench/my-activity` (`ProcessedTransactionsController@activity`, stages declared in `App\Services\MyActivity\MyActivityService`). The cash tabs render only with biller rights (`ReceptionWorkbenchController::userCanBill`).
- `GET /pharmacy-workbench/prescription-queue` returns the whole list as before; with DataTables parameters (`length`, `start`, `search[value]`) it returns one page (`draw`, `recordsTotal`, `recordsFiltered`, `data`), which the staff app uses.

