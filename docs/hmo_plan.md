# HMO Billing System Implementation Prompt

You are tasked with implementing a comprehensive HMO (Health Maintenance Organization) billing system into an existing medical billing application. Read all sections carefully before beginning implementation.

---

## Current System Understanding

### Existing Architecture & Payment Flow

#### Database Tables
- **`product_or_service_requests`**: Main billing table
  - Columns: `id`, `invoice_id`, `user_id`, `staff_user_id`, `product_id`, `service_id`, `payment_id`, `qty`, `discount`, `created_at`, `updated_at`
  - **Critical**: Stores ONLY IDs (product_id, service_id), NOT amounts
  - Links to patient via `user_id` (patient's user_id)
  - Links to payment via `payment_id` (nullable until paid)
  
- **`payments`**: Payment records
  - Columns: `id`, `reference_no`, `total`, `payment_type`, `invoice_id`, `patient_id`, `user_id` (staff), `hmo_id`, `total_discount`, `created_at`, `updated_at`
  - Payment types include: `POS`, `CASH`, `TRANSFER`, `TELLER`, `CHEQUE`, `ACC_WITHDRAW`, `CLAIMS`
  
- **`products`**: Product catalog
  - Has one-to-one relationship with `prices` table via `price()` method
  - Price accessed via: `$product->price->current_sale_price`
  
- **`services`**: Service catalog
  - Has one-to-one relationship with `service_prices` table via `price()` method
  - Price accessed via: `$service->price->sale_price`

- **`service_prices`**: Service pricing
  - Columns: `service_id`, `cost_price`, `sale_price`, `max_discount`, `status`

- **`hmos`**: HMO organizations
  - Columns: `id`, `name`, `desc`, `status`, `discount`, `hmo_scheme_id`
  - Related to patients via `hmo_id` on patients table

#### Payment Flow (3 Pages)

**Page 1: Patient List** (`/product-or-service-request`)
- Route: `Route::resource('product-or-service-request', ProductOrServiceRequestController::class)`
- Controller: `ProductOrServiceRequestController@index`
- View: `admin.product_or_service_request.index`
- Purpose: Lists all patients with unpaid bills
- Query: `ProductOrServiceRequest::where('payment_id', '=', null)->groupBy('user_id')`
- Shows: Patient name, file_no, HMO, HMO number
- Action: Click "View" to go to Page 2

**Page 2: Service/Product Selection** (`/servicess/{user_id}`)
- Route: `Route::get('servicess/{id}', [accountsController::class, 'index'])`
- Controller: `accountsController@index` → shows view, then `accountsController@services()` and `accountsController@products()` for DataTables
- View: `admin.Accounts.services`
- Purpose: Shows all unpaid services and products for selected patient
- Query: `ProductOrServiceRequest::where('user_id', $id)->where('invoice_id', NULL)`
- Features:
  - Two DataTables: Services and Products
  - User selects items with checkboxes
  - User can adjust quantities
  - Form submits to `/service-payment` (POST)
- Price Display: Fetched from related model
  - Services: `$service->service->price->sale_price`
  - Products: `$product->product->price->current_sale_price`

**Page 3: Payment Summary & Completion** (`/service-payment` POST → summary view)
- Route: `Route::post('service-payment', [paymentController::class, 'process'])`
- Controller: `paymentController@process` → shows summary, then `paymentController@payment()` completes payment
- Views: 
  - Summary: `admin.Accounts.summary`
  - Completion route: `/complete-payment`
- Purpose: Review selected items, apply discounts, choose payment method, complete payment
- Process:
  1. `process()` method calculates totals using prices from related models
  2. Shows summary page with discount options
  3. User selects payment type (including **CLAIMS**)
  4. User submits to `/complete-payment`
  5. `payment()` method creates payment record and links requests via `payment_id`

### Critical Current Behavior

**Price Fetching Logic:**
- In `accountsController@services()` and `products()`: Displays prices from related models
- In `paymentController@process()`: Calculates totals using:
  ```php
  // Services
  $total = $service->service->price->sale_price * $qty;
  
  // Products  
  $total = $product->product->price->current_sale_price * $qty;
  ```
- **This behavior MUST change for HMO patients** to use tariff-based pricing

**Current CLAIMS Payment Method:**
- In `summary.blade.php`, payment types include: `<option value="CLAIMS">Claims</option>`
- In `paymentController@payment()`, when payment_type is 'CLAIMS':
  ```php
  if (strtolower($request->payment_type) == 'claims') {
      $patient = \App\Models\patient::find($request->patient_id);
      $hmo_id = $patient && $patient->hmo_id ? $patient->hmo_id : null;
  }
  ```
- Creates payment with `hmo_id` populated
- Receipt notes say: "Payment Billed to claims, No cash was received, Cash to be claimed from HMO"
- **IMPORTANT**: This CLAIMS payment method should be REMOVED as claims will be handled through HMO validation workflow instead

---

## Implementation Requirements

## 1. Database Schema

### Create New Table: `hmo_tariffs`

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
    CHECK ((product_id IS NOT NULL AND service_id IS NULL) OR (product_id IS NULL AND service_id IS NOT NULL))
);
```

**Field Descriptions**:
- `claims_amount`: Amount the HMO will pay (0 means HMO doesn't cover this service/product)
- `payable_amount`: Amount the patient must pay (0 means HMO covers 100%)
- `coverage_mode`:
  - `express`: Auto-approved, no validation needed
  - `primary`: Requires HMO executive validation before patient can access service
  - `secondary`: Requires HMO executive validation + authorization code before patient can access service

**Default Values When Creating New Tariffs**:
- `claims_amount`: 0
- `payable_amount`: Should equal the price from the product or service model

### Modify Existing Table: `product_or_service_request`

Add these columns to the existing table structure:

```sql
ALTER TABLE product_or_service_requests
ADD COLUMN payable_amount DECIMAL(10,2) NULL COMMENT 'Amount patient must pay (from tariff)',
ADD COLUMN claims_amount DECIMAL(10,2) NULL DEFAULT 0 COMMENT 'Amount HMO will pay (from tariff)',
ADD COLUMN coverage_mode VARCHAR(20) NULL COMMENT 'express, primary, or secondary',
ADD COLUMN validation_status ENUM('pending', 'approved', 'rejected') NULL COMMENT 'HMO validation status',
ADD COLUMN auth_code VARCHAR(100) NULL COMMENT 'Authorization code for secondary coverage',
ADD COLUMN validated_by BIGINT NULL COMMENT 'User ID of HMO executive who validated',
ADD COLUMN validated_at TIMESTAMP NULL COMMENT 'When validation occurred',
ADD COLUMN validation_notes TEXT NULL COMMENT 'Notes from HMO executive during validation',
ADD FOREIGN KEY (validated_by) REFERENCES users(id) ON DELETE SET NULL;
```

**Existing Columns** (for reference):
- `id`, `invoice_id`, `user_id`, `staff_user_id`, `product_id`, `service_id`, `payment_id`, `qty`, `discount`, `created_at`, `updated_at`

**Critical Implementation Note**: 
- For HMO patients: `payable_amount` and `claims_amount` must be populated from the tariff when the request is created
- For non-HMO patients: These fields remain NULL, and the system continues to fetch prices from product/service models
- The billing logic MUST check if `payable_amount` is set; if so, use it. If NULL, fall back to fetching from product/service model

**Model Update Required**:
Update `ProductOrServiceRequest` model fillable array:
```php
protected $fillable = [
    'invoice_id',
    'user_id',
    'staff_user_id',
    'product_id',
    'payment_id',
    'qty',
    'service_id',
    'discount',
    'payable_amount',      // NEW
    'claims_amount',       // NEW
    'coverage_mode',       // NEW
    'validation_status',   // NEW
    'auth_code',          // NEW
    'validated_by',       // NEW
    'validated_at',       // NEW
    'validation_notes',   // NEW
];
```

---

## 2. Tariff Auto-Generation

### Requirement
Automatically create tariff entries for every HMO-Product and HMO-Service combination when new products/services are created or when new HMOs are added.

### Implementation Instructions

Create a function that:
1. Checks if a tariff entry exists for a given HMO-Product or HMO-Service combination
2. If not, creates one with:
   - `claims_amount`: 0
   - `payable_amount`: Price from the product or service model
   - `coverage_mode`: 'primary' (default)

**Where to Implement**:
- Add observers to Product and Service models (ProductObserver, ServiceObserver)
- When a product/service is created, generate tariff entries for all existing HMOs
- Add observer to HMO model (HmoObserver)
- When an HMO is created, generate tariff entries for all existing products and services
- Use bulk insert operations for performance

**Example Logic**:
```php
// Pseudo-code
function generateTariffsForProduct($product) {
    $hmos = HMO::all();
    $tariffs = [];
    
    foreach ($hmos as $hmo) {
        if (!HmoTariff::where('hmo_id', $hmo->id)
                      ->where('product_id', $product->id)
                      ->exists()) {
            $tariffs[] = [
                'hmo_id' => $hmo->id,
                'product_id' => $product->id,
                'service_id' => null,
                'claims_amount' => 0,
                'payable_amount' => $product->price,
                'coverage_mode' => 'primary',
            ];
        }
    }
    
    if (!empty($tariffs)) {
        HmoTariff::insert($tariffs);
    }
}
```

---

## 3. Request Creation Flow Modification

### Current Behavior
When a request is created, only IDs are stored. Amounts are fetched at billing time.

### New Behavior for HMO Patients

When creating a `product_or_service_request` for an HMO patient:

1. **Check if patient has HMO**
2. **Fetch the tariff** for the HMO-Product or HMO-Service combination
3. **Populate the request fields**:
   - `payable_amount`: From tariff
   - `claims_amount`: From tariff
   - `coverage_mode`: From tariff
   - `validation_status`: 
     - 'approved' if coverage_mode is 'express'
     - 'pending' if coverage_mode is 'primary' or 'secondary'
4. **Service Access Logic**:
   - Express: Patient can access immediately
   - Primary/Secondary: Patient CANNOT access until validation_status is 'approved'

### Implementation Points

**Modify request creation logic in**:
- Lab Workbench (LabWorkbenchController)
- Encounter page controller (EncounterController)
- Patient show page controller (PatientController)
- Any other location where ProductOrServiceRequest records are created

**Identify all ProductOrServiceRequest creation points**:
Search codebase for: `ProductOrServiceRequest::create()` or `new ProductOrServiceRequest()`

Common patterns found:
```php
// Example from ProductOrServiceRequestController@store
$req = new ProductOrServiceRequest();
$req->service_id = $serviceId;
$req->patient_id = $patientId;
$req->user_id = $patient->user_id;
$req->staff_user_id = Auth::id();
$req->qty = $qty;
$req->save();
```

**Add HMO tariff logic to each creation point**:
```php
// Get patient to check HMO status
$patient = \App\Models\patient::find($patientId);

