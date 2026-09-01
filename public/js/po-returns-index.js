if (typeof window.wbUrl !== 'function') {
    window.wbUrl = function(path) {
        var base = (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.baseUrl) ? window.WORKBENCH_CONFIG.baseUrl : '';
        base = base.replace(/\/$/, '');
        var cleanPath = (path || '').replace(/^\//, '');
        return base ? (base + '/' + cleanPath) : ('/' + cleanPath);
    };
}
if (typeof window.wbRoute !== 'function') {
    window.wbRoute = function(name, fallbackPath) {
        if (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.routes && window.WORKBENCH_CONFIG.routes[name]) {
            return window.WORKBENCH_CONFIG.routes[name];
        }
        return window.wbUrl(fallbackPath || '');
    };
}

(function($) {
    'use strict';

    $(function() {
        var $table = $('#po-returns-table');
        if (!$table.length) return;

        var dt = $table.DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: window.wbRoute('inventory.po-returns.datatables', 'inventory/purchase-order-returns/datatables'),
                data: function(d) {
                    d.status = $('#filter-status').val();
                    d.store_id = $('#filter-store').val();
                }
            },
            columns: [
                { data: 'return_info', name: 'return_number' },
                { data: 'order_info', name: 'purchaseOrder.po_number' },
                { data: 'item_details', name: 'product.product_name' },
                { data: 'qty_packaging', name: 'qty_returned' },
                { data: 'value_reason_status', name: 'total_value' },
                { data: 'recorder_actions', name: 'creator.surname', orderable: false, searchable: false }
            ],
            order: [[0, 'desc']],
            pageLength: 25,
            responsive: true,
            language: {
                search: '_INPUT_',
                searchPlaceholder: 'Search PO returns...'
            }
        });

        // Filter event listeners
        $('#filter-status, #filter-store').on('change', function() {
            dt.ajax.reload();
        });

        $('#btn-reset-filters').on('click', function() {
            $('#filter-status').val('');
            $('#filter-store').val('');
            dt.ajax.reload();
        });

        var currentApproveId = null;

        // Financial Review & Approve action handler
        $(document).on('click', '.btn-approve-por', function() {
            currentApproveId = $(this).data('id');
            $('#por-modal-loading').show();
            $('#por-modal-content').hide();
            $('#por-review-modal').modal('show');

            $.get(window.wbUrl('inventory/purchase-order-returns/' + currentApproveId))
                .done(function(res) {
                    if (!res.success) {
                        toastr.error('Failed to load return details');
                        $('#por-review-modal').modal('hide');
                        return;
                    }
                    var ret = res.return;
                    var fin = ret.financial_context || {};

                    $('#por-modal-return-num').text(ret.return_number || ('POR-' + ret.id));
                    $('#por-modal-product-name').text(ret.product_name || 'N/A');
                    $('#por-modal-store-name').text(ret.store_name || 'N/A');
                    $('#por-modal-batch-num').text(ret.batch_number || 'N/A');
                    $('#por-modal-qty').text((ret.qty_returned || 0) + ' Units');

                    $('#por-modal-po-num').text(ret.po_number || 'N/A');
                    $('#por-modal-supplier').text(ret.supplier || 'N/A');
                    $('#por-modal-unit-cost').text('₦' + parseFloat(ret.unit_cost || 0).toLocaleString('en-US', {minimumFractionDigits: 2}));
                    $('#por-modal-total-val').text('₦' + parseFloat(ret.total_value || 0).toLocaleString('en-US', {minimumFractionDigits: 2}));

                    // Payment status badge & impact summary
                    var payStatus = (fin.payment_status || 'unpaid').toUpperCase();
                    var badgeClass = fin.is_fully_paid ? 'badge-success' : (payStatus === 'PARTIAL' ? 'badge-warning' : 'badge-secondary');
                    $('#por-modal-payment-badge').attr('class', 'badge px-3 py-1 font-weight-bold ' + badgeClass).text(payStatus + ' PO');
                    $('#por-modal-impact-summary').text(fin.impact_summary || '');

                    // Toggle settlement selector for fully paid POs
                    if (fin.is_fully_paid) {
                        $('#por-settlement-selector-box').show();
                    } else {
                        $('#por-settlement-selector-box').hide();
                    }

                    // Render GL Journal Entry lines preview
                    var glHtml = '';
                    var drName = fin.dr_account_name || 'Accounts Payable - Suppliers (2110)';
                    var crName = fin.cr_account_name || 'Inventory - Medical Supplies (1310)';
                    var totValStr = '₦' + parseFloat(ret.total_value || 0).toLocaleString('en-US', {minimumFractionDigits: 2});

                    glHtml += '<tr>' +
                        '<td><strong class="text-dark">' + drName + '</strong></td>' +
                        '<td class="text-right text-success font-weight-bold">' + totValStr + '</td>' +
                        '<td class="text-right text-muted">₦0.00</td>' +
                        '</tr>';

                    glHtml += '<tr>' +
                        '<td><strong class="text-dark">' + crName + '</strong></td>' +
                        '<td class="text-right text-muted">₦0.00</td>' +
                        '<td class="text-right text-danger font-weight-bold">' + totValStr + '</td>' +
                        '</tr>';

                    $('#por-modal-gl-rows').html(glHtml);

                    $('#por-modal-loading').hide();
                    $('#por-modal-content').show();
                })
                .fail(function() {
                    toastr.error('Error fetching return financial details');
                    $('#por-review-modal').modal('hide');
                });
        });

        // Confirm Approve button click inside modal
        $('#por-confirm-approve-btn').on('click', function() {
            if (!currentApproveId) return;

            var notes = $('#por-approval-notes').val();
            var settlement = $('#por-settlement-option').val();

            var $btn = $(this);
            $btn.prop('disabled', true).html('<i class="spinner-border spinner-border-sm mr-1"></i> Processing...');

            $.post(window.wbUrl('inventory/purchase-order-returns/' + currentApproveId + '/approve'), {
                _token: window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.csrf : '',
                approval_notes: notes,
                settlement_option: settlement
            })
            .done(function(r) {
                toastr.success(r.message || 'PO Return approved successfully');
                $('#por-review-modal').modal('hide');
                dt.ajax.reload(null, false);
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to approve return');
            })
            .always(function() {
                $btn.prop('disabled', false).html('<i class="mdi mdi-check mr-1"></i> Approve Return & Post GL');
            });
        });

        // Reject action handler
        $(document).on('click', '.btn-reject-por', function() {
            var id = $(this).data('id');
            var reason = prompt('Please enter the reason for rejection (at least 5 characters):');
            if (reason) {
                reason = reason.trim();
                if (reason.length < 5) {
                    toastr.warning('Rejection reason must be at least 5 characters long.');
                    return;
                }
                $.post(window.wbUrl('inventory/purchase-order-returns/' + id + '/reject'), {
                    _token: window.WORKBENCH_CONFIG ? window.WORKBENCH_CONFIG.csrf : '',
                    rejection_reason: reason,
                    reason: reason
                })
                .done(function(r) {
                    toastr.success(r.message || 'PO Return rejected');
                    dt.ajax.reload(null, false);
                })
                .fail(function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to reject return');
                });
            }
        });
    });

})(jQuery);
