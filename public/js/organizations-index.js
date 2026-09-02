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
        if (window.WORKBENCH_CONFIG && window.WORKBENCH_CONFIG.routes) {
            if (window.WORKBENCH_CONFIG.routes[name]) return window.WORKBENCH_CONFIG.routes[name];
            var dotKey = name.replace(/_/g, '.');
            if (window.WORKBENCH_CONFIG.routes[dotKey]) return window.WORKBENCH_CONFIG.routes[dotKey];
            var underscoreKey = name.replace(/\./g, '_');
            if (window.WORKBENCH_CONFIG.routes[underscoreKey]) return window.WORKBENCH_CONFIG.routes[underscoreKey];
        }
        return window.wbUrl(fallbackPath || '');
    };
}

var table = null;

$(document).ready(function() {
    if ($('#organizations-table').length > 0) {
        table = $('#organizations-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: wbRoute('organizations.data', '/admin/organizations/data'),
            columns: [
                { data: 'id', name: 'id' },
                { data: 'name', name: 'name' },
                { data: 'email', name: 'email' },
                { data: 'phone', name: 'phone' },
                { data: 'credit_limit_formatted', name: 'credit_limit' },
                { data: 'balance_formatted', name: 'balance' },
                { data: 'status_badge', name: 'status', orderable: false, searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ]
        });
    }

    $('#organizationForm').on('submit', function(e) {
        e.preventDefault();
        let id = $('#org_id').val();
        let url = id ? wbUrl('/admin/organizations/' + id) : wbRoute('organizations.store', '/admin/organizations');
        let type = id ? 'PUT' : 'POST';

        $('#save-btn').prop('disabled', true).text('Saving...');

        $.ajax({
            url: url,
            type: type,
            data: $(this).serialize(),
            success: function(response) {
                $('#save-btn').prop('disabled', false).text('Save Organization');
                if (response.success) {
                    $('#organizationModal').modal('hide');
                    toastr.success(response.message);
                    if (table) table.ajax.reload();
                } else {
                    toastr.error(response.message);
                }
            },
            error: function(xhr) {
                $('#save-btn').prop('disabled', false).text('Save Organization');
                let errorMessage = 'An error occurred';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    errorMessage = Object.values(xhr.responseJSON.errors)[0][0];
                } else if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                toastr.error(errorMessage);
            }
        });
    });

    $('body').on('click', '.edit-org', function() {
        if (!table) return;
        let rowData = table.row($(this).parents('tr')).data();
        if (!rowData) return;

        $('#org_id').val(rowData.id);
        $('#name').val(rowData.name);
        $('#email').val(rowData.email);
        $('#phone').val(rowData.phone);
        $('#address').val(rowData.address);
        $('#credit_limit').val(rowData.credit_limit);
        $('#status').val(rowData.status);

        $('#organizationModalLabel span').text('Edit Organization');
        $('#organizationModal').modal('show');
    });
});

function resetForm() {
    if ($('#organizationForm').length > 0) {
        $('#organizationForm')[0].reset();
    }
    $('#org_id').val('');
    $('#organizationModalLabel span').text('Add Organization');
}
