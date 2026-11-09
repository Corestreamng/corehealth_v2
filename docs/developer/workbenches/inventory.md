# Store & Inventory Developer API Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | STORE</span>

## 1. Controllers & Route Contracts
- **Primary Controller**: `App\Http\Controllers\Inventory\StoreWorkbenchController`.
- **Primary Routes (`routes/inventory.php`)**:
  - `GET /inventory/store-workbench` (`inventory.store-workbench.index`) — Main store cockpit.
  - `GET /inventory/purchase-orders` (`inventory.purchase-orders.index`) — Purchase order management.
  - `GET /inventory/po-returns` (`inventory.po-returns.index`) — Purchase order return logs.

## 2. Models & Observers
- `Store`: Store roles (Central, Pharmacy Hub, Ward Sub-Store, Lab).
- `StockBatch` & `StockBatchObserver`: Three-tier FIFO synchronization.

## 3. Testing Verification
```bash
vendor/bin/phpunit tests/Feature/Inventory/
```

👉 **[Read User Story Guide for Inventory](/user-guides/workbenches/inventory)**
