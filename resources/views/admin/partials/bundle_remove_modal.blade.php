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
window.BundleRemoveModal = window.BundleRemoveModal || (function() {
    let currentOptions = {};
    
    function show(options) {
        // options = { bundleId, bundleName, items, removeItemUrl, onConfirm: callback }
        currentOptions = options;
        const items = options.items || [];
        let html = `
            <div class="alert alert-warning alert-sm mb-3">
                <i class="fa fa-exclamation-triangle"></i>
                <strong>Remove Combo?</strong> You can remove individual items below, or remove the entire combo.
            </div>
            
            <div class="card-modern border-0 bg-light mb-3">
                <div class="card-body">
                    <h6 class="text-danger fw-bold mb-2">${options.bundleName || 'Combo'}</h6>
                    <p class="mb-2 text-muted"><small>Combo Items:</small></p>
                    
                    ${items.length > 0 ? `
                        <div class="list-group list-group-flush">
                            ${items.map((item, idx) => `
                                <div class="list-group-item ps-0 border-0 py-1">
                                    <div class="d-flex align-items-center justify-content-between">
                                        <div class="d-flex align-items-start">
                                            <span class="badge bg-danger me-2 mt-1">${idx + 1}</span>
                                            <div>
                                                <strong>${item.name || 'Item'}</strong>
                                                ${item.code ? `<br><small class="text-muted">${item.code}</small>` : ''}
                                            </div>
                                        </div>
                                        ${(item.id || item.child_id) ? `
                                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="window.BundleRemoveModal.removeItem(${item.id || item.child_id}, '${(item.name || 'Item').replace(/'/g, "\\'")}')" title="Remove only this item">
                                                <i class="fa fa-times"></i> Remove Item
                                            </button>
                                        ` : ''}
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    ` : '<p class="text-muted mb-0">No items in combo</p>'}
                    
                    <hr class="my-2">
                    <small class="text-muted d-block">
                        <i class="fa fa-info-circle"></i> Removing an item will cancel it while keeping the rest of the combo active.
                    </small>
                </div>
            </div>
        `;
        
        var contentEl = document.getElementById('bundleRemoveContent');
        if (contentEl) {
            contentEl.innerHTML = html;
        }
        
        // Setup confirm button
        var confirmBtn = document.getElementById('bundleRemoveConfirmBtn');
        if (confirmBtn) {
            confirmBtn.onclick = confirmRemoval;
        }
        
        var $modal = $('#bundleRemoveModal');
        if (typeof $ !== 'undefined' && $.fn && $.fn.modal) {
            $modal.modal({ keyboard: false, backdrop: 'static' });
            $modal.modal('show');
        } else if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function') {
            var inst = (typeof bootstrap.Modal.getInstance === 'function' ? bootstrap.Modal.getInstance(document.getElementById('bundleRemoveModal')) : null)
                || (typeof bootstrap.Modal.getOrCreateInstance === 'function' ? bootstrap.Modal.getOrCreateInstance(document.getElementById('bundleRemoveModal'), { keyboard: false, backdrop: 'static' }) : null)
                || new bootstrap.Modal(document.getElementById('bundleRemoveModal'), { keyboard: false, backdrop: 'static' });
            if (inst && typeof inst.show === 'function') inst.show();
        }
    }
    
    function removeItem(childId, itemName) {
        if (!confirm('Are you sure you want to remove "' + itemName + '" from this combo?')) {
            return;
        }

        var removeItemUrl = currentOptions.removeItemUrl || '/service-combo/remove-item';
        var csrfToken = $('meta[name="csrf-token"]').attr('content') ||
            (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.csrf) ||
            '';

        $.ajax({
            url: removeItemUrl,
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({ child_request_id: childId }),
            success: function(r) {
                if (r.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(r.message || 'Item removed from combo');
                    }
                    if (currentOptions.items) {
                        currentOptions.items = currentOptions.items.filter(function(it) {
                            return (it.id != childId && it.child_id != childId);
                        });
                        if (currentOptions.items.length === 0) {
                            var $modal = $('#bundleRemoveModal');
                            if (typeof $ !== 'undefined' && $.fn && $.fn.modal) {
                                $modal.modal('hide');
                            }
                        } else {
                            show(currentOptions);
                        }
                    }
                    [
                        '#investigation_history_list',
                        '#imaging_history_list',
                        '#presc_history_list',
                        '#presc_history_table',
                        '#cr_presc_history_list',
                        '#cr_lab_history_list',
                        '#cr_imaging_history_list',
                        '#mco_presc_history_list',
                        '#mco_lab_history_list',
                        '#mco_imaging_history_list',
                        '#procedure_history_list',
                        '#cr_proc_history_list'
                    ].forEach(function(selector) {
                        if ($.fn.DataTable && $.fn.DataTable.isDataTable(selector)) {
                            $(selector).DataTable().ajax.reload(null, false);
                        }
                    });
                    if (typeof initPrescHistory === 'function') { initPrescHistory(); }
                    if (typeof initLabHistory === 'function') { initLabHistory(); }
                    if (typeof initImagingHistory === 'function') { initImagingHistory(); }
                    if (typeof loadLabServices === 'function') { loadLabServices(); }
                    if (typeof loadImagingServices === 'function') { loadImagingServices(); }
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(r.message || 'Failed to remove item');
                    }
                }
            },
            error: function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error removing item';
                if (typeof toastr !== 'undefined') {
                    toastr.error(msg);
                }
            }
        });
    }

    function confirmRemoval() {
        if (!currentOptions.onConfirm) return;
        
        const btn = document.getElementById('bundleRemoveConfirmBtn');
        if (btn) btn.disabled = true;
        var spinner = document.getElementById('bundleRemoveSpinner');
        if (spinner) spinner.style.display = 'inline';
        var icon = document.getElementById('bundleRemoveIcon');
        if (icon) icon.style.display = 'none';
        var text = document.getElementById('bundleRemoveText');
        if (text) text.innerText = 'Removing...';
        
        // Call the callback which handles the AJAX
        currentOptions.onConfirm(function(error) {
            if (btn) btn.disabled = false;
            if (spinner) spinner.style.display = 'none';
            if (icon) icon.style.display = 'inline';
            if (text) text.innerText = 'Remove Combo';
            
            if (!error) {
                // Close modal on success
                var $modal = $('#bundleRemoveModal');
                if (typeof $ !== 'undefined' && $.fn && $.fn.modal) {
                    $modal.modal('hide');
                } else if (typeof bootstrap !== 'undefined' && typeof bootstrap.Modal === 'function') {
                    var inst = (typeof bootstrap.Modal.getInstance === 'function' ? bootstrap.Modal.getInstance(document.getElementById('bundleRemoveModal')) : null)
                        || (typeof bootstrap.Modal.getOrCreateInstance === 'function' ? bootstrap.Modal.getOrCreateInstance(document.getElementById('bundleRemoveModal')) : null);
                    if (inst && typeof inst.hide === 'function') inst.hide();
                }
            }
        });
    }
    
    return { show, removeItem };
})();
</script>
@endpush
