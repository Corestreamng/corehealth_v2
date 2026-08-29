# Product Enhancement Plan — Packaging, Classification & CRUD Redesign

**Date:** March 17, 2026  
**Scope:** Products table enhancements, new packaging hierarchy, product classification, CRUD UI redesign, purchase order integration  
**Estimated Files Affected:** ~45 files (new + modified)

> **CORE PRINCIPLE:** All internal calculations, stock ledger, billing, accounting, and reporting operate in **base units (pieces/units)**. Packaging and UOM are purely a **UI/UX convenience layer** for human-friendly input and display. Every screen that shows a packaging quantity MUST also show the base-unit equivalent.

---

## Table of Contents

1. [Overview](#1-overview)
2. [Feature 1: Product Packagings (Hierarchical UOM)](#2-feature-1-product-packagings-hierarchical-uom)
3. [Feature 2: Product Classification (Drug / Consumable / Utility)](#3-feature-2-product-classification)
4. [Feature 3: Product CRUD Redesign (Staff-Style UI)](#4-feature-3-product-crud-redesign)
5. [Feature 4: Purchase Order Packaging Integration](#5-feature-4-purchase-order-packaging-integration)
6. [Impact Map — All Affected Areas](#6-impact-map)
7. [Database Schema](#7-database-schema)
8. [Implementation Phases](#8-implementation-phases)
9. [File-by-File Change List](#9-file-by-file-change-list)
10. [UI/UX Wireframes (Text)](#10-uiux-wireframes)

---

## 1. Overview

### Current State
- Products table has basic fields: `product_name`, `product_code`, `category_id`, `reorder_alert`, `has_have`, `has_piece`, `howmany_to`, `current_quantity`, `status`
- No product classification (drug vs consumable vs utility)
- No packaging hierarchy — quantity is always in base units (pieces)
- CRUD pages use legacy form styling (horizontal `form-group row` layout, basic Bootstrap cards)
- All products appear in all search endpoints regardless of type

### Target State
- **Fully customizable** packaging hierarchy (user-defined names, levels, and conversion factors — NOT a fixed piece→strip→box→carton standard)
- **Customizable base unit name** per product ("Piece", "Tablet", "ml", "Vial", "Ampoule", etc.) — internally always stored as numeric base units
- **Liquid & partial quantity support** — decimal base units for syrups, IV fluids, injections; fractional dispensing tracked accurately
- Product classification enum: `drug`, `consumable`, `utility`
- Staff-style CRUD UI with `modern-forms.css`, `card-modern` cards, MDI icons, rich DataTable
- Type-aware search filtering across all workbenches
- **Purchase order UI** fully integrated with packaging-aware qty entry and classification

---

## 2. Feature 1: Product Packagings (Hierarchical UOM)

### 2.1 Concept

A product can have multiple **fully customizable** packaging levels. Each level has:
- A **user-defined name** (anything — "Strip", "Sachet", "Pack", "Tray", "Drum", "Bottle", etc.)
- A **quantity per parent** (e.g., a Strip has 10 Tablets, a Box has 20 Strips)
- A **computed conversion factor** to the base unit

The hierarchy is **not a fixed standard** — each product defines its own packaging chain from scratch. The admin picks the base unit name and builds levels on top.

### 2.1.1 Customizable Base Unit

Each product has a **`base_unit_name`** field (default: "Piece"). This is the smallest countable/measurable unit:

| Product Type | Example Base Unit | Notes |
|---|---|---|
| Solid tablets | "Tablet" | Discrete whole units |
| Capsules | "Capsule" | Discrete |
| Surgical gloves | "Piece" | Discrete |
| Syrup (liquid) | "ml" | Continuous — allows decimals |
| IV Fluid | "ml" | Continuous |
| Injection ampoule | "Ampoule" | Discrete (but partial use tracked) |
| Cream/Ointment | "g" | Continuous |
| Cotton wool | "Roll" | Discrete |
| Cleaning detergent | "Litre" | Continuous |

### 2.1.2 Liquid & Partial Quantity Support

Products with `allow_decimal_qty = true` (liquids, creams, etc.) can have fractional base units:
- A 100ml bottle of cough syrup: base unit = "ml", incoming stock = 100 ml per bottle
- Nurse dispenses 15ml → stock becomes 85ml → system tracks partial use
- Purchase orders and requisitions can specify "5 Bottles" = 500ml in base units
- Dispensing can be in ml (partial) or full Bottles

For **solids with partial use** (e.g., half-tablet, splitting ampoules):
- `allow_decimal_qty = true` enables fractional dispensing
- This **replaces** the legacy `has_have` / `has_piece` / `howmany_to` fields with a cleaner design

### 2.1.3 Example Hierarchies

**Paracetamol 500mg** (base unit: "Tablet"):
```
Carton  (1 Carton  = 20 Boxes   = 4000 Tablets)  ← level 3
  └─ Box    (1 Box     = 20 Strips  = 200 Tablets)   ← level 2
       └─ Strip  (1 Strip   = 10 Tablets)              ← level 1
            └─ Tablet  (base unit)                      ← level 0 (stored on product)
```

**Cough Syrup 100ml** (base unit: "ml", allow_decimal_qty: true):
```
Carton (1 Carton = 24 Bottles = 2400 ml)  ← level 2
  └─ Bottle (1 Bottle = 100 ml)            ← level 1
       └─ ml (base unit)                   ← level 0 (stored on product)
```

**Surgical Gloves** (base unit: "Piece"):
```
Carton (1 Carton = 20 Boxes = 2000 Pieces)  ← level 2
  └─ Box (1 Box = 100 Pieces)                ← level 1
       └─ Piece (base unit)                   ← level 0 (stored on product)
```

**IV Normal Saline 1000ml** (base unit: "ml", allow_decimal_qty: true):
```
Case (1 Case = 12 Bags = 12000 ml)  ← level 1
  └─ Bag (1 Bag = 1000 ml)          ← level 0.5 (optional intermediate)
       └─ ml (base unit)             ← level 0
```

**Bleach** (base unit: "Litre", allow_decimal_qty: true):
```
Drum (1 Drum = 200 Litres)  ← level 1
  └─ Litre (base unit)      ← level 0
```

> **Key rule:** The admin defines the chain. The system doesn't impose names or structures.

### 2.2 Database Design

**New Table: `product_packagings`**

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | — |
| `product_id` | bigint FK → products | Parent product |
| `name` | varchar(100) | User-defined: "Strip", "Bottle", "Sachet", "Drum", anything |
| `description` | varchar(255) nullable | e.g., "Contains 10 tablets per strip" |
| `level` | tinyint unsigned | 1, 2, 3… (0 = base unit, stored on product itself) |
| `parent_packaging_id` | bigint FK → product_packagings, nullable | Self-referential for hierarchy |
| `units_in_parent` | decimal(12,4) unsigned | How many of the child unit fit in this packaging (decimal for liquids) |
| `base_unit_qty` | decimal(12,4) unsigned | **Computed**: total base units in one of this packaging |
| `is_default_purchase` | boolean default 0 | Default packaging used for purchase orders |
| `is_default_dispense` | boolean default 0 | Default packaging used for dispensing |
| `barcode` | varchar(100) nullable | Optional barcode per packaging level |
| `created_at` | timestamp | — |
| `updated_at` | timestamp | — |

**New columns on `products` table:**

| Column | Type | Description |
|--------|------|-------------|
| `base_unit_name` | varchar(50) default 'Piece' | Customizable name for the base unit ("Tablet", "ml", "Capsule", etc.) |
| `allow_decimal_qty` | boolean default 0 | Whether fractional base units are allowed (for liquids, creams, etc.) |

**Constraints:**
- `UNIQUE(product_id, name)` — no duplicate packaging names per product
- `UNIQUE(product_id, level)` — one packaging per level
- Level 0 is the base unit — its name is stored as `products.base_unit_name`, NOT in this table
- `base_unit_qty` is auto-calculated: `units_in_parent × parent.base_unit_qty`
- `units_in_parent` is decimal(12,4) to support liquid conversions (e.g., 1 Bottle = 100.0 ml)

### 2.3 Model: `ProductPackaging`

```php
class ProductPackaging extends Model
{
    protected $fillable = [
        'product_id', 'name', 'description', 'level',
        'parent_packaging_id', 'units_in_parent', 'base_unit_qty',
        'is_default_purchase', 'is_default_dispense', 'barcode',
    ];

    protected $casts = [
        'units_in_parent' => 'decimal:4',
        'base_unit_qty' => 'decimal:4',
    ];

    public function product() { return $this->belongsTo(Product::class); }
    public function parent() { return $this->belongsTo(self::class, 'parent_packaging_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_packaging_id'); }

    // Convert a qty *of this packaging* into base units
    public function toBaseUnits(float $qty): float
    {
        return $qty * $this->base_unit_qty;
    }

    // Convert base units into this packaging (with remainder)
    public function fromBaseUnits(float $baseQty): array
    {
        if ($this->base_unit_qty <= 0) return ['qty' => 0, 'remainder' => $baseQty];
        $full = floor($baseQty / $this->base_unit_qty);
        $remainder = fmod($baseQty, $this->base_unit_qty);
        return ['qty' => $full, 'remainder' => round($remainder, 4)];
    }
}
```

**Product Model — new relationship:**
```php
// In Product.php — NEW FIELDS
protected $fillable = [
    // ... existing fields ...
    'product_type',        // enum: drug, consumable, utility
    'base_unit_name',      // customizable: "Tablet", "ml", "Piece", etc.
    'allow_decimal_qty',   // boolean: true for liquids/creams
];

public function packagings()
{
    return $this->hasMany(ProductPackaging::class)->orderBy('level');
}

/**
 * Express a base-unit qty in the most human-readable packaging breakdown.
 * e.g., 4230 tablets → "1 Carton, 1 Box, 3 Strips, 0 Tablets"
 * e.g., 250.5 ml → "2 Bottles, 50.5 ml"
 *
 * ALWAYS appends the base-unit remainder so the user sees the exact count.
 */
public function formatQty(float $baseQty): string
{
    $packagings = $this->packagings()->orderByDesc('level')->get();
    $parts = [];
    $remaining = $baseQty;
    $unitName = $this->base_unit_name ?? 'Piece';

    foreach ($packagings as $pkg) {
        if ($pkg->base_unit_qty > 0 && $remaining >= $pkg->base_unit_qty) {
            $count = floor($remaining / $pkg->base_unit_qty);
            $remaining = fmod($remaining, $pkg->base_unit_qty);
            $parts[] = "{$count} {$pkg->name}" . ($count > 1 ? 's' : '');
        }
    }

    // Always show base unit remainder (even 0 for clarity)
    if ($remaining > 0 || empty($parts)) {
        $display = $this->allow_decimal_qty ? round($remaining, 2) : (int) $remaining;
        $parts[] = "{$display} {$unitName}" . ($display != 1 ? 's' : '');
    }

    return implode(', ', $parts);
}

/**
 * Returns the base unit label with qty: "4,200 Tablets" or "500 ml"
 */
public function baseQtyLabel(float $baseQty): string
{
    $unitName = $this->base_unit_name ?? 'Piece';
    $display = $this->allow_decimal_qty ? round($baseQty, 2) : (int) $baseQty;
    return number_format($display, $this->allow_decimal_qty ? 2 : 0) . ' ' . $unitName . ($display != 1 ? 's' : '');
}
```

### 2.4 Impact by Area

#### A. Product CRUD (Create/Edit)
- Add a **"Packaging Levels"** section (dynamic repeater rows) in the create/edit form
- Each row: Name (text), Units in Parent (number), Description (text), Default Purchase (toggle), Default Dispense (toggle)
- First row shows the customizable **base unit name** field (editable text input, default "Piece")
- Toggle for **"Allow Decimal Qty"** (for liquids/creams)
- "Add Packaging Level" button appends the next level
- On save, auto-compute `base_unit_qty` = `units_in_parent × parent.base_unit_qty`
- Validation: `units_in_parent` must be > 0, names unique per product

#### B. Product Excel Import/Export
- **Export:** Add columns: `packaging_levels` (JSON string or pipe-delimited)  
  Format: `"Strip:10|Box:200|Carton:4000"` (name:base_unit_qty)
- **Import:** Parse the `packaging_levels` column and create `ProductPackaging` rows
- Template download updated with example and instruction header
- Backward compatible: column is optional; missing = product has no packaging (pieces only)

#### C. Requisitions (Store Requisition Create)
- Product search results now show available packagings as a **dropdown** next to qty input
- Options: "Tablets", "Strips (10 tabs)", "Boxes (200 tabs)" — uses product's `base_unit_name`
- When user enters `qty: 2, packaging: Box`, the system stores `requested_qty_base = 2 × 200 = 400` base units
- For **liquids**: user can enter "5 Bottles" or "250 ml" — both convert to base units (ml)
- For **partial liquids**: user can requisition "2.5 Litres" of bleach = 2.5 base units
- `StoreRequisitionItem` gets two new nullable columns:
  - `packaging_id` (FK → product_packagings) — which packaging was selected
  - `packaging_qty` (decimal) — qty in that packaging unit
  - existing `requested_qty` continues to store the **base unit** value (always!)
- Display: Show "2 Boxes (400 Tablets)" or "5 Bottles (500 ml)" in requisition views
- **Always show base unit equivalent** alongside packaging qty for transparency

#### D. Stock Workbench (Inventory)
- Product list / stock overview: Show qty in **smart breakdown** using `Product::formatQty()`
  - e.g., "2 Cartons, 5 Boxes, 30 Pieces" instead of "2530"
- Batch view: Add packaging column showing which packaging the batch was received in
- Stock receive (add batch): Allow entering qty in any packaging, auto-convert to pieces

#### E. Nurse Billing Tab
- When billing a product, show packaging dropdown next to qty
- Options: "Tablets", "Strips (10 tabs)" etc. or "ml", "Bottles (100 ml)" for liquids
- For liquids: nurse can bill "15 ml" of cough syrup (partial bottle use)
- Internally always store and deduct in **base units**
- `ProductRequest` and `ProductOrServiceRequest` get new nullable columns:
  - `packaging_id` (FK → product_packagings)
  - `packaging_qty` (decimal) — qty in selected packaging
  - existing `qty` remains as **base unit** for all calculations
- Display: "2 Strips (20 Tablets)" or "15 ml" — always show base unit equivalent
- Price calculations always use: `price_per_base_unit × qty_in_base_units`

#### F. Pharmacy Workbench
- **Creative UX approach:** When a product is selected, show a **packaging visualizer card**:
  ```
  ┌─────────────────────────────────────────────┐
  │ 📦 Paracetamol 500mg — Packaging Breakdown  │
  │                                              │
  │  🏷️ Piece ─── 10 ──→ Strip ─── 20 ──→ Box  │
  │                              ─── 20 ──→ Carton │
  │                                              │
  │  Quick Qty:  [2] [Strips ▾]  = 20 pieces     │
  │                                              │
  │  In Stock: 4,200 pcs (1 Carton, 1 Box, 0 Strips) │
  └─────────────────────────────────────────────┘
  ```
- Dispensing screen: Show packaging-aware qty selector with **live conversion preview**
- Prescription display: Show both packaging qty and piece equivalent
- History: Show dispensed packaging unit alongside piece count

#### G. Purchase Orders
- **Full packaging integration** in the PO create/edit/receive flow
- When adding a line item, user sees the packaging dropdown: "Tablets", "Strips", "Boxes", "Cartons"
- Pre-selects the product's `is_default_purchase` packaging if set
- User enters qty in selected packaging; system auto-calculates and stores `ordered_qty` in **base units**
- Display shows both: "50 Boxes" with "(10,000 Tablets)" base unit equivalent
- For **liquids**: "100 Bottles" = "10,000 ml" in base units
- `PurchaseOrderItem` gets new nullable columns:
  - `packaging_id` (FK → product_packagings)
  - `packaging_qty` (decimal) — qty in selected packaging
  - existing `ordered_qty` and `received_qty` remain in **base units** (always!)
- **Receive form**: When receiving items, show packaging context
  - "Ordered: 50 Boxes (10,000 Tablets)" | "Received so far: 30 Boxes (6,000 Tablets)" | "Receive now: [___] Boxes"
  - Allow receiving in different packaging than ordered (e.g., ordered 50 Boxes, received 10 Cartons)
  - System always converts to base units for storage
- **Accounts payable view**: Show packaging context alongside amounts
- **Print/PDF**: Show both packaging qty and base unit qty

#### H. All Other Areas (No Change)
- Invoice views, HMO claims, accounting, reports — continue using `qty` in base units
- No changes to `ProductOrServiceRequest.amount` calculations — amount = price × qty (base units)
- Packaging is purely a **UX convenience layer** on top of the base unit system
- Journal entries (PurchaseOrderObserver) — no change, uses monetary amounts not qty

---

## 3. Feature 2: Product Classification

### 3.1 Concept

Every product has a `product_type` that determines where it appears:

| Type | Description | Visible In |
|------|-------------|------------|
| `drug` | Medications, pharmaceuticals | Pharmacy workbench, Nurse billing, Doctor prescriptions, Reception walk-in sales, ALL product searches |
| `consumable` | Gloves, syringes, cotton wool | Nurse billing tab, Reception walk-in sales, Stock workbench |
| `utility` | Cleaning supplies, office items | Nurse billing tab, Reception walk-in sales, Stock workbench |

### 3.2 Database Change

**Migration: Add `product_type` to `products` table**

```php
Schema::table('products', function (Blueprint $table) {
    $table->enum('product_type', ['drug', 'consumable', 'utility'])
          ->default('drug')
          ->after('category_id');
});
```

Default is `drug` so existing products remain visible everywhere (backward compatible).

### 3.3 Model Scopes (Product.php)

```php
// Scope: only drugs
public function scopeDrugsOnly($query) {
    return $query->where('product_type', 'drug');
}

// Scope: consumables + utilities (non-drugs)
public function scopeNonDrugs($query) {
    return $query->whereIn('product_type', ['consumable', 'utility']);
}

// Scope: items allowed in nurse billing (all types)
public function scopeNurseBillable($query) {
    return $query; // all types allowed
}

// Scope: items allowed in reception/walk-in (ALL types — drugs included)
public function scopeWalkInSellable($query) {
    return $query; // all types — drugs, consumables, utilities all sellable at walk-in
}
```

### 3.4 Impact by Area

| Area | Current Behavior | New Behavior |
|------|-----------------|--------------|
| **Pharmacy Workbench** search | Returns all products | `.drugsOnly()` — only drugs |
| **Doctor Prescription** search | Returns all products | `.drugsOnly()` — only drugs |
| **Nurse Billing Tab** search | Returns all products | All types (no filter change) |
| **Reception Walk-in Sales** | Returns all products | `.walkInSellable()` — ALL types (drugs + consumable + utility) |
| **Product CRUD (admin)** | Shows all products | Shows all products + type badge + type filter dropdown |
| **Stock Workbench** | Shows all products | Shows all products + type badge + type filter |
| **Requisitions** | Shows all products | Shows all products (all types can be requisitioned) |
| **Import/Export** | No type column | New `product_type` column (default: drug) |
| **Product Search (`liveSearchProducts`)** | No type filter | Accept optional `?type=drug` query param |

### 3.5 Controller Changes

**PharmacyWorkbenchController::searchProducts()**
```php
// Add to the product query:
$products = Product::drugsOnly()->where('product_name', 'LIKE', "%{$search}%")...
```

**NursingWorkbenchController::searchProducts()**
```php
// No change — all types billable in nurse tab
$products = Product::where('product_name', 'LIKE', "%{$search}%")...
```

**ReceptionWorkbenchController** (if product sales exist):
```php
// All product types sellable at walk-in (drugs, consumables, utilities)
$products = Product::walkInSellable()->where('product_name', 'LIKE', "%{$search}%")...
```

**PurchaseOrderController::searchProducts()**
```php
// All product types can be purchased — add packaging data to response
$products = Product::with('packagings')->where('product_name', 'LIKE', "%{$search}%")...
// Return packaging options so UI can show dropdown
```

**ProductController::liveSearchProducts()**
```php
// Accept optional type filter
$query = Product::query();
if ($request->has('type')) {
    $query->where('product_type', $request->type);
}
```

---

## 4. Feature 3: Product CRUD Redesign

### 4.1 Design System Reference

Replicate the Staff CRUD pattern from:
- CSS: `public/css/modern-forms.css` (already exists — shared)
- Layout: 3-column (sidebar) + 9-column (content) grid
- Cards: `.card-modern`, `.card-header-modern`, `.card-title-modern`
- Forms: `.form-control-modern`, `.form-label-modern`
- Icons: Material Design Icons (`mdi-*`)
- DataTable: Server-side with export buttons, formatted columns

### 4.2 Index Page Redesign

**Current:** Basic table with 12 columns, minimal styling  
**New Design:**

```
┌─────────────────────────────────────────────────────────────┐
│ 💊 Products                                                 │
│ Manage your product inventory, pricing and stock levels     │
│                                                             │
│ [+ Add Product]  [📥 Import]  [📤 Export]  [📋 Template]   │
├─────────────────────────────────────────────────────────────┤
│ Filter: [All Types ▾] [All Categories ▾] [All Status ▾]    │
├────┬───────────────────┬──────────┬────────┬────────┬───────┤
│ #  │ Product           │ Type     │ Stock  │ Price  │ Actions│
│    │ Name + Code + Cat │ Badge    │ Qty+Alert│ Sale  │ ⚙     │
├────┼───────────────────┼──────────┼────────┼────────┼───────┤
│ 1  │ 💊 Paracetamol    │ 🟢 Drug  │ 4,200  │ ₦50   │ 👁️📝  │
│    │ PCM-500 • Pharma  │          │ ⚠️ Low │        │       │
├────┼───────────────────┼──────────┼────────┼────────┼───────┤
│ 2  │ 🧤 Surgical Gloves│ 🟡 Cons. │ 2,000  │ ₦200  │ 👁️📝  │
│    │ GLV-001 • Supply  │          │ ✅ OK  │        │       │
└────┴───────────────────┴──────────┴────────┴────────┴───────┘
```

**Key changes:**
- Combined "Product" column: name (bold) + code (muted) + category (small badge)
- Type column with colored badges: Drug (green), Consumable (yellow), Utility (blue)
- Stock column: formatted qty + reorder alert indicator (⚠️ if below threshold, ✅ if ok)
- Price column: formatted sale price
- Actions column: View (eye icon) + Edit (pencil icon) + dropdown for (Add Batch, Manage Stock, View Batches)
- Header filter dropdowns for type, category, status
- Export buttons row: Excel, PDF, Print, Import, Download Template

### 4.3 Create/Edit Page Redesign

**Layout: Staff-style 3+9 columns**

```
┌──────────────┬──────────────────────────────────────────────┐
│  LEFT (col-3)│  RIGHT (col-9)                               │
│              │                                               │
│ ┌──────────┐ │ ┌─────────────────────────────────────────┐   │
│ │ Product  │ │ │ 📋 Basic Information                     │   │
│ │ Image    │ │ │  Product Name    [________________]      │   │
│ │ (upload) │ │ │  Product Code    [________________]      │   │
│ │          │ │ │  Category        [Dropdown ▾     ]      │   │
│ └──────────┘ │ │  Product Type    (●) Drug (○) Consumable│   │
│              │ │                  (○) Utility             │   │
│ ┌──────────┐ │ │  Description     [________________]      │   │
│ │ Quick    │ │ └─────────────────────────────────────────┘   │
│ │ Stats    │ │                                               │
│ │          │ │ ┌─────────────────────────────────────────┐   │
│ │ Qty: 420 │ │ │ 📦 Packaging Levels                     │   │
│ │ Cat: Pha │ │ │                                          │   │
│ │ Type:Drug│ │ │  Base Unit: Piece (always)               │   │
│ │          │ │ │                                          │   │
│ └──────────┘ │ │  Level 1: [Strip    ] [10] per Piece    │   │
│              │ │  Level 2: [Box      ] [20] per Strip    │   │
│              │ │  Level 3: [Carton   ] [20] per Box      │   │
│              │ │                                          │   │
│              │ │  [+ Add Level]                           │   │
│              │ └─────────────────────────────────────────┘   │
│              │                                               │
│              │ ┌─────────────────────────────────────────┐   │
│              │ │ ⚙️ Inventory Settings                    │   │
│              │ │  Reorder Alert   [________________]      │   │
│              │ │  Allow Halves    [Toggle Switch   ]      │   │
│              │ │  Allow Pieces    [Toggle Switch   ]      │   │
│              │ │  Qty In Unit     [________________]      │   │
│              │ └─────────────────────────────────────────┘   │
│              │                                               │
│              │ ┌─────────────────────────────────────────┐   │
│              │ │ 💰 Pricing (edit mode only)              │   │
│              │ │  Cost Price      [________________]      │   │
│              │ │  Sale Price      [________________]      │   │
│              │ └─────────────────────────────────────────┘   │
│              │                                               │
│              │  [Cancel]                     [Save Product]  │
└──────────────┴──────────────────────────────────────────────┘
```

### 4.4 Show (Product Detail) Page Redesign

**Layout: Staff-style profile**

```
┌──────────────┬──────────────────────────────────────────────┐
│  LEFT (col-3)│  RIGHT (col-9)                               │
│              │                                               │
│ ┌──────────┐ │ ┌─────────────────────────────────────────┐   │
│ │ Gradient │ │ │ 📋 Product Information                   │   │
│ │  Header  │ │ │  Name: Paracetamol 500mg                │   │
│ │          │ │ │  Code: PCM-500                           │   │
│ │ 💊       │ │ │  Category: Pharmaceuticals               │   │
│ │Paracetamol│ │ │  Type: 🟢 Drug                          │   │
│ │PCM-500   │ │ │  Status: Active                         │   │
│ │🟢 Drug   │ │ └─────────────────────────────────────────┘   │
│ │Active    │ │                                               │
│ └──────────┘ │ ┌─────────────────────────────────────────┐   │
│              │ │ 📦 Packaging Breakdown                   │   │
│ ┌──────────┐ │ │                                          │   │
│ │ Stock    │ │ │  Piece → Strip (10) → Box (200) → Carton│   │
│ │ Summary  │ │ │                          (4000)         │   │
│ │          │ │ │                                          │   │
│ │ 4,200 pcs│ │ │  Current Stock: 1 Carton, 1 Box, 0 pcs │   │
│ │ 1C,1B    │ │ └─────────────────────────────────────────┘   │
│ │          │ │                                               │
│ │ Price:₦50│ │ ┌─────────────────────────────────────────┐   │
│ │ Alert:100│ │ │ 💰 Pricing & Sales History               │   │
│ └──────────┘ │ │  [DataTable of sales history]            │   │
│              │ └─────────────────────────────────────────┘   │
│ ┌──────────┐ │                                               │
│ │ Actions  │ │ ┌─────────────────────────────────────────┐   │
│ │ [Edit]   │ │ │ 🏪 Store Stock Overview                 │   │
│ │ [Batch+] │ │ │  Store A: 2,000 pcs (FIFO batches...)   │   │
│ │ [Export] │ │ │  Store B: 2,200 pcs                     │   │
│ └──────────┘ │ └─────────────────────────────────────────┘   │
└──────────────┴──────────────────────────────────────────────┘
```

---

## 5. Feature 4: Purchase Order Packaging Integration

### 5.1 Current PO System

The existing purchase order system (fully documented in codebase) handles:
- Create PO with line items (product + ordered_qty + unit_cost)
- Workflow: Draft → Submit → Approve → Receive (partial/full)
- Receipt creates StockBatch entries via StockService
- Payment tracking with journal entries (Inventory DR / AP CR)
- Accounts payable dashboard

**Current qty fields are plain integers in base units. We enhance without breaking this.**

### 5.2 PO Changes

#### 5.2.1 Create/Edit PO Form

When adding a product line item:
```
┌────────────────────────────────────────────────────────────────────────┐
│ Product         | Qty    | Packaging    | Base Equiv.  | Unit Cost │
├─────────────────┼────────┼──────────────┼──────────────┼───────────┤
│ Paracetamol 500 | [50]   | [Box ▾]      | = 10,000 Tabs| [₦120]   │
│ Cough Syrup     | [100]  | [Bottle ▾]   | = 10,000 ml  | [₦500]   │
│ Surg. Gloves    | [5]    | [Carton ▾]   | = 10,000 Pcs | [₦1,200] │
└─────────────────┴────────┴──────────────┴──────────────┴───────────┘
```

- **Packaging dropdown** shows all configured packagings for the selected product
- Auto-selects the `is_default_purchase` packaging
- **Base Equiv. column** live-updates: `pkg_qty × base_unit_qty = N base_units`
- Unit cost is per packaging unit entered (system divides for per-base-unit cost internally)
- Line total = pkg_qty × unit_cost (displayed), stored as ordered_qty (base) × per-base-unit cost

#### 5.2.2 PO Show / Print View

```
Item               | Ordered              | Unit Cost  | Line Total
Paracetamol 500mg  | 50 Boxes (10,000 Tabs)| ₦120/Box   | ₦6,000
Cough Syrup 100ml  | 100 Bottles (10,000ml)| ₦500/Bot  | ₦50,000
```

Always shows **packaging qty + (base unit equivalent)** side by side.

#### 5.2.3 Receive Form

```
Item              | Ordered          | Received    | Pending        | Receive Now
Paracetamol       | 50 Boxes         | 30 Boxes    | 20 Boxes       | [___] [Box ▾]
                  | (10,000 Tabs)    | (6,000 Tabs)| (4,000 Tabs)   | = ___ Tabs
```

- **Receive in any packaging** — can receive in Cartons even if ordered in Boxes
- System converts to base units and creates StockBatch with base-unit qty
- For liquids: receive partial bottles ("received 95 Bottles" of cough syrup = 9,500 ml)

#### 5.2.4 Database: `purchase_order_items`

New columns (nullable, backward-compatible):
```sql
ALTER TABLE purchase_order_items
    ADD COLUMN packaging_id BIGINT UNSIGNED NULL,
    ADD COLUMN packaging_qty DECIMAL(12,4) NULL,
    ADD COLUMN received_packaging_id BIGINT UNSIGNED NULL,
    ADD COLUMN received_packaging_qty DECIMAL(12,4) NULL,
    ADD FOREIGN KEY (packaging_id) REFERENCES product_packagings(id) ON DELETE SET NULL,
    ADD FOREIGN KEY (received_packaging_id) REFERENCES product_packagings(id) ON DELETE SET NULL;
```

- `packaging_id` + `packaging_qty` = what was ordered in user-friendly packaging
- `received_packaging_id` + `received_packaging_qty` = what was received (may differ)
- `ordered_qty` and `received_qty` ALWAYS store **base units** (system core)

---

## 6. Impact Map

| Area | Packaging | Classification | CRUD Redesign |
|------|-----------|---------------|---------------|
| **Product Model** | ✅ New relationship + `formatQty()` | ✅ New `product_type` field + scopes | — |
| **ProductPackaging Model** | ✅ NEW | — | — |
| **Products Migration** | — | ✅ Add `product_type` column | — |
| **Product Packagings Migration** | ✅ NEW table | — | — |
| **ProductController** | ✅ Save packagings on store/update | ✅ Type filter in `listProducts` | ✅ New views + formatted DataTable |
| **Product Create View** | ✅ Packaging repeater section | ✅ Type radio/select | ✅ Full redesign (staff-style) |
| **Product Edit View** | ✅ Packaging repeater (pre-filled) | ✅ Type radio/select (pre-filled) | ✅ Full redesign (staff-style) |
| **Product Index View** | ✅ Show packaging-formatted qty | ✅ Type badge + filter | ✅ Full redesign (rich DataTable) |
| **Product Show View** | ✅ Packaging breakdown display | ✅ Type badge | ✅ Full redesign (profile-style) |
| **Import/Export** | ✅ New columns | ✅ New column | — |
| **Pharmacy Workbench** | ✅ Packaging visualizer + qty selector | ✅ `.drugsOnly()` filter | — |
| **Nurse Billing Tab** | ✅ Packaging dropdown on billing | — (all types allowed) | — |
| **Stock Workbench** | ✅ Formatted qty display | ✅ Type filter/badge | — |
| **Requisitions** | ✅ Packaging-aware qty input | — (all types allowed) | — |
| **Reception Walk-in Sales** | — | ✅ `.walkInSellable()` — ALL types | — |
| **Doctor Prescriptions** | — | ✅ `.drugsOnly()` filter | — |
| **Purchase Orders (Create/Edit)** | ✅ Packaging dropdown + base equiv. display | ✅ Type badge in product search | ✅ Enhanced line item UI |
| **Purchase Orders (Receive)** | ✅ Packaging-aware receive with cross-pkg support | — | — |
| **Purchase Orders (Show/Print)** | ✅ Dual display (packaging + base units) | ✅ Type badge | — |
| **Purchase Orders (Accounts Payable)** | — | ✅ Type badge | — |
| **Invoices / Accounting / HMO** | — (qty stays in base units) | — | — |

---

## 7. Database Schema

### 7.1 New Table: `product_packagings`

```sql
CREATE TABLE product_packagings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    level TINYINT UNSIGNED NOT NULL DEFAULT 1,
    parent_packaging_id BIGINT UNSIGNED NULL,
    units_in_parent DECIMAL(12,4) UNSIGNED NOT NULL DEFAULT 1,
    base_unit_qty DECIMAL(12,4) UNSIGNED NOT NULL DEFAULT 1,
    is_default_purchase BOOLEAN NOT NULL DEFAULT 0,
    is_default_dispense BOOLEAN NOT NULL DEFAULT 0,
    barcode VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_packaging_id) REFERENCES product_packagings(id) ON DELETE SET NULL,
    UNIQUE KEY uq_product_packaging_name (product_id, name),
    UNIQUE KEY uq_product_packaging_level (product_id, level),
    INDEX idx_product_packagings_product (product_id)
);
```

> `units_in_parent` and `base_unit_qty` are DECIMAL(12,4) to support liquid conversions
> (e.g., 1 Bottle = 100.0000 ml, 1 Carton = 2400.0000 ml)

### 7.2 Alter Table: `products`

```sql
ALTER TABLE products
    ADD COLUMN product_type ENUM('drug', 'consumable', 'utility')
        NOT NULL DEFAULT 'drug'
        AFTER category_id,
    ADD COLUMN base_unit_name VARCHAR(50) NOT NULL DEFAULT 'Piece'
        AFTER product_type,
    ADD COLUMN allow_decimal_qty BOOLEAN NOT NULL DEFAULT 0
        AFTER base_unit_name;

-- Add index for filtering
CREATE INDEX idx_products_type ON products(product_type);
```

### 7.3 Alter Table: `store_requisition_items`

```sql
ALTER TABLE store_requisition_items
    ADD COLUMN packaging_id BIGINT UNSIGNED NULL AFTER requested_qty,
    ADD COLUMN packaging_qty DECIMAL(10,2) NULL AFTER packaging_id,
    ADD FOREIGN KEY (packaging_id) REFERENCES product_packagings(id) ON DELETE SET NULL;
```

### 7.4 Alter Table: `product_requests`

```sql
ALTER TABLE product_requests
    ADD COLUMN packaging_id BIGINT UNSIGNED NULL AFTER qty,
    ADD COLUMN packaging_qty DECIMAL(10,2) NULL AFTER packaging_id,
    ADD FOREIGN KEY (packaging_id) REFERENCES product_packagings(id) ON DELETE SET NULL;
```

### 7.5 Alter Table: `product_or_service_requests`

```sql
ALTER TABLE product_or_service_requests
    ADD COLUMN packaging_id BIGINT UNSIGNED NULL AFTER qty,
    ADD COLUMN packaging_qty DECIMAL(10,2) NULL AFTER packaging_id,
    ADD FOREIGN KEY (packaging_id) REFERENCES product_packagings(id) ON DELETE SET NULL;
```

### 7.6 Alter Table: `purchase_order_items`

```sql
ALTER TABLE purchase_order_items
    ADD COLUMN packaging_id BIGINT UNSIGNED NULL AFTER product_id,
    ADD COLUMN packaging_qty DECIMAL(12,4) NULL AFTER packaging_id,
    ADD COLUMN received_packaging_id BIGINT UNSIGNED NULL AFTER received_qty,
    ADD COLUMN received_packaging_qty DECIMAL(12,4) NULL AFTER received_packaging_id,
    ADD FOREIGN KEY (packaging_id) REFERENCES product_packagings(id) ON DELETE SET NULL,
    ADD FOREIGN KEY (received_packaging_id) REFERENCES product_packagings(id) ON DELETE SET NULL;
```

> `ordered_qty` and `received_qty` remain in **base units**. The packaging columns are for UI display only.

---

## 8. Implementation Phases

### Phase 1: Database & Models (Foundation)
> Priority: HIGH | Dependencies: None

| # | Task | Files |
|---|------|-------|
| 1.1 | Create migration: `add_product_type_and_base_unit_to_products_table` (product_type + base_unit_name + allow_decimal_qty) | `database/migrations/` |
| 1.2 | Create migration: `create_product_packagings_table` | `database/migrations/` |
| 1.3 | Create migration: `add_packaging_fields_to_requisition_items` | `database/migrations/` |
| 1.4 | Create migration: `add_packaging_fields_to_product_requests` | `database/migrations/` |
| 1.5 | Create migration: `add_packaging_fields_to_product_or_service_requests` | `database/migrations/` |
| 1.6 | Create migration: `add_packaging_fields_to_purchase_order_items` | `database/migrations/` |
| 1.7 | Create `ProductPackaging` model | `app/Models/ProductPackaging.php` |
| 1.8 | Update `Product` model — add `product_type`, `base_unit_name`, `allow_decimal_qty`, packagings relationship, scopes, `formatQty()`, `baseQtyLabel()` | `app/Models/Product.php` |
| 1.9 | Update `StoreRequisitionItem` model — add packaging relation | `app/Models/StoreRequisitionItem.php` |
| 1.10 | Update `ProductRequest` model — add packaging relation | `app/Models/ProductRequest.php` |
| 1.11 | Update `ProductOrServiceRequest` model — add packaging relation | `app/Models/ProductOrServiceRequest.php` |
| 1.12 | Update `PurchaseOrderItem` model — add packaging + received_packaging relations | `app/Models/PurchaseOrderItem.php` |
| 1.13 | Run migrations | `php artisan migrate` |

### Phase 2: Product CRUD Redesign + New Fields
> Priority: HIGH | Dependencies: Phase 1

| # | Task | Files |
|---|------|-------|
| 2.1 | Redesign `index.blade.php` — staff-style DataTable with type badges, formatted qty, filters | `resources/views/admin/product/index.blade.php` |
| 2.2 | Redesign `create.blade.php` — staff-style layout with type selector, base unit name, decimal toggle, packaging repeater | `resources/views/admin/product/create.blade.php` |
| 2.3 | Redesign `edit.blade.php` — staff-style layout with pre-filled type + packagings | `resources/views/admin/product/edit.blade.php` |
| 2.4 | Redesign `product.blade.php` (show) — staff-style profile with packaging breakdown | `resources/views/admin/product/product.blade.php` |
| 2.5 | Update `ProductController::store()` — save product_type + packagings | `app/Http/Controllers/ProductController.php` |
| 2.6 | Update `ProductController::update()` — update product_type + sync packagings | `app/Http/Controllers/ProductController.php` |
| 2.7 | Update `ProductController::listProducts()` — add type badge, formatted columns, filter support | `app/Http/Controllers/ProductController.php` |
| 2.8 | Update `ProductController::create()` — pass packaging UOM presets to view | `app/Http/Controllers/ProductController.php` |
| 2.9 | Create packaging repeater JS component | `public/js/product-packaging.js` |

### Phase 3: Import/Export Enhancement
> Priority: MEDIUM | Dependencies: Phase 1

| # | Task | Files |
|---|------|-------|
| 3.1 | Update template download — add `product_type`, `base_unit_name`, `allow_decimal_qty`, and `packaging_levels` columns | `ImportExportController::downloadProductTemplate()` |
| 3.2 | Update import logic — parse all new fields | `ImportExportController::importProducts()` |
| 3.3 | Update export logic — include all new fields | `ImportExportController::exportProducts()` |

### Phase 4: Workbench Integration — Classification Filters
> Priority: HIGH | Dependencies: Phase 1

| # | Task | Files |
|---|------|-------|
| 4.1 | Pharmacy Workbench — add `.drugsOnly()` to product search | `PharmacyWorkbenchController::searchProducts()` |
| 4.2 | Doctor Prescription — add `.drugsOnly()` to product search | Relevant prescription controller |
| 4.3 | Reception Walk-in — add `.walkInSellable()` filter (all types) | `ReceptionWorkbenchController` (if applicable) |
| 4.4 | `liveSearchProducts()` — accept optional `?type=` param | `ProductController::liveSearchProducts()` |
| 4.5 | Purchase Order product search — return packaging data + type badge | `PurchaseOrderController::searchProducts()` |

### Phase 5: Workbench Integration — Packaging UX
> Priority: MEDIUM | Dependencies: Phase 2

| # | Task | Files |
|---|------|-------|
| 5.1 | Pharmacy Workbench — packaging visualizer card + qty selector | `resources/views/admin/pharmacy/workbench.blade.php` |
| 5.2 | Nurse Billing Tab — packaging dropdown on product billing | `resources/views/admin/nursing/workbench.blade.php` |
| 5.3 | Requisition Create — packaging-aware qty input | Requisition create view |
| 5.4 | Stock Workbench — show `formatQty()` in stock overview | `resources/views/admin/inventory/store-workbench/index.blade.php` |
| 5.5 | Stock Receive (Add Batch) — packaging-aware qty entry | Batch create form |
| 5.6 | Create shared Blade component: `packaging-qty-selector` | `resources/views/components/packaging-qty-selector.blade.php` |
| 5.7 | Create API endpoint for getting product packagings | `ProductController` or API route |

### Phase 5b: Purchase Order UI Enhancement
> Priority: HIGH | Dependencies: Phase 1, Phase 5

| # | Task | Files |
|---|------|-------|
| 5b.1 | PO Create/Edit — add packaging dropdown + base equiv. column to line items | `resources/views/admin/inventory/purchase-orders/create.blade.php` |
| 5b.2 | PO Create/Edit — update JS to handle packaging conversion + live base-unit preview | `resources/views/admin/inventory/purchase-orders/create.blade.php` |
| 5b.3 | PO Edit — pre-fill packaging selections from saved data | `resources/views/admin/inventory/purchase-orders/edit.blade.php` |
| 5b.4 | PO Show/Print — display dual qty (packaging + base units) | `resources/views/admin/inventory/purchase-orders/show.blade.php` |
| 5b.5 | PO Receive — packaging-aware receive form with cross-packaging support | `resources/views/admin/inventory/purchase-orders/receive.blade.php` |
| 5b.6 | PO Accounts Payable — add type badges to product display | `resources/views/admin/inventory/purchase-orders/accounts_payable.blade.php` |
| 5b.7 | Update `PurchaseOrderController::store()` — save packaging_id/qty, convert to base units | `app/Http/Controllers/PurchaseOrderController.php` |
| 5b.8 | Update `PurchaseOrderController::receive()` — handle received packaging, convert to base | `app/Http/Controllers/PurchaseOrderController.php` |
| 5b.9 | Update `PurchaseOrderService::create()` / `receiveItems()` — packaging-aware logic | `app/Services/PurchaseOrderService.php` |
| 5b.10 | Update `PurchaseOrderController::searchProducts()` — return packaging data in response | `app/Http/Controllers/PurchaseOrderController.php` |

### Phase 6: Testing & Polish
> Priority: HIGH | Dependencies: All phases

| # | Task |
|---|------|
| 6.1 | Test packaging CRUD (create, edit, delete levels) |
| 6.2 | Test product type filtering across all workbenches |
| 6.3 | Test import/export with new columns |
| 6.4 | Test packaging qty conversion in billing + requisitions |
| 6.5 | Test backward compatibility (products without packagings) |
| 6.6 | Verify all existing features still work (regression) |
| 6.7 | Test purchase order create with packaging qty + base unit conversion |
| 6.8 | Test PO receive with cross-packaging (order in Boxes, receive in Cartons) |
| 6.9 | Test liquid products: partial dispensing, decimal qty billing, PO with bottles/ml |
| 6.10 | Test custom base unit names across all views (Tablet, ml, Capsule, Litre) |

---

## 9. File-by-File Change List

### New Files (10)

| File | Purpose |
|------|---------|  
| `database/migrations/YYYY_MM_DD_HHMMSS_add_product_type_and_base_unit_to_products_table.php` | Add `product_type`, `base_unit_name`, `allow_decimal_qty` columns |
| `database/migrations/YYYY_MM_DD_HHMMSS_create_product_packagings_table.php` | New `product_packagings` table with decimal(12,4) qty fields |
| `database/migrations/YYYY_MM_DD_HHMMSS_add_packaging_fields_to_store_requisition_items.php` | Add packaging FK to requisition items |
| `database/migrations/YYYY_MM_DD_HHMMSS_add_packaging_fields_to_product_requests.php` | Add packaging FK to product_requests |
| `database/migrations/YYYY_MM_DD_HHMMSS_add_packaging_fields_to_product_or_service_requests.php` | Add packaging FK to product_or_service_requests |
| `database/migrations/YYYY_MM_DD_HHMMSS_add_packaging_fields_to_purchase_order_items.php` | Add packaging + received_packaging FKs to PO items |
| `app/Models/ProductPackaging.php` | New Eloquent model with `toBaseUnits()`, `fromBaseUnits()` |
| `public/js/product-packaging.js` | Dynamic packaging repeater JS |
| `resources/views/components/packaging-qty-selector.blade.php` | Reusable Blade component for packaging-aware qty input |
| `resources/views/components/packaging-visualizer.blade.php` | Reusable visual packaging chain component |

### Modified Files (~30)

| File | Changes |
|------|---------|  
| `app/Models/Product.php` | Add `product_type`, `base_unit_name`, `allow_decimal_qty` to fillable; add scopes; add `packagings()` relationship; add `formatQty()`, `baseQtyLabel()` |
| `app/Models/ProductRequest.php` | Add `packaging_id`, `packaging_qty` to fillable, add `packaging()` relation |
| `app/Models/ProductOrServiceRequest.php` | Add `packaging_id`, `packaging_qty` to fillable, add `packaging()` relation |
| `app/Models/StoreRequisitionItem.php` | Add `packaging_id`, `packaging_qty` to fillable, add `packaging()` relation |
| `app/Models/PurchaseOrderItem.php` | Add `packaging_id`, `packaging_qty`, `received_packaging_id`, `received_packaging_qty` to fillable; add `packaging()`, `receivedPackaging()` relations |
| `app/Http/Controllers/ProductController.php` | Store/update packaging + base_unit_name + allow_decimal_qty; type filter; redesigned DataTable columns |
| `app/Http/Controllers/ImportExportController.php` | Template, import, export for `product_type`, `base_unit_name`, `allow_decimal_qty`, `packaging_levels` |
| `app/Http/Controllers/PharmacyWorkbenchController.php` | `.drugsOnly()` filter + packaging visualizer data |
| `app/Http/Controllers/NursingWorkbenchController.php` | Return packaging data with product search |
| `app/Http/Controllers/StoreWorkbenchController.php` | Stock overview `formatQty()` display |
| `app/Http/Controllers/PurchaseOrderController.php` | Packaging-aware store/receive/search; return packaging data in product search |
| `app/Services/PurchaseOrderService.php` | Packaging conversion in `create()`, `receiveItems()` |
| `resources/views/admin/product/index.blade.php` | Full redesign — staff-style DataTable |
| `resources/views/admin/product/create.blade.php` | Full redesign — staff-style form + base unit + decimal toggle + packaging repeater |
| `resources/views/admin/product/edit.blade.php` | Full redesign — staff-style form + packaging repeater |
| `resources/views/admin/product/product.blade.php` | Full redesign — staff-style profile view |
| `resources/views/admin/pharmacy/workbench.blade.php` | Packaging visualizer card + qty selector |
| `resources/views/admin/nursing/workbench.blade.php` | Packaging dropdown in billing section |
| `resources/views/admin/inventory/store-workbench/index.blade.php` | Packaging-formatted qty display |
| `resources/views/admin/inventory/purchase-orders/create.blade.php` | Packaging dropdown + base equiv. column in line items |
| `resources/views/admin/inventory/purchase-orders/edit.blade.php` | Pre-fill packaging selections |
| `resources/views/admin/inventory/purchase-orders/show.blade.php` | Dual qty display (packaging + base units) |
| `resources/views/admin/inventory/purchase-orders/receive.blade.php` | Packaging-aware receive with cross-pkg support |
| `resources/views/admin/inventory/purchase-orders/accounts_payable.blade.php` | Type badges |

---

## 10. UI/UX Wireframes

### 10.1 Product Type Selector (Create/Edit Form)

```html
<!-- Radio button group with colored icons -->
<div class="product-type-selector">
    <label class="type-option active" data-type="drug">
        <input type="radio" name="product_type" value="drug" checked>
        <i class="mdi mdi-pill"></i>
        <span>Drug</span>
        <small>Medications & pharmaceuticals</small>
    </label>
    <label class="type-option" data-type="consumable">
        <input type="radio" name="product_type" value="consumable">
        <i class="mdi mdi-bandage"></i>
        <span>Consumable</span>
        <small>Gloves, syringes, cotton</small>
    </label>
    <label class="type-option" data-type="utility">
        <input type="radio" name="product_type" value="utility">
        <i class="mdi mdi-broom"></i>
        <span>Utility</span>
        <small>Cleaning & office supplies</small>
    </label>
</div>
```

### 10.2 Packaging Repeater (Create/Edit Form)

```html
<!-- Base Unit Configuration -->
<div class="base-unit-config card-modern p-3 mb-3">
    <div class="row align-items-center">
        <div class="col-md-4">
            <label class="form-label-modern">Base Unit Name</label>
            <input name="base_unit_name" placeholder="e.g. Tablet, ml, Piece, Capsule"
                   class="form-control-modern" value="Piece">
            <small class="text-muted">The smallest countable/measurable unit</small>
        </div>
        <div class="col-md-4">
            <label class="form-label-modern">Allow Decimal Quantities?</label>
            <div class="custom-switch-wrapper">
                <input type="checkbox" name="allow_decimal_qty" id="allow_decimal_qty">
                <label for="allow_decimal_qty">Enable for liquids, creams, etc.</label>
            </div>
            <small class="text-muted">When ON: 15.5 ml, 0.5 Tablets allowed</small>
        </div>
    </div>
</div>

<!-- Dynamic repeater with hierarchy visualization -->
<div id="packaging-levels">
    <!-- Base Unit (display only, uses base_unit_name) -->
    <div class="packaging-row level-0 locked">
        <span class="level-indicator">Base</span>
        <span class="base-unit-display">Piece</span> <!-- JS-synced from input above -->
        <span class="conversion-display">= 1 base unit</span>
    </div>

    <!-- Level 1 (editable, fully customizable name) -->
    <div class="packaging-row level-1">
        <span class="level-indicator">L1</span>
        <input name="packagings[0][name]" placeholder="e.g. Strip, Bottle, Sachet" class="form-control-modern">
        <input name="packagings[0][units_in_parent]" type="number" step="any" placeholder="Qty" class="form-control-modern">
        <span class="per-label">× <span class="prev-unit">Piece</span></span>
        <span class="conversion-display">= <strong>10</strong> base units</span>
        <button class="btn-remove-level"><i class="mdi mdi-close"></i></button>
    </div>

    <!-- Level 2 (auto-chains from Level 1) -->
    <div class="packaging-row level-2">
        <span class="level-indicator">L2</span>
        <input name="packagings[1][name]" placeholder="e.g. Box, Case, Tray" class="form-control-modern">
        <input name="packagings[1][units_in_parent]" type="number" step="any" placeholder="Qty" class="form-control-modern">
        <span class="per-label">× <span class="prev-unit">Strip</span></span>
        <span class="conversion-display">= <strong>200</strong> base units</span>
        <button class="btn-remove-level"><i class="mdi mdi-close"></i></button>
    </div>

    <!-- Add button -->
    <button id="add-packaging-level" class="btn btn-outline-primary btn-sm">
        <i class="mdi mdi-plus"></i> Add Packaging Level
    </button>
</div>
```

> **Note:** `step="any"` on qty inputs allows decimal values for liquid conversions
> (e.g., 1 Bottle = 100.0 ml). The JS component live-updates the conversion display
> and chains the "per X" label from the previous level's name. All names are free-text.

### 10.3 Pharmacy Workbench — Packaging Visualizer

```html
<!-- Shown when a product is selected in pharmacy dispense -->
<div class="packaging-visualizer card-modern p-3">
    <h6><i class="mdi mdi-package-variant-closed text-primary"></i> Packaging</h6>

    <!-- Visual chain (dynamically built from product's packagings) -->
    <div class="packaging-chain">
        <span class="pkg-node base">Tablet</span>
        <span class="pkg-arrow">×10 →</span>
        <span class="pkg-node">Strip</span>
        <span class="pkg-arrow">×20 →</span>
        <span class="pkg-node">Box</span>
        <span class="pkg-arrow">×20 →</span>
        <span class="pkg-node top">Carton</span>
    </div>

    <!-- Quick qty with packaging selector -->
    <div class="d-flex align-items-center gap-2 mt-2">
        <input type="number" id="pkg-qty" class="form-control-modern" style="width:80px" value="1" step="any">
        <select id="pkg-unit" class="form-control-modern" style="width:180px">
            <option value="1">Tablet (base unit)</option>
            <option value="10">Strip (10 Tablets)</option>
            <option value="200">Box (200 Tablets)</option>
            <option value="4000">Carton (4000 Tablets)</option>
        </select>
        <span class="text-muted">= <strong id="pkg-total">200</strong> Tablets</span>
    </div>

    <!-- Smart stock display -->
    <div class="mt-2 text-muted small">
        <i class="mdi mdi-warehouse"></i>
        In Stock: <strong>4,200 Tablets</strong>
        (1 Carton, 1 Box, 0 Strips, 0 Tablets)
    </div>
</div>
```

**For liquid products (e.g., Cough Syrup):**
```html
<div class="packaging-visualizer card-modern p-3">
    <h6><i class="mdi mdi-bottle-tonic text-primary"></i> Packaging</h6>
    <div class="packaging-chain">
        <span class="pkg-node base">ml</span>
        <span class="pkg-arrow">×100 →</span>
        <span class="pkg-node">Bottle</span>
        <span class="pkg-arrow">×24 →</span>
        <span class="pkg-node top">Carton</span>
    </div>
    <div class="d-flex align-items-center gap-2 mt-2">
        <input type="number" step="0.01" value="15" style="width:80px" class="form-control-modern">
        <select class="form-control-modern" style="width:180px">
            <option value="1">ml (base unit)</option>
            <option value="100">Bottle (100 ml)</option>
            <option value="2400">Carton (2400 ml)</option>
        </select>
        <span class="text-muted">= <strong>15</strong> ml</span>
    </div>
    <div class="mt-2 text-muted small">
        <i class="mdi mdi-warehouse"></i>
        In Stock: <strong>2,350 ml</strong> (23 Bottles, 50 ml)
    </div>
</div>
```

### 10.4 Type Badge Styles

```css
.badge-drug       { background: #d4edda; color: #155724; }  /* Green */
.badge-consumable { background: #fff3cd; color: #856404; }  /* Yellow */
.badge-utility    { background: #d1ecf1; color: #0c5460; }  /* Blue */
```

### 10.5 Index DataTable Column Format

```javascript
// Product column — combined info
{
    data: "product_info",
    render: function(data) {
        return `
            <div class="d-flex align-items-center">
                <i class="mdi ${data.type_icon} me-2" style="font-size:1.5rem; color:${data.type_color}"></i>
                <div>
                    <strong>${data.product_name}</strong>
                    <br><small class="text-muted">${data.product_code}</small>
                    <span class="badge badge-light ms-1">${data.category_name}</span>
                </div>
            </div>
        `;
    }
}
```

### 10.6 Purchase Order Line Item UI

```html
<!-- Enhanced PO line item row -->
<tr class="po-line-item" data-product-id="123">
    <td>
        <!-- Product Select2 search (existing) -->
        <select class="product-search" name="items[0][product_id]"></select>
    </td>
    <td>
        <!-- NEW: Packaging dropdown (populated on product select) -->
        <select class="packaging-select form-control-modern" name="items[0][packaging_id]">
            <option value="" data-base="1">Tablet (base unit)</option>
            <option value="5" data-base="10">Strip (10 Tablets)</option>
            <option value="6" data-base="200" selected>Box (200 Tablets)</option>
            <option value="7" data-base="4000">Carton (4000 Tablets)</option>
        </select>
    </td>
    <td>
        <!-- Qty input (in selected packaging) -->
        <input type="number" step="any" name="items[0][packaging_qty]" value="50"
               class="form-control-modern pkg-qty-input">
    </td>
    <td>
        <!-- Base unit equivalent (auto-calculated, read-only) -->
        <div class="base-equiv">
            <strong class="base-qty-display">10,000</strong>
            <small class="base-unit-name text-muted">Tablets</small>
        </div>
        <!-- Hidden: actual ordered_qty in base units -->
        <input type="hidden" name="items[0][ordered_qty]" value="10000">
    </td>
    <td>
        <!-- Unit cost (per packaging unit) -->
        <input type="number" step="0.01" name="items[0][unit_cost]" value="120"
               class="form-control-modern">
        <small class="text-muted">per Box</small>
    </td>
    <td>
        <!-- Line total -->
        <strong class="line-total">₦6,000</strong>
    </td>
</tr>
```

```javascript
// JS: Live conversion on packaging or qty change
$('.pkg-qty-input, .packaging-select').on('change', function() {
    const row = $(this).closest('.po-line-item');
    const pkgQty = parseFloat(row.find('.pkg-qty-input').val()) || 0;
    const basePerPkg = parseFloat(row.find('.packaging-select option:selected').data('base')) || 1;
    const baseQty = pkgQty * basePerPkg;

    row.find('.base-qty-display').text(baseQty.toLocaleString());
    row.find('input[name$="[ordered_qty]"]').val(baseQty);

    // Update line total
    const unitCost = parseFloat(row.find('[name$="[unit_cost]"]').val()) || 0;
    row.find('.line-total').text('₦' + (pkgQty * unitCost).toLocaleString());
});
```

### 10.7 Purchase Order Receive Form (Packaging-Aware)

```html
<!-- Enhanced receive row with cross-packaging support -->
<tr class="receive-item" data-item-id="45">
    <td>
        <strong>Paracetamol 500mg</strong>
        <br><small class="text-muted">PCM-500 • 🟢 Drug</small>
    </td>
    <td>
        <!-- What was ordered -->
        <span>50 Boxes</span>
        <br><small class="text-muted">(10,000 Tablets)</small>
    </td>
    <td>
        <!-- What's been received so far -->
        <span>30 Boxes</span>
        <br><small class="text-muted">(6,000 Tablets)</small>
    </td>
    <td>
        <!-- Pending -->
        <span class="text-warning">20 Boxes</span>
        <br><small class="text-muted">(4,000 Tablets)</small>
    </td>
    <td>
        <!-- Receive now: qty + packaging (can differ from ordered packaging!) -->
        <div class="d-flex gap-2 align-items-center">
            <input type="number" step="any" name="received[45][packaging_qty]"
                   class="form-control-modern" style="width:80px">
            <select name="received[45][packaging_id]" class="form-control-modern" style="width:140px">
                <option value="" data-base="1">Tablet</option>
                <option value="5" data-base="10">Strip</option>
                <option value="6" data-base="200">Box</option>
                <option value="7" data-base="4000">Carton</option>
            </select>
        </div>
        <small class="receive-base-equiv text-muted">= 0 Tablets</small>
    </td>
</tr>
```

---

## Key Design Decisions

| Decision | Rationale |
|----------|-----------|
| **Base unit is the system core** | ALL internal calculations, stock ledger, billing, accounting, journal entries, and reporting operate in base units. Packaging is purely a UI/UX convenience. Every screen shows base unit equivalent alongside packaging qty. |
| **Customizable base unit name** | Products are diverse: "Tablet", "ml", "Capsule", "Litre", "Piece". A fixed "Piece" label would be confusing for liquids. The admin picks the right name. |
| **`allow_decimal_qty` flag** | Liquids (syrups, IV fluids) need fractional quantities (15.5 ml). Solids don't. The flag controls validation and display formatting per-product. |
| **DECIMAL(12,4) for qty conversions** | Supports liquid packaging: 1 Bottle = 100.0000 ml. Integer would truncate. 4 decimal places covers all practical cases. |
| **Fully customizable hierarchy (no fixed standard)** | Every product is different. Gloves come in Packs, drugs in Strips, liquids in Bottles, cleaning supplies in Drums. The admin builds the chain from scratch with free-text names. |
| **`base_unit_qty` stored (not computed on-the-fly)** | Avoids recursive queries. Updated on save via model event. |
| **Self-referential hierarchy (parent_packaging_id)** | Cleaner than adjacency list. Allows flexible nesting without schema changes. |
| **`product_type` enum, not FK to separate table** | Only 3 fixed types. An enum is simpler, faster, and sufficient. If types proliferate later, migrate to a lookup table. |
| **Default `drug` for existing products** | Zero disruption — all existing products continue to appear in pharmacy/all searches. Admins can reclassify over time. |
| **Drugs sellable at walk-in** | Walk-in patients buy OTC medications directly. Restricting walk-in to consumables/utilities would be impractical. All types are sellable at reception. |
| **Shared Blade components** | `packaging-qty-selector` and `packaging-visualizer` are used in nurse billing, pharmacy workbench, requisitions, and PO forms — consistent UX, single maintenance point. |
| **Packaging optional per product** | Products without packagings work exactly as before (qty in base units). No forced data entry. |
| **PO cross-packaging receive** | Supplier may ship in different packaging than ordered (ordered Boxes, shipped Cartons). The receive form allows selecting any packaging for the incoming qty. |
| **Legacy `has_have`/`has_piece`/`howmany_to` retained** | Not removed in this phase. The new `allow_decimal_qty` + packaging system supersedes them. A future cleanup migration can remove them once all references are updated. |

## Migration Safety

- All new columns are **nullable** or have **defaults** — zero risk to existing data
- `product_type` defaults to `drug` — existing products remain functional
- Packaging tables are additive — no existing columns modified
- All FK constraints use `ON DELETE SET NULL` or `CASCADE` appropriately
- Recommended: Run on staging first, verify with `php artisan migrate --pretend`

---

## Appendix: Base Unit Principle (Golden Rule)

> **Every quantity stored in the database is in BASE UNITS.**
>
> - `products.current_quantity` = base units
> - `stock_batches.current_qty` = base units
> - `store_stocks.current_quantity` = base units
> - `purchase_order_items.ordered_qty` = base units
> - `purchase_order_items.received_qty` = base units
> - `product_requests.qty` = base units
> - `product_or_service_requests.qty` = base units
> - `store_requisition_items.requested_qty` = base units
>
> The `packaging_id` + `packaging_qty` columns on these tables are **metadata for display only**.
> They record what the user entered in the UI so it can be shown back in human-friendly form.
> All arithmetic (stock deduction, billing amounts, journal entries, FIFO) uses the base unit columns.
>
> If a packaging row is deleted, the base unit qty remains correct. The system never breaks.

---

*This plan is ready for phase-by-phase implementation. Confirm to proceed with Phase 1 (Database & Models).*
