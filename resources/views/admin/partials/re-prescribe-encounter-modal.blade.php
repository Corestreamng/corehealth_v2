{{-- Re-Prescribe from Encounter — Preview + Apply Modal (Plan §5.3) --}}
<div class="modal fade" id="rePrescribeEncounterModal" tabindex="-1" aria-labelledby="rePrescribeEncounterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="rePrescribeEncounterModalLabel">
                    <i class="fa fa-redo"></i> Re-prescribe from Previous Encounter
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-2">
                    Select items below to re-prescribe into the current session. Duplicates are flagged automatically.
                </p>
                <input type="hidden" class="rp-enc-id" value="">
                <div class="rp-preview-body">
                    {{-- Populated dynamically by JS --}}
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex w-100 justify-content-between align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            onclick="$('#rePrescribeEncounterModal .rp-item-check:not(:disabled)').prop('checked', true)">
                        <i class="fa fa-check-double"></i> Select All
                    </button>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary rp-apply-btn" onclick="ClinicalOrdersKit._rpApplySelected()">
                            <i class="fa fa-redo"></i> Re-prescribe Selected
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
