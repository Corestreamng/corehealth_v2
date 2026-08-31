$(function() {
    if ($('#requisition-table').length > 0) {
        var table = $('#requisition-table').DataTable({
            dom: 'Bfrtip',
            iDisplayLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print'],
            processing: true,
            serverSide: true,
            ajax: {
                url: wbRoute('inventory.requisitions.index', '/inventory/requisitions'),
                type: "GET",
                data: function(d) {
                    d.status = $('#status-filter').val();
                    d.from_store_id = $('#from-store-filter').val();
                    d.to_store_id = $('#to-store-filter').val();
                    d.queue = window.WORKBENCH_CONFIG?.queue || '';
                }
            },
            columns: [
                { data: "requisition_number", name: "requisition_number" },
                { data: "request_date", name: "created_at" },
                { data: "from_store", name: "fromStore.store_name" },
                { data: "to_store", name: "toStore.store_name" },
                { data: "items_count", name: "items_count", orderable: false },
                { data: "status", name: "status" },
                { data: "requested_by", name: "requester.name" },
                { data: "actions", name: "actions", orderable: false, searchable: false }
            ],
            order: [[1, 'desc']]
        });

        // Filters
        $('#status-filter, #from-store-filter, #to-store-filter').on('change', function() {
            table.ajax.reload();
        });
    }
});

function approveRequisition(id) {
    if (confirm('Approve this requisition?')) {
        $.post(wbUrl('/inventory/requisitions/' + id + '/approve'), {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '')
        })
            .done(function(response) {
                toastr.success(response.message || 'Requisition approved');
                if ($.fn.DataTable.isDataTable('#requisition-table')) {
                    $('#requisition-table').DataTable().ajax.reload();
                }
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to approve');
            });
    }
}

function rejectRequisition(id) {
    var reason = prompt('Please enter rejection reason:');
    if (reason) {
        $.post(wbUrl('/inventory/requisitions/' + id + '/reject'), {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || ''),
            rejection_reason: reason
        })
            .done(function(response) {
                toastr.success(response.message || 'Requisition rejected');
                if ($.fn.DataTable.isDataTable('#requisition-table')) {
                    $('#requisition-table').DataTable().ajax.reload();
                }
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to reject');
            });
    }
}

function cancelRequisition(id) {
    if (confirm('Cancel this requisition?')) {
        $.post(wbUrl('/inventory/requisitions/' + id + '/cancel'), {
            _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '')
        })
            .done(function(response) {
                toastr.success(response.message || 'Requisition cancelled');
                if ($.fn.DataTable.isDataTable('#requisition-table')) {
                    $('#requisition-table').DataTable().ajax.reload();
                }
            })
            .fail(function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to cancel');
            });
    }
}
