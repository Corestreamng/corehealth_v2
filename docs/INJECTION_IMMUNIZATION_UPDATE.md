# Injection & Immunization Product Search Update

## Overview
Updated the nursing workbench injection and immunization modules to use the same product search component as the drug prescription form, with full HMO price breakdown.

## Changes Made

### 1. Frontend Changes (workbench.blade.php)

#### HTML Forms Updated:
- **Injection Tab (lines 325-427)**:
  - Removed Select2 dropdown (`#inj_product_id`)
  - Added product search input (`#inj_product_search`) with `onkeyup="searchInjectionProducts(this.value)"`
  - Added results list (`#inj_product_results`)
  - Added selected products table (`#selected-injection-products`) with columns: Name, Price, Dose, Actions
  - Removed batch_number, expiry_date, notes fields (simplified form)

- **Immunization Tab (lines 428-620)**:
  - Removed Select2 dropdown (`#imm_product_id`)
  - Added product search input (`#imm_product_search`) with `onkeyup="searchVaccineProducts(this.value)"`
  - Added results list (`#imm_product_results`)
  - Added selected vaccine products table (`#selected-vaccine-products`) with columns: Name, Price, Dose/Freq, Actions
  - Removed batch_number, manufacturer, expiry_date, next_due_date, adverse_reaction fields

#### JavaScript Functions Added:

**Injection Functions:**
- `searchInjectionProducts(q)` - Searches products via `url('live-search-products')` with patient HMO context
- `addInjectionProduct(id, name, price, payable, claims, coverage)` - Adds product to selected table
- `removeInjectionRow(btn)` - Removes product from table
- Updated `$('#injectionForm').on('submit')` to:
  - Collect array of products from table rows
  - Send `products[]` array with: product_id, dose, payable_amount, claims_amount, coverage_mode
  - Send common fields: route, site, administered_at

**Immunization Functions:**
- `searchVaccineProducts(q)` - Searches vaccines via `url('live-search-products')` with patient HMO context
- `addVaccineProduct(id, name, price, payable, claims, coverage)` - Adds vaccine to selected table
- `removeVaccineRow(btn)` - Removes vaccine from table
- Updated `$('#immunizationForm').on('submit')` to:
  - Collect array of vaccines from table rows
  - Send `products[]` array with: product_id, dose_number, payable_amount, claims_amount, coverage_mode
  - Send common fields: route, site, administered_at
- Updated `loadImmunizationSchedule()` to handle dynamic vaccine list (not hardcoded)

#### Product Search Display Format:
```
[Category] Product Name [Code]
(qty available) NGN price [COVERAGE_MODE] Pay: NGN payable_amount Claim: NGN claims_amount
```

Example:
```
[Injections] Penicillin 500mg [PEN-500]
(150 available) NGN 2000.00 [HMO_WITH_COPAY] Pay: NGN 500.00 Claim: NGN 1500.00
```

### 2. Backend Changes (NursingWorkbenchController.php)

#### Updated Methods:

**administerInjection() - Lines 449-534:**
- Changed validation from single `product_id` to `products[]` array
- Validation rules:
  ```php
  'products' => 'required|array|min:1',
  'products.*.product_id' => 'required|exists:products,id',
  'products.*.dose' => 'required|string|max:100',
  'products.*.payable_amount' => 'nullable|numeric',
  'products.*.claims_amount' => 'nullable|numeric',
  'products.*.coverage_mode' => 'nullable|string',
  'route' => 'required|in:IM,IV,SC,ID',
  'site' => 'nullable|string|max:100',
  'administered_at' => 'required|date',
  ```
- Removed: batch_number, expiry_date, notes fields
- Loops through products array
- Creates billing record (ProductOrServiceRequest) for each product using provided HMO data
- Creates InjectionAdministration record for each product
- Returns success message with count of injections

