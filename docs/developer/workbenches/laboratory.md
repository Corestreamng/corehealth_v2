# Laboratory (LIS) Developer API Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | LAB SCIENTIST</span>

## 1. Controllers & Route Contracts
- **Primary Controller**: `App\Http\Controllers\LabWorkbenchController`.
- **Primary Routes (`routes/web.php`)**:
  - `GET /lab-workbench` (`lab.workbench`) — LIS sample collection, testing, and verification queue.
  - `GET /lab-workbench/queue` (`lab.queue`) — Live laboratory work orders DataTable feed.
  - `POST /lab-workbench/collect-sample` (`lab.collectSample`) — Specimen accessioning and barcoding action.
  - `GET /lab-workbench/approval-queue` (`lab.approval-queue`) — Consultant verification and sign-off queue.

## 2. Models & Schema Fields
- `LabServiceRequest`: Captures `entered_by_name`, `entered_by_designation`, `approved_by_name`, and `approved_by_designation`.
- `LabTemplate`: Structured numeric ranges, critical thresholds, and test units.

## 3. Testing Verification
```bash
vendor/bin/phpunit tests/Feature/Lab/
```

👉 **[Read User Story Guide for Laboratory](/user-guides/workbenches/laboratory)**
