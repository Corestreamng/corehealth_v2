# Laboratory (LIS) Developer API Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | LAB SCIENTIST</span>

**Git Release Tag**: `v2.6.0.69`

## 1. Controllers & Route Contracts
- **Primary Controller**: `App\Http\Controllers\LabWorkbenchController`.
- **Primary Routes (`routes/web.php`)**:
  - `GET /lab-workbench` (`lab.workbench`) — LIS sample collection, testing, and verification queue.
  - `GET /lab-workbench/patient-search` (`lab.search-patients`) — Multi-token patient search by name, file number, phone, and `lab_number`.
  - `GET /lab-workbench/queue` (`lab.queue`) — Live laboratory work orders DataTable feed with `lab_number` filtering.
  - `GET /lab-workbench/next-lab-number` (`lab.nextLabNumber`) — Auto-increment generation with prefix parsing, max sequence extraction, and collision avoidance loop.
  - `GET /lab-workbench/check-lab-number` (`lab.checkLabNumber`) — Whitespace-normalized duplicate check distinguishing current patient vs cross-patient collisions.
  - `POST /lab-workbench/collect-sample` (`lab.collectSample`) — Specimen accessioning and barcoding action with cross-patient duplicate rejection (`422 Conflict`).
  - `GET /lab-workbench/approval-queue` (`lab.approvalQueue`) — Consultant verification and sign-off queue with exposed `lab_number`.
  - `GET /lab-workbench/reports` (`lab.reports`) — Laboratory reporting DataTable feed with dedicated `lab_number` column.

## 2. Models & Schema Fields
- `LabServiceRequest`: Captures `lab_number`, `sample_taken`, `sample_date`, `sample_taken_by`, `entered_by_name`, `entered_by_designation`, `approved_by_name`, and `approved_by_designation`.
- `LabTemplate`: Structured numeric ranges, critical thresholds, and test units.

## 3. Specimen Auto-Increment & Accession Architecture
- **Max Sequence Extraction**: Scans recent requests for the facility prefix (`LAB{YY} - `), extracting the true maximum sequential integer rather than relying on order creation ID (`orderBy('id', 'desc')`).
- **Whitespace Normalization**: Compares lab numbers with `REPLACE(lab_number, ' ', '') = ?` to prevent spacing inconsistencies (`LAB26 - 11624` vs `LAB26-11624`) from producing false negatives.
- **Session Accession Reuse**: Detects existing samples collected for the patient today, allowing accession grouping.
- **Cross-Patient Collision Guard**: Aborts any attempt to reuse an existing lab number for a different patient at both client validation and transaction level.

## 4. NHMIS Outcome Tracking & Service Linking
- **Killswitch**: `application_status.enable_nhmis_results_tracking` (boolean, default `true`; migration `2026_11_19_130000_add_nhmis_results_tracking_to_application_status`). Edited in `HospitalConfigController@update` and read through `appsettings('enable_nhmis_results_tracking')`.
- **Backend gate**: When the flag is off, the `NhmisServiceMapping` lookup is skipped in `LabWorkbenchController`, `LabServiceRequestController`, `ImagingWorkbenchController` and `ImagingServiceRequestController`. No 422 is raised and `nhmis_outcome` / `nhmis_classified_at` stay null.
- **Frontend gate**: `resources/views/admin/partials/invest_res_js.blade.php` exposes `_nhmisTrackingEnabled`. `_setupNhmisOutcome()` returns early, so the outcome bar never renders.
- **Service linking routes** (`nhmis.` prefix, `NhmisWorkbenchController`):
  - `GET /nhmis-workbench/service-mappings` (`nhmis.service-mappings`): indicators, current links, and the available service catalogue.
  - `POST /nhmis-workbench/service-mappings` (`nhmis.save-service-mappings`): JSON body `{mappings: {<indicator_code>: {service_ids: [], supported_outcomes, positive_outcomes}}}`. Each indicator sent is fully replaced, and an empty `service_ids` clears its links.
  - `POST /nhmis-workbench/service-mappings/auto-detect` (`nhmis.auto-detect-service-mappings`).
  - `GET /nhmis-workbench/service-mapping/{serviceId}` (`nhmis.service-mapping-single`): the lookup behind the result-entry outcome bar. A catalogue service (`serviceId > 0`) is resolved **only** by its saved links. Name keywords are used only when `serviceId` is `0` (free-form items that cannot be linked).
- **Keyword matching**: `NhmisServiceMapping::nameMatchesKeyword()` / `matchIndicatorByName()` match whole words only, so `hb` does not match "HBsAg" and `pt` does not match "SGPT". Used by Auto-Detect, the free-text lookup, and free-form procedure outcomes (`PatientProcedureController`).
- **UI**: The `#modalServiceMappings` markup lives in `resources/views/admin/nhmis/partials/_modals.blade.php`. Its logic is section 9 of `public/js/nhmis-workbench.js`: client-side `original` / `working` state, with only changed indicators sent as JSON. Styles are the `.nhm-*` rules in `public/css/nhmis-workbench.css`.

## 5. Testing Verification
```bash
vendor/bin/phpunit tests/Feature/Lab/LabWorkbenchTest.php
vendor/bin/phpunit tests/Feature/Nhmis/NhmisResultsTrackingToggleTest.php
```

👉 **[Read User Story Guide for Laboratory](/user-guides/workbenches/laboratory)**
