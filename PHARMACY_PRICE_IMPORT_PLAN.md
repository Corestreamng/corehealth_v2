# Pharmacy Drug Price & Tariff Import Plan

## Objective

Create a PHP artisan command (`pharmacy:import-price-list`) that imports the pharmacy price list CSV and updates:

1. **GOPD (base) selling prices** — `prices.current_sale_price`
2. **HMO tariffs** — `hmo_tariffs.claims_amount` + `hmo_tariffs.payable_amount` per product per HMO

---

## CSV Structure

| Col | Header | Description |
|-----|--------|-------------|
| 0   | Row # | Sequential row number (ignored) |
| 1   | NAME | Drug/product name — **used for matching** |
| 2   | GOPD | Base selling price (updates `prices.current_sale_price`) |
| 3   | HMO | Price for generic/unmatched HMOs |
| 4   | NHIS | Price for NHIS/NHIA scheme HMOs |
| 5   | CBN | Price for HMOs with "CBN" in their name |
| 6   | PLASHEMA | Price for PLASCHEMA/SHIS scheme HMOs |

---

## Price Column Interpretation

### Special Values
| Value | Meaning |
|-------|---------|
| Numeric (e.g. `500`) | The full tariff price for that category |
| `NC` or `N/C` | Not Covered — patient pays 100%, claims = 0 |
| `NEG` | Negotiable — skip, don't update this tariff |
| `PE{amount}` (e.g. `PE2000`) | Partial Exclusion — the amount is the **base**, patient pays 50%, HMO covers 50% |
| Empty/blank | Skip — no update |

### Tariff Split Rules by Scheme Type

| Column | Target HMOs | Patient Pays | HMO Covers | Coverage Mode |
|--------|------------|--------------|------------|---------------|
| **GOPD** | N/A (updates base price only) | — | — | — |
| **HMO** (generic) | All HMOs not matched by other rules¹ | 0 | Full amount | `primary` |
| **NHIS** | All HMOs under NHIS or NHIA scheme | 10% of amount | 90% of amount | `primary` |
| **CBN** | Any HMO with "CBN" in its name (case-insensitive) | 0 | Full amount | `primary` |
| **PLASHEMA** | All HMOs under SHIS scheme (PLASCHEMA) | 10% of amount | 90% of amount | `primary` |

¹ "Other rules" = NHIS/NHIA scheme, SHIS scheme, CBN-named HMOs, and Private-named HMOs.

### Special handling for NC / PE within NHIS, PLASHEMA columns:
- **NC** → Not covered: `payable_amount = GOPD price`, `claims_amount = 0` (patient pays 100% of GOPD)
- **PE{amount}** → `payable_amount = 50% of amount`, `claims_amount = 50% of amount`
- **Normal amount** → `payable_amount = 10% of amount`, `claims_amount = 90% of amount`

### Special handling for NC / PE within HMO, CBN columns:
- **NC** → Not covered: `payable_amount = GOPD price`, `claims_amount = 0`
- **PE{amount}** → `payable_amount = 50% of amount`, `claims_amount = 50% of amount`
- **Normal amount** → `payable_amount = 0`, `claims_amount = full amount` (HMO covers all)

### Private/GOPD HMOs:
- HMOs with "Private" in their name or under the SELF scheme → use **GOPD** price
- `payable_amount = GOPD price`, `claims_amount = 0` (patient pays full price, no HMO claim)

---

## HMO Categorization Logic

```
For each HMO in the database:
  1. If HMO name contains "Private" (case-insensitive) OR scheme code = "SELF"
     → Use GOPD column: payable = GOPD price, claims = 0
  
  2. If HMO name contains "CBN" (case-insensitive)
     → Use CBN column
  
  3. If HMO scheme code IN ('NHIS', 'NHIA') OR scheme name LIKE '%NHIS%' OR '%NHIA%'
     → Use NHIS column (10% payable / 90% claims for normal amounts)
  
  4. If HMO scheme code IN ('SHIS') OR scheme name LIKE '%PLASCHEMA%' OR '%SHIS%'
     → Use PLASHEMA column (10% payable / 90% claims for normal amounts)
  
  5. Otherwise (PHIS, CORPORATE, OTHERS, or unclassified)
     → Use HMO column
```

---

## Product Matching Strategy

1. **Exact match** on `products.product_name` (case-insensitive, trimmed)
2. **Fuzzy fallback**: Normalize both names (strip extra spaces, remove parenthetical info) and try again
3. **Unmatched products** are logged and reported; user reviews them manually

---

## Script Implementation Plan

### Command: `app/Console/Commands/ImportPharmacyPriceList.php`

**Signature:**
```
pharmacy:import-price-list {file : Path to the CSV file}
                           {--dry-run : Preview changes without applying}
                           {--skip-gopd : Skip updating base GOPD prices}
                           {--skip-tariffs : Skip updating HMO tariffs}
```

### Processing Steps

