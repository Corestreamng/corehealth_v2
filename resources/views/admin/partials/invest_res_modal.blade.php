<style>
    #investResModal .modal-dialog { max-width: min(1380px, 94vw) !important; width: 100% !important; }
    #investResModal .ck-editor__editable { min-height: 55vh !important; max-height: calc(100vh - 260px) !important; overflow-y: auto !important; }
    #investResModal .ck.ck-editor, #investResModal .ck-content table { width: 100% !important; }
    #investResModal .ck-content figure.table { width: 100% !important; margin: 0 0 1rem 0 !important; }
</style>
<div class="modal fade" id="investResModal" tabindex="-1" role="dialog" aria-labelledby="investResModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
        <div class="modal-content">
            <form action="{{ route(!empty($save_route) ? $save_route : 'service-save-result') }}" method="post" enctype="multipart/form-data" id="investResForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="investResModalLabel">Enter Result (<span
                            id="invest_res_service_name"></span>)</h5>
                    <button type="button" data-bs-dismiss="modal" class="btn- btn-close btn-close-white" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="invest_res_entry_id" id="invest_res_entry_id">
                    <input type="hidden" name="invest_res_is_edit" id="invest_res_is_edit" value="0">
                    <input type="hidden" name="invest_res_template_version" id="invest_res_template_version" value="1">
                    <textarea name="invest_res_template_submited" style="display:none;" id="invest_res_template_submited"></textarea>

                    <!-- V2 Hidden Input for structured data -->
                    <input type="hidden" name="invest_res_template_data" id="invest_res_template_data">
                    <input type="hidden" name="deleted_attachments" id="deleted_attachments">
                    <input type="hidden" name="entry_source" id="invest_res_entry_source" value="workbench">

                    <!-- V1/V2 Template Version Toggle (only shown when V2 template exists for the service) -->
                    <div id="template_version_toggle_container" class="mb-3" style="display:none;">
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small me-1">Entry Mode:</span>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="template_version_toggle" id="toggle_v1" value="1" autocomplete="off">
                                <label class="btn btn-outline-secondary" for="toggle_v1"><i class="mdi mdi-file-document-edit-outline"></i> Free Text (V1)</label>
                                <input type="radio" class="btn-check" name="template_version_toggle" id="toggle_v2" value="2" autocomplete="off">
                                <label class="btn btn-outline-secondary" for="toggle_v2"><i class="mdi mdi-form-select"></i> Structured (V2)</label>
                            </div>
                        </div>
                    </div>

                    <!-- V1 Template Selector (only shown for V1 templates) -->
                    <div id="v1_template_selector_container" class="mb-3" style="display:none;">
                        <div class="d-flex align-items-center gap-2 w-100">
                            <div style="flex: 1; max-width: 650px;">
                                <select class="form-control" id="v1_result_template_select" style="width: 100%;">
                                    <option value="">-- Search and Insert Template --</option>
                                </select>
                            </div>
                            <button type="button" class="btn btn-outline-primary" id="v1_insert_template_btn" disabled onclick="insertV1ResultTemplate()">
                                <i class="mdi mdi-file-import"></i> Insert
                            </button>
                        </div>
                    </div>

                    <!-- NHMIS Standardized Clinical Outcome Auto-Sense Bar -->
                    <div id="nhmis_outcome_container" class="card border-info mb-3 nhmis-outcome-bar" style="display: none; background: rgba(var(--hospital-primary-rgb, 1, 27, 51), 0.03); border-left: 4px solid var(--hospital-primary, #0a6cf2) !important;">
                        <div class="card-body p-2">
                            <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-1">
                                <span class="small font-weight-bold text-dark d-flex align-items-center flex-wrap gap-1">
                                    <i class="mdi mdi-checkbox-marked-circle-outline text-primary"></i> 
                                    <span>NHMIS Monthly Return:</span>
                                    <span id="nhmis_indicator_badge" class="badge badge-light text-primary border"></span>
                                    <span class="badge bg-danger text-white ms-1" style="font-size: 0.68rem;"><i class="mdi mdi-asterisk"></i> Required Outcome</span>
                                </span>
                                <span class="small" id="nhmis_auto_sense_indicator" style="font-size: 0.78rem;">
                                    <i class="mdi mdi-auto-fix text-success"></i> Auto-sensed from result
                                </span>
                            </div>
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-1">
                                <div id="nhmis_outcome_pills" class="d-flex flex-wrap gap-2">
                                    <!-- Dynamically populated radio pills for supported outcomes -->
                                </div>
                                <div class="d-none d-md-flex align-items-center gap-1" style="min-width: 170px;">
                                    <small class="text-muted text-nowrap">Dropdown:</small>
                                    <select class="form-control form-control-sm" id="nhmis_outcome_select" style="font-size: 0.8rem; height: 32px; padding: 2px 8px;">
                                        <option value="">-- Choose Outcome --</option>
                                    </select>
                                </div>
                            </div>
                            <div id="nhmis_outcome_validation_hint" class="small text-danger mt-1 fw-bold" style="display: none;">
                                <i class="mdi mdi-alert-circle-outline"></i> Selecting an outcome is required for this mapped service. Please click an outcome pill above.
                            </div>
                            <input type="hidden" name="nhmis_outcome" id="nhmis_outcome">
                            <input type="hidden" name="nhmis_outcome_raw" id="nhmis_outcome_raw">
                        </div>
                    </div>

                    <!-- V1 Template: WYSIWYG Editor -->
                    <div id="v1_template_container">
                        <div id="invest_res_template_editor" class="ckeditor-content" style="min-height: 300px; border: 1px solid #ddd; padding: 10px;"></div>
                    </div>

                    <!-- V2 Template: Structured Form -->
                    <div id="v2_template_container" style="display: none;">
                        <div id="v2_form_fields"></div>
                    </div>

                    <!-- Result Attestation & Signatures Section (Left: Entered By, Right: Approved By) -->
                    <div class="card mt-3 mb-3 signature-entry-card" id="invest_res_signatures_card" style="background: rgba(var(--hospital-primary-rgb, 1, 27, 51), 0.02); border: 1px solid #dee2e6;">
                        <div class="card-header py-2 px-3 bg-light d-flex justify-content-between align-items-center">
                            <span class="font-weight-bold text-dark small">
                                <i class="mdi mdi-draw-pen text-primary me-1"></i> Result Attestation &amp; Signatures
                            </span>
                            <span id="approval_status_badge" class="badge badge-secondary"></span>
                        </div>
                        <div class="card-body p-3">
                            <div class="row g-3">
                                <!-- Left Column: Entered By -->
                                <div class="col-md-6 border-end-md">
                                    <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                                        <span class="fw-bold text-dark small">
                                            <i class="mdi mdi-account-edit text-info me-1"></i> Entered By <span class="text-muted fw-normal">(Left Signature Line)</span>
                                        </span>
                                    </div>
                                    <div class="form-group mb-2">
                                        <label class="form-label small text-muted mb-1" for="entered_by_name">Staff Name</label>
                                        <input type="text" class="form-control form-control-sm" name="entered_by_name" id="entered_by_name" placeholder="Full name of staff entering result">
                                    </div>
                                    <div class="form-group mb-0">
                                        <label class="form-label small text-muted mb-1" for="entered_by_designation">
                                            Specialization / Cadre <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" class="form-control form-control-sm" name="entered_by_designation" id="entered_by_designation" required placeholder="e.g. Medical Laboratory Scientist, Sonographer">
                                        <small class="text-muted" style="font-size: 0.72rem;">Pre-filled from your staff profile specialization / cadre; editable.</small>
                                    </div>
                                </div>

                                <!-- Right Column: Approved By -->
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center justify-content-between mb-2 pb-1 border-bottom">
                                        <span class="fw-bold text-dark small">
                                            <i class="mdi mdi-check-decagram text-success me-1"></i> Approved By <span class="text-muted fw-normal">(Right Signature Line)</span>
                                        </span>
                                    </div>

                                    <!-- When approval workflow is DISABLED: manual input is required -->
                                    <div id="manual_approval_fields">
                                        <div class="alert alert-warning py-1 px-2 mb-2 small" style="font-size: 0.75rem;">
                                            <i class="mdi mdi-alert-circle-outline"></i> Result approval gate is disabled in settings. You must type the approver name and designation below.
                                        </div>
                                        <div class="form-group mb-2">
                                            <label class="form-label small text-muted mb-1" for="approved_by_name">
                                                Approver Name <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control form-control-sm" name="approved_by_name" id="approved_by_name" placeholder="e.g. Dr. Jane Smith">
                                        </div>
                                        <div class="form-group mb-0">
                                            <label class="form-label small text-muted mb-1" for="approved_by_designation">
                                                Approver Designation / Cadre <span class="text-danger">*</span>
                                            </label>
                                            <input type="text" class="form-control form-control-sm" name="approved_by_designation" id="approved_by_designation" placeholder="e.g. Consultant Pathologist / HOD, Chief Radiologist">
                                        </div>
                                    </div>

                                    <!-- When approval workflow is ENABLED: shows informational notice -->
                                    <div id="workflow_approval_notice" style="display: none;">
                                        <div class="alert alert-info py-2 px-3 mb-0 small" style="font-size: 0.78rem;">
                                            <div class="fw-bold mb-1"><i class="mdi mdi-shield-check text-primary"></i> Approval Workflow Enabled</div>
                                            <span>This result will be submitted to the Unit/Department Head Approval Queue for formal sign-off review before publishing.</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attachments Section -->
                    <div class="mt-4">
                        <h6><i class="fa fa-paperclip"></i> Attachments</h6>

                        <!-- Existing Attachments -->
                        <div id="existing_attachments_container" style="display: none;" class="mb-3">
                            <label class="form-label text-muted">Existing Files:</label>
                            <div id="existing_attachments_list" class="attachment-list"></div>
                        </div>

                        <!-- New Attachments -->
                        <div class="mb-3">
                            <label class="form-label">Add New Files:</label>
                            <input type="file" class="form-control" name="result_attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <small class="text-muted">Allowed types: PDF, Images, Word Docs (Max 10MB)</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Result</button>
                </div>
            </form>
        </div>
    </div>
</div>
