# Product Creation & Tariff Auto-Generation Study

## System Overview

The system uses **Laravel Observers** pattern to automatically create and manage HMO tariffs. When products, services, or HMOs are created, observers listen to these events and automatically generate tariff entries.

---

## 1. Product Creation Flow

### 1.1 Creating a Product
**File**: `app/Models/Product.php`

When a new Product is created:
```php
$product = Product::create([
    'user_id' => auth()->id(),
    'category_id' => $categoryId,
    'product_name' => 'Product Name',
    'product_code' => 'CODE001',
    'product_type' => 'pharmacy', // pharmacy, consumable, etc.
    'status' => 1,
    'current_quantity' => 0,
]);
```

**Triggers**: `ProductObserver::created(Product $product)`

---

## 2. ProductObserver - Auto-Generate Tariffs

**File**: `app/Observers/ProductObserver.php`

### Flow:
1. **Listen for Product Creation**
   - Observer automatically triggered on `Product::create()`

2. **Fetch Active HMOs**
   ```php
   $hmos = Hmo::where('status', 1)->get();
   ```

3. **Get Product Price**
   ```php
   $price = $product->price ? $product->price->current_sale_price : 0;
   ```

4. **For Each HMO**:
   - Check if tariff already exists
   - If NOT exists, create bulk tariff entry

5. **Bulk Insert**
   ```php
   HmoTariff::insert($tariffs);
   ```

### Created Tariff Structure:
```
hmo_id:          [HMO ID]
product_id:      [Product ID]
service_id:      null
claims_amount:   0
payable_amount:  [Product's current_sale_price]
coverage_mode:   'primary'
created_at:      now()
updated_at:      now()
```

### Example Result:
If system has 5 HMOs and creates a Product:
- **5 tariff entries created** (one for each HMO-Product combination)

---

## 3. PriceObserver - Update Tariffs on Price Changes

**File**: `app/Observers/PriceObserver.php`

### Trigger:
When a `Price` record is created (or attached to a Product)

### Logic:
```
For each active HMO:
  ├─ Find existing tariff for this Product-HMO
  ├─ If EXISTS and payable_amount == 0:
  │  └─ UPDATE payable_amount to new price (auto-pricing)
  ├─ If EXISTS and payable_amount != 0:
  │  └─ SKIP (preserve manual configuration)
  └─ If NOT EXISTS:
     └─ CREATE new tariff with new price
```

### Key Feature:
**Only updates auto-generated tariffs** (payable_amount == 0)
- **Preserves manually configured prices**
- If admin sets payable_amount > 0, it won't be overwritten

### Logged Output:
```
PriceObserver: Product {productId} - Created {count}, Updated {count} HMO tariffs
```

---

## 4. Product Model Relationships

**File**: `app/Models/Product.php`

```php
class Product extends Model {
    // One Product has ONE Price record
    public function price()
    {
        return $this->hasOne(Price::class);
    }

    // Many Tariffs reference this Product
    // (implied through HmoTariff model)
}
```

---

## 5. Price Model Structure

**File**: `app/Models/Price.php`

```php
class Price extends Model {
    protected $fillable = [
        'product_id',
        'pr_buy_price',           // Cost price
        'initial_sale_price',     // Initial selling price
        'current_sale_price',     // Current selling price
        'max_discount',           // Max discount allowed
        'status',
    ];

    public function product() 
    {
        return $this->belongsTo(Product::class);
    }
}
```

---

## 6. Service Creation & ServiceObserver

**File**: `app/Observers/ServiceObserver.php`

### Similar to ProductObserver:
1. When Service is created
2. Fetch all active HMOs
3. Get service price from ServicePrice.sale_price
4. Create tariff for each HMO

```php
// Service pricing lookup
$price = $service->price ? $service->price->sale_price : 0;

// Create tariff
[
    'hmo_id'         => $hmo->id,
    'product_id'     => null,
    'service_id'     => $service->id,
    'claims_amount'  => 0,
    'payable_amount' => $price,
    'coverage_mode'  => 'primary',
]
```

---

## 7. ServicePriceObserver - Dynamic Service Price Updates

**File**: `app/Observers/ServicePriceObserver.php`

### Trigger:
When `ServicePrice` record is created/updated

### Logic:
Similar to `PriceObserver`:
- Updates tariffs for affected service
- Only updates payable_amount if it's 0 (auto-generated)
- Preserves manual configurations

---

## 8. HmoObserver - Reverse Relationship

**File**: `app/Observers/HmoObserver.php`

### Trigger:
When a new `Hmo` (Health insurance organization) is created

### Flow:
1. **Fetch ALL Products**
   ```php
   $products = Product::all();
   ```

2. **Fetch ALL Services**
   ```php
   $services = Service::all();
   ```

3. **For Each Product**: Create tariff
4. **For Each Service**: Create tariff
5. **Bulk Insert with Chunking** (for performance with large datasets)
   ```php
   foreach (array_chunk($tariffs, 500) as $chunk) {
       HmoTariff::insert($chunk);
   }
   ```

### Result:
If system has:
- 50 Products
- 30 Services
- And you create 1 new HMO

**Result**: 80 tariff entries created

---

## 9. HmoTariff Model & Validation

**File**: `app/Models/HmoTariff.php`