$req = new ProductOrServiceRequest();
$req->service_id = $serviceId ?? null;
$req->product_id = $productId ?? null;
$req->user_id = $patient->user_id;
$req->staff_user_id = Auth::id();
$req->qty = $qty ?? 1;

// HMO Tariff Logic - NEW
if ($patient && $patient->hmo_id) {
    $tariff = \App\Models\HmoTariff::where('hmo_id', $patient->hmo_id)
        ->where(function($q) use ($productId, $serviceId) {
            if ($productId) {
                $q->where('product_id', $productId)->whereNull('service_id');
            } else {
                $q->where('service_id', $serviceId)->whereNull('product_id');
            }
        })
        ->first();
    
    if ($tariff) {
        $req->payable_amount = $tariff->payable_amount;
        $req->claims_amount = $tariff->claims_amount;
        $req->coverage_mode = $tariff->coverage_mode;
        
        // Set validation status based on coverage mode
        if ($tariff->coverage_mode === 'express') {
            $req->validation_status = 'approved';
        } else {
            // primary or secondary - requires validation
            $req->validation_status = 'pending';
        }
    } else {
        // Tariff doesn't exist - handle based on business rules
        // Option A: Throw error
        throw new \Exception("No HMO tariff found for this service/product. Please contact administrator.");
        
        // Option B: Fall back to regular pricing (patient pays full amount)
        // $req->payable_amount = null; // Will use regular pricing
        // $req->claims_amount = 0;
        // $req->coverage_mode = null;
        // $req->validation_status = null;
    }
}
// End HMO Logic

