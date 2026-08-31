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
