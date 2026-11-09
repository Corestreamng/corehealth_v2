# Theatre & Surgery Developer API Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | SURGERY | DOCTOR | NURSE</span>

## 1. Controllers & Route Contracts
- **Primary Controller**: `App\Http\Controllers\SurgeryWorkbenchController`.
- **Primary Routes (`routes/surgery_workbench.php`)**:
  - `GET /surgery-workbench` (`surgery-workbench.index`) — Main surgical cockpit.
  - `GET /surgery-workbench/queue` (`surgery-workbench.queue`) — Surgical cases and procedure schedule.
  - `GET /surgery-workbench/patient/{patientId}/procedures` (`surgery-workbench.patient-procedures`) — Patient operative logs.
  - `GET /surgery-workbench/queue-counts` (`surgery-workbench.queue-counts`) — Theatre queue badges.

## 2. Models
- `Procedure`: Surgical checklist logs, anaesthesia notes, and surgical team assignments.
- `ServiceCombo`: Procedure bundles with child `bundleItems.service` and `bundleItems.product`.

## 3. Testing Verification
```bash
vendor/bin/phpunit tests/Feature/Surgery/
```

👉 **[Read User Story Guide for Theatre](/user-guides/workbenches/theatre)**
