{{-- Treatment Plan Modal — Shared partial for Doctor Encounter + Nurse Workbench
     Ref: CLINICAL_ORDERS_PLAN.md §6.4
     Include this partial in both views. It needs jQuery + Bootstrap 5 + toastr.
--}}

<div class="modal fade" id="treatmentPlanModal" tabindex="-1" aria-labelledby="treatmentPlanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="treatmentPlanModalLabel">
                    <i class="fa fa-clipboard-list"></i> Treatment Plans
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{-- Browse / Preview tabs --}}
                <ul class="nav nav-tabs mb-3" id="tp-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="tp-browse-tab" data-bs-toggle="tab" data-bs-target="#tp-browse-pane" type="button" role="tab">Browse Plans</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="tp-preview-tab" data-bs-toggle="tab" data-bs-target="#tp-preview-pane" type="button" role="tab">Preview</button>
                    </li>
                </ul>

                <div class="tab-content">
                    {{-- Browse pane --}}
                    <div class="tab-pane fade show active" id="tp-browse-pane" role="tabpanel">
                        <div class="row mb-2">
                            <div class="col-8">
                                <input type="text" id="tp-search-input" class="form-control form-control-sm" placeholder="Search plans...">
                            </div>
                            <div class="col-4">
                                <select id="tp-specialty-filter" class="form-select form-select-sm">
                                    <option value="">All Specialties</option>
                                </select>
                            </div>
                        </div>
                        <div id="tp-plan-list" style="max-height: 400px; overflow-y: auto;">
                            <div class="text-center text-muted py-4">
                                <i class="fa fa-spinner fa-spin"></i> Loading plans...
                            </div>
                        </div>
                    </div>

                    {{-- Preview pane --}}
                    <div class="tab-pane fade" id="tp-preview-pane" role="tabpanel">
                        <div id="tp-preview-content">
                            <div class="text-center text-muted py-4">Select a plan to preview</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="tp-modal-footer" style="display:none;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="tp-apply-btn" disabled>
                    <i class="fa fa-check"></i> Apply Selected Items
                </button>
            </div>
        </div>
    </div>
</div>

{{-- "Save current as template" modal --}}
<div class="modal fade" id="saveTemplateModal" tabindex="-1" aria-labelledby="saveTemplateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="saveTemplateModalLabel">
                    <i class="fa fa-save"></i> Save as Template
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-5">
                        <div class="mb-3">
                            <label class="form-label">Template Name <span class="text-danger">*</span></label>
                            <input type="text" id="save-tpl-name" class="form-control" placeholder="e.g. Malaria Protocol (Adult)">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea id="save-tpl-desc" class="form-control" rows="2" placeholder="Optional description..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Specialty</label>
                            <input type="text" id="save-tpl-specialty" class="form-control" placeholder="e.g. General Medicine">
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" id="save-tpl-global" class="form-check-input">
                            <label class="form-check-label" for="save-tpl-global">Share globally (visible to all staff)</label>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label fw-bold"><i class="fa fa-eye"></i> Items to be saved</label>
                        <div id="save-tpl-preview" style="max-height:300px; overflow-y:auto; border:1px solid #dee2e6; border-radius:0.25rem; padding:0.5rem;">
                            <div class="text-center text-muted py-3">Gathering items...</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success btn-sm" id="save-tpl-confirm-btn">
                    <i class="fa fa-save"></i> Save Template
                </button>
            </div>
        </div>
    </div>
</div>
