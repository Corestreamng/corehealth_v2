# Maternity & Child Health Developer API Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | MATERNITY</span>

## 1. Controllers & Route Contracts
- **Primary Controller**: `App\Http\Controllers\MaternityWorkbenchController`.
- **Primary Routes (`routes/maternity_workbench.php`)**:
  - `GET /maternity-workbench` (`maternity-workbench.index`) — Maternity cockpit and antenatal register.
  - `POST /maternity-workbench/enroll` (`maternity-workbench.enroll`) — Antenatal enrollment and obstetric history log.
  - `GET /maternity-workbench/patient/{id}/details` (`maternity-workbench.patient.details`) — Full maternal medical records.
  - `POST /maternity-workbench/delivery/{id}/partograph` (`maternity-workbench.delivery.partograph.store`) — Digital partograph progress entry.

## 2. Models & Schema Governance
- `Patient`: Model method `getNextFileNumber('ANC-')` and `getNextFileNumber('PANC-')`.
- `MaternityProfile`: Parity, gravidity, LMP, EDD, and ultrasound-derived gestational age.

## 3. Testing Verification
```bash
vendor/bin/phpunit tests/Feature/Maternity/MaternityWorkbenchTest.php
```

👉 **[Read User Story Guide for Maternity](/user-guides/workbenches/maternity)**
