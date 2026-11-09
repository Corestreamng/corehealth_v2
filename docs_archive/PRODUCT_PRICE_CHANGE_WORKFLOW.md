# Product Price Change Workflow - Complete Study

## Overview

The system separates **Product Management** and **Price Management** into two distinct workflows:
1. **Product CRUD** - Name, code, category, packaging, inventory settings
2. **Price CRUD** - Cost price, sale price, discounts, pricing rules

This document studies how prices are changed in both contexts.

---

## Table of Contents

1. [Product Edit Page - Pricing Context](#product-edit-page)
2. [Product List - Price Management Links](#product-list)
3. [Price CRUD Pages](#price-crud-pages)
4. [Price Update Flow](#price-update-flow)
5. [Database Impact](#database-impact)
6. [Observer Integration](#observer-integration)

---

## 1. Product Edit Page - Pricing Context {#product-edit-page}

**File**: `resources/views/admin/product/edit.blade.php`

### Current Situation
The product edit page **does NOT include pricing fields**. It only edits:
- Product name
- Product code
- Category
- Base unit & packaging
- Inventory settings (reorder alert, half-sale, piece-sale options)

### Control Link in Edit View
From the product list, admins see:

```php
// Button to edit product
<a href="{{ route('products.edit', $product->id) }}" class="btn btn-primary btn-xs">
    <i class="fa fa-pencil"></i> Edit
</a>

// Button to adjust price
<a href="{{ route('prices.edit', $product->id) }}" class="btn btn-primary btn-xs">
    <i class="fa fa-pencil"></i> Adjust Price
</a>
```

### Product Update Controller
**File**: `app/Http/Controllers/Product/productController.php`

```php
public function update(Request $request, $id)
{
    // Only updates product information, NOT price
    $myproduct->product_name = $request->product_name;
    $myproduct->product_code = $request->product_code;
    $myproduct->category_id = $request->category_id;
    $myproduct->reorder_alert = $request->reorder_alert;
    $myproduct->has_have = $request->s1;        // Half sale
    $myproduct->has_piece = $request->s2;       // Piece sale
    $myproduct->howmany_to = $request->quantity_in;
    
    if ($myproduct->update()) {
        $msg = 'The Product ' . $request->product_name . ' Was Updated Successfully.';
        return redirect(route('products.index'))->withMessage($msg);
    }
}
```

**Key Point**: No price update happens here!

---

## 2. Product List - Price Management Links {#product-list}

**File**: `resources/views/admin/product/_index.blade.php`

### Product Listing Display

The product list shows action buttons for price management:

```php
@foreach ($products as $datas)
    <tr>
        <td>{{ $datas->product_name }}</td>
        <td>
            @if ($datas->price) {
                // Price EXISTS - Show Edit button
                <a href="{{ route('prices.edit', $datas->id) }}" 
                   class="btn btn-primary btn-xs">
                    <i class="fa fa-pencil"></i> Adjust Price
                </a>
            }} @else {
                // NO Price - Show Add button
                <a href="{{ route('prices.show', $datas->id) }}" 
                   class="btn btn-warning btn-xs">
                    <i class="fa fa-plus"></i> Add Price
                </a>
            } @endif
        </td>
        <td>
            <a href="{{ route('products.edit', $datas->id) }}" 
               class="btn btn-primary btn-xs">
                <i class="fa fa-pencil"></i> Edit
            </a>
        </td>
    </tr>
@endforeach
```

### Routes Mapping

| Action | Route | Method | View | Purpose |
|--------|-------|--------|------|---------|
| Add new price | `prices.show` | GET | `prices/newprice` | Create initial price |
| Adjust price | `prices.edit` | GET | `prices/edit` | Edit existing price |
| Save new price | `prices.store` | POST | - | Store new price record |
| Save price change | `prices.update` | PUT | - | Update existing price |

---

## 3. Price CRUD Pages {#price-crud-pages}

### 3.1 Create New Price - `prices.show` Route

**Controller**: `PriceController::show()`

```php
public function show($id)  // $id is product_id
{
    $products = Product::whereId($id)->first();
    $application = ApplicationStatu::whereId(1)->first();
    return view('admin.prices.newprice', compact('products', 'application'));
}
```

**View**: `resources/views/admin/prices/newprice.blade.php`

#### Form Layout
```html
<form method="POST" action="{{ route('prices.store') }}">
    @csrf
    
    <table>
        <tr>
            <td>Buy Price (₦)</td>
            <td>Issue Price (₦)</td>
            <td>Max Discount (₦)</td>
            @if ($application->allow_piece_sale == 1)
                <td>Pieces Price (₦)</td>
                <td>Pieces Max Discount (₦)</td>
            @endif
        </tr>
        <tr>
            <td>
                <input type="number" name="buy_price" placeholder="Buying Price" />
            </td>
            <td>
                <input type="number" name="price" placeholder="Price" required />
            </td>
            <td>
                <input type="number" name="max_discount" 
                       placeholder="Maximum Discount" readonly />
            </td>
            <td>
                <input type="number" name="piece_sprice" 
                       placeholder="Pieces Price" />
            </td>
            <td>
                <input type="number" name="pieces_max_discount" 
                       placeholder="Pieces Max Discount" readonly />
            </td>
        </tr>
    </table>
    
    <input type="hidden" name="products" value="{{ $products->id }}" />
    <button type="submit">Submit</button>
</form>
```

#### Key Input Fields:
| Field | Name | Type | Required | Notes |
|-------|------|------|----------|-------|
| Cost Price | `buy_price` | number | No | Supplier/acquisition cost |
| Sale Price | `price` | number | **Yes** | What customer pays |
| Max Discount | `max_discount` | number | No | Readonly (auto-calculated?) |
| Pieces Price | `piece_sprice` | number | No | Only if allow_piece_sale=1 |
| Pieces Discount | `pieces_max_discount` | number | No | Readonly, only if pieces enabled |

### 3.2 Edit Price - `prices.edit` Route

**Controller**: `PriceController::edit()`

```php
public function edit($id)  // $id is product_id
{
    $data = Price::with('product')
        ->whereProduct_id($id)
        ->first();
    
    if (empty($data)) {
        // No price exists, redirect to create
        return redirect(route('prices.index', ['product_id' => $id]));
    }
    
    return view('admin.prices.edit', compact('data', 'application'));
}
```

**View**: `resources/views/admin/prices/edit.blade.php`

#### Form Layout - Two Rows

**Row 1 - Current Values (Read-only Display)**:
```html
<tr>
    <td>{{ formatMoney($data->pr_buy_price) }}</td>
    <td>{{ formatMoney($data->current_sale_price) }}</td>
    <td>{{ formatMoney($data->max_discount) }}</td>
    @if ($application->allow_piece_sale == 1)
        <td>{{ formatMoney($data->pieces_price) }}</td>
        <td>{{ formatMoney($data->pieces_max_discount) }}</td>
    @endif
</tr>
```

**Row 2 - New Values (Editable Input)**:
```html
<tr>
    <td>
        <input type="number" name="new_buy_price" 
               value="{{ old('new_buy_price', $data->pr_buy_price) }}" />
    </td>
    <td>
        <input type="number" name="price" 
               value="{{ old('price', $data->current_sale_price) }}" />
    </td>
    <td>
        <input type="number" name="max_discount" 
               value="{{ old('max_discount', $data->max_discount) }}" />
    </td>
    @if ($application->allow_piece_sale == 1)
        <td>
            <input type="number" name="pieces_price" 
                   value="{{ old('pieces_price', $data->pieces_price) }}" />
        </td>
        <td>
            <input type="number" name="pieces_max_discount" 
                   value="{{ old('pieces_max_discount', $data->pieces_max_discount) }}" />
        </td>
    @endif
</tr>
```

#### Input Fields for Editing:
| Field | Name | Current Value | Notes |
|-------|------|----------------|-------|
| Cost Price | `new_buy_price` | pr_buy_price | Can be changed |
| Sale Price | `price` | current_sale_price | Can be changed |
| Max Discount | `max_discount` | max_discount | Can be changed |
| Pieces Price | `pieces_price` | pieces_price | Can be changed |
| Pieces Discount | `pieces_max_discount` | pieces_max_discount | Can be changed |

---

## 4. Price Update Flow {#price-update-flow}

### 4.1 Store New Price - `PriceController::store()`

**File**: `app/Http/Controllers/PriceController.php`

```php
public function store(Request $request)
{
    // Validation
    $rules = [
        'products' => 'required|max:100',
        'price' => 'required|max:11'
    ];
    
    if (validator fails) {
        return redirect()->back()->withInput()->with('errors', $v->messages());
    }
    
    // Create new Price object
    $myprice = new Price();
    $myprice->product_id = $request->products;
    $myprice->initial_sale_date = now();
    $myprice->current_sale_date = now();
    $myprice->initial_sale_price = $request->price;
    $myprice->current_sale_price = $request->price;  // ← KEY: Sale price
    $myprice->pr_buy_price = $request->buy_price;     // ← KEY: Cost price
    $myprice->max_discount = $request->max_discount ?? 0;
    
    // Optional piece pricing
    if (has_piece_sale_enabled) {
        $myprice->piece_sprice = $request->piece_sprice ?? 0;
        $myprice->pieces_max_discount = $request->pieces_max_discount ?? 0;
    }
    
    $myprice->status = 1;
    
    if ($myprice->save()) {
        // *** OBSERVER TRIGGERED HERE ***
        // PriceObserver::created() fires
        // Creates tariffs for all HMOs
        
        return redirect(route('products.index'))
            ->withMessage('Price created successfully');
    }
}
```

#### Fields Saved:
| Database Column | Source | Purpose |
|-----------------|--------|---------|
| `product_id` | `$request->products` | Links to product |
| `initial_sale_price` | `$request->price` | When price was first set |
| `current_sale_price` | `$request->price` | **Active selling price** |
| `pr_buy_price` | `$request->buy_price` | Cost/acquisition price |
| `max_discount` | `$request->max_discount` | Max discount allowed |
| `initial_sale_date` | `now()` | When price started |
| `current_sale_date` | `now()` | When price was last updated |

### 4.2 Update Existing Price - `PriceController::update()`

**File**: `app/Http/Controllers/PriceController.php`

```php
public function update(Request $request, $id)
{
    // Load existing Price
    $myprice = Price::where('id', $id)->first();
    
    // Update fields
    $myprice->initial_sale_date = now();
    $myprice->current_sale_date = now();
    
    // These are the key fields changed by admin
    $myprice->initial_sale_price = $request->price;
    $myprice->current_sale_price = $request->price;        // ← UPDATED
    $myprice->pr_buy_price = $request->new_buy_price;     // ← UPDATED
    $myprice->max_discount = $request->max_discount ?? 0;  // ← UPDATED
    
    // Update pieces pricing if applicable
    $myprice->half_price = 0;
    $myprice->pieces_price = $request->pieces_price ?? 0;
    $myprice->pieces_max_discount = $request->pieces_max_discount ?? 0;
    
    $myprice->status = 1;
    
    if ($myprice->update()) {
        // *** IMPORTANT: Observer NOT triggered on update() ***
        // Only on create() - be careful!
        
        return redirect(route('products.index'))
            ->withMessage('Price updated successfully');
    }
}
```

### 4.3 Price Model Structure

**File**: `app/Models/Price.php`

```php
class Price extends Model
{
    protected $fillable = [
        'product_id',
        'pr_buy_price',           // Cost price
        'initial_sale_price',     // Starting price
        'current_sale_price',     // ← ACTIVE PRICE (Used in sales, tariffs)
        'max_discount',           // Maximum discount
        'status',
    ];
    
    public function product() 
    {
        return $this->belongsTo(Product::class);
    }
}
```

**Key Column**: `current_sale_price` is the active price used throughout the system.

---

## 5. Database Impact {#database-impact}

### Price Table Schema

```sql
CREATE TABLE prices (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT,
    pr_buy_price DECIMAL(10,2),           -- Cost price
    initial_sale_price DECIMAL(10,2),     -- Starting price
    current_sale_price DECIMAL(10,2),     -- ← ACTIVE PRICE
    initial_sale_date TIMESTAMP,
    current_sale_date TIMESTAMP,
    max_discount DECIMAL(10,2),
    pieces_price DECIMAL(10,2),           -- Price per piece (if enabled)
    pieces_max_discount DECIMAL(10,2),
    half_price DECIMAL(10,2),             -- Price for half unit (if enabled)
    status INT DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (product_id) REFERENCES products(id),
    UNIQUE KEY unique_product (product_id)
);
```

### When Price is Updated

**Direct Change**:
```sql
-- Admin updates from prices.edit form
UPDATE prices 
SET current_sale_price = 199.99, 
    pr_buy_price = 100.00,
    max_discount = 50.00,
    current_sale_date = NOW()
WHERE id = 123;
```

**Tariff Impact**:
- Price update does **NOT** automatically trigger tariff updates
- For auto-update, need to use PriceObserver on `created` event
- For manual updates, tariffs would need manual adjustment or batch update

---

## 6. Observer Integration {#observer-integration}

### PriceObserver - Triggered on NEW Price

**File**: `app/Observers/PriceObserver.php`

```php
public function created(Price $price)
{
    $this->updateOrCreateTariffsWithPrice($price);
}

protected function updateOrCreateTariffsWithPrice(Price $price)
{
    $salePrice = $price->current_sale_price ?? 0;
    $productId = $price->product_id;
    
    // Get all active HMOs
    $hmos = Hmo::where('status', 1)->get();
    
    foreach ($hmos as $hmo) {
        // Try to find existing tariff
        $tariff = HmoTariff::where('hmo_id', $hmo->id)
            ->where('product_id', $productId)
            -> whereNull('service_id')
            ->first();
        
        if ($tariff) {
            // Only update if payable_amount is 0 (not manually configured)
            if ($tariff->payable_amount == 0) {
                $tariff->update(['payable_amount' => $salePrice]);
                $updated++;
            }
        } else {
            // Create new tariff with price
            HmoTariff::create([
                'hmo_id' => $hmo->id,
                'product_id' => $productId,
                'service_id' => null,
                'claims_amount' => 0,
                'payable_amount' => $salePrice,
                'coverage_mode' => 'primary',
            ]);
            $created++;
        }
    }
    
    Log::info("PriceObserver: Product {$productId} - Created {$created}, Updated {$updated}");
}
```

### Registration

**File**: `app/Providers/AppServiceProvider.php`

```php
public function boot()
{
    Price::observe(PriceObserver::class);
}
```

### Tariff Update Flow

```
Admin enters price → POST to prices.store
        ↓
PriceController::store() creates Price record
        ↓
Price::save() triggers model events
        ↓
PriceObserver::created() fires automatically
        ↓
For each active HMO:
  ├─ Check if tariff exists
  ├─ If NO → Create tariff with new price
  ├─ If YES and payable_amount == 0 → Update with new price
  └─ If YES and payable_amount > 0 → Keep manual price
        ↓
Log: "Created X, Updated Y tariffs"
```

---

## 7. Complete Workflow Example

### Scenario: Add Price to New Product

**Step 1**: Admin goes to Products list
- Sees "Paracetamol 500mg" with no price
- Clicks "Add Price" button → `prices.show`

**Step 2**: `PriceController::show()` loads form
```php
$products = Product::find(123);  // Paracetamol
```

**Step 3**: Admin fills form (newprice.blade.php)
```
Buy Price: 80.00
Sale Price: 150.00
Max Discount: 10.00
```

**Step 4**: Admin submits → POST to `prices.store`

**Step 5**: `PriceController::store()` creates Price
```php
$price = new Price();
$price->product_id = 123;
$price->current_sale_price = 150.00;
$price->pr_buy_price = 80.00;
$price->save();  // ← Triggers observer
```

**Step 6**: `PriceObserver::created()` fires
```
Query: Find all HMOs (5 found)
For each HMO (1-5):
  Create tariff with payable_amount = 150.00
Result: 5 new tariffs created
Log: "PriceObserver: Product 123 - Created 5 HMO tariffs"
```

**Step 7**: Admin redirected to products list
- Success message: "Price created successfully"
- Now sees "Adjust Price" button instead of "Add Price"

---

### Scenario: Update Existing Price

**Step 1**: Admin clicks "Adjust Price" → `prices.edit`

**Step 2**: Form shows (edit.blade.php)
```
Current:
  Buy Price: 80.00
  Sale Price: 150.00
  
New Values: [Empty input fields]
```

**Step 3**: Admin updates (new prices)
```
New Buy Price: 85.00
New Sale Price: 175.00
Max Discount: 15.00
```

**Step 4**: Admin submits → PUT to `prices.update`

**Step 5**: `PriceController::update()` updates Price
```php
$price = Price::find(123);
$price->current_sale_price = 175.00;
$price->pr_buy_price = 85.00;
$price->update();
```

**Step 6**: In database: Price record updated
```sql
UPDATE prices 
SET current_sale_price = 175.00,
    pr_buy_price = 85.00,
    current_sale_date = NOW()
WHERE id = 123;
```

**Step 7**: ⚠️ **Tariffs NOT automatically updated**
- Because observer only fires on `created()`, not `update()`
- Tariffs still have old payable_amount = 150.00
- **This is a gap** - tariffs should be synced

---

## 8. Important Notes & Gotchas

### ⚠️ Gap: Tariff Not Updated on Price Update
- PriceObserver only has `created()` method, not `updated()`
- If price is edited via `prices.update`, tariffs don't auto-update
- Manual tariff adjustment needed OR tariffs keep old price
- **Solution**: Should add `updated()` handler to PriceObserver

### ✅ Separate Concerns
- Product CRUD only touches product table
- Price CRUD only touches price table
- Clean separation between product info and pricing

### ✅ Bulk Operations
- Creating a tariff for each HMO uses `HmoTariff::create()` (slower)
- Could optimize with `HmoTariff::insert()` for bulk insert

### ✅ Manual vs Auto Pricing
- `payable_amount == 0` = auto-generated, can be auto-updated
- `payable_amount > 0` = manually configured, preserved

---

## Summary Table

| Operation | File | Route | Triggers |
|-----------|------|-------|----------|
| View product list | product/_index | products.index | - |
| Edit product | product/edit | products.edit | product update |
| Add price | prices/newprice | prices.show | form page |
| Submit new price | - | prices.store | PriceObserver::created() |
| Edit price | prices/edit | prices.edit | form page |
| Update price | - | prices.update | No observer! |

---

## Files Reference

### Controllers
- `app/Http/Controllers/Product/productController.php` - Product CRUD
- `app/Http/Controllers/PriceController.php` - Price CRUD

### Views
- `resources/views/admin/product/edit.blade.php` - Edit product
- `resources/views/admin/prices/newprice.blade.php` - Add price
- `resources/views/admin/prices/edit.blade.php` - Edit price
- `resources/views/admin/product/_index.blade.php` - Product list

### Models
- `app/Models/Product.php` - Product model
- `app/Models/Price.php` - Price model
- `app/Models/HmoTariff.php` - HMO tariff model

### Observers
- `app/Observers/PriceObserver.php` - Price creation observer
- `app/Providers/AppServiceProvider.php` - Observer registration