**administerImmunization() - Lines 660-745:**
- Changed validation from single `product_id` to `products[]` array
- Validation rules:
  ```php
  'products' => 'required|array|min:1',
  'products.*.product_id' => 'required|exists:products,id',
  'products.*.dose_number' => 'required|string|max:100',
  'products.*.payable_amount' => 'nullable|numeric',
  'products.*.claims_amount' => 'nullable|numeric',
  'products.*.coverage_mode' => 'nullable|string',
  'route' => 'required|in:IM,SC,Oral,ID',
  'site' => 'nullable|string|max:100',
  'administered_at' => 'required|date',
  ```
- Removed: vaccine_name, batch_number, manufacturer, expiry_date, next_due_date, adverse_reaction fields
- Uses product_name as vaccine_name automatically
- Loops through products array
- Creates billing record for each vaccine using provided HMO data
- Creates ImmunizationRecord for each vaccine
- Returns success message with count of immunizations

### 3. Key Features

#### Multiple Products Support:
- Nurses can now select and administer multiple injections/vaccines at once
- Each product creates separate billing and administration records
- All products share common route, site, and administration time

#### HMO Price Breakdown:
- Shows full HMO tariff information in search results:
  - Initial price
  - Coverage mode (FULL_PAYMENT, HMO_WITH_COPAY, HMO_FULL_COVERAGE)
  - Payable amount (what patient pays)
  - Claims amount (what HMO covers)
- HMO data is calculated by `url('live-search-products')` endpoint using HmoHelper
- Frontend passes calculated amounts to backend to ensure consistency

#### Customizable Immunization Schedule:
- Removed hardcoded vaccine list (BCG, OPV, Pentavalent, etc.)
- Schedule now loads from backend dynamically
- Can accommodate any vaccine products in the system

#### Simplified Forms:
- Removed unnecessary fields (batch numbers, expiry dates, etc.) to streamline workflow
- Focus on essential information: product, dose, route, site, time
- Cleaner, faster data entry for nurses

## API Endpoint Used

**Product Search Endpoint:**
```
GET {{ url('live-search-products') }}

Parameters:
- term: search query
- patient_id: current patient ID (for HMO context)

Returns: Array of products with:
- id
- category
- product_name
- product_code
- stock.current_quantity
- price.initial_sale_price
- payable_amount (calculated HMO amount)
- claims_amount (calculated HMO amount)
- coverage_mode (HMO coverage type)
```

## Database Schema (No Changes Required)

The existing tables support this implementation:
- `injection_administrations` - stores injection records
- `immunization_records` - stores vaccination records
- `product_or_service_requests` - stores billing records with HMO tariff data

## Testing Recommendations

1. **Injection Module:**
   - Search for various products
   - Add multiple products to the table
   - Remove products from table
   - Submit form and verify billing records created
   - Check HMO tariff calculations are correct

2. **Immunization Module:**
   - Search for vaccine products
   - Add multiple vaccines to the table
   - Submit form and verify immunization records created
   - Check immunization schedule loads dynamically

3. **HMO Integration:**
   - Test with patients on different HMO plans
   - Verify FULL_PAYMENT mode for non-HMO patients
   - Verify HMO_WITH_COPAY shows correct split
   - Verify HMO_FULL_COVERAGE shows zero payable amount

4. **History Loading:**
   - Verify injection history loads after submission
   - Verify immunization history loads after submission
   - Check schedule updates after immunization

## Benefits

1. **Consistency** - Same UX as prescription forms across the system
2. **Transparency** - Nurses see exact billing amounts before administration
3. **Flexibility** - Can use any product, not restricted by categories
4. **Efficiency** - Multiple products can be administered at once
5. **Accuracy** - HMO calculations done once, used consistently
6. **Scalability** - Immunization schedule not hardcoded, can grow with facility needs

## Notes

- Product search requires patient selection first (validates currentPatientId)
- Minimum 2 characters required for search
- Search results limited to available stock items
- All billing automatically created with correct HMO tariff
- Frontend JavaScript functions are globally scoped for onclick handlers