$req->save();
```

**Alternative: Create a helper method**:
Create `app/Helpers/HmoHelper.php`:
```php
<?php

namespace App\Helpers;

use App\Models\HmoTariff;
use App\Models\patient;

class HmoHelper
{
    /**
     * Apply HMO tariff to a ProductOrServiceRequest
     *
     * @param int $patientId
     * @param int|null $productId
     * @param int|null $serviceId
     * @return array ['payable_amount', 'claims_amount', 'coverage_mode', 'validation_status'] or null
     */
    public static function applyHmoTariff($patientId, $productId = null, $serviceId = null)
    {
        $patient = patient::find($patientId);
        
        if (!$patient || !$patient->hmo_id) {
            return null; // Not an HMO patient
        }
        
        $tariff = HmoTariff::where('hmo_id', $patient->hmo_id)
            ->where(function($q) use ($productId, $serviceId) {
                if ($productId) {
                    $q->where('product_id', $productId)->whereNull('service_id');
                } else {
                    $q->where('service_id', $serviceId)->whereNull('product_id');
                }
            })
            ->first();
        
        if (!$tariff) {
            throw new \Exception("No HMO tariff found for this service/product. Please contact administrator.");
        }
        
        return [
            'payable_amount' => $tariff->payable_amount,
            'claims_amount' => $tariff->claims_amount,
            'coverage_mode' => $tariff->coverage_mode,
            'validation_status' => $tariff->coverage_mode === 'express' ? 'approved' : 'pending',
        ];
    }
}
```

Then use it:
```php
use App\Helpers\HmoHelper;

$req = new ProductOrServiceRequest();
$req->service_id = $serviceId ?? null;
$req->product_id = $productId ?? null;
$req->user_id = $patient->user_id;
$req->staff_user_id = Auth::id();
$req->qty = $qty ?? 1;

// Apply HMO tariff if applicable
$hmoData = HmoHelper::applyHmoTariff($patientId, $productId, $serviceId);
if ($hmoData) {
    $req->payable_amount = $hmoData['payable_amount'];
    $req->claims_amount = $hmoData['claims_amount'];
    $req->coverage_mode = $hmoData['coverage_mode'];
    $req->validation_status = $hmoData['validation_status'];
}

$req->save();
```

---

## 4. Billing Logic Modification

### Critical Changes Required

The billing flow involves multiple controllers that need modification:

#### A. accountsController (Service/Product Selection Page)

**File**: `app/Http/Controllers/Account/accountsController.php`

**Methods to Update**:
1. `services($id)` - Line ~55
2. `products($id)` - Line ~20
3. `mergedList($id)` - Line ~107

**Current logic** (in DataTables columns):
```php
// Services
'price' => $item->service && $item->service->price 
    ? $item->service->price->sale_price 
    : ''

// Products
'price' => $item->product && $item->product->price 
    ? $item->product->price->current_sale_price 
    : ''
```

**New logic**:
```php
// Services
'price' => $item->payable_amount !== null 
    ? $item->payable_amount 
    : ($item->service && $item->service->price 
        ? $item->service->price->sale_price 
        : 0)

// Products
'price' => $item->payable_amount !== null 
    ? $item->payable_amount 
    : ($item->product && $item->product->price 
        ? $item->product->price->current_sale_price 
        : 0)
```

**Additional Display Logic**:
Add badges to show HMO coverage status:
```php
// In mergedList and other methods, add coverage badge
'coverage_badge' => $item->coverage_mode 
    ? '<span class="badge badge-' . 
        ($item->coverage_mode === 'express' ? 'success' : 
        ($item->coverage_mode === 'primary' ? 'warning' : 'danger')) . 
        '">' . strtoupper($item->coverage_mode) . '</span>' 
    : '',
```

#### B. paymentController (Summary & Payment Processing)

**File**: `app/Http/Controllers/Account/paymentController.php`

**Method**: `process(Request $request)` - Line ~23

**Current logic** (calculating totals):
```php
// Services
$services = ProductOrServiceRequest::with('service.price')
    ->whereIn('id', array_values($checkboxServices))
    ->get();
    
$total = 0;
for ($i = 0; $i < count($services); ++$i) {
    $total += $services[$i]->service->price->sale_price * $serviceQty[$i];
}

// Products
$products = ProductOrServiceRequest::with('product.price')
    ->whereIn('id', array_values($inputs))
    ->get();
    
$productsTotal = 0;
for ($j = 0; $j < count($products); ++$j) {
    $productsTotal += $products[$j]->product->price->current_sale_price * $productQty[$j];
}
```

**New logic**:
```php
// Services
$services = ProductOrServiceRequest::with('service.price')
    ->whereIn('id', array_values($checkboxServices))
    ->get();
    
$total = 0;
for ($i = 0; $i < count($services); ++$i) {
    // Use payable_amount if set (HMO patient), otherwise use regular price
    $price = $services[$i]->payable_amount !== null 
        ? $services[$i]->payable_amount 
        : $services[$i]->service->price->sale_price;
    $total += $price * $serviceQty[$i];
}

// Products
$products = ProductOrServiceRequest::with('product.price')
    ->whereIn('id', array_values($inputs))
    ->get();
    
$productsTotal = 0;
for ($j = 0; $j < count($products); ++$j) {
    // Use payable_amount if set (HMO patient), otherwise use regular price
    $price = $products[$j]->payable_amount !== null 
        ? $products[$j]->payable_amount 
        : $products[$j]->product->price->current_sale_price;
    $productsTotal += $price * $productQty[$j];
}
```

**Method**: `payment(Request $request)` - Line ~78

**Remove CLAIMS payment option**:
```php
// REMOVE THIS SECTION
if (strtolower($request->payment_type) == 'claims') {
    $patient = \App\Models\patient::find($request->patient_id);
    $hmo_id = $patient && $patient->hmo_id ? $patient->hmo_id : null;
}

// REMOVE from payment creation
$payment = payment::create([
    'payment_type' => $request->payment_type,
    'total' => $request->total,
    'total_discount' => $totalDiscount,
    'reference_no' => $request->reference_no,
    'user_id' => Auth::id(),
    'patient_id' => $request->patient_id,
    // 'hmo_id' => $hmo_id,  // REMOVE THIS LINE
]);

