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
    var dtConfig = function(ajaxUrl) {
        return {
            dom: 'Bfrtip',
            iDisplayLength: 50,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            buttons: ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
            processing: true,
            serverSide: true,
            ajax: {
                url: ajaxUrl,
                type: "GET"
            },
            paging: true
        };
    };

    if ($('#procedure_queue_list').length > 0) {
        var cfg1 = dtConfig(wbRoute('encounterList', '/encounterList'));
        cfg1.columns = [
            { data: "DT_RowIndex", name: "DT_RowIndex" },
            { data: "fullname", name: "fullname" },
            { data: "file_no", name: "file_no" },
            { data: "hmo_id", name: "hmo_id" },
            { data: "clinic_id", name: "clinic_id" },
            { data: "staff_id", name: "staff_id" },
            { data: "created_at", name: "created_at" },
            { data: "view", name: "view" }
        ];
        $('#procedure_queue_list').DataTable(cfg1);
    }

    if ($('#prev_procedure_list').length > 0) {
        var cfg2 = dtConfig(wbUrl('/PrevEncounterList'));
        cfg2.columns = [
            { data: "DT_RowIndex", name: "DT_RowIndex" },
            { data: "fullname", name: "fullname" },
            { data: "file_no", name: "file_no" },
            { data: "hmo_id", name: "hmo_id" },
            { data: "clinic_id", name: "clinic_id" },
            { data: "staff_id", name: "staff_id" },
            { data: "created_at", name: "created_at" },
            { data: "view", name: "view" }
        ];
        $('#prev_procedure_list').DataTable(cfg2);
    }

    if ($('#scheduled_procedure_list').length > 0) {
        var cfg3 = dtConfig(wbUrl('/patientsList'));
        cfg3.columns = [
            { data: "DT_RowIndex", name: "DT_RowIndex" },
            { data: "fullname", name: "fullname" },
            { data: "file_no", name: "file_no" },
            { data: "hmo_id", name: "hmo_id" },
            { data: "created_at", name: "created_at" },
            { data: "view", name: "view" }
        ];
        $('#scheduled_procedure_list').DataTable(cfg3);
    }

    if ($('#my_admissions_list').length > 0) {
        var cfg4 = dtConfig(wbRoute('my-admission-requests-list', '/my-admission-requests-list'));
        cfg4.columns = [
            { data: "DT_RowIndex", name: "DT_RowIndex" },
            { data: "patient", name: "patient" },
            { data: "file_no", name: "file_no" },
            { data: "hmo", name: "hmo" },
            { data: "hmo_no", name: "hmo_no" },
            { data: "doctor_id", name: "doctor_id" },
            { data: "billed_by", name: "billed_by" },
            { data: "bed_id", name: "bed_id" },
            { data: "show", name: "show" }
        ];
        $('#my_admissions_list').DataTable(cfg4);
    }

    if ($('#other_admissions_list').length > 0) {
        var cfg5 = dtConfig(wbRoute('admission-requests-list', '/admission-requests-list'));
        cfg5.columns = [
            { data: "DT_RowIndex", name: "DT_RowIndex" },
            { data: "patient", name: "patient" },
            { data: "file_no", name: "file_no" },
            { data: "hmo", name: "hmo" },
            { data: "hmo_no", name: "hmo_no" },
            { data: "doctor_id", name: "doctor_id" },
            { data: "billed_by", name: "billed_by" },
            { data: "bed_id", name: "bed_id" },
            { data: "show", name: "show" }
        ];
        $('#other_admissions_list').DataTable(cfg5);
    }
});
