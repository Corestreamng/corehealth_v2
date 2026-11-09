# Inpatient Wards Developer API Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | NURSE</span>

## 1. Controllers & Route Contracts
- **Primary Controllers**: `App\Http\Controllers\BedController` and `App\Http\Controllers\WardController`.
- **Primary Routes (`routes/web.php`)**:
  - `GET /beds` (`beds.index`) — Hospital bed inventory and occupancy grid.
  - `POST /beds` (`beds.store`) — New bed registration and ward allocation.
  - `GET /wards` (`wards.index`) — Ward configuration and bed capacity lists.

## 2. Models & Schema Fields
- `Bed`: `bed_no`, `ward_id`, `status` (`vacant`, `occupied`, `maintenance`, `cleaning`).
- `Ward`: `name`, `code`, `capacity`, `gender_scoping`.

## 3. Testing Verification
```bash
vendor/bin/phpunit tests/Feature/Ward/
```

👉 **[Read User Story Guide for Inpatient Wards](/user-guides/workbenches/inpatient)**