// REMOVE notes section for CLAIMS
// Delete this elseif block:
elseif (strtolower($request->payment_type) == strtolower('CLAIMS')) {
    $notes = [
        'Payment Billed to claims',
        'No cash was recieved',
        'Cash to be claimed from HMO',
    ];
}
```

**Add HMO Claims Tracking**:
After payment is successful, create claim records for HMO patients:
```php
// After payment creation, before DB::commit()
if ($payment) {
    // Track HMO claims separately
    $claimsTotal = 0;
    $patient = \App\Models\patient::find($request->patient_id);
    
    if ($patient && $patient->hmo_id) {
        // Process services
        if (null != session('selected')) {
            $services = ProductOrServiceRequest::whereIn('id', array_values(session('selected')))->get();
            foreach ($services as $service) {
                if ($service->claims_amount > 0) {
                    $claimsTotal += $service->claims_amount * ($serviceQty[$u] ?? 1);
                }
            }
        }
        
        // Process products
        if (session('products') != null) {
            $products = ProductOrServiceRequest::whereIn('id', array_values(session('products')))->get();
            foreach ($products as $product) {
                if ($product->claims_amount > 0) {
                    $claimsTotal += $product->claims_amount * ($productQty[$l] ?? 1);
                }
            }
        }
        
        // Create HMO claim entry if there are claims
        if ($claimsTotal > 0) {
            \App\Models\HmoClaim::create([
                'hmo_id' => $patient->hmo_id,
                'patient_id' => $patient->id,
                'payment_id' => $payment->id,
                'claims_amount' => $claimsTotal,
                'status' => 'pending', // or 'approved' depending on coverage_mode
                'created_by' => Auth::id(),
            ]);
        }
    }
}
```

#### C. View Updates

**File**: `resources/views/admin/Accounts/summary.blade.php`

**Remove CLAIMS option from payment type dropdown** (around line 140):
```php
// DELETE THIS LINE:
<option value="CLAIMS">Claims</option>
```

**Update price display to show HMO pricing**:
```php
// Services table - add coverage indicator
<td>
    <span>&#8358;</span>
    <span class="service-price">{{ $service->payable_amount ?? $service->service->price->sale_price ?? 0 }}</span>
    <input type="hidden" name="servicePrice[]" value="{{ $service->payable_amount ?? $service->service->price->sale_price ?? 0 }}">
    @if($service->payable_amount !== null && $service->claims_amount > 0)
        <br><small class="text-success">HMO covers: &#8358;{{ $service->claims_amount }}</small>
    @endif
</td>

// Products table - add coverage indicator  
<td>
    <span>&#8358;</span>
    <span class="product-price">{{ $product->payable_amount ?? $product->product->price->current_sale_price }}</span>
    <input type="hidden" name="productPrice[]" value="{{ $product->payable_amount ?? $product->product->price->current_sale_price }}">
    @if($product->payable_amount !== null && $product->claims_amount > 0)
        <br><small class="text-success">HMO covers: &#8358;{{ $product->claims_amount }}</small>
    @endif
