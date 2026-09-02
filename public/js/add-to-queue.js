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

$(function() {
    if ($('#patient_table_id').length > 0) {
        $('#patient_table_id').DataTable({
            initComplete: function(settings, json) {
                $('div.loading').remove();
            },
            dom: 'Bfrtip',
            iDisplayLength: 50,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            buttons: ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: wbRoute('listReturningPatients', '/reception/listReturningPatients'),
                type: 'GET',
                data: function(data) {
                    data.q = $('#q').val() || 'a';
                }
            },
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex' },
                { data: 'file_no', name: 'file_no' },
                { data: 'user_id', name: 'user_id' },
                { data: 'hmo', name: 'hmo' },
                { data: 'hmo_no', name: 'hmo_no' },
                { data: 'acc_bal', name: 'acc_bal' },
                { data: 'phone', name: 'phone' },
                { data: 'process', name: 'process' }
            ],
            paging: true
        });
    }

    $("#patients_search_form").on('submit', function(e) {
        e.preventDefault();
        $('#showMe').show();
        if ($.fn.DataTable.isDataTable('#patient_table_id')) {
            $('#patient_table_id').DataTable().draw(true);
        }
    });
});
