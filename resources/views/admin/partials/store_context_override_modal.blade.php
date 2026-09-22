<!-- Store Context Override Modal -->
<div class="modal fade" id="storeContextOverrideModal" tabindex="-1" aria-labelledby="storeContextOverrideModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: var(--hospital-primary, #011b33); color: white;">
                <h5 class="modal-title" id="storeContextOverrideModalLabel">
                    <i class="fas fa-store-alt me-2"></i> Change Active Store Context
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none py-2 px-3 small mb-3" id="ctx-override-error"></div>

                <p class="text-muted small mb-3">
                    Select the store you wish to operate from for this workbench session. 
                    Your selection will be saved for your current session until reset or changed.
                </p>

                <div class="mb-3">
                    <label for="ctx-store-select" class="form-label fw-bold">Available Stores <span class="text-danger">*</span></label>
                    <select class="form-select form-control" id="ctx-store-select">
                        <option value="">-- Select Store --</option>
                        @if(isset($stores) && count($stores) > 0)
                            @foreach($stores as $st)
                                <option value="{{ $st->id }}" {{ (isset($resolvedStore) && $resolvedStore && $resolvedStore->id === $st->id) ? 'selected' : '' }}>
                                    {{ $st->store_name }} ({{ method_exists($st, 'distributionRoleLabel') ? $st->distributionRoleLabel() : $st->distribution_role }})
                                </option>
                            @endforeach
                        @endif
                    </select>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="ctx-override-clear-btn" onclick="clearStoreContextOverride()">
                    <i class="fas fa-undo me-1"></i> Reset to Default
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="ctx-override-btn" onclick="confirmStoreContextOverride()">
                        <i class="fas fa-check me-1"></i> Apply
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
