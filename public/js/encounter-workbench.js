/**
 * encounter-workbench.js
 * Encounter Intelligence Workbench — standalone front-end module.
 * Reads configuration from window.ENCOUNTER_WORKBENCH_CONFIG (injected by _scripts.blade.php).
 *
 * Modules:
 *   EWB.Config       — configuration access
 *   EWB.Filters      — filter state management
 *   EWB.KpiStrip     — top KPI bar
 *   EWB.ListTab      — encounter list DataTable
 *   EWB.KpiTab       — overview charts
 *   EWB.ClinicTab    — clinic analytics
 *   EWB.DoctorTab    — doctor productivity
 *   EWB.RevenueTab   — revenue & billing
 *   EWB.PatientsTab  — patient insights
 *   EWB.Modal        — encounter detail modal
 *   EWB.Charts       — Chart.js helper factory
 *   EWB.Init         — bootstrap
 */

/* global $, DataTable, Chart */
'use strict';

var EWB = (function () {

    // ─── Config ───────────────────────────────────────────────────────────────
    var Config = (function () {
        var cfg = window.ENCOUNTER_WORKBENCH_CONFIG || {};
        return {
            route: function (name) { return cfg.routes[name] || ''; },
            can: function (key) { return !!(cfg.capabilities || {})[key]; },
            csrf: function () { return cfg.csrfToken || ''; },
        };
    })();

    // ─── Filters ──────────────────────────────────────────────────────────────
    var Filters = (function () {
        var _state = {};
        var _callbacks = [];

        var QUICK_RANGES = {
            today: function () {
                var d = new Date(); var s = fmt(d);
                return [s, s];
            },
            yesterday: function () {
                var d = new Date(); d.setDate(d.getDate() - 1); var s = fmt(d);
                return [s, s];
            },
            week: function () {
                var now = new Date();
                var day = now.getDay() || 7;
                var mon = new Date(now); mon.setDate(now.getDate() - day + 1);
                return [fmt(mon), fmt(now)];
            },
            month: function () {
                var now = new Date();
                var start = new Date(now.getFullYear(), now.getMonth(), 1);
                return [fmt(start), fmt(now)];
            },
            last_month: function () {
                var now = new Date();
                var start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                var end   = new Date(now.getFullYear(), now.getMonth(), 0);
                return [fmt(start), fmt(end)];
            },
            quarter: function () {
                var now = new Date();
                var qStart = new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 1);
                return [fmt(qStart), fmt(now)];
            },
            year: function () {
                var now = new Date();
                var start = new Date(now.getFullYear(), 0, 1);
                return [fmt(start), fmt(now)];
            },
        };

        function fmt(d) {
            return d.toISOString().slice(0, 10);
        }

        function collect() {
            _state = {};
            var df = $('#ewb-f-date-from').val();
            var dt = $('#ewb-f-date-to').val();
            if (df) _state.start_date = df;
            if (dt) _state.end_date   = dt;

            var clinic = $('#ewb-f-clinic').val();
            var doctor = $('#ewb-f-doctor').val();
            var hmo    = $('#ewb-f-hmo').val();
            var status = $('#ewb-f-status').val();

            if (clinic) _state.clinic_id = clinic;
            if (doctor) _state.doctor_id = doctor;
            if (hmo)    _state.hmo_id    = hmo;
            if (status) _state.status    = status;

            // Tag checkboxes
            $('.ewb-filter-tag').each(function () {
                if ($(this).is(':checked')) {
                    _state[$(this).data('key')] = 1;
                }
            });
        }

        function clear() {
            // Reset date to this month
            var ranges = QUICK_RANGES.month();
            $('#ewb-f-date-from').val(ranges[0]);
            $('#ewb-f-date-to').val(ranges[1]);
            $('#ewb-f-quick-range').val('month');
            $('#ewb-f-clinic').val('');
            $('#ewb-f-doctor').val('');
            $('#ewb-f-hmo').val('');
            $('#ewb-f-status').val('');
            $('.ewb-filter-tag').prop('checked', false);
            collect();
        }

        function get()   { return $.extend({}, _state); }

        function apply() {
            collect();
            _callbacks.forEach(function (cb) { cb(_state); });
        }

        function onChange(cb) { _callbacks.push(cb); }

        function init() {
            // Quick range handler
            $('#ewb-f-quick-range').on('change', function () {
                var val = $(this).val();
                if (val && QUICK_RANGES[val]) {
                    var r = QUICK_RANGES[val]();
                    $('#ewb-f-date-from').val(r[0]);
                    $('#ewb-f-date-to').val(r[1]);
                }
            });
            // Trigger initial month range
            var initRange = QUICK_RANGES.month();
            $('#ewb-f-date-from').val(initRange[0]);
            $('#ewb-f-date-to').val(initRange[1]);

            $('#ewb-btn-apply').on('click', apply);
            $('#ewb-btn-clear').on('click', function () { clear(); apply(); });

            // Also set up export / print
            $('#ewb-btn-export').on('click', function () {
                var params = $.param($.extend({}, get()));
                window.location.href = Config.route('export') + '?' + params;
            });
            $('#ewb-btn-print').on('click', function () {
                var params = $.param($.extend({}, get(), { action: 'print' }));
                window.open(Config.route('export') + '?' + params, '_blank');
            });

            collect();
        }

        return { init: init, get: get, apply: apply, onChange: onChange };
    })();

    // ─── Charts ───────────────────────────────────────────────────────────────
    var Charts = (function () {
        var _instances = {};
        var PALETTE = ['#0d6efd', '#198754', '#17a2b8', '#fd7e14', '#6610f2', '#dc3545', '#20c997', '#e83e8c'];

        function destroy(id) {
            if (_instances[id]) { _instances[id].destroy(); delete _instances[id]; }
        }

        function bar(id, labels, datasets, opts) {
            destroy(id);
            var ctx = document.getElementById(id);
            if (!ctx) return null;
            _instances[id] = new Chart(ctx, {
                type: 'bar',
                data: { labels: labels, datasets: datasets },
                options: $.extend(true, {
                    responsive: true,
                    plugins: { legend: { position: 'top' } },
                    scales: { x: { stacked: false }, y: { beginAtZero: true } },
                }, opts || {}),
            });
            return _instances[id];
        }

        function line(id, labels, datasets, opts) {
            destroy(id);
            var ctx = document.getElementById(id);
            if (!ctx) return null;
            _instances[id] = new Chart(ctx, {
                type: 'line',
                data: { labels: labels, datasets: datasets },
                options: $.extend(true, {
                    responsive: true,
                    plugins: { legend: { position: 'top' } },
                    scales: { y: { beginAtZero: true } },
                }, opts || {}),
            });
            return _instances[id];
        }

        function donut(id, labels, values, colors) {
            destroy(id);
            var ctx = document.getElementById(id);
            if (!ctx) return null;
            _instances[id] = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{ data: values, backgroundColor: colors || PALETTE }],
                },
                options: { responsive: true, plugins: { legend: { position: 'right' } } },
            });
            return _instances[id];
        }

        return { bar: bar, line: line, donut: donut, palette: PALETTE, destroy: destroy };
    })();

    // ─── Global Drilldown Modal Handler ───────────────────────────────────────
    var DrilldownModal = (function () {
        var _dt = null;
        var _filterKey = null;
        var _filterId = null;

        function open(filterKey, filterId, titleName) {
            _filterKey = filterKey;
            _filterId = filterId;

            $('#ewb-list-modal-title-text').text(titleName);
            
            var gl = Filters.get();
            var dStart = gl.start_date || 'Ever';
            var dEnd = gl.end_date || 'Today';
            $('#ewb-list-modal-date-display').text(dStart + ' — ' + dEnd);
            
            $('#ewb-lm-status').val('');
            $('#ewb-lm-hmo').val('');
            $('.ewb-lm-tag').prop('checked', false);

            var modal = new bootstrap.Modal(document.getElementById('ewb-list-modal'));
            modal.show();
            
            _initTable();
        }

        function _initTable() {
            if (_dt) { _dt.destroy(); }
            _dt = $('#ewb-table-list-modal').DataTable({
                serverSide: true,
                processing: true,
                pageLength: 25,
                ajax: {
                    url: Config.route('list'),
                    data: function (d) {
                        $.extend(d, Filters.get());
                        d[_filterKey] = _filterId;
                        if ($('#ewb-lm-status').val()) d.status = $('#ewb-lm-status').val();
                        if ($('#ewb-lm-hmo').val()) d.hmo_id = $('#ewb-lm-hmo').val();
                        $('.ewb-lm-tag:checked').each(function() {
                            d[$(this).val()] = 1;
                        });
                    },
                },
                columns: [
                    { data: 'patient',  name: 'patient' },
                    { data: 'hmo',      name: 'hmo' },
                    { data: 'clinic',   name: 'clinic' },
                    { data: 'date',     name: 'created_at' },
                    { data: 'tags',     name: 'tags', orderable: false, searchable: false },
                    { data: 'status',   name: 'status', orderable: false },
                    { data: 'actions',  name: 'actions', orderable: false, searchable: false },
                ],
                order: [[3, 'desc']],
            });
        }
        
        function init() {
            $('#ewb-lm-status, #ewb-lm-hmo, .ewb-lm-tag').on('change', function() {
                if (_dt) _dt.ajax.reload();
            });
        }

        return { open: open, init: init };
    })();

    // ─── KPI Strip ────────────────────────────────────────────────────────────
    var KpiStrip = (function () {
        function render(kpis) {
            var html = kpis.map(function (k) {
                return '<div class="ewb-kpi-card" style="border-top: 3px solid ' + k.color + ';">' +
                    '<div class="ewb-kpi-icon" style="color:' + k.color + '"><i class="mdi ' + k.icon + '"></i></div>' +
                    '<div class="ewb-kpi-body">' +
                    '<div class="ewb-kpi-value" style="color:' + k.color + '">' + k.value + '</div>' +
                    '<div class="ewb-kpi-label">' + k.label + '</div>' +
                    (k.sub ? '<div class="ewb-kpi-sub">' + k.sub + '</div>' : '') +
                    '</div></div>';
            }).join('');
            $('#ewb-kpi-strip').html(html);
        }

        function load() {
            $('#ewb-kpi-strip').html('<div class="ewb-kpi-loading"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading...</div>');
            $.get(Config.route('kpiStrip'), Filters.get(), function (res) {
                render(res.kpis || []);
            }).fail(function () {
                $('#ewb-kpi-strip').html('<small class="text-danger px-3">KPI load failed</small>');
            });
        }

        return { load: load };
    })();

    // ─── List Tab ─────────────────────────────────────────────────────────────
    var ListTab = (function () {
        var _dt = null;
        var _loaded = false;

        function init() {
            _dt = $('#ewb-table-list').DataTable({
                serverSide: true,
                processing: true,
                dom: 'Bfrtip',
                buttons: ['pageLength', 'copy', 'csv', 'excel', 'pdf'],
                lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
                ajax: {
                    url: Config.route('list'),
                    data: function (d) { $.extend(d, Filters.get()); },
                    dataSrc: function (json) {
                        // Render KPI cards in list tab (if any returned)
                        if (json.kpis) _renderKpis(json.kpis, '#ewb-kpi-strip');
                        return json.data || [];
                    },
                },
                columns: [
                    { 
                        data: null, 
                        orderable: false, 
                        searchable: false, 
                        width: '40px',
                        render: function (data, type, row, meta) {
                            return meta.row + meta.settings._iDisplayStart + 1;
                        }
                    },
                    { data: 'patient',  name: 'patient' },
                    { data: 'hmo',      name: 'hmo' },
                    { data: 'clinic',   name: 'clinic' },
                    { data: 'doctor',   name: 'doctor' },
                    { data: 'date',     name: 'created_at' },
                    { data: 'tags',     name: 'tags', orderable: false, searchable: false },
                    { data: 'status',   name: 'status', orderable: false },
                    { data: 'actions',  name: 'actions', orderable: false, searchable: false },
                ],
                order: [[5, 'desc']],
                language: {
                    processing: '<div class="d-flex align-items-center gap-2"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading encounters...</div>',
                    emptyTable: '<div class="text-center text-muted py-4"><i class="mdi mdi-stethoscope" style="font-size:2rem;"></i><br>No encounters found for the selected filters.</div>',
                },
            });
            _loaded = true;
        }

        function reload() {
            if (!_loaded) { init(); return; }
            _dt.ajax.reload();
        }

        function _renderKpis(kpis, target) {
            // KPI strip is handled by KpiStrip module; skip if already rendered
        }

        return { init: init, reload: reload };
    })();

    // ─── KPI Tab ──────────────────────────────────────────────────────────────
    var KpiTab = (function () {
        var _loaded = false;

        function load() {
            if (_loaded) return;
            _loaded = true;
            _fetch();
        }

        function reload() { _loaded = false; _fetch(); }

        function _fetch() {
            $('#ewb-kpi-overview-cards').html('<div class="col-12 text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>');
            $.get(Config.route('kpi'), Filters.get(), function (res) {
                _renderCards(res.kpis || []);
                _renderCharts(res.charts || {});
            });
        }

        function _renderCards(kpis) {
            var html = kpis.map(function (k) {
                return '<div class="col-md-3 col-sm-6 mb-3">' +
                    '<div class="card border-0 shadow-sm rounded-3 text-center p-3" style="border-top: 3px solid ' + k.color + ' !important;">' +
                    '<div style="font-size:2rem; color:' + k.color + '"><i class="mdi ' + k.icon + '"></i></div>' +
                    '<div style="font-size:1.5rem; font-weight:700; color:' + k.color + '">' + k.value + '</div>' +
                    '<div class="text-muted small">' + k.label + '</div>' +
                    '</div></div>';
            }).join('');
            $('#ewb-kpi-overview-cards').html(html);
        }

        function _renderCharts(charts) {
            // Daily trend
            if (charts.daily_trend && charts.daily_trend.length) {
                var labels = charts.daily_trend.map(function (d) { return d.day; });
                Charts.bar('ewb-chart-daily', labels, [
                    { label: 'Completed', data: charts.daily_trend.map(function (d) { return d.completed; }), backgroundColor: '#198754' },
                    { label: 'Active', data: charts.daily_trend.map(function (d) { return d.active; }), backgroundColor: '#17a2b8' },
                ], { scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } } });
            }

            // Payer split donut
            if (charts.payer_split) {
                Charts.donut('ewb-chart-payer',
                    charts.payer_split.map(function (p) { return p.label; }),
                    charts.payer_split.map(function (p) { return p.value; }),
                    ['#6610f2', '#198754']
                );
            }

            // Clinic bar
            if (charts.clinic_dist && charts.clinic_dist.length) {
                Charts.bar('ewb-chart-clinic-bar',
                    charts.clinic_dist.map(function (c) { return c.clinic_name; }),
                    [{ label: 'Encounters', data: charts.clinic_dist.map(function (c) { return c.total; }), backgroundColor: Charts.palette[0] }]
                );
            }
        }

        return { load: load, reload: reload };
    })();

    // ─── Clinic Tab ───────────────────────────────────────────────────────────
    var ClinicTab = (function () {
        var _dt = null;
        var _loaded = false;

        function load() {
            if (_loaded) return;
            _loaded = true;
            _fetch();
        }

        function reload() { _loaded = false; if (_dt) { _dt.clear().draw(); } _fetch(); }

        function _fetch() {
            $.get(Config.route('clinic'), Filters.get(), function (res) {
                _renderKpis(res.kpis || [], '#ewb-clinic-kpis');
                _renderTable(res.data || []);
                _renderChart(res.chart_data || {});
            });
        }

        function _renderKpis(kpis, target) {
            var html = kpis.map(function (k) {
                return '<div class="col-md-2 col-sm-4 mb-2">' +
                    '<div class="card-modern p-2 text-center" style="border-top:3px solid ' + k.color + '">' +
                    '<div style="font-size:1.4rem;color:' + k.color + '"><i class="mdi ' + k.icon + '"></i></div>' +
                    '<div class="font-weight-bold" style="color:' + k.color + '">' + k.value + '</div>' +
                    '<div class="small text-muted">' + k.label + '</div></div></div>';
            }).join('');
            $(target).html(html);
        }

        function _renderTable(rows) {
            if (_dt) { _dt.destroy(); }
            _dt = $('#ewb-table-clinic').DataTable({
                data: rows,
                dom: 'Bfrtip',
                buttons: ['pageLength', 'csv', 'excel'],
                order: [[1, 'desc']],
                columnDefs: [{ targets: '_all', className: 'text-center' }, { targets: 0, className: 'text-left' }],
                language: { emptyTable: '<div class="text-center text-muted py-3">No clinic data</div>' },
                columns: [
                    { data: 'clinic' }, { data: 'total_encounters' }, { data: 'unique_patients' },
                    { data: 'return_patients' }, { data: 'completed' }, { data: 'completion_rate' },
                    { data: 'avg_duration' }, { data: 'lab_requests' }, { data: 'imaging_requests' },
                    { data: 'prescriptions' }, { data: 'referrals' }, { data: 'admissions' },
                    { data: 'admission_rate' },
                    { 
                        data: null, 
                        orderable: false,
                        render: function (data, type, row) {
                            return '<button class="btn btn-sm btn-outline-primary ewb-drill-clinic" data-clinic-id="'+row.clinic_id+'" data-clinic-name="'+row.clinic.replace(/<[^>]+>/g, '')+'">Details</button>';
                        }
                    }
                ],
            });

            $('#ewb-table-clinic tbody').off('click', '.ewb-drill-clinic').on('click', '.ewb-drill-clinic', function () {
                var cid = $(this).data('clinic-id');
                var cname = $(this).data('clinic-name');
                DrilldownModal.open('clinic_id', cid, cname);
            });
        }

        function _renderChart(chartData) {
            if (!chartData.labels || !chartData.labels.length) return;
            Charts.bar('ewb-chart-clinic-analytics', chartData.labels, [
                { label: 'Encounters', data: chartData.total, backgroundColor: '#0d6efd' },
                { label: 'Admissions', data: chartData.admissions, backgroundColor: '#dc3545' },
            ]);
        }

        return { load: load, reload: reload };
    })();

    // ─── Doctor Tab ───────────────────────────────────────────────────────────
    var DoctorTab = (function () {
        var _dt = null;
        var _loaded = false;

        function load() {
            if (_loaded) return;
            _loaded = true;
            _fetch();
        }

        function reload() { _loaded = false; if (_dt) { _dt.destroy(); _dt = null; } _fetch(); }

        function _fetch() {
            $.get(Config.route('doctor'), Filters.get(), function (res) {
                _renderKpis(res.kpis || []);
                _renderTable(res.data || []);
                _renderCharts(res.chart_data || {});
            });
        }

        function _renderKpis(kpis) {
            var html = kpis.map(function (k) {
                return '<div class="col-md-3 col-sm-6 mb-2">' +
                    '<div class="card-modern p-2 text-center" style="border-top:3px solid ' + k.color + '">' +
                    '<i class="mdi ' + k.icon + '" style="font-size:1.4rem;color:' + k.color + '"></i>' +
                    '<div class="font-weight-bold" style="color:' + k.color + '">' + k.value + '</div>' +
                    '<div class="small text-muted">' + k.label + '</div></div></div>';
            }).join('');
            $('#ewb-doctor-kpis').html(html);
        }

        function _renderTable(rows) {
            if (_dt) { _dt.destroy(); }
            _dt = $('#ewb-table-doctor').DataTable({
                data: rows,
                dom: 'Bfrtip',
                buttons: ['pageLength', 'csv', 'excel'],
                order: [[2, 'desc']],
                columns: [
                    { data: 'doctor' }, { data: 'clinic' }, { data: 'total' },
                    { data: 'unique_patients' }, { data: 'avg_duration' }, { data: 'completion_rate' },
                    { data: 'lab_requests' }, { data: 'imaging' }, { data: 'prescriptions' },
                    { data: 'referrals' }, { data: 'procedures' }, { data: 'admissions' },
                    { data: 'drill_btn', orderable: false },
                ],
            });

            // Drill-down click
            $('#ewb-table-doctor tbody').off('click', '.ewb-drill-doctor').on('click', '.ewb-drill-doctor', function () {
                var docId   = $(this).data('doctor-id');
                var docName = $(this).data('doctor-name');
                DrilldownModal.open('doctor_id', docId, docName);
            });
        }

        function _renderCharts(chartData) {
            if (!chartData.labels || !chartData.labels.length) return;
            Charts.bar('ewb-chart-doctor-bar', chartData.labels, [
                { label: 'Total', data: chartData.total, backgroundColor: '#0d6efd' },
                { label: 'Completed', data: chartData.completed, backgroundColor: '#198754' },
            ]);
            // Donut = completed vs active (first doctor only if > 1)
            var t = chartData.total[0] || 0, c = chartData.completed[0] || 0;
            Charts.donut('ewb-chart-doctor-donut', ['Completed', 'Active'], [c, t - c], ['#198754', '#17a2b8']);
        }

        // We moved drilldown to the global modal, so this is unused, but kept for signature matching if needed.
        function _loadDrilldown(docId, docName) {
            DrilldownModal.open('doctor_id', docId, docName);
        }

        function init() {
            // Nothing specific
        }

        return { load: load, reload: reload, init: init };
    })();

    // ─── Revenue Tab ──────────────────────────────────────────────────────────
    var RevenueTab = (function () {
        var _dt = null;
        var _loaded = false;
        var _currentGroup = 'doctor';

        function load() {
            if (_loaded) return;
            _loaded = true;
            _fetch();
        }

        function reload() { _loaded = false; if (_dt) { _dt.destroy(); _dt = null; } _fetch(); }

        function _fetch() {
            var params = $.extend({}, Filters.get(), { group_by: _currentGroup });
            $.get(Config.route('revenue'), params, function (res) {
                _renderKpis(res.kpis || []);
                _renderTable(res.data || []);
                _renderCharts(res.chart_data || {});
            });
        }

        function _renderKpis(kpis) {
            var html = kpis.map(function (k) {
                return '<div class="col mb-2"><div class="card-modern p-2 text-center" style="border-top:3px solid ' + k.color + '">' +
                    '<i class="mdi ' + k.icon + '" style="font-size:1.2rem;color:' + k.color + '"></i>' +
                    '<div class="font-weight-bold" style="color:' + k.color + ';font-size:1.1rem">' + k.value + '</div>' +
                    '<div class="small text-muted">' + k.label + '</div></div></div>';
            }).join('');
            $('#ewb-revenue-kpis').html('<div class="row">' + html + '</div>');
        }

        function _renderTable(rows) {
            if (_dt) { _dt.destroy(); }
            var cols = [
                { data: 'label' }, { data: 'unique_patients' }, { data: 'encounters' }, { data: 'line_items' },
                { data: 'total_billed' }, { data: 'consult_billed' }, { data: 'consult_payable' }, { data: 'consult_claims' },
                { data: 'total_payable' }, { data: 'hmo_claims' },
                { data: 'hmo_share' }, { data: 'avg_per_enc' },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        if (!row.group_id) return '';
                        var typeKey = _currentGroup === 'doctor' ? 'doctor_id' : (_currentGroup === 'clinic' ? 'clinic_id' : 'hmo_id');
                        if (_currentGroup === 'method') return ''; // we don't filter by payment method yet
                        return '<button class="btn btn-sm btn-outline-primary ewb-drill-revenue" data-type="'+typeKey+'" data-id="'+row.group_id+'" data-name="'+row.label.replace(/<[^>]+>/g, '')+'">Details</button>';
                    }
                }
            ];
            _dt = $('#ewb-table-revenue').DataTable({
                data: rows,
                dom: 'Bfrtip',
                buttons: ['pageLength', 'csv', 'excel'],
                order: [[3, 'desc']],
                columns: cols,
            });
            
            $('#ewb-table-revenue tbody').off('click', '.ewb-drill-revenue').on('click', '.ewb-drill-revenue', function () {
                var typeKey = $(this).data('type');
                var id = $(this).data('id');
                var name = $(this).data('name');
                DrilldownModal.open(typeKey, id, name);
            });
        }

        function _renderCharts(chartData) {
            if (!chartData.labels || !chartData.labels.length) {
                Charts.destroy('ewb-chart-revenue-bar');
                Charts.destroy('ewb-chart-revenue-donut');
                return;
            }
            Charts.bar('ewb-chart-revenue-bar', chartData.labels, [
                { label: 'Total Billed', data: chartData.billed, backgroundColor: '#0d6efd' },
                { label: 'HMO Claims', data: chartData.hmo, backgroundColor: '#6610f2' },
            ]);
            var totalBilled = chartData.billed.reduce(function (a, b) { return a + b; }, 0);
            var totalHmo    = chartData.hmo.reduce(function (a, b) { return a + b; }, 0);
            Charts.donut('ewb-chart-revenue-donut', ['HMO Claims', 'Private'], [totalHmo, totalBilled - totalHmo], ['#6610f2', '#198754']);
        }

        function init() {
            $('#ewb-revenue-group-btns').on('click', '.btn', function () {
                $(this).siblings().removeClass('active');
                $(this).addClass('active');
                _currentGroup = $(this).data('group');
                _loaded = false;
                _fetch();
            });
        }

        return { load: load, reload: reload, init: init };
    })();

    // ─── Patients Tab ─────────────────────────────────────────────────────────
    var PatientsTab = (function () {
        var _loaded = false;

        function load() {
            if (_loaded) return;
            _loaded = true;
            _fetchOverview();
            _fetchReturnRates();
            _fetchHighFrequency();
            _fetchReferralOutcomes();
        }

        function reload() {
            _loaded = false;
            _fetchOverview();
            _fetchReturnRates();
            _fetchHighFrequency();
            _fetchReferralOutcomes();
        }

        function _fetchOverview() {
            $.get(Config.route('patients'), $.extend({}, Filters.get(), { section: 'overview' }), function (res) {
                _renderKpis(res.kpis || []);
                _renderTrendCharts(res.charts || {});
            });
        }

        function _renderKpis(kpis) {
            var html = kpis.map(function (k) {
                return '<div class="col-md-2 col-sm-4 mb-2"><div class="card-modern p-2 text-center" style="border-top:3px solid ' + k.color + '">' +
                    '<i class="mdi ' + k.icon + '" style="font-size:1.4rem;color:' + k.color + '"></i>' +
                    '<div class="font-weight-bold" style="color:' + k.color + '">' + k.value + '</div>' +
                    '<div class="small text-muted">' + k.label + '</div></div></div>';
            }).join('');
            $('#ewb-patient-kpis').html(html);
        }

        function _renderTrendCharts(charts) {
            if (charts.weekly_trend && charts.weekly_trend.length) {
                Charts.line('ewb-chart-new-returning', charts.weekly_trend.map(function (w) { return w.week; }), [
                    { label: 'New', data: charts.weekly_trend.map(function (w) { return w.new; }), borderColor: '#198754', backgroundColor: 'rgba(25,135,84,0.1)', fill: true },
                    { label: 'Returning', data: charts.weekly_trend.map(function (w) { return w.returning; }), borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.1)', fill: true },
                ]);
            } else {
                Charts.destroy('ewb-chart-new-returning');
            }
            if (charts.new_vs_returning && charts.new_vs_returning.length) {
                Charts.donut('ewb-chart-patient-donut',
                    charts.new_vs_returning.map(function (p) { return p.label; }),
                    charts.new_vs_returning.map(function (p) { return p.value; }),
                    ['#198754', '#0d6efd']
                );
            } else {
                Charts.destroy('ewb-chart-patient-donut');
            }
        }

        function _fetchReturnRates() {
            $.get(Config.route('patients'), $.extend({}, Filters.get(), { section: 'return_rate' }), function (res) {
                var html = (res.data || []).map(function (r) {
                    return '<div class="d-flex justify-content-between align-items-center p-2 border-bottom">' +
                        '<span class="small font-weight-bold">' + r.window + '</span>' +
                        '<span class="badge bg-primary">' + r.patients + ' patients</span>' +
                        '</div>';
                }).join('') || '<p class="text-muted text-center small py-2">No return data</p>';
                $('#ewb-return-rate-body').html(html);
            });
        }

        function _fetchHighFrequency() {
            var minEnc = parseInt($('#ewb-min-enc').val()) || 3;
            $.get(Config.route('patients'), $.extend({}, Filters.get(), { section: 'high_frequency', min_encounters: minEnc }), function (res) {
                var rows = (res.data || []).map(function (r) {
                    return '<tr><td>' + r.patient + '</td><td>' + r.hmo + '</td><td>' + r.enc_count + '</td><td>' + r.first_visit + '</td><td>' + r.last_visit + '</td></tr>';
                }).join('') || '<tr><td colspan="5" class="text-center text-muted">No high-frequency patients</td></tr>';
                $('#ewb-hf-tbody').html(rows);
            });
        }

        function _fetchReferralOutcomes() {
            $.get(Config.route('patients'), $.extend({}, Filters.get(), { section: 'referral_outcomes' }), function (res) {
                var rows = (res.data || []).map(function (r) {
                    return '<tr><td>' + r.status + '</td><td>' + r.type + '</td><td>' + r.total + '</td></tr>';
                }).join('') || '<tr><td colspan="3" class="text-center text-muted">No referral data</td></tr>';
                $('#ewb-referral-tbody').html(rows);

                if (res.chart_data && res.chart_data.labels.length) {
                    Charts.donut('ewb-chart-referrals', res.chart_data.labels, res.chart_data.values);
                }
            });
        }

        function init() {
            $('#ewb-btn-hf-reload').on('click', function () { _fetchHighFrequency(); });
        }

        return { load: load, reload: reload, init: init };
    })();

    // ─── Modal ────────────────────────────────────────────────────────────────
    var Modal = (function () {
        function open(id) {
            var url = Config.route('details').replace('__ID__', id);
            $('#ewb-detail-modal-body').html('<div class="text-center py-5"><div class="spinner-border text-primary" role="status"></div></div>');
            $('#ewb-detail-modal-title').html('<i class="mdi mdi-stethoscope me-2 text-primary"></i> Encounter Details');
            $('#ewb-detail-modal').modal('show');

            $.get(url, function (res) {
                $('#ewb-detail-modal-body').html(res.html || '');
                $('#ewb-detail-modal-title').html(res.title || 'Encounter Details');
            }).fail(function () {
                $('#ewb-detail-modal-body').html('<div class="alert alert-danger m-4">Failed to load encounter details.</div>');
            });
        }

        function init() {
            $(document).on('click', '.ewb-view-encounter', function () {
                open($(this).data('id'));
            });
        }

        return { init: init, open: open };
    })();

    // ─── Init ─────────────────────────────────────────────────────────────────
    var Init = (function () {
        function run() {
            // Initialize all sub-modules
            Filters.init();
            Modal.init();
            DrilldownModal.init();
            DoctorTab.init();
            RevenueTab.init();
            PatientsTab.init();

            // Initial data load
            ListTab.init();
            KpiStrip.load();

            // Tab lazy loading
            $('#ewb-tabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                var tab = $(e.target).data('tab');
                if (tab === 'kpi')      KpiTab.load();
                if (tab === 'clinic')   ClinicTab.load();
                if (tab === 'doctor')   DoctorTab.load();
                if (tab === 'revenue')  RevenueTab.load();
                if (tab === 'patients') PatientsTab.load();
            });

            // Filter apply → reload active tab + KPI strip
            Filters.onChange(function () {
                KpiStrip.load();
                var activeTab = $('#ewb-tabs button.active').data('tab');
                if (activeTab === 'list')     ListTab.reload();
                if (activeTab === 'kpi')      KpiTab.reload();
                if (activeTab === 'clinic')   ClinicTab.reload();
                if (activeTab === 'doctor')   DoctorTab.reload();
                if (activeTab === 'revenue')  RevenueTab.reload();
                if (activeTab === 'patients') PatientsTab.reload();
            });
        }

        return { run: run };
    })();

    // Public API
    return {
        init: Init.run,
        Filters: Filters,
        Modal: Modal,
    };

})();

// Bootstrap on DOM ready
$(function () { EWB.init(); });
