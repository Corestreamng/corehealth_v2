# Clinical Consumables and Kits Multi-Token Search Architecture

**Date**: 2026-11-05  
**Git Release Tag**: v2.6.0.67  
**Applies to**: Nursing Workbench, Doctor Workbench, Pharmacy Workbench, Maternity Workbench, BillingKit, Clinical Orders & Service Combos

---

## 1. Problem Statement & Root-Cause Diagnosis

Hospital staff reported two significant search barriers across the clinical application:
1. **Inability to find items from the middle of their names or with non-contiguous keywords**:
   - Searching for `"wound"` or `"wond"` failed to find items like `"Private ward - Wound dressing - Minor"` or `"Private ward - Wound dressing - Major"`.
   - Previous SQL queries used strict, single-string `LIKE "%{$term}%"`. Queries such as `"Private ward- wound dressing"` (missing the space before the hyphen), `"ward wound"`, `"private wound"`, or `"wound minor"` yielded 0 results because SQL required exact contiguous string matching.
   - Products with double spaces or punctuation in catalog names (e.g. `"PARACETAMOL  INJ.  (300MG)"`) failed whenever staff entered a single space (`"paracetamol inj"`).
2. **Consumables vs. Services Schema Disconnect**:
   - In clinical ward operations, nurses treat items like `"Private ward - Wound dressing"`, `"ICU- Cotton Wool"`, `"Cannulation"`, `"Catheterization"`, and `"Setting of IV Line"` as consumable supplies and ward bedside procedures.
   - However, in the database, wound dressings and bedside procedures are cataloged in the `services` table under Category 8 (`Procedures`) and Category 4 (`Nursing`), while the Nurse Consumables billing tab (`#consumable-search`) strictly queried the `products` table.
   - Because `products` had 0 records matching wound dressings, nurses searching under "Consumables" received zero results.
3. **Clinical Kit / Service Combos Search Blind Spots**:
   - `Route::get('service-combos/search')` previously matched only `service_name LIKE "%{$term}%"`.
   - If a clinical kit was named `"ANC Investigations combo (Return)"`, staff searching for `"PCV"`, `"Hepatitis"`, `"Urinalysis"`, or `"anc return"` found nothing, because the search failed to inspect child items in `bundleItems` and could not handle non-contiguous words.

---

## 2. Architecture & Solution Design

### 2.1 Unified Clinical Search Helper (`App\Helpers\ClinicalSearchHelper`)
A centralized, PSR-12 compliant search engine was introduced to govern medical searches across products, services, and combos:
- **`tokenize(string $term): array`**:
  - Cleans and splits input on non-alphanumeric unicode characters (`[^\p{L}\p{N}]+`).
  - Generates discrete search tokens with duplicate removal.
- **`getSynonyms()` / `expandTokens()`**:
  - Handles common clinical abbreviations and spelling variations:
    - `wond` / `woud` / `wnd` &rarr; `wound`
    - `inj` / `injs` &rarr; `injection`
    - `tab` / `tabs` &rarr; `tablet`
    - `syr` &rarr; `syrup`
    - `susp` &rarr; `suspension`
    - `cap` / `caps` &rarr; `capsule`
    - `paracet` &rarr; `paracetamol`
    - `canula` / `cannula` &rarr; bidirectional alias
    - `cather` &rarr; `catheter`
    - `amox`, `ampic`, `artem`, `cipro`, `metro`, `salb`, `ceftriax`, `genta`, `diclo`
- **`applyServiceSearch(Builder $query, string $term, ?int $categoryId, array $allowedCategoryIds)`**:
  - Employs a dual-tier matching strategy: direct exact substring match on `service_name` / `service_code` OR boolean AND matching across all tokens/synonyms.
  - Supports multiple allowed categories (e.g. allowing both Category 8 Procedures and Category 4 Bedside Care when searching procedures).
- **`applyProductSearch(Builder $query, string $term, ?int $categoryId, ?string $type)`**:
  - Applies tokenized multi-keyword search across `product_name` and `product_code`.
- **`applyComboSearch(Builder $query, string $term, ?int $categoryId)`**:
  - Matches combo titles directly or through tokens.
  - Queries `bundleItems` (`service` and `product` relationships), allowing staff to discover clinical kits by searching for any drug, lab test, or consumable contained inside the kit.
- **`findConsumableServices(string $term, int $limit)`**:
  - Surfaces consumable nursing procedures (Categories 4, 8, 5) when nurses search within consumable billing workflows.

---

## 3. Endpoints and Controller Refactoring

1. **`NursingWorkbenchController@searchProducts` (`/nursing-workbench/search-products`)**:
   - Queries `Product` with `ClinicalSearchHelper::applyProductSearch`.
   - Also retrieves matching consumable services via `findConsumableServices`.
   - Batch-loads HMO tariffs for both products and services in a single query.
   - Tags each item with `is_service: false` or `is_service: true`.
2. **`NursingWorkbenchController@searchServices` (`/nursing-workbench/search-services`)**:
   - Utilizes `ClinicalSearchHelper::applyServiceSearch` for direct services.
   - Utilizes `ClinicalSearchHelper::applyComboSearch` for related combos.
3. **`ServiceController@liveSearchServices` (`/live-search-services`)**:
   - Direct service searches support multi-token matching.
   - Context `'procedure'` searches across both Category 8 (`Procedures`) and Category 4 (`Nursing/Bedside Procedures`).
   - Related combos inspect bundle item contents.
4. **`ProductController@liveSearchProducts` (`/live-search-products`)**:
   - Tokenized product search with HMO tariff resolution.
   - Related product combos match by kit name or bundled medication items.
5. **`PharmacyWorkbenchController@searchProducts` (`/pharmacy-workbench/search-products`)**:
   - Multi-token drug search with store stock batch lookups.
6. **`MaternityWorkbenchController@searchServices` (`/maternity-workbench/search-services`)**:
   - Multi-token service and combo search.
7. **`routes/web.php` (`api.service-combos.search`)**:
   - Service combo endpoint updated with `ClinicalSearchHelper::applyComboSearch`.
8. **`TreatmentPlanController@index` (`/treatment-plans`)**:
   - Multi-token search with synonym expansion for treatment plan browsing.

---

## 4. Frontend & BillingKit UX Integration (`public/js/billing-shared.js`)

- **Dual-Type Badges**:
  - Search results display explicit visual badges: `<span class="badge bg-primary">[Service]</span>` or `<span class="badge bg-info">[Product]</span>`.
- **Seamless Tab Switching**:
  - When a nurse searches under "Consumables" and selects a nursing procedure (e.g. `"Private ward - Wound dressing - Minor"`), `BillingKit` automatically switches the UI to the "Services" tab (`#billing-services-tab`), pre-fills the service details and calculated price, and notifies the nurse with an informational toast.
  - This eliminates inventory batch errors while ensuring staff can find and bill nursing consumables from either tab.

---

## 5. Verification & Test Suite

1. **Feature Test**:
   - `tests/Feature/Nursing/ConsumableAndClinicalKitSearchTest.php`
   - Covers tokenization, synonym expansion, middle-of-string search, typo resilience, consumable service integration, and combo bundle search.
   - **Result**: `OK (5 tests, 28 assertions)`
2. **Domain Suite Regression**:
   - Full nursing domain suite: `vendor/bin/phpunit tests/Feature/Nursing/`
   - **Result**: `OK (44 tests, 164 assertions)`
3. **Linting & Code Style**:
   - Verified via `composer lint` (0 errors across 1,334 files).
   - Verified standalone JS via `node --check public/js/billing-shared.js`.

