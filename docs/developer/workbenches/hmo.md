# HMO Tariffs & Claims Developer API Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | HMO EXECUTIVE</span>

## 1. Controllers & Route Contracts
- **Primary Controllers**:
  - `App\Http\Controllers\HmoWorkbenchController` — Claims submission and vetting queue.
  - `App\Http\Controllers\Admin\TariffManagementController` — Tariff matrix and override management.
- **Primary Routes (`routes/web.php` & `routes/hmo_tariffs.php`)**:
  - `GET /hmo/workbench` (`hmo.workbench`) — Claims submission and vetting queue.
  - `GET /hmo/reports` (`hmo.reports`) — Insurer claims summary and remittance reports.
  - `POST /hmo/claims/batch` (`hmo.claims.batch`) — Batch claims compilation and export.
  - `GET /admin/hmo-tariffs/overrides` (`hmo-tariffs.overrides`) — Tariff overrides management view.
  - `GET /admin/hmo-tariffs/overrides-data` (`hmo-tariffs.overrides.data`) — JSON feed for overrides datatable.
  - `POST /admin/hmo-tariffs/overrides` (`hmo-tariffs.overrides.store`) — Save/update tariff override rule.
  - `DELETE /admin/hmo-tariffs/overrides/{id}` (`hmo-tariffs.overrides.delete`) — Deactivate and remove override.

## 2. Models & Tariff Override Architecture
- `Hmo`: Insurer profile, default tariff discount, claim submission format.
- `HmoScheme`: Plan category (e.g., Corporate, NHIS, State Health Scheme, Private).
- `HmoTariff`: Underlying pricing matrix mapping each product/service to an HMO with `payable_amount`, `claims_amount`, and `coverage_mode` (`express`, `primary`, `secondary`).
- `TariffOverride`: Custom interception rules that supersede standard tariffs at HMO or Scheme level.
  - Fields: `hmo_id`, `hmo_scheme_id`, `target_type` (`product`, `service`, `product_category`, `service_category`, `all_products`, `all_services`), `target_id`, `override_type` (`percentage`, `fixed`), `amount`, `coverage_mode` (`express`, `primary`, `secondary`, or `null`), `is_active`.
  - **Resolution Specificity Ranking**:
    1. Exact HMO + Exact Product/Service (Score: 15)
    2. Exact HMO + Item Category (Score: 13)
    3. Exact HMO + All Products/Services (Score: 11)
    4. Scheme + Exact Product/Service (Score: 5)
    5. Scheme + Item Category (Score: 3)
    6. Scheme + All Products/Services (Score: 1)
  - **Coverage Mode Resolution (`HmoHelper::getTariffOverrideData`)**:
    - **Explicit Override**: If `coverage_mode` is set on the override (`express`, `primary`, or `secondary`), it dictates the request's coverage mode and validation status (`express` &rarr; `approved`, `primary`/`secondary` &rarr; `pending`).
    - **Inherit (Null)**: If omitted, the request inherits the underlying `HmoTariff.coverage_mode` for that specific item, falling back to `express` if unlisted.

## 3. Testing Verification
```bash
# Tariff Overrides & Pricing Pipeline Tests
vendor/bin/phpunit tests/Feature/HMO/TariffOverrideTest.php
vendor/bin/phpunit tests/Feature/HMO/TariffManagementTest.php
```

👉 **[Read User Story Guide for HMO](/user-guides/workbenches/hmo)**
