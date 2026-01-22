# Database Field Reference

This document provides a comprehensive reference for database table fields used in the Import/Export module and throughout the application.

## Products Table (`products`)

| Field | Type | Description | Notes |
|-------|------|-------------|-------|
| `id` | bigint | Primary key | Auto-increment |
| `user_id` | bigint | Creator user ID | References users.id |
| `category_id` | bigint | Category reference | References product_categories.id |
| `product_name` | string | Product display name | Required |
| `product_code` | string | SKU/Product code | Required, unique |
| `reorder_alert` | int | Reorder threshold | Default: 10 |
| `has_have` | boolean | Allow half sales | 1 = Yes, 0 = No |
| `has_piece` | boolean | Allow piece sales | 1 = Yes, 0 = No |
| `howmany_to` | int | Pieces per unit | Number of pieces in one unit |
| `visible` | boolean | Is product visible/active | Model uses `visible`, some code uses `status` |
| `current_quantity` | int | Current stock level | Aggregate quantity |
| `promotion` | - | Promotion flag | - |
| `stock_assign` | - | Stock assignment | - |
| `price_assign` | - | Price assignment | - |

### Special Fields Explanation:
- **`has_have`**: "Allow to Sale Half" - When enabled, allows selling half quantities
- **`has_piece`**: "Allow to Sale Pieces" - When enabled, allows selling individual pieces
- **`howmany_to`**: "Quantity In" - Defines how many pieces are in one unit

---

## Prices Table (`prices`)

| Field | Type | Description | Notes |
|-------|------|-------------|-------|
| `id` | bigint | Primary key | Auto-increment |
| `product_id` | bigint | Product reference | References products.id |
| `pr_buy_price` | int | Cost/Buy price | **NOT** `cost_price` |
| `initial_sale_price` | int | Original sale price | - |
| `initial_sale_date` | date | When initial price was set | Nullable |
| `current_sale_price` | float | Current selling price | - |
| `half_price` | int | Price for half quantity | For has_have=1 products |
| `pieces_price` | int | Price per piece | For has_piece=1 products |
| `pieces_max_discount` | int | Max discount for pieces | - |
| `current_sale_date` | date | When current price was set | Nullable |
| `max_discount` | int | Maximum discount allowed | Percentage |
| `status` | boolean | Price record active | 1 = Active |

### Important Notes:
- The cost/buy price field is `pr_buy_price`, NOT `cost_price`
- The selling price field is `current_sale_price`
- Price model has NO `$fillable` array - uses guarded

---

## Service Prices Table (`service_prices`)

| Field | Type | Description | Notes |
|-------|------|-------------|-------|
| `id` | bigint | Primary key | Auto-increment |
| `service_id` | bigint | Service reference | References services.id |
| `cost_price` | decimal | Cost price | - |
| `sale_price` | decimal | Selling price | **NOT** `current_price` |
| `max_discount` | decimal | Maximum discount | Nullable |
| `status` | boolean | Price record active | Default: 1 |

---

## Services Table (`services`)

| Field | Type | Description | Notes |
|-------|------|-------------|-------|
| `id` | bigint | Primary key | Auto-increment |
| `user_id` | bigint | Creator user ID | References users.id |
| `category_id` | bigint | Category reference | References service_categories.id |
| `service_name` | string | Service display name | Required |
| `service_code` | string | Service code | Required, unique |
| `price_assign` | - | Price assignment | - |
| `status` | boolean | Is service active | 1 = Active |
| `result_template_v2` | json | Lab result template | For lab services |

---

## Stock Table (`stocks`)

| Field | Type | Description | Notes |
|-------|------|-------------|-------|
| `id` | bigint | Primary key | Auto-increment |
| `product_id` | bigint | Product reference | References products.id |
| `initial_quantity` | int | Initial stock quantity | - |
| `order_quantity` | int | Quantity on order | - |
| `current_quantity` | int | Current available quantity | - |
| `quantity_sale` | int | Quantity sold | - |

---

## Store Stocks Table (`store_stocks`)

| Field | Type | Description | Notes |
|-------|------|-------------|-------|
| `id` | bigint | Primary key | Auto-increment |
| `store_id` | bigint | Store reference | References stores.id |
| `product_id` | bigint | Product reference | References products.id |
| `initial_quantity` | int | Initial stock quantity | Default: 0 |
| `quantity_sale` | int | Quantity sold | Default: 0 |
| `order_quantity` | int | Quantity on order | Default: 0 |
| `current_quantity` | int | Current available quantity | Default: 0 |
| `reserved_qty` | int | Reserved for pending orders | Added in migration 100009 |
| `reorder_level` | int | Low stock threshold | Default: 10, Added in migration 100009 |
| `max_stock_level` | int | Maximum stock level | Nullable, Added in migration 100009 |
| `is_active` | boolean | Store carries this product | Default: true, Added in migration 100009 |
| `last_restocked_at` | timestamp | Last restock time | Nullable |
| `last_sold_at` | timestamp | Last sale time | Nullable |

