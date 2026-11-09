# Nursing & Inpatient MAR Developer API Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | NURSE</span>

## 1. Controllers & Route Contracts
- **Primary Controller**: `App\Http\Controllers\NursingWorkbenchController`.
- **Primary Routes (`routes/nursing_workbench.php`)**:
  - `GET /nursing-workbench` (`nursing-workbench.index`) — Nursing cockpit and ward bed census.
  - `GET /nursing-workbench/search-products` (`nursing-workbench.search-products`) — Multi-token consumable and service search.
  - `POST /nursing-workbench/vitals` (`nursing-workbench.vitals.save`) — Outpatient and inpatient vitals recording.
  - `POST /nursing-workbench/schedule/{scheduleId}/administer` (`nursing-workbench.schedule.administer`) — Medication administration record action.

## 2. Multi-Token Search Architecture (`ClinicalSearchHelper`)
- Uses `App\Helpers\ClinicalSearchHelper::applyMultiTokenSearch()` across `products` and `services`.
- Auto-expands abbreviations (`inj` $\rightarrow$ `injection`, `canula` $\rightarrow$ `cannula`, `wond` $\rightarrow$ `wound`).
- Integrates nursing billable procedures (categories 4, 8, 5) alongside products with type tags.

## 3. Testing Verification
```bash
vendor/bin/phpunit tests/Feature/Nursing/ConsumableAndClinicalKitSearchTest.php
```

👉 **[Read User Story Guide for Nursing](/user-guides/workbenches/nursing)**
