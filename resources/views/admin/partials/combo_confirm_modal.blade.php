{{-- ============================================================
     Combo Confirmation Modal — shared across all workbenches
     and new_encounter. Include once per page.
     ============================================================ --}}
<div class="modal fade" id="comboConfirmModal" tabindex="-1" aria-labelledby="comboConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="comboConfirmModalLabel">
                    <i class="fa fa-cubes me-2"></i> Apply Service Bundle
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                {{-- Banner --}}
                <div class="alert alert-info mb-3" role="alert">
                    <i class="fa fa-info-circle me-1"></i>
                    Applying this bundle will order all items listed below for the patient in a single transaction.
                    <strong>One charge covers the entire bundle</strong> — individual items are not billed separately.
                </div>

                {{-- Combo name + total price --}}
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <span class="badge bg-primary me-1"><i class="fa fa-cubes me-1"></i>BUNDLE</span>
                        <strong id="comboConfirmName" class="fs-5"></strong>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Bundle Price</div>
                        <div class="fw-bold text-primary fs-5" id="comboConfirmPrice">—</div>
                        <div id="comboConfirmHmo" class="small text-muted"></div>
                    </div>
                </div>

                {{-- Bundle items table --}}
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:32px">#</th>
                                <th>Item</th>
                                <th style="width:90px">Type</th>
                                <th style="width:55px">Qty</th>
                                <th>Dosage / Note</th>
                            </tr>
                        </thead>
                        <tbody id="comboConfirmItems">
                            <tr>
                                <td colspan="5" class="text-center text-muted">Loading…</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Warning when bundle_items empty --}}
                <div id="comboConfirmNoItems" class="d-none mt-2 text-warning small">
                    <i class="fa fa-exclamation-triangle me-1"></i>
                    Bundle item details are not available for preview. Proceeding will still apply the bundle correctly.
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fa fa-times me-1"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="comboConfirmApplyBtn">
                    <span id="comboConfirmApplySpinner" class="d-none me-1">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                    </span>
                    <i class="fa fa-check me-1" id="comboConfirmApplyIcon"></i>
                    Apply Bundle
                </button>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * ComboConfirmModal — singleton utility for showing the bundle-confirmation modal.
 *
 * Usage:
 *   ComboConfirmModal.show({
 *       name        : 'Antenatal Package',
 *       bundleItems : item.bundle_items || [],   // array from search results
 *       price       : 5000,                      // base price (number)
 *       payable     : 4500,                      // HMO payable (optional)
 *       claims      : 500,                       // HMO claims (optional)
 *       mode        : 'hmo',                     // coverage mode (optional)
 *       onConfirm   : function() { /* do POST *\/ }
 *   });
 */
window.comboDataMap = window.comboDataMap || {};

window.ComboConfirmModal = (function () {
    var _onConfirm = null;

    function show(options) {
        options = options || {};
        var name        = options.name        || 'Bundle';
        var bundleItems = options.bundleItems  || [];
        var price       = parseFloat(options.price   || 0);
        var payable     = parseFloat(options.payable != null ? options.payable : price);
        var claims      = parseFloat(options.claims  || 0);
        var mode        = options.mode        || null;

        _onConfirm = options.onConfirm || null;

        // Populate name
        document.getElementById('comboConfirmName').textContent = name;

        // Populate price
        document.getElementById('comboConfirmPrice').textContent =
            'NGN ' + payable.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});

        // HMO line
        var hmoEl = document.getElementById('comboConfirmHmo');
        if (mode && mode !== 'cash' && mode !== 'null') {
            hmoEl.innerHTML =
                '<span class="badge bg-info">' + mode.toUpperCase() + '</span> ' +
                '<span class="text-success">Claim: NGN ' + claims.toLocaleString() + '</span>';
            hmoEl.classList.remove('d-none');
        } else {
            hmoEl.innerHTML = '';
            hmoEl.classList.add('d-none');
        }

        // Populate items
        var $tbody = $('#comboConfirmItems');
        $tbody.empty();

        var noItemsEl = document.getElementById('comboConfirmNoItems');

        if (bundleItems && bundleItems.length > 0) {
            noItemsEl.classList.add('d-none');
            bundleItems.forEach(function (item, idx) {
                var typeBadge = item.type === 'service'
                    ? '<span class="badge bg-info bg-opacity-75">Service</span>'
                    : '<span class="badge bg-success bg-opacity-75">Product</span>';
                var dose = (item.dose || item.note || '').trim();
                var qty  = item.qty  || 1;

                $tbody.append(
                    '<tr>' +
                        '<td class="text-muted">' + (idx + 1) + '</td>' +
                        '<td><strong>' + _esc(item.name || 'Unknown') + '</strong></td>' +
                        '<td>' + typeBadge + '</td>' +
                        '<td class="text-center">' + qty + '</td>' +
                        '<td class="text-muted small">' + _esc(dose) + '</td>' +
                    '</tr>'
                );
            });
        } else {
            $tbody.append('<tr><td colspan="5" class="text-center text-muted fst-italic">Preview not available</td></tr>');
            noItemsEl.classList.remove('d-none');
        }

        // Reset button state
        var applyBtn  = document.getElementById('comboConfirmApplyBtn');
        var applyIcon = document.getElementById('comboConfirmApplyIcon');
        var spinner   = document.getElementById('comboConfirmApplySpinner');
        applyBtn.disabled = false;
        applyIcon.classList.remove('d-none');
        spinner.classList.add('d-none');

        $('#comboConfirmModal').modal({ backdrop: 'static', keyboard: false });
        $('#comboConfirmModal').modal('show');
    }

    function _esc(str) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(str || ''));
        return d.innerHTML;
    }

    // Wire up confirm button (once, on first load)
    document.addEventListener('DOMContentLoaded', function() {
        $(document).on('click', '#comboConfirmApplyBtn', function () {
            // Show spinner, disable button while running
            var applyBtn  = document.getElementById('comboConfirmApplyBtn');
            var applyIcon = document.getElementById('comboConfirmApplyIcon');
            var spinner   = document.getElementById('comboConfirmApplySpinner');
            applyBtn.disabled = true;
            applyIcon.classList.add('d-none');
            spinner.classList.remove('d-none');

            // Hide modal
            $('#comboConfirmModal').modal('hide');

            // Execute callback
            if (typeof _onConfirm === 'function') {
                _onConfirm();
                _onConfirm = null;
            }
        });
    });

    return { show: show };
})();
</script>
