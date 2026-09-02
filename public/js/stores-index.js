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
    if ($('#stores').length > 0) {
        $('#stores').DataTable({
            dom: 'Bfrtip',
            iDisplayLength: 50,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            buttons: ['pageLength', 'copy', 'excel', 'pdf', 'print', 'colvis'],
            processing: true,
            serverSide: true,
            ajax: {
                url: wbRoute('store-list', '/stores/store-list'),
                type: 'GET'
            },
            columns: [
                { data: "DT_RowIndex", name: "DT_RowIndex" },
                { data: "store_name", name: "store_name" },
                { data: "location", name: "location" },
                { data: "status", name: "status" },
                { data: "edit", name: "edit" },
                { data: "view", name: "view" }
            ],
            paging: true
        });
    }
});
