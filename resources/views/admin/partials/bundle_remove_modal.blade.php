<div class="modal fade" id="bundleRemoveModal" tabindex="-1" role="dialog" aria-labelledby="bundleRemoveModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="bundleRemoveModalLabel">
                    <i class="fa fa-trash"></i> Remove Combo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="bundleRemoveContent">
                    <div class="spinner-border spinner-border-sm" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="bundleRemoveConfirmBtn">
                    <span id="bundleRemoveSpinner" style="display: none;">
                        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    </span>
                    <span id="bundleRemoveIcon"><i class="fa fa-trash"></i></span>
                    <span id="bundleRemoveText">Remove Combo</span>
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
window.BundleRemoveModal = (function() {
    let currentOptions = {};
    
    function show(options) {
        // options = { bundleId, bundleName, items, onConfirm: callback } — combo = our service combo system (not procedure bundling)
        currentOptions = options;
        const items = options.items || [];
        let html = `
            <div class="alert alert-warning alert-sm mb-3">
                <i class="fa fa-exclamation-triangle"></i>
                <strong>Remove Combo?</strong> This will remove all combo items from the patient's encounter.
            </div>
            
            <div class="card border-0 bg-light mb-3">
                <div class="card-body">
                    <h6 class="text-danger fw-bold mb-2">${options.bundleName || 'Combo'}</h6>
                    <p class="mb-2 text-muted"><small>The following items will be removed:</small></p>
                    
                    ${items.length > 0 ? `
                        <div class="list-group list-group-flush">
                            ${items.map((item, idx) => `
                                <div class="list-group-item ps-0 border-0 py-1">
                                    <div class="d-flex align-items-start">
                                        <span class="badge bg-danger me-2 mt-1">${idx + 1}</span>
                                        <div>
                                            <strong>${item.name || 'Item'}</strong>
                                            ${item.code ? `<br><small class="text-muted">${item.code}</small>` : ''}
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    ` : '<p class="text-muted mb-0">No items to remove</p>'}
                    
                    <hr class="my-2">
                    <small class="text-muted d-block">
                        <i class="fa fa-info-circle"></i> This action cannot be undone. The items will be marked as removed.
                    </small>
                </div>
            </div>
        `;
        
        document.getElementById('bundleRemoveContent').innerHTML = html;
        
        // Setup confirm button
        document.getElementById('bundleRemoveConfirmBtn').onclick = confirmRemoval;
        
        $('#bundleRemoveModal').modal({ keyboard: false, backdrop: 'static' });
        $('#bundleRemoveModal').modal('show');
    }
    
    function confirmRemoval() {
        if (!currentOptions.onConfirm) return;
        
        const btn = document.getElementById('bundleRemoveConfirmBtn');
        btn.disabled = true;
        document.getElementById('bundleRemoveSpinner').style.display = 'inline';
        document.getElementById('bundleRemoveIcon').style.display = 'none';
        document.getElementById('bundleRemoveText').innerText = 'Removing...';
        
        // Call the callback which handles the AJAX
        currentOptions.onConfirm(function(error) {
            btn.disabled = false;
            document.getElementById('bundleRemoveSpinner').style.display = 'none';
            document.getElementById('bundleRemoveIcon').style.display = 'inline';
            document.getElementById('bundleRemoveText').innerText = 'Remove Combo';
            
            if (!error) {
                // Close modal on success
                $('#bundleRemoveModal').modal('hide');
            }
        });
    }
    
    return { show };
})();
</script>
@endpush
