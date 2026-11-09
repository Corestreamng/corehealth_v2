# OpsAudit & Verification Developer API Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | AUDITOR | AUDIT</span>

## 1. Controllers & Route Contracts
- **Primary Controller**: `App\Http\Controllers\OpsAuditController` extending `OpsAuditBaseController`.
- **Primary Routes (`routes/web.php`)**:
  - `GET /audit-workbench` (`audit.workbench`) — Main audit dashboard and worksheets.
  - `POST /audit-workbench/audit/mark/bulk-stamp` (`audit.bulk_stamp`) — Visible page batch stamping (`stampCurrentPage`).
  - `GET /ops-audit/reception` (`ops-audit.reception`) — Reception operations audit.

## 2. Models & Architectural Pattern
- Shared logic lives in `OpsAuditBaseController`.
- Audit stamps lock audited time windows, preventing edits to reconciled transactions.

## 3. Testing Verification
```bash
vendor/bin/phpunit tests/Feature/Audit/
```

👉 **[Read User Story Guide for OpsAudit](/user-guides/workbenches/ops-audit)**