</td>
```

### Claims Tracking

**New Table Required**: `hmo_claims`
```sql
CREATE TABLE hmo_claims (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    hmo_id BIGINT NOT NULL,
    patient_id BIGINT NOT NULL,
    payment_id BIGINT NOT NULL,
    claims_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'paid') NOT NULL DEFAULT 'pending',
    created_by BIGINT NOT NULL,
    processed_by BIGINT NULL,
    processed_at TIMESTAMP NULL,
    payment_reference VARCHAR(100) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (hmo_id) REFERENCES hmos(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
);
```

---

## 5. Tariff Management Page

### Location
- Add to Admin section, immediately after "Hospital Config"
- Route: `/admin/hmo-tariffs` or `/admin/tariff-management`

### Features to Implement

#### A. DataTables Display

Create a DataTables component showing all tariffs with:

**Columns**:
- HMO Name (from relationship)
- Product Name (from relationship, show "N/A" if null)
- Service Name (from relationship, show "N/A" if null)
- Claims Amount
- Payable Amount  
- Coverage Mode
- Actions (Edit, Delete buttons)

**Filters**:
- HMO dropdown filter
- Product/Service search filter
- Coverage mode filter

**Functionality**:
- Sorting on all columns
- Pagination
- Search across all fields

#### B. Tariff Entry Form

Create a form for adding/editing single tariffs:

**Fields**:
- HMO (dropdown, required)
- Product OR Service (dropdown, required - only one can be selected)
- Claims Amount (number input, min: 0, required)
- Payable Amount (number input, min: 0, required)
- Coverage Mode (dropdown: express, primary, secondary, required)
- Submit button

**Validation**:
- Ensure HMO is selected
- Ensure either Product OR Service is selected (not both, not neither)
- Ensure amounts are non-negative decimals
- Check for duplicate HMO-Product/Service combinations
- Show validation errors clearly

**Behavior**:
- On submit, save to `hmo_tariffs` table
- Show success message
- Refresh DataTables
- Clear form

#### C. CSV Upload/Download

**Download Feature**:
- Button: "Export Tariffs to CSV"
- Generate CSV with columns:
  - HMO ID
  - HMO Name
  - Product ID
  - Product Name
  - Service ID
  - Service Name
  - Claims Amount
  - Payable Amount
  - Coverage Mode
- Include all current tariffs
- Filename: `hmo_tariffs_YYYY-MM-DD.csv`

**Upload Feature**:
- File input accepting .csv files
- Button: "Import Tariffs from CSV"
- Expected CSV format (same as download):
  - Header row: HMO_ID, HMO_Name, Product_ID, Product_Name, Service_ID, Service_Name, Claims_Amount, Payable_Amount, Coverage_Mode
  - Data rows matching format
  
**Upload Processing**:
1. Validate CSV structure
2. For each row:
   - Validate HMO exists (use HMO_ID or HMO_Name)
   - Validate Product or Service exists
   - Validate amounts are numeric and non-negative
   - Validate coverage_mode is one of: express, primary, secondary
   - Check if tariff exists:
     - If exists: Update it
     - If doesn't exist: Create it
3. Show summary report:
   - Rows processed
   - Rows created
   - Rows updated
   - Rows failed (with error details)

**Error Handling**:
- Display clear error messages for invalid rows
- Don't stop processing on single row failure
- Show which rows failed and why

#### D. Bulk Operations (Optional but Recommended)

Add functionality for:
- **Bulk Update Coverage Mode**: Select multiple tariffs and change coverage mode
- **Bulk Update Amounts**: Apply percentage increase/decrease to selected tariffs
- **Copy Tariffs**: Copy all tariffs from one HMO to another HMO

---

## 6. HMO Executive Section in Sidebar

### Create New Section

Add a new section to the application sidebar:

**Label**: "HMO Management" or "HMO Executive"

**Links**:
1. HMO Workbench (primary link)
2. Claims Reports (can be placeholder for now)
3. HMO Settings (can be placeholder for now)

**Position**: After an appropriate existing section (use your judgment based on current sidebar structure)

**Icon**: Use an appropriate icon (medical/insurance related)

**Permissions**: Only visible to users with "HMO Executive" role

---

## 7. HMO Workbench Implementation

### Design Approach
**IMPORTANT**: Copy the exact structure and layout from the existing Lab Workbench.

### Extract Shared Components

Before building HMO Workbench, extract these components from Lab Workbench into reusable partials:

1. **Search Pane Component**
   - Location: Create as `resources/views/partials/search-pane.blade.php` (or equivalent for your framework)
   - Should accept parameters for:
     - Search placeholder text
     - Available filters
     - Search callback function
   
2. **Clinical Context Modal**
   - Location: Create as `resources/views/partials/clinical-context-modal.blade.php`
   - Should display patient clinical information
   - Should be reusable across different workbenches

3. **Any other shared UI elements** you identify in Lab Workbench

### HMO Workbench Features

#### A. Main Interface Layout

Copy the layout from Lab Workbench with these adaptations:

**Top Section**:
- Page title: "HMO Workbench"
- Include the extracted search pane component
- Date range filter
- HMO filter dropdown (if executive manages multiple HMOs)

**Tabs/Filters**:
Create tabs for:
1. **Pending Validation**: Shows requests with validation_status = 'pending' and coverage_mode IN ('primary', 'secondary')
2. **Express (Auto-Approved)**: Shows requests with coverage_mode = 'express' (read-only)
3. **Approved**: Shows requests with validation_status = 'approved'
4. **Rejected**: Shows requests with validation_status = 'rejected'
5. **All Requests**: Shows all requests

#### B. Requests Table

Display requests in a DataTables format with columns:

- Request ID
- Request Date
- Patient Name (clickable to view patient details)
- Patient ID
- Service/Product Name
- Coverage Mode (badge: green for express, yellow for primary, red for secondary)
- Payable Amount (what patient pays)
- Claims Amount (what HMO pays)
- Status (badge: pending/approved/rejected)
- Actions column

**Actions Column** (conditional based on status and coverage_mode):
- For pending + primary: "Validate" button
- For pending + secondary: "Validate with Auth Code" button
- For all: "View Details" button
- For approved: "View Validation Details"

#### C. Validation Modal/Interface

**For Primary Coverage**:

Create a modal or page section that displays:
- Patient Information:
  - Name, ID, Age, Gender
  - HMO details
  - Contact information
- Request Details:
  - Service/Product name and description
  - Request date and time
  - Requesting doctor/staff
  - Claims amount (what HMO will pay)
  - Payable amount (what patient will pay)
- Clinical Context:
  - Use the extracted clinical context modal component
  - Show patient history, diagnoses, allergies, etc.
- Validation Form:
  - Notes/Comments textarea
  - Approve button (green)
  - Reject button (red)

**On Approve**:
```php
// Pseudo-code
$request->validation_status = 'approved';
$request->validated_by = auth()->user()->id;
$request->validated_at = now();
$request->save();
// Show success message
// Close modal
// Refresh table
```

**On Reject**:
```php
// Pseudo-code
$request->validation_status = 'rejected';
$request->validated_by = auth()->user()->id;
$request->validated_at = now();
// Optionally capture rejection reason
$request->save();
// Show success message
// Close modal
// Refresh table
```

**For Secondary Coverage**:

Same as primary, but add:
- **Auth Code Input Field** (required)
  - Text input
  - Label: "Authorization Code"
  - Validation: Required, minimum length (define as needed)
  
**On Approve**:
```php
// Pseudo-code
// Validate auth code is provided
if (empty($authCode)) {
    return error("Authorization code is required for secondary coverage");
}

