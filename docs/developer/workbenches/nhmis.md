# NHMIS Monthly Summary Developer Reference

<span class="badge-role">AUTHORITATIVE ROLE GUARD: SUPERADMIN | ADMIN | RECEPTIONIST (unit/dept head)</span>

## 1. Controllers & Routes
- **Controller:** `App\Http\Controllers\NhmisWorkbenchController`. Access is enforced by `authorizeAccess()`: `SUPERADMIN`/`ADMIN`, or `RECEPTIONIST` with `staff_profile.is_unit_head`/`is_dept_head`.
- **Routes** (`routes/web.php`, prefix `nhmis-workbench`, name `nhmis.`):
  - `GET /` (`nhmis.workbench`): form for a period (`year`, `month`; `0` = full year, `255` = custom range).
  - `POST /compile`: `NhmisDataAggregatorService::compileReport()`.
  - `GET /drill-down` (`nhmis.drill-down`): evidence records for `cell_key` + `report_id`.
  - Service linking routes: see the [Laboratory reference](/developer/workbenches/laboratory#_4-nhmis-outcome-tracking-service-linking).

## 2. Evidence engine (single source of truth)
`App\Services\Nhmis\NhmisDataAggregatorService` with the traits `Concerns\AggregatesClinicalSections` and `Concerns\AggregatesMaternalAndChildSections`.

- `build(NhmisMonthlyReport)` returns `values`, `evidence`, `unplaced` and `manual` for **every schema cell** (661 cells in v2019).
- Each count is the number (or `qty` sum) of evidence items added with `add($row, $col, $src, $id, $patientId, $date, $detail, $qty)`. `$col = null` means the record cannot be placed in a column: it is counted in the row total and in `unplaced[$row]`.
- `drillDown()` returns `getCellEvidence()`, the same list, so **form value = drill-down record count** by construction. The build is cached for 30 minutes per report (`nhmis_evidence:{id}:{compiled_at}`) and refreshed on compile.
- **Manual cells:** `MANUAL_ROWS` (EMR has no data) plus dynamic reasons (`markManual`) when a required pharmacy product does not exist or an indicator has no linked service. These are stored as `auto_value = null`, with `metadata.manual_reason`.
- **Persistence:** `nhmis_monthly_report_values` (`auto_value`, `final_value`, `override_value`, `metadata.unplaced`, `metadata.manual_reason`). Overrides survive recompiles.

## 3. Shared rules
| Concern | Rule |
|---|---|
| Age / sex | From `patients.dob` / `gender` on the event date. Missing values are **never** defaulted (old code assumed ≥20 y and male). |
| Pregnancy | `maternity_enrollments.lmp` → first `delivery_records.delivery_date` (or EDD + 14 d). `isPregnantOrPostpartum()` adds 42 days. |
| Diagnoses | `diagnosisStatus()`: structured items (ICD prefix or keyword in name; custom items by name; `comment_1` QUERY/DIFFRENTIAL = query) plus the assessment section of `encounters.notes` (`ass`, `assessment`, `diagnosis`, `impression`, `dx`, `managed for`). Returns `confirmed`, `query` or null; negations (`no`, `nil`, `negative`, `denies`…) are skipped. |
| New cases | `patientsWithPriorDiagnosis()`: no earlier encounter with the condition (any note text). IMCI uses a 14-day window. |
| Pharmacy | `dispensed()`: `product_requests.status = 3`, dated `dispense_date` (else `created_at`); product names matched with word-boundary regexes. |
| Lab | `resultedLabs()`: services linked in `nhmis_service_mappings`, result present, dated `COALESCE(sample_date, created_at)`. Positive = `nhmis_outcome`, else parsed result. |
| Attendance | One per patient-day across encounters, ANC, PNC and immunization visits. Inpatient days are those between `admission_requests.created_at` and `discharge_date` (encounters carry no admission link). |

## 4. Row sources
| Rows | Source |
|---|---|
| 1-4 | Patient-days (above); `admission_requests` by `COALESCE(bed_assign_date, created_at)`; discharges by `discharge_date` (matches the Nursing DNS report). |
| 5-9 | `death_records` excluding `death_type` "Still Birth"; maternal = female who died pregnant/≤42 d postpartum or with an obstetric cause. |
| 10-16 | `anc_visits` (age bands 10-14, 15-19, 20-34, 35-49, 50+); first visit GA from visit, booking or LMP; counselling from visit notes. |
| 17-25 | Linked syphilis/hep B/hep C tests on women pregnant at test date; treated = penicillin-class dispensed; referred = external referral. |
| 26-33 | IPTp dose = SP dispenses in this pregnancy; IFA dispensed; Hb < 7 g/dL; urine protein ≥ +. |
| 36-46 | `delivery_records.type_of_delivery` (`svd`, `assisted_vaginal`/`vacuum`, `*_cs`); preterm from LMP; complications excluding NIL; partograph entries; oxytocin flag or misoprostol dispensed; MVA/PAC linked procedures; unsafe abortion from admission diagnoses. |
| 47 | `postnatal_visits` by `days_postpartum` (or days since delivery). |
| 48-60 | `maternity_babies` by delivery date; HIV-positive mothers from linked HIV tests or diagnoses; APGAR < 7 at 1 min; neonatal tetanus/jaundice diagnoses under 28 days. |
| 63-88 | `immunization_records` (word-boundary antigen matching; Td pregnancy from enrolment) plus birth doses on the baby record; AEFI from `adverse_reaction` (total only). |
| 101-108 | `child_growth_records` (birth anthropometry excluded; new = first GMP record); Vitamin A records or dispensing; deworming dispensed; SAM admissions. |
| 110-114 | Under-5 diagnoses; ORS **and** zinc, or amoxicillin, dispensed within −1/+3 days. |
| 115-131 | Contraceptives dispensed; new acceptor = first contraceptive ever; postnatal FP counselling/method fields; sterilisation procedures. |
| 132-136 | External referrals + transfer discharges. |
| 137-145 | New NCD cases (first-ever diagnosis); GDM only for women. |
| 146-160 | Fever (full note, negation-aware); RDT/MP tests; confirmed = positive test; clinical = diagnosis without positive test; ACT/other/injectable antimalarials dispensed. |
| 162-184 | TB diagnostic tests (presumptive); hepatitis tests, antivirals, referrals; GBV, fistula, trachoma, snake bite diagnoses; fistula repair outcomes. |

## 5. Testing
```bash
vendor/bin/phpunit tests/Feature/Nhmis/NhmisEvidenceAccuracyTest.php
vendor/bin/phpunit tests/Feature/Nhmis/
```
`NhmisEvidenceAccuracyTest` asserts that every cell equals its evidence, that manual cells are null, and covers the regressions fixed here: negation and queries, custom diagnoses, missing DOB, still births, CS types, NIL complications, internal referrals, unresulted labs, and the LLIN/amoxicillin false match.

👉 **[Read the NHMIS User Guide](/user-guides/workbenches/nhmis)**
