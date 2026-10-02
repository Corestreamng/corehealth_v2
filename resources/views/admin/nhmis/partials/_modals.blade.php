{{-- =========================================================================
     MODAL 1: Clinical Diagnosis Audit & Case Insight Modal
     Reusing Reception Workbench & ClinicalReportsController Insight Patterns
     ========================================================================= --}}
<div class="modal fade" id="modalNhmisAudit" tabindex="-1" role="dialog" aria-labelledby="modalNhmisAuditTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title font-weight-bold text-primary" id="modalNhmisAuditTitle">
                    <i class="mdi mdi-microscope"></i> Clinical Diagnosis Audit & Encounter Insight
                </h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="row align-items-center mb-3">
                    <div class="col-md-7">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="nhmis-audit-keyword" placeholder="Search diagnosis name, keyword, or ICD-10 code (e.g. Malaria, B50, Hypertension, E11)...">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="button" id="btn-exec-audit">
                                    <i class="mdi mdi-magnify"></i> Search Diagnosis
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5 text-md-right mt-2 mt-md-0">
                        <span class="badge badge-info py-2 px-3 mr-2" id="audit-stat-patients">
                            <i class="mdi mdi-account-multiple"></i> Unique Patients: 0
                        </span>
                        <span class="badge badge-primary py-2 px-3" id="audit-stat-encounters">
                            <i class="mdi mdi-file-document-outline"></i> Encounters: 0
                        </span>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 520px;">
                    <table class="table table-sm table-hover table-bordered nhmis-audit-table" id="table-audit-results">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Patient Name</th>
                                <th>File No</th>
                                <th>Gender</th>
                                <th>Age</th>
                                <th>Doctor / Clinician</th>
                                <th>Clinic / Unit</th>
                                <th>Diagnosis / ICD-10</th>
                                <th>Clinical Notes</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-audit-results">
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">
                                    <i class="mdi mdi-information-outline"></i> Click "Audit" on any diagnosis row or enter a search query above to inspect underlying patient encounters.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- =========================================================================
     MODAL 2: Cell Override Reason Modal
     ========================================================================= --}}
<div class="modal fade" id="modalCellOverride" tabindex="-1" role="dialog" aria-labelledby="modalCellOverrideTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title font-weight-bold" id="modalCellOverrideTitle">
                    <i class="mdi mdi-pencil-box-outline text-warning"></i> Adjust Cell Value
                </h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <p class="small text-muted mb-1">Cell Key: <strong id="modal-override-cellkey"></strong></p>
                    <p class="small text-muted mb-2">System Auto-Computed Count: <strong id="modal-override-autoval" class="text-primary">0</strong></p>
                </div>
                <div class="form-group mb-3">
                    <label for="modal-override-input" class="form-label font-weight-bold">New Adjusted Count</label>
                    <input type="number" min="0" class="form-control" id="modal-override-input" required>
                </div>
                <div class="form-group mb-2">
                    <label for="modal-override-reason" class="form-label font-weight-bold">Justification / Audit Reason</label>
                    <textarea class="form-control" id="modal-override-reason" rows="3" placeholder="Provide audit reason (e.g. Reconciled with physical paper register, retrospective entry adjustment)..." required></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="btn-save-cell-override">Apply Adjustment</button>
            </div>
        </div>
    </div>
</div>

{{-- =========================================================================
     MODAL 3: Status & Verification Lock Modal
     ========================================================================= --}}
<div class="modal fade" id="modalVerifyLock" tabindex="-1" role="dialog" aria-labelledby="modalVerifyLockTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning-light">
                <h5 class="modal-title font-weight-bold text-dark" id="modalVerifyLockTitle">
                    <i class="mdi mdi-shield-lock-outline text-warning"></i> Verify and Lock Monthly Summary
                </h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small mb-3">
                    <i class="mdi mdi-alert"></i> <strong>Important:</strong> Verifying and locking this report signifies that all clinical registers and parameters have been reconciled. Locked reports cannot be re-compiled or modified without administrator clearance.
                </div>
                <div class="form-group mb-2">
                    <label for="modal-lock-notes" class="form-label font-weight-bold">Verification Notes / Clearance Remark</label>
                    <textarea class="form-control" id="modal-lock-notes" rows="3" placeholder="Enter optional clearance remarks or supervisory approval notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-warning font-weight-bold" id="btn-confirm-lock">
                    <i class="mdi mdi-lock-check"></i> Confirm Lock & Verify
                </button>
            </div>
        </div>
    </div>
</div>

{{-- =========================================================================
     MODAL 4: Service Delegations Modal (Facility Service Catalog Mapping)
     ========================================================================= --}}
