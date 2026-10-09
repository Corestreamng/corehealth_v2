<div class="modal fade" id="procedureOutcomeModal" tabindex="-1" role="dialog" aria-labelledby="procedureOutcomeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="procedureOutcomeModalLabel">
                    <i class="fa fa-notes-medical mr-2 text-primary"></i>Document Procedure Outcome
                </h5>
                <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3">
                <div id="procedureOutcomeAlert" class="alert alert-warning py-2 px-3 small" style="display:none;">
                    <i class="fa fa-info-circle mr-1"></i>
                    <strong>Standard Procedure Detected:</strong> Quick documentation for bedside or external procedure.
                    For detailed consent and tracking, visit the
                    <a href="#" id="procedureOutcomeShowLink" target="_blank" class="alert-link text-decoration-underline">Procedure Show Page</a>.
                </div>

                {{-- Dynamic NHMIS Delegated Indicator Bar (Shown ONLY when procedure is mapped) --}}
                <div id="procedure_nhmis_indicator_bar" class="card border-info mb-3" style="display:none; background: rgba(var(--hospital-primary-rgb, 1, 27, 51), 0.03); border-left: 4px solid var(--hospital-primary, #0a6cf2) !important;">
                    <div class="card-body p-2 d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <span class="badge badge-primary mr-1"><i class="fa fa-clipboard-check mr-1"></i> NHMIS Delegated</span>
                            <strong id="procedure_nhmis_indicator_name" class="text-dark small"></strong>
                        </div>
                        <small class="text-muted"><i class="fa fa-sync-alt mr-1"></i> Syncs to NHMIS monthly reports</small>
                    </div>
                </div>

                <form id="procedureOutcomeForm">
                    <input type="hidden" id="outcome_procedure_id" name="procedure_id">
                    <input type="hidden" id="procedure_nhmis_outcome" name="nhmis_outcome">
                    <input type="hidden" id="procedure_nhmis_outcome_raw" name="nhmis_outcome_raw">
                    <input type="hidden" id="procedure_is_nhmis_mapped" value="0">
                    
                    <div class="form-group mb-3">
                        <label class="d-block mb-1 font-weight-bold">Outcome <span class="text-danger">*</span></label>
                        {{-- Interactive Outcome Pills --}}
                        <div class="btn-group btn-group-toggle w-100 mb-2 flex-wrap" id="modal_outcome_pills">
                            <button type="button" class="btn btn-outline-success modal-outcome-pill" data-val="successful" data-is-pos="1">
                                <i class="fa fa-check-circle mr-1"></i> Successful
                            </button>
                            <button type="button" class="btn btn-outline-warning modal-outcome-pill" data-val="complications" data-is-pos="0">
                                <i class="fa fa-exclamation-triangle mr-1"></i> Complications
                            </button>
                            <button type="button" class="btn btn-outline-danger modal-outcome-pill" data-val="aborted" data-is-pos="0">
                                <i class="fa fa-times-circle mr-1"></i> Aborted
                            </button>
                            <button type="button" class="btn btn-outline-info modal-outcome-pill" data-val="converted" data-is-pos="0">
                                <i class="fa fa-exchange-alt mr-1"></i> Converted
                            </button>
                        </div>
                        <select class="form-control form-control-sm" id="procedure_outcome" name="outcome" required>
                            <option value="">-- Select Outcome --</option>
                            <option value="successful">Successful</option>
                            <option value="complications">Complications</option>
                            <option value="aborted">Aborted</option>
                            <option value="converted">Converted</option>
                        </select>
                    </div>

                    <div class="form-group mt-2">
                        <label for="procedure_outcome_notes" class="font-weight-bold">Post-Op / Outcome Notes</label>
                        <textarea class="form-control" id="procedure_outcome_notes" name="outcome_notes" rows="3" placeholder="Enter any post-operative notes or details about the outcome..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light p-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-procedure-outcome">
                    <i class="fa fa-save mr-1"></i>Save Outcome
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('js/procedure-outcome-modal.js') }}"></script>
@endpush