$request->auth_code = $authCode;
$request->validation_status = 'approved';
$request->validated_by = auth()->user()->id;
$request->validated_at = now();
$request->save();
// Show success message
// Close modal
// Refresh table
```

#### D. Search and Filter Implementation

Reuse the extracted search pane component with:

**Search by**:
- Patient name
- Patient ID  
- Request ID
- Service/Product name

**Filter by**:
- Date range
- Coverage mode
- Validation status
- HMO (if executive manages multiple)
- Validated by (show own validations vs all)

**Implement**:
- Real-time search (AJAX)
- Clear filters button
- Show active filter badges

#### E. Claims Reports Section

Create a reports section within the workbench showing:

**Summary Statistics Cards**:
- Total Pending Requests (count)
- Total Approved Today (count and sum of claims_amount)
- Total Rejected Today (count)
- Total Claims This Month (sum of claims_amount for approved requests)
- Average Processing Time (time from request creation to validation)

**Detailed Report Table**:
- Date range selector
- Table showing:
  - Date
  - Total Requests
  - Approved Count
  - Rejected Count
  - Total Claims Amount
  - Total Payable Amount

**Export Options**:
- Export to CSV
- Export to PDF (optional)

**Filters**:
- Date range
- HMO
- Coverage mode

---

## 8. Access Control and Service Delivery

### Implementation Requirements

Before a patient can access/receive a service or product, check:

```php
// Pseudo-code
function canPatientAccessService($request) {
    // Non-HMO patients can always access
    if (is_null($request->coverage_mode)) {
        return true;
    }
    
    // Express coverage - auto approved
    if ($request->coverage_mode === 'express') {
        return true;
    }
    
    // Primary and Secondary - must be approved
    if (in_array($request->coverage_mode, ['primary', 'secondary'])) {
        return $request->validation_status === 'approved';
    }
    
    return false;
}
```

**Integration Points**:
- Add this check to the Lab Workbench before allowing sample collection
- Add this check to the Pharmacy before dispensing medications
- Add this check to any service delivery point
- Display clear message to staff: "This service requires HMO approval. Please contact HMO executive."

---

## 9. Testing Requirements

Before deploying, test the following scenarios:

### Scenario 1: Non-HMO Patient
1. Create a service request for a non-HMO patient
2. Verify `payable_amount`, `claims_amount`, etc. are NULL
3. Go to billing
4. Verify amount is fetched from service/product model
5. Complete payment
6. Verify service can be accessed

### Scenario 2: HMO Patient - Express Coverage
1. Create a product for testing (e.g., "X-Ray Test" - $100)
2. Create an HMO for testing (e.g., "Test HMO")
3. Verify tariff was auto-created (or create it manually if observers not yet implemented)
4. Set tariff to: claims_amount = $80, payable_amount = $20, coverage_mode = 'express'
5. Create a patient with this HMO
6. Create a service request for "X-Ray Test"
7. Verify request has: payable_amount = $20, claims_amount = $80, coverage_mode = 'express', validation_status = 'approved'
8. Go to billing
9. Verify amount shown is $20 (not $100)
10. Complete payment
11. Verify service can be accessed immediately

### Scenario 3: HMO Patient - Primary Coverage
1. Set tariff to: claims_amount = $80, payable_amount = $20, coverage_mode = 'primary'
2. Create a service request
3. Verify validation_status = 'pending'
4. Try to access service - should be BLOCKED with message
5. Login as HMO Executive
6. Go to HMO Workbench
7. Verify request appears in "Pending Validation" tab
8. Open validation modal
9. Approve the request
10. Verify validation_status = 'approved', validated_by and validated_at are set
11. Try to access service - should now be ALLOWED
12. Go to billing - verify amount is $20
13. Complete payment

### Scenario 4: HMO Patient - Secondary Coverage
1. Set tariff to: coverage_mode = 'secondary'
2. Create a service request
3. Verify validation_status = 'pending'
4. Login as HMO Executive
5. Try to approve without auth code - should FAIL with error
6. Try to approve with auth code (e.g., "AUTH12345") - should SUCCEED
7. Verify auth_code is stored
8. Verify service can now be accessed

### Scenario 5: Tariff Management
1. Go to tariff management page
2. Create a new tariff manually
3. Verify it appears in DataTables
4. Edit the tariff
5. Verify changes are saved
6. Export tariffs to CSV
7. Verify CSV format is correct
8. Delete a tariff
9. Modify the CSV (change some amounts)
10. Import the CSV
11. Verify changes are applied
12. Test bulk operations (if implemented)

### Scenario 6: Tariff Auto-Generation
1. Create a new HMO
2. Verify tariffs are created for all existing products and services
3. Create a new product
4. Verify tariffs are created for all existing HMOs
5. Create a new service
6. Verify tariffs are created for all existing HMOs

---

## 10. Documentation Requirements

After implementation, create or update:

1. **Admin Documentation**:
   - How to manage HMO tariffs
   - How to use CSV import/export
   - Coverage mode explanations

2. **HMO Executive Documentation**:
   - How to use HMO Workbench
   - Validation workflows
   - When to use auth codes

3. **Staff Documentation**:
   - Changes to billing process
   - How to handle HMO patients
   - What to do when service is blocked pending HMO approval

4. **Technical Documentation**:
   - Database schema changes
   - New models and relationships
   - API endpoints added/modified
   - Observer implementations

---

## 11. Performance Considerations

### Tariff Auto-Generation
- Use queue jobs for bulk tariff generation
- Implement batching when creating tariffs for new HMOs (thousands of products × one HMO)
- Add progress tracking for long-running operations

### DataTables
- Ensure proper indexing on:
  - `hmo_tariffs`: (hmo_id, product_id), (hmo_id, service_id)
  - `product_or_service_request`: (validation_status), (coverage_mode)
- Use server-side processing for large datasets

### Caching
- Consider caching tariffs (they change infrequently)
- Cache HMO patient flag on patient model

---

## 12. Edge Cases to Handle

1. **Tariff doesn't exist for HMO-Product/Service combination**:
   - Option A: Throw error and block request creation
   - Option B: Create tariff on-the-fly with defaults
   - Option C: Fall back to regular pricing (not recommended)
   - **Recommended**: Option A or B depending on business requirements

2. **HMO is deleted**:
   - Tariffs are cascade deleted (handled by foreign key)
   - Consider soft deletes for audit trail

3. **Product/Service price changes**:
   - Tariffs are NOT automatically updated
   - Create admin report showing products/services with price changes to prompt tariff review

4. **Patient changes HMO**:
   - Existing pending requests should be re-evaluated
   - Consider creating a migration script or manual process

5. **Validation is rejected**:
   - Patient cannot access service
   - Consider allowing re-submission or appeal process

6. **Auth code is invalid (for secondary coverage)**:
   - HMO executive should verify with HMO company before approving
   - Consider adding auth code verification API integration if HMO provides one

---

## 13. Security Considerations

1. **Permissions**:
   - Create "HMO Executive" role with appropriate permissions
   - Ensure only HMO executives can validate requests
   - Ensure only admins can manage tariffs

2. **Validation**:
   - Validate all amounts are non-negative
   - Prevent users from manipulating payable_amount or claims_amount directly
   - Ensure validation_status can only be changed through proper workflow

3. **Audit Trail**:
   - Log all tariff changes (who, when, what changed)
   - Log all validation actions
   - Consider creating an audit_log table for this purpose

4. **Data Integrity**:
   - Ensure payable_amount + claims_amount <= original service/product price (or not, depending on business rules)
   - Prevent orphaned records

---

## Summary Checklist

Use this checklist to track implementation progress:

### Phase 1: Database & Models (Week 1)
- [ ] Create `hmo_tariffs` table migration
- [ ] Create `hmo_claims` table migration  
- [ ] Modify `product_or_service_requests` table migration (add HMO columns)
- [ ] Create HmoTariff model (`app/Models/HmoTariff.php`)
- [ ] Create HmoClaim model (`app/Models/HmoClaim.php`)
- [ ] Update ProductOrServiceRequest model fillable array
- [ ] Add relationships to models (Hmo, Patient, ProductOrServiceRequest)
- [ ] Run migrations on development database
- [ ] Test database structure

### Phase 2: Tariff Auto-Generation (Week 1)
- [ ] Create ProductObserver (`app/Observers/ProductObserver.php`)
- [ ] Create ServiceObserver (`app/Observers/ServiceObserver.php`)
- [ ] Create HmoObserver (`app/Observers/HmoObserver.php`)
- [ ] Register observers in `AppServiceProvider`
- [ ] Create HmoHelper class (`app/Helpers/HmoHelper.php`)
- [ ] Add `applyHmoTariff()` method
- [ ] Test observer functionality (create product/service/HMO and verify tariffs)
- [ ] Create artisan command to backfill existing tariffs (`php artisan hmo:generate-tariffs`)

### Phase 3: Request Creation Logic Updates (Week 1-2)
- [ ] Find all ProductOrServiceRequest creation points (grep search)
- [ ] Update LabWorkbenchController (if applicable)
- [ ] Update EncounterController (if applicable)
- [ ] Update PatientController (if applicable)
- [ ] Update ProductOrServiceRequestController@store
- [ ] Update any other controllers creating requests
- [ ] Add validation check: Block request if no tariff exists for HMO patient
- [ ] Test request creation for HMO patients
- [ ] Test request creation for non-HMO patients (ensure no regression)

### Phase 4: Billing Logic Updates (Week 2)
- [ ] Update `accountsController@services()` - Line ~55
  - File: `app/Http/Controllers/Account/accountsController.php`
  - Modify price display to use `payable_amount` if set
- [ ] Update `accountsController@products()` - Line ~20
  - Modify price display to use `payable_amount` if set
- [ ] Update `accountsController@mergedList()` - Line ~107
  - Add coverage badges, use `payable_amount`
- [ ] Update `paymentController@process()` - Line ~23
  - File: `app/Http/Controllers/Account/paymentController.php`
  - Modify total calculation to use `payable_amount`
- [ ] Update `paymentController@payment()` - Line ~78
  - Remove CLAIMS payment type logic
  - Add HmoClaim creation logic
  - Update receipt notes
- [ ] Update `summary.blade.php`
  - File: `resources/views/admin/Accounts/summary.blade.php`
  - Remove CLAIMS option from dropdown (Line ~140)
  - Add HMO coverage display
- [ ] Update `services.blade.php`
  - File: `resources/views/admin/Accounts/services.blade.php`
  - Add coverage badges to DataTables
- [ ] Test billing flow end-to-end for HMO patients
- [ ] Test billing flow end-to-end for non-HMO patients

### Phase 5: Tariff Management Page (Week 2)
- [ ] Create migration for audit logs (optional)
- [ ] Create TariffManagementController (`app/Http/Controllers/Admin/TariffManagementController.php`)
- [ ] Add routes in `routes/web.php`
- [ ] Create index view (`resources/views/admin/tariffs/index.blade.php`)
- [ ] Implement DataTables for tariff listing
- [ ] Create tariff entry form (create/edit)
- [ ] Add validation rules
- [ ] Implement store/update methods
- [ ] Implement delete method
- [ ] Add CSV export functionality
- [ ] Add CSV import functionality
- [ ] Add bulk operations (optional)
- [ ] Update sidebar menu (add after Hospital Config)
- [ ] Test tariff CRUD operations
- [ ] Test CSV export/import

### Phase 6: HMO Executive Section (Week 3)
- [ ] Create "HMO Executive" role and permissions
- [ ] Add HMO Management section to sidebar
  - Update sidebar view file
  - Add permission checks
  - Add icon
- [ ] Extract reusable components from Lab Workbench
  - Create `resources/views/partials/search-pane.blade.php`
  - Create `resources/views/partials/clinical-context-modal.blade.php`
- [ ] Create HmoWorkbenchController (`app/Http/Controllers/HmoWorkbenchController.php`)
- [ ] Add routes for HMO workbench
- [ ] Create workbench index view (`resources/views/admin/hmo/workbench.blade.php`)
- [ ] Implement tabs/filters (Pending, Express, Approved, Rejected, All)
- [ ] Implement DataTables for requests listing
- [ ] Create validation modal view
- [ ] Implement validation endpoints (approve/reject)
- [ ] Add auth code validation for secondary coverage
- [ ] Implement search and filter functionality
- [ ] Create claims reports section
- [ ] Add export functionality for reports
- [ ] Test workbench functionality

### Phase 7: Access Control & Service Delivery (Week 3)
- [ ] Create `canPatientAccessService()` helper method
- [ ] Add check to Lab Workbench before sample collection
  - File: `app/Http/Controllers/LabWorkbenchController.php`
  - Method: `collectSample()`
- [ ] Add check to Pharmacy before dispensing
  - Find pharmacy controller
  - Add validation check
- [ ] Add check to any other service delivery points
- [ ] Update UI to show clear messages when blocked
- [ ] Test access control for all coverage modes
- [ ] Test emergency override (if implemented)

### Phase 8: Testing (Week 3)
- [ ] Test Scenario 1: Non-HMO Patient
- [ ] Test Scenario 2: HMO Patient - Express Coverage
- [ ] Test Scenario 3: HMO Patient - Primary Coverage
- [ ] Test Scenario 4: HMO Patient - Secondary Coverage
- [ ] Test Scenario 5: Tariff Management
- [ ] Test Scenario 6: Tariff Auto-Generation
- [ ] Test edge cases (missing tariffs, HMO deletion, etc.)
- [ ] Performance testing (large tariff datasets)
- [ ] Security testing (permission checks, SQL injection, etc.)

### Phase 9: Documentation & Training (Week 4)
- [ ] Write Admin Documentation
  - How to manage HMO tariffs
  - CSV import/export guide
  - Coverage mode explanations
- [ ] Write HMO Executive Documentation
  - How to use HMO Workbench
  - Validation workflows
  - Auth code procedures
- [ ] Write Staff Documentation
  - Changes to billing process
  - Handling HMO patients
  - What to do when service blocked
- [ ] Write Technical Documentation
  - Database schema changes
  - API endpoints
  - Code architecture
- [ ] Create training materials (slides/videos)
- [ ] Schedule training sessions
- [ ] Conduct training for all user groups

### Phase 10: Deployment (Week 4)
- [ ] Code review
- [ ] Security audit
- [ ] Backup production database
- [ ] Deploy to staging environment
- [ ] Run all tests on staging
- [ ] User acceptance testing on staging
- [ ] Fix any issues found
- [ ] Create rollback plan
- [ ] Schedule deployment window
- [ ] Deploy to production
- [ ] Monitor for errors
- [ ] Verify functionality on production
- [ ] Collect user feedback

### Post-Deployment
- [ ] Monitor system performance
- [ ] Address user feedback
- [ ] Create maintenance plan
- [ ] Plan for future enhancements

---

## Key Files Reference

### Models
- `app/Models/ProductOrServiceRequest.php` - Main billing request model
- `app/Models/Product.php` - Product catalog
- `app/Models/service.php` - Service catalog  
- `app/Models/Price.php` - Product pricing
- `app/Models/ServicePrice.php` - Service pricing
- `app/Models/payment.php` - Payment records
- `app/Models/Hmo.php` - HMO organizations
- `app/Models/patient.php` - Patient records
- **NEW**: `app/Models/HmoTariff.php` - HMO pricing tariffs
- **NEW**: `app/Models/HmoClaim.php` - HMO claims tracking

### Controllers
- `app/Http/Controllers/ProductOrServiceRequestController.php` - Request management
- `app/Http/Controllers/Account/accountsController.php` - Service/product selection (Page 2)
- `app/Http/Controllers/Account/paymentController.php` - Payment processing (Page 3)
- **NEW**: `app/Http/Controllers/Admin/TariffManagementController.php` - Tariff management
- **NEW**: `app/Http/Controllers/HmoWorkbenchController.php` - HMO validation workbench

### Views
- `resources/views/admin/product_or_service_request/index.blade.php` - Patient list (Page 1)
- `resources/views/admin/Accounts/services.blade.php` - Service/product selection (Page 2)
- `resources/views/admin/Accounts/summary.blade.php` - Payment summary (Page 3)
- **NEW**: `resources/views/admin/tariffs/index.blade.php` - Tariff management
- **NEW**: `resources/views/admin/hmo/workbench.blade.php` - HMO workbench
- **NEW**: `resources/views/partials/search-pane.blade.php` - Reusable search component
- **NEW**: `resources/views/partials/clinical-context-modal.blade.php` - Reusable clinical modal

### Routes
- `routes/web.php` - Main route definitions
  - Line ~250: `Route::get('servicess/{id}', [accountsController::class, 'index'])`
  - Line ~258: `Route::post('service-payment', [paymentController::class, 'process'])`
  - Line ~320: `Route::resource('product-or-service-request', ProductOrServiceRequestController::class)`

### Database Migrations
- `database/migrations/2023_03_09_025436_create_product_or_service_requests_table.php` - Base table
- `database/migrations/2023_07_14_232733_add_payment_id_col_to_product_or_service_requests.php` - Payment link
- `database/migrations/2023_07_23_205645_add_qty_column_to_product_or_service_requests_table.php` - Quantity
- `database/migrations/2024_06_12_000001_add_discount_fields_to_payments_and_requests.php` - Discounts
- `database/migrations/2022_12_31_160021_create_payments_table.php` - Payments
- `database/migrations/2023_07_14_221743_add_patient_id_col_to_payments.php` - Patient link
- **NEW**: `database/migrations/YYYY_MM_DD_create_hmo_tariffs_table.php`
- **NEW**: `database/migrations/YYYY_MM_DD_add_hmo_columns_to_product_or_service_requests.php`
- **NEW**: `database/migrations/YYYY_MM_DD_create_hmo_claims_table.php`

### Helpers
- `app/helpers.php` - Global helper functions (includes `userfullname()`, `appsettings()`)
- **NEW**: `app/Helpers/HmoHelper.php` - HMO-specific helper methods

### Observers (NEW)
- `app/Observers/ProductObserver.php` - Auto-generate tariffs on product creation
- `app/Observers/ServiceObserver.php` - Auto-generate tariffs on service creation
- `app/Observers/HmoObserver.php` - Auto-generate tariffs on HMO creation

---

## Additional Notes

- **Timeline**: Estimate 3-4 weeks for complete implementation (detailed phase breakdown in checklist)
- **Priority**: Implement in phases as outlined in checklist
  - Phase 1-2: Database & core functionality (Week 1)
  - Phase 3-4: Request creation & billing logic (Week 1-2)
  - Phase 5: Tariff management UI (Week 2)
  - Phase 6-7: HMO workbench & access control (Week 3)
  - Phase 8-10: Testing, documentation, deployment (Week 3-4)
- **Rollback Plan**: 
  - Keep old billing logic intact, use feature flag or config setting to enable HMO billing
  - Database changes are additive (new columns, new tables) - no data loss on rollback
  - Migration rollback available for all new tables
- **Training**: 
  - Plan separate sessions for: Admins, HMO Executives, Billing Staff, Service Delivery Staff
  - Create video tutorials for complex workflows
  - Provide quick reference guides
- **Performance Considerations**:
  - Tariff auto-generation may take time for large datasets - use queues
  - Index all foreign keys and frequently queried columns
  - Consider caching tariffs for active HMOs
- **Data Migration**:
  - Run backfill command after initial deployment to create tariffs for existing products/services/HMOs
  - Test on copy of production data before live deployment

---

## Questions for Stakeholders

Before starting, clarify:

1. Can payable_amount + claims_amount exceed the original service price?
2. What happens if a patient's HMO coverage changes mid-treatment?
3. Should rejected requests be visible to requesting doctors?
4. Is there a time limit for HMO validation (SLA)?
5. Should patients be notified when their requests are approved/rejected?
6. Do we need integration with HMO companies' systems for auth code verification?
7. How should we handle emergency situations where HMO approval isn't possible immediately?
8. Should there be an override mechanism for admins to bypass HMO validation in emergencies?

---

**End of Implementation Prompt**