### Database Schema:
```sql
CREATE TABLE hmo_tariffs (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    hmo_id BIGINT NOT NULL,
    product_id BIGINT NULL,
    service_id BIGINT NULL,
    claims_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payable_amount DECIMAL(10,2) NOT NULL,
    coverage_mode ENUM('express', 'primary', 'secondary') NOT NULL DEFAULT 'primary',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (hmo_id) REFERENCES hmos(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    UNIQUE KEY unique_hmo_product_service (hmo_id, product_id, service_id),
    CHECK ((product_id IS NOT NULL AND service_id IS NULL) OR 
           (product_id IS NULL AND service_id IS NOT NULL))
);
```

### Model Validation:
```php
static::saving(function ($tariff) {
    // MUST have either product_id OR service_id, NOT both, NOT neither
    if (($tariff->product_id && $tariff->service_id) || 
        (!$tariff->product_id && !$tariff->service_id)) {
        throw new \Exception('Either product_id OR service_id must be set, not both, not neither.');
    }
});
```

### Field Descriptions:
| Field | Type | Purpose |
|-------|------|---------|
| `hmo_id` | FK | Reference to HMO/Insurance provider |
| `product_id` | FK OR NULL | Product reference (if product tariff) |
| `service_id` | FK OR NULL | Service reference (if service tariff) |
| `claims_amount` | DECIMAL | What insurance company claims/reimburses |
| `payable_amount` | DECIMAL | What facility receives from patient/HMO |
| `coverage_mode` | ENUM | How coverage works: primary, secondary, express |

---

## 10. Observer Registration

**File**: `app/Providers/AppServiceProvider.php`

```php
public function boot()
{
    // Register HMO tariff auto-generation observers
    Product::observe(ProductObserver::class);
    Service::observe(ServiceObserver::class);
    ServicePrice::observe(ServicePriceObserver::class);
    Price::observe(PriceObserver::class);
    Hmo::observe(HmoObserver::class);
    
    // ... other observers ...
}
```

---

## 11. Complete Workflow Examples

### Example 1: Create Product in Existing System
```
Action: Create Product
↓
ProductObserver triggered
↓
Query: Find all active HMOs (let's say 3 HMOs)
↓
For each HMO:
  ├─ Check if tariff exists
  ├─ If NO → Create tariff with product price
  └─ Skip if YES (duplicate prevention)
↓
Result: 3 new tariff entries created
  - HMO1-Product99 (payable_amount: $15.00)
  - HMO2-Product99 (payable_amount: $15.00)
  - HMO3-Product99 (payable_amount: $15.00)
```

### Example 2: Update Product Price
```
Action: Create or Update Price record
↓
PriceObserver triggered
↓
Query: Find all active HMOs (3 HMOs)
↓
For each HMO:
  ├─ Find existing tariff for this Product-HMO
  ├─ If payable_amount == 0:
  │  └─ UPDATE to new price (auto-pricing)
  └─ If payable_amount > 0:
     └─ SKIP (manual price, don't override)
↓
Log result: "Created 2, Updated 1 HMO tariffs"
```

### Example 3: Create New HMO
```
Action: Create HMO organization
↓
HmoObserver triggered
↓
Query: Fetch ALL products (50 items)
       Fetch ALL services (30 items)
↓
For each product: create tariff entry
For each service: create tariff entry
↓
Bulk insert in chunks of 500 (for performance)
↓
Log result: "Created 80 tariff entries for HMO: {hmo_id}"
```

---

## 12. Key Design Patterns

### 1. **Bulk Insert for Performance**
- Use `HmoTariff::insert()` instead of creating one-by-one
- HmoObserver chunks inserts (500 at a time) for large datasets

### 2. **Duplicate Prevention**
- Check if tariff exists before creating
- Prevents accidental duplicates

### 3. **Auto vs Manual Pricing**
- `payable_amount == 0` = auto-generated, can be updated
- `payable_amount > 0` = manually configured, preserved

### 4. **Uniqueness Constraint**
- Database has UNIQUE constraint on `(hmo_id, product_id, service_id)`
- Prevents even database-level duplicates

### 5. **XOR Validation**
- Either product_id OR service_id must be set
- Not both, not neither
- Enforced in model `boot()` method

---

## 13. Logging

All observers log their actions:

```php
// Success
Log::info("Created 5 tariff entries for product: 123");
Log::info("PriceObserver: Product 123 - Created 2, Updated 3 HMO tariffs with price 199.99");

// Errors
Log::error("Failed to create tariffs for product 123: {error}");
Log::error("PriceObserver: Failed to update/create tariffs for product 123: {error}");
```

Check logs in:
- `storage/logs/laravel.log` (daily by default)
- Or tail with: `tail -f storage/logs/laravel.log`

---

## 14. Tariff Management UI

**File**: `app/Http/Controllers/Admin/TariffManagementController.php`

Once tariffs are auto-created, admins can:
- View all tariffs in table
- Edit individual tariffs (change claims/payable amounts)
- Export tariffs to CSV
- Import tariffs from Excel
- Delete tariffs

---

## Summary

| When | Observer | Creates/Updates | For |
|------|----------|-----------------|-----|
| Product created | ProductObserver | Tariffs | All active HMOs |
| Service created | ServiceObserver | Tariffs | All active HMOs |
| Price created | PriceObserver | Tariffs | All active HMOs |
| ServicePrice created | ServicePriceObserver | Tariffs | All active HMOs |
| HMO created | HmoObserver | Tariffs | All Products + Services |

Each observer ensures **no duplicates** and **optimal performance** through bulk operations and chunking.
