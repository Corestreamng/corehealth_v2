/**
 * Transactions Console, "What I did": the stages a person can work at in their workbench (book, bill,
 * take a sample, enter a result, approve, dispense, return...) with MY counts for the period, a
 * "where is it now" lens (needs my attention / waiting on others / done) and the items behind them.
 * Server: ProcessedTransactionsController@activity (the same feed the staff app uses). The cash views
 * (paid through me, manage) stay in billing-my-transactions.js.
 */
(function (window, $) {
    'use strict';

    var $pane = $('#pane-my-activity');
    if (!$pane.length) return;

    var URL = '/billing-workbench/my-activity';
    var wb = $pane.data('workbench') || 'reception';
    var stage = 'all';
    var state = '';
    var dept = '';
    var category = '';
    var loaded = false;
    var seq = 0;

    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
    function naira(v) { return '₦' + (parseFloat(v || 0)).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function pad(n) { return String(n).padStart(2, '0'); }
    function local(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }

    function today() {
        var d = new Date();
        $('#act-from').val(local(new Date(d.getFullYear(), d.getMonth(), d.getDate(), 0, 0)));
        $('#act-to').val(local(new Date(d.getFullYear(), d.getMonth(), d.getDate(), 23, 59)));
    }

    var TONES = { primary: 'primary', info: 'info', success: 'success', warning: 'warning', purple: 'purple' };
    var STATE = {
        done: { cls: 'bg-success', icon: 'mdi-check-circle-outline' },
        waiting: { cls: 'bg-warning text-dark', icon: 'mdi-timer-sand' },
        attention: { cls: 'bg-danger', icon: 'mdi-alert-circle-outline' }
    };

    function load() {
        var mine = ++seq;
        $('#act-tbody').html('<tr><td colspan="6" class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span> Loading…</td></tr>');
        $.ajax({
            url: URL, type: 'GET',
            data: {
                workbench: wb, stage: stage, state: state,
                from: $('#act-from').val(), to: $('#act-to').val(),
                department_id: dept || $('#act-department').val(), item_type: $('#act-item-type').val(),
                category: category || $('#act-category').val()
            },
            success: function (d) { if (mine === seq) render(d); },
            error: function (xhr) {
                $('#act-tbody').html('<tr><td colspan="6" class="text-center text-danger py-4">Could not load: ' + esc((xhr.responseJSON && xhr.responseJSON.message) || xhr.statusText) + '</td></tr>');
            }
        });
    }

    function fillSelects(d) {
        if (loaded) return;
        var $d = $('#act-department');
        (d.all_departments || []).forEach(function (x) { $d.append('<option value="' + esc(x.id) + '">' + esc(x.name) + '</option>'); });
        var $c = $('#act-category');
        var cats = d.all_categories || {};
        var add = function (label, list) {
            if (!list || !list.length) return;
            var g = '<optgroup label="' + label + '">';
            list.forEach(function (n) { g += '<option value="' + esc(n) + '">' + esc(n) + '</option>'; });
            $c.append(g + '</optgroup>');
        };
        add('Services', cats.services);
        add('Products', cats.products);
        loaded = true;
    }

    function renderPipeline(d) {
        var html = '';
        (d.pipeline || []).forEach(function (p, i) {
            var on = p.key === stage;
            var tone = TONES[p.tone] || 'secondary';
            if (i === 1) html += '<div class="d-flex align-items-center text-muted px-1"><i class="mdi mdi-chevron-right"></i></div>';
            else if (i > 1) html += '<div class="d-flex align-items-center text-muted px-1"><i class="mdi mdi-arrow-right-thin"></i></div>';
            html += '<button type="button" class="btn act-stage text-start ' + (on ? 'btn-' + tone + ' text-white' : 'btn-outline-' + tone) + '" data-stage="' + esc(p.key) + '" title="' + esc(p.hint) + '" style="min-width:128px;">' +
                '<div class="small fw-semibold text-nowrap"><i class="mdi ' + esc(p.icon) + '"></i> ' + esc(p.label) + '</div>' +
                '<div class="fs-4 fw-bold lh-1">' + p.count + '</div>' +
                '<div class="small text-nowrap">' +
                (p.attention ? '<span class="badge bg-danger text-white">' + p.attention + ' need me</span> ' : '') +
                (p.waiting ? '<span class="badge bg-warning text-dark">' + p.waiting + ' waiting</span>' : (p.attention ? '' : '<span class="opacity-75">all done</span>')) +
                '</div></button>';
        });
        $('#act-pipeline').html(html || '<span class="text-muted">Nothing recorded for this period.</span>');
        var cur = (d.pipeline || []).filter(function (p) { return p.key === stage; })[0];
        if (cur) {
            $('#act-lens-attention').text(cur.attention);
            $('#act-lens-waiting').text(cur.waiting);
            $('#act-lens-done').text(Math.max(0, cur.count - cur.waiting - cur.attention));
            $('#act-stage-hint').text(cur.hint || '');
        }
    }

    function kpi(title, value, sub, color) {
        return '<div class="col-6 col-md-3 col-lg-2"><div class="card h-100"><div class="card-body p-2">' +
            '<div class="text-muted font-10 text-uppercase">' + esc(title) + '</div>' +
            '<div class="fw-bold font-15 text-' + color + '">' + value + '</div>' + (sub ? '<div class="text-muted font-10">' + sub + '</div>' : '') +
            '</div></div></div>';
    }

    function pills(title, icon, color, list, selected, kind, idKey) {
        var body = '';
        (list || []).forEach(function (x) {
            var key = idKey ? x.id : x.name;
            var on = String(selected) === String(key);
            body += '<button type="button" class="btn btn-xs ' + (on ? 'btn-' + color + ' text-white' : 'btn-outline-' + color) + ' act-pill d-inline-flex align-items-center gap-1 font-11 py-0 px-2 me-1 mb-1" data-kind="' + kind + '" data-key="' + esc(key) + '">' +
                esc(x.name) + ' <span class="badge ' + (on ? 'bg-white text-' + color : 'bg-' + color + ' text-white') + ' rounded-pill">' + x.count + '</span> <span class="fw-bold">' + naira(x.amount) + '</span></button>';
        });
        return '<div class="card bg-light border mb-1"><div class="card-body p-2"><div class="fw-semibold font-12 mb-1"><i class="mdi ' + icon + ' text-' + color + '"></i> ' + title + '</div>' +
            '<div style="max-height:80px;overflow-y:auto;">' + (body || '<span class="text-muted font-11">None in this period</span>') + '</div></div></div>';
    }

    function renderKpis(d) {
        var s = d.summary || {};
        $('#act-kpis').html(
            kpi('Items', (s.count || 0).toLocaleString(), (s.done || 0) + ' done', 'primary') +
            kpi('Value', naira(s.value), 'patients ' + naira(s.patient_pays), 'success') +
            kpi('HMO claims', naira(s.claims), '', 'info') +
            kpi('Waiting on others', (s.waiting || 0).toLocaleString(), 'not finished by someone else', 'warning') +
            kpi('Need my attention', (s.attention || 0).toLocaleString(), 'e.g. a rejected result', 'danger') +
            kpi('Services / Products', naira(s.service_total) + ' / ' + naira(s.product_total), '', 'secondary')
        );
        $('#act-rollups').html(
            pills('Departments', 'mdi-office-building', 'primary', s.department_kpis, dept, 'dept', true) +
            pills('Services', 'mdi-medical-bag', 'info', s.service_category_kpis, category, 'cat', false) +
            pills('Products', 'mdi-pill', 'success', s.product_category_kpis, category, 'cat', false)
        );
    }

    function renderRows(d) {
        var rows = d.items || [];
        if (!rows.length) {
            $('#act-tbody').html('<tr><td colspan="6" class="text-center text-muted py-5"><i class="mdi mdi-timeline-check-outline" style="font-size:2rem"></i><p class="mb-0">Nothing here for these filters.</p></td></tr>');
        } else {
            var html = '';
            rows.forEach(function (r) {
                var st = STATE[r.state] || STATE.done;
                var acts = (r.my_actions || []).map(function (a) { return '<span class="badge bg-light text-dark border me-1" title="' + esc(a.at || '') + '">' + esc(a.label) + '</span>'; }).join('');
                html += '<tr>' +
                    '<td class="text-nowrap font-11">' + esc((r.at || '').replace('T', ' ').substring(0, 16)) + '</td>' +
                    '<td><div class="fw-semibold">' + esc(r.patient_name) + '</div><div class="text-muted font-11">' + esc(r.file_no) + '</div></td>' +
                    '<td><div>' + esc(r.item_name) + (r.qty > 1 ? ' <span class="text-muted">× ' + r.qty + '</span>' : '') + '</div><div class="text-muted font-11">' + esc(r.type) + ' · ' + esc(r.category) + (r.department_name ? ' · ' + esc(r.department_name) : '') + '</div></td>' +
                    '<td>' + acts + '</td>' +
                    '<td><span class="badge ' + st.cls + '"><i class="mdi ' + st.icon + '"></i> ' + esc(r.state_label) + '</span>' + (r.payment_reference ? '<div class="text-muted font-10">' + esc(r.payment_reference) + '</div>' : '') + '</td>' +
                    '<td class="text-end fw-bold">' + naira(r.amount) + (r.claims_amount > 0 ? '<div class="text-success font-10 fw-normal">HMO ' + naira(r.claims_amount) + '</div>' : '') + '</td></tr>';
            });
            $('#act-tbody').html(html);
        }
        var $t = $('#act-truncated');
        if (d.is_truncated) {
            $t.html('<i class="mdi mdi-information-outline"></i> Showing the latest ' + rows.length + ' of ' + (d.total_count || rows.length).toLocaleString() + '. The stage counts above cover everything; narrow the period or filters to see every row.').show();
        } else { $t.hide(); }
    }

    function render(d) {
        fillSelects(d);
        renderPipeline(d);
        renderKpis(d);
        renderRows(d);
    }

    function ensure() {
        if (!$('#act-from').val()) today();
        load();
    }

    $(document).on('click', '#act-load', function (e) { e.preventDefault(); load(); });
    $(document).on('click', '#act-today', function (e) { e.preventDefault(); today(); load(); });
    $(document).on('click', '.act-stage', function () { stage = $(this).data('stage'); load(); });
    $(document).on('click', '.act-lens', function () {
        $('.act-lens').removeClass('active');
        $(this).addClass('active');
        state = $(this).data('state') || '';
        load();
    });
    $(document).on('click', '.act-pill', function () {
        var kind = $(this).data('kind'), key = String($(this).data('key'));
        if (kind === 'dept') { dept = (dept === key ? '' : key); $('#act-department').val(dept); }
        else { category = (category === key ? '' : key); $('#act-category').val(category); }
        load();
    });
    $(document).on('change', '#act-department', function () { dept = ''; });
    $(document).on('change', '#act-category', function () { category = ''; });
    $(document).on('click', '#tab-my-activity', function () { setTimeout(ensure, 0); });
    $(document).on('shown.bs.modal shown.modal', '#myTransactionsModal', function () {
        if ($('#pane-my-activity').hasClass('active')) ensure();
    });

    window.CoreHealth = window.CoreHealth || {};
    window.CoreHealth.MyActivity = { reload: load };
})(window, jQuery);
