# Laboratory and Imaging Result Signatures & Attestation Governance

**Date**: 2026-10-28  
**Git Release Tag**: `v2.6.0.56`  

---

## 1. Executive Summary

This release standardizes the attestation, signature, and approval governance model across both Laboratory and Imaging diagnostic services in CoreHealth v2. It establishes:
1. **Balanced Dual Signature Block**: "Entered By" on the left and "Approved By" on the right across all diagnostic reports, printable views, and preview modals.
2. **Dynamic Staff Specialization / Cadre Pre-population**: Result entry automatically pre-fills the operator's clinical designation from their staff profile (`specialization->name ?: (cadre->name ?: job_title)`) in a fully editable input field.
3. **Approval-Gated vs Direct Entry Dual Behavior**:
   - **When Approval Workflow is Disabled**: The result entry modal renders side-by-side forms for Entry (left) and Approval (right). The operator must explicitly type the approver's name and designation before the result can be completed and published.
   - **When Approval Workflow is Enabled**: The result entry modal captures "Entered By" and queues the result for Unit/Department Head review (Status 5). During approval review and confirmation, the approving supervisor is presented with editable "Approved By" and "Designation" fields pre-populated from their staff profile to review or customize prior to sign-off.
4. **Standardized Report Footer Legal & Clinical Notice**:
   - **Laboratory**: *"This laboratory report was electronically generated and authorized. Results should be interpreted in the context of clinical findings."*
   - **Imaging**: *"This imaging report was electronically generated and authorized. Results should be interpreted in the context of clinical findings."*

---

## 2. Architecture & Data Model

### Database Migration
Migration `database/migrations/2026_10_28_071500_add_signature_and_approver_fields_to_lab_and_imaging_requests.php` adds the following columns to both `lab_service_requests` and `imaging_service_requests`:
- `entered_by_name` (`string`, nullable): Full name of the clinician/technician who typed/entered the result.
- `entered_by_designation` (`string`, nullable): Staff cadre/specialization of the technician entering the result.
- `approved_by_name` (`string`, nullable): Full name of the signing consultant/supervisor authorizing the result.
- `approved_by_designation` (`string`, nullable): Specialization/cadre of the signing supervisor.

### Eloquent Models
Updated `$fillable` on:
- `app/Models/LabServiceRequest.php`
- `app/Models/ImagingServiceRequest.php`

---

## 3. Workflow Implementation

### A. Result Entry Modal (`resources/views/admin/partials/invest_res_modal.blade.php` & `invest_res_js.blade.php`)
- **Left Column ("Entered By")**:
  - `entered_by_name`: Defaults to current authenticated user's name (`userfullname(Auth::id())`), editable.
  - `entered_by_designation`: Pre-populated from `staff_profile->specialization->name` or `staff_profile->cadre->name` (with domain fallbacks "Medical Laboratory Scientist" or "Radiologist / Imaging Technician"), editable and required.
- **Right Column ("Approved By")**:
  - **Approval Disabled**: Renders interactive inputs (`approved_by_name` and `approved_by_designation`), with validation requiring both before result submission.
  - **Approval Enabled**: Displays an informational badge indicating that approval will take place in the formal department approval queue.

### B. Approval Review & Confirmation Flow
- **Workbench Controllers**:
  - `app/Http/Controllers/LabWorkbenchController.php`:
    - `getApprovalDetail($id)`: Returns `entered_by`, `entered_by_designation`, `approved_by_name`, and `approved_by_designation`.
    - `approveResult($id)`: Accepts `approved_by_name` and `approved_by_designation` from request payload, falling back to authenticated approver's profile.
  - `app/Http/Controllers/ImagingWorkbenchController.php`:
    - `getApprovalDetail($id)`: Returns approver metadata and staff specialization.
    - `approveResult($id)`: Stores customized approver name and designation.
- **Frontend Modules**:
  - `public/js/lab-reports.js`: Displays Approver Sign-Off card in `approvalReviewModal` and pre-populates `approveConfirmModal` inputs. Transmits values on confirmation.
  - `public/js/imaging-results.js`: Displays Approver Sign-Off card and pre-populates `approveConfirmModal` inputs for imaging studies.
- **Modals**:
  - `resources/views/admin/lab/partials/_modals.blade.php`: Added editable Approver Name and Specialization / Cadre inputs to `approveConfirmModal`.
  - `resources/views/admin/imaging/partials/_modals.blade.php`: Added editable Approver Name and Specialization / Cadre inputs to `approveConfirmModal`.

### C. Diagnostic Reports, Printable Views, & View Modals
Updated signature layout to Left = Entered By and Right = Approved By across:
1. `resources/views/admin/lab_service_requests/show.blade.php`
2. `resources/views/admin/imaging_service_requests/show.blade.php`
3. `resources/views/admin/partials/invest_res_view_modal.blade.php` & `invest_res_view_js.blade.php`
4. `resources/views/admin/partials/invest_res_view_imaging_modal.blade.php` & `invest_res_view_imaging_js.blade.php`
5. `resources/views/admin/patients/partials/modals.blade.php`

---

## 4. Verification & Testing

- **Syntax Verification**:
  - `node --check public/js/lab-reports.js`
  - `node --check public/js/imaging-results.js`
  - `node --check public/js/lab-workbench.js`
  - `node --check public/js/imaging-workbench.js`
- **Feature Tests**:
  - `tests/Feature/Lab/LabWorkbenchTest.php`:
    - `test_lab_save_result_persists_entered_by_and_approved_by_when_approval_disabled`
    - `test_lab_approve_result_persists_edited_approver_and_designation`
    - `test_lab_report_show_view_renders_entered_by_and_approved_by`
  - `tests/Feature/Imaging/ImagingWorkbenchTest.php`:
    - `test_imaging_save_result_persists_entered_by_and_approved_by_when_approval_disabled`
    - `test_imaging_approve_result_persists_edited_approver_and_designation`
    - `test_imaging_report_show_view_renders_entered_by_and_approved_by`