<div class="modal fade" id="modalServiceMappings" tabindex="-1" role="dialog" aria-labelledby="modalServiceMappingsTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title font-weight-bold text-dark" id="modalServiceMappingsTitle">
                    <i class="mdi mdi-flask-round-bottom text-primary"></i> NHMIS Service Delegations & Multi-Modality Mapping
                </h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="alert alert-info small mb-3 d-flex flex-wrap justify-content-between align-items-center">
                    <div class="mb-2 mb-md-0" style="max-width: 65%;">
                        <i class="mdi mdi-information-outline"></i>
                        Facilities maintain independent service catalogs. Map each national standard NHMIS clinical indicator below to your local hospital services (supports multiple price tiers/service codes).
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary" id="btn-autodetect-mappings">
                            <i class="mdi mdi-auto-fix"></i> Auto-Detect Defaults
                        </button>
                        <button type="button" class="btn btn-outline-warning" id="btn-run-historical-backfill">
                            <i class="mdi mdi-history"></i> Run Historical Backfill
                        </button>
                    </div>
                </div>

                {{-- Modality Filter Navigation --}}
                <ul class="nav nav-pills nav-fill mb-3" id="mapping-modality-tabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active py-2 font-weight-bold" data-modality="all" href="javascript:void(0);">
                            <i class="mdi mdi-format-list-bulleted"></i> All Indicators
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-2 font-weight-bold" data-modality="investigation" href="javascript:void(0);">
                            <i class="mdi mdi-flask-outline"></i> Laboratory (Cat 2)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-2 font-weight-bold" data-modality="imaging" href="javascript:void(0);">
                            <i class="mdi mdi-radiology-box"></i> Imaging (Cat 6)
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-2 font-weight-bold" data-modality="procedure" href="javascript:void(0);">
                            <i class="mdi mdi-needle"></i> Procedures (Cat 8)
                        </a>
                    </li>
                </ul>

                <div class="table-responsive" style="max-height: 500px;">
                    <table class="table table-sm table-hover table-bordered" id="table-service-mappings">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 28%;">NHMIS Standard Indicator</th>
                                <th style="width: 14%;">Domain / Type</th>
                                <th style="width: 42%;">Delegated Hospital Services (Multi-Select)</th>
                                <th style="width: 16%;">Standardized Outcome Preset</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-service-mappings">
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="mdi mdi-loading mdi-spin"></i> Loading configured service delegations...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm btn-primary" id="btn-save-service-mappings">
                    <i class="mdi mdi-content-save"></i> Save Delegations
                </button>
            </div>
        </div>
    </div>
</div>

{{-- =========================================================================
     MODAL 5: Universal Cell Drill-Down Modal
     ========================================================================= --}}
<div class="modal fade" id="modalNhmisDrillDown" tabindex="-1" role="dialog" aria-labelledby="modalNhmisDrillDownTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title font-weight-bold text-dark" id="modalNhmisDrillDownTitle">
                    <i class="mdi mdi-clipboard-text-search-outline text-primary"></i> Cell Inspection & Patient Drill-Down
                </h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div class="mb-2 p-2 bg-light border rounded">
                    <h6 class="font-weight-bold text-dark mb-0">
                        <i class="mdi mdi-text-box-search-outline text-primary mr-1"></i> <span id="drilldown-row-label">Indicator Name</span>
                    </h6>
                </div>
                <div class="row align-items-center mb-3">
                    <div class="col-md-6">
                        <span class="badge badge-secondary font-weight-bold py-2 px-3 mr-2" id="drilldown-badge-cellkey"></span>
                        <span class="text-muted small" id="drilldown-period-text"></span>
                    </div>
                    <div class="col-md-6 text-md-right mt-2 mt-md-0">
                        <span class="badge badge-info py-2 px-3 mr-2" id="drilldown-stat-patients">
                            <i class="mdi mdi-account-multiple"></i> Unique Patients: 0
                        </span>
                        <span class="badge badge-primary py-2 px-3" id="drilldown-stat-records">
                            <i class="mdi mdi-file-document-outline"></i> Total Records: 0
                        </span>
                    </div>
                </div>

                <!-- Search & Filters Toolbar -->
                <div class="card bg-light border mb-3">
                    <div class="card-body p-2">
                        <div class="form-row align-items-center">
                            <div class="col-md-4 mb-2 mb-md-0">
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                                    </div>
                                    <input type="text" class="form-control" id="drilldown-search-input" placeholder="Search patient, file no, clinician, notes...">
                                    <div class="input-group-append" id="drilldown-search-clear" style="display:none; cursor:pointer;">
                                        <span class="input-group-text"><i class="mdi mdi-close"></i></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <select class="form-control form-control-sm" id="drilldown-hmo-filter">
                                    <option value="">All HMOs / Coverage</option>
                                    <option value="cash">Private / Cash (Non-HMO)</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-2 mb-md-0">
                                <select class="form-control form-control-sm" id="drilldown-scheme-filter">
                                    <option value="">All HMO Schemes</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-center justify-content-end">
                                <label class="mr-1 mb-0 small text-muted">Per page:</label>
                                <select class="form-control form-control-sm" id="drilldown-per-page" style="width: auto;">
                                    <option value="15">15</option>
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <button type="button" class="btn btn-outline-secondary btn-sm ml-1" id="drilldown-btn-reset" title="Reset Filters">
                                    <i class="mdi mdi-refresh"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 480px;">
                    <table class="table table-sm table-hover table-bordered" id="table-drilldown-results">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 4%;">#</th>
                                <th style="width: 12%;">Date & Time</th>
                                <th style="width: 18%;">Patient Full Name</th>
                                <th style="width: 11%;">File Number</th>
                                <th style="width: 15%;">HMO & Scheme</th>
                                <th style="width: 6%;">Sex</th>
                                <th style="width: 6%;">Age</th>
                                <th style="width: 13%;">Doctor / Clinician</th>
                                <th style="width: 15%;">Clinical Details / Result</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-drilldown-results">
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="mdi mdi-loading mdi-spin"></i> Loading underlying records...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Server-Side Pagination Bar -->
                <div class="d-flex align-items-center justify-content-between mt-3 px-1" id="drilldown-pagination-wrapper">
                    <div class="small text-muted" id="drilldown-pagination-info">
                        Showing 0 to 0 of 0 records
                    </div>
                    <nav aria-label="Drilldown pagination">
                        <ul class="pagination pagination-sm mb-0" id="drilldown-pagination-links">
                            <!-- Populated dynamically via JS -->
                        </ul>
                    </nav>
                </div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

