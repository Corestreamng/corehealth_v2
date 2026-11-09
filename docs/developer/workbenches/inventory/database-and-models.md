# Store Database Schema, Models & Observers

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | super-admin | STORE | ACCOUNTS</span>

The Store and Inventory subsystem utilizes a normalized relational schema designed for multi-warehouse tenancy, high-throughput batch movements, FEFO fulfillment, and real-time General Ledger inventory synchronization.

---

## 1. Entity-Relationship Architecture

```mermaid
erDiagram
    STORES ||--o{ STORE_STOCKS : "holds"
    STORES ||--o{ PRODUCT_BATCHES : "stores"
    STORES ||--o{ STORE_REQUISITIONS : "sources_or_receives"
    PRODUCTS ||--o{ STORE_STOCKS : "tracked_in"
    PRODUCTS ||--o{ PRODUCT_BATCHES : "divided_into"
    PRODUCTS ||--o{ PURCHASE_ORDER_ITEMS : "ordered_in"

    PURCHASE_ORDERS ||--|{ PURCHASE_ORDER_ITEMS : "contains"
    STORE_REQUISITIONS ||--|{ STORE_REQUISITION_ITEMS : "composed_of"
    PRODUCT_BATCHES ||--o{ STOCK_UTILIZATIONS : "consumed_by"
    PRODUCT_BATCHES ||--o{ STORE_DAMAGES : "logged_in"

    STORES {
        bigint id PK
        string store_name
        string store_code UK
        string store_type
        string location
        boolean is_active
    }

    PRODUCTS {
        bigint id PK
        string product_name
        string product_code UK
        string product_type
        bigint category_id FK
        decimal standard_cost
        decimal standard_price
        boolean is_active
    }

    PRODUCT_BATCHES {
        bigint id PK
        bigint product_id FK
        bigint store_id FK
        string batch_number
        integer current_qty
        decimal cost_price
        decimal selling_price
        date manufacturing_date
        date expiry_date
        string status
    }

    PURCHASE_ORDERS {
        bigint id PK
        string po_number UK
        bigint supplier_id FK
        bigint target_store_id FK
        decimal total_amount
        string status
        string payment_status
        timestamp created_at
    }

    STORE_REQUISITIONS {
        bigint id PK
        string requisition_number UK
        bigint source_store_id FK
        bigint destination_store_id FK
        string urgency
        string status
        bigint requested_by FK
    }

    STOCK_UTILIZATIONS {
        bigint id PK
        bigint store_id FK
        bigint patient_id FK
        bigint product_id FK
        bigint batch_id FK
        integer quantity
        string utilization_type
        timestamp created_at
    }
```

---

## 2. Core Relational Schemas

### `product_batches`
Maintains individual manufacturing lots with expiration tracking:
```sql
CREATE TABLE `product_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint unsigned NOT NULL,
  `store_id` bigint unsigned NOT NULL,
  `batch_number` varchar(100) NOT NULL,
  `initial_qty` int NOT NULL,
  `current_qty` int NOT NULL DEFAULT '0',
  `cost_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `selling_price` decimal(12,2) NOT NULL DEFAULT '0.00',
  `manufacturing_date` date DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `status` enum('active','quarantined','expired','exhausted') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `batch_expiry_idx` (`expiry_date`),
  INDEX `batch_store_prod_idx` (`store_id`, `product_id`, `status`),
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### `purchase_orders` & `purchase_order_items`
Governs supplier procurement contracts:
```sql
CREATE TABLE `purchase_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `po_number` varchar(50) NOT NULL UNIQUE,
  `supplier_id` bigint unsigned NOT NULL,
  `target_store_id` bigint unsigned NOT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `delivery_note_number` varchar(100) DEFAULT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `status` enum('draft','submitted','approved','partial','received','cancelled') NOT NULL DEFAULT 'draft',
  `payment_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 3. Eloquent Observers & Ledger Hooks

### 1. `PurchaseOrderObserver` (`App\Observers\PurchaseOrderObserver`)
- **Trigger**: Fired on `PurchaseOrder::updated` when `status` transitions to `RECEIVED`.
- **Side Effect**:
  - Automatically verifies delivered batches.
  - Instantiates `AccountsPayable` liability record.
  - Posts General Ledger entry:
    - **`DR 1210 Central Store Inventory`**
    - **`CR 2010 Accounts Payable (Vendor)`**

### 2. `PurchaseOrderPaymentObserver`
- **Trigger**: Fired on `PurchaseOrderPayment::created`.
- **Side Effect**:
  - Updates PO `payment_status` (`PARTIAL` or `PAID`).
  - Posts General Ledger disbursement:
    - **`DR 2010 Accounts Payable`**
    - **`CR 1020 Zenith Bank Operating`**

### 3. `StockBatchObserver`
- **Trigger**: Fired on `StockBatch::saving` and `StockBatch::updated`.
- **Side Effect**:
  - Recalculates store-level aggregated inventory in `store_stocks`.
  - Automatically flags status as `EXPIRED` if `expiry_date < CURDATE()`.
  - Updates running inventory valuation.

---

## Developer Reference
👉 **[Read Store User Guide](/user-guides/workbenches/inventory)**  
👉 **[Read Store API Contracts & Route Specifications](/developer/workbenches/inventory/api-contracts)**