---

## Users Table (`users`)

| Field | Type | Description | Notes |
|-------|------|-------------|-------|
| `id` | bigint | Primary key | Auto-increment |
| `is_admin` | int | User type | 1=Admin, 2=Staff, 3=Patient |
| `email` | string | Email address | Required, unique |
| `password` | string | Hashed password | - |
| `surname` | string | Last name | Required |
| `firstname` | string | First name | Required |
| `othername` | string | Middle name | Nullable |
| `filename` | string | Profile picture | Nullable |
| `old_records` | json | Legacy data | - |
| `assignRole` | - | Role assignment | - |
| `assignPermission` | - | Permission assignment | - |
| `status` | boolean | Account active | 1 = Active |

---

## Patients Table (`patients`)

| Field | Type | Description | Notes |
|-------|------|-------------|-------|
| `id` | bigint | Primary key | Auto-increment |
| `user_id` | bigint | User account | References users.id |
| `file_no` | string | Patient file number | Unique |
| `insurance_scheme` | string | Insurance type | - |
| `hmo_id` | bigint | HMO reference | References hmos.id, Nullable |
| `hmo_no` | **bigint** | HMO enrollment number | **unsignedBigInteger**, NOT string |
| `gender` | string | Gender | Male/Female |
| `dob` | **timestamp** | Date of birth | **timestamp** type, format: YYYY-MM-DD |
| `blood_group` | string | Blood group | A+, B+, O-, etc. |
| `genotype` | string | Genotype | AA, AS, SS, etc. |
| `disability` | string | Disability info | Nullable |
| `address` | text | Home address | Nullable |
| `phone_no` | string | Phone number | - |
| `nationality` | string | Nationality | - |
| `ethnicity` | string | Ethnicity | - |
| `misc` | json | Miscellaneous data | - |
| `next_of_kin_name` | string | NOK name | - |
| `next_of_kin_phone` | string | NOK phone | - |
| `next_of_kin_address` | text | NOK address | - |
| `allergies` | **json** | Allergies list | Cast to array in model |
| `medical_history` | text | Medical history | - |

### Important Notes:
- `hmo_no` is `unsignedBigInteger`, not string
- `dob` is `timestamp` type
- `allergies` is JSON, cast to array

---

## Staff Table (`staff`)

| Field | Type | Description | Notes |
|-------|------|-------------|-------|
| `id` | bigint | Primary key | Auto-increment |
| `user_id` | bigint | User account | References users.id |
| `specialization_id` | bigint | Specialization | References specializations.id, Nullable |
| `clinic_id` | bigint | Assigned clinic | References clinics.id, Nullable |
| `gender` | string | Gender | Male/Female |
| `date_of_birth` | date | Date of birth | - |
| `home_address` | text | Home address | - |
| `phone_number` | string | Phone number | - |
| `consultation_fee` | decimal | Consultation fee | For doctors |
| `is_unit_head` | boolean | Is unit head | - |
| `is_dept_head` | boolean | Is department head | - |
| `status` | boolean | Staff active | 1 = Active |

---

## Import/Export Field Mappings

### Product Import CSV → Database

| CSV Field | Database Table.Field |
|-----------|---------------------|
| `product_name` | products.product_name |
| `product_code` | products.product_code |
| `category_name` | → lookup/create product_categories |
| `cost_price` | prices.pr_buy_price |
| `sale_price` | prices.current_sale_price |
| `reorder_level` | products.reorder_alert, store_stocks.reorder_level |
| `initial_quantity` | products.current_quantity, stocks.*, store_stocks.* |
| `store_name` | → lookup stores for store_stocks |
| `is_active` | products.visible |

### Service Import CSV → Database

| CSV Field | Database Table.Field |
|-----------|---------------------|
| `service_name` | services.service_name |
| `service_code` | services.service_code |
| `category_name` | → lookup/create service_categories |
| `price` | service_prices.sale_price |
| `cost_price` | service_prices.cost_price |
| `is_active` | services.status |

---

## Code Consistency Notes

1. **Product visibility**: Code inconsistently uses both `visible` and `status`. The model fillable uses `visible`, but the original migration has `status`. Both appear to work, suggesting the database may have both or a rename occurred.

2. **Price table cost field**: Always use `pr_buy_price` for cost price in products (NOT `cost_price`).

3. **Service price field**: Always use `sale_price` for service selling price (NOT `current_price`).

4. **Patient HMO number**: `hmo_no` is a BigInteger, not a string.

---

*Document created: $(date)*
*Purpose: Reference for Import/Export module and database operations*
