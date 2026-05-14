<div class="modal fade" id="bundleViewModal" tabindex="-1" role="dialog" aria-labelledby="bundleViewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="bundleViewModalLabel">
                    <i class="fa fa-cube text-primary"></i> Combo Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="bundleViewContent">
                    <div class="spinner-border spinner-border-sm" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
window.BundleViewModal = (function() {
    function show(bundleData) {
        // comboData = { name, service_name, service_code, bundle_items, base_price, payable_amount, claims_amount, coverage_mode }
        const rawItems = bundleData.items || bundleData.bundle_items || [];
        const items = rawItems.map(function(item) {
            return {
                name: item.name || item.service_name || item.product_name || 'Item',
                code: item.code || item.service_code || item.product_code || null,
                qty: item.qty || 1,
                price: item.price || item.payable_amount || item.amount || 0
            };
        });
        
        let html = `
            <div class="card border-0">
                <div class="card-body">
                    <h6 class="card-title text-primary fw-bold mb-1">
                        ${bundleData.name || bundleData.service_name || 'Combo'}
                    </h6>
                    <small class="text-muted d-block mb-3">
                        Code: <code>${bundleData.service_code || 'N/A'}</code>
                    </small>
                    
                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <div class="bg-light p-2 rounded">
                                <small class="text-muted">Patient Payable</small>
                                <div class="text-success fw-bold">₦${parseFloat(bundleData.payable_amount || 0).toLocaleString('en-NG', {minimumFractionDigits: 2})}</div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="bg-light p-2 rounded">
                                <small class="text-muted">HMO Claims</small>
                                <div class="text-info fw-bold">₦${parseFloat(bundleData.claims_amount || 0).toLocaleString('en-NG', {minimumFractionDigits: 2})}</div>
                            </div>
                        </div>
                    </div>
                    
                    ${bundleData.coverage_mode ? `<div class="alert alert-info alert-sm py-2 mb-3"><small><strong>Coverage:</strong> ${bundleData.coverage_mode.toUpperCase()}</small></div>` : ''}
                    
                    <hr>
                    <h6 class="text-secondary mb-2">Combo Items (${items.length})</h6>
                    
                    ${items.length > 0 ? `
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-center" style="width: 60px">Qty</th>
                                        <th class="text-end" style="width: 100px">Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${items.map(item => `
                                        <tr>
                                            <td>
                                                <strong>${item.name || 'Item'}</strong>
                                                ${item.code ? `<br><small class="text-muted">Code: ${item.code}</small>` : ''}
                                            </td>
                                            <td class="text-center">${item.qty || 1}</td>
                                            <td class="text-end">₦${parseFloat(item.price || 0).toLocaleString('en-NG', {minimumFractionDigits: 2})}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    ` : '<p class="text-muted mb-0">No items in combo</p>'}
                </div>
            </div>
        `;
        
        document.getElementById('bundleViewContent').innerHTML = html;
        $('#bundleViewModal').modal({ keyboard: true, backdrop: 'static' });
        $('#bundleViewModal').modal('show');
    }
    
    return { show };
})();
</script>