```
1. PARSE CSV
   - Skip header rows (rows 0-2 or until first numeric row number)
   - For each data row, extract: name, gopd, hmo, nhis, cbn, plashema
   - Clean values: trim whitespace, detect NC/NEG/PE/numeric/empty

2. LOAD REFERENCE DATA
   - All Products with their Price relation
   - All active HMOs with their Scheme relation
   - Categorize HMOs into groups: private, cbn, nhis, shis, generic

3. MATCH PRODUCTS
   - For each CSV row, find matching Product by name
   - Log unmatched rows

4. UPDATE GOPD PRICES (unless --skip-gopd)
   - For each matched product with a valid GOPD value:
     prices.current_sale_price = GOPD value

5. UPDATE HMO TARIFFS (unless --skip-tariffs)
   - For each matched product × each HMO group:
     a) Determine which CSV column applies to this HMO
     b) Parse the value (numeric / NC / PE / NEG / empty)
     c) If NEG or empty → skip
     d) Calculate payable_amount and claims_amount per rules above
     e) Upsert into hmo_tariffs (hmo_id + product_id unique)

6. REPORT
   - Summary table: products matched, unmatched, prices updated, tariffs created/updated
   - List of unmatched product names for manual review
```

### Pseudocode for Value Parsing

```php
function parseValue(string $raw): array {
    $val = strtoupper(trim($raw));
    
    if ($val === '' || $val === '-')
        return ['type' => 'skip'];
    
    if ($val === 'NC' || $val === 'N/C')
        return ['type' => 'nc'];
    
    if ($val === 'NEG')
        return ['type' => 'neg'];  // skip — negotiable
    
    if (str_starts_with($val, 'PE')) {
        $amount = (float) str_replace(',', '', substr($val, 2));
        return ['type' => 'pe', 'amount' => $amount];
    }
    
    // Numeric — remove commas, spaces
    $num = str_replace([',', ' '], '', $val);
    if (is_numeric($num))
        return ['type' => 'numeric', 'amount' => (float) $num];
    
    return ['type' => 'skip'];  // unrecognized
}
```

### Pseudocode for Tariff Calculation

```php
function calculateTariff(array $parsed, string $group, float $gopdPrice): ?array {
    if ($parsed['type'] === 'neg' || $parsed['type'] === 'skip')
        return null;  // don't update
    
    if ($parsed['type'] === 'nc') {
        // Not covered — patient pays 100% of GOPD price
        return ['payable' => $gopdPrice, 'claims' => 0, 'mode' => 'primary'];
    }
    
    if ($parsed['type'] === 'pe') {
        // Partial Exclusion — 50/50 split
        $half = round($parsed['amount'] / 2, 2);
        return ['payable' => $half, 'claims' => $half, 'mode' => 'primary'];
    }
    
    // Normal numeric amount
    $amount = $parsed['amount'];
    
    if (in_array($group, ['nhis', 'shis'])) {
        // NHIS/PLASCHEMA: 10% payable, 90% claims
        return [
            'payable' => round($amount * 0.10, 2),
            'claims'  => round($amount * 0.90, 2),
            'mode'    => 'primary',
        ];
    }
    
    if ($group === 'private') {
        // Private: patient pays full, no HMO claim
        return ['payable' => $amount, 'claims' => 0, 'mode' => 'primary'];
    }
    
    // Generic HMO / CBN: HMO covers full amount
    return ['payable' => 0, 'claims' => $amount, 'mode' => 'primary'];
}
```

---

## Edge Cases to Handle

| Case | Handling |
|------|----------|
| Product not found in DB | Log warning, skip row, include in unmatched report |
| Product has no Price record | Create Price record with GOPD value, or skip with warning |
| HMO has no scheme assigned | Treat as generic HMO group |
| Value has commas (e.g. "3,250") | Strip commas before parsing as number |
| Value has spaces (e.g. " 630") | Trim before parsing |
| Multiple products with same name | Warn and update all matches (or first match) |
| CSV has extra header/blank rows | Auto-detect data rows by checking if col 0 is numeric |
| GOPD column is empty/NC for a product | Don't update base price |

---

## Safety Measures

1. **`--dry-run`** mode shows all changes without applying them
2. **Database transaction** wraps all writes — all or nothing
3. **Backup prompt** — command asks for confirmation before applying
4. **Detailed logging** — all changes logged to `storage/logs/price-import.log`
5. **Summary report** — printed to console after run

---

## File Locations

| File | Path |
|------|------|
| Command | `app/Console/Commands/ImportPharmacyPriceList.php` |
| CSV file | Passed as argument (e.g. `storage/app/price-list.csv`) |
| Logs | `storage/logs/laravel.log` (via Log facade) |

---

## Example Usage

```bash
# Dry run — preview changes
php artisan pharmacy:import-price-list storage/app/price-list.csv --dry-run

# Full import — update prices and tariffs
php artisan pharmacy:import-price-list storage/app/price-list.csv

# Only update GOPD prices, skip tariffs
php artisan pharmacy:import-price-list storage/app/price-list.csv --skip-tariffs

# Only update tariffs, skip GOPD prices
php artisan pharmacy:import-price-list storage/app/price-list.csv --skip-gopd
```

---

## Estimated Impact

- ~510 product rows in CSV
- ~6 HMO scheme groups × N HMOs per group
- GOPD price updates: up to ~510 products
- Tariff upserts: up to ~510 × (total active HMOs) rows
