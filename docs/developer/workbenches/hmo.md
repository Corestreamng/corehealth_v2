# HMO Tariffs & Claims Developer API Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | HMO EXECUTIVE</span>

## 1. Controllers & Route Contracts
- **Primary Controller**: `App\Http\Controllers\HmoWorkbenchController` and `App\Http\Controllers\Admin\TariffController`.
- **Primary Routes (`routes/web.php`)**:
  - `GET /hmo/workbench` (`hmo.workbench`) — Claims submission and vetting queue.
  - `GET /hmo/reports` (`hmo.reports`) — Insurer claims summary and remittance reports.
  - `POST /hmo/claims/batch` (`hmo.claims.batch`) — Batch claims compilation and export.

## 2. Models & Observers
- `Hmo`: Insurer profile, default tariff discount, claim submission format.
- `PriceObserver`: Automatically updates HMO-specific tariffs when base product/service prices change.

## 3. Testing Verification
```bash
vendor/bin/phpunit tests/Feature/Hmo/
```

👉 **[Read User Story Guide for HMO](/user-guides/workbenches/hmo)**
