{{-- Shared Plan Viewer Modal (Phase 11) --}}
<div class="modal fade" id="treatmentPlanViewerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" style="max-width: 90%;">
        <div class="modal-content" style="border-radius: 16px; overflow: hidden; min-height: 70vh;">
            <div class="modal-header border-0 text-white" style="background: linear-gradient(135deg, #00796b, #004d40);">
                <h5 class="modal-title">
                    <i class="fa fa-clipboard-list me-2"></i>
                    <span id="tpv-plan-name"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="min-height: 450px;">
                <div class="row h-100">
                    <div class="col-md-8 border-end" id="tpv-details-content">
                        <div class="text-center py-5">
                            <div class="spinner-border text-teal" role="status"><span class="visually-hidden">Loading...</span></div>
                        </div>
                    </div>
                    <div class="col-md-4" id="tpv-timeline-content">
                        <h6 class="text-muted mb-3" style="font-size: 0.82rem;"><i class="fa fa-stream me-1"></i> Activity Timeline</h6>
                        <div id="tpv-timeline-items" class="text-muted small">Loading...</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 d-flex flex-wrap justify-content-between align-items-center">
                <div class="d-flex flex-wrap gap-2 me-auto" id="tpv-action-buttons">
                    <button type="button" class="btn btn-teal btn-sm" id="tpv-set-active-btn" onclick="TreatmentPlansTab.setActive(window._tpvCurrentPlanId)" style="border-radius: 8px;">
                        <i class="fa fa-check-circle me-1"></i> Set as Active
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="tpv-edit-btn" onclick="ClinicalOrdersKit.openEditModalFromViewer()" style="border-radius: 8px;">
                        <i class="fa fa-edit me-1"></i> Edit Plan
                    </button>
                    <button type="button" class="btn btn-outline-dark btn-sm" id="tpv-retire-btn" onclick="ClinicalOrdersKit.openRetireModalFromViewer()" style="border-radius: 8px;">
                        <i class="fa fa-archive me-1"></i> Retire Plan
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-teal btn-sm" id="tpv-refresh-btn" onclick="ClinicalOrdersKit.viewTreatmentPlan(window._tpvCurrentPlanId)" style="border-radius: 8px;">
                        <i class="fa fa-sync-alt me-1"></i> Refresh
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" style="border-radius: 8px;">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
