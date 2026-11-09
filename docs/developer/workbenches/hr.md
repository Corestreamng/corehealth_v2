# Human Resources & ESS Developer API Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | HR</span>

## 1. Controllers & Route Contracts
- **Primary Controller**: `App\Http\Controllers\HR\StaffController` and `App\Http\Controllers\LocumPassController`.
- **Primary Routes**:
  - `GET /hr/staff` (`hr.staff.index`) — Staff master and status governance.
  - `POST /hr/staff/status` (`hr.staff.update_status`) — Active, on-leave, suspended, terminated toggles.
  - `POST /locum/pass/generate` (`locum.pass.generate`) — 6-digit PIN pass generation with automated TTL.

## 2. Models
- `Staff`: Cadre, specialization, Spatie user linkage, professional body credentials.
- `LocumPass`: Shift-bounded token with sha256 PIN hash and expiration timestamps.

👉 **[Read User Story Guide for Human Resources](/user-guides/workbenches/hr)**

