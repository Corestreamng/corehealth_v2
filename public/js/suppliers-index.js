$(function() {
    if ($('#suppliers-table').length > 0) {
        var table = $('#suppliers-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: wbRoute('suppliers.list', '/suppliers/list'),
                dataSrc: function(json) {
                    if (json.recordsTotal !== undefined) {
                        $('#total-suppliers').text(json.recordsTotal);
                    }
                    return json.data;
                }
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'company_name', name: 'company_name' },
                { data: 'contact_person', name: 'contact_person', defaultContent: '-' },
                { data: 'phone', name: 'phone' },
                { data: 'batches_count', name: 'batches_count' },
                { data: 'po_count', name: 'po_count' },
                { data: 'outstanding', name: 'outstanding' },
                { data: 'last_activity', name: 'last_activity' },
                { data: 'status_badge', name: 'status' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ],
            dom: 'Bfrtip',
            buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print'],
            pageLength: 25
        });
    }
});

function deleteSupplier(id) {
    if (confirm('Are you sure you want to delete this supplier?')) {
        $.ajax({
            url: wbUrl('/suppliers/' + id),
            type: 'DELETE',
            data: { _token: (window.WORKBENCH_CONFIG?.csrf || $('meta[name="csrf-token"]').attr('content') || '') },
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    if ($.fn.DataTable.isDataTable('#suppliers-table')) {
                        $('#suppliers-table').DataTable().ajax.reload();
                    }
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                toastr.error(xhr.responseJSON?.message || 'Failed to delete supplier');
            }
        });
    }
}
