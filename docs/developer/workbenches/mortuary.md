# Mortuary Records Developer API Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | MORGUE</span>

## 1. Controllers & Route Contracts
- **Primary Controller**: `App\Http\Controllers\MorgueWorkbenchController`.
- **Primary Routes (`routes/web.php`)**:
  - `GET /morgue-workbench` (`morgue.workbench`) — Deceased intake register and vault compartment grid.
  - `POST /morgue-workbench/intake` (`morgue.intake.store`) — Body intake and compartment assignment.
  - `POST /morgue-workbench/release` (`morgue.release.store`) — Relative identification verification and release action.

## 2. Models & Schema Fields
- `MorgueRecord`: Death certificate reference, compartment ID, daily preservation fee, release status.
- `MorgueCompartment`: Cold-room vault tracking (`available`, `occupied`, `decontamination`).

## 3. Testing Verification
```bash
vendor/bin/phpunit tests/Feature/Morgue/
```

👉 **[Read User Story Guide for Mortuary](/user-guides/workbenches/mortuary)**
